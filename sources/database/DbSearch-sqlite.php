<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 * This class handles database search. (SQLite)
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

class DbSearch_SQLite
{
	/**
	 * Initialize. (if necessary)
	 */
	function initialize()
	{
		// initialize or let it go :P
	}

	/**
	 * This function will tell you whether this database type supports this search type.
	 *
	 * @param string $search_type
	 */
	function search_support($search_type)
	{
		$supported_types = array('custom');

		return in_array($search_type, $supported_types);
	}

	/**
	 * Compute and return the result of a query for the search operation.
	 *
	 * @param string $identifier
	 * @param string $db_string
	 * @param array $db_values default array()
	 * @param resource $connection
	 */
	function search_query($identifier, $db_string, $db_values = array(), $connection = null)
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
			// For eventual multi-insert, skip errors.
			$db_values['db_error_skip'] = true;
		}

		$return = $smcFunc['db_query']('', $db_string,
			$db_values, $connection
		);

		return $return;
	}

	/**
	 * Make a custom word index.
	 *
	 * @param $size
	 */
	function create_word_search($size)
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
}