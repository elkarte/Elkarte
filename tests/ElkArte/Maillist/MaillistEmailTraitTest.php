<?php

use ElkArte\Maillist\MaillistEmailTrait;
use PHPUnit\Framework\TestCase;

/**
 * Tests the moderation logic shared by MaillistPost and MaillistTopic
 */
class MaillistEmailTraitTest extends TestCase
{
	private array $savedSettings;

	protected function setUp(): void
	{
		global $modSettings;

		$this->savedSettings = $modSettings ?? [];
	}

	protected function tearDown(): void
	{
		global $modSettings;

		$modSettings = $this->savedSettings;
	}

	private function makeSubject(array $permissions, int $warning): object
	{
		$subject = new class {
			use MaillistEmailTrait;

			public function moderate(): void
			{
				$this->applyModerationLimits();
			}
		};
		$subject->pbeUser = ['user_info' => ['warning' => $warning, 'permissions' => $permissions]];

		return $subject;
	}

	public function testNoChangeWhenPostModerationInactive(): void
	{
		global $modSettings;

		$modSettings['postmod_active'] = 0;
		$modSettings['warning_mute'] = 10;
		$subject = $this->makeSubject(['post_new', 'post_reply_any'], 50);
		$subject->moderate();

		$this->assertSame(['post_new', 'post_reply_any'], $subject->pbeUser['user_info']['permissions']);
	}

	public function testMutedUserLosesPostingPermissions(): void
	{
		global $modSettings;

		$modSettings['postmod_active'] = 1;
		$modSettings['warning_mute'] = 50;
		$modSettings['warning_moderate'] = 20;
		$subject = $this->makeSubject(['post_new', 'post_reply_any', 'view_stats'], 60);
		$subject->moderate();

		$this->assertSame(['view_stats'], array_values($subject->pbeUser['user_info']['permissions']));
	}

	public function testModeratedUserGetsUnapprovedPermissions(): void
	{
		global $modSettings;

		$modSettings['postmod_active'] = 1;
		$modSettings['warning_mute'] = 50;
		$modSettings['warning_moderate'] = 20;
		$subject = $this->makeSubject(['post_new', 'view_stats'], 30);
		$subject->moderate();
		$permissions = $subject->pbeUser['user_info']['permissions'];

		$this->assertContains('post_unapproved_topics', $permissions);
		$this->assertNotContains('post_new', $permissions);
		$this->assertContains('view_stats', $permissions);
		$this->assertNotContains('post_unapproved_replies_any', $permissions);
	}

	public function testLowWarningIsUnchanged(): void
	{
		global $modSettings;

		$modSettings['postmod_active'] = 1;
		$modSettings['warning_mute'] = 50;
		$modSettings['warning_moderate'] = 20;
		$subject = $this->makeSubject(['post_new'], 5);
		$subject->moderate();

		$this->assertSame(['post_new'], $subject->pbeUser['user_info']['permissions']);
	}
}
