<?php

namespace {
	if (!function_exists('database'))
	{
		function database($fatal = true, $force = false)
		{
			return null;
		}
	}
}

namespace ElkArte\Menu {

use ElkArte\Cache\Cache;
use ElkArte\Helper\ValuesContainer;
use ElkArte\User;
use PHPUnit\Framework\TestCase;

class MenuContextTest extends TestCase
{
	protected $backupGlobalsExcludeList = ['user_info'];

	public static function setUpBeforeClass(): void
	{
		if (!defined('ELK'))
		{
			define('ELK', '1');
		}

		if (!defined('BOARDDIR'))
		{
			define('BOARDDIR', dirname(__DIR__, 3));
			define('SOURCEDIR', BOARDDIR . '/sources');
			define('SUBSDIR', SOURCEDIR . '/subs');
			define('CACHEDIR', BOARDDIR . '/cache');
			define('LANGUAGEDIR', SOURCEDIR . '/ElkArte/Languages');
		}

		require_once(SOURCEDIR . '/QueryString.php');
		require_once(SOURCEDIR . '/Subs.php');
		require_once(SOURCEDIR . '/Load.php');
		require_once(SOURCEDIR . '/Security.php');
		require_once(SUBSDIR . '/Menu.subs.php');
	}

	protected function setUp(): void
	{
		global $context, $modSettings, $settings, $scripturl, $boardurl, $txt;

		$boardurl = 'http://example.com';
		$scripturl = 'http://example.com/index.php';
		$modSettings['lastActive'] = 15;
		$modSettings['settings_updated'] = 0;
		$modSettings['cache_enable'] = 2;
		$modSettings['postmod_active'] = 0;
		$modSettings['maillist_enabled'] = 0;
		$modSettings['mentions_enabled'] = 1;
		$modSettings['drafts_enabled'] = 1;
		$modSettings['drafts_post_enabled'] = 1;

		$txt = [
			'community' => 'Home',
			'recent_posts' => 'Recent',
			'search' => 'Search',
			'members_title' => 'Members',
			'calendar' => 'Calendar',
			'contact' => 'Contact',
			'help' => 'Help',
			'admin' => 'Admin',
			'admin_center' => 'Admin Center',
			'modSettings_title' => 'Features and Options',
			'package' => 'Package Manager',
			'errlog' => 'Error Log',
			'moderate' => 'Moderate',
			'mc_reported_posts' => 'Reported Posts',
			'modlog_view' => 'Moderation Log',
			'mc_unapproved_attachments' => 'Unapproved Attachments',
			'mc_unapproved_poststopics' => 'Unapproved Posts',
			'mc_failed_emails' => 'Failed Emails',
			'mc_emailerror' => 'Email Errors',
			'mc_group_requests' => 'Group Requests',
			'mc_unapproved_users' => 'Unapproved Members',
			'pm_short' => 'PMs',
			'pm_menu_read' => 'Read PMs',
			'pm_menu_send' => 'Send PM',
			'mention' => 'Mentions',
			'view_unread_category' => 'Unread',
			'view_replies_category' => 'Replies',
			'login' => 'Login',
			'register' => 'Register',
			'account_short' => 'Profile',
			'profile' => 'Profile',
			'account' => 'Account',
			'mydrafts' => 'Drafts',
			'forumprofile' => 'Forum Profile',
			'theme' => 'Theme',
			'logout' => 'Logout',
			'main_menu' => 'Main Menu',
			'upshrink_description' => 'Collapse',
		];

		$settings['menu_numeric_notice'] = [
			-1 => ' <span class="pm_indicator" style="display: none">%1$s</span>',
			0 => ' <span class="pm_indicator">%1$s</span>',
			1 => ' <span>[<strong>%1$s</strong>]</span>',
			2 => ' <span>[<strong>%1$s</strong>]</span>',
		];

		User::$info = new ValuesContainer([
			'id' => 1,
			'name' => 'TestUser',
			'groups' => [1],
			'language' => 'english',
			'is_guest' => false,
			'is_admin' => true,
			'permissions' => ['admin_forum', 'pm_read'],
			'query_see_board' => '1=1',
			'mod_cache' => ['bq' => '1=1', 'gq' => '1=1', 'mq' => '1=1', 'ap' => [0]],
		]);

		$context['user'] = [
			'is_owner' => true,
			'is_guest' => false,
			'is_admin' => true,
			'can_mod' => false,
			'unread_messages' => 0,
			'mentions' => 0,
		];
		$context['current_action'] = 'home';
		$context['allow_search'] = true;
		$context['allow_admin'] = true;
		$context['allow_edit_profile'] = true;
		$context['allow_memberlist'] = true;
		$context['allow_calendar'] = false;
		$context['allow_moderation_center'] = false;
		$context['allow_pm'] = true;
		$context['theme_header_callbacks'] = [];
		$context['session_var'] = 'sessid';
		$context['session_id'] = '123456';
	}

	public function testMenuContextCountersDecoupledFromCache()
	{
		global $context, $modSettings;

		$cache = Cache::instance();
		$cache->setLevel(2);
		$cache->enable(true);

		// Clean up any existing cache key for this test
		$cacheKey = 'menu_buttons-1-english';
		$cache->remove($cacheKey);

		// User A has 5 unread PMs and 3 mentions
		$context['user']['unread_messages'] = 5;
		$context['user']['mentions'] = 3;

		$menu = new MenuContext();
		$menu->setupMenuContext();

		$this->assertNotEmpty($context['menu_buttons']);
		$this->assertArrayHasKey('pm', $context['menu_buttons']);
		$this->assertSame('i-menu-pm-on', $context['menu_buttons']['pm']['data-icon']);
		$this->assertStringContainsString('5', $context['menu_buttons']['pm']['title']);
		$this->assertTrue($context['menu_buttons']['pm']['indicator'] ?? false);

		// Verify cached item does not have user A's counts baked in
		$cached = $cache->get($cacheKey, $modSettings['lastActive'] * 60);
		$this->assertNotNull($cached, 'Menu buttons should be cached');
		$this->assertArrayHasKey('pm', $cached);
		// The cached button title should NOT contain the indicator span or count
		$this->assertStringNotContainsString('5', $cached['pm']['title'], 'Cached title must not contain user counts');
		$this->assertFalse(isset($cached['pm']['indicator']) && $cached['pm']['indicator'] === true, 'Cached button must not have indicator set to true');

		// User B in the same membergroup visits (0 unread PMs, 0 mentions)
		$context['user']['unread_messages'] = 0;
		$context['user']['mentions'] = 0;

		$menu2 = new MenuContext();
		$menu2->setupMenuContext();

		$this->assertArrayHasKey('pm', $context['menu_buttons']);
		$this->assertSame('i-menu-pm-off', $context['menu_buttons']['pm']['data-icon']);
		$this->assertStringNotContainsString('5', $context['menu_buttons']['pm']['title']);
		$this->assertStringContainsString('style="display: none"', $context['menu_buttons']['pm']['title']);

		// Verify profile button dynamic properties
		$this->assertArrayHasKey('profile', $context['menu_buttons']);
		$this->assertStringContainsString('123456', $context['menu_buttons']['profile']['sub_buttons']['logout']['href']);
		$this->assertStringContainsString('u=1', $context['menu_buttons']['profile']['href']);

		// Clean up cache
		$cache->remove($cacheKey);
	}

	public function testSubButtonCountersAppliedDynamically()
	{
		global $context, $modSettings;

		$cache = Cache::instance();
		$cache->setLevel(2);
		$cache->enable(true);

		$cacheKey = 'menu_buttons-1-english';
		$cache->remove($cacheKey);

		// Admin menu with grand_total
		$menu_count = [
			'grand_total' => 4,
			'reports' => 2,
			'attachments' => 2,
			'unread_messages' => 0,
			'mentions' => 0,
		];

		$menu = new MenuContext();
		$menu->setupMenuContext();

		$this->assertArrayHasKey('admin', $context['menu_buttons']);

		// Clean up cache
		$cache->remove($cacheKey);
	}
}
}
