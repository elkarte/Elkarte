<?php

use ElkArte\Controller\MessageIndex;
use ElkArte\EventManager;
use ElkArte\User;
use ElkArte\Languages\Loader;


/**
 * TestCase class for the Message Index Controller
 */
class TestMessageIndex extends ElkArteCommonSetupTest
{
	protected $backupGlobalsBlacklist = ['user_info'];

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	protected function setUp(): void
	{
		global $board, $txt;

		parent::setUp();

		$board = 1;
		loadBoard();

		$lang = new Loader('english', $txt, database());
		$lang->load('Post');
	}

	/**
	 * Test the settings for MessageIndex
	 */
	public function testMessageActionIndex()
	{
		global $context, $board_info;

		$controller = new MessageIndex(new EventManager());
		$controller->setUser(User::$info);
		$controller->pre_dispatch();
		$controller->action_index();

		// Lets see some items loaded into context, there should some data
		$this->assertEquals('topic_listing', $context['sub_template']);
		$this->assertEquals($context['name'], $board_info['name']);
		$this->assertEquals(array('1'), array_keys($context['topics']));
		$this->assertEquals('Welcome to ElkArte!', $context['topics'][1]['subject']);
	}
}
