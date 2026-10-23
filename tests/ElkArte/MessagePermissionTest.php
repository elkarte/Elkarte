<?php

namespace ElkArte;

use PHPUnit\Framework\TestCase;
use RuntimeException;

class MessagePermissionTest extends TestCase
{
	private ?array $originalModSettings = null;

	protected function setUp(): void
	{
		$this->originalModSettings = $GLOBALS['modSettings'] ?? null;
		$GLOBALS['modSettings'] = [];
	}

	protected function tearDown(): void
	{
		if ($this->originalModSettings === null)
		{
			unset($GLOBALS['modSettings']);
		}
		else
		{
			$GLOBALS['modSettings'] = $this->originalModSettings;
		}
	}

	public function testModifyTimeLimitAppliesWhenPostModerationIsDisabledEvenIfApprovedFlagIsStale()
	{
		$permission = $this->permission(['modify_own' => true], [
			'postmod_active' => 0,
			'edit_disable_time' => 60,
		]);

		$message = [
			'id_member' => 1,
			'poster_time' => time() - 7200,
			'approved' => 0,
		];

		$this->assertFalse($permission->canModify($message, 1));
	}

	public function testModifyTimeLimitDoesNotApplyToUnapprovedPostsWhenPostModerationIsActive()
	{
		$permission = $this->permission(['modify_own' => true], [
			'postmod_active' => 1,
			'edit_disable_time' => 60,
		]);

		$message = [
			'id_member' => 1,
			'poster_time' => time() - 7200,
			'approved' => 0,
		];

		$this->assertTrue($permission->canModify($message, 1));
	}

	public function testModifyOwnPostInOwnTopicUsesModifyRepliesWhenModifyOwnIsMissing()
	{
		$permission = $this->permission(['modify_replies' => true]);

		$this->assertTrue($permission->canModify(['id_member' => 1], 1));
	}

	public function testModifyOtherUsersPostRequiresModifyAny()
	{
		$permission = $this->permission([
			'modify_own' => true,
			'modify_replies' => true,
		]);

		$this->assertFalse($permission->canModify(['id_member' => 2], 2));
	}

	public function testCheckModifyFlagsModerationWhenTopicStarterModifiesReply()
	{
		$permission = $this->permission(['modify_replies' => true]);

		$this->assertTrue($permission->checkModify(['id_member' => 2], 1));
	}

	public function testLockedTopicRequiresModerateBoardToModify()
	{
		$permission = $this->permission(['modify_any' => true]);

		$this->assertFalse($permission->canModify(['id_member' => 2], 2, true));

		$permission = $this->permission([
			'moderate_board' => true,
			'modify_any' => true,
		]);

		$this->assertTrue($permission->canModify(['id_member' => 2], 2, true));
	}

	public function testDeleteOwnReplyRespectsTimeLimit()
	{
		$permission = $this->permission(['delete_own' => true], [
			'edit_disable_time' => 60,
		]);

		$message = [
			'id_member' => 1,
			'poster_time' => time() - 7200,
		];

		$this->assertFalse($permission->canDelete($message, 2));
	}

	public function testDeleteOwnReplyInOwnTopicBypassesTimeLimitWithDeleteReplies()
	{
		$permission = $this->permission([
			'delete_own' => true,
			'delete_replies' => true,
		], [
			'edit_disable_time' => 60,
		]);

		$message = [
			'id_member' => 1,
			'poster_time' => time() - 7200,
		];

		$this->assertTrue($permission->canDelete($message, 1));
	}

	public function testDeleteReplyInOwnTopicUsesDeleteRepliesPermission()
	{
		$permission = $this->permission(['delete_replies' => true]);

		$this->assertTrue($permission->canDelete(['id_member' => 2], 1));
	}

	public function testDeleteOtherUsersReplyRequiresDeleteAny()
	{
		$permission = $this->permission([
			'delete_own' => true,
			'delete_replies' => true,
		]);

		$this->assertFalse($permission->canDelete(['id_member' => 2], 3));
	}

	public function testDeleteFirstMessageReturnsTopicWhenOnlyPost()
	{
		$permission = $this->permission([
			'delete_any' => true,
			'remove_any' => true,
		]);

		$message = [
			'id_member' => 1,
			'id_first_msg' => 10,
			'id_msg' => 10,
			'num_replies' => 0,
		];

		$this->assertSame('topic', $permission->checkDelete($message, 1));
	}

	public function testDeleteFirstMessageWithRepliesIsRejected()
	{
		$permission = $this->permissionThatThrows([
			'delete_any' => true,
			'remove_any' => true,
		]);

		$message = [
			'id_member' => 1,
			'id_first_msg' => 10,
			'id_msg' => 10,
			'num_replies' => 1,
		];

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('delFirstPost');

		$permission->checkDelete($message, 1);
	}

	public function testDeleteUnapprovedReplyWithoutApprovalReturnsExitWhenRequested()
	{
		$permission = $this->permission(['delete_replies' => true], [
			'postmod_active' => 1,
		]);

		$message = [
			'id_member' => 2,
			'approved' => 0,
			'id_first_msg' => 10,
			'id_msg' => 11,
			'num_replies' => 1,
		];

		$this->assertFalse($permission->canDelete($message, 1));
		$this->assertSame('exit', $permission->checkDelete($message, 1, null, false));
	}

	public function testDeleteUnapprovedReplyWithoutApprovalThrowsWhenRequested()
	{
		$permission = $this->permission(['delete_replies' => true], [
			'postmod_active' => 1,
		]);

		$message = [
			'id_member' => 2,
			'approved' => 0,
			'id_first_msg' => 10,
			'id_msg' => 11,
			'num_replies' => 1,
		];

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Missing permission: approve_posts');

		$permission->checkDelete($message, 1);
	}

	public function testCanRemoveTopicRequiresFirstMessage()
	{
		$permission = $this->permission(['remove_any' => true]);

		$this->assertFalse($permission->canRemoveTopic([
			'id_member' => 1,
			'id_first_msg' => 10,
			'id_msg' => 11,
		]));
	}

	public function testCanRemoveOwnTopicWithRemoveOwn()
	{
		$permission = $this->permission(['remove_own' => true]);

		$this->assertTrue($permission->canRemoveTopic([
			'id_member' => 1,
			'id_first_msg' => 10,
			'id_msg' => 10,
		]));
	}

	public function testCanRemoveOtherUsersTopicRequiresRemoveAny()
	{
		$permission = $this->permission(['remove_own' => true]);

		$this->assertFalse($permission->canRemoveTopic([
			'id_member' => 2,
			'id_first_msg' => 10,
			'id_msg' => 10,
		]));
	}

	private function permission(array $allowed, array $modSettings = []): MessagePermission
	{
		$GLOBALS['modSettings'] = $modSettings;

		return new MessagePermission(1, $this->isAllowed($allowed), $this->assertAllowed($allowed));
	}

	private function permissionThatThrows(array $allowed, array $modSettings = []): MessagePermission
	{
		$GLOBALS['modSettings'] = $modSettings;

		return new class(1, $this->isAllowed($allowed), $this->assertAllowed($allowed)) extends MessagePermission {
			protected function throwException(string $message): void
			{
				throw new RuntimeException($message);
			}
		};
	}

	private function isAllowed(array $allowed): callable
	{
		return static fn (string $permission, ?int $board = null): bool => !empty($allowed[$permission]);
	}

	private function assertAllowed(array $allowed): callable
	{
		return static function (string $permission, ?int $board = null) use ($allowed): void {
			if (empty($allowed[$permission]))
			{
				throw new RuntimeException('Missing permission: ' . $permission);
			}
		};
	}
}
