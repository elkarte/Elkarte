<?php

class TestBrowser extends \PHPUnit\Framework\TestCase
{
	protected $browser_testcases = array();

	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	public function setUp()
	{
		// user agent
		// expected detection
		// expected version
		$this->browser_testcases = array(
			array(
				'Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; Media Center PC 6.0; InfoPath.3; MS-RTC LM 8; Zune 4.7)',
				'ie9',
				'is_ie9'
			),
			array(
				'Mozilla/5.0 (Windows NT 6.0; rv:2.0) Gecko/20100101 Firefox/4.0 Opera 12.14',
				'opera',
				'is_opera12'
			),
			array(
				'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:32.0) Gecko/20100101 Firefox/32.0',
				'firefox',
				'is_firefox32'
			),
			array(
				'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/36.0.1944.0 Safari/537.36',
				'chrome',
				'is_chrome36'
			),
			array(
				'Mozilla/5.0 (iPhone; U; CPU iPhone OS 4_2_1 like Mac OS X; da-dk) AppleWebKit/533.17.9 (KHTML, like Gecko) Version/5.0.2 Mobile/8C148 Safari/6533.18.5',
				'mobile',
				'is_iphone'
			),
			array(
				'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_5) AppleWebKit/536.26.17 (KHTML like Gecko) Version/6.0.2 Safari/536.26.17',
				'safari',
				'is_safari6'
			),
			array(
				'Opera/9.5 (Microsoft Windows; PPC; Opera Mobi; U) SonyEricssonX1i/R2AA Profile/MIDP-2.0 Configuration/CLDC-1.1',
				'mobile',
				'is_opera_mobi'
			),
			array(
				'Mozilla/5.0 (iPad; CPU OS 6_0 like Mac OS X) AppleWebKit/536.26 (KHTML, like Gecko) Version/6.0 Mobile/10A5355d Safari/8536.25',
				'tablet',
				'is_safari6'
			),
			array(
				'Mozilla/5.0 (Linux; Android 4.3; Nexus 10 Build/JWR66Y) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/29.0.1547.72 Safari/537.36',
				'tablet',
				'is_chrome29'
			)
		);
	}

	/**
	 * cleanup data we no longer need at the end of the tests in this class.
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	public function tearDown()
	{
	}

	/**
	* Test broswer strings with BrowserDetector
	*/
	public function testBrowserDetector()
	{
		global $context;

		$detector = new testBrowser_Detector;

		foreach ($this->browser_testcases as $testcase)
		{
			// Set user agent
			$_SERVER['HTTP_USER_AGENT'] = $testcase[0];

			// What say you?
			$detector->testdetectBrowser();

			// Check for correct detection
			$this->assertEquals($testcase[1], $context['browser_body_id']);
			$this->assertArrayHasKey($testcase[2], array_flip(array_keys($context['browser'], true)));
		}
	}
}

class testBrowser_Detector extends Browser_Detector
{
	public function testdetectBrowser()
	{
		// Init
		$this->_ua = $_SERVER['HTTP_USER_AGENT'];
		$this->detectBrowser();
	}
}