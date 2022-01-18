<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

use ElkArte\Cache\Cache;

/**
 * Used to add emoji images to text
 *
 * What it does:
 *
 * - Searches text for :tag: strings
 * - If tag is found to be a known emoji, replaces it with an image tag
 */
class Emoji extends AbstractModel
{
	/** @var null|\ElkArte\Emoji holds the instance of this class */
	private static $instance = null;

	/** @var string holds the url of where the emojis are stored */
	public $smileys_url;

	/** @var string[] Array of keys with known emoji names */
	public $shortcode_replace = [];

	/**
	 * Emoji constructor.
	 *
	 * @param string $smileys_url
	 */
	public function __construct($smileys_url = '')
	{
		parent::__construct();

		if (empty($smileys_url))
		{
			$smileys_url = htmlspecialchars($this->_modSettings['smileys_url']) . '/' . $this->_modSettings['emoji_selection'];
		}

		$this->smileys_url = $smileys_url;
	}

	/**
	 * Simple search and replace function
	 *
	 * What it does:
	 * - Finds emoji tags outside of code tags and converts applicable ones to images
	 * - Called from integrate_pre_bbc_parser
	 *
	 * @param string $string
	 * @return string
	 */
	public function emojiNameToImage($string)
	{
		$emoji = Emoji::instance();

		// Only work on the areas outside of code tags
		$parts = preg_split('~(\[/code]|\[code(?:=[^]]+)?])~i', $string, -1, PREG_SPLIT_DELIM_CAPTURE);

		// Only converts :tags: outside.
		for ($i = 0, $n = count($parts); $i < $n; $i++)
		{
			// It goes 0 = outside, 1 = begin tag, 2 = inside, 3 = close tag, repeat.
			if ($i % 4 == 0)
			{
				// :emoji: must be at the start of a line, or have a leading space or be after a bbc ] tag
				$parts[$i] = preg_replace_callback('~(?:\s?|^|]|<br />|<br>)(:([-+\w]+):\s?)~si', [$emoji, 'emojiToImage'], $parts[$i]);

				// Check for embeded html / hex emoji
				$parts[$i] = $this->keyboardEmojiToImage($parts[$i]);
			}
		}

		return implode('', $parts);
	}

	/**
	 * Find emoji codes that were keyboard entered, or HTML &#xxx codes and if found
	 * and replaceable with our SVG standard ones, do it
	 *
	 * @param $string
	 * @return string
	 */
	public function keyboardEmojiToImage($string)
	{
		$string = $this->emojiFromHTML($string);
		$string = $this->emojiFromUni($string);

		return $string;
	}

	/**
	 * Search and replace on &#xHEX; &#DEC; style emoji
	 *
	 * @param $string
	 * @return string|string[]|null
	 */
	public function emojiFromHTML($string)
	{
		$result = preg_replace_callback('~&#([0-9]+);|&#x([0-9a-fA-F]+);~', function ($match) {
			// See if we have an Emoji version of this HTML entity
			$entity = !empty($match[1]) ? dechex($match[1]) : $match[2];
			$found = $this->searchEmojiByHex($entity);

			// Replace it with or emoji <img>
			if ($found !== false)
			{
				return $this->emojiToImage([$match[0], ':' . $found . ':', $found]);
			}

			return $match[0];
		}, $string);

		return empty($result) ? $string : $result;
	}

	/**
	 * Search the Emoji array by hex code
	 *
	 * @param $hex
	 * @return string|false
	 */
	public function searchEmojiByHex($hex)
	{
		$this->loadEmoji();

		// Is it one we have in our library?
		if (!empty($hex) && $key = (array_search($hex, $this->shortcode_replace)))
		{
			return $key;
		}

		return false;
	}

	/**
	 * Takes a shortcode array and, if available, converts it to an <img> emoji
	 *
	 * - Uses input array of the form m[2] = 'doughnut' m[1]= ':doughnut:' m[0]= original
	 * - If shortcode does not exist in the emoji returns m[0] the preg full match
	 *
	 * @param array $m results from preg_replace_callback or other array
	 * @return string
	 */
	public function emojiToImage($m)
	{
		// No :tag: found or not a complete result, return
		if (!is_array($m) || empty($m[2]))
		{
			return $m[0];
		}

		// Finally, going to need these
		$this->loadEmoji();

		// It is not a known tag, just return what was passed
		if (!isset($this->shortcode_replace[$m[2]]))
		{
			return $m[0];
		}

		// Otherwise, we have some Emoji :dancer:
		$filename = $this->smileys_url . '/' . $this->shortcode_replace[$m[2]] . '.svg';
		$alt = strtr($m[1], [':' => '&#58;', '(' => '&#40;', ')' => '&#41;', '$' => '&#36;', '[' => '&#091;']);
		$title = ucwords(strtr(htmlspecialchars($m[2]), [':' => '&#58;', '(' => '&#40;', ')' => '&#41;', '$' => '&#36;', '[' => '&#091;', '_' => ' ']));

		return '<img class="smiley emoji" src="' . $filename . '" alt="' . $alt . '" title="' . $title . '" />';
	}

	/**
	 * Searches a string for unicode points and replaces them with emoji <img> tags
	 *
	 * Instead of searching in specific groups of emoji code points, such as:
	 *
	 * flags -> (?:\x{1F3F4}[\x{E0060}-\x{E00FF}]{1,6})|[\x{1F1E0}-\x{1F1FF}]{2}
	 * dingbats -> [\x{2700}-\x{27bf}]\x{FE0F}
	 * emoticons -> [\x{1F000}-\x{1F6FF}\x{1F900}-\x{1F9FF}]\x{FE0F}?
	 * symbols -> [\x{2600}-\x{26ff}]\x{FE0F}?
	 * peeps -> (?:[\x{1F466}-\x{1F469}]+\x{FE0F}?[\x{1F3FB}-\x{1F3FF}]?)
	 *
	 * We will use \p{S} which will match anything in the symbol area including
	 * symbols, currency signs, dingbats, box-drawing characters, etc.  This is an
	 * easier regex but with more "false" hits for what we want.  The array searching
	 * should be faster than the detailed regex.
	 *
	 * @param $string
	 * @return string|string[]|null
	 */
	public function emojiFromUni($string)
	{
		$result = preg_replace_callback('~\p{S}~u', function ($match) {
			$found = $this->knownEmojiCode($match[0]);

			// Hey I know you, your :space_invader:
			if ($found !== false)
			{
				return $this->emojiToImage([$match[0], ':' . $found . ':', $found]);
			}

			return $match[0];
		}, $string);

		return empty($result) ? $string : $result;
	}

	/**
	 * Given a unicode convert to hex for emoji array searching
	 *
	 * @param string $code
	 * @return string|false
	 */
	public function knownEmojiCode($code)
	{
		$points = [];
		for ($i = 0; $i < Util::strlen($code); $i++)
		{
			$points[] = strtolower(dechex($this->uniord(Util::substr($code, $i, 1))));
		}
		$hex_str = implode('-', $points);

		return $this->searchEmojiByHex($hex_str);
	}

	/**
	 * Converts a 4byte char into the corresponding HTML entity code.
	 * Subset of function _uniord($c) found in query.php as we are only
	 * dealing with the emoji space
	 *
	 * @param $c
	 * @return false|string
	 */
	private function uniord($c)
	{
		$ord0 = ord($c[0]);
		if ($ord0 >= 0 && $ord0 <= 127)
		{
			return $ord0;
		}

		$ord1 = ord($c[1]);
		if ($ord0 >= 192 && $ord0 <= 223)
		{
			return ($ord0 - 192) * 64 + ($ord1 - 128);
		}

		$ord2 = ord($c[2]);
		if ($ord0 >= 224 && $ord0 <= 239)
		{
			return ($ord0 - 224) * 4096 + ($ord1 - 128) * 64 + ($ord2 - 128);
		}

		$ord3 = ord($c[3]);
		if ($ord0 >= 240 && $ord0 <= 247)
		{
			return ($ord0 - 240) * 262144 + ($ord1 - 128) * 4096 + ($ord2 - 128) * 64 + ($ord3 - 128);
		}

		return false;
	}

	/**
	 * Load the base emoji tags file and load to PHP array
	 */
	public function loadEmoji()
	{
		global $settings;

		$this->_checkCache();

		if (empty($this->shortcode_replace))
		{
			$emoji = file_get_contents($settings['default_theme_dir'] . '/scripts/emoji_tags.js');
			preg_match_all('~{name:(.*?), key:(.*?)}~s', $emoji, $matches, PREG_SET_ORDER);
			foreach ($matches as $match)
			{
				$name = trim($match[1], "' ");
				$key = trim($match[2], "' ");
				$this->shortcode_replace[$name] = $key;
			}

			Cache::instance()->put('shortcode_replace', $this->shortcode_replace, 480);
			call_integration_hook('integrate_custom_emoji', array(&$this->shortcode_replace));
		}
	}

	/**
	 * Check the cache to see if we already have these loaded
	 *
	 * @return void
	 */
	private function _checkCache()
	{
		if (!empty($this->shortcode_replace))
		{
			return;
		}

		if (Cache::instance()->getVar($shortcode_replace, 'shortcode_replace', 480))
		{
			$this->shortcode_replace = $shortcode_replace;
			unset($shortcode_replace);
		}
	}

	/**
	 * Retrieve the sole instance of this class.
	 *
	 * @return \ElkArte\Emoji
	 */
	public static function instance()
	{
		if (self::$instance === null)
		{
			self::$instance = new Emoji();
		}

		return self::$instance;
	}
}
