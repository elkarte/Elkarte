<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1
 *
 */

// Version Constants
const CURRENT_VERSION = '1.1';
const CURRENT_LANG_VERSION = '1.1';
const DB_SCRIPT_VERSION = '1-1';
const REQUIRED_PHP_VERSION = '5.5.0';

// String constants
const SITE_SOFTWARE = 'http://www.elkarte.net';

function getUpgradeFiles()
{
	global $db_type;

	return array(
		array('upgrade_1-0.php', '1.0', '1.0'),
		array('upgrade_1-0_' . $db_type . '.php', '1.0', '1.0'),
		array('upgrade_1-1.php', '1.1', CURRENT_VERSION),
		array('upgrade_1-1_' . $db_type . '.php', '1.1 RC 2', CURRENT_VERSION),
	);
}