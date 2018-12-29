<?php

/**
 * This class handles display, edit, save, of forum settings.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright:    2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\SettingsForm\SettingsFormAdapter;

/**
 * Interface AdapterInterface
 *
 * @package ElkArte\SettingsForm\SettingsFormAdapter
 */
interface AdapterInterface
{
	/**
	 * @return array
	 */
	public function getConfigVars();

	/**
	 * @param array $configVars
	 */
	public function setConfigVars(array $configVars);

	/**
	 * @return array
	 */
	public function getConfigValues();

	/**
	 * @param array $configValues
	 */
	public function setConfigValues(array $configValues);

	public function prepare();


	/**
	 * This method saves the settings.
	 *
	 * It will put them in Settings.php or in the settings table.
	 *
	 * What it does:
	 *
	 * - Used to save those settings set from ?action=admin;area=serversettings.
	 * - Requires the admin_forum permission.
	 * - Contains arrays of the types of data to save into Settings.php.
	 */
	public function save();
}
