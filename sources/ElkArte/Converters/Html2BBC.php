<?php

/**
 * Converts a string of HTML to BBC
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Converters;

use ElkArte\Util;

/**
 * Converts a string of HTML to BBC
 *
 * Initiate
 *    $bbc_converter = new \ElkArte\Html2BBC($html);
 *    where $html is a string of html we want to convert to bbc
 *
 * Override
 *    $bbc_converter->skip_tags(array())
 *    will prevent the conversion of certain html tags to bbc
 *
 * Convert
 *    $bbc = $bbc_converter->get_bbc();
 *
 */
class Html2BBC extends AbstractDomParser
{
	/** @var string Line break character */
	public $line_break = "\n\n";

	/** @var string[] Font numbers to pt size */
	public $sizes_equivalence = [1 => '8px', '10px', '12px', '14px', '18px', '20px', '22px'];

	/** @var bool|null Used to strip newlines inside 'p' and 'div' elements */
	public $strip_newlines;

	/** @var string[] Html tags to skip that would normally be converted */
	protected $_skip_tags = [];

	/** @var string[] Style attributes to skip that would normally be converted */
	protected $_skip_style = [];

	/**
	 * Gets everything started using the built-in or external parser
	 *
	 * @param string $html string of html to convert
	 * @param bool $strip flag to strip newlines, true by default
	 */
	public function __construct($html, $strip = true)
	{
		// Up front, remove whitespace between html tags
		$html = preg_replace('/(?:(?<=>)|(?<=\/>))(\s+)(?=<\/?)/', '', $html);

		// Replace invisible (except \n \t) characters with a space
		$html = preg_replace('~[^\S\n\t]~u', ' ', $html);

		// This seems the best way to deal with this construct and elkarte code tags
		$html = preg_replace('~<pre>\s?(<code>.*?</code>)\s?</pre>~us', '$1', $html);
		$html = preg_replace('~<pre class="bbc_code.*?>(.*?)</pre>~us', '<code>$1</code>', $html);

		// Escape items that are BBC tags
		$html = strtr($html, ['[' => '&amp#91;', ']' => '&amp#93;']);

		// Set a Parser then load the HTML
		$this->strip_newlines = $strip;
		$this->setParser();
		$this->loadHTML($html);
	}

	/**
	 * If we want to skip over some tags (that would normally be converted)
	 *
	 * @param string|string[] $tags
	 */
	public function skip_tags($tags)
	{
		if (!is_array($tags))
		{
			$tags = array($tags);
		}

		if (!empty($tags))
		{
			$this->_skip_tags = $tags;
		}
	}

	/**
	 * If we want to skip over inline style tags (that would normally be converted)
	 *
	 * @param string|string[] $styles
	 */
	public function skip_styles($styles)
	{
		if (!is_array($styles))
		{
			$styles = array($styles);
		}

		if (!empty($styles))
		{
			$this->_skip_style = $styles;
		}
	}

	/**
	 * Loads the html body and sends it to the parsing loop to convert all
	 * DOM nodes to BBC
	 */
	public function get_bbc()
	{
		// Convert all the nodes that we know how to
		$this->convertChildNodes($this->getDOMBodyNode());

		// Done replacing HTML elements, now get the converted DOM tree back into a string
		$bbc = $this->getHTML();
		$bbc = $this->_recursive_decode($bbc);
		$bbc = $this->getBodyText($bbc);

		return $this->cleanBBC($bbc);
	}

	/**
	 * Traverse each node to its base, then convert tags to bbc on the way back out
	 *
	 * @param \DOMNode|object $node
	 */
	public function convertChildNodes($node)
	{
		if (self::hasParentCode($node, $this->internalParser) && $this->getName($node) !== 'code')
		{
			return;
		}

		// Keep traversing till we are at the tip of this node
		if ($node->hasChildNodes())
		{
			$num = $this->getLength($this->getChildren($node));
			for ($i = 0; $i < $num; $i++)
			{
				$child = $this->getChild($node, $i);
				$this->convertChildNodes($child);
			}
		}

		// At the tip of this node, convert it to bbc
		$this->convertToBBC($node);
	}

	/**
	 * Convert the supplied node into its bbc equivalent
	 *
	 * @param \DOMNode|object $node
	 */
	public function convertToBBC($node)
	{
		// HTML tag names
		$tag = $this->getName($node);

		// Skipping over this tag?
		if (in_array($tag, $this->_skip_tags))
		{
			// Grab any inner content, dropping the tag
			$bbc = $this->getInnerHTML($node);
			$this->setTextNode($node, $bbc);

			return;
		}

		// Based on the current tag, determine how to convert
		switch ($tag)
		{
			case 'a':
				$bbc = $this->_convertAnchor($node);
				break;
			case 'abbr':
				$bbc = $this->_convertAbbr($node);
				break;
			case 'b':
			case 'strong':
				$bbc = '[b]' . $this->getValue($node) . '[/b]';
				break;
			case 'bdo':
				$bbc = $this->_convertBdo($node);
				break;
			case 'blockquote':
				$bbc = $this->line_end . '[quote]' . $this->getValue($node) . '[/quote]' . $this->line_end;
				break;
			case 'br':
				$bbc = '[br]';
				break;
			case 'center':
				$bbc = $this->line_end . '[center]' . $this->getValue($node) . '[/center]' . $this->line_end;
				break;
			case 'code':
				$bbc = $this->_convertCode($node);
				break;
			case 'dt':
				$bbc = str_replace(array("\n", "\r", "\n\r"), '', $this->getValue($node)) . $this->line_end;
				break;
			case 'dd':
				$bbc = ':   ' . $this->getValue($node) . $this->line_break;
				break;
			case 'dl':
				$bbc = trim($this->getValue($node)) . $this->line_break;
				break;
			case 'div':
				$bbc = $this->_convertStyles($node);
				$bbc = $this->strip_newlines ? str_replace("\n", ' ', $bbc) : $bbc;
				$bbc = $this->line_end . trim($bbc) . $this->line_end;
				break;
			case 'em':
			case 'i':
				$bbc = '[i]' . $this->getValue($node) . '[/i]';
				break;
			case 'font':
				$bbc = $this->_convertFont($node);
				break;
			case 'hr':
				$bbc = $this->line_end . '[hr]' . $this->line_end;
				break;
			case 'h1':
			case 'h2':
			case 'h3':
			case 'h4':
			case 'h5':
			case 'h6':
				$bbc = $this->line_end . $this->_convertHeader($tag, $this->getValue($node)) . $this->line_end;
				break;
			case 'img':
				$bbc = $this->_convertImage($node);
				break;
			case 'ol':
				$bbc = $this->hasParentList($node) ? trim($this->getValue($node)) : rtrim($this->getValue($node));
				$bbc = $this->line_end . '[list type=decimal]' . $this->line_end . $bbc . $this->line_end . '[/list]' . $this->line_end;
				break;
			case 'ul':
				$bbc = $this->hasParentList($node) ? trim($this->getValue($node)) : rtrim($this->getValue($node));
				$bbc = $this->line_end . '[list]' . $this->line_end . $bbc . $this->line_end . '[/list]' . $this->line_end;
				break;
			case 'li':
				$bbc = '[li]' . ltrim($this->getValue($node)) . '[/li]' . $this->line_end;
				break;
			case 'p':
				$bbc = $this->_convertStyles($node);
				$bbc = $this->strip_newlines ? str_replace("\n", ' ', $bbc) : $bbc;
				$bbc = $this->line_end . rtrim($bbc) . $this->line_end;
				break;
			case 'pre':
				$bbc = $this->line_end . '[pre]' . $this->getInnerHTML($node) . '[/pre]' . $this->line_end;
				break;
			case 'script':
			case 'style':
				$bbc = '';
				break;
			case 'span':
				$bbc = $this->_convertStyles($node);
				break;
			case 'strike':
			case 'del':
			case 's':
				$bbc = '[s]' . $this->getValue($node) . '[/s]';
				break;
			case 'sub':
				$bbc = '[sub]' . $this->getValue($node) . '[/sub]';
				break;
			case 'sup':
				$bbc = '[sup]' . $this->getValue($node) . '[/sup]';
				break;
			case 'title':
				$bbc = '[size=2]' . $this->getValue($node) . '[/size]';
				break;
			case 'table':
				$bbc = $this->line_end . '[table]' . $this->line_end . $this->getValue($node) . '[/table]' . $this->line_end;
				break;
			case '#text':
				$bbc = $this->getValue($node);
				break;
			case 'th':
			case 'td':
				$bbc = $this->_convertTableCell($node) . $this->line_end;
				break;
			case 'tr':
				$bbc = '[tr]' . $this->line_end . $this->getValue($node) . '[/tr]' . $this->line_end;
				break;
			case 'tbody':
			case 'tfoot':
			case 'thead':
				$bbc = $this->getInnerHTML($node);
				break;
			case 'tt':
				$bbc = '[tt]' . $this->getValue($node) . '[/tt]';
				break;
			case 'u':
			case 'ins':
				$bbc = '[u]' . $this->getValue($node) . '[/u]';
				break;
			case 'root':
			case 'body':
				// Remove these tags and simply replace with the text inside the tags
				$bbc = '~`skip`~';
				break;
			default:
				// Don't know you, so just preserve is there, less the tag
				$bbc = $this->getOuterHTML($node);
		}

		// Replace the node with our bbc replacement, or with the node itself if none was found
		if ($bbc !== '~`skip`~')
		{
			$this->setTextNode($node, $bbc);
		}
	}

	/**
	 * Converts <a> tags to bbc
	 *
	 * html: <a href='http://somesite.com' title='Title'>Awesome Site</a>
	 * bbc: [url=http://somesite.com]Awesome Site[/url]
	 *
	 * @param \DOMNode|object $node
	 *
	 * @return string
	 */
	private function _convertAnchor($node)
	{
		global $modSettings, $scripturl;

		$href = htmlentities($node->getAttribute('href'));
		$id = htmlentities($node->getAttribute('id'));
		$value = $this->getValue($node);

		// An anchor link
		if (empty($href) && !empty($id))
		{
			return '[anchor=' . $id . ']' . $value . '[/anchor]';
		}

		if (!empty($href) && $href[0] === '#')
		{
			return '[url=' . $href . ']' . $value . '[/url]';
		}

		// Maybe an email link
		if (substr($href, 0, 7) === 'mailto:')
		{
			if ($href !== 'mailto:' . ($modSettings['maillist_sitename_address'] ?? ''))
			{
				$href = substr($href, 7);
			}
			else
			{
				$href = '';
			}

			return !empty($value) ? '[email=' . $href . ']' . $value . '[/email]' : '[email]' . $href . '[/email]';
		}

		// FTP
		if (substr($href, 0, 6) === 'ftp://')
		{
			return !empty($value) ? '[ftp=' . $href . ']' . $value . '[/ftp]' : '[ftp]' . $href . '[/ftp]';
		}

		// Oh a link then
		// If No http(s), then attempt to fix this potential relative URL.
		if (preg_match('~^https?://~i', $href) === 0 && is_array($parsedURL = parse_url($scripturl)) && isset($parsedURL['host']))
		{
			$baseURL = ($parsedURL['scheme'] ?? 'http') . '://' . $parsedURL['host'] . (empty($parsedURL['port']) ? '' : ':' . $parsedURL['port']);
			if (substr($href, 0, 1) === '/')
			{
				$href = $baseURL . $href;
			}
			else
			{
				$href = $baseURL . (empty($parsedURL['path']) ? '/' : preg_replace('~/(?:index\\.php)?$~', '', $parsedURL['path'])) . '/' . $href;
			}
		}

		return !empty($value) ? '[url=' . $href . ']' . $value . '[/url]' : '[url]' . $href . '[/url]';
	}

	/**
	 * Converts <abbr> tags to bbc
	 *
	 * html: <abbr title="Hyper Text Markup Language">HTML</abbr>
	 * bbc:  [abbr=Hyper Text Markup Language]HTML[/abbr]
	 *
	 * @param \DOMNode|object $node
	 *
	 * @return string
	 */
	private function _convertAbbr($node)
	{
		$title = $node->getAttribute('title');
		$value = $this->getValue($node);

		return !empty($title) ? '[abbr=' . $title . ']' . $value . '[/abbr]' : '';
	}

	/**
	 * Converts <bdo> tags to bbc
	 *
	 * html: <bdo dir="rtl">Some text</bdo>
	 * bbc: [bdo=rtl]Some Text[/bdo]
	 *
	 * @param \DOMNode|object $node
	 *
	 * @return string
	 */
	private function _convertBdo($node)
	{
		$bbc = '';

		$dir = htmlentities($node->getAttribute('dir'));
		$value = $this->getValue($node);

		if ($dir === 'rtl' || $dir === 'ltr')
		{
			$bbc = '[bdo=' . $dir . ']' . $value . '[/bdo]';
		}

		return $bbc;
	}

	/**
	 * Converts code tags to bbc block code
	 *
	 * @param \DOMNode|object $node
	 *
	 * @return string
	 */
	private function _convertCode($node)
	{
		$bbc = '';
		$this->strip_newlines = false;
		$value = $this->getInnerHTML($node);
		$value = preg_replace('~<br( /)?>~', $this->line_end, $value);

		// Get the number of lines of code that we have
		$lines = preg_split('~\r\n|\r|\n~', $value);
		$total = count($lines);

		// If there's more than one line of code we clean it up a bit
		if ($total > 1)
		{
			// Remove any leading and trailing blank lines
			while (trim($lines[0]) === '')
			{
				array_shift($lines);
			}
			while (trim($lines[count($lines) - 1]) === '')
			{
				array_pop($lines);
			}

			// Convert what remains
			foreach ($lines as $line)
			{
				$bbc .= $line . $this->line_end;
			}

			$bbc = rtrim($bbc, $this->line_end);
		}
		// Single line
		else
		{
			$bbc .= $lines[0];
		}

		return '[code]' . Util::htmlspecialchars($bbc) . '[/code]';
	}

	/**
	 * Searches an inline tag for style attributes and if found converts them to basic bbc
	 *
	 * html: <span style="font-weight: bold;">Some Text</span>
	 * bbc: [b]Some Text[/b]
	 *
	 * @param \DOMNode|object $node
	 *
	 * @return string
	 */
	private function _convertStyles($node)
	{
		$style = $node->getAttribute('style');
		$value = $this->getInnerHTML($node);

		// Don't style if it's really just empty
		$test_value = trim($value);
		if (empty($style) || empty($test_value) || $test_value === '[br]' || $test_value === '<br>')
		{
			return $value;
		}

		$bbc = $value;
		$styles = $this->_getStyleValues($style);
		foreach ($styles as $tag => $value)
		{
			// Skip any inline styles as needed
			if (in_array($tag, $this->_skip_style))
			{
				continue;
			}

			// Well this can be as long, complete and exhaustive as we want
			switch ($tag)
			{
				case 'font-family':
					// Only get the first font if there's a list
					if (strpos($value, ',') !== false)
					{
						$value = substr($value, 0, strpos($value, ','));
					}
					$bbc = '[font=' . strtr($value, array("'" => '')) . ']' . $bbc . '[/font]';
					break;
				case 'font-weight':
					if ($value === 'bold' || $value === 'bolder' || $value == '700' || $value == '600')
					{
						$bbc = '[b]' . $bbc . '[/b]';
					}
					break;
				case 'font-style':
					if ($value == 'italic')
					{
						$bbc = '[i]' . $bbc . '[/i]';
					}
					break;
				case 'text-decoration':
					if ($value == 'underline')
					{
						$bbc = '[u]' . $bbc . '[/u]';
					}
					elseif ($value == 'line-through')
					{
						$bbc = '[s]' . $bbc . '[/s]';
					}
					break;
				case 'font-size':
					// Account for formatting issues, decimal in the wrong spot
					if (preg_match('~(\d+)\.\d+(p[xt])~i', $value, $dec_matches) === 1)
					{
						$value = $dec_matches[1] . $dec_matches[2];
					}
					$bbc = '[size=' . $value . ']' . $bbc . '[/size]';
					break;
				case 'color':
					$bbc = '[color=' . $value . ']' . $bbc . '[/color]';
					break;
				// These tags all mean the same thing as far as BBC is concerned
				case 'float':
				case 'text-align':
				case 'align':
					if ($value === 'right')
					{
						$bbc = '[right]' . $value . '[/right]';
					}
					elseif ($value === 'left')
					{
						$bbc = '[left]' . $value . '[/left]';
					}
					elseif ($value === 'center')
					{
						$bbc = '[center]' . $value . '[/center]';
					}
					break;
			}
		}

		return $bbc;
	}

	/**
	 * If there are inline styles, returns an array of $array['attribute'] => $value
	 * $style['width'] = '150px'
	 *
	 * @param string $style
	 *
	 * @return array
	 */
	private function _getStyleValues($style)
	{
		$styles = array();

		if (preg_match_all('~.*?:.*?(;|$)~', $style, $matches, PREG_SET_ORDER))
		{
			foreach ($matches as $match)
			{
				if (strpos($match[0], ':'))
				{
					list ($key, $value) = explode(':', trim($match[0], ';'));
					$key = trim($key);
					$styles[$key] = trim($value);
				}
			}
		}

		return $styles;
	}

	/**
	 * Convert font tags to the appropriate sequence of bbc tags
	 * html: <font size="3" color="red">This is some text!</font>
	 * bbc: [color=red][size=12pt]This is some text![/size][/color]
	 *
	 * @param \DOMNode|object $node
	 *
	 * @return string
	 */
	private function _convertFont($node)
	{
		$size = $node->getAttribute('size');
		$color = $node->getAttribute('color');
		$face = $node->getAttribute('face');
		$bbc = $this->getInnerHTML($node);

		// Font / size can't span across certain tags with our bbc parser, so fix them now
		$blocks = preg_split('~(\[hr\]|\[quote\])~', $bbc, 2, PREG_SPLIT_DELIM_CAPTURE);

		if (!empty($size))
		{
			// All this for a depreciated tag attribute :P
			$size = (int) $size;
			$size = $this->sizes_equivalence[$size];
			$blocks[0] = '[size=' . $size . ']' . $blocks[0] . '[/size]';
		}

		if (!empty($face))
		{
			$blocks[0] = '[font=' . strtolower($face) . ']' . $blocks[0] . '[/font]';
		}

		if (!empty($color))
		{
			$blocks[0] = '[color=' . strtolower($color) . ']' . $blocks[0] . '[/color]';
		}

		return implode('', $blocks);
	}

	/**
	 * Converts <h1> ... <h7> headers to bbc size text,
	 * html: <h1>header</h1>
	 * bbc: [size=36pt]header[/size]
	 *
	 * @param string $level
	 * @param string $content
	 *
	 * @return string
	 */
	private function _convertHeader($level, $content)
	{
		$level = (int) trim($level, 'h');
		$hsize = array(1 => 7, 2 => 6, 3 => 5, 4 => 4, 5 => 3, 6 => 2, 7 => 1);

		$size = $this->sizes_equivalence[$hsize[$level]] ?? $this->sizes_equivalence[4];

		return '[size=' . $size . ']' . $content . '[/size]';
	}

	/**
	 * Converts <img> tags to bbc
	 *
	 * html: <img src='source' alt='alt' title='title' />
	 * bbc: [img]src[/img]
	 *
	 * @param \DOMNode|object $node
	 *
	 * @return array|string|string[]
	 */
	private function _convertImage($node)
	{
		$src = $node->getAttribute('src');
		$alt = $node->getAttribute('alt');
		$title = $node->getAttribute('title');
		$width = $node->getAttribute('width');
		$height = $node->getAttribute('height');
		$style = $node->getAttribute('style');

		$size = '';

		// First if this is an inline image, we don't support those, but will use any ALT found
		if (substr($src, 0, 4) === 'cid:')
		{
			return $alt;
		}

		// Do the basic things first, title/alt
		if (!empty($title) && empty($alt))
		{
			$bbc = '[img alt=' . $title . ']' . $src . '[/img]';
		}
		elseif (!empty($alt))
		{
			$bbc = '[img alt=' . $alt . ']' . $src . '[/img]';
		}
		else
		{
			$bbc = '[img]' . $src . '[/img]';
		}

		// If the tag has a style attribute
		if (!empty($style))
		{
			$styles = $this->_getStyleValues($style);

			// Image size defined in the tag
			if (isset($styles['width']))
			{
				preg_match('~^\d*~', $styles['width'], $width);
				$size .= 'width=' . $width[0] . ' ';
			}

			if (isset($styles['height']))
			{
				preg_match('~^\d*~', $styles['height'], $height);
				$size .= 'height=' . $height[0];
			}
		}

		// Only use depreciated width/height tags if no css was supplied
		if (empty($size))
		{
			if (!empty($width))
			{
				$size .= 'width=' . $width . ' ';
			}

			if (!empty($height))
			{
				$size .= 'height=' . $height;
			}
		}

		if (!empty($size))
		{
			$bbc = str_replace('[img', '[img ' . $size, $bbc);
		}

		return $bbc;
	}

	/**
	 * Checks if an td/th has colspan set, if so repeat those as a number of td's
	 * Checks if there is an align attribute and adds the proper bbc tag
	 *
	 * html: <td colspan="2">Some Text</td>
	 * bbc: [td]Some Text[/td][td][/td]
	 *
	 * @param \DOMNode|object $node
	 *
	 * @return string
	 */
	private function _convertTableCell($node)
	{
		$value = $this->getInnerHTML($node);
		$align = $node->getAttribute('align');
		$colspan = $node->getAttribute('colspan');

		// Any cell alignment to account for?
		if (!empty($align))
		{
			$align = trim($align, '"');
			if ($align === 'right')
			{
				$value = '[right]' . $value . '[/right]';
			}
			elseif ($align === 'left')
			{
				$value = '[left]' . $value . '[/left]';
			}
			elseif ($align === 'center')
			{
				$value = '[center]' . $value . '[/center]';
			}
		}

		// And are we spanning more than one col here?
		$colspan = trim($colspan);
		$colspan = empty($colspan) ? 1 : (int) $colspan;

		return '[td]' . $value . str_repeat('[/td][td]', $colspan - 1) . '[/td]';
	}

	/**
	 * Looks for double html encoding items and continues to decode until fixed
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	private function _recursive_decode($text)
	{
		do
		{
			$text = preg_replace('/&amp;([a-zA-Z0-9]{2,7});/', '&$1;', $text, -1, $count);
		} while (!empty($count));

		return html_entity_decode(htmlspecialchars_decode($text, ENT_QUOTES), ENT_QUOTES, 'UTF-8');
	}

	/**
	 * Sanitize spacing and excess blank lines
	 *
	 * @param string $bbc
	 * @return string
	 */
	public function cleanBBC($bbc)
	{
		// Remove comment blocks
		$bbc = preg_replace('~\\<\\!--.*?-->~', '', $bbc);
		$bbc = preg_replace('~\\<\\!\\[CDATA\\[.*?\\]\\]\\>~i', '', $bbc);

		// Strip any excess leading/trailing blank lines we may have produced O:-)
		$bbc = trim($bbc);
		$bbc = preg_replace("~(?:\s?\n\s?){2,6}~", "\n\n", $bbc);

		// Return protected tags
		$bbc = strtr($bbc, array('&amp#91;' => '[', '&amp#93;' => ']'));

		// Remove any html tags we left behind ( outside of code tags that is )
		$parts = preg_split('~(\[/code\]|\[code(?:=[^\]]+)?\])~i', $bbc, -1, PREG_SPLIT_DELIM_CAPTURE);
		if ($parts !== false)
		{
			foreach ($parts as $i => $part)
			{
				if ($i % 4 === 0)
				{
					// protect << symbols from being stripped
					$working = htmlspecialchars($part, ENT_NOQUOTES, 'UTF-8');
					$working = strip_tags($working);

					// Strip can return nothing due to an error
					if (empty($working))
					{
						$parts[$i] = $part;
					}
					else
					{
						$parts[$i] = htmlspecialchars_decode($working);
					}
				}
			}
		}

		return empty($parts) ? '' : implode('', $parts);
	}
}
