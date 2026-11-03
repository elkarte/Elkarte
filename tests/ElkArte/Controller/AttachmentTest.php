<?php

/**
 * TestCase class for the Attachment Controller
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them local if you need to keep your data untouched!
 */

namespace ElkArte\Controller;

use ElkArte;
use ElkArte\EventManager;
use ElkArte\Http\Headers;
use ElkArte\Languages\Loader;
use tests\ElkArteCommonSetupTest;

class AttachmentTest extends ElkArteCommonSetupTest
{
	protected $backupGlobalsExcludeList = ['user_info'];

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	protected function setUp(): void
	{
		global $txt;
		// Load in the common items so the system thinks we have an active login
		parent::setUp();
		$this->setSession();

		new ElkArte\Themes\ThemeLoader();
		$lang = new Loader('english', $txt, database());
		$lang->load('Post+Errors');
	}

	/**
	 * Test getting the controller
	 */
	public function testNeedTheme()
	{
		// Get the attachment controller
		$controller = new Attachment(new EventManager());
		$check = $controller->needTheme();

		// Check
		$this->assertFalse($check);
	}

	public function testUlattach()
	{
		global $context;

		// Get the attachment controller
		$controller = new Attachment(new EventManager());
		$controller->action_ulattach();

		// With no files supplied, the json result should be false
		$this->assertFalse($context['json_data']['result']);

		// Some BS file name
		$_FILES['attachment'] = ['name' => [0 => 'blablabla'], 'tmp_name' => [0 => 'blablabla']];
		$controller->action_ulattach();

		// just check for a key, the results are somewhat random from CI
		$this->assertArrayHasKey('result', $context['json_data']);
	}

	/**
	 * Test prepare_headers caching behavior for inline images
	 */
	public function testPrepareHeadersInlineImage(): void
	{
		$headers = Headers::instance();
		$headers->removeHeader('all');

		$controller = new Attachment(new EventManager());
		$controller->prepare_headers('test.jpg', '"etag123"', 'image/jpeg', 'inline', 'test.jpg', true, false);

		$setHeaders = $headers->getHeaders();
		$this->assertSame('max-age=31536000, private', $setHeaders['Cache-Control']);
		$this->assertArrayNotHasKey('Pragma', $setHeaders);
		$this->assertSame('inline; filename="test.jpg"', $setHeaders['Content-Disposition']);
		$this->assertSame('nosniff', $setHeaders['X-Content-Type-Options']);
		$this->assertSame('"etag123"', $setHeaders['ETag']);
		$this->assertSame('image/jpeg', $headers->getContentType());
	}

	/**
	 * Test prepare_headers caching behavior for downloaded image attachments
	 */
	public function testPrepareHeadersAttachmentImage(): void
	{
		$headers = Headers::instance();
		$headers->removeHeader('all');

		$controller = new Attachment(new EventManager());
		$controller->prepare_headers('test.png', '"etag456"', 'image/png', 'attachment', 'test.png', true, false);

		$setHeaders = $headers->getHeaders();
		$this->assertSame('max-age=31536000, private', $setHeaders['Cache-Control']);
		$this->assertArrayNotHasKey('Pragma', $setHeaders);
		$this->assertSame('attachment; filename="test.png"', $setHeaders['Content-Disposition']);
		$this->assertSame('nosniff', $setHeaders['X-Content-Type-Options']);
		$this->assertSame('image/png', $headers->getContentType());
	}

	/**
	 * Test prepare_headers caching behavior for non-image attachments
	 */
	public function testPrepareHeadersNonImageAttachment(): void
	{
		$headers = Headers::instance();
		$headers->removeHeader('all');

		$controller = new Attachment(new EventManager());
		$controller->prepare_headers('archive.zip', '"etag789"', 'application/zip', 'attachment', 'archive.zip', true, false);

		$setHeaders = $headers->getHeaders();
		$this->assertSame('max-age=31536000, private', $setHeaders['Cache-Control']);
		$this->assertArrayNotHasKey('Pragma', $setHeaders);
		$this->assertSame('attachment; filename="archive.zip"', $setHeaders['Content-Disposition']);
		$this->assertSame('application/octet-stream', $headers->getContentType());
	}

	/**
	 * Test prepare_headers when caching is explicitly disabled
	 */
	public function testPrepareHeadersNoCache(): void
	{
		$headers = Headers::instance();
		$headers->removeHeader('all');

		$controller = new Attachment(new EventManager());
		$controller->prepare_headers('nocache.txt', '"etag000"', '', 'attachment', 'nocache.txt', false, false);

		$setHeaders = $headers->getHeaders();
		$this->assertSame('no-cache', $setHeaders['Cache-Control']);
		$this->assertSame('no-cache', $setHeaders['Pragma']);
	}
}
