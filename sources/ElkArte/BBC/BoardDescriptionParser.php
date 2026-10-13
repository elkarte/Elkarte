<?php

/**
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace BBC;

use ElkArte\Cache\Cache;

/**
 * Class BoardDescriptionParser
 *
 * Board description parser with a two-level cache, a per-request
 * in-memory copy, and a persistent copy in the Cache.
 *
 * The cache key includes everything the output depends on, the description text,
 * the viewer's smiley settings, and the disabled bbc tags, so one viewer's markup is
 * never served to another and edits or setting changes naturally produce new keys.
 */
final class BoardDescriptionParser
{
	/** @var int Persistent cache time to live in seconds */
	public const CACHE_TTL = 86400;

	/** @var array Parsed board descriptions for this request */
	protected static $memo = [];

	/** @var BoardDescriptionParser */
	protected static $instance;

	/** @var ParserWrapper */
	protected $parser;

	/**
	 * @param ParserWrapper|null $parser The parser being decorated
	 */
	public function __construct(?ParserWrapper $parser = null)
	{
		$this->parser = $parser ?? ParserWrapper::instance();
	}

	/**
	 * Find and return the shared instance, or create it
	 *
	 * @return BoardDescriptionParser
	 */
	public static function instance(): BoardDescriptionParser
	{
		if (self::$instance === null)
		{
			self::$instance = new BoardDescriptionParser();
		}

		return self::$instance;
	}

	/**
	 * Parse a board description, using cached output when available
	 *
	 * @param string|null $description Raw board description
	 *
	 * @return string
	 */
	public function parse($description): string
	{
		if (empty($description))
		{
			return '';
		}

		$hash = $this->getKey($description);
		if (isset(self::$memo[$hash]))
		{
			return self::$memo[$hash];
		}

		$cache_key = 'board_desc_' . $hash;
		$cached = Cache::instance()->get($cache_key);
		if ($cached !== null)
		{
			return self::$memo[$hash] = $cached;
		}

		$parsed = $this->parser->parseBoard($description);
		self::$memo[$hash] = $parsed;
		Cache::instance()->put($cache_key, $parsed, self::CACHE_TTL);

		return $parsed;
	}

	/**
	 * Build the cache hash from the description and the settings that affect the output
	 *
	 * @param string $description
	 *
	 * @return string
	 */
	protected function getKey($description): string
	{
		return md5(serialize([
			$description,
			$GLOBALS['context']['smiley_set'] ?? '',
			!empty($GLOBALS['context']['smiley_enabled']),
			!empty($GLOBALS['options']['show_no_smileys']),
			$GLOBALS['modSettings']['disabledBBC'] ?? '',
		]));
	}

	/**
	 * Clear the in-memory copies, mainly useful for tests
	 */
	public static function reset(): void
	{
		self::$memo = [];
	}
}
