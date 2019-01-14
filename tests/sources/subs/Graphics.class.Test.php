<?php

class TestGraphics extends \PHPUnit\Framework\TestCase
{
	protected $image_testcases = array();

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
			),
			array(
				'url' => 'http://weblogs.us/images/weblogs.us.png',
				'width' => 432,
				'height' => 78,
			),
			array(
				'url' => 'https://pngimage.net/wp-content/uploads/2018/05/eyes-drawing-png-3.png',
				'width' => 2400,
  				'height' => 1817,
			),
			array(
				'url' => 'http://www.google.com/intl/en_ALL/images/logo.gif',
				'width' => 276,
				'height' => 110,
			),
			array(
				'url' => 'http://eeweb.poly.edu/~yao/EL5123/image/barbara_gray.bmp',
				'width' => 512,
				'height' => 512,
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
		$images = new \ElkArte\Graphics\Image();

		foreach ($this->image_testcases as $image)
		{
			$success = $images->createThumbnail($image['url'], 100, 100, 'test');

			// Check for correct results
			$this->assertEquals($success, true);
		}
	}
}