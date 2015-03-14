<?php
/**
*
* @package phpBB Extension - Reply By Email
* @copyright (c) 2015 Paul Thomas
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/
namespace replyPUSH\replybyemail\notification\type;
use \phpbb\notification\type\quote as quote_base;
use replyPUSH\replybyemail\notification\type\reply_push_interface;

/**
* Extend quote notification tyle to work with replyPUSH
*/
class quote extends quote_base implements reply_push_interface
{
	
	/** @var utility methods */
	protected $utility;
	
	/**
	* Notification Type Constructor
	*
	* @param \phpbb\user_loader                     $user_loader
	* @param \phpbb\db\driver\driver_interface      $db
	* @param \phpbb\cache\driver\driver_interface   $cache
	* @param \phpbb\user                            $user
	* @param \phpbb\auth\auth                       $auth
	* @param \phpbb\config\config                   $config
	* @param string                                 $phpbb_root_path
	* @param string                                 $php_ext
	* @param string                                 $notification_types_table
	* @param string                                 $notifications_table
	* @param string                                 $user_notifications_table
	* @param replyPUSH\replybyemail\helper\utility  $utility
	*/
	public function __construct(\phpbb\user_loader $user_loader, \phpbb\db\driver\driver_interface $db, \phpbb\cache\driver\driver_interface $cache, $user, \phpbb\auth\auth $auth, \phpbb\config\config $config, $phpbb_root_path, $php_ext, $notification_types_table, $notifications_table, $user_notifications_table, $utility)
	{
		$this->utility = $utility;
		parent::__construct($user_loader, $db, $cache, $user, $auth, $config, $phpbb_root_path, $php_ext, $notification_types_table, $notifications_table, $user_notifications_table);
	}
	
	/**
	* Subject ID
	* 
	* Used in subject line for uniqueness
	* 
	* return int
	*/
	public function subject_id()
	{
		return $this->item_parent_id;
	}
	
	/**
	* For preparing the data for insertion in an SQL query
	* extended to add message
	*
	* @param    array   $type_data Data unique to this notification type
	* @param    array   $pre_create_data Data from pre_create_insert_array()
	* @return   array   Array of data ready to be inserted into the database
	*/
	public function create_insert_array($type_data, $pre_create_data = array())
	{
		$this->message = $this->utility->parse_message($type_data);
		return parent::create_insert_array($type_data, $pre_create_data);
	}
	
}
