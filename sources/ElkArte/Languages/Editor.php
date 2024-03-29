<?php

/**
 * This class takes care of Language edits via the ACP
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Languages;

use ElkArte\Database\QueryInterface;
use ElkArte\Helper\Util;

/**
 * This class takes care of handing language edits
 */
class Editor
{
	public const IGNORE_FILES = ['Agreement', 'PrivacyPolicy'];

	/** @var string */
	protected $path = '';

	/** @var QueryInterface */
	protected $db = '';

	/** @var string */
	protected $language = 'English';

	/** @var string */
	protected $variableName = '';

	/** @var Loader */
	protected $loaders = [];

	/** @var array */
	protected $txt = '';

	/** @var array|string */
	protected $editorTxt = '';

	/** @var array|string */
	protected $txtBirthdayEmails = '';

	/** @var array */
	protected $editingStrings = [];

	/** @var array|string[] */
	protected $ignoreKeys = [];

	/**
	 * Constructs a new instance of the class.
	 *
	 * @param string $lang The language code to be used.
	 * @param QueryInterface $db An instance of QueryInterface to interact with the database.
	 * @param string $variable_name The name of the variable. Default is 'txt'.
	 */
	public function __construct($lang, QueryInterface $db, string $variable_name = 'txt')
	{
		if ($lang !== null)
		{
			$this->language = ucfirst($lang);
		}

		$this->path = SOURCEDIR . '/ElkArte/Languages/';
		$this->db = $db;
		$this->txt = [];
		$this->editorTxt = [];
		$this->txtBirthdayEmails = [];
		$this->variableName = $variable_name;

		$this->loaders = [
			'txt' => new Loader($lang, $this->txt, $this->db, 'txt'),
			'editortxt' => new Loader($lang, $this->editorTxt, $this->db, 'editortxt'),
			'txtBirthdayEmails' => new Loader($lang, $this->txtBirthdayEmails, $this->db, 'txtBirthdayEmails'),
		];

		// Ignore some things that are specific of the language pack
		// (or just require some casting I don't want to be bothered of considering, like lang_capitalize_dates).
		$this->ignoreKeys = [
			'lang_character_set',
			'lang_locale',
			'lang_dictionary',
			'lang_spelling',
			'lang_rtl',
			'lang_capitalize_dates'
		];
	}

	/**
	 * Change the path.
	 *
	 * @param string $path The new path.
	 *
	 * @return void
	 */
	public function changePath($path)
	{
		$this->path = $path;
	}

	/**
	 * Load method loads a file using a specified file name.
	 *
	 * @param string $file_name The name of the file to be loaded.
	 *
	 * @return void
	 */
	public function load($file_name)
	{
		foreach (array_keys($this->loaders) as $k)
		{
			$this->loaders[$k]->load($file_name, true, false);
		}
	}

	/**
	 * Get method retrieves an element from the loaders array using the specified index.
	 *
	 * @param int $idx The index of the element to be retrieved.
	 *
	 * @return mixed|null The retrieved element if found, otherwise null.
	 */
	public function get($idx)
	{
		foreach (array_keys($this->loaders) as $k)
		{
			if (isset($this->{$k}[$idx]))
			{
				return $this->{$k}[$idx];
			}
		}

		return null;
	}

	/**
	 * Retrieves an array of strings for editing.
	 *
	 * This method retrieves strings from loaders and prepares them for editing. It applies certain modifications to each string, such as HTML encoding and calculating the number of rows
	 * needed for the editing textarea.
	 *
	 * @return array An array of strings for editing. Each array entry has the following keys:
	 *               - 'key': A MD5 hash of the original string key.
	 *               - 'display_key': The original string key.
	 *               - 'value': The modified string value, HTML encoded.
	 *               - 'rows': The number of rows needed for the editing textarea.
	 */
	public function getForEditing()
	{
		$this->editingStrings = [];
		foreach (array_keys($this->loaders) as $k)
		{
			foreach ($this->{$k} as $key => $value)
			{
				if (in_array($key, $this->ignoreKeys, true))
				{
					continue;
				}

				$md5EntryKey = md5($key);
				$editing_string = Util::htmlspecialchars(htmlentities($value));

				$this->editingStrings[$md5EntryKey] = [
					'key' => $md5EntryKey,
					'display_key' => $key,
					'value' => $editing_string,
					'rows' => (int) (strlen($editing_string) / 38) + substr_count($editing_string, "\n") + 1,
				];
			}
		}

		return $this->editingStrings;
	}

	/**
	 * Save method saves content to the DB with the specified file name.
	 *
	 * @param string $file_name The name of the file to save the content to.
	 * @param array $txt The array of content to save.
	 *
	 * @return void
	 */
	public function save($file_name, $txt)
	{
		if (in_array($file_name, self::IGNORE_FILES))
		{
			return;
		}

		$columns = [
			'language' => 'string-40',
			'file' => 'string-40',
			'language_key' => 'string-40',
			'value' => 'text'
		];

		foreach ($txt as $key => $val)
		{
			foreach (['txt', 'editortxt', 'txtBirthdayEmails'] as $var)
			{
				$display_key = $this->editingStrings[$key]['display_key'] ?? null;
				if (!isset($this->{$var}[$display_key]))
				{
					continue;
				}

				// For some reason, apparently sometimes a carriage return char (ASCII 13) appears in
				// content from textareas, but we only use line feed (ASCII 10), so... just remove them all.
				$val = str_replace("\r", "", $val);
				if (trim($val) !== trim($this->{$var}[$display_key]))
				{
					$this->db->replace('{db_prefix}languages',
						$columns,
						[
							'language' => $this->language,
							'file' => $file_name,
							'language_key' => $display_key,
							'value' => $val
						],
						['language', 'file']
					);
				}
			}
		}
	}
}
