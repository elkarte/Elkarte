<?php

/**
 * 
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 dev
 *
 */

if (!defined('ELK'))
	die('No access...');

class Random_Display_Module
{
	public static function hooks()
	{
		global $modSettings;

		$return = array();

		if (!empty($modSettings['enableFollowup']))
		{
			add_integration_function('integrate_topic_query', 'Random_Display_Module::followup_topic_query', '', false);
		}

		return $return;
	}

	public static function followup_topic_query(&$topic_selects, &$topic_tables, &$topic_parameters)
	{
		$topic_selects[] = 'fu.derived_from';
		$topic_tables[] = 'LEFT JOIN {db_prefix}follow_ups AS fu ON (fu.follow_up = t.id_topic)';
	}
}