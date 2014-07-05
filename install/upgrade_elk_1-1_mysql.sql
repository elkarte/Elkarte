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
