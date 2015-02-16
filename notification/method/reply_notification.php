<?php
/**
*
* @package phpBB Extension - Reply By Email
* @copyright (c) 2015 Paul Thomas
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/
namespace replyPUSH\replybyemail\notification\method;

use \phpbb\notification\method\email;
use replyPUSH\replybyemail\vendor\ReplyPush;

/**
* Extend email notification method to work with replyPUSH
*/
class reply_notification extends email
{
    
    /** @vat custom messenger */
    protected $messenger;
    
    /** @var model for tracking replyPUSH notifications */
    protected $rp_model;
    
    /** @var utility methods */
    protected $utility;
    
    /**
    * Constructor
    *
    * @param \phpbb\user_loader                             $user_loader                  User Loader
    * @param \phpbb\db\driver\driver_interface              $db                           Database driver interface
    * @param \phpbb\cache\driver\driver_interface           $cache                        Cache driver interface
    * @param \phpbb\user                                    $user                         User object
    * @param \phpbb\auth\auth                               $auth                         Auth object
    * @param \phpbb\config\config                           $config                       Config object
    * @param \replyPUSH\replybyemail\helper\messenger       $messenger                    Custom messenger object
    * @param \replyPUSH\replybyemail\model\rp_model         $rp_model                     replyPUSH model object
    * @param \replyPUSH\replybyemail\helper\utility         $utility                      Reply By Email utility helper
    * @param string                                         $phpbb_root_path              phpBB root path
    * @param string                                         $php_ext                      phpEx
    * @access public
    */
    
    
    public function __construct(\phpbb\user_loader $user_loader, \phpbb\db\driver\driver_interface $db, \phpbb\cache\driver\driver_interface $cache, \phpbb\user $user, \phpbb\auth\auth $auth, \phpbb\config\config $config, \replyPUSH\replybyemail\helper\messenger $messenger, \replyPUSH\replybyemail\model\rp_model $rp_model, \replyPUSH\replybyemail\helper\utility $utility, $phpbb_root_path, $php_ext)
    {
        $this->messenger = $messenger;
        $this->rp_model = $rp_model;
        $this->utility = $utility;
        parent::__construct($user_loader,  $db, $cache, $user, $auth, $config, $phpbb_root_path, $php_ext, $messenger);
    }

    /**
    * Notify using phpBB messenger overide
    *
    * @param int $notify_method             Notify method for messenger (e.g. NOTIFY_IM)
    * @param string $template_dir_prefix    Base directory to prepend to the email template name
    *
    * @return null
    */
    protected function notify_using_messenger($notify_method, $template_dir_prefix = '')
    {
        if (!$this->utility->credentials())
        {
            parent::notify_using_messenger($notify_method, $template_dir_prefix);
            return;
        }
        
        if (empty($this->queue))
        {
            return;
        }

        // Load all users we want to notify (we need their email address)
        $user_ids = $users = array();
        foreach ($this->queue as $notification)
        {
            $user_ids[] = $notification->user_id;
        }

        // We do not send emails to banned users
        if (!function_exists('phpbb_get_banned_user_ids'))
        {
            include($this->phpbb_root_path . 'includes/functions_user.' . $this->php_ext);
        }
        $banned_users = phpbb_get_banned_user_ids($user_ids);

        // Load all the users we need
        $this->user_loader->load_users($user_ids);

        $board_url = generate_board_url();
        
        // used for message id reference
        $hash_method = in_array('sha1',hash_algos()) ? 'sha1': 'md5';
        
        extract($this->utility->credentials());
        
        $notified_ids = array();
        // Time to go through the queue and send emails
        foreach ($this->queue as $notification)
        {
            if ($notification->get_email_template() === false)
            {
                continue;
            }

            $user = $this->user_loader->get_user($notification->user_id);

            if ($user['user_type'] == USER_IGNORE || in_array($notification->user_id, $banned_users))
            {
                continue;
            }

            $this->messenger->set_addresses($user);
            
            // use the site name as from
            $this->messenger->from($this->utility->encode_email_name(htmlspecialchars_decode($this->config['sitename']), $this->config['board_contact']));
            
            $type_class = get_class($notification);
            
            // is replyPUSH notification?
            if (constant("{$type_class}::REPLY_PUSH"))
            {     
                $this->messenger->assign_vars(array_merge(array(
                    'USERNAME'                  => $user['username'],
                    'U_NOTIFICATION_SETTINGS'   => generate_board_url() . '/ucp.' . $this->php_ext . '?i=ucp_notifications',
                    'MESSAGE'                   => $notification->message,
                    'SUBJECT_ID'                => $notification->subject_id(),
                    'SUBJECT_STRIPPED'          => $this->utility->subject_stripped($notification->get_reference()),
                    'RP_EMAIL_SIG'              => str_replace('{RP_SIG_ID}', mt_rand(), $this->user->lang['REPLY_PUSH_EMAIL_SIG']),
                ), $notification->get_email_template_variables()));
                
                $this->messenger->template($template_dir_prefix . $notification->get_email_template(), $user['user_lang'], '', 'replybyemail');

                $from_users  =  $notification->users_to_query();
                if (sizeof($from_users))
                {
                    $from_user_id   =  $from_users[0];
                    $record_id      =  $notification->item_id;
                    $type           =  $notification->notification_type_id;
                    $content_id     =  $notification->item_parent_id ? $notification->item_parent_id: $this->utility->subject_code($notification->get_reference());
                    $time_stamp     =  time();
                    
                    $data = sprintf("%08x%08x%08x%08x%08x", $from_user_id, $record_id, $type, $content_id, $time_stamp);
                    
                    $reply_push = new replyPush($account_no, $secret_id, $secret_key, $user['user_email'], $data, $hash_method);
                    
                    $message_id = $reply_push->reference();
                    
                    // messages html
                    $this->messenger->header('Content-Type', 'text/html; charset=UTF-8');
                    $this->messenger->header('Message-ID', $message_id);
                    
                    // get special reference key for threading
                    $ref_hash = $this->rp_model->get_reference_key($type, $record_id, $content_id, $user['user_email']);
                    
                    // get historic reference for threading
                    $ref = $this->rp_model->get_ref($ref_hash);
                    
                    // add headers if historic references
                    if ($ref)
                    {
                        $this->messenger->header("References", $ref);
                        $this->messenger->header("In-Reply-To", $ref);
                    }
            
                    // save current message id as ref
                    $this->rp_model->save_ref($ref_hash, $message_id);
                    
                    $this->messenger->replyto($this->utility->encode_email_name($this->user->lang('REPLY_PUSH_FROM_NAME',$user['username'], $this->config['server_name'])));
                    
                    if ($this->config['board_contact'] == $user['user_email'])
                    {
                        $this->messenger->from($this->utility->encode_email_name(htmlspecialchars_decode($this->config['sitename'])));
                    }
                }
                $notified_ids[] = $notification->item_id;
            }
            else
            {
                $this->messenger->assign_vars(array_merge(array(
                    'USERNAME'                  => $user['username'],
                    'U_NOTIFICATION_SETTINGS'   => generate_board_url() . '/ucp.' . $this->php_ext . '?i=ucp_notifications'
                ), $notification->get_email_template_variables()));
                
                 $this->messenger->template($template_dir_prefix . $notification->get_email_template(), $user['user_lang']);

            }
            $this->messenger->send($notify_method);
        }
        
        // clear notifications of messages in order not to bloat the database
        $this->clear_messages($notified_ids);
        
        // save the queue in the messenger class (has to be called or these emails could be lost?)
        $this->messenger->save_queue();

        // we're done, empty the queue
        $this->empty_queue();
    }
    
    /**
    * Clear Messages
    *
    * Ensures that messages are purged after notifications sent
    * 
    * @param int $notified_ids
    *
    * @return null
    */
    public function clear_messages($notified_ids)
    {
        if (empty($notified_ids))
        {
            return;
        }
        $sql = 'UPDATE ' . NOTIFICATIONS_TABLE . 
                ' SET ' . $this->db->sql_build_array('UPDATE', array('message'=>'')).
                ' WHERE ' . $this->db->sql_in_set('item_id', $notified_ids);
        $this->db->sql_query($sql);
        
    }
    
}
