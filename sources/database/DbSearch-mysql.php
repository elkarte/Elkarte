<?php

/**
 * This class handles database search. (MySQL)
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 dev
 *
 */

/**
 * MySQL implementation of DbSearch
 */
class DbSearch_MySQL extends DbSearch_Abstract
{
	/**
	 * This instance of the search
	 * @var DbSearch_MySQL
	 */
	private static $_search = null;

	/**
	 * Everything starts here... more or less
	 */
	public function __construct()
	{
		$this->_supported_types = array('fulltext');
	}

	/**
	 * {@inheritDoc}
	 */
	public function search_query($identifier, $db_string, $db_values = array(), $connection = null)
	{
		// Simply delegate to the database adapter method.
		return parent::search_query('', $db_string, $db_values, $connection);
	}

	/**
	 * {@inheritDoc}
	 */
	public function membersTableInfo()
	{
		global $db_prefix;

		$db = database();

		$table_info = array();

		if (preg_match('~^`(.+?)`\.(.+?)$~', $db_prefix, $match) !== 0)
		{
			$request = $db->query('', '
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
			$request = $db->query('', '
				SHOW TABLE STATUS
				LIKE {string:table_name}',
				array(
					'table_name' => str_replace('_', '\_', $db_prefix) . 'messages',
				)
			);
		}

		if ($request !== false && $db->num_rows($request) == 1)
		{
			// Only do this if the user has permission to execute this query.
			$row = $db->fetch_assoc($request);
			$table_info['data_length'] = $row['Data_length'];
			$table_info['index_length'] = $row['Index_length'];
			$table_info['fulltext_length'] = $row['Index_length'];
			$db->free_result($request);
		}

		// Now check the custom index table, if it exists at all.
		if (preg_match('~^`(.+?)`\.(.+?)$~', $db_prefix, $match) !== 0)
			$request = $db->query('', '
				SHOW TABLE STATUS
				FROM {string:database_name}
				LIKE {string:table_name}',
				array(
					'database_name' => '`' . strtr($match[1], array('`' => '')) . '`',
					'table_name' => str_replace('_', '\_', $match[2]) . 'log_search_words',
				)
			);
		else
			$request = $db->query('', '
				SHOW TABLE STATUS
				LIKE {string:table_name}',
				array(
					'table_name' => str_replace('_', '\_', $db_prefix) . 'log_search_words',
				)
			);

		if ($request !== false && $db->num_rows($request) == 1)
		{
			// Only do this if the user has permission to execute this query.
			$row = $db->fetch_assoc($request);
			$table_info['index_length'] += $row['Data_length'] + $row['Index_length'];
			$table_info['custom_index_length'] = $row['Data_length'] + $row['Index_length'];
			$db->free_result($request);
		}

		return $table_info;
	}

	/**
	 * {@inheritDoc}
	 */
	public function create_word_search($type, $size = 10)
	{
		$db_table = db_table();

		if ($size == 'small')
		{
			$type = 'smallint';
			$size = 5;
		}
		elseif ($size == 'medium')
		{
			$type = 'mediumint';
			$size = 8;
		}
		else
		{
			$type = 'int';
			$size = 10;
		}

		parent::create_word_search->db_create_table($type, $size);
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