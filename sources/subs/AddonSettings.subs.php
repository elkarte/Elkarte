<?php

/**
 * Functions to support addon settings controller
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 Beta 1
 *
 */

/**
 * Gets all PHP files in a directory and its children directories
 *
 * @param string $dir_path
 * @return array
 * @package AddonSettings
 */
function get_files_recursive($dir_path)
{
	$files = [];
	try
	{
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($dir_path, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::SELF_FIRST,
			RecursiveIteratorIterator::CATCH_GET_CHILD
		);
		foreach ($iterator as $file)
		{
			if ($file->isFile() && pathinfo($file->getFilename(), PATHINFO_EXTENSION) === 'php')
			{
				$files[] = ['dir' => $file->getPath(), 'name' => $file->getFilename()];
			}
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
 * - Gets all the hooks in the system and their status
 * - Would be better documented if Ema was not lazy
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page The number of items to show per page
 * @param string $sort A string indicating how to sort the results
 * @return array
 * @package AddonSettings
 */
function list_integration_hooks_data($start, $items_per_page, $sort)
{
	global $txt, $context, $scripturl;

	require_once(SUBSDIR . '/Package.subs.php');

	$hooks = $temp_hooks = get_integration_hooks();
	$hooks_data = $temp_data = $hook_status = [];

	$files = get_files_recursive(SOURCEDIR);
	$files = array_merge($files, get_files_recursive(ADDONSDIR));

	if (!empty($files))
	{
		foreach ($files as $file)
		{
			$fp = fopen($file['dir'] . '/' . $file['name'], 'rb');
			$fc = strtr(fread($fp, max(filesize($file['dir'] . '/' . $file['name']), 1)), ["\r" => '', "\n" => '']);
			fclose($fp);

			foreach ($temp_hooks as $hook => $functions)
			{
				foreach ($functions as $function_o)
				{
					$hook_name = str_replace(']', '', $function_o);

					if (str_contains($hook_name, '::'))
					{
						[$class, $function] = explode('::', $hook_name);
					}
					else
					{
						$class = '';
						$function = $hook_name;
					}

					$function = explode('|', $function);
					$function = $function[0];

					// If the hook is an include, we need to check if the file exists.
					if (str_ends_with($hook, '_include'))
					{
						$real_path = parse_path(trim($hook_name));

						if ($real_path === $hook_name)
						{
							$hook_status[$hook][$hook_name]['exists'] = false;
						}
						else
						{
							$hook_status[$hook][$hook_name]['exists'] = file_exists(parse_path(ltrim($real_path, '|')));
						}

						// I need to know if there is at least one function called in this file.
						$temp_data['include'][basename($function)] = ['hook' => $hook, 'function' => $function];
						unset($temp_hooks[$hook][$function_o]);
					}
					// Perhaps we are dealing with a generic menu hook
					elseif (str_ends_with($hook, '_areas'))
					{
						$menuPart = str_replace(['integrate_', '_areas'], '', $hook);
						$regex = '~\'hook\'\s*=>\s*\'' . $menuPart . '\'[\s,]*~';

						if (preg_match($regex, $fc) === 1)
						{
							$hook_status[$hook][$hook_name]['exists'] = true;
							$hook_status[$hook][$hook_name]['in_file'] = $file['name'];

							// I want to remember all the functions called within this file (to check later if they are
							// enabled or disabled and decide if the integrate_*_include of that file can be disabled too)
							$temp_data['function'][$file['name']][] = $function_o;
							unset($temp_hooks[$hook][$function_o]);
						}
					}
					// Procedural functions are easy
					elseif (empty($class) && str_contains(str_replace(' (', '(', $fc), 'function ' . trim($function) . '('))
					{
						$hook_status[$hook][$hook_name]['exists'] = true;
						$hook_status[$hook][$hook_name]['in_file'] = $file['name'];

						// I want to remember all the functions called within this file (to check later if they are
						// enabled or disabled and decide if the integrate_*_include of that file can be disabled too)
						$temp_data['function'][$file['name']][] = $function_o;
						unset($temp_hooks[$hook][$function_o]);
					}
					// OOP a bit more difficult
					elseif (!empty($class))
					{
						$lastPart = substr($class, strrpos($class, '\\') + 1);
						$regex = '~class\h*' . preg_quote(trim($lastPart)) . '.*function\h*' . preg_quote(trim($function), '~') . '\h*\(~i';
						if (preg_match($regex, $fc) === 1)
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

	$sort_types = [
		'hook_name' => ['hook_name', SORT_ASC],
		'hook_name DESC' => ['hook_name', SORT_DESC],
		'function_name' => ['function_name', SORT_ASC],
		'function_name DESC' => ['function_name', SORT_DESC],
		'file_name' => ['file_name', SORT_ASC],
		'file_name DESC' => ['file_name', SORT_DESC],
		'status' => ['status', SORT_ASC],
		'status DESC' => ['status', SORT_DESC],
	];

	$sort_options = $sort_types[$sort];
	$sort = [];
	$hooks_filters = [];

	foreach ($hooks as $hook => $functions)
	{
		$hooks_filters[] = '<option ' . ($context['current_filter'] === $hook ? 'selected="selected" ' : '') . ' value="' . $hook . '">' . $hook . '</option>';
		foreach ($functions as $function)
		{
			$function = str_replace(']', '', $function);

			// This is a not an include and the function is included in a certain file (if not it doesn't exist so don't care)
			if (isset($hook_status[$hook][$function]['in_file']) && !str_ends_with($hook, '_include'))
			{
				$current_hook = $temp_data['include'][$hook_status[$hook][$function]['in_file']] ?? '';
				$enabled = false;

				// Checking all the functions within this particular file
				// if any of them is enabled then the file *must* be included and
				// the integrate_*_include hook cannot be disabled
				foreach ($temp_data['function'][$hook_status[$hook][$function]['in_file']] as $func)
				{
					$enabled = $enabled || str_contains($func, ']');
				}

				if (!$enabled && !empty($current_hook))
				{
					$hook_status[$current_hook['hook']][$current_hook['function']]['enabled'] = true;
				}
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

	$temp_data = [];
	$id = 0;

	foreach ($hooks as $hook => $functions)
	{
		if (empty($context['filter']) || $context['filter'] === $hook)
		{
			foreach ($functions as $function)
			{
				$enabled = !str_contains($function, ']');
				$function = str_replace(']', '', $function);
				$hook_exists = !empty($hook_status[$hook][$function]['exists']);

				if (str_contains($function, '::'))
				{
					$function = explode('::', $function);
					$function = $function[1];
				}

				$exploded = explode('|', $function);

				$temp_data[] = [
					'id' => 'hookid_' . ($id++),
					'hook_name' => $hook,
					'function_name' => $function,
					'real_function' => $exploded[0],
					'included_file' => isset($exploded[1]) ? parse_path(trim($exploded[1])) : '',
					'file_name' => ($hook_status[$hook][$function]['in_file'] ?? ''),
					'hook_exists' => $hook_exists,
					'status' => $hook_exists ? ($enabled ? 'allow' : 'moderate') : 'deny',
					'img_text' => $txt['hooks_' . ($hook_exists ? ($enabled ? 'active' : 'disabled') : 'missing')],
					'enabled' => $enabled,
					'can_be_disabled' => false,
				];

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
		{
			continue;
		}

		if ($counter === $start + $items_per_page)
		{
			break;
		}

		$hooks_data[] = $data;
	}

	return $hooks_data;
}

/**
 * Returns the total count of integration hooks
 *
 * What it does:
 *
 * - Used by createList() as a callback to determine the number of hooks in
 * use in the system
 *
 * @param bool $filter
 *
 * @return int
 * @package AddonSettings
 *
 */
function integration_hooks_count($filter = false)
{
	$hooks = get_integration_hooks();
	$hooks_count = 0;

	foreach ($hooks as $hook => $functions)
	{
		if (empty($filter) || ($filter === $hook))
		{
			$hooks_count += count($functions);
		}
	}

	return $hooks_count;
}

/**
 * Parses modSettings to find all registered integration hooks
 *
 * @staticvar type $integration_hooks
 * @return array|null
 */
function get_integration_hooks(): ?array
{
	global $modSettings;
	static $integration_hooks = null;

	if ($integration_hooks === null)
	{
		$integration_hooks = [];
		foreach ($modSettings as $key => $value)
		{
			if (!empty($value) && str_starts_with($key, 'integrate_'))
			{
				$integration_hooks[$key] = explode(',', $value);
			}
		}
	}

	return $integration_hooks;
}
