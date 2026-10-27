<?php

namespace {
	if (!function_exists('database'))
	{
		function database()
		{
			return null;
		}
	}
	if (!function_exists('call_integration_hook'))
	{
		function call_integration_hook($hook, $parameters = [])
		{
			return [];
		}
	}
	if (!function_exists('detectServer'))
	{
		function detectServer()
		{
			return new \ElkArte\Server($_SERVER);
		}
	}
	if (!function_exists('checkSession'))
	{
		function checkSession($type = 'post')
		{
			return true;
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

class SearchParamsTest extends TestCase
{
	/**
	 * Helper to create a base64url compressed search params string
	 */
	private function createSearchBlob(array $params): string
	{
		$json = json_encode($params);
		$compressed = gzcompress($json);

		return str_replace(['+', '/', '='], ['-', '_', '.'], base64_encode($compressed));
	}

	public function testDecodesValidParams()
	{
		$blob = $this->createSearchBlob([
			'search' => 'test search',
			'topic' => 42,
			'minage' => 10,
			'maxage' => 100,
			'searchtype' => 2,
			'sort' => 'relevance',
			'sort_dir' => 'asc',
			'brd' => [1, 2, 3],
			'userspec' => 'admin',
			'show_complete' => true,
			'subject_only' => true,
		]);

		$searchParams = new SearchParams($blob);
		$data = $searchParams->get();

		$this->assertSame('test search', $data['search']);
		$this->assertSame(42, $data['topic']);
		$this->assertSame(10, $data['minage']);
		$this->assertSame(100, $data['maxage']);
		$this->assertSame(2, $data['searchtype']);
		$this->assertSame('relevance', $data['sort']);
		$this->assertSame('asc', $data['sort_dir']);
		$this->assertSame([1, 2, 3], $data['brd']);
		$this->assertSame('admin', $data['userspec']);
		$this->assertTrue($data['show_complete']);
		$this->assertTrue($data['subject_only']);
	}

	public function testSanitizesInjectedAndMalformedParams()
	{
		$blob = $this->createSearchBlob([
			'search' => 'malicious search',
			'topic' => '0 UNION SELECT 1,2,3,4--',
			'minage' => '10; DROP TABLE test',
			'maxage' => '100',
			'searchtype' => 'malicious',
			'sort' => 'relevance',
			'sort_dir' => 'invalid_dir',
			'brd' => ['1', '2 UNION SELECT', 'not_a_num'],
			'evil_key' => 'evil_value',
		]);

		$searchParams = new SearchParams($blob);
		$data = $searchParams->get();

		$this->assertSame('malicious search', $data['search']);
		// Injected string topic evaluating to 0 is not set as valid positive topic
		$this->assertArrayNotHasKey('topic', $data);
		// Non-numeric minage is not set
		$this->assertArrayNotHasKey('minage', $data);
		$this->assertSame(100, $data['maxage']);
		$this->assertSame(1, $data['searchtype']);
		$this->assertSame('desc', $data['sort_dir']);
		$this->assertSame([1, 2], $data['brd']);
		$this->assertArrayNotHasKey('evil_key', $data);
	}

	public function testStandardAddRelevanceUsesParameterizedTopic()
	{
		$capturedQuery = null;
		$capturedParams = null;

		$mockDbSearch = $this->createMock(AbstractSearch::class);
		$mockResult = $this->createMock(AbstractResult::class);
		$mockResult->method('fetch_assoc')->willReturn(false);

		$mockDbSearch->method('search_query')->willReturnCallback(
			function ($identifier, $query, $params) use (&$capturedQuery, &$capturedParams, $mockResult) {
				$capturedQuery = $query;
				$capturedParams = $params;
				return $mockResult;
			}
		);

		$blob = $this->createSearchBlob([
			'topic' => 123,
			'sort' => 'relevance',
			'sort_dir' => 'desc',
		]);
		$searchParams = new SearchParams($blob);

		$reflection = new \ReflectionClass(Standard::class);
		$standard = $reflection->newInstanceWithoutConstructor();

		$searchParamsProp = $reflection->getProperty('_searchParams');
		$searchParamsProp->setAccessible(true);
		$searchParamsProp->setValue($standard, $searchParams);

		$dbSearchProp = $reflection->getProperty('_db_search');
		$dbSearchProp->setAccessible(true);
		$dbSearchProp->setValue($standard, $mockDbSearch);

		$topics = [];
		$standard->addRelevance($topics, 1, 0, 10);

		$this->assertNotNull($capturedQuery);
		$this->assertStringContainsString('{int:topic} AS id_topic', $capturedQuery);
		$this->assertSame(123, $capturedParams['topic']);
	}

	public function testStandardSubjectSearchUsesParameterizedTopic()
	{
		global $modSettings;

		$modSettings = [
			'postmod_active' => false,
			'search_match_words' => 1,
			'search_max_results' => 5,
		];

		$capturedQueries = [];
		$mockDb = $this->createMock(QueryInterface::class);
		$mockDb->method('support_ignore')->willReturn(true);

		$mockDbSearch = $this->createMock(AbstractSearch::class);
		$mockResult = $this->createMock(AbstractResult::class);
		$mockResult->method('affected_rows')->willReturn(0);

		$mockDbSearch->method('search_query')->willReturnCallback(
			function ($identifier, $query, $params) use (&$capturedQueries, $mockResult) {
				$capturedQueries[] = [
					'query' => $query,
					'params' => $params,
				];
				return $mockResult;
			}
		);

		$config = new ValuesContainer([
			'humungousTopicPosts' => 200,
			'shortTopicPosts' => 5,
			'banned_words' => [],
		]);

		$blob = $this->createSearchBlob([
			'topic' => 456,
		]);
		$searchParams = new SearchParams($blob);

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
		];

		$method = $reflection->getMethod('getSubjectResults');
		$method->setAccessible(true);
		$method->invoke($standard, 1, $searchWords, []);

		$this->assertNotEmpty($capturedQueries);
		$this->assertStringContainsString('t.id_topic = {int:search_topic_id}', $capturedQueries[0]['query']);
		$this->assertSame(456, $capturedQueries[0]['params']['search_topic_id']);
	}

	public function testSetTopicWithSearchSelection()
	{
		$searchParams = new SearchParams('');
		$params = [
			'search_selection' => 'topic',
			'sd_topic' => 789,
		];
		$searchParams->cleanParams($params);
		$searchParams->setTopic($params);

		$data = $searchParams->get();
		$this->assertSame(789, $data['topic']);
		$this->assertTrue($data['show_complete']);
	}

	public function testSetTopicWithDirectTopic()
	{
		$searchParams = new SearchParams('');
		$params = [
			'topic' => '321',
		];
		$searchParams->cleanParams($params);
		$searchParams->setTopic($params);

		$data = $searchParams->get();
		$this->assertSame(321, $data['topic']);
		$this->assertTrue($data['show_complete']);
	}

	public function testDecodesAndSanitizesSearchSelectionAndSdTopic()
	{
		$blob = $this->createSearchBlob([
			'search_selection' => ' topic ',
			'sd_topic' => '88',
			'sd_brd' => ['5', '0', '6', 'invalid', -1],
			'advanced' => '1',
		]);

		$searchParams = new SearchParams($blob);
		$data = $searchParams->get();

		$this->assertSame('topic', $data['search_selection']);
		$this->assertSame(88, $data['sd_topic']);
		$this->assertSame([5, 6, -1], $data['sd_brd']);
		$this->assertSame(1, $data['advanced']);
	}

	public function testRejectsNegativeAndZeroTopicsAndWhitespaceFields()
	{
		$blob = $this->createSearchBlob([
			'topic' => -5,
			'sd_topic' => 0,
			'advanced' => 0,
			'userspec' => '   ',
			'search_selection' => '   ',
			'sort' => '   ',
			'sort_dir' => 'ASC',
		]);

		$searchParams = new SearchParams($blob);
		$data = $searchParams->get();

		$this->assertArrayNotHasKey('topic', $data);
		$this->assertArrayNotHasKey('sd_topic', $data);
		$this->assertSame(0, $data['advanced']);
		$this->assertArrayNotHasKey('userspec', $data);
		$this->assertArrayNotHasKey('search_selection', $data);
		$this->assertArrayNotHasKey('sort', $data);
		$this->assertSame('asc', $data['sort_dir']);
	}
}
}
