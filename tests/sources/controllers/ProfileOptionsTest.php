<?php

/**
 * TestCase class for the Profile Options Controller
 */

use ElkArte\EventManager;
use ElkArte\Languages\Loader;
use ElkArte\MembersList;
use ElkArte\Menu\Menu;
use ElkArte\Profile\ProfileOptions;
use ElkArte\User;
use tests\ElkArteCommonSetupTest;

class ProfileOptionsTest extends ElkArteCommonSetupTest
{
	protected $backupGlobalsExcludeList = ['user_info'];

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	protected function setUp(): void
	{
		global $context, $cur_profile, $txt;

		parent::setUp();

		new ElkArte\Themes\ThemeLoader();
		$lang = new Loader('english', $txt, database());
		$lang->load('Profile');

		// Some tricks, maybe
		require_once(SUBSDIR . '/Profile.subs.php');
		MembersList::load('1', false, 'profile');
		$cur_profile = MembersList::get('1');
		$context['user']['is_owner'] = true;
		$context['id_member'] = 1;

		$db = database();

		$db->insert('',
			'{db_prefix}notifications_pref',
			array(
				'id_member' => 'int',
				'notification_type' => 'string',
				'mention_type' => 'string',
			),
			array(
				array(
					'id_member' => 1,
					'notification_type' => json_encode(['email', 'notification']),
					'mention_type' => 'likemsg'
				),
				array(
					'id_member' => 1,
					'notification_type' => json_encode(['email']),
					'mention_type' => 'mentionmem'
				),
				array(
					'id_member' => 2,
					'notification_type' => json_encode(['email', 'notification']),
					'mention_type' => 'mentionmem'
				),
				array(
					'id_member' => 2,
					'notification_type' => json_encode(['email']),
					'mention_type' => 'likemsg'
				),
			),
			array('id_member', 'mention_type')
		);
	}

	/**
	 * cleanup data we no longer need at the end of the tests in this class.
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	protected function tearDown(): void
	{
		$db = database();
		$db->query('', '
			DELETE FROM {db_prefix}notifications_pref', []);
	}

	/**
	 * Test the settings for profile account
	 */
	public function testProfileAccount()
	{
		global $context;

		$controller = new ProfileOptions(new EventManager());
		$controller->setUser(User::$info);
		$controller->pre_dispatch();
		$controller->action_account();

		// Lets see some items loaded into $context['profile_fields']
		$this->assertNotNull($context['profile_fields']);
		$this->assertEquals('test_admin', $context['profile_fields']['member_name']['value']);
	}

	/**
	 * Not run yet, the avatar data 'name' is not loading?
	 * @todo figure that out and change name to testForumProfile
	 */
	public function ForumProfile()
	{
		global $context;

		$controller = new ProfileOptions(new EventManager());
		$controller->setUser(User::$info);
		$controller->pre_dispatch();
		$controller->action_forumProfile();

		// Lets see some items loaded into $context['profile_fields']
		$this->assertEquals('signature_modify', $context['profile_fields']['signature']['callback_func']);
	}

	public function testNotification()
	{
		global $context;

		$context['menu_item_selected'] = 'notification';
		$context['token_check'] = 'profile-u1';
		$context['profile-u1_token_var'] = 'profile-u1';
		$context['profile-u1_token'] = 'profile-u1';
		$context['profile_menu_name'] = 'menu_data_1';
		$context['menu_data_1']['object'] = new Menu();

		$controller = new ProfileOptions(new EventManager());
		$controller->setUser(User::$info);
		$controller->pre_dispatch();
		$controller->action_notification();

		// Lets see some items loaded into $context['profile_fields']
		$this->assertArrayHasKey('mentionmem', $context['mention_types'], 'Missing mention type');
		$this->assertEquals('0', $context['member']['notify_announcements']);
	}
}
