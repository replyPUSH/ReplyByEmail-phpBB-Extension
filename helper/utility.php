<?php
/**
*
* @package phpBB Extension - Reply By Email
* @copyright (c) 2015 Paul Thomas
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/
namespace replyPUSH\replybyemail\helper;
use replyPUSH\replybyemail\library\ReplyPush;


/**
* Bunch of utility helpers
*/
class utility
{
	/** @const int POST type index */
	const POST_REQ = \phpbb\request\request_interface::POST;

	/** @const int GET type index */
	const GET_REQ = \phpbb\request\request_interface::GET;

	/** @const int REQUEST type index */
	const REQUEST_REQ = \phpbb\request\request_interface::REQUEST;

	/** @const int COOKIE type index */
	const COOKIE_REQ = \phpbb\request\request_interface::COOKIE;

	/** @const int SERVER type index */
	const SERVER_REQ = \phpbb\request\request_interface::SERVER;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\factory */
	protected $db;

	/** @var \phpbb\request\request */
	public $request;

	/** @var string phpBB root path */
	protected $phpbb_root_path;

	/** @var string phpEx */
	protected $php_ext;

	/** @var array[string] stash credentials */
	private $credentials = null;

	/** @var string for references/checksums */
	private $hash_function = 'md5';

	/**
	* Constructor
	*
	* @param \phpbb\user                          $user                         User object
	* @param \phpbb\auth\auth                     $auth                         Auth object
	* @param \phpbb\config\config                 $config                       Config object
	* @param \phpbb\db\driver\factory             $db                           Database factory object
	* @param \phpbb\request\request               $request                      Request Object
	* @param string                               $phpbb_root_path              phpBB root path
	* @param string                               $php_ext                      phpEx
	* @access public
	*/

	function __construct(\phpbb\user $user, \phpbb\auth\auth $auth, \phpbb\config\config $config, \phpbb\db\driver\factory $db, \phpbb\request\request $request,  $phpbb_root_path, $php_ext)
	{
		$this->user = $user;
		$this->auth = $auth;
		$this->config = $config;
		$this->db = $db;
		$this->request = $request;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
	}

	/**
	* Special Include
	*
	* Include core entry point for simulated request
	*
	* @param    string   $file
	* @return   null
	*/
	protected function special_include($file)
	{
		// have to use global for this hack to exclude common.php
		// it is dirty but the only other alternatives
		// are eval or a session proxy which aren't the best
		global $phpbb_root_path;
		$phpbb_root_path_real = $phpbb_root_path;
		define('PHPBB_ROOT_PATH',dirname(__FILE__).'/');
		error_reporting(0);
		include($file);
	}

	/**
	* Prime request
	*
	* Processes query string as GET
	* Processes $post_data as POST
	*
	* @param    string              $file
	* @param    array[string]mixed  $post_data
	* @return   string              returns $file
	*/
	protected function prime_request($file, $post_data)
	{
		if (strpos($file, '?')!==false)
		{
			list($file,$get_str) = explode('?',$file);
			$get_data = array();
			parse_str($get_str, $get_data);
			foreach ($get_data as $key => $val)
			{
				$this->request->overwrite($key, $val, self::GET_REQ);
				$this->request->overwrite($key, $val, self::REQUEST_REQ);
			}

		}
		foreach ($post_data as $key => $val)
		{
			$this->request->overwrite($key, $val, self::POST_REQ);
			$this->request->overwrite($key, $val, self::REQUEST_REQ);
		}
		return $file;
	}

	/**
	* Sub request
	*
	* Special method simulating request internally
	*
	* @param    string              $file
	* @param    array[string]mixed  $post_data
	* @return   null
	*/
	public function sub_request($file, $post_data)
	{
		$file = $this->prime_request($file, $post_data);
		$this->special_include($this->phpbb_root_path.$file);
		$this->leave();
	}

	/**
	* Sub request
	*
	* Special method simulating request internally
	* but with module specific loading
	*
	* @param    string              $file
	* @param    array[string]mixed  $post_data
	* @return   null
	*/
	public function sub_request_module($file, $post_data)
	{
		$section = $this->prime_request($file, $post_data);

		$section = preg_replace('`\.' . $this->php_ext . '$`', '', $section);

		if (!function_exists('user_get_id_name'))
		{
			require($this->phpbb_root_path  . 'includes/functions_user.' . $this->php_ext);
		}

		if (!class_exists('p_master'))
		{
			require($this->phpbb_root_path . 'includes/functions_module.' . $this->php_ext);
		}

		$module_name = $this->request->variable('i', self::GET_REQ);
		$mode = $this->request->variable('mode', self::GET_REQ);
		$module = new \p_master();
		$this->user->setup($section);

		$module->load($section, $module_name, $mode);
		$this->leave();
	}

	/**
	* Credentials check
	*
	* Auto-validation for replyPUSH credentials
	* returning them if exists and valid.
	*
	* @return  array[string]|bool
	*/
	public function credentials()
	{
		$prefix = 'reply_push_';

		if ($this->credentials !== null)
		{
			return $this->credentials;
		}

		if (!isset($this->config[$prefix . 'account_no'])
			|| !isset($this->config[$prefix . 'secret_id'])
			|| !isset($this->config[$prefix . 'secret_key']))
		{
			$this->credentials = false;
			return false;
		}

		// html_entity_decode necessary because of how config values are stored.
		$creds = array(
			'account_no' => html_entity_decode($this->config[$prefix . 'account_no']),
			'secret_id'  => html_entity_decode($this->config[$prefix . 'secret_id']),
			'secret_key' => html_entity_decode($this->config[$prefix . 'secret_key']),
		);

		try
		{
			ReplyPush::validateCredentials(
				$creds['account_no'],
				$creds['secret_id'],
				$creds['secret_key']
			);
		}
		catch(ReplyPushError $ex)
		{
			return false;
		}
		catch(\Exception $ex)
		{
			//sometimes above is not sufficient as instance is wrong
			if (get_class($ex) == 'replyPUSH\replybyemail\library\ReplyPushError')
			{
				return false;
			}

			throw $e;
		}

		return $this->credentials = $creds;
	}

	/**
	* Check URI
	*
	* Checks $url against stored (randomly generated) uri to prevent spoof attacks
	*
	* @param   string   $uri
	* @return  bool
	*/
	public function check_uri($uri)
	{
		return $this->config['replybyemail_notify_uri'] == $uri;
	}

	/**
	* Get User by email
	*
	* Useful function in lieu of native method
	*
	* @param   string   $email
	* @return  int
	*/
	public function get_user_id_by_email($email)
	{
		$sql = "SELECT user_id FROM " . USERS_TABLE . " WHERE user_email = '".$this->db->sql_escape($email)."'";

		$result = $this->db->sql_query($sql);
		$member = $this->db->sql_fetchrow($result);

		$this->db->sql_freeresult($result);
		return $member['user_id'];
	}

	/**
	* Start Session
	*
	* For creating a session based on selected user
	*
	* @param   int   $user_id
	* @return  null
	*/
	public function start_session($user_id)
	{

		// start session
		$this->user->session_create($user_id);

		// init permissions
		$this->auth->acl($this->user->data);

		// fake session cookies for persistance
		$this->request->overwrite($this->config['cookie_name'] . '_u', $this->user->cookie_data['u'], self::COOKIE_REQ);
		$this->request->overwrite($this->config['cookie_name'] . '_k', $this->user->cookie_data['k'], self::COOKIE_REQ);
		$this->request->overwrite($this->config['cookie_name'] . '_sid', $this->user->session_id, self::COOKIE_REQ);
	}

	/**
	* Update notify status
	*
	* Ensure all watched topic and forums
	* send out new emails
	*
	* @param   int   $forum_id
	* @param   int   $topic_id
	* @return  null
	*/
	public function update_notify_status($forum_id, $topic_id)
	{
		$sql = "UPDATE ".FORUMS_WATCH_TABLE .
			" SET notify_status = " . NOTIFY_YES .
			" WHERE forum_id = " . (int) $forum_id .
			" AND user_id = " . (int) $this->user->data['user_id'];

		$this->db->sql_query($sql);

		$sql = "UPDATE ".TOPICS_WATCH_TABLE .
			" SET notify_status = " . NOTIFY_YES .
			" WHERE topic_id = " . (int) $topic_id .
			" AND user_id = " . (int) $this->user->data['user_id'];

		$this->db->sql_query($sql);
	}

	/**
	* Parse message
	*
	* Parse a message as a post
	*
	* @param   string   $data
	* @return  string
	*/
	public function parse_message($data)
	{

		if (!class_exists('parse_message'))
		{
			// need global for include scope
			global $phpbb_root_path, $phpEx;
			include($this->phpbb_root_path . 'includes/message_parser.' . $this->php_ext);
		}

		$message_parser = new \parse_message(isset($data['post_text']) ? $data['post_text'] : $data['message']);
		$message_parser->bbcode_bitfield = $data['bbcode_bitfield'];
		$message_parser->bbcode_uid = $data['bbcode_uid'];

		$message = $message_parser->format_display(
			$data['enable_bbcode'],
			isset($data['enable_magic_url']) ? $data['enable_magic_url'] : $data['enable_urls'],
			$data['enable_smilies'],
			false
		);

		return $message;

	}

	/**
	* Parse html to text
	*
	* @param   string   $content
	* @return  string
	*/

	public function pre_format_html_content($content)
	{
		return trim(
			html_entity_decode(
				strip_tags(
					preg_replace(
						array(
							'`\n`',
							'`<br\s*/?>`i',
							'`<p(\s[^>]+)?>(.*?)</\s*p(\s[^>]+)?>`i',
							'`<div(\s[^>]+)?>(.*?)</\s*div(\s[^>]+)?>`i',
						),
						array(
							'',
							"\n",
							"$2\n",
							"$2\n",
						),
						$content
					)
				)
			)
		);
	}

	/**
	* Parse text clean it up
	*
	* @param   string   $content
	* @return  string
	*/
	public function pre_format_text_content($content)
	{
		return trim($content);
	}

	/**
	* hash method
	*
	* Optional tries a list of algorithms before defaulting.
	*
	* @param   string            $value
	* @param   array[int]string  $try
	* @return  string
	*/
	public function hash_method($value, $try = array())
	{
		$hash_function = $this->hash_function;
		$algos = hash_algos();
		foreach($try as $func)
		{
			if(in_array($func, $algos))
			{
				$hash_function = $func;
				break;
			}
		}

		return $hash_function($value);
	}

	/**
	* Strip subject from 'Re:' prefix
	*
	* @param   string   $subject
	* @return  string
	*/
	public function subject_stripped($subject)
	{
		return preg_replace('`^Re:\s*`','',$subject);
	}

	/**
	* Subject Code
	*
	* Compact Hash can be used in absence of parent for collation
	*
	* @param   string   $subject
	* @return  string
	*/
	public function subject_code($subject)
	{
		return hexdec(substr($this->hash_method($this->subject_stripped($subject)), 0, 8));
	}

	/**
	* Service Email
	*
	* @return  string
	*/
	public function service_email()
	{
		return defined('REPLY_PUSH_EMAIL') ? REPLY_PUSH_EMAIL : 'post@replypush.com';
	}

	/**
	* Encode email name
	*
	* UTF-8 encoding of email name for headers
	*
	* @param   string   $name
	* @param   string   $email
	* @return  string
	*/
	public function encode_email_name($name, $email = null)
	{
		return sprintf('=?UTF-8?B?%s?= <%s>', base64_encode($name), $email ? $email : $this->service_email());
	}


	public function can_access_site()
	{
		$local = array('127.0.0.1', '::1');
		$addresses = array();
		
		if (function_exists('gethostbyname')) 
		{
			$addresses[] = gethostbyname($this->request->server('HTTP_HOST'));
		} else {
			$addresses[] = $this->request->server('SERVER_ADDR'); 
			$addresses[] = array_pop(explode(',', $this->request->server('HTTP_X_FORWARDED_FOR')));
			$addresses[] = $this->request->server('HTTP_X_REAL_IP');
		}

		$access = false;

		foreach ($addresses as $address)
		{
			$access = $address && !in_array($address, $local);
			
			if ($access)
			{
				break;
			}
		}
		
		return $access;
	}

}
