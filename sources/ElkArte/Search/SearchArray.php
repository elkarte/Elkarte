<?php

/**
 * Utility class for search functionality.
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

namespace ElkArte\Search;

use ElkArte\AbstractModel;
use ElkArte\Helper\Util;
use ElkArte\Helper\ValuesContainer;

/**
 * Actually do the searches
 */
class SearchArray extends AbstractModel
{
	/** @var array Words not to be found in the search results (-word) */
	private $_excludedWords = [];

	/** @var bool If we are performing a boolean or simple search */
	private $_no_regexp = false;

	/** @var array Holds the words and phrases to be searched on */
	private $_searchArray = [];

	/** @var bool If search words were found on the blocklist */
	private $_foundBlockListedWords = false;

	/** @var array Holds words that will not be searched on to inform the user they were skipped */
	private $_ignored = [];

	/**
	 * Usual constructor that does what any constructor does.
	 *
	 * @param string $_search_string
	 * @param string[] $_blocklist_words
	 * @param bool $_search_simple_fulltext
	 */
	public function __construct(protected $_search_string, private $_blocklist_words, private $_search_simple_fulltext = false)
	{
		parent::__construct();

		// Build the search query appropriate for the API in use
		$search_config = new ValuesContainer([
			'search_index' => $this->_modSettings['search_index'] ?: '',
		]);
		$searchAPI = new SearchApiWrapper($search_config);
		$searchAPI->supportsExtended() ? $this->searchArrayExtended() : $this->searchArray();
		unset($searchAPI);
	}

	/**
	 * Builds the search array
	 *
	 * @return array
	 */
	protected function searchArray(): array
	{
		// Change non-word characters into spaces.
		$stripped_query = $this->cleanString($this->_search_string);

		// This option will do fulltext searching in the most basic way.
		if ($this->_search_simple_fulltext)
		{
			$stripped_query = strtr($stripped_query, ['"' => '']);
		}

		$this->_no_regexp = preg_match('~&#(?:\d{1,7}|x[0-9a-fA-F]{1,6});~', $stripped_query) === 1;

		// Extract phrase parts first (e.g., some words "this is a phrase" some more words.)
		preg_match_all('/(?:^|\s)([-]?)"([^"]+)"(?:$|\s)/', $stripped_query, $matches, PREG_PATTERN_ORDER);
		$phraseArray = $matches[2];

		// Remove the phrase parts and extract the words.
		$wordArray = preg_replace('~(?:^|\s)(?:[-]?)"(?:[^"]+)"(?:$|\s)~u', ' ', $this->_search_string);
		$wordArray = explode(' ', Util::htmlspecialchars(un_htmlspecialchars($wordArray), ENT_QUOTES));

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
			if (($this->_searchArray[$index] = trim($value, "-_' ")) === '')
			{
				unset($this->_searchArray[$index]);
			}
			// Skip blocklisted words. Make sure to note we skipped them in case we end up with nothing.
			elseif (in_array($this->_searchArray[$index], $this->_blocklist_words))
			{
				$this->_foundBlockListedWords = true;
				unset($this->_searchArray[$index]);
			}
			// Don't allow very, very short words.
			elseif (Util::strlen($value) < 2)
			{
				$this->_ignored[] = $value;
				unset($this->_searchArray[$index]);
			}
		}

		$this->_searchArray = array_slice(array_unique($this->_searchArray), 0, 10);

		return $this->_searchArray;
	}

	/**
	 * Looks for phrases that should be excluded from results
	 *
	 * - Check for things like -"some words", but not "-some words"
	 * - Prevents redundancy with blocklist words
	 *
	 * @param string[] $matches
	 * @param string[] $phraseArray
	 *
	 * @return string[]
	 */
	private function _checkExcludePhrase($matches, $phraseArray): array
	{
		foreach ($matches as $index => $word)
		{
			if ($word === '-')
			{
				if (($word = trim($phraseArray[$index], "-_' ")) !== '' && !in_array($word, $this->_blocklist_words))
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
	 * - Look for -test, etc.
	 * - Prevents excluding blocklist words since it is redundant
	 *
	 * @param string[] $wordArray
	 *
	 * @return string[]
	 */
	private function _checkExcludeWord($wordArray): array
	{
		foreach ($wordArray as $index => $word)
		{
			if (str_starts_with(trim($word), '-'))
			{
				if (($word = trim($word, "-_' ")) !== '' && !in_array($word, $this->_blocklist_words))
				{
					$this->_excludedWords[] = $word;
				}

				unset($wordArray[$index]);
			}
		}

		return $wordArray;
	}

	/**
	 * Constructs a complex/extended boolean mode query to pass back to a search API
	 *
	 * Understands the use of OR | AND & as search modifiers, negations, and phrases.
	 *
	 * @return string
	 */
	public function searchArrayExtended(): string
	{
		$query = SearchHelpers::textToSearchQuery($this->_search_string, $this->_blocklist_words);

		if ($query === '')
		{
			if (!empty($this->_blocklist_words) && trim($this->_search_string) !== '')
			{
				$this->_foundBlockListedWords = true;
			}

			return $this->_searchArray[] = '';
		}

		return $this->_searchArray[] = $query;
	}

	/**
	 * Cleans a string of everything but alphanumeric characters, and certain
	 * special characters ",-,_ so -movie or "animal farm" are preserved
	 *
	 * @param string $string A string to clean
	 * @return string A cleaned-up string
	 */
	public function cleanString($string): string
	{
		return SearchHelpers::cleanString($string);
	}

	/**
	 * Retrieves the search array
	 *
	 * @return array The search array
	 */
	public function getSearchArray(): array
	{
		return $this->_searchArray;
	}

	/**
	 * Retrieves the array of excluded words
	 *
	 * @return array The array of excluded words
	 */
	public function getExcludedWords(): array
	{
		return $this->_excludedWords;
	}

	/**
	 * Get the value of the _no_regexp property
	 *
	 * @return bool
	 */
	public function getNoRegexp(): bool
	{
		return $this->_no_regexp;
	}

	/**
	 * Returns if blocklisted words are found
	 *
	 * @return bool
	 */
	public function foundBlockListedWords(): bool
	{
		return $this->_foundBlockListedWords;
	}

	/**
	 * Returns the array of ignored items
	 *
	 * @return array The array of ignored items
	 */
	public function getIgnored(): array
	{
		return $this->_ignored;
	}
}
