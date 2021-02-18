<?php

/**
 * This class handles database search. (PostgreSQL)
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Database\Postgresql;

use ElkArte\Database\AbstractSearch;

/**
 * PostgreSQL implementation of DbSearch
 */
class Search extends AbstractSearch
{
	/**
	 * Everything starts here... more or less
	 */
	public function __construct($db)
	{
		$this->_supported_types = array('custom');
		parent::__construct($db);
	}

	/**
	 * {@inheritDoc}
	 */
	public function search_query($identifier, $db_string, $db_values = array())
	{
		$replacements = array(
			'drop_tmp_log_search_topics' => array(
				'~IF\sEXISTS~i' => '',
			),
			'drop_tmp_log_search_messages' => array(
				'~IF\sEXISTS~i' => '',
			),
		);

		if (isset($replacements[$identifier]))
		{
			$db_string = preg_replace(array_keys($replacements[$identifier]), array_values($replacements[$identifier]), $db_string);
		}
		elseif (preg_match('~^\s*INSERT\sIGNORE~i', $db_string) != 0)
		{
			$db_string = preg_replace('~^\s*INSERT\sIGNORE~i', 'INSERT', $db_string);
			// Don't error on multi-insert.
			$this->_skip_error = true;
		}

		return parent::search_query('', $db_string, $db_values);
	}

	/**
	 * {@inheritDoc}
	 */
	public function membersTableInfo()
	{
		global $db_prefix, $txt;

		$db_table = db_table();

		$table_info = array();

		// In order to report the sizes correctly we need to perform vacuum (optimize) on the tables we will be using.
		$db_table->optimize('{db_prefix}messages');
		if ($db_table->table_exists('{db_prefix}log_search_words'))
		{
			$db_table->optimize('{db_prefix}log_search_words');
		}

		// PostGreSql has some hidden sizes.
		$request = $this->_db->query('', '
			SELECT relname, relpages * 8 *1024 AS "KB" FROM pg_class
			WHERE relname = {string:messages} OR relname = {string:log_search_words}
			ORDER BY relpages DESC',
			array(
				'messages' => $db_prefix . 'messages',
				'log_search_words' => $db_prefix . 'log_search_words',
			)
		);

		if ($request !== false && $request->num_rows() > 0)
		{
			while (($row = $request->fetch_assoc()))
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
			$request->free_result();
		}
		else
		{
			// Didn't work for some reason...
			$table_info = array(
				'data_length' => $txt['not_applicable'],
				'index_length' => $txt['not_applicable'],
				'fulltext_length' => $txt['not_applicable'],
				'custom_index_length' => $txt['not_applicable'],
			);
		}

		return $table_info;
	}
}
