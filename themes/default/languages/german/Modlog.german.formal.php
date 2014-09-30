<?php
// Version: 1.0; Modlog

$txt['modlog_date'] = 'Datum';
$txt['modlog_member'] = 'Mitglied';
$txt['modlog_position'] = 'Position';
$txt['modlog_action'] = 'Aktion';
$txt['modlog_ip'] = 'IP';
$txt['modlog_search_result'] = 'Suchergebnisse';
$txt['modlog_total_entries'] = 'Insgesamt';
$txt['modlog_ac_approve_topic'] = 'Thema &quot;{topic}&quot; von &quot;{member}&quot; freigeschaltet';
$txt['modlog_ac_unapprove_topic'] = 'Thema &quot;{topic}&quot; von &quot;{member}&quot; nicht freigeschaltet';
$txt['modlog_ac_approve'] = 'Nachricht &quot;{subject}&quot; in &quot;{topic}&quot; von &quot;{member}&quot; freigeschaltet';
$txt['modlog_ac_unapprove'] = 'Nachricht &quot;{subject}&quot; in &quot;{topic}&quot; von &quot;{member}&quot; nicht freigeschaltet';
$txt['modlog_ac_lock'] = '&quot;{topic}&quot; gesperrt';
$txt['modlog_ac_warning'] = '{member} verwarnt für &quot;{message}&quot;';
$txt['modlog_ac_unlock'] = '&quot;{topic}&quot; entsperrt';
$txt['modlog_ac_sticky'] = '&quot;{topic}&quot; angeheftet';
$txt['modlog_ac_unsticky'] = '&quot;{topic}&quot; nicht mehr angeheftet';
$txt['modlog_ac_delete'] = 'Beitrag &quot;{subject}&quot; von &quot;{member}&quot; aus &quot;{topic}&quot; gelöscht';
$txt['modlog_ac_delete_member'] = 'Mitglied &quot;{name}&quot; gelöscht';
$txt['modlog_ac_remove'] = 'Thema &quot;{topic}&quot; aus &quot;{board}&quot; gelöscht';
$txt['modlog_ac_modify'] = 'Nachricht &quot;{message}&quot; von &quot;{member}&quot; geändert';
$txt['modlog_ac_merge'] = 'Themen in &quot;{topic}&quot; zusammengeführt';
$txt['modlog_ac_split'] = 'Thema &quot;{topic}&quot; teilweise in &quot;{new_topic}&quot; abgetrennt';
$txt['modlog_ac_move'] = '&quot;{topic}&quot; aus &quot;{board_from}&quot; nach &quot;{board_to}&quot; verschoben';
$txt['modlog_ac_profile'] = 'Profil von &quot;{member}&quot; geändert';
$txt['modlog_ac_pruned'] = 'Beiträge, die älter als {days} Tage waren, entfernt';
$txt['modlog_ac_news'] = 'Neuigkeiten geändert';
$txt['modlog_enter_comment'] = 'Geben Sie einen Moderationskommentar ein';
$txt['modlog_moderation_log'] = 'Moderationsprotokoll';
$txt['modlog_moderation_log_desc'] = 'Unten finden Sie eine Liste aller von Moderatoren dieses Forums durchgeführten Aktionen.<br /><strong>Bitte beachten Sie:</strong> Einträge können nicht aus diesem Protokoll entfernt werden, bevor sie mindestens 24 Stunden alt sind.';
$txt['modlog_no_entries_found'] = 'Es gibt derzeit keine Einträge im Moderationsprotokoll.';
$txt['modlog_remove'] = 'Ausgewählte löschen';
$txt['modlog_removeall'] = 'Protokoll leeren';
$txt['modlog_remove_selected_confirm'] = 'Sind Sie sich sicher, dass Sie die ausgewählten Einträge löschen möchten?';
$txt['modlog_remove_all_confirm'] = 'Sind Sie sich sicher, dass Sie das Protokoll vollständig leeren möchten?';
$txt['modlog_go'] = 'Los';
$txt['modlog_add'] = 'Hinzufügen';
$txt['modlog_search'] = 'Schnellsuche';
$txt['modlog_by'] = 'Von';
$txt['modlog_id'] = '<em>Gelöscht - ID:%1$d</em>';

$txt['modlog_ac_add_warn_template'] = 'Verwarnvorlage hinzugefügt: &quot;{template}&quot;';
$txt['modlog_ac_modify_warn_template'] = 'Verwarnvorlage geändert: &quot;{template}&quot;';
$txt['modlog_ac_delete_warn_template'] = 'Verwarnvorlage gelöscht: &quot;{template}&quot;';

$txt['modlog_ac_ban'] = 'Bannauslöser hinzugefügt:';
$txt['modlog_ac_ban_trigger_member'] = ' <em>Mitglied:</em> {member}';
$txt['modlog_ac_ban_trigger_email'] = ' <em>E-Mail:</em> {email}';
$txt['modlog_ac_ban_trigger_ip_range'] = ' <em>IP:</em> {ip_range}';
$txt['modlog_ac_ban_trigger_hostname'] = ' <em>Hostname:</em> {hostname}';

$txt['modlog_admin_log'] = 'Administrationsprotokoll';
$txt['modlog_admin_log_desc'] = 'Unten finden Sie eine Liste aller von Administratoren dieses Forums durchgeführten Aktionen.<br /><strong>Bitte beachten Sie:</strong> Einträge können nicht aus diesem Protokoll entfernt werden, bevor sie mindestens 24 Stunden alt sind.';
$txt['modlog_admin_log_no_entries_found'] = 'Es gibt derzeit keine Einträge im Administrationsprotokoll.';

// Admin type strings.
$txt['modlog_ac_upgrade'] = 'Forum auf Version {version} aktualisiert';
$txt['modlog_ac_install'] = 'Version {version} installiert';
$txt['modlog_ac_add_board'] = 'Neues Forum hinzugefügt: &quot;{board}&quot;';
$txt['modlog_ac_edit_board'] = 'Forum &quot;{board}&quot; geändert';
$txt['modlog_ac_delete_board'] = 'Forum &quot;{boardname}&quot; gelöscht';
$txt['modlog_ac_add_cat'] = 'Neue Kategorie hinzugefügt: &quot;{catname}&quot;';
$txt['modlog_ac_edit_cat'] = 'Kategorie &quot;{catname}&quot; geändert';
$txt['modlog_ac_delete_cat'] = 'Kategorie &quot;{catname}&quot; gelöscht';

$txt['modlog_ac_delete_group'] = 'Gruppe &quot;{group}&quot; gelöscht';
$txt['modlog_ac_add_group'] = 'Neue Gruppe hinzugefügt: &quot;{group}&quot;';
$txt['modlog_ac_edited_group'] = 'Gruppe &quot;{group}&quot; geändert';
$txt['modlog_ac_added_to_group'] = 'Mitglied &quot;{member}&quot; der Gruppe &quot;{group}&quot; hinzugefügt';
$txt['modlog_ac_removed_from_group'] = 'Mitglied &quot;{member}&quot; aus der Gruppe &quot;{group}&quot; entfernt';
$txt['modlog_ac_removed_all_groups'] = 'Mitglied &quot;{member}&quot; aus allen Gruppen entfernt';

$txt['modlog_ac_remind_member'] = 'Aktivierungserinnerung an &quot;{member}&quot; versandt';
$txt['modlog_ac_approve_member'] = 'Das Konto von &quot;{member}&quot; freigeschaltet/aktiviert';
$txt['modlog_ac_newsletter'] = 'Newsletter versandt';

$txt['modlog_ac_install_package'] = 'Neues Paket installiert: &quot;{package}&quot;, Version {version}';
$txt['modlog_ac_upgrade_package'] = 'Paket &quot;{package}&quot; auf Version {version} aktualisiert';
$txt['modlog_ac_uninstall_package'] = 'Paket deinstalliert: &quot;{package}&quot;, Version {version}';

// Restore topic.
$txt['modlog_ac_restore_topic'] = 'Thema &quot;{topic}&quot; aus &quot;{board}&quot; in &quot;{board_to}&quot; wiederhergestellt';
$txt['modlog_ac_restore_posts'] = 'Beiträge aus &quot;{subject}&quot; im Thema &quot;{topic}&quot; im Forum &quot;{board}&quot; wiederhergestellt.';

$txt['modlog_parameter_guest'] = '<em>Gast</em>';

$txt['modlog_ac_approve_attach'] = 'Anhang &quot;{filename}&quot; in &quot;{message}&quot; freigeschaltet';
$txt['modlog_ac_remove_attach'] = 'Nicht freigeschalteten Anhang &quot;{filename}&quot; in &quot;{message}&quot; entfernt';
