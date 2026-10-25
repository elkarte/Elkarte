<?php

namespace {
	if (!function_exists('call_integration_hook'))
	{
		function call_integration_hook($hook, $parameters = [])
		{
			return [];
		}
	}
}

namespace ElkArte\Search {

use ElkArte\Database\AbstractResult;
use ElkArte\Database\AbstractSearch;
use ElkArte\Database\QueryInterface;
use ElkArte\Helper\ValuesContainer;
use ElkArte\Search\API\Standard;
use PHPUnit\Framework\TestCase;

class StandardSubjectSearchTest extends TestCase
{
	protected function setUp(): void
	{
		global $modSettings;

		$modSettings = [
			'postmod_active' => false,
			'search_match_words' => 1,
			'search_max_results' => 5,
		];
	}

	public function testMultiWordSubjectSearchAccumulatesAndCapsResults()
	{
		global $modSettings;

		$modSettings['search_max_results'] = 5;

		$capturedQueries = [];
		$resultBatches = [3, 2];
		$callCount = 0;

		$mockDb = $this->createMock(QueryInterface::class);
		$mockDb->method('support_ignore')->willReturn(true);

		$mockDbSearch = $this->createMock(AbstractSearch::class);
		$mockDbSearch->method('search_query')->willReturnCallback(
			function ($identifier, $query, $params) use (&$capturedQueries, &$resultBatches, &$callCount) {
				$capturedQueries[] = [
					'query' => $query,
					'params' => $params,
				];

				$mockResult = $this->createMock(AbstractResult::class);
				$affected = $resultBatches[$callCount] ?? 0;
				$callCount++;
				$mockResult->method('affected_rows')->willReturn($affected);

				return $mockResult;
			}
		);

		$config = new ValuesContainer([
			'humungousTopicPosts' => 200,
			'shortTopicPosts' => 5,
			'banned_words' => [],
		]);
		$searchParamsReflection = new \ReflectionClass(SearchParams::class);
		$searchParams = $searchParamsReflection->newInstanceWithoutConstructor();

		// Create standard instance without invoking constructor to set mock DBs
		$reflection = new \ReflectionClass(Standard::class);
		$standard = $reflection->newInstanceWithoutConstructor();

		// Set protected/private properties
		$configProp = $reflection->getProperty('config');
		$configProp->setAccessible(true);
		$configProp->setValue($standard, $config);

		$searchParamsProp = $reflection->getProperty('_searchParams');
		$searchParamsProp->setAccessible(true);
		$searchParamsProp->setValue($standard, $searchParams);

		$dbProp = $reflection->getProperty('_db');
		$dbProp->setAccessible(true);
		$dbProp->setValue($standard, $mockDb);

		$dbSearchProp = $reflection->getProperty('_db_search');
		$dbSearchProp->setAccessible(true);
		$dbSearchProp->setValue($standard, $mockDbSearch);

		$weightFactorsProp = $reflection->getProperty('_weight_factors');
		$weightFactorsProp->setAccessible(true);
		$weightFactorsProp->setValue($standard, []);

		$weightTotalProp = $reflection->getProperty('_weight_total');
		$weightTotalProp->setAccessible(true);
		$weightTotalProp->setValue($standard, 100);

		$searchWords = [
			['subject_words' => ['apple']],
			['subject_words' => ['banana']],
			['subject_words' => ['cherry']],
		];

		$method = $reflection->getMethod('getSubjectResults');
		$method->setAccessible(true);

		$total = $method->invoke($standard, 1, $searchWords, []);

		// Total results returned should be 3 + 2 = 5
		$this->assertSame(5, $total);

		// Exactly 2 queries should have been run (cherry should not be queried since limit 5 was reached)
		$this->assertCount(2, $capturedQueries);

		// First query limit should be 5 (search_max_results - 0)
		$this->assertSame(5, $capturedQueries[0]['params']['limit']);

		// Second query limit should be 2 (search_max_results - 3)
		$this->assertSame(2, $capturedQueries[1]['params']['limit']);
	}

	public function testSubjectSearchWithoutMaxResultsLimit()
	{
		global $modSettings;

		$modSettings['search_max_results'] = 0;

		$capturedQueries = [];
		$resultBatches = [4, 6];
		$callCount = 0;

		$mockDb = $this->createMock(QueryInterface::class);
		$mockDb->method('support_ignore')->willReturn(true);

		$mockDbSearch = $this->createMock(AbstractSearch::class);
		$mockDbSearch->method('search_query')->willReturnCallback(
			function ($identifier, $query, $params) use (&$capturedQueries, &$resultBatches, &$callCount) {
				$capturedQueries[] = [
					'query' => $query,
					'params' => $params,
				];

				$mockResult = $this->createMock(AbstractResult::class);
				$affected = $resultBatches[$callCount] ?? 0;
				$callCount++;
				$mockResult->method('affected_rows')->willReturn($affected);

				return $mockResult;
			}
		);

		$config = new ValuesContainer([
			'humungousTopicPosts' => 200,
			'shortTopicPosts' => 5,
			'banned_words' => [],
		]);
		$searchParamsReflection = new \ReflectionClass(SearchParams::class);
		$searchParams = $searchParamsReflection->newInstanceWithoutConstructor();

		$reflection = new \ReflectionClass(Standard::class);
		$standard = $reflection->newInstanceWithoutConstructor();

		$configProp = $reflection->getProperty('config');
		$configProp->setAccessible(true);
		$configProp->setValue($standard, $config);

		$searchParamsProp = $reflection->getProperty('_searchParams');
		$searchParamsProp->setAccessible(true);
		$searchParamsProp->setValue($standard, $searchParams);

		$dbProp = $reflection->getProperty('_db');
		$dbProp->setAccessible(true);
		$dbProp->setValue($standard, $mockDb);

		$dbSearchProp = $reflection->getProperty('_db_search');
		$dbSearchProp->setAccessible(true);
		$dbSearchProp->setValue($standard, $mockDbSearch);

		$weightFactorsProp = $reflection->getProperty('_weight_factors');
		$weightFactorsProp->setAccessible(true);
		$weightFactorsProp->setValue($standard, []);

		$weightTotalProp = $reflection->getProperty('_weight_total');
		$weightTotalProp->setAccessible(true);
		$weightTotalProp->setValue($standard, 100);

		$searchWords = [
			['subject_words' => ['apple']],
			['subject_words' => ['banana']],
		];

		$method = $reflection->getMethod('getSubjectResults');
		$method->setAccessible(true);

		$total = $method->invoke($standard, 1, $searchWords, []);

		// Total should be 4 + 6 = 10
		$this->assertSame(10, $total);
		$this->assertCount(2, $capturedQueries);
		$this->assertSame(0, $capturedQueries[0]['params']['limit']);
		$this->assertSame(0, $capturedQueries[1]['params']['limit']);
	}
}
}
