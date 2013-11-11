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
$txt['personal_messages'] = 'Persönliche Nachrichten';
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
$txt['personal_message'] = 'Persönliche Nachricht';
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

$txt['your_pms'] = 'Ihre persönlichen Nachrichten';

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

$txt['report_to_mod_func'] = 'Use this function to inform the moderators and administrators of an abusive or wrongly posted message.<br /><em>Please note that your email address will be revealed to the moderators if you use this.</em>';

$txt['online'] = 'Online';
$txt['member_is_online'] = '%1$s is online';
$txt['offline'] = 'Offline';
$txt['member_is_offline'] = '%1$s is offline';
$txt['pm_online'] = 'Personal Message (Online)';
$txt['pm_offline'] = 'Personal Message (Offline)';
$txt['status'] = 'Status';

$txt['skip_nav'] = 'Skip Navigation';
$txt['go_up'] = 'Go Up';
$txt['go_down'] = 'Go Down';

$forum_copyright = '<a href="' . $scripturl . '?action=who;sa=credits" title="ElkArte Forum" target="_blank" class="new_win">%1$s</a> | <a href="https://github.com/elkarte/ElkArte/blob/master/license.md" title="License" target="_blank" class="new_win">ElkArte &copy; 2013</a>';

$txt['birthdays'] = 'Birthdays:';
$txt['events'] = 'Events:';
$txt['birthdays_upcoming'] = 'Upcoming Birthdays:';
$txt['events_upcoming'] = 'Upcoming Events:';
// Prompt for holidays in the calendar, leave blank to just display the holiday's name.
$txt['calendar_prompt'] = 'Holidays:';
$txt['calendar_month'] = 'Month:';
$txt['calendar_year'] = 'Year:';
$txt['calendar_day'] = 'Day:';
$txt['calendar_event_title'] = 'Event Title';
$txt['calendar_event_options'] = 'Event Options';
$txt['calendar_post_in'] = 'Post In:';
$txt['calendar_edit'] = 'Edit Event';
$txt['event_delete_confirm'] = 'Delete this event?';
$txt['event_delete'] = 'Delete Event';
$txt['calendar_post_event'] = 'Post Event';
$txt['calendar'] = 'Calendar';
$txt['calendar_link'] = 'Link to Calendar';
$txt['calendar_upcoming'] = 'Upcoming Calendar';
$txt['calendar_today'] = 'Today\'s Calendar';
$txt['calendar_week'] = 'Week';
$txt['calendar_week_title'] = 'Week %1$d of %2$d';
$txt['calendar_numb_days'] = 'Number of Days:';
$txt['calendar_how_edit'] = 'how do you edit these events?';
$txt['calendar_link_event'] = 'Link Event To Post:';
$txt['calendar_confirm_delete'] = 'Are you sure you want to delete this event?';
$txt['calendar_linked_events'] = 'Linked Events';
$txt['calendar_click_all'] = 'click to see all %1$s';

$txt['moveTopic1'] = 'Post a redirection topic';
$txt['moveTopic2'] = 'Change the topic\'s subject';
$txt['moveTopic3'] = 'New subject';
$txt['moveTopic4'] = 'Change every message\'s subject';
$txt['move_topic_unapproved_js'] = 'Warning! This topic has not yet been approved.\\n\\nIt is not recommended that you create a redirection topic unless you intend to approve the post immediately following the move.';
$txt['movetopic_auto_board'] = '[BOARD]';
$txt['movetopic_auto_topic'] = '[TOPIC LINK]';
$txt['movetopic_default'] = 'This topic has been moved to ' . $txt['movetopic_auto_board'] . ".\n\n" . $txt['movetopic_auto_topic'];
$txt['movetopic_redirect'] = 'Redirect to the moved topic';
$txt['movetopic_expires'] = 'Automatically remove the redirection topic';

$txt['merge_to_topic_id'] = 'ID of target topic';
$txt['split_topic'] = 'Split Topic';
$txt['merge'] = 'Merge Topics';
$txt['subject_new_topic'] = 'Subject For New Topic';
$txt['split_this_post'] = 'Only split this post.';
$txt['split_after_and_this_post'] = 'Split topic after and including this post.';
$txt['select_split_posts'] = 'Select posts to split.';

$txt['splittopic_notification'] = 'Post a message when the topic is split.';
$txt['splittopic_default'] = 'One or more of the messages of this topic have been moved to ' . $txt['movetopic_auto_board'] . ".\n\n" . $txt['movetopic_auto_topic'];
$txt['splittopic_move'] = 'Move the new topic to another board';

$txt['new_topic'] = 'New Topic';
$txt['split_successful'] = 'Topic successfully split into two topics.';
$txt['origin_topic'] = 'Origin Topic';
$txt['please_select_split'] = 'Please select which posts you wish to split.';
$txt['merge_successful'] = 'Topics successfully merged.';
$txt['new_merged_topic'] = 'Newly Merged Topic';
$txt['topic_to_merge'] = 'Topic to be merged';
$txt['target_board'] = 'Target board';
$txt['target_topic'] = 'Target topic';
$txt['merge_confirm'] = 'Are you sure you want to merge';
$txt['with'] = 'with';
$txt['merge_desc'] = 'This function will merge the messages of two topics into one topic. The messages will be sorted according to the time of posting. Therefore the earliest posted message will be the first message of the merged topic.';

$txt['theme_template_error'] = 'Unable to load the \'%1$s\' template.';
$txt['theme_language_error'] = 'Unable to load the \'%1$s\' language file.';

$txt['parent_boards'] = 'Child Boards';

$txt['smtp_no_connect'] = 'Could not connect to SMTP host';
$txt['smtp_port_ssl'] = 'SMTP port setting incorrect; it should be 465 for SSL servers.';
$txt['smtp_bad_response'] = 'Couldn\'t get mail server response codes';
$txt['smtp_error'] = 'Ran into problems sending Mail. Error: ';
$txt['mail_send_unable'] = 'Unable to send mail to the email address \'%1$s\'';

$txt['mlist_search'] = 'Search For Members';
$txt['mlist_search_again'] = 'Search again';
$txt['mlist_search_filter'] = 'Search Options';
$txt['mlist_search_email'] = 'Search by email address';
$txt['mlist_search_group'] = 'Search by position';
$txt['mlist_search_name'] = 'Search by name';
$txt['mlist_search_website'] = 'Search by website';
$txt['mlist_search_results'] = 'Search results for';
$txt['mlist_search_by'] = 'Search by %1$s';
$txt['mlist_menu_view'] = 'View the memberlist';

$txt['attach_downloaded'] = 'downloaded %1$d times';
$txt['attach_viewed'] = 'viewed %1$d times';

$txt['settings'] = 'Settings';
$txt['never'] = 'Never';
$txt['more'] = 'more';

$txt['hostname'] = 'Hostname';
$txt['you_are_post_banned'] = 'Sorry %1$s, you are banned from posting and sending personal messages on this forum.';
$txt['ban_reason'] = 'Reason';

$txt['tables_optimized'] = 'Database tables optimized';

$txt['add_poll'] = 'Add poll';
$txt['poll_options6'] = 'You may only select up to %1$s options.';
$txt['poll_remove'] = 'Remove Poll';
$txt['poll_remove_warn'] = 'Are you sure you want to remove this poll from the topic?';
$txt['poll_results_expire'] = 'Results will be shown when voting has closed';
$txt['poll_expires_on'] = 'Voting closes';
$txt['poll_expired_on'] = 'Voting closed';
$txt['poll_change_vote'] = 'Remove Vote';
$txt['poll_return_vote'] = 'Voting options';
$txt['poll_cannot_see'] = 'You cannot see the results of this poll at the moment.';

$txt['quick_mod_approve'] = 'Approve selected';
$txt['quick_mod_remove'] = 'Remove selected';
$txt['quick_mod_lock'] = 'Lock/Unlock selected';
$txt['quick_mod_sticky'] = 'Sticky/Unsticky selected';
$txt['quick_mod_move'] = 'Move selected to';
$txt['quick_mod_merge'] = 'Merge selected';
$txt['quick_mod_markread'] = 'Mark selected read';
$txt['quick_mod_go'] = 'Go';
$txt['quickmod_confirm'] = 'Are you sure you want to do this?';

$txt['spell_check'] = 'Spell Check';

$txt['quick_reply'] = 'Quick Reply';
$txt['quick_reply_desc'] = 'With <em>Quick-Reply</em> you can write a post when viewing a topic without loading a new page. You can still use bulletin board code and smileys as you would in a normal post.';
$txt['quick_reply_warning'] = 'Warning! This topic is currently locked, only admins and moderators can reply.';
$txt['quick_reply_verification'] = 'After submitting your post you will be directed to the regular post page to verify your post %1$s.';
$txt['quick_reply_verification_guests'] = '(required for all guests)';
$txt['quick_reply_verification_posts'] = '(required for all users with less than %1$d posts)';
$txt['wait_for_approval'] = 'Note: this post will not display until it\'s been approved by a moderator.';

$txt['notification_enable_board'] = 'Are you sure you wish to enable notification of new topics for this board?';
$txt['notification_disable_board'] = 'Are you sure you wish to disable notification of new topics for this board?';
$txt['notification_enable_topic'] = 'Are you sure you wish to enable notification of new replies for this topic?';
$txt['notification_disable_topic'] = 'Are you sure you wish to disable notification of new replies for this topic?';

$txt['report_to_mod'] = 'Report Post';
$txt['issue_warning_post'] = 'Issue a warning because of this message';

$txt['like_post'] = 'Like Post';
$txt['unlike_post'] = 'Unlike Post';
$txt['likes'] = 'Likes';
$txt['liked_by'] = 'Liked by:';
$txt['liked_you'] = 'You';
$txt['liked_more'] = 'more';

$txt['unread_topics_visit'] = 'Recent Unread Topics';
$txt['unread_topics_visit_none'] = 'No unread topics found since your last visit. <a href="' . $scripturl . '?action=unread;all" class="linkbutton">Click here to try all unread topics</a>';
$txt['unread_topics_all'] = 'All Unread Topics';
$txt['unread_replies'] = 'Updated Topics';

$txt['who_title'] = 'Who\'s Online';
$txt['who_and'] = ' and ';
$txt['who_viewing_topic'] = ' are viewing this topic.';
$txt['who_viewing_board'] = ' are viewing this board.';
$txt['who_member'] = 'Member';

// No longer used by default theme, but for backwards compat
$txt['powered_by_php'] = 'Powered by PHP';
$txt['powered_by_mysql'] = 'Powered by MySQL';
$txt['valid_css'] = 'Valid CSS';

// Current footer strings
$txt['valid_html'] = 'Valid HTML 5';
$txt['rss'] = 'RSS';
$txt['atom'] = 'Atom';
$txt['html'] = 'HTML';

$txt['guest'] = 'Guest';
$txt['guests'] = 'Guests';
$txt['user'] = 'User';
$txt['users'] = 'Users';
$txt['hidden'] = 'Hidden';
// Plural form of hidden for languages other than English
$txt['hidden_s'] = 'Hidden';
$txt['buddy'] = 'Buddy';
$txt['buddies'] = 'Buddies';
$txt['most_online_ever'] = 'Most Online Ever';
$txt['most_online_today'] = 'Most Online Today';

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
