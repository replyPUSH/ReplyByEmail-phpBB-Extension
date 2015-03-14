<?php
/**
*
* @package phpBB Extension - Reply By Email
* @copyright (c) 2015 Paul Thomas
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/
namespace replyPUSH\replybyemail\helper;

if (!defined('IN_PHPBB'))
{
	exit;
}
// old school required
if (!class_exists('messenger'))
{
	global $phpbb_root_path, $phpEx;
	require $phpbb_root_path . 'includes/functions_messenger.' . $phpEx;
}

/**
* More suitable messenger
*/
class messenger extends \messenger
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\user */
	protected $user;

	/** @var string phpBB root path */
	protected $phpbb_root_path;

	/** @var string phpEx */
	protected $phpEx;

	/** @var \phpbb\extension\manager */
	protected $phpbb_extension_manager;

	/**
	* Constructor
	*
	* @param \phpbb\config\config                 $config                       Config object
	* @param \phpbb\user                          $user                         User object
	* @param string                               $phpbb_root_path              phpBB root path
	* @param string                               $php_ext                      phpEx
	* @param \phpbb\extension\manager             $phpbb_extension_manager      phpBB Extension Manager
	* @access public
	*/
	public function __construct(\phpbb\config\config $config, \phpbb\user $user, $phpbb_root_path, $phpEx, \phpbb\extension\manager $phpbb_extension_manager)
	{
		$this->config = $config;
		$this->user = $user;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->phpEx = $phpEx;
		$this->phpbb_extension_manager = $phpbb_extension_manager;
		parent::messenger();
	}

	/**
	* Set email template to use
	*
	* @param string $template_file
	* @param string $template_lang
	* @param string $template_path
	* @param string $name_space
	*
	* @return bool
	*/
	function template($template_file, $template_lang = '', $template_path = '', $name_space = 'email')
	{

		$this->setup_template();

		if (!trim($template_file))
		{
			trigger_error('No template file for emailing set.', E_USER_ERROR);
		}

		if (!trim($template_lang))
		{
			// fall back to board default language if the user's language is
			// missing $template_file.  If this does not exist either,
			// $this->template->set_filenames will do a trigger_error
			$template_lang = basename($this->config['default_lang']);
		}

		if ($template_path)
		{
			$template_paths = array(
				$template_path,
			);

		}
		else
		{
			$template_path = (!empty($this->user->lang_path)) ? $this->user->lang_path : $this->phpbb_root_path . 'language/';
			$template_path .= $template_lang . '/'. $name_space;

			$template_path_ext = $this->phpbb_root_path . 'ext/replyPUSH/replybyemail/language/';
			$template_path_ext .= $template_lang . '/'. $name_space;

			$template_paths = array();

			if (file_exists($template_path))
			{
				$template_paths[] = $template_path;
			}

			$template_paths[] = $template_path_ext;

			// we can only specify default language fallback when the path is not a custom one for which we
			// do not know the default language alternative
			if ($template_lang !== basename($this->config['default_lang']))
			{
				$fallback_template_path = (!empty($this->user->lang_path)) ? $this->user->lang_path : $this->phpbb_root_path . 'language/';
				$fallback_template_path .= basename($this->config['default_lang']) . '/'. $name_space;

				$fallback_template_path_ext = $this->phpbb_root_path . 'ext/replyPUSH/replybyemail/language/';
				$fallback_template_path_ext .= basename($this->config['default_lang']) . '/'. $name_space;

				if (file_exists($fallback_template_path))
				{
					$template_paths[] = $fallback_template_path;
				}

				$template_paths[] = $fallback_template_path_ext;
			}
		}

		$this->set_template_paths(array(
			array(
				'name' => $template_lang . '_email',
				'ext_path'=> $this->phpbb_root_path . 'language/' . $template_lang . '/'. $name_space,
			),
		), $template_paths);

		$this->template->set_filenames(array(
			'body'  => $template_file . '.txt',
		));

		return true;
	}

	/**
	* set up extra mail headers
	*
	* @param strng $name
	* @param strng $value
	*/
	public function header($name, $value)
	{
		$name   = trim($name);
		$value  = trim($value);
		$this->extra_headers[$name] = $value;
	}

	/**
	* Adds X-AntiAbuse headers
	*
	* @param array  $config        Configuration array
	* @param user   $user          A user object
	*
	* @return null
	*/
	public function anti_abuse_headers($config, $user)
	{
		$this->headers('X-AntiAbuse', 'Board servername - ' . mail_encode($config['server_name']));
		$this->headers('X-AntiAbuse', 'User_id - ' . $user->data['user_id']);
		$this->headers('X-AntiAbuse', 'Username - ' . mail_encode($user->data['username']));
		$this->headers('X-AntiAbuse', 'User IP - ' . $user->ip);
	}

	/**
	* Return email header
	*
	* @param  string $to
	* @param  string $cc
	* @param  string $bcc
	* @return array[int]string
	*/
	public function build_header($to, $cc, $bcc)
	{

		$headers = array(
			'Reply-To'                  => $this->replyto,
			'Return-Path'               => '<' . $this->config['board_email'] . '>',
			'Sender'                    => '<' . $this->config['board_email'] . '>',
			'MIME-Version'              => '1.0',
			'Message-ID'                => '<' . $this->generate_message_id() . '>',
			'Date'                      => date('r', time()),
			'Content-Type'              => 'text/plain; charset=UTF-8', // format=flowed
			'Content-Transfer-Encoding' => '8bit', // 7bit
			'X-Priority'                => $this->mail_priority,
			'X-MSMail-Priority'         => (($this->mail_priority == MAIL_LOW_PRIORITY) ? 'Low' : (($this->mail_priority == MAIL_NORMAL_PRIORITY) ? 'Normal' : 'High')),
			'X-Mailer'                  => 'phpBB3',
			'X-MimeOLE'                 => 'phpBB3',
			'X-phpBB-Origin'            => 'phpbb://' . str_replace(array('http://', 'https://'), array('', ''), generate_board_url()),
		);

		$headers['From'] = $this->from;

		if ($cc)
		{
			$headers['Cc'] =  $cc;
		}

		if ($bcc)
		{
			$headers['Bcc'] =  $bcc;
		}

		if (sizeof($this->extra_headers))
		{
			$headers = array_merge($headers, $this->extra_headers);
		}

		$raw_headers = array();

		foreach ($headers as $key => $value)
			$raw_headers[] = $key.': '.$value;
		return $raw_headers;
	}

	/**
	* Send out emails
	*
	* Add special replyPUSH marker tag
	*
	* @return bool
	*/
	public function msg_email()
	{
		$match = array();
		if (preg_match('#^(Subject:(.*?))$#m', $this->msg, $match))
		{
			$this->subject = (trim($match[2]) != '') ? trim($match[2]) : $this->subject;
			$drop_header .= '[\r\n]*?' . preg_quote($match[1], '#');
			$this->msg = trim(preg_replace('#' . $drop_header . '#s', '', $this->msg));
		}
		$this->msg = '<a href="http://replypush.com#rp-message"></a>' . trim($this->msg);

		parent::msg_email();
	}
}
