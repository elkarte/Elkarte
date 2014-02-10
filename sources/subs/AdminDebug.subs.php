<?php

/**
 * Functions to support debug controller
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Beta 2
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Get the admin info file from the database
 *
 * @param string $filename
 *
 * @return array
 */
function adminInfoFile($filename)
{
	$db = database();

	$file = array();
	$request = $db->query('', '
		SELECT data, filetype
		FROM {db_prefix}admin_info_files
		WHERE filename = {string:current_filename}
		LIMIT 1',
		array(
			'current_filename' => $filename,
		)
	);
	if ($db->num_rows($request) == 0)
		fatal_lang_error('admin_file_not_found', true, array($filename));
	list ($file['file_data'], $file['filetype']) = $db->fetch_row($request);
	$db->free_result($request);

	return $file;
}