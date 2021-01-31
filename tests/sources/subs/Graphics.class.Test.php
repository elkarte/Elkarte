<?php

use ElkArte\Graphics\Image;
use ElkArte\Graphics\Manipulators\Gd2;
use ElkArte\Graphics\TextImage;
use PHPUnit\Framework\TestCase;

class TestGraphics extends TestCase
{
	protected $image_testcases = array();
	protected $backupGlobalsBlacklist = ['user_info'];

	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	public function setUp()
	{
		$this->image_testcases = array(
			array(
				'url' => 'https://images.pexels.com/photos/753626/pexels-photo-753626.jpeg',
				'width' => 2000,
  				'height' => 1335,
				'format' => IMAGETYPE_JPEG
			),
			array(
				'url' => 'http://weblogs.us/images/weblogs.us.png',
				'width' => 432,
				'height' => 78,
				'format' => IMAGETYPE_PNG
			),
			array(
				'url' => 'http://www.google.com/intl/en_ALL/images/logo.gif',
				'width' => 276,
				'height' => 110,
				'format' => IMAGETYPE_GIF
			),
			array(
				'url' => 'https://raw.githubusercontent.com/recurser/exif-orientation-examples/master/Landscape_5.jpg',
				'width' => 1200,
				'height' => 1800,
				'format' => IMAGETYPE_PNG
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
	 * Test url_image_size fetching
	 */
	public function testQuickImageSize()
	{
		require_once(SUBSDIR . '/Attachments.subs.php');

		foreach ($this->image_testcases as $image)
		{
			$size = url_image_size($image['url']);

			// Check for correct results
			$this->assertEquals($image['width'], $size[0]);
		}
	}

	public function testThumbs()
	{
		global $modSettings;

		$modSettings['attachment_autorotate'] = 1;

		$success = Gd2::canUse();
		$this->assertEquals($success, true, 'GD NOT INSTALLED');

		foreach ($this->image_testcases as $image)
		{
			$current_image = new Image($image['url']);
			$success = $current_image->createThumbnail(100, 100, '/tmp/test', $image['format']);

			// Check for correct results
			$this->assertEquals($success !== false, true, $image['url']);
		}
	}

	public function testText()
	{
		$images = new TextImage('test');
		$success = $images->generate(100, 75, 'png');
		$success = !empty($success);

		$this->assertEquals($success, true);
	}
}
