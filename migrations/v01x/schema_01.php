<?php
namespace replyPUSH\reply_by_email\migrations\v01x;

class schema_01 extends \phpbb\db\migration\migration
{
    
    public function effectively_installed()
    {
        return $this->db_tools->sql_column_exists($this->table_prefix . 'notifications', 'message');
    }
    
    static public function depends_on()
    {
        return array('\phpbb\db\migration\data\v310\notifications');
    }
    
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
