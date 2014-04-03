<?php

/**
 * Performs a search in an array of settings
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Beta 2
 *
 */

/**
 * Perform a search in the admin settings (and maybe other settings as well)
 *
 */
class Settings_Search
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
	 * @param string[] $settings_search - Settings to search in (see
	 *                 _load_settings for details on the structure)
	 */
	public function __construct($language_files = array(), $include_files = array(), $settings_search = array())
	{
		if (!empty($language_files))
			loadLanguage(implode('+', $language_files));
		if (!empty($include_files))
			$this->_include_files($include_files);
		if (!empty($settings_search))
			$this->_settings = $this->_load_settings($settings_search);
	}

	/**
	 * Initialize the search populating the array to search in
	 *
	 * @param string $menu_name - The name of the menu to look into
	 * @param string[] $additional_settings - Possible additional settings
	 *                 (see _load_settings for the array structure)
	 */
	public function initSearch($menu_name, $additional_settings = array())
	{
		$this->_menu_name = $menu_name;

		/* This is the huge array that defines everything ... it's items are formatted as follows:
			0 = Language index (Can be array of indexes) to search through for this setting.
			1 = URL for this indexes page.
			2 = Help index for help associated with this item (If different from 0)
		*/

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
	 * @return string[] - an array of search results with 4 indexes:
	 *                     - url
	 *                     - name
	 *                     - type
	 *                     - help
	 */
	public function doSearch($search_term)
	{
		global $txt, $scripturl, $context, $helptxt;

		$search_results = array();

		foreach ($this->_search_data as $section => $data)
		{
			foreach ($data as $item)
			{
				$found = $this->_find_term($search_term, $item);

				if ($found)
				{
					// Format the name - and remove any descriptions the entry may have.
					$name = isset($txt[$found]) ? $txt[$found] : (isset($txt['setting_' . $found]) ? $txt['setting_' . $found] : $found);
					$name = preg_replace('~<(?:div|span)\sclass="smalltext">.+?</(?:div|span)>~', '', $name);

					if (!empty($name))
						$search_results[] = array(
							'url' => (substr($item[1], 0, 4) == 'area' ? $scripturl . '?action=admin;' . $item[1] : $item[1]) . ';' . $context['session_var'] . '=' . $context['session_id'] . ((substr($item[1], 0, 4) == 'area' && $section == 'settings' ? '#' . $item[0][0] : '')),
							'name' => $name,
							'type' => $section,
							'help' => shorten_text(isset($item[2]) ? strip_tags($helptxt[$item[2]]) : (isset($helptxt[$found]) ? strip_tags($helptxt[$found]) : ''), 255),
						);
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
	 * @return false|string - False if $search_term is not found, the $item (or
	 *                        or one of its temrs if an array) in which it has
	 *                        been found
	 */
	protected function _find_term($search_term, $item)
	{
		global $txt;

		$found = false;

		if (!is_array($item[0]))
			$item[0] = array($item[0]);
		foreach ($item[0] as $term)
		{
			if (stripos($term, $search_term) !== false || (isset($txt[$term]) && stripos($txt[$term], $search_term) !== false) || (isset($txt['setting_' . $term]) && stripos($txt['setting_' . $term], $search_term) !== false))
			{
				$found = $term;
				break;
			}
		}

		return $found;
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
				$controller = new $setting_area[2]();
				$config_vars = $controller->{$setting_area[0]}();
			}
			else
			{
				// a good ole' procedural controller: get the settings from the function.
				$config_vars = $setting_area[0](true);
			}

			foreach ($config_vars as $var)
				if (!empty($var[1]) && !in_array($var[0], array('permissions', 'switch', 'warning')))
					$settings[] = array($var[(isset($var[2]) && in_array($var[2], array('file', 'db'))) ? 0 : 1], $setting_area[1]);
		}

		return $settings;
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
					foreach ($menu_item['subsections'] as $key => $sublabel)
					{
						if (isset($sublabel['label']))
							$sections[] = array($sublabel['label'], 'area=' . $menu_key . ';sa=' . $key);
					}
			}
		}

		return $sections;
	}
}