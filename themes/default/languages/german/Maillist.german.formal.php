<?php
// Version: 1.0; Maillist

// Email posting errors
$txt['error_locked'] = 'Dieses Thema wurde gesperrt, es können keine weiteren Antworten geschrieben werden';
$txt['error_locked_short'] = 'Thema gesperrt';
$txt['error_cant_start'] = 'Nicht befugt, ein neues Thema im angegebenen Forum zu eröffnen';
$txt['error_cant_start_short'] = 'Kann neues Thema nicht eröffnen';
$txt['error_cant_reply'] = 'Nicht befugt zu antworten';
$txt['error_cant_reply_short'] = 'Thema außer Reichweite';
$txt['error_topic_gone'] = 'Das Thema konnte nicht gefunden werden - es könnte gelöscht oder zusammengeführt worden sein.';
$txt['error_topic_gone_short'] = 'Gelöschtes Thema';
$txt['error_not_find_member'] = 'Ihre E-Mail-Adresse konnte in der Mitgliederdatenbank nicht gefunden werden, nur Mitglieder dürfen Beiträge verfassen.';
$txt['error_not_find_member_short'] = 'E-Mail-ID nicht in Datenbank';
$txt['error_key_sender_match'] = 'Der E-Mail-Schlüssel, obwohl gültig, wurde nicht an die E-Mail-Adresse gesendet, die mit dem Schlüssel antwortete.  Sie müssen von der gleichen Adresse aus antworten, die die Nachricht erhalten hat';
$txt['error_key_sender_match_short'] = 'Schlüssel stimmen nicht überein';
$txt['error_not_find_entry'] = 'Es scheint, als hätten Sie diese E-Mail bereits beantwortet.  Wenn Sie Ihren Beitrag ändern möchten, benutzen Sie bitte die Weboberfläche, wenn Sie eine weitere Antwort auf das Thema verfassen möchten, antworten Sie bitte auf die neueste Benachrichtigung';
$txt['error_not_find_entry_short'] = 'Schlüssel abgelaufen';
$txt['error_pm_not_found'] = 'Die private Nachricht, auf die Sie antworteten, konnte nicht gefunden werden.';
$txt['error_pm_not_found_short'] = 'PN fehlt';
$txt['error_pm_not_allowed'] = 'Sie haben nicht die nötigen Befugnisse, um private Nachrichten zu versenden!';
$txt['error_pm_not_allowed_short'] = 'Nicht autorisiert für PN';
$txt['error_no_message'] = 'Wir konnten in der E-Mail keine Nachricht finden, Sie müssen eine angeben, um einen Beitrag zu schreiben';
$txt['error_no_message_short'] = 'Leere Nachricht';
$txt['error_no_subject'] = 'Sie müssen einen Betreff angeben, um ein neues Thema zu eröffnen, es wurde keines gefunden';
$txt['error_no_subject_short'] = 'Kein Betreff';
$txt['error_board_gone'] = 'Das Forum, in das Sie zu schreiben versuchten, war entweder ungültig oder für Sie unerreichbar';
$txt['error_board_gone_short'] = 'Ungültiges oder geschütztes Forum';
$txt['error_missing_key'] = 'Konnte den Schlüssel in der Antwort nicht finden.  Damit E-Mails akzeptiert werden, müssen sie von derselben E-Mail-Adresse, die Sie für Benachrichtigungen verwenden, als Antwort auf eine gültige Benachrichtigungs-E-Mail gesendet werden.';
$txt['error_missing_key_short'] = 'Schlüssel fehlt';
$txt['error_found_spam'] = 'Warnung: Ihr Beitrag wurde von Ihrem Spamfilter als möglicher Spam erkannt und nicht veröffentlicht.';
$txt['error_found_spam_short'] = 'Möglicher Spam';
$txt['error_pm_not_find_entry'] = 'Es scheint, als hätten Sie diese private Nachricht bereits beantwortet.  Wenn Sie eine weitere Antwort verfassen möchten, verwenden Sie bitte die Weboberfläche oder warten Sie auf eine weitere Antwort.';
$txt['error_pm_not_find_entry_short'] = 'PN-Schlüssel abgelaufen';
$txt['error_not_find_board'] = 'Versuch unternommen, ein neues Thema in einem nicht vorhandenen Forum zu eröffnen; potenzieller Hackversuch';
$txt['error_not_find_board_short'] = 'Kein solches Forum';
$txt['error_no_pm_attach'] = '[PN-Anhänge werden nicht unterstützt]';
$txt['error_no_attach'] = '[E-Mail-Anhänge wurden deaktiviert]';
$txt['error_in_maintenance_mode'] = 'E-Mail wurde empfangen, während das Forum sich im Wartungsmodus befand, und konnte daher nicht verarbeitet werden';
$txt['error_in_maintenance_mode_short'] = 'Im Wartungsmodus';
$txt['error_email_notenabled_short'] = 'Nicht aktiviert';
$txt['error_email_notenabled'] = 'Die E-Mail-Beitragsfunktionen wurden nicht aktiviert, die E-Mail konnte nicht verarbeitet werden';
$txt['error_permission'] = 'Der Autor hat nicht die nötigen Befugnisse, um in diesem Forum Beiträge per E-Mail zu verfassen';

// Maillist page items
$txt['ml_admin_configuration'] = 'Mailbeitragskonfiguration';
$txt['ml_configuration_desc'] = 'Dieser Bereich erlaubt es Ihnen, einige Einstellungen rund um das Veröffentlichen von Beiträgen per E-Mail vorzunehmen';
$txt['ml_emailerror_none'] = 'Es gibt keine fehlgeschlagenen Einträge, die Moderation benötigen';
$txt['ml_emailerror'] = 'Fehlgeschlagene E-Mails';;
$txt['ml_emailsettings'] = 'Einstellungen';

// Settings tab
$txt['maillist_enabled'] = 'Mailbeitragsfunktionen aktivieren (Hauptschalter)';
$txt['pbe_post_enabled'] = 'Veröffentlichen neuer Beiträge per E-Mail erlauben';
$txt['pbe_pm_enabled'] = 'Antworten auf private Nachrichten per E-Mail erlauben';
$txt['pbe_no_mod_notices'] = 'Moderationshinweise abschalten';
$txt['pbe_no_mod_notices_desc'] = 'Keine Benachrichtigungen über verschobene, gesperrte, gelöschte, zusammengeführte usw. Themen versenden.  Diese belasten Ihre Mailkapazitäten ohne wirklichen Mehrwert';
$txt['saved'] = 'Informationen gespeichert';

// General Sending Settings
$txt['maillist_outbound'] = 'Allgemeine Versandeinstellungen';
$txt['maillist_outbound_desc'] = 'Verwenden Sie diese Einstellungen, um zu ändern, wie ausgehende E-Mails einem Benutzer angezeigt werden sollen und wohin eine Antwort gesendet wird.  ';
$txt['maillist_group_mode'] = 'Gruppenmailmodus aktivieren';
$txt['maillist_digest_enabled'] = 'Erweiterte tägliche Zusammenfassung aktivieren (stellt Themenausschnitte in der Zusammenfassung bereit)';
$txt['maillist_sitename'] = 'Name der Site zur Verwendung für die E-Mail (nicht die E-Mail-Adresse)';
$txt['maillist_sitename_desc'] = 'Dies ist der Name des Forums für die E-Mail; er sollte den Benutzern vertraut sein, da er in verschiedenen Bereichen der E-Mail wie dem Betreff - [Name] Betreff - vorkommen wird.';
$txt['maillist_sitename_post'] = 'z.B. &lt;<strong>Name</strong>&gt;emailpost@ihredomain.de';
$txt['maillist_sitename_address'] = 'Antwort- und Absenderadresse';
$txt['maillist_sitename_address_desc'] = 'Die E-Mail-Adresse, an die Antworten gesendet werden sollen. Falls leer, wird die Benachrichtigungs- (falls gesetzt) oder die E-Mail-Adresse des Webmasters verwendet.';
$txt['maillist_sitename_regards'] = 'E-Mail-"Signatur"';
$txt['maillist_sitename_regards_desc'] = 'Was am Ende ausgehender E-Mails stehen soll, so etwas wie "Grüße, Ihr Forumsteam"';
$txt['maillist_sitename_address_post'] = 'z. B. emailpost@ihredomain.de';
$txt['maillist_sitename_help'] = 'Hilfe-E-Mail-Adresse';
$txt['maillist_sitename_help_desc'] = 'Verwendet für die "Listenbesitzer"-Kopfzeile, um zu verhindern, dass die ausgehenden E-Mails als Spam gekennzeichnet werden.';
$txt['maillist_sitename_help_post'] = 'z.B. hilfe@ihredomain.de';
$txt['maillist_mail_from'] = 'Benachrichtigungs-E-Mail-Adresse';
$txt['maillist_mail_from_desc'] = 'Die E-Mail-Adresse, die für Passworterinnerungen, Benachrichtugngen usw. verwendet wird.  Falls leer, wird die Webmaster-E-Mail-Adresse verwendet (dies ist das Standardverhalten)';
$txt['maillist_mail_from_post'] = 'z.B. keineantwort@ihredomain.de';

// Imap settings
$txt['maillist_imap'] = 'IMAP-Einstellungen';
$txt['maillist_imap_host'] = 'Postfachservername';
$txt['maillist_imap_host_desc'] = 'Geben Sie einen Servernamen und (optional) einen Port ein. Beispiel: imap.gmail.com:993';
$txt['maillist_imap_mailbox'] = 'Postfachname';
$txt['maillist_imap_mailbox_desc'] = 'Geben Sie den Namen eines Postfachs auf dem Server ein, zum Beispiel: INBOX';
$txt['maillist_imap_uid'] = 'Postfachbenutzername';
$txt['maillist_imap_uid_desc'] = 'Benutzername zum Anmelden am Postfach.';
$txt['maillist_imap_pass'] = 'Postfachpasswort';
$txt['maillist_imap_pass_desc'] = 'Passwort zum Anmelden am Postfach.';
$txt['maillist_imap_connection'] = 'Postfachverbindung';
$txt['maillist_imap_connection_desc'] = 'Art der zu verwendenden Verbindung, IMAP oder POP3 (im unverschlüsselten, TLS- oder SSL-Modus).';
$txt['maillist_imap_unsecure'] = 'IMAP';
$txt['maillist_pop3_unsecure'] = 'POP3';
$txt['maillist_imap_tls'] = 'IMAP/TLS';
$txt['maillist_imap_ssl'] = 'IMAP/SSL';
$txt['maillist_pop3_tls'] = 'POP3/TLS';
$txt['maillist_pop3_ssl'] = 'POP3/SSL';
$txt['maillist_imap_delete'] = 'Nachrichten löschen';
$txt['maillist_imap_delete_desc'] = 'Versuchen, E-Mails zu entfernen, die empfangen und verarbeitet wurden.';
$txt['maillist_imap_reason'] = 'Folgendes sollte LEER gelassen werden, wenn Sie vorhaben, Nachrichten in das Forum weiterzuleiten (empfohlen)';
$txt['maillist_imap_missing'] = 'IMAP-Funktionen sind auf Ihrem System nicht installiert, es sind keine Einstellungen verfügbar';
$txt['maillist_imap_cron'] = 'Cron simulieren (geplante Aufgabe)';
$txt['maillist_imap_cron_desc'] = 'Wenn Sie auf Ihrem System keinen Cronjob ausführen können, können Sie als letzte Rettung diese Funktion aktivieren, um dies stattdessen als eine geplante Aufgabe auszuführen';
$txt['scheduled_task_desc_pbeIMAP'] = 'Führt das Mailbeiträge-IMAP-Postfachprogramm aus, um neue E-Mails aus dem gewählten Postfach abzurufen'; // ? :)

// General Receiving Settings
$txt['maillist_inbound'] = 'Allgemeine Empfangseinstellungen';
$txt['maillist_inbound_desc'] = 'Verwenden Sie diese Einstellungen, um die Aktionen festzulegen, die das System durchführen wird, wenn eine Neues-Thema-E-Mail erhalten wurde.  Dies betrifft keine Antworten auf unsere Benachrichtigungen';
$txt['maillist_newtopic_change'] = 'Das Eröffnen eines neuen Themas mittels Änderung des Antwortbetreffs erlauben';
$txt['maillist_newtopic_needsapproval'] = 'Freischaltung neuer Themen anfordern';
$txt['maillist_newtopic_needsapproval_desc'] = 'Setzt alle per E-Mail eröffneten Themen auf Vorabmoderation, um nicht regelkonforme Nutzung zu verhindern';
$txt['recommended'] = 'Dies wird empfohlen';
$txt['receiving_address'] = 'Erhaltende E-Mail-Adressen';
$txt['receiving_board'] = 'Forum, in das geschrieben werden soll';
$txt['reply_add_more'] = 'Eine weitere Adresse hinzufügen';
$txt['receiving_address_desc'] = 'Geben Sie eine Liste an E-Mail-Adressen gefolgt von dem Forum, in dem erhaltene E-Mails veröffentlicht werden sollen, ein.  Dies wird benötigt, um in einem bestimmten Forum ein NEUES Thema zu eröffnen, Mitglieder müssen an diese E-Mail-Adresse eine E-Mail senden und sie wird automatisch in dem jeweiligen Forum veröffentlicht.  Um ein vorhandenes Element zu entfernen, leeren Sie einfach das E-Mail-Adressfeld und speichern Sie';
$txt['email_not_valid'] = 'Die E-Mail-Adresse (%s) ist ungültig';
$txt['board_not_valid'] = 'Sie haben eine ungültige Forums-ID (%d) angegeben';

// Other settings
$txt['misc'] = 'Weitere Einstellungen';
$txt['maillist_allow_attachments'] = 'Veröffentlichung von E-Mail-Dateianhängen erlauben (funktioniert nicht in privaten Nachrichten)';
$txt['maillist_key_active'] = 'Tage, für die Schlüssel in der Datenbank vorgehalten werden sollen';
$txt['maillist_key_active_desc'] = 'das heißt, wie lange nach dem Versand einer Benachrichtigung eine Antwort möglich sein soll';
$txt['maillist_sig_keys'] = 'Wörter, die den Start jemandes Signatur kennzeichnen';
$txt['maillist_sig_keys_desc'] = 'Trennen Sie Wörter mittels des |-Zeichens, zum Beispiel "Grüße|Danke". Zeilen, die mit diesen Wörtern beginnen, werden als Beginn einer Signaturzeile behandelt';
$txt['maillist_leftover_remove'] = 'Zeilen, die von E-Mails übrig bleiben';
$txt['maillist_leftover_remove_desc'] = 'Trennen Sie Wörter mittels des |-Zeichens, zum Beispiel "To: |Re: |AW: |Subject: |From: ". Das meiste wird vom Parser entfernt, einiges jedoch in Anführungszeichen zurückbleiben.  Fügen Sie hier nichts hinzu, wenn Sie nicht wissen, was Sie tun.'; // translator note: mail headers are always in english IIRC
$txt['maillist_short_line'] = 'Kurze Zeilenlänge, verwendet, um Zeilenumbrüche zu entfernen';
$txt['maillist_short_line_desc'] = 'Eine Änderung kann zu unüblichen Ergebnissen führen, ändern Sie dies also achtsam';

// Failed log actions
$txt['approved'] = 'E-Mail wurde genehmigt und veröffentlicht';
$txt['error_approved'] = 'Beim Versuch, diese E-Mail zu genehmigen, ist ein Fehler aufgetreten';
$txt['id'] = '#';
$txt['error'] = 'Fehler';
$txt['key'] = 'Schlüssel';
$txt['message_id'] = 'Nachricht';
$txt['message_type'] = 'Typ';
$txt['message_action'] = 'Aktionen';
$txt['emailerror_title'] = 'Protokoll fehlgeschlagener E-Mails';
$txt['show_notice'] = 'E-Mail-Details';
$txt['private'] = 'Privat';
$txt['show_notice_text'] = 'Beitragstext';
$txt['noaccess'] = 'Private Nachrichten können nicht eingesehen werden';
$txt['badid'] = 'Ungültige oder fehlende E-Mail-Kennung';
$txt['delete_warning'] = 'Sind Sie sich sicher, dass Sie diesen Eintrag löschen möchten?';
$txt['filter_delete_warning'] = 'Sind Sie sich sicher, dass Sie diesen Filter entfernen möchten?';
$txt['parser_delete_warning'] = 'Sind Sie sich sicher, dass Sie diesen Parser entfernen möchten?';
$txt['bounce'] = 'Abweisen';
$txt['heading'] = 'Dies ist die Liste fehlgeschlagener Beitrags-E-Mails, von hier aus können Sie auswählen, sie anzusehen, zu genehmigen (falls möglich), zu löschen oder an den Absender zurückzusenden';
$txt['cant_approve'] = 'Der Fehler erlaubt keine Genehmigung (kann nicht automatisch repariert werden)';
$txt['email_attachments'] = '[Es gibt %d E-Mail-Anhänge in dieser Nachricht]';
$txt['email_failure'] = 'Ursache für das Fehlverhalten';

// Filters
$txt['filters'] = 'E-Mail-Filter';
$txt['add_filter'] = 'Filter hinzufügen';
$txt['sort_filter'] = 'Filter sortieren';
$txt['edit_filter'] = 'Vorhandenen Filter ändern';
$txt['no_filters'] = 'Sie haben keine Filter definiert';
$txt['error_no_filter'] = 'Konnte angegebenen Filter nicht finden/laden';
$txt['regex_invalid'] = 'Der reguläre Ausdruck ist ungültig';
$txt['filter_to'] = 'Ersetzungstext';
$txt['filter_to_desc'] = 'Den gefundenen Text hierdurch ersetzen';
$txt['filter_from'] = 'Suchtext';
$txt['filter_from_desc'] = 'Geben Sie den Text ein, nach dem Sie suchen möchten';
$txt['filter_type'] = 'Typ';
$txt['filter_type_desc'] = 'Standard wird den genauen Suchbegriff finden und ihn durch den Text im Ersetzen-Feld ersetzen.  Ein regulärer Ausdruck ist die Platzhalteroption des Standards, der Ausdruck muss im PCRE-Format vorliegen.';
$txt['filter_name'] = 'Name';
$txt['filter_name_desc'] = 'Geben Sie optional einen Namen ein, um sich später daran zu erinnern, was dieser Filter tut';
$txt['filters_title'] = 'In diesem Bereich können Sie E-Mail-Filter hinzufügen, ändern oder entfernen. Filter suchen in einer Antwort nach bestimmtem Text und ersetzen diesen dann durch einen Text Ihrer Wahl, normalerweise nichts.';
$txt['filter_invalid'] = 'Die Definition ist ungültig und konnte nicht gespeichert werden';
$txt['error_no_id_filter'] = 'Die Filterkennung ist ungültig';
$txt['saved_filter'] = 'Der Filter wurde erfolgreich gespeichert';
$txt['filter_sort_description'] = 'Filter werden in der angezeigten Reihenfolge ausgeführt, zunächst Regex-Gruppierung, dann die Standardgruppierung, um dies zu ändern, ziehen Sie ein Element an eine andere Stelle in der Liste (Sie können jedoch einen Standardfilter nicht dazu zwingen, vor einem Regexfilter angewandt zu werden).';

// Parsers
$txt['saved_parser'] = 'Der Parser wurde erfolgreich gespeichert';
$txt['parser_reordered'] = 'Die Felder wurden erfolgreich umsortiert';
$txt['error_no_id_parser'] = 'Die Parserkennung ist ungültig';
$txt['add_parser'] = 'Parser hinzufügen';
$txt['sort_parser'] = 'Parser sortieren';
$txt['edit_parser'] = 'Vorhandenen Parser ändern';
$txt['parsers'] = 'E-Mail-Parser';
$txt['parser_from'] = 'Suchbegriff in der Original-E-Mail';
$txt['parser_from_desc'] = 'Geben Sie den Anfangsterm der Original-E-Mail ein, das System wird die Nachricht an dieser Stelle abschneiden und nur die neue Nachricht übrig lassen (sofern möglich).  Wenn ein regulärer Ausdruck verwendet wird, müssen korrekte Trennzeichen gesetzt werden';
$txt['parser_type'] = 'Typ';
$txt['parser_type_desc'] = 'Standard wird den genauen Suchbegriff finden und die E-Mail an dieser Stelle abschneiden.  Ein regulärer Ausdruck ist die Platzhalteroption des Standards, der Ausdruck muss im PCRE-Format vorliegen.';
$txt['parser_name'] = 'Name';
$txt['parser_name_desc'] = 'Geben Sie optional einen Namen ein, um sich später daran zu erinnern, für welchen E-Mail-Client dieser Parser benutzt wird';
$txt['no_parsers'] = 'Sie haben keine Parser definiert';
$txt['parsers_title'] = 'In diesem Bereich können Sie E-Mail-Parser hinzufügen, ändern oder entfernen.  Parser suchen nach der angegebenen Zeile und schneiden die Nachricht an dieser Stelle ab, um zu versuchen, die ursprüngliche Nachricht, auf die geantwortet wurde, zu entfernen. Falls ein Parser keinen Text zurückgibt (wenn die Antwort zum Beispiel darunter steht oder in der ursprünglichen Nachricht gemischte Zitate verwendet werden), so wird er übersprungen';
$txt['option_standard'] = 'Standard';
$txt['option_regex'] = 'Regulärer Ausdruck';
$txt['parser_sort_description'] = 'Parser werden in der angezeigten Reihenfolge ausgeführt, um dies zu ändern, ziehen Sie ein Element an eine andere Stelle in der Liste.';

// Bounce
$txt['bounce_subject'] = 'Fehlfunktion'; // ? :)
$txt['bounce_error'] = 'Fehler';
$txt['bounce_title'] = 'Ersteller der abgewiesenen E-Mail';
$txt['bounce_notify_subject'] = 'Abweisungsbenachrichtigungsbetreff';
$txt['bounce_notify'] = 'Eine Abweisungsbenachrichtigung senden';
$txt['bounce_notify_template'] = 'Wählen Sie eine Vorlage aus';
$txt['bounce_notify_body'] = 'Abweisungsbenachrichtigungstext';
$txt['bounce_issue'] = 'Abweisungsbenachrichtigung senden';
$txt['bad_bounce'] = 'Text und/oder Betreff ist leer, die Nachricht kann nicht versandt werden';

// Subject tags
$txt['RE:'] = 'AW:';
$txt['FW:'] = 'FW:';
$txt['FWD:'] = 'FWD:';
$txt['SUBJECT:'] = 'BETREFF:';

// Quote strings
$txt['email_wrote'] = 'schrieb';
$txt['email_quoting'] = 'Zitieren';
$txt['email_quotefrom'] = 'Zitat von';
$txt['email_on'] = 'am';
$txt['email_at'] = 'um';

// Our digest strings for the digest "template"
$txt['digest_preview'] = "\n     <*> Themenvorschau:\n     ";
$txt['digest_see_full'] = "\n\n     <*> Betrachten Sie das komplette Thema auf folgender Seite:\n     <*> ";
$txt['digest_reply_preview'] = "\n     <*> Vorschau der neuesten Antwort:\n     ";
$txt['digest_unread_reply_link'] = "\n\n     <*> Sie können ungelesene Antworten auf dieses Thema auf folgender Seite ansehen:\n     <*> ";
$txt['message_attachments'] = '<*> Diese Nachricht enthält %d Verknüpfungen mit Bildern/Dateien.
<*> Um sie anzusehen, folgen Sie bitte diesem Verweis: %s';

// Help
$txt['maillist_help'] = 'Für Hilfe bei der Einrichtung dieser Funktionen besuchen Sie bitte den maillist-Bereich im <a href="https://github.com/elkarte/Elkarte/wiki/Maillist-Feature" target="_blank" class="new_win">ElkArte-Wiki</a>';

// Email bounce templates
$txt['ml_bounce_templates_title'] = 'Eigene Abweisungs-E-Mail-Vorlagen';
$txt['ml_bounce_templates_none'] = 'Es wurden noch keine eigenen Vorlagen erstellt';
$txt['ml_bounce_templates_time'] = 'Erstellungszeitpunkt';
$txt['ml_bounce_templates_name'] = 'Vorlage';
$txt['ml_bounce_templates_creator'] = 'Erstellt von';
$txt['ml_bounce_template_add'] = 'Vorlage hinzufügen';
$txt['ml_bounce_template_modify'] = 'Vorlage ändern';
$txt['ml_bounce_template_delete'] = 'Ausgewählte löschen';
$txt['ml_bounce_template_delete_confirm'] = 'Sind Sie sich sicher, dass Sie die ausgewählten Vorlagen löschen möchten?';
$txt['ml_bounce_subject'] = 'Benachrichtigungsbetreff';
$txt['ml_bounce_body'] = 'Benachrichtigungstext';
$txt['ml_bounce_template_desc'] = 'Verwenden Sie diese Seite, um die Details der Vorlage festzulegen. Beachten Sie, dass der Betreff der E-Mail nicht Teil der Vorlage ist.';
$txt['ml_bounce_template_title'] = 'Titel der Vorlage';
$txt['ml_bounce_template_title_desc'] = 'Der Name, der in der Vorlagenauswahlliste angezeigt wird';
$txt['ml_bounce_template_body'] = 'Inhalt der Vorlage';
$txt['ml_bounce_template_body_desc'] = 'Der Inhalt der Abweisungsnachricht. Beachten Sie, dass Sie folgende Kürzel in dieser Vorlage verwenden können:<ul style="margin-top: 0px;"><li>{MEMBER} - Mitgliedsname.</li><li>{FORUMNAME} - Name des Forums.</li><li>{FORUMNAMESHORT} - Kurzer Name der Site.</li><li>{ERROR} - Der Fehler, den die E-Mail verursachte.</li><li>{SUBJECT} - Der Betreff der fehlgeschlagenen E-Mail.</li><li>{SCRIPTURL} - Webadresse des Forums.</li><li>{EMAILREGARDS} - Signatur der Maillist-E-Mail.</li><li>{REGARDS} - Standard-Forumssignatur.</li></ul>';
$txt['ml_bounce_template_personal'] = 'Persönliche Vorlage';
$txt['ml_bounce_template_personal_desc'] = 'Wenn Sie diese Option auswählen, können nur Sie diese Vorlage sehen, ändern und verwenden; andernfalls werden alle Moderatoren sie verwenden können.';
$txt['ml_bounce_template_error_no_title'] = 'Sie müssen einen aussagekräftigen Titel eingeben.';
$txt['ml_bounce_template_error_no_body'] = 'Sie müssen einen Text für die Vorlage eingeben.';

$txt['ml_bounce'] = 'E-Mail-Vorlagen';
$txt['ml_bounce_description'] = 'In diesem Bereich können Sie Vorlagen, die für Abweisungsnachrichten verwendet werden, die versandt werden, wenn eine Beitrags-E-Mail abgelehnt wird, hinzufügen und ändern.';
$txt['ml_bounce_title'] = 'Abgewiesen';
$txt['ml_bounce_subject'] = 'Ihre E-Mail konnte nicht veröffentlicht werden';
$txt['ml_bounce_body'] = 'Hallo. Hier meldet sich das Beitragsmailprogramm auf {FORUMNAMESHORT}

Ich befürchte, dass ich Ihre Nachricht mit dem Titel {SUBJECT} nicht weiterleiten und/oder veröffentlichen konnte.

Der Fehler, den ich beim Versuch, dies zu tun, verursachte, lautete: {ERROR}

Dies ist ein dauerhafter Fehler; ich hab\'s aufgegeben. Ich bitte um Verzeihung, dass es nicht geklappt hat.

{EMAILREGARDS}';
$txt['ml_inform_title'] = 'Benachrichtigen';
$txt['ml_inform_subject'] = 'Es gab ein Problem mit Ihrer E-Mail';
$txt['ml_inform_body'] = '{MEMBER},

die E-Mail, die Sie an {FORUMNAMESHORT} gesendet haben, erzeugte einen Fehler, der zu Verzögerungen bei der Veröffentlichung führte.  Der Fehler lautete: {ERROR}

Um künftige Verzögerungen bei der Veröffentlichung zu vermeiden, sollten Sie diesen Fehler beheben.

{EMAILREGARDS}';
$txt['ml_bounce_template_body_default'] = 'Hallo. Hier meldet sich das Beitragsmailprogramm auf {FORUMNAMESHORT}

Ich befürchte, dass ich Ihre Nachricht mit dem Titel {SUBJECT} nicht weiterleiten und/oder veröffentlichen konnte.

Der Fehler, den ich beim Versuch, dies zu tun, verursachte, lautete: {ERROR}

Dies ist ein dauerhafter Fehler; ich hab\'s aufgegeben. Ich bitte um Verzeihung, dass es nicht geklappt hat.

{EMAILREGARDS}'; // redundant?
