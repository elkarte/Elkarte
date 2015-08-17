#!/usr/local/bin/php -q
<?php

/**
 * Handles the replying and posting of messages by email
 *
 * Note the shebang #!/usr/local/bin/php -q needs to point to the installed
 * location of php, this is the typical location but yours may be different
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 dev
 *
 */

// Only do something for a pipe and direct calling
if (!defined('STDIN'))
	return;

// Any output here is not good
error_reporting(0);

// Need SSI to do much
require_once(__DIR__ . '/bootstrap.php');

// No need to ID the server if we fall on our face :)
$_SERVER['SERVER_SOFTWARE'] = '';
$_SERVER['SERVER_NAME'] = '';

// Our mail controller
$controller = new Emailpost_Controller();
$controller->action_pbe_post();

// Always exit as successful
exit(0);