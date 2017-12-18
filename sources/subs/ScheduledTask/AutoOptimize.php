<?php

/**
 * Auto optimize the database.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0-dev
 *
 */

namespace ElkArte\sources\subs\ScheduledTask;

/**
 * Auto optimize the database.
 *
 * @package ScheduledTasks
 */
class Auto_Optimize implements Scheduled_Task_Interface
{
	/**
	 * Auto optimize the database.
	 */
	public function run()
	{
		global $modSettings, $db_prefix;

		// we're working with them databases but we shouldn't :P
		$db = database();
		$db_table = db_table();

		// By default do it now!
		$delay = false;

		// As a kind of hack, if the server load is too great delay, but only by a bit!
		if (!empty($modSettings['load_average']) && !empty($modSettings['loadavg_auto_opt']) && $modSettings['load_average'] >= $modSettings['loadavg_auto_opt'])
			$delay = true;

		// Otherwise are we restricting the number of people online for this?
		if (!empty($modSettings['autoOptMaxOnline']))
		{
			$request = $db->query('', '
				SELECT COUNT(*)
				FROM {db_prefix}log_online',
				array(
				)
			);
			list ($dont_do_it) = $db->fetch_row($request);
			$db->free_result($request);

			if ($dont_do_it > $modSettings['autoOptMaxOnline'])
				$delay = true;
		}

		// If we are gonna delay, do so now!
		if ($delay)
			return false;

		// Get all the tables.
		$tables = $db->db_list_tables(false, $db_prefix . '%');

		foreach ($tables as $table)
			$db_table->optimize($table);

		// Return for the log...
		return true;
	}
}