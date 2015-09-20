# phpBB 3.1 Extension - Reply By Email

## About

This extension uses a service called [replyPUSH](http://replypush.com). Which is currently being trialled for free.

If you are happy to trial it you may sign up, having read the terms.

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

## Minimum requirements

The minimum requirement are the same as phpBB plus <u>full php cURL support is required</u>.
cURL is only used for internally and not to send data elsewhere. 

## Installation

Grab the latest stable version from the [Reply By Email phpBB CBD page](https://www.phpbb.com/customise/db/extension/reply_by_email/)

Unzip and move the folder to phpBB/ext where phpBB is your phpBB directory
    
## Enable
    
Go to "ACP" > "Customise" > "Extensions" and enable the "Reply By Email" extension.

## Setup

Go to "ACP" > "General" > "Email settings" scroll to "Reply by Email Settings", enable and fill it out

Your credentials will be on your replyPUSH [profile](http://replypush.com/profile)

Save the Notify Url on your replyPUSH profile, and you should be good to go. 

## Troubleshooting

### Diagnosis

Questions about using the service or any extension issues please feel free to use the replyPUSH.com help forums.

The extension comes with error logging of two types. <u>These are your first port of call when encountering problems.</u>

Critical and general errors will be logged in:

"ACP" > "Maintenance" > "Error log"

Anything prefixed with `[replyPUSH Error]` is relevant to this extension.

These are often triggered by things wrong with your setup, that will need to be corrected (e.g. cURL support).
They will also be triggered by fraudulent / inappropriate use of the notification url.  

Access and service notifications can be caused by access problems.

This does not mean anything is wrong necessarily, but can aid troubleshooting for your users and help ensure access
is as you want it. 

You can find these access / service notifications in:

"ACP" > "Maintenance" > "Admin log"

These will be prefixed by `[replyPUSH Notice]`

Checking server access and error log may also yield useful information. 

### Sending and receiving

Notifications have to be received by the email client to reply to them. 

Assuming phpBB is able to send emails normally, and the correct notification settings have to be set through
the users ucp, the user needs to have selected a way of being notified e.g. Subscribing to forum or topic,
bookmarking, etc to receive anything. This is not phpBB facility, rather than controlled by this extension.

Reply by Email Settings does provide a way to turn on the "Notify me when a reply is posted" for topic posts
automatically for NEW users. It also provides a suggested query to turn this on for existing users,
which mush be executed using the database manually (e.g through mySQL cli or phpMyAdmin). Use sparingly.

Delays can occur normally as phpBB has a queueing mechanism to send notifications, and email traffic
is subject to normal network delays. This is true for phpBB transactional emails and replies
send back to the service. Neither service or this extension can control this, the service tries to process
incoming messages as fast as possible. 

## License

[GPLv2](license.txt)
