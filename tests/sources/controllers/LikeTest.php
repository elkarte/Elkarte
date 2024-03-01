<?php
/**
 * TestCase class for the Likes Controller
 */

use ElkArte\Controller\Likes;
use ElkArte\EventManager;
use ElkArte\Helper\HttpReq;
use ElkArte\Languages\Loader;
use ElkArte\Menu\Menu;
use ElkArte\User;
use tests\ElkArteCommonSetupTest;

class LikeTest extends ElkArteCommonSetupTest
{
	protected $backupGlobalsExcludeList = ['user_info'];

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	protected function setUp(): void
	{
		global $txt;
		parent::setUp();

		new ElkArte\Themes\ThemeLoader();
		$lang = new Loader('english', $txt, database());
		$lang->load('Profile+Errors');
	}

	/**
	 * Test the showing the Likes Listing
	 */
	public function testShowLikes()
	{
		global $context, $modSettings;

		$modSettings['likes_enabled'] = 1;
		$context['profile_menu_name'] = 'menu_data_1';
		$context['menu_data_1']['object'] = new Menu();

		require_once(SUBSDIR . '/Profile.subs.php');

		$controller = new Likes(new EventManager());
		$controller->setUser(User::$info);
		$controller->pre_dispatch();
		$controller->action_showProfileLikes();

		// Lets see some items loaded into context, as createlist will have run
		$this->assertEquals('Likes', $context['menu_data_1']['tab_data']['title']);
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
		$_req = HttpReq::instance();
		$_req->query['msg'] = 1;
		$_req->query['xml'] = '';
		$_req->query['api'] = 'json';

		// Trick the session
		$this->setSession();

		// Enabled but no mentions please
		$modSettings['likes_enabled'] = 1;
		$modSettings['mentions_enabled'] = 0;

		// Make a like
		$controller = new Likes(new EventManager());
		$controller->setUser(User::$info);
		$controller->pre_dispatch();
		$controller->action_likepost_api();

		$this->assertEquals(1, $context['json_data']['count']);
	}
}
