<?php

/**
 * Abstract class with the common elements to handle database search.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Database;

/**
 * The common base for the implementation of SearchInterface
 */
abstract class AbstractSearch implements SearchInterface
{
	/**
	 * The database object
	 *
	 * @var \ElkArte\Database\QueryInterface
	 */
	protected $_db = null;

	/**
	 * The supported search methods
	 *
	 * @var string[]
	 */
	protected $_supported_types = array();

	/**
	 * The way to skip a database error
	 *
	 * @var bool
	 */
	protected $_skip_error = false;

	/**
	 * Usual constructor
	 */
	public function __construct($db)
	{
		$this->_db = $db;
	}

	/**
	 * {@inheritdoc }
	 */
	public function search_support($search_type)
	{
		return in_array($search_type, $this->_supported_types);
	}

	/**
	 * {@inheritdoc }
	 */
	public function search_query($identifier, $db_string, $db_values = array())
	{
		if ($this->_skip_error)
		{
			$this->_db->skip_next_error();
			$this->_skip_error = false;
		}

		// Simply delegate to the database adapter method.
		return $this->_db->query($identifier, $db_string, $db_values);
	}

	/**
	 * {@inheritDoc}
	 */
	public function skip_next_error()
	{
		$this->_skip_error = true;
	}

	/**
	 * {@inheritDoc}
	 */
	abstract public function membersTableInfo();

	/**
	 * {@inheritDoc}
	 */
	public function create_word_search()
	{
		$db_table = db_table();
		$db_table->create_table('{db_prefix}log_search_words',
			array(
				array(
					'name' => 'id_word',
					'type' => 'int',
					'size' => 10,
					'unsigned' => true,
					'default' => 0
				),
				array(
					'name' => 'id_msg',
					'type' => 'int',
					'size' => 10,
					'unsigned' => true,
					'default' => 0
				),
			),
			array(
				array('name' => 'id_word', 'columns' => array('id_word', 'id_msg'), 'type' => 'primary')
			)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function createTemporaryTable($name, $columns, $indexes)
	{
		$db_table = db_table();

		return $db_table->create_table($name, $columns, $indexes, array(
			'temporary' => true,
			'if_exists' => 'force_drop'
		));
	}
}
