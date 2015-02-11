<?php
namespace replyPUSH\replybyemail\controller;
use replyPUSH\replybyemail\vendor\ReplyPush;



class notify_controller
{    
    protected $user;
    protected $config;
    protected $db;
    protected $utility;
    protected $messenger;
    protected $rp_model;
    protected $phpbb_root_path;
    protected $php_ext;
     
    protected $notification_types = array();
    
    function __construct(\phpbb\user $user, \phpbb\config\config $config, \phpbb\db\driver\factory $db, $utility, $messenger, $rp_model, $notification_types_table, $phpbb_root_path, $php_ext)
    {
        $this->user = $user;
        $this->config = $config;
        $this->db = $db;
        $this->messenger = $messenger;
        $this->utility = $utility;
        $this->rp_model = $rp_model;
        $this->notification_types = $this->rp_model->get_notification_types($notification_types_table);
        $this->phpbb_root_path = $phpbb_root_path;
        $this->php_ext = $php_ext;
    }
    
    protected function denied($denied_msg = null)
    {
        header("HTTP/1.0 403 Denied");
        $this->utility->kill($denied_msg);
    }
    
    public function ping($uri = null)
    {
        if (!$this->utility->check_uri($uri))
        {
            $this->denied('DENIED');
        }
        // I'm here ...
        $this->utility->kill("OK");
    }
    
    public function process_incoming_notification($uri = null)
    {
        // no credentials can't process
        if (!$this->utility->credentials())
        {
            $this->denied();
        }
        
        // spoofed
        if (!$this->utility->check_uri($uri))
        {
            $this->denied();
        }
            
        $notification = $this->utility->rq_vals();
        
        if (empty($notification))
        {
            $this->utility->kill(); // do nothing.
        }
        
        // is valid?
        if (!$this->rp_model->has_required($notification))
        {
            $this->denied();
        }
            
        //check for duplicate message id
        if ($this->rp_model->get_transaction($notification['msg_id']))
        {
            $this->utility->kill(); //ignore
        }
        
        // add optional
        $this->rp_model->populate_schema($notification);
        
        // get credentials
        extract($this->utility->credentials());
        
        // authenticate 
        $reply_push = new ReplyPush($account_no, $secret_id, $secret_key, $this->user->data['user_email'], $notification['in_reply_to']);
        
        if ($reply_push->hashCheck())
        {
            
            // find user
            $user_id = $this->utility->get_user_id_by_email($notification['from']);
            
            // don't know you go away
            if (!$user_id)
            {
                $this->denied();
            }
            
            // start session
            $this->utility->start_session($user_id);
            
            // split 56 bytes into 8 byte components and process
            $message_data = str_split($reply_push->referenceData, 8);
            
            $from_user_id = hexdec($message_data[2]);
            $record_id    = hexdec($message_data[3]);
            $type_id      = hexdec($message_data[4]);
            $content_id   = hexdec($message_data[5]);
            
            // get special reference key for threading
            $ref_hash = $this->rp_model->get_reference_key($type_id, $record_id, $content_id, $this->user->data['user_email']);
            
            // get historic Reference for threading
            $ref = $this->rp_model->get_ref($ref_hash);
            
            // save current message id as Ref
            $this->rp_model->save_ref($ref_hash, $notification['from_msg_id']);
            
            // handle error notifications without inserting anything.
            if (isset($notification['error']))
            {
                $this->process_incoming_error($notification['error'], $this->user, $notification['subject'], $ref);
                $this->utility->kill();
            }
            
            // don't know what you are talking about
            if (!isset($this->notification_types[$type_id]))
            {
                $this->utility->kill();
            }
               
            $type = $this->notification_types[$type_id];
            
            // valid function name
            $type_process = 'process_'.preg_replace('`notification\.type\.|[^a-z_]`', '', $type).'_notification';
            
            // better than switch statement
            if (is_callable(array($this, $type_process)))
            {
                // process
                $this->$type_process(
                    $from_user_id,
                    $record_id,
                    $content_id,
                    $notification['content']['text/html'] ? 
                        $this->utility->pre_format_html_content($notification['content']['text/html']) :
                        $this->utility->pre_format_text_content($notification['content']['text/plain'])
                );
            }
            
        }
        
        // don't save actual message
        unset($notification['content']);
    
        // save transaction
        $this->rp_model->log_transaction($notification);
        
        // no output
        $this->utility->kill();
    }
    
    protected function process_topic_notification($from_user_id, $topic_id, $forum_id, $message)
    {
        $sql = "SELECT topic_title FROM ". TOPICS_TABLE. 
                " WHERE topic_id = ". (int) $topic_id .
                " AND forum_id = ". (int) $forum_id .
                " AND topic_poster = ". (int) $from_user_id;
        
        $result = $this->db->sql_query($sql);
        
        // can't match all the meta so leave
        // better safe than sorry
        if (!$result)
        {
            return;
        }
            
        $row = $this->db->sql_fetchrow($result);
        
        $this->db->sql_freeresult($result);
        
        $subject = $row['topic_title'];
        
        $this->process_topic_reply($topic_id, $forum_id, $message, $subject);
    }
    
    protected function process_post_notification($from_user_id, $post_id, $topic_id, $message)
    {
        $sql = "SELECT p.post_subject, t.forum_id FROM ". POSTS_TABLE. " p, " . TOPICS_TABLE . " t" .
                " WHERE p.topic_id = t.topic_id" .
                " AND p.post_id = ". (int) $post_id .
                " AND p.topic_id = ". (int) $topic_id .
                " AND p.poster_id = ". (int) $from_user_id;
                
        $result = $this->db->sql_query($sql);
        
        // can't match all the meta so leave
        // better safe than sorry
        if (!$result)
        {
            return;
        }
           
        $row = $this->db->sql_fetchrow($result);
        
        $this->db->sql_freeresult($result);
        
        $forum_id = $row['forum_id'];
        $subject = $row['post_subject'];
        
        $this->process_topic_reply($topic_id, $forum_id, $message, $subject); 
    }
    
    protected function process_bookmark_notification($from_user_id, $post_id, $topic_id, $message)
    {
        $this->process_post_notification($from_user_id, $post_id, $topic_id, $message);
    }
    
    protected function process_quote_notification($from_user_id, $post_id, $topic_id, $message)
    {
        $this->process_post_notification($from_user_id, $post_id, $topic_id, $message);
    }
    
    protected function process_pm_notification($from_user_id, $message_id, $content_id, $message)
    {
        $sql = "SELECT pm.message_subject, pmt.user_id FROM " . PRIVMSGS_TABLE . " pm, " . PRIVMSGS_TO_TABLE . " pmt". 
                " WHERE pm.msg_id = pmt.msg_id" .
                " AND pm.msg_id = ". (int) $message_id .
                " AND pm.author_id = ". (int) $from_user_id.
                " AND pmt.user_id <> ". (int) $this->user->data['user_id'];
                
        $result = $this->db->sql_query($sql);
        
        $to = array();
    
        $row = array();
        
        $subject = null;
        
        while ($row = $this->db->sql_fetchrow($result))
        {
            $to[] = $row['user_id'];
            if (!$subject)
            {
                $subject = $row['message_subject'];
            }
        }
        
        // can't match all the meta so leave
        // better safe than sorry
        if (!empty($row))
        {
            return;
        }
        
        $this->db->sql_freeresult($result);

        $this->process_pm_reply($message_id, $message, $subject, $to);
    }
    
    protected function process_topic_reply($topic_id, $forum_id, $message, $subject)
    {
        
        // post the reply
        
        // to prevent time lock
        $time = strtotime('-10 seconds');
        
        $post = array(
            'subject'  => $subject,
            'message' => $message,
            'post' => 'Submit',
            'creation_time' => $time,
            'lastclick' => $time,
            'form_token' => sha1($time . $this->user->data['user_form_salt'] . 'posting'),
        );

        // special method devised to deal with the problem of 
        // the long and sprawling entry points like posting.php
        // in the absence of rationalised auth / model setups.
        $this->utility->sub_request("posting.{$this->php_ext}?mode=reply&f={$forum_id}&t={$topic_id}", $post);
        
    }
    
    protected function process_pm_reply($message_id, $message, $subject, $to)
    {
        
        if (!is_array($to))
        {
            $to = array($to);
        }
        
        $address_list = array('u' => array_combine($to, array_fill(0,sizeof($to), 'to')));

        $time = strtotime('-10 seconds');

        $post = array(
            'subject' => $subject,
            'message' => $message,
            'post' => 'Submit',
            'reply_to_all' => 1,
            'creation_time' => $time,
            'lastclick' => $time,
            'address_list' => $address_list,
            'form_token' => sha1($time . $this->user->data['user_form_salt'] . 'ucp_pm_compose'),
        );
        
        // special method that calls a module as if handling a request
        $this->utility->sub_request_module("ucp.{$this->php_ext}?i=pm&mode=compose&action=reply&p={$message_id}", $post);
    }
    
    
    
    protected function process_incoming_error($error, $user, $subject, $ref='')
    {
        $error_msg = isset($user->lang['REPLY_PUSH_ERROR_'.strtoupper($error)]) ? $user->lang['REPLY_PUSH_ERROR_'.strtoupper($error)] : $user->lang['REPLY_PUSH_ERROR_GENERAL'];
        if ($error_msg)
        {
            $this->send_reply_error($user, $error_msg, $subject);
        }
    }
    
    protected function send_reply_error($user, $error_msg, $subject, $ref='')
    {
        $lang = $user->lang;
        $user = $user->data;
            
        $this->messenger->set_addresses($user);
        $this->messenger->subject($subject ? $subject : $lang['REPLY_PUSH_ERROR_SUBJECT']);
        $this->messenger->header('Content-Type', 'text/html; charset=UTF-8');
        if ($this->config['board_contact'] == $user['user_email'])
        {
            $this->messenger->from($this->utility->service_email());
        }
        
        if ($ref){
            $this->messenger->header("References","{$ref}");
            $this->messenger->header("In-Reply-To","{$ref}");
        }
        
        $this->messenger->replyto($this->utility->encode_email_name($this->user->lang('REPLY_PUSH_FROM_NAME', $user['username'], $this->config['server_name'])));
        
        $this->messenger->assign_vars(array(
            'USERNAME'  => $user['username'],
            'MESSAGE'   => $error_msg,
        ));
        
        $this->messenger->template('error', $user['user_lang'], '', 'replybyemail');
        
        $this->messenger->send();
    }
}
