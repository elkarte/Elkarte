<?php

/**
 * Handles the mysql and mariadb install actions
 *
 * Called by setup-database.sh as part of the install
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1
 *
 */

define('TESTDIR', dirname(__FILE__));

// Call in the support
require_once(TESTDIR . '/SetupDbUtil.php');
require_once(TESTDIR . '/ElkTestingMysql.php');

// Lets install the db
$setup = new ElkTestingMysql();
return $setup->init();
