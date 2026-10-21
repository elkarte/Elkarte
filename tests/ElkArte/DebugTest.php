<?php

namespace ElkArte;

use PHPUnit\Framework\TestCase;

/**
 * TestCase class for Debug class.
 */
class DebugTest extends TestCase
{
	protected function setUp(): void
	{
		// Reset singleton state if needed
		$debug = Debug::instance();
		$ref = new \ReflectionClass($debug);
		$prop = $ref->getProperty('_db_cache');
		$prop->setAccessible(true);
		$prop->setValue($debug, []);

		$propCount = $ref->getProperty('_db_count');
		$propCount->setAccessible(true);
		$propCount->setValue($debug, 0);
	}

	public function testGetQueryStatsEmpty(): void
	{
		$debug = Debug::instance();
		$stats = $debug->getQueryStats();

		$this->assertSame(0, $stats['total']);
		$this->assertSame(0, $stats['duplicate_queries']);
		$this->assertSame([], $stats['duplicate_sql']);
		$this->assertSame(0.0, $stats['longest_query_time']);
		$this->assertNull($stats['longest_query_index']);
		$this->assertSame('', $stats['longest_query_location']);
	}

	public function testGetQueryStatsWithDuplicatesAndLongest(): void
	{
		$debug = Debug::instance();

		$debug->db_query([
			'q' => 'SELECT * FROM elkarte_settings WHERE variable = "test"',
			'f' => '/sources/ElkArte/Themes/ThemeLoader.php',
			'l' => 305,
			's' => 0.01,
			't' => 0.002,
		]);

		$debug->db_query([
			'q' => 'SELECT * FROM elkarte_settings WHERE variable = "test"',
			'f' => '/sources/ElkArte/Themes/ThemeLoader.php',
			'l' => 310,
			's' => 0.02,
			't' => 0.001,
		]);

		$debug->db_query([
			'q' => 'SELECT * FROM elkarte_settings WHERE variable = "test"',
			'f' => '/sources/ElkArte/Themes/ThemeLoader.php',
			'l' => 315,
			's' => 0.03,
			't' => 0.004,
		]);

		$debug->db_query([
			'q' => 'SELECT id_member FROM elkarte_members WHERE id_member = 1',
			'f' => '/sources/subs/Members.subs.php',
			'l' => 1140,
			's' => 0.05,
			't' => 0.060,
		]);

		$stats = $debug->getQueryStats();

		$this->assertSame(4, $stats['total']);
		// ThemeLoader query ran 3 times, so 2 duplicate executions
		$this->assertSame(2, $stats['duplicate_queries']);
		$this->assertCount(1, $stats['duplicate_sql']);
		$this->assertSame(3, $stats['duplicate_sql'][0]['count']);
		$this->assertSame('/sources/ElkArte/Themes/ThemeLoader.php line 305', $stats['duplicate_sql'][0]['location']);

		$this->assertSame(0.060, $stats['longest_query_time']);
		$this->assertSame(3, $stats['longest_query_index']);
		$this->assertSame('/sources/subs/Members.subs.php line 1140', $stats['longest_query_location']);
	}

	public function testOver50QueriesWithoutFalseDuplicates(): void
	{
		$debug = Debug::instance();

		for ($i = 1; $i <= 75; $i++)
		{
			$debug->db_query([
				'q' => 'SELECT id_member FROM elkarte_members WHERE id_member = ' . $i,
				'f' => '/sources/subs/Members.subs.php',
				'l' => 1140,
				's' => 0.001 * $i,
				't' => 0.0005,
			]);
		}

		$stats = $debug->getQueryStats();

		$this->assertSame(75, $stats['total']);
		$this->assertSame(0, $stats['duplicate_queries']);
		$this->assertSame([], $stats['duplicate_sql']);
	}

	public function testCustomCacheAndPlaceholderIgnoring(): void
	{
		$debug = Debug::instance();

		$customCache = [
			[
				'q' => '...',
				'f' => 'file1.php',
				'l' => 10,
				't' => 0.001,
			],
			[
				'q' => '...',
				'f' => 'file2.php',
				'l' => 20,
				't' => 0.002,
			],
			[
				'q' => '',
				'f' => 'file3.php',
				'l' => 30,
				't' => 0.003,
			],
			[
				'q' => 'SELECT 1',
				'f' => 'file4.php',
				'l' => 40,
				't' => 0.004,
			],
			[
				'q' => 'SELECT 1',
				'f' => 'file5.php',
				'l' => 50,
				't' => 0.005,
			],
		];

		$stats = $debug->getQueryStats($customCache);

		$this->assertSame(5, $stats['total']);
		$this->assertSame(1, $stats['duplicate_queries']);
		$this->assertCount(1, $stats['duplicate_sql']);
		$this->assertSame('select 1', $stats['duplicate_sql'][0]['sql']);
		$this->assertSame(2, $stats['duplicate_sql'][0]['count']);
		$this->assertSame('file4.php line 40', $stats['duplicate_sql'][0]['location']);
	}
}
