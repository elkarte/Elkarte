<?php
// Version: 1.0; MaillistTemplates

// Do not translate anything that is between {}, they are used as replacement variables and MUST remain exactly how they are.
// 		Additionally do not translate the @additioinal_params: line or the variable names in the lines that follow it.  You may
//		translate the description of the variable.
//		Do not translate @description:, however you may translate the rest of that line.

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
	@description: A memeber wants to be notified of new topics on a board they are watching
*/
$txt['pbe_notify_boards_once_body_subject'] = '[{FORUMNAMESHORT}] {TOPICSUBJECT}';
$txt['pbe_notify_boards_once_body_body'] = 'Ein neues Thema, \'{TOPICSUBJECT}\', wurde in \'{BOARDNAME}\' eröffnet.

{MESSAGE}

{SIGNATURE}


------------------------------------
Informationen: Es könnten weitere Themen eröffnet worden sein, aber Sie werden keine weiteren Benachrichtigungen hierzu erhalten, bevor Sie nicht einige von ihnen gelesen haben.
Sie können auf diese E-Mail antworten und die Antwort direkt im Thema veröffentlichen.

Weiterführende Verweise zu {FORUMNAMESHORT}:

<*> Um {FORUMNAMESHORT} im Netz aufzurufen, gehen Sie auf:
    {FORUMURL}

<*> Sie können diese Nachricht mit Anhängen und/oder Bildern (falls vorhanden) mittels dieses Links lesen:
    {TOPICLINK}

<*> Sie können das Abonnement hier kündigen:
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
Informationen: {POSTERNAME} hat das Thema \'{TOPICSUBJECT}\' im Forum \'{BOARDNAME}\' eröffnet.
Sie können auf diese E-Mail antworten und die Antwort direkt im Thema veröffentlichen.

Weiterführende Verweise zu {FORUMNAMESHORT}:

<*> Um {FORUMNAMESHORT} im Netz aufzurufen, gehen Sie auf:
    {FORUMURL}

<*> Sie können diese Nachricht mit Anhängen und/oder Bildern (falls vorhanden) mittels dieses Links lesen:
    {TOPICLINK}

<*> Hier gelangen Sie zur ersten ungelesenen Nachricht in dieser Diskussion:
    {TOPICLINKNEW}

<*> Sie können das Abonnement hier kündigen:
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
$txt['pbe_notification_reply_body_subject'] = 'AW: [{FORUMNAMESHORT}] {TOPICSUBJECT}';
$txt['pbe_notification_reply_body_body'] = '
{MESSAGE}

{SIGNATURE}


------------------------------------
Informationen: {POSTERNAME} hat auf das Thema \'{TOPICSUBJECT}\' im Forum \'{BOARDNAME}\' geantwortet.
Sie können auf diese E-Mail antworten und die Antwort direkt im Thema veröffentlichen.

Weiterführende Verweise zu {FORUMNAMESHORT}:

<*> Um {FORUMNAMESHORT} im Netz aufzurufen, gehen Sie auf:
    {FORUMURL}

<*> Sie können diese Nachricht mit Anhängen und/oder Bildern (falls vorhanden) mittels dieses Links lesen:
    {TOPICLINK}

<*> Hier gelangen Sie zur ersten ungelesenen Nachricht in dieser Diskussion:
    {TOPICLINKNEW}

<*> Sie können das Abonnement hier kündigen:
    {UNSUBSCRIBELINK}

{EMAILREGARDS}';

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
	@description: Full body email notifcation for the first new reply in a topic
*/
$txt['pbe_notification_reply_body_once_subject'] = 'AW: [{FORUMNAMESHORT}] {TOPICSUBJECT}';
$txt['pbe_notification_reply_body_once_body'] = '
{MESSAGE}

{SIGNATURE}


------------------------------------
Informationen: {POSTERNAME} hat auf das Thema \'{TOPICSUBJECT}\' im Forum \'{BOARDNAME}\' geantwortet.
Es könnte weitere Antworten geben, aber Sie werden keine weiteren Benachrichtigungen hierzu erhalten, bevor Sie sie nicht gelesen haben.
Sie können auf diese E-Mail antworten und die Antwort direkt im Thema veröffentlichen.

Weiterführende Verweise zu {FORUMNAMESHORT}:

<*> Um {FORUMNAMESHORT} im Netz aufzurufen, gehen Sie auf:
    {FORUMURL}

<*> Sie können diese Nachricht mit Anhängen und/oder Bildern (falls vorhanden) mittels dieses Links lesen:
    {TOPICLINK}

<*> Hier gelangen Sie zur ersten ungelesenen Nachricht in dieser Diskussion:
    {TOPICLINKNEW}

<*> Sie können das Abonnement hier kündigen:
    {UNSUBSCRIBELINK}

{EMAILREGARDS}';

/*
	@additional_params: pbe_new_pm_body
		SUBJECT: The personal message subject.
		SENDER:  The user name for the member sending the personal message.
		MESSAGE:  The text of the personal message.
		REPLYLINK:  The link to directly access the reply page.
		FORUMNAMESHORT: Short or nickname for the forum
	@description: A notification email sent to the receivers of a personal message
*/
$txt['pbe_new_pm_body_subject'] = 'Neue private Nachricht: {SUBJECT}';
$txt['pbe_new_pm_body_body'] = 'Sie haben in {FORUMNAMESHORT} eine private Nachricht von {SENDER} erhalten.

Die Nachricht lautet:

{MESSAGE}

------------------------------------
Informationen:
Sie können auf diese E-Mail antworten und die Antwort als PN direkt an {SENDER} senden.

Weiterführende Verweise zu {FORUMNAMESHORT}:

<*> Um {FORUMNAMESHORT} im Netz aufzurufen, gehen Sie auf:
    {FORUMURL}

<*> Sie können die private Nachricht mittels dieses Links beantworten:
    {REPLYLINK}

{EMAILREGARDS}';

/*
	@additional_params: new_pm_body_tolist
		SUBJECT: The personal message subject.
		SENDER:  The user name for the member sending the personal message.
		MESSAGE:  The text of the personal message.
		REPLYLINK:  The link to directly access the reply page.
		TOLIST:  The list of users that will receive the personal message.
	@description: A notification email sent to the receivers of a personal message
*/
$txt['pbe_new_pm_body_tolist_subject'] = 'Neue private Nachricht: {SUBJECT}';
$txt['pbe_new_pm_body_tolist_body'] = '{TOLIST} haben in {FORUMNAME} eine private Nachricht von {SENDER} erhalten

Die Nachricht lautet:

{MESSAGE}

------------------------------------
Weiterführende Verweise zu {FORUMNAMESHORT}:

<*> Um {FORUMNAMESHORT} im Netz aufzurufen, gehen Sie auf:
    {FORUMURL}

<*> Sie können die private Nachricht mittels dieses Links beantworten:
    {REPLYLINK}

{EMAILREGARDS}';