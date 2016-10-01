<?php

/**
 * This class implements a standard way of displaying lists.
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

/**
 * This class implements a standard way of displaying lists.
 */
class Generic_List
{
	/**
	 * List options, an array with the format:
	 * 'id'
	 * 'columns'
	 * 'items_per_page'
	 * 'no_items_label'
	 * 'no_items_align'
	 * 'base_href'
	 * 'default_sort_col'
	 * 'form'
	 *   'href'
	 *   'hidden_fields'
	 * 'list_menu'
	 * 'javascript'
	 * 'data_check'
	 * 'start_var_name'
	 * 'default_sort_dir'
	 * 'request_vars'
	 *   'sort'
	 *   'desc'
	 * 'get_items'
	 *   'function'
	 * 'get_count'
	 *   'file'
	 *   'function'
	 * @var array
	 */
	protected $_listOptions;

	/**
	 * Starts a new list
	 * Makes sure the passed list contains the minimum needed options to create a list
	 * Loads the options in to this instance
	 *
	 * @param mixed[] $listOptions
	 */
	public function __construct($listOptions)
	{
		// First make sure the array is constructed properly.
		$this->_validateListOptions($listOptions);

		// Now that we've done that, let's set it, we're gonna need it!
		$this->_listOptions = $listOptions;

		// Be ready for those pesky errors
		loadLanguage('Errors');

		// Load the template
		loadTemplate('GenericList');
	}

	/**
	 * Validate the options sent
	 *
	 * @param mixed[] $listOptions
	 */
	protected function _validateListOptions($listOptions)
	{
		// @todo trigger error here?
		assert(isset($listOptions['id']));
		assert(isset($listOptions['columns']));
		assert(is_array($listOptions['columns']));
		assert((empty($listOptions['items_per_page']) || (isset($listOptions['get_count']['function'], $listOptions['base_href']) && is_numeric($listOptions['items_per_page']))));
		assert((empty($listOptions['default_sort_col']) || isset($listOptions['columns'][$listOptions['default_sort_col']])));
		assert((!isset($listOptions['form']) || isset($listOptions['form']['href'])));
	}

	/**
	 * Make the list.
	 * The list will be populated in $context.
	 */
	public function buildList()
	{
		global $context;

		// All the context data will be easily accessible by using a reference.
		$context[$this->_listOptions['id']] = array();
		$list_context = &$context[$this->_listOptions['id']];

		// Let's set some default that could be useful to avoid repetitions
		if (!isset($context['sub_template']))
		{
			if (function_exists('template_' . $this->_listOptions['id']))
				$context['sub_template'] = $this->_listOptions['id'];
			else
			{
				$context['sub_template'] = 'show_list';
				if (!isset($context['default_list']))
					$context['default_list'] = $this->_listOptions['id'];
			}
		}

		// Figure out the sort.
		if (empty($this->_listOptions['default_sort_col']))
		{
			$list_context['sort'] = array();
			$sort = '1=1';
		}
		else
		{
			$request_var_sort = isset($this->_listOptions['request_vars']['sort']) ? $this->_listOptions['request_vars']['sort'] : 'sort';
			$request_var_desc = isset($this->_listOptions['request_vars']['desc']) ? $this->_listOptions['request_vars']['desc'] : 'desc';

			if (isset($_REQUEST[$request_var_sort], $this->_listOptions['columns'][$_REQUEST[$request_var_sort]], $this->_listOptions['columns'][$_REQUEST[$request_var_sort]]['sort']))
			{
					$list_context['sort'] = array(
					'id' => $_REQUEST[$request_var_sort],
					'desc' => isset($_REQUEST[$request_var_desc]) && isset($this->_listOptions['columns'][$_REQUEST[$request_var_sort]]['sort']['reverse']),
				);
			}
			else
			{
				$list_context['sort'] = array(
					'id' => $this->_listOptions['default_sort_col'],
					'desc' => (!empty($this->_listOptions['default_sort_dir']) && $this->_listOptions['default_sort_dir'] == 'desc') || (!empty($this->_listOptions['columns'][$this->_listOptions['default_sort_col']]['sort']['default']) && substr($this->_listOptions['columns'][$this->_listOptions['default_sort_col']]['sort']['default'], -4, 4) == 'desc') ? true : false,
				);
			}

			// Set the database column sort.
			$sort = $this->_listOptions['columns'][$list_context['sort']['id']]['sort'][$list_context['sort']['desc'] ? 'reverse' : 'default'];
		}

		$list_context['start_var_name'] = isset($this->_listOptions['start_var_name']) ? $this->_listOptions['start_var_name'] : 'start';

		// In some cases the full list must be shown, regardless of the amount of items.
		if (empty($this->_listOptions['items_per_page']))
		{
			$list_context['start'] = 0;
			$list_context['items_per_page'] = 0;
		}
		// With items per page set, calculate total number of items and page index.
		else
		{
			// First get an impression of how many items to expect.
			if (isset($this->_listOptions['get_count']['file']))
				require_once($this->_listOptions['get_count']['file']);

			$list_context['total_num_items'] = call_user_func_array($this->_listOptions['get_count']['function'], empty($this->_listOptions['get_count']['params']) ? array() : $this->_listOptions['get_count']['params']);

			// Default the start to the beginning...sounds logical.
			$list_context['start'] = isset($_REQUEST[$list_context['start_var_name']]) ? (int) $_REQUEST[$list_context['start_var_name']] : 0;
			$list_context['items_per_page'] = $this->_listOptions['items_per_page'];

			// Then create a page index.
			if ($list_context['total_num_items'] > $list_context['items_per_page'])
				$list_context['page_index'] = constructPageIndex($this->_listOptions['base_href'] . (empty($list_context['sort']) ? '' : ';' . $request_var_sort . '=' . $list_context['sort']['id'] . ($list_context['sort']['desc'] ? ';' . $request_var_desc : '')) . ($list_context['start_var_name'] != 'start' ? ';' . $list_context['start_var_name'] . '=%1$d' : ''), $list_context['start'], $list_context['total_num_items'], $list_context['items_per_page'], $list_context['start_var_name'] != 'start');
		}

		// Prepare the headers of the table.
		$list_context['headers'] = array();
		foreach ($this->_listOptions['columns'] as $column_id => $column)
		{
			if (!isset($column['evaluate']) || $column['evaluate'] === true)
			{
				if (isset($column['header']['eval']))
				{
					try
					{
						$label = eval($column['header']['eval']);
					}
					catch (ParseError $e)
					{
						$label = isset($column['header']['value']) ? $column['header']['value'] : '';
					}
				}
				else
				{
					$label = isset($column['header']['value']) ? $column['header']['value'] : '';
				}
				$list_context['headers'][] = array(
					'id' => $column_id,
					'label' => $label,
					'href' => empty($this->_listOptions['default_sort_col']) || empty($column['sort']) ? '' : $this->_listOptions['base_href'] . ';' . $request_var_sort . '=' . $column_id . ($column_id === $list_context['sort']['id'] && !$list_context['sort']['desc'] && isset($column['sort']['reverse']) ? ';' . $request_var_desc : '') . (empty($list_context['start']) ? '' : ';' . $list_context['start_var_name'] . '=' . $list_context['start']),
					'sort_image' => empty($this->_listOptions['default_sort_col']) || empty($column['sort']) || $column_id !== $list_context['sort']['id'] ? null : ($list_context['sort']['desc'] ? 'down' : 'up'),
					'class' => isset($column['header']['class']) ? $column['header']['class'] : '',
					'style' => isset($column['header']['style']) ? $column['header']['style'] : '',
					'colspan' => isset($column['header']['colspan']) ? $column['header']['colspan'] : '',
				);
			}
		}

		// We know the amount of columns, might be useful for the template.
		$list_context['num_columns'] = count($this->_listOptions['columns']);
		$list_context['width'] = isset($this->_listOptions['width']) ? $this->_listOptions['width'] : '0';

		// Maybe we want this one to interact with jquery UI sortable
		$list_context['sortable'] = isset($this->_listOptions['sortable']) ? true : false;

		// Get the file with the function for the item list.
		if (isset($this->_listOptions['get_items']['file']))
			require_once($this->_listOptions['get_items']['file']);

		// Call the function and include which items we want and in what order.
		$list_items = call_user_func_array($this->_listOptions['get_items']['function'], array_merge(array($list_context['start'], $list_context['items_per_page'], $sort), empty($this->_listOptions['get_items']['params']) ? array() : $this->_listOptions['get_items']['params']));
		$list_items = empty($list_items) ? array() : $list_items;

		// Loop through the list items to be shown and construct the data values.
		$list_context['rows'] = array();
		foreach ($list_items as $item_id => $list_item)
		{
			$cur_row = array();
			foreach ($this->_listOptions['columns'] as $column_id => $column)
			{
				if (isset($column['evaluate']) && $column['evaluate'] === false)
					continue;

				$cur_data = array();

				// A value straight from the database?
				if (isset($column['data']['db']))
					$cur_data['value'] = $list_item[$column['data']['db']];
				// Take the value from the database and make it HTML safe.
				elseif (isset($column['data']['db_htmlsafe']))
					$cur_data['value'] = htmlspecialchars($list_item[$column['data']['db_htmlsafe']], ENT_COMPAT, 'UTF-8');
				// Using sprintf is probably the most readable way of injecting data.
				elseif (isset($column['data']['sprintf']))
				{
					$params = array();
					foreach ($column['data']['sprintf']['params'] as $sprintf_param => $htmlsafe)
						$params[] = $htmlsafe ? htmlspecialchars($list_item[$sprintf_param], ENT_COMPAT, 'UTF-8') : $list_item[$sprintf_param];
					$cur_data['value'] = vsprintf($column['data']['sprintf']['format'], $params);
				}
				// The most flexible way probably is applying a custom function.
				elseif (isset($column['data']['function']))
					$cur_data['value'] = $column['data']['function']($list_item);
				// A modified value (inject the database values).
				elseif (isset($column['data']['eval']))
				{
					try
					{
						$cur_data['value'] = eval(preg_replace('~%([a-zA-Z0-9\-_]+)%~', '$list_item[\'$1\']', $column['data']['eval']));
					}
					catch (ParseError $e)
					{
						$cur_data['value'] = '';
					}
				}
				// A literal value.
				elseif (isset($column['data']['value']))
					$cur_data['value'] = $column['data']['value'];
				// Empty value.
				else
					$cur_data['value'] = '';

				// Allow for basic formatting.
				if (!empty($column['data']['comma_format']))
					$cur_data['value'] = comma_format($cur_data['value']);
				elseif (!empty($column['data']['timeformat']))
				{
					// Maybe we need a relative time?
					if ($column['data']['timeformat'] == 'html_time')
						$cur_data['value'] = htmlTime($cur_data['value']);
					else
						$cur_data['value'] = standardTime($cur_data['value']);
				}
				// Set a style class for this column?
				if (isset($column['data']['class']))
					$cur_data['class'] = $column['data']['class'];

				// Fully customized styling for the cells in this column only.
				if (isset($column['data']['style']))
					$cur_data['style'] = $column['data']['style'];

				// Add the data cell properties to the current row.
				$cur_row[$column_id] = $cur_data;
			}

			$list_context['rows'][$item_id]['class'] = '';
			$list_context['rows'][$item_id]['style'] = '';

			// Maybe we wat set a custom class for the row based on the data in the row itself
			if (isset($this->_listOptions['data_check']))
			{
				if (isset($this->_listOptions['data_check']['class']))
					$list_context['rows'][$item_id]['class'] = ' ' . $this->_listOptions['data_check']['class']($list_item);

				if (isset($this->_listOptions['data_check']['style']))
					$list_context['rows'][$item_id]['style'] = ' style="' . $this->_listOptions['data_check']['style']($list_item) . '"';
			}

			// Insert the row into the list.
			$list_context['rows'][$item_id]['data'] = $cur_row;
		}

		// The title is currently optional.
		if (isset($this->_listOptions['title']))
		{
			$list_context['title'] = $this->_listOptions['title'];

			// And the icon is optional for the title
			if (isset($this->_listOptions['icon']))
				$list_context['icon'] = $this->_listOptions['icon'];
		}

		// In case there's a form, share it with the template context.
		if (isset($this->_listOptions['form']))
		{
			$list_context['form'] = $this->_listOptions['form'];

			if (!isset($list_context['form']['hidden_fields']))
				$list_context['form']['hidden_fields'] = array();

			// Always add a session check field.
			$list_context['form']['hidden_fields'][$context['session_var']] = $context['session_id'];

			// Will this do a token check?
			if (isset($this->_listOptions['form']['token']))
				$list_context['form']['hidden_fields'][$context[$this->_listOptions['form']['token'] . '_token_var']] = $context[$this->_listOptions['form']['token'] . '_token'];

			// Include the starting page as hidden field?
			if (!empty($list_context['form']['include_start']) && !empty($list_context['start']))
				$list_context['form']['hidden_fields'][$list_context['start_var_name']] = $list_context['start'];

			// If sorting needs to be the same after submitting, add the parameter.
			if (!empty($list_context['form']['include_sort']) && !empty($list_context['sort']))
			{
				$list_context['form']['hidden_fields']['sort'] = $list_context['sort']['id'];

				if ($list_context['sort']['desc'])
					$list_context['form']['hidden_fields']['desc'] = 1;
			}
		}

		// Wanna say something nice in case there are no items?
		if (isset($this->_listOptions['no_items_label']))
		{
			$list_context['no_items_label'] = $this->_listOptions['no_items_label'];
			$list_context['no_items_align'] = isset($this->_listOptions['no_items_align']) ? $this->_listOptions['no_items_align'] : '';
		}

		// A list can sometimes need a few extra rows above and below.
		if (isset($this->_listOptions['additional_rows']))
		{
			$list_context['additional_rows'] = array();
			foreach ($this->_listOptions['additional_rows'] as $row)
			{
				if (empty($row))
					continue;

				// Supported row positions: top_of_list, after_title, selectors,
				// above_column_headers, below_table_data, bottom_of_list.
				if (!isset($list_context['additional_rows'][$row['position']]))
					$list_context['additional_rows'][$row['position']] = array();

				$list_context['additional_rows'][$row['position']][] = $row;
			}
		}

		// Add an option for inline JavaScript.
		if (isset($this->_listOptions['javascript']))
			addInlineJavascript($this->_listOptions['javascript'], true);

		// We want a menu.
		if (isset($this->_listOptions['list_menu']))
		{
			if (!isset($this->_listOptions['list_menu']['position']))
				$this->_listOptions['list_menu']['position'] = 'left';

			$list_context['list_menu'] = $this->_listOptions['list_menu'];
		}
	}
}