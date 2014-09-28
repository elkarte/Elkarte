<?php

if (!defined('SMF') && file_exists(dirname(__FILE__) . '/SSI.php'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('ELK'))
	die('<b>Error:</b> Cannot install - please verify you put this in the same place as ELK\'s index.php.');


$db_table = db_table();

$db_table->db_add_column(
	'{db_prefix}message_likes',
	array(
		'name' => 'like_timestamp',
		'type' => 'int',
		'size' => 10,
		'unsigned' => true,
		'default' => 0
	)
);

$db_table->db_change_column(
	'{db_prefix}postby_emails_error',
	'filter_style',
	array(
		'type' => 'char',
		'size' => '6',
	)
);

$db_table->db_change_column(
	'{db_prefix}mail_queue',
	'message_id',
	array(
		'type' => 'varchar',
		'size' => '12',
	)
);

if (ELK == 'SSI')
	echo 'Database changes were carried out successfully.';