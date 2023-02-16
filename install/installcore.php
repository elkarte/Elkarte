<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

// Version Constants
const CURRENT_VERSION = '2.0 dev';
const CURRENT_LANG_VERSION = '2.0';
const DB_SCRIPT_VERSION = '2-0';
const REQUIRED_PHP_VERSION = '7.2.0';

// String constants
const SITE_SOFTWARE = 'https://www.elkarte.net';

/**
 * @return array
 */
function getUpgradeFiles()
{
	global $db_type;

	// Name, less than version, insert_on_complete.
	return array(
		array('upgrade_1-0.php', '1.0', '1.0'),
		array('upgrade_1-0_' . $db_type . '.php', '1.0', '1.0'),
		array('upgrade_1-1.php', '1.2', '1.1'),
		array('upgrade_1-1_' . $db_type . '.php', '1.2', '1.1'),
		array('upgrade_2-0.php', '2.1', CURRENT_VERSION),
		array('upgrade_2-0_' . $db_type . '.php', '2.1', CURRENT_VERSION),
	);
}
