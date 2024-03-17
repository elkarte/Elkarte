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

use ElkArte\Attachments\TemporaryAttachmentProcess;
use ElkArte\Errors\AttachmentErrorContext;
use ElkArte\Languages\Loader;
use tests\ElkArteCommonSetupTest;

class AttachmentSubsTest extends ElkArteCommonSetupTest
{
	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	protected function setUp(): void
	{
		global $txt;

		parent::setUp();
		parent::setSession();

		// Some strings we will need
		$lang = new Loader('english', $txt, database());
		$lang->load('Post');

		// This is not intended to be correct, in fact it is intended to fail
		$_FILES = [
			'attachment'    =>  [
				'name'      =>  ['foo.txt'],
				'tmp_name'  =>  ['/tmp/php42up23'],
				'type'      =>  ['text/plain'],
				'size'      =>  [42],
				'error'     =>  [0]
			]
		];

		$_SESSION['temp_attachments']['post']['files'] = $_FILES;
		require_once(SUBSDIR . '/Attachments.subs.php');
	}

	/**
	 * cleanup data we no longer need at the end of the tests in this class.
	 *
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	protected function tearDown(): void
	{
		parent::tearDown();

		$_FILES = [];
		$_SESSION['temp_attachments'] = [];
	}

	/**
	 * Just a mock to run through the attachment process code
	 */
	public function testProcessAttachments()
	{
		// Better not pass
		$processAttachments = new TemporaryAttachmentProcess();
		$result = $processAttachments->processAttachments(0);
		$this->assertFalse($result);

		// What did it think of this
		$attach_errors = AttachmentErrorContext::context();
		$result = $attach_errors->hasErrors();
		$this->assertNotEmpty($result);

		// Errors, we hope so
		$errors = $attach_errors->prepareErrors();
		$this->assertEquals('Error uploading attachments.', $errors['attach_generic']['title']);
	}

	public function testGetAttachmentFromTopic()
	{
		$result = getAttachmentFromTopic(1,1);
		$this->assertEmpty($result);
	}

	public function testGetUtlImageSize()
	{
		$result = url_image_size('https://www.elkarte.net/community/themes/default/images/logo.png');
		$this->assertEquals(145, $result[0]);
	}

	public function testGetServerStoredAvatars()
	{
		global $context, $txt, $modSettings;

		$modSettings['avatar_directory'] = '/home/runner/work/Elkarte/Elkarte/elkarte/avatars';
		$context['member']['avatar']['server_pic'] = 'blank.png';
		$txt['no_pic'] = 'None';

		$result = getServerStoredAvatars( '/Oxygen');

		$this->assertCount(22, $result[0]['files']);
		//$this->assertEquals('invisible-user.png', $result[0]['files'][0]['filename']);
	}
}
