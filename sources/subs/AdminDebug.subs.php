<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Get the admin info file from the database
 *
 * @param type $filename
 * @return array
 */
function list_getAdminInfoFile($filename)
{
	global $smcFunc;

	$file = array();
	$request = $smcFunc['db_query']('', '
		SELECT data, filetype
		FROM {db_prefix}admin_info_files
		WHERE filename = {string:current_filename}
		LIMIT 1',
		array(
			'current_filename' => $filename,
		)
	);

	if ($smcFunc['db_num_rows']($request) == 0)
		fatal_lang_error('admin_file_not_found', true, array($filename));

	list ($file['file_data'], $file['filetype']) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $file;
}