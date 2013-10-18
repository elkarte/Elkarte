<?php

define('TESTDIR', dirname(__FILE__));

global $testing_db, $db_dsn, $db_user;

$db_dsn = 'mysql:dbname=hello_world_test;host=localhost';
$db_user = 'root';
$testing_db = 'mysql';

require_once(TESTDIR . '/setup.php');
