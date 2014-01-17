<?php
// Version: 1.0; ManageScheduledTasks

$txt['scheduled_tasks_title'] = 'Geplante Aufgaben';
$txt['scheduled_tasks_header'] = 'Alle geplanten Aufgaben';
$txt['scheduled_tasks_name'] = 'Aufgabenname';
$txt['scheduled_tasks_next_time'] = 'Nächster Termin';
$txt['scheduled_tasks_regularity'] = 'Regelmäßigkeit';
$txt['scheduled_tasks_enabled'] = 'Aktiviert';
$txt['scheduled_tasks_run_now'] = 'Jetzt ausführen';
$txt['scheduled_tasks_save_changes'] = 'Änderungen speichern';
$txt['scheduled_tasks_time_offset'] = '<strong>Hinweis:</strong> Alle unten angegebenen Zeitpunkte beziehen sich auf die <em>Serverzeit</em> und berücksichtigen etwaige Zeitabweichungen des Forums nicht.';
$txt['scheduled_tasks_were_run'] = 'Alle ausgewählten Aufgaben sind abgeschlossen';
$txt['scheduled_tasks_were_run_errors'] = 'Folgende Fehler traten beim Ausführen der ausgewählten Aufgaben auf:';

$txt['scheduled_tasks_na'] = 'n.v.';
$txt['scheduled_task_approval_notification'] = 'Freischaltungsbenachrichtigungen';
$txt['scheduled_task_desc_approval_notification'] = 'Sendet E-Mails bezüglich freizuschaltender Beiträge an alle Moderatoren.';
$txt['scheduled_task_auto_optimize'] = 'Datenbank optimieren';
$txt['scheduled_task_desc_auto_optimize'] = 'Optimiert die Datenbank, um Fragmentierungsprobleme zu beseitigen.';
$txt['scheduled_task_daily_maintenance'] = 'Tägliche Wartung';
$txt['scheduled_task_desc_daily_maintenance'] = 'Führt tägliche grundlegende Wartungsarbeiten im Forum aus - sollte nicht deaktiviert werden.';
$txt['scheduled_task_daily_digest'] = 'Tägliche Benachrichtigungszusammenfassung';
$txt['scheduled_task_desc_daily_digest'] = 'Versendet die tägliche Zusammenfassung über Neuigkeiten an alle Abonnenten.';
$txt['scheduled_task_weekly_digest'] = 'Wöchentliche Benachrichtigungszusammenfassung';
$txt['scheduled_task_desc_weekly_digest'] = 'Versendet die wöchentliche Zusammenfassung über Neuigkeiten an alle Abonnenten.';
$txt['scheduled_task_fetchFiles'] = 'ElkArte-Versionsinformationen abrufen';
$txt['scheduled_task_desc_fetchFiles'] = 'Ruft Javascripts mit der aktuellen Revision, Updatebenachrichtigungen und anderen Informationen ab.';
$txt['scheduled_task_birthdayemails'] = 'Geburtstagsmails versenden';
$txt['scheduled_task_desc_birthdayemails'] = 'Versendet E-Mails, die Mitgliedern alles Gute zum Geburtstag wünschen.';
$txt['scheduled_task_weekly_maintenance'] = 'Wöchentliche Wartung';
$txt['scheduled_task_desc_weekly_maintenance'] = 'Führt wöchentliche grundlegende Wartungsarbeiten im Forum aus - sollte nicht deaktiviert werden.';
$txt['scheduled_task_paid_subscriptions'] = 'Überprüfung bezahlter Abonnements';
$txt['scheduled_task_desc_paid_subscriptions'] = 'Versendet Erinnerungs-E-Mails bezüglich anstehender Abonnementerneuerungen und entfernt abgelaufene Abonnements.';
$txt['scheduled_task_remove_topic_redirect'] = 'VERSCHOBEN:-Weiterleitungen entfernen';
$txt['scheduled_task_desc_remove_topic_redirect'] = 'Löscht alte "VERSCHOBEN:"-Themen, die angelegt werden, wenn ein Thema verschoben wird.';
$txt['scheduled_task_remove_temp_attachments'] = 'Temporäre Dateianhänge löschen';
$txt['scheduled_task_desc_remove_temp_attachments'] = 'Löscht temporäre Dateien, die beim Anhängen von Dateien an Beiträge erstellt wurden und aus irgendeinem Grund noch nicht gelöscht oder verschoben worden sind.';
$txt['scheduled_task_remove_old_drafts'] = 'Alte Entwürfe löschen';
$txt['scheduled_task_desc_remove_old_drafts'] = 'Lösche Entwürfe, die älter als das im ACP eingestellte Höchstalter für Entwürfe sind.';
$txt['scheduled_task_remove_old_followups'] = 'Alte Folgenachrichten löschen';
$txt['scheduled_task_desc_remove_old_followups'] = 'Löscht Folgebeiträge, die noch in der Datenbank gespeichert sind, aber nicht mehr auf einen vorhandenen Beitrag zeigen.';
$txt['scheduled_task_maillist_fetch_IMAP'] = 'E-Mails via IMAP abrufen';
$txt['scheduled_task_desc_maillist_fetch_IMAP'] = 'Holt E-Mails für die Rundmailfunktion von einem IMAP-Server ab und verarbeitet sie.';

$txt['scheduled_task_reg_starting'] = 'Beginne um %1$s';
$txt['scheduled_task_reg_repeating'] = 'wiederhole alle %1$d %2$s';
$txt['scheduled_task_reg_unit_m'] = 'Minute(n)';
$txt['scheduled_task_reg_unit_h'] = 'Stunde(n)';
$txt['scheduled_task_reg_unit_d'] = 'Tag(e)';
$txt['scheduled_task_reg_unit_w'] = 'Woche(n)';

$txt['scheduled_task_edit'] = 'Geplante Aufgabe ändern';
$txt['scheduled_task_edit_repeat'] = 'Wiederhole Aufgabe alle';
$txt['scheduled_task_edit_pick_unit'] = 'Wählen Sie eine Einheit aus';
$txt['scheduled_task_edit_interval'] = 'Intervall';
$txt['scheduled_task_edit_start_time'] = 'Startzeit';
$txt['scheduled_task_edit_start_time_desc'] = 'Der Zeitpunkt, an dem die erste Instanz am Tag ausgeführt werden soll (Stunden:Minuten)';
$txt['scheduled_task_time_offset'] = 'Berücksichtigen Sie die eventuelle Zeitabweichung der aktuellen Serverzeit. Die aktuelle Serverzeit ist: %1$s';

$txt['scheduled_view_log'] = 'Protokoll ansehen';
$txt['scheduled_log_empty'] = 'Zurzeit gibt es keine Aufgabenprotokolleinträge.';
$txt['scheduled_log_time_run'] = 'Ausführzeitpunkt';
$txt['scheduled_log_time_taken'] = 'Dauer';
$txt['scheduled_log_time_taken_seconds'] = '%1$d Sekunden';
$txt['scheduled_log_completed'] = 'Aufgabe abgeschlossen';
$txt['scheduled_log_empty_log'] = 'Protokoll leeren';
$txt['scheduled_log_empty_log_confirm'] = 'Sind Sie sich sicher, dass Sie das Protokoll vollständig leeren möchten?';
