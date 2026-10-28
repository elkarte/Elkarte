<?php

/**
 * TestCase class for Board-Scoped Permissions across controllers and topic helpers.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 2
 */

namespace ElkArte\Controller;

use ElkArte\Exceptions\Exception;
use ElkArte\User;
use tests\ElkArteCommonSetupTest;

class BoardScopedPermissionsTest extends ElkArteCommonSetupTest
{
	protected $backupGlobalsExcludeList = ['user_info'];

	/**
	 * Setup dependencies before each test.
	 */
	protected function setUp(): void
	{
		require_once(SUBSDIR . '/Topic.subs.php');
		require_once(SUBSDIR . '/Post.subs.php');
		require_once(SUBSDIR . '/Messages.subs.php');
		require_once(SUBSDIR . '/Boards.subs.php');

		parent::setUp();
		parent::setSession();
	}

	/**
	 * Test getTopicBoard returns 0 for non-existent topic.
	 */
	public function testGetTopicBoardNonExistent(): void
	{
		$this->assertSame(0, getTopicBoard(9999999));
	}

	/**
	 * Test assertTopicPermission throws Exception when topic has no board.
	 */
	public function testAssertTopicPermissionMissingBoardThrows(): void
	{
		global $txt;

		$this->expectException(Exception::class);
		if (!empty($txt['no_board']))
		{
			$this->expectExceptionMessage($txt['no_board']);
		}

		assertTopicPermission('lock', 9999999);
	}

	/**
	 * Test allowedToTopic returns false when topic board is missing.
	 */
	public function testAllowedToTopicMissingBoardReturnsFalse(): void
	{
		$this->assertFalse(allowedToTopic('lock', 9999999));
	}

	/**
	 * Test global admin privileges for allowedToTopic across any topic and permission.
	 */
	public function testAdminBypassForTopicPermissions(): void
	{
		// Admin user (id 1, is_admin = true)
		User::$info->is_admin = true;

		$this->assertTrue(allowedToTopic('lock_any', 1, 1));
		$this->assertTrue(allowedToTopic('lock', 1, 1));
		$this->assertTrue(allowedToTopic('remove', 1, 1));
		$this->assertTrue(allowedToTopic('move', 1, 1));
		$this->assertTrue(allowedToTopic('poll_edit', 1, 1));
		$this->assertTrue(allowedToTopic('poll_lock', 1, 1));
		$this->assertTrue(allowedToTopic('poll_remove', 1, 1));

		// assertTopicPermission should not throw for admin
		assertTopicPermission('lock', 1, 1);
		assertTopicPermission('remove', 1, 1);
		assertTopicPermission('move', 1, 1);
	}

	/**
	 * Test composite permissions evaluate ownership correctly.
	 */
	public function testCompositePermissionOwnershipEvaluation(): void
	{
		// Save original user
		$origId = User::$info->id;
		$origAdmin = User::$info->is_admin;
		$origPermissions = User::$info->permissions;

		try
		{
			// Non-admin user with ID 42
			User::$info->id = 42;
			User::$info->is_admin = false;
			User::$info->permissions = [];

			// Test with explicit board = 1 where user has no permissions in DB
			$this->assertFalse(allowedToTopic('lock_any', 1, 1));
		}
		finally
		{
			User::$info->id = $origId;
			User::$info->is_admin = $origAdmin;
			User::$info->permissions = $origPermissions;
		}
	}
}
