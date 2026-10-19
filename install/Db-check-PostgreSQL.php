<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 2
 *
 */

$GLOBALS['databases']['postgresql'] = [
	'name' => 'PostgreSQL',
	'extension' => 'PostgreSQL (PgSQL)',
	'version' => '9.6',
	'supported' => function_exists('pg_connect'),
	'additional_file' => 'install_' . DB_SCRIPT_VERSION . '_postgresql.php',
	'validate_prefix' => static function (&$value) {
		global $txt;

		$value = preg_replace('~[^A-Za-z0-9_$]~', '', $value);

		// Is it reserved?
		if ($value === 'pg_')
		{
			return $txt['error_db_prefix_reserved'];
		}

		// Is the prefix numeric?
		if (preg_match('~^\d~', $value))
		{
			return $txt['error_db_prefix_numeric'];
		}

		return true;
	},
	'require_db_confirm' => true,
];
