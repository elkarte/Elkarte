<?php

/**
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 *
 */

namespace BBC;

class SmileyParser
{
	protected $enabled = true;
	protected $smileys = array();
	// This smiley regex makes sure it doesn't parse smileys within code tags (so [url=mailto:David@bla.com] doesn't parse the :D smiley)
	protected $search = array();
	protected $replace = array();
	protected $marker = "\r";
	protected $path = '';

	public function __construct($path, array $smileys = array())
	{
		$this->setPath($path);

		if ($this->enabled)
		{
			$this->smileys = empty($smileys) ? $this->load() : $smileys;
		}
	}

	public function setEnabled($toggle)
	{
		$this->enabled = (bool) $toggle;
		return $this;
	}

	public function setMarker($marker)
	{
		$this->marker = $marker;
		return $this;
	}

	public function setPath($path)
	{
		$this->path = htmlspecialchars($path);
		return $this;
	}

	/**
	 * Parse smileys in the passed message.
	 *
	 * What it does:
	 * - The smiley parsing function which makes pretty faces appear :).
	 * - If custom smiley sets are turned off by smiley_enable, the default set of smileys will be used.
	 * - These are specifically not parsed in code tags [url=mailto:Dad@blah.com]
	 * - Caches the smileys from the database or array in memory.
	 * - Doesn't return anything, but rather modifies message directly.
	 *
	 * @param string $message
	 */
	public function parseBlock(&$message)
	{
		// No smiley set at all?!
		if (!$this->enabled || $message === '' || trim($message) === '')
		{
			return;
		}

		// Replace away!
		$message = preg_replace_callback($this->search, array($this, 'parser_callback'), $message);
	}

	public function parse(&$message)
	{
		// Parse the smileys within the parts where it can be done safely.
		if ($this->enabled && trim($message) !== '')
		{
			$message_parts = explode($this->marker, $message);

			// first part (0) parse smileys. Then every other one after that parse smileys
			for ($i = 0, $n = count($message_parts); $i < $n; $i += 2)
			{
				$this->parseBlock($message_parts[$i]);
			}

			$message = implode('', $message_parts);
		}
		// No smileys, just get rid of the markers.
		else
		{
			$message = str_replace($this->marker, '', $message);
		}
	}

	public function add($from, $to, $description)
	{

	}

	protected function parser_callback(array $matches)
	{
		return $this->replace[$matches[0]];
	}

	protected function setSearchReplace($smileysfrom, $smileysto, $smileysdescs)
	{
		$searchParts = array();

		for ($i = 0, $n = count($smileysfrom); $i < $n; $i++)
		{
			$specialChars = htmlspecialchars($smileysfrom[$i], ENT_QUOTES);
			$smileyCode = '<img src="' . $this->path . $smileysto[$i] . '" alt="' . strtr($specialChars, array(':' => '&#58;', '(' => '&#40;', ')' => '&#41;', '$' => '&#36;', '[' => '&#091;')). '" title="' . strtr(htmlspecialchars($smileysdescs[$i]), array(':' => '&#58;', '(' => '&#40;', ')' => '&#41;', '$' => '&#36;', '[' => '&#091;')) . '" class="smiley" />';

			$this->replace[$smileysfrom[$i]] = $smileyCode;

			$searchParts[] = preg_quote($smileysfrom[$i], '~');
			if ($smileysfrom[$i] != $specialChars)
			{
				$this->replace[$specialChars] = $smileyCode;
				$searchParts[] = preg_quote($specialChars, '~');
			}
		}

		$this->search = '~(?<=[>:\?\.\s\x{A0}[\]()*\\\;]|^)(' . implode('|', $searchParts) . ')(?=[^[:alpha:]0-9]|$)~';
	}

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

		$this->setSearchReplace($smileysfrom, $smileysto, $smileysdescs);
	}

	protected function getDefault()
	{
		global $txt;

		$smileysfrom = array('>:D', ':D', '::)', '>:(', ':))', ':)', ';)', ';D', ':(', ':o', '8)', ':P', '???', ':-[', ':-X', ':-*', ':\'(', ':-\\', '^-^', 'O0', 'C:-)', 'O:)');
		$smileysto = array('evil.gif', 'cheesy.gif', 'rolleyes.gif', 'angry.gif', 'laugh.gif', 'smiley.gif', 'wink.gif', 'grin.gif', 'sad.gif', 'shocked.gif', 'cool.gif', 'tongue.gif', 'huh.gif', 'embarrassed.gif', 'lipsrsealed.gif', 'kiss.gif', 'cry.gif', 'undecided.gif', 'azn.gif', 'afro.gif', 'police.gif', 'angel.gif');
		$smileysdescs = array('', $txt['icon_cheesy'], $txt['icon_rolleyes'], $txt['icon_angry'], $txt['icon_laugh'], $txt['icon_smiley'], $txt['icon_wink'], $txt['icon_grin'], $txt['icon_sad'], $txt['icon_shocked'], $txt['icon_cool'], $txt['icon_tongue'], $txt['icon_huh'], $txt['icon_embarrassed'], $txt['icon_lips'], $txt['icon_kiss'], $txt['icon_cry'], $txt['icon_undecided'], '', '', '', $txt['icon_angel']);

		return array($smileysfrom, $smileysto, $smileysdescs);
	}

	protected function getFromDB()
	{
		// Load the smileys in reverse order by length so they don't get parsed wrong.
		if (($temp = cache_get_data('parsing_smileys', 480)) == null)
		{
			$smileysfrom = array();
			$smileysto = array();
			$smileysdescs = array();

			$db = database();

			$db->fetchQueryCallback('
					SELECT code, filename, description
					FROM {db_prefix}smileys
					ORDER BY LENGTH(code) DESC',
				array(
				),
				function($row) use (&$smileysfrom, &$smileysto, &$smileysdescs)
				{
					$smileysfrom[] = $row['code'];
					$smileysto[] = htmlspecialchars($row['filename']);
					$smileysdescs[] = $row['description'];
				}
			);

			$temp = array($smileysfrom, $smileysto, $smileysdescs);

			cache_put_data('parsing_smileys', $temp, 480);
		}

		return $temp;
	}
}