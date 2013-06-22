/* ATTENTION: You don't need to run or use this file!  The upgrade.php script does everything for you! */

/******************************************************************************/
--- Adding new settings...
/******************************************************************************/

---# Adding login history...
CREATE TABLE IF NOT EXISTS {$db_prefix}member_logins (
	id_login int(10) NOT NULL auto_increment,
	id_member mediumint(8) NOT NULL,
	time int(10) NOT NULL,
	ip varchar(255) NOT NULL default '',
	ip2 varchar(255) NOT NULL default '',
	PRIMARY KEY id_login(id_login),
	KEY id_member (id_member),
	KEY time (time)
) ENGINE=MyISAM{$db_collation};
---#

---# Copying the current package backup setting...
---{
if (!isset($modSettings['package_make_full_backups']) && isset($modSettings['package_make_backups']))
	upgrade_query("
		INSERT INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('package_make_full_backups', '" . $modSettings['package_make_backups'] . "')");
---}
---#

---# Adding new settings to the settings table...
INSERT IGNORE INTO {$db_prefix}settings
	(variable, value)
VALUES
	('avatar_default', '0'),
	('gravatar_rating', 'g'),
	('xmlnews_limit', 5),
	('visual_verification_num_chars', '6'),
	('enable_disregard', 0),
	('jquery_source', 'local');
---#

/******************************************************************************/
--- Updating legacy attachments...
/******************************************************************************/

---# Converting legacy attachments.
---{
$request = upgrade_query("
	SELECT MAX(id_attach)
	FROM {$db_prefix}attachments");
list ($step_progress['total']) = $smcFunc['db_fetch_row']($request);
$smcFunc['db_free_result']($request);

$_GET['a'] = isset($_GET['a']) ? (int) $_GET['a'] : 0;
$step_progress['name'] = 'Converting legacy attachments';
$step_progress['current'] = $_GET['a'];

// We may be using multiple attachment directories.
if (!empty($modSettings['currentAttachmentUploadDir']) && !is_array($modSettings['attachmentUploadDir']))
	$modSettings['attachmentUploadDir'] = unserialize($modSettings['attachmentUploadDir']);

$is_done = false;
while (!$is_done)
{
	nextSubStep($substep);

	$request = upgrade_query("
		SELECT id_attach, id_folder, filename, file_hash
		FROM {$db_prefix}attachments
		WHERE file_hash = ''
		LIMIT $_GET[a], 100");

	// Finished?
	if ($smcFunc['db_num_rows']($request) == 0)
		$is_done = true;

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// The current folder.
		$current_folder = !empty($modSettings['currentAttachmentUploadDir']) ? $modSettings['attachmentUploadDir'][$row['id_folder']] : $modSettings['attachmentUploadDir'];

		// The old location of the file.
		$old_location = getLegacyAttachmentFilename($row['filename'], $row['id_attach'], $row['id_folder']);

		// The new file name.
		$file_hash = getAttachmentFilename($row['filename'], $row['id_attach'], $row['id_folder'], true);

		// And we try to move it.
		rename($old_location, $current_folder . '/' . $row['id_attach'] . '_' . $file_hash);

		// Only update thif if it was successful.
		if (file_exists($current_folder . '/' . $row['id_attach'] . '_' . $file_hash) && !file_exists($old_location))
			upgrade_query("
				UPDATE {$db_prefix}attachments
				SET file_hash = '$file_hash'
				WHERE id_attach = $row[id_attach]");
	}
	$smcFunc['db_free_result']($request);

	$_GET['a'] += 100;
	$step_progress['current'] = $_GET['a'];
}

unset($_GET['a']);
---}
---#

/******************************************************************************/
--- Adding support for IPv6...
/******************************************************************************/

---# Adding new columns to ban items...
ALTER TABLE {$db_prefix}ban_items
ADD COLUMN ip_low5 smallint(255) unsigned NOT NULL DEFAULT '0',
ADD COLUMN ip_high5 smallint(255) unsigned NOT NULL DEFAULT '0',
ADD COLUMN ip_low6 smallint(255) unsigned NOT NULL DEFAULT '0',
ADD COLUMN ip_high6 smallint(255) unsigned NOT NULL DEFAULT '0',
ADD COLUMN ip_low7 smallint(255) unsigned NOT NULL DEFAULT '0',
ADD COLUMN ip_high7 smallint(255) unsigned NOT NULL DEFAULT '0',
ADD COLUMN ip_low8 smallint(255) unsigned NOT NULL DEFAULT '0',
ADD COLUMN ip_high8 smallint(255) unsigned NOT NULL DEFAULT '0';
---#

---# Changing existing columns to ban items...
ALTER TABLE {$db_prefix}ban_items
CHANGE ip_low1 ip_low1 smallint(255) unsigned NOT NULL DEFAULT '0',
CHANGE ip_high1 ip_high1 smallint(255) unsigned NOT NULL DEFAULT '0',
CHANGE ip_low2 ip_low2 smallint(255) unsigned NOT NULL DEFAULT '0',
CHANGE ip_high2 ip_high2 smallint(255) unsigned NOT NULL DEFAULT '0',
CHANGE ip_low3 ip_low3 smallint(255) unsigned NOT NULL DEFAULT '0',
CHANGE ip_high3 ip_high3 smallint(255) unsigned NOT NULL DEFAULT '0',
CHANGE ip_low4 ip_low4 smallint(255) unsigned NOT NULL DEFAULT '0',
CHANGE ip_high4 ip_high4 smallint(255) unsigned NOT NULL DEFAULT '0';
---#

/******************************************************************************/
--- Adding support for <credits> tag in package manager
/******************************************************************************/
---# Adding new columns to log_packages ..
ALTER TABLE {$db_prefix}log_packages
ADD COLUMN credits varchar(255) NOT NULL DEFAULT '';
---#

/******************************************************************************/
--- Adding more space for session ids
/******************************************************************************/
---# Altering the session_id columns...
ALTER TABLE {$db_prefix}log_online
CHANGE `session` `session` varchar(64) NOT NULL DEFAULT '';

ALTER TABLE {$db_prefix}log_errors
CHANGE `session` `session` char(64) NOT NULL default '                                                                ';

ALTER TABLE {$db_prefix}sessions
CHANGE `session_id` `session_id` char(64) NOT NULL;
---#

/******************************************************************************/
--- Adding support for MOVED topics enhancements
/******************************************************************************/
---# Adding new columns to topics ..
ALTER TABLE {$db_prefix}topics
ADD COLUMN redirect_expires int(10) unsigned NOT NULL default '0',
ADD COLUMN id_redirect_topic mediumint(8) unsigned NOT NULL default '0';
---#

/******************************************************************************/
--- Adding new scheduled tasks
/******************************************************************************/
---# Adding new scheduled tasks
INSERT INTO {$db_prefix}scheduled_tasks
	(next_time, time_offset, time_regularity, time_unit, disabled, task)
VALUES
	(0, 120, 1, 'd', 0, 'remove_temp_attachments');
INSERT INTO {$db_prefix}scheduled_tasks
	(next_time, time_offset, time_regularity, time_unit, disabled, task)
VALUES
	(0, 180, 1, 'd', 0, 'remove_topic_redirect');
INSERT INTO {$db_prefix}scheduled_tasks
	(next_time, time_offset, time_regularity, time_unit, disabled, task)
VALUES
	(0, 240, 1, 'd', 0, 'remove_old_drafts');
INSERT INTO {$db_prefix}scheduled_tasks
	(next_time, time_offset, time_regularity, time_unit, disabled, task)
VALUES
	(0, 0, 6, 'h', 0, 'remove_old_followups');
INSERT INTO {$db_prefix}scheduled_tasks
	(next_time, time_offset, time_regularity, time_unit, disabled, task)
VALUES
	(0, 360, 10, 'm', 0, 'maillist_fetch_IMAP');
---#

/******************************************************************************/
--- Adding support for deny boards access
/******************************************************************************/
---# Adding new columns to boards...
ALTER TABLE {$db_prefix}boards
ADD COLUMN deny_member_groups varchar(255) NOT NULL DEFAULT '';
---#

/******************************************************************************/
--- Adding support for topic disregard
/******************************************************************************/
---# Adding new columns to boards...
ALTER TABLE {$db_prefix}log_topics
ADD COLUMN disregarded tinyint(3) NOT NULL DEFAULT '0';

UPDATE {$db_prefix}log_topics
SET disregarded = 0;
---#

/******************************************************************************/
--- Adding support for custom profile fields on memberlist
/******************************************************************************/
---# Adding new columns to boards...
ALTER TABLE {$db_prefix}custom_fields
ADD COLUMN show_memberlist tinyint(3) NOT NULL DEFAULT '0';
---#

/******************************************************************************/
--- Fixing mail queue for long messages
/******************************************************************************/
---# Altering mil_queue table...
ALTER TABLE {$db_prefix}mail_queue
CHANGE body body mediumtext NOT NULL;
---#

/******************************************************************************/
--- Name changes
/******************************************************************************/
---# Altering the membergroup stars to icons
ALTER TABLE {$db_prefix}membergroups
CHANGE `stars` `icons` varchar(255) NOT NULL DEFAULT '';
---#

/******************************************************************************/
--- Adding support for drafts
/******************************************************************************/
---# Creating draft table
CREATE TABLE IF NOT EXISTS {$db_prefix}user_drafts (
  id_draft int(10) unsigned NOT NULL auto_increment,
  id_topic mediumint(8) unsigned NOT NULL default '0',
  id_board smallint(5) unsigned NOT NULL default '0',
  id_reply int(10) unsigned NOT NULL default '0',
  type tinyint(4) NOT NULL default '0',
  poster_time int(10) unsigned NOT NULL default '0',
  id_member mediumint(8) unsigned NOT NULL default '0',
  subject varchar(255) NOT NULL default '',
  smileys_enabled tinyint(4) NOT NULL default '1',
  body mediumtext NOT NULL,
  icon varchar(16) NOT NULL default 'xx',
  locked tinyint(4) NOT NULL default '0',
  is_sticky tinyint(4) NOT NULL default '0',
  to_list varchar(255) NOT NULL default '',
  outbox tinyint(4) NOT NULL default '0',
  PRIMARY KEY id_draft(id_draft),
  UNIQUE id_member (id_member, id_draft, type)
) ENGINE=MyISAM{$db_collation};
---#

---# Adding draft permissions...
---{
// We cannot do this twice
// @todo this won't work when you upgrade from smf
if (@$modSettings['elkVersion'] < '1.0')
{
	// Anyone who can currently post unapproved topics we assume can create drafts as well ...
	$request = upgrade_query("
		SELECT id_group, id_profile, add_deny, permission
		FROM {$db_prefix}board_permissions
		WHERE permission = 'post_unapproved_topics'");
	$inserts = array();
	while ($row = mysql_fetch_assoc($request))
	{
		$inserts[] = "($row[id_group], $row[id_profile], 'post_draft', $row[add_deny])";
		$inserts[] = "($row[id_group], $row[id_profile], 'post_autosave_draft', $row[add_deny])";
	}
	mysql_free_result($request);

	if (!empty($inserts))
		upgrade_query("
			INSERT IGNORE INTO {$db_prefix}board_permissions
				(id_group, id_profile, permission, add_deny)
			VALUES
				" . implode(',', $inserts));

	// Next we find people who can send PM's, and assume they can save pm_drafts as well
	$request = upgrade_query("
		SELECT id_group, add_deny, permission
		FROM {$db_prefix}permissions
		WHERE permission = 'pm_send'");
	$inserts = array();
	while ($row = mysql_fetch_assoc($request))
	{
		$inserts[] = "($row[id_group], 'pm_draft', $row[add_deny])";
		$inserts[] = "($row[id_group], 'pm_autosave_draft', $row[add_deny])";
	}
	mysql_free_result($request);

	if (!empty($inserts))
		upgrade_query("
			INSERT IGNORE INTO {$db_prefix}permissions
				(id_group, permission, add_deny)
			VALUES
				" . implode(',', $inserts));
}
---}
---#

/******************************************************************************/
--- Messenger fields
/******************************************************************************/
---# Insert new fields
INSERT INTO `{$db_prefix}custom_fields`
	(`col_name`, `field_name`, `field_desc`, `field_type`, `field_length`, `field_options`, `mask`, `show_reg`, `show_display`, `show_profile`, `private`, `active`, `bbc`, `can_search`, `default_value`, `enclose`, `placement`)
VALUES
	('cust_aim', 'AOL Instant Messenger', 'This is your AOL Instant Messenger nickname.', 'text', 50, '', 'regex~[a-z][0-9a-z.-]{1,31}~i', 0, 1, 'forumprofile', 0, 1, 0, 0, '', '<a class="aim" href="aim:goim?screenname={INPUT}&message=Hello!+Are+you+there?" target="_blank" title="AIM - {INPUT}"><img src="{IMAGES_URL}/aim.png" alt="AIM - {INPUT}"></a>', 1),
	('cust_icq', 'ICQ', 'This is your ICQ number.', 'text', 12, '', 'regex~[1-9][0-9]{4,9}~i', 0, 1, 'forumprofile', 0, 1, 0, 0, '', '<a class="icq" href="http://www.icq.com/whitepages/about_me.php?uin={INPUT}" target="_blank" title="ICQ - {INPUT}"><img src="http://status.icq.com/online.gif?img=5&icq={INPUT}" alt="ICQ - {INPUT}" width="18" height="18"></a>', 1),
	('cust_msn', 'MSN/Live', 'Your Live Messenger email address', 'text', 50, '', 'email', 0, 1, 'forumprofile', 0, 1, 0, 0, '', '<a class="msn" href="http://members.msn.com/{INPUT}" target="_blank" title="Live - {INPUT}"><img src="{IMAGES_URL}/msntalk.png" alt="Live - {INPUT}"></a>', 1),
	('cust_yim', 'Yahoo! Messenger', 'This is your Yahoo! Instant Messenger nickname.', 'text', 50, '', 'email', 0, 1, 'forumprofile', 0, 1, 0, 0, '', '<a class="yim" href="http://edit.yahoo.com/config/send_webmesg?.target={INPUT}" target="_blank" title="Yahoo! Messenger - {INPUT}"><img src="http://opi.yahoo.com/online?m=g&t=0&u={INPUT}" alt="Yahoo! Messenger - {INPUT}"></a>', 1);
---#

---# Move existing values...
---{
// We cannot do this twice
// @todo this won't work when you upgrade from smf
if (@$modSettings['elkVersion'] < '1.0')
{
	$request = upgrade_query("
		SELECT id_member, aim, icq, msn, yim
		FROM {$db_prefix}members");
	$inserts = array();
	while ($row = mysql_fetch_assoc($request))
	{
		if (!empty($row[aim]))
			$inserts[] = "($row[id_member], -1, 'cust_aim', $row[aim])";

		if (!empty($row[icq]))
			$inserts[] = "($row[id_member], -1, 'cust_icq', $row[icq])";

		if (!empty($row[msn]))
			$inserts[] = "($row[id_member], -1, 'cust_msn', $row[msn])";

		if (!empty($row[yim]))
			$inserts[] = "($row[id_member], -1, 'cust_yim', $row[yim])";
	}
	mysql_free_result($request);

	if (!empty($inserts))
		upgrade_query("
			INSERT INTO {$db_prefix}themes
				(id_member, id_theme, variable, value)
			VALUES
				" . implode(',', $inserts));
}
---}
---#

---# Drop the old cols
ALTER TABLE `{$db_prefix}members`
	DROP `icq`,
	DROP `aim`,
	DROP `yim`,
	DROP `msn`;
---#

---# Adding gravatar permissions...
---{
// Don't do this twice!
if (@$modSettings['elkVersion'] < '1.0')
{
	// Try find people who probably can use remote avatars.
	$request = upgrade_query("
		SELECT id_group, add_deny, permission
		FROM {$db_prefix}permissions
		WHERE permission = 'profile_remote_avatar'");
	$inserts = array();
	while ($row = mysql_fetch_assoc($request))
	{
		$inserts[] = "($row[id_group], 'profile_gravatar', $row[add_deny])";
	}
	mysql_free_result($request);

	if (!empty($inserts))
		upgrade_query("
			INSERT IGNORE INTO {$db_prefix}permissions
				(id_group, permission, add_deny)
			VALUES
				" . implode(',', $inserts));
}
---}
---#

/******************************************************************************/
--- Updating URLs information.
/******************************************************************************/

---# Changing URL to Elk package server...
UPDATE {$db_prefix}package_servers
SET url = 'https://github.com/elkarte/addons/tree/master/packages'
WHERE url = 'http://custom.simplemachines.org/packages/mods';
---#

/******************************************************************************/
--- Adding follow-up support.
/******************************************************************************/

---# Creating follow-up table...
CREATE TABLE IF NOT EXISTS {$db_prefix}follow_ups (
  follow_up int(10) NOT NULL default '0',
  derived_from int(10) NOT NULL default '0',
  PRIMARY KEY (follow_up, derived_from)
) ENGINE=MyISAM;
---#

/******************************************************************************/
--- Updating antispam questions.
/******************************************************************************/

---# Creating antispam questions table...
CREATE TABLE IF NOT EXISTS {$db_prefix}antispam_questions (
  id_question tinyint(4) unsigned NOT NULL auto_increment,
  question text NOT NULL,
  answer text NOT NULL,
  language varchar(50) NOT NULL default '',
  PRIMARY KEY (id_question),
  KEY language (language(30))
) ENGINE=MyISAM;
---#

---# Move existing values...
---{
global $language;

$request = upgrade_query("
	SELECT id_comment, recipient_name as answer, body as question
	FROM {$db_prefix}log_comments
	WHERE comment_type = 'ver_test'");
if (mysql_num_rows($request) != 0)
{
	$values = array();
	$id_comments = array();
	while ($row = mysql_fetch_assoc($request))
	{
		upgrade_query("
			INSERT INTO {$db_prefix}antispam_questions
				(answer, question, language)
			VALUES
				('" . serialize(array($row['answer'])) . "', '" . $row['question'] . "', '" . $language . "')");
		upgrade_query("
			DELETE FROM {$db_prefix}log_comments
			WHERE id_comment  = " . $row['id_comment'] . "
			LIMIT 1");
	}
}
---}
---#

/******************************************************************************/
--- Adding support for Maillist
/******************************************************************************/
---# Creating postby_emails table
CREATE TABLE IF NOT EXISTS {$db_prefix}postby_emails (
	id_email varchar(50) NOT NULL,
	time_sent int(10) NOT NULL default '0',
	email_to varchar(50) NOT NULL,
	PRIMARY KEY (id_email)
) ENGINE=MyISAM{$db_collation};
---#

---# Creating postby_emails_error table
CREATE TABLE IF NOT EXISTS {$db_prefix}postby_emails_error (
	id_email int(10) NOT NULL auto_increment,
	error varchar(255) NOT NULL default '',
	data_id varchar(255) NOT NULL default '0',
	subject varchar(255) NOT NULL default '',
	id_message int(10) NOT NULL default '0',
	id_board smallint(5) NOT NULL default '0',
	email_from varchar(50) NOT NULL default '',
	message_type char(10) NOT NULL default '',
	message mediumtext NOT NULL default '',
	PRIMARY KEY (id_email),
) ENGINE=MyISAM{$db_collation};
---#

---# Creating postby_emails_filters table
CREATE TABLE IF NOT EXISTS {$db_prefix}postby_emails_filters (
	id_filter int(10) NOT NULL auto_increment,
	filter_style char(5) NOT NULL default '',
	filter_type varchar(255) NOT NULL default '',
	filter_to varchar(255) NOT NULL default '',
	filter_from varchar(255) NOT NULL default '',
	filter_name varchar(255) NOT NULL default '',
	PRIMARY KEY (id_filter),
) ENGINE=MyISAM{$db_collation};
---#

---# Adding new columns to log_activity...
ALTER TABLE {$db_prefix}log_activity
ADD COLUMN pm smallint(5) unsigned NOT NULL DEFAULT '0',
ADD COLUMN email smallint(5) unsigned NOT NULL DEFAULT '0';
---#

---# Adding new columns to mail_queue...
ALTER TABLE {$db_prefix}mail_queue
ADD COLUMN message_id int(10)  NOT NULL DEFAULT '0';
 ---#

---# Updating board profiles...
INSERT INTO {$db_prefix}board_permissions (id_group, id_profile, permission) VALUES (0, 1, 'postby_email');
INSERT INTO {$db_prefix}board_permissions (id_group, id_profile, permission) VALUES (0, 2, 'postby_email');
---#


/******************************************************************************/
--- Adding likes support.
/******************************************************************************/

---# Creating likes log  table...
CREATE TABLE IF NOT EXISTS {$db_prefix}log_likes (
  action char(1) NOT NULL default '0',
  id_target mediumint(8) unsigned NOT NULL default '0',
  id_member mediumint(8) unsigned NOT NULL default '0',
  log_time int(10) unsigned NOT NULL default '0',
  PRIMARY KEY (id_target, id_member),
  KEY log_time (log_time)
) ENGINE=MyISAM;
---#

---# Creating likes message  table...
CREATE TABLE IF NOT EXISTS {$db_prefix}message_likes (
  id_member mediumint(8) unsigned NOT NULL default '0',
  id_msg mediumint(8) unsigned NOT NULL default '0',
  PRIMARY KEY (id_msg, id_member) 
) ENGINE=MyISAM;
---#

---# Adding new columns to topics...
ALTER TABLE {$db_prefix}topics
ADD COLUMN num_likes int(10) unsigned NOT NULL default '0';
---#

---# Adding new columns to members...
ALTER TABLE {$db_prefix}members
ADD COLUMN likes_given mediumint(5) unsigned  NOT NULL default '0',
ADD COLUMN likes_received mediumint(5) unsigned  NOT NULL default '0';
---#
