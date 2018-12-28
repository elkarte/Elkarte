<?php

/**
 * Handles the mysql db actions for travis-ci
 *
 * Called by setup-elkarte.sh as part of the install: directive in .travis.yml
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause  (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

define('TESTDIR', dirname(__FILE__));

require_once(TESTDIR . '/setup.php');
require_once(TESTDIR . '/Elk_Testing_mysql.php');

$setup = new Elk_Testing_mysql();
$setup->init();
