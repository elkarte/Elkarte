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
	/**
	 * @return array
	 */
	public function getConfigVars()
	{
		return $this->adapter->getConfigVars();
	}

	/**
	 * @param array $config_vars
	 */
	public function setConfigVars($config_vars)
	{
		$this->adapter->setConfigVars($config_vars);
	}

	/**
	 * @return array
	 */
	public function getPostVars()
	{
		return $this->adapter->getPostVars();
	}

	/**
	 * @param array $post_vars
	 */
	public function setPostVars($post_vars)
	{
		$this->adapter->setPostVars($post_vars);
	}

	/**
	 * @var SettingsFormAdapter
	 */
	private $adapter;

	/**
	 * @var SettingsFormAdapter $adapter
	 */
	public function __construct(SettingsFormAdapter $adapter = null)
	{
		$this->adapter = $adapter ?: new SettingsFormAdapterFile;
	}

	/**
	 * @return SettingsFormAdapter
	 */
	public function getAdapter()
	{
		return $this->adapter;
	}

	public function prepare_file()
	{
		if (!$this->adapter instanceof SettingsFormAdapterFile)
		{
			$this->adapter = new SettingsFormAdapterFile;
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
	 * @param mixed[] $config_vars
	 */
	public static function prepare_db($config_vars)
	{
		$settingsForm = new self(new SettingsFormAdapterDb);
		$settingsForm->setConfigVars($config_vars);
		$settingsForm->prepare();
	}

	/**
	 * This method saves the settings.
	 *
	 * It will put them in Settings.php or in the settings table.
	 *
	 * What it does:
	 * - Used to save those settings set from ?action=admin;area=serversettings.
	 * - Requires the admin_forum permission.
	 * - Contains arrays of the types of data to save into Settings.php.
	 */
	public function save()
	{
		validateToken('admin-ssc');
		$this->adapter->save();
	}

	/**
	 * Helper method for saving settings.
	 *
	 * @param mixed[] $config_vars
	 */
	public static function save_file($config_vars)
	{
		$settingsForm = new self;
		$settingsForm->setConfigVars($config_vars);
		$settingsForm->save();
	}

	/**
	 * Helper method for saving database settings.
	 *
	 * @param mixed[]        $config_vars
	 * @param mixed[]|object $post_object
	 */
	public static function save_db($config_vars, $post_object = null)
	{
		// Just look away if you have a weak stomach
		if ($post_object !== null && is_object($post_object))
		{
			$post_vars = array_replace($_POST, (array) $post_object);
		}
		else
		{
			$post_vars = $_POST;
		}
		$settingsForm = new self(new SettingsFormAdapterDb);
		$settingsForm->setConfigVars($config_vars);
		$settingsForm->setPostVars($post_vars);
		$settingsForm->save();
	}

	/**
	 * Method which retrieves or sets new configuration variables.
	 *
	 * If the $config_vars parameter is sent, the method tries to update
	 * the internal configuration of the Settings_Form instance.
	 *
	 * If the $config_vars parameter is not sent (is null), the method
	 * simply returns the current configuration set.
	 *
	 *  The array is formed of:
	 *  - either, variable name, description, type (constant), size/possible values, helptext.
	 *  - either, an empty string for a horizontal rule.
	 *  - or, a string for a titled section.
	 *
	 * @param mixed[]|null $config_vars = null array of config vars, if null the method returns the current
	 *                                  configuration
	 */
	public function settings($config_vars = null)
	{
		if (is_null($config_vars))
		{
			// Simply return the config vars we have
			return $this->adapter->getConfigVars();
		}
		else
		{
			// We got presents :P
			$this->adapter->setConfigVars(is_array($config_vars) ? $config_vars : array($config_vars));

			return $this->adapter->getConfigVars();
		}
	}
}
