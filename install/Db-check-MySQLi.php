<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

$GLOBALS['databases']['mysqli'] = [
	'name' => 'MySQL',
	'extension' => 'MySQL Improved (MySQLi)',
	'version' => '5.7.0',
	'version_check' => static fn($db_connection) => min(
		mysqli_get_server_info($db_connection),
		mysqli_get_client_info()
	),
	'supported' => function_exists('mysqli_connect'),
	'additional_file' => '',
	'default_user' => 'mysqli.default_user',
	'default_password' => 'mysqli.default_password',
	'default_host' => 'mysqli.default_host',
	'default_port' => 'mysqli.default_port',
	'test_collation' => true,
	'alter_support' => true,
	'validate_prefix' => static function (&$value) {
		$value = preg_replace('~[^A-Za-z0-9_$]~', '', $value);
		return true;
	},
	'require_db_confirm' => true,
];
