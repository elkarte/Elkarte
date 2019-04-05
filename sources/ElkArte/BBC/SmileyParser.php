<?php

/**
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace BBC;

/**
 * Class SmileyParser
 *
 * @package BBC
 */
class SmileyParser
{
	/** @var bool Are smiley enabled */
	protected $enabled = true;
	/** @var string smiley regex for parse protection */
	protected $search = '';
	/** @var array The replacement images for the ;) */
	protected $replace = array();
	/** @var string marker */
	protected $marker = "\r";
	/** @var string path */
	protected $path = '';

	/**
	 * SmileyParser constructor.
	 *
	 * @param string $path
	 * @param array $smileys
	 */
	public function __construct($path)
	{
		$this->setPath($path);

		if ($this->enabled)
		{
			$this->load();
		}
	}

	/**
	 * Enables/disabled the smiley parsing
	 *
	 * @param bool $toggle
	 *
	 * @return SmileyParser
	 */
	public function setEnabled($toggle)
	{
		$this->enabled = (bool) $toggle;

		return $this;
	}

	/**
	 * Set the smiley separation marker
	 *
	 * @param string $marker
	 *
	 * @return $this
	 */
	public function setMarker($marker)
	{
		$this->marker = $marker;

		return $this;
	}

	/**
	 * Set the image path to the smileys
	 *
	 * @param string $path
	 *
	 * @return $this
	 */
	public function setPath($path)
	{
		$this->path = htmlspecialchars($path);
		return $this;
	}

	/**
	 * Parse smileys in the passed message.
	 *
	 * What it does:
	 *   - The smiley parsing function which makes pretty faces appear :)
	 *   - If custom smiley sets are turned off by smiley_enable, the default set of smileys will be used.
	 *   - These are specifically not parsed in code tags [url=mailto:Dad@blah.com]
	 *   - Caches the smileys from the database or array in memory.
	 *   - Doesn't return anything, but rather modifies message directly.
	 *
	 * @param string $message
	 *
	 * @return null|string|string[]
	 */
	public function parseBlock($message)
	{
		// No smiley set at all?!
		if (!$this->enabled || trim($message) === '')
		{
			return $message;
		}

		// Replace away!
		return preg_replace_callback($this->search, array($this, 'parser_callback'), $message);
	}

	/**
	 * @param string $message
	 *
	 * @return string
	 */
	public function parse($message)
	{
		// Parse the smileys within the parts where it can be done safely.
		if ($this->enabled && trim($message) !== '')
		{
			$message_parts = explode($this->marker, $message);

			// first part (0) parse smileys. Then every other one after that parse smileys
			for ($i = 0, $n = count($message_parts); $i < $n; $i += 2)
			{
				$message_parts[$i] = $this->parseBlock($message_parts[$i]);
			}

			return implode('', $message_parts);
		}
		// No smileys, just get rid of the markers.
		else
		{
			return str_replace($this->marker, '', $message);
		}
	}

	/**
	 * Callback function for parseBlock
	 *
	 * @param array $matches
	 *
	 * @return mixed
	 */
	protected function parser_callback(array $matches)
	{
		return $this->replace[$matches[0]];
	}

	/**
	 * Builds the search and replace sequence for the :) => img
	 *
	 * What it does:
	 *   - Creates the search regex to find the enabled smiles in a message
	 *   - Builds the replacement array for text smiley to image smiley
	 *
	 * @param array $smileysfrom
	 * @param array $smileysto
	 * @param array $smileysdescs
	 */
	protected function setSearchReplace($smileysfrom, $smileysto, $smileysdescs)
	{
		$searchParts = array();

		$replace = array(':' => '&#58;', '(' => '&#40;', ')' => '&#41;', '$' => '&#36;', '[' => '&#091;');

		for ($i = 0, $n = count($smileysfrom); $i < $n; $i++)
		{
			$specialChars = htmlspecialchars($smileysfrom[$i], ENT_QUOTES);
			$smileyCode = '<img src="' . $this->path . $smileysto[$i] . '" alt="' . strtr($specialChars, $replace) . '" title="' . strtr(htmlspecialchars($smileysdescs[$i]), $replace) . '" class="smiley" />';

			$this->replace[$smileysfrom[$i]] = $smileyCode;

			$searchParts[] = preg_quote($smileysfrom[$i], '~');
			if ($smileysfrom[$i] != $specialChars)
			{
				$this->replace[$specialChars] = $smileyCode;
				$searchParts[] = preg_quote($specialChars, '~');
			}
		}

		// This smiley regex makes sure it doesn't parse smileys within code tags
		// (so [url=mailto:David@bla.com] doesn't parse the :D smiley)
		$this->search = '~(?<=[>:\?\.\s\x{A0}[\]()*\\\;]|^)(' . implode('|', $searchParts) . ')(?=[^[:alpha:]0-9]|$)~';
	}

	/**
	 * Load in the enabled smileys, either default ones or from the DB
	 */
	protected function load()
	{
		global $modSettings;

		// Use the default smileys if it is disabled. (better for "portability" of smileys.)
		if (empty($modSettings['smiley_enable']))
		{
			list ($smileysfrom, $smileysto, $smileysdescs) = $this->getDefault();
		}
		else
		{
			list ($smileysfrom, $smileysto, $smileysdescs) = $this->getFromDB();
		}

		// Build the search/replace regex
		$this->setSearchReplace($smileysfrom, $smileysto, $smileysdescs);
	}

	/**
	 * Returns the default / built in array of smileys
	 *
	 * @return array
	 */
	protected function getDefault()
	{
		global $txt;

		$smileysfrom = array('>:D', ':D', '::)', '>:(', ':))', ':)', ';)', ';D', ':(', ':o', '8)', ':P', '???', ':-[', ':-X', ':-*', ':\'(', ':-\\', '^-^', 'O0', 'C:-)', 'O:)');
		$smileysto = array('evil.gif', 'cheesy.gif', 'rolleyes.gif', 'angry.gif', 'laugh.gif', 'smiley.gif', 'wink.gif', 'grin.gif', 'sad.gif', 'shocked.gif', 'cool.gif', 'tongue.gif', 'huh.gif', 'embarrassed.gif', 'lipsrsealed.gif', 'kiss.gif', 'cry.gif', 'undecided.gif', 'azn.gif', 'afro.gif', 'police.gif', 'angel.gif');
		$smileysdescs = array('', $txt['icon_cheesy'], $txt['icon_rolleyes'], $txt['icon_angry'], $txt['icon_laugh'], $txt['icon_smiley'], $txt['icon_wink'], $txt['icon_grin'], $txt['icon_sad'], $txt['icon_shocked'], $txt['icon_cool'], $txt['icon_tongue'], $txt['icon_huh'], $txt['icon_embarrassed'], $txt['icon_lips'], $txt['icon_kiss'], $txt['icon_cry'], $txt['icon_undecided'], '', '', '', $txt['icon_angel']);

		return array($smileysfrom, $smileysto, $smileysdescs);
	}

	/**
	 * Returns the chosen/custom smileys from the database
	 *
	 * @return array
	 */
	protected function getFromDB()
	{
		// Load the smileys in reverse order by length so they don't get parsed wrong.
		if (!\ElkArte\Cache\Cache::instance()->getVar($temp, 'parsing_smileys', 480))
		{
			$smileysfrom = array();
			$smileysto = array();
			$smileysdescs = array();

			$db = database();

			$db->fetchQuery('
				SELECT code, filename, description
				FROM {db_prefix}smileys
				ORDER BY LENGTH(code) DESC',
				[]
			)->fetch_callback(
				function ($row) use (&$smileysfrom, &$smileysto, &$smileysdescs)
				{
					$smileysfrom[] = $row['code'];
					$smileysto[] = htmlspecialchars($row['filename']);
					$smileysdescs[] = $row['description'];
				}
			);

			// Cache this for a bit
			$temp = array($smileysfrom, $smileysto, $smileysdescs);
			\ElkArte\Cache\Cache::instance()->put('parsing_smileys', $temp, 480);
		}

		return $temp;
	}
}
