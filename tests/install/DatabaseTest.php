<?php

/**
 * TestCase class for tables present
 */
class TestDatabase extends \PHPUnit\Framework\TestCase
{
	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	function setUp()
	{
	}

	/**
	 * testTablesExist() get a list of tables and see if they all exist
	 */
	function testTablesExist()
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
			'log_badbehavior',
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
			'openid_assoc',
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
		$exists = false;

		foreach ($known_tables as $table)
		{
			$exists = in_array($db_prefix . $table, $tables);
			$this->assertTrue($exists, 'The table ' . $table . ' doesn\'t esist');
		}
	}
}
