/* ATTENTION: You don't need to run or use this file! The upgrade.php script does everything for you! */

/******************************************************************************/
--- Fixing theme directories and URLs...
/******************************************************************************/

---# Try to detect where the themes are...
---{
$request = upgrade_query("
	SELECT variable, value
	FROM {$db_prefix}themes
	WHERE variable LIKE 'theme_dir'
		AND id_theme = 1
		AND id_member = 0
	LIMIT 1");

$row = $db->fetch_assoc($request);
$db->free_result($request);

// Again, the only option is to find a file we know exist, in this case script_elk.js
$original_theme_dir = $row['value'];

// There are not many options, one is the check for a file ElkArte has and SMF
// does not have, for example GenericHelpers.template.php

// If the file exists, everything is in the correct place, so go back.
if (file_exists($original_theme_dir . '/GenericHelpers.template.php'))
{
	$possible_theme_dir = $original_theme_dir;
}
else
{
	// If this file is not there, we can try replacing "Themes" with "themes"
	$possible_theme_dir = str_replace('Themes', 'themes', $original_theme_dir);
	if (!file_exists($possible_theme_dir . '/GenericHelpers.template.php'))
	{
		// Last try, BOARDDIR + themes
		$possible_theme_dir = BOARDDIR . '/themes/default';
		// If it does not exist do not change anything
		if (!file_exists($possible_theme_dir . '/GenericHelpers.template.php'))
			$possible_theme_dir = '';
	}
}

if (!empty($possible_theme_dir))
{
	// If you arrived here means the template exists and we have a valid directory
	// Time to update the database.

	upgrade_query("
		UPDATE {$db_prefix}themes
		SET value = REPLACE(value, '" . $original_theme_dir . "', '" . $possible_theme_dir . "')
		WHERE variable LIKE '%_dir'");
}
---}
---#

---# Try to detect how to reach the theme...
---{
$request = upgrade_query("
	SELECT variable, value
	FROM {$db_prefix}themes
	WHERE variable LIKE 'theme_url'
		AND id_theme = 1
		AND id_member = 0
	LIMIT 1");

$row = $db->fetch_assoc($request);
$db->free_result($request);

// Again, the only option is to find a file we know exist, in this case script_elk.js
if (strpos($row['value'], 'http') !== 0)
	$original_theme_url = $boardurl . '/' . $row['value'];
else
	$original_theme_url = $row['value'];

// If the file exists, everything is in the correct place, so go back.
if (fetch_web_data($original_theme_url . '/scripts/script_elk.js'))
{
	$possible_theme_url = $original_theme_url;
}
else
{
	// If this file is not there, we can try replacing "Themes" with "themes"
	$possible_theme_url = str_replace('Themes', 'themes', $original_theme_url);

	if (!fetch_web_data($possible_theme_url . '/scripts/script_elk.js'))
	{
		// Last try, $boardurl + themes
		$possible_theme_url = $boardurl . '/themes/default';
		// If it does not exist do not change anything
		if (!fetch_web_data($possible_theme_url . '/scripts/script_elk.js'))
			$possible_theme_url = '';
	}
}

if (!empty($possible_theme_url))
{
	// If you arrived here means the template exists and we have a valid directory
	// Time to update the database.

	upgrade_query("
		UPDATE {$db_prefix}themes
		SET value = REPLACE(value, '" . $original_theme_url . "', '" . $possible_theme_url . "')
		WHERE variable LIKE '%_url'");
}
---}
---#

/******************************************************************************/
--- Adding new settings...
/******************************************************************************/

---# Creating login history sequence.
CREATE SEQUENCE {$db_prefix}member_logins_seq;
---#

---# Adding login history...
CREATE TABLE IF NOT EXISTS {$db_prefix}member_logins (
	id_login int NOT NULL default nextval('{$db_prefix}member_logins_seq'),
	id_member int NOT NULL,
	time int NOT NULL,
	ip varchar(255) NOT NULL default '',
	ip2 varchar(255) NOT NULL default '',
	PRIMARY KEY (id_login)
);
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

---# Fix some settings that changed in the meantime...
---{
if (empty($modSettings['elkVersion']) || compareVersions($modSettings['elkVersion'], '1.0') == -1)
{
	upgrade_query("
		UPDATE {$db_prefix}settings
		SET value = CASE WHEN value = '2' THEN '0' ELSE value END
		WHERE variable LIKE 'pollMode'");
}
---}
---#

---# Adding new settings ...
INSERT IGNORE INTO {$db_prefix}settings
	(variable, value)
VALUES
	('avatar_default', '0');
INSERT IGNORE INTO {$db_prefix}settings
	(variable, value)
VALUES
	('gravatar_rating', 'g');
INSERT IGNORE INTO {$db_prefix}settings
	(variable, value)
VALUES
	('admin_session_lifetime', 10);
INSERT IGNORE INTO {$db_prefix}settings
	(variable, value)
VALUES
	('xmlnews_limit', 5);
INSERT IGNORE INTO {$db_prefix}settings
	(variable, value)
VALUES
	('visual_verification_num_chars', '6');
INSERT IGNORE INTO {$db_prefix}settings
	(variable, value)
VALUES
	('enable_unwatch', 0);
INSERT IGNORE INTO {$db_prefix}settings
	(variable, value)
VALUES
	('jquery_source', 'local');
INSERT IGNORE INTO {$db_prefix}settings
	(variable, value)
VALUES
	('mentions_enabled', '1');
INSERT IGNORE INTO {$db_prefix}settings
	(variable, value)
VALUES
	('mentions_buddy', '0');
INSERT IGNORE INTO {$db_prefix}settings
	(variable, value)
VALUES
	('mentions_dont_notify_rlike', '0');
INSERT IGNORE INTO {$db_prefix}settings
	(variable, value)
VALUES
	('detailed-version.js', 'https://elkarte.github.io/Elkarte/site/detailed-version.js');
---#

/******************************************************************************/
--- Updating legacy attachments...
/******************************************************************************/

---# Converting legacy attachments.
---{
$request = upgrade_query("
	SELECT MAX(id_attach)
	FROM {$db_prefix}attachments");
list ($step_progress['total']) = $db->fetch_row($request);
$db->free_result($request);

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
		WHERE file_hash != ''
		LIMIT $_GET[a], 200");

	// Finished?
	if ($db->num_rows($request) == 0)
		$is_done = true;

	while ($row = $db->fetch_assoc($request))
	{
		// The current folder and name
		$current_folder = !empty($modSettings['currentAttachmentUploadDir']) ? $modSettings['attachmentUploadDir'][$row['id_folder']] : $modSettings['attachmentUploadDir'];
		$current_name = $current_folder . '/' . $row['id_attach'] . '_' . $file_hash;

		// And we try to rename it.
		if (substr($current_name, -4) != '.elk')
			@rename($current_name, $current_name . '.elk');
	}
	$db->free_result($request);

	$_GET['a'] += 200;
	$step_progress['current'] = $_GET['a'];
}

unset($_GET['a']);
---}
---#

/******************************************************************************/
--- Adding new indexes to attachments table.
/******************************************************************************/
---# Adding index on id_thumb...
CREATE INDEX {$db_prefix}attachments_id_thumb ON {$db_prefix}attachments (id_thumb);
---#

/******************************************************************************/
--- Adding support for IPv6...
/******************************************************************************/

---# Adding new columns to ban items...
ALTER TABLE {$db_prefix}ban_items
ADD COLUMN ip_low5 smallint NOT NULL DEFAULT '0',
ADD COLUMN ip_high5 smallint NOT NULL DEFAULT '0',
ADD COLUMN ip_low6 smallint NOT NULL DEFAULT '0',
ADD COLUMN ip_high6 smallint NOT NULL DEFAULT '0',
ADD COLUMN ip_low7 smallint NOT NULL DEFAULT '0',
ADD COLUMN ip_high7 smallint NOT NULL DEFAULT '0',
ADD COLUMN ip_low8 smallint NOT NULL DEFAULT '0',
ADD COLUMN ip_high8 smallint NOT NULL DEFAULT '0';
---#

---# Changing existing columns to ban items...
---{
upgrade_query("
	ALTER TABLE {$db_prefix}ban_items
	ALTER COLUMN ip_low1 type smallint,
	ALTER COLUMN ip_high1 type smallint,
	ALTER COLUMN ip_low2 type smallint,
	ALTER COLUMN ip_high2 type smallint,
	ALTER COLUMN ip_low3 type smallint,
	ALTER COLUMN ip_high3 type smallint,
	ALTER COLUMN ip_low4 type smallint,
	ALTER COLUMN ip_high4 type smallint;"
);

upgrade_query("
	ALTER TABLE {$db_prefix}ban_items
	ALTER COLUMN ip_low1 SET DEFAULT '0',
	ALTER COLUMN ip_high1 SET DEFAULT '0',
	ALTER COLUMN ip_low2 SET DEFAULT '0',
	ALTER COLUMN ip_high2 SET DEFAULT '0',
	ALTER COLUMN ip_low3 SET DEFAULT '0',
	ALTER COLUMN ip_high3 SET DEFAULT '0',
	ALTER COLUMN ip_low4 SET DEFAULT '0',
	ALTER COLUMN ip_high4 SET DEFAULT '0';"
);

upgrade_query("
	ALTER TABLE {$db_prefix}ban_items
	ALTER COLUMN ip_low1 SET NOT NULL,
	ALTER COLUMN ip_high1 SET NOT NULL,
	ALTER COLUMN ip_low2 SET NOT NULL,
	ALTER COLUMN ip_high2 SET NOT NULL,
	ALTER COLUMN ip_low3 SET NOT NULL,
	ALTER COLUMN ip_high3 SET NOT NULL,
	ALTER COLUMN ip_low4 SET NOT NULL,
	ALTER COLUMN ip_high4 SET NOT NULL;"
);
---}
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
---{
upgrade_query("
	ALTER TABLE {$db_prefix}log_online
	ALTER COLUMN session type varchar(64);

	ALTER TABLE {$db_prefix}log_errors
	ALTER COLUMN session type char(64);

	ALTER TABLE {$db_prefix}sessions
	ALTER COLUMN session_id type char(64);");

upgrade_query("
	ALTER TABLE {$db_prefix}log_online
	ALTER COLUMN session SET DEFAULT '';

	ALTER TABLE {$db_prefix}log_errors
	ALTER COLUMN session SET default '                                                                ';");
upgrade_query("
	ALTER TABLE {$db_prefix}log_online
	ALTER COLUMN session SET NOT NULL;

	ALTER TABLE {$db_prefix}log_errors
	ALTER COLUMN session SET NOT NULL;

	ALTER TABLE {$db_prefix}sessions
	ALTER COLUMN session_id SET NOT NULL;");
---}
---#

/******************************************************************************/
--- Adding more space for IP addresses
/******************************************************************************/
---# Altering the session_id columns...
upgrade_query("
	TRUNCATE TABLE {db_prefix}log_online;

	ALTER TABLE {$db_prefix}log_online
	CHANGE `ip` `ip` varchar(255) NOT NULL DEFAULT ''");
---#

/******************************************************************************/
--- Adding support for MOVED topics enhancements
/******************************************************************************/
---# Adding new columns to topics table
---{
upgrade_query("
	ALTER TABLE {$db_prefix}topics
	ADD COLUMN redirect_expires int NOT NULL DEFAULT '0'");
upgrade_query("
	ALTER TABLE {$db_prefix}topics
	ADD COLUMN id_redirect_topic int NOT NULL DEFAULT '0'");
---}
---#

/******************************************************************************/
--- Updating scheduled tasks
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
	(0, 360, 10, 'm', 1, 'maillist_fetch_IMAP');
INSERT INTO {$db_prefix}scheduled_tasks
	(next_time, time_offset, time_regularity, time_unit, disabled, task)
VALUES
	(0, 30, 1, 'h', 0, 'user_access_mentions');
---#

---# Remove unused scheduled tasks...
---{
upgrade_query("
	DELETE FROM {$db_prefix}scheduled_tasks
	WHERE task = 'fetchFiles'");
---}
---#

/******************************************************************************/
--- Adding support for deny boards access
/******************************************************************************/
---# Adding new columns to boards...
---{
upgrade_query("
	ALTER TABLE {$db_prefix}boards
	ADD COLUMN deny_member_groups varchar(255) NOT NULL DEFAULT ''");
---}
---#

/******************************************************************************/
--- Adding support for topic unwatch
/******************************************************************************/
---# Adding new columns to log_topics...
---{
upgrade_query("
	ALTER TABLE {$db_prefix}log_topics
	ADD COLUMN unwatched int NOT NULL DEFAULT '0'");
---}

UPDATE {$db_prefix}log_topics
SET unwatched = 0;
---#

/******************************************************************************/
--- Adding support for custom profile fields on the memberlist and ordering
/******************************************************************************/
---# Adding new columns to boards...
ALTER TABLE {$db_prefix}custom_fields
ADD COLUMN show_memberlist smallint NOT NULL DEFAULT '0',
ADD COLUMN vieworder smallint NOT NULL default '0';
---#

/******************************************************************************/
--- Fixing mail queue for long messages
/******************************************************************************/
---# Altering mail_queue table...
ALTER TABLE {$db_prefix}mail_queue
CHANGE body body mediumtext NOT NULL;
---#

/******************************************************************************/
--- Fixing floodcontrol for long types
/******************************************************************************/
---# Altering the floodcontrol table...
ALTER TABLE {$db_prefix}log_floodcontrol
CHANGE `log_type` `log_type` varchar(10) NOT NULL DEFAULT 'post';
---#

/******************************************************************************/
--- Name changes
/******************************************************************************/
---# Altering the membergroup stars to icons
---{
upgrade_query("
	ALTER TABLE {$db_prefix}membergroups
	CHANGE COLUMN stars icons varchar(255) NOT NULL DEFAULT ''");
---}
---#

/******************************************************************************/
--- Adding support for drafts
/******************************************************************************/
---# Creating sequence for user_drafts.
CREATE SEQUENCE {$db_prefix}user_drafts_seq;
---#

---# Creating draft table
CREATE TABLE IF NOT EXISTS {$db_prefix}user_drafts (
	id_draft int default nextval('{$db_prefix}user_drafts_seq'),
	id_topic int NOT NULL default '0',
	id_board smallint NOT NULL default '0',
	id_reply int NOT NULL default '0',
	type smallint NOT NULL default '0',
	poster_time int NOT NULL default '0',
	id_member int NOT NULL default '0',
	subject varchar(255) NOT NULL default '',
	smileys_enabled smallint NOT NULL default '1',
	body text NOT NULL,
	icon varchar(16) NOT NULL default 'xx',
	locked smallint NOT NULL default '0',
	is_sticky smallint NOT NULL default '0',
	to_list varchar(255) NOT NULL default '',
	PRIMARY KEY (id_draft)
);
---#

---# Adding draft permissions...
---{
// We cannot do this twice
// @todo this won't work when you upgrade from smf <= is it still true?
if (empty($modSettings['elkVersion']) || compareVersions($modSettings['elkVersion'], '1.0') == -1)
{
	// Anyone who can currently post unapproved topics we assume can create drafts as well ...
	$request = upgrade_query("
		SELECT id_group, id_profile, add_deny, permission
		FROM {$db_prefix}board_permissions
		WHERE permission = 'post_unapproved_topics'");
	$inserts = array();
	while ($row = $db->fetch_assoc($request))
	{
		$inserts[] = "($row[id_group], $row[id_profile], 'post_draft', $row[add_deny])";
		$inserts[] = "($row[id_group], $row[id_profile], 'post_autosave_draft', $row[add_deny])";
	}
	$db->free_result($request);

	if (!empty($inserts))
		upgrade_query("
			INSERT IGNORE INTO {$db_prefix}board_permissions
				(id_group, id_profile, permission, add_deny)
			VALUES
				" . implode(',', $inserts));

	// Next we find people who can send PMs, and assume they can save pm_drafts as well
	$request = upgrade_query("
		SELECT id_group, add_deny, permission
		FROM {$db_prefix}permissions
		WHERE permission = 'pm_send'");
	$inserts = array();
	while ($row = $db->fetch_assoc($request))
	{
		$inserts[] = "($row[id_group], 'pm_draft', $row[add_deny])";
		$inserts[] = "($row[id_group], 'pm_autosave_draft', $row[add_deny])";
	}
	$db->free_result($request);

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
--- Adding support for custom profile fields data
/******************************************************************************/
---# Creating custom profile fields data table
CREATE TABLE IF NOT EXISTS {$db_prefix}custom_fields_data (
	id_member int NOT NULL default '0',
	variable varchar(255) NOT NULL default '',
	value text NOT NULL,
	PRIMARY KEY (id_member, variable)
);
---#

---#
CREATE INDEX {$db_prefix}custom_fields_data_id_member ON {$db_prefix}custom_fields_data (id_member);
---#

---# Move existing custom profile values...
---{
$request = upgrade_query("
	INSERT INTO {$db_prefix}custom_fields_data
		(id_member, variable, value)
	SELECT id_member, variable, value
	FROM {$db_prefix}themes
	WHERE SUBSTRING(variable, 1, 5) = 'cust_'");

// remove the moved rows from themes
if ($db->affected_rows() != 0)
{
	upgrade_query("
		DELETE FROM {$db_prefix}themes
		SUBSTRING(variable,1,5) = 'cust_'");
}
---}
---#

/******************************************************************************/
--- Messenger fields
/******************************************************************************/
---# Insert new fields
INSERT INTO {$db_prefix}custom_fields
	(col_name, field_name, field_desc, field_type, field_length, field_options, mask, show_reg, show_display, show_profile, private, active, bbc, can_search, default_value, enclose, placement)
VALUES
	('cust_aim', 'AOL Instant Messenger', 'This is your AOL Instant Messenger nickname.', 'text', 50, '', 'regex~[a-z][0-9a-z.-]{1,31}~i', 0, 1, 'forumprofile', 0, 1, 0, 0, '', '<a class="aim" href="aim:goim?screenname={INPUT}&message=Hello!+Are+you+there?" target="_blank" title="AIM - {INPUT}"><img src="{IMAGES_URL}/profile/aim.png" alt="AIM - {INPUT}"></a>', 1);
INSERT INTO {$db_prefix}custom_fields
	(col_name, field_name, field_desc, field_type, field_length, field_options, mask, show_reg, show_display, show_profile, private, active, bbc, can_search, default_value, enclose, placement)
VALUES
	('cust_icq', 'ICQ', 'This is your ICQ number.', 'text', 12, '', 'regex~[1-9][0-9]{4,9}~i', 0, 1, 'forumprofile', 0, 1, 0, 0, '', '<a class="icq" href="http://www.icq.com/whitepages/about_me.php?uin={INPUT}" target="_blank" title="ICQ - {INPUT}"><img src="http://status.icq.com/online.gif?img=5&icq={INPUT}" alt="ICQ - {INPUT}" width="18" height="18"></a>', 1);
INSERT INTO {$db_prefix}custom_fields
	(col_name, field_name, field_desc, field_type, field_length, field_options, mask, show_reg, show_display, show_profile, private, active, bbc, can_search, default_value, enclose, placement)
VALUES
	('cust_skye', 'Skype', 'This is your Skype account name', 'text', 32, '', 'regex~[a-z][0-9a-z.-]{1,31}~i', 0, 1, 'forumprofile', 0, 1, 0, 0, '', '<a href="skype:{INPUT}?call"><i class="fa fa-skype" title="Skype - {INPUT}"></i></a>', 1);
INSERT INTO {$db_prefix}custom_fields
	(col_name, field_name, field_desc, field_type, field_length, field_options, mask, show_reg, show_display, show_profile, private, active, bbc, can_search, default_value, enclose, placement)
VALUES
	('cust_fbook', 'Facebook Profile', 'Enter your Facebook username.', 'text', 50, '', 'regex~[a-z][0-9a-z.-]{1,31}~i', 0, 1, 'forumprofile', 0, 1, 0, 0, '', '<a target="_blank" href="https://www.facebook.com/{INPUT}"><img src="{DEFAULT_IMAGES_URL}/profile/facebook.png" alt="{INPUT}" /></a>', 1);
INSERT INTO {$db_prefix}custom_fields
	(col_name, field_name, field_desc, field_type, field_length, field_options, mask, show_reg, show_display, show_profile, private, active, bbc, can_search, default_value, enclose, placement)
VALUES
	('cust_twitt', 'Twitter Profile', 'Enter your Twitter username.', 'text', 50, '', 'regex~[a-z][0-9a-z.-]{1,31}~i', 0, 1, 'forumprofile', 0, 1, 0, 0, '', '<a target="_blank" href="https://www.twitter.com/{INPUT}"><img src="{DEFAULT_IMAGES_URL}/profile/twitter.png" alt="{INPUT}" /></a>', 1);
INSERT INTO {$db_prefix}custom_fields
	(col_name, field_name, field_desc, field_type, field_length, field_options, mask, show_reg, show_display, show_profile, private, active, bbc, can_search, default_value, enclose, placement)
VALUES
	('cust_linked', 'LinkedIn Profile', 'Set your LinkedIn Public profile link. You must set a Custom public url for this to work.', 'text', 255, '', 'nohtml', 0, 1, 'forumprofile', 0, 1, 0, 0, '', '<a target={INPUT}"><img src="{DEFAULT_IMAGES_URL}/profile/linkedin.png" alt="LinkedIn profile" /></a>', 1);
INSERT INTO {$db_prefix}custom_fields
	(col_name, field_name, field_desc, field_type, field_length, field_options, mask, show_reg, show_display, show_profile, private, active, bbc, can_search, default_value, enclose, placement)
VALUES
	('cust_gplus', 'Google+ Profile', 'This is your Google+ profile url.', 'text', 255, '', 'nohtml', 0, 1, 'forumprofile', 0, 1, 0, 0, '', '<a target="_blank" href="{INPUT}"><img src="{DEFAULT_IMAGES_URL}/profile/gplus.png" alt="G+ profile" /></a>', 1);
INSERT INTO {$db_prefix}custom_fields
	(col_name, field_name, field_desc, field_type, field_length, field_options, mask, show_reg, show_display, show_profile, private, active, bbc, can_search, default_value, enclose, placement)
VALUES
	('cust_yim', 'Yahoo! Messenger', 'This is your Yahoo! Instant Messenger nickname.', 'text', 50, '', 'email', 0, 1, 'forumprofile', 0, 1, 0, 0, '', '<a class="yim" href="http://edit.yahoo.com/config/send_webmesg?.target={INPUT}" target="_blank" title="Yahoo! Messenger - {INPUT}"><img src="http://opi.yahoo.com/online?m=g&t=0&u={INPUT}" alt="Yahoo! Messenger - {INPUT}"></a>', 1);
---#

---# Move existing values...
---{
// We cannot do this twice
$db_table = db_table();
$members_tbl = $db_table->db_table_structure("{db_prefix}members");
$move_im = false;
foreach ($members_tbl['columns'] as $members_col)
{
	// One spot, if there is just one we can go on and do the moving
	if ($members_col['name'] == 'aim')
	{
		$move_im = true;
		break;
	}
}

if ($move_im)
{
	$request = upgrade_query("
		SELECT id_member, aim, icq, msn, yim
		FROM {$db_prefix}members");
	$inserts = array();
	while ($row = $db->fetch_assoc($request))
	{
		if (!empty($row[aim]))
			$inserts[] = "($row[id_member], 'cust_aim', '" . addslashes($row['aim']) . "')";

		if (!empty($row[icq]))
			$inserts[] = "($row[id_member], 'cust_icq', '" . addslashes($row['icq']) . "')";

		if (!empty($row[msn]))
			$inserts[] = "($row[id_member], 'cust_skype', '" . addslashes($row['msn']) . "')";

		if (!empty($row[yim]))
			$inserts[] = "($row[id_member], 'cust_yim', '" . addslashes($row['yim']) . "')";
	}
	$db->free_result($request);

	if (!empty($inserts))
		upgrade_query("
			INSERT INTO {$db_prefix}custom_fields_data
				(id_member, variable, value)
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
if (empty($modSettings['elkVersion']) || compareVersions($modSettings['elkVersion'], '1.0') == -1)
{
	// Try find people who probably can use remote avatars.
	$request = upgrade_query("
		SELECT id_group, add_deny, permission
		FROM {$db_prefix}permissions
		WHERE permission = 'profile_remote_avatar'");
	$inserts = array();
	while ($row = $db->fetch_assoc($request))
	{
		$inserts[] = "($row[id_group], 'profile_gravatar', $row[add_deny])";
	}
	$db->free_result($request);

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
	follow_up int NOT NULL default '0',
	derived_from int NOT NULL default '0',
	PRIMARY KEY (follow_up, derived_from)
);
---#

/******************************************************************************/
--- Updating antispam questions.
/******************************************************************************/

---# Creating sequence for antispam_questions table...
CREATE SEQUENCE {$db_prefix}antispam_questions_seq;
---#

---# Creating antispam questions table...
CREATE TABLE IF NOT EXISTS {$db_prefix}antispam_questions (
	id_question int default nextval('{$db_prefix}antispam_questions_seq'),
	question text NOT NULL default '',
	answer text NOT NULL default '',
	language varchar(50) NOT NULL default '',
	PRIMARY KEY (id_question)
);
---#

#
# Indexes for table `ban_items`
#

---# Creating index for antispam_questions table...
CREATE INDEX {$db_prefix}antispam_questions_language ON {$db_prefix}antispam_questions (language);
---#

---# Move existing values...
---{
global $language;

$request = upgrade_query("
	SELECT id_comment, recipient_name as answer, body as question
	FROM {$db_prefix}log_comments
	WHERE comment_type = 'ver_test'");
if ($db->num_rows($request) != 0)
{
	$values = array();
	$id_comments = array();
	while ($row = $db->fetch_assoc($request))
	{
		upgrade_query("
			INSERT INTO {$db_prefix}antispam_questions
				(answer, question, language)
			VALUES
				('" . serialize(array($row['answer'])) . "', '" . $row['question'] . "', '" . $language . "')");
		upgrade_query("
			DELETE FROM {$db_prefix}log_comments
			WHERE id_comment = " . $row['id_comment'] . "
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
	id_email varchar(50) NOT NULL default '',
	time_sent int NOT NULL default '0',
	email_to varchar(50) NOT NULL default '',
	PRIMARY KEY (id_email)
);
---#

---# Sequence for table `postby_emails_error`
CREATE SEQUENCE {$db_prefix}postby_emails_error_seq;
---#

---# Creating postby_emails_error table
CREATE TABLE IF NOT EXISTS {$db_prefix}postby_emails_error (
	id_email int default nextval('{$db_prefix}postby_emails_error_seq'),
	error varchar(255) NOT NULL default '',
	data_id varchar(255) NOT NULL default '0',
	subject varchar(255) NOT NULL default '',
	id_message int NOT NULL default '0',
	id_board smallint NOT NULL default '0',
	email_from varchar(50) NOT NULL default '',
	message_type char(10) NOT NULL default '',
	message mediumtext NOT NULL default '',
	PRIMARY KEY (id_email)
);
---#

---# Sequence for table `postby_emails_filter`
CREATE SEQUENCE {$db_prefix}postby_emails_filters_seq;
---#

---# Creating postby_emails_filters table
CREATE TABLE IF NOT EXISTS {$db_prefix}postby_emails_filters (
	id_filter int default nextval('{$db_prefix}postby_emails_filters_seq'),
	filter_style char(5) NOT NULL default '',
	filter_type varchar(255) NOT NULL default '',
	filter_to varchar(255) NOT NULL default '',
	filter_from varchar(255) NOT NULL default '',
	filter_name varchar(255) NOT NULL default '',
	PRIMARY KEY (id_filter)
);
---#

---# Adding new columns to postby_emails_filters...
ALTER TABLE {$db_prefix}postby_emails_filters
ADD COLUMN filter_order int NOT NULL default '0';
---#

---# Set the default values so the order is set / maintained
---{
upgrade_query("
	UPDATE {$db_prefix}postby_emails_filters
	SET filter_order = id_filter");
---}
---#

---# Adding new columns to log_activity...
ALTER TABLE {$db_prefix}log_activity
ADD COLUMN pm smallint NOT NULL DEFAULT '0',
ADD COLUMN email smallint NOT NULL DEFAULT '0';
---#

---# Adding new columns to mail_queue...
ALTER TABLE {$db_prefix}mail_queue
ADD COLUMN message_id varchar(12) NOT NULL DEFAULT '';
---#

---# Updating board profiles...
INSERT INTO {$db_prefix}board_permissions (id_group, id_profile, permission) VALUES (0, 1, 'postby_email');
INSERT INTO {$db_prefix}board_permissions (id_group, id_profile, permission) VALUES (0, 2, 'postby_email');
---#

/******************************************************************************/
--- Adding likes support.
/******************************************************************************/

---# Creating likes log table...
CREATE TABLE IF NOT EXISTS {$db_prefix}log_likes (
	action char(1) NOT NULL default '0',
	id_target int NOT NULL default '0',
	id_member int NOT NULL default '0',
	log_time int NOT NULL default '0',
	PRIMARY KEY (id_target, id_member)
);
---#

---# Creating likes log index ...
CREATE INDEX {$db_prefix}log_likes_log_time ON {$db_prefix}log_likes (log_time);
---#

---# Creating likes message table...
CREATE TABLE IF NOT EXISTS {$db_prefix}message_likes (
	id_member int NOT NULL default '0',
	id_msg int NOT NULL default '0',
	id_poster int NOT NULL default '0',
	PRIMARY KEY (id_msg, id_member)
);
---#

---# Creating message_likes index ...
CREATE INDEX {$db_prefix}message_likes_id_member ON {$db_prefix}message_likes (id_member);
CREATE INDEX {$db_prefix}message_likes_id_poster ON {$db_prefix}message_likes (id_poster);
---#

---# Adding new columns to topics...
ALTER TABLE {$db_prefix}topics
ADD COLUMN num_likes int NOT NULL default '0';
---#

---# Adding new columns to members...
ALTER TABLE {$db_prefix}members
ADD COLUMN likes_given int NOT NULL default '0',
ADD COLUMN likes_received int NOT NULL default '0';
---#

/******************************************************************************/
--- Changing contacting options.
/******************************************************************************/

---# Renaming column that stores the PM receiving setting...
ALTER TABLE {$db_prefix}members
CHANGE pm_receive_from receive_from tinyint NOT NULL default '1';
---#

/******************************************************************************/
--- Adding mentions support.
/******************************************************************************/

---# Creating mentions log index ...
CREATE SEQUENCE {$db_prefix}log_mentions_id_mention_seq;
---#

---# Creating mentions log table...
CREATE TABLE IF NOT EXISTS {$db_prefix}log_mentions (
	id_mention int default nextval('{$db_prefix}log_mentions_id_mention_seq'),
	id_member int NOT NULL DEFAULT '0',
	id_msg int NOT NULL DEFAULT '0',
	status int NOT NULL DEFAULT '0',
	id_member_from int NOT NULL DEFAULT '0',
	log_time int NOT NULL DEFAULT '0',
	mention_type varchar(5) NOT NULL DEFAULT '',
	PRIMARY KEY (id_mention)
);
---#

---# Creating mentions log index ...
CREATE INDEX {$db_prefix}log_mentions_id_member ON {$db_prefix}log_mentions (id_member, status);
---#

---# Adding new columns to members...
ALTER TABLE {$db_prefix}members
ADD COLUMN mentions smallint NOT NULL default '0';
---#

/******************************************************************************/
--- Fixing personal messages column name
/******************************************************************************/
---# Renaming instant_messages to personal_messages...
ALTER TABLE {$db_prefix}members
CHANGE `instant_messages` `personal_messages` smallint NOT NULL default 0;
---#

/******************************************************************************/
--- Fixes from 1.0.1
/******************************************************************************/
---# Adding new column to message_likes...
ALTER TABLE {$db_prefix}message_likes
ADD COLUMN like_timestamp int NOT NULL default '0';
---#

---# More space for email filters...
ALTER TABLE {$db_prefix}postby_emails_filters
CHANGE `filter_style` `filter_style` char(10) NOT NULL default '';
---#

---# Possible wrong type for mail_queue...
ALTER TABLE {$db_prefix}mail_queue
CHANGE `message_id` `message_id` varchar(12) NOT NULL default '';
---#

/******************************************************************************/
--- Changes for 1.0.2
/******************************************************************************/
---# Remove unused avatar permissions and settings...
---{
upgrade_query("
	DELETE FROM {$db_prefix}permissions
	WHERE permission = 'profile_upload_avatar'");
upgrade_query("
	DELETE FROM {$db_prefix}permissions
	WHERE permission = 'profile_remote_avatar'");
upgrade_query("
	DELETE FROM {$db_prefix}permissions
	WHERE permission = 'profile_gravatar'");
upgrade_query("
	UPDATE {$db_prefix}permissions
	SET permission = 'profile_set_avatar'
	WHERE permission = 'profile_server_avatar'");

upgrade_query("
	UPDATE {$db_prefix}settings
	SET value = {string:value}
	WHERE variable = {string:variable}",
	array(
		'value' => $modSettings['avatar_max_height_external'],
		'variable' => 'avatar_max_height'
	)
);
upgrade_query("
	UPDATE {$db_prefix}settings
	SET value = {string:value}
	WHERE variable = {string:variable}",
	array(
		'value' => $modSettings['avatar_max_width_external'],
		'variable' => 'avatar_max_width'
	)
);

upgrade_query("
	INSERT INTO {$db_prefix}settings
		(variable, value)
	VALUES
	('avatar_stored_enabled', '1')");
upgrade_query("
	INSERT INTO {$db_prefix}settings
		(variable, value)
	VALUES
	('avatar_external_enabled', '1')");
upgrade_query("
	INSERT INTO {$db_prefix}settings
		(variable, value)
	VALUES
	('avatar_gravatar_enabled', '1')");
upgrade_query("
	INSERT INTO {$db_prefix}settings
		(variable, value)
	VALUES
	('avatar_upload_enabled', '1')");
---}
---#

/******************************************************************************/
--- Changes for 1.0.4
/******************************************************************************/
---# Update to new package server...
---{
upgrade_query("
	UPDATE {$db_prefix}package_servers
	SET url = {string:value}
	WHERE name = {string:name}",
	array(
		'value' => 'http://addons.elkarte.net/package.json',
		'name' => 'ElkArte Third-party Add-ons Site'
	)
);
---}
---#

/******************************************************************************/
--- Changes for 1.0.10
/******************************************************************************/
---# Update to new ICQ syntax
---{
$request = upgrade_query("
  SELECT id_field, col_name
  FROM {$db_prefix}custom_fields
  WHERE placement=1");

  while ($row = $db->fetch_assoc($request))
  {
    if ($row['col_name'] == 'cust_icq')
    {
      upgrade_query('
        UPDATE {$db_prefix}custom_fields
        SET enclose=\'<a class="icq" href="//www.icq.com/people/{INPUT}" target="_blank" title="ICQ - {INPUT}"><img src="http://status.icq.com/online.gif?img=5&icq={INPUT}" alt="ICQ - {INPUT}" width="18" height="18"></a>\'
        WHERE id_field=' . $row['id_field']);
    }
  }
---}
---#
