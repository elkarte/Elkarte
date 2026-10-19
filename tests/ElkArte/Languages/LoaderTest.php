<?php

declare(strict_types=1);

namespace ElkArte\Languages;

use ElkArte\Database\QueryInterface;
use PHPUnit\Framework\TestCase;

class LoaderTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		Loader::resetDbCache();
	}

	public function testLoadFromDbMemoization(): void
	{
		$txt = [];
		$queries = [];

		$dbMock = $this->createMock(QueryInterface::class);
		$resultMock = new class {
			private int $index = 0;
			private array $data = [
				['language_key' => 'custom_test_key', 'value' => 'Overridden Value']
			];

			public function fetch_assoc()
			{
				if ($this->index < count($this->data))
				{
					return $this->data[$this->index++];
				}
				return false;
			}

			public function free_result(): void
			{
			}
		};

		// Track queries
		$dbMock->expects($this->exactly(2))
			->method('fetchQuery')
			->willReturnCallback(function ($query, $values = []) use (&$queries, $resultMock) {
				// Return resultMock only when querying 'Index'
				if (in_array('Index', $values['files'] ?? [], true))
				{
					$queries[] = $values['files'];
					return $resultMock;
				}

				$queries[] = $values['files'];
				return new class {
					public function fetch_assoc() { return false; }
					public function free_result(): void {}
				};
			});

		$loader = new Loader('English', $txt, $dbMock);

		// 1. Initial load for 'index' -> should query DB with normalized 'Index'
		$loader->load('index');
		$this->assertSame('Overridden Value', $txt['custom_test_key'] ?? null);
		$this->assertCount(1, $queries);
		$this->assertSame(['Index'], $queries[0]);

		// 2. Second load for 'index' and 'Index' -> should NOT query DB (memoized regardless of case)
		$loader->load('index');
		$loader->load('Index');
		$this->assertCount(1, $queries);

		// 3. Third load for 'index+Post' -> should only query 'Post'
		$loader->load('index+Post');
		$this->assertCount(2, $queries);
		$this->assertSame(['Post'], $queries[1]);

		// 4. Fourth load for 'Post', 'post', and 'index' in various orders/casing -> should NOT query DB
		$loader->load('Post');
		$loader->load('post');
		$loader->load('index');
		$loader->load('Index');
		$loader->load('index+Post');
		$loader->load('Index+post');
		$this->assertCount(2, $queries);

		// 5. Ignored files (Agreement, PrivacyPolicy) -> should NOT query DB
		$loader->load('Agreement');
		$loader->load('PrivacyPolicy');
		$this->assertCount(2, $queries);
	}

	public function testMultipleDbLoadsMergeResults(): void
	{
		$txt = [];
		$dbMock = $this->createMock(QueryInterface::class);

		$dbMock->expects($this->exactly(2))
			->method('fetchQuery')
			->willReturnCallback(function ($query, $values = []) {
				$files = $values['files'] ?? [];
				$data = [];
				if (in_array('FileA', $files, true))
				{
					$data[] = ['language_key' => 'keyA', 'value' => 'ValA'];
				}
				if (in_array('FileB', $files, true))
				{
					$data[] = ['language_key' => 'keyB', 'value' => 'ValB'];
				}

				return new class($data) {
					private array $data;
					private int $idx = 0;

					public function __construct(array $data) { $this->data = $data; }
					public function fetch_assoc()
					{
						return $this->idx < count($this->data) ? $this->data[$this->idx++] : false;
					}
					public function free_result(): void {}
				};
			});

		$loader = new Loader('English', $txt, $dbMock);

		$loader->load('fileA', false);
		$this->assertSame('ValA', $txt['keyA'] ?? null);

		$loader->load('fileB', false);
		$this->assertSame('ValA', $txt['keyA'] ?? null);
		$this->assertSame('ValB', $txt['keyB'] ?? null);

		// Reloading both shouldn't fire query or lose values
		$loader->load('fileA+fileB', false);
		$this->assertSame('ValA', $txt['keyA'] ?? null);
		$this->assertSame('ValB', $txt['keyB'] ?? null);
	}

	public function testMultipleLoaderInstancesShareDbMemoizationAndCache(): void
	{
		$txt1 = [];
		$txt2 = [];
		$queries = [];

		$dbMock = $this->createMock(QueryInterface::class);
		$dbMock->expects($this->once())
			->method('fetchQuery')
			->willReturnCallback(function ($query, $values = []) use (&$queries) {
				$queries[] = $values;
				return new class {
					private int $idx = 0;
					private array $rows = [
						['file' => 'Settings', 'language_key' => 'setting_title', 'value' => 'Custom Settings Title']
					];

					public function fetch_assoc()
					{
						return $this->idx < count($this->rows) ? $this->rows[$this->idx++] : false;
					}

					public function free_result(): void {}
				};
			});

		// Instance 1 loads Settings
		$loader1 = new Loader('English', $txt1, $dbMock);
		$loader1->load('Settings', false);

		$this->assertSame('Custom Settings Title', $txt1['setting_title'] ?? null);
		$this->assertCount(1, $queries);
		$this->assertSame(['Settings'], $queries[0]['files']);

		// Instance 2 loads Settings (different instance, e.g. from another theme or component)
		$loader2 = new Loader('English', $txt2, $dbMock);
		$loader2->load('Settings', false);

		// Should receive the cached DB value without triggering an extra query
		$this->assertSame('Custom Settings Title', $txt2['setting_title'] ?? null);
		$this->assertCount(1, $queries);
	}
}
