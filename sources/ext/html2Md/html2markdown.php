<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Converts HTML to Markdown text
 */
class Convert_Md
{
	/**
	 * The value that will hold our dom object
	 */
	public $doc;

	/**
	 * The value that will hold if we are using the internal or external parser
	 */
	private $_parser;

	/**
	 * line end character
	 */
	public $line_end = "\n";

	/**
	 * line break character
	 */
	public $line_break = "\n\n";

	/**
	 * Gets everything started using the built in or external parser
	 * @param type $html
	 */
	public function __construct($html)
	{
		// Remove multiple newlines between html tags
		$html = preg_replace('~(</[a-zA-Z0-9]+>)(\s)+(<[a-zA-Z0-9]+(?: /)?>)~s', "$1$3", $html);

		// Use PHP built in functions ...
		if (class_exists('DOMDocument'))
		{
			$this->_parser = true;
			$this->doc = new DOMDocument();

			// Make it a utf-8 doc always and be silent about those html structure errors
			libxml_use_internal_errors(true);
			$this->doc->loadHTML('<?xml encoding="UTF-8">' . $html);
			$this->doc->encoding = 'UTF-8';
			libxml_clear_errors();
		}
		// Or use the simple html parser
		else
		{
			$this->_parser = false;
			require_once(EXTDIR . '/other/simple_html_dom.php');
			$this->doc = str_get_html($html, true, true, 'UTF-8', false);
		}
	}

	/**
	 * Loads the html body and sends it to the parsing loop to convert all nodes
	 *
	 * @return string
	 */
	public function get_markdown()
	{
		// For this html node, find all child elements and convert
		$body = ($this->_parser) ? $this->doc->getElementsByTagName("body")->item(0) : $this->doc->root;
		$this->_convert_childNodes($body);

		// Done replacing HTML elements, so we get the internal DOM tree back into a string
		$markdown = ($this->_parser) ? $this->doc->saveHTML() : $this->doc->save();

		if ($this->_parser)
		{
			// Using the internal method we need to do a little extra work
			$markdown =  html_entity_decode(htmlspecialchars_decode($markdown, ENT_QUOTES), ENT_QUOTES, 'UTF-8');
			if (preg_match('~<body>(.*)</body>~s', $markdown, $body))
				$markdown = $body[1];
		}

		return $markdown;
	}

	/**
	 * Don't convert code that's inside a code block
	 *
	 * @param type $node
	 * @return boolean
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

			// Back out another level
			$parent = $parser ? $parent->parentNode : $parent->parentNode();
		}
		return false;
	}

	/**
	 * Get the nesting level when inside a list
	 *
	 * @param type $node
	 * @return boolean
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
				$depth++;

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
		if (self::_has_parent_code($node, $this->_parser))
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

		// At the root of this node, convert it to markdown
		$this->_convert_to_markdown($node);
	}

	/**
	 * Convert the supplied node into its markdown equivalent
	 * Supports some markdown extra tags, namely: table, abbr & dl
	 *
	 * @param object $node
	 */
	private function _convert_to_markdown($node)
	{
		// html tag and contents
		$tag = $this->_parser ? $node->nodeName : $node->nodeName();
		$value =$this->_get_value($node);

		// based on the tag, determine how to convert
		switch ($tag)
		{
			case 'root':
				$markdown = '';
				break;
			case 'a':
				$markdown = $this->_convert_anchor($node);
				break;
			case 'abbr':
				$markdown = $this->_convert_abbr($node);
				break;
			case 'b':
			case 'strong':
				$markdown = '**' . $value . '**';
				break;
			case 'blockquote':
				$markdown = $this->_convert_blockquote($node);
				break;
			case 'br':
				$markdown = '  ' . $this->line_end;
				break;
			case 'code':
				$markdown = $this->_convert_code($node);
				break;
			case 'dt':
				$markdown = str_replace(array("\n", "\r", "\n\r"), '', $value) . $this->line_end;
				break;
			case 'dd':
				$markdown = ':   ' . $value . $this->line_break;
				break;
			case 'dl':
				$markdown = trim($value) . $this->line_end;
				break;
			case 'em':
			case 'i':
				$markdown = '*' . $value . '*';
				break;
			case 'hr':
				$markdown = str_repeat('- ', 20) . $this->line_break;
				break;
			case 'h1':
			case 'h2':
			case 'h3':
			case 'h4':
			case 'h5':
			case 'h6':
				$markdown = $this->_convert_header($tag, $value);
				break;
			case 'img':
				$markdown = $this->_convert_image($node);
				break;
			case 'ol':
			case 'ul':
				$markdown = rtrim($value) . $this->line_break;
				break;
			case 'li':
				$markdown = $this->_convert_list($node);
				break;
			case 'p':
			case 'pre':
				$markdown = $value . $this->line_break;
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
				$markdown = '`skip`';
				break;
			default:
				$markdown = $this->_parser ? $this->doc->saveHTML($node) : trim($node->outertext);
				$markdown = $tag !== '#text' ? trim(strip_tags($markdown)) : $markdown;
		}

		// Replace the node with our markdown replacement, or with the node itself if none was found
		if ($markdown !== '`skip`')
		{
			if ($this->_parser)
			{
				// Create a new text node with our markdown tag and replace the original node
				$markdown_node = $this->doc->createTextNode($markdown);
				$node->parentNode->replaceChild($markdown_node, $node);
			}
			else
				$node->outertext = $markdown;
		}
	}

	/**
	 * 	Converts <abbr> tags to markdown (extra)
	 *
	 * 	html: <abbr title="Hyper Text Markup Language">HTML</abbr>
	 * 	md:	*[HTML]: Hyper Text Markup Language
	 *
	 * @param type $node
	 */
	private function _convert_abbr($node)
	{
		$title = $node->getAttribute('title');
		$value = $this->_get_value($node);

		if (!empty($title))
			$markdown = '*[' . $value . ']: ' . $title . $this->line_break;
		else
			$markdown = '';

		return $markdown;
	}

	/**
	 * Converts <a> tags to markdown
	 *
	 * html: <a href='http://somesite.com' title='Title'>Awesome Site</a>
	 * md: [Awesome Site](http://somesite.com 'Title')
	 *
	 * @param object $node
	 */
	private function _convert_anchor($node)
	{
		$href = $node->getAttribute('href');
		$title = $node->getAttribute('title');
		$value = $this->_get_value($node);

		if (!empty($title))
			$markdown = '[' . $value . '](' . $href . ' "' . $title . '")';
		else
			$markdown = '[' . $value . '](' . $href . ')';

		return $markdown;
	}

	/**
	 * Converts blockquotes to markdown > quote style
	 * html: <blockquote>quote</blockquote>
	 * md: > quote
	 *
	 * @param object $node
	 */
	private function _convert_blockquote($node)
	{
		$markdown = '';

		// All the contents of this block quote
		$value = $this->_get_value($node);
		$value = trim($value);

		// Go line by line
		$lines = preg_split('~\r\n|\r|\n~', $value);

		// Each line gets a '> ' in front of it, just like email quotes really
		foreach ($lines as $line)
			$markdown .= '> ' . ltrim($line, "\t") . $this->line_end;

		return $markdown;
	}

	/**
	 * Converts code tags to markdown span `code` or block code
	 * Converts single line code to inline tick mark
	 * Converts multi line to indented code
	 *
	 * @param object $node
	 */
	private function _convert_code($node)
	{
		$markdown = '';
		$value = $this->_innerHTML($node);
		//$value = str_replace(array('<code>', '</code>'), '', $value);
		$value = trim($value);

		// Get the number of lines of code that we have
		$lines = preg_split('~\r\n|\r|\n~', $value);
		$total = count($lines);

		// If there's more than one line of code, use leading four space syntax
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
				$markdown .= str_repeat(' ', 4) . $line . $this->line_end;

			// The parser will encode, but we don't want that for our code block
			if ($this->_parser)
				$markdown = html_entity_decode($markdown, ENT_QUOTES, 'UTF-8');
		}
		// Single line, back tick and move on
		else
			$markdown .= '`' . ($this->_parser ? html_entity_decode($lines[0], ENT_QUOTES, 'UTF-8') : $lines[0]) . '`';

		return $markdown;
	}

	/**
	 * Converts <h1> and <h2> headers to markdown-style headers in setex style,
	 * all other headers are returned as atx style ### h3
	 *
	 * @param int $level
	 * @param string $content
	 */
	private function _convert_header($level, $content)
	{
		global $smcFunc;

		$level = (int) ltrim($level, 'h');

		if ($level < 3)
		{
			$length = $smcFunc['strlen']($content);
			$underline = ($level === 1) ? '=' : '-';
			$markdown = $content . $this->line_end . str_repeat($underline, $length) . $this->line_break;
		}
		else
			$markdown = str_repeat('#', $level) . ' ' . $content . $this->line_break;

		return $markdown;
	}

	/**
	 * 	Converts <img> tags to markdown
	 *
	 * 	html: <img src='source' alt='alt' title='title' />
	 * 	md: ![alt](source 'title')
	 *
	 * @param object $node
	 */
	private function _convert_image($node)
	{
		$src = $node->getAttribute('src');
		$alt = $node->getAttribute('alt');
		$title = $node->getAttribute('title');

		if (!empty($title))
			$markdown = '![' . $alt . '](' . $src . ' "' . $title . '")';
		else
			$markdown = '![' . $alt . '](' . $src . ')';

		return $markdown;
	}

	/**
	 * Converts ordered <ol> and unordered <ul> lists to markdown syntax
	 *
	 * html: <ul><li>one</li></ul>
	 * md * one
	 *
	 * @param object $node
	 */
	private function _convert_list($node)
	{
		$list_type = $this->_parser ? $node->parentNode->nodeName : $node->parentNode()->nodeName();
		$value = $this->_get_value($node);

		$loose = rtrim($value) !== $value;
		$depth = max(0, $this-> _has_parent_list($node, $this->_parser) - 1);

		// Unordered lists get a simple bullet
		if ($list_type === 'ul')
			$markdown = str_repeat("\t", $depth) . '* ' . $value;
		// Ordered lists need a number
		else
		{
			$number = $this->_get_list_position($node);
			$markdown = str_repeat("\t", $depth) . $number . '. ' . $value;
		}

		return $markdown . (!$loose ? $this->line_end : '');
	}

	/**
	 * Converts tables tags to markdown extra table syntax
	 * Have to build top down vs inside out due to needing col numbers and widths
	 *
	 * @param object $node
	 * @return string
	 */
	function _convert_table($node)
	{
		global $smcFunc;

		$table_heading = $node->getElementsByTagName('th');
		$th_parent = ($table_heading) ? ($this->_parser ? $this->_get_item($table_heading, 0)->parentNode->nodeName : $this->_get_item($table_heading, 0)->parentNode()->nodeName()) : false;

		// We only markdown well formed tables ...
		if ($table_heading && $th_parent === 'tr')
		{
			// find out how many columns we are dealing with
			$th_num = $this->_get_length($table_heading);
			for ($col = 0; $col < $th_num; $col++)
			{
				// Get the align and text for each th (html5 this is no longer valid)
				$th = $this->_get_item($table_heading, $col);
				$align_value = strtolower($th->getAttribute('align'));
				$align[0][$col] = $align_value === false ? 'left' : $align_value;
				$value[0][$col] = $this->_get_value($th);
				$width[0][$col] = $smcFunc['strlen']($this->_get_value($th));

				// Seed the max col width
				$max[$col] = $width[0][$col];
			}

			// Get all of the rows
			$table_rows = $node->getElementsByTagName('tr');
			$num_rows =$this->_get_length($table_rows);
			for ($row = 1; $row < $num_rows; $row++)
			{
				// Start at row 1 and get all of the td's in this row
				$row_data = $this->_get_item($table_rows, $row)->getElementsByTagName('td');

				// Simply use the th count as the number of columns, if its not right its not markdown-able anyway
				for ($col = 0; $col < $th_num; $col++)
				{
					// Get the align and text for each td in this row
					$td = $this->_get_item($row_data, $col);
					$align_value = strtolower($td->getAttribute('align'));
					$align[$row][$col] = $align_value === false ? 'left' : $align_value;
					$value[$row][$col] = $this->_get_value($td);
					$width[$row][$col] = $smcFunc['strlen']($this->_get_value($td));

					// Keep track of the longest col cell as we go
					if ($width[$row][$col] > $max[$col])
						$max[$col] = $width[$row][$col];
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
						$header[] = str_repeat('-', $max[$col]);

					// Build the data for each col, align/pad as needed
					$temp[] = $this->_align_row_content($align[$row][$col], $width[$row][$col], $value[$row][$col], $max[$col]);
				}

				// Join it all up so we have a nice looking row
				$rows[] = '| ' . implode(' | ', $temp) . ' |';

				// Stuff in the header after the th row
				if ($row === 0)
					$rows[] = '| ' . implode(' | ', $header) . ' | ';
			}

			// Return what we did so it can be swapped in
			return implode($this->line_end, $rows);
		}
	}

	/**
	 * Helper function for getting a node object
	 *
	 * @param object $node
	 * @param int $item
	 */
	private function _get_item($node, $item)
	{
		if ($this->_parser)
			return $node->item($item);
		else
			return $node[$item];
	}

	/**
	 * Helper function for getting a node length
	 *
	 * @param object $node
	 */
	private function _get_length($node)
	{
		if ($this->_parser)
			return $node->length;
		else
			return count($node);
	}

	/**
	 * Helper function for getting a node value
	 *
	 * @param object $node
	 */
	private function _get_value($node)
	{
		if ($this->_parser)
			return $node->nodeValue;
		else
			return $node->innertext;
	}

	/**
	 * Helper function for creating ol's
	 * Returns the absolute number of an <li> inside an <ol>
	 *
	 * @param object $node
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
				$position = $i + 1;
		}

		return $position;
	}

	/**
	 * Helper function for table creation, builds td's to a give width, aligned as needed
	 *
	 * @param string $align
	 * @param int $width
	 * @param string $content
	 * @param int $max
	 */
	private	function _align_row_content($align, $width, $content, $max)
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
				$left = floor($paddingNeeded / 2);
				$right = $paddingNeeded - $left;
				$content = str_repeat(' ', $left) . $content . str_repeat(' ', $right);
				break;
		}
		return $content;
	}

	/**
	 * Gets the inner html of a node
	 *
	 * @param object $node
	 * @return string
	 */
	private function _innerHTML($node)
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
}