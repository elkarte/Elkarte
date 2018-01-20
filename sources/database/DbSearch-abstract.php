<?php

/**
 * Abstract class with the common elements to handle database search.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 dev
 *
 */

/**
 * The common base for the implementation of DbSearch
 */
class DbSearch_Abstract implements DbSearch
{
	/**
	 * The supported search methods
	 * @var string[]
	 */
	protected $_supported_types = array();

	/**
	 * The way to skip a database error
	 * @var boolean
	 */
	protected $_skip_error = false;

	/**
	 * {@inheritdoc }
	 */
	public function search_support($search_type)
	{
		return in_array($search_type, $this->$_supported_types);
	}

	/**
	 * {@inheritdoc }
	 */
	public function search_query($identifier, $db_string, $db_values = array(), $connection = null)
	{
		$db = database();

		if ($this->_skip_error === true)
		{
			$db->skip_next_error();
			$this->_skip_error = false;
		}

		// Simply delegate to the database adapter method.
		return $db->query($identifier, $db_string, $db_values, $connection);
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
	public abstract function membersTableInfo();


	/**
	 * {@inheritDoc}
	 */
	public function create_word_search($type, $size = 10)
	{
		$db_table->db_create_table('{db_prefix}log_search_words',
			array(
				array(
					'name' => 'id_word',
					'type' => $type,
					'size' => $size,
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
				'columns' => array('id_word', 'id_msg')
			)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function createTemporaryTable($name, $columns, $indexes)
	{
		$db_table = db_table();
		return $db_table->db_create_table($name, $columns, $indexes, array('temporary' => true);
	}
}