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
use replyPUSH\replybyemail\library\ReplyPush;
use replyPUSH\replybyemail\library\ReplyPushError;

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
	
	/** @var utility methods */
	protected $utility;

	/**
	* Constructor
	*
	* @param \phpbb\config\config                       $config                       Config object
	* @param \phpbb\template\template                   $template                     Template builder
	* @param string                                     $table_prefix                 prefix for phpBB db tables
	* @param \phpbb\user                                $user                         User object
	* @param \phpbb\controller\helper $helper           $helper                       Controller helper
	* @param \replyPUSH\replybyemail\helper\utility     $utility                      Reply By Email utility helper
	* @access public
	*/
	function __construct(\phpbb\config\config $config, \phpbb\template\template $template, \phpbb\user $user, \phpbb\controller\helper $helper, \replyPUSH\replybyemail\helper\utility $utility)
	{
		$this->config = $config;
		$this->template = $template;
		$this->user = $user;
		$this->helper = $helper;
		$this->utility = $utility;
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
			'<div class="errorbox" style="clear: none;">' . $this->user->lang['REPLY_PUSH_PUBLIC_REACH'] . '</div>';
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
		$url      = $this->helper->route('replyPUSH_replybyemail_notify', array('uri' => $key), true, null, UrlGeneratorInterface::ABSOLUTE_URL);
		$ping_url = $this->helper->route('replyPUSH_replybyemail_notify_ping', array('uri' => $key), true, null, UrlGeneratorInterface::ABSOLUTE_URL);
		$bord_url = generate_board_url();
		
		$is_found = $this->utility->is_ok($ping_url);
		$is_found_img  = '<img style="vertical-align:middle;margin:0 4px;" src="' . $bord_url . '/ext/replyPUSH/replybyemail/adm/style/images/' . ($is_found ? '' : 'not_') . 'found.gif" />';
		$not_found_img = '<img style="vertical-align:middle;margin:0 4px;" src="' . $bord_url . '/ext/replyPUSH/replybyemail/adm/style/images/not_found.gif" />';

		return
			'<input class="reply_push_uri" type="text" value="' . $url. '" readonly="readonly" size="80">' .
			$is_found_img .
			str_replace('{NOT_FOUND_IMG}', $not_found_img, $this->user->lang['REPLY_PUSH_URI_BLURB']);
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
			if (!isset($this->config['reply_push_notify_uri']))
			{
				$this->config->set('reply_push_notify_uri', uniqid());
			}

			$display_vars = $event['display_vars'];
			$x = 0;
			while (true)
			{
				$x++;
				if (!isset($display_vars['vars']['legend' . $x]))
				{
					break;
				}
			}

			$display_vars['vars']['legend' . ($x-1)] = 'REPLY_BY_EMAIL_SETTINGS';
		
			if ($this->utility->can_access_site()) // if public
			{
				$display_vars['vars']['reply_push_enabled']       = array('lang' => 'REPLY_PUSH_ENABLE', 'validate' => 'bool', 'type' => 'radio:enabled_disabled', 'explain' => true);
				$display_vars['vars']['reply_push_account_no']    = array('lang' => 'REPLY_PUSH_ACCOUNT_NO', 'validate' => 'reply_push',  'type' => 'text:8:8', 'explain' => true);
				$display_vars['vars']['reply_push_secret_id']     = array('lang' => 'REPLY_PUSH_SECRET_ID', 'validate' => 'reply_push',  'type' => 'text:32:32', 'explain' => true);
				$display_vars['vars']['reply_push_secret_key']    = array('lang' => 'REPLY_PUSH_SECRET_KEY', 'validate' => 'reply_push',  'type' => 'text:32:32', 'explain' => true);
				$display_vars['vars']['reply_push_uri']           = array('lang' => 'REPLY_PUSH_URI', 'type' => 'custom', 'function' => array($this, 'uri_boxes'), 'params' => array($this->config['reply_push_notify_uri']), 'explain' => true);
			}
			else
			{
				$display_vars['vars']['reply_push_disabled']      = array('lang' => 'REPLY_PUSH_DISABLED', 'type' => 'custom', 'function' => array($this, 'not_public'), 'explain' => true);
			}
			

			$display_vars['vars']['legend' . $x] =  'ACP_SUBMIT_CHANGES';

			$event['display_vars'] = $display_vars;
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

		if (isset($cfg_array['reply_push_enabled']) && $cfg_array['reply_push_enabled'])
		{
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
				$item = strtoupper(preg_replace('`([a-z])([A-Z])`', '$1_$2', $e->getItem()));
				$error[] = $this->user->lang['REPLY_PUSH_' . $item . '_INVALID'];
			}
		}

		if (sizeof($error) > 0)
		{
			$event['error'] = $error;
			$event['cfg_array'] = $cfg_array;
			$this->validation_failed = true;
			return;
		}
		$this->validate_reply_push = false;
		
		$this->config->set('reply_push_enabled', isset($cfg_array['reply_push_enabled']) ? (bool) $cfg_array['reply_push_enabled'] : false);
		$this->config->set('reply_push_account_no', $cfg_array['reply_push_account_no']);
		$this->config->set('reply_push_secret_id', $cfg_array['reply_push_secret_id']);
		$this->config->set('reply_push_secret_key', $cfg_array['reply_push_secret_key']);

		$event['cfg_array'] = $cfg_array;
	}
}
