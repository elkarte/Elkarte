<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0.2
 *
 */

// Version Constants
const CURRENT_VERSION = '1.0.2';
const CURRENT_LANG_VERSION = '1.0';
const DB_SCRIPT_VERSION = '1-1';
const REQUIRED_PHP_VERSION = '5.3.3';

// String constants
const SITE_SOFTWARE = 'http://www.elkarte.net';

function getUpgradeFiles()
{
	global $db_type;

	return array(
		array('upgrade_' . DB_SCRIPT_VERSION . '.php', '1.1', CURRENT_VERSION),
		array('upgrade_' . DB_SCRIPT_VERSION . '_' . $db_type . '.php', '1.1', CURRENT_VERSION),
	);
}