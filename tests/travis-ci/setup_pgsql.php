<?php
define('TESTDIR', dirname(__FILE__));

global $testing_db, $db_dsn, $db_user;

$db_dsn = 'pgsql:dbname=hello_world_test;host=localhost';
$db_user = 'postgres';
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
