<?php
/**
*
* @package phpBB Extension - Reply By Email
* @copyright (c) 2015 Paul Thomas
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/
namespace replyPUSH\replybyemail\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use replyPUSH\replybyemail\vendor\ReplyPush;
use replyPUSH\replybyemail\vendor\ReplyPushError;

/**
* All the admin hooks
*/
class acp_listener implements EventSubscriberInterface
{
	/** @var bool if config validation failed */
	private $validation_failed = false;

	/** @var bool if validation started */
	private $validate_reply_push = false;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user $user */
	protected $user;
	
	/** @var \phpbb\controller\helper $helper */
	protected $helper;
	
	/** @var \phpbb\symfony_request $request */
	protected $request;

	/**
	* Constructor
	*
	* @param \phpbb\config\config                 $config                       Config object
	* @param \phpbb\template\template             $template                     Template builder
	* @param string                               $table_prefix                 prefix for phpBB db tables
	* @param \phpbb\user                          $user                         User object
	* @access public
	*/
	function __construct(\phpbb\config\config $config, \phpbb\template\template $template, \phpbb\user $user, \phpbb\controller\helper $helper, \phpbb\symfony_request $request)
	{
		$this->config = $config;
		$this->template = $template;
		$this->user = $user;
		$this->helper = $helper;
		$this->request = $request;
	}

	/**
	* Get subscribed events
	*
	* Listen to these
	*
	* return array[string]string
	*/

	static public function getSubscribedEvents()
	{
		return array(
			'core.acp_board_config_edit_add' => 'replybyemail_config',
			'core.validate_config_variable'  => 'replybyemail_config_validate',
		);
	}
	
	/**
	* Not Public
	*
	* Display not public message
	*/
	public function not_public()
	{
		return 
			'<div class="errorbox">' . $this->user->lang['REPLY_PUSH_PUBLIC_REACH'] . '</div>';
	}

	/**
	* URI Boxes
	*
	* Special read-only form fields for display of
	* notification URI
	*
	* @param string  $key
	*/
	public function uri_boxes($key)
	{
		$url = $this->helper->route('replybyemail_notify', array('uri' => $key), true, null, UrlGeneratorInterface::ABSOLUTE_URL);
		return
			'<input class="reply_push_uri" type="text" value="'. $url.'" readonly="readonly" size="80">'.
			$this->user->lang['REPLY_PUSH_URI_BLURB'];
	}

	/**
	* Reply By Email config
	*
	* Display of form section
	*
	* @param phpbb\event\data  $event
	*/
	public function replybyemail_config($event)
	{

		if ($event['mode'] == 'email')
		{
			// if notify_uri doesn't exist create it
			if (!isset($this->config['replybyemail_notify_uri']))
			{
				$this->config->set('replybyemail_notify_uri', uniqid());
			}

			$display_vars = $event['display_vars'];
			$x = 0;
			while (true)
			{
				$x++;
				if (!isset($display_vars['vars']['legend'.$x]))
				{
					break;
				}
			}

			$display_vars['vars']['legend'.($x-1)] = 'REPLY_BY_EMAIL_SETTINGS';
			
			
			if (!in_array($this->request->server->get('REMOTE_ADDR'), array('127.0.0.1', '::1'))) // if not localhost
			{
				$display_vars['vars']['reply_push_account_no']    = array('lang' => 'REPLY_PUSH_ACCOUNT_NO', 'validate' => 'reply_push',  'type' => 'text:8:8', 'explain' => true);
				$display_vars['vars']['reply_push_secret_id']     = array('lang' => 'REPLY_PUSH_SECRET_ID', 'validate' => 'reply_push',  'type' => 'text:32:32', 'explain' => true);
				$display_vars['vars']['reply_push_secret_key']    = array('lang' => 'REPLY_PUSH_SECRET_KEY', 'validate' => 'reply_push',  'type' => 'text:32:32', 'explain' => true);
				$display_vars['vars']['reply_push_uri']           = array('lang' => 'REPLY_PUSH_URI', 'type' => 'custom', 'function' => array($this, 'uri_boxes'), 'params' => array($this->config['replybyemail_notify_uri']),'explain' => true);
			}
			else
			{
				$display_vars['vars']['reply_push_uri']           = array('lang' => 'REPLY_PUSH_DISABLED', 'type' => 'custom', 'function' => array($this, 'not_public'), 'explain' => true);
			}
			

			$display_vars['vars']['legend'.$x] =  'ACP_SUBMIT_CHANGES';

			$event['display_vars'] = $display_vars;

			$this->template->assign_vars(array('IN_REPLY_BY_EMAIL_SETTINGS' => true));
			$this->validate_reply_push = true;
		}
	}

	/**
	* Reply By Email config validate
	*
	* Processing and validation
	*
	* @param phpbb\event\data  $event
	*/

	public function replybyemail_config_validate($event)
	{
		if (!$this->validate_reply_push || $this->validation_failed)
		{
			return;
		}

		$error = $event['error'];
		$cfg_array = $event['cfg_array'];

		if (!isset($cfg_array['reply_push_account_no']))
		{
			$error[] = $this->user->lang['REPLY_PUSH_ACCOUNT_NO_MISSING'];
		}
		if (!isset($cfg_array['reply_push_secret_id']))
		{
			$error[] = $this->user->lang['REPLY_PUSH_SECRET_ID_MISSING'];
		}

		if (!isset($cfg_array['reply_push_secret_key']))
		{
			$error[] = $this->user->lang['REPLY_PUSH_SECRET_KEY_MISSING'];
		}

		if (sizeof($error)>0)
		{
			$event['error'] = $error;
			$event['cfg_array'] = $cfg_array;
			$this->validation_failed = true;
			return;
		}

		//necessary because of htmlspecialchars use
		$reply_push_account_no = html_entity_decode($cfg_array['reply_push_account_no']);
		$reply_push_secret_id  = html_entity_decode($cfg_array['reply_push_secret_id']);
		$reply_push_secret_key = html_entity_decode($cfg_array['reply_push_secret_key']);

		try
		{
			ReplyPush::validateCredentials($reply_push_account_no, $reply_push_secret_id, $reply_push_secret_key);
		}
		catch(ReplyPushError $e)
		{
			$item = strtoupper(preg_replace('`([a-z])([A-Z])`','$1_$2', $e->getItem()));
			$error[] = $this->user->lang['REPLY_PUSH_'.$item.'_INVALID'];
		}

		if (sizeof($error)>0)
		{
			$event['error'] = $error;
			$event['cfg_array'] = $cfg_array;
			$this->validation_failed = true;
			return;
		}
		$this->validate_reply_push = false;

		$event['cfg_array'] = $cfg_array;

	}
}
