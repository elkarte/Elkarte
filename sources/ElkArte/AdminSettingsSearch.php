<?php

/**
 * Performs a search in an array of settings
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

/**
 * Perform a search in the admin settings (and maybe other settings as well)
 */
class AdminSettingsSearch
{
	/**
	 * All the settings we have found
	 * @var array
	 */
	protected $_search_data;

	/**
	 * Sections are supposed to be stored in a menu,
	 * and the menu is in $context['menu_name']
	 * @var string
	 */
	protected $_menu_name;

	/**
	 * An array of settings used in the search
	 * @var string[]
	 */
	protected $_settings = array();

	/**
	 * Constructor!
	 *
	 * @param string[] $language_files - Language file names
	 * @param string[] $include_files - File names to include (see _include_files
	 *                 for details on the structure)
	 * @param mixed[] $settings_search - Settings to search in (see
	 *                 _load_settings for details on the structure)
	 */
	public function __construct($language_files = array(), $include_files = array(), $settings_search = array())
	{
		if (!empty($language_files))
		{
			theme()->getTemplates()->loadLanguageFile(implode('+', $language_files));
		}

		if (!empty($include_files))
		{
			$this->_include_files($include_files);
		}

		if (!empty($settings_search))
		{
			$this->_settings = $this->_load_settings($settings_search);
		}
	}

	/**
	 * Initialize the search populating the array to search in
	 *
	 * @param string $menu_name - The name of the menu to look into
	 * @param array $additional_settings - Possible additional settings
	 *                 (see _load_settings for the array structure)
	 */
	public function initSearch($menu_name, $additional_settings = array())
	{
		$this->_menu_name = $menu_name;

		// This is the huge array that defines everything ... it's items are formatted as follows:
		//	0 = Language index (Can be array of indexes) to search through for this setting.
		//	1 = URL for this indexes page.
		//	2 = Help index for help associated with this item (If different from 0)
		$this->_search_data = array(
			// All the major sections of the forum.
			'sections' => $this->_load_search_sections(),
			'settings' => array_merge(
				$additional_settings,
				$this->_settings
			),
		);
	}

	/**
	 * Actually perform the search of a term in the array
	 *
	 * @param string $search_term - The term to search
	 *
	 * @return string[] - an array of search results with 4 indexes:
	 *                     - url
	 *                     - name
	 *                     - type
	 *                     - help
	 */
	public function doSearch($search_term)
	{
		global $scripturl, $context;

		$search_results = array();

		foreach ($this->_search_data as $section => $data)
		{
			foreach ($data as $item)
			{
				$search_result = $this->_find_term($search_term, $item);

				if (!empty($search_result))
				{
					$search_result['type'] = $section;
					$search_result['url'] = $item[1] . ';' . $context['session_var'] . '=' . $context['session_id'];

					if (substr($item[1], 0, 4) == 'area')
					{
						$search_result['url'] = $scripturl . '?action=admin;' . $search_result['url'] . ($section == 'settings' && !empty($item['named_link']) ? '#' . $item['named_link'] : '');
					}

					$search_results[] = $search_result;
				}
			}
		}

		return $search_results;
	}

	/**
	 * Find a term inside an $item
	 *
	 * @param string $search_term - The term to search
	 * @param string|string[] $item - A string or array of strings that may be
	 *                        standalone strings, index for $txt, partial index
	 *                        for $txt['setting_' . $item]
	 *
	 * @return string[] - An empty array if $search_term is not found, otherwise
	 *                    part of the search_result array (consisting of 'name'
	 *                    and 'help') of the term the result was found
	 */
	protected function _find_term($search_term, $item)
	{
		global $helptxt;

		$found = false;
		$return = array();

		if (!is_array($item[0]))
		{
			$item[0] = array($item[0]);
		}

		foreach ($item[0] as $term)
		{
			if (stripos($term, $search_term) !== false)
			{
				$found = $term;
			}
		}

		// Format the name - and remove any descriptions the entry may have.
		if ($found !== false)
		{
			$name = preg_replace('~<(?:div|span)\sclass="smalltext">.+?</(?:div|span)>~', '', $found);

			$return = array(
				'name' => $name,
				'help' => Util::shorten_text(isset($item[2]) ? strip_tags($helptxt[$item[2]]) : (isset($helptxt[$found]) ? strip_tags($helptxt[$found]) : ''), 255),
			);
		}

		return $return;
	}

	/**
	 * Includes a set of files.
	 *
	 * @param string[] $include_files - array of file names (without extension),
	 *                  it's possible to specify an array of arrays instead of an
	 *                  array of strings, in that case the index 0 is the
	 *                  directory of the file, while the 1 is the file name.
	 *                  If a directory is not specified it will default to
	 *                  the value of the constant ADMINDIR
	 *                  e.g.
	 *                  $include_files = array(
	 *                    'file_name.controller',
	 *                    'file_name2.controller',
	 *                    array(
	 *                      'dir_name'
	 *                      'file_name3.controller'
	 *                    )
	 *                  )
	 */
	protected function _include_files($include_files)
	{
		foreach ($include_files as $file)
		{
			if (is_array($file))
			{
				$dir = $file[0];
				$file = $file[1];
			}
			else
			{
				$dir = ADMINDIR;
			}

			require_once($dir . '/' . $file . '.php');
		}
	}

	/**
	 * Loads all the settings
	 *
	 * @param mixed[] $settings_search - An array that defines where to look
	 *                for settings. The structure is:
	 *                array(
	 *                  method name
	 *                  url
	 *                  controller name
	 *                )
	 *
	 * @todo move to subs?
	 * @return array
	 */
	private function _load_settings($settings_search)
	{
		$settings = array();

		foreach ($settings_search as $setting_area)
		{
			// Get a list of their variables.
			if (isset($setting_area[2]))
			{
				// an OOP controller: get the settings from the settings method.
				$controller = new $setting_area[2](new EventManager());
				$controller->pre_dispatch();
				$config_vars = $controller->{$setting_area[0]}();
			}
			else
			{
				// a good ole' procedural controller: get the settings from the function.
				$config_vars = $setting_area[0](true);
			}

			foreach ($config_vars as $var)
			{
				if (!empty($var[1]) && !in_array($var[0], array('permissions', 'callback', 'message', 'warning', 'title', 'desc')))
				{
					$settings[] = array($this->_get_label($var), $setting_area[1], 'named_link' => $var[1]);
				}
			}
		}

		return $settings;
	}

	/**
	 * Checks if any configuration settings have label text that should be
	 * included in the search
	 *
	 * @param $var
	 *
	 * @return string
	 */
	private function _get_label($var)
	{
		global $txt;

		// See if there are any labels that might fit?
		if (isset($var[2]) && in_array($var[2], array('file', 'db')))
		{
			$var[1] = $var[0];
		}

		// See if there are any labels that might fit?
		if (isset($var['text_label']))
		{
			$return = $var['text_label'];
		}
		elseif (isset($txt[$var[1]]))
		{
			$return = $txt[$var[1]];
		}
		elseif (isset($txt['setting_' . $var[1]]))
		{
			$return = $txt['setting_' . $var[1]];
		}
		elseif (isset($txt['groups_' . $var[1]]))
		{
			$return = $txt['groups_' . $var[1]];
		}
		else
		{
			$return = $var[1];
		}

		return $return;
	}

	/**
	 * Loads all the admin sections
	 */
	private function _load_search_sections()
	{
		global $context;

		$sections = array();

		// Go through the admin menu structure trying to find suitably named areas!
		foreach ($context[$this->_menu_name]['sections'] as $section)
		{
			foreach ($section['areas'] as $menu_key => $menu_item)
			{
				$sections[] = array($menu_item['label'], 'area=' . $menu_key);
				if (!empty($menu_item['subsections']))
				{
					foreach ($menu_item['subsections'] as $key => $sublabel)
					{
						if (isset($sublabel['label']))
						{
							$sections[] = array($sublabel['label'], 'area=' . $menu_key . ';sa=' . $key);
						}
					}
				}
			}
		}

		return $sections;
	}
}
