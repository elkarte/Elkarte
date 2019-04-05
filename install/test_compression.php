<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 */

/**
 * Does the test to check whether compression is supported or not.
 * Called by ?obgz
 */

ob_start('ob_gzhandler');

if (ini_get('session.save_handler') == 'user')
    @ini_set('session.save_handler', 'files');
session_start();

if (!headers_sent())
    echo '<!DOCTYPE html>
<html>
	<head>
		<title>', htmlspecialchars($_GET['pass_string'], ENT_COMPAT, 'UTF-8'), '</title>
	</head>
	<body style="background: #d4d4d4; margin-top: 16%; font-size: 16pt;">
		<strong>', htmlspecialchars($_GET['pass_string'], ENT_COMPAT, 'UTF-8'), '</strong>
	</body>
</html>';
exit;
