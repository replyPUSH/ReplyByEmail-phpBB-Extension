<?php
/**
*
* @package phpBB Extension - Reply By Email
* @copyright (c) 2015 Paul Thomas
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/
namespace replyPUSH\replybyemail\event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
* All the genral hooks
*/
class listener implements EventSubscriberInterface
{
    
    /** @var \phpbb\config\config */
    protected $config;
    
    /** @var \phpbb\template\template */
    protected $template;
    
    /** @var \phpbb\user */
    protected $user;
    
    /** @var \phpbb\auth\auth */
    protected $auth;
    
    /** @var utility methods */
    protected $utility;
    
    /** @var \phpbb\symfony_request */
    protected $request;
    
    /** @var string phpBB admin path */
    protected $phpbb_admin_path;
    
    /** @var string phpEx */
    protected $php_ext;
    
    /**
    * Constructor
    *
    * @param \phpbb\config\config                       $config                       Config object
    * @param \phpbb\template\template                   $template                     Template builder
    * @param \phpbb\user                                $user                         User object
    * @param \phpbb\auth\auth                           $auth                         Auth object
    * @param \replyPUSH\replybyemail\helper\utility     $utility                      Reply By Email utility helper
    * @param \phpbb\symfony_request                     $request                      Symfony request object
    * @param string                                     $phpbb_root_path              phpBB root path
    * @param string                                     $php_ext                      phpEx
    * @access public
    */
    function __construct(\phpbb\config\config $config, \phpbb\template\template $template, \phpbb\user $user, \phpbb\auth\auth $auth, \replyPUSH\replybyemail\helper\utility $utility, \phpbb\symfony_request $request, $phpbb_admin_path, $php_ext)
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
    
    /**
    * Get subscribed events
    *
    * Listen to these
    * 
    * return array[string]string
    */
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
    
    /**
    * Submit post event
    *
    * Prevent output when posting
    * with Reply by Email
    * 
    * @param phpbb\event\data  $event
    */
    public function submit_post($event)
    {
        $route = $this->request->attributes->get('_route');
        // if in replyPUSH_replybyemail leave without output
        if (strpos($route, 'replyPUSH_replybyemail') !== false)
        {
            //leave
            $this->utility->leave();
        }
    }
    
    /**
    * Load Language
    *
    * Setup default language file.
    * 
    * @param phpbb\event\data  $event
    */
    public function load_language($event)
    {
        $lang_set_ext = $event['lang_set_ext'];
        $lang_set_ext[] = array(
            'ext_name' => 'replyPUSH/replybyemail',
            'lang_set' => 'main',
        );
        $event['lang_set_ext'] = $lang_set_ext;
    }
    
    /**
    * Reply By Email setup
    *
    * Inform admin if not yet set up
    * 
    * @param phpbb\event\data  $event
    */
    public function set_up($event)
    {   
        echo get_class($event);
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
