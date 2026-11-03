<?php

namespace ElkArte\BBC;

use BBC\MarkdownParser;
use tests\ElkArteCommonSetupTest;

class MarkdownParserTest extends ElkArteCommonSetupTest
{
	protected $parseTestCases;
	protected $inlineCodeTestCases;
	protected $calloutTestCases;
	protected $backupGlobalsExcludeList = ['user_info'];

	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	protected function setUp(): void
	{
		parent::setUp();

		// Test cases for the parse() method
		$this->parseTestCases = array(
			array(
				'Bold double asterisk',
				'**bold**',
				'[b]bold[/b]',
			),
			array(
				'Bold double underscore',
				'__bold__',
				'[b]bold[/b]',
			),
			array(
				'Italic single asterisk',
				'*italic*',
				'[i]italic[/i]',
			),
			array(
				'Italic single underscore',
				'_italic_',
				'[i]italic[/i]',
			),
			array(
				'Strikethrough double tilde',
				'~~strike~~',
				'[s]strike[/s]',
			),
			array(
				'Horizontal rule dashes',
				'---<br />after',
				'[hr]after',
			),
			array(
				'Horizontal rule underscores',
				'___<br />after',
				'[hr]after',
			),
			array(
				'Horizontal rule asterisks',
				'***<br />after',
				'[hr]after',
			),
			array(
				'Blockquote with > marker',
				'before<br />>quoted',
				'before[quote]quoted[/quote]',
			),
			array(
				'Blockquote with &gt; entity',
				'before<br />&gt;quoted',
				'before[quote]quoted[/quote]',
			),
			array(
				'ATX heading level 1',
				'# Heading 1',
				'[size=7][b]Heading 1[/b][/size]',
			),
			array(
				'ATX heading level 2',
				'## Heading 2',
				'[size=6][b]Heading 2[/b][/size]',
			),
			array(
				'ATX heading level 3',
				'### Heading 3',
				'[size=5][b]Heading 3[/b][/size]',
			),
			array(
				'ATX heading level 4',
				'#### Heading 4',
				'[size=4][b]Heading 4[/b][/size]',
			),
			array(
				'ATX heading level 5',
				'##### Heading 5',
				'[size=3][b]Heading 5[/b][/size]',
			),
			array(
				'ATX heading level 6',
				'###### Heading 6',
				'[size=2][b]Heading 6[/b][/size]',
			),
			array(
				'ATX heading level 7',
				'####### Heading 7',
				'[size=1][b]Heading 7[/b][/size]',
			),
			array(
				'ATX heading level 8 clamps to size 1',
				'######## Heading 8',
				'[size=1][b]Heading 8[/b][/size]',
			),
			array(
				'ATX heading with inline formatting',
				'## Heading with *italic* and ~~strike~~',
				'[size=6][b]Heading with [i]italic[/i] and [s]strike[/s][/b][/size]',
			),
			array(
				'ATX heading requires text',
				'#<br />next',
				'#<br />next',
			),
			array(
				'ATX heading must be at line start',
				'prefix # not a heading',
				'prefix # not a heading',
			),
			array(
				'ATX heading is single line only',
				'## Heading<br />continuation',
				'[size=6][b]Heading[/b][/size]<br />continuation',
			),
		);

		// Test cases for inlineCodeTags()
		$this->inlineCodeTestCases = array(
			array(
				'Inline code backtick',
				'`code`',
				'[icode]code[/icode]',
			),
			array(
				'Inline code with BBC brackets escaped',
				'`[b]text[/b]`',
				'[icode]&#91;b&#93;text&#91;/b&#93;[/icode]',
			),
			array(
				'Fenced code block',
				'```<br />code here<br />```',
				'[code]code here[/code]',
			),
		);

		// Test cases for calloutTags()
		$this->calloutTestCases = array(
			array(
				'Callout box note',
				'> [!NOTE]<br />> note content<br />',
				'[quote box=note]note content[/quote]',
			),
			array(
				'Callout box tip',
				'> [!TIP]<br />> tip content<br />',
				'[quote box=tip]tip content[/quote]',
			),
			array(
				'Callout box warning with &gt; entity',
				'&gt; [!WARNING]<br />&gt; warning content<br />',
				'[quote box=warning]warning content[/quote]',
			),
		);
	}

	/**
	 * Test the parse() method - bold, italic, strikethrough, rule, and blockquote conversions.
	 */
	public function testParse()
	{
		$parser = new MarkdownParser();

		foreach ($this->parseTestCases as $testcase)
		{
			[$name, $input, $expected] = $testcase;
			$this->assertEquals($expected, $parser->parse($input), $name);
		}
	}

	/**
	 * Test the inlineCodeTags() method - backtick inline code and fenced code blocks.
	 */
	public function testInlineCodeTags()
	{
		$parser = new MarkdownParser();

		foreach ($this->inlineCodeTestCases as $testcase)
		{
			[$name, $input, $expected] = $testcase;
			$this->assertEquals($expected, $parser->inlineCodeTags($input), $name);
		}
	}

	/**
	 * Test the calloutTags() method - GitHub-style alert/callout blocks.
	 */
	public function testCalloutTags()
	{
		$parser = new MarkdownParser();

		foreach ($this->calloutTestCases as $testcase)
		{
			[$name, $input, $expected] = $testcase;
			$this->assertEquals($expected, $parser->calloutTags($input), $name);
		}
	}
}
