<?php

/**
 * This class handles database search. (PostgreSQL)
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Beta 2
 *
 */

if (!defined('ELK'))
	die('No access...');

// It should be already defined in Db-type.class.php, but better have it twice
if (!defined('DB_TYPE'))
	define('DB_TYPE', 'PostgreSQL');

/**
 * PostgreSQL implementation of DbSearch
 */
class DbSearch_PostgreSQL implements DbSearch
{
	/**
	 * This instance of the search
	 * @var instance
	 */
	private static $_search = null;

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
	 * Compute and execute the correct query for search.
	 * Returns the result of executing the query.
	 *
	 * @param string $identifier
	 * @param string $db_string
	 * @param array $db_values default array()
	 * @param resource $connection
	 */
	function search_query($identifier, $db_string, $db_values = array(), $connection = null)
	{
		$db = database();

		$replacements = array(
			'create_tmp_log_search_topics' => array(
				'~mediumint\(\d\)~i' => 'int',
				'~unsigned~i' => '',
				'~ENGINE=MEMORY~i' => '',
			),
			'create_tmp_log_search_messages' => array(
				'~mediumint\(\d\)' => 'int',
				'~unsigned~i' => '',
				'~ENGINE=MEMORY~i' => '',
			),
			'drop_tmp_log_search_topics' => array(
				'~IF\sEXISTS~i' => '',
			),
			'drop_tmp_log_search_messages' => array(
				'~IF\sEXISTS~i' => '',
			),
			'insert_into_log_messages_fulltext' => array(
				'~LIKE~i' => 'iLIKE',
				'~NOT\sLIKE~i' => '~NOT iLIKE',
				'~NOT\sRLIKE~i' => '!~*',
				'~RLIKE~i' => '~*',
			),
			'insert_log_search_results_subject' => array(
				'~LIKE~i' => 'iLIKE',
				'~NOT\sLIKE~i' => 'NOT iLIKE',
				'~NOT\sRLIKE~i' => '!~*',
				'~RLIKE~i' => '~*',
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

		$return = $db->query('', $db_string,
			$db_values, $connection
		);

		return $return;
	}

	/**
	 * Returns some basic info about the {db_prefix}messages table
	 * Used in ManageSearch.controller.php in the page to select the index method
	 */
	public function membersTableInfo()
	{
		global $db_prefix, $txt;

		$db = database();

		$table_info = array();

		// In order to report the sizes correctly we need to perform vacuum (optimize) on the tables we will be using.
		$temp_tables = $db->db_list_tables();
		foreach ($temp_tables as $table)
			if ($table == $db_prefix. 'messages' || $table == $db_prefix. 'log_search_words')
				$db->db_optimize_table($table);

		// PostGreSql has some hidden sizes.
		$request = $db->query('', '
			SELECT relname, relpages * 8 *1024 AS "KB" FROM pg_class
			WHERE relname = {string:messages} OR relname = {string:log_search_words}
			ORDER BY relpages DESC',
			array(
				'messages' => $db_prefix. 'messages',
				'log_search_words' => $db_prefix. 'log_search_words',
			)
		);

		if ($request !== false && $db->num_rows($request) > 0)
		{
			while ($row = $db->fetch_assoc($request))
			{
				if ($row['relname'] == $db_prefix . 'messages')
				{
					$table_info['data_length'] = (int) $row['KB'];
					$table_info['index_length'] = (int) $row['KB'];

					// Doesn't support fulltext
					$table_info['fulltext_length'] = $txt['not_applicable'];
				}
				elseif ($row['relname'] == $db_prefix. 'log_search_words')
				{
					$table_info['index_length'] = (int) $row['KB'];
					$table_info['custom_index_length'] = (int) $row['KB'];
				}
			}
			$db->free_result($request);
		}
		else
			// Didn't work for some reason...
			$table_info = array(
				'data_length' => $txt['not_applicable'],
				'index_length' => $txt['not_applicable'],
				'fulltext_length' => $txt['not_applicable'],
				'custom_index_length' => $txt['not_applicable'],
			);

		return $table_info;
	}

	/**
	 * Make a custom word index.
	 *
	 * @param string $size
	 */
	function create_word_search($size)
	{
		$db = database();

		$size = 'int';

		$db->query('', '
			CREATE TABLE {db_prefix}log_search_words (
				id_word {raw:size} NOT NULL default {string:string_zero},
				id_msg int NOT NULL default {string:string_zero},
				PRIMARY KEY (id_word, id_msg)
			)',
			array(
				'size' => $size,
				'string_zero' => '0',
			)
		);
	}

	/**
	 * Static method that allows to retrieve or create an instance of this class.
	 */
	public static function db_search()
	{
		if (is_null(self::$_search))
		{
			self::$_search = new self();
		}
		return self::$_search;
	}
}