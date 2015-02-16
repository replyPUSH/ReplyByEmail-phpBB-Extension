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
class schema_01 extends \phpbb\db\migration\migration
{
    
    /**
    * Effectively installed
    *
    * Message column exists
    * 
    * return bool
    */
    public function effectively_installed()
    {
        return $this->db_tools->sql_column_exists($this->table_prefix . 'notifications', 'message');
    }
    
    /**
    * Depends on
    *
    * Core notifications migration
    * 
    * return array[int]string
    */
    static public function depends_on()
    {
        return array('\phpbb\db\migration\data\v310\notifications');
    }
    
    /**
    * Update schema
    *
    * Add message column
    * 
    * return array[string]mixed
    */
    public function update_schema()
    {
        return array(
            'add_columns' => array(
                $this->table_prefix . 'notifications' => array(
                    'message' => array('TEXT', NULL)
                )
            )
        );
    }
    
}
