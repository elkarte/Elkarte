<?php
// Version: 1.0; ManagePermissions

$txt['permissions_title'] = 'Befugnisse verwalten';
$txt['permissions_modify'] = 'Ändern';
$txt['permissions_view'] = 'Ansehen';
$txt['permissions_allowed'] = 'Erlaubt';
$txt['permissions_denied'] = 'Verweigert';
$txt['permission_cannot_edit'] = '<strong>Hinweis:</strong> Sie können dieses Befugnisprofil nicht ändern, da es ein in der Forensoftware standardmäßig enthaltenes vordefiniertes Profil ist. Sofern Sie die Befugnisse dieses Profils ändern möchten, müssen Sie zunächst ein Duplikat dieses Profils erzeugen. Dies können Sie <a href="%1$s">hier tun</a>.';

$txt['permissions_for_profile'] = 'Befugnisse für das Profil';
$txt['permissions_boards_desc'] = 'Unten stehende Liste zeigt Ihnen, welche Befugnisse jedem Ihrer Foren zugewiesen wurden. Sie können diese Zuweisung per Klick auf den Namen des jeweiligen Forums oder per Auswahl von &quot;Alle bearbeiten&quot; am Ende dieser Seite ändern. Um das Profil selbst zu ändern, klicken Sie einfach auf seinen Namen.';
$txt['permissions_board_all'] = 'Alle bearbeiten';
$txt['permission_profile'] = 'Befugnisprofil';
$txt['permission_profile_desc'] = 'Welchen <a href="%1$s">Befugnissatz</a> das Forum nutzen soll.';
$txt['permission_profile_inherit'] = 'Vom übergeordneten Forum erben';

$txt['permissions_profile'] = 'Profil';
$txt['permissions_profiles_desc'] = 'Befugnisprofile werden einzelnen Foren zugewiesen, um es Ihnen zu ermöglichen, Ihre Sicherheitseinstellungen einfach zu verwalten. In diesem Bereich können Sie Befugnisprofile erstellen, ändern und löschen.';
$txt['permissions_profiles_change_for_board'] = 'Befugnisprofil ändern für: &quot;%1$s&quot;';
$txt['permissions_profile_default'] = 'Standard';
$txt['permissions_profile_no_polls'] = 'Keine Umfragen';
$txt['permissions_profile_reply_only'] = 'Nur antworten';
$txt['permissions_profile_read_only'] = 'Nur lesen';

$txt['permissions_profile_rename'] = 'Alle umbenennen';
$txt['permissions_profile_edit'] = 'Profile ändern';
$txt['permissions_profile_new'] = 'Neues Profil';
$txt['permissions_profile_new_create'] = 'Profil erstellen';
$txt['permissions_profile_name'] = 'Profilname';
$txt['permissions_profile_used_by'] = 'Benutzt von';
$txt['permissions_profile_used_by_one'] = 'einem Forum';
$txt['permissions_profile_used_by_many'] = '%1$d Foren';
$txt['permissions_profile_used_by_none'] = 'keinem Forum';
$txt['permissions_profile_do_edit'] = 'Ändern';
$txt['permissions_profile_do_delete'] = 'Löschen';
$txt['permissions_profile_copy_from'] = 'Befugnisse kopieren von';

$txt['permissions_includes_inherited'] = 'Abgeleitete Gruppen';
$txt['permissions_includes_inherited_from'] = 'Abgeleitet von: ';

$txt['permissions_all'] = 'alle';
$txt['permissions_none'] = 'keine';
$txt['permissions_set_permissions'] = 'Befugnisse erteilen';

$txt['permissions_advanced_options'] = 'Erweiterte Optionen';
$txt['permissions_with_selection'] = 'Mit Auswahl';
$txt['permissions_apply_pre_defined'] = 'Vordefinierten Befugnissatz übernehmen';
$txt['permissions_select_pre_defined'] = 'Wählen Sie ein vordefiniertes Profil aus';
$txt['permissions_copy_from_board'] = 'Befugnisse von diesem Forum übernehmen';
$txt['permissions_select_board'] = 'Wählen Sie ein Forum aus';
$txt['permissions_like_group'] = 'Befugnisse von dieser Gruppe übernehmen';
$txt['permissions_select_membergroup'] = 'Wählen Sie eine Benutzergruppe aus';
$txt['permissions_add'] = 'Befugnis hinzufügen';
$txt['permissions_remove'] = 'Befugnis zurücksetzen';
$txt['permissions_deny'] = 'Befugnis verweigern';
$txt['permissions_select_permission'] = 'Wählen Sie eine Befugnis aus';

// All of the following block of strings should not use entities, instead use \\" for &quot; etc.
$txt['permissions_only_one_option'] = 'Sie können nur eine Aktion zur Änderung der Befugnisse auswählen';
$txt['permissions_no_action'] = 'Keine Aktion ausgewählt';
$txt['permissions_deny_dangerous'] = 'Sie sind im Begriff, eine oder mehrere Befugnisse zu verweigern.\\nDies kann gefährlich sein und unerwartete Ergebnisse erzielen, wenn Sie nicht sichergestellt haben, dass niemand \\"versehentlich\\" in der Gruppe ist, der Sie die Befugnisse entziehen.\\n\\nSind Sie sich sicher, dass Sie fortfahren möchten?';

$txt['permissions_modify_group'] = 'Gruppe ändern';
$txt['permissions_general'] = 'Allgemeine Befugnisse';
$txt['permissions_board'] = 'Standardforenprofilbefugnisse';
$txt['permissions_board_desc'] = '<strong>Hinweis</strong>: das Ändern dieser Forenbefugnisse wird alle Foren betreffen, denen derzeit das Befugnisprofil &quot;Standard&quot; zugewiesen ist. Foren, die ein anderes Profil verwenden, sind nicht betroffen.';
$txt['permissions_commit'] = 'Änderungen speichern';
$txt['permissions_on'] = 'im Profil';
$txt['permissions_local_for'] = 'Befugnisse für Gruppe';
$txt['permissions_option_on'] = 'G';
$txt['permissions_option_off'] = 'X';
$txt['permissions_option_deny'] = 'V';
$txt['permissions_option_desc'] = 'Für jede Befugnis können Sie entweder \'Gewähren\' (G), \'Verbieten\' (X) oder <span style="color: red;">\'Verweigern\' (V)</span> auswählen.<br /><br />Bedenken Sie, dass, wenn Sie eine Befugnis verweigern, jedes Mitglied - egal ob Moderator oder nicht - dieser Gruppe diese Befugnis ebenfalls nicht haben wird.<br />Aus diesem Grund sollten Sie die Verweigerung behutsam und nur dann verwenden, wenn es <strong>notwendig</strong> erscheint. Verbieten andererseits verweigert die Befugnis nur, wenn sie nicht anderweitig gewährt wurde.';

$txt['permissiongroup_general'] = 'Allgemein';
$txt['permissionname_view_stats'] = 'Forumsstatistiken ansehen';
$txt['permissionhelp_view_stats'] = 'Die Forumsstatistiken sind eine Seite, die alle Statistiken des Forums wie die Mitgliederzahl, die tägliche Anzahl an Beiträgen und verschiedene Top-10-Statistiken zusammenfasst. Die Aktivierung dieser Befugnis fügt unten auf der Startseite des Forums den Verweis \'[Mehr Statistiken]\' hinzu.';
$txt['permissionname_view_mlist'] = 'Benutzerlisten und Gruppen ansehen';
$txt['permissionhelp_view_mlist'] = 'Die Benutzerliste zeigt alle in Ihrem Forum registrierten Benutzer an. Die Liste kann sortiert und durchsucht werden. Sie wird sowohl auf der Startseite des Forums als auch auf der Statistikseite per Klick auf die Anzahl an Mitgliedern verlinkt. Dies gilt auch für die Gruppenseite, die eine Mini-Mitgliederliste dieser Gruppe ist.';
$txt['permissionname_who_view'] = 'Wer ist online? ansehen';
$txt['permissionhelp_who_view'] = '\'Wer ist online?\' zeigt alle Mitglieder an, die derzeit online sind, und was sie gerade tun. Diese Befugnis funktioniert nur, wenn Sie die Funktion auch in \'Funktionen und Optionen\' aktiviert haben. Sie können die Onlineliste abrufen, indem Sie auf den Verweis im Bereich \'Benutzer online\' auf der Startseite des Forums klicken. Selbst, wenn dies verweigert wird, können Mitglieder immer noch sehen, wer online ist, nur nicht, wo er ist.';
$txt['permissionname_search_posts'] = 'Nach Beiträgen und Themen suchen';
$txt['permissionhelp_search_posts'] = 'Die Suchbefugnis erlaubt es dem Benutzer, alle Foren zu durchsuchen, auf die er zugreifen darf. Wird diese Befugnis gewährt, so wird eine \'Suchen\'-Schaltfläche im Kopfbereich des Forums hinzugefügt.';
$txt['permissionname_karma_edit'] = 'Das Karma anderer Leute ändern';
$txt['permissionhelp_karma_edit'] = 'Karma ist eine Funktion, die die Beliebtheit eines Mitglieds widerspiegelt. Um diese Funktion zu aktivieren, muss sie unter \'Funktionen und Optionen\' freigeschaltet worden sein. Diese Befugnis erlaubt es einer Benutzergruppe, eine Stimme zu vergeben. Diese Befugnis hat keine Auswirkungen auf Gäste.';
$txt['permissionname_like_posts'] = 'Beiträge anderer Benutzer mögen';
$txt['permissionhelp_like_posts'] = '\'Gefällt mir\' ist eine Funktion, die die Beliebtheit eines Beitrags widerspiegelt. Um diese Funktion zu aktivieren, muss sie unter \'Funktionen und Optionen\' freigeschaltet worden sein. Diese Befugnis erlaubt es einer Benutzergruppe zu kennzeichnen, dass ihr ein Beitrag gefällt oder nicht mehr gefällt. Diese Befugnis hat keine Auswirkungen auf Gäste.';
$txt['permissionname_disable_censor'] = 'Wortzensur deaktivieren';
$txt['permissionhelp_disable_censor'] = 'Gewährt Mitgliedern die Möglichkeit, die Wortzensur zu deaktivieren.';

$txt['permissiongroup_pm'] = 'Private Nachrichten';
$txt['permissionname_pm_read'] = 'Private Nachrichten lesen';
$txt['permissionhelp_pm_read'] = 'Diese Befugnis erlaubt es Benutzer, ihren Posteingang zu öffnen und ihre privaten Nachrichten zu lesen. Ohne diese Befugnis kann ein Benutzer auch keine privaten Nachrichten senden.';
$txt['permissionname_pm_send'] = 'Private Nachrichten senden';
$txt['permissionhelp_pm_send'] = 'Anderen registrierten Mitgliedern private Nachrichten senden. Setzt die Befugnis \'Private Nachrichten lesen\' voraus.';
$txt['permissionname_send_email_to_members'] = 'E-Mails senden';
$txt['permissionhelp_send_email_to_members'] = 'Anderen registrierten Mitgliedern E-Mails senden.';

$txt['permissiongroup_calendar'] = 'Kalender';
$txt['permissionname_calendar_view'] = 'Den Kalender ansehen';
$txt['permissionhelp_calendar_view'] = 'Der Kalender zeigt für jeden Monat die Geburtstage, Ereignisse und Feiertage an. Diese Befugnis gewährt Zugriff auf diesen Kalender. Wenn sie gewährt wurde, wird der Leiste eine Schaltfläche hinzugefügt und unten auf der Startseite des Forums eine Liste mit heutigen und kommenden Geburtstagen, Ereignissen und Feiertagen eingeblendet. Der Kalender muss in \'Konfiguration - Kernfunktionen\' aktiviert werden.';
$txt['permissionname_calendar_post'] = 'Ereignisse im Kalender erstellen';
$txt['permissionhelp_calendar_post'] = 'Ein Ereignis ist ein Thema, das mit einem bestimmten Datum oder Zeitraum verknüpft ist. Ereignisse können aus dem Kalender heraus angelegt werden. Ein Ereignis kann nur angelegt werden, wenn der Benutzer, der das Ereignis anlegen möchte, neue Themen erstellen darf.';
$txt['permissionname_calendar_edit'] = 'Ereignisse im Kalender ändern';
$txt['permissionhelp_calendar_edit'] = 'Ein Ereignis ist ein Thema, das mit einem bestimmten Datum oder Zeitraum verknüpft ist. Das Ereignis kann per Klick auf das rote Sternchen (*) neben dem Ereignis in der Kalenderansicht geändert werden. Um ein Ereignis ändern zu können, muss ein Benutzer das Recht haben, die erste Nachricht in dem Thema, das mit dem Ereignis verknüpft ist, zu bearbeiten.';
$txt['permissionname_calendar_edit_own'] = 'Eigene Ereignisse';
$txt['permissionname_calendar_edit_any'] = 'Jegliche Ereignisse';

$txt['permissiongroup_maintenance'] = 'Forumsadministration';
$txt['permissionname_admin_forum'] = 'Forum und Datenbank administrieren';
$txt['permissionhelp_admin_forum'] = 'Diese Befugnis erlaubt es einem Benutzer,<ul class="normallist"><li>Foren-, Datenbanken- und Designeinstellungen zu ändern</li><li>Pakete zu verwalten</li><li>die Wartungswerkzeuge für Forum und Datenbank zu verwenden</li><li>die Fehler- und Moderationsprotokolle einzusehen</li></ul> Verwenden Sie diese Befugnis mit Bedacht, da sie sehr mächtig ist.';
$txt['permissionname_manage_boards'] = 'Foren und Kategorien verwalten';
$txt['permissionhelp_manage_boards'] = 'Diese Befugnis erlaubt die Erzeugung, Änderung und Entfernung von Foren und Kategorien.';
$txt['permissionname_manage_attachments'] = 'Dateianhänge und Avatare verwalten';
$txt['permissionhelp_manage_attachments'] = 'Diese Befugnis gewährt Zugang zum Anhangszentrum, wo alle Dateianhänge und Avatare des Forums aufgelistet werden und entfernt werden können.';
$txt['permissionname_manage_smileys'] = 'Smileys und Nachrichtensymbole verwalten';
$txt['permissionhelp_manage_smileys'] = 'Dies gewährt den Zugang zum Smileyzentrum. Dort können Sie Smileys und Smileysätze hinzufügen, ändern und löschen. Wenn Sie angepasste Nachrichtensymbole aktiviert haben, können Sie mit dieser Befugnis auch diese hinzufügen und ändern.';
$txt['permissionname_edit_news'] = 'Neuigkeiten ändern';
$txt['permissionhelp_edit_news'] = 'Die Neuigkeitenfunktion erlaubt es einer zufälligen Schlagzeile, auf jedem Bildschirm zu erscheinen. Um sie zu verwenden, aktivieren Sie sie in den Forumseinstellungen.';
$txt['permissionname_access_mod_center'] = 'Zugriff auf das Moderationszentrum';
$txt['permissionhelp_access_mod_center'] = 'Mit dieser Befugnis können alle Mitglieder dieser Gruppe auf das Moderationszentrum zugreifen, von wo aus sie Zugang zu Funktionen haben, die die Moderation vereinfachen. Beachten Sie, dass dies noch keine Moderationsbefugnisse einräumt.';

$txt['permissiongroup_member_admin'] = 'Benutzeradministration';
$txt['permissionname_moderate_forum'] = 'Mitglieder des Forums moderieren';
$txt['permissionhelp_moderate_forum'] = 'Diese Befugnis beinhaltet alle wichtigen Mitgliedsmoderationsfunktionen:<ul class="normallist"><li>Zugriff auf die Registrierungsverwaltung</li><li>Zugriff auf die Oberfläche zum Ansehen und Löschen von Mitgliedern</li><li>ausführliche Profilinformationen einschlißenlich der IP-/Benutzerverfolgung und des (versteckten) Onlinestatus\'</li><li>Konten aktivieren</li><li>Freischaltungsbenachrichtigungen bekommen und Konten freischalten</li><li>Immunität gegenüber PN-Ignoranz</li><li>diverse kleine Dinge</li></ul>';
$txt['permissionname_manage_membergroups'] = 'Benutzergruppen verwalten und zuweisen';
$txt['permissionhelp_manage_membergroups'] = 'Diese Befugnis erlaubt es einem Benutzer, Benutzergruppen zu ändern und sie Mitgliedern zuzuweisen.';
$txt['permissionname_manage_permissions'] = 'Befugnisse verwalten';
$txt['permissionhelp_manage_permissions'] = 'Diese Befugnis erlaubt es einem Benutzer, alle Befugnisse einer Benutzergruppe, global oder für einzelne Foren zu ändern.';
$txt['permissionname_manage_bans'] = 'Sperrliste verwalten';
$txt['permissionhelp_manage_bans'] = 'Diese Befugnis erlaubt es einem Benutzer, Benutzernamen, IP-Adressen, Hostnamen und E-Mail-Adresse der Sperrliste hinzuzufügen oder sie von ihr zu entfernen. Zudem erlaubt sie es ihm, Protokolleinträge gesperrter Benutzer, die sich anzumelden versuchen, anzusehen und zu entfernen.';
$txt['permissionname_send_mail'] = 'Eine Rundnachricht an Mitglieder senden';
$txt['permissionhelp_send_mail'] = 'Allen Mitgliedern des Forums oder nur einigen Benutzergruppen eine E-Mail oder private Nachricht (dies setzt die \'Private Nachrichten senden\'-Befugnis voraus) senden.';
$txt['permissionname_issue_warning'] = 'Mitglieder verwarnen';
$txt['permissionhelp_issue_warning'] = 'Mitgliedern des Forums eine Verwarnung aussprechen und deren Warnstufe ändern. Setzt voraus, dass das Verwarnungssystem aktiviert ist.';

$txt['permissiongroup_profile'] = 'Mitgliedsprofile';
$txt['permissionname_profile_view'] = 'Profilzusammenfassung und Statistiken ansehen';
$txt['permissionhelp_profile_view'] = 'Diese Befugnis ermöglicht es, per Klick auf einen Benutzernamen eine Zusammenfassung der Profileinstellungen, ein paar Statistiken und alle Beiträge des Benutzers zu sehen.';
$txt['permissionname_profile_view_own'] = 'Eigenes Profil';
$txt['permissionname_profile_view_any'] = 'Jedes Profil';
$txt['permissionname_profile_identity'] = 'Kontoeinstellungen ändern';
$txt['permissionhelp_profile_identity'] = 'Kontoeinstellungen sind die grundlegenden Einstellungen eines Profils wie das Passwort, die E-Mail-Adresse, Benutzergruppen und die bevorzugte Sprache.';
$txt['permissionname_profile_identity_own'] = 'Eigenes Profil';
$txt['permissionname_profile_identity_any'] = 'Jegliches Profil';
$txt['permissionname_profile_extra'] = 'Weitere Profileinstellungen ändern';
$txt['permissionhelp_profile_extra'] = 'Weitere Profileinstellungen beinhalten Einstellungen für Avatare, Designeinstellungen, Benachrichtigungen und private Nachrichten.';
$txt['permissionname_profile_extra_own'] = 'Eigenes Profil';
$txt['permissionname_profile_extra_any'] = 'Jegliches Profil';
$txt['permissionname_profile_title'] = 'Eigenen Titel ändern'; // translator note: could there be a better term?
$txt['permissionhelp_profile_title'] = 'Der eigene Titel wird in der Themenübersicht unter dem Profil jedes Benutzers angezeigt, der einen solchen angelegt hat.';
$txt['permissionname_profile_title_own'] = 'Eigenes Profil';
$txt['permissionname_profile_title_any'] = 'Jegliches Profil';
$txt['permissionname_profile_remove'] = 'Konto löschen';
$txt['permissionhelp_profile_remove'] = 'Diese Befugnis erlaubt einem Benutzer die Löschung von Benutzerkonten.'; // translator note: original text is too specific here.
$txt['permissionname_profile_remove_own'] = 'Eigenes Konto';
$txt['permissionname_profile_remove_any'] = 'Jegliches Konto';
$txt['permissionname_profile_server_avatar'] = 'Auswahl eines Avatars vom Server';
$txt['permissionhelp_profile_server_avatar'] = 'Sofern aktiviert, erlaubt dies einem Benutzer, einen Avatar aus den Avatarsammlungen auf dem Server auszuwählen.';
$txt['permissionname_profile_upload_avatar'] = 'Hochladen eines Avatars auf den Server';
$txt['permissionhelp_profile_upload_avatar'] = 'Diese Befugnis erlaubt es einem Benutzer, seinen eigenen Avatar auf den Server hochzuladen.';
$txt['permissionname_profile_remote_avatar'] = 'Auswahl eines anderswo hochgeladenen Avatars';
$txt['permissionhelp_profile_remote_avatar'] = 'Weil Avatare die zum Erstellen der Seite benötigte Zeit negativ beeinflussen können, ist es möglich, bestimmten Benutzergruppen die Verwendung von extern gehosteten Avataren zu verbieten.';

$txt['permissiongroup_general_board'] = 'Allgemein';
$txt['permissionname_moderate_board'] = 'Das Forum moderieren'; // translator note: "das" vs. "eine" to make the difference...
$txt['permissionhelp_moderate_board'] = 'Diese Befugnis fügt ein paar kleine Berechtigungen hinzu, mittels derer der Benutzer volle Moderationsrechte bekommt. Diese Berechtigungen beinhalten das Antworten auf gesperrte Themen, die Änderung der Laufzeit von Umfragen und das Ansehen von Umfrageergebnissen.';

$txt['permissiongroup_topic'] = 'Themen';
$txt['permissionname_post_new'] = 'Neue Themen erstellen';
$txt['permissionhelp_post_new'] = 'Diese Befugnis erlaubt es einem Benutzer, neue Themen zu erstellen. Das Antworten auf bestehende Themen ist nicht enthalten.';
$txt['permissionname_merge_any'] = 'Jegliches Thema zusammenführen';
$txt['permissionhelp_merge_any'] = 'Zwei oder mehr Themen in eines zusammenführen. Die Reihenfolge der Beiträge im zusammengeführten Thema wird abhängig von dem Zeitpunkt ihrer Erstellung sein. Ein Benutzer kann nur Themen in Foren zusammenführen, in denen er diese Befugnis erhalten hat. Um mehrere Themen gleichzeitig zusammenführen zu können, muss ein Benutzer Schnellmoderation in seinem Profil aktivieren.';
$txt['permissionname_split_any'] = 'Jegliches Thema aufteilen';
$txt['permissionhelp_split_any'] = 'Ein Thema in zwei verschiedene Themen aufteilen.';
$txt['permissionname_send_topic'] = 'Themen Freunden senden';
$txt['permissionhelp_send_topic'] = 'Diese Befugnis ermöglicht es einem Benutzer, ein Thema mittels Eingabe der E-Mail-Adresse einem Freund zu senden und eine Nachricht beizufügen.';
$txt['permissionname_make_sticky'] = 'Themen anheften';
$txt['permissionhelp_make_sticky'] = 'Angeheftete Themen sind Themen, die in einem Forum immer oben stehen. Sie können für Ankündigungen oder andere wichtige Mitteilungen nützlich sein.';
$txt['permissionname_move'] = 'Themen verschieben';
$txt['permissionhelp_move'] = 'Ein Thema aus einem Forum in ein anderes verschieben. Benutzer können als Ziel nur Foren auswählen, auf die sie zugreifen dürfen.';
$txt['permissionname_move_own'] = 'Eigene Themen';
$txt['permissionname_move_any'] = 'Jegliche Themen';
$txt['permissionname_lock'] = 'Themen sperren';
$txt['permissionhelp_lock'] = 'Diese Befugnis erlaubt es einem Benutzer, ein Thema zu sperren. Dies kann getan werden, um sicherzustellen, dass niemand auf ein Thema antworten kann. Nur Benutzer mit der \'Forum moderieren\'-Befugnis können weiterhin auf gesperrte Themen antworten.';
$txt['permissionname_lock_own'] = 'Eigene Themen';
$txt['permissionname_lock_any'] = 'Jegliche Themen';
$txt['permissionname_remove'] = 'Themen entfernen';
$txt['permissionhelp_remove'] = 'Themen vollständig löschen. Beachten Sie, dass diese Befugnis das Löschen einzelner Beiträge in einem Thema nicht erlaubt!';
$txt['permissionname_remove_own'] = 'Eigene Themen';
$txt['permissionname_remove_any'] = 'Jegliche Themen';
$txt['permissionname_post_reply'] = 'Auf Themen antworten';
$txt['permissionhelp_post_reply'] = 'Diese Befugnis erlaubt das Antworten auf Themen.';
$txt['permissionname_post_reply_own'] = 'Eigene Themen';
$txt['permissionname_post_reply_any'] = 'Jegliche Themen';
$txt['permissionname_modify_replies'] = 'Antworten auf eigene Themen bearbeiten';
$txt['permissionhelp_modify_replies'] = 'Diese Befugnis erlaubt es einem Benutzer, der ein Thema eröffnet hat, alle Antworten auf dieses Thema zu ändern.';
$txt['permissionname_delete_replies'] = 'Antworten auf eigene Themen löschen';
$txt['permissionhelp_delete_replies'] = 'Diese Befugnis erlaubt es einem Benutzer, der ein Thema eröffnet hat, alle Antworten auf dieses Thema zu löschen.';
$txt['permissionname_announce_topic'] = 'Thema ankündigen';
$txt['permissionhelp_announce_topic'] = 'Dies erlaubt es einem Benutzer, eine Ankündigungs-E-Mail für ein Thema allen Mitgliedern oder ausgewählten Benutzergruppen zu senden.';

$txt['permissionname_approve_emails'] = 'Verfassen-per-E-Mail-Fehler moderieren';
$txt['permissionhelp_approve_emails'] = 'Erlaubt es Moderatoren, auf das Verfassen-per-E-Mail-Protokoll zuzugreifen, um Aktionen wie Freischalten, Löschen, Ansehen und Weiterleiten durchzuführen.  Beachten Sie, dass, da das System möglicherweise nicht immer weiß, in welches Forum ein Beitrag gesendet werden soll, diese Befugnis nur Mitgliedern mit vollem Forenzugriff gegeben werden sollte';
$txt['permissionname_postby_email'] = 'Verfassen per E-Mail';
$txt['permissionhelp_postby_email'] = 'Diese Befugnis erlaubt es Benutzern, per E-Mail sowohl neue Themen zu starten als auch auf Themen und private Nachrichten zu antworten.';

$txt['permissiongroup_post'] = 'Beiträge';
$txt['permissionname_delete'] = 'Beiträge löschen';
$txt['permissionhelp_delete'] = 'Beiträge entfernen. Davon ausgenommen ist jeweils der erste Beitrag in einem Thema.';
$txt['permissionname_delete_own'] = 'Eigene Beiträge';
$txt['permissionname_delete_any'] = 'Jegliche Beiträge';
$txt['permissionname_modify'] = 'Beiträge ändern';
$txt['permissionhelp_modify'] = 'Beiträge ändern'; // translator note: as if there was such a huge difference between editing and modifying a post...
$txt['permissionname_modify_own'] = 'Eigene Beiträge';
$txt['permissionname_modify_any'] = 'Jegliche Beiträge';
$txt['permissionname_report_any'] = 'Beiträge den Moderatoren melden';
$txt['permissionhelp_report_any'] = 'Diese Befugnis fügt jedem Beitrag einen Verweis hinzu, der es einem Benutzer ermöglicht, ihn einem Moderator zu melden. Infolge einer solchen Meldung bekommen alle Moderatoren für das jeweilige Forum eine E-Mail mit einem Verweis auf den gemeldeten Beitrag und einer Beschreibung des Problems (wie vom meldenden Benutzer angegeben).';

$txt['permissiongroup_poll'] = 'Umfragen';
$txt['permissionname_poll_view'] = 'Umfragen ansehen';
$txt['permissionhelp_poll_view'] = 'Diese Befugnis erlaubt es einem Benutzer, eine Umfrage anzusehen. Ohne sie wird er nur das zugehörige Thema sehen.';
$txt['permissionname_poll_vote'] = 'An Umfragen teilnehmen';
$txt['permissionhelp_poll_vote'] = 'Diese Befugnis erlaubt es einem (registrierten) Benutzer, in Umfragen abzustimmen. Sie hat keine Auswirkung auf Gäste.';
$txt['permissionname_poll_post'] = 'Umfragen starten';
$txt['permissionhelp_poll_post'] = 'Diese Befugnis erlaubt es einem Benutzer, eine neue Umfrage zu veröffentlichen. Sie erfordert die \'Neue Themen erstellen\'-Befugnis.';
$txt['permissionname_poll_add'] = 'Umfrage zu Themen hinzufügen';
$txt['permissionhelp_poll_add'] = 'Dies erlaubt es einem Benutzer, nach der Eröffnung eines Themas eine Umfrage hinzuzufügen. Sie setzt ausreichende Rechte zur Änderung des ersten Beitrags in einem Thema voraus.';
$txt['permissionname_poll_add_own'] = 'Eigene Themen';
$txt['permissionname_poll_add_any'] = 'Jegliche Themen';
$txt['permissionname_poll_edit'] = 'Umfragen ändern';
$txt['permissionhelp_poll_edit'] = 'Diese Befugnis erlaubt es einem Benutzer, die Optionen einer Umfrage zu ändern und sie zurückzusetzen. Um die maximale Anzahl an Stimmen und die Laufzeit einer Umfrage zu ändern, muss ein Benutzer die \'Forum moderieren\'-Befugnis haben.';
$txt['permissionname_poll_edit_own'] = 'Eigene Umfragen';
$txt['permissionname_poll_edit_any'] = 'Jegliche Umfragen';
$txt['permissionname_poll_lock'] = 'Umfragen schließen';
$txt['permissionhelp_poll_lock'] = 'Das Schließen einer Umfrage lässt sie keine weiteren Stimmen mehr aufnehmen.';
$txt['permissionname_poll_lock_own'] = 'Eigene Umfragen';
$txt['permissionname_poll_lock_any'] = 'Jegliche Umfragen';
$txt['permissionname_poll_remove'] = 'Umfragen entfernen';
$txt['permissionhelp_poll_remove'] = 'Diese Befugnis erlaubt die Entfernung von Umfragen.';
$txt['permissionname_poll_remove_own'] = 'Eigene Umfragen';
$txt['permissionname_poll_remove_any'] = 'Jegliche Umfragen';

$txt['permissionname_post_draft'] = 'Entwürfe neuer Beiträge speichern';
$txt['permissionname_simple_post_draft'] = 'Entwürfe neuer Beiträge speichern';
$txt['permissionhelp_post_draft'] = 'Diese Befugnis erlaubt es Benutzern, Entwürfe ihrer Beiträge zu speichern, so dass sie sie später fertigstellen können.';
$txt['permissionhelp_simple_post_draft'] = 'Diese Befugnis erlaubt es Benutzern, Entwürfe ihrer Beiträge zu speichern, so dass sie sie später fertigstellen können.';
$txt['permissionname_post_autosave_draft'] = 'Automatisch Entwürfe neuer Beiträge speichern';
$txt['permissionname_simple_post_autosave_draft'] = 'Automatisch Entwürfe neuer Beiträge speichern';
$txt['permissionhelp_post_autosave_draft'] = 'Diese Befugnis erlaubt es Benutzern, ihre Beiträge als Entwürfe automatisch speichern zu lassen, so dass sie es vermeiden können, dass ihre Arbeit bei Zeitüberschreitung, Verbindungstrennung oder anderen Fehlern verloren geht.  Die Zeitplanung für die automatische Speicherung wird im Administrationsbereich festgelegt.';
$txt['permissionhelp_simple_post_autosave_draft'] = 'Diese Befugnis erlaubt es Benutzern, ihre Beiträge als Entwürfe automatisch speichern zu lassen, so dass sie es vermeiden können, dass ihre Arbeit bei Zeitüberschreitung, Verbindungstrennung oder anderen Fehlern verloren geht.  Die Zeitplanung für die automatische Speicherung wird im Administrationsbereich festgelegt.';
$txt['permissionname_pm_autosave_draft'] = 'Automatisch Entwürfe neuer privater Nachrichten speichern';
$txt['permissionname_simple_pm_autosave_draft'] = 'Automatisch Entwürfe neuer privater Nachrichten speichern';
$txt['permissionhelp_pm_autosave_draft'] = 'Diese Befugnis erlaubt es Benutzern, ihre privaten Nachrichten als Entwürfe automatisch speichern zu lassen, so dass sie es vermeiden können, dass ihre Arbeit bei Zeitüberschreitung, Verbindungstrennung oder anderen Fehlern verloren geht.  Die Zeitplanung für die automatische Speicherung wird im Administrationsbereich festgelegt';
$txt['permissionhelp_simple_post_autosave_draft'] = 'Diese Befugnis erlaubt es Benutzern, ihre privaten Nachrichten als Entwürfe automatisch speichern zu lassen, so dass sie es vermeiden können, dass ihre Arbeit bei Zeitüberschreitung, Verbindungstrennung oder anderen Fehlern verloren geht.  Die Zeitplanung für die automatische Speicherung wird im Administrationsbereich festgelegt';
$txt['permissionname_pm_draft'] = 'Entwürfe prívater Nachrichten speichern';
$txt['permissionname_simple_pm_draft'] = 'Entwürfe prívater Nachrichten speichern';
$txt['permissionhelp_pm_draft'] = 'Diese Befugnis erlaubt es Benutzern, Entwürfe ihrer privaten Nachrichten zu speichern, so dass sie sie später fertigstellen können.';
$txt['permissionhelp_simple_pm_draft'] = 'Diese Befugnis erlaubt es Benutzern, Entwürfe ihrer privaten Nachrichten zu speichern, so dass sie sie später fertigstellen können.';

$txt['permissiongroup_approval'] = 'Beitragsmoderation';
$txt['permissionname_approve_posts'] = 'Beiträge in Moderationswarteschlange freischalten';
$txt['permissionhelp_approve_posts'] = 'Diese Befugnis gewährt es einem Benutzer, nicht freigeschaltete Beiträge in einem Forum freizuschalten.';
$txt['permissionname_post_unapproved_replies'] = 'Antworten auf Themen schreiben, aber bis zur Freischaltung verstecken';
$txt['permissionhelp_post_unapproved_replies'] = 'Diese Befugnis erlaubt es einem Benutzer, auf bestehende Themen zu antworten.  Die Antworten werden bis zur Freischaltung seitens eines Moderators nicht angezeigt.';
$txt['permissionname_post_unapproved_replies_own'] = 'Eigene Themen';
$txt['permissionname_post_unapproved_replies_any'] = 'Jegliche Themen';
$txt['permissionname_post_unapproved_topics'] = 'Neue Themen erstellen, aber bis zur Freischaltung verstecken';
$txt['permissionhelp_post_unapproved_topics'] = 'Diese Befugnis erlaubt es einem Benutzer, ein neues Thema zu erstellen, das freigeschaltet werden muss, bevor es angezeigt wird.';
$txt['permissionname_post_unapproved_attachments'] = 'Dateien anhängen, aber bis zur Freischaltung verstecken';
$txt['permissionhelp_post_unapproved_attachments'] = 'Diese Befugnis erlaubt es einem Benutzer, Dateien an seine Beiträge anzuhängen. Die angehängten Dateien müssen freigeschaltet werden, bevor sie anderen Benutzern angezeigt werden.';

$txt['permissiongroup_notification'] = 'Benachrichtigungen';
$txt['permissionname_mark_any_notify'] = 'Bei Antworten benachrichtigen lassen';
$txt['permissionhelp_mark_any_notify'] = 'Diese Funktion erlaubt es einem Benutzer, eine E-Mail-Benachrichtigung zu erhalten, sobald jemand auf ein von ihm abonniertes Thema antwortet.';
$txt['permissionname_mark_notify'] = 'Bei neuen Themen benachrichtigen lassen';
$txt['permissionhelp_mark_notify'] = 'Diese Funktion erlaubt es einem Benutzer, eine E-Mail-Benachrichtigung zu erhalten, sobald in einem von ihm abonnierten Forum ein neues Thema eröffnet wird.';

$txt['permissiongroup_attachment'] = 'Dateianhänge';
$txt['permissionname_view_attachments'] = 'Dateianhänge ansehen';
$txt['permissionhelp_view_attachments'] = 'Dateianhänge sind Dateien, die an Beiträge angehängt werden. Diese Funktion kann in \'Dateianhänge und Avatare\' aktiviert und eingestellt werden. Da Dateianhänge nicht direkt abgerufen werden, können Sie sie davor schützen, von unbefugten Benutzern heruntergeladen zu werden.';
$txt['permissionname_post_attachment'] = 'Dateianhänge veröffentlichen';
$txt['permissionhelp_post_attachment'] = 'Dateianhänge sind Dateien, die an Beiträge angehängt werden. Ein Beitrag kann mehrere Dateianhänge haben.';

$txt['permissionicon'] = '';

$txt['permission_settings_title'] = 'Befugniseinstellungen';
$txt['groups_manage_permissions'] = 'Benutzergruppen, die Befugnisse verwalten dürfen';
$txt['permission_settings_enable_deny'] = 'Aktivieren Sie diese Option, um Befugnisse zu verweigern';
// Escape any single quotes in here twice.. 'it\'s' -> 'it\\\'s'.
$txt['permission_disable_deny_warning'] = 'Das Abschalten dieser Option wird \\\'Verweigern\\\'-Befugnisse zu \\\'Verbieten\\\' ändern.';
$txt['permission_by_board_desc'] = 'Hier können Sie festlegen, welches Befugnisprofil ein Forum benutzt. Weitere Befugnisprofile können Sie im Menü &quot;Profile ändern&quot; anlegen.';
$txt['permission_settings_desc'] = 'Hier können Sie festlegen, wer die Befugnis zur Änderung von Befugnissen hat und wie durchdacht das Befugnissystem sein soll.';
$txt['permission_settings_enable_postgroups'] = 'Befugnisse für beitragsbasierte Gruppen aktivieren';
// Escape any single quotes in here twice.. 'it\'s' -> 'it\\\'s'.
$txt['permission_disable_postgroups_warning'] = 'Die Deaktivierung dieser Einstellung wird derzeitige Befugnisse beitragsbasierter Gruppen entfernen.';

$txt['permissions_post_moderation_desc'] = 'Auf dieser Seite können Sie einfach profilbasiert ändern, welche Gruppen nur moderierte Beiträge schreiben dürfen.';
$txt['permissions_post_moderation_deny_note'] = 'Beachten Sie, dass Ihnen, während Sie erweiterte Befugnisse verwenden, die &quot;verweigern&quot;-Befugnis auf dieser Seite nicht zur Verfügung steht. Bitte ändern Sie die Befugnisse direkt, wenn Sie eine Befugnis gezieht verweigern möchten.';
$txt['permissions_post_moderation_select'] = 'Profil auswählen';
$txt['permissions_post_moderation_new_topics'] = 'Neue Themen';
$txt['permissions_post_moderation_replies_own'] = 'Eigene Antworten';
$txt['permissions_post_moderation_replies_any'] = 'Jegliche Antworten';
$txt['permissions_post_moderation_attachments'] = 'Dateianhänge';
$txt['permissions_post_moderation_legend'] = 'Legende';
$txt['permissions_post_moderation_allow'] = 'Kann erstellen';
$txt['permissions_post_moderation_moderate'] = 'Kann erstellen, muss jedoch freigeschaltet werden';
$txt['permissions_post_moderation_disallow'] = 'Kann nicht erstellen';
$txt['permissions_post_moderation_group'] = 'Gruppe';

$txt['auto_approve_topics'] = 'Neue Themen ohne nötige Freischaltung eröffnen';
$txt['auto_approve_replies'] = 'Auf Themen ohne nötige Freischaltung antworten';
$txt['auto_approve_attachments'] = 'Dateien ohne nötige Freischaltung anhängen';
