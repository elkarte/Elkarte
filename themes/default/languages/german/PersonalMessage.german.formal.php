<?php
// Version: 1.0; PersonalMessage

$txt['pm_inbox'] = 'Private Nachrichten - Posteingang';
$txt['pm_add'] = 'Hinzufügen';
$txt['make_bcc'] = 'BCC hinzufügen';
$txt['pm_to'] = 'An';
$txt['pm_bcc'] = 'BCC';
$txt['inbox'] = 'Posteingang';
$txt['conversation'] = 'Gespräch';
$txt['messages'] = 'Nachrichten';
$txt['sent_items'] = 'Gesendet';
$txt['new_message'] = 'Neue Nachricht';
$txt['delete_message'] = 'Nachrichten löschen';
// Don't translate "PMBOX" in this string.
$txt['delete_all'] = 'Alle Nachrichten im Postfach löschen';
$txt['delete_all_confirm'] = 'Sind Sie sich sicher, dass Sie alle Nachrichten löschen möchten?';

$txt['delete_selected_confirm'] = 'Sind Sie sich sicher, dass Sie alle ausgewählten privaten Nachrichten löschen möchten?';

$txt['sent_to'] = 'Gesendet an';
$txt['reply_to_all'] = 'Allen antworten';
$txt['delete_conversation'] = 'Gespräch löschen';

$txt['pm_capacity'] = 'Kapazität';
$txt['pm_currently_using'] = '%1$s Nachrichten, %2$s %% voll.';
$txt['pm_sent'] = 'Ihre Nachricht wurde erfolgreich versandt.';

$txt['pm_error_user_not_found'] = 'Konnte Mitglied \'%1$s\' nicht finden.';
$txt['pm_error_ignored_by_user'] = 'Benutzer \'%1$s\' hat Ihre private Nachricht blockiert.';
$txt['pm_error_data_limit_reached'] = 'PN konnte nicht an \'%1$s\' gesendet werden, das Postfach ist voll.';
$txt['pm_error_user_cannot_read'] = 'Benutzer \'%1$s\' kann keine privaten Nachrichten erhalten.';
$txt['pm_successfully_sent'] = 'PN erfolgreich an \'%1$s\' versandt.';
$txt['pm_send_report'] = 'Sendebericht'; // ? :)
$txt['pm_undisclosed_recipients'] = 'Verborgene Empfänger';
$txt['pm_too_many_recipients'] = 'Sie können private Nachrichten nicht an mehr als %1$d Empfänger gleichzeitig versenden.';

$txt['pm_read'] = 'Gelesen';
$txt['pm_replied'] = 'Beantwortet';
$txt['pm_mark_unread'] = 'Als ungelesen markieren';

// Message Pruning.
$txt['pm_prune'] = 'Nachrichten aufräumen';
$txt['pm_prune_desc'] = 'Alle privaten Nachrichten löschen, die älter als %1$s Tage sind.';
$txt['pm_prune_warning'] = 'Sind Sie sich sicher, dass Sie die privaten Nachrichten aufräumen möchten?';

// Actions Drop Down.
$txt['pm_actions_title'] = 'Weitere Aktionen';
$txt['pm_actions_delete_selected'] = 'Ausgewählte löschen';
$txt['pm_actions_filter_by_label'] = 'Nach Etikett filtern';
$txt['pm_actions_go'] = 'Los';

// Manage Labels Screen.
$txt['pm_apply'] = 'Anwenden';
$txt['pm_manage_labels'] = 'Etiketten verwalten';
$txt['pm_labels_delete'] = 'Sind Sie sich sicher, dass Sie die ausgewählten Etiketten löschen möchten?';
$txt['pm_labels_desc'] = 'Von hier aus können Sie die in Ihrem Nachrichtenzentrum benutzten Etiketten hinzufügen, ändern und entfernen.';
$txt['pm_label_add_new'] = 'Neues Etikett';
$txt['pm_label_name'] = 'Etikettenname';
$txt['pm_labels_no_exist'] = 'Sie haben momentan keine Etiketten eingerichtet!';

// Labeling Drop Down.
$txt['pm_current_label'] = 'Etikett';
$txt['pm_msg_label_title'] = 'Nachricht etikettieren';
$txt['pm_msg_label_apply'] = 'Etikett anhängen';
$txt['pm_msg_label_remove'] = 'Etikett entfernen';
$txt['pm_msg_label_inbox'] = 'Posteingang';
$txt['pm_sel_label_title'] = 'Ausgewählte etikettieren';

// Sidebar Headings.
$txt['pm_labels'] = 'Etiketten';
$txt['pm_messages'] = 'Nachrichten';
$txt['pm_actions'] = 'Aktionen';
$txt['pm_preferences'] = 'Einstellungen';

$txt['pm_is_replied_to'] = 'Sie haben diese Nachricht weitergeleitet oder beantwortet.';

// Reporting messages.
$txt['pm_report_to_admin'] = 'Einem Admin melden';
$txt['pm_report_title'] = 'Private Nachricht melden';
$txt['pm_report_desc'] = 'Auf dieser Seite können Sie die erhaltene private Nachricht dem Administratorenteam des Forums melden. Stellen Sie sicher, dass Sie eine Beschreibung beifügen, wieso Sie die Nachricht melden, da diese gemeinsam mit dem Inhalt der Originalnachricht versandt wird.';
$txt['pm_report_admins'] = 'Administrator, dem die Nachricht gemeldet werden soll';
$txt['pm_report_all_admins'] = 'An alle Administratoren senden';
$txt['pm_report_reason'] = 'Grund für die Meldung';
$txt['pm_report_message'] = 'Nachricht melden';

// Important - The following strings should use numeric entities.
$txt['pm_report_pm_subject'] = '[MELDUNG] ';
// In the below string, do not translate "{REPORTER}" or "{SENDER}".
$txt['pm_report_pm_user_sent'] = '{REPORTER} hat unten stehende private Nachricht von {SENDER} aus folgendem Grund gemeldet:';
$txt['pm_report_pm_other_recipients'] = 'Weitere Empfänger der Nachricht sind:';
$txt['pm_report_pm_hidden'] = '%1$d versteckte(r) Empfänger';
$txt['pm_report_pm_unedited_below'] = 'Es folgt der Originalinhalt der beanstandeten Nachricht:';
$txt['pm_report_pm_sent'] = 'Gesendet:';

$txt['pm_report_done'] = 'Danke für Ihre Meldung. Sie werden in Kürze von den Administratoren hören.';
$txt['pm_report_return'] = 'Zurück zum Posteingang';

$txt['pm_search_title'] = 'Private Nachrichten durchsuchen';
$txt['pm_search_bar_title'] = 'Nachrichten durchsuchen';
$txt['pm_search_text'] = 'Suchen nach';
$txt['pm_search_go'] = 'Suchen';
$txt['pm_search_advanced'] = 'Erweiterte Optionen anzeigen';
$txt['pm_search_simple'] = 'Erweiterte Optionen verstecken';
$txt['pm_search_user'] = 'Von Benutzer';
$txt['pm_search_match_all'] = 'Finde alle Wörter';
$txt['pm_search_match_any'] = 'FInde mindestens eines der Wörter';
$txt['pm_search_options'] = 'Optionen';
$txt['pm_search_post_age'] = 'Alter der Nachricht';
$txt['pm_search_show_complete'] = 'Vollständige Nachricht in den Ergebnissen anzeigen.';
$txt['pm_search_subject_only'] = 'Nur nach Betreff und Autor suchen.';
$txt['pm_search_sent_only'] = 'Nur in den versendeten Nachricht suchen.';
$txt['pm_search_between'] = 'zwischen';
$txt['pm_search_between_and'] = 'und';
$txt['pm_search_between_days'] = 'Tagen';
$txt['pm_search_order'] = 'Suchreihenfolge';
$txt['pm_search_choose_label'] = 'Zu suchende Etiketten auswählen oder alle durchsuchen';

$txt['pm_search_results'] = 'Suchergebnisse';
$txt['pm_search_none_found'] = 'Keine Nachrichten gefunden';

$txt['pm_search_orderby_relevant_first'] = 'Relevanteste zuerst';
$txt['pm_search_orderby_recent_first'] = 'Neueste zuerst';
$txt['pm_search_orderby_old_first'] = 'Älteste zuerst';

$txt['pm_visual_verification_label'] = 'Verifizierung';
$txt['pm_visual_verification_desc'] = 'Bitte geben Sie den Code auf oben stehender Grafik ein, um diese private Nachricht zu versenden.';

$txt['pm_settings'] = 'Einstellungen ändern';
$txt['pm_change_view'] = 'Ansicht ändern';

$txt['pm_manage_rules'] = 'Regeln verwalten';
$txt['pm_manage_rules_desc'] = 'Nachrichtenregeln erlauben es Ihnen, automatisch eingehende Nachrichten abhängig von einstellbaren Kriterien zu sortieren. Unten finden Sie alle momentan eingerichteten Regeln. Um eine Regel zu ändern, klicken Sie einfach auf ihren Namen.';
$txt['pm_rules_none'] = 'Sie haben noch keine Nachrichtenregeln eingerichtet.';
$txt['pm_rule_title'] = 'Regel';
$txt['pm_add_rule'] = 'Neue Regel hinzufügen';
$txt['pm_apply_rules'] = 'Regeln jetzt anwenden';
// Use entities in the below string.
$txt['pm_js_apply_rules_confirm'] = 'Sind Sie sich sicher, dass Sie die aktuellen Regeln auf alle privaten Nachrichten anwenden möchten?';
$txt['pm_edit_rule'] = 'Regel ändern';
$txt['pm_rule_save'] = 'Regel speichern';
$txt['pm_delete_selected_rule'] = 'Ausgewählte Regeln löschen';
// Use entities in the below string.
$txt['pm_js_delete_rule_confirm'] = 'Sind Sie sich sicher, dass Sie die ausgewählten Regeln löschen möchten?';
$txt['pm_rule_name'] = 'Name';
$txt['pm_rule_name_desc'] = 'Name, mit dem sich diese Regel merken lässt';
$txt['pm_rule_name_default'] = '[NAME]';
$txt['pm_rule_description'] = 'Beschreibung';
$txt['pm_rule_not_defined'] = 'Fügen Sie ein paar Kriterien hinzu, um diese Regel zu beschreiben.';
$txt['pm_rule_js_disabled'] = '<span class="alert"><strong>Hinweis:</strong> Sie haben JavaScript anscheinend deaktiviert. Wir empfehlen Ihnen wärmstens, zur Nutzung dieser Funktion JavaScript zu aktivieren.</span>';
$txt['pm_rule_criteria'] = 'Kriterien';
$txt['pm_rule_criteria_add'] = 'Kriterien hinzufügen';
$txt['pm_rule_criteria_pick'] = 'Kriterien auswählen';
$txt['pm_rule_mid'] = 'Absendername';
$txt['pm_rule_gid'] = 'Absendergruppe';
$txt['pm_rule_sub'] = 'Nachrichtenbetreff enthält';
$txt['pm_rule_msg'] = 'Nachrichtentext enthält';
$txt['pm_rule_bud'] = 'Absender ist Freund';
$txt['pm_rule_sel_group'] = 'Wählen Sie eine Gruppe aus';
$txt['pm_rule_logic'] = 'Beim Überprüfen der Kriterien';
$txt['pm_rule_logic_and'] = 'Alle Kriterien müssen erfüllt werden';
$txt['pm_rule_logic_or'] = 'Mindestens ein Kriterium muss erfüllt werden';
$txt['pm_rule_actions'] = 'Aktionen';
$txt['pm_rule_sel_action'] = 'Wählen Sie eine Aktion aus';
$txt['pm_rule_add_action'] = 'Aktion hinzufügen';
$txt['pm_rule_label'] = 'Nachricht etikettieren mit';
$txt['pm_rule_sel_label'] = 'Wählen Sie ein Etikett aus';
$txt['pm_rule_delete'] = 'Nachricht löschen';
$txt['pm_rule_no_name'] = 'Sie haben vergessen, einen Namen für die Regel einzugeben.';
$txt['pm_rule_no_criteria'] = 'Eine Regel muss mindestens ein Kriterium und eine Aktion umfassen.';
$txt['pm_rule_too_complex'] = 'Die Regel, die Sie zu erstellen versuchen, ist zu lang. Versuchen Sie, sie in kürzere Regeln aufzuteilen.';

$txt['pm_readable_and'] = '<em>und</em>';
$txt['pm_readable_or'] = '<em>oder</em>';
$txt['pm_readable_start'] = 'Wenn ';
$txt['pm_readable_end'] = '.';
$txt['pm_readable_member'] = 'Nachricht ist von &quot;{MEMBER}&quot;';
$txt['pm_readable_group'] = 'Absender ist in der Gruppe &quot;{GROUP}&quot;';
$txt['pm_readable_subject'] = 'Betreff enthält &quot;{SUBJECT}&quot;';
$txt['pm_readable_body'] = 'Nachrichtentext enthält &quot;{BODY}&quot;';
$txt['pm_readable_buddy'] = 'Absender ist ein Freund';
$txt['pm_readable_label'] = 'hänge Etikett &quot;{LABEL}&quot; an';
$txt['pm_readable_delete'] = 'lösche die Nachricht';
$txt['pm_readable_then'] = '<strong>dann</strong>';
