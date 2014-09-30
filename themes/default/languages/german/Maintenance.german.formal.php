<?php
// Version: 1.0; Maintenance

$txt['repair_zero_ids'] = 'Themen und/oder Nachrichten mit Themen- oder Nachrichten-ID 0 gefunden.';
$txt['repair_missing_topics'] = 'Nachricht #%1$d befindet sich im nicht existenten Thema #%2$d.';
$txt['repair_missing_messages'] = 'Thema #%1$d enthält keine (tatsächlichen) Nachrichten.';
$txt['repair_stats_topics_1'] = 'Die erste Nachricht im Thema #%1$d hat die ID #%2$d, was nicht stimmt.',
$txt['repair_stats_topics_2'] = 'Die letzte Nachricht im Thema #%1$d hat die ID #%2$d, was nicht stimmt.',
$txt['repair_stats_topics_3'] = 'Thema #%1$d hat eine falsche Anzahl an Antworten, %2$d.';
$txt['repair_stats_topics_4'] = 'Thema #%1$d hat eine falsche Anzahl an nicht freigegebenen Beiträgen, %2$d.';
$txt['repair_stats_topics_5'] = 'Thema #%1$d besitzt die falsche Freischaltungseinstellung.';
$txt['repair_missing_boards'] = 'Thema #%1$d befindet sich im nicht existenten Forum #%2$d.';
$txt['repair_missing_categories'] = 'Forum #%1$d befindet sich in der nicht existenten Kategorie #%2$d.';
$txt['repair_missing_posters'] = 'Nachricht #%1$d wurde vom nicht existenten Mitglied #%2$d verfasst.';
$txt['repair_missing_parents'] = 'Forum #%1$d ist ein Unterforum des nicht existenten Forums #%2$d.';
$txt['repair_missing_polls'] = 'Thema #%1$d ist mit der nicht vorhandenen Umfrage #%2$d verknüpft.';
$txt['repair_polls_missing_topics'] = 'Umfrage #%1$d ist mit dem nicht vorhandenen Thema #%2$d verknüpft.';
$txt['repair_missing_calendar_topics'] = 'Ereignis #%1$d ist mit dem nicht vorhandenen Thema #%2$d verknüpft.';
$txt['repair_missing_log_topics'] = 'Thema #%1$d wurde von einem oder mehreren Benutzern als gelesen markiert, existiert jedoch nicht.';
$txt['repair_missing_log_topics_members'] = 'Benutzer #%1$d hat ein oder mehrere Themen als gelesen markiert, existiert jedoch nicht.';
$txt['repair_missing_log_boards'] = 'Forum #%1$d wurde von einem oder mehreren Benutzern als gelesen markiert, existiert jedoch nicht.';
$txt['repair_missing_log_boards_members'] = 'Benutzer #%1$d hat ein oder mehrere Foren als gelesen markiert, existiert jedoch nicht.';
$txt['repair_missing_log_mark_read'] = 'Forum #%1$d wurde von einem oder mehreren Benutzern als gelesen markiert, existiert jedoch nicht.';
$txt['repair_missing_log_mark_read_members'] = 'Benutzer #%1$d hat ein oder mehrere Foren als gelesen markiert, existiert jedoch nicht.';
$txt['repair_missing_pms'] = 'Private Nachricht #%1$d wurde an einen oder mehrere Benutzer gesendet, existiert jedoch nicht.';
$txt['repair_missing_recipients'] = 'Benutzer #%1$d hat eine oder mehrere private Nachrichten erhalten, existiert jedoch nicht.';
$txt['repair_missing_senders'] = 'Private Nachricht #%1$d wurde vom nicht existenten Mitglied #%2$d versandt.';
$txt['repair_missing_notify_members'] = 'Benutzer #%1$d hat eine oder mehrere Benachrichtigungen aktiviert, existiert jedoch nicht.';
$txt['repair_missing_cached_subject'] = 'Der Betreff des Themas #%1$d ist zurzeit nicht im Betreffzwischenspeicher gespeichert.';
$txt['repair_missing_topic_for_cache'] = 'Das zwischengespeicherte Wort \'%1$s\' ist mit einem nicht vorhandenen Thema verknüpft.';
$txt['repair_missing_log_poll_member'] = 'In der Umfrage #%1$d hat das nicht vorhandene Mitglied #%2$d abgestimmt.';
$txt['repair_missing_log_poll_vote'] = 'Mitglied #%1$d hat an der nicht vorhandenen Umfrage #%2$d teilgenommen.';
$txt['repair_missing_thumbnail_parent'] = 'Eine Miniaturansicht namens %1$s ist vorhanden, hat jedoch kein Elternelement.';
$txt['repair_report_missing_comments'] = 'Die Meldung #%1$d für das Thema &quot;%2$s&quot; hat keine Kommentare.';
$txt['repair_comments_missing_report'] = 'Der Meldungskommentar #%1$d von %2$s gehört zu keiner Meldung.';
$txt['repair_group_request_missing_member'] = 'Es ist noch eine Gruppenanfrage für das gelöschte Mitglied #%1$d vorhanden.';
$txt['repair_group_request_missing_group'] = 'Es ist noch eine Gruppenanfrage für die gelöschte Gruppe #%1$d vorhanden.';

$txt['repair_currently_checking'] = 'Prüfe: &quot;%1$s&quot;';
$txt['repair_currently_fixing'] = 'Korrigiere: &quot;%1$s&quot;';
$txt['repair_operation_zero_topics'] = 'Themen, deren id_topic fälschlicherweise auf 0 gesetzt wurde';
$txt['repair_operation_zero_messages'] = 'Beiträge, deren id_msg fälschlicherweise auf 0 gesetzt wurde';
$txt['repair_operation_missing_topics'] = 'Beiträge, die zu keinem Thema gehören';
$txt['repair_operation_missing_messages'] = 'Themen ohne jegliche Beiträge';
$txt['repair_operation_stats_topics'] = 'Themen mit falscher Angabe des ersten oder letzten Beitrags.';
$txt['repair_operation_stats_topics2'] = 'Themen mit der falschen Antwortzahl';
$txt['repair_operation_stats_topics3'] = 'Themen mit der falschen Anzahl nicht freigegebener Beiträge';
$txt['repair_operation_missing_boards'] = 'Themen in einem nicht vorhandenen Forum';
$txt['repair_operation_missing_categories'] = 'Foren in einer nicht vorhandenen Kategorie';
$txt['repair_operation_missing_posters'] = 'Beiträge, die mit nicht vorhandenen Mitgliedern verknüpft sind';
$txt['repair_operation_missing_parents'] = 'Subforen mit nicht vorhandenen Elternforen';
$txt['repair_operation_missing_polls'] = 'Themen, die mit nicht vorhandenen Umfragen verknüpft sind';
$txt['repair_operation_missing_calendar_topics'] = 'Ereignisse, die mit nicht vorhandenen Themen verknüpft sind';
$txt['repair_operation_missing_log_topics'] = 'Themenprotokolle, die mit nicht vorhandenen Themen verknüpft sind';
$txt['repair_operation_missing_log_topics_members'] = 'Themenprotokolle, die mit nicht vorhandenen Mitgliedern verknüpft sind';
$txt['repair_operation_missing_log_boards'] = 'Forenprotokolle, die mit nicht vorhandenen Foren verknüpft sind';
$txt['repair_operation_missing_log_boards_members'] = 'Forenprotokolle, die mit nicht vorhandenen Mitgliedern verknüpft sind';
$txt['repair_operation_missing_log_mark_read'] = 'Gelesen-Markierungen nicht vorhandener Foren';
$txt['repair_operation_missing_log_mark_read_members'] = 'Gelesen-Markierungen nicht vorhandener Mitglieder';
$txt['repair_operation_missing_pms'] = 'PN-Empfänger, zu denen es keine private Nachricht gibt';
$txt['repair_operation_missing_recipients'] = 'PN-Empfänger, zu denen es kein Mitglied gibt';
$txt['repair_operation_missing_senders'] = 'Private Nachrichten, die mit einem nicht vorhandenen Mitglied verknüpft sind';
$txt['repair_operation_missing_notify_members'] = 'Benachrichtigungsprotokolle, die mit einem nicht vorhandenen Mitglied verknüpft sind';
$txt['repair_operation_missing_cached_subject'] = 'Themen, denen ihre Suchzwischenspeichereinträge fehlen';
$txt['repair_operation_missing_topic_for_cache'] = 'Suchzwischenspeichereinträge, die mit einem nicht vorhandenen Thema verknüpft sind';
$txt['repair_operation_missing_member_vote'] = 'Abstimmungen, die mit einem nicht vorhandenen Mitglied verknüpft sind';
$txt['repair_operation_missing_log_poll_vote'] = 'Abstimmungen, die mit einer nicht vorhandenen Umfrage verknüpft sind';
$txt['repair_operation_report_missing_comments'] = 'Themenmeldungen ohne jeglichen Kommentar';
$txt['repair_operation_comments_missing_report'] = 'Meldungskommentare ohne zugehörige Meldung';
$txt['repair_operation_group_request_missing_member'] = 'Gruppenanfragen, denen das anfragende Mitglied fehlt';
$txt['repair_operation_group_request_missing_group'] = 'Gruppenanfragen für eine nicht vorhandene Gruppe';

$txt['salvaged_category_name'] = 'Papierkorb'; // translator note: hmm, not sure about that...
$txt['salvaged_category_error'] = 'Konnte die Papierkorbkategorie nicht erzeugen!';
$txt['salvaged_board_name'] = 'Themen im Papierkorb';
$txt['salvaged_board_description'] = 'Themen, die für Beiträge ohne ein zugehöriges Thema erstellt wurden';
$txt['salvaged_board_error'] = 'Konnte das Forum für gelöschte Themen nicht erzeugen!';
$txt['salvaged_poll_topic_name'] = 'Gelöschte Umfrage';
$txt['salvaged_poll_message_body'] = 'Diese Umfrage scheint zu keinem Thema zu gehören.';

$txt['database_optimize'] = 'Datenbank optimieren';
$txt['database_numb_tables'] = 'Ihre Datenbank enthält %1$d Tabellen.';
$txt['database_optimize_attempt'] = 'Versuche Ihre Datenbank zu optimieren...';
$txt['database_optimizing'] = 'Optimizere %1$s... %2$01.2f KiB gespart.';
$txt['database_already_optimized'] = 'Alle Tabellen wurden bereits optimiert.';
$txt['database_optimized'] = ' Tabelle(n) optimiert.';

$txt['apply_filter'] = 'Filter anwenden';
$txt['applying_filter'] = 'Wende Filter an';
$txt['filter_only_member'] = 'Nur die Fehlermeldungen dieses Mitglieds anzeigen';
$txt['filter_only_ip'] = 'Nur die Fehlermeldungen dieser IP-Adresse anzeigen';
$txt['filter_only_session'] = 'Nur die Fehlermeldungen dieser Sitzung anzeigen';
$txt['filter_only_url'] = 'Nur die Fehlermeldungen dieses URLs anzeigen';
$txt['filter_only_message'] = 'Nur die Fehler mit derselben Nachricht anzeigen';
$txt['session'] = 'Sitzung';
$txt['error_url'] = 'URL der Seite, die den Fehler verursacht hat';
$txt['error_message'] = 'Fehlermeldung';
$txt['clear_filter'] = 'Filter leeren';
$txt['remove_selection'] = 'Auswahl entfernen';
$txt['remove_filtered_results'] = 'Alle gefilterten Ergebnisse entferen';
$txt['sure_about_errorlog_remove'] = 'Sind Sie sich sicher, dass Sie das Fehlerprotokoll vollständig leeren möchten?';
$txt['remove_selection_confirm'] = 'Sind Sie sich sicher, dass Sie die ausgewählten Einträge löschen möchten?';
$txt['remove_filtered_results_confirm'] = 'Sind Sie sich sicher, dass Sie die gefilterten Einträge löschen möchten?';
$txt['reverse_direction'] = 'Chronologische Reihenfolge umkehren';
$txt['error_type'] = 'Art des Fehlers';
$txt['filter_only_type'] = 'Nur die Fehler dieses Typs anzeigen';
$txt['filter_only_file'] = 'Nur die Fehler aus dieser Daten anzeigen';
$txt['apply_filter_of_type'] = 'Filter nach Typ aktivieren';

$txt['errortype_all'] = 'Alle Fehler';
$txt['errortype_general'] = 'Allgemein';
$txt['errortype_general_desc'] = 'Allgemeine Fehler, die keiner der anderen Kategorien zugeordnet werden können';
$txt['errortype_critical'] = '<span style="color:red;">Kritisch</span>';
$txt['errortype_critical_desc'] = 'Kritische Fehler.  Um diese sollten Sie sich schnellstmöglich kümmern.  Tun Sie dies nicht, so kann das zum Ausfall Ihres Forums und möglichen Sicherheitsproblemen führen';
$txt['errortype_database'] = 'Datenbank';
$txt['errortype_database_desc'] = 'Fehler, die durch fehlerhafte Datenbankabfragen verursacht wurden.  Diese sollten angesehen und dem ElkArte-Team gemeldet werden.';
$txt['errortype_undefined_vars'] = 'Undefiniert';
$txt['errortype_undefined_vars_desc'] = 'Fehler, die durch die Verwendung undefinierter Variablen, Indizes oder Offsets verursacht wurden.'; // translator note: "offset" - "Versatz"? something like that...
$txt['errortype_template'] = 'Vorlage';
$txt['errortype_template_desc'] = 'Fehler, die mit dem Laden von Vorlagen zusammenhängen.';
$txt['errortype_user'] = 'Benutzer';
$txt['errortype_user_desc'] = 'Fehler, die aus Benutzerfehlern resultieren.  Beinhaltet falsche Passwörter, Anmeldeversuche gesperrter Benutzer und versuchte Anwendung von Aktionen, für die die nötigen Befugnisse nicht erteilt wurden.';

$txt['maintain_recount'] = 'Alle Zähler und Statistiken des Forums neu berechnen';
$txt['maintain_recount_info'] = 'Sollte die Anzahl der Beiträge in einem Thema oder der privaten Nachrichten in Ihrem Posteingang nicht stimmen: diese Funktion wird alle gespeicherten Zähler und Statistiken für Sie neu berechnen.';
$txt['maintain_errors'] = 'Alle Fehler finden und beheben';
$txt['maintain_errors_info'] = 'Falls zum Beispiel nach einem Serverabsturz Themen oder Beiträge fehlen, könnte diese Funktion Ihnen dabei helfen, sie wiederzufinden.';
$txt['maintain_logs'] = 'Unwichtige Protokolle leeren';
$txt['maintain_logs_info'] = 'Diese Funktion wird alle unwichtigen Protokolle leeren. Dies sollte vermieden werden, wenn es nicht nötig ist, aber es richtet auch keinen Schaden an.';
$txt['maintain_cache'] = 'Den Zwischenspeicher leeren';
$txt['maintain_cache_info'] = 'Diese Funktion wird den Zwischenspeicher leeren, wenn Sie dies wünschen.';
$txt['maintain_optimize'] = 'Alle Tabellen optimieren';
$txt['maintain_optimize_info'] = 'Diese Aufgabe erlaubt es Ihnen, alle Tabellen zu optimieren. Dies wird überschüssige Daten entfernen, was letztendlich die Tabellen kleiner und Ihr Forum schneller macht.';
$txt['maintain_version'] = 'Alle Dateien auf aktuelle Versionen prüfen';
$txt['maintain_version_info'] = 'Diese Wartungsaufgabe ermöglicht es Ihnen, die Versionsnummern aller Dateien Ihres Forums mit denen in der offiziellen Liste zu vergleichen.';
$txt['maintain_run_now'] = 'Aufgabe jetzt ausführen';
$txt['maintain_return'] = 'Zurück zur Forenwartung';

$txt['maintain_backup'] = 'Datenbank sichern';
$txt['maintain_backup_info'] = 'Laden Sie für den Notfall eine Kopie Ihrer Forendatenbank herunter.';
$txt['maintain_backup_struct'] = 'Tabellenstruktur sichern.';
$txt['maintain_backup_data'] = 'Tabellendaten sichern (das wichtige Zeug).';
$txt['maintain_backup_gz'] = 'Datei per GZip komprimieren.';
$txt['maintain_backup_save'] = 'Herunterladen';

$txt['maintain_old'] = 'Alte Beiträge entfernen';
$txt['maintain_old_since_days'] = 'Alle Themen entfernen, in denen seit %1$s Tagen nichts geschrieben wurde, nämlich:';
$txt['maintain_old_nothing_else'] = 'Jede Art von Thema.';
$txt['maintain_old_are_moved'] = 'Thema-verschoben-Hinweise.';
$txt['maintain_old_are_locked'] = 'Gesperrt.';
$txt['maintain_old_are_not_stickied'] = 'Angeheftete Themen nicht zählen.';
$txt['maintain_old_all'] = 'Alle Foren (klicken Sie zur Auswahl bestimmter Foren)';
$txt['maintain_old_choose'] = 'Bestimmte Foren (klicken Sie zur Auswahl aller Foren)';
$txt['maintain_old_remove'] = 'Jetzt entfernen';
$txt['maintain_old_confirm'] = 'Sind Sie sich wirklich sicher, dass Sie jetzt diese alten Beiträge entfernen möchten?\\n\\nDies kann nicht rückgängig gemacht werden!';

$txt['maintain_old_drafts'] = 'Alte Entwürfe entfernen';
$txt['maintain_old_drafts_days'] = 'Alle Entwürfe entfernen, die älter sind als %1$s Tage';
$txt['maintain_old_drafts_confirm'] = 'Sind Sie sich wirklich sicher, dass Sie jetzt diese alten Entwürfe entfernen möchten?\\n\\nDies kann nicht rückgängig gemacht werden!';
$txt['maintain_members'] = 'Inaktive Benutzer entfernen';
$txt['maintain_members_since'] = 'Alle Mitglieder entfernen, deren letzte {select_conditions} länger als {num_days} Tage zurückliegt.';
$txt['maintain_members_activated'] = 'Aktivierung';
$txt['maintain_members_logged_in'] = 'Anmeldung';
$txt['maintain_members_all'] = 'Alle Benutzergruppen';
$txt['maintain_members_choose'] = 'Ausgewählte Gruppen';
$txt['maintain_members_confirm'] = 'Sind Sie sich wirklich sicher, dass Sie jetzt diese Benutzerkonten entfernen möchten?\\n\\nDies kann nicht rückgängig gemacht werden!';

$txt['text_title'] = 'In TEXT umwandeln';
$txt['mediumtext_title'] = 'In MEDIUMTEXT umwandeln';
$txt['mediumtext_introduction'] = 'Die Standardnachrichtentabelle kann Beiträge mit bis zu 65.535 Zeichen aufnehmen; um größere Beiträge speichern zu können, muss die Spalte in MEDIUMTEXT umgewandelt werden. Es ist auch möglich, diese Umwandlung rückgängig zu machen (dies würde den benötigten Speicherplatz reduzieren), aber <strong>nur, falls</strong> keiner der Beiträge in der Datenbank die Länge von 65.535 Zeichen überschreitet. Diese Bedingung wird vor der Umwandung geprüft.';
$txt['body_checking_introduction'] = 'Diese Funktion wird die Spalte in Ihrer Datenbank, die den Text von Beiträgen enthält, in ein TEXT-Format umwandeln (zurzeit ist es MEDIUMTEXT). Dieser Vorgang wird den von jeder Nachricht belegten Speicherplatz geringfügig reduzieren (1 Byte pro Nachricht). Falls irgendeine Nachricht in Ihrer Datenbank die Länge von 65.535 Zeichen überschreitet, wird sie abgeschnitten und ein Teil des Textes verworfen.';
$txt['exceeding_messages'] = 'Folgende Nachrichten sind länger als 65.535 Zeichen und werden während des Vorgangs abgeschnitten:';
$txt['exceeding_messages_morethan'] = 'Und weitere %1$d';
$txt['convert_to_text'] = 'Keine Nachricht überschreitet die Länge von 65.535 Zeichen. Sie können den Vorgang fortsetzen, ohne Daten zu verlieren.';
$txt['convert_to_suggest_text'] = 'Die Tabellenspalte, die den Text von Nachrichten aufnimmt, liegt zurzeit im MEDIUMTEXT-Format vor, aber die Zeichenbegrenzung für Beiträge und Nachrichten in Ihrem Forum liegt unter 65.535 Zeichen. Sie können ein wenig Platz sparen, indem Sie die Spalte in das TEXT-Format umwandeln.';
$txt['convert_proceed'] = 'Fortfahren';

// Move topics out.
$txt['move_topics_maintenance'] = 'Themen verschieben';
$txt['move_topics_from'] = 'Verschiebe Themen aus';
$txt['move_topics_to'] = 'in';
$txt['move_topics_now'] = 'Jetzt verschieben';
$txt['move_topics_confirm'] = 'Sind Sie sich sicher, dass Sie ALLE Themen aus &quot;%board_from%&quot; in &quot;%board_to%&quot; verschieben möchten?';

$txt['maintain_reattribute_posts'] = 'Benutzerbeiträge neu zuordnen';
$txt['reattribute_guest_posts'] = 'Gastbeiträge zuordnen anhand';
$txt['reattribute_email'] = 'der E-Mail-Adresse';
$txt['reattribute_username'] = 'dem Benutzernamen';
$txt['reattribute_current_member'] = 'Beiträge einem Mitglied zuweisen';
$txt['reattribute_increase_posts'] = 'Beiträge in Beitragszähler des Mitglieds aufnehmen';
$txt['reattribute'] = 'Neu zuordnen';
// Don't use entities in the below string.
$txt['reattribute_confirm'] = 'Sind Sie sich sicher, dass Sie alle Gastbeiträge mit %type% "%find%" dem Mitglied "%member_to%" zuweisen möchten?';
$txt['reattribute_confirm_username'] = 'dem Benutzernamen';
$txt['reattribute_confirm_email'] = 'der E-Mail-Adresse';
$txt['reattribute_cannot_find_member'] = 'Das Mitglied, dem die Beiträge zugewiesen werden sollen, konnte nicht gefunden werden.';

$txt['maintain_recountposts'] = 'Benutzerbeiträge neu zählen';
$txt['maintain_recountposts_info'] = 'Führen Sie dieses Wartungswerkzeug aus, um die Beitragszähler Ihrer Benutzer neu zu berechnen.  Es wird alle (zählbaren) Beiträge jedes Benutzers neu zählen und anschließend den Beitragszähler in ihrem Profil aktualisieren';

$txt['safe_mode_enabled'] = '<a href="http://www.php.net/manual/de/features.safe-mode.php">safe_mode</a> ist auf Ihrem Server aktiviert!<br />Die Sicherung mit diesem Hilfsprogramm kann nicht als verlässlich angesehen werden!';
$txt['use_external_tool'] = 'Bitte ziehen Sie in Erwägung, ein externes Werkzeug zu verwenden, um eine Sicherung Ihres Forums anzufertigen; jegliche Sicherung, die mit diesem Hilfsprogramm erzeugt wird, kann nicht als zu 100 % verlässlich eingestuft werden.';
$txt['zipped_file'] = 'Wenn Sie wollen, können Sie eine komprimierte (gezippte) Sicherheitskopie erstellen.';
$txt['plain_text'] = 'Die beste Vorgehensweise, um Ihre Datenbank zu sichern, ist es, eine Reintextdatei zu erzeugen, ein komprimiertes Paket ist möglicherweise weniger verlässlich.';
$txt['enable_maintenance1'] = 'Aufgrund der Größe Ihres Forums ist es ratsam, es zunächst in den Wartungsmodus zu versetzen, bevor Sie fortfahren.';
$txt['enable_maintenance2'] = 'Aufgrund der Größe Ihres Forums müssen Sie Ihr Forum zunächst in den Wartungsmodus versetzen, bevor Sie fortfahren.';
