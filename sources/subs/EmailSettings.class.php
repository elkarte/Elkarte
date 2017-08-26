<?php

/**
 * Specialized version of saveDBSettings to save configVars in a table and not
 * an external file.
 * Handles saving of config vars in another table than settings.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 Release Candidate 2
 *
 */

/**
 * Similar in construction to saveDBSettings,
 *
 * - Saves the config_vars to a specified table instead of a file
 * - Var names are considered the table col names,
 * - Values are cast per config vars
 * - If editing a row, the primary col index and existing index value must be
 *   supplied (editid and editname), otherwise a new row will be added
 *
 * @package Maillist
 */
class Email_Settings extends Settings_Form
{
	/**
	 * static function saveTableSettings, now part of the Settings Form class
	 *
	 * @param array             $configVars   the key names of the vars are the table cols
	 * @param string            $tableName    name of the table the values will be saved in
	 * @param array|object|null $configValues the key names of the vars are the table cols
	 * @param string[]          $indexes      for compatibility
	 * @param integer           $editId       -1 add a row, otherwise edit a row with the supplied key value
	 * @param string            $editName     used when editing a row, needs to be the name of the col to find $editId
	 */
	public static function saveTableSettings(array $configVars, $tableName, $configValues = null, array $indexes = array(), $editId = -1, $editName = '')
	{
		if ($configValues === null)
		{
			$configValues = $_POST;
		}
		elseif (is_object($configValues))
		{
			$configValues = (array) $configValues;
		}

		$settingsForm = new self(self::DBTABLE_ADAPTER);
		/** @var ElkArte\sources\subs\SettingsFormAdapter\DbTable */
		$settingsAdapter = $settingsForm->getAdapter();
		$settingsAdapter->setTableName($tableName);
		$settingsAdapter->setEditId($editId);
		$settingsAdapter->setEditName($editName);
		$settingsAdapter->setIndexes($indexes);
		$settingsForm->setConfigVars($configVars);
		$settingsForm->setConfigValues($configValues);
		$settingsForm->save();
	}
}
