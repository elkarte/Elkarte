<?php

/**
 * Lists are a crazy thing, so they need different classes, this is the source
 * of them all.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file includes code also covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditio
 *
 * @version 1.0 Release Candidate 2
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Some common methods usable by classes that want to create lists.
 * In some time it will replace GenericList.
 */
interface List_Interface
{
	public function getResults();

	public function getPagination($base_url, $totals, $flexible);
}