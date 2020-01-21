#!/usr/bin/php -q
<?php

/**
 * Handles the replying and posting of messages by email
 *
 * Note the shebang (#!) needs to point to the installed location of php on your
 * system.  If you have shell access running "which php" should return the correct
 * path to use.
 *
 * For example
 * - Ubuntu and Debian would normally be #!/usr/bin/php -q
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

// Only do something for a pipe and direct calling
use ElkArte\Controller\Emailpost;
use ElkArte\EventManager;
use ElkArte\User;

if (!defined('STDIN'))
{
	return;
}

// Any output here is not good
error_reporting(0);

global $ssi_guest_access;

// Need to bootstrap to do much
require_once(__DIR__ . '/bootstrap.php');
$ssi_guest_access = true;
new Bootstrap(true);

// No need to ID the server if we fall on our face :)
$_SERVER['SERVER_SOFTWARE'] = '';
$_SERVER['SERVER_NAME'] = '';

// Our mail controller
$controller = new Emailpost(new EventManager());
$controller->setUser(User::$info);
$controller->action_pbe_post();

// Always exit as successful
exit(0);
