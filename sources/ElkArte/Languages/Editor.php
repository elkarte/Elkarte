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

use ElkArte\Util;
use ElkArte\Database\QueryInterface;

/**
 * This class takes care of loading language files
 */
class Editor
{
	public const IGNORE_FILES = ['Agreement'];

	/** @var string */
	protected $path = '';

	/** @var QueryInterface */
	protected $db = '';

	/** @var string */
	protected $language = 'English';

	/** @var string */
	protected $variable_name = '';

	/** @var Loader */
	protected $loaders = [];

	/** @var mixed[] */
	protected $txt = '';
	protected $editortxt = '';
	protected $txtBirthdayEmails = '';
	protected $editing_strings = [];
	protected $ignore_keys = [];

	public function __construct($lang = null, QueryInterface $db, string $variable_name = 'txt')
	{
		if ($lang !== null)
		{
			$this->language = ucfirst($lang);
		}

		$this->path = SOURCEDIR . '/ElkArte/Languages/';
		$this->db = $db;

		$this->txt = [];
		$this->editortxt = [];
		$this->txtBirthdayEmails = [];
		$this->variable_name = $variable_name;
		$this->loaders = [
			'txt' => new Loader($lang, $this->txt, $this->db, 'txt'),
			'editortxt' => new Loader($lang, $this->editortxt, $this->db, 'editortxt'),
			'txtBirthdayEmails' => new Loader($lang, $this->txtBirthdayEmails, $this->db, 'txtBirthdayEmails'),
		];
		// Ignore some things that are specific of the language pack (or just require some casting I don't want be bother of considering, like lang_capitalize_dates).
		$this->ignore_keys = ['lang_character_set', 'lang_locale', 'lang_dictionary', 'lang_spelling', 'lang_rtl', 'lang_capitalize_dates'];
	}

	public function changePath($path)
	{
		$this->path = $path;
	}

	public function load($file_name)
	{
		foreach (array_keys($this->loaders) as $k)
		{
			$this->loaders[$k]->load($file_name, true, false);
		}
	}

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

	public function getForEditing()
	{
		$this->editing_strings = [];
		foreach (array_keys($this->loaders) as $k)
		{
			foreach ($this->{$k} as $key => $value)
			{
				if (in_array($key, $this->ignore_keys))
				{
					continue;
				}
				$md5EntryKey = md5($key);
				$editing_string = Util::htmlspecialchars(htmlentities($value));

				$this->editing_strings[$md5EntryKey] = [
					'key' => $md5EntryKey,
					'display_key' => $key,
					'value' => $editing_string,
					'rows' => (int) (strlen($editing_string) / 38) + substr_count($editing_string, "\n") + 1,
				];
			}
		}
		return $this->editing_strings;
	}

	public function save($file_name, $txt)
	{
		if (in_array($file_name, self::IGNORE_FILES))
		{
			return;
		}
		$to_save = [];
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
				$display_key = $this->editing_strings[$key]['display_key'] ?? null;
				if (!isset($this->{$var}[$display_key]))
				{
					continue;
				}
				// For some reason, apparently sometimes a carriage return char (ASCII 13) appears in content from textareas, but we only use line feed (ASCII 10), so... just remove them all.
				$val = str_replace("\r", "", $val);
				if (trim($val) != trim($this->{$var}[$display_key]))
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