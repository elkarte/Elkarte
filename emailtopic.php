#!/usr/bin/php -q
<?php

/**
 * Handles the creating of new topics by email
 *
 * Note the shebang (#!) needs to point to the installed location of php on your
 * system.  If you have shell access running "which php" should return the correct
 * path to use.
 *
 * For example
 * - Ubuntu and Debian would normally be #!/usr/bin/php -q
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0-dev
 *
 */

// Only work for a pipe and direct calling
if (!defined('STDIN'))
	return;

// Any output here is not good, it will be bounced as email
error_reporting(0);

// Need to bootstrap the system to do much
require_once(dirname(__FILE__) . '/bootstrap.php');
new Bootstrap();

// No need to ID the server if we fall on our face :)
$_SERVER['SERVER_SOFTWARE'] = '';
$_SERVER['SERVER_NAME'] = '';

// Our mail controller
$controller = new Emailpost_Controller(new Event_manager());
$controller->action_pbe_topic();

// Always exit as successful
exit(0);