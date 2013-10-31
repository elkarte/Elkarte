<?php

define('BOARDDIR', dirname(__FILE__) . '/../..');

require_once(BOARDDIR . '/sources/database/Db-' . $testing_db . '.class.php');

echo 'Find the database' . "\n";
// quick 'n dirty initialization of the right database class.
if ($testing_db == 'mysql')
	$db = Database_MySQL::initiate($db_server, $db_name, $db_user, $db_passwd, $db_prefix);
elseif ($testing_db == 'postgresql')
	$db = Database_PostgreSQL::initiate($db_server, $db_name, $db_user, $db_passwd, $db_prefix);
elseif ($testing_db == 'sqlite')
	$db = Database_SQLite::initiate($db_server, $db_name, $db_user, $db_passwd, $db_prefix);

echo 'Prepare queries' . "\n";flush();die();
$install_query = str_replace('{$db_prefix}', 'elk_', file_get_contents(BOARDDIR . '/install/install_1-0_' . $testing_db . '.sql'));

echo 'Any fix?' . "\n";
if (function_exists('fix_query_string'))
	$install_query = fix_query_string($install_query);

$queries_parts = explode("\n", $install_query);
$query = '';

echo 'Running queries' . "\n";
foreach ($queries_parts as $part)
{
	if (substr($part, -1) == ';')
	{
		echo $query . "\n" . $part . "\n";
		$db->query($query . "\n" . $part);
		$query = '';
	}
	else
	{
		$query .= "\n" . $part;
	}
}

$fh = fopen(BOARDDIR . '/Settings.php', 'a');
fwrite($fh, "\n" . '$test_enabled = 1;');