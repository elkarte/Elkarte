<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

$GLOBALS['databases']['postgresql'] = [
	'name' => 'PostgreSQL',
	'extension' => 'PostgreSQL (PgSQL)',
	'version' => '9.5',
	'function_check' => 'pg_connect',
	'version_check' => static function ($db_connection) {
		$request = pg_query('SELECT version()');
		[$version] = pg_fetch_row($request);
		[$pgl, $version] = explode(" ", $version);
		return $version;
	},
	'supported' => function_exists('pg_connect'),
	'additional_file' => 'install_' . DB_SCRIPT_VERSION . '_postgresql.php',
	'test_collation' => false,
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
