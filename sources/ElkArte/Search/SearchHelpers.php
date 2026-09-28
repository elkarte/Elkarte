<?php

/**
 * Helper class for search query processing and normalization.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Search;

use ElkArte\Helper\Util;

/**
 * SearchHelpers provides centralized methods for query sanitation,
 * natural query parsing for complex search engines (such as Manticore),
 * stopword/blocklist filtering, and query normalization.
 *
 * @todo Language-specific filtering.
 * For now use integrate_search_blocklist_words and integrate_search_filler_phrases hooks to customize for specific languages.
 */
class SearchHelpers
{
	/** @var array Default blocklisted/noise words */
	protected static $defaultBlocklist = ['img', 'url', 'quote', 'www', 'http', 'the', 'is', 'it', 'are', 'if', 'in'];

	/** @var array Default conversational entry / filler phrases structure */
	protected static $defaultFillerPhrases = [
		'i am looking for' => ['', 'posts on', 'topics on', 'threads on'],
		'im looking for' => ['', 'posts on', 'topics on', 'threads on'],
		"i'm looking for" => ['', 'posts on', 'topics on', 'threads on'],
		'i want to find',
		'i would like to find',
		'i need to find',
		'looking for',
		'look for',
		'find' => [
			'posts' => ['', 'about', 'with', 'that', 'containing', 'where', 'for', 'on'],
			'topics' => ['', 'about', 'with', 'that', 'containing', 'where', 'for', 'on'],
			'threads' => ['', 'about', 'with', 'that', 'containing', 'where', 'for', 'on'],
			'messages' => ['', 'about', 'with', 'that', 'containing', 'where', 'for', 'on'],
		],
		'search for' => [
			'posts' => ['', 'about', 'with', 'that', 'containing', 'where'],
			'topics' => ['', 'about', 'with', 'that', 'containing', 'where'],
			'threads' => ['', 'about', 'with', 'that', 'containing', 'where'],
			'messages' => ['', 'about', 'with', 'that', 'containing', 'where'],
			'',
		],
		'search' => [
			'posts' => ['for', 'about', 'with'],
			'topics' => ['for', 'about', 'with'],
			'threads' => ['for', 'about', 'with'],
		],
		'show me' => [
			'posts' => ['', 'about', 'with', 'that', 'where'],
			'topics' => ['', 'about', 'with', 'that', 'where'],
			'threads' => ['', 'about', 'with', 'that', 'where'],
			'all posts about',
			'all topics about',
			'all',
			'',
		],
		'how do i find',
		'how to find',
		'where can i find',
		'can you find',
		'can someone find',
		'can i find',
		'help me find',
		'where is',
		'where are',
		'what is',
		'what are',
		'tell me about',
	];

	/**
	 * Converts a natural language search query into a complex/extended boolean search query
	 * compatible with search engines like Manticore and Sphinx.
	 *
	 * Handles:
	 * - HTML entity decoding
	 * - Multi-byte lowercasing
	 * - Number & identifier normalization (e.g., 123-45-6789 => 123_45_6789, 3.14 => 3_14, node.js => node_js)
	 * - Smart / curly quotes and dashes normalization
	 * - Explicit quoted phrase preservation
	 * - Conversational filler phrase filtering ('i am looking for', 'find posts about', etc.)
	 * - Grouping with parentheses
	 * - Negation operators ('not word', '-word', 'not "phrase"', '-"phrase"') -> '!word', '!"phrase"'
	 * - Boolean operators ('OR', 'or' -> '|', 'AND', 'and' -> '&')
	 * - Standalone stopword / blocklist filtering outside quoted phrases
	 * - Operator sanitation and parentheses balancing
	 *
	 * @param string $naturalQuery The input query from the user
	 * @param array $stopwords Optional array of stopwords / blocklisted words to filter
	 * @param array|null $fillerPhrases Optional array of filler phrases to remove (null for default list)
	 * @param int $minFillerLength Minimum query length to trigger filler phrase filtering (default 12)
	 *
	 * @return string The processed search query
	 */
	public static function textToSearchQuery(string $naturalQuery, array $stopwords = [], ?array $fillerPhrases = null,	int $minFillerLength = 12): string
	{
		// Decode entities and check for empty string
		$query = html_entity_decode($naturalQuery, ENT_QUOTES, 'UTF-8');
		$query = trim($query);
		if ($query === '')
		{
			return '';
		}

		// Normalize smart quotes, curly quotes, dashes
		$query = strtr($query, [
			'“' => '"',
			'”' => '"',
			'„' => '"',
			'‟' => '"',
			'«' => '"',
			'»' => '"',
			'‘' => "'",
			'’' => "'",
			'‚' => "'",
			'‛' => "'",
			'—' => '-',
			'–' => '-',
			'−' => '-',
		]);

		// Lowercase the query for consistent processing
		$query = Util::strtolower($query);

		// Normalize numbers and dotted/hyphenated identifiers
		$query = self::normalizeNumbers($query);

		// Ensure balanced double quotes for phrases
		$query = self::balanceQuotes($query);

		// Extract phrases (both normal and negated phrases)
		$phrases = [];
		$query = preg_replace_callback('/(-|not\s+|!\s*)?"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"/iu', static function ($matches) use (&$phrases) {
			$prefix = !empty($matches[1]) ? '!' : '';
			$phraseContent = trim($matches[2]);
			if ($phraseContent === '')
			{
				return ' ';
			}

			// Clean noise inside phrase but keep letters, numbers, spaces, underscores, and hyphens
			$cleanPhrase = preg_replace('/[^\p{L}\p{N}_\s.+&!@-]/u', '', $phraseContent);
			$cleanPhrase = trim(preg_replace('/\s+/u', ' ', $cleanPhrase));
			if ($cleanPhrase === '')
			{
				return ' ';
			}

			$index = count($phrases);
			$phrases[$index] = $prefix . '"' . $cleanPhrase . '"';

			return " ___PHRASE_{$index}___ ";
		}, $query);

		// Filter out conversational filler phrases outside quoted phrases
		$query = self::filterFillerPhrases($query, $fillerPhrases, $minFillerLength);

		// Handle negative keywords and groupings (e.g., "not windows", "-windows", "not (a or b)")
		$query = preg_replace('/\bnot\s*\(/iu', '!(', $query);
		$query = preg_replace('/(?:\s+|^)-\s*\(/u', ' !(', $query);
		$query = preg_replace('/\bnot\s+([^\s|&()]+)/iu', '!$1', $query);
		$query = preg_replace('/(?:\s+|^)-\s*([^\s|&()]+)/u', ' !$1', $query);

		// Handle explicit logical operators
		$query = preg_replace('/\bor\b/iu', '|', $query);
		$query = preg_replace('/\band\b/iu', '&', $query);

		// Clean up punctuation outside phrases while preserving operators
		$query = preg_replace('/[^\p{L}\p{N}\s|&!()_]/u', ' ', $query);

		// Filter out stopwords outside phrases if provided
		if (!empty($stopwords))
		{
			$stopwordsMap = array_fill_keys(array_map([Util::class, 'strtolower'], $stopwords), true);
			$tokens = preg_split('/\s+/u', trim($query), -1, PREG_SPLIT_NO_EMPTY);
			$filtered = [];

			foreach ($tokens as $token)
			{
				$checkWord = ltrim($token, '!()');
				$checkWord = rtrim($checkWord, '!()');

				if (isset($stopwordsMap[$checkWord]))
				{
					// Preserve any parentheses wrapped around stopword
					$prefix = '';
					$suffix = '';
					while (str_starts_with($token, '('))
					{
						$prefix .= '(';
						$token = substr($token, 1);
					}

					while (str_ends_with($token, ')'))
					{
						$suffix .= ')';
						$token = substr($token, 0, -1);
					}

					if ($prefix !== '' || $suffix !== '')
					{
						$filtered[] = $prefix . $suffix;
					}

					continue;
				}

				$filtered[] = $token;
			}

			$query = implode(' ', $filtered);
		}

		// Inject saved phrases back into the query
		foreach ($phrases as $index => $phrase)
		{
			$query = str_replace("___PHRASE_{$index}___", $phrase, $query);
		}

		// Balance parentheses and clean up empty groups
		$query = self::balanceParentheses($query);

		// Sanitize operators and spacing
		$query = self::sanitizeOperators($query);

		return trim($query);
	}

	/**
	 * Cleans a string of everything but alphanumeric characters and basic search symbols.
	 * Decodes entities, lowercases, and normalizes numbers.
	 *
	 * @param string $string The string to clean
	 *
	 * @return string The cleaned string
	 */
	public static function cleanString(string $string): string
	{
		// Strip HTML tags first
		$string = strip_tags($string);

		// Decode entities first
		$string = html_entity_decode($string, ENT_QUOTES, 'UTF-8');

		// Lowercase string
		$string = Util::strtolower($string);

		// Fix numbers so they search easier (decimals, SSN, dates, versions)
		$string = self::normalizeNumbers($string);

		// Strip everything out that's not alphanumeric, hyphen, underscore, or double quote
		$string = preg_replace('~[^\pL\pN_"-]+~u', ' ', $string);

		return trim(preg_replace('/\s+/u', ' ', $string));
	}

	/**
	 * Normalizes numbers and dotted/hyphenated identifiers so internal delimiters
	 * are replaced with underscores (e.g. 123-45-6789 => 123_45_6789, 3.14 => 3_14, node.js => node_js).
	 *
	 * @param string $string
	 *
	 * @return string
	 */
	public static function normalizeNumbers(string $string): string
	{
		return preg_replace('~(?<=\p{L}|\p{N})[-./]+(?=\p{L}|\p{N})~u', '_', $string);
	}

	/**
	 * Ensures quotation marks are balanced by appending a closing quote if count is odd.
	 *
	 * @param string $query
	 *
	 * @return string
	 */
	public static function balanceQuotes(string $query): string
	{
		$quoteCount = substr_count($query, '"');
		if ($quoteCount % 2 !== 0)
		{
			$query .= '"';
		}

		return $query;
	}

	/**
	 * Balances parentheses in a query string and removes empty groups.
	 *
	 * @param string $query
	 *
	 * @return string
	 */
	public static function balanceParentheses(string $query): string
	{
		$openCount = 0;
		$length = strlen($query);
		$result = '';

		for ($i = 0; $i < $length; $i++)
		{
			$char = $query[$i];
			if ($char === '(')
			{
				$openCount++;
				$result .= '(';
			}
			elseif ($char === ')')
			{
				if ($openCount > 0)
				{
					$openCount--;
					$result .= ')';
				}
				// Skip closing parenthesis if no matching opening parenthesis
			}
			else
			{
				$result .= $char;
			}
		}

		// Close any unclosed opening parentheses
		if ($openCount > 0)
		{
			$result .= str_repeat(')', $openCount);
		}

		// Remove empty parentheses like (), ( ), (!), (|), (&)
		return preg_replace('/\(\s*([|&!]*)\s*\)/u', ' ', $result);
	}

	/**
	 * Sanitizes boolean operators in a query string to prevent syntax errors in search engines.
	 *
	 * @param string $query
	 *
	 * @return string
	 */
	public static function sanitizeOperators(string $query): string
	{
		// Collapse duplicate operators
		$query = preg_replace('/\|+/u', '|', $query);
		$query = preg_replace('/&+/u', '&', $query);
		$query = preg_replace('/!+/u', '!', $query);

		// Remove conflicting adjacent operators
		$query = preg_replace('/\|\s*&|&\s*\|/u', '|', $query);

		// Remove operators next to opening/closing parentheses
		$query = preg_replace('/\(\s*[|&]+/u', '(', $query);
		$query = preg_replace('/[|&]+\s*\)/u', ')', $query);

		// Remove orphaned negation operators
		$query = preg_replace('/!(?=[\s|&)]|$)/u', '', $query);

		// Clean multiple spaces and trim
		$query = preg_replace('/\s+/u', ' ', $query);
		$query = trim($query);

		// Strip leading or trailing | and &
		$query = preg_replace('/^[|&]+\s*/u', '', $query);
		$query = preg_replace('/\s*[|&]+$/u', '', $query);

		// Clean up empty parentheses that might have resulted from stripping operators
		$query = preg_replace('/\(\s*\)/u', '', $query);

		return trim($query);
	}

	/**
	 * Returns the default list of blocklisted/noise words, including any modifications
	 * made via the `integrate_search_blocklist_words` hook.
	 *
	 * @return array
	 */
	public static function getDefaultBlocklistedWords(): array
	{
		$blocklist = static::$defaultBlocklist;
		if (function_exists('call_integration_hook'))
		{
			call_integration_hook('integrate_search_blocklist_words', [&$blocklist]);
		}

		return $blocklist;
	}

	/**
	 * Returns the default list of filler phrases, including any modifications
	 * made via the `integrate_search_filler_phrases` hook.
	 *
	 * @return array
	 */
	public static function getDefaultFillerPhrases(): array
	{
		$fillerPhrases = self::expandFillerPhrases(static::$defaultFillerPhrases);
		if (function_exists('call_integration_hook'))
		{
			call_integration_hook('integrate_search_filler_phrases', [&$fillerPhrases]);
		}

		return self::expandFillerPhrases($fillerPhrases);
	}

	/**
	 * Recursively flattens and expands nested filler phrase definitions into a flat array of strings.
	 *
	 * Supports concise definitions such as:
	 *   'search for' => ['posts' => ['', 'about', 'with'], 'topics' => ['', 'about'], '']
	 *
	 * @param array $phrases
	 * @param string $prefix
	 *
	 * @return array
	 */
	public static function expandFillerPhrases(array $phrases, string $prefix = ''): array
	{
		$expanded = [];

		foreach ($phrases as $key => $value)
		{
			if (is_array($value))
			{
				$currentPrefix = is_string($key) && $key !== '' ? ($prefix === '' ? $key : $prefix . ' ' . $key) : $prefix;
				$expanded = array_merge($expanded, self::expandFillerPhrases($value, $currentPrefix));
			}
			else
			{
				$entry = trim((string) $value);
				$phrase = $prefix === '' ? $entry : ($entry === '' ? $prefix : $prefix . ' ' . $entry);
				$phrase = trim(preg_replace('/\s+/u', ' ', $phrase));
				if ($phrase !== '')
				{
					$expanded[] = $phrase;
				}
			}
		}

		return array_values(array_unique($expanded));
	}

	/**
	 * Filters out common conversational filler phrases and entry queries
	 * (e.g. 'i am looking for', 'find posts about', 'show me', 'where is').
	 *
	 * @param string $query The query string
	 * @param array|null $fillerPhrases Optional custom list of filler phrases (null for default list)
	 * @param int $minLength Minimum query length to trigger filler phrase filtering (default 12)
	 *
	 * @return string The query with filler phrases removed
	 */
	public static function filterFillerPhrases(string $query, ?array $fillerPhrases = null, int $minLength = 12): string
	{
		$trimmed = trim($query);
		if ($trimmed === '' || ($minLength > 0 && Util::strlen($trimmed) < $minLength))
		{
			return $query;
		}

		if ($fillerPhrases === null)
		{
			$fillerPhrases = self::getDefaultFillerPhrases();
		}
		else
		{
			$fillerPhrases = self::expandFillerPhrases($fillerPhrases);
		}

		if (empty($fillerPhrases))
		{
			return $query;
		}

		// Sort phrases by length descending so longer phrases match before substrings
		usort($fillerPhrases, static function ($a, $b) {
			return Util::strlen($b) <=> Util::strlen($a);
		});

		$escapedPhrases = [];
		foreach ($fillerPhrases as $phrase)
		{
			$phrase = trim(Util::strtolower($phrase));
			if ($phrase === '')
			{
				continue;
			}

			$quoted = preg_quote($phrase, '/');

			// Handle apostrophes flexibly (match ASCII or curly apostrophe or optional)
			$quoted = preg_replace("/['’]/u", "['’]?", $quoted);

			// Allow any whitespace sequence between words
			$escapedPhrases[] = preg_replace('/\s+/u', '\s+', $quoted);
		}

		if (empty($escapedPhrases))
		{
			return $query;
		}

		$pattern = '/\b(' . implode('|', $escapedPhrases) . ')\b/iu';
		$filtered = preg_replace($pattern, ' ', $query);
		$filtered = trim(preg_replace('/\s+/u', ' ', $filtered));

		// If filtering removed all substantive content (e.g., query was only a filler phrase),
		// preserve the original query to avoid dropping the search entirely.
		$substantive = preg_replace('/[^\p{L}\p{N}]/u', '', $filtered);
		if ($substantive === '')
		{
			return $query;
		}

		return $filtered;
	}

	/**
	 * Extracts keywords and phrases intended for inclusion (useful for highlighting or term listing).
	 *
	 * @param string $naturalQuery
	 * @param array|null $fillerPhrases Optional filler phrases to filter
	 *
	 * @return array List of included words/phrases
	 */
	public static function extractKeywords(string $naturalQuery, ?array $fillerPhrases = null): array
	{
		$cleaned = self::cleanString($naturalQuery);
		if ($cleaned === '')
		{
			return [];
		}

		$cleaned = self::filterFillerPhrases($cleaned, $fillerPhrases);

		// Extract phrases
		$keywords = [];
		if (preg_match_all('/"([^"]+)"/u', $cleaned, $matches))
		{
			foreach ($matches[1] as $phrase)
			{
				$phrase = trim($phrase);
				if ($phrase !== '')
				{
					$keywords[] = $phrase;
				}
			}
			$cleaned = preg_replace('/"[^"]+"/u', ' ', $cleaned);
		}

		// Extract remaining words
		$words = preg_split('/\s+/u', trim($cleaned), -1, PREG_SPLIT_NO_EMPTY);
		foreach ($words as $word)
		{
			$word = trim($word, "-_' ");
			if ($word !== '' && !in_array($word, $keywords, true))
			{
				$keywords[] = $word;
			}
		}

		return $keywords;
	}
}

/**
 * Procedural function wrapper for textToSearchQuery.
 *
 * @param string $naturalQuery
 * @param array $stopwords
 * @param array|null $fillerPhrases
 * @param int $minFillerLength
 *
 * @return string
 */
function textToSearchQuery(string $naturalQuery, array $stopwords = [], ?array $fillerPhrases = null, int $minFillerLength = 12): string
{
	return SearchHelpers::textToSearchQuery($naturalQuery, $stopwords, $fillerPhrases, $minFillerLength);
}
