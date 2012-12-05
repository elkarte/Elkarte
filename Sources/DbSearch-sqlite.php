<?php

/**
 * @name      Dialogo Forum
 * @copyright Dialogo Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Alpha
 *
 * This file contains database functions specific to search related activity.
 *
 */

if (!defined('DIALOGO'))
	die('Hacking attempt...');

/**
 *  Add the file functions to the $smcFunc array.
 */
function db_search_init()
{
	global $smcFunc;

	if (!isset($smcFunc['db_search_query']) || $smcFunc['db_search_query'] != 'smf_db_search_query')
		$smcFunc += array(
			'db_search_query' => 'smf_db_search_query',
			'db_search_support' => 'smf_db_search_support',
			'db_create_word_search' => 'smf_db_create_word_search',
			'db_support_ignore' => false,
		);
}

/**
 * This function will tell you whether this database type supports this search type.
 *
 * @param string $search_type
 */
function smf_db_search_support($search_type)
{
	$supported_types = array('custom');

	return in_array($search_type, $supported_types);
}

/**
 * Returns the correct query for this search type.
 *
 * @param string $identifier
 * @param string $db_string
 * @param array $db_values default array()
 * @param resource $connection
 */
function smf_db_search_query($identifier, $db_string, $db_values = array(), $connection = null)
{
	global $smcFunc;

	$replacements = array(
		'create_tmp_log_search_topics' => array(
			'~mediumint\(\d\)~i' => 'int',
			'~TYPE=HEAP~i' => '',
		),
		'create_tmp_log_search_messages' => array(
			'~mediumint\(\d\)~i' => 'int',
			'~TYPE=HEAP~i' => '',
		),
	);

	if (isset($replacements[$identifier]))
		$db_string = preg_replace(array_keys($replacements[$identifier]), array_values($replacements[$identifier]), $db_string);
	elseif (preg_match('~^\s*INSERT\sIGNORE~i', $db_string) != 0)
	{
		$db_string = preg_replace('~^\s*INSERT\sIGNORE~i', 'INSERT', $db_string);
		// Don't error on multi-insert.
		$db_values['db_error_skip'] = true;
	}

	$return = $smcFunc['db_query']('', $db_string,
		$db_values, $connection
	);

	return $return;
}

/**
 * Highly specific function, to create the custom word index table.
 *
 * @param $size
 */
function smf_db_create_word_search($size)
{
	global $smcFunc;

	$size = 'int';

	$smcFunc['db_query']('', '
		CREATE TABLE {db_prefix}log_search_words (
			id_word {raw:size} NOT NULL default {string:string_zero},
			id_msg int(10) NOT NULL default {string:string_zero},
			PRIMARY KEY (id_word, id_msg)
		)',
		array(
			'size' => $size,
			'string_zero' => '0',
		)
	);
}
