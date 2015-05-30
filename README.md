# phpBB 3.1 Extension - Reply By Email

## About

This extension uses a service called [replyPUSH](http://replypush.com). Which is currently being trialled for free.

If you are happy to trial it you may sign up, having read the terms. We do recommend you also 
[contact](https://www.phpbb.com/community/memberlist.php?mode=email&u=1453826) x00 if you wish to be involved, as you may be ignored otherwise.

## What it does

This extension allows email notifications from phpBB  (topic, post, bookmark, quote, pm) 
to be replied to directly by the user, posting back their reply in context.

The notifications are sent directly from your users to their email as normal. Special email headers allow 
the user to reply though the replyPUSH service, which processes and posts them back to your site.

Security is achieved through integrity and authenticity verification, which uses replyPUSH
account number and credentials, and under normal posting rules and permissions. 

This is strictly a "push" not a "pull" based service, so the extension doesn't 'phone home', instead it 
simply waits for the replies to come in.

In order to make it work more fluidly, we have relaxed phpBB3's normal notification restrictions, 
to be more conducive to this sort of user experience. We may be introducing configurable caps to 
control this to your satisfaction.

For user experience, we have have collated notifications relating to topics under one subject, 
they appear in conversation/thread view in email clients (subject to their settings), 
and the same for PMs.

## Installation

Either grab the latest version from 

    [Reply By Email CBD Listing](https://www.phpbb.com/customise/db/extension/reply_by_email/)

Unzip and move the folder to phpBB/ext where phpBB is your phpBB directory

__OR__

Clone into phpBB/replyPUSH/replybyemail:

    git clone https://github.com/replyPUSH/ReplyByEmail-phpBB-Extension.git phpBB/ext/replyPUSH/replybyemail
    
Check out the latest stable version using stable.php
    
    cd phpBB/ext/replyPUSH
    php stable.php
    
## Enable
    
Go to "ACP" > "Customise" > "Extensions" and enable the "Reply By Email" extension.

## Setup

Go to "ACP" > "General" > "Email settings" scroll to "Reply by Email Settings", enable and fill it out

Your credentials will be on your replyPUSH [profile](http://replypush.com/profile)

Save the Notify Url on your replyPUSH profile, and you should be good to go. 

## License

[GPLv2](license.txt)
