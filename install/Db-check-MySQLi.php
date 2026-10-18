<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

$GLOBALS['databases']['mysqli'] = [
	'name' => 'MySQL',
	'extension' => 'MySQL Improved (MySQLi)',
	'version' => '5.7.0',
	'supported' => function_exists('mysqli_connect'),
	'additional_file' => '',
	'alter_support' => true,
	'validate_prefix' => static function (&$value) {
		$value = preg_replace('~[^A-Za-z0-9_$]~', '', $value);
		return true;
	},
	'require_db_confirm' => true,
];
