<?php
/**
*
* @package phpBB Extension - Reply By Email
* @copyright (c) 2015 Paul Thomas
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/
/**
* DO NOT CHANGE
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

$lang = array_merge($lang, array(
	'REPLY_PUSH_ACCOUNT_NO_MISSING'     => 'replyPUSH API Account No Missing',
	'REPLY_PUSH_SECRET_ID_MISSING'      => 'replyPUSH API Secret ID Missing',
	'REPLY_PUSH_SECRET_KEY_MISSING'     => 'replyPUSH API Secret Key Missing',
	'REPLY_PUSH_ACCOUNT_NO_INVALID'     => 'replyPUSH API Account No should be 8 character long hexadecimal',
	'REPLY_PUSH_SECRET_ID_INVALID'      => 'replyPUSH API Secret ID should be 32 characters long with alphanumeric and punctuation characters',
	'REPLY_PUSH_SECRET_KEY_INVALID'     => 'replyPUSH API Secret Key should be 32 characters long with alphanumeric and punctuation characters',
	'REPLY_BY_EMAIL_SETTINGS'           => '<a id="rp_settings">Reply by Email Settings</a>',
	'REPLY_PUSH_ENABLE'                 => 'Enable Reply by Email',
	'REPLY_PUSH_ACCOUNT_NO'             => 'API Account No',
	'REPLY_PUSH_SECRET_ID'              => 'API Secret ID',
	'REPLY_PUSH_SECRET_KEY'             => 'API Secret Key',
	'REPLY_PUSH_URI'                    => 'Notify Url',
	'REPLY_PUSH_ENABLE'                 => 'If set it will use the credentials bellow to send out emails that can be replied to through the replyPUSH service.',
	'REPLY_PUSH_ACCOUNT_NO_EXPLAIN'     => 'The Account No found <a href="http://replypush.com/profile">here</a>. Sign up for an account first.',
	'REPLY_PUSH_SECRET_ID_EXPLAIN'      => 'The API ID found <a href="http://replypush.com/profile">here</a>.',
	'REPLY_PUSH_SECRET_KEY_EXPLAIN'     => 'The API Key found <a href="http://replypush.com/profile">here</a>.',
	'REPLY_PUSH_URI_EXPLAIN'            => 'Save this Notify Url <a href="http://replypush.com/profile">here</a>',
	'REPLY_PUSH_URI_BOXES_SEP'          => 'or',
	'REPLY_PUSH_URI_BLURB'              => '
<br>
<br>
<u>tips:</u><br>
If you see {NOT_FOUND_IMG} next to the url that means the url cannot be accessed and it will not work.
It is down to server rules to ensure that urls are correctly routed.<br>
<br>
In nginx you need to make sure the <code>app.php/[someapp]</code> is open.
It is common for non Apache servers to restrict cgi handling to certain files like index.php<br>
<br>
You could modify you php handler rules by changing the condition e.g.<br>
<pre>
	location ~ (\.php|app\.php/.*)$ {
		...
</pre>
<br>
Then you can route the urls
<br>
<pre>
	# phpBB folder location
	location /forum {
		try_files $uri @phpbb;
	}

	location @phpbb{
		rewrite ^/forum/(.*)$ /forum/app.php/$1 last;
	}

</pre>
replacing any <code>/forum</code> text to reflect the relative location of the forum to the root directory or simply <code>/</code> if using the root.
',
	'REPLY_PUSH_ERROR_GENERAL'        => 'An error has occurred',
	'REPLY_PUSH_ERROR_NOEOM'          => 'Could not find /eom so can\'t send message!  Make sure to end your reply with /eom (on new line) to use this service.',
	'REPLY_PUSH_ERROR_NOMSG'          => 'We could not find a message in your reply.',
	'REPLY_PUSH_ERROR_NOMARK'         => 'We could not process your reply, please reply above the quoted message.',
	'REPLY_PUSH_SETUP_MSG'            => '<h4>Reply By Email has not been set up yet!</h4><hr>Please set it up <a href="%s">here</a>.',
	'REPLY_PUSH_SETUP_MSG_DISMISS'    => 'dismiss',
	'REPLY_PUSH_EMAIL_SIG'            => '
<br>
<b>***** reply service *****</b>
<br>
<br>
<p><b>You can reply by the link provided, or reply directly to this email.</p><br>
<p><u>Please put your message directly ABOVE the quoted message you get when you click reply. Please ensure privacy of others.</u></p>
<br>
<p>Thank You.</b></p>
<br>
<p><small>{RP_SIG_ID}</small></p>',
	'REPLY_PUSH_FROM_NAME'           => '%s [at] %s',
	'REPLY_PUSH_PUBLIC_REACH'        => '<h3>Access the site through a public address</h3>The site must be hosted at a publicly reachable address to use the service.',
	'REPLY_PUSH_DISABLED'            => 'Information',
	'REPLY_PUSH_DISABLED_EXPLAIN'    => 'You can\'t configure Reply By Email for the reason stated.',
));
