<?php
// Version: 1.0; Profile

global $scripturl, $context;

$txt['no_profile_edit'] = 'Sie haben keine Berechtigung, dieses Profil zu �ndern.';
$txt['website_title'] = 'Titel der Webseite';
$txt['website_url'] = 'URL der Webseite';
$txt['signature'] = 'Signatur';
$txt['profile_posts'] = 'Beitr�ge';

$txt['profile_info'] = 'Weitere Einzelheiten';
$txt['profile_contact'] = 'Kontakt Information';
$txt['profile_moderation'] = 'Moderations Information';
$txt['profile_more'] = 'Signatur';
$txt['profile_attachments'] = 'Letzte Dateianh�nge';
$txt['profile_attachments_no'] = 'Dieses Mitglied hat keine Dateien hochgeladen';
$txt['profile_posts'] = 'Letzte Beitr�ge';
$txt['profile_posts_no'] = 'Dieses Mitglied hat keine Beitr�ge verfasst';
$txt['profile_topics'] = 'Letzte Themen';
$txt['profile_topics_no'] = 'Dieses Mitglied hat keine Themen verfasst';
$txt['profile_buddies_no'] = 'Ihre Freundeliste ist leer';
$txt['profile_user_info'] = 'Mitgliedsinformationen';
$txt['profile_contact_no'] = 'Es sind keine Kontaktinformationen f�r dieses Mitglied hinterlegt';
$txt['profile_signature_no'] = 'Dieses Mitglied hat keine Signatur angelegt';
$txt['profile_additonal_no'] = 'Es gibt keine weiteren Informationen �ber dieses Mitglied';
$txt['profile_user_summary'] = 'Profil';
$txt['profile_action'] = 'Derzeit';
$txt['profile_recent_activity'] = 'Letzte Aktivit�t';
$txt['profile_activity'] = 'Aktivit�t';
$txt['profile_loadavg'] = 'Die Information kann im Moment nicht angezeigt werden. Bitte versuchen Sie sp�ter noch einmal.';

$txt['change_profile'] = 'Profil �ndern';
$txt['preview_signature'] = 'Vorschau Signatur';
$txt['current_signature'] = 'Aktuelle Signatur';
$txt['signature_preview'] = 'Signaturvorschau';
$txt['delete_user'] = 'Benutzer l�schen';
$txt['current_status'] = 'Dieses Mitglied ist:';
$txt['personal_picture'] = 'Benutzerbild';
$txt['no_avatar'] = 'Kein Benutzerbild';
$txt['choose_avatar_gallery'] = 'W�hle einen Avatar aus der Galerie';
$txt['picture_text'] = 'Bild/Text';
$txt['reset_form'] = 'Eingabe l�schen';
$txt['preferred_language'] = 'Bevorzugte Sprache';
$txt['age'] = 'Alter';
$txt['no_pic'] = '(kein Bild)';
$txt['latest_posts'] = 'Letzter Beitrag: ';
$txt['additional_info'] = 'Zus�tzliche Informationen';
$txt['avatar_by_url'] = 'Geben Sie den URL zu Ihrem Benutzerbild an (z.B.: <em>http://www.meineseite.de/bild.gif</em>)';
$txt['my_own_pic'] = 'Eigenes Benutzerbild via URL';
$txt['gravatar'] = 'Gravatar';
$txt['date_format'] = 'Bestimmt das Format von Datum und Zeit im Forum.';
$txt['time_format'] = 'Zeitformat';
$txt['display_name_desc'] = 'Der im Forum angezeigte Name.';
$txt['personal_time_offset'] = 'Zeitdifferenz in +/- Stunden zwischen Serverzeit und deiner lokalen Zeit.';
$txt['dob'] = 'Geburtsdatum';
$txt['dob_month'] = 'Monat (MM)';
$txt['dob_day'] = 'Tag (TT)';
$txt['dob_year'] = 'Jahr (JJJJ)';
$txt['password_strength'] = 'Wir empfehlen mindestens 6 Zeichen zu verwenden und Buchstaben mit Ziffern zu kombinieren.';
$txt['include_website_url'] = 'Bitte ausf�llen, wenn Sie unten eine URL eingeben!';
$txt['complete_url'] = 'Dies muss ein vollst�ndiger URL sein.';
$txt['sig_info'] = 'Signaturen werden unter jedem Beitrag und jeder privaten Nachricht angezeigt. In der Signatur k�nnen Smileys und BBCode verwendet werden.';
$txt['max_sig_characters'] = 'Max. Zeichen: %1$d; Zeichen �brig:';
$txt['send_member_pm'] = 'Diesem Mitglied eine private Nachricht senden';
$txt['hidden'] = 'versteckt';
$txt['current_time'] = 'Aktuelle Zeit des Forums';

$txt['language'] = 'Sprache';
$txt['avatar_too_big'] = 'Das Benutzerbild ist zu gro�. Bitte verkleinern Sie es und versuchen Sie es erneut (max';
$txt['invalid_registration'] = 'Ung�ltiges Registrierungsdatum, g�ltiges Beispiel:';
$txt['current_password'] = 'Aktuelles Passwort';
// Don't use entities in the below string, except the main ones. (lt, gt, quot.)
$txt['required_security_reasons'] = 'Aus Sicherheitsgr�nden muss bei jeder �nderung des Benutzerkontos das aktuelle Passwort eingegeben werden.';

$txt['timeoffset_autodetect'] = '(automatisch ermittelt)';

$txt['secret_question'] = 'Geheime Frage';
$txt['secret_desc'] = 'Die eingegebene Frage kann Ihnen helfen, Ihr Passwort wieder zu bekommen. Die Antwort sollten <strong>nur</strong> nur Sie kennen!';
$txt['secret_desc2'] = 'W�hlen Sie die Antwort vorsichtig aus. Es sollte kein anderer die Antwort erraten k�nnen!';
$txt['secret_answer'] = 'Antwort';
$txt['secret_ask'] = 'Stelle mir die geheime Frage';
$txt['cant_retrieve'] = 'Sie haben keine M�glichkeit, Ihr Passwort abzufragen! Sie k�nnen aber ein neues festlegen, indem Sie dem Link folgen, der Ihnen per E-Mail zugeschickt werden kann. Beantworten Sie Ihre geheime Frage, so k�nnen Sie gleich ein neues Passwort festlegen.';
$txt['incorrect_answer'] = 'Sie haben keine g�ltige Kombination aus Geheimfrage und -antwort im Benutzerprofil angegeben. Bitte klicken Sie auf die Schaltfl�che \'Zur�ck\' und benutzen die Standardmethode, um Ihr Passwort zu erlangen.';
$txt['enter_new_password'] = 'Bitte geben Sie die Antwort auf Ihre geheime Frage und das gew�nschte Passwort ein. Das Passwort wird danach ge�ndert - vorausgesetzt, Sie haben die Frage richtig beantwortet.';
$txt['password_success'] = 'Ihr Passwort wurde erfolgreich ge�ndert.<br />Zur Anmeldung <a href="' . $scripturl . '?action=login">hier</a> klicken.';
$txt['secret_why_blank'] = 'Warum ist dieses Feld leer?';

$txt['authentication_reminder'] = 'Authentifizierungserinnerung';
$txt['password_reminder_desc'] = 'Wenn Sie Ihre Anmeldedaten vergessen haben, so k�nnen Sie diese anfordern. Um diesen Vorgang zu starten, geben Sie bitte den Benutzernamen oder die E-Mail-Adresse ein.';
$txt['authentication_options'] = 'Bitte w�hlen Sie eine der folgenden Optionen aus';
$txt['authentication_openid_email'] = 'Eine Erinnerung meiner OpenID-Identit�t zusenden';
$txt['authentication_openid_secret'] = 'Die "geheime Frage" beantworten um die OpenID-Identit�t anzuzeigen';
$txt['authentication_password_email'] = 'Neues Passwort zusenden';
$txt['authentication_password_secret'] = 'Neues Passwort nach Beantwortung der "geheimen Frage" vergeben';
$txt['openid_secret_reminder'] = 'Bitte geben Sie die Antwort auf die Frage ein. Beantworten Sie diese richtig, wird Ihre OpenID-Identit�t angezeigt.';
$txt['reminder_openid_is'] = 'Die OpenID-Identit�t, die mit Ihrem Benutzerkonto verkn�pft ist, lautet:<br />&nbsp;&nbsp;&nbsp;&nbsp;<strong>%1$s</strong><br /><br />Bitte merken Sie sich diese f�r die Zukunft.';
$txt['reminder_continue'] = 'Weiter';

$txt['current_theme'] = 'Aktuelles Thema';
$txt['change'] = '(�ndern)';
$txt['theme_preferences'] = 'Themenvoreinstellungen';
$txt['theme_forum_default'] = 'Forums- oder Boardstandard';
$txt['theme_forum_default_desc'] = 'Dies ist das Standard-Thema, das hei�t, Ihr Thema wechselt mit den Einstellungen des Administrators und dem Forum, das Sie aufrufen.';

$txt['profileConfirm'] = 'Sind Sie sich sicher, dass Sie diesen Benutzer l�schen m�chten?';

$txt['custom_title'] = 'Individueller Titel';

$txt['lastLoggedIn'] = 'Letzter Besuch';

$txt['notify_settings'] = 'Benachrichtigungs-Einstellungen:';
$txt['notify_save'] = 'Einstellungen speichern';
$txt['notify_important_email'] = 'Erhalten Sie Rundbriefe, Ank�ndigungen und wichtige Benachrichtigungen per E-Mail.';
$txt['notify_regularity'] = 'F�r abonnierte Themen und Foren wie folgt benachrichtigen';
$txt['notify_regularity_instant'] = 'Sofort';
$txt['notify_regularity_first_only'] = 'Sofort - nur zur ersten ungelesenen Antwort';
$txt['notify_regularity_daily'] = 'T�glich';
$txt['notify_regularity_weekly'] = 'W�chentlich';
$txt['auto_notify'] = 'Benachrichtigung einschalten, wenn Sie einen Beitrag oder eine Antwort schreiben';
$txt['notify_send_types'] = 'Benachrichtige mich bei abonnierten Themen';
$txt['notify_send_type_everything'] = '�ber alles, was passiert';
$txt['notify_send_type_everything_own'] = '�ber alles, wenn ich das Thema gestartet habe';
$txt['notify_send_type_only_replies'] = 'Nur �ber neue Antworten';
$txt['notify_send_type_nothing'] = 'Gar nicht';
$txt['notify_send_body'] = 'Beim Senden von Benachrichtigungen �ber neue Antworten auf ein Thema diese in die E-Mail einf�gen (aber bitte antworten Sie nicht auf diese E-Mails.)';

$txt['notifications_topics'] = 'Aktuelle Themenbenachrichtigungen';
$txt['notifications_topics_list'] = 'Sie sind �ber Antworten auf folgendes Thema benachrichtigt worden';
$txt['notifications_topics_none'] = 'Sie erhalten im Moment keine Benachrichtigungen �ber neue Themen.';
$txt['notifications_topics_howto'] = 'Um Benachrichtigungen �ber ein Thema zu erhalten, klicken Sie im gew�nschten Thema auf die Schaltfl�che "Benachrichtigen".';

$txt['notifications_boards'] = 'Aktuelle Forenbenachrichtigungen';
$txt['notifications_boards_list'] = 'Sie sind �ber neue Themen in folgendem Forum benachrichtigt worden';
$txt['notifications_boards_none'] = 'Sie erhalten im Moment keine Benachrichtigungen �ber Foren.';
$txt['notifications_boards_howto'] = 'Um Benachrichtigungen �ber ein Forum zu erhalten, klicken Sie in der Themen�bersicht auf die Schaltfl�che "Benachrichtigen".';
$txt['notifications_boards_current'] = 'Sie erhalten Benachrichtigungen �ber die <strong>FETT</strong> markierten Foren.  Verwenden Sie die Kontrollk�stchen, um diese abzuschalten oder Ihrer Benachrichtigungsliste weitere Foren hinzuzuf�gen';
$txt['notifications_boards_update'] = 'Aktualisieren';
$txt['notifications_update'] = 'Abbestellen';

$txt['statPanel_showStats'] = 'Benutzerstatistiken f�r: ';
$txt['statPanel_users_votes'] = 'Abgegebene Stimmen';
$txt['statPanel_users_polls'] = 'Erstellte Umfragen';
$txt['statPanel_total_time_online'] = 'Gesamte Onlinezeit';
$txt['statPanel_noPosts'] = 'Noch kein Beitrag vorhanden!';
$txt['statPanel_generalStats'] = 'Benutzerstatistiken';
$txt['statPanel_posts'] = 'Beitr�ge';
$txt['statPanel_topics'] = 'Themen';
$txt['statPanel_total_posts'] = 'Gesamte Beitr�ge';
$txt['statPanel_total_topics'] = 'Begonnene Themen';
$txt['statPanel_votes'] = 'Stimmen';
$txt['statPanel_polls'] = 'Umfragen';
$txt['statPanel_topBoards'] = 'Meistbesuchte Foren nach Beitr�gen';
$txt['statPanel_topBoards_posts'] = '%1$d Beitr�ge des Forums %2$d Beitr�ge (%3$01.2f%%)';
$txt['statPanel_topBoards_memberposts'] = '%1$d Beitr�ge des Benutzers %2$d Beitr�ge (%3$01.2f%%)';
$txt['statPanel_topBoardsActivity'] = 'Meistbesuchte Foren nach Aktivit�t';
$txt['statPanel_activityTime'] = 'Aktivit�t nach Zeit';
$txt['statPanel_activityTime_posts'] = '%1$d Beitr�ge (%2$d%%)';
$txt['statPanel_timeOfDay'] = 'Tageszeit';

$txt['deleteAccount_warning'] = 'Warnung - Diese Aktion ist nicht r�ckg�ngig zu machen!';
$txt['deleteAccount_desc'] = 'Auf dieser Seite k�nnen Sie das Benutzerkonto und die Beitr�ge des Benutzers l�schen.';
$txt['deleteAccount_member'] = 'Benutzerkonto des Mitglieds l�schen';
$txt['deleteAccount_posts'] = 'Beitr�ge des Benutzers, die gel�scht werden';
$txt['deleteAccount_none'] = 'Keine';
$txt['deleteAccount_all_posts'] = 'Alle Beitr�ge';
$txt['deleteAccount_topics'] = 'Themen und Beitr�ge';
$txt['deleteAccount_confirm'] = 'Sind Sie sich vollends sicher, dass Sie das Benutzerkonto l�schen m�chten?';
$txt['deleteAccount_approval'] = 'Bitte beachten Sie, dass der Administrator dem L�schen eines Benutzerkontos zustimmen muss.';

$txt['profile_of_username'] = 'Profil von %1$s';
$txt['profileInfo'] = 'Profilinformationen';
$txt['showPosts'] = 'Beitr�ge anzeigen';
$txt['showPosts_help'] = 'Diese Sektion erlaubt es Ihnen, alle Beitr�ge dieses Mitglieds zu sehen. Beachten Sie, dass nur solche Beitr�ge zu sehen sind, auf die Sie zugreifen d�rfen.';
$txt['showMessages'] = 'Nachrichten';
$txt['showTopics'] = 'Themen';
$txt['showAttachments'] = 'Dateianh�nge';
$txt['viewWarning_help'] = 'Dieser Bereich erlaubt es Ihnen, alle gegen�ber diesem Mitglied ausgesprochenen Verwarnungen anzusehen.';
$txt['statPanel'] = 'Statistiken anzeigen';
$txt['editBuddyIgnoreLists'] = 'Freundes-/Ignorierlisten';
$txt['editBuddies'] = 'Freunde verwalten';
$txt['editIgnoreList'] = 'Ignorierliste bearbeiten';
$txt['trackUser'] = 'Benutzer beobachten';
$txt['trackActivity'] = 'Aktivit�t';
$txt['trackIP'] = 'IP-Adresse';
$txt['trackLogins'] = 'Anmelden';

// translator note: why should we keep up with the "the Like" wording? :)
$txt['likes_show'] = 'Zeige \'Gef�llt mirs\'';
$txt['likes_given'] = 'Beitr�ge, die Ihnen gefallen';
$txt['likes_profile_received'] = '\'Gef�llt mirs\' empfangen';
$txt['likes_profile_given'] = '\'Gef�llt mirs\' gegeben';
$txt['likes_received'] = 'Ihre Beitr�ge, die anderen gefallen';
$txt['likes_none_given'] = 'Ihnen gef�llt bisher kein Beitrag';
$txt['likes_none_received'] = 'Bisher gef�llt niemandem mindestens einer Ihrer Beitr�ge :\'(';
$txt['likes_confirm_delete'] = 'Dieses \'Gef�llt mir\' entfernen?';
$txt['likes_show_who'] = 'Mitglieder anzeigen, denen dieser Beitrag gef�llt';
$txt['likes_by'] = '\'Gef�llt mir\' gegeben von';
$txt['likes_delete'] = 'L�schen';

$txt['authentication'] = 'Authentifizierung';
$txt['change_authentication'] = 'Hier k�nnen Sie ausw�hlen, wie Sie sich im Forum anmelden m�chten. W�hlen Sie die Authentifizierung �ber ein OpenID-Profil aus oder wechseln Sie zu Benutzername und Passwort.';

$txt['profileEdit'] = 'Profil �ndern';
$txt['account_info'] = 'Dies sind Ihre Kontoeinstellungen. Hier k�nnen Sie alle wichtigen Informationen �ndern, die Sie im Forum identifizieren. Aus Sicherheitsgr�nden m�ssen Sie Ihr (aktuelles) Passwort eingeben, wenn Sie diese Angaben �ndern.';
$txt['forumProfile_info'] = 'Hier k�nnen Sie Ihre pers�nlichen Informationen �ndern. Diese Informationen werden �berall im Forum ' . $context['forum_name_html_safe'] . ' angezeigt werden. Wenn Sie bestimmte Informationen nicht preisgeben m�chten, lassen Sie die entsprechenden Felder einfach leer.';
$txt['theme_info'] = 'Hier k�nnen Sie das Design und Layout des Forums �ndern.';
$txt['notification'] = 'Benachrichtigungen &amp; E-Mail';
$txt['notification_info'] = 'Dies erlaubt es Ihnen, �ber Antworten auf Beitr�ge, neu er�ffnete Themen und Forumsank�ndigungen benacrichtigt zu werden. Hier k�nnen Sie die abonnierten Themen und Foren anschauen sowie die entsprechenden Einstellungen �ndern.';
$txt['groupmembership'] = 'Gruppenmitgliedschaft';
$txt['groupMembership_info'] = 'Hier k�nnen Sie Ihre Mitgliedschaft in den verschiedenen Gruppen verwalten.';
$txt['ignoreboards'] = 'Foren ignorieren';
$txt['ignoreboards_info'] = 'Hier k�nnen Sie bestimmte Foren ignorieren. Wenn Sie ein Forum ignorieren, wird das Symbol \'Neue Beitr�ge\' auf der Startseite nicht angezeigt. Auch �ber den Link \'Ungelesene Beitr�ge\' werden aus diesen Foren keine neuen Beitr�ge angezeigt, da dort nicht gesucht wird. Schauen Sie sich in den betreffenden Foren die Themen�bersicht an, so werden die Themen mit neuen Beitr�gen jedoch markiert. Benutzen Sie hingegen den Link \'Ungelesene Antworten zu deinen Beitr�gen\', so werden neue Antworten auch aus den ignorierten Foren aufgelistet.';
$txt['contactprefs'] = 'Benachrichtigungen';

$txt['profileAction'] = 'Aktionen';
$txt['deleteAccount'] = 'Benutzerkonto l�schen';
$txt['profileSendIm'] = 'Private Nachricht senden';
$txt['profile_sendpm_short'] = 'PN senden';

$txt['profileBanUser'] = 'Benutzer sperren';

$txt['display_name'] = 'Anzeigenname';
$txt['enter_ip'] = 'IP-Adresse (Bereich) eingeben';
$txt['errors_by'] = 'Fehlermeldungen von';
$txt['errors_desc'] = 'Eine Auflistung aller Fehler, die von diesem Benutzer gemacht worden sind.';
$txt['errors_from_ip'] = 'Fehlermeldungen der IP-Adresse (Bereich)';
$txt['errors_from_ip_desc'] = 'Eine Auflistung aller Fehler, die von dieser IP-Adresse (bzw. diesem IP-Bereich) verursacht worden sind.';
$txt['ip_address'] = 'IP-Adresse';
$txt['ips_in_errors'] = 'Benutzte IP-Adressen in Fehlermeldungen';
$txt['ips_in_messages'] = 'Benutzte IP-Adressen in den letzten Beitr�gen';
$txt['members_from_ip'] = 'Mitglieder der IP-Adresse (Bereich)';
$txt['members_in_range'] = 'M�gliche Mitglieder im gleichen Bereich';
$txt['messages_from_ip'] = 'Beitr�ge von IP-Adresse (Bereich)';
$txt['messages_from_ip_desc'] = 'Eine Auflistung aller Beitr�ge, die von dieser IP-Adresse (bzw. diesem IP-Bereich) ver�ffentlicht worden sind.';
$txt['trackLogins_desc'] = 'Die unten stehende Liste zeigt alle Logins dieses Kontos.';
$txt['most_recent_ip'] = 'Meistgenutzte IP-Adresse';
$txt['why_two_ip_address'] = 'Warum werden zwei IP-Adressen aufgelistet?';
$txt['no_errors_from_ip'] = 'Keine Fehlermeldung von dieser IP-Adresse (Bereich) gefunden';
$txt['no_errors_from_user'] = 'Keine Fehlermeldung von diesem Benutzer gefunden';
$txt['no_members_from_ip'] = 'Kein Mitglied von dieser IP-Adresse (Bereich) gefunden';
$txt['no_messages_from_ip'] = 'Keine Nachricht von dieser IP-Adresse (Bereich) gefunden';
$txt['trackLogins_none_found'] = 'Es wurden keine k�rzlichen Anmeldungen gefunden';
$txt['none'] = 'Keine';
$txt['own_profile_confirm'] = 'M�chten Sie Ihr Benutzerkonto wirklich l�schen?';
$txt['view_ips_by'] = 'Zeige IP-Adressen von';

$txt['avatar_will_upload'] = 'Eigenes Benutzerbild hochladen';

$txt['activate_changed_email_title'] = 'E-Mail-Adresse wurde ge�ndert';
$txt['activate_changed_email_desc'] = 'Sie haben Ihre E-Mail-Adresse ge�ndert. Zur �berpr�fung dieser Adresse erhalten Sie eine E-Mail. Klicken Sie auf den Link in der E-Mail, um Ihr Benutzerkonto zu reaktivieren.';

// Use numeric entities in the below three strings.
$txt['no_reminder_email'] = 'Senden der Erinnerungs-E-Mail nicht m�glich.';
$txt['send_email'] = 'E-Mail senden an';
$txt['to_ask_password'] = 'um nach dem Passwort zu fragen';

$txt['user_email'] = 'Benutzername/E-Mail';

// Use numeric entities in the below two strings.
$txt['reminder_subject'] = 'Neues Passwort f�r ' . $context['forum_name'];
$txt['reminder_mail'] = 'Diese E-Mail wurde gesendet, weil Sie die \'Passwort vergessen\'-Funktion benutzt haben. Um ein neues Passwort einzugeben, klicken Sie bitte auf folgenden Link';
$txt['reminder_sent'] = 'Eine E-Mail wurde an Ihre Adresse geschickt. Klicken Sie auf den dortigen Link, um ein neues Passwort einzugeben.';
$txt['reminder_openid_sent'] = 'Ihre aktuelle OpenID-Identit�t wurde an Ihre E-Mail-Adresse gesendet.';
$txt['reminder_set_password'] = 'Passwort eingeben';
$txt['reminder_password_set'] = 'Passwort erfolgreich ge�ndert';
$txt['reminder_error'] = '%1$s hat die geheime Frage, w�hrend des Versuches ein vergessenes Passwort zu �ndern, nicht richtig beantwortet.';

$txt['registration_not_approved'] = 'Dieses Benutzerkonto wurde noch nicht akzeptiert. Wenn Sie die E-Mail-Adresse �ndern m�chten, klicken Sie';
$txt['registration_not_activated'] = 'Dieses Benutzerkonto wurde noch nicht aktiviert. Wenn Sie die Aktivierungs-E-Mail nochmals senden m�chten, klicken Sie';

$txt['primary_membergroup'] = 'Prim�re Mitgliedergruppe';
$txt['additional_membergroups'] = 'Weitere Mitgliedergruppe';
$txt['additional_membergroups_show'] = '[ weitere Gruppen anzeigen ]';
$txt['no_primary_membergroup'] = '(keine prim�re Mitgliedergruppe)';
$txt['deadmin_confirm'] = 'Sind Sie sich sicher, dass Sie Ihren Administrator-Status unwiderruflich entfernen m�chten?';

$txt['account_activate_method_2'] = 'Das Benutzerkonto ben�tigt eine erneute Aktivierung nach �nderung der E-Mail-Adresse';
$txt['account_activate_method_3'] = 'Das Benutzerkonto ist noch nicht genehmigt';
$txt['account_activate_method_4'] = 'Das L�schen des Benutzerkontos muss noch genehmigt werden';
$txt['account_activate_method_5'] = 'Dieses Benutzerkonto geh�rt einem Minderj�hrigen und muss genehmigt werden';
$txt['account_not_activated'] = 'Das Benutzerkonto ist momentan nicht aktiviert';
$txt['account_activate'] = 'aktiviere';
$txt['account_approve'] = 'akzeptiere';
$txt['user_is_banned'] = 'Benutzer ist gebannt';
$txt['view_ban'] = 'Anschauen';
$txt['user_banned_by_following'] = 'Dieser Benutzer ist wegen folgender Regeln gesperrt';
$txt['user_cannot_due_to'] = 'Benutzer kann nicht %1$s wegen des Bans: "%2$s"';
$txt['ban_type_post'] = 'schreiben';
$txt['ban_type_register'] = 'registrieren';
$txt['ban_type_login'] = 'anmelden';
$txt['ban_type_access'] = 'auf das Forum zugreifen';

$txt['show_online'] = 'Anderen Benutzern Ihren Onlinestatus anzeigen';

$txt['return_to_post'] = 'Nach dem Schreiben zum Thema zur�ckkehren';
$txt['no_new_reply_warning'] = 'Beim Schreiben nicht bez�glich neuer Antworten warnen';
$txt['recent_posts_at_top'] = 'Die neuesten Beitr�ge am Anfang anzeigen';
$txt['recent_pms_at_top'] = 'Die neuesten privaten Nachrichten am Anfang anzeigen';
$txt['wysiwyg_default'] = 'WYSIWYG-Editor standardm��ig auf Antwortseiten anzeigen.';

$txt['timeformat_default'] = '(Forumsstandard)';
$txt['timeformat_easy1'] = 'Monat Tag, Jahr, HH:MM:SS am/pm';
$txt['timeformat_easy2'] = 'Monat Tag, Jahr, HH:MM:SS (24 Stunden)';
$txt['timeformat_easy3'] = 'JJJJ-MM-TT, HH:MM:SS';
$txt['timeformat_easy4'] = 'TT Monat JJJJ, HH:MM:SS';
$txt['timeformat_easy5'] = 'TT-MM-JJJJ, HH:MM:SS';

$txt['poster'] = 'Autor';

$txt['use_sidebar_menu'] = 'Men�s statt Aufklappmen�s im Seitenmen� verwenden.';
$txt['use_click_menu'] = 'Men� durch Anklicken anstatt durch �berfahren �ffnen.';
$txt['show_no_avatars'] = 'Benutzerbilder von anderen Benutzern nicht anzeigen';
$txt['show_no_signatures'] = 'Signaturen von anderen Benutzern nicht anzeigen';
$txt['show_no_censored'] = 'W�rter nicht zensieren';
$txt['topics_per_page'] = 'Anzahl der Themen pro Seite:';
$txt['messages_per_page'] = 'Anzahl der Beitr�ge pro Seite:';
$txt['hide_poster_area'] = 'Bereich der Mitgliederinformationen verstecken.';
$txt['per_page_default'] = 'Forumsstandard';
$txt['calendar_start_day'] = 'Tag des Wochenanfangs';
$txt['display_quick_reply'] = 'Schnellantwort im Thema ';
$txt['display_quick_reply1'] = 'nicht anzeigen.';
$txt['display_quick_reply2'] = 'minimiert anzeigen.';
$txt['display_quick_reply3'] = 'normal anzeigen.';
$txt['use_editor_quick_reply'] = 'Vollen Editor in Schnellantwortbox benutzen';
$txt['display_quick_mod'] = 'Schnellmoderation anzeigen als';
$txt['display_quick_mod_none'] = 'nicht anzeigen.';
$txt['display_quick_mod_check'] = 'als Auswahlkasten anzeigen.';
$txt['display_quick_mod_image'] = 'als Symbol anzeigen.';

$txt['whois_title'] = 'Informationen zu der IP-Adresse suchen';
$txt['whois_afrinic'] = 'AfriNIC (Afrika)';
$txt['whois_apnic'] = 'APNIC (Asien-Pazifik-Region)';
$txt['whois_arin'] = 'ARIN (Nordamerika, ein Teil Karibik und Afrika s�dl. der Sahara)';
$txt['whois_lacnic'] = 'LACNIC (Lateinamerika und Karibik)';
$txt['whois_ripe'] = 'RIPE (Europa, der Mittlere Osten and Teile von Afrika und Asien)';

$txt['moderator_why_missing'] = 'Warum fehlt hier \'Moderator\'?';
$txt['username_change'] = '�ndern';
$txt['username_warning'] = 'Um den Benutzernamen dieses Mitglieds zu �ndern, muss das Forum auch das Passwort zur�cksetzen. Das neue Passwort wird dem Mitglied mit dem neuen Benutzernamen per E-Mail zugeschickt.';

$txt['show_member_posts'] = 'Beitr�ge anzeigen';
$txt['show_member_topics'] = 'Themen anzeigen';
$txt['show_member_attachments'] = 'Dateianh�nge anzeigen';
$txt['show_posts_none'] = 'Es wurden noch keine Beitr�ge erstellt.';
$txt['show_topics_none'] = 'Es wurden noch keine Themen ver�ffentlicht.';
$txt['unwatched_topics_none'] = 'Es befinden sich keine Themen in Ihrer Ignorierliste.';
$txt['show_attachments_none'] = 'Es wurden noch keine Dateianh�nge ver�ffentlicht.';
$txt['show_attach_filename'] = 'Dateiname';
$txt['show_attach_downloads'] = 'Downloads';
$txt['show_attach_posted'] = 'Erstellt';

$txt['showPermissions'] = 'Berechtigungen anzeigen';
$txt['showPermissions_status'] = 'Berechtigungsstatus';
$txt['showPermissions_help'] = 'Dieser Abschnitt erlaubt es Ihnen, alle Berechtigungen f�r diesen Benutzer (verbotene sind <del>durchgestrichen</del>) zu sehen.';
$txt['showPermissions_given'] = 'Erhalten von';
$txt['showPermissions_denied'] = 'Verboten von';
$txt['showPermissions_permission'] = 'Berechtigung (verbotene sind <del>durchgestrichen</del>)';
$txt['showPermissions_none_general'] = 'Dieses Mitglied hat keine generellen Berechtigungen.';
$txt['showPermissions_none_board'] = 'Dieses Mitglied hat keine forumsspezifischen Berechtigungen.';
$txt['showPermissions_all'] = 'Als Administrator hat dieses Mitglied alle Berechtigungen.';
$txt['showPermissions_select'] = 'Forumssspezifische Berechtigungen f�r';
$txt['showPermissions_general'] = 'Generelle Berechtigungen';
$txt['showPermissions_global'] = 'Alle Foren';
$txt['showPermissions_restricted_boards'] = 'Eingeschr�nkte Foren';
$txt['showPermissions_restricted_boards_desc'] = 'Die folgenden Foren sind f�r den Benutzer nicht einsehbar';

$txt['local_time'] = 'Lokale Zeit';
$txt['posts_per_day'] = 'pro Tag';

$txt['buddy_ignore_desc'] = 'Dieser Bereich erlaubt Ihnen das Verwalten Ihrer Freundes- und Ignorierlisten f�r dieses Forum. Das Hinzuf�gen von Mitgliedern in diese Listen wird, neben anderen Dingen, dabei unterst�tzen, den Mail- und PN-Verkehr abh�ngig von Ihren Einstellungen zu kontrollieren.';

$txt['buddy_add'] = 'Zur Freundesliste hinzuf�gen';
$txt['buddy_remove'] = 'Von FreundelListe entfernen';
$txt['buddy_add_button'] = 'Hinzuf�gen';
$txt['no_buddies'] = 'Ihre Freundesliste ist momentan leer';

$txt['ignore_add'] = 'Zur Ignorierliste hinzuf�gen';
$txt['ignore_remove'] = 'Von Ignorierliste entfernen';
$txt['ignore_add_button'] = 'Hinzuf�gen';
$txt['no_ignore'] = 'Ihre Ignorierliste ist momentan leer';

$txt['regular_members'] = 'Registrierte Mitglieder';
$txt['regular_members_desc'] = 'Jeder Benutzer des Forums ist ein Mitglied dieser Gruppe.';
$txt['group_membership_msg_free'] = 'Ihre Gruppenmitgliedschaft wurde erfolgreich aktualisiert.';
$txt['group_membership_msg_request'] = 'Ihre Anforderung wurde �bermittelt, bitte warten Sie, w�hrend sie bearbeitet wird.';
$txt['group_membership_msg_primary'] = 'Ihre prim�re Gruppe wurde aktualisiert';
$txt['current_membergroups'] = 'Aktuelle Benutzergruppen';
$txt['available_groups'] = 'Verf�gbare Gruppen';
$txt['join_group'] = 'Gruppe beitreten';
$txt['leave_group'] = 'Gruppe verlassen';
$txt['request_group'] = 'Mitgliedschaft anfordern';
$txt['approval_pending'] = 'Genehmigung steht aus';
$txt['make_primary'] = 'Als prim�re Gruppen festlegen';

$txt['request_group_membership'] = 'Gruppenmitgliedschaft anfragen';
$txt['request_group_membership_desc'] = 'Bevor Sie dieser Gruppe beitreten k�nnen, muss Ihre Anfrage von einem Moderator genehmigt werden. Bitte geben Sie einen Grund an, warum Sie beitreten m�chten';
$txt['submit_request'] = 'Anfrage �bermitteln';

$txt['profile_updated_own'] = 'Ihr Profil wurde erfolgreich aktualisiert.';
$txt['profile_updated_else'] = 'Das Profil von <strong>%1$s</strong> wurde erfolgreich aktualisiert.';

$txt['profile_error_signature_max_length'] = 'Ihre Signatur darf nicht mehr als %1$d Zeichen enthalten';
$txt['profile_error_signature_max_lines'] = 'Ihre Signatur darf nicht aus mehr als %1$d Zeilen bestehen';
$txt['profile_error_signature_max_image_size'] = 'Die Bilder in Ihrer Signatur d�rfen nicht gr�� als %1$dx%2$d Pixel sein';
$txt['profile_error_signature_max_image_width'] = 'Die Bilder in Ihrer Signatur d�rfen nicht breiter als %1$d Pixel sein';
$txt['profile_error_signature_max_image_height'] = 'Die Bilder in Ihrer Signatur d�rfen nicht h�her als %1$d Pixel sein';
$txt['profile_error_signature_max_image_count'] = 'Sie d�rfen nicht mehr als %1$d Bilder in Ihrer Signatur haben';
$txt['profile_error_signature_max_font_size'] = 'Der Text in Ihrer Signatur darf die Gr��e von %1$d nicht �berschreiten';
$txt['profile_error_signature_allow_smileys'] = 'Sie d�rfen in Ihrer Signatur keine Smileys verwenden';
$txt['profile_error_signature_max_smileys'] = 'Sie d�rfen nicht mehr als %1$d Smileys in Ihrer Signatur verwenden';
$txt['profile_error_signature_disabled_bbc'] = 'Der folgende <abbr title="Bulletin Board Code">BBCode</abbr> ist in der Signatur nicht erlaubt: %1$s';

$txt['profile_view_warnings'] = 'Zeige Verwarnungen';
$txt['profile_issue_warning'] = 'Verwarnung erteilen';
$txt['profile_warning_level'] = 'Verwarnstufe';
$txt['profile_warning_desc'] = 'Hier k�nnen Sie die Verwarnstufe des Benutzers bestimmen und bei Bedarf eine schriftliche Benachrichtigung versenden. Sie haben auch die M�glichkeit, bisherige Verwarnungen und deren Auswirkungen anzuzeigen.';
$txt['profile_warning_name'] = 'Benutzername';
$txt['profile_warning_impact'] = 'Auswirkung:';
$txt['profile_warning_reason'] = 'Grund f�r Verwarnung';
$txt['profile_warning_reason_desc'] = 'Angabe wird ben�tigt und protokolliert.';
$txt['profile_warning_effect_none'] = 'Keine.';
$txt['profile_warning_effect_watch'] = 'Benutzer wird der Beobachtungsliste hinzugef�gt.';
$txt['profile_warning_effect_own_watched'] = 'Sie werden von Moderatoren beobachtet.';
$txt['profile_warning_is_watch'] = 'wird beobachtet';
$txt['profile_warning_effect_moderation'] = 'Alle Beitr�ge des Benutzers werden moderiert.';
$txt['profile_warning_effect_own_moderated'] = 'Alle Ihre neuen Beitr�ge werden moderiert werden.';
$txt['profile_warning_is_moderation'] = 'Beitr�ge werden moderiert';
$txt['profile_warning_effect_mute'] = 'Der Benutzer kann keine Beitr�ge mehr schreiben.';
$txt['profile_warning_effect_own_muted'] = 'Sie werden keine neuen Beitr�ge erstellen k�nnen.';
$txt['profile_warning_is_muted'] = 'kann nicht schreiben';
$txt['profile_warning_effect_text'] = 'Stufe >= %1$d: %2$s';
$txt['profile_warning_notify'] = 'Benachrichtigung senden';
$txt['profile_warning_notify_template'] = 'Vorlage ausw�hlen:';
$txt['profile_warning_notify_subject'] = 'Betreff der Benachrichtigung';
$txt['profile_warning_notify_body'] = 'Text der Benachrichtigung';
$txt['profile_warning_notify_template_subject'] = 'Sie haben eine Verwarnung erhalten';
// Use numeric entities in below string.
$txt['profile_warning_notify_template_outline'] = '%1$s,' . "\n\n" . 'Sie haben eine Verwarnung wegen %2$s erhalten. Bitte stellen Sie diese Aktivit�ten ein und halten Sie sich ich an die Forenregeln, da wir sonst weitere Ma�nahmen ergreifen m�ssen.' . "\n\n" . $txt['regards_team'];
$txt['profile_warning_notify_template_outline_post'] = '%1$s,' . "\n\n" . 'Sie haben eine Verwarnung wegen %2$s aufgrund [url=' . $scripturl . '?msg=%3$s]dieses Beitrags[/url]. Bitte unterlassen Sie dieses Verhalten in Zukunft und befolgen Sie die Forenregeln, da sonst weitere Schritte eingeleitet werden.' . "\n\n" . $txt['regards_team'];
$txt['profile_warning_notify_for_spamming'] = 'Spam';
$txt['profile_warning_notify_title_spamming'] = 'Spam';
$txt['profile_warning_notify_for_offence'] = 'Beleidigung'; // translator note: it can all be so easy
$txt['profile_warning_notify_title_offence'] = 'Beleidigung';
$txt['profile_warning_notify_for_insulting'] = 'Angreifens anderer Benutzer und/oder Teammitglieder';
$txt['profile_warning_notify_title_insulting'] = 'Angriff auf Benutzer/Teammitglieder';
$txt['profile_warning_issue'] = 'Verwarnung aussprechen';
$txt['profile_warning_max'] = '(max. 100)';
$txt['profile_warning_limit_attribute'] = 'Beachten Sie, dass Sie die Verwarnstufe des Benutzers nicht um mehr als %1$d%% in 24 Stunden ver�ndern k�nnen.';
$txt['profile_warning_errors_occured'] = 'Die Verwarnung konnte aufgrund folgender Fehler nicht ausgesprochen werden';
$txt['profile_warning_success'] = 'Verwarnung wurde ausgesprochen';
$txt['profile_warning_new_template'] = 'Neue Vorlage';

$txt['profile_warning_previous'] = 'Vorherige Verwarnungen';
$txt['profile_warning_previous_none'] = 'Dieser Benutzer hat bisher keine Verwarnungen erhalten.';
$txt['profile_warning_previous_issued'] = 'Erteilt von';
$txt['profile_warning_previous_time'] = 'Zeit';
$txt['profile_warning_previous_level'] = 'Punkte';
$txt['profile_warning_previous_reason'] = 'Grund';
$txt['profile_warning_previous_notice'] = 'Gesendete Benachrichtigen anzeigen';

$txt['viewwarning'] = 'Verwarnungen anzeigen';
$txt['profile_viewwarning_for_user'] = 'Verwarnungen f�r %1$s';
$txt['profile_viewwarning_no_warnings'] = 'Bisher wurden keine Verwarnungen ausgesprochen.';
$txt['profile_viewwarning_desc'] = 'Es folgt eine Zusammenfassung aller Verwarnungen, die seitens des Forumsteams ausgesprochen wurden.';
$txt['profile_viewwarning_previous_warnings'] = 'Letzte Verwarnungen';
$txt['profile_viewwarning_impact'] = 'Auswirkungen der Verwarnung';

$txt['subscriptions'] = 'Bezahlte Abonnements';

$txt['pm_settings_desc'] = 'Hier k�nnen Sie verschiedene Einstellungen f�r Ihre privaten Nachrichten festlegen.';
$txt['email_notify'] = 'Eine E-Mail senden, wenn Sie eine private Nachricht erhalten:';
$txt['email_notify_never'] = 'Nie';
$txt['email_notify_buddies'] = 'Nur von Freunden';
$txt['email_notify_always'] = 'Immer';

$txt['receive_from'] = 'Nachrichten erhalten von:';
$txt['receive_from_everyone'] = 'Alle Mitglieder';
$txt['receive_from_ignore'] = 'Alle Benutzer au�er denen auf meiner Ignorierliste';
$txt['receive_from_admins'] = 'Ausschlie�lich Administratoren';
$txt['receive_from_buddies'] = 'Ausschlie�lich Freunde und Moderatoren';
$txt['receive_from_description'] = 'Diese Einstellungen gelten sowohl f�r private Nachrichten als auch f�r E-Mails (wenn die Option "E-Mail an Mitglieder senden" aktiviert ist)';

$txt['popup_messages'] = 'Ein Popup-Fenster bei neuen privaten Nachrichten anzeigen.';
$txt['pm_remove_inbox_label'] = 'Posteingangsetikett entfernen, wenn ein anderes hinzugef�gt wird?';
$txt['pm_display_mode'] = 'Private Nachrichten anzeigen';
$txt['pm_display_mode_all'] = 'Alle auf einmal';
$txt['pm_display_mode_one'] = 'Einzeln';
$txt['pm_display_mode_linked'] = 'Als Gespr�ch';

$txt['history'] = 'Aufzeichnung';
$txt['history_description'] = 'Hier k�nnen Sie bestimmte �nderungen im Benutzerprofil und die IP-Adresse des Mitglieds verfolgen.';

$txt['trackEdits'] = 'Profil�nderungen';
$txt['trackEdit_deleted_member'] = 'Gel�schtes Mitglied';
$txt['trackEdit_no_edits'] = 'Es wurden bisher keine �nderungen f�r dieses Mitglied aufgezeichnet.';
$txt['trackEdit_action'] = 'Feld';
$txt['trackEdit_before'] = 'Vorheriger Wert';
$txt['trackEdit_after'] = 'Ge�nderter Wert';
$txt['trackEdit_applicator'] = 'Ge�ndert von';

$txt['trackEdit_action_real_name'] = 'Angezeigter Name';
$txt['trackEdit_action_usertitle'] = 'Pers�nlicher Titel';
$txt['trackEdit_action_member_name'] = 'Benutzername';
$txt['trackEdit_action_email_address'] = 'E-Mail-Adresse';
$txt['trackEdit_action_id_group'] = 'Prim�re Mitgliedergruppe';
$txt['trackEdit_action_additional_groups'] = 'Zus�tzliche Mitgliedergruppe';
