<?php

/**
 * Converts HTML to Markdown text
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1.9
 *
 */

/**
 * Converts HTML to Markdown text
 */
class Html_2_Md
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
	public $line_break = "  \n\n";

	/**
	 * Wordwrap output, set to 0 to skip wrapping
	 * @var int
	 */
	public $body_width = 76;

	/**
	 * Strip remaining tags, set as false to leave them in
	 * @var boolean
	 */
	public $strip_tags = true;

	/**
	 * Regex to run on plain text to prevent markdown from erroneously converting
	 * @var string[]
	 */
	private $_textEscapeRegex;

	/**
	 * The passed html string to convert
	 * @var string
	 */
	public $html;

	/**
	 * The markdown equivalent to the  html string
	 * @var string
	 */
	public $markdown;

	/**
	 * Various settings on how render certain markdown tag
	 * @var string[]
	 */
	public $config = ['heading' => 'atx', 'bullet' => '*', 'em' => '_', 'strong' => '**'];

	/**
	 * Gets everything started using the built-in or external parser
	 *
	 * @param string $html string of html to convert to MD text
	 */
	public function __construct($html)
	{
		// Up front, remove whitespace between html tags
		$this->html = preg_replace('/(?:(?<=>)|(?<=\/>))(\s+)(?=<\/?)/', '', $html);

		// Replace invisible (except \n \t) characters with a space
		$this->html = preg_replace('~[^\S\n\t]~u', ' ', $this->html);

		// The XML parser will not deal gracefully with these
		$this->html = strtr($this->html, array(
			'?<' => '|?|&lt',
			'?>' => '|?|&gt',
			'>?' => '&gt|?|',
			'<?' => '&lt|?|'
		));

		// Set the dom parser to use and load the HTML to the parser
		$this->_set_parser();

		// Initialize the regex array to escape text areas so markdown does
		// not interpret plain text as markdown syntax
		$this->_textEscapeRegex = array(
			'~([*_\\[\\]\\\\])~' => '\\\\$1',
			'~^-~m' => '\\-',
			'~^\+ ~m' => '\\+ ',
			'~^(=+)~m' => '\\\\$1',
			'~^(#{1,6}) ~m' => '\\\\$1 ',
			'~`~' => '\\`',
			'~^>~m' => '\\>',
			'~^(\d+)\. ~m' => '$1\\. ',
		);
	}

	/**
	 * Set the DOM parser for class, loads the supplied HTML
	 */
	private function _set_parser()
	{
		// Using PHP built in functions ...
		if (class_exists('DOMDocument'))
		{
			$this->_parser = true;
			$previous = libxml_use_internal_errors(true);

			// Set up basic parameters for DomDocument, including silencing structural errors
			$this->_setupDOMDocument();

			// Set the error handle back to what it was, and flush
			libxml_use_internal_errors($previous);
			libxml_clear_errors();
		}
		// Or using the external simple html parser
		else
		{
			$this->_parser = false;
			require_once(EXTDIR . '/simple_html_dom.php');
			$this->doc = str_get_html($this->html, true, true, 'UTF-8', false);
		}
	}

	/**
	 * Loads the html body and sends it to the parsing loop to convert all
	 * DOM nodes to markup
	 */
	public function get_markdown()
	{
		// For this html node, find all child elements and convert
		$body = $this->_getBody();
		$this->_convert_childNodes($body);

		// Done replacing HTML elements, now get the converted DOM tree back into a string
		$this->markdown = ($this->_parser) ? $this->doc->saveHTML() : $this->doc->save();

		// Using the internal DOM methods requires we need to do a little extra work
		if ($this->_parser)
		{
			$this->markdown = html_entity_decode(htmlspecialchars_decode($this->markdown, ENT_QUOTES), ENT_QUOTES, 'UTF-8');
		}

		// Clean up any excess spacing etc
		$this->_clean_markdown();

		// Wordwrap?
		if (!empty($this->body_width))
		{
			$this->markdown = $this->_utf8_wordwrap($this->markdown, $this->body_width, $this->line_end);
		}

		// The null character will trigger a base64 version in outbound email
		return $this->markdown . "\n\x00";
	}

	/**
	 * Returns just the body of the HTML, as best possible, so we are not dealing with head
	 * and above head markup
	 *
	 * @return object
	 */
	private function _getBody()
	{
		// If there is a head node, then off with his head!
		$this->_clipHead();

		// The body of the HTML is where its at.
		if ($this->_parser)
		{
			$body = $this->doc->getElementsByTagName('body')->item(0);
		}
		else
		{
			if ($this->doc->find('body', 0) !== null)
			{
				$body = $this->doc->find('body', 0);
			}
			elseif ($this->doc->find('html', 0) !== null)
			{
				$body = $this->doc->find('html', 0);
			}
			else
			{
				$body = $this->doc->root;
			}
		}

		return $body;
	}

	/**
	 * Remove any <head node from the DOM
	 */
	private function _clipHead()
	{
		$head = ($this->_parser) ? $this->doc->getElementsByTagName('head')->item(0) : $this->doc->find('head', 0);
		if ($head !== null)
		{
			if ($this->_parser)
			{
				$head->parentNode->removeChild($head);
			}
			else
			{
				$this->doc->find('head', 0)->outertext = '';
			}
		}
	}

	/**
	 * Sets up processing parameters for DOMDocument to ensure that text is processed as UTF-8
	 */
	private function _setupDOMDocument()
	{
		// If the html is already wrapped, remove it
		$this->html = $this->_returnBodyText($this->html);

		// Set up processing details
		$this->doc = new DOMDocument();
		$this->doc->preserveWhiteSpace = false;
		$this->doc->encoding = 'UTF-8';

		// Do what we can to ensure this is processed as UTF-8
		$this->doc->loadHTML('<?xml encoding="UTF-8"><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/></head><body>' . $this->html . '</body></html>');
	}

	/**
	 * Normalize any spacing and excess blank lines that may have been generated
	 */
	private function _clean_markdown()
	{
		// We only want the content, no wrappers
		$this->markdown = $this->_returnBodyText($this->markdown);

		// Remove any "bonus" tags
		if ($this->strip_tags)
		{
			$this->markdown = strip_tags($this->markdown);
		}

		// Replace content that we "hide" from the XML parsers
		$this->markdown = strtr($this->markdown, array(
			'|?|&gt' => '?>',
			'|?|&lt' => '?<',
			'&lt|?|' => '<?',
			'&gt|?|' => '>?'
		));

		// We may have hidden content ending in ?<br /> due to the above
		$this->markdown = str_replace('<br />', "\n\n", $this->markdown);

		// Strip the chaff and any excess blank lines we may have produced
		$this->markdown = trim($this->markdown);
		$this->markdown = preg_replace("~(?:\s?\n\s?){3,6}~", "\n\n", $this->markdown);	}

	/**
	 * Looks for the text inside <body> and then <html>, returning just the inner
	 *
	 * @param $text
	 *
	 * @return string
	 */
	private function _returnBodyText($text)
	{
		if (preg_match('~<body.*?>(.*)</body>~su', $text, $body))
		{
			return $body[1];
		}

		if (preg_match('~<html.*?>(.*)</html>~su', $text, $body))
		{
			return $body[1];
		}

		// Parsers may have clipped the ending body or html tag off with the quote/signature
		if (preg_match('~<body.*?>(.*)~su', $text, $body))
		{
			return $body[1];
		}

		return $text;
	}

	/**
	 * For a given node, checks if it is anywhere nested inside a code block
	 *  - Prevents converting anything that's inside a code block
	 *
	 * @param object $node
	 * @param boolean $parser flag for internal or external parser
	 *
	 * @return boolean
	 */
	private static function _has_parent_code($node, $parser)
	{
		$parent = $parser ? $node->parentNode : $node->parentNode();
		while ($parent)
		{
			// Anywhere nested inside a code block we don't render tags
			if (in_array($parser ? $parent->nodeName : $parent->nodeName(), array('pre', 'code')))
			{
				return true;
			}

			// Back out another level, until we are done
			$parent = $parser ? $parent->parentNode : $parent->parentNode();
		}

		return false;
	}

	/**
	 * Get the nesting level when inside a list
	 *
	 * @param object $node
	 * @param boolean $parser flag for internal or external parser
	 *
	 * @return int
	 */
	private static function _has_parent_list($node, $parser)
	{
		$inlist = array('ul', 'ol');
		$depth = 0;

		$parent = $parser ? $node->parentNode : $node->parentNode();
		while ($parent)
		{
			// Anywhere nested inside a list we need to get the depth
			$tag = $parser ? $parent->nodeName : $parent->nodeName();
			if (in_array($tag, $inlist))
			{
				$depth++;
			}

			// Back out another level
			$parent = $parser ? $parent->parentNode : $parent->parentNode();
		}

		return $depth;
	}

	/**
	 * Traverse each node to its base, then convert tags to markup on the way back out
	 *
	 * @param object $node
	 */
	private function _convert_childNodes($node)
	{
		if (self::_has_parent_code($node, $this->_parser) && $this->_get_name($node) !== 'code')
		{
			return;
		}

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

		// At the root of this node, convert it to markdown
		$this->_convert_to_markdown($node);
	}

	/**
	 * Convert the supplied node into its markdown equivalent
	 *  - Supports *some* markdown extra tags, namely: table, abbr & dl in a limited fashion
	 *
	 * @param object $node
	 */
	private function _convert_to_markdown($node)
	{
		// HTML tag we are dealing with
		$tag = $this->_get_name($node);

		// Based on the tag, determine how to convert
		switch ($tag)
		{
			case 'a':
				$markdown = $this->_convert_anchor($node);
				break;
			case 'abbr':
				$markdown = $this->_convert_abbr($node);
				break;
			case 'b':
			case 'strong':
				$markdown = $this->config['strong'] . trim($this->_get_value($node)) . $this->config['strong'];
				break;
			case 'blockquote':
				$markdown = $this->_convert_blockquote($node);
				break;
			case 'br':
				$markdown = $this->line_break;
				break;
			case 'center':
				$markdown = $this->line_end . $this->_get_value($node) . $this->line_end;
				break;
			case 'cite':
				$markdown = $this->_convert_cite($node);
				break;
			case 'code':
				$markdown = $this->_convert_code($node);
				break;
			case 'dt':
				$markdown = str_replace(array("\n", "\r", "\n\r"), '', $this->_get_value($node)) . $this->line_end;
				break;
			case 'dd':
				$markdown = ':   ' . $this->_get_value($node) . $this->line_break;
				break;
			case 'dl':
				$markdown = trim($this->_get_value($node)) . $this->line_break;
				break;
			case 'em':
			case 'i':
				$markdown = $this->config['em'] . trim($this->_get_value($node)) . $this->config['em'];
				break;
			case 'hr':
				$markdown = $this->line_end . '---' . $this->line_end;
				break;
			case 'h1':
			case 'h2':
			case 'h3':
			case 'h4':
			case 'h5':
			case 'h6':
				$markdown = $this->_convert_header($tag, $this->_get_value($node));
				break;
			case 'img':
				$markdown = $this->_convert_image($node) . $this->line_end;
				break;
			case 'ol':
			case 'ul':
				if ($this->_has_parent_list($node, $this->_parser))
					$markdown = trim($this->_get_value($node));
				else
					$markdown = $this->line_end . $this->_get_value($node) . $this->line_end;
				break;
			case 'li':
				$markdown = $this->_convert_list($node);
				break;
			case 'p':
				$markdown = $this->line_end . rtrim($this->_get_value($node)) . $this->line_end;
				$markdown = $this->_convert_plaintxt_links($markdown, $node);
				$markdown = $this->_utf8_wordwrap($markdown, $this->body_width, $this->line_end);
				break;
			case 'pre':
				$markdown = $this->_get_innerHTML($node) . $this->line_break;
				break;
			case 'div':
				$markdown = $this->line_end . rtrim($this->_get_value($node));
				$markdown = $this->_utf8_wordwrap($markdown, $this->body_width, $this->line_end) . $this->line_break;
				break;
			case '#text':
				$markdown = $this->_escape_text($this->_get_value($node));
				$markdown = $this->_convert_plaintxt_links($markdown, $node);
				break;
			case 'title':
				$markdown = '# ' . $this->_get_value($node) . $this->line_break;
				break;
			case 'table':
				$markdown = $this->_convert_table($node) . $this->line_break;
				break;
			case 'th':
			case 'tr':
			case 'td':
			case 'tbody':
			case 'tfoot':
			case 'thead':
				// Just skip over these as we handle them in the table tag itself
				$markdown = '~`skip`~';
				break;
			case 'span':
				$markdown = $this->_convert_span($node);
				break;
			case 'root':
			case 'body':
				// Remove these tags and simply replace with the text inside the tags
				$markdown = $this->_get_innerHTML($node);
				break;
			default:
				// Don't know you or text, so just preserve whats there
				$markdown = $this->_get_outerHTML($node) . $this->line_end;
		}

		// Replace the node with our markdown replacement, or with the node itself if none was found
		if ($markdown !== '~`skip`~')
		{
			if ($this->_parser)
			{
				// Create a new text node with our markdown tag and replace the original node
				$markdown_node = $this->doc->createTextNode($markdown);
				$node->parentNode->replaceChild($markdown_node, $node);
			}
			else
			{
				$node->outertext = $markdown;
			}
		}
	}

	/**
	 * Converts <abbr> tags to markdown (extra)
	 *
	 * html: <abbr title="Hyper Text Markup Language">HTML</abbr>
	 * md:   *[HTML]: Hyper Text Markup Language
	 *
	 * @param object $node
	 * @return string
	 */
	private function _convert_abbr($node)
	{
		$title = $node->getAttribute('title');
		$value = $this->_get_value($node);

		return !empty($title) ? '*[' . $value . ']: ' . $title : '';

	}

	/**
	 * Converts <a> tags to markdown
	 *
	 * html: <a href='http://somesite.com' title='Title'>Awesome Site</a>
	 * md: [Awesome Site](http://somesite.com 'Title')
	 *
	 * @param object $node
	 * @return string
	 */
	private function _convert_anchor($node)
	{
		global $txt;

		if ($node->getAttribute('data-lightboximage') || $node->getAttribute('data-lightboxmessage'))
			return '~`skip`~';

		$href = str_replace('\_', '_', htmlspecialchars_decode($node->getAttribute('href')));
		$title = $node->getAttribute('title');
		$class = $node->getAttribute('class');
		$value = str_replace('\_', '_', trim($this->_get_value($node), "\t\n\r\0\x0B"));

		// Provide a more compact [name] if none is given
		if ($value == $node->getAttribute('href') || empty($value))
		{
			$value = empty($title) ? $txt['link'] : $title;
		}

		// Special processing just for our own footnotes
		if ($class === 'target' || $class === 'footnote_return')
		{
			$markdown = $value;
		}
		elseif (!empty($title))
		{
			$markdown = '[' . $value . '](' . $href . ' "' . $title . '")';
		}
		else
		{
			$markdown = '[X](' . $href . ' "' . $txt['link'] . '")';
		}

		$this->_check_line_length($markdown, $this->get_buffer($node));

		return $markdown . $this->line_end;
	}

	/**
	 * Converts blockquotes to markdown > quote style
	 *
	 * html: <blockquote>quote</blockquote>
	 * md: > quote
	 *
	 * @param object $node
	 * @return string
	 */
	private function _convert_blockquote($node)
	{
		$markdown = '';

		// All the contents of this block quote
		$value = trim($this->_get_value($node));

		// Go line by line
		$lines = preg_split('~\r\n|\r|\n~', $value);

		// Each line gets a '> ' in front of it, just like email quotes really
		foreach ($lines as $line)
		{
			$markdown .= '> ' . ltrim($line, "\t") . $this->line_end;
		}

		return $this->line_end . $markdown . $this->line_end;
	}

	/**
	 * Converts cites to markdown with the assumption that they are in a blockquote
	 *
	 * html: <blockquote>quote</blockquote>
	 * md: > quote
	 *
	 * @param object $node
	 * @return string
	 */
	private function _convert_cite($node)
	{
		// All the contents of this cite
		$markdown = trim($this->_get_value($node));

		// Drop the link, just use the citation [bla](link)
		if (preg_match('~\[(.*?)\]\(.*?\)~', $markdown, $match))
		{
			$markdown = $match[1];
		}

		return $this->line_end . $markdown . $this->line_end;
	}

	/**
	 * Converts code tags to markdown span `code` or block code
	 * Converts single line code to inline tick mark
	 * Converts multi line to 4 space indented code
	 *
	 * html: <code>code</code>
	 * md: `code`
	 *
	 * @param object $node
	 * @return string
	 */
	private function _convert_code($node)
	{
		$value = html_entity_decode($this->_get_innerHTML($node), ENT_COMPAT, 'UTF-8');

		// Empty Block
		if (empty($value))
		{
			return '``';
		}

		// Turn off things that may mangle code tags
		$this->strip_tags = false;
		$this->body_width = 0;

		// If we have a multi line code block, we are working outside to in, and need to convert the br's ourselves
		$value = preg_replace('~<br( /)?' . '>~', $this->line_end, str_replace('&nbsp;', ' ', $value));

		// Get the number of lines of code that we have
		$lines = preg_split('~\r\n|\r|\n~', $value);

		// Remove leading and trailing blank lines
		while (trim($lines[0]) === '')
		{
			array_shift($lines);
		}
		while (trim($lines[count($lines) - 1]) === '')
		{
			array_pop($lines);
		}

		// If there's more than one line of code, use fenced code syntax
		$total = count($lines);
		if ($total > 1)
		{
			$fence = $this->line_end . '```' . $this->line_end;

			// Convert what remains
			$markdown = '';
			foreach ($lines as $line)
			{
				$markdown .= $line . $this->line_end;
			}

			return $fence . $markdown . $fence;
		}

		// Single line, back tick, accounting for lines with \'s, and move on
		$ticks = $this->_has_ticks($value);
		if (!empty($ticks))
		{
			// If the ticks were at the start/end of the word space it off
			if ($lines[0][0] === '`' || substr($lines[0], -1) === '`')
			{
				$lines[0] = ' ' . $lines[0] . ' ';
			}

			return $ticks . $lines[0] . $ticks;
		}

		return '`' . $lines[0] . '`';
	}

	/**
	 * Converts <h1> and <h2> headers to markdown-style headers in setex style,
	 * all other headers are returned as atx style ### h3
	 *
	 * html: <h1>header</h1>
	 * md: header
	 *     ======
	 *
	 * html: <h3>header</h3>
	 * md: ###header
	 *
	 * @param int $level
	 * @param string $content
	 * @return string
	 */
	private function _convert_header($level, $content)
	{
		if ($this->config['heading'] === 'setext')
		{
			$length = Util::strlen($content);

			return $this->line_end . $content . $this->line_end . str_repeat('=', $length) . $this->line_break;
		}

		$level = (int) ltrim($level, 'h');

		return $this->line_end . str_repeat('#', $level) . ' ' . $content . $this->line_break;
	}

	/**
	 * Converts <img> tags to markdown
	 *
	 * html: <img src='source' alt='alt' title='title' />
	 * md: ![alt](source 'title')
	 *
	 * @param object $node
	 * @return string
	 */
	private function _convert_image($node)
	{
		$src = $node->getAttribute('src');
		$alt = $node->getAttribute('alt');
		$title = $node->getAttribute('title');
		$parent = $this->_parser ? $node->parentNode : $node->parentNode();

		// A plain linked image, just return the alt text for use in the link
		if ($this->_get_name($parent) === 'a' && !($parent->getAttribute('data-lightboximage') || $parent->getAttribute('data-lightboxmessage')))
		{
			return !empty($alt) ? $alt : (!empty($title) ? $title : 'xXx');
		}

		if (!empty($title))
		{
			$markdown = '![' . $alt . '](' . $src . ' "' . $title . '")';
		}
		else
		{
			$markdown = '![' . $alt . '](' . $src . ')';
		}

		$this->_check_line_length($markdown, $this->get_buffer($node));

		return $markdown . $this->line_end;
	}

	/**
	 * Converts ordered <ol> and unordered <ul> lists to markdown syntax
	 *
	 * html: <ul><li>one</li></ul>
	 * md * one
	 *
	 * @param object $node
	 * @return string
	 */
	private function _convert_list($node)
	{
		$list_type = $this->_parser ? $node->parentNode->nodeName : $node->parentNode()->nodeName();
		$value = $this->_get_value($node);
		$depth = $this->_has_parent_list($node, $this->_parser);

		$loose = $value[0] === $this->line_end ? $this->line_end : '';

		// Keep multi line list items indented the same as the list depth
		$indent = str_repeat('   ', $depth);
		$value = rtrim(implode($this->line_end . $indent, explode($this->line_end, trim($value))));

		// Unordered lists get a simple bullet
		if ($list_type === 'ul')
		{
			return $loose . $this->config['bullet'] . '   ' . $value . $this->line_end;
		}

		// Ordered lists need a number
		$start = (int) ($this->_parser ? $node->parentNode->getAttribute('start') : $node->parentNode()->getAttribute('start'));
		$start = $start > 0 ? $start - 1 : 0;
		$number = $start + $this->_get_list_position($node);

		return $loose . $number . '. ' . $value . $this->line_end;
	}

	/**
	 * Generally returns the innerHTML
	 *
	 * @param object $node
	 * @return string
	 */
	private function _convert_span($node)
	{
		return $this->getInnerHTML($node);
	}

	/**
	 * Converts tables tags to markdown extra table syntax
	 *
	 * - Have to build top down vs normal inside out due to needing col numbers and widths
	 *
	 * @param object $node
	 * @return string
	 */
	private function _convert_table($node)
	{
		$table_heading = $node->getElementsByTagName('th');
		if ($this->_get_item($table_heading, 0) === null)
		{
			return '';
		}

		$th_parent = $this->_parser ? $this->_get_item($table_heading, 0)->parentNode->nodeName : $this->_get_item($table_heading, 0)->parentNode()->nodeName();

		// Set up for a markdown table, then storm the castle
		$align = array();
		$value = array();
		$width = array();
		$max = array();
		$header = array();
		$rows = array();

		// We only markdown well formed tables ...
		if ($table_heading && $th_parent === 'tr')
		{
			// Find out how many columns we are dealing with
			$th_num = $this->_get_length($table_heading);

			for ($col = 0; $col < $th_num; $col++)
			{
				// Get the align and text for each th (html5 this is no longer valid)
				$th = $this->_get_item($table_heading, $col);
				$align_value = ($th !== null) ? strtolower($th->getAttribute('align')) : false;
				$align[0][$col] = $align_value === false ? 'left' : $align_value;
				$value[0][$col] = $this->_get_value($th);
				$width[0][$col] = Util::strlen($this->_get_value($th));

				// Seed the max col width
				$max[$col] = $width[0][$col];
			}

			// Get all of the rows
			$table_rows = $node->getElementsByTagName('tr');
			$num_rows = $this->_get_length($table_rows);
			for ($row = 1; $row < $num_rows; $row++)
			{
				// Start at row 1 and get all of the td's in this row
				$row_data = $this->_get_item($table_rows, $row)->getElementsByTagName('td');

				// Simply use the th count as the number of columns, if its not right its not markdown-able anyway
				for ($col = 0; $col < $th_num; $col++)
				{
					// Get the align and text for each td in this row
					$td = $this->_get_item($row_data, $col);
					$align_value = ($td !== null) ? strtolower($td->getAttribute('align')) : false;
					$align[$row][$col] = $align_value === false ? 'left' : $align_value;
					$value[$row][$col] = $this->_get_value($td);
					$width[$row][$col] = Util::strlen($this->_get_value($td));

					// Keep track of the longest col cell as we go
					if ($width[$row][$col] > $max[$col])
					{
						$max[$col] = $width[$row][$col];
					}
				}
			}

			// Done collecting data, we can rebuild it, we can make it better than it was. Better...stronger...faster
			for ($row = 0; $row < $num_rows; $row++)
			{
				$temp = array();
				for ($col = 0; $col < $th_num; $col++)
				{
					// Build the header row once
					if ($row === 0)
					{
						$header[] = str_repeat('-', $max[$col]);
					}

					// Build the data for each col, align/pad as needed
					$temp[] = $this->_align_row_content($align[$row][$col], $width[$row][$col], $value[$row][$col], $max[$col]);
				}

				// Join it all up so we have a nice looking row
				$rows[] = '| ' . implode(' | ', $temp) . ' |';

				// Stuff in the header after the th row
				if ($row === 0)
				{
					$rows[] = '| ' . implode(' | ', $header) . ' | ';
				}
			}

			// Adjust the word wrapping since this has a table, will get mussed by email anyway
			$this->_check_line_length($rows[1], 2);

			// Return what we did so it can be swapped in
			return implode($this->line_end, $rows);
		}
	}

	/**
	 * Helper function for getting a node object
	 *
	 * @param object $node
	 * @param int $item
	 * @return object
	 */
	private function _get_item($node, $item)
	{
		if ($this->_parser)
		{
			return $node->item($item);
		}
		else
		{
			return $node[$item];
		}
	}

	/**
	 * Helper function for getting a node length
	 *
	 * @param object|array $node
	 * @return int
	 */
	private function _get_length($node)
	{
		if ($this->_parser)
		{
			return $node->length;
		}
		else
		{
			return count($node);
		}
	}

	/**
	 * Helper function for getting a node value
	 *
	 * @param object $node
	 * @return string
	 */
	private function _get_value($node)
	{
		if ($node === null)
		{
			return '';
		}

		if ($this->_parser)
		{
			return $node->nodeValue;
		}
		else
		{
			return html_entity_decode(htmlspecialchars_decode($node->innertext, ENT_QUOTES), ENT_QUOTES, 'UTF-8');
		}
	}

	/**
	 * Helper function for getting a node name
	 *
	 * @param object $node
	 * @return string
	 */
	private function _get_name($node)
	{
		if ($node === null)
		{
			return '';
		}

		if ($this->_parser)
		{
			return $node->nodeName;
		}
		else
		{
			return $node->nodeName();
		}
	}

	/**
	 * Helper function for creating ol's
	 *
	 * - Returns the absolute number of an <li> inside an <ol>
	 *
	 * @param object $node
	 * @return int
	 */
	private function _get_list_position($node)
	{
		$position = 1;

		// Get all of the list nodes inside this parent
		$list_node = $this->_parser ? $node->parentNode : $node->parentNode();
		$total_nodes = $this->_parser ? $node->parentNode->childNodes->length : count($list_node->childNodes());

		// Loop through all li nodes and find where we are in this list
		for ($i = 0; $i < $total_nodes; $i++)
		{
			$current_node = $this->_parser ? $list_node->childNodes->item($i) : $list_node->childNodes($i);
			if ($current_node === $node)
			{
				$position = $i + 1;
				break;
			}
		}

		return $position;
	}

	/**
	 * Helper function for table creation
	 *
	 * - Builds td's to a give width, aligned as needed
	 *
	 * @param string $align
	 * @param int $width
	 * @param string $content
	 * @param int $max
	 * @return string
	 */
	private function _align_row_content($align, $width, $content, $max)
	{
		switch ($align)
		{
			default:
			case 'left':
				$content .= str_repeat(' ', $max - $width);
				break;
			case 'right':
				$content = str_repeat(' ', $max - $width) . $content;
				break;
			case 'center':
				$paddingNeeded = $max - $width;
				$left = (int) floor($paddingNeeded / 2);
				$right = $paddingNeeded - $left;
				$content = str_repeat(' ', $left) . $content . str_repeat(' ', $right);
				break;
		}

		return $content;
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
			$doc->preserveWhiteSpace = true;
			$doc->appendChild($doc->importNode($node, true));
			$html = trim($doc->saveHTML());
			$tag = $node->nodeName;

			return preg_replace('@^<' . $tag . '[^>]*>|</' . $tag . '>$@', '', $html);
		}
		else
		{
			return $node->innertext;
		}
	}

	/**
	 * Gets the outer html of a node
	 *
	 * @param DOMNode|object $node
	 * @return string
	 */
	private function _get_outerHTML($node)
	{
		if ($this->_parser)
		{
			if (version_compare(PHP_VERSION, '5.3.6') >= 0)
			{
				return htmlspecialchars_decode($this->doc->saveHTML($node));
			}
			else
			{
				// @todo remove when 5.3.6 min
				$doc = new DOMDocument();
				$doc->appendChild($doc->importNode($node, true));
				$html = $doc->saveHTML();

				// We just want the html of the inserted node, it *may* be wrapped
				$html = $this->_returnBodyText($html);

				// Clean it up
				$html = rtrim($html, "\n");

				return html_entity_decode(htmlspecialchars_decode($html, ENT_QUOTES), ENT_QUOTES, 'UTF-8');
			}
		}
		else
		{
			return $node->outertext;
		}
	}

	/**
	 * Escapes markup looking text in html to prevent accidental assignment
	 *
	 * <p>*stuff*</p> should not convert to *stuff* but \*stuff\* since its not to
	 * be converted by md to html as <strong>stuff</strong>
	 *
	 * @param string $value
	 * @return string
	 */
	private function _escape_text($value)
	{
		// Search and replace ...
		foreach ($this->_textEscapeRegex as $regex => $replacement)
		{
			$value = preg_replace($regex, $replacement, $value);
		}

		return $value;
	}

	/**
	 * If inline code contains backticks ` as part of its content, we need to wrap them so
	 * when markdown is run we don't interpret the ` as additional code blocks
	 *
	 * @param string $value
	 * @return string
	 */
	private function _has_ticks($value)
	{
		$ticks = '';

		// If we have backticks in code, then we back tick the ticks
		// e.g. <code>`bla`</code> will become `` `bla` `` so markdown will deal with it properly
		preg_match_all('~`+~', $value, $matches);
		if (!empty($matches[0]))
		{
			// Yup ticks in the hair
			$ticks = '`';
			rsort($matches[0]);

			// Backtick as many as needed so markdown will work
			while (true)
			{
				if (!in_array($ticks, $matches[0]))
				{
					break;
				}
				$ticks .= '`';
			}
		}

		return $ticks;
	}

	/**
	 * Helper function to adjust wrapping width for long-ish links
	 *
	 * @param string $markdown
	 * @param bool|int $buffer
	 */
	private function _check_line_length($markdown, $buffer = false)
	{
		// Off we do nothing
		if ($this->body_width === 0)
		{
			return;
		}

		// Some Lines can be very long and if we wrap them they break
		$lines = explode($this->line_end, $markdown);
		foreach ($lines as $line)
		{
			$line_strlen = Util::strlen($line) + (!empty($buffer) ? (int) $buffer : 0);
			if ($line_strlen > $this->body_width)
			{
				$this->body_width = $line_strlen;
			}
		}
	}

	/**
	 * Helper function to find and wrap plain text links in MD format
	 */
	private function _convert_plaintxt_links($text, $node)
	{
		if (in_array($this->_get_name($this->_parser ? $node->parentNode : $node->parentNode()), array('a', 'code', 'pre')))
		{
			return $text;
		}

		// Any evidence of a code block we skip
		if (preg_match('~`.*`~s', $text) === 1)
		{
			return $text;
		}

		// Link finding regex that will skip our markdown [link](xx) constructs
		$re = '/((?<!\\\\\( |]\()https?:\/\/|(?<!\\\\\( |]\(|:\/\/)www)[-\p{L}0-9+&@#\/%?=~_|!:,.;]*[\p{L}0-9+&@#\/%=~_|]/ui';
		$count = 0;
		$text = preg_replace_callback($re,
			function ($matches) {
				return $this->_plaintxt_callback($matches);
			}, $text, -1, $count);

		// If we made changes, lets protect that link from wrapping
		if ($count > 0)
		{
			$this->_check_line_length($text);
		}

		return $text;
	}

	/**
	 * Callback function used by _convert_plaintxt_links for plain link to MD
	 *
	 * @param string[] $matches
	 * @return string
	 */
	private function _plaintxt_callback($matches)
	{
		global $txt;

		return '[' . $txt['link'] . '](' . trim(str_replace('\_', '_', $matches[0])) . ')';
	}

	/**
	 * Breaks a string up so its no more than width characters long
	 *
	 * - Will break at word boundaries
	 * - If no natural space is found will break mid-word
	 *
	 * @param string $string
	 * @param int $width
	 * @param string $break
	 * @return string
	 */
	private function _utf8_wordwrap($string, $width = 76, $break = "\n")
	{
		if ($width < 76)
		{
			return $string;
		}

		$strings = explode($break, $string);
		$lines = array();

		foreach ($strings as $string)
		{
			$in_quote = isset($string[0]) && $string[0] === '>';
			if (empty($string))
			{
				$lines[] = '';
			}
			while (!empty($string))
			{
				// Get the next #width characters before a break (space, punctuation tab etc)
				if (preg_match('~^(.{1,' . $width . '})(?:\s|$|,|\.)~u', $string, $matches))
				{
					// Add the #width to the output and set up for the next pass
					$lines[] = ($in_quote && $matches[1][0] !== '>' ? '> ' : '') . $matches[1];
					$string = Util::substr($string, Util::strlen($matches[1]));
				}
				// Humm just a long word with no place to break, so we simply cut it after width characters
				else
				{
					$lines[] = ($in_quote && $string[0] !== '>' ? '> ' : '') . Util::substr($string, 0, $width);
					$string = Util::substr($string, $width);
				}
			}
		}

		// Join it all the shortened sections up on our break characters
		return implode($break, $lines);
	}

	/**
	 * Gets the length of html in front of a given node and its parent.
	 *
	 * - Used to add needed buffer to adjust length wrapping
	 *
	 * @param $node
	 * @return int
	 */
	private function get_buffer($node)
	{
		$cut = $this->_get_outerHTML($node);

		$parent = $this->_parser ? $node->parentNode : $node->parentNode();

		if ($this->_get_name($parent) !== 'body')
		{
			$string = $this->_get_innerHTML($parent);
			$string = substr($string, 0, strpos($string, $cut));
		}

		return empty($string) ? 0 : Util::strlen($string);
	}
}
