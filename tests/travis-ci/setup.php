<?php

define('BOARDDIR', dirname(__FILE__) . '/../..');

$install_query = str_replace('{$db_prefix}', 'elk_', file_get_contents(BOARDDIR . '/install/install_1-0_' . $testing_db . '.sql'));

if (function_exists('fix_query_string'))
	$install_query = fix_query_string($install_query);

$pdo = new PDO($db_dsn, $db_user, '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->query($install_query);

$fh = fopen(BOARDDIR . '/Settings.php', 'a');
fwrite($fh, "\n" . '$test_enabled = 1;');