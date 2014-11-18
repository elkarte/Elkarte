<?php

/**
 * TestCase class for the Calendar_Event class.
 */
class TestCalendar_Event extends PHPUnit_Framework_TestCase
{
	/**
	 * To avoid duplicated function declarations, we need an empty Calendar.subs.php
	 */
	public function setUpBeforeClass()
	{
		rename(SUBSDIR . '/Calendar.subs.php', SUBSDIR . '/Calendar_tmp.subs.php');
		touch(SUBSDIR . '/Calendar.subs.php');
	}

	/**
	 * Better restore it before leaving
	 */
	public function tearDownAfterClass()
	{
		unlink(SUBSDIR . '/Calendar.subs.php');
		rename(SUBSDIR . '/Calendar_tmp.subs.php', SUBSDIR . '/Calendar.subs.php');
	}

	/**
	 * null or -1 means new event, any other number, means existing one
	 */
	public function testNew()
	{
		$event = new Calendar_Event(null, array());
		$this->assertTrue($event->isNew());

		$event = new Calendar_Event(-1, array());
		$this->assertTrue($event->isNew());

		$event = new Calendar_Event(1, array());
		$this->assertFalse($event->isNew());
	}

	public function testStarter()
	{
		$event = new Calendar_Event(1, array());

		// Guest means not the starter
		$this->assertFalse($event->isStarter(0));

		// According to the fake getEventPoster below, the poster is 1
		$this->assertTrue($event->isStarter(1));
		$this->assertFalse($event->isStarter(2));
	}

	/**
	 * @expectedException Exception
	 * @expectedExceptionMessage removeEvent called with id = 1
	 */
	public function testRemove()
	{
		$event = new Calendar_Event(1, array());
		$event->remove();
	}

	/**
	 * @expectedException Elk_Exception
	 * @expectedExceptionMessage no_span
	 */
	public function testValidateNoSpan()
	{
		$event = new Calendar_Event(1, array());
		$event->validate(array('span' => 1));
	}

	/**
	 * @expectedException Elk_Exception
	 * @expectedExceptionMessage invalid_days_numb
	 */
	public function testValidateInvalidSpan1()
	{
		$event = new Calendar_Event(1, array('cal_allowspan' => 2));
		$event->validate(array('span' => -1));
	}

	/**
	 * @expectedException Elk_Exception
	 * @expectedExceptionMessage invalid_days_numb
	 */
	public function testValidateInvalidSpan2()
	{
		$event = new Calendar_Event(1, array('cal_allowspan' => 2));
		$event->validate(array('span' => 5));
	}

	/**
	 * @expectedException Elk_Exception
	 * @expectedExceptionMessage event_month_missing
	 */
	public function testValidateNotDelete1()
	{
		$event = new Calendar_Event(1, array('cal_minyear' => 2012, 'cal_maxyear' => 2015));
		// No month passed => Elk_Exception
		$event->validate(array());
	}

	/**
	 * @expectedException Elk_Exception
	 * @expectedExceptionMessage event_year_missing
	 */
	public function testValidateNotDelete2()
	{
		$event = new Calendar_Event(1, array('cal_minyear' => 2012, 'cal_maxyear' => 2015));
		// No year passed => Elk_Exception
		$event->validate(array('month' => 1));
	}

	/**
	 * @expectedException Elk_Exception
	 * @expectedExceptionMessage invalid_month
	 */
	public function testValidateNotDelete3()
	{
		$event = new Calendar_Event(1, array('cal_minyear' => 2012, 'cal_maxyear' => 2015));
		// Negative months are not allowed
		$event->validate(array('month' => -1, 'year' => 2013));
	}

	/**
	 * @expectedException Elk_Exception
	 * @expectedExceptionMessage invalid_month
	 */
	public function testValidateNotDelete4()
	{
		$event = new Calendar_Event(1, array('cal_minyear' => 2012, 'cal_maxyear' => 2015));
		// Zero is not a month
		$event->validate(array('month' => 0, 'year' => 2013));
	}

	/**
	 * @expectedException Elk_Exception
	 * @expectedExceptionMessage invalid_month
	 */
	public function testValidateNotDelete5()
	{
		$event = new Calendar_Event(1, array('cal_minyear' => 2012, 'cal_maxyear' => 2015));
		// An years haz only 12 months...
		$event->validate(array('month' => 13, 'year' => 2013));
	}

	/**
	 * @expectedException Elk_Exception
	 * @expectedExceptionMessage invalid_year
	 */
	public function testValidateNotDelete6()
	{
		$event = new Calendar_Event(1, array('cal_minyear' => 2012, 'cal_maxyear' => 2015));
		// Too low year
		$event->validate(array('month' => 1, 'year' => 2011));
	}

	/**
	 * @expectedException Elk_Exception
	 * @expectedExceptionMessage invalid_year
	 */
	public function testValidateNotDelete7()
	{
		$event = new Calendar_Event(1, array('cal_minyear' => 2012, 'cal_maxyear' => 2015));
		// Too far away in the future
		$event->validate(array('month' => 1, 'year' => 2017));
	}

	/**
	 * @expectedException Elk_Exception
	 * @expectedExceptionMessage event_day_missing
	 */
	public function testValidateNotDelete8()
	{
		$event = new Calendar_Event(1, array('cal_minyear' => 2012, 'cal_maxyear' => 2015));
		// No day => Elk_Exception
		$event->validate(array('month' => 1, 'year' => 2013));
	}

	/**
	 * @expectedException Elk_Exception
	 * @expectedExceptionMessage event_title_missing
	 */
	public function testValidateNotDelete9()
	{
		$event = new Calendar_Event(1, array('cal_minyear' => 2012, 'cal_maxyear' => 2015));
		// No title => Elk_Exception
		$event->validate(array('month' => 1, 'year' => 2013, 'day' => 1));
	}

	/**
	 * @expectedException Elk_Exception
	 * @expectedExceptionMessage event_title_missing
	 */
	public function testValidateNotDelete10()
	{
		$event = new Calendar_Event(1, array('cal_minyear' => 2012, 'cal_maxyear' => 2015));
		// Subject but no evtitle => Elk_Exception
		$event->validate(array('month' => 1, 'year' => 2013, 'day' => 1, 'subject' => 'string'));
	}

	/**
	 * @expectedException Elk_Exception
	 * @expectedExceptionMessage invalid_date
	 */
	public function testValidateNotDelete11()
	{
		$event = new Calendar_Event(1, array('cal_minyear' => 2012, 'cal_maxyear' => 2015));
		// No need to test the PHP checkdata function, so just one single bad date
		$event->validate(array('month' => 2, 'year' => 2013, 'day' => 30, 'evtitle' => 'string', 'subject' => 'string'));
	}

	/**
	 * @expectedException Elk_Exception
	 * @expectedExceptionMessage no_event_title
	 */
	public function testValidateNotDelete12()
	{
		$event = new Calendar_Event(1, array('cal_minyear' => 2012, 'cal_maxyear' => 2015));
		// A evtitle made up of spaces should be trimmed and result in an empty string
		$event->validate(array('month' => 2, 'year' => 2013, 'day' => 30, 'evtitle' => '    ', 'subject' => 'string'));
	}

	/**
	 * Testing (ev)title/subject trimming and adjustments.
	 */
	public function testValidateTitle()
	{
		$input = array('month' => 1, 'year' => 2013, 'day' => 1, 'subject' => 'string');

		$event = new Calendar_Event(1, array('cal_minyear' => 2012, 'cal_maxyear' => 2015));

		// If no evtitle, but subject present, then evtitle == subject
		$result = $event->validate($input);
		$this->assertSame($input['subject'], $result['evtitle']);

		$input['evtitle'] = 'This must be an evtitle longer than one hundred characters, so that it is cut to less than one hundred characters';
		// Long titles are cut
		$result = $event->validate($input);
		$this->assertSame('This must be an evtitle longer than one hundred characters, so that it is cut to less than one hundr', $result['evtitle']);

		$input['evtitle'] = 'Not sure why, but ; are removed from evtitles';
		// Semi-colons are stripped from evtitles
		$result = $event->validate($input);
		$this->assertSame('Not sure why, but  are removed from evtitles', $result['evtitle']);
	}
}

/**
 * Redefine some of the functions used by the class
 */
function insertEvent($array)
{

}

/**
 * Mock of the real removeEvent function
 * (Calendar.subs.php)
 * throws an Exception with message:
 *   'removeEvent called with id = ' . $id
 */
function removeEvent($id)
{
	throw new Exception('removeEvent called with id = ' . $id);
}

function getEventProperties($event, $calendar_only)
{

}

function modifyEvent($event, $options)
{

}

/**
 * Mock of the real getEventPoster function
 * (Calendar.subs.php)
 * always returns 1
 */
function getEventPoster($event)
{
	return 1;
}