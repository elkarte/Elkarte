<?php

/**
 * This file is called before PHPUnit runs any tests.  Its purpose is
 * to initiate enough functions so the testcases can run with minimal
 * setup needs.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

use ElkArte\ext\Composer\Autoload\ClassLoader;
use PHPUnit\Extensions\Selenium2TestCase;

define('IGNORE_INSTALL_DIR', 1);
$mySource = './sources';
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

// A few files we cannot live without and will not autoload
require_once($mySource . '/Subs.php');
require_once($mySource . '/Load.php');
require_once($mySource . '/ext/ClassLoader.php');
require_once('./tests/headless/ElkArteWebSupport.php');

$loader = new ClassLoader();
$loader->setPsr4('ElkArte\\', $mySource . '/ElkArte');
$loader->register();

// If we are running functional tests as well
Selenium2TestCase::shareSession(true);
