<?php

/**
 * This class handles display, edit, save, of forum settings.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:    2011 Simple Machines (http://www.simplemachines.org)
 * license:    BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version   1.1 beta 2
 *
 */
class SettingsFormAdapterDbTable extends SettingsFormAdapterDb
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

	/**
	 * @param string $tableName name of the table the values will be saved in
	 * @param int    $editId    -1 add a row, otherwise edit a row with the supplied key value
	 * @param string $editName  used when editing a row, needs to be the name of the col to find $editId
	 */
	public function __construct($tableName, $editId, $editName)
	{
		$this->db = database();
		$this->tableName = $tableName;
		$this->editId = $editId;
		$this->editName = $editName;
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

		// Do it !!
		$this->db->insert($update ? 'replace' : 'insert',
			'{db_prefix}' . $this->tableName,
			$insertVars,
			$insertValues,
			$index
		);
	}
}
