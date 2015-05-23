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
	
	/** @var\phpbb\cache\service */
	protected $cache;

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

	function __construct(\phpbb\user $user, \phpbb\auth\auth $auth, \phpbb\config\config $config, \phpbb\db\driver\factory $db, \phpbb\request\request $request, \phpbb\cache\service $cache, $phpbb_root_path, $php_ext)
	{
		$this->user = $user;
		$this->auth = $auth;
		$this->config = $config;
		$this->db = $db;
		$this->request = $request;
		$this->cache = $cache;
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
	* Is Proxy ?
	*
	* Is posted on from notifier
	*
	* @return bool
	*/
	
	public function is_proxy()
	{
		if ($this->request->variable('form_token')
			&& $this->request->variable('creation_time')
			&& $this->request->variable('rp_token'))
		{
			return $this->hash_cmp(
				$this->request->variable('rp_token'),
				$this->utility->hash_method(
					$this->request->variable('creation_time') . 
						$this->utility->credencials()['account_no'] . 
						$this->config['reply_push_notify_uri'],
					array('sha1')
				)
			);
		}
		
		return false;
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

		// create session cookies for persistance
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
		
		return parse_url('http://' . $address, PHP_URL_HOST);
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
		$can_access_site = $this->cache->get('rp_can_access_site');
		
		// is stashed ?
		if (isset($can_access_site[$this->request->server('HTTP_HOST')]))
		{
			return $can_access_site[$this->request->server('HTTP_HOST')];
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
		$this->cache->put('rp_can_access_site', array($this->request->server('HTTP_HOST') => $access));
		
		return $access;
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
		$url = generate_board_url() . $url;
		$ch = curl_init(); 
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
		
		$cookies = $this->request->get_super_global(self::COOKIE_REQ); 

		$cookie_array = array();
		foreach ($cookies as $cookie_name => $cookie_value)
		{
			$cookie_array[] = "{$cookie_name}={$cookie_value}";
		}

		$cookie_string = implode('; ', $cookie_array);
		curl_setopt($ch, CURLOPT_COOKIE, $cookie_string);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		$response = curl_exec($ch);
		curl_close($ch);
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
		$is_ok = $this->cache->get('rp_is_ok');
		
		// is stashed ?
		if (isset($is_ok[$url]))
		{
			return $is_ok[$url];
		}
		
		$ch = curl_init(); 
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);
		curl_close($ch);
		
		// stash
		$this->cache->put('rp_is_ok', array($url => $response == 'OK'));
		
		return $response == 'OK';
	}

}
