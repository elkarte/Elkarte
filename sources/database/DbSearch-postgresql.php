<?php

/**
 * This class handles database search. (PostgreSQL)
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 beta 4
 *
 */

/**
 * PostgreSQL implementation of DbSearch
 */
class DbSearch_PostgreSQL implements DbSearch
{
	/**
	 * This instance of the search
	 * @var DbSearch_PostgreSQL
	 */
	private static $_search = null;

	/**
	 * This function will tell you whether this database type supports this search type.
	 *
	 * @param string $search_type
	 */
	public function search_support($search_type)
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
	 * @param mixed[] $db_values default array()
	 * @param resource|null $connection
	 */
	public function search_query($identifier, $db_string, $db_values = array(), $connection = null)
	{
		$db = database();

		$replacements = array(
			'create_tmp_log_search_topics' => array(
				'~mediumint\(\d\)~i' => 'int',
				'~unsigned~i' => '',
				'~ENGINE=MEMORY~i' => '',
			),
			'create_tmp_log_search_messages' => array(
				'~mediumint\(\d\)~i' => 'int',
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
			$db->skip_next_error();
		}

		// @deprecated since 1.1 - temporary measure until a proper skip_error is implemented
		if (!empty($db_values['db_error_skip']))
		{
			$db->skip_next_error();
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
		$db_table = db_table();

		$table_info = array();

		// In order to report the sizes correctly we need to perform vacuum (optimize) on the tables we will be using.
		$db_table->optimize('{db_prefix}messages');
		if ($db_table->table_exists('{db_prefix}log_search_words'))
			$db_table->optimize('{db_prefix}log_search_words');

		// PostGreSql has some hidden sizes.
		$request = $db->query('', '
			SELECT relname, relpages * 8 *1024 AS "KB" FROM pg_class
			WHERE relname = {string:messages} OR relname = {string:log_search_words}
			ORDER BY relpages DESC',
			array(
				'messages' => $db_prefix . 'messages',
				'log_search_words' => $db_prefix . 'log_search_words',
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
				elseif ($row['relname'] == $db_prefix . 'log_search_words')
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
	public function create_word_search($size)
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