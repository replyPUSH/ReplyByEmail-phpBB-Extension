<?php
/**
*
* @package phpBB Extension - Reply By Email
* @copyright (c) 2015 Paul Thomas
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/
namespace replyPUSH\replybyemail\model;

/**
* Model for tracking replyPUSH notifications
*/
class rp_model
{
    /** @var \phpbb\db\driver\factory */
    protected $db;
    
    /** @var string notification_types_table */
    protected $notification_types_table;
    
    /** @var array[int]string notification types id => value */
    protected $notification_types = array();
    
    /** @var array[string]string notification types that will be collated under a parent type */
    protected $collate_types  = array(
        'notification.type.topic' => array('notification.type.bookmark','notification.type.quote','notification.type.post'),
        'notification.type.pm'    => array('notification.type.pm')
    );
    
    /** @var array[mixed] notification types that will be collated under a parent type */
    protected $required_schema = array('msg_id', 'from', 'in_reply_to', 'subject', 'from_msg_id', 'content' => array('text/plain'));
    
    /** @var array[mixed] optional schema for population */
    protected $optional_schema = array('error', 'content' => array('text/html'));
    
    /** @var utility methods */
    protected $utility;
    
    /** @staticvar array[string]string cache of references */
    public static $ref = array();

    /**
    * Constructor
    *
    * @param \phpbb\db\driver\factory                       $db                           Database factory object
    * @param \replyPUSH\replybyemail\helper\utility         $utility                      Reply By Email utility helper
    * @param string                                         $notification_types_table     notification_types table name
    * @param string                                         $table_prefix                 prefix for phpBB db tables
    * @access public
    */
    function __construct(\phpbb\db\driver\factory $db, \replyPUSH\replybyemail\helper\utility $utility, $notification_types_table, $table_prefix)
    {
        $this->db = $db;
        $this->utility = $utility;
        $this->notification_types_table = $notification_types_table;
        $this->table_prefix = $table_prefix;
    }
    
    public function get_ref($ref_hash)
    {
        if (array_key_exists($ref_hash,self::$ref))
        {
            return self::$ref[$ref_hash];
        }
            
        $sql = "SELECT ref FROM {$this->table_prefix}reply_push_ref".
                " WHERE ref_hash = '". $this->db->sql_escape($ref_hash). "'";
        
        $result = $this->db->sql_query($sql);
            
        $row = $this->db->sql_fetchrow($result);
        
        if (!$row)
        {
            return '';
        }
            
        return $row['ref'];
    }
    
    public function save_ref($ref_hash, $ref)
    {
        if (!$ref_hash || !$ref)
        {
            return;
        }
            
        if ($this->get_ref($ref_hash))
        {
            $sql = "UPDATE {$this->table_prefix}reply_push_ref SET " .
                $this->db->sql_build_array('UPDATE',
                    array(
                        'ref' => $ref
                    )
                ).
                " WHERE ref_hash = '". $this->db->sql_escape($ref_hash). "'";
                
            $result = $this->db->sql_query($sql);
            
            self::$ref[$ref_hash] = $ref;
        }
        else
        {
            
            $sql = "INSERT INTO {$this->table_prefix}reply_push_ref " .
                $this->db->sql_build_array('INSERT',
                    array(
                        'ref' => $ref,
                        'ref_hash' => $ref_hash,
                    )
                );
                
            $result = $this->db->sql_query($sql);
        }
    }
    
    public function get_transaction($msg_id)
    {
        $sql = "SELECT message_id FROM {$this->table_prefix}reply_push_log".
                " WHERE message_id = ". (int) $msg_id;
            
        $result = $this->db->sql_query($sql);
            
        return $this->db->sql_fetchrow($result);
    }
    
    public function log_transaction($notification)
    {
        try
        {
            $this->db->sql_transaction('begin');
            "INSERT INTO {$this->table_prefix}reply_push_log " . $this->db->sql_build_array('INSERT', array(
                'message_id'    => $notification['msg_id'],
                'notification'  => serialize($notification),
            ));
            $this->db->sql_query($sql);
            $this->db->sql_transaction('commit');
        }
        catch(Exception $ex)
        {
            $this->db->sql_transaction('rollback');
            throw $ex;
        }
    }
    
    public function get_notification_types(){
        
        if ($this->notification_types)
        {
            return $this->notification_types;
        }
        
        $sql = "SELECT notification_type_id, notification_type_name 
                FROM " . $this->notification_types_table;
        
        
        $result = $this->db->sql_query($sql);
        
        $notification_types = array();
        
        while ($row = $this->db->sql_fetchrow($result))
        {
            $notification_types[$row['notification_type_id']] = $row['notification_type_name'];
        }
        
        $this->notification_types = $notification_types;
        
        return $notification_types;        
    }
    
    public function get_reference_key($type_id, $record_id, $content_id, $email)
    {
        
        $this->get_notification_types();
        
        if (!isset($this->notification_types[$type_id]))
        {
            return '';
        }
            
        $type = $this->notification_types[$type_id];
        
        foreach ($this->collate_types as $collate_parent => $collate_children)
        {
            if (in_array($type, $collate_children))
            {
                $record_id = $content_id;
                $type = $collate_parent;
                break;
            }
        }
        
        return $this->utility->hash_method($type.$record_id.$email);
    }
    
    public function has_required($data, $schema = NULL)
    {
        if (!$schema)
        {
            $schema = $this->required_schema;
        }
        
        $return = true;
        foreach ($schema as $key => $value)
        {
            if (is_array($value))
            {
                $this->has_required($data, array($key));
                $this->has_required($data[$key], $value);
            }
            else if (!isset($data[$value]))
            {
                $return = false;
                break;
            }
        }
        
        return $return;
    }
    
    public function populate_schema(&$data, $schema = NULL)
    {
        if (!$schema)
        {
            $schema = $this->optional_schema;
        }
        
        foreach ($schema as $key => $value)
        {
            if (is_array($value))
            {
                $this->populate_schema($data, array($key));
                $this->populate_schema($data[$key], $value);
            }
            else if (!isset($data[$value]))
            {
                $data[$value] = null;
            }
        }
    }

}
