<?php
define('TESTDIR', dirname(__FILE__));

global $testing_db, $db_server, $db_name, $db_user, $db_passwd, $db_prefix;

$db_server = 'localhost';
$db_name = 'hello_world_test';
$db_user = 'postgres';
$db_passwd = '';
$db_prefix = 'elk_';

$testing_db = 'postgresql';

function fix_query_string($string)
{
	$lines = explode("\n", $string);
	$output = '';

	foreach ($lines as $line)
		if (!empty($line[0]) && $line[0] != '#')
			$output .= "\n" . str_replace(array('{$current_time}', '{$sched_task_offset}'), array(time(), '1'), $line);

	echo $output;
	return $output;
}

require_once(TESTDIR . '/setup.php');
