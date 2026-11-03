<?php

namespace ElkArte\Attachments;

use ElkArte\Controller\Attachment;
use ElkArte\EventManager;
use ElkArte\Http\Headers;
use PHPUnit\Framework\TestCase;

/**
 * TestCase for Attachment security hardening (MIME whitelisting, nosniff, disposition safety).
 */
class AttachmentSecurityTest extends TestCase
{
	protected function setUp(): void
	{
		$sourcedir = defined('SOURCEDIR') ? SOURCEDIR : dirname(__DIR__, 3) . '/sources';
		require_once($sourcedir . '/Load.php');
		require_once($sourcedir . '/subs/Attachments.subs.php');
		Headers::instance()->removeHeader('all');
	}

	protected function tearDown(): void
	{
		Headers::instance()->removeHeader('all');
	}

	/**
	 * Verify that safe raster image formats allow inline disposition and preserve content-type.
	 */
	public function testSetAttachmentFileParamsSafeImages(): void
	{
		$headers = Headers::instance();

		$safeTypes = [
			'image/png' => 'test.png',
			'image/jpeg' => 'test.jpg',
			'image/gif' => 'test.gif',
			'image/webp' => 'test.webp',
			'image/avif' => 'test.avif',
			'image/bmp' => 'test.bmp',
		];

		foreach ($safeTypes as $mime => $filename)
		{
			$headers->removeHeader('all');
			$headers->setAttachmentFileParams($mime, $filename, 'inline');

			$this->assertSame($mime, $headers->getContentType(), "Safe image MIME $mime should be preserved");
			$allHeaders = $headers->getHeaders();
			$this->assertArrayHasKey('Content-Disposition', $allHeaders);
			$this->assertStringStartsWith('inline;', $allHeaders['Content-Disposition']);
		}
	}

	/**
	 * Verify that dangerous or non-image MIME types (such as SVG, HTML, JS) are forced
	 * to application/octet-stream and attachment disposition, preventing Stored XSS.
	 */
	public function testSetAttachmentFileParamsDangerousMimeTypes(): void
	{
		$headers = Headers::instance();

		$dangerousTypes = [
			'image/svg+xml' => 'xss.svg',
			'text/html' => 'evil.html',
			'application/javascript' => 'payload.js',
			'application/x-shockwave-flash' => 'flash.swf',
			'application/pdf' => 'document.pdf',
			'text/plain' => 'notes.txt',
		];

		foreach ($dangerousTypes as $mime => $filename)
		{
			$headers->removeHeader('all');
			// Request inline disposition
			$headers->setAttachmentFileParams($mime, $filename, 'inline');

			$this->assertSame('application/octet-stream', $headers->getContentType(), "Dangerous MIME $mime must be forced to application/octet-stream");
			$allHeaders = $headers->getHeaders();
			$this->assertArrayHasKey('Content-Disposition', $allHeaders);
			$this->assertStringStartsWith('attachment;', $allHeaders['Content-Disposition'], "Dangerous MIME $mime must be forced to attachment disposition");
		}
	}

	/**
	 * Verify getValidMimeImageType accepts only safe image formats and rejects dangerous formats.
	 */
	public function testGetValidMimeImageType(): void
	{
		// Safe types
		$this->assertSame('image/png', getValidMimeImageType('png'));
		$this->assertSame('image/png', getValidMimeImageType('image/png'));
		$this->assertSame('image/jpeg', getValidMimeImageType('jpeg'));
		$this->assertSame('image/jpeg', getValidMimeImageType('image/jpeg'));
		$this->assertSame('image/jpg', getValidMimeImageType('jpg'));
		$this->assertSame('image/gif', getValidMimeImageType('gif'));
		$this->assertSame('image/webp', getValidMimeImageType('webp'));
		$this->assertSame('image/avif', getValidMimeImageType('avif'));
		$this->assertSame('image/bmp', getValidMimeImageType('bmp'));
		$this->assertSame('image/png', getValidMimeImageType(IMAGETYPE_PNG));
		$this->assertSame('image/jpeg', getValidMimeImageType(IMAGETYPE_JPEG));

		// Dangerous or unsupported formats must return empty string
		$this->assertSame('', getValidMimeImageType('svg'));
		$this->assertSame('', getValidMimeImageType('image/svg+xml'));
		$this->assertSame('', getValidMimeImageType('html'));
		$this->assertSame('', getValidMimeImageType('text/html'));
		$this->assertSame('', getValidMimeImageType('js'));
		$this->assertSame('', getValidMimeImageType('application/javascript'));
		$this->assertSame('', getValidMimeImageType('pdf'));
		$this->assertSame('', getValidMimeImageType('exe'));
		$this->assertSame('', getValidMimeImageType(''));
	}

	/**
	 * Verify prepare_headers injects X-Content-Type-Options: nosniff and handles safe/unsafe attachments.
	 */
	public function testPrepareHeadersSecurityHeaders(): void
	{
		$headers = Headers::instance();
		$headers->removeHeader('all');

		$controller = new Attachment(new EventManager());

		// Test with check_filename = false to test header generation
		$controller->prepare_headers('dummy_image.png', '"etag123"', 'image/png', 'inline', 'dummy_image.png', true, false);

		$allHeaders = $headers->getHeaders();
		$this->assertArrayHasKey('X-Content-Type-Options', $allHeaders);
		$this->assertSame('nosniff', $allHeaders['X-Content-Type-Options']);
		$this->assertSame('image/png', $headers->getContentType());
		$this->assertStringStartsWith('inline;', $allHeaders['Content-Disposition']);

		// Test with dangerous SVG
		$headers->removeHeader('all');
		$controller->prepare_headers('dummy.svg', '"etag456"', 'image/svg+xml', 'inline', 'dummy.svg', true, false);

		$allHeaders = $headers->getHeaders();
		$this->assertArrayHasKey('X-Content-Type-Options', $allHeaders);
		$this->assertSame('nosniff', $allHeaders['X-Content-Type-Options']);
		$this->assertSame('application/octet-stream', $headers->getContentType());
		$this->assertStringStartsWith('attachment;', $allHeaders['Content-Disposition']);
	}

	/**
	 * Verify prepare_headers sends proper caching headers for inline image requests.
	 */
	public function testPrepareHeadersCachingInlineImage(): void
	{
		$headers = Headers::instance();
		$headers->removeHeader('all');

		$controller = new Attachment(new EventManager());
		$controller->prepare_headers('photo.jpg', '"etag123"', 'image/jpeg', 'inline', 'photo.jpg', true, false);

		$allHeaders = $headers->getHeaders();
		$this->assertSame('max-age=31536000, private', $allHeaders['Cache-Control']);
		$this->assertArrayNotHasKey('Pragma', $allHeaders);
		$this->assertSame('inline; filename="photo.jpg"', $allHeaders['Content-Disposition']);
		$this->assertSame('image/jpeg', $headers->getContentType());
	}

	/**
	 * Verify prepare_headers sends proper caching headers for image file downloads.
	 */
	public function testPrepareHeadersCachingDownloadedImage(): void
	{
		$headers = Headers::instance();
		$headers->removeHeader('all');

		$controller = new Attachment(new EventManager());
		// Image requested as download attachment
		$controller->prepare_headers('photo.png', '"etag456"', 'image/png', 'attachment', 'photo.png', true, false);

		$allHeaders = $headers->getHeaders();
		$this->assertSame('max-age=31536000, private', $allHeaders['Cache-Control']);
		$this->assertArrayNotHasKey('Pragma', $allHeaders);
		$this->assertSame('attachment; filename="photo.png"', $allHeaders['Content-Disposition']);
		$this->assertSame('image/png', $headers->getContentType());
	}

	/**
	 * Verify prepare_headers sends proper caching headers for non-image file downloads.
	 */
	public function testPrepareHeadersCachingNonImageAttachment(): void
	{
		$headers = Headers::instance();
		$headers->removeHeader('all');

		$controller = new Attachment(new EventManager());
		$controller->prepare_headers('archive.zip', '"etag789"', 'application/zip', 'attachment', 'archive.zip', true, false);

		$allHeaders = $headers->getHeaders();
		$this->assertSame('max-age=31536000, private', $allHeaders['Cache-Control']);
		$this->assertArrayNotHasKey('Pragma', $allHeaders);
		$this->assertSame('attachment; filename="archive.zip"', $allHeaders['Content-Disposition']);
		$this->assertSame('application/octet-stream', $headers->getContentType());
	}

	/**
	 * Verify prepare_headers respects $do_cache = false when anti-caching is explicitly requested.
	 */
	public function testPrepareHeadersExplicitNoCache(): void
	{
		$headers = Headers::instance();
		$headers->removeHeader('all');

		$controller = new Attachment(new EventManager());
		$controller->prepare_headers('file.txt', '"etag000"', '', 'attachment', 'file.txt', false, false);

		$allHeaders = $headers->getHeaders();
		$this->assertSame('no-cache', $allHeaders['Cache-Control']);
		$this->assertSame('no-cache', $allHeaders['Pragma']);
	}
}
