<?php

namespace ElkArte;

use ElkArte\Themes\Javascript;
use PHPUnit\Framework\TestCase;

class JavascriptTest extends TestCase
{
	protected $javascript;

	/** @var array */
	protected $originalContext = [];

	protected function setUp(): void
	{
		$this->originalContext = $GLOBALS['context'] ?? [];

		// Isolate JS context state so tests are deterministic.
		$GLOBALS['context']['js_files'] = [];
		$GLOBALS['context']['js_vars'] = [];
		$GLOBALS['context']['js_inline'] = ['standard' => [], 'defer' => []];

		$this->javascript = new Javascript();
	}

	protected function tearDown(): void
	{
		$GLOBALS['context'] = $this->originalContext;
	}

	public function testAddInlineJavascriptPreventsDuplicates()
	{
		$code = 'console.log("test");';

		// Baseline before adding anything in this test.
		$reflection = new \ReflectionClass($this->javascript);
		$property = $reflection->getProperty('js_inline');
		$property->setAccessible(true);
		$before = $property->getValue($this->javascript);
		$beforeCount = count($before['standard']);

		// Add it twice.
		$this->javascript->addInlineJavascript($code, false);
		$this->javascript->addInlineJavascript($code, false);

		$js_inline = $property->getValue($this->javascript);

		$this->assertCount($beforeCount + 1, $js_inline['standard'], 'Duplicate inline JS was not prevented');
		$this->assertArrayHasKey(md5($code), $js_inline['standard']);
		$this->assertEquals($code, $js_inline['standard'][md5($code)]);
	}

	public function testAddInlineJavascriptDifferentDefer()
	{
		$code = 'console.log("test");';

		// Add it once for standard, once for defer
		$this->javascript->addInlineJavascript($code, false);
		$this->javascript->addInlineJavascript($code, true);

		$reflection = new \ReflectionClass($this->javascript);
		$property = $reflection->getProperty('js_inline');
		$property->setAccessible(true);
		$js_inline = $property->getValue($this->javascript);

		$this->assertCount(1, $js_inline['standard']);
		$this->assertCount(1, $js_inline['defer']);
	}

	public function testAddJavascriptVarPreventsDuplicates()
	{
		$vars = ['myVar' => 'value1'];

		$this->javascript->addJavascriptVar($vars);
		$this->javascript->addJavascriptVar(['myVar' => 'value2']); // Should overwrite

		$reflection = new \ReflectionClass($this->javascript);
		$property = $reflection->getProperty('js_vars');
		$property->setAccessible(true);
		$js_vars = $property->getValue($this->javascript);

		$this->assertCount(1, $js_vars);
		$this->assertEquals('value2', $js_vars['myVar']);
	}
}
