<?php

namespace ElkArte\Graphics;

use ElkArte\Graphics\Manipulators\Gd2;
use ElkArte\Graphics\Manipulators\ImageMagick;
use PHPUnit\Framework\TestCase;

class ImageTest extends TestCase
{
	protected $image_testcases = array();
	protected $backupGlobalsExcludeList = ['user_info'];
	private array $tmp_files = [];

	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	protected function setUp(): void
	{
		$this->image_testcases = array(
			array(
				'url' => 'https://images.pexels.com/photos/753626/pexels-photo-753626.jpeg',
				'width' => 2000,
				'height' => 1335,
				'format' => IMAGETYPE_JPEG
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
	protected function tearDown(): void
	{
		// Cleanup any temp files we created
		foreach ($this->tmp_files as $f)
		{
			if (is_file($f))
			{
				@unlink($f);
			}
		}
		$this->tmp_files = [];
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

		foreach ($this->image_testcases as $image)
		{
			$current_image = new Image($image['url'], true);
			$success = $current_image->createThumbnail(100, 100, '/tmp/test', $image['format']);

			// Check for correct results
			$this->assertTrue($success !== false, $image['url']);

			$current_image = new Image($image['url']);
			$success = $current_image->createThumbnail(100, 100, '/tmp/test', $image['format']);

			// Check for correct results
			$this->assertTrue($success !== false, $image['url']);
		}
	}

	public function testText()
	{
		$images = new TextImage('test', true);
		$success = $images->generate(100, 75, 'png');
		$success = !empty($success);

		$this->assertTrue($success);

		$images = new TextImage('test');
		$success = $images->generate(100, 75, 'png');
		$success = !empty($success);

		$this->assertTrue($success);
	}

	/**
	 * Create a simple PNG image. Optionally with transparency.
	 */
	private function createPng(int $w, int $h, bool $transparent = false): string
	{
		if (!function_exists('imagecreatetruecolor'))
		{
			$this->markTestSkipped('GD not available to create fixtures');
		}

		$im = imagecreatetruecolor($w, $h);
		if ($transparent)
		{
			imagesavealpha($im, true);
			$trans = imagecolorallocatealpha($im, 0, 0, 0, 127);
			imagefill($im, 0, 0, $trans);
		}
		else
		{
			$white = imagecolorallocate($im, 255, 255, 255);
			imagefill($im, 0, 0, $white);
		}

		$path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'img_' . uniqid('', true) . '.png';
		imagepng($im, $path);
		imagedestroy($im);
		$this->tmp_files[] = $path;
		return $path;
	}

	/**
	 * Create a simple JPEG image.
	 */
	private function createJpeg(int $w, int $h, int $quality = 85): string
	{
		if (!function_exists('imagecreatetruecolor'))
		{
			$this->markTestSkipped('GD not available to create fixtures');
		}

		$im = imagecreatetruecolor($w, $h);
		$color = imagecolorallocate($im, 200, 100, 50);
		imagefill($im, 0, 0, $color);
		$path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'img_' . uniqid('', true) . '.jpg';
		imagejpeg($im, $path, $quality);
		imagedestroy($im);
		$this->tmp_files[] = $path;
		return $path;
	}

	public function testLocalMimeAndFilesize()
	{
		$png = $this->createPng(40, 30, false);
		$jpg = $this->createJpeg(50, 20, 80);

		$imgPng = new Image($png, true);
		$this->assertTrue($imgPng->isImage());
		$this->assertSame('image/png', $imgPng->getMimeType());
		$this->assertGreaterThan(0, $imgPng->getFilesize());

		$imgJpg = new Image($jpg, false);
		$this->assertTrue($imgJpg->isImage());
		$this->assertSame('image/jpeg', $imgJpg->getMimeType());
		$this->assertGreaterThan(0, $imgJpg->getFilesize());
	}

	public function testManipulatorSelection()
	{
		$png = $this->createPng(20, 20, false);

		// Forced GD should select GD when available
		if (Gd2::canUse())
		{
			$img = new Image($png, true);
			$this->assertSame('GD', $img->getManipulator());
		}

		// Without forcing and if Imagick is available, should prefer ImageMagick
		if (ImageMagick::canUse())
		{
			$img2 = new Image($png, false);
			$this->assertSame('ImageMagick', $img2->getManipulator());
		}
	}

	public function testDefaultFormatMatrix()
	{
		global $modSettings;

		// Transparent PNG should prefer PNG when WEBP disabled
		$pngT = $this->createPng(16, 16, true);
		$modSettings['attachment_webp_enable'] = 0;
		$imgT = new Image($pngT, true);
		$this->assertSame(IMAGETYPE_PNG, $imgT->getDefaultFormat());

		// Opaque JPEG should prefer JPEG regardless when WEBP disabled
		$jpg = $this->createJpeg(16, 12, 80);
		$imgJ = new Image($jpg, true);
		$this->assertSame(IMAGETYPE_JPEG, $imgJ->getDefaultFormat());

		// If WEBP is enabled and supported, expect WEBP on any image
		$modSettings['attachment_webp_enable'] = 1;
		$imgT2 = new Image($pngT, true);
		if ($imgT2->hasWebpSupport())
		{
			$this->assertSame(IMAGETYPE_WEBP, $imgT2->getDefaultFormat());
		}
		else
		{
			$this->markTestSkipped('WEBP not supported in this environment');
		}
	}

	public function testCreateThumbnailLocal()
	{
		$jpg = $this->createJpeg(200, 100, 85);
		$dst = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'thumb_' . uniqid('', true) . '.jpg';
		$this->tmp_files[] = $dst;

		$img = new Image($jpg, true);
		$thumb = $img->createThumbnail(50, 50, $dst, IMAGETYPE_JPEG);

		$this->assertNotFalse($thumb);
		$this->assertFileExists($dst);
		$size = getimagesize($dst);
		$this->assertSame(IMAGETYPE_JPEG, $size[2]);
		$this->assertLessThanOrEqual(50, $size[0]);
		$this->assertLessThanOrEqual(50, $size[1]);
	}

	public function testIsImageWithNonImage()
	{
		$txt = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'not_image_' . uniqid('', true) . '.txt';
		file_put_contents($txt, 'not an image');
		$this->tmp_files[] = $txt;

		$img = new Image($txt, true);
		$this->assertFalse($img->isImage());
		$this->assertFalse($img->isImageLoaded());
	}

	public function testTextAdditionalFormats()
	{
		$images = new TextImage('hello', true);
		$jpeg = $images->generate(80, 40, 'jpeg');
		$gif = $images->generate(80, 40, 'gif');

		$this->assertNotEmpty($jpeg);
		$this->assertNotEmpty($gif);

		$this->assertIsArray(getimagesizefromstring($jpeg));
		$this->assertIsArray(getimagesizefromstring($gif));
	}
}
