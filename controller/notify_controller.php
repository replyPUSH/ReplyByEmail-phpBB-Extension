<?php
/**
*
* @package phpBB Extension - Reply By Email
* @copyright (c) 2015 Paul Thomas
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/
namespace replyPUSH\replybyemail\controller;
use replyPUSH\replybyemail\library\ReplyPush;
use Symfony\Component\HttpFoundation\Response;

/**
* Controller that handles incoming messages from replyPUSH
*/
class notify_controller
{

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\factory */
	protected $db;

	/** @var utility methods */
	protected $utility;

	/** @var custom messenger */
	protected $messenger;

	/** @var model for tracking replyPUSH notifications */
	protected $rp_model;

	/** @var string phpBB root path */
	protected $phpbb_root_path;

	/** @var string phpEx */
	protected $php_ext;

	/** @var array[int]string notification types id => value */
	protected $notification_types = array();

	/**
	* Constructor
	*
	* @param \phpbb\user                                    $user                         User object
	* @param \phpbb\config\config                           $config                       Config object
	* @param \phpbb\db\driver\factory                       $db                           Database factory object
	* @param \replyPUSH\replybyemail\helper\utility         $utility                      Reply By Email utility helper
	* @param \replyPUSH\replybyemail\helper\messenger       $messenger                    Custom messenger object
	* @param \replyPUSH\replybyemail\model\rp_model         $rp_model                     replyPUSH model object
	* @param string                                         $notification_types_table     notification_types table name
	* @param string                                         $phpbb_root_path              phpBB root path
	* @param string                                         $php_ext                      phpEx
	* @access public
	*/
	function __construct(\phpbb\user $user, \phpbb\config\config $config, \phpbb\db\driver\factory $db, \replyPUSH\replybyemail\helper\utility $utility, \replyPUSH\replybyemail\helper\messenger $messenger, \replyPUSH\replybyemail\model\rp_model $rp_model, $notification_types_table, $phpbb_root_path, $php_ext)
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
	
	/**
	* leave Request
	*
	* Ends request
	*
	* @param   string   $message output this message
	* @param   string   $code HTTP status code
	* @return  Symfony\Component\HttpFoundation\Response
	*/

	public function leave($message = '', $code = 200)
	{
		$response = new Response($message, $code);
		$response->setStatusCode($code);
		return $response;
	}

	/**
	* Denies Access
	*
	* Outputs Denied satus code and exit, with optional message
	*
	* @param string $denied_msg message to output on exit
	* @return  Symfony\Component\HttpFoundation\Response
	*/

	protected function denied($denied_msg = '')
	{
		return $this->leave($denied_msg, 403);
	}

	/**
	* For Checking Notification Url
	*
	* Checks uri code is correct, if not denies
	*
	* @param string $uri code randomly generated on setup
	* @return Symfony\Component\HttpFoundation\Response
	*/
	public function ping($uri)
	{
		if (!$this->utility->check_uri($uri))
		{
			return $this->denied('DENIED');
		}
		// I'm here ...
		return $this->leave('OK');
	}

	/**
	* Process incoming notifications
	*
	* The entry point controller method for replyPUSH notifications
	* inlcude security checks.
	*
	* @param  string $uri code randomly generated on setup
	* @return Symfony\Component\HttpFoundation\Response
	*/
	public function process_incoming_notification($uri)
	{
		// spoofed
		if (!$this->utility->check_uri($uri))
		{
			return $this->denied();
		}
		
		$notification = $this->utility->request->get_super_global(\phpbb\request\request_interface::POST);
		
		if (empty($notification))
		{
			return $this->leave(); // do nothing.
		}
		
		// no credentials can't process
		if (!$this->utility->credentials())
		{
			return $this->denied();
		}

		// is valid?
		if (!$this->rp_model->has_required($notification))
		{
			return $this->denied();
		}

		//check for duplicate message id
		if ($this->rp_model->get_transaction($notification['msg_id']))
		{
			return $this->leave(); //ignore
		}

		// add optional
		$this->rp_model->populate_schema($notification);

		// get credentials
		extract($this->utility->credentials());

		// authenticate
		$reply_push = new ReplyPush($account_no, $secret_id, $secret_key, $notification['from'], $notification['in_reply_to']);
		
		if ($reply_push->hashCheck())
		{

			// find user
			$user_id = $this->utility->get_user_id_by_email($notification['from']);

			// don't know you go away
			if (!$user_id)
			{
				return $this->denied();
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
				return $this->leave();
			}

			// don't know what you are talking about
			if (!isset($this->notification_types[$type_id]))
			{
				return $this->leave();
			}
			
			$type = $this->notification_types[$type_id];

			// valid function name
			$type_process = 'process_' . preg_replace('`notification\.type\.|[^a-z_]`', '', $type) . '_notification';
			
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
		return $this->leave();
	}

	/**
	* Process topic notifications
	*
	* Validates topic and context, then replies
	*
	* @param    int    $from_user_id
	* @param    int    $topic_id
	* @param    int    $forum_id
	* @param    string $message
	* @return   null
	*/
	protected function process_topic_notification($from_user_id, $topic_id, $forum_id, $message, $in_reply_to)
	{
		$sql = "SELECT topic_title FROM " . TOPICS_TABLE .
				" WHERE topic_id = " . (int) $topic_id .
				" AND forum_id = " . (int) $forum_id .
				" AND topic_poster = " . (int) $from_user_id;

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

	/**
	* Process post notifications
	*
	* Validates post and context, then replies
	*
	* @param    int    $from_user_id
	* @param    int    $post_id
	* @param    int    $topic_id
	* @param    string $message
	* @return   null
	*/

	protected function process_post_notification($from_user_id, $post_id, $topic_id, $message)
	{
		$sql = "SELECT p.post_subject, t.forum_id FROM " . POSTS_TABLE . " p, " . TOPICS_TABLE . " t" .
				" WHERE p.topic_id = t.topic_id" .
				" AND p.post_id = " . (int) $post_id .
				" AND p.topic_id = " . (int) $topic_id .
				" AND p.poster_id = " . (int) $from_user_id;

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

	/**
	* Process bookmark notifications
	*
	* Wraper around process_post_notification
	*
	* @param    int    $from_user_id
	* @param    int    $post_id
	* @param    int    $topic_id
	* @param    string $message
	* @return   null
	*/

	protected function process_bookmark_notification($from_user_id, $post_id, $topic_id, $message)
	{
		$this->process_post_notification($from_user_id, $post_id, $topic_id, $message);
	}

	/**
	* Process quote notifications
	*
	* Wraper around process_post_notification
	*
	* @param    int    $from_user_id
	* @param    int    $post_id
	* @param    int    $topic_id
	* @param    string $message
	* @return   null
	*/

	protected function process_quote_notification($from_user_id, $post_id, $topic_id, $message)
	{
		$this->process_post_notification($from_user_id, $post_id, $topic_id, $message);
	}

	/**
	* Process pm notifications
	*
	* Validates pm and context, then replies
	*
	* @param int    $from_user_id
	* @param int    $post_id
	* @param int    $topic_id
	* @param string $message
	*/

	protected function process_pm_notification($from_user_id, $message_id, $content_id, $message)
	{
		$sql = "SELECT pm.message_subject, pmt.user_id FROM " . PRIVMSGS_TABLE . " pm, " . PRIVMSGS_TO_TABLE . " pmt" .
				" WHERE pm.msg_id = pmt.msg_id" .
				" AND pm.msg_id = " . (int) $message_id .
				" AND pm.author_id = " . (int) $from_user_id.
				" AND pmt.user_id <> " . (int) $this->user->data['user_id'];

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

	/**
	* Process topic replies
	*
	* Used for various types of replies.
	*
	* @param    int    $topic_id
	* @param    int    $forum_id
	* @param    string $message
	* @param    string $subject
	* @return   null
	*/

	protected function process_topic_reply($topic_id, $forum_id, $message, $subject)
	{

		$table_sql = ($mode == 'forum') ? FORUMS_WATCH_TABLE : TOPICS_WATCH_TABLE;
		$where_sql = ($mode == 'forum') ? 'forum_id' : 'topic_id';

		// anti-constipation
		$this->utility->update_notify_status($topic_id, $forum_id);

		// post the reply

		// to prevent time lock
		$time = strtotime('-10 seconds');

		$post = array(
			'subject'       => $subject,
			'message'       => $message,
			'post'          => 'Submit',
			'creation_time' => $time,
			'lastclick'     => $time,
			'form_token'    => $this->utility->hash_method($time . $this->user->data['user_form_salt'] . 'posting', array('sha1')),
			'rp_token'      => $this->utility->hash_method($time . $this->utility->credentials()['account_no'] . $this->config['reply_push_notify_uri'], array('sha1'))
		);

		$response = $this->utility->post_request("posting.{$this->php_ext}?mode=reply&f={$forum_id}&t={$topic_id}", $post);
		//$this->post_process();
	}

	/**
	* Process pm replies
	*
	* @param    int          $message_id
	* @param    string       $message
	* @param    string       $subject
	* @param    string|array $to
	* @return   null
	*/

	protected function process_pm_reply($message_id, $message, $subject, $to)
	{

		if (!is_array($to))
		{
			$to = array($to);
		}

		$address_list = array('u' => array_combine($to, array_fill(0, sizeof($to), 'to')));

		$time = strtotime('-10 seconds');

		$post = array(
			'subject'       => $subject,
			'message'       => $message,
			'post'          => 'Submit',
			'reply_to_all'  => 1,
			'creation_time' => $time,
			'lastclick'     => $time,
			'address_list'  => $address_list,
			'form_token'    => $this->utility->hash_method($time . $this->user->data['user_form_salt'] . 'ucp_pm_compose', array('sha1')),
			'rp_token'      => $this->utility->hash_method($time . $this->utility->credentials()['account_no'] . $this->config['reply_push_notify_uri'], array('sha1'))
		);

		$response = $this->utility->post_request("ucp.{$this->php_ext}?i=pm&mode=compose&action=reply&p={$message_id}", $post);
		$this->post_process();
	}
	
	/**
	* Post process
	*
	* Stuff that need to be done after post request
	* 
	* @return null
	*/

	protected function post_process()
	{
		// trigger proccess of notification queue
		
		// old school required
		if (!class_exists('queue'))
		{
			require $this->phpbb_root_path . 'includes/functions_messenger.' . $this->php_ext;
		}
		
		$queue = new \queue();
		$queue->queue();
		$queue->process();  
	}

	/**
	* Process incoming errors from replyPUSH notifications
	*
	* @param int            $error
	* @param \phpbb\user    $user
	* @param string         $subject
	* @param string         $ref
	*/

	protected function process_incoming_error($error, $user, $subject, $ref='')
	{
		$error_msg = isset($user->lang['REPLY_PUSH_ERROR_' . strtoupper($error)]) ? $user->lang['REPLY_PUSH_ERROR_' . strtoupper($error)] : $user->lang['REPLY_PUSH_ERROR_GENERAL'];
		if ($error_msg)
		{
			$this->send_reply_error($user, $error_msg, $subject);
		}
	}

	/**
	* Emial error back to user
	*
	* @param    \phpbb\user    $user
	* @param    string         $error_msg
	* @param    string         $subject
	* @param    string         $ref
	* @return   null
	*/

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

		if ($ref)
		{
			$this->messenger->header("References", "{$ref}");
			$this->messenger->header("In-Reply-To", "{$ref}");
		}

		$this->messenger->replyto($this->utility->encode_email_name($this->user->lang('REPLY_PUSH_FROM_NAME', $user['username'], $this->config['server_name'])));

		$this->messenger->assign_vars(array(
			'USERNAME'  => $user['username'],
			'MESSAGE'   => $error_msg,
		));

		$this->messenger->template('error', $user['user_lang'], '', 'replybyemail');

		$this->messenger->use_queue = false;
		$this->messenger->send();
	}
}
