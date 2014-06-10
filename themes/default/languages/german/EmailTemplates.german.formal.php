<?php
// Version: 1.0; EmailTemplates

// Since all of these strings are being used in emails, numeric entities should be used.

// Do not translate anything that is between {}, they are used as replacement variables and MUST remain exactly how they are.
//   Additionally do not translate the @additioinal_parmas: line or the variable names in the lines that follow it.  You may
//   translate the description of the variable.  Do not translate @description:, however you may translate the rest of that line.

// Do not use block comments in this file, they will have special meaning.

global $txtBirthdayEmails;

$txt['scheduled_approval_email_topic'] = 'Folgende Themen warten auf Genehmigung:';
$txt['scheduled_approval_email_msg'] = 'Folgende Beiträge warten auf Genehmigung:';
$txt['scheduled_approval_email_attach'] = 'Folgende Dateianhänge warten auf Genehmigung:';
$txt['scheduled_approval_email_event'] = 'Folgende Ereignisse warten auf Genehmigung:';

/*
	@additional_params: resend_activate_message
		REALNAME: The display name for the member receiving the email.
		USERNAME:  The user name for the member receiving the email.
		ACTIVATIONLINK:  The url link to activate the member's account.
		ACTIVATIONCODE:  The code needed to activate the member's account.
		ACTIVATIONLINKWITHOUTCODE: The url to the page where the activation code can be entered.
		FORGOTPASSWORDLINK: The url to the "forgot password" page.
	@description:
*/
$txt['resend_activate_message_subject'] = 'Willkommen im Forum {FORUMNAME}';
$txt['resend_activate_message_body'] = 'Vielen Dank für die Registrierung bei {FORUMNAME}, {USERNAME}. Passwort vergessen? Bitte hier {FORGOTPASSWORDLINK}, ein neues anfordern.

Bevor Sie sich einloggen können, müssen Sie zuerst Ihr Konto aktivieren. Dazu bitte folgenden Link anklicken:

{ACTIVATIONLINK}

Sollten Sie Probleme mit der Aktivierung haben, verwenden Sie bitte diesen Link {ACTIVATIONLINKWITHOUTCODE} und geben Sie dort folgenden Code ein: "{ACTIVATIONCODE}".

{REGARDS}';

/*
	@additional_params: resend_pending_message
		REALNAME: The display name for the member receiving the email.
		USERNAME:  The user name for the member receiving the email.
	@description:
*/
$txt['resend_pending_message_subject'] = 'Willkommen im Forum {FORUMNAME}';
$txt['resend_pending_message_body'] = 'Hallo, {REALNAME}, wir haben Ihre Anfrage zur Registrierung im Forum {FORUMNAME} erhalten.

Der Benutzername mit dem Sie sich registriert haben ist {USERNAME}.

Bevor Sie sich einloggen und das Forum benutzen können, wird Ihre Anfrage geprüft und genehmigt. Wenn dies geschehen ist, erhalten Sie eine weitere E-Mail von dieser Adresse.

{REGARDS}';

/*
	@additional_params: mc_group_approve
		USERNAME: The user name for the member receiving the email.
		GROUPNAME: The name of the membergroup that the user was accepted into.
	@description: The request to join a particular membergroup has been accepted.
*/
$txt['mc_group_approve_subject'] = 'Genehmigung der Gruppenmitgliedschaft';
$txt['mc_group_approve_body'] = '{USERNAME},

Wir freuen uns Ihnen mitteilen zu können, dass Ihre Anfrage der Gruppe "{GROUPNAME}" im Forum {FORUMNAME} beizutreten akzeptiert wurde. Ihr Benutzerkonto wurde um die neue Mitgliedergruppe ergänzt.

{REGARDS}';

/*
	@additional_params: mc_group_reject
		USERNAME: The user name for the member receiving the email.
		GROUPNAME: The name of the membergroup that the user was rejected from.
	@description: The request to join a particular membergroup has been rejected.
*/
$txt['mc_group_reject_subject'] = 'Ablehnung der Gruppenmitgliedschaft';
$txt['mc_group_reject_body'] = '{USERNAME},

Wir bedauern Ihnen mitteilen zu müssen, dass Ihre Anfrage der Gruppe "{GROUPNAME}" im Forum {FORUMNAME} beizutreten abgelehnt wurde.

{REGARDS}';

/*
	@additional_params: mc_group_reject_reason
		USERNAME: The user name for the member receiving the email.
		GROUPNAME: The name of the membergroup that the user was rejected from.
		REASON: Reason for the rejection.
	@description: The request to join a particular membergroup has been rejected with a reason given.
*/
$txt['mc_group_reject_reason_subject'] = 'Ablehnung der Gruppenmitgliedschaft';
$txt['mc_group_reject_reason_body'] = '{USERNAME},

Wir bedauern Ihnen mitteilen zu müssen, dass Ihre Anfrage der Gruppe "{GROUPNAME}" im Forum {FORUMNAME} beizutreten abgelehnt wurde.

Dies erfolgte aus folgenden Gründen: {REASON}

{REGARDS}';

/*
	@additional_params: admin_approve_accept
		NAME: The display name of the member.
		USERNAME: The user name for the member receiving the email.
		PROFILELINK: The URL of the profile page.
		FORGOTPASSWORDLINK: The URL of the "forgot password" page.
	@description:
*/
$txt['admin_approve_accept_subject'] = 'Willkommen im Forum {FORUMNAME}';
$txt['admin_approve_accept_body'] = 'Willkommen, {NAME}!

Ihr Benutzerkonto wurde vom Administrator aktiviert. Sie können sich jetzt einloggen und Beiträge schreiben. Ihr Benutzername lautet: {USERNAME}

Wenn Sie Ihr Passwort vergessen haben, können Sie es unter folgenden Link zurücksetzen: {FORGOTPASSWORDLINK}

{REGARDS}';

/*
	@additional_params: admin_approve_activation
		USERNAME: The user name for the member receiving the email.
		ACTIVATIONLINK:  The url link to activate the member's account.
		ACTIVATIONLINKWITHOUTCODE: The url to the page where the activation code can be entered.
		ACTIVATIONCODE: The activation code.
	@description:
*/
$txt['admin_approve_activation_subject'] = 'Willkommen im Forum {FORUMNAME}';
$txt['admin_approve_activation_body'] = 'Willkommen, {USERNAME}!

Ihr Benutzerkonto im Forum {FORUMNAME} wurde vom Administrator genehmigt und muss jetzt aktiviert werden, bevor Sie Beiträge schreiben können. Bitte benutze folgenden Link, um Ihr Benutzerkonto zu aktivieren:

{ACTIVATIONLINK}

Sollten Sie Probleme mit der Aktivierung haben, verwenden Sie bitte diesen Link {ACTIVATIONLINKWITHOUTCODE} und geben Sie dort folgenden Code ein: "{ACTIVATIONCODE}".

{REGARDS}';

/*
	@additional_params: admin_approve_reject
		USERNAME: The user name for the member receiving the email.
	@description:
*/
$txt['admin_approve_reject_subject'] = 'Registrierung abgelehnt';
$txt['admin_approve_reject_body'] = '{USERNAME},

Ihre Anfrage dem Forum {FORUMNAME} beizutreten wurde abgelehnt.

{REGARDS}';

/*
	@additional_params: admin_approve_delete
		USERNAME: The user name for the member receiving the email.
	@description:
*/
$txt['admin_approve_delete_subject'] = 'Profil gelöscht';
$txt['admin_approve_delete_body'] = '{USERNAME},

Ihr Profil im Forum {FORUMNAME} wurde gelöscht. Dies ist eventuell passiert, weil Sie Ihr Profil nie aktiviert haben. War dies der Fall, können Sie sich jederzeit erneut registrieren.

{REGARDS}';

/*
	@additional_params: admin_approve_remind
		USERNAME: The user name for the member receiving the email.
		ACTIVATIONLINK:  The url link to activate the member's account.
		ACTIVATIONLINKWITHOUTCODE: The url to the page where the activation code can be entered.
		ACTIVATIONCODE: The activation code.
	@description:
*/
$txt['admin_approve_remind_subject'] = 'Registrierungserinnerung';
$txt['admin_approve_remind_body'] = '{USERNAME},
Sie haben Ihr Benutzerkonto im Forum {FORUMNAME} noch nicht aktiviert.

Bitte benutzen Sie folgenden Link, um Ihr Benutzerkonto zu aktivieren:
{ACTIVATIONLINK}

Sollten Sie Probleme mit der Aktivierung haben, verwenden Sie bitte diesen Link {ACTIVATIONLINKWITHOUTCODE} und geben Sie dort folgenden Code ein: "{ACTIVATIONCODE}".

{REGARDS}';

/*
	@additional_params:
		USERNAME: The user name for the member receiving the email.
		ACTIVATIONLINK:  The url link to activate the member's account.
		ACTIVATIONLINKWITHOUTCODE: The url to the page where the activation code can be entered.
		ACTIVATIONCODE: The activation code.
	@description:
*/
$txt['admin_register_activate_subject'] = 'Willkommen im Forum {FORUMNAME}';
$txt['admin_register_activate_body'] = 'Sie sind jetzt mit einem Benutzerkonto im Forum {FORUMNAME} registriert. Ihr Benutzername lautet {USERNAME} und Ihr Passwort lautet {PASSWORD}.

Bevor Sie sich einloggen können, müssen Sie zuerst Ihr Konto aktivieren. Dazu folgen Sie bitte folgendem Link:

{ACTIVATIONLINK}

Sollten Sie Probleme mit der Aktivierung haben, verwenden Sie bitte diesen Link {ACTIVATIONLINKWITHOUTCODE} und geben Sie dort folgenden Code ein "{ACTIVATIONCODE}".
{REGARDS}';

$txt['admin_register_immediate_subject'] = 'Willkommen im Forum {FORUMNAME}';
$txt['admin_register_immediate_body'] = 'Sie sind jetzt mit einem Benutzerkonto im Forum {FORUMNAME} registriert. Ihr Benutzername lautet {USERNAME} und Ihr Passwort lautet {PASSWORD}.

{REGARDS}';

/*
	@additional_params: new_announcement
		TOPICSUBJECT: The subject of the topic being announced.
		MESSAGE: The message body of the first post of the announced topic.
		TOPICLINK: A link to the topic being announced.
	@description:
*/
$txt['new_announcement_subject'] = 'Neue Ankündigung: {TOPICSUBJECT}';
$txt['new_announcement_body'] = '{MESSAGE}

Um diese Ankündigungen abzubestellen, loggen Sie sich bitte im Forum ein und deaktivieren die Option "E-Mail Benachrichtigung bei neuen Ankündigungen schicken" in Ihrem Profil.

Sie können die komplette Ankündigung unter folgendem Link lesen:
{TOPICLINK}

{REGARDS}';

/*
	@additional_params: notify_boards_once_body
		TOPICSUBJECT: The subject of the topic causing the notification
		TOPICLINK: A link to the topic.
		MESSAGE: This is the body of the message.
		UNSUBSCRIBELINK: Link to unsubscribe from notifications.
	@description:
*/
$txt['notify_boards_once_body_subject'] = 'Neues Thema: {TOPICSUBJECT}';
$txt['notify_boards_once_body_body'] = 'Ein neues Thema, \'{TOPICSUBJECT}\', wurde in einem Forum erstellt, welches Sie beobachten.

Sie finden es unter
{TOPICLINK}


Es könnten mehrere Themen erstellt worden sein, Sie erhalten jedoch erst weitere Benachrichtigungen, wenn Sie das Forum besucht haben.

Der Titel des Themas lautet:
{MESSAGE}

Um die Benachrichtigungen über neue Themen aus diesem Board abzubestellen, klicken Sie bitte auf folgenden Link:
{UNSUBSCRIBELINK}

{REGARDS}';

/*
	@additional_params: notify_boards_once
		TOPICSUBJECT: The subject of the topic causing the notification
		TOPICLINK: A link to the topic.
		UNSUBSCRIBELINK: Link to unsubscribe from notifications.
	@description:
*/
$txt['notify_boards_once_subject'] = 'Neues Thema: {TOPICSUBJECT}';
$txt['notify_boards_once_body'] = 'Ein neues Thema, \'{TOPICSUBJECT}\', wurde in einem Forum erstellt, welches Sie beobachten.

Sie finden es unter
{TOPICLINK}

Es könnten mehrere Themen erstellt worden sein, Sie erhalten jedoch erst weitere Benachrichtigungen, wenn Sie das Forum besucht haben.

Um die Benachrichtigungen über neue Themen aus diesem Board abzubestellen, klicken Sie bitte auf folgenden Link:
{UNSUBSCRIBELINK}

{REGARDS}';

/*
	@additional_params: notify_boards_body
		TOPICSUBJECT: The subject of the topic causing the notification
		TOPICLINK: A link to the topic.
		MESSAGE: This is the body of the message.
		UNSUBSCRIBELINK: Link to unsubscribe from notifications.
	@description:
*/
$txt['notify_boards_body_subject'] = 'Neues Thema: {TOPICSUBJECT}';
$txt['notify_boards_body_body'] = 'Ein neues Thema, \'{TOPICSUBJECT}\', wurde in einem Forum erstellt, welches Sie beobachten.

Sie finden es unter
{TOPICLINK}

Der Inhalt des Themas lautet:
{MESSAGE}

Um die Benachrichtigungen über neue Themen aus diesem Board abzubestellen, klicken Sie bitte auf folgenden Link:
{UNSUBSCRIBELINK}

{REGARDS}';

/*
	@additional_params: notify_boards
		TOPICSUBJECT: The subject of the topic causing the notification
		TOPICLINK: A link to the topic.
		UNSUBSCRIBELINK: Link to unsubscribe from notifications.
	@description:
*/
$txt['notify_boards_subject'] = 'Neues Thema: {TOPICSUBJECT}';
$txt['notify_boards_body'] = 'Ein neues Thema, \'{TOPICSUBJECT}\', wurde in einem Forum erstellt, welches Sie beobachten.

Sie finden es unter
{TOPICLINK}

Um die Benachrichtigungen über neue Themen aus diesem Board abzubestellen, klicken Sie bitte auf folgenden Link:
{UNSUBSCRIBELINK}

{REGARDS}';

/*
	@additional_params: request_membership
		RECPNAME: The name of the person receiving the email
		APPYNAME: The name of the person applying for group membership
		GROUPNAME: The name of the group being applied to.
		REASON: The reason given by the applicant for wanting to join the group.
		MODLINK: Link to the group moderation page.
	@description:
*/
$txt['request_membership_subject'] = 'Neue Gruppenbeitrittsanfrage';
$txt['request_membership_body'] = '{RECPNAME},

{APPYNAME} hat eine Mitgliedschaft für die Gruppe "{GROUPNAME}" angefordert. Der Benutzer hat folgenden Grund angegeben:

{REASON}

Sie können diese Anfrage genehmigen oder ablehnen, indem Sie den folgenden Link besuchen:

{MODLINK}

{REGARDS}';

/*
	@additional_params: scheduled_approval
		REALNAME: The real (display) name of the person receiving the email.
		PROFILE_LINK: Link to profile of member receiving email where can renew.
		SUBSCRIPTION: Name of the subscription.
		END_DATE: Date it expires.
	@description:
*/
$txt['paid_subscription_reminder_subject'] = 'Das Abonnement im Forum {FORUMNAME} läuft ab';
$txt['paid_subscription_reminder_body'] = '{REALNAME},

Eines Ihrer Abonnements im Forum {FORUMNAME} läuft in der nächsten Zeit ab. Wenn Sie beim Bestellen des Abonnements die automatische Erneuerung aktiviert haben, müssen Sie nichts weiter tun; ansonsten müssen Sie das Abonnement nochmal durchführen, wenn Sie dies möchten. Lesen Sie die folgenden Details:

Name des Abonnements: {SUBSCRIPTION}
Läuft ab: {END_DATE}

Um Ihre Abonnements zu ändern, besuchen Sie bitte folgenden Link:
{PROFILE_LINK}

{REGARDS}';

/*
	@additional_params: activate_reactivate
		ACTIVATIONLINK:  The url link to reactivate the member's account.
		ACTIVATIONCODE:  The code needed to reactivate the member's account.
		ACTIVATIONLINKWITHOUTCODE: The url to the page where the activation code can be entered.
	@description:
*/
$txt['activate_reactivate_subject'] = 'Willkommen zurück im Forum {FORUMNAME}';
$txt['activate_reactivate_body'] = 'Um Ihre E-Mail-Adresse zu überprüfen, wurde Ihr Profil deaktiviert. Klicken Sie den folgenden Link, um es wieder zu aktivieren:
{ACTIVATIONLINK}

Sollten Sie Probleme mit der Aktivierung haben, verwenden Sie bitte diesen Link {ACTIVATIONLINKWITHOUTCODE} und geben Sie dort folgenden Code ein "{ACTIVATIONCODE}".

{REGARDS}';

/*
	@additional_params: forgot_password
		REALNAME: The real (display) name of the person receiving the reminder.
		REMINDLINK: The link to reset the password.
		IP: The IP address of the requester.
		MEMBERNAME:
	@description:
*/
$txt['forgot_password_subject'] = 'Neues Passwort für das Forum {FORUMNAME}';
$txt['forgot_password_body'] = 'Hallo {REALNAME},
Diese E-Mail wurde versandt, weil die Funktion \'Passwort vergessen\' auf Ihr Benutzerkonto angewendet worden ist. Um ein neues Passwort festzulegen, klicken Sie bitte auf den folgenden Link:
{REMINDLINK}

IP: {IP}
Benutzername: {MEMBERNAME}

{REGARDS}';

/*
	@additional_params: forgot_password
		REALNAME: The real (display) name of the person receiving the reminder.
		IP: The IP address of the requester.
		OPENID: The members OpenID identity.
	@description:
*/
$txt['forgot_openid_subject'] = 'OpenID Erinnerung für {FORUMNAME}';
$txt['forgot_openid_body'] = 'Hallo {REALNAME},
Diese E-Mail wurde versandt, weil die Funktion \'OpenID vergessen\' auf Ihr Benutzerkonto angewendet worden ist. Dies ist die OpenID, die mit ihrem Benutzerkonto verbunden ist:
{OPENID}

IP: {IP}
Benutzername: {MEMBERNAME}

{REGARDS}';

/*
	@additional_params: scheduled_approval
		REALNAME: The real (display) name of the person receiving the email.
		BODY: The generated body of the mail.
	@description:
*/
$txt['scheduled_approval_subject'] = 'Zusammenfassung der Beiträge, die eine Genehmigung im Forum {FORUMNAME} erwarten';
$txt['scheduled_approval_body'] = '{REALNAME},

Diese E-Mail enthält eine Zusammenfassung der Beiträge, die im Forum {FORUMNAME} eine Genehmigung erwarten.

{BODY}

Bitte loggen Sie sich im Forum ein, um die einzelnen Beiträge zu prüfen.
{SCRIPTURL}

{REGARDS}';

/*
	@additional_params: send_topic
		TOPICSUBJECT: The subject of the topic being sent.
		SENDERNAME: The name of the member sending the topic.
		RECPNAME: The name of the person receiving the email.
		TOPICLINK: A link to the topic being sent.
	@description:
*/
$txt['send_topic_subject'] = 'Thema: {TOPICSUBJECT} (Von: {SENDERNAME})';
$txt['send_topic_body'] = 'Hallo {RECPNAME},
Bitte schauen Sie sich das Thema "{TOPICSUBJECT}" im Forum {FORUMNAME} an. Um dorthin zu gelangen, klicken Sie bitte auf folgenden Link:

{TOPICLINK}

Vielen Dank,

{SENDERNAME}';

/*
	@additional_params: send_topic_comment
		TOPICSUBJECT: The subject of the topic being sent.
		SENDERNAME: The name of the member sending the topic.
		RECPNAME: The name of the person receiving the email.
		TOPICLINK: A link to the topic being sent.
		COMMENT: A comment left by the sender.
	@description:
*/
$txt['send_topic_comment_subject'] = 'Thema: {TOPICSUBJECT} (Von: {SENDERNAME})';
$txt['send_topic_comment_body'] = 'Hallo {RECPNAME},
Bitte schauen Sie sich das Thema "{TOPICSUBJECT}" im Forum {FORUMNAME} an. Um dorthin zu gelangen, klicken Sie bitte auf folgenden Link:

{TOPICLINK}

Es wurde ebenfalls ein Kommentar dazu abgegeben:
{COMMENT}

Vielen Dank,

{SENDERNAME}';

/*
	@additional_params: send_email
		EMAILSUBJECT: The subject the user wants to email.
		EMAILBODY: The body the user wants to email.
		SENDERNAME: The name of the member sending the email.
		RECPNAME: The name of the person receiving the email.
	@description:
*/
$txt['send_email_subject'] = '{EMAILSUBJECT}';
$txt['send_email_body'] = '{EMAILBODY}';

/*
	@additional_params: report_to_moderator
		TOPICSUBJECT: The subject of the reported post.
		POSTERNAME: The report post's author's name.
		REPORTERNAME: The name of the person reporting the post.
		TOPICLINK: The url of the post that is being reported.
		REPORTLINK: The url of the moderation center report.
		COMMENT: The comment left by the reporter, hopefully to explain why they are reporting the post.
	@description: When a user reports a post this email is sent out to moderators and admins of that board.
*/
$txt['report_to_moderator_subject'] = 'Gemeldeter Beitrag: {TOPICSUBJECT} von {POSTERNAME}';
$txt['report_to_moderator_body'] = 'Der Beitrag "{TOPICSUBJECT}" von {POSTERNAME} wurde von {REPORTERNAME} in einem von Ihnen moderierten Board gemeldet:

Das Thema: {TOPICLINK}
Moderationszentrum: {REPORTLINK}

Der Benutzer hat folgenden Kommentar dazu geschrieben:
{COMMENT}

{REGARDS}';

/*
	@additional_params: change_password
		USERNAME: The user name for the member receiving the email.
		PASSWORD: The password for the member.
	@description:
*/
$txt['change_password_subject'] = 'Details zum neuen Passwort';
$txt['change_password_body'] = 'Hallo, {USERNAME}!

Ihre Daten zum Anmelden im Forum {FORUMNAME} wurden geändert und das Passwort zurückgesetzt. Im Folgenden sind Ihre neuen Login-Daten aufgelistet.

Ihr Benutzername lautet "{USERNAME}" und Ihr Passwort lautet "{PASSWORD}".

Sie können diese Daten ändern, wenn Sie nach dem Anmelden Ihr Profil editieren. Sie können nach dem Anmelden auch folgenden Link besuchen:
{SCRIPTURL}?action=profile

{REGARDS}';

/*
	@additional_params: register_activate
		REALNAME: The display name for the member receiving the email.
		USERNAME: The user name for the member receiving the email.
		PASSWORD: The password for the member.
		ACTIVATIONLINK:  The url link to reactivate the member's account.
		ACTIVATIONLINKWITHOUTCODE: The url to the page where the activation code can be entered.
		ACTIVATIONCODE:  The code needed to reactivate the member's account.
		FORGOTPASSWORDLINK: The url to the "forgot password" page.
	@description:
*/
$txt['register_activate_subject'] = 'Sie sind jetzt mit einem Benutzerkonto im Forum {FORUMNAME} registriert, {REALNAME}!
Ihr Benutzername ist {USERNAME}. Wenn Sie Ihr Passwort vergessen haben, können Sie es unter folgenden Link zurücksetzen: {FORGOTPASSWORDLINK}

Bevor Sie sich anmelden können, müssen Sie zuerst Ihr Benutzerkonto aktivieren. Um dies zu tun, klicken Sie bitte auf folgenden Link:

{ACTIVATIONLINK}

Sollten Sie Probleme mit der Aktivierung haben, verwenden Sie bitte diesen Link {ACTIVATIONLINKWITHOUTCODE} und geben Sie dort folgenden Code ein "{ACTIVATIONCODE}".

{REGARDS}';

/*
	@additional_params: register_activate
		REALNAME: The display name for the member receiving the email.
		USERNAME: The user name for the member receiving the email.
		OPENID: The openID identity for the member.
		ACTIVATIONLINK:  The url link to reactivate the member's account.
		ACTIVATIONLINKWITHOUTCODE: The url to the page where the activation code can be entered.
		ACTIVATIONCODE:  The code needed to reactivate the member's account.
	@description:
*/
$txt['register_openid_activate_subject'] = 'Willkommen im Forum {FORUMNAME}';
$txt['register_openid_activate_body'] = 'Sie sind jetzt mit einem Benutzerkonto im Forum {FORUMNAME} registriert, {REALNAME}!

Ihr Benutzername ist {USERNAME}.
Sie haben sich entschieden, sich mit der folgenden OpenID zu authentifizieren:
{OPENID}

Bevor Sie sich einloggen können, müssen Sie zuerst Ihr Benutzerkonto aktivieren. Um dies zu tun, klicken Sie bitte auf folgenden Link:

{ACTIVATIONLINK}

Sollten Sie Probleme mit der Aktivierung haben, verwenden Sie bitte diesen Link {ACTIVATIONLINKWITHOUTCODE} und geben Sie dort folgenden Code ein: "{ACTIVATIONCODE}".

{REGARDS}';

/*
	@additional_params: register_coppa
		REALNAME: The display name for the member receiving the email.
		USERNAME: The user name for the member receiving the email.
		PASSWORD: The password for the member.
		COPPALINK:  The url link to the coppa form.
		FORGOTPASSWORDLINK: The url to the "forgot password" page.
	@description:
*/
// translator note: COPPA = a kid = no formal wording required. ;)
$txt['register_coppa_subject'] = 'Willkommen im Forum {FORUMNAME}';
$txt['register_coppa_body'] = 'Du bist jetzt mit einem Benutzerkonto im Forum {FORUMNAME} registriert, {REALNAME}!

Dein Benutzername lautet {USERNAME} und das Passwort lautet {PASSWORD}. Wenn du dein Passwort vergessen hast, kannst du es unter folgendem Link zurücksetzen: {FORGOTPASSWORDLINK}

Bevor du dich anmelden kannst, benötigt der Administrator das Einverständnis deiner Eltern/Erziehungsberechtigten. Weitere Informationen findest du hier:

{COPPALINK}

{REGARDS}';

/*
	@additional_params: register_coppa
		REALNAME: The display name for the member receiving the email.
		USERNAME: The user name for the member receiving the email.
		OPENID: The openID identity for the member.
		COPPALINK:  The url link to the coppa form.
	@description:
*/
	// translator note: COPPA = a kid = no formal wording required. ;)
$txt['register_openid_coppa_subject'] = 'Willkommen im Forum {FORUMNAME}';
$txt['register_openid_coppa_body'] = 'Du bist jetzt mit einem Benutzerkonto im Forum {FORUMNAME} registriert, {REALNAME}!

Dein Benutzername lautet {USERNAME}.

Du hast dich entschieden, sich mit der folgenden OpenID zu authentifizieren:
{OPENID}

Bevor du dich anmelden kannst, benötigt der Administrator das Einverständnis deiner Eltern/Erziehungsberechtigten. Weitere Informationen findest du hier:

{COPPALINK}

{REGARDS}';

/*
	@additional_params: register_immediate
		REALNAME: The display name for the member receiving the email.
		USERNAME: The user name for the member receiving the email.
		PASSWORD: The password for the member.
		FORGOTPASSWORDLINK: The url to the "forgot password" page.
	@description:
*/
$txt['register_immediate_subject'] = 'Willkommen im Forum {FORUMNAME}';
$txt['register_immediate_body'] = 'Sie sind jetzt mit einem Benutzerkonto im Forum {FORUMNAME} registriert, {REALNAME}!

Ihr Benutzername lautet {USERNAME}. Wenn Sie Ihr Passwort vergessen haben, können Sie es unter folgendem Link zurücksetzen: {FORGOTPASSWORDLINK}

{REGARDS}';

/*
	@additional_params: register_immediate
		REALNAME: The display name for the member receiving the email.
		USERNAME: The user name for the member receiving the email.
		OPENID: The openID identity for the member.
	@description:
*/
$txt['register_openid_immediate_subject'] = 'Willkommen im Forum {FORUMNAME}';
$txt['register_openid_immediate_body'] = 'Sie sind jetzt mit einem Benutzerkonto im Forum {FORUMNAME} registriert, {REALNAME}!

Ihr Benutzername ist {USERNAME}.

Sie haben entschieden, sich mit der folgenden OpenID zu authentifizieren:
{OPENID}

Sie können Ihr Profil aktualisieren, indem Sie nach dem Anmelden die folgende Seite besuchen:

{SCRIPTURL}?action=profile

{REGARDS}';

/*
	@additional_params: register_pending
		REALNAME: The display name for the member receiving the email.
		USERNAME: The user name for the member receiving the email.
		PASSWORD: The password for the member.
		FORGOTPASSWORDLINK: The url to the "forgot password" page.
	@description:
*/
$txt['register_pending_subject'] = 'Willkommen im Forum {FORUMNAME}';
$txt['register_pending_body'] = 'Hallo, {REALNAME}, wir haben Ihre Anfrage zur Registrierung im Forum {FORUMNAME} erhalten.

Ihr Benutzername, mit dem Sie sich registriert haben, lautet {USERNAME}. Wenn Sie Ihr Passwort vergessen haben, können Sie es unter folgendem Link zurücksetzen: {FORGOTPASSWORDLINK}

Bevor Sie sich anmelden und das Forum benutzen können, muss Ihre Registrierung genehmigt werden. Sobald dies geschehen ist, erhalten Sie eine weitere E-Mail von dieser Adresse.

{REGARDS}';

/*
	@additional_params: register_pending
		REALNAME: The display name for the member receiving the email.
		USERNAME: The user name for the member receiving the email.
		OPENID: The openID identity for the member.
	@description:
*/
$txt['register_openid_pending_subject'] = 'Willkommen im Forum {FORUMNAME}';
$txt['register_openid_pending_body'] = 'Ihre Registrierungsanfrage im Forum {FORUMNAME} haben wir erhalten, {REALNAME}.

Ihr Benutzername, mit dem Sie sich registriert haben, ist {USERNAME}.

Sie haben entschieden sich mit der folgenden OpenID zu authentifizieren:
{OPENID}

Bevor Sie sich anmelden und das Forum benutzen können, muss Ihre Registrierung genehmigt werden. Sobald dies geschehen ist, erhalten Sie eine weitere E-Mail von dieser Adresse.

{REGARDS}';

/*
	@additional_params: notification_reply
		TOPICSUBJECT:
		POSTERNAME:
		TOPICLINK:
		UNSUBSCRIBELINK:
	@description:
*/
$txt['notification_reply_subject'] = 'Antwort im Thema: {TOPICSUBJECT}';
$txt['notification_reply_body'] = 'In diesem Thema wurde eine Antwort von {POSTERNAME} geschrieben.

Lesen Sie die Antwort unter: {TOPICLINK}

Um die Benachrichtigungen für dieses Thema abzubestellen, klicken Sie auf folgenden Link: {UNSUBSCRIBELINK}

{REGARDS}';

/*
	@additional_params: notification_reply_body
		TOPICSUBJECT:
		POSTERNAME:
		TOPICLINK:
		UNSUBSCRIBELINK:
		MESSAGE:
	@description:
*/
$txt['notification_reply_body_subject'] = 'Antwort im Thema: {TOPICSUBJECT}';
$txt['notification_reply_body_body'] = 'In diesem Thema wurde eine Antwort von {POSTERNAME} geschrieben.

Lesen Sie die Antwort unter: {TOPICLINK}

Um die Benachrichtigungen für dieses Thema abzubestellen, klicken Sie auf folgenden Link: {UNSUBSCRIBELINK}

Der Text der Antwort lautet:
{MESSAGE}

{REGARDS}';

/*
	@additional_params: notification_reply_once
		TOPICSUBJECT:
		POSTERNAME:
		TOPICLINK:
		UNSUBSCRIBELINK:
	@description:
*/
$txt['notification_reply_once_subject'] = 'Antwort im Thema: {TOPICSUBJECT}';
$txt['notification_reply_once_body'] = 'In einem Thema, das Sie beobachten, wurde eine Antwort von {POSTERNAME} geschrieben.

Lesen Sie die Antwort unter: {TOPICLINK}

Um die Benachrichtigungen für dieses Thema abzubestellen, klicken Sie auf folgenden Link: {UNSUBSCRIBELINK}

Es könnten mehrere Antworten geschrieben worden sein, Sie erhalten jedoch erst weitere Benachrichtigungen, wenn Sie das Thema gelesen haben.

{REGARDS}';

/*
	@additional_params: notification_reply_body_once
		TOPICSUBJECT:
		POSTERNAME:
		TOPICLINK:
		UNSUBSCRIBELINK:
		MESSAGE:
	@description:
*/
$txt['notification_reply_body_once_subject'] = 'Antwort im Thema: {TOPICSUBJECT}';
$txt['notification_reply_body_once_body'] = 'In einem Thema, das Sie beobachten, wurde eine Antwort von {POSTERNAME} geschrieben.

Lesen Sie die Antwort unter: {TOPICLINK}

Um die Benachrichtigungen für dieses Thema abzubestellen, klicken Sie auf folgenden Link: {UNSUBSCRIBELINK}

Der Text der Antwort lautet:
{MESSAGE}

Es könnten mehrere Antworten geschrieben worden sein, Sie erhalten jedoch erst weitere Benachrichtigungen, wenn Sie das Thema gelesen haben.

{REGARDS}';

/*
	@additional_params: notification_sticky
	@description:
*/
$txt['notification_sticky_subject'] = 'Thema angeheftet: {TOPICSUBJECT}';
$txt['notification_sticky_body'] = 'Ein von Ihnen beobachtetes Thema wurde von {POSTERNAME} angeheftet.

Lesen Sie das Thema unter: {TOPICLINK}

Um die Benachrichtigungen für dieses Thema abzubestellen, klicken Sie auf folgenden Link: {UNSUBSCRIBELINK}

{REGARDS}';

/*
	@additional_params: notification_lock
	@description:
*/
$txt['notification_lock_subject'] = 'Thema geschlossen: {TOPICSUBJECT}';
$txt['notification_lock_body'] = 'Ein von Ihnen beobachtetes Thema wurde von {POSTERNAME} geschlossen.

Lesen Sie das Thema unter: {TOPICLINK}

Um die Benachrichtigungen für dieses Thema abzubestellen, klicken Sie auf folgenden Link: {UNSUBSCRIBELINK}

{REGARDS}';

/*
	@additional_params: notification_unlock
	@description:
*/
$txt['notification_unlock_subject'] = 'Thema geöffnet: {TOPICSUBJECT}';
$txt['notification_unlock_body'] = 'Ein von Ihnen beobachtetes Thema wurde von {POSTERNAME} wieder geöffnet.

Lesen Sie das Thema unter: {TOPICLINK}

Um die Benachrichtigungen für dieses Thema abzubestellen, klicken Sie auf folgenden Link: {UNSUBSCRIBELINK}

{REGARDS}';

/*
	@additional_params: notification_remove
	@description:
*/
$txt['notification_remove_subject'] = 'Thema gelöscht: {TOPICSUBJECT}';
$txt['notification_remove_body'] = 'Ein von Ihnen beobachtetes Thema wurde von {POSTERNAME} gelöscht.

{REGARDS}';

/*
	@additional_params: notification_move
	@description:
*/
$txt['notification_move_subject'] = 'Thema verschoben: {TOPICSUBJECT}';
$txt['notification_move_body'] = 'Ein von Ihnen beobachtetes Thema wurde von {POSTERNAME} in ein anderes Forum verschoben.

Lesen Sie das Thema unter: {TOPICLINK}

Um die Benachrichtigungen für dieses Thema abzubestellen, klicken Sie auf folgenden Link: {UNSUBSCRIBELINK}

{REGARDS}';

/*
	@additional_params: notification_merged
	@description:
*/
$txt['notification_merge_subject'] = 'Thema zusammengeführt: {TOPICSUBJECT}';
$txt['notification_merge_body'] = 'Ein von Ihnen beobachtetes Thema wurde von {POSTERNAME} mit einem anderen Thema zusammengeführt.

Lesen Sie das neue, zusammengeführte Thema unter: {TOPICLINK}

Um die Benachrichtigungen für dieses Thema abzubestellen, klicken Sie auf folgenden Link: {UNSUBSCRIBELINK}

{REGARDS}';

/*
	@additional_params: notification_split
	@description:
*/
$txt['notification_split_subject'] = 'Thema geteilt: {TOPICSUBJECT}';
$txt['notification_split_body'] = 'Ein von Ihnen beobachtetes Thema wurde von {POSTERNAME} in zwei oder mehr Themen geteilt.

Lesen Sie das verbliebene Thema unter: {TOPICLINK}

Um die Benachrichtigungen für dieses Thema abzubestellen, klicken Sie auf folgenden Link: {UNSUBSCRIBELINK}

{REGARDS}';

/*
	@additional_params: admin_notify
		USERNAME:
		PROFILELINK:
	@description:
*/
$txt['admin_notify_subject'] = 'Ein neues Mitglied hat sich angemeldet';
$txt['admin_notify_body'] = '{USERNAME} hat sich als neues Mitglied in Ihrem Forum angemeldet. Klicken Sie auf den folgenden Link, um das Profil zu betrachten.
{PROFILELINK}

{REGARDS}';

/*
	@additional_params: admin_notify_approval
		USERNAME:
		PROFILELINK:
		APPROVALLINK:
	@description:
*/
$txt['admin_notify_approval_subject'] = 'Ein neues Mitglied hat sich angemeldet';
$txt['admin_notify_approval_body'] = '{USERNAME} hat sich als neues Mitglied in Ihrem Forum angemeldet. Klicken Sie auf den folgenden Link, um das Profil zu betrachten.
{PROFILELINK}

Bevor dieses Mitglied Beiträge schreiben kann, muss das Profil zuerst genehmigt werden. Klicken Sie auf den folgenden Link, um auf die Aktivierungsseite zu gelangen.
{APPROVALLINK}

{REGARDS}';

/*
	@additional_params: admin_attachments_full
		REALNAME:
	@description:
*/
$txt['admin_attachments_full_subject'] = 'Dringend! Das Anhänge-Verzeichnis ist fast voll';
$txt['admin_attachments_full_body'] = '{REALNAME},

Das Verzeichnis für Anhänge des Forums {FORUMNAME} ist fast voll. Bitte kontrollieren Sie die Einstellungen, um das Problem zu beseitigen.

Wenn das Anhänge-Verzeichnis die max. Größe erreicht hat, können die Benutzer keine weiteren Dateianhänge oder Benutzerbilder hochladen (wenn aktiviert).

{REGARDS}';

/*
	@additional_params: paid_subscription_refund
		NAME: Subscription title.
		REALNAME: Recipients name
		REFUNDUSER: Username who took out the subscription.
		REFUNDNAME: User's display name who took out the subscription.
		DATE: Today's date.
		PROFILELINK: Link to members profile.
	@description:
*/
$txt['paid_subscription_refund_subject'] = 'Zurückgezahltes Abonnement';
$txt['paid_subscription_refund_body'] = '{REALNAME},

Ein Benutzer hat ein bezahltes Abonnement zurückerstattet bekommen. Hier sind weitere Details des Abonnements:

	Abonnement: {NAME}
	Benutzername: {REFUNDNAME} ({REFUNDUSER})
	Datum: {DATE}

Sie können das Profil des Benutzers anschauen, in dem Sie den folgenden Link anklicken:
{PROFILELINK}

{REGARDS}';

/*
	@additional_params: paid_subscription_new
		NAME: Subscription title.
		REALNAME: Recipients name
		SUBEMAIL: Email address of the user who took out the subscription
		SUBUSER: Username who took out the subscription.
		SUBNAME: User's display name who took out the subscription.
		DATE: Today's date.
		PROFILELINK: Link to members profile.
	@description:
*/
$txt['paid_subscription_new_subject'] = 'Neues bezahltes Abonnement';
$txt['paid_subscription_new_body'] = '{REALNAME},

Ein Benutzer hat ein neues Abonnement bestellt. Hier sind die Details zu der Bestellung:

	Abonnement: {NAME}
	Benutzername: {SUBNAME} ({SUBUSER})
	E-Mail-Adresse: {SUBEMAIL}
	Preis: {PRICE}
	Datum: {DATE}

Sie können das Profil des Benutzers anschauen, in dem Sie den folgenden Link anklicken:
{PROFILELINK}

{REGARDS}';

/*
	@additional_params: paid_subscription_error
		ERROR: Error message.
		REALNAME: Recipients name
	@description:
*/
$txt['paid_subscription_error_subject'] = 'Bezahltes Abonnement. Ein Fehler ist aufgetreten';
$txt['paid_subscription_error_body'] = '{REALNAME},

Der folgende Fehler trat während der Verarbeitung eines bezahlten Abonnements auf
---------------------------------------------------------------
{ERROR}

{REGARDS}';

/*
	@additional_params: new_pm
		SUBJECT: The personal message subject.
		SENDER:  The user name for the member sending the personal message.
		READLINK:  The link to directly access the read page.
		REPLYLINK:  The link to directly access the reply page.
	@description: A notification email sent to the receivers of a personal message
*/
$txt['new_pm_subject'] = 'Neue private Nachricht: {SUBJECT}';
$txt['new_pm_body'] = 'Sie haben eine private Nachricht von {SENDER} im Forum {FORUMNAME} erhalten.

WICHTIG: Das ist nur eine Benachrichtigung - bitte antworten Sie nicht auf diese E-Mail!

Die Nachricht lesen: {READLINK}

Auf die Nachricht antworten: {REPLYLINK}';

/*
	@additional_params: new_pm_body
		SUBJECT: The personal message subject.
		SENDER:  The user name for the member sending the personal message.
		MESSAGE:  The text of the personal message.
		REPLYLINK:  The link to directly access the reply page.
	@description: A notification email sent to the receivers of a personal message
*/
$txt['new_pm_body_subject'] = 'Neue private Nachricht: {SUBJECT}';
$txt['new_pm_body_body'] = 'Sie haben eine private Nachricht von {SENDER} im Forum {FORUMNAME} erhalten.

WICHTIG: Das ist nur eine Benachrichtigung - bitte antworten Sie nicht auf diese E-Mail!

Diese Nachricht wurde Ihnen gesendet:

{MESSAGE}

Auf die Nachricht antworten: {REPLYLINK}';

/*
	@additional_params: new_pm_tolist
		SUBJECT: The personal message subject.
		SENDER:  The user name for the member sending the personal message.
		READLINK:  The link to directly access the read page.
		REPLYLINK:  The link to directly access the reply page.
		TOLIST:  The list of users that will receive the personal message.
	@description: A notification email sent to the receivers of a personal message
*/
$txt['new_pm_tolist_subject'] = 'Neue private Nachricht: {SUBJECT}';
$txt['new_pm_tolist_body'] = '{TOLIST} hat eine private Nachricht von {SENDER} im Forum {FORUMNAME} erhalten.

WICHTIG: Das ist nur eine Benachrichtigung - bitte antworten Sie nicht auf diese E-Mail!

Die Nachricht lesen: {READLINK}

Auf die Nachricht antworten (nur dem Absender): {REPLYLINK}';

/*
	@additional_params: new_pm_body_tolist
		SUBJECT: The personal message subject.
		SENDER:  The user name for the member sending the personal message.
		MESSAGE:  The text of the personal message.
		REPLYLINK:  The link to directly access the reply page.
		TOLIST:  The list of users that will receive the personal message.
	@description: A notification email sent to the receivers of a personal message
*/
$txt['new_pm_body_tolist_subject'] = 'Neue private Nachricht: {SUBJECT}';
$txt['new_pm_body_tolist_body'] = '{TOLIST} hat eine private Nachricht von {SENDER} im Forum {FORUMNAME} erhalten.

WICHTIG: Das ist nur eine Benachrichtigung - bitte antworten Sie nicht auf diese E-Mail!

Diese Nachricht wurde gesendet:

{MESSAGE}

Auf die Nachricht antworten (nur dem Absender): {REPLYLINK}';

/*
	@additional_params: happy_birthday
		REALNAME: The real (display) name of the person receiving the birthday message.
	@description: A message sent to members on their birthday.
*/

$txtBirthdayEmails['happy_birthday_subject'] = 'Alles Gute vom Forum {FORUMNAME}.';
$txtBirthdayEmails['happy_birthday_body'] = 'Hallo {REALNAME},

Wir vom Forum {FORUMNAME} wünschen Ihnen alles Gute zum Geburtstag. Wir hoffen, dass dieser Tag und das folgende Jahr zu Ihrer vollsten Zufriedenheit verläuft.

{REGARDS}';
$txtBirthdayEmails['happy_birthday_author'] = '<a href="http://www.simplemachines.org/community/?action=profile;u=2676">Thantos</a>, aus dem Englischen übersetzt';

$txtBirthdayEmails['karlbenson1_subject'] = 'An Ihrem Geburtstag...';
$txtBirthdayEmails['karlbenson1_body'] = 'Wir hätten Ihnen eine Geburtstagskarte senden können. Wir hätten Ihnen Blumen oder einen Kuchen schicken können.

Haben wir aber nicht.

Wir hätten Ihnen auch eine dieser automatisch generierten Nachrichten schicken können, bei der noch nicht mal der Wortlaut NAME EINFÜGEN hätte ersetzt werden müssen.

Haben wir aber nicht.

Wir haben diesen Geburtstagsgruß extra für Sie geschrieben.

Wir wünschen Ihnen alles Gute zu Ihrem Geburtstag.

{REGARDS}

//:: Diese Nachricht wurde automatisch generiert :://';
$txtBirthdayEmails['karlbenson1_author'] = '<a href="http://www.simplemachines.org/community/?action=profile;u=63186">karlbenson</a>, aus dem Englischen übersetzt';

$txtBirthdayEmails['nite0859_subject'] = 'Herzlichen Glückwunsch!';
$txtBirthdayEmails['nite0859_body'] = 'Ihre Freunde im Forum {FORUMNAME} würden gerne einen Moment deoner kostbaren Zeit stehlen, um Ihnen alles Gute zum Geburtstag zu wünschen, {REALNAME}. Wenn Sie es noch nicht getan haben, besuchen Sie das Forum, um anderen Benutzern die Möglichkeit zu geben, ein paar Grüße loszuwerden.

Auch wenn heute Ihr Geburtstag ist, {REALNAME}, möchten wir daran erinnern, dass Ihre Mitgliedschaft in unserem Forum das größte Geschenk von allen war.

Herzliche Grüße,
Die Forumleitung von {FORUMNAME}';
$txtBirthdayEmails['nite0859_author'] = '<a href="http://www.simplemachines.org/community/?action=profile;u=46625">nite0859</a>, aus dem Englischen übersetzt';

$txtBirthdayEmails['zwaldowski_subject'] = 'Geburtstagsgrüße für {REALNAME}';
$txtBirthdayEmails['zwaldowski_body'] = 'Hallo {REALNAME},

ein weiteres Jahr in Ihrem Leben ist vorbei. Wir vom Forum {FORUMNAME} hoffen, dass es Ihnen Spaß gemacht hat, und wünschen Ihnen für das kommende Jahr viel Glück.

{REGARDS}';
$txtBirthdayEmails['zwaldowski_author'] = '<a href="http://www.simplemachines.org/community/?action=profile;u=72038">zwaldowski</a>, aus dem Englischen übersetzt';

$txtBirthdayEmails['geezmo_subject'] = 'Herzlichen Glückwunsch, {REALNAME}!';
$txtBirthdayEmails['geezmo_body'] = 'Wissen Sie, wer heute Geburtstag hat, {REALNAME}?

Wir wissen es... Sie!

Herzlichen Glückwunsch!

Sie sind jetzt zwar ein Jahr älter, aber wir hoffen, dass Sie auch glücklicher als letztes Jahr sind.

Wir wünschen Ihnen einen schönen Tag, {REALNAME}!

- Ihr Forenteam von {FORUMNAME}';
$txtBirthdayEmails['geezmo_author'] = '<a href="http://www.simplemachines.org/community/?action=profile;u=48671">geezmo</a>, aus dem Englischen übersetzt';

$txtBirthdayEmails['karlbenson2_subject'] = 'Ihre Geburtstagsglückwünsche';
$txtBirthdayEmails['karlbenson2_body'] = 'Wir hoffen, dass dieser Geburtstag der beste aller Zeiten ist, egal, welches Wetter herrscht.
Wir wünschen Ihnen viele Geburtstagskuchen und viel Spaß - erzählen Sie uns, was Sie heute erlebt haben.

Bis nächstes Jahr zur selben Zeit am selben Ort.

{REGARDS}';
$txtBirthdayEmails['karlbenson2_author'] = '<a href="http://www.simplemachines.org/community/?action=profile;u=63186">karlbenson</a>, aus dem Englischen übersetzt';
