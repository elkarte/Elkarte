#!/usr/local/bin/php -q
<?php

/**
 * Handles the creating of new topics by email
 * 
 * Note the shebang #!/usr/local/bin/php -q needs to point to the installed
 * location of php, this is the typical location but yours may be different.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Release Candidate 1
 *
 */

// Only work for a pipe and direct calling
if (!defined('STDIN'))
	return;

// Any output here is not good, it will be bounced as email
error_reporting(0);

// Need SSI to do much
require_once(dirname(__FILE__) . '/SSI.php');

// No need to ID the server if we fall on our face :)
$_SERVER['SERVER_SOFTWARE'] = '';
$_SERVER['SERVER_NAME'] = '';

// Our mail controller
require_once(CONTROLLERDIR . '/Emailpost.controller.php');
$controller = new Emailpost_Controller();
$controller->action_pbe_topic();

// Always exit as successful
exit(0);