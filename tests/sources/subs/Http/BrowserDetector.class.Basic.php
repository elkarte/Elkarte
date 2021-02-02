<?php

use ElkArte\Http\BrowserDetector;
use PHPUnit\Framework\TestCase;

class TestBrowser extends TestCase
{
	protected $browser_testcases = array();
	protected $backupGlobalsBlacklist = ['user_info'];

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
				'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/70.0.3538.77 Safari/537.36',
				'chrome',
				'is_chrome70'
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
			),
			array(
				'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.135 Safari/537.36 Edge/12.10147',
				'edge',
				'is_edge',
				'Win10 edge'
			),
			array(
				'Mozilla/5.0 (Linux; Android 7.0; SM-G892A Build/NRD90M; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/60.0.3112.107 Mobile Safari/537.36',
				'mobile',
				'is_android',
				'Galaxy S8'
			),
			array(
				'Mozilla/5.0 (iPhone; CPU iPhone OS 12_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/69.0.3497.105 Mobile/15E148 Safari/605.1',
				'mobile',
				'is_iphone',
				'Apple iPhone XR'
			),
			array(
				'Mozilla/5.0 (Apple-iPhone7C2/1202.466; U; CPU like Mac OS X; en) AppleWebKit/420+ (KHTML, like Gecko) Version/3.0 Mobile/1A543 Safari/419.3',
				'mobile',
				'is_iphone',
				'Apple iPhone 6'
			),
			array(
				'Mozilla/5.0 (iPhone; CPU iPhone OS 11_0 like Mac OS X) AppleWebKit/604.1.34 (KHTML, like Gecko) Version/11.0 Mobile/15A5341f Safari/604.1',
				'mobile',
				'is_iphone',
				'Apple iPhone 8'
			),
			array(
				'Mozilla/5.0 (Linux; Android 7.0; Pixel C Build/NRD90M; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/52.0.2743.98 Safari/537.36',
				'tablet',
				'is_tablet',
				'Pixel C'
			),
			array(
				'Mozilla/5.0 (Linux; Android 5.0.2; SAMSUNG SM-T550 Build/LRX22G) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/3.3 Chrome/38.0.2125.102 Safari/537.36',
				'tablet',
				'is_tablet',
				'Galaxy Tab A'
			),
			array(
				'Mozilla/5.0 (Linux; Android 4.4.3; KFTHWI Build/KTU84M) AppleWebKit/537.36 (KHTML, like Gecko) Silk/47.1.79 like Chrome/47.0.2526.80 Safari/537.36',
				'tablet',
				'is_android_tablet',
				'Kindle'
			),
			array(
				'Mozilla/5.0 (X11; CrOS x86_64 8172.45.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.64 Safari/537.36',
				'chrome',
				'is_chrome51',
				'Chrome Book'
			),
			array(
				'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_2) AppleWebKit/601.3.9 (KHTML, like Gecko) Version/9.0.2 Safari/601.3.9',
				'safari',
				'is_safari',
				'Mac OS X Safari'
			),
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
	* Test browser strings with BrowserDetector
	*/
	public function testBrowserDetector()
	{
		global $context;

		$detector = new testBrowserDetector;

		foreach ($this->browser_testcases as $testcase)
		{
			// Set user agent
			$_SERVER['HTTP_USER_AGENT'] = $testcase[0];

			// What say you?
			$browser = $detector->testdetectBrowser();
			$browser_body_id = $detector->browserBodyId();

			// Check for correct detection
			$this->assertEquals($testcase[1], $browser_body_id);
			$this->assertArrayHasKey($testcase[2], array_flip(array_keys($browser, true)));
		}
	}
}

class testBrowserDetector extends BrowserDetector
{
	public function testdetectBrowser()
	{
		// Init
		$this->_ua = $_SERVER['HTTP_USER_AGENT'];
		return $this->detectBrowser();
	}
}
