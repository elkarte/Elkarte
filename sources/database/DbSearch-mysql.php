<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 * This class handles database search. (MySQL)
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

class DbSearch_MySQL
{
	/**
	 *  Initialize. (if necessary)
	 */
	function initialize()
	{
		global $smcFunc;

		if (!isset($smcFunc['db_search_query']) || $smcFunc['db_search_query'] != 'elk_db_query')
			$smcFunc += array(
				'db_search_query' => 'search_query',
				'db_search_support' => 'search_support',
				'db_create_word_search' => 'create_word_search',
				'db_support_ignore' => true,
			);
	}

	/**
	 * Execute the appropriate query for the search.
	 *
	 * @param string $identifier
	 * @param string $db_string
	 * @param array $db_values
	 * @param resource $connection
	 */
	function search_query($identifier, $db_string, $db_values = array(), $connection = null)
	{
		global $db;

		// Simply delegate to the database adapter method.
		return $db->query($identifier, $db_string, $db_values, $connection);
	}

	/**
	 * This method will tell you whether this database type supports this search type.
	 *
	 * @param string $search_type
	 */
	function search_support($search_type)
	{
		$supported_types = array('fulltext');

		return in_array($search_type, $supported_types);
	}

	/**
	 * Method for the custom word index table.
	 *
	 * @param $size
	 */
	function create_word_search($size)
	{
		global $smcFunc;

		if ($size == 'small')
			$size = 'smallint(5)';
		elseif ($size == 'medium')
			$size = 'mediumint(8)';
		else
			$size = 'int(10)';

		$smcFunc['db_query']('', '
			CREATE TABLE {db_prefix}log_search_words (
				id_word {raw:size} unsigned NOT NULL default {string:string_zero},
				id_msg int(10) unsigned NOT NULL default {string:string_zero},
				PRIMARY KEY (id_word, id_msg)
			) ENGINE=InnoDB',
			array(
				'string_zero' => '0',
				'size' => $size,
			)
		);
	}

	/**
	 * Returns whether the database system supports ignore.
	 *
	 * @return bool
	 */
	function support_ignore()
	{
		return true;
	}
}