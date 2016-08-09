<?php

if (!defined('ELK') && file_exists(dirname(__FILE__) . '/SSI.php'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('ELK'))
	die('<b>Error:</b> Cannot install - please verify you put this in the same place as ELK\'s index.php.');


$db_table = db_table();

$db_table->db_change_column(
	'{db_prefix}log_online',
	'ip',
	array(
		'type' => 'varchar',
		'size' => '255',
	)
);

updateSettings(array('banLastUpdated' => time()));