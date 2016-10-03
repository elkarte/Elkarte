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
 * @version 1.1 beta 3
 *
 */

namespace ElkArte\sources\subs\SettingsFormAdapter;

class File extends Db
{
	/**
	 * @var int
	 */
	private $last_settings_change;

	/**
	 * @var array
	 */
	private $settingsArray = array();

	/**
	 * @var array
	 */
	private $new_settings = array();

	/**
	 * Helper method, it sets up the context for the settings which will be saved
	 * to the settings.php file
	 *
	 * What it does:
	 * - The basic usage of the six numbered key fields are
	 * - array(0 ,1, 2, 3, 4, 5
	 *    0 variable name - the name of the saved variable
	 *    1 label - the text to show on the settings page
	 *    2 saveto - file or db, where to save the variable name - value pair
	 *    3 type - type of data to display int, float, text, check, select, password
	 *    4 size - false or field size, if type is select, this needs to be an array of
	 *                select options
	 *    5 help - '' or helptxt variable name
	 *  )
	 * - The following named keys are also permitted
	 *    'disabled' =>
	 *    'postinput' =>
	 *    'preinput' =>
	 *    'subtext' =>
	 */
	public function prepare()
	{
		global $context, $modSettings;

		$defines = array(
			'boarddir',
			'sourcedir',
			'cachedir',
		);

		$safe_strings = array(
			'mtitle',
			'mmessage',
			'mbname',
		);
		foreach ($this->configVars as $identifier => $configVar)
		{
			$new_setting = $configVar;
			if (is_array($configVar) && isset($configVar[1]))
			{
				$varname = $configVar[0];
				global ${$varname};

				// Rewrite the definition a bit.
				$new_setting = array(
					$configVar[3],
					$configVar[0],
					'text_label' => $configVar[1],
				);
				if (isset($configVar[4]))
				{
					$new_setting[2] = $configVar[4];
				}
				if (isset($configVar[5]))
				{
					$new_setting['helptext'] = $configVar[5];
				}

				// Special value needed from the settings file?
				if ($configVar[2] == 'file')
				{
					$value = in_array($varname, $defines) ? constant(strtoupper($varname)) : $$varname;
					if (in_array($varname, $safe_strings))
					{
						$new_setting['mask'] = 'nohtml';
					}
					$modSettings[$configVar[0]] = $value;
				}
			}
			$this->new_settings[] = $new_setting;
		}
		$this->setConfigVars($this->new_settings);
		parent::prepare();
	}

	/**
	 * Update the Settings.php file.
	 *
	 * Typically this method is used from admin screens, just like this entire class.
	 * They're also available for addons and integrations.
	 *
	 * What it does:
	 * - updates the Settings.php file with the changes supplied in new_settings.
	 * - expects new_settings to be an associative array, with the keys as the
	 *   variable names in Settings.php, and the values the variable values.
	 * - does not escape or quote values.
	 * - preserves case, formatting, and additional options in file.
	 * - writes nothing if the resulting file would be less than 10 lines
	 *   in length (sanity check for read lock.)
	 * - check for changes to db_last_error and passes those off to a separate handler
	 * - attempts to create a backup file and will use it should the writing of the
	 *   new settings file fail
	 */
	public function save()
	{
		$this->cleanSettings();

		// When was Settings.php last changed?
		$this->last_settings_change = filemtime(BOARDDIR . '/Settings.php');

		// Load the settings file.
		$settingsFile = trim(file_get_contents(BOARDDIR . '/Settings.php'));

		// Break it up based on \r or \n, and then clean out extra characters.
		if (strpos($settingsFile, "\n") !== false)
		{
			$this->settingsArray = explode("\n", $settingsFile);
		}
		elseif (strpos($settingsFile, "\r") !== false)
		{
			$this->settingsArray = explode("\r", $settingsFile);
		}
		else
		{
			return;
		}
		$this->prepareSettings();
		$this->updateSettingsFile();
		$this->extractDbVars();
	}

	private function extractDbVars()
	{
		// Now loop through the remaining (database-based) settings.
		$this->configVars = array_map(
			function ($configVar)
			{
				// We just saved the file-based settings, so skip their definitions.
				if (!is_array($configVar) || $configVar[2] == 'file')
				{
					return '';
				}

				// Rewrite the definition a bit.
				if (is_array($configVar) && $configVar[2] == 'db')
				{
					return array($configVar[3], $configVar[0]);
				}
				else
				{
					// This is a regular config var requiring no special treatment.
					return $configVar;
				}
			}, $this->configVars
		);

		// Save the new database-based settings, if any.
		parent::save();
	}

	private function fixCookieName()
	{
		// Fix the darn stupid cookiename! (more may not be allowed, but these for sure!)
		if (isset($this->configValues['cookiename']))
		{
			$this->configValues['cookiename'] = preg_replace('~[,;\s\.$]+~u', '', $this->configValues['cookiename']);
		}
	}

	private function fixBoardUrl()
	{
		// Fix the forum's URL if necessary.
		if (isset($this->configValues['boardurl']))
		{
			if (substr($this->configValues['boardurl'], -10) == '/index.php')
			{
				$this->configValues['boardurl'] = substr($this->configValues['boardurl'], 0, -10);
			}
			elseif (substr($this->configValues['boardurl'], -1) == '/')
			{
				$this->configValues['boardurl'] = substr($this->configValues['boardurl'], 0, -1);
			}

			$this->configValues['boardurl'] = addProtocol($this->configValues['boardurl'], array('http://', 'https://', 'file://'));
		}
	}

	private function cleanSettings()
	{
		$this->fixCookieName();
		$this->fixBoardUrl();

		// Any passwords?
		$config_passwords = array(
			'db_passwd',
			'ssi_db_passwd',
			'cache_password',
		);

		// All the strings to write.
		$config_strs = array(
			'mtitle',
			'mmessage',
			'language',
			'mbname',
			'boardurl',
			'cookiename',
			'webmaster_email',
			'db_name',
			'db_user',
			'db_server',
			'db_prefix',
			'ssi_db_user',
			'cache_accelerator',
			'cache_memcached',
			'cache_uid',
		);

		$safe_strings = array(
			'mtitle',
			'mmessage',
			'mbname',
		);

		// All the numeric variables.
		$config_ints = array(
			'cache_enable',
		);

		// All the checkboxes.
		$config_bools = array(
			'db_persist',
			'db_error_send',
			'maintenance',
		);

		// Now sort everything into a big array, and figure out arrays and etc.
		foreach ($config_passwords as $configVar)
		{
			if (isset($this->configValues[$configVar][1]) && $this->configValues[$configVar][0] == $this->configValues[$configVar][1])
			{
				$this->new_settings[$configVar] = '\'' . addcslashes($this->configValues[$configVar][0], '\'\\') . '\'';
			}
		}

		foreach ($config_strs as $configVar)
		{
			if (isset($this->configValues[$configVar]))
			{
				if (in_array($configVar, $safe_strings))
				{
					$this->new_settings[$configVar] = '\'' . addcslashes(\Util::htmlspecialchars($this->configValues[$configVar], ENT_QUOTES), '\'\\') . '\'';
				}
				else
				{
					$this->new_settings[$configVar] = '\'' . addcslashes($this->configValues[$configVar], '\'\\') . '\'';
				}
			}
		}

		foreach ($config_ints as $configVar)
		{
			if (isset($this->configValues[$configVar]))
			{
				$this->new_settings[$configVar] = (int) $this->configValues[$configVar];
			}
		}

		foreach ($config_bools as $key)
		{
			// Check boxes need to be part of this settings form
			if ($this->_array_value_exists__recursive($key, $this->getConfigVars()))
			{
				$this->new_settings[$key] = (int) !empty($this->configValues[$key]);
			}
		}
	}

	/**
	 * Recursively checks if a value exists in an array
	 *
	 * @param string  $needle
	 * @param mixed[] $haystack
	 *
	 * @return boolean
	 */
	private function _array_value_exists__recursive($needle, $haystack)
	{
		foreach ($haystack as $item)
		{
			if ($item == $needle || (is_array($item) && $this->_array_value_exists__recursive($needle, $item)))
			{
				return true;
			}
		}

		return false;
	}

	private function prepareSettings()
	{
		// Presumably, the file has to have stuff in it for this function to be called :P.
		if (count($this->settingsArray) < 10)
		{
			return;
		}

		// remove any /r's that made there way in here
		foreach ($this->settingsArray as $k => $dummy)
		{
			$this->settingsArray[$k] = strtr($dummy, array("\r" => '')) . "\n";
		}

		// go line by line and see whats changing
		for ($i = 0, $n = count($this->settingsArray); $i < $n; $i++)
		{
			// Don't trim or bother with it if it's not a variable.
			if (substr($this->settingsArray[$i], 0, 1) != '$')
			{
				continue;
			}

			$this->settingsArray[$i] = trim($this->settingsArray[$i]) . "\n";

			// Look through the variables to set....
			foreach ($this->new_settings as $var => $val)
			{
				if (strncasecmp($this->settingsArray[$i], '$' . $var, 1 + strlen($var)) == 0)
				{
					$comment = strstr(substr(un_htmlspecialchars($this->settingsArray[$i]), strpos(un_htmlspecialchars($this->settingsArray[$i]), ';')), '#');
					$this->settingsArray[$i] = '$' . $var . ' = ' . $val . ';' . ($comment == '' ? '' : "\t\t" . rtrim($comment)) . "\n";

					// This one's been 'used', so to speak.
					unset($this->new_settings[$var]);
				}
			}

			// End of the file ... maybe
			if (substr(trim($this->settingsArray[$i]), 0, 2) == '?' . '>')
			{
				$end = $i;
			}
		}

		// This should never happen, but apparently it is happening.
		if (empty($end) || $end < 10)
		{
			$end = count($this->settingsArray) - 1;
		}

		// Still more variables to go?  Then lets add them at the end.
		if (!empty($this->new_settings))
		{
			if (trim($this->settingsArray[$end]) == '?' . '>')
			{
				$this->settingsArray[$end++] = '';
			}
			else
			{
				$end++;
			}

			// Add in any newly defined vars that were passed
			foreach ($this->new_settings as $var => $val)
			{
				$this->settingsArray[$end++] = '$' . $var . ' = ' . $val . ';' . "\n";
			}
		}
		else
		{
			$this->settingsArray[$end] = trim($this->settingsArray[$end]);
		}
	}

	/**
	 * Write out the contents of Settings.php file.
	 * This function will add the variables passed to it in $this->new_settings,
	 * to the Settings.php file.
	 */
	private function updateSettingsFile()
	{
		global $context;

		// Sanity error checking: the file needs to be at least 12 lines.
		if (count($this->settingsArray) < 12)
		{
			return;
		}

		// Try to avoid a few pitfalls:
		//  - like a possible race condition,
		//  - or a failure to write at low diskspace
		//
		// Check before you act: if cache is enabled, we can do a simple write test
		// to validate that we even write things on this filesystem.
		if ((!defined('CACHEDIR') || !file_exists(CACHEDIR)) && file_exists(BOARDDIR . '/cache'))
		{
			$tmp_cache = BOARDDIR . '/cache';
		}
		else
		{
			$tmp_cache = CACHEDIR;
		}

		$test_fp = @fopen($tmp_cache . '/settings_update.tmp', 'w+');
		if ($test_fp)
		{
			fclose($test_fp);
			$written_bytes = file_put_contents($tmp_cache . '/settings_update.tmp', 'test', LOCK_EX);
			@unlink($tmp_cache . '/settings_update.tmp');

			if ($written_bytes !== 4)
			{
				// Oops. Low disk space, perhaps. Don't mess with Settings.php then.
				// No means no. :P
				return;
			}
		}

		// Protect me from what I want! :P
		clearstatcache();
		if (filemtime(BOARDDIR . '/Settings.php') === $this->last_settings_change)
		{
			// Save the old before we do anything
			$settings_backup_fail = !@is_writable(BOARDDIR . '/Settings_bak.php') || !@copy(BOARDDIR . '/Settings.php', BOARDDIR . '/Settings_bak.php');
			$settings_backup_fail = !$settings_backup_fail ? (!file_exists(BOARDDIR . '/Settings_bak.php') || filesize(BOARDDIR . '/Settings_bak.php') === 0) : $settings_backup_fail;

			// Write out the new
			$write_settings = implode('', $this->settingsArray);
			$written_bytes = file_put_contents(BOARDDIR . '/Settings.php', $write_settings, LOCK_EX);

			// Survey says ...
			if ($written_bytes !== strlen($write_settings) && !$settings_backup_fail)
			{
				// Well this is not good at all, lets see if we can save this
				$context['settings_message'] = 'settings_error';

				if (file_exists(BOARDDIR . '/Settings_bak.php'))
				{
					@copy(BOARDDIR . '/Settings_bak.php', BOARDDIR . '/Settings.php');
				}
			}
			// And ensure we are going to read the correct file next time
			if (function_exists('opcache_invalidate'))
			{
				opcache_invalidate(BOARDDIR . '/Settings.php');
			}
		}
	}
}
