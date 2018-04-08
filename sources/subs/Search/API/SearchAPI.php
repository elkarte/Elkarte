<?php

/**
 * Abstract class that defines the methods search APIs shall implement
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 dev
 */

namespace ElkArte\Search\API;

/**
 * Abstract class that defines the methods any search API shall implement
 * to properly work with ElkArte
 *
 * @package Search
 */
abstract class SearchAPI
{
	/**
	 * This is the last version of ElkArte that this was tested on, to protect against API changes.
	 * @var string
	 */
	public $version_compatible;

	/**
	 * This won't work with versions of ElkArte less than this.
	 * @var string
	 */
	public $min_elk_version;

	/**
	 * Standard search is supported by default.
	 * @var boolean
	 */
	public $is_supported;

	/**
	 * Any word excluded from the search?
	 * @var array
	 */
	protected $_excludedWords = array();

	/**
	 * 
	 * @var int
	 */
	protected $_num_results = 0;

	/**
	 * What words are banned?
	 * @var array
	 */
	protected $bannedWords = array();

	/**
	 * What is the minimum word length?
	 * @var int
	 */
	protected $min_word_length = 3;

	/**
	 * What databases support the custom index?
	 * @var array
	 */
	protected $supported_databases = array('MySQL', 'PostgreSQL');

	/**
	 * Fulltext::__construct()
	 */
	public function __construct()
	{
		global $modSettings;

		$this->bannedWords = empty($modSettings['search_banned_words']) ? array() : explode(',', $modSettings['search_banned_words']);
		$this->min_word_length = $this->_getMinWordLength();
	}

	/**
	 * Fulltext::_getMinWordLength()
	 *
	 * What is the minimum word length full text supports?
	 */
	protected function _getMinWordLength()
	{
		return 3;
	}

	/**
	 * If the settings don't exist we can't continue.
	 */
	public function isValid()
	{
		// Always fall back to the standard search method.
		return in_array(DB_TYPE, $this->supported_databases);
	}

	/**
	 * Adds the excluded words list
	 *
	 * @param string[] $words An array of words to exclude
	 */
	public function setExcludedWords($words)
	{
		$this->_excludedWords = $words;
	}

	/**
	 * Number of results?
	 */
	public function getNumResults()
	{
		return $this->_num_results;
	}

	/**
	 * Callback function for usort used to sort the fulltext results.
	 *
	 * - In the standard search ordering is not needed, so only 0 is returned.
	 *
	 * @param string $a Word A
	 * @param string $b Word B
	 *
	 * @return int An integer indicating how the words should be sorted (-1, 0 1)
	 */
	public function searchSort($a, $b)
	{
		return 0;
	}

	/**
	 * Do we have to do some work with the words we are searching for to prepare them?
	 *
	 * @param string $word A word to index
	 * @param mixed[] $wordsSearch The Search words
	 * @param string[] $wordsExclude Words to exclude
	 * @param boolean $isExcluded
	 */
	public function prepareIndexes($word, &$wordsSearch, &$wordsExclude, $isExcluded)
	{
	}

	/**
	 * If the current API can use a word index.
	 *
	 * @return bool
	 */
	abstract public function useWordIndex();

	/**
	 * Search for indexed words.
	 *
	 * @param mixed[] $words An array of words
	 * @param mixed[] $search_data An array of search data
	 *
	 * @return resource
	 */
	abstract public function indexedWordQuery($words, $search_data);

	/**
	 * Escape words passed by the client
	 *
	 * @param string $phrase - The string to escape
	 * @param bool $no_regexp - If true or $modSettings['search_match_words']
	 *              is empty, uses % at the beginning and end of the string,
	 *              otherwise returns a regular expression
	 *
	 * @return string
	 */
	public function prepareWord($phrase, $no_regexp)
	{
		global $modSettings;

		return empty($modSettings['search_match_words']) || $no_regexp ? '%' . strtr($phrase, array('_' => '\\_', '%' => '\\%')) . '%' : '[[:<:]]' . addcslashes(preg_replace(array('/([\[\]$.+*?|{}()])/'), array('[$1]'), $phrase), '\\\'') . '[[:>:]]';
	}
}
