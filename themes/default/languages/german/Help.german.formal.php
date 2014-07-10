<?php
// Version: 1.0; Help

global $helptxt;

$txt['close_window'] = 'Fenster schließen';

$helptxt['manage_boards'] = '
	<strong>Foren ändern</strong><br />
	In diesem Menü können Sie Foren und die Kategorien über ihnen
	erstellen/umsortieren/entfernen. Wenn Sie zum Beispiel eine Website
	mit vielen Themen wie &quot;Sport&quot; und &quot;Autos&quot; und &quot;Musik&quot; betreiben, wären dies
	die Oberkategorien, die Sie erstellen. Unter jeder dieser Kategorien
	möchten Sie wahrscheinlich hierarchische &quot;Unterkategorien&quot; oder Foren
	für jedes einzelne Thema anlegen. Dies ist eine einfache Hierarchie
	mit folgender Struktur:<br />
	<ul class="normallist">
		<li>
			<strong>Sport</strong>
			&nbsp;- Eine &quot;Kategorie&quot;
			<ul class="normallist">
				<li>
					<strong>Handball</strong>
					&nbsp;- Ein Forum unterhalb der &quot;Sport&quot;-Kategorie
					<ul class="normallist">
						<li>
							<strong>Ergebnisse</strong>
							&nbsp;- Ein Unterforum unterhalb des &quot;Handball&quot;-Forums
						</li>
					</ul>
				</li>
				<li><strong>Fußball</strong>
				&nbsp;- Ein Forum unterhalb der &quot;Sport&quot;-Kategorie</li>
			</ul>
		</li>
	</ul>
	Kategorien ermöglichen es Ihnen, das Forum in Themenbereiche (&quot;Autos&quot;,
	&quot;Sport&quot;) aufzuteilen, und die &quot;Foren&quot; darunter sind die eigentlichen
	Themen, in denen Mitglieder Beiträge schreiben können. Ein Benutzer,
	der sich für Smarts interessiert, würde zum Beispiel einen Beitrag
	unter &quot;Autos&rarr;Smart&quot; schreiben. Kategorien erlauben es Mitgliedern,
	ihre Interessen schnell zu finden: Anstelle eines &quot;Ladens&quot; können Sie
	etwa getrennte &quot;Geräte&quot;- und &quot;Bekleidungs&quot;läden aufführen. Dies
	würde die Suche nach bestimmten Produkten vereinfachen, denn wenn ein
	Benutzer sich zum Beispiel für Schraubenzieher interessiert, so kann
	er direkt in den &quot;Geräteladen&quot; gehen, statt einen Umweg über das
	&quot;Bekleidungsgeschäft&quot; machen zu müssen, wo es vermutlich keinen
	Schraubenzieher gibt.<br />
	Ein Unterforum, wie oben angemerkt, ist ein Schlüsselthema innerhalb
	einer breiten Kategorie. Wenn Sie über &quot;Smarts&quot; diskutieren möchten,
	gehen Sie direkt in die &quot;Auto&quot;-Kategorie und springen Sie dort in das
	&quot;Smart&quot;-Forum.<br />
	Administrative Funktionen für diesen Menüeintrag dienen dem Zweck, in
	jeder Kategorie neue Foren zu erstellen, sie umzusortieren (&quot;Smart&quot;
	etwa hinter &quot;S-Klasse&quot; zu platzieren) oder das Forum vollständig zu
	löschen.';

$helptxt['edit_news'] = '
	<ul class="normallist">
		<li>
			<strong>Neuigkeiten</strong><br />
			Dieser Bereich ermöglicht es Ihnen, den Text der Neuigkeiten, die auf der Startseite des Forums angezeigt werden, festzulegen.
			Fügen Sie ein Element Ihrer Wahl ein (zum Beispiel &quot;Verpassen Sie nicht die Konferenz am Dienstag&quot;). Jede Neuigkeit wird zufällig angezeigt und sollte in einem eigenen Feld platziert werden.
		</li>
		<li>
			<strong>Rundbriefe</strong><br />
			Dieser Bereich ermöglicht es Ihnen, per privater Nachricht oder E-Mail Rundbriefe an die Mitglieder des Forums zu versenden. Wählen Sie zunächst die Gruppen aus, die den Rundbrief erhalten oder nicht erhalten sollen. Auf Wunsch können Sie auch weitere Mitglieder und E-Mail-Adressen hinzufügen. Geben Sie dann die Nachricht ein, die Sie versenden möchten, und wählen Sie aus, ob sie per privater Nachricht oder per E-Mail versandt werden soll.
		</li>
		<li>
			<strong>Einstellungen</strong><br />
				Dieser Bereich enthält einige Einstellungen bezüglich Neuigkeiten und Rundbriefen, darunter, welche Gruppen Neuigkeiten ändern oder Rundbriefe versenden dürfen. Es gibt auch eine Einstellung zur Aktivierung von Newsfeeds im Forum und eine zur Konfiguration der Länge (wie viele Zeichen angezeigt werden) jedes Eintrags in einem Newsfeed.
		</li>
	</ul>'; // translator note: "Newsfeed" seems to be a common "German" word...?

$helptxt['view_members'] = '
	<ul class="normallist">
		<li>
			<strong>Alle Mitglieder ansehen</strong><br />
			Hier können Sie alle Mitglieder des Forums ansehen. Ihnen wird eine verlinkte Liste der Mitgliedsnamen
			präsentiert. Sie können auf jeden der Namen klicken, um Details über die Mitglieder (Website, Alter und
			so weiter) zu erfahren, als Administrator können Sie obendrein diese Eigenschaften ändern. Sie haben
			die volle Kontrolle über die Mitglieder, dies schließt die Möglichkeit, sie aus dem Forum zu löschen,
			ein.<br /><br />
		</li>
		<li>
			<strong>Wartet auf Freischaltung</strong><br />
			Dieser Bereich wird nur angezeigt, wenn Sie die administrative Freischaltung aller neuen Registrierungen aktiviert haben.
			Jeder, der sich in Ihrem Forum registriert, wird erst dann ein vollwertiges Mitglied, wenn er von einem Administrator
			überprüft und freigeschaltet worden ist. Der Bereich listet alle Mitglieder, die noch darauf warten, sowie deren E-Mail-
			und IP-Adressen auf. Sie können jedes Mitglied auf der Liste entweder akzeptieren oder ablehnen (löschen), indem Sie den
			Auswahlkasten neben ihm aktivieren und die jeweilige Aktion aus dem Auswahlfeld am Ende der Seite auswählen. Wenn Sie ein
			Mitglied ablehnen, können Sie es mit oder ohne Benachrichtigung über Ihre Entscheidung löschen.<br /><br />
		</li>
		<li>
			<strong>Wartet auf Aktivierung</strong><br />
			Dieser Bereich ist nur sichtbar, wenn Sie die Aktivierung aller neuen Benutzerkonten im Forum zur Pflicht gemacht haben. Es
			werden alle Mitglieder, die ihr Konto noch nicht aktiviert haben, aufgelistet. Auf dieser Seite können Sie Mitglieder mit
			ausstehender Aktivierung händisch aktivieren, ablehnen oder an die Aktivierung erinnern. Wie oben können Sie den Benutzer
			auch per E-Mail über Ihr Handeln informieren.<br /><br />
		</li>
	</ul>';

$helptxt['ban_members'] = '<strong>Mitglieder sperren</strong><br />
	Dies stellt die Fähigkeit bereit, Benutzer zu &quot;sperren&quot;, um Leute, die das Vertrauen der Administration
	mittels Spammens, Trollens und dergleichen missbraucht haben, an ihrem Treiben zu hindern. Dies erlaubt es Ihnen,
	diejenigen Benutzer auszusperren, die Ihrem Forum Schaden zufügen möchten. Als Administrator können Sie, wenn Sie
	Beiträge ansehen, die IP-Adresse sehen, die jeder Benutzer zum jeweiligen Zeitpunkt verwendet hat. In der Sperrliste
	können Sie dann einfach diese IP-Adresse eingeben und speichern, und schon kann der jeweilige Benutzer von diesem
	Ort aus keine weiteren Beiträge tätigen.<br />
	Sie können Personen auch anhand ihrer E-Mail-Adresse sperren.';

$helptxt['featuresettings'] = '<strong>Funktionen und Optionen</strong><br />
	In diesem Bereich stehen verschiedene Funktionen zur Verfügung, die an Ihre Vorlieben angepasst werden können.';

$helptxt['securitysettings'] = '<strong>Sicherheit und Moderation</strong><br />
	Dieser Bereich beinhaltet Einstellungen bezüglich der Sicherheit und der Moderation Ihres Forums.';

$helptxt['addonsettings'] = '<strong>Erweiterungseinstellungen</strong><br />
	Dieser Bereich sollte jegliche Einstellungen beinhalten, die von installierten Erweiterungen Ihres Forums hinzugefügt wurden.';

$helptxt['time_format'] = '<strong>Zeitformat</strong><br />
	Sie haben die Macht zu entscheiden, wie Zeit und Datum für Sie aussehen. Es gibt viele kleine Buchstaben, aber es ist ziemlich einfach.
	Die Konventionen folgen PHPs strftime-Funktion und werden unten beschrieben (weitere Details können Sie auf <a href="http://de3.php.net/manual/de/function.strftime.php">php.net</a> finden).<br />
	<br />
	Folgende Zeichen werden in der Formatierungszeichenkette erkannt:<br />
	<span class="smalltext">
	&nbsp;&nbsp;%a - kurzer Wochentagsname<br />
	&nbsp;&nbsp;%A - vollständiger Wochentagsname<br />
	&nbsp;&nbsp;%b - kurzer Monatsname<br />
	&nbsp;&nbsp;%B - vollständiger Monatsname<br />
	&nbsp;&nbsp;%d - Tag des Monats (01 bis 31) <br />
	&nbsp;&nbsp;%D<strong>*</strong> - wie %m/%d/%y <br />
	&nbsp;&nbsp;%e<strong>*</strong> - Tag des Monats (1 bis 31) <br />
	&nbsp;&nbsp;%H - Stunden im 24-Stunden-Format (von 00 bis 23) <br />
	&nbsp;&nbsp;%I - Stunden im 12-Stunden-Format (von 01 bis 12) <br />
	&nbsp;&nbsp;%m - Monat als Zahl (01 bis 12) <br />
	&nbsp;&nbsp;%M - Minuten als Zahl <br />
	&nbsp;&nbsp;%p - je nach Uhrzeit entweder &quot;am&quot; (vormittags) oder &quot;pm&quot; (nachmittags)<br />
	&nbsp;&nbsp;%R<strong>*</strong> - Zeit im 24-Stunden-Format<br />
	&nbsp;&nbsp;%S - Sekunden als Dezimalzahl<br />
	&nbsp;&nbsp;%T<strong>*</strong> - aktuelle Uhrzeit, identisch mit %H:%M:%S <br />
	&nbsp;&nbsp;%y - zweistelliges Jahr (00 bis 99) <br />
	&nbsp;&nbsp;%Y - vierstelliges Jahr<br />
	&nbsp;&nbsp;%% - das Zeichen \'%\'<br />
	<br />
	<em>* Funktioniert nicht auf Windows-Servern.</em></span>';

$helptxt['deleteAccount_posts'] = 'Nur Antworten: Dies wird nur die Beiträge entfernen, die dieses Mitglied als Antwort auf andere Beiträge veröffentlicht hat.<br />
	Themen und Antworten: Dies wird ebenso verfahren, außerdem wird es alle von diesem Mitglied eröffneten Themen entfernen.';

$helptxt['live_news'] = '<strong>Liveankündigungen</strong><br />
	Dieser Kasten zeigt kürzlich aktualisierte Ankündigungen von <a href="http://www.elkarte.net/" target="_blank" class="new_win">www.elkarte.net/</a> an.
	Hier erhalten Sie gelegentlich Informationen zu Aktualisierungen und neuen Versionen sowie wichtige Informationen über ElkArte.';

$helptxt['registrations'] = '<strong>Registrierungsverwaltung</strong><br />
	Dieser Bereich beinhaltet alle nötigen Funktionen, um Neuanmeldungen im Forum zu verwalten. Er enthält bis zu vier Bereiche, die
	abhängig von Ihren Forumseinstellungen sichtbar sind. Dies sind:<br /><br />
	<ul class="normallist">
		<li>
			<strong>Neues Mitglied registrieren</strong><br />
			Auf dieser Seite können Sie Konten für neue Mitglieder anlegen. Dies kann in Foren nützlich sein, in denen die Registrierung neuer Benutzer
			geschlossen wurde, oder wenn ein Administrator ein Testkonto anlegen möchte. Falls eine separate Aktivierung des Kontos nötig ist, wird dem
			Mitglied ein Aktivierungslink zugesandt, der angeklickt werden muss, bevor es das Konto nutzen kann. Sie können auch auswählen, dass das neue
			Passwort für den Benutzer an die angegebene E-Mail-Adresse gesendet wird.<br /><br />
		</li>
		<li>
			<strong>Nutzungsbedingungen ändern</strong><br />
			Hier können Sie den Text der Nutzungsbedingungen ändern, die angezeigt werden, wenn sich Benutzer in Ihrem Forum registrieren.
			Sie können die Standardnutzungsbedingungen, die in ElkArte enthalten sind, nach Belieben ändern.<br /><br />
		</li>
		<li>
			<strong>Reservierte Namen einstellen</strong><br />
			Mittels dieser Eingabemaske können Sie Namen definieren, die von Ihren Benutzern nicht verwendet werden können.<br /><br />
		</li>
		<li>
			<strong>Einstellungen</strong><br />
			Dieser Bereich ist nur sichtbar, wenn Sie die Befugnis besitzen, das Forum zu administrieren. Hier können Sie die Registrierungsmethode
			festlegen, die für Ihr Forum verwendet wird, sowie weitere registrierungsbezogene Einstellungen vornehmen.
		</li>
	</ul>';

$helptxt['modlog'] = '<strong>Moderationsprotokoll</strong><br />
	Dieser Bereich erlaubt es Mitgliedern des Moderationsteams, alle Aktionen zu verfolgen, die von Moderatoren vorgenommen wurden. Um
	sicherzustellen, dass Moderatoren ihre Aktionen nicht verschleiern können, können Einträge nicht vor Ablauf von 24 Stunden nach der erfolgten
	Aktion gelöscht werden.';
$helptxt['adminlog'] = '<strong>Administrationsprotokoll</strong><br />
	Dieser Bereich erlaubt es Mitgliedern des Administrationsteams, alle administrativen Aktionen zu verfolgen, die im Forum vorgenommen wurden. Um
	sicherzustellen, dass Administratoren ihre Aktionen nicht verschleiern können, können Einträge nicht vor Ablauf von 24 Stunden nach der erfolgten
	Aktion gelöscht werden.';
$helptxt['badbehaviorlog'] = '<strong>Bad-Behavior-Protokoll</strong><br />
	Dieser Bereich erlaubt es Mitgliedern des Adminteams, einige der böswilligen Aktionen im Forum anzusehen. Dieses Protokoll wird von der Bad-Behavior-Funktion automatisch aufgeräumt, somit enthält es nur die Aktivitäten der letzten Woche.';
$helptxt['warning_enable'] = '<strong>Benutzerverwarnystem</strong><br />
	Diese Funktion gewährt Administratoren und Moderatoren die Fähigkeit, Mitglieder zu verwarnen und eine Verwarnstufe zu verwenden, um die Aktionen
	auszuwählen, die diesen künftig verwehrt werden sollen. Bei Aktivierung dieser Funktion wird im Befugnisbereich eine Befugnis verfügbar gemacht,
	mittels derer festgelegt werden kann, welche Gruppen Mitglieder verwarnen dürfen. Verwarnstufen können aus einem Mitgliedsprofil heraus angepasst
	werden. Folgende zusätzliche Optionen sind verfügbar:';
$helptxt['watch_enable'] = '<strong>Verwarnstufe für Beobachtung</strong><br />Diese Einstellung legt die prozentuale Verwarnstufe fest, die ein Mitglied erreichen muss, um automatisch &quot;beobachtet&quot; zu werden. Jedes &quot;beobachtete&quot; Mitglied erscheint im entsprechenden Bereich im Moderationszentrum.';
$helptxt['moderate_enable'] = '<strong>Verwarnstufe für Beitragsmoderation</strong><br />Die Beiträge jedes Mitglieds, das diesen Wert überschreitet, müssen von einem Moderatoren freigeschaltet werden, bevor sie öffentlich im Forum angezeigt werden. Dies wird jegliche lokalen Forenbefugnisse überschreiben, die mit Beitragsmoderation zu tun haben.';
$helptxt['mute_enable'] = '<strong>Verwarnstufe für Stummschaltung</strong><br />Wird diese Verwarnstufe von einem Mitglied überschritten, so unterliegt es einer Beitragssperre. Es verliert damit jede Berechtigung zum Verfassen neuer Beiträge.';
$helptxt['perday_limit'] = '<strong>Maximale Verwarnpunkte pro Mitglied und Tag</strong><br />Diese Einstellung begrenzt die Anzahl an Punkten, die ein Moderator einem einzelnen Mitglied innerhalb von 24 Stunden geben/nehmen kann. Dies kann verwendet werden, um zu beschränken, was ein Moderator binnen kurzer Zeit tun kann. Die Einstellung kann mit dem Wert 0 deaktiviert werden. Beachten Sie, dass Mitglieder mit Administrationsrechten nicht von diesem Wert betroffen sind.';
$helptxt['error_log'] = '<strong>Fehlerprotokoll</strong><br />
	Das Fehlerprotokoll verfolgt jeden von Benutzern festgestellten ernsthaften Fehler beim Benutzen Ihres Forums. Es listet all diese Fehler nach Datum
	auf, sie können per Klick auf den schwarzen Pfeil neben jedem Datum sortiert werden. Sie können die Fehler mittels Klicks auf das Bild neben jeder
	Fehlerstatistik zusätzlich filtern. Dadurch können Sie zum Beispiel die Fehler nach einem bestimmten Mitglied filtern. Wenn ein Filter aktiv ist,
	werden nur die Fehler angezeigt, auf die dieser Filter zutrifft.';
$helptxt['theme_settings'] = '<strong>Designeinstellungen</strong><br />
	Diese Einstellungsseite erlaubt es Ihnen, Einstellungen zu ändern, die sich nur auf ein Design auswirken. Solche Designeinstellungen beinhalten Optionen
	wie das Designverzeichnis und URL-Informationen, aber auch Optionen, die das Aussehen des Designs beeinflussen. Die meisten Designs haben eine Vielzahl
	an konfigurierbaren Optionen, was Ihnen erlaubt, ein Design auf Ihre individuellen Bedürfnisse zuzuschneiden.';
$helptxt['smileys'] = '<strong>Smileyzentrum</strong><br />
	Hier können Sie Smileys und Smileysätze hinzufügen und entfernen.  Beachten Sie unbedingt, dass, wenn ein Smiley in <em>einem</em> Satz enthalten ist, er
	in <em>allen</em> Sätzen enthalten ist - andernfalls könnte es für Ihre Benutzer verwirrend sein, wenn sie unterschiedliche Smileysätze verwenden.<br /><br />

	Sie können hier auch die Nachrichtensymbole ändern, wenn Sie sie in den Einstellungen aktiviert haben.';

$helptxt['calendar'] = '<strong>Kalender verwalten</strong><br />
	Hier können Sie die aktuellen Kalendereinstellungen anpassen sowie Feiertage, die im Kalender erscheinen, hinzufügen und entfernen.';
$helptxt['calendar_settings'] = 'Der Kalender kann verwendet werden, um Geburtstage oder wichtige Ereignisse in Ihrem Forum anzuzeigen.<br /><br />Denken Sie daran, dass die Verwendung des Kalenders (Veröffentlichung und Ansehen von Ereignissen usw.) mittels Befugnissen gesteuert wird, die Sie auf der Befugnisseite vergeben können.';
$helptxt['cal_days_for_index'] = 'Max. Tage im Voraus auf der Hauptseite:<br />Wird dies auf 7 gesetzt, so werden alle Ereignisse der nächsten Woche angezeigt.';
$helptxt['cal_showevents'] = 'Aktiviert die Hervorhebung von Ereignissen in den Minikalendern, im Hauptkalender, beides oder nirgendwo.';
$helptxt['cal_showholidays'] = 'Diese Einstellung ermöglicht es Ihnen, Feiertage in den Minikalendern, im Hauptkalender oder in allen Kalendern hervorzuheben sowie die Hervorhebung von Ereignissen zu deaktivieren.';
$helptxt['cal_showbdays'] = 'Diese Einstellung ermöglicht es Ihnen, Geburtstags in den Minikalendern, im Hauptkalender oder in allen Kalendern hervorzuheben sowie die Hervorhebung von Ereignissen zu deaktivieren.';
$helptxt['cal_export'] = 'Exportiert eine Textdatei im iCal-Format zum Import in andere Kalenderanwendungen.';
$helptxt['cal_daysaslink'] = 'Zeigt Tage als Verweis auf \'Ereignis veröffentlichen\' an:<br />Dies erlaubt es Mitgliedern, Ereignisse an diesem Tag anzukündigen, wenn sie auf ein Datum klicken.';
$helptxt['cal_allow_unlinked'] = 'Nicht mit Beiträgen verknüpfte Ereignisse erlauben:<br />Erlaubt es Mitgliedern, Ereignisse zu veröffentlichen, ohne sie mit einem Beitrag im Forum zu verknüpfen.';
$helptxt['cal_defaultboard'] = 'Standardforum zur Veröffentlichung von Ereignissen:<br />Geben Sie das Forum an, in dem Ereignisse standardmäßig veröffentlicht werden sollen.';
$helptxt['cal_showInTopic'] = 'Verknüpfte Ereignisse in Themenansicht anzeigen:<br />Aktivieren, um einen Verweis auf das Ereignis oben in der Themenansicht anzuzeigen.';
$helptxt['cal_allowspan'] = 'Ereignisse dürfen mehrere Tage umfassen:<br />Aktivieren, um Ereignisse zu erlauben, die mehrere Tage umfassen.'; // translator note: you don't say? :-D
$helptxt['cal_maxspan'] = 'Max. Anzahl an Tagen, die ein Ereignis umfassen darf:<br />Geben Sie die maximale Anzahl an Tagen ein, die ein Ereignis umfassen darf.';
$helptxt['cal_minyear'] = 'Mindestjahr:<br />Das &quot;erste&quot; Jahr in der Kalenderliste auswählen.';
$helptxt['cal_maxyear'] = 'Höchstjahr:<br />Das &quot;letzte&quot; Jahr in der Kalenderliste auswählen<br />';

$helptxt['serversettings'] = '<strong>Servereinstellungen</strong><br />
	Hier können Sie die Kernkonfiguration Ihres Forums vornehmen. Dieser Bereich umfasst die Datenbank- und URL-Einstellungen sowie
	weitere wichtige Konfigurationselemente wie E-Mail-Einstellungen und Zwischenspeicherung. Denken Sie sorgsam über jede Änderung
	an diesen Einstellungen nach, da ein Fehler den Zugriff auf das Forum verhindern kann';
$helptxt['manage_files'] = '
	<ul class="normallist">
		<li>
			<strong>Dateien durchsuchen</strong><br />
			Hier können Sie alle Dateianhänge, Avatare und Miniaturansichten ansehen, die vom System gespeichert wurden.<br /><br />
		</li><li>
			<strong>Dateianhangseinstellungen</strong><br />
			Konfigurieren Sie hier, wo Dateianhänge gespeichert werden, und beschränken Sie den Zugriff auf verschiedene Arten von
			Dateianhängen.<br /><br />
		</li><li>
			<strong>Avatareinstellungen</strong><br />
			Konfigurieren Sie hier, wo Avatare gespeichert werden, und stellen Sie die Größenänderung von Avataren ein.<br /><br />
		</li><li>
			<strong>Dateiwartung</strong><br />
			Prüfen und reparieren Sie jeglichen Fehler im Dateianhangsverzeichnis und löschen Sie ausgewählte Dateianhänge.<br /><br />
		</li>
	</ul>';

$helptxt['topicSummaryPosts'] = 'Dies erlaubt es Ihnen, die Anzahl früherer Beiträge festzulegen, die in der Themenzusammenfassung auf der Antworten-Seite angezeigt werden.';
$helptxt['enableAllMessages'] = 'Setzen Sie dies auf die <em>Höchstzahl</em> an Beiträgen, die ein Thema haben darf, damit der &quot;Alle&quot;-Verweis angezeigt wird.  Wird dieser Wert niedriger als die &quot;maximale Anzahl an Beiträgen pro Seite&quot; gesetzt, so bedeutet dies nur, dass er niemals angezeigt wird; setzen Sie ihn zu hoch, so könnte dies Ihr Forum verlangsamen.';
$helptxt['enableStickyTopics'] = 'Angeheftete Themen sind Themen, die in der Themenübersicht immer oben stehen. Sie werden meist für wichtige Mitteilungen verwendet.
		Obwohl Sie dies über Befugnisse ändern können, können standardmäßig nur Moderatoren und Administratoren Themen anheften.';
$helptxt['allow_guestAccess'] = 'Die Deaktivierung dieser Option wird es Gästen verbieten, irgendetwas außer den nötigsten Aktionen - Anmelden, Registrieren, Passworterinnerung und so weiter - in Ihrem Forum zu tun.  Dies ist nicht dasselbe wie das Verbot des Zugriffs auf einzelne Foren durch Gäste.';
$helptxt['userLanguage'] = 'Die Aktivierung dieser Option wird es Benutzern erlauben, auszuwählen, welche Sprachdatei sie verwenden möchten. Dies betrifft nicht die Standardauswahl.';
$helptxt['trackStats'] = 'Statistiken:<br />Dies wird es Benutzern erlauben, die neuesten Beiträge und beliebtesten Themen in Ihrem Forum zu sehen.
		Es wird außerdem diverse Statistiken wie die Höchstzahl an Mitgliedern, die gleichzeitig online waren, neue Mitglieder und neue Themen anzeigen.<hr />
		Seitenansichten:<br />Fügt der Statistikseite eine weitere Spalte mit der Anzahl an Seitenansichten in Ihrem Forum hinzu.';
$helptxt['enable_unwatch'] = 'Die Aktivierung dieser Option wird es Benutzern erlauben, gezielt die Benachrichtigung über neue Antworten in Themen abzuschalten, in denen sie zuvor einen Beitrag geschrieben haben.';
$helptxt['titlesEnable'] = 'Schalten Sie eigene Titel an, so wird es Mitgliedern mit den entsprechenden Befugnissen erlaubt, einen besonderen Titel für sich selbst zu erstellen.
		Dieser wird unter dem Namen angezeigt.<br /><em>Beispiel:</em><br />Max<br />Toller Typ';
$helptxt['topbottomEnable'] = 'Dies fügt &quot;Nach oben&quot;- und &quot;Nach unten&quot;-Schaltflächen hinzu, so dass Mitglieder auf der Seite nach oben und unten springen können, ohne zu scrollen.';
$helptxt['onlineEnable'] = 'Dies wird ein Bild hinzufügen, das anzeigt, ob das Mitglied online oder offline ist';
$helptxt['todayMod'] = 'Dies wird &quot;Heute&quot; oder &quot;Gestern&quot; in einer Vielzahl an Formaten anstelle des vollständigen Datums anzeigen.<br /><br />
		<strong>Beispiele:</strong><br /><br />
		<dl class="settings">
			<dt>Deaktiviert</dt>
			<dd>3. Oktober 2009 um 12:59:18</dd>
			<dt>Relativ</dt>
			<dd>Vor 2 Stunden</dd>
			<dt>Nur heute</dt>
			<dd>Heute um 12:59:18</dd>
			<dt>Heute &amp; gestern</dt>
			<dd>Gestern um 21:36:55</dd>
		</dl>';
$helptxt['disableCustomPerPage'] = 'Verwenden Sie diese Option, um Benutzer davon abzuhalten, die Menge an Beiträgen und Themen pro Seite in der Nachrichten- beziehungsweise Themenübersicht anzupassen.';
$helptxt['enablePreviousNext'] = 'Dies wird Verweise auf das nächste und auf das vorherige Thema anzeigen.';
$helptxt['pollMode'] = 'Hier können Sie auswählen, ob Umfragen aktiviert werden sollen oder nicht. Werden sie deaktiviert, so werden Themen angezeigt, als hätten sie keine Umfragen.
<br /><br />Um auszuwählen, wer Umfragen starten, ansehen und dergleichen darf, können Sie diese Befugnisse separat setzen. Denken Sie daran, falls Umfragen nicht zu funktionieren scheinen.';
$helptxt['enableVBStyleLogin'] = 'Dies wird eine kompaktere Anmeldung für Gäste auf jeder Seite des Forums anzeigen.';
$helptxt['enableCompressedOutput'] = 'Diese Option wird die Ausgabe für niedrigere Bandbreitennutzung komprimieren, benötigt jedoch eine installierte zlib.';
$helptxt['disableTemplateEval'] = 'Standardmäßig werden Vorlagen ausgewertet statt nur eingebunden. Dies hilft dabei, nützlichere Debuginformationen anzuzeigen, falls eine Vorlage einen Fehler enthält.<br /><br />
		In großen Foren allerdings könnte dieser Vorgang deutlich langsamer ablaufen. Daher können fortgeschrittene Nutzer sie deaktivieren.';
$helptxt['databaseSession_enable'] = 'Mit dieser Option wird die Datenbank zur Speicherung von Sitzungen verwendet - dies ist das Beste für lastausgeglichene Server, hilft jedoch bei allen Zeitüberschreitungsproblemen und kann das Forum beschleunigen.'; // translator note: "load balanced"?!
$helptxt['databaseSession_loose'] = 'Wird dies angeschaltet, so verringert dies die Bandbreite, die von Ihrem Forum verwendet wird, und sorgt dafür, dass ein Klick auf Zurück nicht die Seite neu lädt - der Nachteil ist, dass unter anderem die (Neu)-Symbole nicht aktualisiert werden. (Es sei denn, Sie öffnen diese Seite mit einem Klick statt zu ihr zurückzukehren.)';
$helptxt['databaseSession_lifetime'] = 'Dies ist die Anzahl an Sekunden, für die Sitzungen nach ihrer letzten Verwendung bestehen bleiben sollen.  Wird eine Sitzung lange genug nicht verwendet, wird davon ausgegangen, dass sie &quot;abgelaufen&quot; ist. Ein Wert über 2400 (entspricht 40 Minuten) wird empfohlen.';
$helptxt['cache_enable'] = 'ElkArte führt Zwischenspeicherung in verschiedenen Stufen durch. Je höher die aktivierte Stufe ist, um so mehr Prozessorzeit wird für das Abrufen der zwischengespeicherten Informationen aufgewendet. Wenn auf Ihrem Rechner Zwischenspeicherung verfügbar ist, wird empfohlen, dass Sie es zunächst mit Stufe 1 versuchen.';
$helptxt['cache_memcached'] = 'Verwenden Sie memcached, so müssen Sie die Serverdaten bereitstellen. Sie sollten wie in folgendem Beispiel als kommagetrennte Liste angegeben werden:<br /><br/>	&quot;server1,server2,server3:port,server4&quot;<br /><br />Beachten Sie, dass, wenn kein Port angegeben ist, das Programm Port 11211 verwenden wird. Das System wird zudem versuchen, grundlegende/zufällige Lastverteilung zwischen den angegebenen Servern durchzuführen.';
$helptxt['cache_cachedir'] = 'Diese Einstallung kommt nur bei dateisystembasierter Zwischenspeicherung zur Anwendung. Sie gibt den Pfad zum Zwischenspeicherungsverzeichnis an.  Es wird empfohlen, dass Sie dieses in /tmp/ anlegen, wenn Sie diese Methode verwenden möchten, aber es funktioniert auch jedes andere Verzeichnis';
$helptxt['cache_uid'] = 'Einige Cachesysteme, zum Beispiel Xcache, benötigen eine Benutzerkennung und ein Passwort, um ElkArte das Leeren des Zwischenspeichers zu erlauben.';
$helptxt['cache_password'] = 'Einige Cachesysteme, zum Beispiel Xcache, benötigen eine Benutzerkennung und ein Passwort, um ElkArte das Leeren des Zwischenspeichers zu erlauben.';
$helptxt['enableErrorLogging'] = 'Dies wird jegliche Fehler wie fehlgeschlagene Anmeldungen protokollieren, so dass Sie sehen können, was falsch lief.';
$helptxt['enableErrorQueryLogging'] = 'Dies fügt die vollständige Datenbankabfrage in das Fehlerprotokoll ein.  Setzt die Aktivierung der Fehlerprotokollierung voraus.<br /><br /><strong>Hinweis:  Dies beeinflusst die Fähigkeit, das Fehlerprotokoll nach der Fehlernachricht zu filtern.</strong>';
$helptxt['allow_disableAnnounce'] = 'Dies erlaubt es Benutzern, die Benachrichtigungen über Themen, die Sie bei der Veröffentlichung mittels Aktivierung des Auswahlkastens &quot;Thema ankündigen&quot; ankündigen, abzubestellen.'; // translator note: uhm. what?
$helptxt['disallow_sendBody'] = 'Diese Option entfernt die Möglichkeit, den Text von Antworten, Beiträgen und privaten Nachrichten in Benachrichtigungs-E-Mails zu erhalten.<br /><br />Oft antworten Mitglieder auf die Benachrichtigungsmail, was meist bedeutet, dass der Serveradministrator die Antwort erhält.';
$helptxt['enable_contactform'] = 'Diese Option fügt der Registrierungsseite eine Schaltfläche für die Kontaktaufnahme hinzu';
$helptxt['jquery_source'] = 'Dies legt die Quelle fest, aus der die jQuery-Bibliothek geladen wird.  Auto wird zunächst das CDN verwenden und, wenn es nicht verfügbar ist, auf die lokale Quelle zurückgreifen.  Lokal wird nur die lokale Quelle verwenden, CDN wird die Bibliothek nur von Googles Servern laden';
$helptxt['jquery_default'] = 'Wenn Sie eine andere jQuery-Version als diejenige, die mit ElkArte ausgeliefert wurde, verwenden möchten, wählen Sie diesen Kasten aus und geben Sie die Versionsnummer (X.XX.X) ein. Die lokale Datei muss der Namenskonvention jquery-X.XX.X.min.js folgen, damit sie geladen werden kann.';
$helptxt['jqueryui_default'] = 'Wenn Sie eine andere jQuery-UI-Version als diejenige, die mit ElkArte ausgeliefert wurde, verwenden möchten, wählen Sie diesen Kasten aus und geben Sie die Versionsnummer (X.XX.X) ein. Die lokale Datei muss der Namenskonvention jquery-ui-X.XX.X.min.js folgen, damit sie geladen werden kann.';
$helptxt['minify_css_js'] = 'Dies wird mehrere CSS- oder JavaScript-Dateien bei Bedarf auf jeder Seite miteinander kombinieren.  Es entfernt zudem unnötigen Leerraum und Kommentare aus den Dateien, um ihre Größe zu verringern.  Die kombinierten und verkleinerten Dateien werden gespeichert, so dass weitere Anfragen direkt diese Dateien ausliefern können.  Beachten Sie, dass es bei der ersten Zusammenstellung zu einer kleinen Verzögerung beim Laden der Seite kommen wird, um die Datei erzeugen zu können (dies wird auch nach dem Leeren des Zwischenspeichers passieren)';
$helptxt['compactTopicPagesEnable'] = 'Dies wird die angegebene Anzahl an umgebenden Seiten im Thema anzeigen.<br /><em>Beispiel:</em>
		&quot;3&quot; zeigt drei Seiten an: 1 ... 4 [5] 6 ... 9 <br />
		&quot;5&quot; zeigt fünf Seiten an: 1 ... 3 4 [5] 6 7 ... 9';
$helptxt['timeLoadPageEnable'] = 'Dies wird unten im Forum die Zeit in Sekunden anzeigen, die benötigt wurde, um die jeweilige Seite zu erstellen.';
$helptxt['removeNestedQuotes'] = 'Dies wird verschachtelte Zitate aus einem Beitrag entfernen, wenn aus diesem Beitrag mittels eines Zitieren-Links zitiert wird.';
$helptxt['search_dropdown'] = 'Dies wird neben dem Schnellsuchfeld eine Suchaufklappliste anzeigen.  Aus dieser können Sie auswählen, ob Sie die gesamte Website, das aktuelle Forum (sofern Sie in einem Forum sind), das aktuelle Thema (sofern Sie in einem Thema sind) durchsuchen oder nach Mitgliedern suchen möchten.';
$helptxt['max_image_width'] = 'Dies erlaubt es Ihnen, eine maximale Größe für veröffentlichte Bilder festzulegen. Bilder, die kleiner sind, sind nicht betroffen. Dies legt außerdem fest, wie angehängte Bilder angezeigt werden sollen, wenn auf ihre Miniaturansicht geklickt wird.';
$helptxt['mail_type'] = 'Diese Einstellung erlaubt es Ihnen, entweder PHPs Standardeinstellungen zu verwenden oder stattdessen auf SMTP zurückzugreifen.  PHP unterstützt keine Anmeldung per SMTP (was viele Anbieter mittlerweile voraussetzen), weshalb Sie, wenn Sie dies wollen, SMTP auswählen sollten.  Bitte beachten Sie, dass SMTP langsamer sein kann und einige Server Benutzernamen und Passwort nicht entgegennehmen.<br /><br />Sie müssen die SMTP-Daten nicht angeben, wenn dies auf den PHP-Standard gesetzt ist.';
$helptxt['mail_batch_size'] = 'Diese Einstellung legt fest, wie viele E-Mails pro Seitenaufruf versandt werden, und kann den Höchstwert pro Minute nicht überschreiten.<br />Wird sie auf 0 gesetzt, so wird das System automatisch eine Stapelgröße festlegen, um die Last gleichmäßig zu verteilen und das Kontingent zu füllen.<br />Sofern Sie Ihre eigenen Werte einsetzen möchten, ist es eine gute Option, dies auf den gleichen Wert wie die Begrenzung (für niedrige Pro-Minute-Werte) oder 1/6 der Begrenzung (für höhere Pro-Minute-Werte) zu setzen.';

$helptxt['attachment_manager_settings'] = 'Hier können Sie Ihre Dateianhangseinstellungen ändern, etwa den Ort zum Speichern der hochgeladenen Dateien und die maximal sichtbaren Größen ändern und die Verwendung von Dateierweiterungen beschränken.';
$helptxt['attachmentEnable'] = 'Aktiviert/Deaktiviert das Dateianhangssystem oder deaktiviert nur neue Dateianhänge, lässt jedoch bereits vorhandene unangetastet.';
$helptxt['attachmentRecodeLineEndings'] = 'Die Aktivierung dieser Option wird Zeilenenden von reinen Textdateien (txt, css, html, php, xml) abhängig von Ihrem Server (Windows, Mac oder Unix) neu kodieren.';
$helptxt['automanage_attachments'] = 'Dies wird eine Verzeichnisstruktur auf Basis der ausgewählten Option erzeugen.  Bei dieser kann es sich um das Beitragsdatum (Aufteilung per Jahr oder per Jahr und Monat oder per Jahr, Monat und Tag) oder um das schlichte Hinzufügen eines neuen Verzeichnisses, wenn die Kapazitätsgrenze erreicht wurde, handeln.  Jedes erzeugte Verzeichnis wird die gleiche Anzahl an Dateien beherbergen und die gleichen Größenbeschränkungen besitzen.  Dies wird dabei helfen, Verzeichnisse daran zu hindern, eine bestimmte Größe (Anzahl oder Dateigröße der enthaltenen Dateien) zu erreichen.';
$helptxt['use_sub-directories_for_attachments'] = 'Dies wird alle neuen Verzeichnisse als Unterverzeichnisse des Hauptdateianhangsverzeichnisses anlegen.';
$helptxt['attachmentDirSizeLimit'] = ' Legen Sie fest, wie groß der Dateianhangsordner sein darf.';
$helptxt['attachmentDirFileLimit'] = 'Legen Sie die Höchstanzahl an Dateien fest, die ein einzelnes Dateianhangsverzeichnis enthalten darf';
$helptxt['attachmentPostLimit'] = 'Geben Sie an, wie groß (in KiB) ein einzelner Beitrag insgesamt sein darf; dies betrifft die Gesamtgröße aller Dateianhänge eines Beitrags.';
$helptxt['attachmentSizeLimit'] = 'Geben Sie die maximale Größe eines einzelnen Dateianhangs an.';
$helptxt['attachmentNumPerPostLimit'] = 'Wählen Sie die Anzahl an Dateien aus, die ein Mitglied pro Beitrag anhängen darf.';
$helptxt['attachmentCheckExtensions'] = 'Aktivieren Sie diesen Auswahlkasten, um die Dateianhangsfilterung zu aktivieren, die nur das Hochladen von Dateien mit den Dateierweiterungen erlaubt, die Sie definiert haben.';
$helptxt['attachmentExtensions'] = 'Legen Sie fest, welche Dateianhangstypen erlaubt sind, zum Beispiel: jpg,png,gif  Denken Sie daran, vorsichtig zu sein, da einige Dateitypen ein Sicherheitsrisiko für Ihre Website darstellen können.';
$helptxt['attachment_image_paranoid'] = 'Die Auswahl dieser Option wird sehr strikte Sicherheitsprüfungen für Bildanhänge aktivieren. Warnung! Diese tiefgehenden Prüfungen können auch bei gültigen Bildern fehlschlagen. Es wird wärmstens empfohlen, diese Option ausschließlich zusammen mit der Bildneukodierung zu verwenden, um ElkArte dazu zu bringen, zu versuchen, diejenigen Bilder, deren Sicherheitsprüfung fehlgeschlagen ist, neu aufzubauen: bei Erfolg werden sie bereinigt und hochgeladen. Ansonsten, sofern Bildneukodierung nicht aktiviert ist, werden alle Dateianhänge, deren Sicherheitsprüfung fehlschlug, zurückgewiesen.';
$helptxt['attachmentShowImages'] = 'Falls die hochgeladene Datei ein Bild ist, wird es automatisch unterhalb des Beitrags angezeigt.';
$helptxt['attachmentThumbnails'] = 'Aktivieren Sie dies, um Beitragsbilder als kleinere Miniaturbilder anzuzeigen, die bei Auswahl auf die volle Größe erweitert werden.';
$helptxt['attachment_thumb_png'] = 'Beim Erzeugen von Miniaturansichten zwecks Anzeige unter einem Beitrag wird dies selbige nur als png-Dateien erzeugen.';
$helptxt['attachmentThumbWidth'] = 'Wird nur zusammen mit der Option &quot;Bildgröße beim Anzeigen unter Beiträgen ändern&quot; verwendet, legt die maximale Breite fest, von der Dateianhänge verkleinert werden.  Die Größenänderung erfolgt proportional.';
$helptxt['attachmentThumbHeight'] = 'Wird nur zusammen mit der Option &quot;Bildgröße beim Anzeigen unter Beiträgen ändern&quot; verwendet, legt die maximale Höhe fest, von der Dateianhänge verkleinert werden.  Die Größenänderung erfolgt proportional.';
$helptxt['attachment_image_reencode'] = 'Die Auswahl dieser Option wird eine Methode aktivieren, die versuchen wird, hochgeladene Bildanhänge neu zu kodieren. Dadurch wird die Sicherheit verbessert. Beachten Sie jedoch, dass die Neukodierung von Bildern alle animierten Grafiken in statische umwandelt.<br />Diese Funktion steht nur zur Verfügung, wenn auf Ihrem Server das GD-Modul installiert ist.';
$helptxt['attachment_thumb_memory'] = 'Je größer (Dateigröße & Breite x Höhe) die Quellgrafik ist, desto mehr Speicher benötigt das System, um erfolgreich eine Miniaturvorschau zu erstellen.<br />Wird diese Option aktiviert, so wird das System den benötigten Speicher schätzen und dann diese Menge anfordern.  Nur bei Erfolg wird es versuchen, die Miniaturvorschau zu erzeugen.<br />Dies wird zwar zu weniger Weißer-Bildschirm-Fehlern, jedoch auch zu weniger Miniaturvorschauen führen.  Bleibt die Option deaktiviert, so wird das System immer versuchen, die Vorschau (mit einer festen Menge an Speicher) zu erzeugen.  Dies könnte zu mehr Weißer-Bildschirm-Fehlern führen.';
$helptxt['max_image_height'] = 'Die maximal angezeigte Höhe eines Bildanhangs.';
$helptxt['max_image_width'] = 'Die maximal angezeigte Breite eines Bildanhangs.';
$helptxt['attachmentUploadDir'] = 'Wählen Sie aus, wo auf Ihrem Server hochgeladene Dateien gespeichert werden sollen. Für mehr Sicherheit kann dieses Verzeichnis auch außerhalb des öffentlichen Webserververzeichnisses liegen.';
$helptxt['attachment_transfer_empty'] = 'Enabling this will move all the files from the source directory to the new location. Disabled only the count of files in excess of the maximum allowed per directory setting will be moved.';
$helptxt['avatar_paranoid'] = 'Die Auswahl dieser Option wird sehr strikte Sicherheitsprüfungen für Avatare aktivieren. Warnung! Diese tiefgehenden Prüfungen können auch bei gültigen Avataren fehlschlagen. Es wird wärmstens empfohlen, diese Option ausschließlich zusammen mit der Bildneukodierung zu verwenden, um ElkArte dazu zu bringen, zu versuchen, diejenigen Avatare, deren Sicherheitsprüfung fehlgeschlagen ist, neu aufzubauen: bei Erfolg werden sie bereinigt und hochgeladen. Ansonsten, sofern Bildneukodierung nicht aktiviert ist, werden alle Avatare, deren Sicherheitsprüfung fehlschlug, zurückgewiesen.';
$helptxt['avatar_reencode'] = 'Die Auswahl dieser Option wird eine Methode aktivieren, die versuchen wird, hochgeladene Avatare neu zu kodieren. Dadurch wird die Sicherheit verbessert. Beachten Sie jedoch, dass die Neukodierung von Bildern alle animierten Grafiken in statische umwandelt.<br />Diese Funktion steht nur zur Verfügung, wenn auf Ihrem Server das GD-Modul installiert ist.';
$helptxt['karmaMode'] = 'Karma ist eine Funktion, die die Beliebtheit eines Mitglieds anzeigt, Wenn aktiviert, können
		Mitglieder anderen Mitgliedern \'applaudieren\' oder sie \'zerschmettern\', anhand dessen deren Beliebtheit
		berechnet wird. Sie können die Anzahl an Beiträgen, die benötigt werden, um ein &quot;Karma&quot; zu haben, den
		Zeitabstand zwischen zwei Aktionen und die Einstellung, ob dieser auch für Administratoren gilt, ändern.<br /><br />
		Ob Benutzergruppen andere zerschmettern können, wird über eine Befugnis entschieden.  Wenn Sie Probleme dabei
		haben, diese Funktion für jedermann zu aktivieren, überprüfen Sie die vergebenen Befugnisse erneut.';
$helptxt['localCookies'] = 'Das System verwendet Cookies, um Anmeldeinformationen auf dem Benutzerrechner zu speichern.
	Cookies können global (meinserver.de) oder lokal (meinserver.de/pfad/zum/forum) gespeichert werden.<br />
	Aktivieren Sie diese Option, wenn Benutzer wider Erwarten automatisch abgemeldet werden.<hr />
	Global gespeicherte Cookies sind auf einem geteilten Webserver (etwa bplaced) weniger sicher.<hr />
	Lokale Cookies funktionieren außerhalb des Forumsverzeichnisses nicht, so dass, wenn Ihr Forum unter www.meinserver.de/forum liegt, Seiten wie www.meinserver.de/index.php nicht auf die Kontoinformationen zugreifen können.
	Vor allem, wenn SSI.php verwendet wird, werden globale Cookies empfohlen.';
$helptxt['enableBBC'] = 'Die Auswahl dieser Option wird es Ihren Mitgliedern erlauben, Bulletin Board Code (BBCode) überall im Forum zu verwenden, was es ihnen ermöglicht, ihre Beiträge mit Bildern, Schriftformatierung und mehr zu formatieren.';
$helptxt['time_offset'] = 'Nicht alle Forumsadministratoren wollen, dass ihr Forum dieselbe Zeitzone wie der Server, auf dem es liegt, nutzt. Verwenden Sie diese Option, um einen Zeitunterschied (in Stunden) anzugeben, den das Forum abhängig von der Serverzeit befolgen soll. Negative und Dezimalwerte sind erlaubt.';
$helptxt['default_timezone'] = 'Die Serverzeitzone sagt PHP, wo Ihr Server steht. Sie sollten sicherstellen, dass dieser Wert korrekt gesetzt ist, bevorzugt auf das Land/die Stadt, wo Ihr Server steht. Sie können weitere Informationen hierüber im <a href="http://www.php.net/manual/de/timezones.php" target="_blank">PHP-Handbuch</a> finden.';
$helptxt['spamWaitTime'] = 'Hier können Sie die Zeitspanne, die zwischen zwei Beiträgen vergehen muss, auswählen. Dies kann benutzt werden, um Leute davon abzuhalten, in Ihrem Forum zu "spammen", indem beschränkt wird, wie oft sie neue Beiträge schreiben können.';

$helptxt['enablePostHTML'] = 'Dies wird die Nutzung einiger grundlegender HTML-Tags gestatten:
	<ul class="normallist enablePostHTML">
		<li>&lt;b&gt;, &lt;u&gt;, &lt;i&gt;, &lt;s&gt;, &lt;em&gt;, &lt;ins&gt;, &lt;del&gt;</li>
		<li>&lt;a href=&quot;&quot;&gt;</li>
		<li>&lt;img src=&quot;&quot; alt=&quot;&quot; /&gt;</li>
		<li>&lt;br /&gt;, &lt;hr /&gt;</li>
		<li>&lt;pre&gt;, &lt;blockquote&gt;</li>
	</ul>';

// Anfängliche Designeinstellungen - Verwalten und Installieren
$helptxt['themes'] = 'Hier können Sie auswählen, ob das Standarddesign ausgewählt werden kann, welches Design Gäste benutzen und weitere Optionen. Klicken Sie rechts auf ein Design, um seine Einstellungen zu ändern.';
$helptxt['theme_install'] = 'Dieser Bereich erlaubt es Ihnen, neue Designs zu installieren. Sie tun dies mittels Hochladens einer archivierten Datei für das Design von Ihrem Rechner, Installierens aus einem Designverzeichnis auf dem Server oder Kopierens des Standarddesigns und Umbenennens der Kopie.<br /><br />Bitte bedenken Sie dies: die archivierte Datei oder das archivierte Verzeichnis muss die Definitionsdatei <span style="color:red">theme_info.xml</span> als Teil des Archivs oder Verzeichnisses enthalten.';
$helptxt['initial_theme_settings'] = 'Das Ändern des globalen Forenstandards betrifft keine Mitglieder, die ein anderes verfügbares Design ausgewählt haben. Sie müssen auch alle Mitglieder \'zurücksetzen\', um sie zum neuen Standard zu zwingen. Sie können auch ein Standarddesign auswählen, das von Gästen gesehen wird, und dann Ihre Mitglieder auf ein anderes Design zurücksetzen. <br /><br />Denken Sie daran, dass Benutzer, sofern Sie ihnen erlaubt haben, ihr eigenes Design auszuwählen, das von Ihnen gesetzte Design überschreiben können.';

// Theme Management and Options - Theme settings
$helptxt['themeadmin_list_reset'] = 'In seltenen Fällen geht der Pfad zum Design verloren und Ihr Forum wird nicht korrekt angezeigt. Dies kann in einem Fehler durch einen Administrator, Datenbankfehlern, fehlgeschlagenen Softwareaktualisierungen, Modinstallationen oder anderen Ereignissen begründet sein. Das Zurücksetzen der Design-URLs und -Verzeichnisse löst dieses Problem normalerweise.';
$helptxt['themeadmin_delete_help'] = 'Das Standarddesign kann nicht gelöscht werden, da dies Ihr Forum und andere Designs zerstören würde. Sie können allerdings jedes Design löschen, neben dem ein rotes \'X\' steht, indem Sie auf dieses \'X\' klicken. <br /><br /> Beachten Sie: Das Löschen eines Designs wird es nicht vom Server löschen, es wird nur im Forum nicht mehr zur Verfügung stehen. Sie werden das eigene Design via FTP vom Server löschen müssen. Löschen Sie niemals das \'Standard\'-Design.';

$helptxt['enableVideoEmbeding'] = 'Dies erlaubt die automatische Konversion von Standard-URLs in ein eingebettetes Video, wenn der Beitrag angesehen wird.  Unterstützt momentan nur YouTube-, Vimeo- und Dailymotion-Videolinks';
$helptxt['enableCodePrettify'] = 'Dies wird das Prettify-Script laden, das Code innerhalb von code-Tags farbig markieren wird.  Es fügt Codeschnipseln Stile hinzu, so dass Schlüsselwörter hervorgehoben werden und Ihre Benutzer den Code einfacher lesen können.';
$helptxt['xmlnews_enable'] = 'Erlaubt es Leuten, auf die <a href="%1$s?action=.xml;sa=news" target="_blank" class="new_win">letzten Neuigkeiten</a>
	und ähnliche Daten zu verlinken.  Es wird außerdem empfohlen, dass Sie die Anzahl der letzten Beiträge/Neuigkeiten
	begrenzen, weil, wenn RSS-Daten in einigen Clients wie Trillian angezeigt werden, diese vermutlich abgeschnitten werden.';
$helptxt['hotTopicPosts'] = 'Ändern Sie die Anzahl an Beiträgen, die ein Thema benötigt, um zu einem &quot;heißen&quot; oder
	&quot;brandheißen&quot; Thema zu werden.  Wählen Sie die Gefällt-mir-Option aus, um diesen Status auf der Anzahl an
	&quot;Gefällt mir&quot;s anstatt der Anzahl der Beiträge beruhen zu lassen';
$helptxt['globalCookies'] = 'Stellt Anmeldecookies über Subdomains hinaus zur Verfügung.  Wenn zum Beispiel...<br />
	Ihre Website auf http://www.meinserver.de/ liegt<br />
	und Ihr Forum unter http://forum.meinserver.de/,<br />
	gewährt diese Option der Website Zugriff auf die Cookies des Forums.  Aktivieren Sie diese Option nicht, wenn es andere Subdomains (wie hacker.meinserver.de) gibt, die nicht unter Ihrer Kontrolle stehen.<br />
	Diese Option funktioniert nicht, wenn lokale Cookies aktiviert sind.';
$helptxt['globalCookiesDomain'] = 'Legen Sie die Hauptdomain fest, die verwendet werden soll, wenn Anmeldecookies über Subdomains hinweg verfügbar sind';
$helptxt['httponlyCookies'] = 'Wird dies aktiviert, so kann mit Skriptsprachen wie JavaScript nicht auf die Cookies des Forums zugegriffen werden. Diese Einstellungen kann dabei helfen, Identitätsdiebstahl mittels XSS-Angriffen zu reduzieren. Dies kann Probleme mit einigen Drittanbieterskripten verursachen, aber es wird empfohlen, dies nach Möglichkeit zu aktivieren.';
$helptxt['secureCookies'] = 'Die Aktivierung dieser Option wird die für die Benutzer Ihres Forums erzeugten Cookies als sicher markieren. Aktivieren Sie diese Option nur, wenn Sie auf Ihrer Website ausnahmslos HTTPS verwenden, da sonst die Cookiebehandlung nicht mehr korrekt funktioniert!';
$helptxt['admin_session_lifetime'] = 'Dies steuert die maximale Dauer einer Administratorensitzung. Sobald diese Stoppuhr abläuft, wird die Sitzung beendet, was dazu führt, dass Sie Ihre Zugangsdaten erneut eingeben müssen, um weiterhin auf administrative Funktionen zugreifen zu können. Der Mindestwert beträgt 5 Minuten, der Höchstwert 14400 Minuten (entspricht einem Tag). Es wird aus Sicherheitsgründen dringend empfohlen, einen Wert unter 60 Minuten anzugeben.';
$helptxt['auto_admin_session'] = 'Dies legt fest, ob eine administrative Sitzung bereits bei der Anmeldung gestartet werden soll oder nicht.';
$helptxt['securityDisable'] = 'Dies <em>deaktiviert</em> die zusätzliche Passwortüberprüfung für den Administrationsbereich. Dies wird nicht empfohlen!';
$helptxt['securityDisable_why'] = 'Dies ist Ihr derzeitiges Passwort (das Sie auch zur Anmeldung verwenden).<br /><br />Dies eingeben zu müssen unterstützt dabei, sicherzugehen, dass Sie, was immer Sie gerade Administratives tun wollen, wirklich vorhaben und dass es wirklich <strong>Sie</strong> sind.';
$helptxt['securityDisable_moderate'] = 'Dies <em>deaktiviert</em> die zusätzliche Passwortüberprüfung für den Moderationsbereich. Dies wird nicht empfohlen!';
$helptxt['securityDisable_moderate_why'] = 'Dies ist Ihr derzeitiges Passwort (das Sie auch zur Anmeldung verwenden).<br /><br />Dies eingeben zu müssen unterstützt dabei, sicherzugehen, dass Sie, was immer Sie gerade Moderierendes tun wollen, wirklich vorhaben und dass es wirklich <strong>Sie</strong> sind.';
$helptxt['emailmembers'] = 'In dieser Nachricht können Sie ein paar &quot;Variablen&quot; verwenden.  Dies sind:<br />
	{$board_url} - Der URL Ihres Forums.<br />
	{$current_time} - Der momentane Zeitpunkt.<br />
	{$member.email} - Die E-Mail-Adresse des aktuellen Mitglieds.<br />
	{$member.link} - Der Verweis zum aktuellen Mitglied.<br />
	{$member.id} - Die Kennung des aktuellen Mitglieds.<br />
	{$member.name} - Der Name des aktuellen Mitglieds (zwecks Personalisierung).<br />
	{$latest_member.link} - Der Verweis zum neuesten Mitglied.<br />
	{$latest_member.id} - Die Kennung des neuesten Mitglieds.<br />
	{$latest_member.name} - Der Name des neuesten Mitglieds.';
$helptxt['attachmentEncryptFilenames'] = 'Die Verschlüsselung der Dateinamen von Dateianhängen erlaubt es Ihnen, mehr als einen Dateianhang des gleichen Namens zu haben, und erhöht die Sicherheit.  Es könnte es allerdings schwieriger machen, Ihre Datenbank neu aufzubauen, wenn etwas Schlimmes passiert ist.';

$helptxt['failed_login_threshold'] = 'Legt die Anzahl an fehlgeschlagenen Anmeldeversuchen fest, bevor der Benutzer auf die Passwort-vergessen-Seite weitergeleitet wird.';
$helptxt['loginHistoryDays'] = 'Die Anzahl an Tagen, für die der Anmeldeverlauf unter dem Profilverlauf eines Benutzers gespeichert wird. Standard sind 30 Tage.';
$helptxt['oldTopicDays'] = 'Sofern diese Option aktiviert ist, wird einem Benutzer eine Warnung angezeigt, wenn er versucht, auf ein Thema zu antworten, das seit der hier (in Tagen) angegebenen Zeit keine neuen Antworten erhalten hat. Setzen Sie dies auf 0, um diese Funktion zu deaktivieren.';
$helptxt['edit_wait_time'] = 'Anzahl an Sekunden, während derer ein Beitrag bearbeitet werden kann, bevor der Zeitpunkt der letzten Änderung protokolliert wird.';
$helptxt['edit_disable_time'] = 'Anzahl an Minuten, die vergehen dürfen, bevor ein Benutzer einen eigenen Beitrag nicht mehr bearbeiten darf. Setzen Sie dies zum Deaktivieren auf 0.<br /><br /><em>Beachten Sie: Dies betrifft nicht diejenigen Benutzer, die die Beiträge anderer Benutzer uneingeschränkt bearbeiten dürfen.</em>';
$helptxt['preview_characters'] = 'Diese Option legt die Anzahl an verfügbaren Zeichen für den ersten und den letzten Beitrag in der Themenvorschau fest.  <strong>Beachten Sie,</strong> dass dies die Informationen nur für das Design verfügbar macht, das Design muss die Einstellung &quot;Beitragsvorschau im Nachrichtenindex anzeigen&quot; selbst unterstützen';
$helptxt['posts_require_captcha'] = 'Diese Einstellung wird es voraussetzen, dass Benutzer jedes Mal, wenn sie einen Beitrag veröffentlichen möchten, einer Anti-Spambot-Überprüfung standhalten. Nur Benutzer mit einer niedrigeren als der festgelegten Anzahl an Beiträgen müssen den Code eingeben - dies sollte bei der Bekämpfung automatischer Spamskripte helfen.';
$helptxt['enableSpellChecking'] = 'Aktiviert die Rechtschreibprüfung. Sie MÜSSEN die pspell-Bibliothek auf Ihrem Server installieren und Ihre PHP-Installation so konfigurieren, dass sie diese verwendet. Ihr Server verfügt ' . (function_exists('pspell_new') ? '' : 'NICHT') . ' über diese Bibliothek.';
$helptxt['disable_wysiwyg'] = 'Diese Einstellung verbietet es allen Benutzern, den WYSIWYG-Editor (&quot;What You See Is What You Get&quot;) auf der Beitragsseite zu verwenden.';
$helptxt['lastActive'] = 'Legt die Anzahl an Minuten fest, binnen derer Benutzer auf der Startseite als aktiv angezeigt werden. Standard sind 15 Minuten';

$helptxt['customoptions'] = 'Dieser Bereich definiert die Optionen, aus denen ein Benutzer aus einer Aufklappliste auswählen kann. Hier gilt es einige wichtige Punkte zu beachten:
	<ul class="normallist">
		<li><strong>Standardoption:</strong> Jedwelche Option, deren nebenstehendes Optionsfeld ausgewählt ist, wird als Standardauswahl gesetzt, wenn ein Benutzer sein Profil ausfüllt.</li>
		<li><strong>Optionen entfernen:</strong> Um eine Option zu entfernen, leeren Sie einfach ihr Textfeld - bei allen Benutzern, die sie ausgewählt haben, wird sie daraufhin zurückgesetzt.</li>
		<li><strong>Optionen umsortieren:</strong> Sie können die Optionen umsortieren, indem Sie Text zwischen den Kästen verschieben. Sie müssen allerdings - wichtiger Hinweis - sicherstellen, dass Sie den Text beim Umsortieren <strong>nicht</strong> ändern, da sonst Benutzerdaten verloren gehen.</li>
	</ul>';

$helptxt['autoOptDatabase'] = 'Diese Option aktiviert die Optimierung der Datenbank im festgelegten Intervall (in Tagen).  Setzen Sie dies auf 1 für eine tägliche Optimierung.  Sie können auch eine maximale Anzahl an Onlinebenutzern angeben, so dass Ihr Server nicht überlastet wird oder Sie zu viele Benutzer verärgern.';
$helptxt['autoFixDatabase'] = 'Dies wird automatisch defekte Tabellen reparieren und fortsetzen, als wäre nichts geschehen.  Dies kann nützlich sein, denn die einzige Möglichkeit zur Behebung ist es, die Tabelle zu REPARIEREN, und auf diese Weise wird Ihr Forum nicht unerreichbar sein, ohne dass Sie es bemerken.  Sie werden eine E-Mail erhalten, wenn es geschieht.';

$helptxt['enableParticipation'] = 'Dies zeigt an Themen, in denen der Benutzer einen Beitrag geschrieben hat, ein kleines Symbol an.';
$helptxt['enableFollowup'] = 'Dies erlaubt es Mitgliedern, neue Themen mit einem Zitat aus irgendeinem Beitrag zu eröffnen.';

$helptxt['db_persist'] = 'Hält die Verbindung aufrecht, um die Geschwindigkeit zu erhöhen.  Wenn Sie nicht auf einem eigenen Server sind, könnte dies Probleme mit Ihrem Anbieter verursachen.';
$helptxt['ssi_db_user'] = 'Optionale Einstellung zur Verwendung anderer Datenbankzugangsdaten, wenn Sie SSI.php benutzen.';

$helptxt['queryless_urls'] = 'Dies ändert das Format von URLs ein wenig, so dass Suchmaschinen sie lieber mögen.  Sie werden etwa wie index.php/topic,1.0.html aussehen.';
$helptxt['countChildPosts'] = 'Die Aktivierung dieser Option bedeutet, dass Beiträge und Themen in einem Unterforum auf der Startseite zu denen des Elternforums addiert werden.<br /><br />Dies wird die Dinge merklich verlangsamen, bedeutet aber, dass ein Elternforum ohne enthaltene Beiträge nicht \'0\' anzeigen wird.';
$helptxt['allow_ignore_boards'] = 'Die Aktivierung dieser Option wird es Benutzern erlauben, Foren auszuwählen, die sie ignorieren möchten.';
$helptxt['deny_boards_access'] = 'Die Aktivierung dieser Option wird es Ihnen erlauben, den Zugriff auf bestimmte Foren basierend auf dem Benutzergruppenzugriff zu verweigern';

$helptxt['who_enabled'] = 'Diese Option erlaubt es Ihnen, die Möglichkeit, dass Benutzer sehen können, wer online ist und was derjenige gerade tut, an- und abzuschalten.';

$helptxt['recycle_enable'] = '&quot;Wirft&quot; gelöschte Themen und Beiträge in das angegebene Forum.';

$helptxt['enableReportPM'] = 'Diese Option erlaubt es Ihren Benutzern, erhaltene private Nachrichten den Administratoren zu melden. Dies könnte dabei helfen, Missbrauch des Private-Nachrichten-Systems zu verfolgen.';
$helptxt['max_pm_recipients'] = 'Diese Option erlaubt es Ihnen, die maximale Anzahl an Empfängern pro privater Nachricht seitens eines Mitglieds zu begrenzen. Sie kann verwendet werden, um Spammissbrauch des Private-Nachrichten-Systems einzudämmen. Beachten Sie, dass Benutzer mit der Befugnis, Rundbriefe zu versenden, von dieser Beschränkung ausgenommen sind. Setzen Sie dies auf null, um die Begrenzung aufzuheben.';
$helptxt['pm_posts_verification'] = 'Diese Einstellung wird Benutzer dazu zwingen, jedes Mal, wenn sie eine private Nachricht versenden möchten, einen Code auf einer Verifizierungsgrafik einzugeben. Nur Benutzer mit einer Beitragsanzahl unterhalb des eingestellten Wertes müssen den Code eingeben - dies sollte dabei helfen, automatische Spamskripte zu bezwingen.';
$helptxt['pm_posts_per_hour'] = 'Dies wird die Anzahl an privaten Nachrichten, die ein Benutzer pro Stunde versenden kann, begrenzen. Dies betrifft weder Administratoren noch Moderatoren.';

$helptxt['default_personal_text'] = 'Legt den Standardtext fest, der im Profil eines neuen Benutzers als sein &quot;persönlicher Text&quot; erscheinen wird. Diese Option ist nicht verfügbar, wenn persönliche Texte deaktiviert sind oder Benutzer sie bei der Registrierung selbst festlegen können.';

$helptxt['modlog_enabled'] = 'Protokolliert alle Moderationshandlungen.';

$helptxt['registration_method'] = 'Diese Option legt fest, welche Art der Registrierung Leuten angeboten wird, die Ihrem Forum beitreten möchten. Sie können auswählen aus:<br /><br />
	<ul class="normallist">
		<li>
			<strong>Registrierung deaktiviert</strong><br />
				Deaktiviert den Registrierungsvorgang, was bedeutet, dass Ihr Forum keine neuen Mitglieder aufnehmen kann.<br />
		</li><li>
			<strong>Sofortige Registrierung</strong><br />
				Neue Mitglieder können sich sofort anmelden und neue Beiträge verfassen, sobald sie sich in Ihrem Forum registriert haben.<br />
		</li><li>
			<strong>E-Mail-Aktivierung</strong><br />
				Ist diese Option aktiviert, so wird jedem Mitglied, das sich im Forum registriert, ein Link per E-Mail gesendet, den es zunächst anklicken muss, bevor es ein vollwertiges Mitglied werden kann.<br />
		</li><li>
			<strong>Administrative Freischaltung</strong><br />
				Diese Option sorgt dafür, dass alle neuen Mitglieder nach der Registrierung zunächst von einem Administrator freigeschaltet werden müssen, bevor sie vollwertige Mitglieder werden können.
		</li>
	</ul>';
$helptxt['register_openid'] = '<strong>Mit OpenID authentifizieren</strong><br />
	OpenID erlaubt es Ihnen, dieselben Anmeldedaten für mehrere Websites zu verwenden, um Ihre Onlineerfahrung zu vereinfachen. Um OpenID zu verwenden, müssen Sie zunächst ein OpenID-Konto erstellen - eine Liste von Anbietern kann auf der <a href="http://openid.net/" target="_blank">offiziellen OpenID-Website</a> eingesehen werden.<br /><br />
	Sobald Sie ein OpenID-Konto besitzen, geben Sie einfach Ihren eindeutigen Identifizierungs-URL in das OpenID-Feld ein und senden Sie es ab. Sie werden dann auf die Website Ihres Anbieters weitergeleitet, um Ihre Identität zu bestätigen, bevor Sie hierher zurückgelangen.<br /><br />
	Bei Ihrem ersten Besuch auf dieser Site werden Sie gebeten, ein paar Details anzugeben, bevor Sie erkannt werden; anschließend können Sie sich hier nur mit Ihrer OpenID anmelden und Ihre Profileinstellungen ändern.<br /><br />
	Für weitere Informationen besuchen Sie bitte die For more information please visit the <a href="http://openid.net/" target="_blank">offizielle OpenID-Website</a>';

$helptxt['send_validation_onChange'] = 'Wenn diese Option angekreuzt ist, müssen alle Mitglieder, die ihre E-Mail-Adresse in ihrem Profil ändern, ihr Konto unter Zuhilfenahme einer E-Mail, die an die neue Adresse gesendet wird, erneut aktivieren';
$helptxt['send_welcomeEmail'] = 'Wenn diese Option aktiviert ist, wird allen neuen Mitgliedern eine E-Mail gesendet, die sie in Ihrem Forum willkommen heißt';
$helptxt['password_strength'] = 'Diese Einstellung legt die nötige Stärke für von Ihren Benutzern ausgewählte Passwörter fest. Je stärker das Passwort ist, desto schwerer sollte es sein, das jeweilige Benutzerkonto zu kompromittieren.
	Ihre verfügbaren Optionen lauten:
	<ul class="normallist">
		<li><strong>Niedrig:</strong> Das Passwort muss mindestens vier Zeichen lang sein.</li>
		<li><strong>Mittel:</strong> Das Passwort muss mindestens acht Zeichen lang sein und kann kein Teil eines Benutzernamens oder einer E-Mail-Adresse sein.</li>
		<li><strong>Hoch:</strong> Wie mittel, aber das Passwort muss außerdem Groß- und Kleinbuchstaben und mindestens eine Ziffer enthalten.</li>
	</ul>';
$helptxt['enable_password_conversion'] = 'Nach Aktivierung dieser Einstellung wird ElkArte versuchen, Passwörter, die in anderen Formaten vorliegen, zu erkennen und zur Verwendung in dieser Software zu konvertieren.  Typischerweise wird dies für konvertierte Foren genutzt, es könnte aber auch andere Anwendungsfälle geben.  Die Deaktivierung verhindert, dass sich ein Benutzer nach einer Konvertierung mit seinen bisherigen Benutzerdaten anmeldet, und zwingt ihn zu einer Zurücksetzung seines Passworts.';

$helptxt['coppaAge'] = 'Der in diesem Feld angegebene Wert legt das Mindestalter fest, das neue Mitglieder erreicht haben müssen, damit ihnen sofortiger Zugriff auf das Forum gewährt wird.
	Bei der Registrierung werden sie darum gebeten, zu bestätigen, dass sie alt genug sind, und andernfalls entweder abgewiesen oder bis zur elterlichen Zustimmung suspendiert - abhängig von der ausgewählten Registrierungsart.
	Wird hier ein Wert von 0 ausgewählt, so werden alle anderen Altersbeschränkungen ignoriert.';
$helptxt['coppaType'] = 'Falls Altersbeschränkungen aktiv sind, definiert diese Einstellung, was passieren soll, wenn ein Benutzer unterhalb des Mindestalters sich zu registrieren versucht. Es gibt zwei mögliche Optionen:
	<ul class="normallist">
		<li>
			<strong>Registrierung abweisen:</strong><br />
				Die Registrierung jedes neuen Mitglieds unterhalb des Mindestalters wird umgehend abgewiesen.<br />
		</li><li>
			<strong>Einverständniserklärung eines Erziehungsberechtigten voraussetzen</strong><br />
				Jedes neue Mitglied, das sich zu registrieren versucht und das Mindestalter noch nicht erreicht hat, wird angemeldet, jedoch nicht freigeschaltet, und erhält ein Formular, auf dem seine Eltern oder Erziehungsberechtigten ihr Einverständnis für die Mitgliedschaft im Forum mitteilen müssen.
				Außerdem werden die Kontaktdaten angezeigt, die auf der Einstellungsseite angegeben wurden, so dass sie das Formular per E-Mail oder Fax übermitteln können.
		</li>
	</ul>';
$helptxt['coppaPost'] = 'Die Kontaktfelder werden benötigt, damit Formulare zur Beantragung einer Registrierung für minderjährige Benutzer an den Administrator gesendet werden können. Die Kontaktdaten werden all diesen neuen Benutzern angezeigt und sind Voraussetzung für die Genehmigung der Eltern/Erziehungsberechtigten. Außerdem muss eine Postadresse oder Faxnummer angegeben werden.';

$helptxt['allow_hideOnline'] = 'Nach der Aktivierung dieser Option werden alle Mitglieder dazu in der Lage sein, ihren Onlinestatus vor anderen Benutzern (außer Administratoren) zu verstecken. Falls deaktiviert, können nur Benutzer, die das Forum moderieren, ihre Gegenwart verbergen. Beachten Sie, dass die Deaktivierung dieser Option nicht den aktuellen Status von Mitgliedern ändern wird - sie hält sie nur davon ab, sich künftig zu verstecken.';
$helptxt['make_email_viewable'] = 'Falls diese Option aktiviert ist, wird es möglich sein, anderen Benutzern eine E-Mail zu senden. Es werden keine E-Mail-Adressen öffentlich angezeigt, so dass kein Risiko für Ihre Benutzer besteht, Opfer von Spam in Folge von Besuchen von Adresssammlern in Ohrem Forum zu werden. Beachten Sie, dass diese Einstellung nicht die Benutzereinstellung für die Aktivierung des E-Mail-Formulars außer Kraft setzt.';
$helptxt['meta_keywords'] = 'Diese Schlüsselwörter werden mit jeder Seitenausgabe versandt, um Suchmaschinen usw. den Hauptinhalt Ihrer Site darzulegen. Es sollte sich um eine kommagetrennte Liste von Wörtern handeln, HTML sollte nicht verwendet werden.';

$helptxt['latest_support'] = 'Dieser Bereich zeigt Ihnen einige der häufigsten Probleme und Fragen zu Ihrer Serverkonfiguration an. Keine Sorge, diese Informationen werden nicht protokolliert oder so.<br /><br />Wenn die Anzeige auf &quot;Rufe Supportinformationen ab...&quot; stehen bleibt, kann Ihr Rechner vermutlich keine Verbindung zur Website herstellen.'; // translator note: we're a bit informal here intentionally.
$helptxt['latest_packages'] = 'Hier können Sie einige der beliebtesten und ein paar zufällige Pakete mit schneller und einfacher Installation sehen.<br /><br />Wenn dieser Bereich nicht angezeigt wird, kann Ihr Rechner vermutlich keine Verbindung zu <a href="http://www.elkarte.net/" target="_blank" class="new_win">www.elkarte.net/</a> herstellen.';
$helptxt['latest_themes'] = 'Dieser Bereich zeigt ein paar der neuesten und beliebtesten Designs von <a href="http://www.elkarte.net/" target="_blank" class="new_win">www.elkarte.net/</a> an.  Er könnte allerdings nicht korrekt angezeigt werden, wenn Ihr Rechner <a href="http://www.elkarte.net/" target="_blank" class="new_win">www.elkarte.net/</a> nicht findet.';

$helptxt['secret_why_blank'] = 'Zu Ihrer Sicherheit werden Ihr Passwort und die Antwort auf Ihre geheime Frage verschlüsselt, so dass weder ElkArte noch irgendjemand anders Ihnen sagen kann, wie sie lauten.';
$helptxt['moderator_why_missing'] = 'Da Moderation auf einer Per-Forum-Basis stattfindet, müssen Sie Mitglieder in der <a href="%1$s?action=admin;area=manageboards" target="_blank" class="new_win">Forenverwaltung</a> zu Moderatoren machen.';

$helptxt['permissions'] = 'Befugnisse sind Ihre Möglichkeit, Gruppen bestimmte Aktionen zu erlauben oder sie ihnen zu verwehren.<br /><br />Sie können mithilfe der Auswahlkästen mehrere Foren gleichzeitig ändern oder die Befugnisse für eine bestimmte Gruppe per Klick auf \'Ändern\' ansehen.';
$helptxt['permissions_board'] = 'Falls ein Forum auf \'Global\' gesetzt ist, so bedeutet dies, dass es keine besonderen Befugnisse besitzt.  \'Lokal\' heißt, dass es seine eigenen Befugnisse besitzt - getrennt von den globalen.  Dies erlaubt es Ihnen, ein Forum zu haben, das mehr oder weniger Befugnisse als ein anderes hat, ohne dass Sie sie für jedes einzelne Forum festlegen müssen.';
$helptxt['permissions_quickgroups'] = 'Diese erlauben es Ihnen, &quot;Standard&quot;-Befugnissätze zu verwenden - \'Standard\' heißt \'nichts Besonderes\', \'beschränkt\' heißt \'wie ein Gast\', \'Moderator\' heißt \'was ein Moderator hat\', \'Wartung\' schließlich umfasst Befugnisse, die denen eines Administrators sehr nahe kommen.';
$helptxt['permissions_deny'] = 'Das Verweigern von Befugnissen kann nützlich sein, wenn Sie sie bestimmten Mitgliedern entziehen möchten. Sie können Mitgliedern, denen Sie eine Befugnis verweigern möchten, eine Benutzergruppe mit einer \'Verweigern\'-Befugnis zuweisen.<br /><br />Verwenden Sie dies achtsam, eine entzogene Befugnis bleibt unabhängig von anderen Benutzergruppen, in denen ein Mitglied ist, entzogen.';
$helptxt['permissions_postgroups'] = 'Die Aktivierung von Befugnissen für beitragsbasierte Gruppen wird es Ihnen ermöglichen, Mitgliedern mit einer bestimmten Anzahl an Beiträgen gesonderte Berechtigungen zu erteilen. Diese Berechtigungen werden den Befugnissen der übrigen Benutzergruppen des Mitglieds <em>hinzugefügt</em>.';
$helptxt['membergroup_guests'] = 'Die Benutzergruppe der Gäste enthält alle Benutzer, die nicht angemeldet sind.';
$helptxt['membergroup_regular_members'] = 'Normale Mitglieder sind alle Mitglieder, die angemeldet sind, denen jedoch keine primäre Benutzergruppe zugewiesen wurde.';
$helptxt['membergroup_administrator'] = 'Der Administrator kann definitionsgemäß alles tun und jedes Forum sehen. Für ihn gibt es daher keine Befugniseinstellungen.';
$helptxt['membergroup_moderator'] = 'Die Benutzergruppe der Moderatoren ist eine besondere Benutzergruppe. Befugnisse und Einstellungen für diese Gruppen gelten für Moderatoren, aber <em>nur in den Foren, die sie moderieren</em>. Außerhalb dieser Foren sind sie lediglich ein normales Mitglied wie jedes andere auch.';
$helptxt['membergroups'] = 'Es gibt zwei Arten von Gruppen, in die Ihre Benutzer gehören können. Dies sind:
	<ul class="normallist">
		<li><strong>Normale Gruppen:</strong> Eine normale Gruppe ist eine Gruppe, die Mitgliedern nicht automatisch zugeteilt wird. Um eine Gruppe einem Benutzer zuzuweisen, gehen Sie einfach in sein Profil und klicken Sie auf &quot;Kontoeinstellungen&quot;. Von hier aus können Sie ihn einer beliebigen Anzahl an normalen Gruppen zuweisen, denen er fortan angehören wird.</li>
		<li><strong>Beitragsgruppen:</strong> Anders als normalen Gruppen können Benutzer beitragsbasierten Gruppen nicht zugewiesen werden. Stattdessen werden Mitglieder einer solchen Gruppe automatisch zugewiesen, wenn sie die Mindestanzahl an nötigen Beiträgen für diese Gruppe erreicht haben.</li>
	</ul>';

$helptxt['calendar_how_edit'] = 'Sie können diese Ereignisse per Klick auf das rote Sternchen (*) neben ihren Namen ändern.';

$helptxt['maintenance_backup'] = 'Dieser Bereich erlaubt es Ihnen, eine Kopie all der Beiträge, Einstellungen, Mitglieder und weiterer Informationen über Ihr Forum in eine sehr große Datei zu speichern.<br /><br />Aus Sicherheitsgründen wird empfohlen, dass Sie dies oft tun, womöglich etwa wöchentlich.';
$helptxt['maintenance_rot'] = 'Dies erlaubt es Ihnen, alte Themen <strong>vollständig</strong> und <strong>unwiderruflich</strong> zu entfernen.  Es wird empfohlen, dass Sie zunächst versuchen, eine Sicherungskopie anzulegen, falls Sie versehentlich etwas entfernen, das Sie nicht entfernen wollten.<br /><br />Verwenden Sie diese Option sorgsam.';
$helptxt['maintenance_members'] = 'Dies erlaubt es Ihnen, Benutzerkonten <strong>vollständig</strong> und <strong>unwiderruflich</strong> aus Ihrem Forum zu entfernen.  Es wird <strong>wärmstens</strong> empfohlen, dass Sie zunächst versuchen, eine Sicherungskopie anzulegen, falls Sie versehentlich etwas entfernen, das Sie nicht entfernen wollten.<br /><br />Verwenden Sie diese Option sorgsam.';

$helptxt['avatar_default'] = 'Ist diese Option aktiviert, so wird für alle Benutzer ohne ein eigenes Benutzerbild ein Standardavatar angezeigt. Die Datei \'default_avatar.png\' liegt im Ordner <em>images</em> im Designverzeichnis.';
$helptxt['avatar_server_stored'] = 'Dies erlaubt es Ihren Mitgliedern, ihren Avatar aus einer Reihe an auf Ihrem Server gespeicherten Avataren selbst auszuwählen.  Diese liegen im Allgemeinen am selben Ort wie das Forum im Avatarverzeichnis.<br />Ein Tipp: Indem Sie in diesem Verzeichnis Ordner erstellen, können Sie sie zu &quot;Avatarkategorien&quot; machen.';
$helptxt['avatar_external'] = 'Ist dies aktiviert, so können Ihre Mitglieder den URL ihres eigenen Benutzerbildes eingeben.  Der Nachteil hiervon ist in manchen Fällen, dass sie Avatare verwenden könnten, die übermäßig groß sind oder Bilder darstellen, die Sie in Ihrem Forum nicht haben möchten.';
$helptxt['avatar_download_external'] = 'Wenn Sie dies aktivieren, wird auf den vom Benutzer angegebenen URL zugegriffen, um den Avatar an dieser Stelle herunterzuladen. Bei Erfolg wird der Avatar wie ein hochladbares Benutzerbild behandelt.';
$helptxt['avatar_upload'] = 'Diese Option funktioniert ähnlich wie &quot;Externe Avatare erlauben&quot;, außer dass Sie eine bessere Kontrolle über die Avatare, eine schnellere Größenänderung und den Vorteil erzielen, dass Ihre Mitglieder keinen gesonderten Ordner brauchen, in den sie Avatare hochladen können.<br /><br />Der Nachteil ist allerdings, dass Avatare viel Platz auf Ihrem Server belegen können.';
$helptxt['avatar_download_png'] = 'PNGs sind größer, bieten jedoch eine bessere Kompressionsqualität.  Ist dies nicht aktiviert, so wird stattdessen das JPEG-Format verwendet - das oft kleiner, jedoch auch schlechterer Qualität oder verschwommen ist.';
$helptxt['gravatar'] = 'Gravatar (global erkannter Avatar) ist ein Dienst, der weltweit eindeutige Avatare zur Verfüging stellt. Für weitere Informationen besuchen Sie bitte die Gravatar-<a href="http://www.gravatar.com" target="_blank"><strong>Website</strong>.</a>';
$helptxt['gravatar_rating'] = 'Gravatar erlaubt es Benutzern, ihre Bilder selbst zu bewerten, so dass sie anzeigen können, ob ein Bild für eine bestimmte Zielgruppe angemessen ist. Standardmäßig werden nur als \'G\' bewertete Bilder angezeigt, es sei denn, Sie legen fest, dass Sie auch gern höhere Ränge sehen möchten.<br /><br /><ul><li><strong>g:</strong> angemessen für die Anzeige auf allen Webseiten mit beliebiger Zielgruppe.</li><li><strong>pg:</strong> kann rüde Gesten, provokant gekleidete Personen, mindere Schimpfwörter oder milde Gewalt enthalten.</li><li><strong>r:</strong> kann Dinge wie herbe Obszönität, heftige Gewalt, Nacktheit oder den Gebrauch harter Drogen enthalten.</li><li><strong>x:</strong>kann Hardcoresex-Darstellungen oder äußerst verstörende Gewalt enthalten.</li></ul>';
$helptxt['custom_avatar_enabled'] = 'Es wird empfohlen, dass Sie dies zwecks höchster Geschwindigkeit aktivieren, da es sowohl die Prozessor- als auch die Datenbanklast beim Ansehen von Seiten mit Avataren reduziert.<br />Sie müssen ein öffentlich zugängliches Verzeichnis, in dem Avatare gespeichert werden sollen, und den öffentlich zugänglichen URL zu diesem Verzeichnis angeben.  Beispiel: Das Verzeichnis /home/ihrforum/public_html/NeuesAvatarVerzeichnis und der URL http://www.ihrforum.de/NeuesAvatarVerzeichnis';
$helptxt['disableHostnameLookup'] = 'Dies deaktiviert das Nachschlagen von Hostnamen, das auf manchen Servern sehr langsam ist.  Beachten Sie, dass dies Sperrungen weniger effizient macht.';

$helptxt['search_weight_commonheader'] = 'Gewichtungsfaktoren werden verwendet, um die Relevanz eines Suchergebnisses festzulegen. Ändern Sie diese Faktoren anhand der Dinge, die für Ihr Forum besodners wichtig sind. Das Forum einer Nachrichtensite könnte zum Beispiel einen relativ hohen Wert für das Alter des neuesten passenden Beitrags verwenden wollen. Alle Werte sind relativ zueinander und sollten positive Ganzzahlen sein.<br /><br />';
$helptxt['search_weight_frequency'] = 'Dieser Faktor zählt die Menge passender Beiträge und teilt sie durch die Gesamtzahl an Beiträgen in einem Thema.';
$helptxt['search_weight_age'] = 'Dieser Faktor bewertet das Alter des letzten passenden Beitrags in einem Thema. Je neuer der Beitrag ist, desto höher sein Wert.';
$helptxt['search_weight_length'] = 'Dieser Faktor basiert auf der Größe eines Themas. Je mehr Beiträge es enthält, desto höher sein Wert.';
$helptxt['search_weight_subject'] = 'Dieser Faktor schaut nach, ob ein Suchbegriff innerhalb eines Thementitels gefunden werden kann.';
$helptxt['search_weight_first_message'] = 'Dieser Faktor schaut nach, ob ein Treffer im ersten Beitrag eines Themas gefunden werden kann.';
$helptxt['search_weight_sticky'] = 'Dieser Faktor schaut nach, ob ein Thema angeheftet ist, und erhöht dessen Relevanz, wenn es das ist.';
$helptxt['search'] = 'Nehmen Sie hier alle Einstellungen für die Suchfunktion vor.';
$helptxt['search_why_use_index'] = 'Ein Suchindex kann die Geschwindigkeit von Suchanfragen in Ihrem Forum nennenswert verbessern. Besonders dann, wenn die Anzahl an Beiträgen in einem Forum größer wird, kann das Suchen ohne einen Index lange dauern und die Last auf Ihrer Datenbank vergrößern. Wenn Ihr Forum mehr als 50.000 Beiträge hat, sollten Sie in Erwägung ziehen, einen Suchindex zu erstellen, um eine dauerhaft hohe Geschwindigkeit Ihres Forums sicherzustellen.<br /><br />Beachten Sie, dass ein Suchindex ein wenig Speicherplatz belegen kann. Ein Volltextindex ist ein eingebauter Index der Datenbank. Er ist vergleichsweise klein (ungefähr so groß wie die Beitragstabelle), aber viele allgemeine Begriffe werden nicht indiziert und er kann sich bei komplexen Abfragen als langsam herausstellen. Der eigene Index ist größer (abhängig von Ihrer Konfiguration kann er bis zu dreimal so groß wie die Beitragstabelle werden), aber seine Geschwindigkeit ist oft besser als die des Volltextindexes und er indiziert die meisten Begriffe.';

$helptxt['see_admin_ip'] = 'IP-Adressen werden Administratoren und Moderatoren angezeigt, um die Moderation zu vereinfachen und es leichter zu machen, Leute zu verfolgen, die Böses im Sinn haben.  Beachten Sie, dass IP-Adressen nicht immer eindeutig identifizierbar sind und die IP-Adressen der meisten Leute sich wiederkehrend ändern.<br /><br />Mitglieder können auch ihre eigenen IPs sehen.';
$helptxt['see_member_ip'] = 'Ihre IP-Adresse wird nur Ihnen und Moderatoren angezeigt.  Beachten Sie, dass diese Informationen keine eindeutige Identifikation ermöglichen und dass die meisten IPs sich wiederkehrend ändern.<br /><br />Sie können die IP-Adressen anderer Mitglieder nicht sehen und diese die Ihre nicht.';
$helptxt['whytwoip'] = 'Verschiedene Methoden werden verwendet, um Benutzer-IP-Adressen zu ermitteln. Normalerweise resultieren diese beiden Methoden in der gleichen Adresse, aber in manchen Fällen könnte mehr als eine Adresse entdeckt werden. In diesem Fall werden beide Adressen protokolliert und für Sperrprüfungen (und so weiter) verwendet. Sie können auf jede Adresse klicken, um sie zu verfolgen únd bei Bedarf zu sperren.';

$helptxt['ban_cannot_post'] = 'Die \'kann keine Beiträge erstellen\'-Berschränkung versetzt das Forum für den gesperrten Benutzer in einen Lesemodus. Der Benutzer kann keine neuen Themen erstellen oder auf bestehende Themen antworten, private Nachrichten versenden oder an Umfragen teilnehmen. Der gesperrte Benutzer kann jedoch noch immer private Nachrichten und Themen lesen.<br /><br />Eine Warnung wird auf diese Weise gesperrten Benutzern angezeigt.';

$helptxt['posts_and_topics'] = '
	<ul class="normallist">
		<li>
			<strong>Beitragseinstellungen</strong><br />
			Ändern Sie Einstellungen bezüglich des Verfassens von Beiträgen und die Art, wie sie angezeigt werden. Sie können hier auch die Rechtschreibprüfung aktivieren.
		</li><li>
			<strong>BBCode</strong><br />
			Aktivieren Sie den Code, der die Formatierung von Beiträgen ermöglicht. Legen Sie auch fest, welche BBCodes erlaubt sind und welche nicht.
		</li><li>
			<strong>Wortzensur</strong>
			Um die Sprache in Ihrem Forum unter Kontrolle zu halten, können Sie bestimmte Wörter zensieren. Diese Funktion erlaubt es Ihnen, verbotene Wörter in unschuldige Versionen umzuwandeln.
		</li><li>
			<strong>Themeneinstellungen</strong>
			Ändern Sie themenbezogene Einstellungen; die Anzahl an Themen pro Seite, ob angeheftete Themen aktiviert sind oder nicht, die Anzahl an Beiträgen, die nötig sind, damit ein Thema heiß wird, und so weiter.
		</li>
	</ul>';
$helptxt['allow_no_censored'] = 'Wenn aktiviert, erlaubt diese globale Einstellung Ihren Mitgliedern, die Wortzensur in ihrem Benutzerprofil über die Designeinstellungen abzuschalten. Ihre Fähigkeit, die Wortzensur zu deaktivieren, wird weiterhin über ihr Befugnisprofil beschränkt.';
$helptxt['spider_mode'] = 'Legt die Protokollierungsstufe fest.<br />
Standard - Protokolliert minimale Suchmaschinenaktivität.<br />
Moderat - Stellt genauere Statistiken zur Verfügung.<br />
Aggressiv - Wie &quot;Moderat&quot;, protokolliert jedoch auch Daten für jede besuchte Seite.';

$helptxt['spider_group'] = 'Mittels Auswählens einer beschränkten Gruppe wird ein Gast, sofern er als Suchmaschine identifiziert wird, zusätzlich zu den normalen Gastbefugnissen automatisch alle &quot;Verbieten&quot;-Befugnisse dieser Gruppe erhalten. Sie können dies verwenden, um einer Suchmaschine weniger Zugriffsrechte als einem normalen Gast einzuräumen. Sie könnten zum Beispiel eine neue Gruppe namens &quot;Suchmaschinen&quot; erstellen und hier auswählen. Sie können dann dieser Gruppe die Anzeige von Profilen verbieten, um Suchmaschinen davon abzuhalten, die Profile Ihrer Mitglieder zu indizieren.<br />Hinweis: Die Suchmaschinenerkennung ist nicht perfekt und eine Suchmaschine kann von Benutzern simuliert werden, so dass wir nicht garantieren können, dass diese Funktion Inhalte nur für die hinzugefügten Suchmaschinen beschränkt.';
$helptxt['show_spider_online'] = 'Diese Einstellungen erlaubt es Ihnen, auszuwählen, ob Suchmaschinen in der Onlineliste auf der Startseite des Forums und auf der &quot;Wer ist online?&quot;-Seite angezeigt werden sollen. Die Optionen sind:
	<ul class="normallist">
		<li>
			<strong>Gar nicht</strong><br />
			Suchmaschinen werden allen Benutzern nur als Gäste angezeigt.
		</li><li>
			<strong>Anzahl an Spidern anzeigen</strong><br />
			Die Startseite des Forums wird die Anzahl an Suchmaschinen anzeigen, die derzeit das Forum besuchen.
		</li><li>
			<strong>Namen der Spider anzeigen</strong><br />
			Jeder Suchmaschinenname wrd aufgedeckt, so dass Benutzer sehen können, wie viele Spider von jeder Suchmaschine momentan im Forum unterwegs sind - dies wirkt sich sowohl auf die Onlineliste als auch auf die Onlineseite aus.
		</li><li>
			<strong>Namen der Spider anzeigen - nur Admin</strong><br />
			Wie oben, gilt aber nur für Administratoren - allen anderen Benutzern werden Spider als Gäste angezeigt.
		</li>
	</ul>';

$helptxt['birthday_email'] = 'Wählen Sie den Text der Geburtstags-E-Mail aus, den Sie verwenden möchten.  Eine Vorschau wird in den Betreff- und Inhalt-Feldern angezeigt.<br /><strong>Hinweis:</strong> Die Änderung dieser Option aktiviert nicht automatisch die Geburtstags-E-Mails.  Um dies zu tun, gehen Sie zu den <a href="%1$s?action=admin;area=scheduledtasks;%3$s=%2$s" target="_blank" class="new_win">geplanten Aufgaben</a> und aktivieren Sie die Aufgabe für die Geburtstags-E-Mails.';
$helptxt['pm_bcc'] = 'Beim Versand einer privaten Nachricht können Sie einen Empfänger als BCC oder &quot;Blindkopie&quot; auswählen. BCC-Empfänger werden anderen Empfängern einer Nachricht nicht angezeigt.';

$helptxt['move_topics_maintenance'] = 'Dies wird es Ihnen erlauben, alle Beiträge von einem Forum in ein anderes zu verschieben.';
$helptxt['maintain_reattribute_posts'] = 'Sie können diese Funktion verwenden, um Gastbeiträge in Ihrem Forum einem registrierten Mitglied zuzuweisen. Dies ist nützlich, wenn ein Benutzer zum Beispiel sein Konto gelöscht, sich dann aber umentschieden hat und seine alten Beiträge wieder an sein (neues) Konto binden lassen möchte.';
$helptxt['chmod_flags'] = 'Sie können die Befugnisse, die Sie den ausgewählten Dateien zuteilen möchten, händisch festlegen. Um dies zu tun, geben Sie den chmod-Wert als nummerischen Wert (Oktett) ein. Beachten Sie, dass dieser Wert unter Microsoft Windows keine Auswirkungen hat.';

$helptxt['postmod'] = 'Dieser Bereich erlaubt es Mitgliedern des Moderationsteams (mit ausreichenden Befugnissen), jegliche Beiträge und Themen zu überprüfen, bevor sie freigegeben werden.';

$helptxt['field_show_enclosed'] = 'Schließt die Benutzereingabe in Text oder HTML-Code ein.  Dies erlaubt es Ihnen, weitere Sofortnachrichtenanbieter, Grafiken, eingebettete Objekte und so weiter hinzuzufügen. Ein Beispiel:<br /><br />
		&lt;a href="http://website.de/{INPUT}"&gt;&lt;img src="{DEFAULT_IMAGES_URL}/symbol.png" alt="{INPUT}" /&gt;&lt;/a&gt;<br /><br />
		Sie können folgende Variablen verwenden:<br />
		<ul class="normallist">
			<li>{INPUT} - Der vom Benutzer eingegebene Text.</li>
			<li>{SCRIPTURL} - Webadresse des Forums.</li>
			<li>{IMAGES_URL} - URI des Grafikverzeichnisses des aktuellen Designs des Benutzers.</li>
			<li>{DEFAULT_IMAGES_URL} - URI des Grafikverzeichnisses des Standarddesigns.</li>
		</ul>';

$helptxt['custom_mask'] = 'Die Eingabemaske ist wichtig für die Sicherheit Ihres Forums. Die Validierung der Eingabe eines Benutzers kann dabei helfen, sicherzustellen, dass Daten nicht auf unerwartete Weise verwendet werden. Wir haben einige einfache reguläre Ausdrücke als Tipps bereitgestellt.<br /><br />
	<div class="smalltext custom_mask">
		&quot;~[A-Za-z]+~&quot; - Alle Groß- und Kleinbuchstaben des Alphabets passen.<br />
		&quot;~[0-9]+~&quot; - Alle Ziffern passen.<br />
		&quot;~[A-Za-z0-9]{7}~&quot; - Alle Groß- und Kleinbuchstaben des Alphabets sowie Ziffern passen genau siebenmal.<br />
		&quot;~[^0-9]?~&quot; - Verbietet es jeglicher Zahl, zu passen.<br />
		&quot;~^([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$~&quot; - Erlaubt nur drei- oder sechsstellige Hexadezimalwerte.<br />
	</div><br /><br />
	Des Weiteren können die besonderen Metazeichen ?+*^$ und {xx} definiert werden.
	<div class="smalltext custom_mask">
		? - Kein oder ein Vorkommen des vorherigen Ausdrucks.<br />
		+ - Ein oder mehr Vorkommen des vorherigen Ausdrucks.<br />
		* - Eine beliebige Anzahl des vorherigen Ausdrucks.<br />
		{xx} - Eine genaue Anzahl des vorherigen Ausdrucks.<br />
		{xx,} - Mindestens diese Anzahl des vorherigen Ausdrucks.<br />
		{,xx} - Höchstens diese Anzahl des vorherigen Ausdrucks.<br />
		{xx,yy} - Eine genaue Übereinstimmung zwischen diesen beiden Anzahlen des vorherigen Ausdrucks.<br />
		^ - Anfang der Zeichenkette.<br />
		$ - Ende der Zeichenkette.<br />
		\ - Maskiert das nächste Zeichen.<br />
	</div><br /><br />
	Weitere Informationen und fortgeschrittene Techniken sind im Internet zu finden.';

$helptxt['badbehavior_reverse_proxy_addresses'] = 'In einigen Serverfarm-Konfigurationen könnte es Bad Behavior nicht möglich sein, festzustellen, ob eine externe Anfrage von Ihrem Reverse-Proxy/Lastverteiler stammt oder direkt übermittelt wurde. In diesem Fall sollten Sie alle internen IP-Adressen für Ihren Reverse Proxy/Lastverteiler wie vom Ursprungsserver gesehen hinzufügen. Sie können normalerweise weggelassen werden; wenn Sie allerdings eine Konfiguration verwenden, in der einige Anfragen den Lastverteiler umgehen und direkt den Ursprungsserver erreichen können, so sollten Sie diese Option verwenden. Sie sollten sie auch benutzen, wenn eingehende Anfragen zwei oder mehr Reverse Proxys durchlaufen, bevor sie den Ursprungsserver erreichen.<br /><br />Geben Sie jede IP-Adresse und jeden CIDR-Block per | getrennt ein (1.2.3.4|5.4.3.2/27)';
$helptxt['badbehavior_reverse_proxy_header'] = 'Wenn ein Reverse Proxy verwendet wird, schaut Bad Behavior diese HTTP-Kopfzeile an, um die eigentliche IP-Adresse für jede Webanfrage herauszufinden. Ihr Reverse Proxy oder Lastverteiler muss eine HTTP-Kopfzeile mit der IP-Adresse, an der die Verbindung ihren Ursprung hatte, hinzufügen. Die meisten tun dies standardmäßig; überprüfen Sie die Konfiguration, um es sicherzustellen.<br /><br />Wenn Sie den CloudFlare-Dienst verwenden, sollten Sie diese Option auf CF-Connecting-IP setzen.';
$helptxt['badbehavior_reverse_proxy'] = 'Sofern aktiviert, wird Bad Behavior annehmen, dass es eine Verbindung von einem Reverse Proxy erhält, wenn eine bestimmte HTTP-Kopfzeile empfangen wird.';
$helptxt['badbehavior_eucookie'] = 'Aktivieren Sie diese Option, wenn Sie annehmen, dass der Sicherheitscookie von Bad Behavior nicht von der 2012 beschlossenen Cookieregelung der EU ausgenommen ist.</a>';
$helptxt['badbehavior_httpbl_maxage'] = 'Dies ist die Anzahl an Tagen, seit eine verdächtige Aktivität durch eine IP-Adresse zuletzt durch das Project Honey Pot beobachtet wurde. Bad Behavior wird Anfragen mit einem Höchstalter gleich oder niedriger als diese Einstellung.';
$helptxt['badbehavior_httpbl_threat'] = 'Diese Zahl dient als Messwert, wie verdächtig eine IP-Adresse ist, basierend auf bei Project Honey Pot beobachteter Aktivität. Bad Behavior wird Anfragen mit einer Bedrohungsstufe ab dieser Einstellung blockieren. Project Honey Pot hat <a href="http://www.projecthoneypot.org/threat_info.php" target="_blank">weitere Informationen über diesen Parameter</a>.';
$helptxt['badbehavior_httpbl_key'] = 'Bad Behavior ist dazu in der Lage, Daten des vom <a href="http://www.projecthoneypot.org/" target="_blank">Project Honey Pot</a> bereitgestellten <a href="http://www.projecthoneypot.org/faq.php#g" target="_blank">http:BL</a>-Dienstes zur Durchleuchtung von Anfragen zu verwenden.<br /><br />Dieser Dienst ist rein optional; wenn Sie ihn allerdings verwenden möchten, müssen Sie sich <a href="http://www.projecthoneypot.org/httpbl_configure.php" target="_blank">für den Dienst registrieren</a> und einen API-Schlüssel anfordern. Um die Verwendung von http:BL zu deaktivieren, entfernen Sie den API-Schlüssel aus Ihren Einstellungen.';
$helptxt['badbehavior_verbose'] = 'Die Aktivierung der ausführlichen Protokollierung sorgt dafür, dass alle HTTP-Anfragen protokolliert werden. Ist sie deaktiviert, so werden nur blockierte und verdächtige Anfragen protokolliert.<br /><br />Die ausführliche Protokollierung ist standardmäßig ausgeschaltet. Ihre Verwendung wird nicht empfohlen, da sie Ihr Forum merklich verlangsamen kann; sie existiert, um Daten von Spammern, die nicht blockiert werden, direkt mitzuschneiden.';
$helptxt['badbehavior_logging'] = 'Soll Bad Behavior ein Protokoll der Anfragen aufbewahren? Standardmäßig an, und es wird nicht empfohlen, dies zu deaktivieren, da hierdurch weiterer Spam durchkommen würde.';
$helptxt['badbehavior_strict'] = 'Bad Behavior arbeitet in zwei Blockiermodi: normal und strikt.<br />Wenn der strikte Modus aktiviert ist, werden weitere Prüfungen für (alte) Software, die Spamquelle war, aktiviert, aber gelegentlich werden eventuell auch normale Benutzer blockiert, die die gleiche Software verwenden.';
$helptxt['badbehavior_offsite_forms'] = 'Bad Behavior verhindert normalerweise, dass Ihr Forum Daten erhält, die in Formularen auf anderen Websites eingegeben wurden. Dies hält Spammer davon ab, zum Beispiel eine zwischengespeicherte Version Ihrer Website dazu zu verwenden, Ihnen Spam zu senden. Einige Webanwendungen wie OpenID setzen allerdings voraus, dass Ihr Forum dazu in der Lage ist, Formulardaten auf diese Weise zu erhalten. Wenn Sie OpenID verwenden, aktivieren Sie diese Option.';
$helptxt['badbehavior_postcount_wl'] = 'Dies erlaubt es Ihnen, Verhaltenskontrollen für Benutzer über einer bestimmten Anzahl an Beiträgen zu umgehen.<br />-1 wird alle registrierten Benutzer ignorieren, auch jene ohne Beiträge<br />0 wird die Umgehung deaktivieren und jeden unabhängig von seinem Beitragszähler scannen<br />Eine Anzahl größer als 0 gibt die Zahl an Beiträgen an, ab der Benutzer nicht mehr überprüft werden.';
$helptxt['badbehavior_ip_wl'] = 'IP-Adressbereiche nutzen das CIDR-Format.  Um eine Adresse zu entfernen, lassen Sie sie einfach leer und speichern Sie dann';
$helptxt['badbehavior_useragent_wl'] = 'Browser werden auf exakte Übereinstimmung verglichen.';
$helptxt['badbehavior_url_wl'] = 'URLs werden ab dem ersten / nach dem Servernamen bis zu, nicht einschließlich, dem ? (falls vorhanden). Der URL, der auf der weißen Liste eingetragen wird, ist ein URL auf IHRER Website. Eine teilweise Übereinstimmung ist erlaubt, weshalb URL-Einträge so spezifisch wie möglich, aber nicht spezifischer als nötig sein sollten.<br />/beispiel würde zum Beispiel auf /beispiel.php und /beispiel/adresse passen';

$helptxt['filter_to'] = 'Ersetzt den gefundenen Text hierdurch, leer lassen, um ihn durch nichts zu ersetzen (d.h. zu entfernen)';
$helptxt['filter_from'] = 'Geben Sie den Text ein, den Sie suchen/ersetzen möchten.  Wenn der Typ auf regex gesetzt ist, muss dies ein gültiger regulärer Ausdruck einschließlich der Trennzeichen sein.  Andernfalls wird eine einfache Textübereinstimmung gesucht und durch den Ersetzungstext ersetzt';
$helptxt['filter_type'] = 'Standard wird den genauen Begriff suchen und durch den Text im Ersetzen-Feld ersetzen.  Regulärer Ausdruck ist eine Platzhalteroption, es muss sich jedoch um einen gültigen regulären Ausdruck handeln.';
$helptxt['pbe_post_enabled'] = 'Aktivieren Sie dies, um es Benutzern zu erlauben, auf E-Mail-Benachrichtungen zu antworten und dies als Antwort zu veröffentlichen.  Sie müssen dennoch berechtigt sein, Beiträge zu verfassen.';
$helptxt['pbe_pm_enabled'] = 'Aktivieren Sie dies, um es Benutzern zu erlauben, per E-Mail auf Benachrichtigungen über private Nachrichten zu antworten.  Sie müssen dennoch berechtigt sein, private Nachrichten zu verfassen, diese Einstellung erlaubt es ihnen nur, sie zu empfangen und auf Benachrichtigungen zu antworten';
$helptxt['maillist_group_mode'] = 'Falls aktiviert, werden ausgehende Beitrags-/Themen-E-Mails vom Anzeigenamen des Verfassers kommen, ansonsten kommen sie vom Namen des Forums.  Dies ist nur ein Umschlag, es hat nur Auswirkungen darauf, wie der "Absendername" im Posteingang des Empfängers erscheint, die E-Mail-Adresse des Absenders bleibt unverändert.';
$helptxt['maillist_newtopic_change'] = 'Dies wird es einem Benutzer erlauben, den Betreff einer E-Mail-Benachrichtigung zu ändern und sie dadurch als neues Thema zu veröffentlichen.  Das neue Thema wird im gleichen Forum wie das Thema eröffnet, in dem sich das Thema befindet, über das der Benutzer benachrichtigt wurde.';
$helptxt['maillist_sitename_address'] = 'Dies muss die Adresse, die an die Datei emailpost.php weitergeleitet wird, oder die Adresse des IMAP-Posteingangs sein';
$helptxt['maillist_help_short'] = 'Diese Funktion erlaubt es Benutzern Ihres Forums, auf die Mailbenachrichtigungen Ihres Forums zu antworten und diese Antworten im Forum zu veröffentlichen.  Bitte besuchen Sie das Wiki für eine vollständige Anleitung';

$helptxt['frame_security'] = 'Die X-Frame-Options-HTTP-Antwortkopfzeile kann verwendet werden, um anzuzeigen, ob ein Browser eine Seite in einem Frame oder einem iframe darstellen darf oder nicht. Sie können diese zusätzliche Sicherheitsbeschränkung auf Ihrer Website gegen Clickjackingangriffe verwenden, indem sichergestellt wird, dass der Inhalt Ihrer Website nicht in andere Websites eingebunden ist.
	<br />
	Weitere Informationen über diese Kopfzeile können im Internet gefunden werden.';