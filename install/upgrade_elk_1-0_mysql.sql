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

---# Adding login history...
---{
$db_table->db_create_table('{db_prefix}member_logins',
	array(
		array(
			'name' => 'id_login',
			'type' => 'int',
			'size' => 10,
			'auto' => true
		),
		array(
			'name' => 'id_member',
			'type' => 'mediumint',
			'unsigned' => true,
			'size' => 8
		),
		array(
			'name' => 'time',
			'type' => 'int',
			'size' => 10
		),
		array(
			'name' => 'ip',
			'type' => 'varchar',
			'size' => 255,
			'default' => ''
		),
		array(
			'name' => 'ip2',
			'type' => 'varchar',
			'size' => 255,
			'default' => ''
		)
	),
	array(
		array(
			'name' => array('id_login'),
			'columns' => array('id_login'),
			'type' => 'primary'
		),
		array(
			'name' => array('id_member'),
			'columns' => array('id_member'),
			'type' => 'key'
		),
		array(
			'name' => array('time'),
			'columns' => array('time'),
			'type' => 'key'
		)
	),
	array(),
	'ignore'
);
---}
---#

---# Copying the current package backup setting...
---{
if (!isset($modSettings['package_make_full_backups']) && isset($modSettings['package_make_backups']))
	$db->insert('',
		'{db_prefix}settings',
		array(
			'variable' => 'string',
			'value' => 'string'
		),
		array (
			'package_make_full_backups',
			$modSettings['package_make_backups']
		),
		array('variable')
	);
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

---# Adding new settings to the settings table...
---{
	$db->insert('ignore',
		'{db_prefix}settings',
		array(
			'variable' => 'string',
			'value' => 'string'
		),
		array (
			array('avatar_default', '0'),
			array('gravatar_rating', 'g'),
			array('admin_session_lifetime', 10),
			array('xmlnews_limit', 5),
			array('visual_verification_num_chars', '6'),
			array('enable_unwatch', 0),
			array('jquery_source', 'local')
			array('mentions_enabled', '1'),
			array('mentions_buddy', '0'),
			array('mentions_dont_notify_rlike', '0'),
			array('detailed-version.js', 'https://elkarte.github.io/Elkarte/site/detailed-version.js');
		),
		array('variable')
	);
---}
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
---{
$db_table->db_add_index('{db_prefix}attachments',
	array(
		array(
			'name' => array('id_thumb'),
			'columns' => array('id_thumb'),
			'type' => 'key'
		)
	),
	array(),
	'ignore'
);
---}
---#

/******************************************************************************/
--- Adding support for IPv6...
/******************************************************************************/

---# Adding new columns to ban items...
---{
$db_table->db_add_column('{db_prefix}ban_items',
	array(
		'name' => 'ip_low5',
		'type' => 'smallint',
		'unsigned' => true,
		'size' => 255,
		'default' => 0
	),
	array(),
	'ignore'
);
$db_table->db_add_column('{db_prefix}ban_items',
	array(
		'name' => 'ip_high5',
		'type' => 'smallint',
		'unsigned' => true,
		'size' => 255,
		'default' => 0
	),
	array(),
	'ignore'
);
$db_table->db_add_column('{db_prefix}ban_items',
	array(
		'name' => 'ip_low6',
		'type' => 'smallint',
		'unsigned' => true,
		'size' => 255,
		'default' => 0
	),
	array(),
	'ignore'
);
$db_table->db_add_column('{db_prefix}ban_items',
	array(
		'name' => 'ip_high6',
		'type' => 'smallint',
		'unsigned' => true,
		'size' => 255,
		'default' => 0
	),
	array(),
	'ignore'
);
$db_table->db_add_column('{db_prefix}ban_items',
	array(
		'name' => 'ip_low7',
		'type' => 'smallint',
		'unsigned' => true,
		'size' => 255,
		'default' => 0
	),
	array(),
	'ignore'
);
$db_table->db_add_column('{db_prefix}ban_items',
	array(
		'name' => 'ip_high7',
		'type' => 'smallint',
		'unsigned' => true,
		'size' => 255,
		'default' => 0
	),
	array(),
	'ignore'
);
$db_table->db_add_column('{db_prefix}ban_items',
	array(
		'name' => 'ip_low8',
		'type' => 'smallint',
		'unsigned' => true,
		'size' => 255,
		'default' => 0
	),
	array(),
	'ignore'
);
$db_table->db_add_column('{db_prefix}ban_items',
	array(
		'name' => 'ip_high8',
		'type' => 'smallint',
		'unsigned' => true,
		'size' => 255,
		'default' => 0
	)
	array(),
	'ignore'
);
---}
---#

---# Changing existing columns to ban items...
---{
$db_table->db_change_column('{db_prefix}ban_items',
	'ip_low1',
	array(
		'name' => 'ip_low1',
		'type' => 'smallint',
		'unsigned' => true,
		'size' => 255,
		'default' => 0
	)
	array()
);
$db_table->db_change_column('{db_prefix}ban_items',
	'ip_high1',
	array(
		'name' => 'ip_high1',
		'type' => 'smallint',
		'unsigned' => true,
		'size' => 255,
		'default' => 0
	)
	array()
);
$db_table->db_change_column('{db_prefix}ban_items',
	'ip_low2',
	array(
		'name' => 'ip_low2',
		'type' => 'smallint',
		'unsigned' => true,
		'size' => 255,
		'default' => 0
	)
	array()
);
$db_table->db_change_column('{db_prefix}ban_items',
	'ip_high2',
	array(
		'name' => 'ip_high2',
		'type' => 'smallint',
		'unsigned' => true,
		'size' => 255,
		'default' => 0
	)
	array()
);
$db_table->db_change_column('{db_prefix}ban_items',
	'ip_low3',
	array(
		'name' => 'ip_low3',
		'type' => 'smallint',
		'unsigned' => true,
		'size' => 255,
		'default' => 0
	)
	array()
);
$db_table->db_change_column('{db_prefix}ban_items',
	'ip_high3',
	array(
		'name' => 'ip_high3',
		'type' => 'smallint',
		'unsigned' => true,
		'size' => 255,
		'default' => 0
	)
	array()
);
$db_table->db_change_column('{db_prefix}ban_items',
	'ip_low4',
	array(
		'name' => 'ip_low4',
		'type' => 'smallint',
		'unsigned' => true,
		'size' => 255,
		'default' => 0
	)
	array()
);
$db_table->db_change_column('{db_prefix}ban_items',
	'ip_high4',
	array(
		'name' => 'ip_high4',
		'type' => 'smallint',
		'unsigned' => true,
		'size' => 255,
		'default' => 0
	)
	array()
);
---}
---#

/******************************************************************************/
--- Adding support for <credits> tag in package manager
/******************************************************************************/
---# Adding new columns to log_packages...
---{
$db_table->db_add_column('{db_prefix}log_packages',
	array(
		'name' => 'credits',
		'type' => 'varchar',
		'size' => 255,
		'default' => ''
	),
	array(),
	'ignore'
);
---}
---#

/******************************************************************************/
--- Adding more space for session ids
/******************************************************************************/
---# Altering the session_id columns...
---{
$db_table->db_change_column('{db_prefix}log_online',
	'session',
	array(
		'name' => 'session',
		'type' => 'varchar',
		'size' => 64,
		'default' => ''
	)
	array()
);
$db_table->db_change_column('{db_prefix}log_errors',
	'session',
	array(
		'name' => 'session',
		'type' => 'char',
		'size' => 64,
		'default' => '                                                                '
	)
	array()
);
$db_table->db_change_column('{db_prefix}sessions',
	'session_id',
	array(
		'name' => 'session_id',
		'type' => 'char',
		'size' => 64
	)
	array()
);
---}
---#

/******************************************************************************/
--- Adding support for MOVED topics enhancements
/******************************************************************************/
---# Adding new columns to topics ..
---{

$db_table->db_add_column('{db_prefix}topics',
	array(
		'name' => 'redirect_expires',
		'type' => 'int',
		'unsigned' => true,
		'size' => 10,
		'default' => 0
	),
	array(),
	'ignore'
);
$db_table->db_add_column('{db_prefix}topics',
	array(
		'name' => 'id_redirect_topic',
		'type' => 'mediumint',
		'unsigned' => true,
		'size' => 8,
		'default' => 0
	),
	array(),
	'ignore'
);
---}
---#

/******************************************************************************/
--- Updating scheduled tasks
/******************************************************************************/
---# Adding new scheduled tasks
---{
$db->insert('',
	'{db_prefix}scheduled_tasks',
	array('next_time' => 'int', 'time_offset' => 'int', 'time_regularity' => 'int', 'time_unit' => 'string', 'disabled' => 'int', 'task' => 'int'),
	array(
		array(0, 120, 1, 'd', 0, 'remove_temp_attachments'),
		array(0, 180, 1, 'd', 0, 'remove_topic_redirect'),
		array(0, 240, 1, 'd', 0, 'remove_old_drafts'),
		array(0, 0, 6, 'h', 0, 'remove_old_followups'),
		array(0, 360, 10, 'm', 0, 'maillist_fetch_IMAP'),
		array(0, 30, 1, 'h', 0, 'user_access_mentions'),
		array('task')
	)
);
---}
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
$db_table->db_add_column('{db_prefix}boards',
	array(
		'name' => 'deny_member_groups',
		'type' => 'varchar',
		'size' => 255,
		'default' => ''
	),
	array(),
	'ignore'
);
---}
---#

/******************************************************************************/
--- Adding support for topic unwatched
/******************************************************************************/
---# Adding new columns to boards...
---{
$db_table->db_add_column('{db_prefix}log_topics',
	array(
		'name' => 'unwatched',
		'type' => 'tinyint',
		'size' => 3,
		'default' => 0
	),
	array(),
	'ignore'
);
---}

UPDATE {$db_prefix}log_topics
SET unwatched = 0;
---#

/******************************************************************************/
--- Adding support for custom profile fields on the memberlist and ordering
/******************************************************************************/
---# Adding new columns to custom_fields...
---{
$db_table->db_add_column('{db_prefix}custom_fields',
	array(
		'name' => 'show_memberlist',
		'type' => 'tinyint',
		'size' => 3,
		'default' => 0
	),
	array(),
	'ignore'
);
$db_table->db_add_column('{db_prefix}custom_fields',
	array(
		'name' => 'vieworder',
		'type' => 'smallint',
		'size' => 5,
		'default' => 0
	),
	array(),
	'ignore'
);
---}
---#

/******************************************************************************/
--- Fixing mail queue for long messages
/******************************************************************************/
---# Altering mail_queue table...
---{
$db_table->db_change_column('{db_prefix}mail_queue',
	'body',
	array(
		'name' => 'body',
		'type' => 'mediumtext'
	)
	array()
);
---}
---#

/******************************************************************************/
--- Fixing floodcontrol for long types
/******************************************************************************/
---# Altering the floodcontrol table...
---{
$db_table->db_change_column('{db_prefix}log_floodcontrol',
	'log_type',
	array(
		'name' => 'log_type',
		'type' => 'varchar',
		'size' => 10,
		'default' => 'post'
	)
	array()
);
---}
---#

/******************************************************************************/
--- Name changes
/******************************************************************************/
---# Altering the membergroup stars to icons
---{
$db_table->db_change_column('{db_prefix}membergroups',
	'stars',
	array(
		'name' => 'icons',
		'type' => 'varchar',
		'size' => 255,
		'default' => ''
	)
	array()
);
---}
---#

/******************************************************************************/
--- Adding support for drafts
/******************************************************************************/
---# Creating draft table
---{
$db_table->db_create_table('{db_prefix}user_drafts',
	array(
		array(
			'name' => 'id_draft',
			'type' => 'int',
			'unsigned' => true,
			'size' => 10,
			'auto' => true
		),
		array(
			'name' => 'id_topic',
			'type' => 'mediumint',
			'unsigned' => true,
			'size' => 8,
			'default' => 0
		),
		array(
			'name' => 'id_board',
			'type' => 'smallint',
			'unsigned' => true,
			'size' => 5,
			'default' => 0
		),
		array(
			'name' => 'id_reply',
			'type' => 'int',
			'unsigned' => true,
			'size' => 10,
			'default' => 0
		),
		array(
			'name' => 'poster_time',
			'type' => 'int',
			'unsigned' => true,
			'size' => 10,
			'default' => 0
		),
		array(
			'name' => 'id_member',
			'type' => 'mediumint',
			'unsigned' => true,
			'size' => 8,
			'default' => 0
		),
		array(
			'name' => 'subject',
			'type' => 'varchar',
			'size' => 255,
			'default' => ''
		),
		array(
			'name' => 'smileys_enabled',
			'type' => 'tinyint',
			'size' => 4,
			'default' => 1
		),
		array(
			'name' => 'body',
			'type' => 'mediumtext'
		),
		array(
			'name' => 'icon',
			'type' => 'varchar',
			'size' => 16,
			'default' => 'xx'
		),
		array(
			'name' => 'locked',
			'type' => 'tinyint',
			'size' => 4,
			'default' => 0
		),
		array(
			'name' => 'is_sticky',
			'type' => 'tinyint',
			'size' => 4,
			'default' => 0
		),
		array(
			'name' => 'to_list',
			'type' => 'varchar',
			'size' => 255,
			'default' => ''
		)
	),
	array(
		array(
			'name' => array('id_draft'),
			'columns' => array('id_draft'),
			'type' => 'primary'
		),
		array(
			'name' => array('id_member'),
			'columns' => array('id_member', 'id_draft', 'type'),
			'type' => 'unique'
		)
	),
	array(),
	'ignore'
);
---}
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
		$db->insert('ignore',
			'{db_prefix}board_permissions',
			array('id_group' => 'int', 'id_profile' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
			$inserts,
			array('id_group', 'id_profile', 'permission')
		);

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
		$db->insert('ignore',
			'{db_prefix}permissions',
			array('id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
			$inserts,
			array('id_group', 'permission')
		);
}
---}
---#

/******************************************************************************/
--- Adding support for custom profile fields data
/******************************************************************************/
---# Creating custom profile fields data table
---{
$db_table->db_create_table('{db_prefix}custom_fields_data',
	array(
		array(
			'name' => 'id_member',
			'type' => 'mediumint',
			'unsigned' => true,
			'size' => 8,
			'default' => 0
		),
		array(
			'name' => 'variable',
			'type' => 'varchar',
			'size' => 255,
			'default' => ''
		),
		array(
			'name' => 'value',
			'type' => 'text'
		)
	),
	array(
		array(
			'name' => array('id_member_variable'),
			'columns' => array('id_member', 'variable(30)'),
			'type' => 'primary'
		),
		array(
			'name' => array('id_member'),
			'columns' => array('id_member'),
			'type' => 'key'
		)
	),
	array(),
	'ignore'
);
---}
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
---{
$db->insert('',
	'{db_prefix}custom_fields',
	array(
		'col_name' => 'string',
		'field_name' => 'string',
		'field_desc' => 'string',
		'field_type' => 'string',
		'field_length' => 'int',
		'field_options' => 'string',
		'mask' => 'string',
		'show_reg' => 'int',
		'show_display' => 'int',
		'show_profile' => 'int',
		'private' => 'int',
		'active' => 'int',
		'bbc' => 'int',
		'can_search' => 'int',
		'default_value' => 'string',
		'enclose' => 'string',
		'placement' => 'int'
	),
	array(
		array('cust_aim', 'AOL Instant Messenger', 'This is your AOL Instant Messenger nickname.', 'text', 50, '', 'regex~[a-z][0-9a-z.-]{1,31}~i', 0, 1, 'forumprofile', 0, 1, 0, 0, '', '<a class="aim" href="aim:goim?screenname={INPUT}&message=Hello!+Are+you+there?" target="_blank" title="AIM - {INPUT}"><img src="{IMAGES_URL}/profile/aim.png" alt="AIM - {INPUT}"></a>', 1),
		array('cust_icq', 'ICQ', 'This is your ICQ number.', 'text', 12, '', 'regex~[1-9][0-9]{4,9}~i', 0, 1, 'forumprofile', 0, 1, 0, 0, '', '<a class="icq" href="http://www.icq.com/whitepages/about_me.php?uin={INPUT}" target="_blank" title="ICQ - {INPUT}"><img src="http://status.icq.com/online.gif?img=5&icq={INPUT}" alt="ICQ - {INPUT}" width="18" height="18"></a>', 1),
		array('cust_skye', 'Skype', 'This is your Skype account name', 'text', 32, '', 'regex~[a-z][0-9a-z.-]{1,31}~i', 0, 1, 'forumprofile', 0, 1, 0, 0, '', '<a href="skype:{INPUT}?call"><img src="http://mystatus.skype.com/smallicon/{INPUT}" alt="Skype - {INPUT}" title="Skype - {INPUT}" /></a>', 1),
		array('cust_fbook', 'Facebook Profile', 'Enter your Facebook username.', 'text', 50, '', 'regex~[a-z][0-9a-z.-]{1,31}~i', 0, 1, 'forumprofile', 0, 1, 0, 0, '', '<a target="_blank" href="https://www.facebook.com/{INPUT}"><img src="{DEFAULT_IMAGES_URL}/profile/facebook.png" alt="{INPUT}" /></a>', 1),
		array('cust_twitt', 'Twitter Profile', 'Enter your Twitter username.', 'text', 50, '', 'regex~[a-z][0-9a-z.-]{1,31}~i', 0, 1, 'forumprofile', 0, 1, 0, 0, '', '<a target="_blank" href="https://www.twitter.com/{INPUT}"><img src="{DEFAULT_IMAGES_URL}/profile/twitter.png" alt="{INPUT}" /></a>', 1),
		array('cust_linked', 'LinkedIn Profile', 'Set your LinkedIn Public profile link. You must set a Custom public url for this to work.', 'text', 255, '', 'nohtml', 0, 1, 'forumprofile', 0, 1, 0, 0, '', '<a target={INPUT}"><img src="{DEFAULT_IMAGES_URL}/profile/linkedin.png" alt="LinkedIn profile" /></a>', 1),
		array('cust_gplus', 'Google+ Profile', 'This is your Google+ profile url.', 'text', 255, '', 'nohtml', 0, 1, 'forumprofile', 0, 1, 0, 0, '', '<a target="_blank" href="{INPUT}"><img src="{DEFAULT_IMAGES_URL}/profile/gplus.png" alt="G+ profile" /></a>', 1),
		array('cust_yim', 'Yahoo! Messenger', 'This is your Yahoo! Instant Messenger nickname.', 'text', 50, '', 'email', 0, 1, 'forumprofile', 0, 1, 0, 0, '', '<a class="yim" href="http://edit.yahoo.com/config/send_webmesg?.target={INPUT}" target="_blank" title="Yahoo! Messenger - {INPUT}"><img src="http://opi.yahoo.com/online?m=g&t=0&u={INPUT}" alt="Yahoo! Messenger - {INPUT}"></a>', 1)
	),
		array('id_field')
);
---}
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
		$db->insert('',
			'{db_prefix}custom_fields_data',
			arrau('id_member' => 'int', 'variable' => 'string', 'value' => 'string'),
			$inserts,
			array('id_member', 'variable')
		);
}
---}
---#

---# Drop the old cols
---{
$db_table->db_remove_column('{db_prefix}members', 'icq');
$db_table->db_remove_column('{db_prefix}members', 'aim');
$db_table->db_remove_column('{db_prefix}members', 'yim');
$db_table->db_remove_column('{db_prefix}members', 'msn');
---}
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
		$db->insert('ignore',
			'{db_prefix}permissions',
			array('id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
			$inserts,
			array('id_group', 'permission')
		);
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
---{
$db_table->db_create_table('{db_prefix}follow_ups',
	array(
		array(
			'name' => 'follow_up',
			'type' => 'int',
			'unsigned' => true,
			'size' => 10,
			'default' => 0
		),
		array(
			'name' => 'derived_from',
			'type' => 'int',
			'unsigned' => true,
			'size' => 10,
			'default' => 0
		)
	),
	array(
		array(
			'name' => array('follow_up'),
			'columns' => array('follow_up', 'derived_from'),
			'type' => 'primary'
		)
	),
	array(),
	'ignore'
);
---}
---#

/******************************************************************************/
--- Updating antispam questions.
/******************************************************************************/

---# Creating antispam questions table...
---{
$db_table->db_create_table('{db_prefix}antispam_questions',
	array(
		array(
			'name' => 'id_question',
			'type' => 'tinyint',
			'unsigned' => true,
			'size' => 4,
			'auto' => true
		),
		array(
			'name' => 'question',
			'type' => 'text',
		),
		array(
			'name' => 'answer',
			'type' => 'text',
		),
		array(
			'name' => 'language',
			'type' => 'varchar',
			'size' => 50,
			'default' => ''
		)
	),
	array(
		array(
			'name' => array('id_question'),
			'columns' => array('id_question'),
			'type' => 'primary'
		),
		array(
			'name' => array('language'),
			'columns' => array('language(30)'),
			'type' => 'key'
		)
	),
	array(),
	'ignore'
);
---}
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
	$inserts = array();
	while ($row = $db->fetch_assoc($request))
	{
		$inserts[] = array(serialize(array($row['answer'])), $row['question'], $language);
		// @todo use $id_comments and move the DELETE out of the loop
		upgrade_query("
			DELETE FROM {$db_prefix}log_comments
			WHERE id_comment = " . $row['id_comment'] . "
			LIMIT 1");
	}
	if (!empty($inserts))
		$db->insert('',
			'{db_prefix}antispam_questions',
			array('answer' => 'string', 'question' => 'string', 'language' => 'string'),
			$inserts,
			array('id_question')
		);
}
---}
---#

/******************************************************************************/
--- Adding support for Maillist
/******************************************************************************/
---# Creating postby_emails table
---{
$db_table->db_create_table('{db_prefix}postby_emails',
	array(
		array(
			'name' => 'id_email',
			'type' => 'varchar',
			'size' => 50,
			'default' => ''
		),
		array(
			'name' => 'time_sent',
			'type' => 'int',
			'size' => 10,
			'default' => 0
		),
		array(
			'name' => 'email_to',
			'type' => 'varchar',
			'size' => 50,
			'default' => ''
		)
	),
	array(
		array(
			'name' => array('id_email'),
			'columns' => array('id_email'),
			'type' => 'primary'
		)
	),
	array(),
	'ignore'
);
---}
---#

---# Creating postby_emails_error table
---{
$db_table->db_create_table('{db_prefix}postby_emails_error',
	array(
		array(
			'name' => 'id_email',
			'type' => 'int',
			'size' => 10,
			'auto' => true
		),
		array(
			'name' => 'error',
			'type' => 'varchar',
			'size' => 255,
			'default' => ''
		),
		array(
			'name' => 'data_id',
			'type' => 'varchar',
			'size' => 255,
			'default' => '0'
		),
		array(
			'name' => 'subject',
			'type' => 'varchar',
			'size' => 255,
			'default' => ''
		),
		array(
			'name' => 'id_message',
			'type' => 'int',
			'size' => 10,
			'default' => 0
		),
		array(
			'name' => 'id_board',
			'type' => 'smallint',
			'size' => 5,
			'default' => 0
		),
		array(
			'name' => 'email_from',
			'type' => 'varchar',
			'size' => 50,
			'default' => ''
		),
		array(
			'name' => 'message_type',
			'type' => 'char',
			'size' => 10,
			'default' => ''
		),
		array(
			'name' => 'message',
			'type' => 'mediumtext'
		)
	),
	array(
		array(
			'name' => array('id_email'),
			'columns' => array('id_email'),
			'type' => 'primary'
		)
	),
	array(),
	'ignore'
);
---}
---#

---# Creating postby_emails_filters table
---{
$db_table->db_create_table('{db_prefix}postby_emails_filters',
	array(
		array(
			'name' => 'id_filter',
			'type' => 'int',
			'size' => 10,
			'auto' => true
		),
		array(
			'name' => 'filter_style',
			'type' => 'char',
			'size' => 5,
			'default' => ''
		),
		array(
			'name' => 'filter_type',
			'type' => 'varchar',
			'size' => 255,
			'default' => ''
		),
		array(
			'name' => 'filter_to',
			'type' => 'varchar',
			'size' => 255,
			'default' => ''
		),
		array(
			'name' => 'filter_from',
			'type' => 'varchar',
			'size' => 255,
			'default' => ''
		),
		array(
			'name' => 'filter_name',
			'type' => 'varchar',
			'size' => 255,
			'default' => ''
		)
	),
	array(
		array(
			'name' => array('id_filter'),
			'columns' => array('id_filter'),
			'type' => 'primary'
		)
	),
	array(),
	'ignore'
);
---}
---#

---# Adding new columns to postby_emails_filters...
---{
$db_table->db_add_column('{db_prefix}postby_emails_filters',
	array(
		'name' => 'filter_order',
		'type' => 'int',
		'size' => 10,
		'default' => 0
	),
	array(),
	'ignore'
);
---}
---#

---# Set the default values so the order is set / maintained
UPDATE {$db_prefix}postby_emails_filters
SET filter_order = 'id_filter';
---#

---# Adding new columns to log_activity...
---{
$db_table->db_add_column('{db_prefix}log_activity',
	array(
		'name' => 'pm',
		'type' => 'smallint',
		'unsigned' => true,
		'size' => 5,
		'default' => 0
	),
	array(),
	'ignore'
);
$db_table->db_add_column('{db_prefix}log_activity',
	array(
		'name' => 'email',
		'type' => 'smallint',
		'unsigned' => true,
		'size' => 5,
		'default' => 0
	),
	array(),
	'ignore'
);
---}
---#

---# Adding new columns to mail_queue...
---{
$db_table->db_add_column('{db_prefix}mail_queue',
	array(
		'name' => 'message_id',
		'type' => 'varchar',
		'size' => 12,
		'default' => 0
	),
	array(),
	'ignore'
);
---}
---#

---# Updating board profiles...
---{
$db->insert('',
	'{db_prefix}board_permissions',
	array('id_group' => 'int', 'id_profile' => 'int', 'permission' => 'string'),
	array(
		array(0, 1, 'postby_email'),
		array(0, 2, 'postby_email')
	),
	array('id_profile', 'id_group')
);
---}
---#

/******************************************************************************/
--- Adding likes support.
/******************************************************************************/

---# Creating likes log table...
---{
$db_table->db_create_table('{db_prefix}log_likes',
	array(
		array(
			'name' => 'action',
			'type' => 'char',
			'size' => 1,
			'default' => '0'
		),
		array(
			'name' => 'id_target',
			'type' => 'mediumint',
			'unsigned' => true,
			'size' => 8,
			'default' => 0
		),
		array(
			'name' => 'id_member',
			'type' => 'mediumint',
			'unsigned' => true,
			'size' => 8,
			'default' => 0
		),
		array(
			'name' => 'log_time',
			'type' => 'int',
			'unsigned' => true,
			'size' => 10,
			'default' => 0
		)
	),
	array(
		array(
			'name' => array('id_target_member'),
			'columns' => array('id_target', 'id_member'),
			'type' => 'primary'
		),
		array(
			'name' => array('log_time'),
			'columns' => array('log_time'),
			'type' => 'key'
		)
	),
	array(),
	'ignore'
);
---}
---#

---# Creating likes message table...
---{
$db_table->db_create_table('{db_prefix}message_likes',
	array(
		array(
			'name' => 'id_member',
			'type' => 'mediumint',
			'unsigned' => true,
			'size' => 8,
			'default' => 0
		),
		array(
			'name' => 'id_msg',
			'type' => 'int',
			'unsigned' => true,
			'size' => 10,
			'default' => 0
		),
		array(
			'name' => 'id_poster',
			'type' => 'mediumint',
			'unsigned' => true,
			'size' => 8,
			'default' => 0
		)
	),
	array(
		array(
			'name' => array('id_msg_member'),
			'columns' => array('id_msg', 'id_member'),
			'type' => 'primary'
		),
		array(
			'name' => array('id_member'),
			'columns' => array('id_member'),
			'type' => 'key'
		),
		array(
			'name' => array('id_poster'),
			'columns' => array('id_poster'),
			'type' => 'key'
		)
	),
	array(),
	'ignore'
);
---}
---#

---# Adding new columns to topics...
---{
$db_table->db_add_column('{db_prefix}topics',
	array(
		'name' => 'num_likes',
		'type' => 'int',
		'unsigned' => true,
		'size' => 10,
		'default' => 0
	),
	array(),
	'ignore'
);
---}
---#

---# Adding new columns to members...
---{
$db_table->db_add_column('{db_prefix}members',
	array(
		'name' => 'likes_given',
		'type' => 'mediumint',
		'unsigned' => true,
		'size' => 5,
		'default' => 0
	),
	array(),
	'ignore'
);
$db_table->db_add_column('{db_prefix}members',
	array(
		'name' => 'likes_received',
		'type' => 'mediumint',
		'unsigned' => true,
		'size' => 5,
		'default' => 0
	),
	array(),
	'ignore'
);
---}
---#

/******************************************************************************/
--- Changing contacting options.
/******************************************************************************/

---# Renaming column that stores the PM receiving setting...
---{
$db_table->db_change_column('{db_prefix}members',
	'pm_receive_from',
	array(
		'name' => 'receive_from',
		'type' => 'tinyint',
		'unsigned' => true,
		'size' => 4,
		'default' => 1
	)
	array()
);
---}
---#

/******************************************************************************/
--- Adding mentions support.
/******************************************************************************/

---# Creating notifications log table...
---{
$db_table->db_create_table('{db_prefix}log_mentions',
	array(
		array(
			'name' => 'id_mention',
			'type' => 'int',
			'unsigned' => true,
			'size' => 10,
			'auto' => true
		),
		array(
			'name' => 'id_member',
			'type' => 'mediumint',
			'unsigned' => true,
			'size' => 8,
			'default' => 0
		),
		array(
			'name' => 'id_msg',
			'type' => 'int',
			'unsigned' => true,
			'size' => 10,
			'default' => 0
		),
		array(
			'name' => 'status',
			'type' => 'tinyint',
			'size' => 1,
			'default' => 0
		),
		array(
			'name' => 'id_member_from',
			'type' => 'mediumint',
			'unsigned' => true,
			'size' => 8,
			'default' => 0
		),
		array(
			'name' => 'log_time',
			'type' => 'int',
			'unsigned' => true,
			'size' => 10,
			'default' => 0
		),
		array(
			'name' => 'mention_type',
			'type' => 'varchar',
			'size' => 5,
			'default' => ''
		)
	),
	array(
		array(
			'name' => array('id_mention'),
			'columns' => array('id_mention'),
			'type' => 'primary'
		),
		array(
			'name' => array('id_member_status'),
			'columns' => array('id_member', 'status'),
			'type' => 'key'
		)
	),
	array(),
	'ignore'
);
---}
---#

---# Adding new columns to members...
---{
$db_table->db_add_column('{db_prefix}members',
	array(
		'name' => 'mentions',
		'type' => 'smallint',
		'size' => 5,
		'default' => 0
	),
	array(),
	'ignore'
);
---}
---#

--- Fixing personal messages column name
/******************************************************************************/
---# Adding new columns to log_packages ..
---{
$db_table->db_change_column('{db_prefix}members',
	'instant_messages',
	array(
		'name' => 'personal_messages',
		'type' => 'smallint',
		'size' => 5,
		'default' => 1
	)
	array()
);
---}
---#
