<?php

/**
 * Utility class for search functionality.
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

namespace ElkArte\Search;

/**
 * Actually do the searches
 */
class SearchArray
{
	/**
	 * Words not be be found in the search results (-word)
	 * @var array
	 */
	private $_excludedWords = array();

	/**
	 * Simplify the fulltext search
	 * @var bool
	 */
	private $_search_simple_fulltext = false;

	/**
	 * If we are performing a boolean or simple search
	 * @var bool
	 */
	private $_no_regexp = false;

	/**
	 * Holds the words and phrases to be searched on
	 * @var array
	 */
	private $_searchArray = array();

	/**
	 * Words we do not search due to length or common terms
	 * @var array
	 */
	private $_blacklisted_words = array();

	/**
	 * If search words were found on the blacklist
	 * @var bool
	 */
	private $_foundBlackListedWords = false;

	/**
	 * Holds words that will not be search on to inform the user they were skipped
	 * @var array
	 */
	private $_ignored = array();

	/**
	 * $_search_params will carry all settings that differ from the default search parameters.
	 *
	 * That way, the URLs involved in a search page will be kept as short as possible.
	 */
	protected $_search_string = array();

	/**
	 * Usual constructor that does what any constructor does.
	 *
	 * @param string $search_string
	 * @param string[] $blacklisted_words
	 * @param bool $search_simple_fulltext
	 */
	public function __construct($search_string, $blacklisted_words, $search_simple_fulltext = false)
	{
		$this->_search_string = $search_string;
		$this->_search_simple_fulltext = $search_simple_fulltext;
		$this->_blacklisted_words = $blacklisted_words;
		$this->searchArray();
	}

	/**
	 * Builds the search array
	 *
	 * @return array
	 */
	protected function searchArray()
	{
		// Change non-word characters into spaces.
		$stripped_query = preg_replace('~(?:[\x0B\0\x{A0}\t\r\s\n(){}\\[\\]<>!@$%^*.,:+=`\~\?/\\\\]+|&(?:amp|lt|gt|quot);)+~u', ' ', $this->_search_string);

		// Make the query lower case. It's gonna be case insensitive anyway.
		$stripped_query = un_htmlspecialchars(\ElkArte\Util::strtolower($stripped_query));

		// This option will do fulltext searching in the most basic way.
		if ($this->_search_simple_fulltext)
		{
			$stripped_query = strtr($stripped_query, array('"' => ''));
		}

		$this->_no_regexp = preg_match('~&#(?:\d{1,7}|x[0-9a-fA-F]{1,6});~', $stripped_query) === 1;

		// Extract phrase parts first (e.g. some words "this is a phrase" some more words.)
		preg_match_all('/(?:^|\s)([-]?)"([^"]+)"(?:$|\s)/', $stripped_query, $matches, PREG_PATTERN_ORDER);
		$phraseArray = $matches[2];

		// Remove the phrase parts and extract the words.
		$wordArray = preg_replace('~(?:^|\s)(?:[-]?)"(?:[^"]+)"(?:$|\s)~u', ' ', $this->_search_string);
		$wordArray = explode(' ', \ElkArte\Util::htmlspecialchars(un_htmlspecialchars($wordArray), ENT_QUOTES));

		// A minus sign in front of a word excludes the word.... so...
		// .. first, we check for things like -"some words", but not "-some words".
		$phraseArray = $this->_checkExcludePhrase($matches[1], $phraseArray);

		// Now we look for -test, etc.... normaller.
		$wordArray = $this->_checkExcludeWord($wordArray);

		// The remaining words and phrases are all included.
		$this->_searchArray = array_merge($phraseArray, $wordArray);

		// Trim everything and make sure there are no words that are the same.
		foreach ($this->_searchArray as $index => $value)
		{
			// Skip anything practically empty.
			if (($this->_searchArray[$index] = trim($value, '-_\' ')) === '')
			{
				unset($this->_searchArray[$index]);
			}
			// Skip blacklisted words. Make sure to note we skipped them in case we end up with nothing.
			elseif (in_array($this->_searchArray[$index], $this->_blacklisted_words))
			{
				$this->_foundBlackListedWords = true;
				unset($this->_searchArray[$index]);
			}
			// Don't allow very, very short words.
			elseif (\ElkArte\Util::strlen($value) < 2)
			{
				$this->_ignored[] = $value;
				unset($this->_searchArray[$index]);
			}
		}

		$this->_searchArray = array_slice(array_unique($this->_searchArray), 0, 10);

		return $this->_searchArray;
	}

	public function getSearchArray()
	{
		return $this->_searchArray;
	}

	public function getExcludedWords()
	{
		return $this->_excludedWords;
	}

	public function getNoRegexp()
	{
		return $this->_no_regexp;
	}

	public function foundBlackListedWords()
	{
		return $this->_foundBlackListedWords;
	}

	public function getIgnored()
	{
		return $this->_ignored;
	}

	/**
	 * Looks for phrases that should be excluded from results
	 *
	 * - Check for things like -"some words", but not "-some words"
	 * - Prevents redundancy with blacklisted words
	 *
	 * @param string[] $matches
	 * @param string[] $phraseArray
	 *
	 * @return string[]
	 */
	private function _checkExcludePhrase($matches, $phraseArray)
	{
		foreach ($matches as $index => $word)
		{
			if ($word === '-')
			{
				if (($word = trim($phraseArray[$index], '-_\' ')) !== '' && !in_array($word, $this->_blacklisted_words))
				{
					$this->_excludedWords[] = $word;
				}

				unset($phraseArray[$index]);
			}
		}

		return $phraseArray;
	}

	/**
	 * Looks for words that should be excluded in the results (-word)
	 *
	 * - Look for -test, etc
	 * - Prevents excluding blacklisted words since it is redundant
	 *
	 * @param string[] $wordArray
	 *
	 * @return string[]
	 */
	private function _checkExcludeWord($wordArray)
	{
		foreach ($wordArray as $index => $word)
		{
			if (strpos(trim($word), '-') === 0)
			{
				if (($word = trim($word, '-_\' ')) !== '' && !in_array($word, $this->_blacklisted_words))
				{
					$this->_excludedWords[] = $word;
				}

				unset($wordArray[$index]);
			}
		}

		return $wordArray;
	}
}
