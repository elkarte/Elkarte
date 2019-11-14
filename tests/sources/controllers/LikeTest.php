<?php

/**
 * TestCase class for the Likes Controller
 */
class TestLike extends ElkArteCommonSetupTest
{
	protected $backupGlobalsBlacklist = ['user_info'];

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	public function setUp()
	{
		parent::setup();

		new ElkArte\Themes\ThemeLoader();
		theme()->getTemplates()->loadLanguageFile('Profile', 'english', true, true);
		theme()->getTemplates()->loadLanguageFile('Errors', 'english', true, true);
	}

	/**
	 * Test the showing the Likes Listing
	 */
	public function testShowLikes()
	{
		global $context, $modSettings;

		$modSettings['likes_enabled'] = 1;
		$context['profile_menu_name'] = 'menu_data_view_likes';
		require_once(SUBSDIR . '/Profile.subs.php');

		$controller = new \ElkArte\Controller\Likes(new \ElkArte\EventManager());
		$controller->setUser(\ElkArte\User::$info);
		$controller->pre_dispatch();
		$controller->action_showProfileLikes();

		// Lets see some items loaded into context, as createlist will have run
		$this->assertEquals('Posts you liked', $context['menu_data_view_likes']['tab_data']['title']);
		$this->assertEquals(4, $context['view_likes']['num_columns']);
		$this->assertEquals('Likes', $context['view_likes']['title']);
	}

	/**
	 * Test liking a post
	 */
	public function testLikePost()
	{
		global $modSettings, $context;

		// Set the form
		$_req = \ElkArte\HttpReq::instance();
		$_req->query['msg'] = 1;
		$_req->query['xml'] = '';
		$_req->query['api'] = 'json';

		// Trick the session
		$this->setSession();

		// Enabled but no mentions please
		$modSettings['likes_enabled'] = 1;
		$modSettings['mentions_enabled'] = 0;

		// Make a like
		$controller = new \ElkArte\Controller\Likes(new \ElkArte\EventManager());
		$controller->setUser(\ElkArte\User::$info);
		$controller->pre_dispatch();
		$controller->action_likepost_api();

		$this->assertEquals(1, $context['json_data']['count']);
	}
}
