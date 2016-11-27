<?php

/**
 * Abstract class that defines the methods search APIs shall implement
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 beta 3
 */

namespace ElkArte\Search\API;

use ElkArte\Search\Search_Interface;

/**
 * Abstract class that defines the methods any search API shall implement
 * to properly work with ElkArte
 *
 * @package Search
 */
abstract class SearchAPI implements Search_Interface
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
	 * Any word excluded from the search?
	 * @var array
	 */
	protected $_excludedWords = array();

	/**
	 * Method to check whether the method can be performed by the API.
	 *
	 * @deprecated since 1.1 - check that the method is callable
	 *
	 * @param string $methodName
	 * @param string|null $query_params
	 * @return boolean
	 */
	public function supportsMethod($methodName, $query_params = null)
	{
		return is_callable(array($this, $methodName));
	}

	/**
	 * If the settings don't exist we can't continue.
	 */
	public function isValid()
	{
		// Always fall back to the standard search method.
		return true;
	}

	/**
	 * Adds the excluded words list
	 *
	 * @param string[] $words An array of words to exclude
	 */
	public function setExcludedWords($words)
	{
		$this->_excludedWords = $words;
	}

	/**
	 * Escape words passed by the client
	 *
	 * @param string $phrase - The string to escape
	 * @param bool $no_regexp - If true or $modSettings['search_match_words']
	 *              is empty, uses % at the beginning and end of the string,
	 *              otherwise returns a regular expression
	 */
	public function prepareWord($phrase, $no_regexp)
	{
		global $modSettings;

		return empty($modSettings['search_match_words']) || $no_regexp ? '%' . strtr($phrase, array('_' => '\\_', '%' => '\\%')) . '%' : '[[:<:]]' . addcslashes(preg_replace(array('/([\[\]$.+*?|{}()])/'), array('[$1]'), $phrase), '\\\'') . '[[:>:]]';
	}
}
