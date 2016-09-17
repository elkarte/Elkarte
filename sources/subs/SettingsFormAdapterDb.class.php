<?php

/**
 * This class handles display, edit, save, of forum settings.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:    2011 Simple Machines (http://www.simplemachines.org)
 * license:    BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 beta 2
 *
 */
class SettingsFormAdapterDb extends SettingsFormAdapter
{
	/**
	 * Helper method, it sets up the context for database settings.
	 *
	 * @param mixed[] $config_vars
	 */
	public function prepare()
	{
		global $txt, $helptxt, $context, $modSettings;
		static $known_rules = null;

		if ($known_rules === null)
		{
			$known_rules = array(
				'nohtml' => 'htmlspecialchars_decode[' . ENT_NOQUOTES . ']',
			);
		}

		loadLanguage('Help');

		$inlinePermissions = array();
		$bbcChoice = array();
		foreach ($this->config_vars as $config_var)
		{
			// HR?
			if (!is_array($config_var))
			{
				$this->context[] = $config_var;
			}
			else
			{
				// If it has no name it doesn't have any purpose!
				if (empty($config_var[1]))
				{
					continue;
				}

				// Special case for inline permissions
				if ($config_var[0] == 'permissions' && allowedTo('manage_permissions'))
				{
					$inlinePermissions[] = $config_var;
				}
				elseif ($config_var[0] == 'permissions')
				{
					continue;
				}

				// Are we showing the BBC selection box?
				if ($config_var[0] == 'bbc')
				{
					$bbcChoice[] = $config_var[1];
				}

				$this->context[$config_var[1]] = array(
					'label' => isset($config_var['text_label']) ? $config_var['text_label'] : (isset($txt[$config_var[1]]) ? $txt[$config_var[1]] : (isset($config_var[3]) && !is_array($config_var[3]) ? $config_var[3] : '')),
					'help' => isset($config_var['helptext']) ? $config_var['helptext'] : (isset($helptxt[$config_var[1]]) ? $config_var[1] : ''),
					'type' => $config_var[0],
					'size' => !empty($config_var[2]) && !is_array($config_var[2]) ? $config_var[2] : (in_array($config_var[0], array('int', 'float')) ? 6 : 0),
					'data' => array(),
					'name' => $config_var[1],
					'value' => isset($modSettings[$config_var[1]]) ? ($config_var[0] == 'select' ? $modSettings[$config_var[1]] : htmlspecialchars($modSettings[$config_var[1]], ENT_COMPAT, 'UTF-8')) : (in_array($config_var[0], array('int', 'float')) ? 0 : ''),
					'disabled' => false,
					'invalid' => !empty($config_var['invalid']),
					'javascript' => '',
					'var_message' => !empty($config_var['message']) && isset($txt[$config_var['message']]) ? $txt[$config_var['message']] : '',
					'preinput' => isset($config_var['preinput']) ? $config_var['preinput'] : '',
					'postinput' => isset($config_var['postinput']) ? $config_var['postinput'] : '',
					'icon' => isset($config_var['icon']) ? $config_var['icon'] : '',
				);

				// If this is a select box handle any data.
				if (!empty($config_var[2]) && is_array($config_var[2]))
				{
					// If we allow multiple selections, we need to adjust a few things.
					if ($config_var[0] == 'select' && !empty($config_var['multiple']))
					{
						$this->context[$config_var[1]]['name'] .= '[]';
						$this->context[$config_var[1]]['value'] = !empty($this->context[$config_var[1]]['value']) ? Util::unserialize($this->context[$config_var[1]]['value']) : array();
					}

					// If it's associative
					if (isset($config_var[2][0]) && is_array($config_var[2][0]))
					{
						$this->context[$config_var[1]]['data'] = $config_var[2];
					}
					else
					{
						foreach ($config_var[2] as $key => $item)
						{
							$this->context[$config_var[1]]['data'][] = array($key, $item);
						}
					}
				}

				// Revert masks if necessary
				if (isset($config_var['mask']))
				{
					$rules = array();

					if (!is_array($config_var['mask']))
					{
						$config_var['mask'] = array($config_var['mask']);
					}
					foreach ($config_var['mask'] as $key => $mask)
					{
						if (isset($known_rules[$mask]))
						{
							$rules[$config_var[1]][] = $known_rules[$mask];
						}
						elseif ($key == 'custom' && isset($mask['revert']))
						{
							$rules[$config_var[1]][] = $mask['revert'];
						}
					}

					if (!empty($rules))
					{
						$rules[$config_var[1]] = implode('|', $rules[$config_var[1]]);

						$validator = new Data_Validator();
						$validator->sanitation_rules($rules);
						$validator->validate(array($config_var[1] => $this->context[$config_var[1]]['value']));

						$this->context[$config_var[1]]['value'] = $validator->{$config_var[1]};
					}
				}

				// Finally allow overrides - and some final cleanups.
				$this->allowOverrides($config_var);
			}
		}

		// If we have inline permissions we need to prep them.
		$this->init_inline_permissions($inlinePermissions, isset($context['permissions_excluded']) ? $context['permissions_excluded'] : array());

		// What about any BBC selection boxes?
		$this->initBbcChoices($bbcChoice);

		call_integration_hook('integrate_prepare_db_settings', array(&$config_vars));
		$this->prepareContext();
	}

	/**
	 * Initialize inline permissions settings.
	 *
	 * @param string[] $permissions
	 * @param int[]    $excluded_groups = array()
	 */
	private function init_inline_permissions($permissions, $excluded_groups = array())
	{
		global $context, $modSettings;

		if (empty($permissions))
		{
			return;
		}

		$permissionsForm = new Inline_Permissions_Form;
		$permissionsForm->setExcludedGroups($excluded_groups);
		$permissionsForm->setPermissions($permissions);

		return $permissionsForm->init();
	}

	/**
	 * @param mixed[] $config_var
	 */
	private function allowOverrides($config_var)
	{
		global $context, $modSettings;

		foreach ($config_var as $k => $v)
		{
			if (!is_numeric($k))
			{
				if (substr($k, 0, 2) == 'on')
				{
					$this->context[$config_var[1]]['javascript'] .= ' ' . $k . '="' . $v . '"';
				}
				else
				{
					$this->context[$config_var[1]][$k] = $v;
				}
			}

			// See if there are any other labels that might fit?
			if (isset($txt['setting_' . $config_var[1]]))
			{
				$this->context[$config_var[1]]['label'] = $txt['setting_' . $config_var[1]];
			}
			elseif (isset($txt['groups_' . $config_var[1]]))
			{
				$this->context[$config_var[1]]['label'] = $txt['groups_' . $config_var[1]];
			}
		}

		// Set the subtext in case it's part of the label.
		// @todo Temporary. Preventing divs inside label tags.
		$divPos = strpos($this->context[$config_var[1]]['label'], '<div');
		if ($divPos !== false)
		{
			$this->context[$config_var[1]]['subtext'] = preg_replace('~</?div[^>]*>~', '', substr($this->context[$config_var[1]]['label'], $divPos));
			$this->context[$config_var[1]]['label'] = substr($this->context[$config_var[1]]['label'], 0, $divPos);
		}
	}

	/**
	 * Initialize a list of available BB codes.
	 *
	 * @param string[] $bbcChoice
	 */
	private function initBbcChoices($bbcChoice)
	{
		global $txt, $helptxt, $context, $modSettings;

		if (empty($bbcChoice))
		{
			return;
		}

		// What are the options, eh?
		$codes = \BBC\ParserWrapper::getInstance()->getCodes();
		$bbcTags = $codes->getTags();

		$bbcTags = array_unique($bbcTags);
		$totalTags = count($bbcTags);

		// The number of columns we want to show the BBC tags in.
		$numColumns = isset($context['num_bbc_columns']) ? $context['num_bbc_columns'] : 3;

		// Start working out the context stuff.
		$context['bbc_columns'] = array();
		$tagsPerColumn = ceil($totalTags / $numColumns);

		$col = 0;
		$i = 0;
		foreach ($bbcTags as $tag)
		{
			if ($i % $tagsPerColumn == 0 && $i != 0)
			{
				$col++;
			}

			$context['bbc_columns'][$col][] = array(
				'tag' => $tag,
				// @todo  'tag_' . ?
				'show_help' => isset($helptxt[$tag]),
			);

			$i++;
		}

		// Now put whatever BBC options we may have into context too!
		$context['bbc_sections'] = array();
		foreach ($bbcChoice as $bbc)
		{
			$context['bbc_sections'][$bbc] = array(
				'title' => isset($txt['bbc_title_' . $bbc]) ? $txt['bbc_title_' . $bbc] : $txt['bbcTagsToUse_select'],
				'disabled' => empty($modSettings['bbc_disabled_' . $bbc]) ? array() : $modSettings['bbc_disabled_' . $bbc],
				'all_selected' => empty($modSettings['bbc_disabled_' . $bbc]),
			);
		}
	}

	/**
	 * Helper method for saving database settings.
	 */
	public function save()
	{
		static $known_rules = null;

		if ($known_rules === null)
		{
			$known_rules = array(
				'nohtml' => 'Util::htmlspecialchars[' . ENT_QUOTES . ']',
				'email' => 'valid_email',
				'url' => 'valid_url',
			);
		}

		validateToken('admin-dbsc');

		$inlinePermissions = array();
		foreach ($this->config_vars as $var)
		{
			if (!isset($var[1]) || (!isset($this->post_vars[$var[1]]) && $var[0] != 'check' && $var[0] != 'permissions' && ($var[0] != 'bbc' || !isset($this->post_vars[$var[1] . '_enabledTags']))))
			{
				continue;
			}

			// Checkboxes!
			elseif ($var[0] == 'check')
			{
				$setArray[$var[1]] = !empty($this->post_vars[$var[1]]) ? '1' : '0';
			}
			// Select boxes!
			elseif ($var[0] == 'select' && in_array($this->post_vars[$var[1]], array_keys($var[2])))
			{
				$setArray[$var[1]] = $this->post_vars[$var[1]];
			}
			elseif ($var[0] == 'select' && !empty($var['multiple']) && array_intersect($this->post_vars[$var[1]], array_keys($var[2])) != array())
			{
				// For security purposes we validate this line by line.
				$options = array();
				foreach ($this->post_vars[$var[1]] as $invar)
				{
					if (in_array($invar, array_keys($var[2])))
					{
						$options[] = $invar;
					}
				}

				$setArray[$var[1]] = serialize($options);
			}
			// Integers!
			elseif ($var[0] == 'int')
			{
				$setArray[$var[1]] = (int) $this->post_vars[$var[1]];
			}
			// Floating point!
			elseif ($var[0] == 'float')
			{
				$setArray[$var[1]] = (float) $this->post_vars[$var[1]];
			}
			// Text!
			elseif ($var[0] == 'text' || $var[0] == 'color' || $var[0] == 'large_text')
			{
				if (isset($var['mask']))
				{
					$rules = array();

					if (!is_array($var['mask']))
					{
						$var['mask'] = array($var['mask']);
					}
					foreach ($var['mask'] as $key => $mask)
					{
						if (isset($known_rules[$mask]))
						{
							$rules[$var[1]][] = $known_rules[$mask];
						}
						elseif ($key == 'custom' && isset($mask['apply']))
						{
							$rules[$var[1]][] = $mask['apply'];
						}
					}

					if (!empty($rules))
					{
						$rules[$var[1]] = implode('|', $rules[$var[1]]);

						$validator = new Data_Validator();
						$validator->sanitation_rules($rules);
						$validator->validate($this->post_vars);

						$setArray[$var[1]] = $validator->{$var[1]};
					}
				}
				else
				{
					$setArray[$var[1]] = $this->post_vars[$var[1]];
				}
			}
			// Passwords!
			elseif ($var[0] == 'password')
			{
				if (isset($this->post_vars[$var[1]][1]) && $this->post_vars[$var[1]][0] == $this->post_vars[$var[1]][1])
				{
					$setArray[$var[1]] = $this->post_vars[$var[1]][0];
				}
			}
			// BBC.
			elseif ($var[0] == 'bbc')
			{
				$codes = \BBC\ParserWrapper::getInstance()->getCodes();
				$bbcTags = $codes->getTags();

				if (!isset($this->post_vars[$var[1] . '_enabledTags']))
				{
					$this->post_vars[$var[1] . '_enabledTags'] = array();
				}
				elseif (!is_array($this->post_vars[$var[1] . '_enabledTags']))
				{
					$this->post_vars[$var[1] . '_enabledTags'] = array($this->post_vars[$var[1] . '_enabledTags']);
				}

				$setArray[$var[1]] = implode(',', array_diff($bbcTags, $this->post_vars[$var[1] . '_enabledTags']));
			}
			// Permissions?
			elseif ($var[0] == 'permissions')
			{
				$inlinePermissions[] = $var;
			}
		}

		if (!empty($setArray))
		{
			updateSettings($setArray);
		}

		// If we have inline permissions we need to save them.
		if (!empty($inlinePermissions) && allowedTo('manage_permissions'))
		{
			// we'll need to save inline permissions
			require_once(SUBSDIR . '/Permission.subs.php');
			InlinePermissions_Form::save_inline_permissions($inlinePermissions);
		}
	}
}
