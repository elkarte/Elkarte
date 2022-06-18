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
	private static $instance;

	/** @var string holds the url of where the emojis are stored */
	public $smileys_url;

	/** @var string[] Array of keys with known emoji names */
	public $shortcode_replace = [];

	/** @var array Holds code blocks so we do not render smileys inside */
	protected $save_code_blocks = [];

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
		$emoji = self::instance();

		// Make sure we do not process emoji in code or icode tags
		$string = $this->_protectCodeBlocks($string);

		// :emoji: must be at the start of a line, or have a leading space or be after a bbc ']' tag
		$string = preg_replace_callback('~(?:\s?|^|]|<br />|<br>)(:([-+\w]+):\s?)~i', [$emoji, 'emojiToImage'], $string);

		// Check for any embedded html / hex emoji
		$string = $this->keyboardEmojiToImage($string);

		return $this->_restoreCodeBlocks($string);
	}

	/**
	 * Replace [code] and [icode] blocks with tokens.  Both may exist on a page, as such you
	 * can't search for one and process and then the next. i.e. [code]bla[/code] xx [icode]bla[/icode]
	 * would process whats outside of code tags, which is an icode !
	 *
	 * @param string $string
	 * @return string
	 */
	private function _protectCodeBlocks($string)
	{
		// Quick sniff, was that you? I thought so !
		if (strpos($string, ':') === false)
		{
			return $string;
		}

		// Protect code and icode blocks
		$tokenizer = new TokenHash();
		$patterns = ['~(\[\/code\]|\[code(?:=[^\]]+)?\])~i', '~(\[\/icode\]|\[icode(?:=[^\]]+)?\])~i'];
		foreach ($patterns as $pattern)
		{
			$parts = preg_split($pattern, $string, -1, PREG_SPLIT_DELIM_CAPTURE);
			foreach ($parts as $i => $part)
			{
				if ($i % 4 === 0 && isset($parts[$i + 3]))
				{
					// Create a unique key to put in place of the code block
					$key = $tokenizer->generate_hash(8);

					// Save what is there [code]stuff[/code]
					$this->save_code_blocks['%%' . $key . '%%'] = $parts[$i + 1] . $parts[$i + 2] . $parts[$i + 3];

					// Replace the code block with %%$key%%
					$parts[$i + 1] = '%%';
					$parts[$i + 2] = $key;
					$parts[$i + 3] = '%%';
				}
			}

			$string = implode('', $parts);
		}

		return $string;
	}

	/**
	 * Replace any code tokens with the saved blocks
	 *
	 * @return string
	 */
	private function _restoreCodeBlocks($string)
	{
		if (!empty($this->save_code_blocks))
		{
			$string = str_replace(array_keys($this->save_code_blocks), array_values($this->save_code_blocks), $string);
		}

		return $string;
	}

	/**
	 * Find emoji codes that are HTML &#xxx codes or pure 😀 codes. If found
	 * replace them with our SVG version.
	 *
	 * Given &#128512; or 😀, aka grinning face, will convert to 1f600
	 * and search for available svg image, retuning <img /> or original
	 * string if not found.
	 *
	 * @param string $string
	 * @return string
	 */
	public function keyboardEmojiToImage($string)
	{
		$string = $this->emojiFromHTML($string);

		return $this->emojiFromUni($string);
	}

	/**
	 * Search and replace on &#xHEX; &#DEC; style emoji
	 *
	 * Given &#128512;; aka 😀 grinning face, will search on 1f600 and
	 * if found return as <img /> string pointing to SVG
	 *
	 * @param string $string
	 * @return string
	 */
	public function emojiFromHTML($string)
	{
		$result = preg_replace_callback('~&#(\d+);|&#x([0-9a-fA-F]+);~', function ($match) {
			// See if we have an Emoji version of this HTML entity
			$entity = !empty($match[1]) ? dechex($match[1]) : $match[2];
			$found = $this->findEmojiByCode($entity);

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
	 * Search the Emoji array by unicode number
	 *
	 * Given unicode 1f600, aka 😀 grinning face, returns grinning
	 *
	 * @param $hex
	 * @return string|false
	 */
	public function findEmojiByCode($hex)
	{
		$this->loadEmoji();

		// Is it one we have in our library?
		if (!empty($hex) && $key = (array_search($hex, $this->shortcode_replace, true)))
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
		$alt = trim(strtr($m[1], [':' => '&#58;', '(' => '&#40;', ')' => '&#41;', '$' => '&#36;', '[' => '&#091;']));
		$title = ucwords(strtr(htmlspecialchars($m[2]), [':' => '&#58;', '(' => '&#40;', ')' => '&#41;', '$' => '&#36;', '[' => '&#091;', '_' => ' ']));

		return '<img class="smiley emoji ' . $this->_modSettings['emoji_selection'] . '" src="' . $filename . '" alt="' . $alt . '" title="' . $title . '" data-emoji-name="' . $alt . '" data-emoji-code="' . $this->shortcode_replace[$m[2]] . '" />';
	}

	/**
	 * Searches a string for unicode points and replaces them with emoji <img> tags
	 *
	 * Instead of searching in specific groups of emoji code points, such as:
	 *  - flags -> (?:\x{1F3F4}[\x{E0060}-\x{E00FF}]{1,6})|[\x{1F1E0}-\x{1F1FF}]{2}
	 *  - dingbats -> [\x{2700}-\x{27bf}]\x{FE0F}
	 *  - emoticons -> [\x{1F000}-\x{1F6FF}\x{1F900}-\x{1F9FF}]\x{FE0F}?
	 *  - symbols -> [\x{2600}-\x{26ff}]\x{FE0F}?
	 *  - peeps -> (?:[\x{1F466}-\x{1F469}]+\x{FE0F}?[\x{1F3FB}-\x{1F3FF}]?)
	 *
	 * We instead use \p{S} which will match anything in the symbol area including
	 * symbols, currency signs, dingbats, box-drawing characters, etc.  This is an
	 * easier regex but with more "false" hits for what we want.  Array searching
	 * should be faster than multiple detailed regex.
	 *
	 * @param $string
	 * @return string
	 */
	public function emojiFromUni($string)
	{
		$result = preg_replace_callback('~\p{S}~u', function ($match) {
			$hex_str = $this->unicodeCharacterToNumber($match[0]);
			$found = $this->findEmojiByCode($hex_str);

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
	 * Given a unicode character, convert to a Unicode number which can be
	 * used for emoji array searching
	 *
	 * Given 😀 aka grinning face returns unicode 1f600
	 * Given 😮‍💨 aka face exhaling returns unicode 1f62e-200d-1f4a8
	 *
	 * @param string $code
	 * @return string
	 */
	public function unicodeCharacterToNumber($code)
	{
		$points = [];

		for ($i = 0; $i < Util::strlen($code); $i++)
		{
			$points[] = str_pad(strtolower(dechex($this->uniord(Util::substr($code, $i, 1)))), 4, '0', STR_PAD_LEFT);
		}

		return implode('-', $points);
	}

	/**
	 * Converts a 4byte char into the corresponding HTML entity code.
	 * Subset of function _uniord($c) found in query.php as we are only
	 * dealing with the emoji space
	 *
	 * @param $c
	 * @return false|int
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

			// Stash for an hour, not like this is going to change
			Cache::instance()->put('shortcode_replace', $this->shortcode_replace, 3600);
			call_integration_hook('integrate_custom_emoji', [&$this->shortcode_replace]);
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
