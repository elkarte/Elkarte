<?php

namespace ElkArte;

use DOMDocument;
use ElkArte\UserInfo;
use ElkArte\User;
use PHPUnit\Framework\TestCase;

/**
 * Test case for Xml.template.php functions and security hardening
 */
class XmlTemplateTest extends TestCase
{
	protected $backupGlobalsExcludeList = ['user_info'];

	protected function setUp(): void
	{
		global $modSettings, $txt;

		if (!defined('BOARDDIR'))
		{
			define('BOARDDIR', dirname(__DIR__, 2));
		}
		if (!defined('SOURCEDIR'))
		{
			define('SOURCEDIR', BOARDDIR . '/sources');
		}
		if (!defined('SUBSDIR'))
		{
			define('SUBSDIR', SOURCEDIR . '/subs');
		}
		if (!defined('LANGUAGEDIR'))
		{
			define('LANGUAGEDIR', SOURCEDIR . '/ElkArte/Languages');
		}

		require_once SOURCEDIR . '/Subs.php';
		require_once SOURCEDIR . '/Load.php';
		require_once BOARDDIR . '/themes/default/Xml.template.php';
		require_once SOURCEDIR . '/ElkArte/Languages/Index/English.php';

		$modSettings['time_offset'] = 0;
		$modSettings['todayMod'] = 0;

		$txt['months'] = [
			1 => $txt['january'] ?? 'January',
			2 => $txt['february'] ?? 'February',
			3 => $txt['march'] ?? 'March',
			4 => $txt['april'] ?? 'April',
			5 => $txt['may'] ?? 'May',
			6 => $txt['june'] ?? 'June',
			7 => $txt['july'] ?? 'July',
			8 => $txt['august'] ?? 'August',
			9 => $txt['september'] ?? 'September',
			10 => $txt['october'] ?? 'October',
			11 => $txt['november'] ?? 'November',
			12 => $txt['december'] ?? 'December',
		];
		$txt['months_short'] = [
			1 => $txt['january_short'] ?? 'Jan',
			2 => $txt['february_short'] ?? 'Feb',
			3 => $txt['march_short'] ?? 'Mar',
			4 => $txt['april_short'] ?? 'Apr',
			5 => $txt['may_short'] ?? 'May',
			6 => $txt['june_short'] ?? 'Jun',
			7 => $txt['july_short'] ?? 'Jul',
			8 => $txt['august_short'] ?? 'Aug',
			9 => $txt['september_short'] ?? 'Sep',
			10 => $txt['october_short'] ?? 'Oct',
			11 => $txt['november_short'] ?? 'Nov',
			12 => $txt['december_short'] ?? 'Dec',
		];
		$txt['months_titles'] = $txt['months'];
		$txt['days'] = [
			0 => $txt['sunday'] ?? 'Sunday',
			1 => $txt['monday'] ?? 'Monday',
			2 => $txt['tuesday'] ?? 'Tuesday',
			3 => $txt['wednesday'] ?? 'Wednesday',
			4 => $txt['thursday'] ?? 'Thursday',
			5 => $txt['friday'] ?? 'Friday',
			6 => $txt['saturday'] ?? 'Saturday',
		];
		$txt['days_short'] = [
			0 => $txt['sunday_short'] ?? 'Sun',
			1 => $txt['monday_short'] ?? 'Mon',
			2 => $txt['tuesday_short'] ?? 'Tue',
			3 => $txt['wednesday_short'] ?? 'Wed',
			4 => $txt['thursday_short'] ?? 'Thu',
			5 => $txt['friday_short'] ?? 'Fri',
			6 => $txt['saturday_short'] ?? 'Sat',
		];

		User::$info = new UserInfo([
			'time_offset' => 0,
			'time_format' => '%B %d, %Y, %I:%M:%S %p',
		]);
	}

	/**
	 * Capture template function output
	 */
	protected function captureOutput(callable $callback): string
	{
		ob_start();
		$callback();
		return ob_get_clean();
	}

	/**
	 * Test generic XML attribute escaping and CDATA boundary safety
	 */
	public function testGenericXmlEscaping(): void
	{
		global $context;

		$context['xml_data'] = [
			'members' => [
				'identifier' => 'member',
				'children' => [
					[
						'attributes' => [
							'id' => '123',
							'attr_breakout' => 'val" onfocus="alert(1)" x="',
							'special' => '<foo & "bar" \'baz\'>',
						],
						'value' => 'User <payload> with ]]> and <script>alert(1)</script> <![CDATA[',
					],
					[
						'attributes' => [
							'id' => '456',
						],
						'value' => 'Clean Value',
					],
				],
			],
		];

		$output = $this->captureOutput(function () {
			template_generic_xml();
		});

		// Verify attribute quote breakout characters are escaped in raw output
		$this->assertStringNotContainsString('attr_breakout="val" onfocus="alert(1)"', $output);
		$this->assertStringContainsString('attr_breakout="val&quot; onfocus=&quot;alert(1)&quot; x=&quot;"', $output);
		$this->assertStringContainsString('special="&lt;foo &amp; &quot;bar&quot; &#039;baz&#039;&gt;"', $output);

		// Verify CDATA block is split to prevent tag breakout
		$this->assertStringContainsString('User <payload> with ]]]]><![CDATA[> and <script>alert(1)</script> <![CDATA[', $output);

		// Validate well-formed XML and verify DOM structure
		$dom = new DOMDocument();
		$loaded = $dom->loadXML($output);
		$this->assertTrue($loaded, 'Output must be well-formed XML: ' . $output);

		$members = $dom->getElementsByTagName('member');
		$this->assertEquals(2, $members->length);

		$firstMember = $members->item(0);
		$this->assertEquals('123', $firstMember->getAttribute('id'));
		$this->assertEquals('val" onfocus="alert(1)" x="', $firstMember->getAttribute('attr_breakout'));
		$this->assertEquals('<foo & "bar" \'baz\'>', $firstMember->getAttribute('special'));
		$this->assertEquals('User <payload> with ]]> and <script>alert(1)</script> <![CDATA[', $firstMember->textContent);

		// Verify no injected element was created by the CDATA breakout attempt
		$injectedTags = $dom->getElementsByTagName('script');
		$this->assertEquals(0, $injectedTags->length);
	}

	/**
	 * Test generic XML buttons template CDATA escaping
	 */
	public function testGenericXmlButtons(): void
	{
		global $context;

		$context['xml_data'] = [
			'url' => 'https://example.com/?a=1&b=2',
			'text' => 'Button ]]> <payload>',
		];

		$output = $this->captureOutput(function () {
			template_generic_xml_buttons();
		});

		$dom = new DOMDocument();
		$loaded = $dom->loadXML($output);
		$this->assertTrue($loaded, 'Output must be valid XML');

		$textElements = $dom->getElementsByTagName('text');
		$this->assertEquals(1, $textElements->length);
		$this->assertEquals('Button ]]> <payload>', $textElements->item(0)->textContent);

		$payloadElements = $dom->getElementsByTagName('payload');
		$this->assertEquals(0, $payloadElements->length);
	}

	/**
	 * Test Atom category attribute escaping in template_xml_news
	 */
	public function testXmlNewsAtomCategoryEscaping(): void
	{
		$data = [
			'category' => 'Technology" attr="injected',
		];

		$output = $this->captureOutput(function () use ($data) {
			template_xml_news($data, 0, null, 'atom');
		});

		$this->assertStringNotContainsString('term="Technology" attr="injected"', $output);
		$this->assertStringContainsString('term="Technology&quot; attr=&quot;injected"', $output);

		// Wrapped in root element for XML parsing validation
		$xml = '<?xml version="1.0" encoding="UTF-8"?><feed>' . $output . '</feed>';
		$dom = new DOMDocument();
		$loaded = $dom->loadXML($xml);
		$this->assertTrue($loaded, 'Output must be valid XML');

		$categories = $dom->getElementsByTagName('category');
		$this->assertEquals(1, $categories->length);
		$this->assertEquals('Technology" attr="injected', $categories->item(0)->getAttribute('term'));
	}

	/**
	 * Test attribute key escaping in generic XML
	 */
	public function testGenericXmlAttributeKeyEscaping(): void
	{
		global $context;

		$context['xml_data'] = [
			'items' => [
				'identifier' => 'item',
				'children' => [
					[
						'attributes' => [
							'data"evil="yes' => 'sample',
							'valid_key' => '0',
						],
						'value' => 'Val',
					],
				],
			],
		];

		$output = $this->captureOutput(function () {
			template_generic_xml();
		});

		// Raw quote in attribute name must be escaped as &quot; preventing attribute injection
		$this->assertStringNotContainsString('data"evil="yes="sample"', $output);
		$this->assertStringContainsString('data&quot;evil=&quot;yes="sample"', $output);
		$this->assertStringContainsString('valid_key="0"', $output);
	}

	/**
	 * Test multiple CDATA sequences in a single string
	 */
	public function testMultipleCdataSequences(): void
	{
		global $context;

		$context['xml_data'] = [
			'items' => [
				'identifier' => 'item',
				'children' => [
					[
						'attributes' => ['id' => '1'],
						'value' => 'First ]]> Middle ]]> Last',
					],
				],
			],
		];

		$output = $this->captureOutput(function () {
			template_generic_xml();
		});

		$this->assertStringContainsString('First ]]]]><![CDATA[> Middle ]]]]><![CDATA[> Last', $output);

		$dom = new DOMDocument();
		$loaded = $dom->loadXML($output);
		$this->assertTrue($loaded, 'Output must be valid XML');

		$items = $dom->getElementsByTagName('item');
		$this->assertEquals('First ]]> Middle ]]> Last', $items->item(0)->textContent);
	}

	/**
	 * Test XML draft autosave response output
	 */
	public function testXmlDraftEscaping(): void
	{
		global $context, $txt;

		$txt['draft_saved_on'] = 'Draft saved on';
		$context['id_draft'] = '42" onfocus="alert(1)" x="';
		$context['draft_saved_on'] = 1700000000;

		$output = $this->captureOutput(function () {
			template_xml_draft();
		});

		$this->assertStringNotContainsString('id="42" onfocus="alert(1)"', $output);
		$this->assertStringContainsString('id="42&quot; onfocus=&quot;alert(1)&quot; x=&quot;"', $output);

		$dom = new DOMDocument();
		$loaded = $dom->loadXML($output);
		$this->assertTrue($loaded, 'Output must be valid XML');

		$drafts = $dom->getElementsByTagName('draft');
		$this->assertEquals(1, $drafts->length);
		$this->assertEquals('42" onfocus="alert(1)" x="', $drafts->item(0)->getAttribute('id'));
	}

	/**
	 * Test draft load XML output
	 */
	public function testXmlLoadDraftEscaping(): void
	{
		global $context;

		$context['drafts'] = [
			'1" onfocus="alert(1)' => [
				'subject' => 'Draft Subject with ]]> payload',
				'poster_time' => '123456789',
				'link' => 'https://example.com/?id=1&action=post',
			],
		];

		$output = $this->captureOutput(function () {
			template_xml_load_draft();
		});

		$dom = new DOMDocument();
		$loaded = $dom->loadXML($output);
		$this->assertTrue($loaded, 'Output must be valid XML');

		$drafts = $dom->getElementsByTagName('draft');
		$this->assertEquals(1, $drafts->length);
		$this->assertEquals('1" onfocus="alert(1)', $drafts->item(0)->getAttribute('id'));

		$subjects = $dom->getElementsByTagName('subject');
		$this->assertEquals('Draft Subject with ]]> payload', $subjects->item(0)->textContent);
	}
}
