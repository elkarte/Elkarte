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
 * @version 1.1 beta 3
 *
 *
 * Adding options to one of the setting screens isn't hard.
 * Call prepareDBSettingsContext;
 * The basic format for a checkbox is:
 *    array('check', 'nameInModSettingsAndSQL'),
 * And for a text box:
 *    array('text', 'nameInModSettingsAndSQL')
 * (NOTE: You have to add an entry for this at the bottom!)
 *
 * In the above examples, it will look for $txt['nameInModSettingsAndSQL'] as the description,
 * and $helptxt['nameInModSettingsAndSQL'] as the help popup description.
 *
 * Here's a quick explanation of how to add a new item:
 *
 * - A text input box.  For textual values.
 *     array('text', 'nameInModSettingsAndSQL', 'OptionalInputBoxWidth'),
 * - A text input box.  For numerical values.
 *     array('int', 'nameInModSettingsAndSQL', 'OptionalInputBoxWidth'),
 * - A text input box.  For floating point values.
 *     array('float', 'nameInModSettingsAndSQL', 'OptionalInputBoxWidth'),
 * - A large text input box. Used for textual values spanning multiple lines.
 *     array('large_text', 'nameInModSettingsAndSQL', 'OptionalNumberOfRows'),
 * - A check box.  Either one or zero. (boolean)
 *     array('check', 'nameInModSettingsAndSQL'),
 * - A selection box.  Used for the selection of something from a list.
 *     array('select', 'nameInModSettingsAndSQL', array('valueForSQL' => $txt['displayedValue'])),
 *     Note that just saying array('first', 'second') will put 0 in the SQL for 'first'.
 * - A password input box. Used for passwords, no less!
 *     array('password', 'nameInModSettingsAndSQL', 'OptionalInputBoxWidth'),
 * - A permission - for picking groups who have a permission.
 *     array('permissions', 'manage_groups'),
 * - A BBC selection box.
 *     array('bbc', 'sig_bbc'),
 *
 * For each option:
 *  - type (see above), variable name, size/possible values.
 *    OR make type '' for an empty string for a horizontal rule.
 *  - SET preinput - to put some HTML prior to the input box.
 *  - SET postinput - to put some HTML following the input box.
 *  - SET invalid - to mark the data as invalid.
 *  - PLUS you can override label and help parameters by forcing their keys in the array, for example:
 *    array('text', 'invalid label', 3, 'label' => 'Actual Label')
 */

/**
 * Settings Form class.
 * This class handles display, edit, save, of forum settings.
 * It is used by the various admin areas which set their own settings,
 * and it is available for addons administration screens.
 *
 */
class Settings_Form
{
	const DB_ADAPTER = 'ElkArte\\sources\\subs\\SettingsFormAdapter\\Db';
	const DBTABLE_ADAPTER = 'ElkArte\\sources\\subs\\SettingsFormAdapter\\DbTable';
	const FILE_ADAPTER = 'ElkArte\\sources\\subs\\SettingsFormAdapter\\File';

	/**
	 * @return array
	 */
	public function getConfigVars()
	{
		return $this->adapter->getConfigVars();
	}

	/**
	 * @param array $configVars
	 */
	public function setConfigVars(array $configVars)
	{
		$this->adapter->setConfigVars($configVars);
	}

	/**
	 * @return array
	 */
	public function getConfigValues()
	{
		return $this->adapter->getConfigValues();
	}

	/**
	 * @param array $configValues
	 */
	public function setConfigValues(array $configValues)
	{
		$this->adapter->setConfigValues($configValues);
	}

	/**
	 * @var ElkArte\sources\subs\SettingsFormAdapter\Adapter
	 */
	private $adapter;

	/**
	 * @param string|null $adapter Will default to the file adapter if none is specified.
	 */
	public function __construct($adapter = null)
	{
		$fqcn = $adapter ?: self::FILE_ADAPTER;

		$this->adapter = new $fqcn;
	}

	/**
	 * @return ElkArte\sources\subs\SettingsFormAdapter\Adapter
	 */
	public function getAdapter()
	{
		return $this->adapter;
	}

	/**
	 * @deprecated since 1.1
	 */
	public function prepare_file()
	{
		if (!$this->adapter instanceof ElkArte\sources\subs\SettingsFormAdapter\File)
		{
			$this->adapter = new ElkArte\sources\subs\SettingsFormAdapter\File;
		}
		$this->prepare();
	}

	public function prepare()
	{
		createToken('admin-ssc');
		$this->adapter->prepare();
	}

	/**
	 * Helper method, it sets up the context for database settings.
	 *
	 * @deprecated since 1.1
	 *
	 * @param mixed[] $configVars
	 */
	public static function prepare_db(array $configVars)
	{
		global $modSettings;

		$settingsForm = new self(self::DB_ADAPTER);
		$settingsForm->setConfigVars($configVars);
		$settingsForm->setConfigValues($modSettings);
		$settingsForm->prepare();
	}

	/**
	 * This method saves the settings.
	 *
	 * It will put them in Settings.php or in the settings table
	 * according to the adapter specified in the constructor.
	 *
	 * May read from $_POST to retain backwards compatibility.
	 * Some older controller may have modified this superglobal,
	 * and HttpReq does not contain the newly modified information.
	 */
	public function save()
	{
		validateToken('admin-ssc');

		// Retain backwards compatibility
		$configValues = $this->getConfigValues();
		if (empty($configValues))
		{
			$this->setConfigValues($_POST);
		}
		$this->adapter->save();
	}

	/**
	 * Helper method for saving settings.
	 *
	 * Uses $_POST because the controller may have modified this superglobal,
	 * and HttpReq does not contain the newly modified information.
	 *
	 * @deprecated since 1.1
	 *
	 * @param mixed[] $configVars
	 */
	public static function save_file(array $configVars)
	{
		$settingsForm = new self;
		$settingsForm->setConfigVars($configVars);
		$settingsForm->setConfigValues($_POST);
		$settingsForm->save();
	}

	/**
	 * Helper method for saving database settings.
	 *
	 * Uses $_POST because the controller may have modified it,
	 * and HttpReq does not contain the newly modified information.
	 *
	 * @deprecated since 1.1
	 *
	 * @param array        $configVars
	 * @param array|object $configValues
	 */
	public static function save_db(array $configVars, $configValues = array())
	{
		// Just look away if you have a weak stomach
		if ($configValues !== null)
		{
			$configValues = array_replace($_POST, (array) $configValues);
		}
		else
		{
			$configValues = $_POST;
		}
		$settingsForm = new self(self::DB_ADAPTER);
		$settingsForm->setConfigVars($configVars);
		$settingsForm->setConfigValues($configValues);
		$settingsForm->save();
	}

	/**
	 * Method which retrieves or sets new configuration variables.
	 *
	 * If the $configVars parameter is sent, the method tries to update
	 * the internal configuration of the Settings_Form instance.
	 *
	 * If the $configVars parameter is not sent (is null), the method
	 * simply returns the current configuration set.
	 *
	 *  The array is formed of:
	 *  - either, variable name, description, type (constant), size/possible values, helptext.
	 *  - either, an empty string for a horizontal rule.
	 *  - or, a string for a titled section.
	 *
	 * @deprecated since 1.1
	 *
	 * @param mixed[]|null $configVars = null array of config vars, if null the method returns the current
	 *                                  configuration
	 */
	public function settings(array $configVars = null)
	{
		if (is_null($configVars))
		{
			// Simply return the config vars we have
			return $this->adapter->getConfigVars();
		}
		else
		{
			// We got presents :P
			$this->adapter->setConfigVars(is_array($configVars) ? $configVars : array($configVars));

			return $this->adapter->getConfigVars();
		}
	}
}
