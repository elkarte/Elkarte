<?php


function countMessages()
{
	global $smcFunc;

	$result = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}messages',
		array()
	);
	list($messages) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);

	return $messages;
}

function getMembergroups()
{
	global $smcFunc, $txt;

	$result = $smcFunc['db_query']('', '
		SELECT id_group, group_name
		FROM {db_prefix}membergroups',
		array(
		)
	);
	$membergroups = array(
		array(
			'id' => 0,
			'name' => $txt['maintain_members_ungrouped']
		),
	);
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		$membergroups[] = array(
			'id' => $row['id_group'],
			'name' => $row['group_name']
		);
	}
	$smcFunc['db_free_result']($result);

	return $membergroups;
}

function flushLogTables()
{
	global $smcFunc;

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_online');

	// Dump the banning logs.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_banned');

	// Start id_error back at 0 and dump the error log.
	$smcFunc['db_query']('truncate_table', '
		TRUNCATE {db_prefix}log_errors');

	// Clear out the spam log.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_floodcontrol');

	// Clear out the karma actions.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_karma');

	// Last but not least, the search logs!
	$smcFunc['db_query']('truncate_table', '
		TRUNCATE {db_prefix}log_search_topics');

	$smcFunc['db_query']('truncate_table', '
		TRUNCATE {db_prefix}log_search_messages');

	$smcFunc['db_query']('truncate_table', '
		TRUNCATE {db_prefix}log_search_results');
}

function getMessageTableColumns()
{
	global $smcFunc;

	db_extend('packages');
	$colData = $smcFunc['db_list_columns']('{db_prefix}messages', true);

	return $colData;
}

function resizeMessageTableBody($type)
{
	global $smcFunc;

	db_extend('packages');
	$smcFunc['db_change_column']('{db_prefix}messages', 'body', array('type' => $type));
}

function detectExceedingMessages($start, $increment)
{
	global $smcFunc;

	$id_msg_exceeding = array();

	$request = $smcFunc['db_query']('', '
		SELECT /*!40001 SQL_NO_CACHE */ id_msg
		FROM {db_prefix}messages
		WHERE id_msg BETWEEN {int:start} AND {int:start} + {int:increment}
			AND LENGTH(body) > 65535',
		array(
			'start' => $start,
			'increment' => $increment - 1,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$id_msg_exceeding[] = $row['id_msg'];
	$smcFunc['db_free_result']($request);

	return $id_msg_exceeding;
}

function getExceedingMessages($msg)
{
	global $smcFunc, $scripturl;

	$exceeding_messages = array();

	$request = $smcFunc['db_query']('', '
		SELECT id_msg, id_topic, subject
		FROM {db_prefix}messages
		WHERE id_msg IN ({array_int:messages})',
		array(
			'messages' => $msg,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$exceeding_messages[] = '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '">' . $row['subject'] . '</a>';
	$smcFunc['db_free_result']($request);

	return $exceeding_messages;
}

function getOurTables()
{
	global $smcFunc, $db_prefix;

	$tables = array();

	db_extend();
	
	// Only optimize the tables related to this installation, not all the tables in the db
	$real_prefix = preg_match('~^(`?)(.+?)\\1\\.(.*?)$~', $db_prefix, $match) === 1 ? $match[3] : $db_prefix;

	// Get a list of tables, as well as how many there are.
	$temp_tables = $smcFunc['db_list_tables'](false, $real_prefix . '%');
	foreach ($temp_tables as $table)
			$tables[] = array('table_name' => $table);

	return $tables;
}

function optimizeTable($tablename)
{
	global $smcFunc;
	
	db_extend();
	$smcFunc['db_optimize_table']($tablename);
}
