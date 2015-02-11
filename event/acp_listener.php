<?php
namespace replyPUSH\replybyemail\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use replyPUSH\replybyemail\vendor\ReplyPush;
use replyPUSH\replybyemail\vendor\ReplyPushError;

class acp_listener implements EventSubscriberInterface
{
    private $validation_failed = false;
    private $validate_reply_push = false;
    
    protected $config;
    protected $template;
    
    
    
    function __construct(\phpbb\config\config $config, \phpbb\template\template $template, $user)
    {
        $this->config = $config;
        $this->template = $template;
        $this->user = $user;
    }
    
    static public function getSubscribedEvents()
    {
        return array(
            'core.acp_board_config_edit_add' => 'replybyemail_config',
            'core.validate_config_variable'  => 'replybyemail_config_validate',
        );
    }
    
    public function uri_boxes($key)
    {
        return
        '<input class="reply_push_uri" type="text" value="'.generate_board_url().'/replypush/'.$key.'" readonly="readonly" size="80">'.
        '<br>'.$this->user->lang['REPLY_PUSH_URI_BOXES_SEP'].'</br>'.
        '<input class="reply_push_uri" type="text" value="'.generate_board_url().'/app.php/replypush/'.$key.'" readonly="readonly" size="80">'.
        $this->user->lang['REPLY_PUSH_URI_BLURB'];
    }
    
    public function replybyemail_config($event)
    {

        if ($event['mode'] == 'email')
        {
            // if notify_uri doesn't exist create it
            if (!isset($this->config['replyPUSH_replybyemail_notify_uri']))
            {
                $this->config->set('replyPUSH_replybyemail_notify_uri', uniqid());
            }
            
            $display_vars = $event['display_vars'];
            $x = 0;
            while (true)
            {
                $x++;
                if (!isset($display_vars['vars']['legend'.$x]))
                {
                    break;
                }
            }
            
            $display_vars['vars']['legend'.($x-1)] = 'REPLY_BY_EMAIL_SETTINGS';
            
            $display_vars['vars']['reply_push_account_no']    = array('lang' => 'REPLY_PUSH_ACCOUNT_NO', 'validate' => 'reply_push',  'type' => 'text:8:8', 'explain' => true);
            $display_vars['vars']['reply_push_secret_id']     = array('lang' => 'REPLY_PUSH_SECRET_ID', 'validate' => 'reply_push',  'type' => 'text:32:32', 'explain' => true);
            $display_vars['vars']['reply_push_secret_key']    = array('lang' => 'REPLY_PUSH_SECRET_KEY', 'validate' => 'reply_push',  'type' => 'text:32:32', 'explain' => true);
            $display_vars['vars']['reply_push_uri']           = array('lang' => 'REPLY_PUSH_URI', 'type' => 'custom', 'function' => array($this, 'uri_boxes'), 'params' => array($this->config['replyPUSH_replybyemail_notify_uri']),'explain' => true);
            
            $display_vars['vars']['legend'.$x] =  'ACP_SUBMIT_CHANGES';

            $event['display_vars'] = $display_vars;

            $this->template->assign_vars(array('IN_REPLY_BY_EMAIL_SETTINGS' => true));
            $this->validate_reply_push = true;
        }
    }
    
    public function replybyemail_config_validate($event)
    {
        if (!$this->validate_reply_push || $this->validation_failed)
        {
            return;
        }
        
        $error = $event['error'];
        $cfg_array = $event['cfg_array'];
        
        if (!isset($cfg_array['reply_push_account_no']))
        {
            $error[] = $this->user->lang['REPLY_PUSH_ACCOUNT_NO_MISSING'];
        }
        if (!isset($cfg_array['reply_push_secret_id']))
        {
            $error[] = $this->user->lang['REPLY_PUSH_SECRET_ID_MISSING'];
        }
        
        if (!isset($cfg_array['reply_push_secret_key']))
        {
            $error[] = $this->user->lang['REPLY_PUSH_SECRET_KEY_MISSING'];
        }
        
        if (sizeof($error)>0)
        {
            $event['error'] = $error;
            $event['cfg_array'] = $cfg_array;
            $this->validation_failed = true;
            return;
        }
        
        //necessary because of htmlspecialchars use
        $reply_push_account_no = html_entity_decode($cfg_array['reply_push_account_no']);
        $reply_push_secret_id  = html_entity_decode($cfg_array['reply_push_secret_id']);
        $reply_push_secret_key = html_entity_decode($cfg_array['reply_push_secret_key']);
        
        try
        {
            ReplyPush::validateCredentials($reply_push_account_no, $reply_push_secret_id, $reply_push_secret_key);
        }
        catch(ReplyPushError $e)
        {
            $item = strtoupper(preg_replace('`([a-z])([A-Z])`','$1_$2', $e->getItem()));
            $error[] = $this->user->lang['REPLY_PUSH_'.$item.'_INVALID'];
        }
        
        if (sizeof($error)>0)
        {
            $event['error'] = $error;
            $event['cfg_array'] = $cfg_array;
            $this->validation_failed = true;
            return;
        }
        $this->validate_reply_push = false;

        $event['cfg_array'] = $cfg_array;

    }
}
