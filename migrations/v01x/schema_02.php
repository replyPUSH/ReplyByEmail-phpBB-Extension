<?php
namespace replyPUSH\replybyemail\migrations\v01x;

class schema_02 extends \phpbb\db\migration\migration
{
    
    public function effectively_installed()
    {
        return 
            $this->db_tools->sql_table_exists($this->table_prefix . 'reply_push_ref') 
            && $this->db_tools->sql_table_exists($this->table_prefix . 'reply_push_log');
        
    }
    
    static public function depends_on()
    {
        return array('\replyPUSH\replybyemail\migrations\v01x\schema_01');
    }
    
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
    
}
