<?php
/**
*
* @package phpBB Extension - Reply By Email
* @copyright (c) 2015 Paul Thomas
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/
namespace replyPUSH\replybyemail\migrations\v01x;

/**
* a message column for notifications
*/
class schema_03 extends \phpbb\db\migration\migration
{
	/**
	* Add data
	*
	* Add config options
	*
	* return array[string]mixed
	*/
	public function add_data()
	{
		return array(
			array('config.add', array('reply_push_notify_uri', uniqid()))
		);
	}
	
	/**
	* Remove data
	*
	* Add config options
	*
	* return array[string]mixed
	*/
	public function revert_data()
	{
		return array(
			array('config.remove', array('reply_push_notify_uri')), 
			array('config.remove', array('reply_push_account_no')), 
			array('config.remove', array('reply_push_secret_id')), 
			array('config.remove', array('reply_push_uri')), 
		);
	}
	
}
