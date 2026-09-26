<?php

namespace ElkArte\Controller;

use ElkArte\EventManager;
use ElkArte\Languages\Loader;
use ElkArte\User;
use tests\ElkArteCommonSetupTest;

class MessageIndexOrderTest extends ElkArteCommonSetupTest
{
	protected $backupGlobalsExcludeList = ['user_info'];

	protected function setUp(): void
	{
		global $board, $txt;
		parent::setUp();
		$board = 1;
		loadBoard();
		$lang = new Loader('english', $txt, database());
		$lang->load('Post');
	}

	public function testMessageOrder()
	{
		global $context, $modSettings;

		// We need enough topics to trigger the "fake_ascending" optimization if we want to test it,
		// but let's first test the default case.
		// By default, last_post DESC is expected.
		
		// Create some dummy topics if needed, but let's see what we have first.
		$controller = new MessageIndex(new EventManager());
		$controller->setUser(User::$info);
		$controller->pre_dispatch();
		$controller->action_index();

		$topics = $context['topics'];
		$topic_ids = array_keys($topics);
		
		if (count($topic_ids) > 1)
		{
			// Check if newest (higher id_last_msg) is first
			$first_topic = reset($topics);
			$last_topic = end($topics);
			
			// For last_post DESC, first_topic's id_last_msg should be > last_topic's
			$this->assertGreaterThan($last_topic['id_last_msg'], $first_topic['id_last_msg'], 'Newest topic should be first');
		}
		else
		{
			$this->markTestSkipped('Not enough topics to test order');
		}
	}
}
