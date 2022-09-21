<?php

/**
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace BBC;

use ElkArte\Cache\Cache;
use ElkArte\Emoji;
use ElkArte\FileFunctions;

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
	protected $replace = [];

	/** @var string marker */
	protected $marker = "\r";

	/** @var string path to the smiley set */
	protected $path = '';

	/** @var string dir to the smiley set */
	protected $dir = '';

	/**
	 * SmileyParser constructor.
	 *
	 * @param string $set The smiley set to use
	 */
	public function __construct($set)
	{
		$this->setPath($set);

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
	 * @param string $set
	 *
	 * @return $this
	 */
	public function setPath($set)
	{
		$this->path = $GLOBALS['modSettings']['smileys_url'] . '/' . htmlspecialchars($set) . '/';
		$this->dir = $GLOBALS['modSettings']['smileys_dir'] . '/' . $set . '/';

		return $this;
	}

	/**
	 * Parse smileys in passed message.
	 *
	 * What it does:
	 *   - The smiley parsing function which makes pretty faces appear :)
	 *   - These are specifically not parsed in bbc/code tags [url=mailto:Dad@blah.com]
	 *   - Caches the smileys from the database or array in memory.
	 *   - Returns the modified message directly.
	 *
	 * @param string $message
	 *
	 * @return null|string|string[]
	 */
	public function parseBlock($message)
	{
		// No smiley set at all?!
		if (!$this->enabled
			|| empty($GLOBALS['context']['smiley_enabled'])
			|| trim($message) === '')
		{
			return $message;
		}

		// Replace away!
		return preg_replace_callback($this->search,
			function (array $matches) {
				return $this->parser_callback($matches);
			}, $message);
	}

	/**
	 * Parse emoji in passed message.
	 *
	 * What it does:
	 *   - The smiley parsing function which makes emoji appear :man_shrugging:
	 *   - Replaces found tags with image from chosen set
	 *   - Finds keyboard entered emoji text and converts to site image for constitent look
	 *   - Specifically not parsed in bbc/code tags [url=mailto:Dad@blah.com] (defined by BBC Parser)
	 *   - Uses the emoji class to do the replacements
	 *
	 * @param string $message
	 *
	 * @return string
	 */
	public function parseEmoji($message)
	{
		// No Emoji set or message at all?!
		if (!$this->enabled
			|| empty($GLOBALS['context']['emoji_enabled'])
			|| trim($message) === '')
		{
			return $message;
		}

		// Replace away!
		return Emoji::instance()->emojiNameToImage($message, false, false);
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

			// first part (0) parse smileys/emoji. Then every other one after that parse smileys
			for ($i = 0, $n = count($message_parts); $i < $n; $i += 2)
			{
				$message_parts[$i] = $this->parseEmoji($this->parseBlock($message_parts[$i]));
			}

			return implode('', $message_parts);
		}

		// No smileys, or not enabled, just get rid of the markers.
		return str_replace($this->marker, '', $message);
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
		$searchParts = [];
		$fileFunc = FileFunctions::instance();
		$replace = [':' => '&#58;', '(' => '&#40;', ')' => '&#41;', '$' => '&#36;', '[' => '&#091;'];

		foreach ($smileysfrom as $i => $smileysfrom_i)
		{
			$specialChars = htmlspecialchars($smileysfrom_i, ENT_QUOTES);

			// If an :emoji: tag, from smiles ACP, does not have an img file, leave it for emoji parsing
			$possibleEmoji = isset($smileysfrom_i[3]) && $smileysfrom_i[0] === ':' && substr($smileysfrom_i, -1, 1) === ':';
			$filename = $this->dir . $smileysto[$i] . '.' . $GLOBALS['context']['smiley_extension'];
			if (!$possibleEmoji || $fileFunc->fileExists($filename))
			{
				// Either a smiley :) or emoji :smile: with a defined image
				$smileyCode = '<img src="' . $this->path . $smileysto[$i] . '.' . $GLOBALS['context']['smiley_extension'] . '" alt="' . strtr($specialChars, $replace) . '" title="' . strtr(htmlspecialchars($smileysdescs[$i]), $replace) . '" class="smiley" />';
				$this->replace[$smileysfrom_i] = $smileyCode;

				$searchParts[] = preg_quote($smileysfrom_i, '~');
				if ($smileysfrom_i !== $specialChars)
				{
					$this->replace[$specialChars] = $smileyCode;
					$searchParts[] = preg_quote($specialChars, '~');
				}
			}
		}

		// This smiley regex makes sure it doesn't parse smileys within bbc tags
		// (so [url=mailto:David@bla.com] doesn't parse the :D smiley)
		$this->search = '~(?<=[>:\?\.\s\x{A0}[\]()*\\\;]|^)(' . implode('|', $searchParts) . ')(?=[^[:alpha:]0-9]|$)~';
	}

	/**
	 * Load in the enabled smileys, either default ones or from the DB
	 */
	protected function load()
	{
		list ($smileysfrom, $smileysto, $smileysdescs) = $this->getFromDB();

		// Build the search/replace regex
		$this->setSearchReplace($smileysfrom, $smileysto, $smileysdescs);
	}

	/**
	 * Returns the chosen/custom smileys from the database
	 *
	 * @return array
	 */
	protected function getFromDB()
	{
		// Load the smileys in reverse order by length, so they don't get parsed wrong.
		if (!Cache::instance()->getVar($temp, 'parsing_smileys', 600))
		{
			$smileysfrom = [];
			$smileysto = [];
			$smileysdescs = [];

			$db = database();

			$db->fetchQuery('
				SELECT 
				    code, filename, description
				FROM {db_prefix}smileys
				ORDER BY LENGTH(code) DESC',
				[]
			)->fetch_callback(
				function ($row) use (&$smileysfrom, &$smileysto, &$smileysdescs) {
					$smileysfrom[] = $row['code'];
					$smileysto[] = htmlspecialchars($row['filename']);
					$smileysdescs[] = $row['description'];
				}
			);

			// Cache this for a bit
			$temp = [$smileysfrom, $smileysto, $smileysdescs];
			Cache::instance()->put('parsing_smileys', $temp, 600);
		}

		return $temp;
	}
}
