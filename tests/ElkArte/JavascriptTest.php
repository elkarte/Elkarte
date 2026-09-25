<?php

namespace ElkArte;

use ElkArte\Themes\Javascript;
use PHPUnit\Framework\TestCase;

class JavascriptTest extends TestCase
{
	protected $javascript;

	protected function setUp(): void
	{
		$this->javascript = new Javascript();
	}

	public function testAddInlineJavascriptPreventsDuplicates()
	{
		$code = 'console.log("test");';
		
		// Add it twice
		$this->javascript->addInlineJavascript($code, false);
		$this->javascript->addInlineJavascript($code, false);
		
		// Use reflection to check private property js_inline
		$reflection = new \ReflectionClass($this->javascript);
		$property = $reflection->getProperty('js_inline');
		$property->setAccessible(true);
		$js_inline = $property->getValue($this->javascript);
		
		// Standard is standard/0
		$this->assertCount(1, $js_inline['standard'], 'Duplicate inline JS was not prevented');
		$this->assertEquals($code, reset($js_inline['standard']));
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
