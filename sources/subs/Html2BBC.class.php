<?php

/**
 * Converts a string of HTML to BBC
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1.7
 *
 */

/**
 * Converts a string of HTML to BBC
 *
 * Initiate
 *    $bbc_converter = new Html_2_BBC($html);
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
class Html_2_BBC
{
	/**
	 * The value that will hold our dom object
	 * @var object
	 */
	public $doc;

	/**
	 * The value that will hold if we are using the internal or external parser
	 * @var boolean
	 */
	private $_parser;

	/**
	 * Line end character
	 * @var string
	 */
	public $line_end = "\n";

	/**
	 * Line break character
	 * @var string
	 */
	public $line_break = '[br]';

	/**
	 * Font numbers to pt size
	 * @var string[]
	 */
	public $sizes_equivalence = array(1 => '8pt', '10pt', '12pt', '14pt', '18pt', '24pt', '36pt');

	/**
	 * Holds block elements, its intentionally not complete and is used to prevent adding extra br's
	 * @var string[]
	 */
	public $block_elements = array('p', 'div', 'ol', 'ul', 'pre', 'table', 'blockquote', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6');

	/**
	 * Used to strip newlines inside of 'p' and 'div' elements
	 * @var boolean|null
	 */
	public $strip_newlines = null;

	/**
	 * Holds any html tags that would normally be convert to bbc but are instead skipped
	 * @var string[]
	 */
	protected $_skip_tags = array();

	/**
	 * Holds any style attributes that would normally be convert to bbc but are instead skipped
	 * @var string[]
	 */
	protected $_skip_style = array();

	/**
	 * Gets everything started using the built in or external parser
	 *
	 * @param string $html string of html to convert
	 * @param boolean $strip flag to strip newlines, true by default
	 */
	public function __construct($html, $strip = true)
	{
		// Up front, remove whitespace between html tags
		$html = preg_replace('/(?:(?<=\>)|(?<=\/\>))(\s+)(?=\<\/?)/', '', $html);
		$html = strtr($html, array('[' => '&amp#91;', ']' => '&amp#93;'));
		$this->strip_newlines = $strip;

		// Using PHP built in functions ...
		if (class_exists('DOMDocument'))
		{
			$this->_parser = true;
			$this->doc = new DOMDocument();
			$this->doc->preserveWhiteSpace = false;

			// Make it a utf-8 doc always and be silent about those html structure errors
			libxml_use_internal_errors(true);
			$this->doc->loadHTML('<?xml encoding="UTF-8">' . $html);
			$this->doc->encoding = 'UTF-8';
			libxml_clear_errors();
		}
		// Or using the external simple html parser
		else
		{
			$this->_parser = false;
			require_once(EXTDIR . '/simple_html_dom.php');
			$this->doc = str_get_html($html, true, true, 'UTF-8', false);
		}
	}

	/**
	 * If we want to skip over some tags (that would normally be converted)
	 *
	 * @param string[] $tags
	 */
	public function skip_tags($tags = array())
	{
		// If its not an array, make it one
		if (!is_array($tags))
			$tags = array($tags);

		if (!empty($tags))
			$this->_skip_tags = $tags;
	}

	/**
	 * If we want to skip over inline style tags (that would normally be converted)
	 *
	 * @param string[] $styles
	 */
	public function skip_styles($styles = array())
	{
		// If its not an array, make it one
		if (!is_array($styles))
			$styles = array($styles);

		if (!empty($styles))
			$this->_skip_style = $styles;
	}

	/**
	 * Loads the html body and sends it to the parsing loop to convert all
	 * DOM nodes to BBC
	 */
	public function get_bbc()
	{
		// For this html node, find all child elements and convert
		$body = ($this->_parser) ? $this->doc->getElementsByTagName('body')->item(0) : $this->doc->root;

		// Convert all the nodes that we know how to
		$this->_convert_childNodes($body);

		// Done replacing HTML elements, now get the converted DOM tree back into a string
		$bbc = ($this->_parser) ? $this->doc->saveHTML() : $this->doc->save();
		$bbc = $this->_recursive_decode($bbc);

		if ($this->_parser)
		{
			// Using the internal DOM methods we need to do a little extra work
			if (preg_match('~<body>(.*)</body>~s', $bbc, $body))
				$bbc = $body[1];
		}

		// Remove comment blocks
		$bbc = preg_replace('~\\<\\!--.*?-->~i', '', $bbc);
		$bbc = preg_replace('~\\<\\!\\[CDATA\\[.*?\\]\\]\\>~i', '', $bbc);

		// Remove non breakable spaces that may be hiding in here
		$bbc = str_replace("\xC2\xA0\x20", ' ', $bbc);
		$bbc = str_replace("\xC2\xA0", ' ', $bbc);

		// Strip any excess leading/trailing blank lines we may have produced O:-)
		$bbc = trim($bbc);
		$bbc = preg_replace('~^(?:\[br\s*\/?\]\s*)+~', '', $bbc);
		$bbc = preg_replace('~(?:\[br\s*\/?\]\s*)+$~', '', $bbc);
		$bbc = preg_replace('~\s?(\[br\])\s?~', '[br]', $bbc);
		$bbc = str_replace('[hr][br]', '[hr]', $bbc);

		// Remove any html tags we left behind ( outside of code tags that is )
		$parts = preg_split('~(\[/code\]|\[code(?:=[^\]]+)?\])~i', $bbc, -1, PREG_SPLIT_DELIM_CAPTURE);
		for ($i = 0, $n = count($parts); $i < $n; $i++)
		{
			if ($i % 4 == 0)
				$parts[$i] = strip_tags($parts[$i]);
		}
		$bbc = implode('', $parts);

		return $bbc;
	}

	/**
	 * For a given node, checks if it is anywhere nested inside of a code block
	 * - Prevents converting anything that's inside a code block
	 *
	 * @param DOMNode|object $node current dom node being worked on
	 * @param boolean $parser internal or external parser
	 */
	private static function _has_parent_code($node, $parser)
	{
		$parent = $parser ? $node->parentNode : $node->parentNode();
		while ($parent)
		{
			if (is_null($parent))
				return false;

			// Anywhere nested inside a code block we don't render tags
			$tag = $parser ? $parent->nodeName : $parent->nodeName();
			if ($tag === 'code')
				return true;

			// Back out another level, until we are done
			$parent = $parser ? $parent->parentNode : $parent->parentNode();
		}

		return false;
	}

	/**
	 * Traverse each node to its base, then convert tags to bbc on the way back out
	 *
	 * @param DOMNode|object $node
	 */
	private function _convert_childNodes($node)
	{
		if (empty($node) || self::_has_parent_code($node, $this->_parser))
			return;

		// Keep traversing till we are at the base of this node
		if ($node->hasChildNodes())
		{
			$num = $this->_parser ? $node->childNodes->length : count($node->childNodes());
			for ($i = 0; $i < $num; $i++)
			{
				$child = $this->_parser ? $node->childNodes->item($i) : $node->childNodes($i);
				$this->_convert_childNodes($child);
			}
		}

		// At the root of this node, convert it to bbc
		$this->_convert_to_bbc($node);
	}

	/**
	 * Convert the supplied node into its bbc equivalent
	 *
	 * @param DOMNode|object $node
	 */
	private function _convert_to_bbc($node)
	{
		// HTML tag names
		$tag = $this->_get_name($node);
		$parent = $this->_get_name($this->_parser ? $node->parentNode : $node->parentNode());
		$next_tag = $this->_get_name($this->_parser ? $node->nextSibling : $node->next_sibling());

		// Looking around, do we need to add any breaks here?
		$needs_leading_break = in_array($tag, $this->block_elements);
		$needs_trailing_break = !in_array($next_tag, $this->block_elements) && $needs_leading_break;

		// Flip things around inside li element, it looks better
		if ($parent == 'li' && $needs_leading_break)
		{
			$needs_trailing_break = true;
			$needs_leading_break = false;
		}

		// Skipping over this tag?
		if (in_array($tag, $this->_skip_tags))
			$tag = '';

		// Based on the current tag, determine how to convert
		switch ($tag)
		{
			case 'a':
				$bbc = $this->_convert_anchor($node);
				break;
			case 'abbr':
				$bbc = $this->_convert_abbr($node);
				break;
			case 'b':
			case 'strong':
				$bbc = '[b]' . $this->_get_value($node) . '[/b]';
				break;
			case 'bdo':
				$bbc = $this->_convert_bdo($node);
				break;
			case 'blockquote':
				$bbc = '[quote]' . $this->_get_value($node) . '[/quote]';
				break;
			case 'br':
				$bbc = $this->line_break . $this->line_end;
				break;
			case 'center':
				$bbc = '[center]' . $this->_get_value($node) . '[/center]' . $this->line_end;
				break;
			case 'code':
				$bbc = $this->_convert_code($node);
				break;
			case 'dt':
				$bbc = str_replace(array("\n", "\r", "\n\r"), '', $this->_get_value($node)) . $this->line_end;
				break;
			case 'dd':
				$bbc = ':   ' . $this->_get_value($node) . $this->line_break;
				break;
			case 'dl':
				$bbc = trim($this->_get_value($node)) . $this->line_break;
				break;
			case 'div':
				$bbc = $this->strip_newlines ? str_replace("\n", ' ', $this->_convert_styles($node)) : $this->_convert_styles($node);
				$bbc = preg_replace('~ {2,}~', '&nbsp; ', $bbc);
				break;
			case 'em':
			case 'i':
				$bbc = '[i]' . $this->_get_value($node) . '[/i]';
				break;
			case 'font':
				$bbc = $this->_convert_font($node);
				break;
			case 'hr':
				$bbc = '[hr]';
				break;
			case 'h1':
			case 'h2':
			case 'h3':
			case 'h4':
			case 'h5':
			case 'h6':
				$bbc = $this->_convert_header($tag, $this->_get_value($node));
				break;
			case 'img':
				$bbc = $this->_convert_image($node);
				break;
			case 'ol':
				$bbc = '[list type=decimal]' . $this->line_end . $this->_get_value($node) . '[/list]';
				break;
			case 'ul':
				$bbc = '[list]' . $this->line_end . $this->_get_value($node) . '[/list]';
				break;
			case 'li':
				$bbc = '[li]' . $this->_get_value($node) . '[/li]' . $this->line_end;
				break;
			case 'p':
				$bbc = $this->strip_newlines ? str_replace("\n", ' ', $this->_convert_styles($node)) : $this->_convert_styles($node);
				break;
			case 'pre':
				$bbc = '[pre]' . $this->_get_value($node) . '[/pre]';
				break;
			case 'script':
			case 'style':
				$bbc = '';
				break;
			case 'span':
				// Convert some basic inline styles to bbc
				$bbc = $this->_convert_styles($node);
				break;
			case 'strike':
			case 'del':
			case 's':
				$bbc = '[s]' . $this->_get_value($node) . '[/s]';
				break;
			case 'sub':
				$bbc = '[sub]' . $this->_get_value($node) . '[/sub]';
				break;
			case 'sup':
				$bbc = '[sup]' . $this->_get_value($node) . '[/sup]';
				break;
			case 'title':
				$bbc = '[size=2]' . $this->_get_value($node) . '[/size]';
				break;
			case 'table':
				$bbc = '[table]' . $this->line_end . $this->_get_value($node) . '[/table]';
				break;
			case 'th':
			case 'td':
				$bbc = $this->_convert_table_cell($node) . $this->line_end;
				break;
			case 'tr':
				$bbc = '[tr]' . $this->line_end . $this->_get_value($node) . '[/tr]' . $this->line_end;
				break;
			case 'tbody':
			case 'tfoot':
			case 'thead':
				$bbc = $this->_get_innerHTML($node);
				break;
			case 'tt':
				$bbc = '[tt]' . $this->_get_value($node) . '[/tt]';
				break;
			case 'u':
			case 'ins':
				$bbc = '[u]' . $this->_get_value($node) . '[/u]';
				break;
			case 'root':
			case 'body':
				// Remove these tags and simply replace with the text inside the tags
				$bbc = '~`skip`~';
				break;
			default:
				// Don't know you, so just preserve whats there, less the tag
				$bbc = $this->_get_outerHTML($node);
		}

		// Replace the node with our bbc replacement, or with the node itself if none was found
		if ($bbc !== '~`skip`~')
		{
			$bbc = $needs_leading_break ? $this->line_break . $this->line_end . $bbc : $bbc;
			$bbc = $needs_trailing_break ? $bbc . $this->line_break . $this->line_end : $bbc;
			if ($this->_parser)
			{
				// Create a new text node with our bbc tag and replace the original node
				$bbc_node = $this->doc->createTextNode($bbc);
				$node->parentNode->replaceChild($bbc_node, $node);
			}
			else
				$node->outertext = $bbc;
		}
	}

	/**
	 * Converts <abbr> tags to bbc
	 *
	 * html: <abbr title="Hyper Text Markup Language">HTML</abbr>
	 * bbc:  [abbr=Hyper Text Markup Language]HTML[/abbr]
	 *
	 * @param DOMNode|object $node
	 */
	private function _convert_abbr($node)
	{
		$title = $node->getAttribute('title');
		$value = $this->_get_value($node);

		if (!empty($title))
			$bbc = '[abbr=' . $title . ']' . $value . '[/abbr]';
		else
			$bbc = '';

		return $bbc;
	}

	/**
	 * Converts <a> tags to bbc
	 *
	 * html: <a href='http://somesite.com' title='Title'>Awesome Site</a>
	 * bbc: [url=http://somesite.com]Awesome Site[/url]
	 *
	 * @param DOMNode|object $node
	 */
	private function _convert_anchor($node)
	{
		global $modSettings, $scripturl;

		$href = htmlentities($node->getAttribute('href'));
		$id = htmlentities($node->getAttribute('id'));
		$value = $this->_get_value($node);

		// An anchor link
		if (empty($href) && !empty($id))
			$bbc = '[anchor=' . $id . ']' . $value . '[/anchor]';
		elseif (!empty($href) && $href[0] === '#')
			$bbc = '[url=' . $href . ']' . $value . '[/url]';
		// Maybe an email link
		elseif (substr($href, 0, 7) === 'mailto:')
		{
			if ($href != 'mailto:' . (isset($modSettings['maillist_sitename_address']) ? $modSettings['maillist_sitename_address'] : ''))
				$href = substr($href, 7);
			else
				$href = '';

			if (!empty($value))
				$bbc = '[email=' . $href . ']' . $value . '[/email]';
			else
				$bbc = '[email]' . $href . '[/email]';
		}
		// FTP
		elseif (substr($href, 0, 6) === 'ftp://')
		{
			if (!empty($value))
				$bbc = '[ftp=' . $href . ']' . $value . '[/ftp]';
			else
				$bbc = '[ftp]' . $href . '[/ftp]';
		}
		// Oh a link then
		else
		{
			// If No http(s), then attempt to fix this potential relative URL.
			if (preg_match('~^https?://~i', $href) === 0 && is_array($parsedURL = parse_url($scripturl)) && isset($parsedURL['host']))
			{
				$baseURL = (isset($parsedURL['scheme']) ? $parsedURL['scheme'] : 'http') . '://' . $parsedURL['host'] . (empty($parsedURL['port']) ? '' : ':' . $parsedURL['port']);

				if (substr($href, 0, 1) === '/')
					$href = $baseURL . $href;
				else
					$href = $baseURL . (empty($parsedURL['path']) ? '/' : preg_replace('~/(?:index\\.php)?$~', '', $parsedURL['path'])) . '/' . $href;
			}

			if (!empty($value))
				$bbc = '[url=' . $href . ']' . $value . '[/url]';
			else
				$bbc = '[url]' . $href . '[/url]';
		}

		return $bbc;
	}

	/**
	 * Converts <bdo> tags to bbc
	 *
	 * html: <bdo dir="rtl">Some text</bdo>
	 * bbc: [bdo=rtl]Some Text[/bdo]
	 *
	 * @param DOMNode|object $node
	 */
	private function _convert_bdo($node)
	{
		$bbc = '';

		$dir = htmlentities($node->getAttribute('dir'));
		$value = $this->_get_value($node);

		if ($dir == 'rtl' || $dir == 'ltr')
			$bbc = '[bdo=' . $dir . ']' . $value . '[/bdo]';

		return $bbc;
	}

	/**
	 * Converts code tags to bbc block code
	 *
	 * @param DOMNode|object $node
	 */
	private function _convert_code($node)
	{
		$bbc = '';
		$value = $this->_get_innerHTML($node);

		// Get the number of lines of code that we have
		$lines = preg_split('~\r\n|\r|\n~', $value);
		$total = count($lines);

		// If there's more than one line of code we clean it up a bit
		if ($total > 1)
		{
			$first_line = trim($lines[0]);
			$last_line = trim($lines[$total - 1]);

			// Remove any leading and trailing blank lines
			if (empty($first_line))
				array_shift($lines);
			if (empty($last_line))
				array_pop($lines);

			// Convert what remains
			foreach ($lines as $line)
				$bbc .= $line . $this->line_end;

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
	 * Convert font tags to the appropriate sequence of bbc tags
	 * html: <font size="3" color="red">This is some text!</font>
	 * bbc: [color=red][size=12pt]This is some text![/size][/color]
	 *
	 * @param DOMNode|object $node
	 */
	private function _convert_font($node)
	{
		$size = $node->getAttribute('size');
		$color = $node->getAttribute('color');
		$face = $node->getAttribute('face');
		$bbc = $this->_get_innerHTML($node);

		// Font / size can't span across certain tags with our bbc parser, so fix them now
		$blocks = preg_split('~(\[hr\]|\[quote\])~s', $bbc, 2, PREG_SPLIT_DELIM_CAPTURE);

		if (!empty($size))
		{
			// All this for a depreciated tag attribute :P
			$size = (int) $size;
			$size = $this->sizes_equivalence[$size];
			$blocks[0] = '[size=' . $size . ']' . $blocks[0] . '[/size]';
		}
		if (!empty($face))
			$blocks[0]  = '[font=' . strtolower($face) . ']' . $blocks[0] . '[/font]';
		if (!empty($color))
			$blocks[0]  = '[color=' . strtolower($color) . ']' . $blocks[0] . '[/color]';

		return implode('', $blocks);
	}

	/**
	 * Converts <h1> ... <h7> headers to bbc size text,
	 * html: <h1>header</h1>
	 * bbc: [size=36pt]header[/size]
	 *
	 * @param int $level
	 * @param string $content
	 */
	private function _convert_header($level, $content)
	{
		$level = (int) trim($level, 'h');
		$hsize = array(1 => 7, 2 => 6, 3 => 5, 4 => 4, 5 => 3, 6 => 2, 7 => 1);

		$size = isset($this->sizes_equivalence[$hsize[$level]]) ? $this->sizes_equivalence[$hsize[$level]] : $this->sizes_equivalence[4];
		$bbc = '[size=' . $size . ']' . $content . '[/size]';

		return $bbc;
	}

	/**
	 * Converts <img> tags to bbc
	 *
	 * html: <img src='source' alt='alt' title='title' />
	 * bbc: [img]src[/img]
	 *
	 * @param DOMNode|object $node
	 */
	private function _convert_image($node)
	{
		$src = $node->getAttribute('src');
		$alt = $node->getAttribute('alt');
		$title = $node->getAttribute('title');
		$width = $node->getAttribute('width');
		$height = $node->getAttribute('height');
		$style = $node->getAttribute('style');

		$bbc = '';
		$size = '';

		// First if this is an inline image, we don't support those
		if (substr($src, 0, 4) === 'cid:')
		{
			return $bbc;
		}

		// Do the basic things first, title/alt
		if (!empty($title) && empty($alt))
			$bbc = '[img alt=' . $title . ']' . $src . '[/img]';
		elseif (!empty($alt))
			$bbc = '[img alt=' . $alt . ']' . $src . '[/img]';
		else
			$bbc = '[img]' . $src . '[/img]';

		// If the tag has a style attribute
		if (!empty($style))
		{
			$styles = $this->_get_style_values($style);

			// Image size defined in the tag
			if (isset($styles['width']))
			{
				preg_match('~^[0-9]*~', $styles['width'], $width);
				$size .= 'width=' . $width[0] . ' ';
			}

			if (isset($styles['height']))
			{
				preg_match('~^[0-9]*~', $styles['height'], $height);
				$size .= 'height=' . $height[0];
			}
		}

		// Only use depreciated width/height tags if no css was supplied
		if (empty($size))
		{
			if (!empty($width))
				$size .= 'width=' . $width . ' ';

			if (!empty($height))
				$size .= 'height=' . $height;
		}

		if (!empty($size))
			$bbc = str_replace('[img', '[img ' . $size, $bbc);

		return $bbc;
	}

	/**
	 * Searches an inline tag for style attributes and if found converts them to basic bbc
	 *
	 * html: <span style="font-weight: bold;">Some Text</span>
	 * bbc: [b]Some Text[/b]
	 *
	 * @param DOMNode|object $node
	 */
	private function _convert_styles($node)
	{
		$style = $node->getAttribute('style');
		$value = $this->_get_innerHTML($node);

		// Don't style it if its really just empty
		$test_value = trim($value);
		if ($test_value === '[br]' || $test_value === '<br>')
		{
			return $value;
		}

		// Its at least going to be itself
		$bbc = $value;

		// If there are some style attributes, lets go through them and convert what we know
		if (!empty($style))
		{
			$styles = $this->_get_style_values($style);
			foreach ($styles as $tag => $value)
			{
				// Skip any inline styles as needed
				if (in_array($tag, $this->_skip_style))
					continue;

				// Well this can be as long, complete and exhaustive as we want :P
				switch ($tag)
				{
					case 'font-family':
						// Only get the first font if there's a list
						if (strpos($value, ',') !== false)
							$value = substr($value, 0, strpos($value, ','));
						$bbc = '[font=' . strtr($value, array("'" => '')) . ']' . $bbc . '[/font]';
						break;
					case 'font-weight':
						if ($value === 'bold' || $value === 'bolder' || $value == '700' || $value == '600')
							$bbc = '[b]' . $bbc . '[/b]';
						break;
					case 'font-style':
						if ($value == 'italic')
							$bbc = '[i]' . $bbc . '[/i]';
						break;
					case 'text-decoration':
						if ($value == 'underline')
							$bbc = '[u]' . $bbc . '[/u]';
						elseif ($value == 'line-through')
							$bbc = '[s]' . $bbc . '[/s]';
						break;
					case 'font-size':
						// Account for formatting issues, decimal in the wrong spot
						if (preg_match('~(\d+)\.\d+(p[xt])~i', $value, $dec_matches) === 1)
							$value = $dec_matches[1] . $dec_matches[2];
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
							$bbc = '[right]' . $value . '[/right]';
						elseif ($value === 'left')
							$bbc = '[left]' . $value . '[/left]';
						elseif ($value === 'center')
							$bbc = '[center]' . $value . '[/center]';
						break;
				}
			}
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
	 * @param DOMNode|object $node
	 */
	private function _convert_table_cell($node)
	{
		$value = $this->_get_innerHTML($node);
		$align = $node->getAttribute('align');
		$colspan = $node->getAttribute('colspan');

		// Any cell alignment to account for?
		if (!empty($align))
		{
			$align = trim($align, '"');
			if ($align === 'right')
				$value = '[right]' . $value . '[/right]';
			elseif ($align === 'left')
				$value = '[left]' . $value . '[/left]';
			elseif ($align === 'center')
				$value = '[center]' . $value . '[/center]';
		}

		// And are we spanning more than one col here?
		$colspan = trim($colspan);
		$colspan = empty($colspan) ? 1 : (int) $colspan;

		$bbc = '[td]' . $value . str_repeat('[/td][td]', $colspan - 1) . '[/td]';

		return $bbc;
	}

	/**
	 * Helper function for getting a node value
	 *
	 * @param DOMNode|object $node
	 */
	private function _get_value($node)
	{
		if ($node === null)
			return '';

		if ($this->_parser)
			return $node->nodeValue;
		else
			return $node->innertext;
	}

	/**
	 * Helper function for getting a node name
	 *
	 * @param DOMNode|object $node
	 */
	private function _get_name($node)
	{
		if ($node === null)
			return '';

		if ($this->_parser)
			return $node->nodeName;
		else
			return $node->nodeName();
	}

	/**
	 * Gets the inner html of a node
	 *
	 * @param DOMNode|object $node
	 * @return string
	 */
	private function _get_innerHTML($node)
	{
		if ($this->_parser)
		{
			$doc = new DOMDocument();
			$doc->appendChild($doc->importNode($node, true));
			$html = trim($doc->saveHTML());
			$tag = $node->nodeName;

			return preg_replace('@^<' . $tag . '[^>]*>|</' . $tag . '>$@', '', $html);
		}
		else
			return $node->innertext;
	}

	/**
	 * Gets the outer html of a node
	 *
	 * @param DOMNode|object $node
	 */
	private function _get_outerHTML($node)
	{
		if ($this->_parser)
		{
			if (version_compare(PHP_VERSION, '5.3.6') >= 0)
				return $this->doc->saveHTML($node);
			else
			{
				// @todo remove when 5.3.6 min
				$doc = new DOMDocument();
				$doc->appendChild($doc->importNode($node, true));
				$html = $doc->saveHTML();

				// We just want the html of the inserted node, it *may* be wrapped
				if (preg_match('~<body>(.*)</body>~s', $html, $body))
					$html = $body[1];
				elseif (preg_match('~<html>(.*)</html>~s', $html, $body))
					$html = $body[1];

				// Clean it up
				return rtrim($html, "\n");
			}
		}
		else
			return $node->outertext;
	}

	/**
	 * If there are inline styles, returns an array of $array['attribute'] => $value
	 * $style['width'] = '150px'
	 *
	 * @param string $style
	 */
	private function _get_style_values($style)
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
	 * Looks for double html encoding items and continues to decode until fixed
	 *
	 * @param string $text
	 */
	private function _recursive_decode($text)
	{
		do
		{
			$text = preg_replace('/&amp;([a-zA-Z0-9]{2,7});/', '&$1;', $text, -1, $count);
		} while (!empty($count));

		$text = html_entity_decode(htmlspecialchars_decode($text, ENT_QUOTES), ENT_QUOTES, 'UTF-8');

		return str_replace(array('&amp#91;', '&amp#93;'), array('&amp;#91;', '&amp;#93;'), $text);
	}
}
