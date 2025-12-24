<?php

/**
 * This class takes care of loading language files
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\Languages;

use ElkArte\Database\QueryInterface;
use ElkArte\Debug;
use ElkArte\Errors;
use ElkArte\User;

/**
 * This class takes care of loading language files
 */
class Loader
{
	/** @var string the area lexicon file to load */
	protected $path = '';

	/** @var QueryInterface Good old db */
	protected $db = '';

	/** @var string the language in use */
	protected $language = 'English';

	/** @var string The string representation of the variable for the loaded results */
	protected $variableName = '';

	/** @var bool if to fallback when we can find a request language area file */
	protected $loadFallback = true;

	/** @var array */
	protected $variable = [];

	/** @var string[] Holds the name of the files already loaded to load them only once */
	protected $loaded = [];

	/**
	 * The constructor
	 *
	 * @param string|null $lang area lexicon file to load
	 * @param array $variable to return string in
	 * @param QueryInterface $db
	 * @param string $variable_name
	 */
	public function __construct($lang, &$variable, QueryInterface $db, string $variable_name = 'txt')
	{
		global $language;

		$this->path = LANGUAGEDIR . '/';
		$this->db = $db;
		$this->variable = &$variable;
		$this->variableName = $variable_name;

		// Normalize the language name
		$lang = $lang ?: User::$info?->language ?: $language ?: 'English';
		$this->language = ucfirst(basename((string) $lang, '.php'));

		if (empty($this->variable))
		{
			$this->variable = [];
		}
	}

	/**
	 * If we should use a fallback language when the requested one is not found
	 *
	 * @param bool $newStatus
	 */
	public function setFallback(bool $newStatus): void
	{
		$this->loadFallback = $newStatus;
	}

	/**
	 * Set the path where we should be looking for files
	 *
	 * @param string $path
	 */
	public function changePath($path): void
	{
		$this->path = $path;
	}

	/**
	 * Does the real work of looking for, then loading the area files.  Will
	 * implement a language fallback if enabled.
	 *
	 * @param string $file_name area language file to load, separate multiple with a +
	 * @param bool $fatal what to do if we cannot load the requested area
	 * @param bool $fix_calendar_arrays if to update the calendar [] as well
	 */
	public function load($file_name, $fatal = true, $fix_calendar_arrays = false): void
	{
		$file_names = explode('+', $file_name);

		// For each file open it up and write it out!
		foreach ($file_names as $file)
		{
			$this->handleFile(ucfirst($file), $fatal);
		}

		// Load custom strings from the database
		$this->loadFromDb($file_names);

		if ($fix_calendar_arrays)
		{
			$this->fix_calendar_text();
		}
	}

	/**
	 * Handle the loading of a language file.
	 *
	 * @param string $file The name of the language file to load.
	 * @param bool $fatal Whether the absence of the file is fatal (true) or not (false).
	 *
	 * @return void
	 */
	private function handleFile($file, $fatal): void
	{
		global $db_show_debug;

		if (isset($this->loaded[$file]) || in_array($file, Editor::IGNORE_FILES, true))
		{
			return;
		}

		// A fallback is used to provide core language strings that are missing from another language
		$found_fallback = false;
		if ($this->loadFallback)
		{
			$found_fallback = $this->loadFile($file, 'English');
		}

		$found = $this->loadFile($file, $this->language);
		$this->loaded[$file] = true;

		// Keep track of what we're up to, soldier.
		if (!$found && $db_show_debug === true)
		{
			$this->logDebug($file);
		}

		// That couldn't be found!  Log the error, but *try* to continue normally.
		if (!$found && $fatal)
		{
			$this->logError($file, $found_fallback);
		}
	}

	/**
	 * Logs a debug message when a language file is not found.
	 *
	 * @param string $file The name of the file
	 * @return void
	 */
	private function logDebug($file): void
	{
		Debug::instance()->add(
			'language_files',
			$file . '.' . $this->language . ' (' . str_replace(BOARDDIR, '', $this->path) . ')'
		);
	}

	/**
	 * Logs a language loading error and throws an exception if necessary.
	 *
	 * @param string $file The file name.
	 * @param bool $found_fallback Whether a fallback was found or not.
	 * @return void
	 */
	private function logError($file, $found_fallback): void
	{
		global $txt;

		if ($file === '')
		{
			return;
		}

		if ($file !== 'Addons')
		{
			// Could have failed to load the index language file!
			$message = $txt['theme_language_error'] ?? 'Unable to load the \'%1$s\' language file.';
			Errors::instance()->log_error(
				sprintf(
					$message,
					$file . '.' . $this->language,
					'template'
				)
			);
		}

		if ($found_fallback === false)
		{
			throw new \RuntimeException("No fallback found for file: {$file}");
		}
	}

	/**
	 * Load in a custom replacement string from the DB
	 *
	 * @param string[] $files
	 */
	protected function loadFromDb($files): void
	{
		$result = $this->db->fetchQuery('
			SELECT 
				language_key, value
			FROM {db_prefix}languages
			WHERE language = {string:language}
				AND file IN ({array_string:files})',
			[
				'language' => $this->language,
				'files' => $files
			]
		);
		while ($row = $result->fetch_assoc())
		{
			$this->variable[$row['language_key']] = $row['value'];
		}

		$result->free_result();
	}

	/**
	 * Load a language file, merging localization strings into the default.
	 *
	 * @param string $name the lexicon file to load.
	 * @param string $language and in which language
	 * @return bool
	 */
	protected function loadFile($name, $language): bool
	{
		// Split the name if it contains a forward slash
		$parts = explode('/', $name, 2);

		// Some addons may have a language file in a subdirectory, so we need to check for that too.
		if (count($parts) === 2)
		{
			$filepath = $this->path . $parts[0] . '/' . basename($language, '.php') . '/' . $parts[1] . '.php';
		}
		else
		{
			$filepath = $this->path . $name . '/' . basename($language, '.php') . '.php';
		}

		if (file_exists($filepath))
		{
			require($filepath);
			if (!empty(${$this->variableName}))
			{
				$this->variable = array_merge($this->variable, ${$this->variableName});
			}

			return true;
		}

		return false;
	}

	/**
	 * Loads / Sets arrays for use in date display
	 * This is here and not in a language file for two reasons:
	 *  1. The code requires the structure, so better be sure to have it the way we are supposed to have it
	 *  2. Transifex (that we use for translating the strings) doesn't support array of arrays, so if we
	 * move this to a language file, we'd need to move away from Tx.
	 */
	protected function fix_calendar_text(): void
	{
		global $txt;

		$txt['days'] = [
			$txt['sunday'],
			$txt['monday'],
			$txt['tuesday'],
			$txt['wednesday'],
			$txt['thursday'],
			$txt['friday'],
			$txt['saturday'],
		];
		$txt['days_short'] = [
			$txt['sunday_short'],
			$txt['monday_short'],
			$txt['tuesday_short'],
			$txt['wednesday_short'],
			$txt['thursday_short'],
			$txt['friday_short'],
			$txt['saturday_short'],
		];
		$txt['months'] = [
			1 => $txt['january'],
			$txt['february'],
			$txt['march'],
			$txt['april'],
			$txt['may'],
			$txt['june'],
			$txt['july'],
			$txt['august'],
			$txt['september'],
			$txt['october'],
			$txt['november'],
			$txt['december'],
		];
		$txt['months_titles'] = [
			1 => $txt['january_titles'],
			$txt['february_titles'],
			$txt['march_titles'],
			$txt['april_titles'],
			$txt['may_titles'],
			$txt['june_titles'],
			$txt['july_titles'],
			$txt['august_titles'],
			$txt['september_titles'],
			$txt['october_titles'],
			$txt['november_titles'],
			$txt['december_titles'],
		];
		$txt['months_short'] = [
			1 => $txt['january_short'],
			$txt['february_short'],
			$txt['march_short'],
			$txt['april_short'],
			$txt['may_short'],
			$txt['june_short'],
			$txt['july_short'],
			$txt['august_short'],
			$txt['september_short'],
			$txt['october_short'],
			$txt['november_short'],
			$txt['december_short'],
		];
	}
}
