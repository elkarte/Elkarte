<?php

/**
 * Converts HTML to Markdown text
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\Converters;

use ElkArte\Helper\Util;

/**
 * Converts HTML to Markdown text, some constructs based on
 * https://github.com/Elephant418/Markdownify
 */
class Html2Md extends AbstractDomParser
{
	/** @var bool Strip remaining tags, set too false to leave them in */
	public $strip_tags = true;

	/** @var string[] various settings on how to render certain Markdown tags */
	public $config = ['heading' => 'atx', 'bullet' => '*', 'em' => '_', 'strong' => '**'];

	/** @var string The Markdown equivalent to the HTML string */
	public $markdown;

	/**
	 * Prepares the passed string and sets the parser to use
	 *
	 * @param string $html string of HTML to convert to MD text
	 */
	public function __construct($html)
	{
		// Up front, remove whitespace between HTML tags
		$html = preg_replace('/(?:(?<=>)|(?<=\/>))(\s+)(?=<\/?)/', '', $html);

		// Replace invisible (except \n \t) characters with a space
		$html = preg_replace('~[^\S\n\t]~u', ' ', $html);

		// The XML parser will not deal gracefully with these, so protect them
		$html = strtr($html, [
			'?<' => '|?|&lt',
			'?>' => '|?|&gt',
			'>?' => '&gt|?|',
			'<?' => '&lt|?|'
		]);

		// Set a parser, then load the HTML
		$this->setParser();
		$this->loadHTML($html);
	}

	/**
	 * Reads the HTML body and sends it to the parsing loop to convert all
	 * DOM nodes to markup
	 */
	public function get_markdown(): string
	{
		// For this HTML node, find all child elements and convert
		$this->convertChildNodes($this->getDOMBodyNode());

		// Done replacing HTML elements, now get the converted DOM tree back into a string
		$this->markdown = $this->getHTML();

		// Clean up any excess spacing etc.
		$this->cleanMarkdown();

		// Wordwrap?
		if (!empty($this->body_width))
		{
			$this->markdown = $this->utf8Wordwrap($this->markdown, $this->body_width, $this->line_end);
		}

		return $this->markdown . "\n";
	}

	/**
	 * Normalize any spacing and excess blank lines that may have been generated
	 */
	public function cleanMarkdown(): void
	{
		// We only want the content, no wrappers
		$this->markdown = $this->getBodyText($this->markdown);

		// Remove any "bonus" tags
		if ($this->strip_tags)
		{
			$this->markdown = strip_tags($this->markdown);
		}

		// Replace content that we "hide" from the XML parsers
		$this->markdown = strtr($this->markdown, [
			'|?|&gt' => '?>',
			'|?|&lt' => '?<',
			'&lt|?|' => '<?',
			'&gt|?|' => '>?'
		]);

		// We may have hidden content ending in ?<br /> due to the above
		$this->markdown = str_replace('<br />', "\n\n", $this->markdown);

		// Strip the chaff and any excess blank lines we may have produced
		$this->markdown = trim($this->markdown);
		$this->markdown = preg_replace("~(?:\s?\n\s?){3,6}~", "\n\n", $this->markdown);
	}

	/**
	 * Traverse each node to its base, then convert tags to markup on the way back out
	 *
	 * @param object $node
	 */
	public function convertChildNodes($node): void
	{
		if (self::hasParentCode($node, $this->internalParser) && $this->getName($node) !== 'code')
		{
			return;
		}

		// Keep traversing till we are at the base of this node
		if ($node->hasChildNodes())
		{
			$num = $this->getLength($this->getChildren($node));
			for ($i = 0; $i < $num; $i++)
			{
				$child = $this->getChild($node, $i);
				$this->convertChildNodes($child);
			}
		}

		// At the root of this node, convert it to Markdown
		$this->convertToMarkdown($node);
	}

	/**
	 * Convert the supplied node into its Markdown equivalent
	 *
	 *  - Supports *some* Markdown extra tags, namely: table, abbr & dl in a limited fashion
	 *
	 * @param object $node
	 */
	public function convertToMarkdown($node): void
	{
		// HTML tag we are dealing with
		$tag = $this->getName($node);

		// Based on the tag, determine how to convert
		switch ($tag)
		{
			case 'a':
				$markdown = $this->_convertAnchor($node);
				break;
			case 'abbr':
				$markdown = $this->_convertAbbr($node);
				break;
			case 'b':
			case 'strong':
				$markdown = $this->config['strong'] . trim($this->getValue($node)) . $this->config['strong'];
				break;
			case 'blockquote':
				$markdown = $this->_convertBlockquote($node);
				break;
			case 'br':
				$markdown = $this->line_break;
				break;
			case 'center':
				$markdown = $this->line_end . $this->getValue($node) . $this->line_end;
				break;
			case 'cite':
				$markdown = $this->_convertCite($node);
				break;
			case 'code':
				$markdown = $this->_convertCode($node);
				break;
			case 'dt':
				$markdown = str_replace(["\n", "\r", "\n\r"], '', $this->getValue($node)) . $this->line_end;
				break;
			case 'dd':
				$markdown = ':   ' . $this->getValue($node) . $this->line_break;
				break;
			case 'dl':
				$markdown = trim($this->getValue($node)) . $this->line_break;
				break;
			case 'em':
			case 'i':
				$markdown = $this->config['em'] . trim($this->getValue($node)) . $this->config['em'];
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
				$markdown = $this->_convertHeader($tag, $this->getValue($node));
				break;
			case 'img':
				$markdown = $this->_convertImage($node);
				break;
			case 'ol':
			case 'ul':
				if ($this->hasParentList($node) !== 0)
				{
					$markdown = trim($this->getValue($node));
				}
				else
				{
					$markdown = $this->line_end . $this->getValue($node) . $this->line_end;
				}

				break;
			case 'li':
				$markdown = $this->_convertList($node);
				break;
			case 'p':
				$markdown = $this->line_end . rtrim($this->getValue($node)) . $this->line_end;
				$markdown = $this->_convertPlaintxtLinks($markdown, $node);
				$markdown = $this->utf8Wordwrap($markdown, $this->body_width, $this->line_end);
				break;
			case 'pre':
				$markdown = $this->getInnerHTML($node) . $this->line_break;
				break;
			case 'div':
				$markdown = $this->line_end . rtrim($this->getValue($node));
				$markdown = $this->utf8Wordwrap($markdown, $this->body_width, $this->line_end) . $this->line_break;
				break;
			case '#text':
				$markdown = $this->_escapeText($this->getValue($node));
				$markdown = $this->_convertPlaintxtLinks($markdown, $node);
				break;
			case 'title':
				$markdown = '# ' . $this->getValue($node) . $this->line_break;
				break;
			case 'table':
				$markdown = $this->_convertTable($node) . $this->line_break;
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
				$markdown = $this->_convertSpan($node);
				break;
			case 'root':
			case 'body':
				// Remove these tags and simply replace with the text inside the tags
				$markdown = $this->getInnerHTML($node);
				break;
			default:
				// Don't know you or text or structural other, so just preserve
				$markdown = $this->getOuterHTML($node) . $this->line_end;
		}

		// Replace the node with our Markdown replacement, or with the node itself if none was found
		if ($markdown !== '~`skip`~')
		{
			$this->setTextNode($node, $markdown);
		}
	}

	/**
	 * Converts <abbr> tags to Markdown (extra)
	 *
	 * HTML: <abbr title="Hyper Text Markup Language">HTML</abbr>
	 * MD: *[HTML]: Hyper Text Markup Language
	 *
	 * @param object $node
	 * @return string
	 */
	private function _convertAbbr($node): string
	{
		$title = $node->getAttribute('title');
		$value = $this->getValue($node);

		return empty($title) ? '' : '*[' . $value . ']: ' . $title;
	}

	/**
	 * Converts <a> tags to Markdown
	 *
	 * HTML: <a href='http://somesite.com' title='Title'>Awesome Site</a>
	 * MD: [Awesome Site](http://somesite.com 'Title')
	 *
	 * @param object $node
	 * @return string
	 */
	private function _convertAnchor($node): string
	{
		global $txt;

		// Ignore lightbox images
		if ($node->getAttribute('data-lightboximage') || $node->getAttribute('data-lightboxmessage'))
		{
			return '~`skip`~';
		}

		// Links with _ would have been escaped ('_' => '\_'), undo that now
		$href = str_replace('\_', '_', htmlspecialchars_decode($node->getAttribute('href')));
		$title = $node->getAttribute('title');
		$class = $node->getAttribute('class');
		$value = str_replace('\_', '_', trim($this->getValue($node), "\t\n\r\0\x0B"));

		// Provide a more compact [name] if none is given
		if (empty($value) || $value === $node->getAttribute('href'))
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
			$markdown = '[' . ($value === $txt['link'] ? 'X' : $value) . '](' . $href . ' "' . $txt['link'] . '")';
		}

		$this->_setBodyWidth($markdown, $this->getBuffer($node));

		return $markdown . $this->line_end;
	}

	/**
	 * Converts blockquotes to Markdown > quote style
	 *
	 * HTML: <blockquote>quote</blockquote>
	 * MD: > quote
	 *
	 * @param object $node
	 * @return string
	 */
	private function _convertBlockquote($node): string
	{
		$markdown = '';

		// All the contents of this block quote
		$value = trim($this->getValue($node));

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
	 * Converts cites to Markdown with the assumption that they are in a blockquote
	 *
	 * HTML: <blockquote>quote</blockquote>
	 * MD: > quote
	 *
	 * @param object $node
	 * @return string
	 */
	private function _convertCite($node): string
	{
		// All the contents of this cite
		$markdown = trim($this->getValue($node));

		// Drop the link, just use the citation [bla](link)
		if (preg_match('~\[(.*?)\]\(.*?\)~', $markdown, $match))
		{
			$markdown = $match[1];
		}

		return $this->line_end . $markdown . $this->line_end;
	}

	/**
	 * Converts code tags to Markdown span `code` or block fenced code
	 * Converts single line code to inline tick mark
	 * Converts multi line to ``` fenced ``` code
	 *
	 * HTML: <code>code</code>
	 * MD: `code`
	 *
	 * @param object $node
	 * @return string
	 */
	private function _convertCode($node): string
	{
		// Get the code block
		$value = $this->getInnerHTML($node);

		// Empty Block
		if (empty($value))
		{
			return '` `';
		}

		// Turn off things that may mangle code tags
		$this->strip_tags = false;
		$this->body_width = 0;

		// If we have a multi-line code block, we are working outside to in, and need to convert the br's ourselves
		$value = preg_replace('~<br( /)?>~', $this->line_end, str_replace('&nbsp;', ' ', $value));

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
		$ticks = $this->_hasBackticks($value);
		if (!empty($ticks))
		{
			// If the ticks were at the start/end of the word space it off
			if ($lines[0][0] === '`' || str_ends_with($lines[0], '`'))
			{
				$lines[0] = ' ' . $lines[0] . ' ';
			}

			return $ticks . $lines[0] . $ticks;
		}

		return '`' . $lines[0] . '`';
	}

	/**
	 * Converts <h1> ... <hx> headers to Markdown-style headers in setext or atx style.
	 *
	 * HTML: <h1>header</h1>
	 * MD: header
	 *     ======
	 *
	 * HTML: <h3>header</h3>
	 * MD: ### header
	 *
	 * @param string $level
	 * @param string $content
	 * @return string
	 */
	private function _convertHeader($level, $content): string
	{
		if ($this->config['heading'] === 'setext')
		{
			$length = Util::strlen($content);

			return $this->line_end . $content . $this->line_end . str_repeat('=', $length) . $this->line_break;
		}

		if (!preg_match('/^h([1-5])$/i', (string) $level, $m))
		{
			$levelInt = 3;
		}
		else
		{
			$levelInt = (int) $m[1];
		}

		return $this->line_end . str_repeat('#', $levelInt) . ' ' . $content . $this->line_break;
	}

	/**
	 * Converts <img> tags to Markdown
	 *
	 * HTML: <img src='source' alt='alt' title='title' />
	 * MD: ![alt](source 'title')
	 *
	 * @param object $node
	 * @return string
	 */
	private function _convertImage($node): string
	{
		$src = preg_replace('~;thumb$~', '', $node->getAttribute('src'));
		$alt = $node->getAttribute('alt');
		$title = $node->getAttribute('title');
		$smile = $node->getAttribute('data-emoji-name');
		$parent = $this->getParent($node);

		// Emoji, return just the :cool: part
		if (!empty($smile))
		{
			return ' ' . $smile . ' ';
		}

		// A plain linked image, just return the alt text for use in the link
		if ($this->getName($parent) === 'a' && (!$parent->getAttribute('data-lightboximage') && !$parent->getAttribute('data-lightboxmessage')))
		{
			return empty($alt) ? (!empty($title) ? $title : 'xXx') : ($alt);
		}

		$markdown = empty($title)
			? '![' . $alt . '](' . $src . ')'
			: '![' . $alt . '](' . $src . ' "' . $title . '")';

		$this->_setBodyWidth($markdown, $this->getBuffer($node));

		return $markdown . $this->line_end;
	}

	/**
	 * Converts ordered <li> and unordered <li> items to Markdown syntax
	 *
	 * HTML: <ul><li>one</li></ul>
	 * md * one
	 *
	 * @param object $node
	 * @return string
	 */
	private function _convertList($node): string
	{
		$list_type = $this->getName($this->getParent($node));
		$value = $this->getValue($node);
		$depth = $this->hasParentList($node);

		// Keep items that started with a newline for list spacing
		$loose = $value[0] === $this->line_end ? $this->line_end : '';

		// Keep multi-line list items indented the same as the list depth
		$indent = str_repeat('   ', $depth);
		$value = rtrim(implode($this->line_end . $indent, explode($this->line_end, trim($value))));

		// Unordered lists get a simple bullet
		if ($list_type === 'ul')
		{
			return $loose . $this->config['bullet'] . '   ' . $value . $this->line_end;
		}

		// Ordered lists will need an item number
		$start = (int) $this->getParent($node)->getAttribute('start');
		$start = $start > 0 ? $start - 1 : 0;

		$number = $start + $this->_getListPosition($node);

		return $loose . $number . '. ' . $value . $this->line_end;
	}

	/**
	 * Generally returns the innerHTML but will check for `icode` DNA
	 *
	 * @param object $node
	 * @return string
	 */
	private function _convertSpan($node): string
	{
		$class = $node->getAttribute('class');

		if ($class === 'bbc_code_inline')
		{
			$value = $this->getInnerHTML($node);
			$ticks = $this->_hasBackticks($value);
			if (!empty($ticks))
			{
				// If the ticks were at the start/end of the word space it off
				if ($value[0] === '`' || str_ends_with($value[0], '`'))
				{
					$value = ' ' . $value . ' ';
				}

				return $ticks . $value . $ticks;
			}

			return '`' . $value . '`';
		}

		return $this->getInnerHTML($node);
	}

	/**
	 * Converts tables tags to Markdown extra table syntax
	 *
	 * - Have to build top down vs. normal inside out due to needing col numbers and widths
	 *
	 * @param object $node
	 * @return string
	 */
	private function _convertTable($node): string
	{
		$table_heading = $node->getElementsByTagName('th');
		if ($this->getItem($table_heading, 0) === null)
		{
			return '';
		}

		$th_parent = $this->getName($this->getParent($this->getItem($table_heading, 0)));

		// Set up for a Markdown table, then storm the castle
		$align = [];
		$value = [];
		$width = [];
		$max = [];
		$header = [];
		$rows = [];

		// We only process well-formed tables ...
		if ($table_heading && $th_parent === 'tr')
		{
			// Find out how many columns we are dealing with
			$th_num = $this->getLength($table_heading);

			for ($col = 0; $col < $th_num; $col++)
			{
				// Get align and text for each th (HTML5 this is no longer valid)
				$th = $this->getItem($table_heading, $col);
				$align_value = ($th !== null) ? strtolower($th->getAttribute('align')) : false;
				$align[0][$col] = $align_value === false ? 'left' : $align_value;
				$value[0][$col] = $this->getValue($th);
				$width[0][$col] = Util::strlen($this->getValue($th));

				// Seed the max col width
				$max[$col] = $width[0][$col];
			}

			// Get all the rows
			$table_rows = $node->getElementsByTagName('tr');
			$num_rows = $this->getLength($table_rows);
			for ($row = 1; $row < $num_rows; $row++)
			{
				// Start at row 1 and get all the td's in this row
				$row_data = $this->getItem($table_rows, $row)->getElementsByTagName('td');

				// Simply use the th count as the number of columns, if it's not right it's not Markdown-able anyway
				for ($col = 0; $col < $th_num; $col++)
				{
					// Get align and text for each td in this row
					$td = $this->getItem($row_data, $col);
					$align_value = ($td !== null) ? strtolower($td->getAttribute('align')) : false;
					$align[$row][$col] = $align_value === false ? 'left' : $align_value;
					$value[$row][$col] = $this->getValue($td);
					$width[$row][$col] = Util::strlen($this->getValue($td));

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
				$temp = [];
				for ($col = 0; $col < $th_num; $col++)
				{
					// Build the header row just once
					if ($row === 0)
					{
						$header[] = str_repeat('-', $max[$col]);
					}

					// Build the data for each col, align/pad as needed
					$temp[] = $this->_alignRowContent($align[$row][$col], $width[$row][$col], $value[$row][$col], $max[$col]);
				}

				// Join it all up, so we have a nice looking row
				$rows[] = '| ' . implode(' | ', $temp) . ' |';

				// Stuff in the header after the th row
				if ($row === 0)
				{
					$rows[] = '| ' . implode(' | ', $header) . ' | ';
				}
			}

			// Adjust the word wrapping since this has a table, will get mussed by email anyway
			$this->_setBodyWidth($rows[1], 2);

			// Return what we did so it can be swapped in
			return implode($this->line_end, $rows);
		}

		return '';
	}

	/**
	 * Helper function for creating lists
	 *
	 * - Returns the absolute number of an <li> inside an <ol> or <ul>
	 *
	 * @param object $node
	 * @return int
	 */
	private function _getListPosition($node): int
	{
		$position = 1;

		// Get all the list nodes inside this parent
		$list_node = $this->getParent($node);
		$total_nodes = $this->internalParser ? $list_node->childNodes->length : count($list_node->childNodes());

		// Loop through all li nodes and find where we are in this list
		for ($i = 0; $i < $total_nodes; $i++)
		{
			$current_node = $this->internalParser ? $list_node->childNodes->item($i) : $list_node->childNodes($i);
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
	private function _alignRowContent($align, $width, $content, $max): string
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
	 * Escapes markup looking text in HTML to prevent accidental assignment
	 *
	 * <p>*stuff*</p> should not convert to *stuff* but \*stuff\* since it's not to
	 * be converted by md to HTML as <strong>stuff</strong>
	 *
	 * @param string $value
	 * @return string
	 */
	private function _escapeText($value): string
	{
		// Escape plain text areas, so it does not convert to Markdown
		$textEscapeRegex = [
			'~([*_\\[\\]\\\\])~' => '\\\\$1',
			'~^-~m' => '\\-',
			'~^\+ ~m' => '\\+ ',
			'~^(=+)~m' => '\\\\$1',
			'~^(#{1,6}) ~m' => '\\\\$1 ',
			'~`~' => '\\`',
			'~^>~m' => '\\>',
			'~^(\d+)\. ~m' => '$1\\. ',
		];

		// Search and replace ...
		foreach ($textEscapeRegex as $regex => $replacement)
		{
			$value = preg_replace($regex, $replacement, $value);
		}

		return $value;
	}

	/**
	 * If inline code contains backticks ` as part of its content, we need to wrap them, so
	 * when Markdown is run, we don't interpret the ` as additional code blocks
	 *
	 * @param string $value
	 * @return string
	 */
	private function _hasBackticks($value): string
	{
		$ticks = '';

		// If we have backticks in code, then we back tick the ticks
		// e.g. <code>`bla`</code> will become `` `bla` `` so Markdown will deal with it properly
		preg_match_all('~`+~', $value, $matches);
		if (!empty($matches[0]))
		{
			// Yup ticks in the hair
			$ticks = '`';
			rsort($matches[0]);

			// Backtick as many as needed so Markdown will work
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
	 * Helper function to adjust wrapping width for longish links
	 *
	 * @param string $markdown
	 * @param bool|int $buffer
	 */
	private function _setBodyWidth($markdown, $buffer = false): void
	{
		// Off we do nothing
		if ($this->body_width === 0)
		{
			return;
		}

		// Some Lines can be very long, and if we wrap them, they break urls etc.
		$lines = explode($this->line_end, $markdown);
		foreach ($lines as $line)
		{
			$line_strlen = Util::strlen($line) + (empty($buffer) ? 0 : (int) $buffer);
			if ($line_strlen > $this->body_width)
			{
				$this->body_width = $line_strlen;
			}
		}
	}

	/**
	 * Helper function to find and wrap plain text links in MD format
	 *
	 * @param string $text
	 * @param object $node
	 *
	 * @return string
	 */
	private function _convertPlaintxtLinks($text, $node): string
	{
		if (in_array($this->getName($this->getParent($node)), ['a', 'code', 'pre']))
		{
			return $text;
		}

		// Any evidence of a code block we skip
		if (preg_match('~`.*`~s', $text) === 1)
		{
			return $text;
		}

		// Link finding regex that will skip our Markdown [link](xx) constructs
		$re = '/((?<!\\\\\( |]\()https?:\/\/|(?<!\\\\\( |]\(|:\/\/)www)[-\p{L}0-9+&@#\/%?=~_|!:,.;]*[\p{L}0-9+&@#\/%=~_|]/ui';
		$count = 0;
		$text = preg_replace_callback($re,
			fn($matches) => $this->_plaintxtCallback($matches), $text, -1, $count);

		// If we made changes, let's protect that link from wrapping
		if ($count > 0)
		{
			$this->_setBodyWidth($text);
		}

		return $text;
	}

	/**
	 * Callback function used by _convertPlaintxtLinks for a plain link to MD
	 *
	 * @param string[] $matches
	 * @return string
	 */
	private function _plaintxtCallback($matches): string
	{
		global $txt;

		// Links may have been escaped, undo that now
		return '[' . $txt['link'] . '](' . trim(str_replace('\_', '_', $matches[0])) . ')';
	}

	/**
	 * Gets the length of HTML in front of a given node and its parent.
	 *
	 * - Used to add necessary buffer to adjust length wrapping
	 *
	 * @param $node
	 * @return int
	 */
	private function getBuffer($node): int
	{
		$cut = $this->getOuterHTML($node);
		$parent = $this->getParent($node);

		if ($this->getName($parent) !== 'body')
		{
			$string = $this->getInnerHTML($parent);
			$string = substr($string, 0, strpos($string, $cut));
		}

		return empty($string) ? 0 : Util::strlen($string);
	}
}
