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
		global $txt, $helptxt, $modSettings;

		loadLanguage('Help');

		foreach ($this->configVars as $configVar)
		{
			// HR?
			if (!is_array($configVar))
			{
				$this->context[] = $configVar;
			}
			else
			{
				// If it has no name it doesn't have any purpose!
				if (empty($configVar[1]))
				{
					continue;
				}

				// Special case for inline permissions
				if ($configVar[0] == 'permissions')
				{
					continue;
				}

				$this->context[$configVar[1]] = array(
					'type' => $configVar[0],
					'size' => !empty($configVar[2]) && !is_array($configVar[2]) ? $configVar[2] : (in_array($configVar[0], array('int', 'float')) ? 6 : 0),
					'data' => array(),
					'name' => $configVar[1],
					'value' => isset($modSettings[$configVar[1]]) ? ($configVar[0] == 'select' ? $modSettings[$configVar[1]] : htmlspecialchars($modSettings[$configVar[1]], ENT_COMPAT, 'UTF-8')) : (in_array($configVar[0], array('int', 'float')) ? 0 : ''),
					'disabled' => false,
					'invalid' => !empty($configVar['invalid']),
					'javascript' => '',
				);
				foreach (array('helptext', 'message'. 'preinput', 'postinput', 'icon') as $k => $v)
				{
					if (isset($configVar[$k]))
					{
						$this->context[$configVar[1]][$k] = $v;
					}
				}

				$this->prepareLabel($configVar);

				// If this is a select box handle any data.
				$this->handleSelect($configVar);

				// Revert masks if necessary
				$this->context[$configVar[1]]['value'] = $this->revertMasks($configVar, $this->context[$configVar[1]]['value']);

				// Finally allow overrides - and some final cleanups.
				$this->allowOverrides($configVar);
			}
		}

		// If we have inline permissions we need to prep them.
		$this->init_inline_permissions();

		// What about any BBC selection boxes?
		$this->initBbcChoices();

		$this->prepareContext();
	}

	/**
	 * Initialize inline permissions settings.
	 */
	private function init_inline_permissions()
	{
		global $context;

		$inlinePermissions = array_filter($this->configVars,
			function ($configVar)
			{
				return isset($configVar[0]) && $configVar[0] == 'permissions';
			}
		);
		if (empty($inlinePermissions))
		{
			return;
		}
		$permissionsForm = new Inline_Permissions_Form;
		$permissionsForm->setExcludedGroups(isset($context['permissions_excluded']) ? $context['permissions_excluded'] : array());
		$permissionsForm->setPermissions($inlinePermissions);
		$permissionsForm->init();
	}

	/**
	 * @param mixed[] $configVar
	 * @param string  $str
	 *
	 * @return string
	 */
	private function revertMasks(array $configVar, $str)
	{
		$known_rules = array(
			'nohtml' => 'htmlspecialchars_decode[' . ENT_NOQUOTES . ']',
		);
		return $this->applyMasks($configVar, $str, $known_rules);
	}

	/**
	 * @param mixed[] $configVar
	 * @param string  $str
	 *
	 * @return string
	 */
	private function setMasks(array $configVar, $str)
	{
		$known_rules = array(
			'nohtml' => 'Util::htmlspecialchars[' . ENT_QUOTES . ']',
			'email' => 'valid_email',
			'url' => 'valid_url',
		);
		return $this->applyMasks($configVar, $str, $known_rules);
	}

	/**
	 * @param mixed[] $configVar
	 * @param string  $str
	 * @param array   $known_rules
	 *
	 * @return string
	 */
	private function applyMasks(array $configVar, $str, $known_rules)
	{
		if (isset($configVar['mask']))
		{
			$rules = array();
			if (!is_array($configVar['mask']))
			{
				$configVar['mask'] = array($configVar['mask']);
			}
			foreach ($configVar['mask'] as $key => $mask)
			{
				if (isset($known_rules[$mask]))
				{
					$rules[$configVar[1]][] = $known_rules[$mask];
				}
				elseif ($key == 'custom' && isset($mask['revert']))
				{
					$rules[$configVar[1]][] = $mask['revert'];
				}
			}
			if (!empty($rules))
			{
				$rules[$configVar[1]] = implode('|', $rules[$configVar[1]]);

				$validator = new Data_Validator();
				$validator->sanitation_rules($rules);
				$validator->validate(array($configVar[1] => $str));

				return $validator->{$configVar[1]};
			}
		}

		return $str;
	}

	/**
	 * @param mixed[] $configVar
	 */
	private function handleSelect(array $configVar)
	{
		if (!empty($configVar[2]) && is_array($configVar[2]))
		{
			// If we allow multiple selections, we need to adjust a few things.
			if ($configVar[0] == 'select' && !empty($configVar['multiple']))
			{
				$this->context[$configVar[1]]['name'] .= '[]';
				$this->context[$configVar[1]]['value'] = !empty($this->context[$configVar[1]]['value']) ? Util::unserialize($this->context[$configVar[1]]['value']) : array();
			}

			// If it's associative
			if (isset($configVar[2][0]) && is_array($configVar[2][0]))
			{
				$this->context[$configVar[1]]['data'] = $configVar[2];
			}
			else
			{
				foreach ($configVar[2] as $key => $item)
				{
					$this->context[$configVar[1]]['data'][] = array($key, $item);
				}
			}
		}
	}

	/**
	 * @param mixed[] $configVar
	 */
	private function allowOverrides(array $configVar)
	{
		global $txt, $helptxt;

		foreach ($configVar as $k => $v)
		{
			if (!is_numeric($k))
			{
				if (substr($k, 0, 2) == 'on')
				{
					$this->context[$configVar[1]]['javascript'] .= ' ' . $k . '="' . $v . '"';
				}
				else
				{
					$this->context[$configVar[1]][$k] = $v;
				}
			}
		}
		if (isset($configVar['message'], $txt[$configVar['message']]))
		{
			$this->context[$configVar[1]]['message'] = $txt[$configVar['message']];
		}
		if (isset($helptxt[$configVar[1]]))
		{
			$this->context[$configVar[1]]['helptext'] = $configVar[1];
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

		if (!isset($this->configValues[$var[1] . '_enabledTags']))
		{
			$this->configValues[$var[1] . '_enabledTags'] = array();
		}
		elseif (!is_array($this->configValues[$var[1] . '_enabledTags']))
		{
			$this->configValues[$var[1] . '_enabledTags'] = array($this->configValues[$var[1] . '_enabledTags']);
		}

		return implode(',', array_diff($bbcTags, $this->configValues[$var[1] . '_enabledTags']));
	}

	private function prepareLabel($configVar)
	{
		global $txt;

		// See if there are any labels that might fit?
		if (isset($configVar['text_label']))
		{
			$this->context[$configVar[1]]['label'] = $configVar['text_label'];
		}
		elseif (isset($txt[$configVar[1]]))
		{
			$this->context[$configVar[1]]['label'] = $txt[$configVar[1]];
		}
		// @todo are any of these actually used?
		elseif (isset($txt['setting_' . $configVar[1]]))
		{
			$this->context[$configVar[1]]['label'] = $txt['setting_' . $configVar[1]];
		}
		elseif (isset($txt['groups_' . $configVar[1]]))
		{
			$this->context[$configVar[1]]['label'] = $txt['groups_' . $configVar[1]];
		}
		else
		{
			$this->context[$configVar[1]]['label'] = $configVar[1];
		}

		// Set the subtext in case it's part of the label.
		// @todo Temporary. Preventing divs inside label tags.
		$divPos = strpos($this->context[$configVar[1]]['label'], '<div');
		if ($divPos !== false)
		{
			$this->context[$configVar[1]]['subtext'] = preg_replace('~</?div[^>]*>~', '', substr($this->context[$configVar[1]]['label'], $divPos));
			$this->context[$configVar[1]]['label'] = substr($this->context[$configVar[1]]['label'], 0, $divPos);
		}
	}

	/**
	 * Initialize a list of available BB codes.
	 */
	private function initBbcChoices()
	{
		global $txt, $helptxt, $context, $modSettings;

		$bbcChoice = array_filter($this->configVars,
			function ($configVar)
			{
				return isset($configVar[0]) && $configVar[0] == 'bbc';
			}
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
		foreach ($bbcChoice as $configVar)
		{
			$disabled = empty($modSettings['bbc_disabled_' . $configVar[1]]);
			$this->context[$configVar[1]] = array_merge_recursive(array(
				'disabled_tags' => $disabled ? array() : $modSettings['bbc_disabled_' . $configVar[1]],
				'all_selected' => $disabled,
				'data' => $bbc_sections,
			), $this->context[$configVar[1]]);
		}
	}

	/**
	 * Cast all the config vars as defined
	 *
	 * @return array
	 */
	protected function sanitizeVars()
	{
		$setTypes = array();
		$setArray = array();
		foreach ($this->configVars as $var)
		{
			if (!isset($var[1]) || !isset($this->configValues[$var[1]]) && $var[0] != 'check')
			{
				continue;
			}
			$setTypes[$var[1]] = $var[1];
			switch ($var[0])
			{
				case 'check':
					$setTypes[$var[1]] = 'int';
					$setArray[$var[1]] = (int) !empty($this->configValues[$var[1]]);
					break;
				case 'select':
					// Select boxes!
					$setTypes[$var[1]] = 'string';
					if (empty($var['multiple']) && in_array($this->configValues[$var[1]], array_keys($var[2])))
					{
						$setArray[$var[1]] = $this->configValues[$var[1]];
					}
					elseif (!empty($var['multiple']))
					{
						// For security purposes we validate this line by line.
						$setArray[$var[1]] = serialize(array_intersect($this->configValues[$var[1]], array_keys($var[2])));
					}
					break;
				case 'int':
					// Integers!
					$setArray[$var[1]] = (int) $this->configValues[$var[1]];
					break;
				case 'float':
					// Floating point!
					$setArray[$var[1]] = (float) $this->configValues[$var[1]];
					break;
				case 'text':
				case 'color':
				case 'large_text':
					// Text!
					$setTypes[$var[1]] = 'string';
					$setArray[$var[1]] = $this->setMasks($var, $this->configValues[$var[1]]);
					break;
				case 'password':
					// Passwords!
					$setTypes[$var[1]] = 'string';
					if (isset($this->configValues[$var[1]][1]) && $this->configValues[$var[1]][0] == $this->configValues[$var[1]][1])
					{
						$setArray[$var[1]] = $this->configValues[$var[1]][0];
					}
					break;
				case 'bbc':
					// BBC.
					$setTypes[$var[1]] = 'string';
					$setArray[$var[1]] = $this->setBbcChoices($var);
					break;
			}
		}
		return array($setArray, $setTypes);
	}

	/**
	 * Helper method for saving database settings.
	 */
	public function save()
	{
		list ($setArray) = $this->sanitizeVars();
		$inlinePermissions = array();
		foreach ($this->configVars as $var)
		{
			if (!isset($var[1]) || !isset($this->configValues[$var[1]]))
			{
				continue;
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
