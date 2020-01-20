<?php

/**
 * Abstract class that defines the methods search APIs shall implement
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 */

namespace ElkArte\Search\API;

use ElkArte\Search\SearchArray;
use ElkArte\Search\WeightFactors;

/**
 * Abstract class that defines the methods any search API shall implement
 * to properly work with ElkArte
 *
 * @package Search
 */
abstract class AbstractAPI
{
	/**
	 * This is the last version of ElkArte that this was tested on, to protect against API changes.
	 *
	 * @var string
	 */
	public $version_compatible;

	/**
	 * This won't work with versions of ElkArte less than this.
	 *
	 * @var string
	 */
	public $min_elk_version;

	/**
	 * Standard search is supported by default.
	 *
	 * @var bool
	 */
	public $is_supported;

	/**
	 * Any word excluded from the search?
	 *
	 * @var array
	 */
	protected $_excludedWords = array();

	/**
	 * Number of hits
	 *
	 * @var int
	 */
	protected $_num_results = 0;

	/**
	 * What words are banned?
	 *
	 * @var array
	 */
	protected $bannedWords = array();

	/**
	 * What is the minimum word length?
	 *
	 * @var int
	 */
	protected $min_word_length = 3;

	/**
	 * All the search configurations
	 *
	 * @var \ElkArte\ValuesContainer
	 */
	protected $config = null;

	/**
	 * Parameters of the search
	 *
	 * @var null|\ElkArte\Search\SearchParams
	 */
	protected $_searchParams = null;

	/**
	 * The weights to associate to various areas for relevancy
	 *
	 * @var array
	 */
	protected $_weight_factors = array();

	/**
	 * Weighing factor each area, ie frequency, age, sticky, etc
	 *
	 * @var array
	 */
	protected $_weight = array();

	/**
	 * The sum of the _weight_factors, normally but not always 100
	 *
	 * @var int
	 */
	protected $_weight_total = 0;

	/**
	 * If we are creating a tmp db table
	 *
	 * @var bool
	 */
	protected $_createTemporary = true;

	/**
	 * Builds the array of words for use in the db query
	 *
	 * @var array
	 */
	protected $_searchWords = array();

	/**
	 * Phrases not to be found in the search results (-"some phrase")
	 *
	 * @var array
	 */
	protected $_excludedPhrases = array();

	/**
	 * Database instance
	 *
	 * @var \ElkArte\Database\QueryInterface
	 */
	protected $_db;

	/**
	 * Search db instance
	 *
	 * @var \ElkArte\Database\AbstractSearch
	 */
	protected $_db_search;

	/**
	 * Words excluded from indexes
	 *
	 * @var array
	 */
	protected $_excludedIndexWords = array();

	/**
	 * Words not be be found in the subject (-word)
	 *
	 * @var array
	 */
	protected $_excludedSubjectWords = array();

	/**
	 * Holds the words and phrases to be searched on
	 *
	 * @var \ElkArte\Search\SearchArray
	 */
	protected $_searchArray = null;

	/**
	 * What databases do we support? (In general.)
	 *
	 * @var array
	 */
	protected $supported_databases = array('MySQL', 'PostgreSQL');

	/**
	 * __construct()
	 */
	public function __construct($config, $searchParams)
	{
		$this->config = $config;
		$this->_searchParams = $searchParams;

		$this->bannedWords = $config->banned_words;
		$this->min_word_length = $this->_getMinWordLength();

		$this->_db = database();
		$this->_db_search = db_search();
	}

	/**
	 * What is a sensible minimum word length?
	 *
	 * @return int
	 */
	protected function _getMinWordLength()
	{
		return 3;
	}

	/**
	 * If the settings don't exist we can't continue.
	 *
	 * @return bool
	 */
	public function isValid()
	{
		// Always fall back to the standard search method.
		return in_array($this->_db->title(), $this->supported_databases);
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
	 * Adds the excluded phrases list
	 *
	 * @param string[] $phrases An array of phrases to exclude
	 */
	public function setExcludedPhrases($phrases)
	{
		$this->_excludedPhrases = $phrases;
	}

	/**
	 * Sets the SearchArray... heck if I know what it is.
	 *
	 * @param \ElkArte\Search\SearchArray $searchArray
	 */
	public function setSearchArray(SearchArray $searchArray)
	{
		$this->_searchArray = $searchArray;
	}

	/**
	 * If we use a temporary table or not
	 *
	 * @param bool $use
	 */
	public function useTemporary($use = false)
	{
		$this->_createTemporary = $use;
	}

	/**
	 * Adds the weight factors
	 *
	 * @param \ElkArte\Search\WeightFactors $weights
	 */
	public function setWeightFactors(WeightFactors $weights)
	{
		$this->_weight_factors = $weights->getFactors();

		$this->_weight = $weights->getWeight();

		$this->_weight_total = $weights->getTotal();
	}

	/**
	 * Number of results?
	 *
	 * @return int
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
	 * Prepares the indexes
	 *
	 * @param string $word
	 * @param string $wordsSearch
	 * @param string $wordsExclude
	 * @param string $isExcluded
	 * @param string $excludedSubjectWords
	 */
	public function prepareIndexes($word, &$wordsSearch, &$wordsExclude, $isExcluded, $excludedSubjectWords)
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
