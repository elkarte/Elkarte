<?php

/**
 * TestCase class for Event class.
 */
class TestEvent extends \PHPUnit\Framework\TestCase
{
	public function testEmpty()
	{
		$event = new \ElkArte\Event(new \ElkArte\Priority());

		// First basic: no events at the beginning
		$this->assertFalse($event->hasEvents());
	}

	public function testSingle()
	{
		$event = new \ElkArte\Event(new \ElkArte\Priority());

		// A properly formed event
		$event_def = array(
			'testing',
			array(
				'class_name',
				'method_name'
			),
			array(),
		);
		$event->add($event_def, 0);
		// Of course, now we have an event.
		$this->assertTrue($event->hasEvents());

		$added = $event->getEvents();
		// Only one
		$this->assertTrue(count($added) === 1);
		// And should be exactly the same as the original one
		$this->assertSame($added[0], $event_def);
	}

	public function testMultiple()
	{
		$event = new \ElkArte\Event(new \ElkArte\Priority());

		// A properly formed event
		$event_def = array(
			array(
				'testing1',
				array(
					'class_name1',
					'method_name1'
				),
				array(),
			),
			array(
				'testing2',
				array(
					'class_name2',
					'method_name2'
				),
				array(),
			),
		);
		foreach ($event_def as $e)
			$event->add($e, 0);
		// Of course, now we have events.
		$this->assertTrue($event->hasEvents());

		$added = $event->getEvents();
		// two of them actually
		$this->assertTrue(count($added) === 2);
		// And should be exactly the same as the original one
		$this->assertSame($added, $event_def);
	}

	/**
	 * Actually this should be tested in Priority I think
	 */
	public function testMultipleSorted()
	{
		$event = new \ElkArte\Event(new \ElkArte\Priority());

		// A properly formed event
		$event_def = array(
			array(
				'testing1',
				array(
					'class_name1',
					'method_name1'
				),
				array(),
			),
			array(
				'testing2',
				array(
					'class_name2',
					'method_name2'
				),
				array(),
			),
		);

		$event->add($event_def[0], 10);
		$event->add($event_def[1], 0);

		// Of course, now we have events.
		$this->assertTrue($event->hasEvents());

		$added = $event->getEvents();
		// two of them actually
		$this->assertTrue(count($added) === 2);
		// And should be similar to the original one... inverted
		$this->assertSame($added, array($event_def[1], $event_def[0]));
	}
}
