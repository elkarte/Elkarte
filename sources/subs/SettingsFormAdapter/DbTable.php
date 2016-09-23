<?php

/**
 * This class handles display, edit, save, of forum settings.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 beta 2
 *
 */

namespace ElkArte\sources\subs\SettingsFormAdapter;

class DbTable extends Db
{
	private $db;

	/**
	 * @var string
	 */
	private $tableName;

	/**
	 * @var int
	 */
	private $editId;

	/**
	 * @var string
	 */
	private $editName;

	public function __construct()
	{
		$this->db = database();
	}

	/**
	 * @param string $editName used when editing a row, needs to be the name of the col to find $editId
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
	 * @param int $editId -1 add a row, otherwise edit a row with the supplied key value
	 */
	public function setEditId($editId)
	{
		$this->editId = $editId;
	}

	public function save()
	{
		list ($insertVars, $insertValues) = $this->sanitizeVars();
		$update = false;

		// Everything is now set so is this a new row or an edit?
		if ($this->editId !== -1 && !empty($this->editName))
		{
			// Time to edit, add in the id col name, assumed to be primary/unique!
			$update = true;
			$insertVars[$this->editName] = 'int';
			$insertValues[] = $this->editId;
		}

		// Do it!!
		$this->db->insert($update ? 'replace' : 'insert',
			'{db_prefix}' . $this->tableName,
			$insertVars,
			$insertValues,
			array()
		);
	}
}
