<?php

namespace ElkArte;

use PHPUnit\Framework\TestCase;

/**
 * Test module class with various method signatures for testing EventManager.
 */
class TestEventManagerModule
{
	public static $noParamCalls = 0;
	public static $withParamCalls = 0;
	public static $lastParam = null;

	public static function hooks(EventManager $eventsManager): array
	{
		return [
			['test_no_param', ['\\ElkArte\\TestEventManagerModule', 'hookNoParam'], ['some_dep']],
			['test_with_param', ['\\ElkArte\\TestEventManagerModule', 'hookWithParam'], ['value']],
		];
	}

	public function hookNoParam()
	{
		self::$noParamCalls++;
	}

	public function hookWithParam($value)
	{
		self::$withParamCalls++;
		self::$lastParam = $value;
	}
}

/**
 * TestCase class for EventManager class.
 */
class EventManagerTest extends TestCase
{
	protected $backupGlobalsExcludeList = ['user_info'];

	public static function setUpBeforeClass(): void
	{
		if (!defined('ELK'))
		{
			define('ELK', '1');
		}

		require_once(__DIR__ . '/../../sources/Load.php');
	}

	protected function setUp(): void
	{
		TestEventManagerModule::$noParamCalls = 0;
		TestEventManagerModule::$withParamCalls = 0;
		TestEventManagerModule::$lastParam = null;
	}

	public function testTriggerEmptyEventReturnsFalse()
	{
		$manager = new EventManager();
		$result = $manager->trigger('unregistered_event');
		$this->assertFalse($result);
	}

	public function testTriggerWithNoParametersMethodClearsDependencies()
	{
		$manager = new EventManager();
		$manager->registerClasses(['\\ElkArte\\TestEventManagerModule']);

		// First trigger: inspects reflection and caches 0 parameter count
		$manager->trigger('test_no_param', ['some_dep' => 'ignored']);
		$this->assertSame(1, TestEventManagerModule::$noParamCalls);

		// Second trigger: uses cached parameter count without reflection error
		$manager->trigger('test_no_param', ['some_dep' => 'ignored_again']);
		$this->assertSame(2, TestEventManagerModule::$noParamCalls);
	}

	public function testTriggerWithParametersPassesDependenciesAndCaches()
	{
		$manager = new EventManager();
		$manager->registerClasses(['\\ElkArte\\TestEventManagerModule']);

		// First trigger: inspects reflection, caches 1 parameter count, passes dependency
		$manager->trigger('test_with_param', ['value' => 'first_call']);
		$this->assertSame(1, TestEventManagerModule::$withParamCalls);
		$this->assertSame('first_call', TestEventManagerModule::$lastParam);

		// Second trigger: uses cached signature
		$manager->trigger('test_with_param', ['value' => 'second_call']);
		$this->assertSame(2, TestEventManagerModule::$withParamCalls);
		$this->assertSame('second_call', TestEventManagerModule::$lastParam);
	}

	public function testTriggerResolvesDependenciesFromSourceController()
	{
		$manager = new EventManager();
		$source = new class($manager) extends AbstractController {
			public $value = 'controller_value';
			public function action_index() {}
		};
		$manager->setSource($source);
		$manager->registerClasses(['\\ElkArte\\TestEventManagerModule']);

		$manager->trigger('test_with_param');
		$this->assertSame(1, TestEventManagerModule::$withParamCalls);
		$this->assertSame('controller_value', TestEventManagerModule::$lastParam);
	}

	public function testTriggerNonexistentClassReturnsFalse()
	{
		$manager = new EventManager();
		$manager->register('invalid_event', ['invalid_event', ['\\ElkArte\\NonExistentClass12345', 'someMethod', 0]]);

		$result = $manager->trigger('invalid_event');
		$this->assertFalse($result);
	}
}
