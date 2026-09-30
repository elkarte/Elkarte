<?php

declare(strict_types=1);

namespace ElkArte\Languages;

use ElkArte\Database\QueryInterface;
use PHPUnit\Framework\TestCase;

class LoaderTest extends TestCase
{
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
				// Return resultMock only when querying 'index'
				if (in_array('index', $values['files'] ?? [], true))
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

		// 1. Initial load for 'index' -> should query DB
		$loader->load('index');
		$this->assertSame('Overridden Value', $txt['custom_test_key'] ?? null);
		$this->assertCount(1, $queries);
		$this->assertSame(['index'], $queries[0]);

		// 2. Second load for 'index' -> should NOT query DB (memoized)
		$loader->load('index');
		$this->assertCount(1, $queries);

		// 3. Third load for 'index+Post' -> should only query 'Post'
		$loader->load('index+Post');
		$this->assertCount(2, $queries);
		$this->assertSame(['Post'], $queries[1]);

		// 4. Fourth load for 'Post' and 'index' in various orders -> should NOT query DB
		$loader->load('Post');
		$loader->load('index');
		$loader->load('index+Post');
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
				if (in_array('fileA', $files, true))
				{
					$data[] = ['language_key' => 'keyA', 'value' => 'ValA'];
				}
				if (in_array('fileB', $files, true))
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
}
