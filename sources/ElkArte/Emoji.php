<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte;

use BBC\PreparseCode;
use ElkArte\Cache\Cache;
use ElkArte\Helper\Util;

/**
 * Class Emoji
 *
 * Provides functionality to handle emojis in texts, including:
 * - Replacing emoji shortcodes (e.g., :smile:) with emoji images.
 * - Handling HTML or Unicode encoded emojis.
 * - Protecting and restoring emoji processing within code blocks.
 * - Converting Unicode emoji points to images or identifying corresponding shortcodes.
 */
class Emoji extends AbstractModel
{
	/** @var string regex to find 4-byte HTML as &#x1f937;‍️
	 * This is how 4-byte characters are stored in the utf-8 db. */
	private const POSSIBLE_HTML_EMOJI = '~(&#x[a-fA-F\d]{5,6};|&#\d{5,6};)~';

	/** @var string regex to check if any none letter characters appear in the string */
	private const POSSIBLE_EMOJI = '~([^\p{L}\x00-\x7F]+)~u';

	/** @var string used to find :emoji: style codes */
	private const EMOJI_NAME = '~(?:\s?|^|]|<br />|<br>)(:([-+\w]+):\s?)~u';

	/** @var Emoji holds the instance of this class */
	private static $instance;

	/** @var string holds the url of where the emojis are stored */
	public $smileys_url;

	/** @var string[] Array of keys with known emoji names */
	public $shortcode_replace = [];

	/** @var string Supported emoji -> image regex 8.1+ only */
	public $emoji_regex = '~\x{1F1E6}[\x{1F1E6}-\x{1F1FF}]|(?:\p{Extended_Pictographic}(?:\x{FE0F})?(?:[\x{1F3FB}-\x{1F3FF}])?)(?:\x{200D}(?:\p{Extended_Pictographic}(?:\x{FE0F})?(?:[\x{1F3FB}-\x{1F3FF}])?))*~u';

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
	 * @param bool $uni false returns an emoji image tag, true returns the unicode point, useful for mail
	 * @param bool $protect if false will bypass codeblock protection (useful if already done!)
	 * @return string
	 */
	public function emojiNameToImage($string, $uni = false, $protect = true): string
	{
		$emoji = self::instance();

		// Make sure we do not process emoji in code or icode tags
		$string = $protect ? $this->_protectCodeBlocks($string) : $string;

		// :emoji: must be at the start of a line, or have a leading space or be after a bbc ']' tag
		if ($uni)
		{
			$string = preg_replace_callback(self::EMOJI_NAME, static fn(array $m): string => $emoji->emojiToUni($m), $string);
		}
		else
		{
			$string = preg_replace_callback(self::EMOJI_NAME, static fn(array $m): string => $emoji->emojiToImage($m), $string);

			// Check for any embedded HTML / hex emoji
			$string = $this->keyboardEmojiToImage($string);
		}

		return $protect ? $this->_restoreCodeBlocks($string) : $string;
	}

	/**
	 * Replace [code] and [icode] blocks with tokens.  Both may exist on a page, as such you
	 * can't search for one and process and then the next. i.e., [code]bla[/code] xx [icode]bla[/icode]
	 * would process what's outside code tags, which is an icode!
	 *
	 * @param string $string
	 * @return string
	 */
	private function _protectCodeBlocks($string): string
	{
		// Quick sniff, was that you? I thought so!
		if (!str_contains($string, ':')
			&& !preg_match(self::POSSIBLE_EMOJI, $string))
		{
			return $string;
		}

		// Protect code and icode blocks
		return PreparseCode::instance('')->tokenizeCodeBlocks($string);
	}

	/**
	 * Replace any code tokens with the saved blocks
	 *
	 * @param string $string
	 * @return string
	 */
	private function _restoreCodeBlocks($string): string
	{
		return PreparseCode::instance('')->restoreCodeBlocks($string);
	}

	/**
	 * Find emoji codes that are HTML &#xxx codes or pure 😀 codes. If found,
	 * replace them with our SVG version.
	 *
	 * Given &#128512; or 😀, aka grinning face, will convert to 1f600
	 * and search for available svg image, retuning <img /> or original
	 * string if not found.
	 *
	 * @param string $string
	 * @return string
	 */
	public function keyboardEmojiToImage($string): string
	{
		$string = $this->emojiFromHTML($string);

		return $this->emojiFromUni($string);
	}

	/**
	 * Search and replace on &#xHEX; &#DEC; style emoji
	 *
	 * Given &#128512;; aka 😀 grinning face, will search on 1f600 and
	 * if found, return as <img /> string pointing to SVG
	 *
	 * @param string $string
	 * @return string
	 */
	public function emojiFromHTML($string): string
	{
		// If there are 4-byte encoded values &#x1f123, change those back to utf8 characters
		return preg_replace_callback(self::POSSIBLE_HTML_EMOJI, static function ($match) {
			$replace = html_entity_decode($match[0], ENT_NOQUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8');

			// The Fitzpatrick Scale modifiers are not (well) supported across all graphics sets.  For now
			// drop it, allowing it to display the generic/cartoon color.  IF not, things would render as
			// individual images such as 🤷 🏻 ♂️ instead of just 🤷🏽‍
			$replace = preg_replace('~[\x{1F3FB}-\x{1F3FF}]~u', '', $replace);

			return $replace ?? $match[0];
		}, $string);
	}

	/**
	 * Search the Emoji array by Unicode number
	 *
	 * Given Unicode 1f600, aka 😀 grinning face, returns grinning
	 * Given Unicode 1f6e9 or 1f6e9-fe0f, aka 🛩️ small airplane, returns small_airplane
	 *
	 * @param $hex
	 * @return string|false
	 */
	public function findEmojiByCode($hex)
	{
		if (empty($hex))
		{
			return false;
		}

		$hex = strtolower($hex);
		$this->setSearchReplaceRegex();

		// Is it one we have in our library?
		if ($key = (array_search($hex, $this->shortcode_replace, true)))
		{
			return $key;
		}

		// If it does not end in -fe0f / Variation Selector-16, then give that a try.
		if (!str_ends_with($hex, '-fe0f'))
		{
			if ($key = (array_search($hex . '-fe0f', $this->shortcode_replace, true)))
			{
				return $key;
			}

			return false;
		}

		// Try it w/o any trailing -fe0f and see if we get a match
		if (!($key = (array_search(substr($hex, 0, -5), $this->shortcode_replace, true))))
		{
			return false;
		}

		return $key;
	}

	/**
	 * Takes a shortcode array and, if available, converts it to an <img> emoji
	 *
	 * - Uses an input array of the form m[2] = 'doughnut' m[1]= ':doughnut:' m[0]= original
	 * - If shortcode does not exist in the emoji returns m[0] the full match
	 *
	 * @param array $m results from preg_replace_callback or another array
	 * @return string
	 */
	public function emojiToImage($m): string
	{
		// No :tag: found or not a complete result, return
		if (empty($m[2]))
		{
			return $m[0];
		}

		// Finally, going to need these
		$this->setSearchReplaceRegex();

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
	 * Searches a string for Unicode points and replaces them with emoji <img> tags
	 *
	 * We use [^\p{L}\x00-\x7F]+ which will match any non-letter character including
	 * symbols, currency signs, dingbats, box-drawing characters, etc. This is an
	 * easier regex but with more "false" hits for what we want.  If this passes, then the
	 * full emoji regex will be used to precisely find supported codepoints
	 *
	 * @param $string
	 * @return string
	 */
	public function emojiFromUni($string): string
	{
		$this->setSearchReplaceRegex();

		// Avoid the large regex if there is no emoji DNA
		if (preg_match(self::POSSIBLE_EMOJI, $string) !== 1)
		{
			return $string;
		}

		$result = preg_replace_callback($this->emoji_regex, function ($match) {
			$hex_str = $this->unicodeCharacterToNumber($match[0]);
			$found = $this->findEmojiByCode($hex_str);

			// Hey, I know you, your :space_invader:
			if ($found !== false)
			{
				return $this->emojiToImage([$match[0], ':' . $found . ':', $found]);
			}

			return $match[0];
		}, $string);

		return empty($result) ? $string : $result;
	}

	/**
	 * Takes a shortcode array and, if available, converts it to an HTML Unicode points emoji
	 *
	 * - Uses an input array of the form m[2] = 'doughnut' m[1]= ':doughnut:' m[0]= original
	 * - If shortcode does not exist in the emoji returns m[0] the full preg match
	 *
	 * - Given Unicode 1f62e-200d-1f4a8 returns &#x1f62e;&#x200d;&#x1f4a8;
	 *
	 * @param array $m results from preg_replace_callback or another array
	 * @return string
	 */
	public function emojiToUni($m): string
	{
		// No :tag: found or not a complete result, return
		if (!is_array($m) || empty($m[2]))
		{
			return $m[0];
		}

		// Need our known codes
		$this->setSearchReplaceRegex();

		// It is not a known :tag:, just return what was passed
		if (!isset($this->shortcode_replace[$m[2]]))
		{
			return $m[0];
		}

		// Otherwise, we have some Emoji :dancer:
		$uniCode = $this->shortcode_replace[$m[2]];
		$uniCode = str_replace('-', ';&#x', $uniCode);

		return '&#x' . $uniCode . ';';
	}

	/**
	 * Given a Unicode character, convert to a Unicode number which can be
	 * used for emoji array searching
	 *
	 * Given 😀 aka grinning face returns Unicode 1f600
	 * Given 😮‍💨 aka face exhaling returns Unicode 1f62e-200d-1f4a8
	 *
	 * @param string $code
	 * @return string
	 */
	public function unicodeCharacterToNumber($code): string
	{
		$points = [];

		// Strip skin tones as none of the libraries support them
		$code = preg_replace('/[\x{1F3FB}\x{1F3FC}\x{1F3FD}\x{1F3FE}\x{1F3FF}]/u', '', $code);

		for ($i = 0; $i < Util::strlen($code); $i++)
		{
			$points[] = str_pad(strtolower(dechex(Util::getUnicodeOrdinal(Util::substr($code, $i, 1)))), 4, '0', STR_PAD_LEFT);
		}

		return implode('-', $points);
	}

	/**
	 * Reads the base emoji tags file and load them to a PHP array.
	 *
	 * Creates a regex to search text for known emoji sequences.  Uses generic search for
	 * singleton emoji such as 1f600 as all multipoint ones would have already been found
	 * and processed
	 */
	public function setSearchReplaceRegex(): void
	{
		global $settings;

		$this->_checkCache();
		if (empty($this->shortcode_replace))
		{
			$this->shortcode_replace = [];
			$emoji = file_get_contents($settings['default_theme_dir'] . '/scripts/emoji_tags.js');
			preg_match_all('~{name:\s[\'"](.*?)[\'"], key:\s[\'"](.*?)[\'"](?:, type:\s[\'"](.*?)[\'"])?}~', $emoji, $matches, PREG_SET_ORDER);
			foreach ($matches as $match)
			{
				if (isset($match[3]))
				{
					continue;
				}

				$name = strtolower(trim($match[1]));
				$key = strtolower(trim($match[2]));
				$this->shortcode_replace[$name] = $key;
			}

			call_integration_hook('integrate_custom_emoji', [&$this->shortcode_replace]);

			// Stash for two hours, not like this is going to change
			Cache::instance()->put('shortcode_replace', $this->shortcode_replace, 7200);
		}
	}

	/**
	 * Check the cache to see if we already have the regex created/loaded
	 *
	 * @return void
	 */
	private function _checkCache(): void
	{
		if (empty($this->shortcode_replace))
		{
			Cache::instance()->getVar($this->shortcode_replace, 'shortcode_replace', 3600);
		}
	}

	/**
	 * Retrieve the sole instance of this class.
	 *
	 * @return Emoji|null
	 */
	public static function instance(): ?self
	{
		if (self::$instance === null)
		{
			self::$instance = new Emoji();
		}

		return self::$instance;
	}
}
