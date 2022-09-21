<?php

use PHPUnit\Framework\TestCase;

/**
 * TestCase class for tables present
 */
class TestDatabase extends TestCase
{
	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	protected function setUp(): void
	{
	}

	protected function bootstrap()
	{
		// We're going to need, cough, a few globals
		global $mbname, $language;
		global $boardurl, $webmaster_email, $cookiename;
		global $db_server, $db_name, $db_user, $db_prefix, $db_persist, $db_error_send, $db_type, $db_port;
		global $modSettings, $context, $user_info, $topic, $board, $txt;
		global $scripturl, $db_passwd;
		global $boarddir, $sourcedir;
		global $ssi_db_user, $ssi_db_passwd;

		define('ELK', '1');
		define('CACHE_STALE', '?R11B2');

		// Get the forum's settings for database and file paths.
		require_once('Settings.php');

		// Set our site "variable" constants
		define('BOARDDIR', $boarddir);
		define('CACHEDIR', $cachedir);
		define('EXTDIR', $extdir);
		define('LANGUAGEDIR', $sourcedir . '/ElkArte/Languages');
		define('SOURCEDIR', $sourcedir);
		define('ADMINDIR', $sourcedir . '/admin');
		define('CONTROLLERDIR', $sourcedir . '/controllers');
		define('SUBSDIR', $sourcedir . '/subs');
		define('ADDONSDIR', $sourcedir . '/addons');

		require_once('.github/bootstrap.php');
	}

	/**
	 * testTablesExist() get a list of tables and see if they all exist
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function testTablesExist()
	{
		global $db_prefix;

		$db = database();
		$tables = $db->list_tables();

		$known_tables = array(
			'antispam_questions',
			'approval_queue',
			'attachments',
			'ban_groups',
			'ban_items',
			'board_permissions',
			'boards',
			'calendar',
			'calendar_holidays',
			'categories',
			'collapsed_categories',
			'custom_fields',
			'custom_fields_data',
			'group_moderators',
			'follow_ups',
			'log_actions',
			'log_activity',
			'log_banned',
			'log_boards',
			'log_comments',
			'log_digest',
			'log_errors',
			'log_floodcontrol',
			'log_group_requests',
			'log_karma',
			'log_likes',
			'log_mark_read',
			'log_member_notices',
			'log_mentions',
			'log_notify',
			'log_online',
			'log_packages',
			'log_polls',
			'log_reported',
			'log_reported_comments',
			'log_scheduled_tasks',
			'log_search_messages',
			'log_search_results',
			'log_search_subjects',
			'log_search_topics',
			'log_spider_hits',
			'log_spider_stats',
			'log_subscribed',
			'log_topics',
			'mail_queue',
			'membergroups',
			'members',
			'member_logins',
			'message_icons',
			'message_likes',
			'messages',
			'moderators',
			'package_servers',
			'permission_profiles',
			'permissions',
			'personal_messages',
			'pm_recipients',
			'pm_rules',
			'polls',
			'poll_choices',
			'postby_emails',
			'postby_emails_error',
			'postby_emails_filters',
			'scheduled_tasks',
			'settings',
			'sessions',
			'smileys',
			'spiders',
			'subscriptions',
			'themes',
			'topics',
			'user_drafts',
		);

		foreach ($known_tables as $table)
		{
			$exists = in_array($db_prefix . $table, $tables);
			$this->assertTrue($exists, 'The table ' . $table . ' doesn\'t exist');
		}
	}

	/**
	 * This test is here to ensure that the tables that should contain something
	 * at the end of the install actually contain what they are supposed to.
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function testTablesPopulated()
	{
		$db = database();

		$known_inserts = [
			'board_permissions' => 367,
			'boards' => 1,
			'calendar_holidays' => 116,
			'categories' => 1,
			'custom_fields' => 9,
			'membergroups' => 8,
			'message_icons' => 13,
			'messages' => 1,
			'notifications_pref' => 5,
			'package_servers' => 1,
			'permission_profiles' => 4,
			'permissions' => 40,
			'scheduled_tasks' => 14,
			'settings' => 201,
			'smileys' => 54,
			'spiders' => 31,
			'themes' => 22,
			'topics' => 1,
		];

		foreach ($known_inserts as $tbl => $number)
		{
			$result = $db->fetchQuery('SELECT count(*) FROM {db_prefix}' . $tbl);
			list($counted) = $result->fetch_row();
			$this->assertEquals($number, $counted, 'The number of inserted rows for the table ' . $tbl . ' doesn\'t match the expectations (' . $counted . ' -VS- ' . $number . ')');
		}
	}
}
