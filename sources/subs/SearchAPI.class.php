<?php

/**
 * Abstract class that defines the methods search APIs shall implement
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Beta 2
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Abstract class that defines the methods any search API shall implement
 * to properly work with ElkArte
 *
 * @package Search
 */
abstract class SearchAPI
{
	/**
	 * This is the last version of ElkArte that this was tested on, to protect against API changes.
	 * @var string
	 */
	public $version_compatible;

	/**
	 * This won't work with versions of ElkArte less than this.
	 * @var string
	 */
	public $min_elk_version;

	/**
	 * Standard search is supported by default.
	 * @var boolean
	 */
	public $is_supported;

	/**
	 * Method to check whether the method can be performed by the API.
	 *
	 * @param string $methodName
	 * @param string|null $query_params
	 * @return boolean
	 */
	abstract public function supportsMethod($methodName, $query_params = null);

	/**
	 * If the settings don't exist we can't continue.
	 */
	public function isValid()
	{
		// Always fall back to the standard search method.
		return true;
	}
}