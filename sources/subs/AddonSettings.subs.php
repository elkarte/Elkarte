<?php

/**
 * Functions to support addon settings controller
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

/**
 * Gets all of the files in a directory and its children directories
 *
 * @package AddonSettings
 * @param string $dir_path
 * @return array
 */
function get_files_recursive($dir_path)
{
	$files = array();

	try
	{
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($dir_path, \RecursiveDirectoryIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::SELF_FIRST,
			\RecursiveIteratorIterator::CATCH_GET_CHILD
		);

		foreach ($iterator as $file)
		{
			if ($file->isFile())
				$files[] = array('dir' => $file->getPath(), 'name' => $file->getFilename());
		}
	}
	catch (UnexpectedValueException $e)
	{
		// @todo, give them a prize
	}

	return $files;
}

/**
 * Callback function for the integration hooks list (list_integration_hooks)
 *
 * What it does:
 *
 * - Gets all of the hooks in the system and their status
 * - Would be better documented if Ema was not lazy
 *
 * @package AddonSettings
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page  The number of items to show per page
 * @param string $sort A string indicating how to sort the results
 * @return array
 */
function list_integration_hooks_data($start, $items_per_page, $sort)
{
	global $txt, $context, $scripturl;

	require_once(SUBSDIR . '/Package.subs.php');

	$hooks = $temp_hooks = get_integration_hooks();
	$hooks_data = $temp_data = $hook_status = array();

	$files = get_files_recursive(SOURCEDIR);
	if (!empty($files))
	{
		foreach ($files as $file)
		{
			if (is_file($file['dir'] . '/' . $file['name']) && substr($file['name'], -4) === '.php')
			{
				$fp = fopen($file['dir'] . '/' . $file['name'], 'rb');
				$fc = strtr(fread($fp, max(filesize($file['dir'] . '/' . $file['name']), 1)), array("\r" => '', "\n" => ''));
				fclose($fp);

				foreach ($temp_hooks as $hook => $functions)
				{
					foreach ($functions as $function_o)
					{
						$hook_name = str_replace(']', '', $function_o);

						if (strpos($hook_name, '::') !== false)
						{
							$function = explode('::', $hook_name);
							$class = $function[0];
							$function = $function[1];
						}
						else
						{
							$class = '';
							$function = $hook_name;
						}

						$function = explode('|', $function);
						$function = $function[0];

						if (substr($hook, -8) === '_include')
						{
							$real_path = parse_path(trim($hook_name));

							if ($real_path == $hook_name)
								$hook_status[$hook][$hook_name]['exists'] = false;
							else
								$hook_status[$hook][$hook_name]['exists'] = file_exists(parse_path(ltrim($real_path, '|')));

							// I need to know if there is at least one function called in this file.
							$temp_data['include'][basename($function)] = array('hook' => $hook, 'function' => $function);
							unset($temp_hooks[$hook][$function_o]);
						}
						// Procedural functions as easy
						elseif (empty($class) && strpos(str_replace(' (', '(', $fc), 'function ' . trim($function) . '(') !== false)
						{
							$hook_status[$hook][$hook_name]['exists'] = true;
							$hook_status[$hook][$hook_name]['in_file'] = $file['name'];

							// I want to remember all the functions called within this file (to check later if they are
							// enabled or disabled and decide if the integrate_*_include of that file can be disabled too)
							$temp_data['function'][$file['name']][] = $function_o;
							unset($temp_hooks[$hook][$function_o]);
						}
						// OOP a bit more difficult
						elseif (!empty($class) && preg_match('~class\s*' . preg_quote(trim($class)) . '.*function\s*' . preg_quote(trim($function), '~') . '\s*\(~i', $fc) != 0)
						{
							$hook_status[$hook][$hook_name]['exists'] = true;
							$hook_status[$hook][$hook_name]['in_file'] = $file['name'];

							// I want to remember all the functions called within this file (to check later if they are
							// enabled or disabled and decide if the integrate_*_include of that file can be disabled too)
							$temp_data['function'][$file['name']][] = $function_o;
							unset($temp_hooks[$hook][$function_o]);
						}
					}
				}
			}
		}
	}

	$sort_types = array(
		'hook_name' => array('hook_name', SORT_ASC),
		'hook_name DESC' => array('hook_name', SORT_DESC),
		'function_name' => array('function_name', SORT_ASC),
		'function_name DESC' => array('function_name', SORT_DESC),
		'file_name' => array('file_name', SORT_ASC),
		'file_name DESC' => array('file_name', SORT_DESC),
		'status' => array('status', SORT_ASC),
		'status DESC' => array('status', SORT_DESC),
	);

	$sort_options = $sort_types[$sort];
	$sort = array();
	$hooks_filters = array();

	foreach ($hooks as $hook => $functions)
	{
		$hooks_filters[] = '<option ' . ($context['current_filter'] == $hook ? 'selected="selected" ' : '') . ' value="' . $hook . '">' . $hook . '</option>';
		foreach ($functions as $function)
		{
			$function = str_replace(']', '', $function);

			// This is a not an include and the function is included in a certain file (if not it doesn't exists so don't care)
			if (substr($hook, -8) !== '_include' && isset($hook_status[$hook][$function]['in_file']))
			{
				$current_hook = isset($temp_data['include'][$hook_status[$hook][$function]['in_file']]) ? $temp_data['include'][$hook_status[$hook][$function]['in_file']] : '';
				$enabled = false;

				// Checking all the functions within this particular file
				// if any of them is enable then the file *must* be included and the integrate_*_include hook cannot be disabled
				foreach ($temp_data['function'][$hook_status[$hook][$function]['in_file']] as $func)
					$enabled = $enabled || strstr($func, ']') !== false;

				if (!$enabled && !empty($current_hook))
					$hook_status[$current_hook['hook']][$current_hook['function']]['enabled'] = true;
			}
		}
	}

		theme()->addInlineJavascript('
			var hook_name_header = document.getElementById(\'header_list_integration_hooks_hook_name\');
			hook_name_header.innerHTML += ' . JavaScriptEscape('
				<select onchange="window.location = \'' . $scripturl . '?action=admin;area=maintain;sa=hooks\' + (this.value ? \';filter=\' + this.value : \'\');">
					<option>---</option>
					<option value="">' . $txt['hooks_reset_filter'] . '</option>' . implode('', $hooks_filters) . '</select>' . '
				</select>') . ';', true);

	$temp_data = array();
	$id = 0;

	foreach ($hooks as $hook => $functions)
	{
		if (empty($context['filter']) || (!empty($context['filter']) && $context['filter'] == $hook))
		{
			foreach ($functions as $function)
			{
				$enabled = strstr($function, ']') === false;
				$function = str_replace(']', '', $function);
				$hook_exists = !empty($hook_status[$hook][$function]['exists']);

				if (strpos($function, '::') !== false)
				{
					$function = explode('::', $function);
					$function = $function[1];
				}

				$exploded = explode('|', $function);

				$temp_data[] = array(
					'id' => 'hookid_' . ($id++),
					'hook_name' => $hook,
					'function_name' => $function,
					'real_function' => $exploded[0],
					'included_file' => isset($exploded[1]) ? parse_path(trim($exploded[1])) : '',
					'file_name' => (isset($hook_status[$hook][$function]['in_file']) ? $hook_status[$hook][$function]['in_file'] : ''),
					'hook_exists' => $hook_exists,
					'status' => $hook_exists ? ($enabled ? 'allow' : 'moderate') : 'deny',
					'img_text' => $txt['hooks_' . ($hook_exists ? ($enabled ? 'active' : 'disabled') : 'missing')],
					'enabled' => $enabled,
					'can_be_disabled' => false,
				);

				// Build the array of sort to values
				$sort_end = end($temp_data);
				$sort[] = $sort_end[$sort_options[0]];
			}
		}
	}

	array_multisort($sort, $sort_options[1], $temp_data);

	$counter = 0;
	$start++;

	foreach ($temp_data as $data)
	{
		if (++$counter < $start)
			continue;
		elseif ($counter === $start + $items_per_page)
			break;

		$hooks_data[] = $data;
	}

	return $hooks_data;
}

/**
 * Simply returns the total count of integration hooks
 *
 * What it does:
 *
 * - used by createList() as a callback to determine the number of hooks in
 * use in the system
 *
 * @package AddonSettings
 *
 * @param boolean $filter
 *
 * @return int
 */
function integration_hooks_count($filter = false)
{
	$hooks = get_integration_hooks();
	$hooks_count = 0;

	foreach ($hooks as $hook => $functions)
	{
		if (empty($filter) || ($filter == $hook))
			$hooks_count += count($functions);
	}

	return $hooks_count;
}

/**
 * Parses modSettings to create integration hook array
 *
 * What it does:
 *
 * - used by createList() callbacks
 *
 * @package AddonSettings
 * @staticvar type $integration_hooks
 * @return array
 */
function get_integration_hooks()
{
	global $modSettings;
	static $integration_hooks = null;

	if ($integration_hooks === null)
	{
		$integration_hooks = array();
		foreach ($modSettings as $key => $value)
		{
			if (!empty($value) && substr($key, 0, 10) === 'integrate_')
				$integration_hooks[$key] = explode(',', $value);
		}
	}

	return $integration_hooks;
}
