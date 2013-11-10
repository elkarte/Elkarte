<?php
// Version: 1.0; ModerationCenter

global $scripturl;

$txt['moderation_center'] = 'Moderationszentrum';
$txt['mc_main'] = 'Hauptseite';
$txt['mc_logs'] = 'Protokolle';
$txt['mc_posts'] = 'Beiträge';
$txt['mc_groups'] = 'Benutzer und Gruppen';

$txt['mc_view_groups'] = 'Benutzergruppen ansehen';

$txt['mc_description'] = '<strong>%1$s %2$s!</strong><br>Dies ist Ihr &quot;Moderationszentrum&quot;. Von hier aus können Sie alle Moderationshandlungen durchführen, die Ihnen vom Administrator genehmigt wurden. Diese Seite stellt eine Übersicht über die neuesten Geschehnisse in Ihrer Community bereit. Sie können <a href="' . $scripturl . '?action=moderate;area=settings">die Darstellung anpassen, indem Sie hier klicken</a>.';
$txt['mc_group_requests'] = 'Benutzergruppenanfragen';
$txt['mc_member_requests'] = 'Benutzeranfragen';
$txt['mc_unapproved_posts'] = 'Nicht freigegebene Beiträge';
$txt['mc_watched_users'] = 'Zuletzt beobachtete Mitglieder';
$txt['mc_watched_topics'] = 'Beobachtete Themen';
$txt['mc_scratch_board'] = 'Moderationszeichenbrett';
$txt['mc_latest_news'] = 'Neueste Nachrichten';
$txt['mc_recent_reports'] = 'Letzte Themenmeldungen';
$txt['mc_warnings'] = 'Verwarnungen';
$txt['mc_notes'] = 'Moderationshinweise';
$txt['mc_required'] = 'Elemente, die auf Überprüfung warten';
$txt['mc_attachments'] = 'Zu überprüfende Anhänge';
$txt['mc_emailmod'] = 'Zu überprüfende E-Mail-Beiträge';
$txt['mc_topics'] = 'Zu überprüfende Themen';
$txt['mc_posts'] = 'Zu überprüfende Beiträge';
$txt['mc_groupreq'] = 'Zu überprüfende Gruppenanfragen';
$txt['mc_memberreq'] = 'Zu überprüfende Mitglieder';
$txt['mc_reports'] = 'Zu überprüfende Beitragsmeldungen';

$txt['mc_cannot_connect_sm'] = 'Sie können ElkArtes Nachrichten nicht abrufen (Verbindungsfehler).';

$txt['mc_recent_reports_none'] = 'Es gibt keine ausstehenden Meldungen.';
$txt['mc_watched_users_none'] = 'Momentan wird niemand beobachtet.';
$txt['mc_group_requests_none'] = 'Es gibt keine offenen Anfragen für eine Gruppenmitgliedschaft.';

$txt['mc_seen'] = '%1$s zuletzt gesehen %2$s';
$txt['mc_seen_never'] = '%1$s niemals gesehen';
$txt['mc_groupr_by'] = 'von';

$txt['mc_reported_posts_desc'] = 'Hier können Sie alle Beitragsmeldungen überprüfen, die von Forenmitgliedern eingesandt wurden.';
$txt['mc_reportedp_active'] = 'Aktive Meldungen';
$txt['mc_reportedp_closed'] = 'Alte Meldungen';
$txt['mc_reportedp_by'] = 'von';
$txt['mc_reportedp_reported_by'] = 'Gemeldet von';
$txt['mc_reportedp_last_reported'] = 'Zuletzt gemeldet';
$txt['mc_reportedp_none_found'] = 'Keine Meldungen gefunden';

$txt['mc_reportedp_details'] = 'Details';
$txt['mc_reportedp_close'] = 'Schließen';
$txt['mc_reportedp_open'] = 'Öffnen';
$txt['mc_reportedp_ignore'] = 'Ignorieren';
$txt['mc_reportedp_unignore'] = 'Nicht mehr ignorieren';
// Do not use numeric entries in the below string.
$txt['mc_reportedp_ignore_confirm'] = 'Sind Sie sich sicher, dass Sie weitere Meldungen zu dieser Nachricht ignorieren möchten?\\n\\nDies wird für alle Moderatoren dieses Forums gelten.';
$txt['mc_reportedp_close_selected'] = 'Ausgewählte schließen';

$txt['mc_groupr_group'] = 'Benutzergruppen';
$txt['mc_groupr_member'] = 'Benutzer';
$txt['mc_groupr_reason'] = 'Grund';
$txt['mc_groupr_none_found'] = 'Es gibt keine offenen Anfragen für eine Gruppenmitgliedschaft.';
$txt['mc_groupr_submit'] = 'Übernehmen';
$txt['mc_groupr_reason_desc'] = 'Grund, %1$ss Anfrage, &quot;%2$s&quot; beizutreten, abzulehnen';
$txt['mc_groups_reason_title'] = 'Gründe für Zurückweisung';
$txt['with_selected'] = 'Mit ausgewählten';
$txt['mc_groupr_approve'] = 'Anfrage annehmen';
$txt['mc_groupr_reject'] = 'Anfrage ablehnen (keine Begründung)';
$txt['mc_groupr_reject_w_reason'] = 'Anfrage begründet ablehnen';
// Do not use numeric entries in the below string.
$txt['mc_groupr_warning'] = 'Sind Sie sich sicher, dass Sie dies tun möchten?';

$txt['mc_unapproved_attachments_none_found'] = 'Momentan warten keine Anhänge auf Überprüfung.';
$txt['mc_unapproved_attachments_desc'] = 'Von hier aus können Sie Anhänge freischalten oder löschen, die auf Moderation warten.';
$txt['mc_unapproved_replies_none_found'] = 'Momentan warten keine Beiträge auf Überprüfung';
$txt['mc_unapproved_topics_none_found'] = 'Momentan warten keine Themen auf Überprüfung';
$txt['mc_unapproved_posts_desc'] = 'Von hier aus können Sie Beiträge freischalten oder löschen, die auf Moderation warten.';
$txt['mc_unapproved_replies'] = 'Antworten';
$txt['mc_unapproved_topics'] = 'Themen';
$txt['mc_unapproved_by'] = 'von';
$txt['mc_unapproved_sure'] = 'Sind Sie sich sicher, dass Sie dies tun möchten?';
$txt['mc_unapproved_attach_name'] = 'Name des Anhangs';
$txt['mc_unapproved_attach_size'] = 'Dateigröße';
$txt['mc_unapproved_attach_poster'] = 'Verfasser';
$txt['mc_viewmodreport'] = 'Moderationsbericht für %1$s von %2$s';
$txt['mc_modreport_summary'] = 'Es gab %1$d Meldung(en) für diesen Beitrag.  Die letzte Meldung war %2$s.';
$txt['mc_modreport_whoreported_title'] = 'Benutzer, die diesen Beitrag gemeldet haben';
$txt['mc_modreport_whoreported_data'] = 'Gemeldet von %1$s am %2$s.  Es wurde folgende Nachricht hinterlassen:';
$txt['mc_modreport_modactions'] = 'Aktionen anderer Moderatoren';
$txt['mc_modreport_mod_comments'] = 'Moderationskommentare';
$txt['mc_modreport_no_mod_comment'] = 'Derzeit gibt es keine Moderationskommentare';
$txt['mc_modreport_add_mod_comment'] = 'Kommentar hinzufügen';

$txt['show_notice'] = 'Hinweistext';
$txt['show_notice_subject'] = 'Betreff';
$txt['show_notice_text'] = 'Text';

$txt['mc_watched_users_title'] = 'Beobachtete Benutzer';
$txt['mc_watched_users_desc'] = 'Hier können Sie alle Benutzer verfolgen, die vom Moderationsteam beobachtet werden.';
$txt['mc_watched_users_post'] = 'Nach Beitrag ansehen';
$txt['mc_watched_users_warning'] = 'Verwarnstufe';
$txt['mc_watched_users_last_login'] = 'Letzte Anmeldung';
$txt['mc_watched_users_last_post'] = 'Letzter Beitrag';
$txt['mc_watched_users_no_posts'] = 'Es gibt keine Beiträge von beobachteten Benutzern.';
// Don't use entities in the two strings below.
$txt['mc_watched_users_delete_post'] = 'Sind Sie sich sicher, dass Sie diesen Beitrag löschen möchten?';
$txt['mc_watched_users_delete_posts'] = 'Sind Sie sich sicher, dass Sie diese Beiträge löschen möchten?';
$txt['mc_watched_users_posted'] = 'Verfasst';
$txt['mc_watched_users_member'] = 'Benutzer';

// TODO FROM HERE :o)
$txt['mc_warnings_description'] = 'From this section you can see which warnings have been issued to members of the forum. You can also add and modify the notification templates used when sending a warning to a member.';
$txt['mc_warning_log'] = 'Log';
$txt['mc_warning_templates'] = 'Custom Templates';
$txt['mc_warning_log_title'] = 'Viewing warning log';
$txt['mc_warning_templates_title'] = 'Custom warning templates';

$txt['mc_warnings_none'] = 'No warnings have been issued.';
$txt['mc_warnings_recipient'] = 'Recipient';

$txt['mc_warning_templates_none'] = 'No warning templates have been created yet';
$txt['mc_warning_templates_time'] = 'Time Created';
$txt['mc_warning_templates_name'] = 'Template';
$txt['mc_warning_templates_creator'] = 'Created By';
$txt['mc_warning_template_add'] = 'Add Template';
$txt['mc_warning_template_modify'] = 'Edit Template';
$txt['mc_warning_template_delete'] = 'Delete Selected';
$txt['mc_warning_template_delete_confirm'] = 'Are you sure you want to delete the selected templates?';

$txt['mc_warning_template_desc'] = 'Use this page to fill in the details of the template. Note that the subject for the email is not part of the template. Note that as the notification is sent by PM you can use BBC within the template. Note if you use the {MESSAGE} variable then this template will not be available when issuing a generic warning (i.e. A warning not linked to a post).';
$txt['mc_warning_template_title'] = 'Template Title';
$txt['mc_warning_template_body_desc'] = 'The content of the notification message. Note that you can use the following shortcuts in this template.<ul style="margin-top: 0px;"><li>{MEMBER} - Member Name.</li><li>{MESSAGE} - Link to Offending Post. (If Applicable)</li><li>{FORUMNAME} - Forum Name.</li><li>{SCRIPTURL} - Web address of forum.</li><li>{REGARDS} - Standard email sign-off.</li></ul>';
$txt['mc_warning_template_body_default'] = '{MEMBER},' . "\n\n" . 'You have received a warning for inappropriate activity. Please cease these activities and abide by the forum rules otherwise we will take further action.' . "\n\n" . '{REGARDS}';
$txt['mc_warning_template_personal'] = 'Personal Template';
$txt['mc_warning_template_personal_desc'] = 'If you select this option only you will be able to see, edit and use this template. If not selected all moderators will be able to use this template.';
$txt['mc_warning_template_error_no_title'] = 'You must set title.';
$txt['mc_warning_template_error_no_body'] = 'You must set a notification body.';

$txt['mc_settings'] = 'Change Settings';
$txt['mc_prefs_title'] = 'Moderation Preferences';
$txt['mc_prefs_desc'] = 'This section allows you to set some personal preferences for moderation related activities such as email notifications.';
$txt['mc_prefs_homepage'] = 'Items to show on moderation homepage';
$txt['mc_prefs_latest_news'] = 'ElkArte News';
$txt['mc_prefs_show_reports'] = 'Show open report count in forum header';
$txt['mc_prefs_notify_report'] = 'Notify of topic reports';
$txt['mc_prefs_notify_report_never'] = 'Never';
$txt['mc_prefs_notify_report_moderator'] = 'Only if it\'s a board I moderate';
$txt['mc_prefs_notify_report_always'] = 'Always';
$txt['mc_prefs_notify_approval'] = 'Notify of items awaiting approval';
$txt['mc_logoff'] = 'Moderator End Session';

// Use entities in the below string.
$txt['mc_click_add_note'] = 'Add a new note';
$txt['mc_add_note'] = 'Add';
