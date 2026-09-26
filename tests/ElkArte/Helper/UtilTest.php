<?php

namespace ElkArte\Helper;

use PHPUnit\Framework\TestCase;

class UtilTest extends TestCase
{
	protected $string = '';
	protected $backupGlobalsExcludeList = ['user_info'];

	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	protected function setUp(): void
	{
		// Create a utf-8 string with 4 byte characters
		$this->string = html_entity_decode('Some 4 byte characters&#x2070e;&#x20731;&#x20779; for elkarte testing', ENT_COMPAT, 'UTF-8');
	}

	/**
	 * cleanup data we no longer need at the end of the tests in this class.
	 *
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	protected function tearDown(): void
	{
	}

	/**
	 * entity fix test, is this even used?
	 */
	public function test_entity_fix()
	{
		$string = '8237'; // 0x202D
		$this->assertEmpty(Util::entity_fix($string));
	}

	/**
	 * Entity check tests
	 */
	public function test_entity_check()
	{
		// Valid entity is preserved
		$this->assertEquals('&#9829;', Util::entity_check('&#9829;'));

		// Invalid entity is stripped
		$this->assertEquals('', Util::entity_check('&#8237;'));
	}

	/**
	 * various htmlspecialchars tests
	 */
	public function test_htmlspecialchars()
	{
		$string = 'Some string &amp;#8238; with "special" chars like & and &gt;';
		$double_false = 'Some string &amp;#8238; with &quot;special&quot; chars like &amp; and &gt;';
		$double_true  = 'Some string &amp;amp;#8238; with &quot;special&quot; chars like &amp; and &amp;gt;';

		// Default call should double-encode (& => &amp;)
		$this->assertEquals($double_true, Util::htmlspecialchars($string));

		// Explicitly passing $double = false should skip pre-existing entities
		$this->assertEquals($double_false, Util::htmlspecialchars($string, ENT_COMPAT, 'UTF-8', false));
	}

	/**
	 * Trim some chaff from the string
	 */
	public function test_htmltrim()
	{
		$string = ' &nbsp;	&#x202D;Some string with leading spaces';
		$actual1 = 'Some string with leading spaces';

		Util::htmltrim($string);
		$this->assertEquals(Util::htmltrim($string), $actual1);
	}

	/**
	 * strpos with some 4byte characters
	 */
	public function test_strpos()
	{
		// Should be 30, strpos would say 39
		$this->assertEquals(30, Util::strpos($this->string, 'elkarte'));
	}

	/**
	 * substr with some 4 byte characters
	 */
	public function test_substr()
	{
		// Should be 30, strpos would say 39
		$this->assertEquals('elkarte', Util::substr($this->string, 30, 7));
	}

	/**
	 * upper case it
	 */
	public function test_uppercase()
	{
		$string = 'Sôn bôn de magnà el véder, el me fa minga mal.';
		$actual = 'SÔN BÔN DE MAGNÀ EL VÉDER, EL ME FA MINGA MAL.';

		$this->assertEquals(Util::strtoupper($string), $actual);
	}

	/**
	 * lower case
	 */
	public function test_lowercase()
	{
		$string = 'Sôn bôn de magnà el véder, el me fa minga mal.';
		$actual = 'sôn bôn de magnà el véder, el me fa minga mal.';

		$this->assertEquals(Util::strtolower($string), $actual);
	}

	/**
	 * shorten a chunk of text, here with 4 byte characters
	 */
	public function test_shorten_text()
	{
		$actual = html_entity_decode('Some 4 byte characters&#x2070e;&#x20731;&#x20779;', ENT_COMPAT, 'UTF-8');

		$this->assertEquals(Util::shorten_text($this->string, 26, true, ''), $actual);
	}

	/**
	 * Cut the html text, this one understands that html code does not account for any space
	 */
	public function test_shorten_html()
	{
		$string = '<div><b><i><u>ElkArte Forum Software</u></i></b></div>';
		$actual = '<div><b><i><u>ElkArte...</u></i></b></div>';

		$this->assertEquals(Util::shorten_html($string, 12), $actual);
	}

	/**
	 * strlen with some 4 byte characters which should only be 1 for length (display)
	 */
	public function test_strlen()
	{
		$this->assertEquals(45, Util::strlen($this->string));
	}
}
