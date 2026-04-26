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

