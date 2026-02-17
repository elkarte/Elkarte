<?php

/**
 * This class handles display, edit, save, of forum settings (Settings.php).
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

namespace ElkArte\SettingsForm\SettingsFormAdapter;

use ElkArte\Helper\FileFunctions;
use ElkArte\Helper\Util;

/**
 * Class File
 *
 * @package ElkArte\SettingsForm\SettingsFormAdapter
 */
class File extends Db
{
	/** @var int */
	private $last_settings_change;

	/** @var array */
	private $settingsArray = [];

	/** @var array */
	private $new_settings = [];

	/** @var FileFunctions */
	private $fileFunc;

	/**
	 * Helper method, it sets up the context for the settings which will be saved
	 * to the settings.php file
	 *
	 * What it does:
	 *
	 * - The basic usage of the six numbered key fields are
	 * - array(0 ,1, 2, 3, 4, 5
	 *    0 variable name - the name of the saved variable.
	 *    1 label - the text to show on the settings page.
	 *    2 saveto - file or db, where to save the variable name - value pair.
	 *    3 type - type of data to display int, float, text, check, select, password.
	 *    4 size - false or field size, if type is select, this needs to be an array of select options.
	 *    5 help - '' or helptxt variable name.
	 *  )
	 * - The following named keys are also permitted
	 *    'disabled' =>
	 *    'postinput' =>
	 *    'preinput' =>
	 *    'subtext' =>
	 *    'force_div_id' =>
	 *    'skip_verify_pass' =>
	 */
	public function prepare()
	{
		global $modSettings;

		$defines = [
			'boarddir',
			'sourcedir',
			'cachedir',
		];

		$safe_strings = [
			'mtitle',
			'mmessage',
			'mbname',
		];

		foreach ($this->configVars as $configVar)
		{
			$new_setting = $configVar;

			if (is_array($configVar) && isset($configVar[1]))
			{
				$varname = $configVar[0];
				global ${$varname};

				// Rewrite the definition a bit.
				$new_setting[0] = $configVar[3];
				$new_setting[1] = $configVar[0];
				$new_setting['text_label'] = $configVar[1];

				if (isset($configVar[4]))
				{
					$new_setting[2] = $configVar[4];
				}

				if (isset($configVar[5]))
				{
					$new_setting['helptext'] = $configVar[5];
				}

				// Special value needed from the settings file?
				if ($configVar[2] === 'file')
				{
					$value = in_array($varname, $defines, true) ? constant(strtoupper($varname)) : ${$varname};

					if (in_array($varname, $safe_strings, true))
					{
						$new_setting['mask'] = 'nohtml';
						$value = strtr($value, [Util::htmlspecialchars('<br />') => "\n"]);
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
	 * Typically, this method is used from admin screens, just like this entire class.
	 * They're also available for addons and integrations.
	 *
	 * What it does:
	 *
	 * - Updates the Settings.php file with the changes supplied in new_settings.
	 * - Expects new_settings to be an associative array, with the keys as the
	 *   variable names in Settings.php, and the values the variable values.
	 * - Does not escape or quote values.
	 * - Preserves case, formatting, and additional options in file.
	 * - Writes nothing if the resulting file is less than 10 lines
	 *   in length (sanity check for read lock.)
	 * - Check for changes to db_last_error and passes those off to a separate handler
	 * - Attempts to create a backup file and will use it should the writing of the
	 *   new settings file fail
	 */
	public function save()
	{
		$this->fileFunc = FileFunctions::instance();

		$this->_cleanSettings();

		// When was Settings.php last changed?
		$this->last_settings_change = filemtime(BOARDDIR . '/Settings.php');

		// Load the settings file.
		$settingsFile = trim(file_get_contents(BOARDDIR . '/Settings.php'));

		// Break it up based on \r or \n, and then clean out extra characters.
		if (str_contains($settingsFile, "\n"))
		{
			$this->settingsArray = explode("\n", $settingsFile);
		}
		elseif (str_contains($settingsFile, "\r"))
		{
			$this->settingsArray = explode("\r", $settingsFile);
		}
		else
		{
			return;
		}

		$this->_prepareSettings();
		$this->_updateSettingsFile();
		$this->_extractDbVars();
	}

	/**
	 * For all known configuration values, ensures they are properly cast / escaped
	 */
	private function _cleanSettings(): void
	{
		$this->_fixCookieName();
		$this->_fixBoardUrl();

		// Any passwords?
		$config_passwords = [
			'db_passwd',
			'ssi_db_passwd',
			'cache_password',
		];

		// All the strings to write.
		$config_strs = [
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
			'cache_servers',
			'url_format',
			'cache_uid',
		];

		// These need HTML encoded. Be sure they all exist in $config_strs!
		$safe_strings = [
			'mtitle',
			'mmessage',
			'mbname',
		];

		// All the numeric variables.
		$config_ints = [
			'cache_enable',
		];

		// All the checkboxes.
		$config_bools = [
			'db_persist',
			'db_error_send',
			'maintenance',
		];

		// Now sort everything into a big array and figure out arrays etc.
		$this->cleanPasswords($config_passwords);

		// Escape and update Setting strings
		$this->cleanStrings($config_strs, $safe_strings);

		// Ints are saved as integers
		$this->cleanInts($config_ints);

		// Convert checkbox selections to 0 / 1
		$this->cleanBools($config_bools);
	}

	/**
	 * Fix the cookie name by removing invalid characters
	 */
	private function _fixCookieName(): void
	{
		// Fix the darn stupid cookiename! (more may not be allowed, but these for sure!)
		if (isset($this->configValues['cookiename']))
		{
			$this->configValues['cookiename'] = preg_replace('~[,;\s\.$]+~u', '', $this->configValues['cookiename']);
		}
	}

	/**
	 * Fix the forum's URL if necessary so that it is a valid root url
	 */
	private function _fixBoardUrl(): void
	{
		if (isset($this->configValues['boardurl']))
		{
			if (str_ends_with($this->configValues['boardurl'], '/index.php'))
			{
				$this->configValues['boardurl'] = substr($this->configValues['boardurl'], 0, -10);
			}
			elseif (str_ends_with($this->configValues['boardurl'], '/'))
			{
				$this->configValues['boardurl'] = substr($this->configValues['boardurl'], 0, -1);
			}

			$this->configValues['boardurl'] = addProtocol($this->configValues['boardurl'], ['http://', 'https://', 'file://']);
		}
	}

	/**
	 * Clean passwords and add them to the new settings array
	 *
	 * @param array $config_passwords The array of config passwords to clean
	 */
	public function cleanPasswords(array $config_passwords): void
	{
		foreach ($config_passwords as $configVar)
		{
			// Handle skip_verify_pass.  Only password[0] will exist from the form
			$key = $this->_array_key_exists__recursive($this->configVars, $configVar, 0);
			if ($key !== false
				&& !empty($this->configVars[$key]['skip_verify_pass'])
				&& $this->configValues[$configVar][0] !== '*#fakepass#*')
			{
				$this->new_settings[$configVar] = "'" . addcslashes($this->configValues[$configVar][0], '\'\\') . "'";
				continue;
			}

			// Validate the _confirm password box exists
			if (!isset($this->configValues[$configVar][1]))
			{
				continue;
			}

			// And that it has the same password
			if ($this->configValues[$configVar][0] !== $this->configValues[$configVar][1])
			{
				continue;
			}

			$this->new_settings[$configVar] = "'" . addcslashes($this->configValues[$configVar][0], '\'\\') . "'";
		}
	}

	/**
	 * Clean strings in the configuration values by escaping characters and applying safe transformations
	 * and add them to the new settings array
	 *
	 * @param array $config_strs The configuration strings to clean
	 * @param array $safe_strings The safe strings that should receive additional transformations
	 */
	public function cleanStrings(array $config_strs, array $safe_strings): void
	{
		foreach ($config_strs as $configVar)
		{
			if (isset($this->configValues[$configVar]))
			{
				if (in_array($configVar, $safe_strings, true))
				{
					$this->new_settings[$configVar] = "'" . addcslashes(Util::htmlspecialchars(strtr($this->configValues[$configVar], ["\n" => '<br />', "\r" => '']), ENT_QUOTES), '\'\\') . "'";
				}
				else
				{
					$this->new_settings[$configVar] = "'" . addcslashes($this->configValues[$configVar], '\'\\') . "'";
				}
			}
		}
	}

	/**
	 * Clean/cast integer values in the configuration array and add them to the new settings array
	 *
	 * @param array $config_ints The array of configuration variables to clean
	 *
	 * @return void
	 */
	public function cleanInts(array $config_ints): void
	{
		foreach ($config_ints as $configVar)
		{
			if (isset($this->configValues[$configVar]))
			{
				$this->new_settings[$configVar] = (int) $this->configValues[$configVar];
			}
		}
	}

	/**
	 * Clean boolean values in the provided config array to be 0 or 1 and add them to the new settings array
	 *
	 * @param array $config_bools The array of boolean keys to clean.
	 * @return void
	 */
	public function cleanBools(array $config_bools): void
	{
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
	 * @param string $needle
	 * @param array $haystack
	 *
	 * @return bool
	 */
	private function _array_value_exists__recursive($needle, $haystack): bool
	{
		foreach ($haystack as $item)
		{
			if ($item === $needle || (is_array($item) && $this->_array_value_exists__recursive($needle, $item)))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Recursively search for a value in a multidimensional array and return the key
	 *
	 * @param array $haystack The array to search in
	 * @param mixed $needle The value to search for
	 * @param mixed $index The index to compare against (optional)
	 * @return string|int|false The key of the found value, false if array search completed without finding value
	 */
	private function _array_key_exists__recursive($haystack, $needle, $index = null)
	{
		$aIt = new \RecursiveArrayIterator($haystack);
		$it = new \RecursiveIteratorIterator($aIt);

		while ($it->valid())
		{
			if (((isset($index) && $it->key() === $index) || !isset($index))
				&& $it->current() === $needle)
			{
				return $aIt->key();
			}

			$it->next();
		}

		// If the loop completed without finding the value, return false
		return false;
	}

	/**
	 * Updates / Validates the Settings array for later output.
	 *
	 * - Updates any values that have been changed.
	 * - Key/value pairs that did not exist are added at the end of the array.
	 * - Ensures the completed array is valid for later output
	 */
	private function _prepareSettings(): void
	{
		// Presumably, the file has to have stuff in it for this function to be called :P.
		if (count($this->settingsArray) < 10)
		{
			return;
		}

		// remove any /r's that made their way in here
		foreach ($this->settingsArray as $k => $dummy)
		{
			$this->settingsArray[$k] = strtr($dummy, ["\r" => '']) . "\n";
		}

		// go line by line and see what's changing
		for ($i = 0, $n = count($this->settingsArray); $i < $n; $i++)
		{
			// Don't trim or bother with it if it's not a variable.
			if (!str_starts_with($this->settingsArray[$i], '$'))
			{
				continue;
			}

			$this->settingsArray[$i] = trim($this->settingsArray[$i]) . "\n";

			// Look through the variables to set...
			foreach ($this->new_settings as $var => $val)
			{
				if (strncasecmp($this->settingsArray[$i], '$' . $var, 1 + strlen($var)) === 0)
				{
					$comment = strstr(substr(un_htmlspecialchars($this->settingsArray[$i]), strpos(un_htmlspecialchars($this->settingsArray[$i]), ';')), '#');
					$this->settingsArray[$i] = '$' . $var . ' = ' . $val . ';' . ($comment === '' ? '' : "\t\t" . rtrim($comment)) . "\n";

					// This one's been 'used', so to speak.
					unset($this->new_settings[$var]);
				}
			}

			// End of the file ... maybe
			if (str_starts_with(trim($this->settingsArray[$i]), '?>'))
			{
				$end = $i;
			}
		}

		// This should never happen, but apparently it is happening.
		if (empty($end) || $end < 10)
		{
			$end = count($this->settingsArray) - 1;
		}

		// Still more variables to go?  Then let's add them at the end.
		if (!empty($this->new_settings))
		{
			if (trim($this->settingsArray[$end]) === '?>')
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
	 *
	 * This function will add the variables passed to it in $this->new_settings,
	 * to the Settings.php file.
	 */
	private function _updateSettingsFile(): void
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
		// Check before you act: if cache is enabled, we can do a simple writing test
		// to validate that we even write things on this filesystem.
		if ((!defined('CACHEDIR') || !$this->fileFunc->fileExists(CACHEDIR)) && $this->fileFunc->fileExists(BOARDDIR . '/cache'))
		{
			$tmp_cache = BOARDDIR . '/cache';
		}
		else
		{
			$tmp_cache = CACHEDIR;
		}

		$test_fp = @fopen($tmp_cache . '/settings_update.tmp', 'wb+');
		if ($test_fp)
		{
			fclose($test_fp);
			$written_bytes = file_put_contents($tmp_cache . '/settings_update.tmp', 'test', LOCK_EX);
			$this->fileFunc->delete($tmp_cache . '/settings_update.tmp');

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
			$settings_backup_fail = !$this->fileFunc->isWritable(BOARDDIR . '/Settings_bak.php') || !@copy(BOARDDIR . '/Settings.php', BOARDDIR . '/Settings_bak.php');
			$settings_backup_fail = $settings_backup_fail ?: !$this->fileFunc->fileExists(BOARDDIR . '/Settings_bak.php') || filesize(BOARDDIR . '/Settings_bak.php') === 0;

			// Write out the new
			$write_settings = implode('', $this->settingsArray);
			$written_bytes = file_put_contents(BOARDDIR . '/Settings.php', $write_settings, LOCK_EX);

			// Survey says ...
			if (!$settings_backup_fail && $written_bytes !== strlen($write_settings))
			{
				// Well, this is not good at all, let's see if we can save this
				$context['settings_message'] = 'settings_error';

				if ($this->fileFunc->fileExists(BOARDDIR . '/Settings_bak.php'))
				{
					@copy(BOARDDIR . '/Settings_bak.php', BOARDDIR . '/Settings.php');
				}
			}

			if (extension_loaded('Zend OPcache') && ini_get('opcache.enable') &&
				((ini_get('opcache.restrict_api') === '' || stripos(BOARDDIR, (string) ini_get('opcache.restrict_api')) !== 0)))
			{
				opcache_invalidate(BOARDDIR . '/Settings.php');
			}
		}
	}

	/**
	 * Find and save the new database-based settings, if any
	 */
	private function _extractDbVars(): void
	{
		// Now loop through the remaining (database-based) settings.
		$this->configVars = array_map(
			static function ($configVar) {
				// We just saved the file-based settings, so skip their definitions.
				if (!is_array($configVar) || $configVar[2] === 'file')
				{
					return '';
				}

				// Rewrite the definition a bit for the database-based settings in a mixed File adapter
				if ($configVar[2] === 'db')
				{
					// For select types, include the options array from position 4
					if ($configVar[3] === 'select' && isset($configVar[4]))
					{
						return [$configVar[3], $configVar[0], $configVar[4]];
					}

					return [$configVar[3], $configVar[0]];
				}

				// This is a regular config var requiring no special treatment.
				return $configVar;
			}, $this->configVars
		);

		// Save the new database-based settings, if any.
		parent::save();
	}
}
