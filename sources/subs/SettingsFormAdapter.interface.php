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
 * @version 1.1 beta 2
 *
 */
interface SettingsFormAdapter_Interface
{
	/**
	 * @return array
	 */
	public function getConfigVars();

	/**
	 * @param array $config_vars
	 */
	public function setConfigVars($config_vars);

	/**
	 * @return array
	 */
	public function getPostVars();

	/**
	 * @param array $post_vars
	 */
	public function setPostVars($post_vars);

	public function prepare();


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
	public function save();
}
