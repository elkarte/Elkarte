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
 * @version 2.0 dev
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

namespace ElkArte\SettingsForm;

/**
 * Settings Form class.
 * This class handles display, edit, save, of forum settings.
 * It is used by the various admin areas which set their own settings,
 * and it is available for addons administration screens.
 *
 */
class SettingsForm
{
	const DB_ADAPTER = 'ElkArte\\SettingsForm\\SettingsFormAdapter\\Db';
	const DBTABLE_ADAPTER = 'ElkArte\\SettingsForm\\SettingsFormAdapter\\DbTable';
	const FILE_ADAPTER = 'ElkArte\\SettingsForm\\SettingsFormAdapter\\File';

	/**
	 * @var ElkArte\SettingsForm\SettingsFormAdapter\Adapter
	 */
	private $adapter;

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
	 * @param string|null $adapter Will default to the file adapter if none is specified.
	 */
	public function __construct($adapter = null)
	{
		$fqcn = $adapter ?: self::FILE_ADAPTER;

		$this->adapter = new $fqcn;
	}

	/**
	 * @return ElkArte\SettingsForm\SettingsFormAdapter\Adapter
	 */
	public function getAdapter()
	{
		return $this->adapter;
	}

	/**
	 * This method reaads the settings and prepares them for
	 * display within the template.
	 *
	 * It will read either Settings.php or the settings table
	 * according to the adapter specified in the constructor.
	 *
	 * Creates the token `admin-ssc`
	 */
	public function prepare()
	{
		createToken('admin-ssc');
		$this->adapter->prepare();
	}

	/**
	 * This method saves the settings.
	 *
	 * It will put them in Settings.php or in the settings table
	 * according to the adapter specified in the constructor.
	 *
	 * Validates the token `admin-ssc`
	 */
	public function save()
	{
		validateToken('admin-ssc');

		$this->adapter->save();
	}
}
