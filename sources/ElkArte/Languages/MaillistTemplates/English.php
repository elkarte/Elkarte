<?php
// Version: 2.0; MaillistTemplates

// Do not translate anything that is between {}, they are used as replacement variables and MUST
// remain exactly how they are.
// Additionally do not translate the @additional_params: line or the variable names in the lines that follow it.  You may
// translate the description of the variable.
// Do not translate @description:, however you may translate the rest of that line.

/*
	@additional_params: pbe_notify_boards_once_body
		FORUMURL: The url to the forum
		FORUMNAMESHORT: Short or nickname for the forum
		BOARDNAME: Name of the board the post was made in
		TOPICSUBJECT: The subject of the topic causing the notification
		TOPICLINK: A link to the topic.
		MESSAGE: This is the body of the message.
		SIGNATURE: The signature of the member who made the post
		UNSUBSCRIBELINK: Link to unsubscribe from notifications.
		EMAILREGARDS: The site name signature
	@description: A member wants to be notified of new topics on a board they are watching
*/
$txt['pbe_notify_boards_once_body_subject'] = '[{FORUMNAMESHORT}] {TOPICSUBJECT}';
$txt['pbe_notify_boards_once_body_body'] = 'A new topic, \'{TOPICSUBJECT}\', has been started in \'{BOARDNAME}\'.

{MESSAGE}

{SIGNATURE}


------------------------------------
Posting Information:
More topics may be posted, but you won\'t receive more email notifications (on this topic) until you return to the board and read some of them.
You can reply to this email and have it posted as a topic reply.

{FORUMNAMESHORT} Links:

<*> To visit {FORUMNAMESHORT} on the web, go to:
    {FORUMURL}

<*> You can see this message by using this link:
    {TOPICLINK}

<*> Unsubscribe to this {SUBSCRIPTION} by using this link:
    {UNSUBSCRIBELINK}

{EMAILREGARDS}';

/*
	@additional_params: pbe_notify_boards_body
		TOPICSUBJECT: The subject of the topic causing the notification
		TOPICLINK: A link to the topic.
		MESSAGE: This is the body of the message.
		UNSUBSCRIBELINK: Link to unsubscribe from notifications.
		FORUMURL: The url to the forum
		FORUMNAMESHORT: Short or nickname for the forum
		BOARDNAME: Name of the board the post was made in
		SIGNATURE: The signature of the member who made the post
		EMAILREGARDS: The site name signature
	@description: A new topic has been started in a subscribed board, includes the topic body
*/
$txt['pbe_notify_boards_body_subject'] = '[{FORUMNAMESHORT}] {TOPICSUBJECT}';
$txt['pbe_notify_boards_body_body'] = '
{MESSAGE}

{SIGNATURE}


------------------------------------
Posting Information:
{POSTERNAME} started a new topic \'{TOPICSUBJECT}\' on the \'{BOARDNAME}\' Board.
You can reply to this email and have it posted as a reply.

{FORUMNAMESHORT} Links:

<*> To visit {FORUMNAMESHORT} on the web, go to:
    {FORUMURL}

<*> You can see this message by using this link:
    {TOPICLINK}

<*> You can go to your first unread message by using this link:
    {TOPICLINKNEW}

<*> Unsubscribe to this {SUBSCRIPTION} by using this link:
    {UNSUBSCRIBELINK}

{EMAILREGARDS}';

/*
	@additional_params: pbe_notification_reply_body
		TOPICSUBJECT: The subject of the topic causing the notification
		TOPICLINK: A link to the topic.
		MESSAGE: This is the body of the message.
		UNSUBSCRIBELINK: Link to unsubscribe from notifications.
		FORUMURL: The url to the forum
		FORUMNAMESHORT: Short or nickname for the forum
		BOARDNAME: Name of the board the post was made in
		SIGNATURE: The signature of the member who made the post
		EMAILREGARDS: The site name signature
	@description: A reply has been made to a topic in a subscribed board, includes the reply body
*/
$txt['pbe_notification_reply_body_subject'] = 'Re: [{FORUMNAMESHORT}] {TOPICSUBJECT}';
$txt['pbe_notification_reply_body_body'] = '
{MESSAGE}

{SIGNATURE}


------------------------------------
Posting Information:
{POSTERNAME} replied to the topic \'{TOPICSUBJECT}\' on the \'{BOARDNAME}\' Board.
You can reply to this email and have it posted as a topic reply.

{FORUMNAMESHORT} Links:

<*> To visit {FORUMNAMESHORT} on the web, go to:
    {FORUMURL}

<*> You can see this message by using this link:
    {TOPICLINK}

<*> You can go to your first unread message by using this link:
    {TOPICLINKNEW}

<*> Unsubscribe to this {SUBSCRIPTION} by using this link:
    {UNSUBSCRIBELINK}

{EMAILREGARDS}';

/**
	@additional_params: pbe_notification_reply
	@description: when a topic reply gets approved
 */
$txt['pbe_notification_reply_once_subject'] = $txt['pbe_notification_reply_body_subject'];
$txt['pbe_notification_reply_once_body'] = $txt['pbe_notification_reply_body_body'];

/*
	@additional_params: pbe_notification_reply_body_once
		TOPICSUBJECT: The subject of the topic causing the notification
		TOPICLINK: A link to the topic.
		MESSAGE: This is the body of the message.
		UNSUBSCRIBELINK: Link to unsubscribe from notifications.
		FORUMURL: The url to the forum
		FORUMNAMESHORT: Short or nickname for the forum
		BOARDNAME: Name of the board the post was made in
		SIGNATURE: The signature of the member who made the post
		EMAILREGARDS: The site name signature
	@description: Full body email notification for the first new reply in a topic
*/
$txt['pbe_notification_reply_body_once_subject'] = 'Re: [{FORUMNAMESHORT}] {TOPICSUBJECT}';
$txt['pbe_notification_reply_body_once_body'] = '
{MESSAGE}

{SIGNATURE}


------------------------------------
Posting Information:
{POSTERNAME} replied to the topic \'{TOPICSUBJECT}\' on the \'{BOARDNAME}\' Board.
More replies may be posted, but you won\'t receive any more notifications until you read the topic.
You can reply to this email and have it posted as a reply.

{FORUMNAMESHORT} Links:

<*> To visit {FORUMNAMESHORT} on the web, go to:
    {FORUMURL}

<*> You can see this message by using this link:
    {TOPICLINK}

<*> You can go to your first unread message by using this link:
    {TOPICLINKNEW}

<*> Unsubscribe to this {SUBSCRIPTION} by using this link:
    {UNSUBSCRIBELINK}

{EMAILREGARDS}';

/**
	@additional_params: pbe_notification_reply_once
	@description: New reply due to it being approved
 */
$txt['pbe_notification_reply_once_subject'] = $txt['pbe_notification_reply_body_once_subject'];
$txt['pbe_notification_reply_once_body'] = $txt['pbe_notification_reply_body_once_body'];

/*
	@additional_params: pbe_new_pm_body
		SUBJECT: The personal message subject.
		SENDER:  The username for the member sending the personal message.
		MESSAGE:  The text of the personal message.
		REPLYLINK:  The link to directly access the reply page.
		FORUMNAMESHORT: Short or nickname for the forum
	@description: A notification email sent to the receivers of a personal message
*/
$txt['pbe_new_pm_body_subject'] = 'New Personal Message: {SUBJECT}';
$txt['pbe_new_pm_body_body'] = 'You have received a personal message from {SENDER} on {FORUMNAMESHORT}
The message they sent you is:

{MESSAGE}

{SIGNATURE}


------------------------------------
Personal Message Information:
You can reply to this email and have it sent as a PM response to {SENDER}

{FORUMNAMESHORT} Links:

<*> To visit {FORUMNAMESHORT} on the web, go to:
    {FORUMURL}

<*> Reply to this Personal Message here:
    {REPLYLINK}

{EMAILREGARDS}';

/**
	@additional_params: new_pm_body_tolist
		SUBJECT: The personal message subject.
		SENDER:  The user name for the member sending the personal message.
		MESSAGE:  The text of the personal message.
		REPLYLINK:  The link to directly access the reply page.
		TOLIST:  The list of users that will receive the personal message.
	@description: A notification email sent to the receivers of a personal message
*/
$txt['pbe_new_pm_body_tolist_subject'] = 'New Personal Message: {SUBJECT}';
$txt['pbe_new_pm_body_tolist_body'] = '{TOLIST} have just been sent a personal message by {SENDER} on {FORUMNAME}

The group message they sent is:

{MESSAGE}

------------------------------------
{FORUMNAMESHORT} Links:

<*> To visit {FORUMNAMESHORT} on the web, go to:
    {FORUMURL}

<*> Reply to this Personal Message here:
    {REPLYLINK}

{EMAILREGARDS}';