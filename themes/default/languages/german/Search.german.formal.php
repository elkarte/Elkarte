<?php
// Version: 1.0; Search

$txt['set_parameters'] = 'Suchparameter einstellen';
$txt['choose_board'] = 'Wählen Sie ein Forum aus, das Sie durchsuchen möchten, oder suchen Sie überall';
$txt['all_words'] = 'Nach allen Begriffen suchen'; // translator note: borrowed from phpBB :)
$txt['any_words'] = 'Nach einem Begriff suchen suchen'; // translator note: borrowed from phpBB :)
$txt['by_user'] = 'Nach Benutzer';

$txt['search_post_age'] = 'Alter des Beitrags';
$txt['search_between'] = 'zwischen';
$txt['search_and'] = 'und';
$txt['search_options'] = 'Optionen';
$txt['search_show_complete_messages'] = 'Ergebnisse als Beiträge anzeigen';
$txt['search_subject_only'] = 'Nur in Betreffzeilen suchen';
$txt['search_relevance'] = 'Relevanz';
$txt['search_date_posted'] = 'Erstellt';
$txt['search_order'] = 'Suchreihenfolge';
$txt['search_orderby_relevant_first'] = 'Relevante Ergebnisse zuerst';
$txt['search_orderby_large_first'] = 'Größte Themen zuerst';
$txt['search_orderby_small_first'] = 'Kleinste Themen zuerst';
$txt['search_orderby_recent_first'] = 'Neueste Themen zuerst';
$txt['search_orderby_old_first'] = 'Älteste Themen zuerst';
$txt['search_visual_verification_label'] = 'Verifikation';
$txt['search_visual_verification_desc'] = 'Bitte geben Sie den Code aus obiger Grafik ein, um die Suche durchzuführen.';

$txt['search_specific_topic'] = 'Durchsuche nur Beiträge im Thema';

$txt['mods_cat_search'] = 'Suche';
$txt['groups_search_posts'] = 'Benutzergruppen mit Zugriff auf die Suchfunktion';
$txt['search_dropdown'] = 'Schnellsuchfeld aktivieren';
$txt['search_results_per_page'] = 'Anzahl an Suchergebnissen pro Seite';
$txt['search_weight_frequency'] = 'Relative Suchgewichtung für die Anzahl an Treffern in einem Thema';
$txt['search_weight_age'] = 'Relative Suchgewichtung für das Alter des letzten Treffers';
$txt['search_weight_length'] = 'Relative Suchgewichtung für die Länge von Themen';
$txt['search_weight_subject'] = 'Relative Suchgewichtung für eine passende Betreffzeile';
$txt['search_weight_first_message'] = 'Relative Suchgewichtung für den Treffer eines eröffnenden Beitrags'; // translator note: phew.
$txt['search_weight_sticky'] = 'Relative Suchgewichtung für angeheftete Themen';

$txt['search_settings_desc'] = 'Hier können Sie grundlegende Einstellungen für die Suchfunktion vornehmen.';
$txt['search_settings_title'] = 'Sucheinstellungen';

$txt['search_weights_desc'] = 'Hier können Sie die individuelle Gewichtung einzelner Suchkriterien festlegen.';
$txt['search_weights_none'] = 'Gewichtungsfaktoren werden nicht benötigt, wenn Sphinx verwendet wird, das auf interne Methoden auf Basis von Begriffsnäherung und Schlüsselworthäufigkeit zurückgreift.';
$txt['search_weights_sphinx'] = 'Um Gewichtungsfaktoren für Sphinx zu aktualisieren, müssen Sie eine neue sphinx.conf-Datei erzeugen und installieren.';
$txt['search_weights_title'] = 'Suche - Gewichtungen';
$txt['search_weights_total'] = 'Gesamt';
$txt['search_weights_save'] = 'Speichern';

$txt['search_method_desc'] = 'Hier können Sie festlegen, welcher Motor die Suche antreiben soll.'; // translator note: yep, that's what I wanted.
$txt['search_method_title'] = 'Suche - Methode';
$txt['search_method_save'] = 'Speichern';
$txt['search_method_messages_table_space'] = 'Von Beiträgen verwendeter Speicherplatz in der Datenbank';
$txt['search_method_messages_index_space'] = 'Zum Indizieren verwendeter Speicherplatz in der Datenbank';
$txt['search_method_kilobytes'] = 'KiB';
$txt['search_method_fulltext_index'] = 'Volltextindex';
$txt['search_method_no_index_exists'] = 'ist derzeit nicht vorhanden';
$txt['search_method_fulltext_create'] = 'einen Volltextindex erzeugen';
$txt['search_method_fulltext_cannot_create'] = 'kann nicht erzeugt werden, weil die maximale Nachrichtenlänge über 65.535 liegt oder der Tabellentyp nicht MyISAM ist';
$txt['search_method_index_already_exists'] = 'bereits erzeugt';
$txt['search_method_fulltext_remove'] = 'Volltextindex entfernen';
$txt['search_method_index_partial'] = 'teilweise erzeugt';
$txt['search_index_custom_resume'] = 'fortsetzen';

// These strings are used in a javascript confirmation popup; don't use entities.
$txt['search_method_fulltext_warning'] = 'Um die Volltextsuche verwenden zu können, müssen Sie zunächst einen Volltextindex erzeugen!';
$txt['search_index_custom_warning'] = 'Um die Suche mit einem eigenen Index verwenden zu können, müssen Sie zunächst einen solchen Index erzeugen!';

$txt['search_index'] = 'Suchindex';
$txt['search_index_none'] = 'Kein Index';
$txt['search_index_custom'] = 'Eigener Index';
$txt['search_index_label'] = 'Index';
$txt['search_index_size'] = 'Größe';
$txt['search_index_create_custom'] = 'eigenen Index erzeugen';
$txt['search_index_custom_remove'] = 'eigenen Index entfernen';

$txt['search_index_sphinx'] = 'Sphinx';
$txt['search_index_sphinx_desc'] = 'Um die Sphinx-Einstellungen anzupassen, verwenden Sie [<a href="' . $scripturl . '?action=admin;area=managesearch;sa=managesphinx">Sphinx konfigurieren</a>]';
$txt['search_index_sphinxql'] = 'SphinxQL';
$txt['search_index_sphinxql_desc'] = 'Um die SphinxQL-Einstellungen anzupassen, verwenden Sie [<a href="' . $scripturl . '?action=admin;area=managesearch;sa=managesphinx">Sphinx konfigurieren</a>]';

$txt['search_force_index'] = 'Suchindex erzwingen';
$txt['search_match_words'] = 'Nur ganze Wörter'; // translator note: well that's maybe a bit short...?
$txt['search_max_results'] = 'Maximal anzuzeigende Ergebnisse';
$txt['search_max_results_disable'] = '(0: keine Beschränkung)';
$txt['search_floodcontrol_time'] = 'Mindestabstand zwischen zwei Suchanfragen seitens desselben Benutzers';
$txt['search_floodcontrol_time_desc'] = '(0 für die Aufhebung der Beschränkung, in Sekunden)';

$txt['additional_search_engines'] = 'Weitere Suchmaschinen';
$txt['setup_search_engine_add_more'] = 'Eine weitere Suchmaschine hinzufügen';

$txt['search_create_index'] = 'Index erzeugen';
$txt['search_create_index_why'] = 'Warum soll ich einen Suchindex erzeugen?';
$txt['search_create_index_start'] = 'Erzeugen';
$txt['search_predefined'] = 'Vordefiniertes Profil';
$txt['search_predefined_small'] = 'Kleiner Index';
$txt['search_predefined_moderate'] = 'Mittelgroßer Index';
$txt['search_predefined_large'] = 'Großer Index';
$txt['search_create_index_continue'] = 'Fortfahren';
$txt['search_create_index_not_ready'] = 'ElkArte erzeugt derzeit einen Suchindex Ihrer Beiträge. Um eine Überlastung Ihres Servers zu vermeiden, wurde der Vorgang vorübergehend angehalten. Er sollte in ein paar Sekunden automatisch fortgesetzt werden. Falls nicht, klicken Sie bitte unten auf Fortfahren.';
$txt['search_create_index_progress'] = 'Fortschritt';
$txt['search_create_index_done'] = 'Eigener Suchindex erfolgreich erzeugt.';
$txt['search_create_index_done_link'] = 'Fortfahren';
$txt['search_double_index'] = 'Sie haben derzeit zwei Indizes in der Nachrichtentabelle erzeugt. Um die Geschwindigkeit zu verbessern, wird es empfohlen, dass Sie einen der beiden Indizes löschen.';

$txt['search_error_indexed_chars'] = 'Ungültige Anzahl indizierter Zeichen. Für einen nützlichen Index werden mindestens drei Zeichen benötigt.';
$txt['search_error_max_percentage'] = 'Ungültiger Anteil zu überspringender Wörter. Verwenden Sie einen Wert von mindestens 5 %.';
$txt['error_string_too_long'] = 'Der Suchbegriff darf höchstens %1$d Zeichen lang sein.';

$txt['search_warning_ignored_word'] = 'Folgender Term wurde in Ihrer Suche nicht berücksichtigt';
$txt['search_warning_ignored_words'] = 'Folgende Termini wurden in Ihrer Suche nicht berücksichtigt';

$txt['search_adjust_query'] = 'Suchparameter einstellen';
$txt['search_adjust_submit'] = 'Erneut suchen';
$txt['search_did_you_mean'] = 'Möglicherweise suchten Sie stattdessen nach';

$txt['search_example'] = '<em>z. B.</em> Orwell "Farm der Tiere" -film';

$txt['search_engines_description'] = 'In diesem Bereich können Sie die Entscheidung treffen, wie detailliert Sie Suchmaschinen erfassen möchten, die Ihr Forum indizieren, sowie Suchmaschinenprotokolle einsehen.';
$txt['spider_mode'] = 'Suchmaschinenverfolgungsstufe'; // translator note: I love the German language for that
$txt['spider_mode_note'] = 'Hinweis: Eine höhere Stufe wird auch die Serverlast erhöhen.';
$txt['spider_mode_off'] = 'Deaktiviert';
$txt['spider_mode_standard'] = 'Standard';
$txt['spider_mode_high'] = 'Moderat';
$txt['spider_mode_vhigh'] = 'Aggressiv';
$txt['spider_settings_desc'] = 'Auf dieser Seite können Sie Einstellungen zur Verfolgung von Suchmaschinenspidern vornehmen. Falls Sie übrigens die <a href="%1$s">zugehörigen Protokolle automatisch aufräumen möchten, können Sie dies hier einrichten</a>';

$txt['spider_group'] = 'Restriktive Befugnisse von Gruppe übernehmen';
$txt['spider_group_note'] = 'Um Spidern das Indizieren bestimmter Seiten zu verbieten.'; // translator note: translating "spider" to the more common "crawler" would be pointless, wouldn't it?
$txt['spider_group_none'] = 'Deaktiviert';

$txt['show_spider_online'] = 'Spider in der Onlineliste anzeigen';
$txt['show_spider_online_no'] = 'Gar nicht';
$txt['show_spider_online_summary'] = 'Anzahl an Spidern anzeigen';
$txt['show_spider_online_detail'] = 'Namen der Spider anzeigen';
$txt['show_spider_online_detail_admin'] = 'Namen der Spider anzeigen - nur Admin';

$txt['spider_name'] = 'Spidername';
$txt['spider_last_seen'] = 'Zuletzt gesehen';
$txt['spider_last_never'] = 'Nie';
$txt['spider_agent'] = 'User Agent'; // translator note: this is said to be "German"? brave new times :|
$txt['spider_ip_info'] = 'IP-Adressen';
$txt['spiders_add'] = 'Neuen Spider hinzufügen';
$txt['spiders_edit'] = 'Spider bearbeiten';
$txt['spiders_remove_selected'] = 'Ausgewählte entfernen';
$txt['spider_remove_selected_confirm'] = 'Sind Sie sich sicher, dass Sie diese Spider entfernen möchten?\\n\\nAlle zugehörigen Statistiken werden ebenfalls gelöscht!';
$txt['spiders_no_entries'] = 'Derzeit sind keine Spider konfiguriert.';

$txt['add_spider_desc'] = 'Auf dieser Seite können Sie die Parameter eines Suchmaschinenspiders einstellen. Falls der User Agent/die IP-Adresse eines Gastes dem/der unten eingegebenen entspricht, wird der Gast als dieser Spider erkannt und anhand der Einstellungen des Forums protokolliert.';
$txt['spider_name_desc'] = 'Name, unter dem der Spider angezeigt wird.';
$txt['spider_agent_desc'] = 'User Agent, der mit diesem Spider verbunden ist.';
$txt['spider_ip_info_desc'] = 'Kommagetrennte Liste von IP-Adressen, die diesem Spider zugewiesen sind.';

$txt['spider'] = 'Spider';
$txt['spider_time'] = 'Zeit';
$txt['spider_viewing'] = ' Betrachtet';
$txt['spider_logs_empty'] = 'Es gibt derzeit keine Spiderprotokolleinträge.';
$txt['spider_logs_info'] = 'Beachten Sie, dass die Protokollierung jeder Aktion seitens eines Spiders nur erfolgt, wenn die Verfolgung entweder auf &quot;hoch&quot; oder &quot;sehr hoch&quot; gesetzt wurde. Detaillierte Protokollierung erfolgt nur mit der Einstellung &quot;sehr hoch&quot;.';
$txt['spider_disabled'] = 'Deaktiviert';
$txt['spider_log_empty_log'] = 'Protokoll leeren';
$txt['spider_log_empty_log_confirm'] = 'Sind Sie sich sicher, dass Sie das Protokoll komplett leeren möchten';

$txt['spider_logs_delete'] = 'Einträge löschen';
$txt['spider_logs_delete_older'] = 'Alle Einträge löschen, die älter sind als %1$s Tage.';
$txt['spider_logs_delete_submit'] = 'Löschen';

$txt['spider_stats_delete_older'] = 'Alle Statistiken von Spidern löschen, die seit %1$s Tagen nicht gesehen wurden.';

// Don't use entities in the below string.
$txt['spider_logs_delete_confirm'] = 'Sind Sie sich sicher, dass Sie alle Protokolleinträge leeren möchten?';

$txt['spider_stats_select_month'] = 'Zum Monat springen';
$txt['spider_stats_page_hits'] = 'Seitentreffer';
$txt['spider_stats_no_entries'] = 'Derzeit sind keine Spiderstatistiken verfügbar.';

// strings for setting up sphinx search
$txt['sphinx_test_not_selected'] = 'Sie haben weder Sphinx noch SphinxQL als Suchmethode ausgewählt';
$txt['sphinx_test_passed'] = 'Alle Tests wurden bestanden, das System konnte sich mittels des Sphinx-APIs mit dem Sphinx-Server verbinden.';
$txt['sphinxql_test_passed'] = 'Alle Tests wurden bestanden, das System konnte sich mittels SphinxQL-Befehlen mit dem Sphinx-Server verbinden.';
$txt['sphinx_test_connect_failed'] = 'Konnte keine Verbindung mit dem Sphinx-Daemon herstellen. Stellen Sie sicher, dass er läuft und richtig konfiguriert ist. Die Sphinx-Suche wird nicht funktionieren, bevor Sie das Problem behoben haben.';
$txt['sphinxql_test_connect_failed'] = 'Konnte nicht auf SphinxQL zugreifen. Stellen Sie sicher, dass Ihre sphinx.conf über eine gesonderte Lauschdirektive für den SphinxQL-Port verfügt. Die SphinxQL-Suche wird nicht funktionieren, bevor Sie das Problem behoben haben';
$txt['sphinx_test_api_missing'] = 'Die Datei sphinxapi.php fehlt in Ihrem &quot;sources&quot;-Verzeichnis. Sie müssen diese Datei aus der Sphinx-Distribution kopieren. Die Sphinx-Suche wird nicht funktionieren, bevor Sie das Problem behoben haben.';
$txt['sphinx_description'] = 'Benutzen Sie diese Schnittstelle, um die Zugriffsdetails für Ihren Sphinx-Suchdaemon anzugeben. <strong>Diese Einstellungen werden nur benutzt, um eine initiale sphinx.conf-Datei zu erzeugen</strong>, die Sie in Ihrem Sphinx-Konfigurationsverzeichnis (normalerweise /usr/local/etc) speichern müssen. In der Regel können die unten stehenden Optionen unangetastet bleiben, sie gehen allerdings davon aus, dass Sphinx in /usr/local installiert wurde und /var/sphinx zum Speichern der Suchindexdaten verwendet. Um Sphinx aktuell zu halten, müssen Sie einen Cronjob zur Aktualisierung der Indizes einsetzen, andernfalls wird neuer oder geänderter Inhalt nicht in den Suchergebnissen auftauchen. Die Konfigurationsdatei definiert zwei Indizes:<br /><br/><strong>elkarte_delta_index</strong>, einen Index, der nur aktuelle Änderungen speichert und regelmäßig abgerufen werden kann. <strong>elkarte_base_index</strong>, einen Index, der die vollständige Datenbank enthält und nicht so häufig abgerufen werden sollte. Beispiel:<br /><span class="tt">10 3 * * * /usr/local/bin/indexer --config /usr/local/etc/sphinx.conf --rotate elkarte_base_index<br />0 * * * * /usr/local/bin/indexer --config /usr/local/etc/sphinx.conf --rotate elkarte_delta_index</span>';
$txt['sphinx_index_data_path'] = 'Indexdatenpfad:';
$txt['sphinx_index_data_path_desc'] = 'Dies ist der Pfad, der die von Sphinx verwendeten Indexdateien enthält.<br />Er <strong>muss</strong> vorhanden und von Sphinx les- sowie beschreibbar sein.';
$txt['sphinx_log_file_path'] = 'Pfad zur Protokolldatei:';
$txt['sphinx_log_file_path_desc'] = 'Pfad auf dem Server, der die Protokolldateien enthält, die Sphinx erzeugt.<br />Dieses Verzeichnis muss auf Ihrem Server existieren und von Sphinx beschreibbar sein.';
$txt['sphinx_stop_word_path'] = 'Stoppwortpfad:';
$txt['sphinx_stop_word_path_desc'] = 'Der Serverpfad zur Ihrer Stoppwortliste (leer lassen, um keine zu verwenden).';
$txt['sphinx_memory_limit'] = 'Speicherbeschränkung für den Sphinx-Indexer:';
$txt['sphinx_memory_limit_desc'] = 'Die maximale Menge an Speicher (RAM), die der Indexer belegen darf.';
$txt['sphinx_searchd_server'] = 'Suchdaemonserver:';
$txt['sphinx_searchd_server_desc'] = 'Adresse des Servers, auf dem der Suchdaemon läuft. Dies muss ein gültiger Hostname oder eine gültige IP-Adresse sein.<br />Falls nicht angegeben, wird localhost genutzt.';
$txt['sphinx_searchd_port'] = 'Suchdaemonport:';
$txt['sphinx_searchd_port_desc'] = 'Port, auf dem der Suchdaemon lauscht.';
$txt['sphinx_searchd_qlport'] = 'SphinxQL-Daemonport:';
$txt['sphinx_searchd_qlport_desc'] = 'Port, auf dem der Suchdaemon nach SphinxQL-Abfragen lauscht.';
$txt['sphinx_max_matches'] = 'Maximale # Ergebnisse:';
$txt['sphinx_max_matches_desc'] = 'Maximale Anzahl an Ergebnissen, die der Suchdaemon zurückliefert.';
$txt['sphinx_create_config'] = 'Sphinxkonfiguration erzeugen';
$txt['sphinx_test_connection'] = 'Verbindung mit dem Sphinxdaemon testen';
