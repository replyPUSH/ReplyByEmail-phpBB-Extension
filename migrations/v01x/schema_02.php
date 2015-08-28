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
* references and log for replyPUSH
*/
class schema_02 extends \phpbb\db\migration\migration
{
	/**
	* Effectively installed
	*
	* reply_push_ref & reply_push_log tables exist
	*
	* return bool
	*/
	public function effectively_installed()
	{
		return
			$this->db_tools->sql_table_exists($this->table_prefix . 'reply_push_ref')
			&& $this->db_tools->sql_table_exists($this->table_prefix . 'reply_push_log');
	}

	/**
	* Depends on
	*
	* Previous Reply By Email notification
	*
	* return array[int]string
	*/
	static public function depends_on()
	{
		return array('\replyPUSH\replybyemail\migrations\v01x\schema_01');
	}

	/**
	* Update schema
	*
	* Add reply_push_ref & reply_push_log tables
	*
	* return array[string]mixed
	*/
	public function update_schema()
	{
		return array(
			'add_tables'    => array(
				$this->table_prefix . 'reply_push_ref' => array(
					'COLUMNS' => array(
						'ref_hash' => array('VCHAR_UNI:32', ''),
						'ref' => array('TEXT', '')
					),
					'PRIMARY_KEY' => 'ref_hash'
				),
				$this->table_prefix . 'reply_push_log' => array(
					'COLUMNS' => array(
						'message_id' => array('VCHAR_UNI:36', ''),
						'message' => array('TEXT', '')
					),
					'PRIMARY_KEY' => 'message_id'
				),
			)
		);
	}
	
	/**
	* Revert schema
	*
	* Drop reply_push_ref & reply_push_log tables
	*
	* return array[string]mixed
	*/
	public function revert_schema()
	{
		return array(
			'drop_tables' => array(
				$this->table_prefix . 'reply_push_ref',
				$this->table_prefix . 'reply_push_log'
			)
		);
	}
}
