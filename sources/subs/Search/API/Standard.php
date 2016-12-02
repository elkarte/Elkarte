<?php

/**
 * Standard non full index, non custom index search
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 beta 3
 *
 */

namespace ElkArte\Search\API;

/**
 * SearchAPI-Standard.class.php, Standard non full index, non custom index search
 *
 * @package Search
 */
class Standard extends SearchAPI
{
	/**
	 * This is the last version of ElkArte that this was tested on, to protect against API changes.
	 * @var string
	 */
	public $version_compatible = 'ElkArte 1.1';

	/**
	 * This won't work with versions of ElkArte less than this.
	 * @var string
	 */
	public $min_elk_version = 'ElkArte 1.0 Beta';

	/**
	 * Standard search is supported by default.
	 * @var boolean
	 */
	public $is_supported = true;
}
