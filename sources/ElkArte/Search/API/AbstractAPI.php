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

use ElkArte\HttpReq;
use ElkArte\Search\SearchArray;
use ElkArte\Search\WeightFactors;
use ElkArte\Util;

/**
 * Abstract class that defines the methods any search API shall implement
 * to properly work with ElkArte
 *
 * @package Search
 */
abstract class AbstractAPI
{
	/** @var string This is the last version of ElkArte that this was tested on, to protect against API changes. */
	public $version_compatible;

	/** @var string This won't work with versions of ElkArte less than this. */
	public $min_elk_version;

	/** @var bool Standard search is supported by default. */
	public $is_supported;

	/** @var array Any word excluded from the search? */
	protected $_excludedWords = [];

	/** @var int Number of hits */
	protected $_num_results = 0;

	/** @var array What words are banned? */
	protected $bannedWords = [];

	/** @var int What is the minimum word length? */
	protected $min_word_length;

	/** @var \ElkArte\ValuesContainer All the search configurations */
	protected $config;

	/** @var null|\ElkArte\Search\SearchParams Parameters of the search */
	protected $_searchParams;

	/** @var array The weights to associate to various areas for relevancy */
	protected $_weight_factors = [];

	/** @var array Weighing factor each area, ie frequency, age, sticky, etc */
	protected $_weight = [];

	/** @var int The sum of the _weight_factors, normally but not always 100*/
	protected $_weight_total = 0;

	/** @var bool If we are creating a tmp db table */
	protected $_createTemporary = true;

	/** @var array Builds the array of words for use in the db query */
	protected $_searchWords = [];

	/** @var array Phrases not to be found in the search results (-"some phrase") */
	protected $_excludedPhrases = [];

	/** @var \ElkArte\Database\QueryInterface Database instance */
	protected $_db;

	/** @var \Elkarte\HttpReq HttpReq instance */
	protected $_req;

	/** @var \ElkArte\Database\AbstractSearch Search db instance */
	protected $_db_search;

	/** @var array Words excluded from indexes */
	protected $_excludedIndexWords =[];

	/** @var array Words not to be found in the subject (-word) */
	protected $_excludedSubjectWords = [];

	/** @var \ElkArte\Search\SearchArray Holds the words and phrases to be searched on */
	protected $_searchArray;

	/** @var array What databases do we support? (In general.) */
	protected $supported_databases = ['MySQL', 'PostgreSQL'];

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

		$this->_req = HttpReq::instance();
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
		return in_array($this->_db->title(), $this->supported_databases, true);
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
	 * Callback function for usort used to sort the results.
	 *
	 * - The order of sorting is: large words, small words, large words that
	 * are excluded from the search, small words that are excluded.
	 *
	 * @param string $a Word A
	 * @param string $b Word B
	 * @return int An integer indicating how the words should be sorted (-1, 0 1)
	 */
	public function searchSort($a, $b)
	{
		$x = Util::strlen($a) - (in_array($a, $this->_excludedWords) ? 1000 : 0);
		$y = Util::strlen($b) - (in_array($b, $this->_excludedWords) ? 1000 : 0);

		return $y < $x ? 1 : ($y > $x ? -1 : 0);
	}

	/**
	 * Prepares the indexes
	 *
	 * @param string $word
	 * @param string $wordsSearch
	 * @param string $wordsExclude
	 * @param boolean $isExcluded
	 * @param string $excludedSubjectWords
	 */
	public function prepareIndexes($word, &$wordsSearch, &$wordsExclude, $isExcluded, $excludedSubjectWords)
	{
		// API specific implementations
	}

	/**
	 * Returns if the API uses the extended query syntax (aka sphinx etc)
	 *
	 * @return bool
	 */
	public function supportsExtended()
	{
		return false;
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
	 * @param array $words An array of words
	 * @param array $search_data An array of search data
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
