/* ATTENTION: You don't need to run or use this file! The upgrade.php script does everything for you! */

/******************************************************************************/
--- Deprecating admin_info_file table...
/******************************************************************************/

---# Remove any old file left and check if the table is empty...
---{
foreach (array('current-version.js', 'detailed-version.js', 'latest-news.js', 'latest-smileys.js', 'latest-versions.txt') as $file)
{
	$db->query('', '
		DELETE FROM {db_prefix}admin_info_file
		WHERE file = {string:current_file}',
		array(
			'current_file' => $file
		)
	);
}
$request = $db->query('', '
	SELECT COUNT(*)
	FROM {db_prefix}admin_info_file',
	array()
);

// Drop it only if it is empty
if ($db->num_rows($request) == 0)
	$db_table->db_drop_table('{db_prefix}admin_info_file');

---}
---#

---# Adding new columns to members table...
---{
$db_table->db_add_column('{db_prefix}members',
	array(
		'name' => 'otp_secret',
		'type' => 'varchar',
		'size' => 16,
		'default' => '',
	),
	array(),
	'ignore'
);
$db_table->db_add_column('{db_prefix}members',
	array(
		'name' => 'enable_otp',
		'type' => 'tinyint',
		'size' => 1,
		'default' => 0,
	),
	array(),
	'ignore'
);

---}
---#

/******************************************************************************/
--- Adapt mentions...
/******************************************************************************/

---# Separate visibility from accessibility...
---{
$db_table->db_add_column('{db_prefix}log_mentions',
	array(
		'name' => 'is_accessible',
		'type' => 'tinyint',
		'size' => 1,
		'default' => 0
	)
);

$db_table->db_change_column('{db_prefix}log_mentions',
	'mention_type',
	array(
		'type' => 'varchar',
		'size' => 12,
		'default' => ''
	)
);

$db->query('', '
	UPDATE {db_prefix}log_mentions
	SET is_accessible = CASE WHEN status < 0 THEN 0 ELSE 1 END',
	array()
);

$db->query('', '
	UPDATE {db_prefix}log_mentions
	SET status = -(status + 1)
	WHERE status < 0',
	array()
);

$db->query('', '
	UPDATE {db_prefix}log_mentions
	SET mention_type = mentionmem
	WHERE mention_type = men',
	array()
);

$db->query('', '
	UPDATE {db_prefix}log_mentions
	SET mention_type = likemsg
	WHERE mention_type = like',
	array()
);

$db->query('', '
	UPDATE {db_prefix}log_mentions
	SET mention_type = rlikemsg
	WHERE mention_type = rlike',
	array()
);

$enabled_mentions = !empty($modSettings['enabled_mentions']) ? explode(',', $modSettings['enabled_mentions']) : array();
$known_settings = array(
	'mentions_enabled' => 'mentionmem',
	'likes_enabled' => 'likemsg',
	'mentions_dont_notify_rlike' => 'rlikemsg',
	'mentions_buddy' => 'buddy',
);
foreach ($known_settings as $setting => $toggle)
{
	if (!empty($modSettings[$setting]))
		$enabled_mentions[] = $toggle;
	else
		$enabled_mentions = array_diff($enabled_mentions, array($toggle));
}
updateSettings(array('enabled_mentions' => implode(',', $enabled_mentions)));
---}
---#

---# Make mentions generic and not message-centric...
---{
$db_table->db_change_column('{db_prefix}log_mentions', 'id_msg',
	array(
		'name' => 'id_target',
	)
);
---}
---#

---# Introducing modules...
---{
if (!empty($modSettings['attachmentEnable']))
{
	enableModules('attachments', array('post'));
}
if (!empty($modSettings['cal_enabled']))
{
	enableModules('calendar', array('post', 'boardindex'));
	Hooks::get()->enableIntegration('Calendar_Integrate');
}
if (!empty($modSettings['drafts_enabled']))
{
	enableModules('drafts', array('post', 'display', 'profile', 'personalmessage'));
	Hooks::get()->enableIntegration('Drafts_Integrate');
}
if (!empty($modSettings['enabled_mentions']))
{
	enableModules('mentions', 'post', 'display');
}
enableModules('poll', array('display'));
---}
---#

---# Introducing notifications...
---{
$db_table->db_create_table('{db_prefix}pending_notifications',
	array(
		array('name' => 'notification_type', 'type' => 'varchar', 'size' => 10),
		array('name' => 'id_member',         'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
		array('name' => 'log_time',          'type' => 'int', 'size' => 10, 'default' => 0),
		array('name' => 'frequency',         'type' => 'varchar', 'size' => 1, 'default' => ''),
		array('name' => 'snippet',           'type' => 'text'),
	),
	array(
		array('name' => 'types_member', 'columns' => array('notification_type', 'id_member'), 'type' => 'unique'),
	),
	array(),
	'ignore'
);

$db_table->db_create_table('{db_prefix}notifications_pref',
	array(
		array('name' => 'id_member',          'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
		array('name' => 'notification_level', 'type' => 'tinyint', 'size' => 1, 'default' => 1),
		array('name' => 'mention_type',       'type' => 'varchar', 'size' => 12, 'default' => ''),
	),
	array(
		array('name' => 'mention_member', 'columns' => array('id_member', 'mention_type'), 'type' => 'unique'),
	),
	array(),
	'ignore'
);
---}
---#