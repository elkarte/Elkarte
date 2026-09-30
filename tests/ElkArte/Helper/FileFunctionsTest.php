<?php

namespace ElkArte\Helper;

use Exception;
use PHPUnit\Framework\TestCase;

class FileFunctionsTest extends TestCase
{
	private FileFunctions $fileFunc;
	private string $tempDir;

	protected function setUp(): void
	{
		$this->fileFunc = FileFunctions::instance();
		$this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'elkarte_filefunc_test_' . uniqid('', true);
		if (!is_dir($this->tempDir))
		{
			@mkdir($this->tempDir, 0777, true);
		}
	}

	protected function tearDown(): void
	{
		if (is_dir($this->tempDir))
		{
			$this->fileFunc->rmDir($this->tempDir, true);
		}
	}

	public function testInstanceAndSetInstance(): void
	{
		$instance1 = FileFunctions::instance();
		$this->assertInstanceOf(FileFunctions::class, $instance1);

		$customInstance = new FileFunctions();
		FileFunctions::setInstance($customInstance);
		$this->assertSame($customInstance, FileFunctions::instance());

		// Restore original singleton
		FileFunctions::setInstance($instance1);
		$this->assertSame($instance1, FileFunctions::instance());
	}

	public function testElkChmodModes(): void
	{
		$testFile = $this->tempDir . DIRECTORY_SEPARATOR . 'chmod_test.txt';
		file_put_contents($testFile, 'test');

		// Integer octal modes
		$this->assertTrue($this->fileFunc->elk_chmod($testFile, 0644));
		$this->assertTrue($this->fileFunc->elk_chmod($testFile, 0666));
		$this->assertTrue($this->fileFunc->elk_chmod($testFile, 0755));
		$this->assertTrue($this->fileFunc->elk_chmod($testFile, 0777));

		// String octal modes
		$this->assertTrue($this->fileFunc->elk_chmod($testFile, '0644'));
		$this->assertTrue($this->fileFunc->elk_chmod($testFile, '644'));
		$this->assertTrue($this->fileFunc->elk_chmod($testFile, '0755'));
		$this->assertTrue($this->fileFunc->elk_chmod($testFile, '755'));
		$this->assertTrue($this->fileFunc->elk_chmod($testFile, '0777'));
		$this->assertTrue($this->fileFunc->elk_chmod($testFile, '777'));

		// Decimal representation integers
		$this->assertTrue($this->fileFunc->elk_chmod($testFile, 755));
		$this->assertTrue($this->fileFunc->elk_chmod($testFile, 644));

		// Empty/default mode
		$this->assertTrue($this->fileFunc->elk_chmod($testFile, ''));
	}

	public function testChmod(): void
	{
		$testFile = $this->tempDir . DIRECTORY_SEPARATOR . 'writable_test.txt';
		file_put_contents($testFile, 'writable content');

		$this->assertTrue($this->fileFunc->chmod($testFile));
		$this->assertTrue($this->fileFunc->chmod($this->tempDir));
	}

	public function testIsDirAndFileExists(): void
	{
		$testDir = $this->tempDir . DIRECTORY_SEPARATOR . 'subdir';
		$testFile = $this->tempDir . DIRECTORY_SEPARATOR . 'file.txt';

		mkdir($testDir);
		file_put_contents($testFile, 'content');

		$this->assertTrue($this->fileFunc->isDir($testDir));
		$this->assertFalse($this->fileFunc->isDir($testFile));
		$this->assertFalse($this->fileFunc->isDir($this->tempDir . DIRECTORY_SEPARATOR . 'nonexistent'));

		$this->assertTrue($this->fileFunc->fileExists($testFile));
		$this->assertFalse($this->fileFunc->fileExists($testDir));
		$this->assertFalse($this->fileFunc->fileExists($this->tempDir . DIRECTORY_SEPARATOR . 'nonexistent.txt'));

		// Test symlink handling if platform supports it
		$symlinkDir = $this->tempDir . DIRECTORY_SEPARATOR . 'link_to_dir';
		$symlinkFile = $this->tempDir . DIRECTORY_SEPARATOR . 'link_to_file';
		if (@symlink($testDir, $symlinkDir))
		{
			$this->assertTrue($this->fileFunc->isDir($symlinkDir));
		}
		if (@symlink($testFile, $symlinkFile))
		{
			$this->assertTrue($this->fileFunc->fileExists($symlinkFile));
		}
	}

	public function testFilePermsSizeAndWritable(): void
	{
		$testFile = $this->tempDir . DIRECTORY_SEPARATOR . 'size_perms.txt';
		file_put_contents($testFile, 'hello world');

		$this->assertEquals(11, $this->fileFunc->fileSize($testFile));
		$this->assertIsInt($this->fileFunc->filePerms($testFile));
		$this->assertTrue($this->fileFunc->isWritable($testFile));
		$this->assertTrue($this->fileFunc->isWritable($this->tempDir));

		$this->assertFalse($this->fileFunc->fileSize($this->tempDir . DIRECTORY_SEPARATOR . 'nonexistent.txt'));
	}

	public function testFileGetContents(): void
	{
		$testFile = $this->tempDir . DIRECTORY_SEPARATOR . 'get_contents.txt';
		$content = 'Sample file content for testing fileGetContents';
		file_put_contents($testFile, $content);

		$this->assertEquals($content, $this->fileFunc->fileGetContents($testFile));
		$this->assertFalse($this->fileFunc->fileGetContents($this->tempDir . DIRECTORY_SEPARATOR . 'nonexistent.txt'));
	}

	public function testCreateDirectory(): void
	{
		$nestedDir = $this->tempDir . DIRECTORY_SEPARATOR . 'level1' . DIRECTORY_SEPARATOR . 'level2' . DIRECTORY_SEPARATOR . 'level3';

		// Create nested directory without secureDirectory
		$this->assertTrue($this->fileFunc->createDirectory($nestedDir, false));
		$this->assertTrue(is_dir($nestedDir));

		// Creating existing directory returns true
		$this->assertTrue($this->fileFunc->createDirectory($nestedDir, false));

		// Empty path throws exception
		$this->expectException(Exception::class);
		$this->fileFunc->createDirectory('');
	}

	public function testCreateDirectoryCollisionWithFile(): void
	{
		$fileAsBlocker = $this->tempDir . DIRECTORY_SEPARATOR . 'blocker';
		file_put_contents($fileAsBlocker, 'i am a file');

		$targetPath = $fileAsBlocker . DIRECTORY_SEPARATOR . 'child';

		$this->expectException(Exception::class);
		$this->expectExceptionMessage('attach_dir_duplicate_file');
		$this->fileFunc->createDirectory($targetPath, false);
	}

	public function testListTree(): void
	{
		$dir = $this->tempDir . DIRECTORY_SEPARATOR . 'treetest';
		mkdir($dir);
		mkdir($dir . DIRECTORY_SEPARATOR . 'sub1');
		mkdir($dir . DIRECTORY_SEPARATOR . 'sub2');

		file_put_contents($dir . DIRECTORY_SEPARATOR . 'root.txt', '123');
		file_put_contents($dir . DIRECTORY_SEPARATOR . 'sub1' . DIRECTORY_SEPARATOR . 'a.txt', '12345');
		file_put_contents($dir . DIRECTORY_SEPARATOR . 'sub2' . DIRECTORY_SEPARATOR . 'b.txt', '1234567');

		$tree = $this->fileFunc->listTree($dir);
		$this->assertCount(3, $tree);

		$filenames = array_column($tree, 'filename');
		$this->assertContains('root.txt', $filenames);
		$this->assertContains('sub1/a.txt', $filenames);
		$this->assertContains('sub2/b.txt', $filenames);

		// Non-existent directory returns empty array
		$this->assertSame([], $this->fileFunc->listTree($this->tempDir . DIRECTORY_SEPARATOR . 'not_found'));
	}

	public function testDelete(): void
	{
		$testFile = $this->tempDir . DIRECTORY_SEPARATOR . 'delete_me.txt';
		file_put_contents($testFile, 'goodbye');

		$this->assertTrue($this->fileFunc->fileExists($testFile));
		$this->assertTrue($this->fileFunc->delete($testFile));
		$this->assertFalse($this->fileFunc->fileExists($testFile));

		// Deleting non-existent file returns false
		$this->assertFalse($this->fileFunc->delete($testFile));
	}

	public function testRmDir(): void
	{
		$targetDir = $this->tempDir . DIRECTORY_SEPARATOR . 'rm_target';
		mkdir($targetDir);
		mkdir($targetDir . DIRECTORY_SEPARATOR . 'nested');

		file_put_contents($targetDir . DIRECTORY_SEPARATOR . 'file1.txt', 'data1');
		file_put_contents($targetDir . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'file2.txt', 'data2');

		// Test delete_dir = false (removes contents, leaves root directory)
		$this->assertTrue($this->fileFunc->rmDir($targetDir, false));
		$this->assertTrue(is_dir($targetDir));
		$this->assertFalse(file_exists($targetDir . DIRECTORY_SEPARATOR . 'file1.txt'));
		$this->assertFalse(file_exists($targetDir . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'file2.txt'));

		// Recreate files
		file_put_contents($targetDir . DIRECTORY_SEPARATOR . 'file3.txt', 'data3');

		// Test delete_dir = true (removes everything including root directory)
		$this->assertTrue($this->fileFunc->rmDir($targetDir, true));
		$this->assertFalse(file_exists($targetDir));
	}
}
