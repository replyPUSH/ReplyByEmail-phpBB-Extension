<?php
namespace replyPUSH\replybyemail\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
    private $has_credencials = false;
    
    protected $config;
    protected $template;
    protected $user;
    protected $request;
    protected $utility;
    protected $phpbb_admin_path;
    protected $php_ext;
    protected $auth;
    
    
    
    function __construct(\phpbb\config\config $config, \phpbb\template\template $template, $user, $auth, $utility, \phpbb\symfony_request $request, $phpbb_admin_path, $php_ext)
    {
        $this->config = $config;
        $this->template = $template;
        $this->user = $user;
        $this->auth = $auth;
        $this->utility = $utility;
        $this->request = $request;
        $this->phpbb_admin_path = $phpbb_admin_path;
        $this->php_ext = $php_ext;
    }
    
    static public function getSubscribedEvents()
    {
        return array(
            'core.posting_modify_submit_post_after'  => 'submit_post',
            'core.submit_pm_after'                   => 'submit_post',
            'core.user_setup'                        => 'load_language',
            'core.page_header'                       => 'set_up',
            'core.adm_page_header'                   => 'set_up',
        );
    }
    
    public function submit_post($event)
    {
        $route = $this->request->attributes->get('_route');
        // if in reply_push $this->utility->kill() without output
        if (strpos($route, 'reply_push') !== false)
        {
            $this->utility->kill();
        }
    }
    
    public function load_language($event)
    {
        $lang_set_ext = $event['lang_set_ext'];
        $lang_set_ext[] = array(
            'ext_name' => 'replyPUSH/replybyemail',
            'lang_set' => 'main',
        );
        $event['lang_set_ext'] = $lang_set_ext;
    }
    
    public function set_up($event)
    {   
        if ($this->auth->acl_get('a_board'))
        {
            // link jump to settings
            $url = generate_board_url().'/'.append_sid("{$this->phpbb_admin_path}index.{$this->php_ext}", "i=acp_board&amp;mode=email#rp_settings", true, $this->user->session_id);
            
            // if missing credentials display message
            $this->template->assign_var('REPLY_PUSH_SETUP', !$this->utility->credentials());
            $this->template->assign_var('REPLY_PUSH_SETUP_MSG', $this->user->lang('REPLY_PUSH_SETUP_MSG',$url));
        }
    }
}
