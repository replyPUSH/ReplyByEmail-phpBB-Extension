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

/**
* All the genral hooks
*/
class listener implements EventSubscriberInterface
{

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var utility methods */
	protected $utility;

	/** @var \phpbb\symfony_request */
	protected $request;

	/** @var \phpbb\controller\helper $helper */
	protected $helper;

	/** @var string phpBB admin path */
	protected $phpbb_admin_path;

	/** @var string phpEx */
	protected $php_ext;

	/**
	* Constructor
	*
	* @param \phpbb\config\config                       $config                       Config object
	* @param \phpbb\template\template                   $template                     Template builder
	* @param \phpbb\user                                $user                         User object
	* @param \phpbb\auth\auth                           $auth                         Auth object
	* @param \replyPUSH\replybyemail\helper\utility     $utility                      Reply By Email utility helper
	* @param \phpbb\symfony_request                     $request                      Symfony request object
	* @param \phpbb\controller\helper $helper           $helper                       Controller helper
	* @param string                                     $phpbb_root_path              phpBB root path
	* @param string                                     $php_ext                      phpEx
	* @access public
	*/
	function __construct(\phpbb\config\config $config, \phpbb\template\template $template, \phpbb\user $user, \phpbb\auth\auth $auth, \replyPUSH\replybyemail\helper\utility $utility, \phpbb\symfony_request $request, \phpbb\controller\helper $helper, $phpbb_admin_path, $php_ext)
	{
		$this->config = $config;
		$this->template = $template;
		$this->user = $user;
		$this->auth = $auth;
		$this->utility = $utility;
		$this->request = $request;
		$this->helper = $helper;
		$this->phpbb_admin_path = $phpbb_admin_path;
		$this->php_ext = $php_ext;
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
			'core.common'                            => 'allow_autologin',
			'core.posting_modify_submit_post_after'  => 'submit_post',
			'core.submit_pm_after'                   => 'submit_post',
			'core.user_setup'                        => 'load_language',
			'core.user_add_modify_data'              => 'notify_default',
		);
	}

	/**
	* Allow auto-login
	*
	* Forces autologin if posting
	* with Reply by Email
	*
	* @param phpbb\event\data  $event
	*/
	public function allow_autologin($event)
	{
		// check security token is replyPUSH internal proxy?
		if ($this->utility->is_proxy())
		{
			// set session assurances (not permanent)
			$this->config->set('allow_autologin', true);
			$this->config->set('ip_check', 0);
			$this->config->set('browser_check', false);
			$this->config->set('forwarded_for_check', false);
		}
	}

	/**
	* Submit post event
	*
	* Prevent output when posting
	* with Reply by Email
	*
	* @param phpbb\event\data  $event
	*/
	public function submit_post($event)
	{
		// check security token is replyPUSH proxy?
		if ($this->utility->is_proxy())
		{
			send_status_line(200, 'OK');
			exit_handler();
		}
	}

	/**
	* Load Language
	*
	* Setup default language file.
	*
	* @param phpbb\event\data  $event
	*/
	public function load_language($event)
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = array(
			'ext_name' => 'replyPUSH/replybyemail',
			'lang_set' => 'main',
		);
		$event['lang_set_ext'] = $lang_set_ext;
	}

	/**
	* Notify Default
	*
	* If notify default is on
	* then set user_notify for user
	* as default on
	*
	* @param phpbb\event\data  $event
	*/
	public function notify_default($event)
	{
		$user_row = $event['user_row'];
		$sql_ary = $event['sql_ary'];

		if (!isset($user_row['user_notify']))
		{
			$sql_ary['user_notify'] = 1;
			$event['sql_ary'] = $sql_ary;
		}
	}
}
