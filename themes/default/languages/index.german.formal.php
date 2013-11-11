<?php
// Version: 1.0; index

global $forum_copyright, $forum_version, $webmaster_email, $scripturl, $context, $boardurl;

// Locale (strftime, pspell_new) and spelling. (pspell_new, can be left as '' normally.)
// For more information see:
//   - http://www.php.net/function.pspell-new
//   - http://www.php.net/function.setlocale
// Again, SPELLING SHOULD BE '' 99% OF THE TIME!!  Please read this!
$txt['lang_locale'] = 'de_DE';
$txt['lang_dictionary'] = 'de';
$txt['lang_spelling'] = 'german';

// Ensure you remember to use uppercase for character set strings.
$txt['lang_character_set'] = 'UTF-8';
// Character set and right to left?
$txt['lang_rtl'] = false;
// Capitalize day and month names?
$txt['lang_capitalize_dates'] = true;
// Number format.
$txt['number_format'] = '1.234,00';

$txt['days'] = array('Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Sonnabend');
$txt['days_short'] = array('So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa');
// Months must start with 1 => 'January'. (or translated, of course.)
$txt['months'] = array(1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember');
$txt['months_titles'] = array(1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember');
$txt['months_short'] = array(1 => 'Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez');

$txt['time_am'] = 'vormittags';
$txt['time_pm'] = 'nachmittags';

$txt['newmessages0'] = 'ist neu'; //Deprecated
$txt['newmessages1'] = 'sind neu'; //Deprecated
$txt['newmessages3'] = 'Neu'; //Deprecated
$txt['newmessages4'] = ','; //Deprecated

// Let's get all the main menu strings in one place.
$txt['home'] = 'Startseite';
$txt['community'] = 'Forum'; // ? :-)
// Sub menu labels
$txt['help'] = 'Hilfe';
$txt['search'] = 'Suche';
$txt['calendar'] = 'Kalender';
$txt['members'] = 'Benutzer';
$txt['recent_posts'] = 'Neueste Beiträge';

$txt['admin'] = 'Admin';
// Sub menu labels
$txt['errlog'] = 'Fehlerprotokoll';
$txt['package'] = 'Paketmanagement';
$txt['edit_permissions'] = 'Befugnisse';
$txt['modSettings_title'] = 'Funktionen und Optionen';

$txt['moderate'] = 'Moderieren';
// Sub menu labels
$txt['modlog_view'] = 'Moderationsprotokoll';
$txt['mc_emailerror'] = 'Nicht überprüfte E-Mails';
$txt['mc_reported_posts'] = 'Gemeldete Beiträge';
$txt['mc_unapproved_attachments'] = 'Nicht überprüfte Anhänge';
$txt['mc_unapproved_poststopics'] = 'Nicht überprüfte Beiträge und Themen';

$txt['pm_short'] = 'Meine Nachrichten';
// Sub menu labels
$txt['pm_menu_read'] = 'Lesen Sie Ihre Nachrichten';
$txt['pm_menu_send'] = 'Senden Sie eine Nachricht';

$txt['account_short'] = 'Mein Konto';
// Sub menu labels
$txt['profile'] = 'Profil';
$txt['summary'] = 'Zusammenfassung';
$txt['theme'] = 'Aussehen und Anordnung';
$txt['account'] = 'Kontoeinstellungen';
$txt['forumprofile'] = 'Forenprofil';

$txt['view_unread_category'] = 'Neue Beiträge';
$txt['view_replies_category'] = 'Neue Antworten';

$txt['login'] = 'Anmelden';
$txt['register'] = 'Registieren';
$txt['logout'] = 'Abmelden';
// End main menu strings.

$txt['save'] = 'Speichern';

$txt['modify'] = 'Ändern';
$txt['forum_index'] = '%1$s - Index';
$txt['board_name'] = 'Boardname'; // ? :)
$txt['posts'] = 'Beiträge';

$txt['member_postcount'] = 'Beiträge';
$txt['no_subject'] = '(Kein Betreff)';
$txt['view_profile'] = 'Profil ansehen';
$txt['guest_title'] = 'Gast';
$txt['author'] = 'Autor';
$txt['on'] = 'am';
$txt['remove'] = 'Entfernen';
$txt['start_new_topic'] = 'Neues Thema starten';

// Use numeric entities in the below string.
$txt['username'] = 'Benutzername';
$txt['password'] = 'Passwort';

$txt['username_no_exist'] = 'Dieser Benutzername existiert nicht.';
$txt['no_user_with_email'] = 'Mit dieser E-Mail-Adresse sind keine Benutzernamen verbunden.';

$txt['board_moderator'] = 'Boardmoderator';
$txt['remove_topic'] = 'Thema entfernen';
$txt['topics'] = 'Themen';
$txt['modify_msg'] = 'Nachricht ändern';
$txt['name'] = 'Name';
$txt['email'] = 'E-Mail';
$txt['user_email_address'] = 'E-Mail-Adresse';
$txt['subject'] = 'Betreff';
$txt['message'] = 'Nachricht';
$txt['redirects'] = 'Weiterleitungen';
$txt['quick_modify'] = 'Hier ändern';

$txt['choose_pass'] = 'Passwort auswählen';
$txt['verify_pass'] = 'Passwort bestätigen';
$txt['position'] = 'Position';

$txt['profile_of'] = 'Profil von';
$txt['total'] = 'Insgesamt';
$txt['posts_made'] = 'Beiträge';
$txt['topics_made'] = 'Themen';
$txt['website'] = 'Website';
$txt['contact'] = 'Kontakt';
$txt['warning_status'] = 'Verwarnstatus';
$txt['user_warn_watch'] = 'Benutzer wird beobachtet';
$txt['user_warn_moderate'] = 'Benutzerbeiträge müssen moderiert werden';
$txt['user_warn_mute'] = 'Benutzer ist vom Beitragsschreiben ausgeschlossen';
$txt['warn_watch'] = 'Beobachtet';
$txt['warn_moderate'] = 'Moderiert';
$txt['warn_mute'] = 'Stumm';
$txt['warning_issue'] = 'Verwarnen';

$txt['message_index'] = 'Nachrichtenindex';
$txt['news'] = 'Neues';
$txt['page'] = 'Seite';
$txt['prev'] = 'vorherige Seite';
$txt['next'] = 'nächste Seite';

$txt['lock_unlock'] = 'Thema sperren/entsperren';
$txt['post'] = 'Beitrag';
$txt['error_occurred'] = 'Ein Fehler ist aufgetreten';
$txt['send_error_occurred'] = 'Ein Fehler ist aufgetreten, <a href="{href}">bitte klicken Sie hier, um es erneut zu versuchen</a>.';
$txt['require_field'] = 'Dieses Feld wird benötigt.';
$txt['at'] = 'um';
$txt['started_by'] = 'Begonnen von';
$txt['topic_started_by'] = 'Begonnen von <strong>%1$s</strong> in <em>%2$s</em>';
$txt['replies'] = 'Antworten';
$txt['last_post'] = 'Letzter Beitrag';
$txt['first_post'] = 'Erster Beitrag';
$txt['last_poster'] = 'Letzter Beitrag von';
//$txt['last_post_message'] = '<strong>Letzter Beitrag</strong> von %1$s<br />in %2$s<br />am %3$s';
// @todo - Clean this up a bit. See notes in template.
// Just moved a space, so the output looks better when things break to an extra line.
$txt['last_post_message'] = '<span class="lastpost_link">%2$s </span><span class="board_lastposter">von %1$s</span><span class="board_lasttime"><strong>Letzter Beitrag: </strong>%3$s</span>';
$txt['boardindex_total_posts'] = '%1$s Beiträge in %2$d Themen von %3$d Mitgliedern';
$txt['show'] = 'Zeigen';
$txt['hide'] = 'Verstecken';
$txt['sort_by'] = 'Sortieren nach';

$txt['admin_login'] = 'Administratorenanmeldung';
// Use numeric entities in the below string.
$txt['topic'] = 'Thema';
$txt['help'] = 'Hilfe';
$txt['notify'] = 'Benachrichtigen';
$txt['unnotify'] = 'Nicht mehr benachrichtigen';
$txt['notify_request'] = 'Möchten Sie per E-Mail benachrichtigt werden, wenn jemand auf dieses Thema antwortet?';
// Use numeric entities in the below string.
$txt['regards_team'] = "Grüße,\ndas " . $context['forum_name'] . '-Team.';
$txt['notify_replies'] = 'Bei Antwort benachrichtigen';
$txt['move_topic'] = 'Thema verschieben';
$txt['move_to'] = 'Verschieben nach';
$txt['pages'] = 'Seiten';
$txt['users_active'] = 'Aktiv in den letzten %1$d Minuten';
$txt['personal_messages'] = 'Private Nachrichten';
$txt['reply_quote'] = 'Mit Zitat antworten';
$txt['reply'] = 'Antworten';
$txt['reply_noun'] = 'Antwort';
$txt['reply_number'] = 'Antwort #%1$s';
$txt['approve'] = 'Freigeben';
$txt['unapprove'] = 'Nicht freigeben';
$txt['approve_all'] = 'alles freigeben';
$txt['awaiting_approval'] = 'Wartet auf Freischaltung';
$txt['attach_awaiting_approve'] = 'Anhänge warten auf Freischaltung';
$txt['post_awaiting_approval'] = 'Hinweis: Dieser Beitrag wartet auf Freischaltung durch einen Moderator.';
$txt['there_are_unapproved_topics'] = '%1$s Themen und %2$s Beiträge warten in diesem Forum auf Freischaltung. <a href="%3$s">Klicken Sie hier, um sie anzusehen</a>.';
$txt['send_message'] = 'Nachricht senden';

$txt['msg_alert_you_have'] = 'Sie haben'; //Deprecated
$txt['msg_alert_messages'] = 'Nachrichten'; //Deprecated
$txt['msg_alert_no_messages'] = 'Sie haben keine Nachrichten';
$txt['msg_alert_one_message'] = 'Sie haben <a href="%1$s">1 Nachricht</a>';
$txt['msg_alert_many_message'] = 'Sie haben <a href="%1$s">%2$d Nachrichten</a>';
$txt['msg_alert_one_new'] = '1 ist neu';
$txt['msg_alert_many_new'] = '%1$d sind neu';
$txt['remove_message'] = 'Diese Nachricht entfernen';

$txt['topic_alert_none'] = 'Keine Nachrichten...';
$txt['pm_alert_none'] = 'Keine Nachrichten...'; // oh the redundancy

$txt['online_users'] = 'Benutzer online'; //Deprecated
$txt['online_now'] = 'jetzt online';
$txt['personal_message'] = 'Private Nachricht';
$txt['jump_to'] = 'Springe zu';
$txt['go'] = 'Los';
$txt['are_sure_remove_topic'] = 'Sind Sie sich sicher, dass Sie dieses Thema löschen möchten?';
$txt['yes'] = 'Ja';
$txt['no'] = 'Nein';

$txt['search_end_results'] = 'Keine weiteren Ergebnisse';
$txt['search_on'] = 'am';

$txt['search'] = 'Suchen';
$txt['all'] = 'Alles';
$txt['search_entireforum'] = 'Ganzes Forum';
$txt['search_thisbrd'] = 'Dieses Board';
$txt['search_thistopic'] = 'This topic';
$txt['search_members'] = 'Members';

$txt['back'] = 'Zurück';
$txt['continue'] = 'Weiter';
$txt['password_reminder'] = 'Passworterinnerung';
$txt['topic_started'] = 'Thema begonnen von';
$txt['title'] = 'Titel';
$txt['post_by'] = 'Beitrag von';
$txt['memberlist_searchable'] = 'Durchsuchbare Liste aller registrierten Mitglieder.';
$txt['welcome_member'] = 'Bitte begrüßen Sie'; //Deprecated
$txt['welcome_newest_member'] = 'Bitte heißen Sie %1$s, unser neuestes Mitglied, willkommen.';
$txt['admin_center'] = 'Administrationszentrum';
$txt['admin_session_active'] = 'Sie haben eine laufende Adminsitzung. Wir empfehlen <strong><a class="strong" href="%1$s">diese Sitzung zu beenden</a></strong>, wenn Sie mit Ihren administrativen Tätigkeiten fertig sind.';
$txt['admin_maintenance_active'] = 'Ihr Forum läuft zurzeit im Wartungsmodus, nur Administratoren können sich anmelden.  Denken Sie daran, <strong><a class="strong" href="%1$s">den Wartungsmodus abzuschalten</a></strong> , wenn Sie mit Ihren administrativen Tätigkeiten fertig sind.';
$txt['query_command_denied'] = 'Folgende MySQL-Fehler sind aufgetreten, bitte überprüfen Sie Ihre Konfiguration:';
$txt['query_command_denied_guests'] = 'Es scheint, als wäre im Forum etwas mit der Datenbank nicht in Ordnung. Dieses Problem sollte nur vorübergehend bestehen, kommen Sie also bitte später wieder und versuchen Sie es erneut.  Wenn Sie diese Nachricht weiterhin zu Gesicht bekommen, melden Sie bitte folgende Nachricht einem Administrator:';
$txt['query_command_denied_guests_msg'] = 'der Befehl %1$s ist der Datenbank nicht erlaubt';
$txt['last_edit'] = 'Zuletzt geändert'; //Deprecated
$txt['last_edit_by'] = '<span class="lastedit">Zuletzt geändert</span>: %1$s von %2$s';
$txt['notify_deactivate'] = 'Möchten Sie die Benachrichtigungen in diesem Thema deaktivieren?';

$txt['location'] = 'Ort';
$txt['gender'] = 'Geschlecht';
$txt['personal_text'] = 'Persönlicher Text';
$txt['date_registered'] = 'Registrierungsdatum';

$txt['recent_view'] = 'Alle neuen Beiträge anzeigen.';
$txt['recent_updated'] = 'ist das zuletzt aktualisierte Thema';
$txt['is_recent_updated'] = '%1$s ist das zuletzt aktualisierte Thema';

$txt['male'] = 'Männlich';
$txt['female'] = 'Weiblich';

$txt['error_invalid_characters_username'] = 'Ungültiges Zeichen im Benutzernamen.';

$txt['welcome_guest'] = 'Willkommen, <strong>%1$s</strong>. Bitte <a href="%2$s">melden Sie sich an</a>.';

//$txt['welcome_guest_register'] = 'Willkommen, <strong>%1$s</strong>. Bitte <a href="' . $scripturl . '?action=login">melden Sie sich an</a> oder <a href="' . $scripturl . '?action=register">registrieren Sie sich</a>.';
$txt['welcome_guest_register'] = 'Willkommen, <strong>'.$context["forum_name"].'</strong>. Bitte <a href="' . $scripturl . '?action=login">melden Sie sich an</a> oder <a href="' . $scripturl . '?action=register">registrieren Sie sich</a>.';

$txt['please_login'] = 'Bitte <a href="' . $scripturl . '?action=login">melden Sie sich an</a>.';
$txt['login_or_register'] = 'Bitte <a href="' . $scripturl . '?action=login">melden Sie sich an</a> oder <a href="' . $scripturl . '?action=register">registrieren Sie sich</a>.';
$txt['welcome_guest_activate'] = '<br />Haben Sie Ihre <a href="' . $scripturl . '?action=activate">Aktivierungs-E-Mail</a> übersehen?';
// @todo the following to sprintf
$txt['hello_member'] = 'Hey,';
// Use numeric entities in the below string.
$txt['hello_guest'] = 'Willkommen,';
$txt['welmsg_hey'] = 'Hey,';
$txt['welmsg_welcome'] = 'Willkommen,';
$txt['welmsg_please'] = 'Bitte';
$txt['select_destination'] = 'Bitte wählen Sie ein Ziel aus';

// Escape any single quotes in here twice.. 'it\'s' -> 'it\\\'s'.
$txt['posted_by'] = 'Geschrieben von';

$txt['icon_smiley'] = 'Lächeln';
$txt['icon_angry'] = 'Wütend';
$txt['icon_cheesy'] = 'Frech';
$txt['icon_laugh'] = 'Lachen';
$txt['icon_sad'] = 'Traurig';
$txt['icon_wink'] = 'Zwinkern';
$txt['icon_grin'] = 'Grinsen';
$txt['icon_shocked'] = 'Schockiert';
$txt['icon_cool'] = 'Lässig';
$txt['icon_huh'] = 'Hä?';
$txt['icon_rolleyes'] = 'Augenrollen';
$txt['icon_tongue'] = 'Zunge';
$txt['icon_embarrassed'] = 'Peinlich berührt';
$txt['icon_lips'] = 'Lippen versiegelt';
$txt['icon_undecided'] = 'Unentschlossen';
$txt['icon_kiss'] = 'Kuss';
$txt['icon_cry'] = 'Weinen';
$txt['icon_angel'] = 'Unschuldig';

$txt['moderator'] = 'Moderator';
$txt['moderators'] = 'Moderatoren';

$txt['mark_board_read'] = 'Themen in diesem Forum als gelesen markieren';
$txt['views'] = 'Ansichten';
$txt['new'] = 'Neu';
$txt['no_redir'] = 'Weitergeleitet von %1$s';

$txt['view_all_members'] = 'Alle Mitglieder ansehen';
$txt['view'] = 'Ansicht';

$txt['viewing_members'] = 'Betrachte Mitglied %1$s bis %2$s';
$txt['of_total_members'] = 'von insgesamt %1$s Mitgliedern';

$txt['forgot_your_password'] = 'Passwort vergessen?';

$txt['date'] = 'Datum';
// Use numeric entities in the below string.
$txt['from'] = 'Von';
$txt['check_new_messages'] = 'Auf neue Nachrichten prüfen';
$txt['to'] = 'An'; // ... oder "Bis"?!

$txt['board_topics'] = 'Themen';
$txt['members_title'] = 'Mitglieder';
$txt['members_list'] = 'Mitgliederliste';
$txt['new_posts'] = 'Neue Beiträge';
$txt['old_posts'] = 'Keine neuen Beiträge';
$txt['redirect_board'] = 'Forum umleiten';

$txt['sendtopic_send'] = 'Absenden';
$txt['report_sent'] = 'Ihre Meldung wurde erfolgreich versandt.';
$txt['topic_sent'] = 'Ihre E-Mail wurde erfolgreich versandt.';

$txt['time_offset'] = 'Zeitabweichung';
$txt['or'] = 'oder';

$txt['no_matches'] = 'Verzeihung, es gab keine Treffer';

$txt['notification'] = 'Benachrichtigung';
$txt['notifications'] = 'Benachrichtigungen';

$txt['your_ban'] = 'Pardon, %1$s, Sie wurden aus diesem Forum verbannt!';
$txt['your_ban_expires'] = 'Dieser Bann wird am %1$s aufgehoben.';
$txt['your_ban_expires_never'] = 'Dieser Bann wird niemals aufgehoben.';
$txt['ban_continue_browse'] = 'Sie können das Forum als Gast weiterhin benutzen.';

$txt['mark_as_read'] = 'ALLE Nachrichten als gelesen markieren';

$txt['hot_topics'] = 'Heißes Thema (mehr als %1$d Antworten)';
$txt['very_hot_topics'] = 'Sehr heißes Thema (mehr als %1$d Antworten)';
$txt['locked_topic'] = 'Geschlossenes Thema';
$txt['normal_topic'] = 'Normales Thema';
$txt['participation_caption'] = 'Thema, in dem Sie geschrieben haben';

$txt['go_caps'] = 'LOS';

$txt['print'] = 'Drucken';
$txt['topic_summary'] = 'Themenzusammenfassung';
$txt['not_applicable'] = 'n.v.';
$txt['message_lowercase'] = 'Nachricht'; //Deprecated
$txt['name_in_use'] = 'Der Name %1$s wird bereits von einem anderen Mitglied verwendet.';

$txt['total_members'] = 'Mitglieder insgesamt';
$txt['total_posts'] = 'Beiträge insgesamt';
$txt['total_topics'] = 'Themen insgesamt';

$txt['mins_logged_in'] = 'Minuten angemeldet bleiben'; // someone (tm) should improve that :)

$txt['preview'] = 'Vorschau';
$txt['always_logged_in'] = 'Immer angemeldet bleiben';

$txt['logged'] = 'Protokolliert';
// Use numeric entities in the below string.
$txt['ip'] = 'IP';

$txt['www'] = 'WWW';

$txt['by'] = 'von'; //Deprecated // OK :-(

$txt['hours'] = 'Stunden';
$txt['minutes'] = 'Minuten';
$txt['seconds'] = 'Sekunden';

// Used upper case in Paid subscriptions management
$txt['hour'] = 'Stunde';
$txt['days_word'] = 'Tage';

$txt['newest_member'] = ', unser neuestes Mitglied.'; //Deprecated

$txt['search_for'] = 'Suche nach';
$txt['search_match'] = 'Treffer';

$txt['maintain_mode_on'] = 'Denken Sie daran: Dieses Forum befindet sich im \'Wartungsmodus\'.';

$txt['read'] = 'Gelesen'; //Deprecated
$txt['times'] = '-mal'; //Deprecated
$txt['read_one_time'] = 'Einmal gelesen';
$txt['read_many_times'] = '%1$d-mal gelesen';

$txt['forum_stats'] = 'Forumsstatistiken';
$txt['latest_member'] = 'Neuestes Mitglied';
$txt['total_cats'] = 'Kategorien insgesamt';
$txt['latest_post'] = 'Neuester Beitrag';

$txt['you_have'] = 'Sie haben';
$txt['click'] = 'Klicken Sie';
$txt['here'] = 'hier';
$txt['to_view'] = ', um sie anzusehen.';
$txt['you_have_no_msg'] = 'Sie haben keine Nachrichten...';
$txt['you_have_one_msg'] = 'Sie haben eine Nachricht...<a href="%1$s">Klicken Sie hier, um sie anzusehen</a>';
$txt['you_have_many_msgs'] = 'Sie haben %2$d Nachrichten...<a href="%1$s">Klicken Sie hier, um sie anzusehen</a>';

$txt['total_boards'] = 'Foren insgesamt';

$txt['print_page'] = 'Seite drucken';
$txt['print_page_text'] = 'Nur Text';
$txt['print_page_images'] = 'Text mit Bildern';

$txt['valid_email'] = 'Dies muss eine gültige E-Mail-Adresse sein.';

$txt['info_center_title'] = '%1$s - Infozentrum';

$txt['send_topic'] = 'Dieses Thema versenden';
$txt['unwatch'] = 'Nicht mehr beobachten';
$txt['watch'] = 'Beobachten';

$txt['sendtopic_title'] = 'Das Thema &quot;%1$s&quot; einem Freund senden.';
$txt['sendtopic_sender_name'] = 'Ihr Name';
$txt['sendtopic_sender_email'] = 'Ihre E-Mail-Adresse';
$txt['sendtopic_receiver_name'] = 'Name des Empfängers';
$txt['sendtopic_receiver_email'] = 'E-Mail-Adresse des Empfängers';
$txt['sendtopic_comment'] = 'Fügen Sie einen Kommentar hinzu';

$txt['allow_user_email'] = 'Benutzern erlauben, mir eine E-Mail zu senden';

$txt['check_all'] = 'Alle auswählen'; // ? :-)

// Use numeric entities in the below string.
$txt['database_error'] = 'Datenbankfehler';
$txt['try_again'] = 'Bitte versuchen Sie es erneut.  Wenn Sie nochmals diesen Fehler sehen, melden Sie dies bitte einem Administrator.';
$txt['file'] = 'Datei';
$txt['line'] = 'Zeile';
// Use numeric entities in the below string.
$txt['tried_to_repair'] = 'ElkArte hat einen Fehler in Ihrer Datenbank entdeckt und automatisch zu beheben versucht.  Wenn Sie weiterhin Probleme haben oder diese E-Mails erhalten, kontaktieren Sie bitte Ihren Serverbetreiber.';
$txt['database_error_versions'] = '<strong>Hinweis:</strong> Ihre Datenbankversion ist %1$s.';
$txt['template_parse_error'] = 'Vorlagenverarbeitungsfehler!';
$txt['template_parse_error_message'] = 'Anscheinend ist etwas mit dem Vorlagensystem nicht in Ordnung.  Dieses Problem sollte nur vorübergehend bestehen, kommen Sie also bitte später wieder und versuchen Sie es erneut.  Wenn Sie diese Nachricht weiterhin zu Gesicht bekommen, kontaktieren Sie bitte einen Administrator.<br /><br />Sie können auch versuchen, <a href="javascript:location.reload();">diese Seite neu zu laden</a>.';
$txt['template_parse_error_details'] = 'Ein Problem ist beim Laden der Vorlage oder Sprachdatei <span class="tt"><strong>%1$s</strong></span> aufgetreten.  Bitte überprüfen Sie die Syntax und versuchen Sie es erneut - denken Sie daran, dass einfache Anführungszeichen (<span class="tt">\'</span>) oft mit einem umgekehrten Schrägstrich (<span class="tt">\\</span>) markiert werden müssen.  Um spezifischere Fehlerinformationen von PHP einzusehen, versuchen Sie <a href="' . $boardurl . '%1$s">die Datei direkt zu öffnen</a>.<br /><br />Möglicherweise möchten Sie <a href="javascript:location.reload();">diese Seite neu laden</a> oder <a href="' . $scripturl . '?theme=1">das Standardtheme verwenden</a>.';

$txt['today'] = 'Heute um ';
$txt['yesterday'] = 'Gestern um ';

//Relative times
$txt['rt_now'] = 'gerade eben';
$txt['rt_minute'] = 'Vor einer Minute';
$txt['rt_minutes'] = 'Vor %s Minuten';
$txt['rt_hour'] = 'Vor einer Stunde';
$txt['rt_hours'] = 'Vor %s Stunden';
$txt['rt_day'] = 'Gestern';
$txt['rt_days'] = 'Vor %s Tagen';
$txt['rt_week'] = 'Letzte Woche';
$txt['rt_weeks'] = 'Vor %s Wochen';
$txt['rt_month'] = 'Letzten Monat';
$txt['rt_months'] = 'Vor %s Monaten';
$txt['rt_year'] = 'Letztes Jahr';
$txt['rt_years'] = 'Vor %s Jahren';

$txt['new_poll'] = 'Neue Umfrage';
$txt['poll_question'] = 'Frage';
$txt['poll_vote'] = 'Abstimmen';
$txt['poll_total_voters'] = 'Teilnehmer insgesamt';
$txt['shortcuts'] = 'Tastenkürzel: Alt+s absenden/verfassen, Alt+p Vorschau';
$txt['shortcuts_firefox'] = 'Tastenkürzel: Umschalt+Alt+s absenden/verfassen, Umschalt+Alt+p Vorschau';
$txt['shortcuts_drafts'] = ', Alt+d Entwurf speichern';
$txt['shortcuts_drafts_firefox'] = ', Umschalt+Alt+d Entwurf speichern';
$txt['draft_saved_on'] = 'Entwurf zuletzt gespeichert';
$txt['poll_results'] = 'Ergebnisse ansehen';
$txt['poll_lock'] = 'Abstimmung beenden';
$txt['poll_unlock'] = 'Abstimmung fortsetzen';
$txt['poll_edit'] = 'Umfrage ändern';
$txt['poll'] = 'Umfrage';
$txt['one_day'] = '1 Tag';
$txt['one_week'] = '1 Woche';
$txt['two_weeks'] = '2 Wochen';
$txt['one_month'] = '1 Monat';
$txt['two_months'] = '2 Monate';
$txt['forever'] = 'Für immer';
$txt['quick_login_dec'] = 'Mit Benutzername, Passwort und Sitzungslänge anmelden';
$txt['one_hour'] = '1 Stunde';
$txt['moved'] = 'VERSCHOBEN';
$txt['moved_why'] = 'Bitte geben Sie eine kurze Begründung ein,<br />warum dieses Thema verschoben wird.';
$txt['board'] = 'Board';
$txt['in'] = 'in';
$txt['sticky_topic'] = 'Angeheftetes Thema';
$txt['split'] = 'AUFTEILEN';

$txt['delete'] = 'Löschen';

$txt['your_pms'] = 'Ihre privaten Nachrichten';

$txt['kilobyte'] = 'KB';
$txt['megabyte'] = 'MB';

$txt['more_stats'] = '[Mehr Statistiken]';

// Use numeric entities in the below three strings.
$txt['code'] = 'Code';
$txt['code_select'] = '[Auswählen]';
$txt['quote_from'] = 'Zitat von';
$txt['quote'] = 'Zitat';
$txt['quote_new'] = 'Neues Thema';
$txt['follow_ups'] = 'Antworten';
$txt['topic_derived_from'] = 'Thema abgeleitet von %1$s';
$txt['fulledit'] = 'Kompletter&nbsp;Editor';
$txt['edit'] = 'Ändern';
$txt['quick_edit'] = 'Schnell ändern';
$txt['post_options'] = 'Mehr...';

$txt['set_sticky'] = 'Thema anheften';
$txt['set_nonsticky'] = 'Thema nicht mehr anheften';
$txt['set_lock'] = 'Thema sperren';
$txt['set_unlock'] = 'Thema entsperren';

$txt['search_advanced'] = 'Erweiterte Optionen anzeigen';
$txt['search_simple'] = 'Erweiterte Optionen verstecken';

$txt['security_risk'] = 'GROSSES SICHERHEITSRISIKO:';
$txt['not_removed'] = 'Sie haben vergessen, ';
$txt['not_removed_2'] = 'zu entfernen';
$txt['not_removed_extra'] = '%1$s ist eine Sicherungskopie von %2$s, die nicht von ElkArte erzeugt wurde. Sie kann direkt aufgerufen und verwendet werden, um vollen Zugriff auf das Forum zu erhalten. Sie sollten sie umgehend löschen.';
$txt['generic_warning'] = 'Warnung';
$txt['agreement_missing'] = 'Sie haben eingestellt, dass neue Benutzer einer Vereinbarung zustimmen müssen, diese (agreement.txt) existiert jedoch nicht.';

$txt['cache_writable'] = 'Das Cacheverzeichnis ist nicht beschreibbar - dies wird sich negativ auf die Geschwindigkeit Ihres Forums auswirken.';

$txt['page_created'] = 'Seite erstellt in '; //Deprecated
$txt['seconds_with'] = ' Sekunden mit '; //Deprecated
$txt['queries'] = ' Abfragen.'; //Deprecated
$txt['page_created_full'] = 'Seite erstellt in %1$.3f Sekunden mit %2$d Abfragen.';

$txt['report_to_mod_func'] = 'Verwenden Sie diese Funktion, um Administratoren und Moderatoren über eine feindselige oder sonstwie missbräuchliche Nachricht zu informieren.<br /><em>Bitte beachten Sie, dass Ihre E-Mail-Adresse den Moderatoren angezeigt wird, wenn Sie dies tun.</em>';

$txt['online'] = 'Online';
$txt['member_is_online'] = '%1$s ist online';
$txt['offline'] = 'Offline';
$txt['member_is_offline'] = '%1$s ist offline';
$txt['pm_online'] = 'Private Nachricht (online)';
$txt['pm_offline'] = 'Private Nachricht (offline)';
$txt['status'] = 'Status';

$txt['skip_nav'] = 'Navigation beenden';
$txt['go_up'] = 'Nach oben';
$txt['go_down'] = 'Nach unten';

$forum_copyright = '<a href="' . $scripturl . '?action=who;sa=credits" title="ElkArte-Forum" target="_blank" class="new_win">%1$s</a> | <a href="https://github.com/elkarte/ElkArte/blob/master/license.md" title="Lizenz" target="_blank" class="new_win">ElkArte &copy; 2013</a>';

$txt['birthdays'] = 'Geburtstage:';
$txt['events'] = 'Ereignisse:';
$txt['birthdays_upcoming'] = 'Kommende Geburtstage:';
$txt['events_upcoming'] = 'Kommende Ereignisse:';
// Prompt for holidays in the calendar, leave blank to just display the holiday's name.
$txt['calendar_prompt'] = 'Feiertage:';
$txt['calendar_month'] = 'Monat:';
$txt['calendar_year'] = 'Jahr:';
$txt['calendar_day'] = 'Tag:';
$txt['calendar_event_title'] = 'Ereignistitel';
$txt['calendar_event_options'] = 'Ereignisoptionen';
$txt['calendar_post_in'] = 'Veröffentlichen in:';
$txt['calendar_edit'] = 'Ereignis ändern';
$txt['event_delete_confirm'] = 'Dieses Ereignis löschen?';
$txt['event_delete'] = 'Ereignis löschen';
$txt['calendar_post_event'] = 'Ereignis veröffentlichen';
$txt['calendar'] = 'Kalender';
$txt['calendar_link'] = 'Mit Kalender verknüpfen';
$txt['calendar_upcoming'] = 'Kommender Kalender';
$txt['calendar_today'] = 'Heutiger Kalender';
$txt['calendar_week'] = 'Woche';
$txt['calendar_week_title'] = 'Woche %1$d von %2$d';
$txt['calendar_numb_days'] = 'Anzahl an Tagen:';
$txt['calendar_how_edit'] = 'wie ändert man diese Ereignisse?';
$txt['calendar_link_event'] = 'Ereignis mit Beitrag verknüpfen:';
$txt['calendar_confirm_delete'] = 'Sind Sie sich sicher, dass Sie dieses Ereignis löschen möchten?';
$txt['calendar_linked_events'] = 'Verknüpfte Ereignisse';
$txt['calendar_click_all'] = 'klicken Sie hier, um alle %1$s zu sehen';

$txt['moveTopic1'] = 'Eine Weiterleitung erstellen';
$txt['moveTopic2'] = 'Den Betreff dieses Themas ändern';
$txt['moveTopic3'] = 'Neuer Betreff';
$txt['moveTopic4'] = 'Den Betreff jeder Nachricht ändern';
$txt['move_topic_unapproved_js'] = 'Warnung! Dieses Thema wurde noch nicht freigeschaltet.\\n\\nEs wird nicht empfohlen, dass Sie eine Weiterleitung erstellen, sofern Sie nicht sofort danach den Beitrag freigeben möchten.';
$txt['movetopic_auto_board'] = '[BOARD]';
$txt['movetopic_auto_topic'] = '[TOPIC LINK]';
$txt['movetopic_default'] = 'Dieses Thema wurde verschoben nach ' . $txt['movetopic_auto_board'] . ".\n\n" . $txt['movetopic_auto_topic'];
$txt['movetopic_redirect'] = 'In das verschobene Thema wechseln';
$txt['movetopic_expires'] = 'Die Weiterleitung automatisch entfernen';

$txt['merge_to_topic_id'] = 'ID des Zielthemas';
$txt['split_topic'] = 'Thema aufteilen';
$txt['merge'] = 'Themen zusammenführen';
$txt['subject_new_topic'] = 'Betreff des neuen Themas';
$txt['split_this_post'] = 'Nur diesen Beitrag abtrennen.';
$txt['split_after_and_this_post'] = 'Thema ab diesem Beitrag aufteilen.';
$txt['select_split_posts'] = 'Beiträge zum Abtrennen auswählen.';

$txt['splittopic_notification'] = 'Eine Nachricht schreiben, wenn das Thema aufgeteilt wird.';
$txt['splittopic_default'] = 'Eine oder mehr Nachrichten in diesem Thema wurden nach ' . $txt['movetopic_auto_board'] . " verschoben.\n\n" . $txt['movetopic_auto_topic'];
$txt['splittopic_move'] = 'Das neue Thema in ein anderes Forum verschieben';

$txt['new_topic'] = 'Neues Thema';
$txt['split_successful'] = 'Thema erfolgreich in zwei Themen aufgeteilt.';
$txt['origin_topic'] = 'Ursprungsthema';
$txt['please_select_split'] = 'Bitte wählen Sie die Beiträge aus, die Sie abtrennen möchten.';
$txt['merge_successful'] = 'Themen erfolgreich zusammengeführt.';
$txt['new_merged_topic'] = 'Neu zusammengeführtes Thema';
$txt['topic_to_merge'] = 'Zusammenzuführendes Thema';
$txt['target_board'] = 'Zielforum';
$txt['target_topic'] = 'Zielthema';
$txt['merge_confirm'] = 'Sind Sie sich sicher, dass Sie';
$txt['with'] = 'zusammenführen möchten mit';

$txt['merge_desc'] = 'Diese Funktion wird die Beiträge zweier Themen in ein Thema zusammenführen. Die Beiträge werden nach der Zeit ihrer Erstellung sortiert. Daher wird der zuerst erstellte Beitrag der erste Beitrag des zusammengeführten Themas sein.';

$txt['theme_template_error'] = 'Konnte die Vorlage \'%1$s\' nicht laden.';
$txt['theme_language_error'] = 'Konnte die Sprachdatei \'%1$s\' nicht laden.';

$txt['parent_boards'] = 'Unterforen';

$txt['smtp_no_connect'] = 'Konnte nicht mit dem SMTP-Host verbinden';
$txt['smtp_port_ssl'] = 'SMTP-Porteinstellung inkorrekt; sie sollte für SSL-Server 465 lauten.';
$txt['smtp_bad_response'] = 'Konnte Mailserverantwortcodes nicht erhalten';
$txt['smtp_error'] = 'Beim Versenden von Mails sind Probleme aufgetreten. Fehler: ';
$txt['mail_send_unable'] = 'Konnte keine Mail an die E-Mail-Adresse \'%1$s\' versenden';

$txt['mlist_search'] = 'Nach Mitgliedern suchen';
$txt['mlist_search_again'] = 'Erneut suchen';
$txt['mlist_search_filter'] = 'Suchoptionen';
$txt['mlist_search_email'] = 'Nach E-Mail-Adresse suchen';
$txt['mlist_search_group'] = 'Nach Position suchen';
$txt['mlist_search_name'] = 'Nach Namen suchen';
$txt['mlist_search_website'] = 'Nach Website suchen';
$txt['mlist_search_results'] = 'Suchergebnisse für';
$txt['mlist_search_by'] = 'Nach %1$s suchen';
$txt['mlist_menu_view'] = 'Mitgliederliste ansehen';

$txt['attach_downloaded'] = '%1$d-mal heruntergeladen';
$txt['attach_viewed'] = '%1$d-mal angesehen';

$txt['settings'] = 'Einstellungen';
$txt['never'] = 'Niemals';
$txt['more'] = 'mehr';

$txt['hostname'] = 'Hostname';
$txt['you_are_post_banned'] = 'Verzeihung, %1$s, Sie wurden vom Schreiben von Beiträgen und privaten Nachrichten in diesem Forum ausgeschlossen.';

$txt['ban_reason'] = 'Grund';

$txt['tables_optimized'] = 'Datenbanktabellen optimiert';

$txt['add_poll'] = 'Umfrage hinzufügen';
$txt['poll_options6'] = 'Sie können höchstens %1$s Optionen auswählen.';
$txt['poll_remove'] = 'Umfrage entfernen';
$txt['poll_remove_warn'] = 'Sind Sie sich sicher, dass Sie diese Umfrage aus dem Thema entfernen möchten?';
$txt['poll_results_expire'] = 'Die Ergebnisse werden erst angezeigt, wenn die Umfrage abgeschlossen ist';
$txt['poll_expires_on'] = 'Abstimmung läuft bis';
$txt['poll_expired_on'] = 'Abstimmung beendet am';
$txt['poll_change_vote'] = 'Stimme zurückziehen';
$txt['poll_return_vote'] = 'Abstimmungsoptionen';
$txt['poll_cannot_see'] = 'Sie können derzeit die Ergebnisse dieser Umfrage nicht einsehen.';

$txt['quick_mod_approve'] = 'Ausgewählte freigeben';
$txt['quick_mod_remove'] = 'Ausgewählte entfernen';
$txt['quick_mod_lock'] = 'Ausgewählte sperren/entsperren';
$txt['quick_mod_sticky'] = 'Ausgewählte anheften/nicht mehr anheften';
$txt['quick_mod_move'] = 'Ausgewählte verschieben nach';
$txt['quick_mod_merge'] = 'Ausgewählte zusammenführen';
$txt['quick_mod_markread'] = 'Ausgewählte als gelesen markieren';
$txt['quick_mod_go'] = 'Los';
$txt['quickmod_confirm'] = 'Sind Sie sich sicher, dass Sie dies tun möchten?';

$txt['spell_check'] = 'Rechtschreibprüfung';

$txt['quick_reply'] = 'Schnellantwort';
$txt['quick_reply_desc'] = 'Mit der <em>Schnellantwort</em> können Sie einen Beitrag verfassen, während Sie ein Thema ansehen, ohne eine neue Seite zu laden. Sie können weiterhin BBCode und Smileys wie in einem normalen Beitrag nutzen.';
$txt['quick_reply_warning'] = 'Warnung! Dieses Thema ist zurzeit geschlossen, nur Administratoren und Moderatoren können antworten.';
$txt['quick_reply_verification'] = 'Nach der Übertragung werden Sie auf die normale Beitragsseite weitergeleitet, um Ihren Beitrag %1$s zu überprüfen.';
$txt['quick_reply_verification_guests'] = '(gilt für alle Gäste)';
$txt['quick_reply_verification_posts'] = '(gilt für alle Benutzer mit weniger als %1$s Beiträgen)';
$txt['wait_for_approval'] = 'Hinweis: Dieser Beitrag wird nicht angezeigt, bevor er von einem Moderator freigeschaltet worden ist.';

$txt['notification_enable_board'] = 'Sind Sie sich sicher, dass Sie die Benachrichtigung über neue Themen in diesem Forum aktivieren möchten?';
$txt['notification_disable_board'] = 'Sind Sie sich sicher, dass Sie die Benachrichtigung über neue Themen in diesem Forum deaktivieren möchten?';
$txt['notification_enable_topic'] = 'Sind Sie sich sicher, dass Sie die Benachrichtigung über neue Antworten in diesem Thema aktivieren möchten?';
$txt['notification_disable_topic'] = 'Sind Sie sich sicher, dass Sie die Benachrichtigung über neue Antworten in diesem Thema deaktivieren möchten?';

$txt['report_to_mod'] = 'Beitrag melden';
$txt['issue_warning_post'] = 'Eine Verwarnung aufgrund dieses Beitrags aussprechen';

$txt['like_post'] = 'Beitrag gefällt mir';
$txt['unlike_post'] = 'Beitrag gefällt mir nicht mehr';
$txt['likes'] = 'gefällt\'s'; // ? :-)
$txt['liked_by'] = 'Gefällt diesen Benutzern:';
$txt['liked_you'] = 'Ihnen';
$txt['liked_more'] = 'mehr';

$txt['unread_topics_visit'] = 'Neue ungelesene Themen';
$txt['unread_topics_visit_none'] = 'Es wurden keine seit Ihrem letzten Besuch ungelesenen Themen gefunden. <a href="' . $scripturl . '?action=unread;all" class="linkbutton">Klicken Sie hier, um alle ungelesenen Themen anzuzeigen</a>';
$txt['unread_topics_all'] = 'Alle ungelesenen Themen';
$txt['unread_replies'] = 'Aktualisierte Themen';

$txt['who_title'] = 'Wer ist online';
$txt['who_and'] = ' und ';
$txt['who_viewing_topic'] = ' betrachten dieses Thema.';
$txt['who_viewing_board'] = ' betrachten dieses Forum.';
$txt['who_member'] = 'Mitglied';

// No longer used by default theme, but for backwards compat
$txt['powered_by_php'] = 'Angetrieben von PHP';
$txt['powered_by_mysql'] = 'Angetrieben von MySQL';
$txt['valid_css'] = 'Gültiges CSS';

// Current footer strings
$txt['valid_html'] = 'Gültiges HTML 5';
$txt['rss'] = 'RSS';
$txt['atom'] = 'Atom';
$txt['html'] = 'HTML';

$txt['guest'] = 'Gast';
$txt['guests'] = 'Gäste';
$txt['user'] = 'Benutzer';
$txt['users'] = 'Benutzer';
$txt['hidden'] = 'Versteckt';
// Plural form of hidden for languages other than English
$txt['hidden_s'] = 'Versteckte';
$txt['buddy'] = 'Freund';
$txt['buddies'] = 'Freunde';
$txt['most_online_ever'] = 'Bisher am meisten online';
$txt['most_online_today'] = 'Heute am meisten online';

// TODO: Continue here.
$txt['merge_select_target_board'] = 'Select the target board of the merged topic';
$txt['merge_select_poll'] = 'Select which poll the merged topic should have';
$txt['merge_topic_list'] = 'Select topics to be merged';
$txt['merge_select_subject'] = 'Select subject of merged topic';
$txt['merge_custom_subject'] = 'Custom subject';
$txt['merge_enforce_subject'] = 'Change the subject of all the messages';
$txt['merge_include_notifications'] = 'Include notifications?';
$txt['merge_check'] = 'Merge?';
$txt['merge_no_poll'] = 'No poll';

$txt['response_prefix'] = 'Re: ';
$txt['current_icon'] = 'Current Icon';
$txt['message_icon'] = 'Message Icon';

$txt['smileys_current'] = 'Current Smiley Set';
$txt['smileys_none'] = 'No Smileys';
$txt['smileys_forum_board_default'] = 'Forum/Board Default';

$txt['search_results'] = 'Search Results';
$txt['search_no_results'] = 'Sorry, no matches were found';

$txt['totalTimeLogged1'] = 'Total time logged in: '; //Deprecated
$txt['totalTimeLogged2'] = ' days, ';
$txt['totalTimeLogged3'] = ' hours and ';
$txt['totalTimeLogged4'] = ' minutes.';
$txt['totalTimeLogged5'] = 'd ';
$txt['totalTimeLogged6'] = 'h ';
$txt['totalTimeLogged7'] = 'm';

$txt['approve_thereis'] = 'There is'; //Deprecated
$txt['approve_thereare'] = 'There are'; //Deprecated
$txt['approve_member'] = 'one member'; //Deprecated
$txt['approve_members'] = 'members'; //Deprecated
$txt['approve_members_waiting'] = 'awaiting approval.'; //Deprecated
$txt['approve_one_member_waiting'] = 'There is <a href="%1$s">one member</a> awaiting approval.';
$txt['approve_many_members_waiting'] = 'There are <a href="%1$s">%2$d members</a> awaiting approval.';

$txt['notifyboard_turnon'] = 'Do you want a notification email when someone posts a new topic in this board?';
$txt['notifyboard_turnoff'] = 'Are you sure you do not want to receive new topic notifications for this board?';

$txt['activate_code'] = 'Your activation code is';

$txt['find_members'] = 'Find Members';
$txt['find_username'] = 'Name, username, or email address';
$txt['find_buddies'] = 'Show Buddies Only?';
$txt['find_wildcards'] = 'Allowed Wildcards: *, ?';
$txt['find_no_results'] = 'No results found';
$txt['find_results'] = 'Results';
$txt['find_close'] = 'Close';

$txt['unread_since_visit'] = 'Show unread posts since last visit.';
$txt['show_unread_replies'] = 'Show new replies to your posts.';

$txt['change_color'] = 'Change Color';

$txt['quickmod_delete_selected'] = 'Remove Selected';
$txt['quickmod_split_selected'] = 'Split Selected';

$txt['show_personal_messages_heading'] = 'New messages';
$txt['show_personal_messages'] = 'You have <strong>%1$s</strong> unread personal messages in your inbox.<br /><br /><a href="%2$s">Go to your inbox</a>';

$txt['help_popup'] = 'A little lost? Let me explain:';

$txt['previous_next_back'] = 'previous topic';
$txt['previous_next_forward'] = 'next topic';

$txt['upshrink_description'] = 'Shrink or expand the header.';

$txt['mark_unread'] = 'Mark unread';

$txt['ssi_not_direct'] = 'Please don\'t access SSI.php by URL directly; you may want to use the path (%1$s) or add ?ssi_function=something.';
$txt['ssi_session_broken'] = 'SSI.php was unable to load a session!  This may cause problems with logout and other functions - please make sure SSI.php is included before *anything* else in all your scripts!';

// Escape any single quotes in here twice.. 'it\'s' -> 'it\\\'s'.
$txt['preview_title'] = 'Preview post';
$txt['preview_fetch'] = 'Fetching preview...';
$txt['preview_new'] = 'New message';
$txt['pm_error_while_submitting'] = 'The following error or errors occurred while sending this personal message:';
$txt['error_while_submitting'] = 'The message has the following error or errors that must be corrected before continuing:';
$txt['error_old_topic'] = 'Warning: this topic has not been posted in for at least %1$d days.<br />Unless you\'re sure you want to reply, please consider starting a new topic.';

$txt['split_selected_posts'] = 'Selected posts';
$txt['split_selected_posts_desc'] = 'The posts below will form a new topic after splitting.';
$txt['split_reset_selection'] = 'reset selection';

$txt['modify_cancel'] = 'Cancel';
$txt['mark_read_short'] = 'Mark Read';

$txt['hello_member_ndt'] = 'Hello';

$txt['unapproved_posts'] = 'Unapproved Posts (Topics: %1$d, Posts: %2$d)';

$txt['ajax_in_progress'] = 'Loading...';

$txt['mod_reports_waiting'] = 'There are currently %1$d moderator reports open.';

$txt['new_posts_in_category'] = 'Click to see the new posts in %1$s';
$txt['verification'] = 'Verification';
$txt['visual_verification_hidden'] = 'Please leave this box empty';
$txt['visual_verification_description'] = 'Type the letters shown in the picture';
$txt['visual_verification_sound'] = 'Listen to the letters';
$txt['visual_verification_request_new'] = 'Request another image';

// Sub menu labels
$txt['calendar_menu'] = 'View Calendar';

// @todo Send email strings - should move?
$txt['send_email'] = 'Send Email';
$txt['send_email_disclosed'] = 'Note this will be visible to the recipient.';
$txt['send_email_subject'] = 'Email Subject';

$txt['ignoring_user'] = 'You are ignoring this user.';
$txt['show_ignore_user_post'] = 'Show me the post.';

$txt['spider'] = 'Spider';
$txt['spiders'] = 'Spiders';
$txt['openid'] = 'OpenID';

$txt['downloads'] = 'Downloads';
$txt['filesize'] = 'Filesize';
$txt['subscribe_webslice'] = 'Subscribe to Webslice';

// Restore topic
$txt['restore_topic'] = 'Restore Topic';
$txt['restore_message'] = 'Restore';
$txt['quick_mod_restore'] = 'Restore Selected';

// Editor prompt.
$txt['prompt_text_email'] = 'Please enter the email address.';
$txt['prompt_text_ftp'] = 'Please enter the ftp address.';
$txt['prompt_text_url'] = 'Please enter the URL you wish to link to.';
$txt['prompt_text_img'] = 'Enter image location';

// Escape any single quotes in here twice.. 'it\'s' -> 'it\\\'s'.
$txt['autosuggest_delete_item'] = 'Delete Item';

// Bad Behavior
$txt['badbehavior_blocked'] = '<a href="http://www.bad-behavior.ioerror.us/">Bad Behavior</a> has blocked %1$s access attempts in the last 7 days.';

// Debug related - when $db_show_debug is true.
$txt['debug_templates'] = 'Templates: ';
$txt['debug_subtemplates'] = 'Sub templates: ';
$txt['debug_language_files'] = 'Language files: ';
$txt['debug_stylesheets'] = 'Style sheets: ';
$txt['debug_javascript'] = 'Scripts: ';
$txt['debug_files_included'] = 'Files included: ';
$txt['debug_kb'] = 'KB.';
$txt['debug_show'] = 'show';
$txt['debug_cache_hits'] = 'Cache hits: ';
$txt['debug_cache_seconds_bytes'] = '%1$ss - %2$s bytes';
$txt['debug_cache_seconds_bytes_total'] = '%1$ss for %2$s bytes';
$txt['debug_queries_used'] = 'Queries used: %1$d.';
$txt['debug_queries_used_and_warnings'] = 'Queries used: %1$d, %2$d warnings.';
$txt['debug_query_in_line'] = 'in <em>%1$s</em> line <em>%2$s</em>, ';
$txt['debug_query_which_took'] = 'which took %1$s seconds.';
$txt['debug_query_which_took_at'] = 'which took %1$s seconds at %2$s into request.';
$txt['debug_show_queries'] = '[Show Queries]';
$txt['debug_hide_queries'] = '[Hide Queries]';
$txt['debug_tokens'] = 'Tokens: ';
$txt['debug_browser'] = 'Browser ID: ';
$txt['debug_hooks'] = 'Hooks called: ';

// Video embedding
$txt['preview_image'] = 'Video Preview Image';
$txt['ctp_video'] = 'Click to play video, double click to load video';
$txt['hide_video'] = 'Show/Hide video';
$txt['youtube'] = 'YouTube video:';
$txt['vimeo'] = 'Vimeo video:';
$txt['dailymotion'] = 'Dailymotion video:';

// Spoiler BBC
$txt['spoiler'] = 'Spoiler (click to show/hide)';
