<?php

/**
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

use tests\ElkArteCommonSetupTest;

class MaillistPostSubsTest extends ElkArteCommonSetupTest
{
	protected $backupGlobalsExcludeList = ['user_info'];
	public $bbcTestCases;
	private $created_filter_ids = [];

	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	protected function setUp(): void
	{
		global $txt, $mbname, $modSettings;

		parent::setUp();

		require_once(SUBSDIR . '/Maillist.subs.php');
		require_once(SUBSDIR . '/MaillistPost.subs.php');

		$this->bbcTestCases = [
			[
				'Test bold',
				'**bold**',
				'[b]bold[/b]',
			],
			[
				'Named links',
				'[ElkArte](http://www.elkarte.net/)',
				'[url=http://www.elkarte.net/]ElkArte[/url]',
			],
			[
				'URL link',
				'[http://www.elkarte.net/](http://www.elkarte.net/)',
				'[url=http://www.elkarte.net/]http://www.elkarte.net/[/url]',
			],
			// This test is here only to remind that the Markdown library doesn't support nested lists
			[
				'Lists',
				'* item
    * sub item
*item',
				'[list][li]item[/li][li]sub item*item[/li][/list]',
			],
		];

		// Minimal globals used by pbe_clean_email_subject
		$txt['RE:'] = 'Re:';
		$txt['SUBJECT:'] = 'Subject:';
		$txt['FW:'] = 'Fw:';
		$txt['FWD:'] = 'Fwd:';
		$mbname = 'MySite';
		$modSettings['maillist_sitename'] = '';

		// Seed some parser/filter rows for tests that depend on DB-driven filters
		$db = database();

		// Parser: simple string marker
		$db->insert('insert', '{db_prefix}postby_emails_filters',
			[
				'filter_style' => 'string',
				'filter_from' => 'string',
				'filter_to' => 'string',
				'filter_type' => 'string',
				'filter_order' => 'int',
				'filter_name' => 'string',
			],
			[
				'parser',
				'--- Original Message ---',
				'',
				'string',
				1,
				'split_marker',
			],
			['id_filter']
		);

		// Parser: regex that matches typical "On ... wrote:" line
		$db->insert('insert', '{db_prefix}postby_emails_filters',
			[
				'filter_style' => 'string',
				'filter_from' => 'string',
				'filter_to' => 'string',
				'filter_type' => 'string',
				'filter_order' => 'int',
				'filter_name' => 'string',
			],
			[
				'parser',
				'~^On .* wrote:$~m',
				'',
				'regex',
				2,
				'split_regex',
			],
			['id_filter']
		);

		// Record created parser ids
		$request = $db->query('', '
			SELECT id_filter FROM {db_prefix}postby_emails_filters 
			WHERE filter_name IN ({array_string:n}) 
			ORDER BY id_filter ASC', [
			'n' => ['split_marker', 'split_regex']
		]);
		while ($row = $request->fetch_assoc())
		{
			$this->created_filter_ids[] = (int) $row['id_filter'];
		}
		$request->free_result();

		// Filters: regex and string replacements
		$db->insert('insert', '{db_prefix}postby_emails_filters',
			[
				'filter_style' => 'string',
				'filter_from' => 'string',
				'filter_to' => 'string',
				'filter_type' => 'string',
				'filter_order' => 'int',
				'filter_name' => 'string',
			],
			[
				'filter',
				'~--\\s*Sent from .*$~m',
				'',
				'regex',
				1,
				'sig_regex',
			],
			['id_filter']
		);

		$db->insert('insert', '{db_prefix}postby_emails_filters',
			[
				'filter_style' => 'string',
				'filter_from' => 'string',
				'filter_to' => 'string',
				'filter_type' => 'string',
				'filter_order' => 'int',
				'filter_name' => 'string',
			],
			[
				'filter',
				'foo',
				'bar',
				'string',
				2,
				'swap_foo_bar',
			],
			['id_filter']
		);

		$request = $db->query('', '
			SELECT id_filter 
			FROM {db_prefix}postby_emails_filters 
			WHERE filter_name IN ({array_string:n}) 
			ORDER BY id_filter ASC', [
			'n' => ['sig_regex', 'swap_foo_bar']
		]);
		while ($row = $request->fetch_assoc())
		{
			$this->created_filter_ids[] = (int) $row['id_filter'];
		}
		$request->free_result();
	}

	protected function tearDown(): void
	{
		// Cleanup created filters
		if (!empty($this->created_filter_ids))
		{
			$db = database();
			$db->query('', '
			DELETE FROM {db_prefix}postby_emails_filters 
			WHERE id_filter IN ({array_int:ids})', [
				'ids' => $this->created_filter_ids,
			]);
		}

		parent::tearDown();
	}

	/**
	 * testHTML2BBcode, parse html to BBC and checks that the results are what we expect
	 */
	public function testpbe_email_to_bbc()
	{
		foreach ($this->bbcTestCases as $testcase)
		{
			$name = $testcase[0];
			$test = $testcase[1];
			$expected = $testcase[2];

			// Convert the html to bbc
			$result = pbe_email_to_bbc($test, false);

			// Remove pretty print newlines
			$result = str_replace("\n", '', $result);

			$this->assertEquals($expected, $result);
		}
	}

	public function testRunParsersCutsOnFirstMatch()
	{
		// Should cut at string marker
		$body = "Reply line\n--- Original Message ---\nold stuff";
		$result = pbe_run_parsers($body);
		$this->assertSame("Reply line\n", $result);

		// Should cut at regex marker
		$body2 = "Top\nOn Tue Dec 1, 2020 wrote:\nquoted";
		$result2 = pbe_run_parsers($body2);
		$this->assertSame("Top\n", $result2);

		// No markers -> returns original
		$body3 = "Just a message";
		$result3 = pbe_run_parsers($body3);
		$this->assertSame($body3, $result3);
	}

	public function testFilterEmailMessageAppliesRegexAndString()
	{
		$text = "Hello foo\n-- Sent from MyPhone";
		$filtered = pbe_filter_email_message($text);
		$this->assertStringNotContainsString('Sent from', $filtered);
		$this->assertStringContainsString('bar', $filtered, 'foo should be replaced with bar');
		$this->assertStringNotContainsString('foo', $filtered);
	}

	public function testEmailQuoteDepthLevelsAndUpdate()
	{
		$line = '> hello';
		$depth = pbe_email_quote_depth($line); // default update = true
		$this->assertSame(1, $depth);
		$this->assertSame('hello', $line);

		$line2 = '>>there';
		$copy = $line2;
		$depth2 = pbe_email_quote_depth($line2, false); // do not update
		$this->assertSame(2, $depth2);
		$this->assertSame($copy, $line2, 'Line should be unchanged when update=false');
	}

	public function testFixEmailQuotesBuildsBBC()
	{
		$body = "> a\n>> b\nplain";
		$out = pbe_fix_email_quotes($body, false);

		// Contains nested quotes and closes properly
		$this->assertStringContainsString('[quote]', $out);
		$this->assertStringContainsString('[/quote]', $out);
		$this->assertStringContainsString('a', $out);
		$this->assertStringContainsString('b', $out);

		// Ensure trailing plain text remains
		$this->assertStringContainsString("\nplain", $out);
	}

	public function testCleanEmailSubjectStripsPrefixes()
	{
		global $txt, $mbname;

		$mbname = 'MySite';
		$subj = $txt['RE:'] . ' [' . $mbname . '] ' . $txt['FWD:'] . ' ' . $txt['SUBJECT:'] . ' Hello there';
		$clean = pbe_clean_email_subject($subj);
		$this->assertSame('Hello there', $clean);

		$this->assertTrue(pbe_clean_email_subject('Just fine', true));
	}

	public function testStrReplaceOnce()
	{
		$hay = 'xx foo yy foo';
		$res = pbe_str_replace_once('foo', 'bar', $hay);
		$this->assertSame('xx bar yy foo', $res);
	}

	public function testPrepareTextConvertsToMarkdown()
	{
		$msg = '[b]bold[/b] and a link: [url=https://example.com]Ex[/url]';
		$subj = 'Hello';
		$sig = "\n--\nSig here";
		pbe_prepare_text($msg, $subj, $sig);
		$this->assertStringContainsString('**bold**', $msg);
		$this->assertNotEmpty($subj);
	}
}
