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

	/** @var \phpbb\cache\service */
	protected $cache;

	/** @var \phpbb\log\log */
	protected $log;

	/** @var string phpBB root path */
	protected $phpbb_root_path;

	/** @var string phpEx */
	protected $php_ext;

	/** @var array[string] stash credentials */
	private $credentials = null;

	/** @var array[string] cookies for proxy */
	private $cookies = array();

	/** @var string for references/checksums */
	private $hash_function = 'md5';

	/** @var bool is proxy? */
	public $is_proxy = false;

	/**
	* Constructor
	*
	* @param \phpbb\user                          $user                         User object
	* @param \phpbb\auth\auth                     $auth                         Auth object
	* @param \phpbb\config\config                 $config                       Config object
	* @param \phpbb\db\driver\factory             $db                           Database factory object
	* @param \phpbb\request\request               $request                      Request object
	* @param \phpbb\cache\service                 $cache                        Cache object
	* @param string                               $phpbb_root_path              phpBB root path
	* @param string                               $php_ext                      phpEx
	* @access public
	*/
	function __construct(\phpbb\user $user, \phpbb\auth\auth $auth, \phpbb\config\config $config, \phpbb\db\driver\factory $db, \phpbb\request\request $request, \phpbb\cache\service $cache, \phpbb\log\log $log, $phpbb_root_path, $php_ext)
	{
		$this->user = $user;
		$this->auth = $auth;
		$this->config = $config;
		$this->db = $db;
		$this->request = $request;
		$this->cache = $cache;
		$this->log = $log;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
	}

	/**
	* Hash Compare
	*
	* Timing neutral string comparison
	*
	* @param string $a
	* @param string $b
	*
	* @return bool
	*/
	protected function hash_cmp($a, $b)
	{
		if (strlen($a) != strlen($b))
		{
			return false;
		}

		$result = 0;

		foreach (array_combine(str_split($a), str_split($b)) as $x => $y)
		{
			$result |= ord($y) ^ ord($y);
		}
		return $result == 0;
	}

	/**
	* Credentials check
	*
	* Auto-validation for replyPUSH credentials
	* returning them if exists and valid.
	*
	* @return  array[string]|bool
	*/
	public function credentials($key = null)
	{
		$prefix = 'reply_push_';

		if ($this->credentials !== null)
		{
			if (is_string($key)) {
				return $this->credentials[$key];
			} else {
				return $this->credentials;
			}
		}

		if (!isset($this->config[$prefix . 'enabled'])
			|| !isset($this->config[$prefix . 'account_no'])
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

		$this->credentials = $creds;

		if (is_string($key)) {
			return $this->credentials[$key];
		} else {
			return $this->credentials;
		}
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
		return $this->config['reply_push_notify_uri'] == $uri;
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
		$sql = 'SELECT user_id
			FROM ' . USERS_TABLE . "
			WHERE user_email = '" . $this->db->sql_escape($email) . "'";

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
		$this->user->session_create($user_id, false, true);

		// init permissions
		$this->auth->acl($this->user->data);

		// set private cookie variable for proxying
		$this->cookies = $this->request->get_super_global(self::COOKIE_REQ);
		$this->cookies[$this->config['cookie_name'] . '_u']   = $this->user->cookie_data['u'];
		$this->cookies[$this->config['cookie_name'] . '_k']   = $this->user->cookie_data['k'];
		$this->cookies[$this->config['cookie_name'] . '_sid'] = $this->user->session_id;
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
		$sql = 'UPDATE ' . FORUMS_WATCH_TABLE . '
			SET notify_status = ' . NOTIFY_YES . '
			WHERE forum_id = ' . (int) $forum_id . '
				AND user_id = ' . (int) $this->user->data['user_id'];

		$this->db->sql_query($sql);

		$sql = 'UPDATE ' . TOPICS_WATCH_TABLE . '
			SET notify_status = ' . NOTIFY_YES . '
			WHERE topic_id = ' . (int) $topic_id . '
				AND user_id = ' . (int) $this->user->data['user_id'];

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
			if (in_array($func, $algos))
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
		return preg_replace('`^Re:\s*`', '', $subject);
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

	/**
	* Parse host
	*
	* Strip port, etc from host
	*
	* @param   string   $address
	* @return  string
	*/
	protected function parse_host($address)
	{
		if (strpos($address, '::1') === 0)
		{
			$address = '[::1]';
		}

		return parse_url('scheme://' . $address, PHP_URL_HOST);
	}

	/**
	* Can access site ?
	*
	* Is this a public address?
	*
	* @return  bool
	*/
	public function can_access_site()
	{
		$can_access_site_stash = $this->cache->get('rp_can_access_site');
		$key = $this->hash_method($this->request->server('HTTP_HOST'));
		// is stashed ?
		if (isset($can_access_site_stash[$key]))
		{
			return $can_access_site_stash[$key];
		}

		$local = array('localhost', '127.0.0.1', '::1', '[::1]');
		$addresses = array();

		if (function_exists('gethostbyname'))
		{
			$addresses[] = gethostbyname($this->parse_host($this->request->server('HTTP_HOST')));
		} else {
			$addresses[] = $this->parse_host($this->request->server('SERVER_ADDR'));
			$addresses[] = $this->parse_host($this->request->server('LOCAL_ADDR'));
			$addresses[] = $this->parse_host(array_pop(explode(',', $this->request->server('HTTP_X_FORWARDED_FOR'))));
			$addresses[] = $this->parse_host($this->request->server('HTTP_X_REAL_IP'));
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

		// stash
		$this->cache->put('rp_can_access_site', array($key => $access));

		return $access;
	}

	/**
	* Log
	*
	* Log errors and warnings
	*
	* @param  string              $code
	* @param  array[string]mixed  $additional_data
	* @param  string              $type
	*/
	public function log($code, $additional_data = array(), $type = 'critical')
	{
		switch($type)
		{
			case 'admin':
				$operation_prefix  = 'REPLY_PUSH_LOG_NOTICE_';
				break;
			case 'critical':
			default:
				$type = 'critical';
				$operation_prefix  = 'REPLY_PUSH_LOG_ERROR_';
				break;
		}

		$this->log->add(
			$type,
			isset($this->user->user_id) ? $this->user->user_id : 1,
			$this->user->ip,
			$operation_prefix . $code,
			false,
			$additional_data
		);
	}

	/**
	* cURL installed?
	*
	* Is cURL installed?
	*
	* @return bool
	*/
	public function curl_installed()
	{
		return
			function_exists('curl_init')
			&& function_exists('curl_setopt')
			&& function_exists('curl_exec')
			&& function_exists('curl_close');
	}

	/**
	* Proxy Init
	*
	* Setup up cURL
	*
	* @param    string  $url
	* @return   object
	*/
	public function proxy_init($url)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, 'phpBB replyPUSH Proxy/0.1');
		return $ch;
	}
	
	/**
	* Proxy Exec
	*
	* Execute cURL with error log
	*
	* @param    string  $ch
	* @return   string|bool
	*/
	public function proxy_exec($ch)
	{
		$result = curl_exec($ch);
		
		if ($response === false) {
			$this->log('CURL_ERROR', array('error' => curl_error($ch)));
		}
		
		curl_close($ch);
		return $result;
	}

	/**
	* Post request
	*
	* Post back form
	*
	* @param    string              $url
	* @param    array[string]mixed  $post_data
	* @return   string
	*/
	public function post_request($url, $post_data)
	{
		$url = generate_board_url() . '/'. ltrim($url, '/');
		$ch = $this->proxy_init($url);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));

		$cookie_array = array();
		foreach ($this->cookies as $cookie_name => $cookie_value)
		{
			$cookie_array[] = "{$cookie_name}={$cookie_value}";
		}

		$cookie_string = implode('; ', $cookie_array);
		curl_setopt($ch, CURLOPT_COOKIE, $cookie_string);

		$response = $this->proxy_exec($ch);

		return $response;
	}

	/**
	* Is OK
	*
	* Check url returns OK
	*
	* @param    string  $url
	* @return   string
	*/
	public function is_ok($url)
	{
		$is_ok_stash = $this->cache->get('rp_is_ok');
		$key = $this->hash_method($url);
		// is stashed ?
		if (isset($is_ok_stash[$key]))
		{
			return $is_ok_stash[$key];
		}

		$ch = $this->proxy_init($url);
		$response = $this->proxy_exec($ch);

		$is_ok = $response == 'OK';

		// stash
		$this->cache->put('rp_is_ok', array($key => $is_ok));

		return $is_ok;
	}

	/**
	* Is Proxy ?
	*
	* Is posted on from notifier
	*
	* @return bool
	*/
	public function is_proxy()
	{
		if ($this->is_proxy)
		{
			return true;
		}

		if ($this->request->variable('form_token', '')
			&& $this->request->variable('creation_time', '')
			&& $this->request->variable('rp_token', ''))
		{
			$proxy = $this->hash_cmp(
				$this->request->variable('rp_token', ''),
				$this->hash_method(
					$this->request->variable('creation_time', '') .
						$this->credentials('account_no') .
						$this->config['reply_push_notify_uri'],
					array('sha1')
				)
			);

			if ($proxy)
			{
				$this->is_proxy = true;
				return true;
			}
		}

		return false;
	}
}
