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
 * @version 1.1
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
	protected $listOptions = array();

	/**
	 * Instance of HttpReq
	 * @var HttpReq
	 */
	protected $req;

	/**
	 * Will hold the created $context
	 * @var array
	 */
	protected $context = array();

	/**
	 * @var array
	 */
	protected $listItems = array();

	/**
	 * @var string
	 */
	protected $sort = '';

	/**
	 * @var string
	 */
	protected $sortVar = '';

	/**
	 * @var string
	 */
	protected $descVar = '';

	/**
	 * Generic_List constructor, Starts a new list
	 * Makes sure the passed list contains the minimum needed options to create a list
	 * Loads the options in to this instance
	 *
	 * @param array $listOptions
	 * @throws Elk_Exception
	 */
	public function __construct(array $listOptions)
	{
		// Access to post/get data
		$this->req = HttpReq::instance();

		// First make sure the array is constructed properly.
		$this->validateListOptions($listOptions);

		// Now that we've done that, let's set it, we're gonna need it!
		$this->listOptions = $listOptions;

		// Be ready for those pesky errors
		theme()->getTemplates()->loadLanguageFile('Errors');

		// Load the template
		theme()->getTemplates()->load('GenericList');
	}

	/**
	 * Validate the options sent
	 *
	 * @param mixed[] $listOptions
	 */
	protected function validateListOptions($listOptions)
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
	 * Prepare the template by loading context
	 * variables for each setting.
	 */
	protected function prepareContext()
	{
		global $context;

		$context[$this->listOptions['id']] = $this->context;

		// Let's set some default that could be useful to avoid repetitions
		if (!isset($context['sub_template']))
		{
			if (function_exists('template_' . $this->listOptions['id']))
				$context['sub_template'] = $this->listOptions['id'];
			else
			{
				$context['sub_template'] = 'show_list';
				if (!isset($context['default_list']))
					$context['default_list'] = $this->listOptions['id'];
			}
		}
	}

	/**
	 * Make the list.
	 * The list will be populated in $context.
	 */
	public function buildList()
	{
		$this->prepareSort();
		$this->calculatePages();
		$this->prepareHeaders();
		$this->prepareColumns();
		$this->setTitle();
		$this->prepareForm();
		$this->prepareNoItemsLabel();
		$this->prepareAdditionalRows();
		$this->prepareJavascript();
		$this->prepareMenu();
		$this->prepareContext();
	}

	/**
	 * Figure out the sorting method.
	 */
	protected function prepareSort()
	{
		if (empty($this->listOptions['default_sort_col']))
		{
			$this->context['sort'] = array();
			$this->sort = '1=1';
		}
		else
		{
			$this->sortVar = isset($this->listOptions['request_vars']['sort']) ? $this->listOptions['request_vars']['sort'] : 'sort';
			$this->descVar = isset($this->listOptions['request_vars']['desc']) ? $this->listOptions['request_vars']['desc'] : 'desc';
			$sortReq = $this->req->getQuery($this->sortVar);

			if (isset($this->listOptions['columns'][$sortReq], $this->listOptions['columns'][$sortReq]['sort']))
			{
					$this->context['sort'] = array(
					'id' => $sortReq,
					'desc' => isset($_REQUEST[$this->descVar]) && isset($this->listOptions['columns'][$sortReq]['sort']['reverse']),
				);
			}
			else
			{
				$this->context['sort'] = array(
					'id' => $this->listOptions['default_sort_col'],
					'desc' => (!empty($this->listOptions['default_sort_dir']) && $this->listOptions['default_sort_dir'] == 'desc') || (!empty($this->listOptions['columns'][$this->listOptions['default_sort_col']]['sort']['default']) && substr($this->listOptions['columns'][$this->listOptions['default_sort_col']]['sort']['default'], -4, 4) == 'desc'),
				);
			}

			// Set the database column sort.
			$this->sort = $this->listOptions['columns'][$this->context['sort']['id']]['sort'][$this->context['sort']['desc'] ? 'reverse' : 'default'];
		}

		$this->context['start_var_name'] = isset($this->listOptions['start_var_name']) ? $this->listOptions['start_var_name'] : 'start';
	}

	/**
	 * Calculate the page index.
	 */
	protected function calculatePages()
	{
		// In some cases the full list must be shown, regardless of the amount of items.
		if (empty($this->listOptions['items_per_page']))
		{
			$this->context['start'] = 0;
			$this->context['items_per_page'] = 0;
		}
		// With items per page set, calculate total number of items and page index.
		else
		{
			// First get an impression of how many items to expect.
			if (isset($this->listOptions['get_count']['file']))
				require_once($this->listOptions['get_count']['file']);

			$this->context['total_num_items'] = call_user_func_array($this->listOptions['get_count']['function'], empty($this->listOptions['get_count']['params']) ? array() : $this->listOptions['get_count']['params']);

			// Default the start to the beginning... sounds logical, amirite?
			$this->context['start'] = $this->req->getQuery($this->context['start_var_name'], 'intval', 0);
			$this->context['items_per_page'] = $this->listOptions['items_per_page'];

			// Then create a page index.
			if ($this->context['total_num_items'] > $this->context['items_per_page'])
				$this->context['page_index'] = constructPageIndex($this->listOptions['base_href'] . (empty($this->context['sort']) ? '' : ';' . $this->sortVar . '=' . $this->context['sort']['id'] . ($this->context['sort']['desc'] ? ';' . $this->descVar : '')) . ($this->context['start_var_name'] != 'start' ? ';' . $this->context['start_var_name'] . '=%1$d' : ''), $this->context['start'], $this->context['total_num_items'], $this->context['items_per_page'], $this->context['start_var_name'] != 'start');
		}
	}

	/**
	 * Prepare the headers of the table.
	 */
	protected function prepareHeaders()
	{
		$this->context['headers'] = array();
		foreach ($this->listOptions['columns'] as $column_id => $column)
		{
			if (isset($column['evaluate']) && $column['evaluate'] === false)
			{
				continue;
			}

			$this->context['headers'][] = array(
				'id' => $column_id,
				'label' => isset($column['header']['value']) ? $column['header']['value'] : '',
				'href' => empty($this->listOptions['default_sort_col']) || empty($column['sort']) ? '' : $this->listOptions['base_href'] . ';' . $this->sortVar . '=' . $column_id . ($column_id === $this->context['sort']['id'] && !$this->context['sort']['desc'] && isset($column['sort']['reverse']) ? ';' . $this->descVar : '') . (empty($this->context['start']) ? '' : ';' . $this->context['start_var_name'] . '=' . $this->context['start']),
				'sort_image' => empty($this->listOptions['default_sort_col']) || empty($column['sort']) || $column_id !== $this->context['sort']['id'] ? null : ($this->context['sort']['desc'] ? 'down' : 'up'),
				'class' => isset($column['header']['class']) ? $column['header']['class'] : '',
				'style' => isset($column['header']['style']) ? $column['header']['style'] : '',
				'colspan' => isset($column['header']['colspan']) ? $column['header']['colspan'] : '',
			);
		}
	}

	/**
	 * Prepare columns.
	 */
	protected function prepareColumns()
	{
		// We know the amount of columns, might be useful for the template.
		$this->context['num_columns'] = count($this->listOptions['columns']);
		$this->context['width'] = isset($this->listOptions['width']) ? $this->listOptions['width'] : '0';

		// Maybe we want this one to interact with jquery UI sortable
		$this->context['sortable'] = isset($this->listOptions['sortable']);

		// Get the file with the function for the item list.
		if (isset($this->listOptions['get_items']['file']))
			require_once($this->listOptions['get_items']['file']);

		// Call the function and include which items we want and in what order.
		$this->listItems = call_user_func_array($this->listOptions['get_items']['function'], array_merge(array($this->context['start'], $this->context['items_per_page'], $this->sort), empty($this->listOptions['get_items']['params']) ? array() : $this->listOptions['get_items']['params']));
		$this->listItems = !empty($this->listItems) ? $this->listItems : array();

		$this->loopItems();
	}

	/**
	 * Build the data values for the column
	 */
	protected function loopItems()
	{
		// Loop through the list items to be shown and construct the data values.
		$this->context['rows'] = array();
		foreach ($this->listItems as $item_id => $list_item)
		{
			$cur_row = array();
			foreach ($this->listOptions['columns'] as $column_id => $column)
			{
				if (isset($column['evaluate']) && $column['evaluate'] === false)
				{
					$this->context['num_columns']--;
					continue;
				}

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
			$this->context['rows'][$item_id]['class'] = '';
			$this->context['rows'][$item_id]['style'] = '';

			// Maybe we wat set a custom class for the row based on the data in the row itself
			if (isset($this->listOptions['data_check']))
			{
				if (isset($this->listOptions['data_check']['class']))
					$this->context['rows'][$item_id]['class'] = ' ' . $this->listOptions['data_check']['class']($list_item);

				if (isset($this->listOptions['data_check']['style']))
					$this->context['rows'][$item_id]['style'] = ' style="' . $this->listOptions['data_check']['style']($list_item) . '"';
			}

			// Insert the row into the list.
			$this->context['rows'][$item_id]['data'] = $cur_row;
		}
	}

	/**
	 * Prepare the title (optional).
	 */
	protected function setTitle()
	{
		// The title is currently optional.
		if (isset($this->listOptions['title']))
		{
			$this->context['title'] = $this->listOptions['title'];

			// And the icon is optional for the title
			if (isset($this->listOptions['icon']))
				$this->context['icon'] = $this->listOptions['icon'];
		}
	}

	/**
	 * Prepare a form (optional) with hidden fields.
	 *
	 * Session check is added automatically. Both a token and a page identifier are optional.
	 */
	protected function prepareForm()
	{
		global $context;

		// In case there's a form, share it with the template context.
		if (isset($this->listOptions['form']))
		{
			$this->context['form'] = $this->listOptions['form'];

			if (!isset($this->context['form']['hidden_fields']))
				$this->context['form']['hidden_fields'] = array();

			// Always add a session check field.
			$this->context['form']['hidden_fields'][$context['session_var']] = $context['session_id'];

			// Will this do a token check?
			if (isset($this->listOptions['form']['token']))
				$this->context['form']['hidden_fields'][$context[$this->listOptions['form']['token'] . '_token_var']] = $context[$this->listOptions['form']['token'] . '_token'];

			// Include the starting page as hidden field?
			if (!empty($this->context['form']['include_start']) && !empty($this->context['start']))
				$this->context['form']['hidden_fields'][$this->context['start_var_name']] = $this->context['start'];

			// If sorting needs to be the same after submitting, add the parameter.
			if (!empty($this->context['form']['include_sort']) && !empty($this->context['sort']))
			{
				$this->context['form']['hidden_fields']['sort'] = $this->context['sort']['id'];

				if ($this->context['sort']['desc'])
					$this->context['form']['hidden_fields']['desc'] = 1;
			}
		}
	}

	/**
	 * Say something nice in case there are no items.
	 */
	protected function prepareNoItemsLabel()
	{
		if (isset($this->listOptions['no_items_label']))
		{
			$this->context['no_items_label'] = $this->listOptions['no_items_label'];
			$this->context['no_items_align'] = isset($this->listOptions['no_items_align']) ? $this->listOptions['no_items_align'] : '';
		}
	}

	/**
	 * A list can sometimes need a few extra rows above and below.
	 *
	 * Supported row positions: top_of_list, after_title, selectors,
	 * above_column_headers, below_table_data, bottom_of_list.
	 */
	protected function prepareAdditionalRows()
	{
		if (isset($this->listOptions['additional_rows']))
		{
			$this->context['additional_rows'] = array();
			foreach ($this->listOptions['additional_rows'] as $row)
			{
				if (empty($row))
					continue;

				if (!isset($this->context['additional_rows'][$row['position']]))
					$this->context['additional_rows'][$row['position']] = array();

				$this->context['additional_rows'][$row['position']][] = $row;
			}
		}
	}

	/**
	 * Add an option for inline JavaScript.
	 */
	protected function prepareJavascript()
	{
		if (isset($this->listOptions['javascript']))
			theme()-addInlineJavascript($this->listOptions['javascript'], true);
	}

	/**
	 * We want a menu.
	 */
	protected function prepareMenu()
	{
		if (isset($this->listOptions['list_menu']))
		{
			if (!isset($this->listOptions['list_menu']['position']))
				$this->listOptions['list_menu']['position'] = 'left';

			$this->context['list_menu'] = $this->listOptions['list_menu'];
		}
	}
}
