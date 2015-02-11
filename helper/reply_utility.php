<?php
namespace replyPUSH\replybyemail\helper;
use replyPUSH\replybyemail\vendor\ReplyPush;

class reply_utility
{
    const POST_REQ = \phpbb\request\request_interface::POST;
    const GET_REQ = \phpbb\request\request_interface::GET;
    const REQUEST_REQ = \phpbb\request\request_interface::REQUEST;
    const COOKIE_REQ = \phpbb\request\request_interface::COOKIE;
    const SERVER_REQ = \phpbb\request\request_interface::SERVER;
    
    protected $user;
    protected $auth;
    protected $config;
    protected $db;
    protected $request;
    protected $phpbb_root_path;
    protected $php_ext;
    
    private $credentials = null;
    
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
    
    private function cnst($type, $suffix = '_REQ')
    {
        return constant('self::'.$type.$suffix);
    }
    
    public function rq_vals($type = 'POST')
    {
        $type = $this->cnst($type);
        return $this->request->get_super_global($type);
    }
    
    public function rq_val($key, $type = 'POST')
    {
        $type = $this->cnst($type);
        return $this->request->variable($key, '', true, $type);
    }
    
    public function set_rq_val($key, $value, $type = 'POST')
    {
        $request = in_array($type,array('POST', 'GET')) ? true : false;
        $type = $this->cnst($type);
        $this->request->overwrite($key, $value, $type);
        if ($request)
        {
            $this->set_rq_val($key, $value, 'REQUEST');
        }
        
        
    }
    
    protected function special_include($file)
    {
        // have to use global for this hack to exclude common.php
        // it is dirty but the only other alternatives
        // are eval or a session proxy which aren't the best
        global $phpbb_root_path;
        $phpbb_root_path_real = $phpbb_root_path;
        define('PHPBB_ROOT_PATH',dirname(__FILE__).'/');
        error_reporting(E_ALL & ~E_NOTICE);
        include($file);
    }
    
    protected function prime_request($file, $post_data)
    {
        if (strpos($file, '?')!==false)
        {
            list($file,$get_str) = explode('?',$file);
            $get_data = array();
            parse_str($get_str, $get_data);
            foreach ($get_data as $key => $val)
            {
                $this->set_rq_val($key, $val, 'GET');
            }
            
        }
        foreach ($post_data as $key => $val)
        {
            $this->set_rq_val($key, $val);
        }
        return $file;
    }
    
    public function sub_request($file, $post_data)
    {
        $file = $this->prime_request($file, $post_data);
        $this->special_include($this->phpbb_root_path.$file);
        die();
    }
    
    
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
        
        $module_name = $this->rq_val('i', 'GET');
        $mode = $this->rq_val('mode','GET');
        $module = new \p_master();
        $this->user->setup($section);
        
        $module->load($section, $module_name, $mode);
        die();
    }
    
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
            if (get_class($ex) == 'replyPUSH\replybyemail\vendor\ReplyPushError')
            {
                return false;
            }
            
            throw $e;
        }
        
        
        return $this->credentials = $creds;
    }
    
    public function check_uri($uri)
    {
        return $this->config['replyPUSH_replybyemail_notify_uri'] == $uri;
    }
    
    public function get_user_id_by_email($email)
    {
        $sql = "SELECT user_id FROM " . USERS_TABLE . " WHERE user_email = '".$this->db->sql_escape($email)."'";
        
        $result = $this->db->sql_query($sql);
        $member = $this->db->sql_fetchrow($result);
        
        $this->db->sql_freeresult($result);
        return $member['user_id'];
    }
    
    public function start_session($user_id)
    {
        
        // start session
        $this->user->session_create($user_id);
        
        // init permissions
        $this->auth->acl($this->user->data);
        
        // fake session cookies for persistance
        $this->set_rq_val($this->config['cookie_name'] . '_u', $this->user->cookie_data['u'], 'COOKIE');
        $this->set_rq_val($this->config['cookie_name'] . '_k', $this->user->cookie_data['k'], 'COOKIE');
        $this->set_rq_val($this->config['cookie_name'] . '_sid', $this->user->session_id, 'COOKIE');
    }
    
    public function parse_message($data)
    {
        
        if (!class_exists('parse_message'))
        {
            global $phpbb_root_path, $phpEx;
            include($phpbb_root_path . 'includes/message_parser.' . $phpEx);
        }
        
        $message_parser = new \parse_message($data['message']);
        $message_parser->bbcode_bitfield = $data['bbcode_bitfield'];
        $message_parser->bbcode_uid = $data['bbcode_uid'];

        $message = $message_parser->format_display(
            $data['enable_bbcode'],
            $data['enable_urls'],
            $data['enable_smilies'],
            false
        );
        
        return $message;
        
    }
    
    public function pre_format_html_content($content)
    {
        return trim(
            strip_tags(
                preg_replace(
                    array(
                        '`\n`',
                        '`<br\s*/>`i',
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
        );
    }
    
    public function pre_format_text_content($content)
    {
        return trim($content);
    }
    
    public function subject_stripped($subject)
    {
        return preg_replace('`^Re:\s*`','',$subject);
    }
    
    public function subject_code($subject)
    {
        return hexdec(substr(md5($this->subject_stripped($subject)), 0, 8));
    }
    
    public function service_email()
    {
        return defined('REPLY_PUSH_EMAIL') ? REPLY_PUSH_EMAIL : 'post@replypush.com';
    }
    
    public function encode_email_name($name, $email = null)
    {
        return sprintf('=?UTF-8?B?%s?= <%s>', base64_encode($name), $email ? $email : $this->service_email());
    }
    
}
