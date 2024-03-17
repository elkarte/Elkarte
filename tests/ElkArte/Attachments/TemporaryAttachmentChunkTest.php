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

namespace ElkArte\Attachments;

use tests\ElkArteCommonSetupTest;

class TemporaryAttachmentChunkTest extends ElkArteCommonSetupTest
{
	protected $tempAttachmentChunk;

	protected function setUp(): void
	{
		$this->tempAttachmentChunk = new TemporaryAttachmentChunk();
	}

	public function testActionAsync()
	{
		$result = $this->tempAttachmentChunk->action_async();

		// No session will just return
		$this->assertIsArray($result);
		$this->assertArrayHasKey('result', $result);
	}

	public function testSaveAsyncFile()
	{
		$result = $this->tempAttachmentChunk->saveAsyncFile();

		// No post data, will fail validatePostData()
		$this->assertIsArray($result);
		$this->assertArrayHasKey('id', $result);
		$this->assertArrayHasKey('code', $result);
		$this->assertEquals('invalid_chunk', $result['code']);

		// Dummy data to pass some checks
		$_POST = [
			'elkchunkindex' => 0,
			'elktotalchunkcount' => 1,
			'elkuuid' => 1234,
		];

		$result = $this->tempAttachmentChunk->saveAsyncFile();

		// Should get to validateReceivedFile and fail
		$this->assertIsArray($result);
		$this->assertArrayHasKey('id', $result);
		$this->assertArrayHasKey('code', $result);
		$this->assertEquals('no_files', $result['code']);
	}

	public function testCheckTotalSize()
	{
		$result = $this->tempAttachmentChunk->checkTotalSize(5);
		$this->assertTrue($result);
	}

	public function testGetSmallerNonZero()
	{
		$result = $this->tempAttachmentChunk->getSmallerNonZero(5, 10);
		$this->assertEquals(5, $result);
	}

	public function testErrorAsyncFile()
	{
		$error_code = 'invalid_chunk';
		$fileID = 'test_id';
		$result = $this->tempAttachmentChunk->errorAsyncFile($error_code, $fileID);
		$this->assertIsArray($result);
		$this->assertArrayHasKey('error', $result);
		$this->assertArrayHasKey('code', $result);
		$this->assertArrayHasKey('id', $result);
		$this->assertEquals($error_code, $result['code']);
		$this->assertEquals($fileID, $result['id']);
	}

	public function testReturnResults()
	{
		$result_data = [
			'id' => 'test_id',
			'code' => ''
		];
		$result = $this->tempAttachmentChunk->returnResults($result_data);
		$this->assertIsArray($result);
		$this->assertArrayHasKey('result', $result);
		$this->assertArrayHasKey('async', $result);
	}
}
