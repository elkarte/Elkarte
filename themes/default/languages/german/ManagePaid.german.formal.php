<?php
// Version: 1.0; ManagePaid

// Symbols.
$txt['usd_symbol'] = '$%1.2f';
$txt['eur_symbol'] = '&euro;%1.2f';
$txt['gbp_symbol'] = '&pound;%1.2f';

$txt['usd'] = 'USD ($)';
$txt['eur'] = 'Euro (&euro;)';
$txt['gbp'] = 'GBP (&pound;)';
$txt['other'] = 'Andere';

$txt['paid_username'] = 'Benutzername';

$txt['paid_subscriptions_desc'] = 'Von hier aus können Sie bezahlte Abonnements in Ihrem Forum hinzufügen, entfernen und ändern.';
$txt['paid_subs_settings'] = 'Einstellungen';
$txt['paid_subs_settings_desc'] = 'Hier können Sie die verfügbaren Bezahlmethoden einstellen.';
$txt['paid_subs_view'] = 'Abonnements ansehen';
$txt['paid_subs_view_desc'] = 'In diesem Bereich können Sie alle verfügbaren Abonnements ansehen.';

// Setting type strings.
$txt['paid_enabled'] = 'Bezahlte Abonnements aktivieren';
$txt['paid_enabled_desc'] = 'Dies muss aktiviert sein, um bezahlte Abonnements im Forum nutzen zu können.';
$txt['paid_email'] = 'Benachrichtigungsmails senden';
$txt['paid_email_desc'] = 'Die Administratoren informieren, wenn ein Abonnement automatisch verlängert wird.';
$txt['paid_email_to'] = 'E-Mail für Korrespondenz';
$txt['paid_email_to_desc'] = 'Kommagetrennte Liste weiterer E-Mail-Adressen, an die Benachrichtigungsmails gesendet werden sollen.';
$txt['paidsubs_test'] = 'Testmodus aktivieren';
$txt['paidsubs_test_desc'] = 'Dies versetzt die bezahlten Abonnements in den Testmodus, der, wann immer es möglich ist, Sandkasten-Bezahlmethoden in PayPal, Authorize.net usw. verwenden wird. Aktivieren Sie dies nicht, wenn Sie nicht wissen, was Sie tun!';
$txt['paidsubs_test_confirm'] = 'Sind Sie sich sicher, dass Sie den Testmodus aktivieren möchten?';
$txt['paid_email_no'] = 'Keine Benachrichtigungen senden';
$txt['paid_email_error'] = 'Informieren, wenn Abonnement fehlschlägt';
$txt['paid_email_all'] = 'Bei allen automatischen Abonnementsverlängerungen informieren';
$txt['paid_currency'] = 'Wählen Sie eine Währung aus';
$txt['paid_currency_code'] = 'Währungskürzel';
$txt['paid_currency_code_desc'] = 'Währungscode, der von Bezahldienstleistern verwendet wird';
$txt['paid_currency_symbol'] = 'Symbol, das von der Bezahlmethode verwendet wird';
$txt['paid_currency_symbol_desc'] = 'Verwenden Sie \'%1.2f\', um anzugeben, wohin der Betrag gehört, zum Beispiel $%1.2f, %1.2f DM und so weiter';
$txt['paypal_email'] = 'PayPal-E-Mail-Adresse';
$txt['paypal_email_desc'] = 'Leer lassen, wenn Sie PayPal nicht verwenden.';
$txt['worldpay_id'] = 'WorldPay-Installationskennung';
$txt['worldpay_id_desc'] = 'Die von WorldPay erzeugte Installations-ID. Leer lassen, wenn Sie WorldPay nicht verwenden';
$txt['worldpay_password'] = 'WorldPay-Callback-Passwort'; // translator note: ? :)
$txt['worldpay_password_desc'] = 'Stellen Sie sicher, dass, wenn Sie dieses Passwort in WorldPay einstellen, es einmalig und nicht identisch mit Ihrem WorldPay-/Admin-Kontopasswort ist!';
$txt['authorize_id'] = 'Authorize.net-Installationskennung';
$txt['authorize_id_desc'] = 'Die von Authorize.net erzeugte Installations-ID. Leer lassen, wenn Sie Authorize.net nicht verwenden';
$txt['authorize_transid'] = 'Authorize.net-Transaktions-ID';
$txt['2co_id'] = '2co.com-Installationskennung';
$txt['2co_id_desc'] = 'Die von 2co.com erzeugte Installations-ID. Leer lassen, wenn Sie 2co.com nicht verwenden';
$txt['2co_password'] = '2co.com - Geheimes Wort';
$txt['2co_password_desc'] = 'Ihr geheimes 2checkout-Wort.';
$txt['nochex_email'] = 'Nochex-E-Mail-Adresse';
$txt['nochex_email_desc'] = 'E-Mail-Adresse Ihres Verkäuferkontos auf Nochex. Leer lassen, wenn Sie Nochex nicht verwenden';

$txt['paid_note'] = '<strong class="alert">Hinweis:</strong><br />Damit Abonnements für Ihre Benutzer automatisch verlängert werden
	können, müssen Sie für jede Ihrer Bezahlmethoden einen Rückkehr-URL einrichten. Für alle Bezahlmethoden sollte dieser URL eingestellt
	werden auf:<br /><br />
	&nbsp;&nbsp;&bull;&nbsp;&nbsp;<strong>{board_url}/subscriptions.php</strong><br /><br />
	Sie können den Link für PayPal direkt ändern, <a href="https://www.paypal.com/us/cgi-bin/webscr?cmd=_profile-ipn-notify" target="_blank">indem Sie hier klicken</a>.<br />
	Bei den übrigen Anbietern finden Sie diese normalerweise in Ihrem Kundenbereich, meist unter dem Begriff &quot;Callback-URL&quot; oder einem ähnlichen.';

// View subscription strings.
$txt['paid_name'] = 'Name';
$txt['paid_status'] = 'Status';
$txt['paid_cost'] = 'Kosten';
$txt['paid_duration'] = 'Dauer';
$txt['paid_active'] = 'Aktiv';
$txt['paid_pending'] = 'Ausstehende Bezahlung';
$txt['paid_finished'] = 'Abgeschlossen';
$txt['paid_total'] = 'Gesamt';
$txt['paid_is_active'] = 'Aktiviert';
$txt['paid_none_yet'] = 'Sie haben noch keine Abonnements eingerichtet.';
$txt['paid_none_ordered'] = 'Sie haben keine Abonnements.';
$txt['paid_payments_pending'] = 'Ausstehende Bezahlungen';
$txt['paid_order'] = 'Bestellung';

// Add/Edit/Delete subscription.
$txt['paid_add_subscription'] = 'Abonnement hinzufügen';
$txt['paid_edit_subscription'] = 'Abonnement ändern';
$txt['paid_delete_subscription'] = 'Abonnement kündigen';

$txt['paid_mod_name'] = 'Name des Abonnements';
$txt['paid_mod_desc'] = 'Beschreibung';
$txt['paid_mod_reminder'] = 'Erinnerungs-E-Mail senden';
$txt['paid_mod_reminder_desc'] = 'Tage, bevor an den anstehenden Ablauf eines Abonnements erinnert werden soll. (In Tagen, 0 zum Deaktivieren)';
$txt['paid_mod_email'] = 'Bei Abschluss zu sendende E-Mail';
$txt['paid_mod_email_desc'] = 'Wobei {NAME} der Name des Mitglieds ist; {FORUM} ist der Name des Forums. Der Betreff der E-Mail sollte in der ersten Zeile stehen. Leer lassen zum Deaktivieren.';
$txt['paid_mod_cost_usd'] = 'Kosten (USD)';
$txt['paid_mod_cost_eur'] = 'Kosten (EUR)';
$txt['paid_mod_cost_gbp'] = 'Kosten (GBP)';
$txt['paid_mod_cost_blank'] = 'Lassen Sie dies leer, um diese Währung nicht anzubieten.';
$txt['paid_mod_span'] = 'Länge des Abonnements';
$txt['paid_mod_span_days'] = 'Tage';
$txt['paid_mod_span_weeks'] = 'Wochen';
$txt['paid_mod_span_months'] = 'Monate';
$txt['paid_mod_span_years'] = 'Jahre';
$txt['paid_mod_active'] = 'Aktiv';
$txt['paid_mod_active_desc'] = 'Ein Abonnement muss aktiv sein, damit neue Mitglieder es benutzen können.';
$txt['paid_mod_prim_group'] = 'Primäre Gruppe bei Abonnement';
$txt['paid_mod_prim_group_desc'] = 'Primäre Gruppe, der ein Benutzer angehören soll, wenn er ein Abonnement abschließt.';
$txt['paid_mod_add_groups'] = 'Weitere Gruppen bei Abonnement';
$txt['paid_mod_add_groups_desc'] = 'Weitere Gruppen, denen ein Benutzer angehören soll, wenn er ein Abonnement abschließt.';
$txt['paid_mod_no_group'] = 'Nicht ändern';
$txt['paid_mod_edit_note'] = 'Beachten Sie, dass, da diese Gruppe vorhandene Abonnenten hat, die Gruppeneinstellungen nicht geändert werden können!';
$txt['paid_mod_delete_warning'] = '<strong>WARNUNG</strong><br /><br />Wenn Sie dieses Abonnement löschen, werden alle derzeit eingetragenen Benutzer alle Zugriffsrechte, die es mit sich bringt, verlieren. Falls Sie sich nicht sicher sind, ob Sie dies tun möchten, wird es empfohlen, dass Sie ein Abonnement einfach deaktivieren, anstatt es zu löschen.<br />';
$txt['paid_mod_repeatable'] = 'Benutzern die automatische Verlängerung erlauben';
$txt['paid_mod_allow_partial'] = 'Teilweises Abonnement erlauben';
$txt['paid_mod_allow_partial_desc'] = 'Wenn diese Option aktiviert ist, können Benutzer für einen geringenren Betrag ein um die entsprechende Dauer verkürztes Abonnement abschließen.';
$txt['paid_mod_fixed_price'] = 'Abonnement für festen Preis und Zeitraum';
$txt['paid_mod_flexible_price'] = 'Preis verändert sich je nach Dauer des Abonnements';
$txt['paid_mod_price_breakdown'] = 'Flexible Preisstaffelung'; // translator note: "breakdown" lacks a good German alternative.
$txt['paid_mod_price_breakdown_desc'] = 'Definieren Sie hier, wie viel das Abonnement abhängig von der Länge desselben kosten soll. Es kann zum Beispiel 12 Euro für einen Monat, aber nur 100 Euro für ein Jahr kosten. Wenn Sie keinen Preis für eine bestimme Zeitspanne festlegen möchten, lassen Sie ihn leer.';
$txt['flexible'] = 'Flexibel';

$txt['paid_per_day'] = 'Preis pro Tag';
$txt['paid_per_week'] = 'Preis pro Woche';
$txt['paid_per_month'] = 'Preis pro Monat';
$txt['paid_per_year'] = 'Preis pro Jahr';
$txt['day'] = 'Tag';
$txt['week'] = 'Woche';
$txt['month'] = 'Monat';
$txt['year'] = 'Jahr';

// View subscribed users.
$txt['viewing_users_subscribed'] = 'Betrachtet Benutzer';
$txt['view_users_subscribed'] = 'Betrachtet Benutzer, die &quot;%1$s&quot; abonniert haben';
$txt['no_subscribers'] = 'Für dieses Abonnement gibt es zurzeit keine Abonnenten.';
$txt['add_subscriber'] = 'Neuen Abonnenten hinzufügen';
$txt['edit_subscriber'] = 'Abonnenten bearbeiten';
$txt['delete_selected'] = 'Ausgewählte löschen';
$txt['complete_selected'] = 'Ausgewählte abschließen';

// @todo These strings are used in conjunction with JavaScript.  Use numeric entities.
$txt['delete_are_sure'] = 'Sind Sie sich sicher, dass Sie alle Aufzeichnungen der ausgewählten Abonnements löschen möchten?'; // ? :)
$txt['complete_are_sure'] = 'Sind Sie sich sicher, dass Sie die ausgewählten Abonnements abschließen möchten?';

$txt['start_date'] = 'Startdatum';
$txt['end_date'] = 'Enddatum';
$txt['start_date_and_time'] = 'Startdatum und -zeit';
$txt['end_date_and_time'] = 'Enddatum und -zeit';
$txt['edit'] = 'ÄNDERN';
$txt['one_username'] = 'Bitte geben Sie nur einen Benutzernamen ein.';
$txt['minute'] = 'Minute';
$txt['error_member_not_found'] = 'Das eingegebene Mitglied konnte nicht gefunden werden';
$txt['member_already_subscribed'] = 'Dieses Mitglied hat dieses Abonnement bereits abgeschlossen. Bitte ändern Sie stattdessen sein bestehendes Abonnement.';
$txt['search_sub'] = 'Benutzer suchen';

// Make payment.
$txt['paid_confirm_payment'] = 'Bezahlung bestätigen';
$txt['paid_confirm_desc'] = 'Um zur Kasse zu gelangen, überprüfen Sie bitte die unten stehenden Details und klicken Sie auf &quot;Bestellen&quot;';
$txt['paypal'] = 'PayPal';
$txt['paid_confirm_paypal'] = 'Um mit <a href="http://www.paypal.com">PayPal</a> zu bezahlen, betätigen Sie bitte unten stehende Schaltfläche. Sie werden zur Bezahlung auf die PayPal-Website weitergeleitet.';
$txt['paid_paypal_order'] = 'Mit PayPal bezahlen';
$txt['worldpay'] = 'WorldPay';
$txt['paid_confirm_worldpay'] = 'Um mit <a href="http://www.worldpay.com">WorldPay</a> zu bezahlen, betätigen Sie bitte unten stehende Schaltfläche. Sie werden zur Bezahlung auf die WorldPay-Website weitergeleitet.';
$txt['paid_worldpay_order'] = 'Mit WorldPay bezahlen';
$txt['nochex'] = 'Nochex';
$txt['paid_confirm_nochex'] = 'Um mit <a href="http://www.nochex.com">Nochex</a> zu bezahlen, betätigen Sie bitte unten stehende Schaltfläche. Sie werden zur Bezahlung auf die Nochex-Website weitergeleitet.';
$txt['paid_nochex_order'] = 'Mit Nochex bezahlen';
$txt['authorize'] = 'Authorize.net';
$txt['paid_confirm_authorize'] = 'Um mit <a href="http://www.authorize.net">Authorize.net</a> zu bezahlen, betätigen Sie bitte unten stehende Schaltfläche. Sie werden zur Bezahlung auf Authorize.net weitergeleitet.';
$txt['paid_authorize_order'] = 'Mit Authorize.net bezahlen';
$txt['2co'] = '2checkout';
$txt['paid_confirm_2co'] = 'Um mit <a href="http://www.2co.com">2co.com</a> zu bezahlen, betätigen Sie bitte unten stehende Schaltfläche. Sie werden zur Bezahlung auf die 2co-Website weitergeleitet.';
$txt['paid_2co_order'] = 'Mit 2co.com bezahlen';
$txt['paid_done'] = 'Zahlung erfolgt';
$txt['paid_done_desc'] = 'Danke für Ihre Bezahlung. Sobald die Transaktion bestätigt wurde, wird Ihr Abonnement aktiviert.';
$txt['paid_sub_return'] = 'Zu Abonnements zurückkehren';
$txt['paid_current_desc'] = 'Unten finden Sie eine Liste all Ihrer derzeitigen und früheren Abonnements. Um ein bestehendes Abonnement zu verlängern, wählen Sie es einfach aus obiger Liste aus.';
$txt['paid_admin_add'] = 'Dieses Abonnement hinzufügen';

$txt['paid_not_set_currency'] = 'Sie haben Ihre Währung noch nicht eingestellt. Bitte holen Sie dies in den <a href="%1$s">Einstellungen</a> nach, bevor Sie fortfahren.';
$txt['paid_no_cost_value'] = 'Sie müssen Kosten und Dauer des Abonnements angeben.';
$txt['paid_all_freq_blank'] = 'Sie müssen für mindestens einen der vier Zeiträume Kosten angeben.';

// Some error strings.
$txt['paid_no_data'] = 'Dem Skript wurden keine gültigen Daten übermittelt.';

$txt['paypal_could_not_connect'] = 'Konnte nicht mit dem PayPal-Server verbinden';
$txt['paypal_currency_unknown'] = 'Der Währungscode von PayPal (%1$s) stimmt nicht mit dem Code in Ihren Einstellungen (%2$) überein.';
$txt['paid_sub_not_active'] = 'Dieses Abonnement steht keinem weiteren Mitglied mehr zur Verfügung.';
$txt['paid_disabled'] = 'Bezahlte Abonnements sind derzeit deaktiviert.';
$txt['paid_unknown_transaction_type'] = 'Unbekannter Transaktionstyp.';
$txt['paid_empty_member'] = 'Abonnementsverwaltung konnte Mitglieds-ID nicht wiederherstellen';
$txt['paid_could_not_find_member'] = 'Abonnementsverwaltung konnte Mitglied mit ID %1$d nicht finden';
$txt['paid_count_not_find_subscription'] = 'Abonnementsverwaltung konnte Abonnement für Mitglieds-ID: %1$s, Abonnements-ID: %2$s nicht finden';
$txt['paid_count_not_find_subscription_log'] = 'Abonnementsverwaltung konnte Abonnementsprotokoll für Mitglieds-ID: %1$s, Abonnements-ID: %2$s nicht finden';
$txt['paid_count_not_find_outstanding_payment'] = 'Konnte keine ausstehende Zahlung für Mitglieds-ID: %1$s, Abonnements-ID: %2$s finden, wird somit ignoriert';
$txt['paid_admin_not_setup_gateway'] = 'Verzeihung, der Administrator ist mit dem Einrichten der bezahlten Abonnements noch nicht fertig. Schauen Sie bitte später noch mal nach.';
$txt['paid_make_recurring'] = 'Diese Zahlung wiederholen';

$txt['subscriptions'] = 'Abonnements';
$txt['subscription'] = 'Abonnement';
$txt['paid_subs_desc'] = 'Unten finden Sie eine Liste aller in diesem Forum verfügbaren Abonnements.';
$txt['paid_subs_none'] = 'Derzeit sind keine bezahlten Abonnements verfügbar.';

$txt['paid_current'] = 'Bestehende Abonnements';
$txt['pending_payments'] = 'Ausstehende Zahlungen';
$txt['pending_payments_desc'] = 'Dieses Mitglied hat folgende Zahlungen für dieses Abonnement zu leisten versucht, aber die Bestätigung hat das Forum noch nicht erhalten. Wenn Sie sich sicher sind, dass die Zahlung eingegangen ist, klicken Sie auf &quot;Annehmen&quot;, um das Abonnement zu bestätigen. Alternativ können Sie auch auf &quot;Entfernen&quot; klicken, um alle Verweise auf diese Zahlung zu löschen.';
$txt['pending_payments_value'] = 'Wert';
$txt['pending_payments_accept'] = 'Annehmen';
$txt['pending_payments_remove'] = 'Entfernen';
