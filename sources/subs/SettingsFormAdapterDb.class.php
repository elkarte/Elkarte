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
	 */
	public function prepare()
	{
		global $txt, $helptxt, $context, $modSettings;

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
				if ($config_var[0] == 'permissions')
				{
					continue;
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
				$this->handleSelect($config_var);

				// Revert masks if necessary
				$this->context[$config_var[1]]['value'] = $this->revertMasks($config_var, $this->context[$config_var[1]]['value']);

				// Finally allow overrides - and some final cleanups.
				$this->allowOverrides($config_var);
			}
		}

		// If we have inline permissions we need to prep them.
		$this->init_inline_permissions(isset($context['permissions_excluded']) ? $context['permissions_excluded'] : array());

		// What about any BBC selection boxes?
		$this->initBbcChoices($bbcChoice);

		$this->prepareContext();
	}

	/**
	 * Initialize inline permissions settings.
	 *
	 * @param int[] $excluded_groups = array()
	 */
	private function init_inline_permissions($excluded_groups = array())
	{
		$inlinePermissions = array_filter(
			function ($config_var)
			{
				return $config_var[0] == 'permissions';
			}, $this->config_vars
		);
		if (empty($inlinePermissions))
		{
			return;
		}
		$permissionsForm = new Inline_Permissions_Form;
		$permissionsForm->setExcludedGroups($excluded_groups);
		$permissionsForm->setPermissions($inlinePermissions);
		$permissionsForm->init();
	}

	/**
	 * @param mixed[] $config_var
	 * @param string  $str
	 *
	 * @return string
	 */
	private function revertMasks($config_var, $str)
	{
		static $known_rules = null;

		if ($known_rules === null)
		{
			$known_rules = array(
				'nohtml' => 'htmlspecialchars_decode[' . ENT_NOQUOTES . ']',
			);
		}
		return $this->applyMasks($config_var, $str, $known_rules);
	}

	/**
	 * @param mixed[] $config_var
	 * @param string  $str
	 *
	 * @return string
	 */
	private function setMasks($config_var, $str)
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
		return $this->applyMasks($config_var, $str, $known_rules);
	}

	/**
	 * @param mixed[] $config_var
	 * @param string  $str
	 * @param array   $known_rules
	 *
	 * @return string
	 */
	private function applyMasks($config_var, $str, $known_rules)
	{
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
				$validator->validate(array($config_var[1] => $str));

				return $validator->{$config_var[1]};
			}
		}

		return $str;
	}

	/**
	 * @param mixed[] $config_var
	 */
	private function handleSelect($config_var)
	{
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
	}

	/**
	 * @param mixed[] $config_var
	 */
	private function allowOverrides($config_var)
	{
		global $txt;

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
	 * @param string[] $var
	 *
	 * @return string
	 */
	private function setBbcChoices($var)
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

		return implode(',', array_diff($bbcTags, $this->post_vars[$var[1] . '_enabledTags']));
	}

	/**
	 * Initialize a list of available BB codes.
	 */
	private function initBbcChoices()
	{
		global $txt, $helptxt, $context, $modSettings;

		$bbcChoice = array_filter(
			function ($config_var)
			{
				return $config_var[0] == 'permissions';
			}, $this->config_vars
		);
		if (empty($bbcChoice))
		{
			return;
		}

		// What are the options, eh?
		$codes = \BBC\ParserWrapper::getInstance()->getCodes();
		$bbcTags = $codes->getTags();
		$bbcTags = array_unique($bbcTags);
		$bbc_sections = array();
		foreach ($bbcTags as $tag)
		{
			$bbc_sections[] = array(
				'tag' => $tag,
				// @todo  'tag_' . ?
				'show_help' => isset($helptxt[$tag]),
			);
		}

		// Now put whatever BBC options we may have into context too!
		foreach ($bbcChoice as $config_var)
		{
			$disabled = empty($modSettings['bbc_disabled_' . $config_var[1]]);
			$this->context[$config_var[1]] = array_merge_recursive(array(
				'disabled_tags' => $disabled ? array() : $modSettings['bbc_disabled_' . $config_var[1]],
				'all_selected' => $disabled,
				'data' => $bbc_sections,
			), $this->context[$config_var[1]]);
		}
	}

	/**
	 * Helper method for saving database settings.
	 */
	public function save()
	{
		$setArray = array();
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
				$setArray[$var[1]] = $this->setMasks($var, $setArray[$var[1]]);
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
				$setArray[$var[1]] = $this->setBbcChoices($var);
			}
			// Permissions?
			elseif ($var[0] == 'permissions')
			{
				$inlinePermissions[] = $var;
			}
		}

		if (!empty($setArray))
		{
			// Just in case we cached this.
			$setArray['settings_updated'] = time();

			updateSettings($setArray);
		}

		// If we have inline permissions we need to save them.
		if (!empty($inlinePermissions) && allowedTo('manage_permissions'))
		{
			$permissionsForm = new Inline_Permissions_Form;
			$permissionsForm->setPermissions($inlinePermissions);
			$permissionsForm->save();
		}
	}
}
