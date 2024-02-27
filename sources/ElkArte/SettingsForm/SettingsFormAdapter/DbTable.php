<?php

/**
 * Similar in construction to Db Settings form adapter but saves the config_vars
 * to a specified table instead of a file.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\SettingsForm\SettingsFormAdapter;

/**
 * Class DbTable
 *
 * @package ElkArte\SettingsForm\SettingsFormAdapter
 */
class DbTable extends Db
{
	private $db;

	/** @var string Table name to save values */
	private $tableName;

	/** @var int Primary col index when editing a row */
	private $editId;

	/** @var string Existing index value when editing a row */
	private $editName;

	/** @var string[] */
	private $indexes = [];

	/**
	 * DbTable constructor.
	 */
	public function __construct()
	{
		$this->db = database();
	}

	/**
	 * @param string $editName used when editing a row, needs to be the name of the col to find $this->editId
	 */
	public function setEditName($editName)
	{
		$this->editName = $editName;
	}

	/**
	 * @param string $tableName name of the table the values will be saved in
	 */
	public function setTableName($tableName)
	{
		$this->tableName = $tableName;
	}

	/**
	 * @param string[] $indexes name of the table indexes (just the primary keys will suffice)
	 */
	public function setIndexes(array $indexes)
	{
		$this->indexes = $indexes;
	}

	/**
	 * @param int $editId -1 add a row, otherwise edit a row with the supplied key ($this->editName) of this value
	 */
	public function setEditId($editId)
	{
		$this->editId = (int) $editId;
	}

	/**
	 * Saves the data by either inserting a new row or updating an existing row.
	 *
	 * @return void
	 */
	public function save()
	{
		[$insertValues, $insertVars] = $this->sanitizeVars();
		$update = false;

		// Everything is now set so is this a new row or an edit?
		if ($this->editId !== -1 && !empty($this->editName))
		{
			// Time to edit, add in the id col name, assumed to be primary/unique!
			$update = true;
			$insertVars = array_merge([$this->editName => 'int'], $insertVars);
			array_unshift($insertValues, $this->editId);
		}

		// Do it!
		$this->db->insert($update ? 'replace' : 'insert',
			'{db_prefix}' . $this->tableName,
			$insertVars,
			$insertValues,
			$this->indexes
		);
	}
}
