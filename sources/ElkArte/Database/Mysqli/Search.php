<?php

/**
 * This class handles database search. (MySQL)
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Database\Mysqli;

use ElkArte\Database\AbstractSearch;

/**
 * MySQL implementation of DbSearch
 */
class Search extends AbstractSearch
{
	/**
	 * Everything starts here... more or less
	 */
	public function __construct($db)
	{
		$this->_supported_types = array('fulltext');
		parent::__construct($db);
	}

	/**
	 * {@inheritDoc}
	 */
	public function search_query($identifier, $db_string, $db_values = array(), $connection = null)
	{
		// Simply delegate to the database adapter method.
		return parent::search_query('', $db_string, $db_values);
	}

	/**
	 * {@inheritDoc}
	 */
	public function membersTableInfo()
	{
		global $db_prefix;

		$table_info = array();

		if (preg_match('~^`(.+?)`\.(.+?)$~', $db_prefix, $match) !== 0)
		{
			$request = $this->_db->query('', '
				SHOW TABLE STATUS
				FROM {string:database_name}
				LIKE {string:table_name}',
				array(
					'database_name' => '`' . strtr($match[1], array('`' => '')) . '`',
					'table_name' => str_replace('_', '\_', $match[2]) . 'messages',
				)
			);
		}
		else
		{
			$request = $this->_db->query('', '
				SHOW TABLE STATUS
				LIKE {string:table_name}',
				array(
					'table_name' => str_replace('_', '\_', $db_prefix) . 'messages',
				)
			);
		}

		if ($request !== false && $request->num_rows() == 1)
		{
			// Only do this if the user has permission to execute this query.
			$row = $request->fetch_assoc();
			$table_info['data_length'] = $row['Data_length'];
			$table_info['index_length'] = $row['Index_length'];
			$table_info['fulltext_length'] = $row['Index_length'];
			$request->free_result();
		}

		// Now check the custom index table, if it exists at all.
		if (preg_match('~^`(.+?)`\.(.+?)$~', $db_prefix, $match) !== 0)
		{
			$request = $this->_db->query('', '
				SHOW TABLE STATUS
				FROM {string:database_name}
				LIKE {string:table_name}',
				array(
					'database_name' => '`' . strtr($match[1], array('`' => '')) . '`',
					'table_name' => str_replace('_', '\_', $match[2]) . 'log_search_words',
				)
			);
		}
		else
		{
			$request = $this->_db->query('', '
				SHOW TABLE STATUS
				LIKE {string:table_name}',
				array(
					'table_name' => str_replace('_', '\_', $db_prefix) . 'log_search_words',
				)
			);
		}

		if ($request !== false && $request->num_rows() == 1)
		{
			// Only do this if the user has permission to execute this query.
			$row = $request->fetch_assoc();
			$table_info['index_length'] += $row['Data_length'] + $row['Index_length'];
			$table_info['custom_index_length'] = $row['Data_length'] + $row['Index_length'];
			$request->free_result();
		}

		return $table_info;
	}
}
