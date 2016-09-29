<?php

/**
 * Specialized version of saveDBSettings to save config_vars in a table and not
 * an external file.
 * Handles saving of config vars in another table than settings.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 beta 3
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
	 * @param array|object|null $config_vars the key names of the vars are the table cols
	 * @param string $tablename name of the table the values will be saved in
	 * @param mixed[]|null $config_values
	 * @param string[] $index for compatibility
	 * @param integer $editid -1 add a row, otherwise edit a row with the supplied key value
	 * @param string $editname used when editing a row, needs to be the name of the col to find $editid key value
	 */
	public static function saveTableSettings($config_vars, $tablename, $config_values = null, $index = array(), $editid = -1, $editname = '')
	{
		$db = database();

		if ($config_values === null)
			$config_values = $_POST;
		elseif (is_object($config_values))
			$config_values = (array) $config_values;

		// Init
		$insert_type = array();
		$insert_value = array();
		$update = false;

		// Cast all the config vars as defined
		foreach ($config_vars as $var)
		{
			if (!isset($var[1]) || (!isset($config_values[$var[1]]) && $var[0] !== 'check'))
				continue;

			// Checkboxes ...
			elseif ($var[0] === 'check')
			{
				$insert_type[$var[1]] = 'int';
				$insert_value[] = !empty($config_values[$var[1]]) ? 1 : 0;
			}
			// Or maybe even select boxes
			elseif ($var[0] === 'select' && in_array($config_values[$var[1]], array_keys($var[2])))
			{
				$insert_type[$var[1]] = 'string';
				$insert_value[] = $config_values[$var[1]];
			}
			elseif ($var[0] === 'select' && !empty($var['multiple']) && array_intersect($config_values[$var[1]], array_keys($var[2])) != array())
			{
				// For security purposes we need to validate this line by line.
				$options = array();
				foreach ($config_values[$var[1]] as $invar)
				{
					if (in_array($invar, array_keys($var[2])))
						$options[] = $invar;
				}

				$insert_type[$var[1]] = 'string';
				$insert_value[] = serialize($options);
			}
			// Integers are fundamental
			elseif ($var[0] == 'int')
			{
				$insert_type[$var[1]] = 'int';
				$insert_value[] = (int) $config_values[$var[1]];
			}
			// Floating points are easy
			elseif ($var[0] === 'float')
			{
				$insert_type[$var[1]] = 'float';
				$insert_value[] = (float) $config_values[$var[1]];
			}
			// Text is fine too!
			elseif ($var[0] === 'text' || $var[0] === 'large_text')
			{
				$insert_type[$var[1]] = 'string';
				$insert_value[] = $config_values[$var[1]];
			}
		}

		// Everything is now set so is this a new row or an edit?
		if ($editid !== -1 && !empty($editname))
		{
			// Time to edit, add in the id col name, assumed to be primary/unique!
			$update = true;
			$insert_type[$editname] = 'int';
			$insert_value[] = $editid;
		}

		// Do it !!
		$db->insert($update ? 'replace' : 'insert',
			'{db_prefix}' . $tablename,
			$insert_type,
			$insert_value,
			$index
		);
	}
}