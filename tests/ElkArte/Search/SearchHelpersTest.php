<?php

namespace ElkArte\Search;

use PHPUnit\Framework\TestCase;

class SearchHelpersTest extends TestCase
{
	/**
	 * Test user test cases
	 */
	public function testBasicNaturalQueries()
	{
		// 1. OR and minus negation
		$result = SearchHelpers::textToSearchQuery('leather jacket OR coat -brown');
		$this->assertEquals('leather jacket | coat !brown', $result);

		// 2. Phrase and "not" negation
		$result = SearchHelpers::textToSearchQuery('PHP framework "web development" not laravel');
		$this->assertEquals('php framework "web development" !laravel', $result);

		// 3. Explicit AND operator
		$result = SearchHelpers::textToSearchQuery('fast & furious');
		$this->assertEquals('fast & furious', $result);
	}

	/**
	 * Test grouping and logical operators
	 */
	public function testGroupingAndLogicalOperators()
	{
		$result = SearchHelpers::textToSearchQuery('(python OR php) AND (framework OR library) -legacy');
		$this->assertEquals('(python | php) & (framework | library) !legacy', $result);

		$result = SearchHelpers::textToSearchQuery('database OR (sql AND postgresql)');
		$this->assertEquals('database | (sql & postgresql)', $result);

		$result = SearchHelpers::textToSearchQuery('not (windows OR macos)');
		$this->assertEquals('!(windows | macos)', $result);
	}

	/**
	 * Test quoted phrases and phrase negation
	 */
	public function testPhrasesAndNegatedPhrases()
	{
		$result = SearchHelpers::textToSearchQuery('-"exact phrase to exclude"');
		$this->assertEquals('!"exact phrase to exclude"', $result);

		$result = SearchHelpers::textToSearchQuery('not "exact phrase to exclude"');
		$this->assertEquals('!"exact phrase to exclude"', $result);

		$result = SearchHelpers::textToSearchQuery('"open source" OR "proprietary software"');
		$this->assertEquals('"open source" | "proprietary software"', $result);
	}

	/**
	 * Test HTML entities and smart quotes
	 */
	public function testEntitiesAndSmartQuotes()
	{
		$result = SearchHelpers::textToSearchQuery('&quot;open source&quot; &amp; &quot;community&quot;');
		$this->assertEquals('"open source" & "community"', $result);

		$result = SearchHelpers::textToSearchQuery('“smart quotes” and ‘single quotes’');
		$this->assertEquals('"smart quotes" & single quotes', $result);
	}

	/**
	 * Test number and identifier normalization
	 */
	public function testNumberAndIdentifierNormalization()
	{
		$result = SearchHelpers::textToSearchQuery('version 2.0 on node.js with SSN 123-45-6789');
		$this->assertEquals('version 2_0 on node_js with ssn 123_45_6789', $result);
	}

	/**
	 * Test stopword / blocklist filtering
	 */
	public function testStopwordFiltering()
	{
		$stopwords = ['in', 'the', 'is', 'it'];

		// Standalone stopwords are removed
		$result = SearchHelpers::textToSearchQuery('leather jacket in the rain', $stopwords);
		$this->assertEquals('leather jacket rain', $result);

		// Stopwords inside quoted phrases are preserved
		$result = SearchHelpers::textToSearchQuery('"to be or not to be in the rain"', $stopwords);
		$this->assertEquals('"to be or not to be in the rain"', $result);

		// All stopwords should return empty string
		$result = SearchHelpers::textToSearchQuery('in the is it', $stopwords);
		$this->assertEquals('', $result);
	}

	/**
	 * Test unbalanced syntax handling (quotes, parentheses, dangling operators)
	 */
	public function testUnbalancedSyntaxAndDanglingOperators()
	{
		// Unclosed quotes are balanced
		$result = SearchHelpers::textToSearchQuery('hello "unclosed phrase');
		$this->assertEquals('hello "unclosed phrase"', $result);

		// Unclosed parentheses are balanced
		$result = SearchHelpers::textToSearchQuery('(open parenthesis without close');
		$this->assertEquals('(open parenthesis without close)', $result);

		// Extra closing parentheses are dropped
		$result = SearchHelpers::textToSearchQuery('extra close) without open');
		$this->assertEquals('extra close without open', $result);

		// Dangling and duplicate operators are sanitized
		$result = SearchHelpers::textToSearchQuery('OR AND foo | & bar -');
		$this->assertEquals('foo | bar', $result);

		$result = SearchHelpers::textToSearchQuery('( | foo | )');
		$this->assertEquals('(foo)', $result);
	}

	/**
	 * Test cleanString method
	 */
	public function testCleanString()
	{
		$cleaned = SearchHelpers::cleanString('<b>Hello</b> &amp; World! 123-456 "my phrase"');
		$this->assertEquals('hello world 123_456 "my phrase"', $cleaned);
	}

	/**
	 * Test extractKeywords method
	 */
	public function testExtractKeywords()
	{
		$keywords = SearchHelpers::extractKeywords('PHP framework "web development" not laravel');
		$this->assertContains('web development', $keywords);
		$this->assertContains('php', $keywords);
		$this->assertContains('framework', $keywords);
		$this->assertContains('not', $keywords);
		$this->assertContains('laravel', $keywords);

		// With filler phrases
		$keywords2 = SearchHelpers::extractKeywords('i am looking for leather jackets');
		$this->assertContains('leather', $keywords2);
		$this->assertContains('jackets', $keywords2);
		$this->assertNotContains('looking', $keywords2);
	}

	/**
	 * Test conversational filler phrase filtering
	 */
	public function testFillerPhraseFiltering()
	{
		// 'i am looking for'
		$result = SearchHelpers::textToSearchQuery('i am looking for leather jacket OR coat -brown');
		$this->assertEquals('leather jacket | coat !brown', $result);

		// 'im looking for' / "i'm looking for"
		$result = SearchHelpers::textToSearchQuery('im looking for php 8.2 installation');
		$this->assertEquals('php 8_2 installation', $result);

		$result = SearchHelpers::textToSearchQuery("i'm looking for mysql database error");
		$this->assertEquals('mysql database error', $result);

		// 'find posts that' / 'find posts about' / 'find posts'
		$result = SearchHelpers::textToSearchQuery('find posts about "open source" not windows');
		$this->assertEquals('"open source" !windows', $result);

		$result = SearchHelpers::textToSearchQuery('find posts that mention postgresql');
		$this->assertEquals('mention postgresql', $result);

		// 'search for' / 'show me' / 'can you find' / 'where is'
		$result = SearchHelpers::textToSearchQuery('search for security vulnerabilities');
		$this->assertEquals('security vulnerabilities', $result);

		$result = SearchHelpers::textToSearchQuery('show me posts about node.js');
		$this->assertEquals('node_js', $result);

		$result = SearchHelpers::textToSearchQuery('can you find database connection issues');
		$this->assertEquals('database connection issues', $result);

		$result = SearchHelpers::textToSearchQuery('where is the settings configuration');
		$this->assertEquals('settings configuration', $result);
	}

	/**
	 * Test that filler phrases inside quoted phrases are preserved
	 */
	public function testFillerPhrasesInsideQuotesPreserved()
	{
		$result = SearchHelpers::textToSearchQuery('"where is the love" in lyrics');
		$this->assertEquals('"where is the love" lyrics', $result);

		$result = SearchHelpers::textToSearchQuery('"show me" the money');
		$this->assertEquals('"show me" money', $result);
	}

	/**
	 * Test short queries and length threshold handling
	 */
	public function testShortQueriesAndLengthThreshold()
	{
		// Very short query equal to filler phrase should not be wiped to empty
		$result = SearchHelpers::textToSearchQuery('where is');
		$this->assertEquals('where is', $result);

		$result = SearchHelpers::textToSearchQuery('show me');
		$this->assertEquals('show me', $result);

		$result = SearchHelpers::textToSearchQuery('look for');
		$this->assertEquals('look for', $result);
	}

	/**
	 * Test filterFillerPhrases standalone method
	 */
	public function testFilterFillerPhrasesStandalone()
	{
		$filtered = SearchHelpers::filterFillerPhrases('find topics about artificial intelligence');
		$this->assertEquals('artificial intelligence', $filtered);

		// Custom flat filler phrases
		$customPhrases = ['custom prefix', 'please find'];
		$filteredCustom = SearchHelpers::filterFillerPhrases('please find my account details', $customPhrases);
		$this->assertEquals('my account details', $filteredCustom);

		// Custom nested filler phrases
		$nestedPhrases = [
			'look up' => [
				'posts' => ['', 'about', 'for'],
				'details' => ['', 'about'],
			],
		];
		$filteredNested = SearchHelpers::filterFillerPhrases('look up posts about system architecture', $nestedPhrases);
		$this->assertEquals('system architecture', $filteredNested);
	}

	/**
	 * Test expandFillerPhrases helper
	 */
	public function testExpandFillerPhrases()
	{
		$input = [
			'search for' => [
				'posts' => ['', 'about', 'with'],
				'topics' => ['', 'about'],
				'',
			],
			'where is',
		];

		$expanded = SearchHelpers::expandFillerPhrases($input);
		$this->assertContains('search for posts', $expanded);
		$this->assertContains('search for posts about', $expanded);
		$this->assertContains('search for posts with', $expanded);
		$this->assertContains('search for topics', $expanded);
		$this->assertContains('search for topics about', $expanded);
		$this->assertContains('search for', $expanded);
		$this->assertContains('where is', $expanded);
	}

	/**
	 * Test getDefaultFillerPhrases
	 */
	public function testGetDefaultFillerPhrases()
	{
		$filler = SearchHelpers::getDefaultFillerPhrases();
		$this->assertIsArray($filler);
		$this->assertContains('i am looking for', $filler);
		$this->assertContains('find posts about', $filler);
		$this->assertContains('show me', $filler);
		$this->assertContains('where is', $filler);
	}

	/**
	 * Test getDefaultBlocklistedWords
	 */
	public function testGetDefaultBlocklistedWords()
	{
		$blocklist = SearchHelpers::getDefaultBlocklistedWords();
		$this->assertIsArray($blocklist);
		$this->assertContains('the', $blocklist);
		$this->assertContains('quote', $blocklist);
		$this->assertContains('www', $blocklist);
	}

	/**
	 * Test procedural function wrapper
	 */
	public function testProceduralFunctionWrapper()
	{
		$result = textToSearchQuery('i am looking for fast & furious');
		$this->assertEquals('fast & furious', $result);
	}
}
