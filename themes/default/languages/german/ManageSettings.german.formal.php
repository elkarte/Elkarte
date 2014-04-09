<?php
// Version: 1.0; ManageSettings

global $scripturl;

$txt['modSettings_desc'] = 'Diese Seite ermöglicht es Ihnen, Einstellungen von Funktionen vorzunehmen und grundlegende Optionen Ihres Forums festzulegen.  Bitte beachten Sie für weitere Einstellungen auch die <a href="' . $scripturl . '?action=admin;area=theme;sa=list;th=%1$s;%3$s=%2$s">Designeinstellungen</a>  Für weitere Informationen über eine Einstellung klicken Sie auf das jeweilige Hilfe-Symbol.';
$txt['security_settings_desc'] = 'Diese Seite erlaubt es Ihnen, Einstellungen vorzunehmen, die insbesondere mit der Sicherheit und Moderation Ihres Forums zu tun haben, einschließlich Anti-Spam-Optionen.';
$txt['modification_settings_desc'] = 'Diese Seite beinhaltet Einstellungen, die durch jegliche Modfikationen an Ihrem Forum hinzugefügt wurden';

$txt['modification_no_misc_settings'] = 'Es sind noch keine Erweiterungen installiert, die irgendwelche Einstellungen hinzugefügt hätten.';

$txt['allow_guestAccess'] = 'Gästen das Benutzen des Forums erlauben';
$txt['userLanguage'] = 'Benutzerseitige Sprachauswahl aktivieren';
$txt['allow_editDisplayName'] = 'Benutzern die Änderung ihres Anzeigenamens erlauben';
$txt['allow_hideOnline'] = 'Nichtadministratoren das Verstecken ihres Onlinestatus\' erlauben';
$txt['titlesEnable'] = 'Eigene Titel aktivieren';
$txt['enable_buddylist'] = 'Freundes-/Ignorierlisten aktivieren';
$txt['enable_unwatch'] = 'Entbeobachten von Themen aktivieren';
$txt['default_personal_text'] = 'Persönlicher Standardtext';
$txt['default_personal_text_note'] = 'Persönlicher Text, der neu registrierten Mitgliedern automatisch zugewiesen wird.';
$txt['time_format'] = 'Standardzeitformat';
$txt['setting_time_offset'] = 'Allgemeine Zeitverschiebung';
$txt['setting_time_offset_note'] = '(zur benutzerspezifischen Option hinzugefügt)';
$txt['setting_default_timezone'] = 'Serverzeitzone';
$txt['failed_login_threshold'] = 'Grenzwert für fehlgeschlagene Anmeldeversuche';
$txt['loginHistoryDays'] = 'Tage zur Aufbewahrung des Anmeldeverlaufs';
$txt['lastActive'] = 'Grenzwert für Onlinezeit pro Benutzer';
$txt['trackStats'] = 'Tägliche Statistiken aufzeichnen';
$txt['hitStats'] = 'Tägliche Seitenbesuche aufzeichnen (setzt Statistiken voraus)';
$txt['enableCompressedOutput'] = 'Komprimierte Ausgabe aktivieren';
$txt['disableTemplateEval'] = 'Auswertung von Vorlagen deaktivieren';
$txt['databaseSession_enable'] = 'Datenbankgetriebene Sitzungen verwenden';
$txt['databaseSession_loose'] = 'Browsern erlauben, auf zwischengespeicherte Seiten zurückzukehren';
$txt['databaseSession_lifetime'] = 'Sekunden bis zur Sitzungszeitüberschreitung';
$txt['enableErrorLogging'] = 'Fehlerprotokollierung aktivieren';
$txt['enableErrorQueryLogging'] = 'Datenbankabfragen in Fehlerprotokoll aufnehmen';
$txt['pruningOptions'] = 'Säubern von Protokolleinträgen aktivieren';
$txt['pruneErrorLog'] = 'Fehlerprotokolleinträge entfernen, die älter sind als';
$txt['pruneModLog'] = 'Moderationsprotokolleinträge entfernen, die älter sind als';
$txt['pruneBanLog'] = 'Sperrtrefferprotokolleinträge entfernen, die älter sind als';
$txt['pruneReportLog'] = 'Beitragsmeldungsprotokolleinträge entfernen, die älter sind als';
$txt['pruneScheduledTaskLog'] = 'Aufgabenplanungsprotokolleinträge entfernen, die älter sind als';
$txt['pruneSpiderHitLog'] = 'Suchmaschinentrefferprotokolleinträge entfernen, die älter sind als';
$txt['pruneBadbehaviorLog'] = 'Bad-Behavior-Protokolleinträge entfernen, die älter sind als';
$txt['cookieTime'] = 'Standardanmeldungscookielebensdauer'; // translator note: uhm...
$txt['localCookies'] = 'Lokale Speicherung von Cookies aktivieren';
$txt['localCookies_note'] = '(SSI funktioniert damit nicht gut)';
$txt['globalCookies'] = 'subdomainunabhängige Cookies verwenden';
$txt['globalCookies_note'] = '(schalten Sie zuerst lokale Cookies ab!)';
$txt['globalCookiesDomain'] = 'Hauptdomain zur Verwendung mit subdomainunabhängigen Cookies';
$txt['globalCookiesDomain_note'] = '(aktivieren Sie zuerst subdomainunabhängige Cookies!<br />Die Domain könnte zum Beispiel "website.com" oder "website.de" ohne http:// oder abschließenden Schrägstrich lauten)';
$txt['invalid_cookie_domain'] = 'The eingeführte Domain scheint ungültig zu sein, bitte überprüfen Sie sie und speichern Sie dann erneut.';
$txt['secureCookies'] = 'Cookies zur Sicherheit zwingen';
$txt['secureCookies_note'] = 'Dies funktioniert nur mit HTTPS - ansonsten nicht verwenden!)';
$txt['httponlyCookies'] = 'Cookieverfügbarkeit nur per HTTP erzwingen';
$txt['httponlyCookies_note'] = '(Cookies werden von Skriptsprachen wie JavaScript nicht mehr erreichbar sein. Diese Einstellung kann dabei helfen, Identitätsdiebstahl durch XSS-Angriffe zu reduzieren.)';
$txt['admin_session_lifetime'] = 'Anzahl an Minuten, für die eine administrative Anmeldung aktiv bleibt';
$txt['auto_admin_session'] = 'Bei der Anmeldung automatisch eine administrative Sitzung starten';
$txt['securityDisable'] = 'Administrationssicherheit deaktivieren';
$txt['securityDisable_moderate'] = 'Moderationssicherheit deaktivieren';
$txt['send_validation_onChange'] = 'Reaktivierung nach Änderung der E-Mail-Adresse erzwingen';
$txt['approveAccountDeletion'] = 'Administrative Bestätigung bei Kontolöschung eines Mitglieds anfordern';
$txt['autoOptMaxOnline'] = 'Maximale Benutzer online bei Optimierung';
$txt['autoFixDatabase'] = 'Defekte Tabellen automatisch korrigieren';
$txt['allow_disableAnnounce'] = 'Benutzern erlauben, Ankündigungen zu deaktivieren';
$txt['disallow_sendBody'] = 'Beitragstext in Benachrichtigungen nicht erlauben';
$txt['jquery_source'] = 'Quelle für die jQuery-Bibliothek';
$txt['jquery_local'] = 'Lokal';
$txt['jquery_cdn'] = 'Google-CDN';
$txt['jquery_auto'] = 'Auto';
$txt['jquery_version'] = 'Geben Sie die zu verwendende Versionsnummer ein, zum Beispiel 1.10.2';
$txt['jquery_default'] = 'Geben Sie die Version von jQuery an, die ElkArte benutzen soll';
$txt['jqueryui_default'] = 'Geben Sie die Version von jQuery UI an, die ElkArte benutzen soll';
$txt['jquery_custom_after'] = 'Es wird nach der lokalen Datei jquery-<strong>X.XX.X</strong>.min.js gesucht';
$txt['jqueryui_custom_after'] = 'Es wird nach der lokalen Datei jquery-ui-<strong>X.XX.X</strong>.min.js gesucht';
$txt['minify_css_js'] = 'JavaScript- und CSS-Dateien komprimieren';
$txt['contact_form_disabled'] = 'Deaktiviert';
$txt['contact_form_registration'] = 'Nur bei Registrierung anzeigen';
$txt['contact_form_menu'] = 'Im Menü anzeigen';
$txt['queryless_urls'] = 'Suchmaschinenfreundliche URLs';
$txt['queryless_urls_note'] = 'Nur Apache/Lighttpd';
$txt['enableReportPM'] = 'Melden privater Nachrichten aktivieren';
$txt['antispam_PM'] = 'Beschränkungen für private Nachrichten';
$txt['max_pm_recipients'] = 'Höchstzahl an Empfänger pro privater Nachricht';
$txt['max_pm_recipients_note'] = '(0 für Aufhebung der Beschränkung, Administratoren sind ausgenommen)';
$txt['compactTopicPagesEnable'] = 'Anzahl angezeigter Seitenlinks beschränken';
$txt['contiguous_page_display'] = 'Anzuzeigende angrenzende Seiten';
$txt['to_display'] = 'anzuzeigen'; // ? :)
$txt['todayMod'] = 'Kurzes Datumsformat aktivieren';
$txt['today_disabled'] = 'Deaktiviert';
$txt['today_only'] = 'Nur heute';
$txt['yesterday_today'] = 'Heute &amp; gestern';
$txt['relative_time'] = 'Relative Zeitstempel';
$txt['onlineEnable'] = '&quot;Online&quot;/&quot;Offline&quot; in Beiträgen und Nachrichten anzeigen';
$txt['enableVBStyleLogin'] = 'Schnellanmeldung auf jeder Seite anzeigen';
$txt['defaultMaxMembers'] = 'Mitglieder pro Seite in Mitgliederliste';
$txt['displayMemberNames'] = 'Auf der Profilkontoschaltfläche den Benutzernamen anstelle von "Mein Konto" anzeigen';
$txt['timeLoadPageEnable'] = 'Benötigte Zeit zur Erstellung jeder Seite anzeigen';
$txt['disableHostnameLookup'] = 'Hostnamensauflösungen deaktivieren';
$txt['who_enabled'] = 'Onlineliste aktivieren';
$txt['make_email_viewable'] = 'Formularmailer aktivieren';
$txt['meta_keywords'] = 'Mit dem Forum verknüpfte Metaschlüsselworte';
$txt['meta_keywords_note'] = 'Für Suchmaschinen. Leer lassen für Standardwerte.';
$txt['settings_error'] = 'Warnung: Aktualisierung der Datei Settings.php schlug fehl, die Einstellungen konnten nicht gespeichert werden.';
$txt['core_settings_saved'] = 'Die Einstellungen wurden erfolgreich gespeichert';

$txt['karmaMode'] = 'Karmamodus';
$txt['karma_options'] = 'Karma deaktivieren|Gesamtkarma aktivieren|Positives/negatives Karma aktivieren';
$txt['karmaMinPosts'] = 'Mindestanzahl an Beiträgen zur Änderung des Karmas';
$txt['karmaWaitTime'] = 'Wartezeit in Stunden festlegen';
$txt['karmaTimeRestrictAdmins'] = 'Administratoren auf Wartezeit beschränken';
$txt['karmaDisableSmite'] = 'Benutzern die Möglichkeit zum Zerschmettern nehmen';
$txt['karmaLabel'] = 'Karmabeschriftung';
$txt['karmaApplaudLabel'] = 'Applaus-Beschriftung';
$txt['karmaSmiteLabel'] = 'Zerschmettern-Beschriftung';

$txt['likes_enabled'] = '&quot;Gefällt mir&quot; aktivieren';
$txt['likeMinPosts'] = 'Mindestanzahl an Beiträgen zum Mögen eines Beitrags';
$txt['likeWaitTime'] = 'Wartezeit in Minuten festlegen';
$txt['likeWaitCount'] = 'Maximale Anzahl an Mögen/Nichtmögen von Beiträgen, die der Benutzer in dieser Zeit vornehmen kann'; // translator note: is this correct grammar at all? :/
$txt['likeRestrictAdmins'] = 'Administratoren auf Begrenzungen beschränken';
$txt['likeAllowSelf'] = 'Mitgliedern das Mögen eigener Beiträge erlauben';
$txt['likeDisplayLimit'] = 'Legt die maximale Anzahl an "gemocht von"-Namen zur Anzeige in der Themenübersicht fest, 0 für keine Begrenzung, -1 zum Deaktivieren';

$txt['caching_information'] = 'ElkArte unterstützt das Zwischenspeichern mittels Beschleunigern. Die derzeit unterstützten Beschleuniger sind:
<ul class="normallist">
	<li>APC</li>
	<li>eAccelerator</li>
	<li>Turck MMCache</li>
	<li>Memcached</li>
	<li>Zend Platform/Performance Suite (nicht Zend Optimizer)</li>
	<li>XCache</li>
</ul>
Die Zwischenspeicherung funktioniert am Besten, wenn Sie PHP mit einem der obigen Beschleuniger kompiliert haben oder Ihnen memcache zur Verfügung steht. Falls dies nicht der Fall ist, wird dateibasierte Zwischenspeicherung verwendet.';
$txt['detected_no_caching'] = '<strong class="alert">Konnte auf Ihrem Server keinen unterstützten Beschleuniger finden.  Es wird stattdessen dateibasierte Zwischenspeicherung angewandt.</strong>';
$txt['detected_accelerators'] = '<strong class="success">Die folgenden Beschleuniger wurden gefunden: %1$s</strong>';

$txt['cache_enable'] = 'Caching-Stufe'; // translator note: "Zwischenspeicherstufe"? any comments?
$txt['cache_off'] = 'Kein Caching';
$txt['cache_level1'] = 'Stufe-1-Caching (empfohlen)';
$txt['cache_level2'] = 'Stufe-2-Caching';
$txt['cache_level3'] = 'Stufe-3-Caching (nicht empfohlen)';
$txt['cache_memcached'] = 'Memcache-Einstellungen';
$txt['cache_accelerator'] = 'Beschleuniger';
$txt['cache_uid'] = 'Beschleunigerbenutzerkennung';
$txt['cache_password'] = 'Beschleunigerpasswort';
$txt['default_cache'] = 'Dateibasiertes Caching';
$txt['apc_cache'] = 'APC';
$txt['eAccelerator_cache'] = 'eAccelerator';
$txt['mmcache_cache'] = 'Turck MMCache';
$txt['memcached_cache'] = 'Memcached';
$txt['zend_cache'] = 'Zend Platform/Performance Suite';
$txt['xcache_cache'] = 'XCache';

$txt['loadavg_warning'] = '<span class="error">Bitte beachten Sie: unten stehende Einstellungen sollten mit Vorsicht geändert werden. Zu niedrige Werte könnten Ihr Forum <strong>unbenutzbar</strong> machen! Der momentane Lastdurchschnitt ist <strong>%01.2f</strong></span>';
$txt['loadavg_enable'] = 'Lastausgleich nach Durchschnittswerten aktivieren';
$txt['loadavg_auto_opt'] = 'Grenzwert für Deaktivierung der automatischen Datenbankoptimierung';
$txt['loadavg_search'] = 'Grenzwert für Deaktivierung der Suche';
$txt['loadavg_allunread'] = 'Grenzwert für Deaktivierung ungelesener Themen';
$txt['loadavg_unreadreplies'] = 'Grenzwert für Deaktivierung ungelesener Antworten';
$txt['loadavg_show_posts'] = 'Grenzwert für Deaktivierung der Anzeige von Benutzerbeiträgen';
$txt['loadavg_userstats'] = 'Grenzwert für Deaktivierung der Anzeige von Benutzerstatistiken';
$txt['loadavg_bbc'] = 'Grenzwert für Deaktivierung der BBCode-Formatierung bei der Anzeige von Beiträgen';
$txt['loadavg_forum'] = 'Grenzwert für <strong>vollständige</strong> Deaktivierung des Forums';
$txt['loadavg_disabled_windows'] = '<span class="error">Lastausgleichsunterstützung ist unter Windows nicht verfügbar.</span>';
$txt['loadavg_disabled_conf'] = '<span class="error">Lastausgleichsunterstützung ist serverseitig deaktiviert.</span>';

$txt['setting_password_strength'] = 'Benötigte Komplexität für Passwörter';
$txt['setting_password_strength_low'] = 'Niedrig - Mindestens vier Zeichen';
$txt['setting_password_strength_medium'] = 'Mittel - Kann den Benutzernamen nicht enthalten';
$txt['setting_password_strength_high'] = 'Hoch - Mischung aus verschiedenen Zeichen';
$txt['setting_enable_password_conversion'] = 'Passworthashumwandlung erlauben';

$txt['antispam_Settings'] = 'Anti-Spam-Verifizierung';
$txt['antispam_Settings_desc'] = 'Dieser Bereich ermöglicht Ihnen die Einrichtung von Verifizierungsprüfungen, um sicherzustellen, dass der Benutzer ein Mensch (und kein Roboter) ist, und die Festlegung, wie und wo diese vorgenommen werden.';
$txt['setting_reg_verification'] = 'Verifizierung bei Registrierung erfordern';
$txt['posts_require_captcha'] = 'Anzahl an Beiträgen, die benötigt werden, um ohne Verifizierung Beiträge zu verfassen';
$txt['posts_require_captcha_desc'] = '(0 für Aufhebung der Verifizierung, Moderatoren sind von ihr ausgenommen)';
$txt['search_enable_captcha'] = 'Verifizierung bei jeder Suche eines Gastes erfordern';
$txt['setting_guests_require_captcha'] = 'Gäste müssen sich zum Verfassen eines Beitrags verifizieren';
$txt['setting_guests_require_captcha_desc'] = '(Automatisch gesetzt, wenn Sie unten eine Beitragszahl angegeben haben)';
$txt['guests_report_require_captcha'] = 'Gäste müssen sich zum Melden eines Beitrags verifizieren';

$txt['badbehavior_title'] = 'Bad-Behavior-Einstellungen';
$txt['badbehavior_details'] = 'Details';
$txt['badbehavior_desc'] = 'Bad Behavior wurde dafür entwickelt, so früh wie möglich einzugreifen, um Spambots rauszuwerfen, bevor sie die Gelegenheit bekommen, auf Ihrer Website zu vandalieren oder gar Ihre Seite nach E-Mail-Adressen und Formularen zum Ausfüllen zu durchwühlen.<br />Bad Behavior blockiert zudem viele E-Mail-Harvester, was zu weniger E-Mail-Spam führt, sowie zahlreiche Websiteknackprogramme, womit es dabei hilft, die Gesamtsicherheit Ihrer Website zu verbessern.';
$txt['badbehavior_wl_desc'] = 'Unangemessenes Whitelisting WIRD Sie Spam aussetzen oder dazu führen, dass Bad Behavior seine Funktionalität vollständig einstellt! <strong>VERWENDEN SIE DIE WEISSE LISTE NICHT</strong>, wenn Sie sich nicht zu 100 % SICHER SIND, dass Sie das tun sollten, und wissen, was Sie tun.';
$txt['badbehavior_enabled'] = 'Bad-Behavior-Prüfung aktivieren';
$txt['badbehavior_enabled_desc'] = 'Aktivieren Sie dies, um den Bad-Behavior-Schutz in Ihrem Forum zu aktivieren.';
$txt['badbehavior_strict'] = 'Strikten Modus aktivieren';
$txt['badbehavior_logging'] = 'Protokollierung aktivieren';
$txt['badbehavior_offsite_forms'] = 'Externe Formulare erlauben';
$txt['badbehavior_verbose'] = 'Ausführliche Protokollierung aktivieren';
$txt['badbehavior_verbose_desc'] = 'Es wird empfohlen, diesen Modus nicht zu verwenden';
$txt['badbehavior_httpbl_key'] = 'http:BL-API-Schlüssel';
$txt['badbehavior_httpbl_key_invalid'] = 'Der angegebene http:BL-API-Schlüssel ist ungültig';
$txt['badbehavior_httpbl_threat'] = 'http:BL-Bedrohungsstufe';
$txt['badbehavior_httpbl_threat_desc'] = '(Standard 25)';
$txt['badbehavior_httpbl_maxage'] = 'http:BL maximales Alter';
$txt['badbehavior_httpbl_maxage_desc'] = '(Standard 30)';
$txt['badbehavior_eucookie'] = 'Aktivieren, um EU-Cookiebehandlung zu aktivieren';
$txt['badbehavior_reverse_proxy'] = 'Reverse Proxy aktivieren'; // translator note: there's no good German term for that :/
$txt['badbehavior_reverse_proxy_header'] = 'Reverse-Proxy-Header';
$txt['badbehavior_reverse_proxy_header_desc'] = '(Standard X-Forwarded-For)';
$txt['badbehavior_reverse_proxy_addresses'] = 'Reverse-Proxy-Adressen';
$txt['badbehavior_default_on'] = '(Standard an)';
$txt['badbehavior_default_off'] = '(Standard aus)';
$txt['badbehavior_whitelist_title'] = 'Whitelisting-Optionen';
$txt['badbehavior_postcount_wl'] = 'Benutzer über einer bestimmten Beitragszahl auf die weiße Liste setzen';
$txt['badbehavior_postcount_wl_desc'] = '(0 zum Deaktivieren)';
$txt['badbehavior_ip_wl'] = 'IP-Adressen auf die weiße Liste setzen';
$txt['badbehavior_ip_wl_desc'] = 'IP-Adresse (CIDR-Format 127.0.0.1 oder 127.0.0.0/24)';
$txt['badbehavior_ip_wl_add'] = 'Eine weitere IP-Adresse hinzufügen';
$txt['badbehavior_useragent_wl'] = 'Browserkennungen auf die weiße Liste setzen';
$txt['badbehavior_useragent_wl_desc'] = 'Beispiel: Mozilla/4.0 (Ich bin\'s, lass\' mich rein)';
$txt['badbehavior_useragent_wl_add'] = 'Eine weitere Browserkennung hinzufügen';
$txt['badbehavior_url_wl'] = 'URLs auf die weiße Liste setzen';
$txt['badbehavior_url_wl_desc'] = 'Beispiel: /subscriptions.php';
$txt['badbehavior_url_wl_add'] = 'Einen weiteren URL hinzufügen';
$txt['badbehavior_wl_comment'] = 'Kommentar';

$txt['configure_emptyfield'] = 'Leeres Verifizierungsfeld';
$txt['configure_emptyfield_desc'] = '<span class="smalltext">Unten können Sie die Leeres-Feld-Verifizierungsmethode aktivieren.  Dies wird ein leeres Feld einfügen, das leer bleiben sollte, es wird verwendet, um Spambots dazu zu bringen, es fälschlicherweise auszufüllen. Obwohl diese Methode separat verwendet werden kann, funktioniert sie am Besten in Verbindung mit CAPTCHA-Verifizierung.</span>';
$txt['enable_emptyfield'] = 'Leeres-Feld-Verifizierung aktivieren';
$txt['configure_verification_means'] = 'Verifizierungsmethoden konfigurieren';
$txt['setting_qa_verification_number'] = 'Anzahl an Verifizierungsfragen, die ein Benutzer beantworten muss';
$txt['setting_qa_verification_number_desc'] = '(0 zum Deaktivieren; Fragen werden unten festgelegt)';
$txt['configure_verification_means_desc'] = '<span class="smalltext">Unten können Sie festlegen, welche Anti-Spam-Funktionen Sie aktivieren möchten, wann immer ein Benutzer verifizieren muss, dass er ein Mensch ist. Beachten Sie, dass der Benutzer <em>jede</em> Verifizierung bestehen muss, so dass, wenn Sie sowohl eine Verifizierungsgrafik als auch einen Frage/Antwort-Test aktivieren, er beide Aufgaben lösen muss, um fortzufahren.</span>';
$txt['setting_visual_verification_num_chars'] = 'Anzahl an Zeichen in der Verifizierungsgrafik';
$txt['setting_visual_verification_type'] = 'Anzuzeigende Verifizierungsgrafik';
$txt['setting_visual_verification_type_desc'] = 'Je komplexer die Grafik ist, desto schwieriger ist es für Maschinen, sie korrekt auszuwerten';
$txt['setting_image_verification_off'] = 'Keine';
$txt['setting_image_verification_vsimple'] = 'Sehr einfach - Normaler Text in der Grafik';
$txt['setting_image_verification_simple'] = 'Einfach - Sich überlappende farbige Zeichen, kein Rauschen';
$txt['setting_image_verification_medium'] = 'Mittel - Sich überlappende farbige Zeichen mit Rauschen/Linien';
$txt['setting_image_verification_high'] = 'Hoch - Gedrehte Zeichen, deutliches Rauschen/deutliche Linien';
$txt['setting_image_verification_extreme'] = 'Extrem - Gedrehte Zeichen, Rauschen, Linien und Blöcke';
$txt['setting_image_verification_sample'] = 'Beispiel';
$txt['setting_image_verification_nogd'] = '<strong>Hinweis:</strong> da die GD-Bibliothek auf diesem Server nicht installiert ist, werden die Komplexitätseinstellungen keine Auswirkungen haben.';
$txt['setup_verification_questions'] = 'Verifizierungsfragen';
$txt['setup_verification_questions_desc'] = '<span class="smalltext">Wenn Sie möchten, dass Benutzer Verifizierungsfragen beantworten, um Spambots abzuhalten, sollten Sie in unten stehender Tabelle einige Fragen einrichten. Sie sollten relativ einfache Fragen wählen; bei Antworten wird nicht auf Groß- und Kleinschreibung geachtet. Sie können BBCode zur Formatierung der Fragen verwenden, zum Löschen einer Frage entfernen Sie einfach den Inhalt der jeweiligen Zeile.</span>';
$txt['setup_verification_question'] = 'Frage';
$txt['setup_verification_answer'] = 'Antwort';
$txt['setup_verification_add_more'] = 'Eine weitere Frage hinzufügen';
$txt['setup_verification_add_more_answers'] = 'Eine weitere Antwort hinzufügen';

$txt['moderation_settings'] = 'Moderationseinstellungen';
$txt['setting_warning_enable'] = 'Verwarnsystem aktivieren';
$txt['warning_enable'] = '<strong>Benutzerverwarnsystem</strong><br />Diese Funktion erlaubt es Mitgliedern des Administrations- und des Moderationsteams, Benutzer zu verwarnen und eine Verwarnstufe festzulegen, anhand derer die Funktionen festgelegt werden, die diesen im Forum zur Verfügung stehen. Bei Aktivierung dieser Option wird eine Befugnis zur Verfügung gestellt, mittels derer definiert werden kann, welche Benutzergruppen Mitglieder verwarnen dürfen. Verwarnstufen können aus einem Benutzerprofil heraus angepasst werden.';
$txt['setting_warning_watch'] = 'Verwarnstufe zur Beobachtung eines Benutzers';
$txt['setting_warning_watch_note'] = 'Die Verwarnstufe, ab der ein Benutzer automatisch auf die Beobachtungsliste gesetzt wird - 0 zum Deaktivieren.';
$txt['setting_warning_moderate'] = 'Verwarnstufe zur Beitragsmoderation';
$txt['setting_warning_moderate_note'] = 'Die Verwarnstufe, ab der alle Beiträge eines Benutzers automatisch auf Vormoderation gesetzt werden - 0 zum Deaktivieren.';
$txt['setting_warning_mute'] = 'Verwarnstufe zur Stummschaltung eines Benutzers';
$txt['setting_warning_mute_note'] = 'Die Verwarnstufe, ab der ein Benutzer keine neuen Beiträge mehr verfassen kann - 0 zum Deaktivieren.';
$txt['setting_user_limit'] = 'Höchstzahl an Benutzerverwarnpunkten pro Tag';
$txt['setting_user_limit_note'] = 'Dieser Wert legt die Höchstzahl an Verwarnpunkten fest, die ein einzelner Moderator einem Benutzer innerhalb von 24 Stunden geben kann - 0 zur Aufhebung der Beschränkung.';
$txt['setting_warning_decrement'] = 'Verwarnpunkte, die alle 24 Stunden verfallen';
$txt['setting_warning_decrement_note'] = 'Wird nur auf Benutzer angewandt, die während der letzten 24 Stunden nicht verwarnt wurden - 0 zum Deaktivieren.';
$txt['setting_warning_show'] = 'Benutzer, die den Verwarnstatus sehen können';
$txt['setting_warning_show_note'] = 'Legt fest, wer die Verwarnstufe von Forenteilnehmern sehen kann.';
$txt['setting_warning_show_mods'] = 'Nur Moderatoren';
$txt['setting_warning_show_user'] = 'Moderatoren und verwarnte Benutzer';
$txt['setting_warning_show_all'] = 'Alle Benutzer';

$txt['signature_settings'] = 'Signatureinstellungen';
$txt['signature_settings_desc'] = 'Verwenden Sie die Einstellungen auf dieser Seite, um festzulegen, wie mit Mitgliedssignaturen umgegangen werden soll.';
$txt['signature_settings_warning'] = 'Beachten Sie, dass Änderungen standardmäßig nicht auf bereits vorhandene Signaturen angewandt werden.<br /><a class="button_submit" href="' . $scripturl . '?action=admin;area=featuresettings;sa=sig;apply;%2$s=%1$s">Diesen Vorgang nun durchführen</a>';
$txt['signature_settings_applied'] = 'Die aktualisierten Regeln wurden auf die vorhandenen Signaturen angewandt.';
$txt['signature_enable'] = 'Signaturen aktivieren';
$txt['signature_max_length'] = 'Maximal erlaubte Zeichen';
$txt['signature_max_lines'] = 'Maximal erlaubte Zeilen';
$txt['signature_max_images'] = 'Maximale Anzahl an Grafiken';
$txt['signature_max_images_note'] = '(0 für keine Begrenzung, Smileys sind ausgenommen)';
$txt['signature_allow_smileys'] = 'Smileys in Signaturen erlauben';
$txt['signature_max_smileys'] = 'Maximal erlaubte Smileys';
$txt['signature_max_image_width'] = 'Maximale Breite von Signaturgrafiken (Pixel)';
$txt['signature_max_image_height'] = 'Maximale Höhe von Signaturgrafiken (Pixel)';
$txt['signature_max_font_size'] = 'Maximal erlaubte Schriftgröße in Signaturen (Pixel)';
$txt['signature_bbc'] = 'Aktivierte BBCode-Tags';

$txt['groups_pm_send'] = 'Benutzergruppen, die private Nachrichten versenden dürfen';
$txt['pm_posts_verification'] = 'Anzahl der Beiträge, unterhalb derer Benutzer vor dem Versenden von privaten Nachrichten verifiziert werden müssen';
$txt['pm_posts_verification_note'] = '(0 für keine Beschränkung, Administratoren sind ausgenommen)';
$txt['pm_posts_per_hour'] = 'Anzahl an privaten Nachrichten, die ein Benutzer pro Stunde versenden darf';
$txt['pm_posts_per_hour_note'] = '(0 für keine Beschränkung, Moderatoren sind ausgenommen)';

$txt['custom_profile_title'] = 'Eigene Profilfelder';
$txt['custom_profile_desc'] = 'Auf dieser Seite können Sie eigene Profilfelder erstellen, die die individuellen Bedürfnisse Ihres Forums erfüllen';
$txt['custom_profile_active'] = 'Aktiv';
$txt['custom_profile_order'] = 'Feldreihenfolge';
$txt['custom_profile_fieldname'] = 'Feldname';
$txt['custom_profile_fieldtype'] = 'Feldtyp';
$txt['custom_profile_make_new'] = 'Neues Feld';
$txt['custom_profile_none'] = 'Sie haben noch keine eigenen Profilfelder erstellt!';
$txt['custom_profile_icon'] = 'Symbol';
$txt['custom_profile_sort'] = 'Um die Reihenfolge der eigenen Felder zu ändern, ziehen Sie sie einfach an die gewünschte Stelle.';

$txt['custom_profile_type_text'] = 'Text';
$txt['custom_profile_type_textarea'] = 'Großer Text';
$txt['custom_profile_type_select'] = 'Auswahlfeld';
$txt['custom_profile_type_radio'] = 'Optionsfeld';
$txt['custom_profile_type_check'] = 'Kontrollkästchen';
$txt['custom_profile_reordered'] = 'Profilfelder erfolgreich umsortiert';

$txt['custom_add_title'] = 'Profilfeld hinzufügen';
$txt['custom_edit_title'] = 'Profilfeld ändern';
$txt['custom_edit_general'] = 'Anzeigeeinstellungen';
$txt['custom_edit_input'] = 'Eingabeeinstellungen';
$txt['custom_edit_advanced'] = 'Erweiterte Einstellungen';
$txt['custom_edit_name'] = 'Name';
$txt['custom_edit_desc'] = 'Beschreibung';
$txt['custom_edit_profile'] = 'Profilbereich';
$txt['custom_edit_profile_desc'] = 'Bereich des Profils, dem dies angehört.';
$txt['custom_edit_profile_none'] = 'Keines';
$txt['custom_edit_registration'] = 'Bei Registrierung zeigen';
$txt['custom_edit_registration_disable'] = 'Nein';
$txt['custom_edit_registration_allow'] = 'Ja';
$txt['custom_edit_registration_require'] = 'Ja, und zum Pflichtfeld machen';
$txt['custom_edit_display'] = 'In Themenübersicht zeigen';
$txt['custom_edit_memberlist'] = 'In Benutzerliste zeigen';
$txt['custom_edit_picktype'] = 'Feldtyp';

$txt['custom_edit_max_length'] = 'Maximale Länge';
$txt['custom_edit_max_length_desc'] = '(0 ür keine Begrenzung)';
$txt['custom_edit_dimension'] = 'Maße';
$txt['custom_edit_dimension_row'] = 'Zeilen';
$txt['custom_edit_dimension_col'] = 'Spalten';
$txt['custom_edit_bbc'] = 'BBCode erlauben';
$txt['custom_edit_options'] = 'Optionen';
$txt['custom_edit_options_desc'] = 'Lassen Sie das Feld zum Entfernen leer. Das Optionsfeld wählt die Standardoption aus.'; // ? :)
$txt['custom_edit_options_more'] = 'Mehr';
$txt['custom_edit_default'] = 'Standardzustand';
$txt['custom_edit_active'] = 'Aktiv';
$txt['custom_edit_active_desc'] = 'Wenn nicht ausgewählt, wird dieses Feld niemandem angezeigt.';
$txt['custom_edit_privacy'] = 'Privatsphäre';
$txt['custom_edit_privacy_desc'] = 'Wer dieses Feld sehen und ändern kann.';
$txt['custom_edit_privacy_all'] = 'Benutzer können dieses Feld sehen; der Besitzer kann es ändern';
$txt['custom_edit_privacy_see'] = 'Benutzer können dieses Feld sehen; nur Administratoren können es ändern';
$txt['custom_edit_privacy_owner'] = 'Benutzer können dieses Feld nicht sehen; der Besitzer und Administratoren können es ändern';
$txt['custom_edit_privacy_none'] = 'Dieses Feld ist nur für Administratoren sichtbar';
$txt['custom_edit_can_search'] = 'Durchsuchbar';
$txt['custom_edit_can_search_desc'] = 'Kann dieses Feld in der Benutzerliste gesucht werden?';
$txt['custom_edit_mask'] = 'Eingabemaske';
$txt['custom_edit_mask_desc'] = 'Für Textfelder kann eine Eingabemaske festgelegt werden, um den Inhalt zu verifizieren.';
$txt['custom_edit_mask_email'] = 'Gültige E-Mail-Adresse';
$txt['custom_edit_mask_number'] = 'Nummerisch';
$txt['custom_edit_mask_nohtml'] = 'Kein HTML';
$txt['custom_edit_mask_regex'] = 'Regulärer Ausdruck (erweitert)';
$txt['custom_edit_enclose'] = 'Benutzereingabe in Text verpacken';
$txt['custom_edit_enclose_desc'] = 'Wir empfehlen <strong>wärmstens</strong>, eine Eingabemaske zu verwenden, um den eingegebenen Text zu verifizieren.';

$txt['custom_edit_placement'] = 'Wählen Sie die Platzierung aus'; // translator note: should the imperative form be used? -> consistency
$txt['custom_edit_placement_standard'] = 'Standard (mit Titel)';
$txt['custom_edit_placement_withicons'] = 'Mit Symbolen';
$txt['custom_edit_placement_abovesignature'] = 'Über Signatur';
$txt['custom_profile_placement'] = 'Platzierung';
$txt['custom_profile_placement_standard'] = 'Standard';
$txt['custom_profile_placement_withicons'] = 'Mit Symbolen';
$txt['custom_profile_placement_abovesignature'] = 'Über Signatur';

// Use numeric entities in the string below!
$txt['custom_edit_delete_sure'] = 'Sind Sie sich sicher, dass Sie dieses Feld löschen möchten? Alle entsprechenden Benutzerdaten gehen verloren!';

$txt['standard_profile_title'] = 'Standardprofilfelder';
$txt['standard_profile_field'] = 'Feld';

$txt['core_settings_welcome_msg'] = 'Willkommen in Ihrem neuen Forum';
$txt['core_settings_welcome_msg_desc'] = 'Wir schlagen vor, dass Sie zu Beginn auswählen, welche von ElkArtes Kernfunktionen Sie aktivieren möchten. Es wird empfohlen, dass Sie nur diejenigen Funktionen auswählen, die Sie wirklich benötigen.';
$txt['core_settings_item_cd'] = 'Kalender';
$txt['core_settings_item_cd_desc'] = 'Die Aktivierung dieser Funktion wird eine Auswahl an Optionen eröffnen, um es Ihren Benutzern zu erlauben, den Kalender anzusehen, Ereignisse hinzuzufügen und zu überarbeiten, die Geburtstage von Benutzern einzusehen und vieles mehr.';
$txt['core_settings_item_dr'] = 'Entwürfe';
$txt['core_settings_item_dr_desc'] = 'Die Aktivierung dieser Funktion wird es Benutzern erlauben, Entwürfe ihrer Beiträge zu speichern, so dass sie später zurückkehren und sie erst dann veröffentlichen können.';
$txt['core_settings_item_cp'] = 'Erweiterte Profilfelder';
$txt['core_settings_item_cp_desc'] = 'Dies ermöglicht es Ihnen, Standardprofilfelder zu verstecken, Profilfelder zur Registrierung hinzuzufügen und neue Profilfelder für Ihr Forum zu erstellen.';
$txt['core_settings_item_ih'] = 'Einschubmethodenverwaltung';
$txt['core_settings_item_ih_desc'] = 'Diese Funktion erlaubt es Ihnen, jegliche von Erweiterungen hinzugefügte Einschubmethoden zu aktivieren oder zu deaktivieren. Die Änderung von Einschubmethoden kann dazu führen, dass Ihr Forum nicht mehr richtig funktioniert, daher sollten Sie diese Funktion nur verwenden, wenn Sie wissen, was Sie tun.';
$txt['core_settings_item_k'] = 'Karma';
$txt['core_settings_item_k_desc'] = 'Karma ist eine Funktion, die die Beliebtheit eines Mitglieds anzeigt. Wenn aktiviert, können Mitglieder anderen Mitgliedern \'applaudieren\' oder sie \'zerschmettern\', anhand dessen deren Beliebtheit berechnet wird.';
$txt['core_settings_item_pe'] = 'E-Mail-Beiträge';
$txt['core_settings_item_pe_desc'] = 'Dies wird es Ihren Benutzern erlauben, auf per E-Mail erhaltene Benachrichtigungen und private Nachrichten zu antworten, so dass die Antworten direkt als Beitrag oder private Nachricht gespeichert werden.  Hierdurch wird ein vertrautes Mailinglistengefühl aufgebaut.  Die Verwendung dieser Funktion setzt zusätzliche Einrichtungsschritte bezüglich Ihres Hostinganbieters voraus.';
$txt['core_settings_item_l'] = 'Gefällt mir';
$txt['core_settings_item_l_desc'] = '\'Gefällt mir\' ist eine Funktion, die es Mitgliedern ermöglicht, einen Beitrag zu \'mögen\', um ihre Zustimmung und die Beliebtheit des Beitragsinhalts zu zeigen.';
$txt['core_settings_item_ml'] = 'Moderations-, Administrations- und Benutzerprotokolle';
$txt['core_settings_item_ml_desc'] = 'Aktivieren Sie die Moderations- und Administrationsprotokolle, um alle wichtigen Aktionen in Ihrem Forum zu verfolgen. Dies erlaubt es Forumsmoderatoren auch, einen Verlauf relevanter Änderungen eines Benutzers an seinem Profil anzuzeigen.';
$txt['core_settings_item_pm'] = 'Beitragsmoderation';
$txt['core_settings_item_pm_desc'] = 'Beitragsmoderation ermöglicht es Ihnen, Benutzergruppen und Foren auszuwählen, innerhalb derer Beiträge freigeschaltet werden müssen, bevor sie öffentlich sichtbar sind. Nach der Aktivierung dieser Funktion sollten Sie den Befugnisbereich aufsuchen, um die relevanten Befugnisse einzustellen.';
$txt['core_settings_item_ps'] = 'Bezahlte Abonnements';
$txt['core_settings_item_ps_desc'] = 'Bezahlte Abonnements erlauben es Benutzern, für Abonnements zu bezahlen, wodurch sie weiteren Benutzergruppen beitreten und andere Zugriffsrechte erhalten können.';
$txt['core_settings_item_rg'] = 'Berichtserzeugung';
$txt['core_settings_item_rg_desc'] = 'Diese Administrationsfunktion erlaubt die Erzeugung von Berichten (die ausgegeben werden können), mittels derer Ihre momentane Forumskonfiguration übersichtlich dargestellt werden kann - besonders nützlich für große Foren.';
$txt['core_settings_item_sp'] = 'Suchmaschinenverfolgung';
$txt['core_settings_item_sp_desc'] = 'Die Aktivierung dieser Funktion erlaubt es Administratoren, Suchmaschinen bei der Indizierung Ihres Forums zu beobachten.';
$txt['core_settings_item_w'] = 'Verwarnungssystem';
$txt['core_settings_item_w_desc'] = 'Dieses System ermöglicht es Administratoren und Moderatoren, Benutzer zu verwarnen, und kann Benutzerrechte automatisch entfernen, sobald deren Verwarnstufe sich erhöht. Um die Vorteile dieses Systems voll ausschöpfen zu können, sollte auch die &quot;Beitragsmoderation&quot; aktiviert werden.';
$txt['core_settings_switch_on'] = 'Zum Aktivieren klicken';
$txt['core_settings_switch_off'] = 'Zum Deaktivieren klicken';
$txt['core_settings_enabled'] = 'Aktiviert';
$txt['core_settings_disabled'] = 'Deaktiviert';

$txt['languages_lang_name'] = 'Name der Sprache';
$txt['languages_locale'] = 'Lokalisierung'; // ? :)
$txt['languages_default'] = 'Standard';
$txt['languages_users'] = 'Benutzer';
$txt['language_settings_writable'] = 'Warnung: Settings.php ist nicht beschreibbar, deshalb kann die Standardspracheinstellung nicht gespeichert werden.';
$txt['edit_languages'] = 'Sprachen ändern';
$txt['lang_file_not_writable'] = '<strong>Warnung:</strong> Die primäre Sprachdatei (%1$s) ist nicht beschreibbar. Sie müssen sie beschreibbar machen, bevor Sie Änderungen vornehmen können.';
$txt['lang_entries_not_writable'] = '<strong>Warnung:</strong> Die Sprachdatei, die Sie ändern möchten (%1$s), ist nicht beschreibbar. Sie müssen sie beschreibbar machen, bevor Sie Änderungen vornehmen können.';
$txt['languages_ltr'] = 'Von rechts nach links';

$txt['add_language'] = 'Sprache hinzufügen';
$txt['add_language_elk'] = 'Aus dem ElkArte-Sprachdepot herunterladen';
$txt['add_language_elk_browse'] = 'Geben Sie den Namen der Sprache ein, nach der Sie suchen möchten, oder lassen Sie das Feld leer, um nach allen Sprachen zu suchen.';
$txt['add_language_elk_install'] = 'Installieren';
$txt['add_language_elk_found'] = 'Folgende Sprachen wurden gefunden. Klicken Sie neben der Sprache, die Sie installieren möchten, auf &quot;Installieren&quot;, Sie werden dann zur Installation zur Paketverwaltung weitergeleitet.';
$txt['add_language_error_no_response'] = 'Der ElkArte-Server antwortet nicht. Bitte versuchen Sie es später erneut.'; // translator note: well, the "site" might do. ;-)
$txt['add_language_error_no_files'] = 'Es konnten keine Dateien gefunden werden.';
$txt['add_language_elk_desc'] = 'Beschreibung';
$txt['add_language_elk_utf8'] = 'UTF-8';
$txt['add_language_elk_version'] = 'Version';

$txt['edit_language_entries_primary'] = 'Unten finden Sie die primären Spracheinstellungen für dieses Sprachpaket.';
$txt['edit_language_entries'] = 'Spracheinträge ändern';
$txt['edit_language_entries_file'] = 'Wählen Sie die zu ändernden Einträge aus';
$txt['languages_dictionary'] = 'Wörterbuch';
$txt['languages_spelling'] = 'Rechtschreibung';
$txt['languages_for_pspell'] = 'Dies sind Einstellungen für <a href="http://www.php.net/function.pspell-new" target="_blank" class="new_win">pSpell</a> - sofern installiert';
$txt['languages_rtl'] = 'Modus &quot;Von links nach rechts&quot; aktivieren';

$txt['lang_file_desc_index'] = 'Allgemeine Zeichenketten';
$txt['lang_file_desc_EmailTemplates'] = 'E-Mail-Vorlagen';

$txt['languages_download'] = 'Sprachpaket herunterladen';
$txt['languages_download_note'] = 'Diese Seite führt alle Dateien, die im Sprachpaket enthalten sind, sowie ein paar nützliche Informationen zu jedr von ihnen auf. Alle Dateien, deren Kontrollkästchen aktiviert ist, werden kopiert.';
$txt['languages_download_info'] = '<strong>Beachten Sie:</strong>
	<ul class="normallist">
		<li>Haben Dateien den Status &quot;Nicht beschreibbar&quot;, so bedeutet dies, dass das System die jeweilige Datei momentan nicht in das Verzeichnis kopieren kann und Sie das Ziel entweder mithilfe eines FTP-Clients oder mittels Ausfüllens der Zugangsdaten unten auf dieser Seite beschreibbar machen müssen.</li>
		<li>Die Versionsinformationen für eine Datei zeigen an, für welche Version des Forums sie zuletzt aktualisiert wurde. Wenn sie grün ist, ist dies eine neuere als Ihre momentan installierte Version, bernsteingelb bedeutet, dass die Versionsnummern identisch sind, und rot bedeutet, dass Sie eine neuere als die enthaltene Version installiert haben.</li>
		<li>Sofern eine Datei in Ihrem Forum bereits vorhanden ist, enthält die Spalte &quot;Bereits vorhanden&quot; einen von zwei Werten: &quot;Identisch&quot; bedeutet, dass die Datei mit dem gleichen Inhalt bereits vorhanden ist und nicht überschrieben werden muss, &quot;Unterschiedlich&quot; bedeutet, dass die Inhalte sich unterscheiden und Überschreiben möglicherweise die beste Lösung ist.</li>
	</ul>';

$txt['languages_download_main_files'] = 'Hauptdateien';
$txt['languages_download_theme_files'] = 'Designbezogene Dateien';
$txt['languages_download_filename'] = 'Dateiname';
$txt['languages_download_dest'] = 'Ziel';
$txt['languages_download_writable'] = 'Beschreibbar';
$txt['languages_download_version'] = 'Version';
$txt['languages_download_older'] = 'Sie haben eine neuere Version dieser Datei installiert, Überschreiben wird nicht empfohlen.';
$txt['languages_download_exists'] = 'Bereits vorhanden';
$txt['languages_download_exists_same'] = 'Identisch';
$txt['languages_download_exists_different'] = 'Unterschiedlich';
$txt['languages_download_copy'] = 'Kopieren';
$txt['languages_download_not_chmod'] = 'Sie können die Installation nicht fortsetzen, bevor alle zum Kopieren ausgewählten Dateien beschreibbar sind.';
$txt['languages_download_illegal_paths'] = 'Das Paket enthält ungültige Pfade - bitte kontaktieren Sie ElkArte';
$txt['languages_download_complete'] = 'Installation abgeschlossen';
$txt['languages_download_complete_desc'] = 'Sprachpaket erfolgreich installiert. Bitte <a href="%1$s">klicken Sie hier, um zur Sprachenseite zurückzukehren</a>';
$txt['languages_delete_confirm'] = 'Sind Sie sich sicher, dass Sie diese Sprache löschen möchten?';

$txt['setting_frame_security'] = 'Framesicherheitsoptionen';
$txt['setting_frame_security_SAMEORIGIN'] = 'Gleichen Ursprung erlauben';
$txt['setting_frame_security_DENY'] = 'Alle Frames verbieten';
$txt['setting_frame_security_DISABLE'] = 'Deaktiviert';
