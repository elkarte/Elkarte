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
abstract class List_Abstract implements List_Interface
{
	protected $_listOptions;
	protected $_listRequest = null;
	protected $_db = null;
	protected $_queryParams = array();
	protected $_fetching = false;
	protected $_fake_ascending = false;

	/**
	 * Starts a new list
	 * Makes sure the passed list contains the miniumn needed options to create a list
	 * Loads the options in to this instance
	 *
	 * @param Database $db
	 * @param mixed[] $listOptions
	 */
	public function __construct($db, $listOptions)
	{
		$this->_db = $db;

		$this->_validateOptions($listOptions);

		$this->_init();
	}

	/**
	 * {@inheritdoc }
	 */
	public abstract function getResults();

	/**
	 * {@inheritdoc }
	 */
	public function getPagination($base_url, $totals, $flexible)
	{
		return constructPageIndex($base_url, $this->_queryParams['start'], $totals, empty($this->_queryParams['limit']) ? $totals : $this->_queryParams['limit'], $flexible);
	}

	/**
	 * Allow to set the sorting order of the list.
	 *
	 * @param string $sort - the sorting index
	 * @param bool $descending - If the sorting is ascending or descending
	 */
	public function sortBy($sort, $descending = null)
	{
		if ($this->_validSort($sort))
			$this->_queryParams['sort'] = $sort;

		if ($descending !== null)
			$this->_queryParams['sort_desc'] = (bool) $descending;
	}

	/**
	 * Returns the current sorting method or the direction.
	 *
	 * @param bool $dir - If true returns the sorting direction instead of the
	 *                    sorting index (default = false)
	 * @return string|bool
	 */
	public function getSort($dir = false)
	{
		if ($dir)
			return !empty($this->_queryParams['sort_desc']);
		else
			return $this->_queryParams['sort'];
	}

	/**
	 * Returns the starting position
	 * @return int
	 */
	public function getStart()
	{
		return $this->_queryParams['start'];
	}

	/**
	 * Adds values as query parameters.
	 *
	 * @param string $key - the parameter index
	 * @param mixed $val - the value the parameter assumes
	 */
	public function addQueryParam($key, $val)
	{
		// @todo should test if set?
		$this->_queryParams[$key] = $val;
	}

	/**
	 * Allows to extend the query (if supported by the query itself).
	 * It allows to adds "pieces" of query to arrays that will be later injected
	 * into the query statement.
	 *
	 * In order to be used specific placeholders are required into the query:
	 *   - {query_extend_select}
	 *   - {query_extend_join}
	 *   - {query_extend_where}
	 *   - {query_extend_group}
	 *
	 * @param string $select - String to add to the SELECT statement
	 * @param string $join - String to add to the JOIN statement
	 * @param string $where - String to add to the WHERE statement
	 * @param string $group - String to add to the GROUP statement
	 */
	public function extendQuery($select = '', $join = '', $where = '', $group = '')
	{
		foreach (array('select', 'join', 'where', 'group') as $item)
		{
			if (!empty($$item))
				$this->_queryExtended[$item][] = $$item;
		}
	}

	/**
	 * Sets the LIMITs of the query
	 *
	 * @param int $start - The staring point
	 * @param int $limit - The number of items to query
	 */
	public function setLimit($start = null, $limit = null)
	{
		if ($start !== null)
			$this->_queryParams['start'] = (int) $start;

		if ($limit !== null)
		{
			$this->_queryParams['limit'] = (int) $limit;
			$this->_queryParams['maxindex'] = empty($this->_queryParams['limit']) ? $this->_queryParams['totals'] : $this->_queryParams['limit'];
		}
	}

	/**
	 * Validates the options passed to the constructor and ensure default
	 * values
	 *
	 * @param mixed[] $options - the possible options
	 */
	protected function _validateOptions($options)
	{
		global $modSettings;

		$defaults = array(
			'id' => 'list_' . md5(rand()),
			'items_per_page' => $modSettings['defaultMaxMessages'],
			'no_items_label' => 'no_items', // @todo add a generic txt
			'no_items_align' => 'center', // @deprecated?
// 			'default_sort_col' => //@todo use the first column
			'form' => array(),
			'list_menu' => array(),
			'data_check' => null,
			'start_var_name' => 'start',
			'use_fake_ascending' => false,
			'totals' => 0,
			'allowed_sortings' => array()
// 			'default_sort_dir' => // @todo pick the default
		);

		assert(isset($options['allowed_sortings']));

		$this->_listOptions = array_merge($defaults, $options);
	}

	/**
	 * Initialize some of the parameters based on the options passed to the
	 * constructor.
	 */
	protected function _init()
	{
		$this->_queryParams['sort'] = '';
		$this->sortBy($this->_listOptions['default_sort_col']);

		$this->setLimit(0, $this->_listOptions['items_per_page']);
		$this->_queryParams['maxindex'] = empty($this->_queryParams['limit']) ? $this->_queryParams['totals'] : $this->_queryParams['limit'];
	}

	/**
	 * Verifies the sorting method requested is known by the object.
	 *
	 * @param string $sort - A sorting index
	 * @return bool - true if the method is valid, false otherwise
	 */
	protected function _validSort($sort)
	{
		return !empty($this->_listOptions['allowed_sortings']) && isset($this->_listOptions['allowed_sortings'][$sort]);
	}

	/**
	 * Does the replacements necessary in order to extend the query
	 * See List_Abstract::extendQuery for details.
	 *
	 * @param string $query - the query string
	 */
	protected function _queryString($query)
	{
		// @todo 'group' is probably useless
		$replacements = array(
			'select' => array('pre' => ', ', 'implode' => ', ', 'post' => ''),
			'join' => array('pre' => "\n", 'implode' => "\n\t\t\t", 'post' => "\n"),
			'where' => array('pre' => ' ', 'implode' => ' ', 'post' => ' '),
			'group' => array('pre' => '', 'implode' => ' ', 'post' => '')
		);
		foreach ($replacements as $statement => $joins)
		{
			if (!empty($this->_queryExtended[$statement]))
				$replace = $this->_queryExtended[$statement];
			else
			{
				$replace = array();
				$joins = array('pre' => '', 'implode' => '', 'post' => '');
			}

			$query = str_replace('{query_extend_' . $statement . '}', $joins['pre'] . implode($joins['implode'], $replace) . $joins['post'], $query);
		}

		return $query;
	}

	/**
	 * Actually performs the query and loads the request into
	 * List_Abstract::$_listRequest.
	 *
	 * Takes care of adjusting the sorting if fake_ascending is necessary,
	 * uses List_Abstract::_queryString to allow extending the query.
	 *
	 * @param string $query - the query string
	 * @param string $identifier - the query string
	 * @return bool - true if the query succeed, false if it fails
	 */
	protected function _doQuery($query, $identifier = '')
	{
		if ($this->_listRequest !== null)
			return;

		if (!empty($this->_listOptions['use_fake_ascending']))
			$this->_adjustFakeSorting();

		$this->_listRequest = $this->_db->query($identifier, $this->_queryString($query), $this->_queryParams);

		return $this->_listRequest !== false;
	}

	/**
	 * For performances' sake, it may be worth sorting the results of the
	 * query upside-down in order to simplify the LIMIT job.
	 * This function determines if it is necessary or not and adjust
	 * the parameters accordingly.
	 */
	protected function _adjustFakeSorting()
	{
		// Calculate the fastest way to get the topics.
		$start = $this->_queryParams['start'];
		$totals = $this->_listOptions['totals'];

		if ($start > ($totals - 1) / 2)
		{
			$this->_fake_ascending = true;

			if ($totals < $start + $this->_queryParams['maxindex'] + 1)
			{
				$this->_queryParams['maxindex'] = $totals - $start;
				$this->_queryParams['start'] = 0;
			}
			else
			{
				$this->_queryParams['start'] = $totals - $start - $this->_queryParams['maxindex'];
			}
		}
	}
}