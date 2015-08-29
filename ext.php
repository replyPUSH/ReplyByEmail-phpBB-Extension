<?php
/**
*
* @package phpBB Extension - Reply By Email
* @copyright (c) 2015 Paul Thomas
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/
namespace replyPUSH\replybyemail;

class ext extends \phpbb\extension\base
{
	/**
	* Is enable-able?
	*
	* Check that the necessary curl functions exist
	*
	* @return bool
	*/
	public function is_enableable()
	{
		return
			function_exists('curl_init')
			&& function_exists('curl_setopt')
			&& function_exists('curl_exec')
			&& function_exists('curl_close');
	}
}
