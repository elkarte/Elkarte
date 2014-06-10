<?php
// Version: 1.0; Login

// Registration agreement page.
$txt['registration_agreement'] = 'Nutzungsbedingungen';
$txt['agreement_agree'] = 'Ich stimme den Nutzungsbedingungen zu.';
$txt['agreement_agree_coppa_above'] = 'Ich stimme den Nutzungsbedingungen zu und ich bin mindestens %1$d Jahre alt.';
$txt['agreement_agree_coppa_below'] = 'Ich stimme den Nutzungsbedingungen zu und ich bin jünger als %1$d Jahre.';
$txt['agree_coppa_above'] = 'Ich bin mindestens %1$d Jahre alt.';
$txt['agree_coppa_below'] = 'Ich bin jünger als %1$d Jahre.';

// Registration form.
$txt['registration_form'] = 'Registrierungsformular';
$txt['error_too_quickly'] = 'Sie haben sich zu schnell registriert - schneller als es eigentlich möglich sein sollte. Bitte warten Sie einen Moment und versuchen Sie es dann erneut.';
$txt['error_token_verification'] = 'Tokenverifizierung fehlgeschlagen. Bitte versuchen Sie es erneut.';
$txt['need_username'] = 'Sie müssen einen Benutzernamen angeben.';
$txt['no_password'] = 'Sie haben kein Passwort eingegeben.';
$txt['improper_password'] = 'Das angegebene Passwort ist zu lang.';
$txt['incorrect_password'] = 'Passwort falsch';
$txt['openid_not_found'] = 'Angegebene OpenID nicht gefunden.';
$txt['choose_username'] = 'Wählen Sie einen Benutzernamen aus';
$txt['maintain_mode'] = 'Wartungsmodus';
$txt['registration_successful'] = 'Erfolgreich registriert';
$txt['now_a_member'] = 'Erfolg! Sie sind nun ein Mitglied dieses Forums.';
// Use numeric entities in the below string.
$txt['your_password'] = 'und Ihr Passwort lautet';
$txt['valid_email_needed'] = 'Bitte geben Sie eine gültige E-Mail-Adresse ein, %1$s.';
$txt['required_info'] = 'Benötigte Informationen';
$txt['identification_by_elkarte'] = 'Wird benutzt, um Sie im Forum zu identifizieren.';
$txt['additional_information'] = 'Weitere Informationen';
$txt['warning'] = 'Warnung!';
$txt['only_members_can_access'] = 'Nur registrierte Mitglieder dürfen diesen Bereich einsehen.';
$txt['login_below'] = 'Bitte melden Sie sich unten an.';
$txt['login_below_or_register'] = 'Bitte melden Sie sich unten an oder <a href="%1$s">erstellen Sie ein Konto</a> auf %2$s';

// Use numeric entities in the below two strings.
$txt['may_change_in_profile'] = 'Sie können Änderungen vornehmen, indem Sie Ihr Profil öffnen oder nach der Anmeldung diese Seite besuchen:';
$txt['your_username_is'] = 'Ihr Benutzername lautet: ';

$txt['login_hash_error'] = 'Es wurden kürzlich Änderungen an der Passwortsicherheit vorgenommen.<br />Bitte geben Sie Ihr Passwort erneut ein.';

$txt['ban_register_prohibited'] = 'Verzeihung, aber Sie dürfen sich in diesem Forum nicht registrieren.';
$txt['under_age_registration_prohibited'] = 'Verzeihung, aber Personen unter %1$d Jahren dürfen sich in diesem Forum nicht registrieren.';

$txt['activate_account'] = 'Konto aktivieren';
$txt['activate_success'] = 'Ihr Konto wurde erfolgreich aktiviert. Sie können sich nun anmelden.';
$txt['activate_not_completed1'] = 'Ihre E-Mail-Adresse muss bestätigt werden, bevor Sie sich anmelden können.';
$txt['activate_not_completed2'] = 'Brauchen Sie noch eine Aktivierungsmail?';
$txt['activate_after_registration'] = 'Danke für Ihre Registrierung. Sie werden in Kürze eine E-Mail mit einem Link erhalten, mithilfe dessen Sie Ihr Konto aktivieren können.  Falls Sie diese E-Mail nicht erhalten zu haben glauben, schauen Sie in Ihrem Spamordner nach.';
$txt['invalid_userid'] = 'Benutzer ist nicht vorhanden';
$txt['invalid_activation_code'] = 'Ungültiger Aktivierungscode';
$txt['invalid_activation_username'] = 'Benutzername oder E-Mail-Adresse';
$txt['invalid_activation_new'] = 'Falls Sie sich mit der falschen E-Mail-Adresse registriert haben, geben Sie hier eine neue und Ihr Passwort ein.';
$txt['invalid_activation_new_email'] = 'Neue E-Mail-Adresse';
$txt['invalid_activation_password'] = 'Altes Passwort';
$txt['invalid_activation_resend'] = 'Aktivierungscode erneut senden';
$txt['invalid_activation_known'] = 'Falls Sie Ihren Aktivierungscode bereits kennen, geben Sie ihn bitte hier ein.';
$txt['invalid_activation_retry'] = 'Aktivierungscode';
$txt['invalid_activation_submit'] = 'Aktivieren';

// translator note: becoming informal here for logical reasons.
$txt['coppa_no_concent'] = 'Der Administrator hat noch keine Einverständniserklärung seitens deiner Eltern oder Erziehungsberechtigten erhalten.';
$txt['coppa_need_more_details'] = 'Brauchst du weitere Informationen?';
// informality ends here.

$txt['awaiting_delete_account'] = 'Ihr Konto wurde zum Löschen vorgemerkt!<br />Sollten Sie Ihr Konto wiederherstellen wollen, wählen Sie bitte &quot;Mein Konto reaktivieren&quot; aus und melden Sie sich erneut an.';
$txt['undelete_account'] = 'Mein Konto reaktivieren';

// Use numeric entities in the below three strings.
$txt['change_password'] = 'Neues Passwort'; // ? :)
$txt['change_password_login'] = 'Ihre Anmeldedaten auf';
$txt['change_password_new'] = 'wurden geändert und Ihr Passwort wurde zurückgesetzt. Unten finden Sie die neuen Daten.';

$txt['in_maintain_mode'] = 'Dieses Forum befindet sich im Wartungsmodus.';

// These two are used as a javascript alert; please use international characters directly, not as entities.
$txt['register_agree'] = 'Bitte lesen und akzeptieren Sie die Nutzungsbedingungen, bevor Sie mit der Registrierung fortfahren.';
$txt['register_passwords_differ_js'] = 'Die Passwörter stimmen nicht überein!';

$txt['approval_after_registration'] = 'Danke, dass Sie sich registriert haben. Ein Administrator muss Ihre Anmeldung noch freischalten. Sie erhalten umgehend eine E-Mail, wenn dies passiert ist.';

$txt['admin_settings_desc'] = 'Hier können Sie eine Vielzahl an Einstellungen für die Anmeldung neuer Mitglieder vornehmen.';

$txt['setting_enableOpenID'] = 'Registrierung via OpenID erlauben';

$txt['setting_registration_method'] = 'Registrierungsmethode für neue Mitglieder';
$txt['setting_registration_disabled'] = 'Registrierung deaktiviert';
$txt['setting_registration_standard'] = 'Sofortige Registrierung';
$txt['setting_registration_activate'] = 'E-Mail-Aktivierung';
$txt['setting_registration_approval'] = 'Adminüberprüfung';
$txt['setting_notify_new_registration'] = 'Administratoren über neue Anmeldungen informieren';
$txt['setting_send_welcomeEmail'] = 'Willkommens-E-Mail an neue Mitglieder senden';

$txt['setting_coppaAge'] = 'Alter, bis zu dem Registrierungsbeschränkungen gelten sollen';
$txt['setting_coppaAge_desc'] = '(Auf 0 setzen zum Deaktivieren)';
$txt['setting_coppaType'] = 'Was soll getan werden, wenn ein Benutzer jünger ist?';
$txt['setting_coppaType_reject'] = 'Registrierung abweisen';
$txt['setting_coppaType_approval'] = 'Einverständniserklärung der Eltern einholen';
$txt['setting_coppaPost'] = 'Postadresse, an die Einverständniserklärungen gesendet werden sollen';
$txt['setting_coppaPost_desc'] = 'Trifft nur zu, wenn Altersbeschränkungen gelten';
$txt['setting_coppaFax'] = 'Faxnummer, an die Einverständniserklärungen gefaxt werden sollen';
$txt['setting_coppaPhone'] = 'Kontaktnummer, an die sich Eltern zwecks Einverständniserklärung wenden können';

$txt['admin_register'] = 'Registrierung eines neuen Mitglieds';
$txt['admin_register_desc'] = 'Von hier aus können Sie neue Mitglieder registrieren und, wenn gewünscht, ihnen ihre Anmeldedaten per E-Mail zusenden lassen.';
$txt['admin_register_username'] = 'Neuer Benutzername';
$txt['admin_register_email'] = 'E-Mail-Adresse';
$txt['admin_register_password'] = 'Passwort';
$txt['admin_register_username_desc'] = 'Benutzername des neuen Mitglieds';
$txt['admin_register_email_desc'] = 'E-Mail-Adresse des Mitglieds';
$txt['admin_register_password_desc'] = 'Passwort für das neue Mitglied';
$txt['admin_register_email_detail'] = 'Dem Benutzer das Passwort per E-Mail senden';
$txt['admin_register_email_detail_desc'] = 'E-Mail-Adresse wird auch benötigt, falls deaktiviert';
$txt['admin_register_email_activate'] = 'Aktivierung seitens des Benutzers wird benötigt';
$txt['admin_register_group'] = 'Primäre Benutzergruppe';
$txt['admin_register_group_desc'] = 'Hauptbenutzergruppe, der das neue Mitglied angehören wird';
$txt['admin_register_group_none'] = '(keine primäre Benutzergruppe)';
$txt['admin_register_done'] = 'Mitglied %1$s wurde erfolgreich registriert!';

// translator note: some more informalities follow. :)
$txt['coppa_title'] = 'Altersbeschränktes Forum';
$txt['coppa_after_registration'] = 'Danke, dass du dich auf {forum_name_html_safe} registriert hast.<br /><br />Weil du jünger als {MINIMUM_AGE} Jahre bist, sind wir verpflichtet, die Erlaubnis deiner Eltern oder Erziehungsberechtigten einzuholen, bevor du das Forum benutzen darfst.  Um alles Nötige in die Wege zu leiten, drucke bitte unten stehendes Formular aus:';
$txt['coppa_form_link_popup'] = 'Formular in neuem Fenster öffnen';
$txt['coppa_form_link_download'] = 'Formular als Textdatei herunterladen';
$txt['coppa_send_to_one_option'] = 'Dann bitte deine Eltern/Erziehungsberechtigten darum, das ausgefüllte Formular folgendermaßen abzusenden:';
$txt['coppa_send_to_two_options'] = 'Dann bitte deine Eltern/Erziehungsberechtigten darum, das ausgefüllte Formular folgendermaßen abzusenden:';
$txt['coppa_send_by_post'] = 'per Post an folgende Adresse:';
$txt['coppa_send_by_fax'] = 'per Fax an folgende Nummer:';
$txt['coppa_send_by_phone'] = 'Sie können stattdessen auch einen Administrator unter {PHONE_NUMBER} anrufen.';

$txt['coppa_form_title'] = 'Erlaubnis zur Registrierung auf {forum_name_html_safe}';
$txt['coppa_form_address'] = 'Adresse';
$txt['coppa_form_date'] = 'Datum';
$txt['coppa_form_body'] = 'Ich, {PARENT_NAME},<br /><br />erteile {CHILD_NAME} (Name des Kindes) die Erlaubnis, ein registriertes Mitglied des Forums {forum_name_html_safe}unter dem Benutzernamen {USER_NAME} zu werden.<br /><br />Mir ist bekannt, dass einzelne persönliche Informationen, die von {USER_NAME} veröffentlicht werden, anderen Benutzern des Forums angezeigt werden könnten.<br /><br />Gezeichnet:<br />{PARENT_NAME} (Elternteil/Erziehungsberechtigter).';

$txt['visual_verification_sound_again'] = 'Nochmals abspielen';
$txt['visual_verification_sound_close'] = 'Fenster schließen';
$txt['visual_verification_sound_direct'] = 'Sie haben Probleme beim Abspielen?  Versuchen Sie es mit einem Direktlink.';

// Use numeric entities in the below.
$txt['registration_username_available'] = 'Benutzername ist verfügbar';
$txt['registration_username_unavailable'] = 'Benutzername ist nicht verfügbar';
$txt['registration_username_check'] = 'Prüfen, ob der Benutzername verfügbar ist';
$txt['registration_password_short'] = 'Passwort ist zu kurz';
$txt['registration_password_reserved'] = 'Passwort enthält Ihren Benutzernamen/Ihre E-Mail-Adresse';
$txt['registration_password_numbercase'] = 'Passwort muss Groß- und Kleinbuchstaben sowie Ziffern enthalten';
$txt['registration_password_no_match'] = 'Passwörter stimmen nicht überein';
$txt['registration_password_valid'] = 'Passwort ist zulässig';

$txt['registration_errors_occurred'] = 'Folgende Fehler wurden bei Ihrer Registrierung festgestellt. Bitte korrigieren Sie sie, bevor Sie fortfahren:';

$txt['authenticate_label'] = 'Authentifizierungsmethode';
$txt['authenticate_password'] = 'Passwort';
$txt['authenticate_openid'] = 'OpenID';
$txt['authenticate_openid_url'] = 'OpenID-Authentifizierungs-URL';

// Contact form
$txt['admin_contact_form'] = 'Admins kontaktieren';
$txt['contact_your_message'] = 'Ihre Nachricht';
$txt['errors_contact_form'] = 'Folgende Fehler traten beim Verarbeiten Ihrer Kontaktanfrage auf';
$txt['contact_subject'] = 'Ein Gast hat Ihnen eine Nachricht gesendet';
$txt['contact_thankyou'] = 'Danke für Ihre Nachricht, jemand wird sich schnellstmöglich bei Ihnen melden.';
