<?php
namespace replyPUSH\reply_by_email\notification\type;
use phpbb\notification\type\pm as pm_base;
use replyPUSH\reply_by_email\notification\type\reply_push_interface;

class pm extends pm_base implements reply_push_interface
{
    
    protected $utility;
    
    public function __construct(\phpbb\user_loader $user_loader, \phpbb\db\driver\driver_interface $db, \phpbb\cache\driver\driver_interface $cache, $user, \phpbb\auth\auth $auth, \phpbb\config\config $config, $phpbb_root_path, $php_ext, $notification_types_table, $notifications_table, $user_notifications_table, $utility)
    {
        $this->utility = $utility;
        parent::__construct($user_loader, $db, $cache, $user, $auth, $config, $phpbb_root_path, $php_ext, $notification_types_table, $notifications_table, $user_notifications_table);
    }
    
    public function subject_id()
    {
        return $this->item_id;
    }
    
    public function create_insert_array($type_data, $pre_create_data = array())
    {
        $this->message = $this->utility->parse_message($type_data);
        return parent::create_insert_array($type_data, $pre_create_data);
    }
}
