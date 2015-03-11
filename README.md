# phpBB 3.1 Extension - Reply By Email

## About

This extension uses a service ![replyPUSH](http://replypush.com). Which is currently free for beta testing. 

If you are happy to be a beta testing you may sign up, having read the terms. We do recommend you also 
![contact](https://www.phpbb.com/community/memberlist.php?mode=email&u=1453826) x00 if you wish to be involved, as you may be ignore otherwise.

## What is does

This extension allows email notifications from phpBB  (topic, post, bookmark, quote, pm) 
to be replied to directly by they user, posting back their reply in context.

The notifications are sent directly from your to their email as normal. Special email header allow 
the user to reply though the replyPUSH service, which processes them and posts them back to your site.

Work has gone into security through integrity and authenticity verification, with which your replyPUSH
account no and credentials will be used.  

This is strictly a "push" not a "pull" based service, so the extension doesn't 'phone home', instead it 
simply waits for the replies to come in.

In order to make it work more fluidly, we have relaxed phpBB3 normal notification restrictions as 
to be more conducive to this sort of user experience. We may be introducing configurable caps to 
control this to your satisfaction.

For user experience, we have have collated notifications relating to topics under one subject, 
they appear in conversation/thread view in email clients (subject tot their settings), 
and the same for PMs.

## Installation

Clone into phpBB/replyPUSH/replybyemail:

    git clone https://github.com/replyPUSH/ReplyByEmail-phpBB-Extension.git phpBB/ext/replyPUSH/replybyemail

Go to "ACP" > "Customise" > "Extensions" and enable the "Reply By Email" extension.

## Setup

Go to "ACP" > "General" > "Email settings" scroll to "Reply by Email Settings" and fill it out

Your credentials will be on your replyPUSH ![profile](http://replypush.com/profile)

Save the Notify Url on your replyPUSH profile, and you should be good to go. 

## License

[GPLv2](license.txt)
