<?php

/**
 * TestCase class for the Message Index Controller
 */
class TestMessageIndex extends ElkArteCommonSetupTest
{
	protected $backupGlobalsBlacklist = ['user_info'];

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	public function setUp()
	{
		global $board;

		parent::setUp();

		new ElkArte\Themes\ThemeLoader();

		$board = 1;
		loadBoard();
	}

	/**
	 * Test the settings for MessageIndex
	 */
	public function testMessageActionIndex()
	{
		global $context, $board_info;

		$controller = new \ElkArte\Controller\MessageIndex(new \ElkArte\EventManager());
		$controller->setUser(\ElkArte\User::$info);
		$controller->pre_dispatch();
		$controller->action_index();

		// Lets see some items loaded into context, there should some data
		$this->assertEquals('topic_listing', $context['sub_template']);
		$this->assertEquals($context['name'], $board_info['name']);
		$this->assertEquals(array('1'), array_keys($context['topics']));
		$this->assertEquals('Welcome to ElkArte!', $context['topics'][1]['subject']);
	}
}
