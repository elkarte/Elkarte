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
class List_Abstract
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
// 			'request_vars' // @todo looks like a "private" setting
// 				'sort'
// 				'desc'
// 			'get_items'
// 				'function'
// 			'get_count'
// 				'file'
// 				'function'
		);

		assert(isset($options['allowed_sortings']));

		$this->_listOptions = array_merge($defaults, $options);
	}

	protected function _init()
	{
		$this->_queryParams['sort'] = '';
		$this->sortBy($this->_listOptions['default_sort_col']);

		$this->setLimit(0, $this->_listOptions['items_per_page']);
		$this->_queryParams['maxindex'] = empty($this->_queryParams['limit']) ? $this->_queryParams['totals'] : $this->_queryParams['limit'];
	}

	public function sortBy($sort, $descending = null)
	{
		if ($this->_validSort($sort))
			$this->_queryParams['sort'] = $sort;

		if ($descending !== null)
			$this->_queryParams['sort_desc'] = (bool) $descending;
	}

	public function getSort($dir = false)
	{
		if ($dir)
			return !empty($this->_queryParams['sort_desc']);
		else
			return $this->_queryParams['sort'];
	}

	public function getStart()
	{
		return $this->_queryParams['start'];
	}

	public function addQueryParam($key, $val)
	{
		// @todo should test if set?
		$this->_queryParams[$key] = $val;
	}

	protected function _validSort($sort)
	{
		return !empty($this->_listOptions['allowed_sortings']) && isset($this->_listOptions['allowed_sortings'][$sort]);
	}

	public function extendQuery($select = '', $join = '', $where = '', $group = '')
	{
		foreach (array('select', 'join', 'where', 'group') as $item)
		{
			if (!empty($$item))
				$this->_queryExtended[$item][] = $$item;
		}
	}

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

// 	public function getResults()//$query, $return = true)
// 	{
// // 		if ($return !== true && $this->_fetching)
// // 			return $this->getNext();
// 
// // 		$this->_doQuery($query);
// 
// 		$results = array();
// 
// 		while ($row = $this->_db->fetch_assoc($this->_listRequest))
// 			$results[] = $row;
// 
// 		return $results;
// 	}

	public function getPagination($base_url, $totals, $flexible)
	{
		return constructPageIndex($base_url, $this->_queryParams['start'], $totals, empty($this->_queryParams['limit']) ? $totals : $this->_queryParams['limit'], $flexible);
	}

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

	protected function _doQuery($query, $identifier = '')
	{
		if ($this->_listRequest !== null)
			return;

		if (!empty($this->_listOptions['use_fake_ascending']))
			$this->_adjustFakeSorting();

		$this->_listRequest = $this->_db->query($identifier, $this->_queryString($query), $this->_queryParams);

		return $this->_listRequest !== false;
	}

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

// 	public function getNext()
// 	{
// 		$this->_fetching = true;
// 
// 		return $this->_db->fetch_assoc($this->_listRequest);
// 	}
}