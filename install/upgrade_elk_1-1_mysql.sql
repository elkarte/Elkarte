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
		'name' => '2fa_secret',
		'type' => 'varchar',
		'size' => 16,
		'default' => '',
	),
	array(),
	'ignore'
);
$db_table->db_add_column('{db_prefix}members',
	array(
		'name' => 'enable_2fa',
		'type' => 'tinyint',
		'size' => 1,
		'default' => 0,
	),
	array(),
	'ignore'
);

---}
---#