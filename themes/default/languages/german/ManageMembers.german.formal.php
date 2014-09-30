<?php
// Version: 1.0; ManageMembers

$txt['groups'] = 'Gruppen';
$txt['viewing_groups'] = 'Betrachtet Benutzergruppen'; // ? :)

$txt['membergroups_title'] = 'Benutzergruppen verwalten';
$txt['membergroups_description'] = 'Benutzergruppen sind Gruppen von Mitgliedern, die ähnliche Befugnisse, Erscheinungsbilder oder Zugriffsrechte innehaben. Die Mitgliedschaft in einigen Benutzergruppen ist abhängig von der Anzahl der Beiträge eines Mitglieds. Sie können jemandem eine Benutzergruppe zuweisen, indem Sie seine Kontoeinstellungen über sein Profil anpassen.';
$txt['membergroups_modify'] = 'Ändern';
$txt['membergroups_modify_parent'] = 'Elterngruppe ändern';

$txt['membergroups_add_group'] = 'Gruppe hinzufügen';
$txt['membergroups_regular'] = 'Normale Gruppen';
$txt['membergroups_post'] = 'Beitragsabhängige Gruppen';
$txt['membergroups_guests_na'] = 'n.v.';

$txt['membergroups_group_name'] = 'Benutzergruppenname';
$txt['membergroups_new_board'] = 'Sichtbare Foren';
$txt['membergroups_new_board_desc'] = 'Foren, die die Benutzergruppe sehen kann';
$txt['membergroups_new_board_post_groups'] = '<em>Hinweis: Normalerweise benötigen Benutzergruppen, die von der Beitragszahl abhängig sind, keinen gesonderten Zugriff, da dieser bereits über die anderen Gruppen eines Mitglieds geregelt wird.</em>';
$txt['membergroups_new_as_inherit'] = 'erbt von';
$txt['membergroups_new_as_type'] = 'nach Typ';
$txt['membergroups_new_as_copy'] = 'basiert auf';
$txt['membergroups_new_copy_none'] = '(keine)';
$txt['membergroups_can_edit_later'] = 'Sie können sie später ändern.';

$txt['membergroups_edit_group'] = 'Benutzergruppe ändern';
$txt['membergroups_edit_name'] = 'Gruppenname';
$txt['membergroups_edit_inherit_permissions'] = 'Befugnisse übernehmen';
$txt['membergroups_edit_inherit_permissions_desc'] = 'Wählen Sie &quot;Nein&quot; aus, um dieser Gruppe eigene Befugnisse zu gewähren.';
$txt['membergroups_edit_inherit_permissions_no'] = 'Nein - Verwende eigene Befugnisse';
$txt['membergroups_edit_inherit_permissions_from'] = 'Übernehmen von';
$txt['membergroups_edit_hidden'] = 'Sichtbarkeit';
$txt['membergroups_edit_hidden_no'] = 'Sichtbar';
$txt['membergroups_edit_hidden_boardindex'] = 'Sichtbar - Apart from in group key'; // ehm, what?
$txt['membergroups_edit_hidden_all'] = 'Unsichtbar';
// Do not use numeric entities in the below string.
$txt['membergroups_edit_hidden_warning'] = 'Sind Sie sich sicher, dass Sie eine Zuweisung dieser Gruppe als Hauptgruppe eines Benutzers nicht zulassen möchten?\\n\\nDies wird die Zuweisung auf Verwendung als weitere Gruppe beschränken und diese Änderung auch bei allen bestehenden &quot;Hauptbenutzern&quot; vornehmen.';
$txt['membergroups_edit_desc'] = 'Gruppenbeschreibung';
$txt['membergroups_edit_group_type'] = 'Gruppentyp';
$txt['membergroups_edit_select_group_type'] = 'Wählen Sie den Gruppentyp aus';
$txt['membergroups_group_type_private'] = 'Privat <span class="smalltext">(Mitgliedschaft muss zugewiesen werden)</span>';
$txt['membergroups_group_type_protected'] = 'Geschützt <span class="smalltext">(Nur Administratoren können die Gruppe zuweisen und verwalten)</span>';
$txt['membergroups_group_type_request'] = 'Auf Anfrage <span class="smalltext">(Benutzer können Mitgliedschaft beantragen)</span>';
$txt['membergroups_group_type_free'] = 'Offen <span class="smalltext">(Benutzer können der Gruppe beliebig beitreten und sie verlassen)</span>';
$txt['membergroups_group_type_post'] = 'Beitragsbasiert <span class="smalltext">(Mitgliedschaft abhängig von der Anzahl der Beiträge)</span>';
$txt['membergroups_min_posts'] = 'Benötigte Beiträge';
$txt['membergroups_online_color'] = 'Farbe in Onlineliste';
$txt['membergroups_icon_count'] = 'Anzahl an Symbolbildern';
$txt['membergroups_icon_image'] = 'Symbolbild-Dateiname';
$txt['membergroups_icon_image_note'] = 'Laden Sie Bilddateien in das Standardthemeverzeichnis hoch, um die Auswahl zu aktivieren.<br />Wählen Sie das Symbol aus, um es zu ändern.';
$txt['membergroups_max_messages'] = 'Max. private Nachrichten';
$txt['membergroups_max_messages_note'] = '0 = unbegrenzt';
$txt['membergroups_max_messages_desc'] = 'Hier können Sie die Höchstzahl an privaten Nachrichten festlegen, die ein Benutzer auf dem Server speichern darf.<br />
Um eine unbegrenzte Speicherung von privaten Nachrichten zu erlauben, setzen Sie den Wert auf 0.';
$txt['membergroups_edit_save'] = 'Speichern';
$txt['membergroups_delete'] = 'Löschen';
$txt['membergroups_confirm_delete'] = 'Sind Sie sich sicher, dass Sie diese Gruppe löschen möchten?';

$txt['membergroups_members_title'] = 'Gruppe ansehen';
$txt['membergroups_members_group_members'] = 'Gruppenmitglieder';
$txt['membergroups_members_no_members'] = 'Diese Gruppe ist momentan leer';
$txt['membergroups_members_add_title'] = 'Dieser Gruppe ein Mitglied hinzufügen';
$txt['membergroups_members_add_desc'] = 'Liste hinzuzufügender Mitglieder';
$txt['membergroups_members_add'] = 'Mitglieder hinzufügen';
$txt['membergroups_members_remove'] = 'Aus Gruppe entfernen';
$txt['membergroups_members_last_active'] = 'Zuletzt aktiv';
$txt['membergroups_members_additional_only'] = 'Nur als zusätzliche Gruppe hinzufügen.';
$txt['membergroups_members_group_moderators'] = 'Gruppenmoderatoren';
$txt['membergroups_members_description'] = 'Beschreibung';
// Use javascript escaping in the below.
$txt['membergroups_members_deadmin_confirm'] = 'Sind Sie sich sicher, dass Sie sich selbst aus der Gruppe der Administratoren entfernen möchten?';

$txt['membergroups_postgroups'] = 'Beitragsgruppen'; // ? :)
$txt['membergroups_settings'] = 'Benutzergruppeneinstellungen';
$txt['groups_manage_membergroups'] = 'Gruppen, die Benutzergruppen ändern dürfen';
$txt['membergroups_select_permission_type'] = 'Wählen Sie ein Befugnisprofil aus';
$txt['membergroups_images_url'] = '{theme URL}/images/group_icons/';
$txt['membergroups_select_visible_boards'] = 'Foren anzeigen';
$txt['membergroups_members_top'] = 'Mitglieder';
$txt['membergroups_name'] = 'Name';
$txt['membergroups_icons'] = 'Symbole';

$txt['admin_browse_approve'] = 'Mitglieder, deren Konto auf Freischaltung wartet';
$txt['admin_browse_approve_desc'] = 'Hier können Sie alle Mitglieder verwalten, die auf die Freischaltung ihres Benutzerkontos warten.';
$txt['admin_browse_activate'] = 'Mitglieder, deren Konto auf Aktivierung wartet';
$txt['admin_browse_activate_desc'] = 'Diese Seite listet alle Mitglieder auf, die ihr Benutzerkonto in diesem Forum noch immer nicht aktiviert haben.';
$txt['admin_browse_awaiting_approval'] = 'Wartet auf Freischaltung [%1$d]';
$txt['admin_browse_awaiting_activate'] = 'Wartet auf Aktivierung [%1$d]';

$txt['admin_browse_username'] = 'Benutzername';
$txt['admin_browse_email'] = 'E-Mail-Adresse';
$txt['admin_browse_ip'] = 'IP-Adresse';
$txt['admin_browse_registered'] = 'Registriert';
$txt['admin_browse_id'] = 'ID';
$txt['admin_browse_with_selected'] = 'Mit Auswahl';
$txt['admin_browse_no_members_approval'] = 'Derzeit wartet kein Mitglied auf Freischaltung.';
$txt['admin_browse_no_members_activate'] = 'Derzeit hat kein Mitglied sein Konto nicht aktiviert.';

// Don't use entities in the below strings, except the main ones. (lt, gt, quot.)
// translator note: not entirely sure about this. :<
$txt['admin_browse_warn'] = 'aller ausgewählten Mitglieder?';
$txt['admin_browse_outstanding_warn'] = 'aller betroffenen Mitglieder?';
$txt['admin_browse_w_approve'] = 'Freischaltung';
$txt['admin_browse_w_activate'] = 'Aktivierung';
$txt['admin_browse_w_delete'] = 'Löschung';
$txt['admin_browse_w_reject'] = 'Zurückweisung (löschen)';
$txt['admin_browse_w_remind'] = 'Erinnerung';
$txt['admin_browse_w_approve_deletion'] = 'Freischaltung (Konten löschen)';
$txt['admin_browse_w_email'] = 'und E-Mail-Benachrichtigung';
$txt['admin_browse_w_approve_require_activate'] = 'Freischaltung und Anforderung der Aktivierung';

$txt['admin_browse_filter_by'] = 'Filtern nach';
$txt['admin_browse_filter_show'] = 'Zeige';
$txt['admin_browse_filter_type_0'] = 'Nicht aktivierte neue Konten';
$txt['admin_browse_filter_type_2'] = 'Nicht aktivierte E-Mail-Adressänderungen';
$txt['admin_browse_filter_type_3'] = 'Nicht freigeschaltete neue Konten';
$txt['admin_browse_filter_type_4'] = 'Nicht freigeschaltete Kontolöschungen';
$txt['admin_browse_filter_type_5'] = 'Nicht freigeschaltete Konten Minderjähriger';

$txt['admin_browse_outstanding'] = 'Ausstehende Mitgliedschaften';
$txt['admin_browse_outstanding_days_1'] = 'Mit allen Mitgliedern, die sich vor mehr als';
$txt['admin_browse_outstanding_days_2'] = 'Tagen registriert haben';
$txt['admin_browse_outstanding_perform'] = 'Folgende Aktion ausführen';
$txt['admin_browse_outstanding_go'] = 'Aktion ausführen';

$txt['check_for_duplicate'] = 'Auf Duplikate prüfen';
$txt['dont_check_for_duplicate'] = 'Nicht auf Duplikate prüfen';
$txt['duplicates'] = 'Duplikate';

$txt['not_activated'] = 'Nicht aktiviert';
