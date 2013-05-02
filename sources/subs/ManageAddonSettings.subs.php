<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Alpha
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Gets all of the files in a directory and its chidren directories
 *
 * @param type $dir_path
 * @return array
 */
function get_files_recursive($dir_path)
{
	$files = array();

	if ($dh = opendir($dir_path))
	{
		while (($file = readdir($dh)) !== false)
		{
			if ($file != '.' && $file != '..')
			{
				if (is_dir($dir_path . '/' . $file))
					$files = array_merge($files, get_files_recursive($dir_path . '/' . $file));
				else
					$files[] = array('dir' => $dir_path, 'name' => $file);
			}
		}
	}
	closedir($dh);

	return $files;
}

/**
 * Callback function for the integration hooks list (list_integration_hooks)
 * Gets all of the hooks in the system and their status
 * Would be better documented if Ema was not lazy
 *
 * @param type $start
 * @param type $per_page
 * @param type $sort
 * @return array
 */
function list_integration_hooks_data($start, $per_page, $sort)
{
	global $settings, $txt, $context, $scripturl, $modSettings;

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
				$fc = fread($fp, filesize($file['dir'] . '/' . $file['name']));
				fclose($fp);

				foreach ($temp_hooks as $hook => $functions)
				{
					foreach ($functions as $function_o)
					{
						$hook_name = str_replace(']', '', $function_o);
						if (strpos($hook_name, '::') !== false)
						{
							$function = explode('::', $hook_name);
							$function = $function[1];
						}
						else
							$function = $hook_name;
						$function = explode(':', $function);
						$function = $function[0];

						if (substr($hook, -8) === '_include')
						{
							$hook_status[$hook][$function]['exists'] = file_exists(strtr(trim($function), array('BOARDDIR' => BOARDDIR, 'SOURCEDIR' => SOURCEDIR, '$themedir' => $settings['theme_dir'])));
							// I need to know if there is at least one function called in this file.
							$temp_data['include'][basename($function)] = array('hook' => $hook, 'function' => $function);
							unset($temp_hooks[$hook][$function_o]);
						}
						elseif (strpos(str_replace(' (', '(', $fc), 'function ' . trim($function) . '(') !== false)
						{
							$hook_status[$hook][$hook_name]['exists'] = true;
							$hook_status[$hook][$hook_name]['in_file'] = $file['name'];
							// I want to remember all the functions called within this file (to check later if they are enabled or disabled and decide if the integrare_*_include of that file can be disabled too)
							$temp_data['function'][$file['name']][] = $function_o;
							unset($temp_hooks[$hook][$function_o]);
						}
					}
				}
			}
		}
	}

	$sort_types = array(
		'hook_name' => array('hook', SORT_ASC),
		'hook_name DESC' => array('hook', SORT_DESC),
		'function_name' => array('function', SORT_ASC),
		'function_name DESC' => array('function', SORT_DESC),
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
		$hooks_filters[] = '<option ' . ($context['current_filter'] == $hook ? 'selected="selected" ' : '') . 'onclick="window.location = \'' . $scripturl . '?action=admin;area=modsettings;sa=hooks;filter=' . $hook . '\';">' . $hook . '</option>';
		foreach ($functions as $function)
		{
			$enabled = strstr($function, ']') === false;
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

				if (!$enabled &&  !empty($current_hook))
					$hook_status[$current_hook['hook']][$current_hook['function']]['enabled'] = true;
			}
		}
	}

	if (!empty($hooks_filters))
		addInlineJavascript('
			var hook_name_header = document.getElementById(\'header_list_integration_hooks_hook_name\');
			hook_name_header.innerHTML += ' . JavaScriptEscape('
				<select style="margin-left:15px;">
					<option>---</option>
					<option onclick="window.location = \'' . $scripturl . '?action=admin;area=modsettings;sa=hooks\';">' . $txt['hooks_reset_filter'] . '</option>' . implode('', $hooks_filters) . '
				</select>'). ';', true);

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
				$file_name = isset($hook_status[$hook][$function]['in_file']) ? $hook_status[$hook][$function]['in_file'] : ((substr($hook, -8) === '_include') ? 'zzzzzzzzz' : 'zzzzzzzza');
				$sort[] = $$sort_options[0];

				if (strpos($function, '::') !== false)
				{
					$function = explode('::', $function);
					$function = $function[1];
				}
				$exploded = explode(':', $function);

				$temp_data[] = array(
					'id' => 'hookid_' . $id++,
					'hook_name' => $hook,
					'function_name' => $function,
					'real_function' => $exploded[0],
					'included_file' => isset($exploded[1]) ? strtr(trim($exploded[1]), array('BOARDDIR' => BOARDDIR, 'SOURCEDIR' => SOURCEDIR, '$themedir' => $settings['theme_dir'])) : '',
					'file_name' => (isset($hook_status[$hook][$function]['in_file']) ? $hook_status[$hook][$function]['in_file'] : ''),
					'hook_exists' => $hook_exists,
					'status' => $hook_exists ? ($enabled ? 'allow' : 'moderate') : 'deny',
					'img_text' => $txt['hooks_' . ($hook_exists ? ($enabled ? 'active' : 'disabled') : 'missing')],
					'enabled' => $enabled,
					'can_be_disabled' => !empty($modSettings['handlinghooks_enabled']) && !isset($hook_status[$hook][$function]['enabled']),
				);
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
		elseif ($counter == $start + $per_page)
			break;

		$hooks_data[] = $data;
	}

	return $hooks_data;
}

/**
 * Simply returns the total count of integraion hooks
 * Used but the intergation hooks list function (list_integration_hooks)
 *
 * @global type $context
 * @return int
 */
function list_integration_hooks_count()
{
	global $context;

	$hooks = get_integration_hooks();
	$hooks_count = 0;

	$context['filter'] = false;
	if (isset($_GET['filter']))
		$context['filter'] = $_GET['filter'];

	foreach ($hooks as $hook => $functions)
	{
		if (empty($context['filter']) || (!empty($context['filter']) && $context['filter'] == $hook))
			$hooks_count += count($functions);
	}

	return $hooks_count;
}

/**
 * Parses modSettings to create integration hook array
 *
 * @staticvar type $integration_hooks
 * @return type
 */
function get_integration_hooks()
{
	global $modSettings;
	static $integration_hooks;

	if (!isset($integration_hooks))
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