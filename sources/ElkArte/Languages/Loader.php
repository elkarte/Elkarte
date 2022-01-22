<?php

/**
 * This class takes care of loading language files
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Languages;

use ElkArte\Debug;
use ElkArte\Errors;
use ElkArte\Database\QueryInterface;

/**
 * This class takes care of loading language files
 */
class Loader
{
	/** @var string */
	protected $path = '';

	/** @var QueryInterface */
	protected $db = '';

	/** @var string */
	protected $language = 'English';

	/** @var string */
	protected $variable_name = '';

	/** @var bool */
	protected $load_fallback = true;

	/** @var mixed[] */
	protected $variable = true;

	/** @var string[] Holds the name of the files already loaded to load them only once */
	protected $loaded = [];

	public function __construct($lang = null, &$variable, QueryInterface $db, string $variable_name = 'txt')
	{
		if ($lang !== null)
		{
			$this->language = ucfirst($lang);
		}

		$this->path = SOURCEDIR . '/ElkArte/Languages/';
		$this->db = $db;

		$this->variable = &$variable;
		$this->variable_name = $variable_name;
		if (empty($this->variable))
		{
			$this->variable = [];
		}
	}

	public function setFallback(bool $new_Status)
	{
		$this->load_fallback = $new_Status;
	}

	public function changePath($path)
	{
		$this->path = $path;
	}

	public function load($file_name, $fatal = true, $fix_calendar_arrays = false)
	{
		global $db_show_debug, $txt;

		$file_names = explode('+', $file_name);

		// For each file open it up and write it out!
		foreach ($file_names as $file)
		{
			$file = ucfirst($file);
			if (isset($this->loaded[$file]))
			{
				continue;
			}

			$found = false;
			$found_fallback = false;
			if ($this->load_fallback)
			{
				$found_fallback = $this->loadFile($file, 'English');
			}
			$found = $this->loadFile($file, $this->language);

			$this->loaded[$file] = true;

			// Keep track of what we're up to, soldier.
			if ($found && $db_show_debug === true)
			{
				Debug::instance()->add(
					'language_files',
					$file . '.' . $this->language .
					' (' . str_replace(BOARDDIR, '', $this->path) . ')'
				);
			}

			// That couldn't be found!  Log the error, but *try* to continue normally.
			if (!$found && $fatal)
			{
				Errors::instance()->log_error(
					sprintf(
						$txt['theme_language_error'],
						$file . '.' . $this->language,
						'template'
					)
				);
				// If we do have a fallback it may not be necessary to break out.
				if ($found_fallback === false)
				{
					break;
				}
			}
		}
		$this->loadFromDb($file_names);

		if ($fix_calendar_arrays)
		{
			$this->fix_calendar_text();
		}
	}

	protected function loadFromDb($files)
	{
		$result = $this->db->fetchQuery('
			SELECT language_key, value
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

	protected function loadFile($name, $language)
	{
		$filepath = $this->path . $name . '/' . $this->language . '.php';
		if (file_exists($filepath))
		{
			require($filepath);
			if (!empty(${$this->variable_name}))
			{
				$this->variable += ${$this->variable_name};
			}
			return true;
		}
		return false;
	}

	/**
	 * Loads / Sets arrays for use in date display
	 * This is here and not in a language file for two reasons:
	 *  1. the structure is required by the code, so better be sure
	 *     to have it the way we are supposed to have it
	 *  2. Transifex (that we use for translating the strings) doesn't
	 *     support array of arrays, so if we move this to a language file
	 *     we'd need to move away from Tx.
	 */
	protected function fix_calendar_text()
	{
		global $txt;

		$txt['days'] = array(
			$txt['sunday'],
			$txt['monday'],
			$txt['tuesday'],
			$txt['wednesday'],
			$txt['thursday'],
			$txt['friday'],
			$txt['saturday'],
		);
		$txt['days_short'] = array(
			$txt['sunday_short'],
			$txt['monday_short'],
			$txt['tuesday_short'],
			$txt['wednesday_short'],
			$txt['thursday_short'],
			$txt['friday_short'],
			$txt['saturday_short'],
		);
		$txt['months'] = array(
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
		);
		$txt['months_titles'] = array(
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
		);
		$txt['months_short'] = array(
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
		);
	}
}