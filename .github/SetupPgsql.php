<?php

/**
 * Handles the postgresql actions
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

require_once(TESTDIR . '/SetupDbUtil.php');
require_once(TESTDIR . '/ElkTestingPsql.php');

$setup = new ElkTestingPsql();
return $setup->init();
