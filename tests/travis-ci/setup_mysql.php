<?php

define('TESTDIR', dirname(__FILE__));

global $testing_db, $db_server, $db_name, $db_user, $db_passwd, $db_prefix, $db_options;

$db_server = 'localhost';
$db_name = 'hello_world_test';
$db_user = 'root';
$db_passwd = '';
$db_prefix = 'elk_';
$testing_db = 'mysql';

require_once(TESTDIR . '/setup.php');
