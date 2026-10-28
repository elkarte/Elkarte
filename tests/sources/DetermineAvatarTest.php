<?php

use PHPUnit\Framework\TestCase;

class DetermineAvatarTest extends TestCase
{
	protected function setUp(): void
	{
		require_once __DIR__ . '/../../vendor/autoload.php';
		require_once __DIR__ . '/../../sources/Subs.php';
		require_once __DIR__ . '/../../sources/Load.php';

		global $context, $modSettings, $settings;

		$context = [];
		$modSettings = [
			'avatar_max_height' => 64,
			'avatar_url' => 'https://forum.example/avatars',
			'custom_avatar_url' => 'https://forum.example/uploads',
			'gravatar_default' => 'none',
			'gravatar_rating' => '',
		];
		$settings = [];
	}

	public function testRemoteAvatarUrlAndAltAreEscapedWithoutDoubleEncoding(): void
	{
		$avatar = determineAvatar([
			'avatar' => 'https://x/a"<b>',
			'id_attach' => 0,
			'member_name' => 'a"b',
		]);

		$this->assertSame('https://x/a"<b>', $avatar['name']);
		$this->assertStringContainsString('src="https://x/a&quot;&lt;b&gt;" alt="a&quot;b"', $avatar['image']);
		$this->assertSame('https://x/a&quot;&lt;b&gt;', $avatar['href']);
		$this->assertSame($avatar['href'], $avatar['url']);

		$encoded = determineAvatar([
			'avatar' => 'https://x/?a=1&amp;b=2',
			'id_attach' => 0,
			'member_name' => 'alice',
		]);

		$this->assertSame('https://x/?a=1&amp;b=2', $encoded['href']);
		$this->assertStringContainsString('src="https://x/?a=1&amp;b=2"', $encoded['image']);
	}

	public function testGalleryAvatarAndAltAreEscaped(): void
	{
		$avatar = determineAvatar([
			'avatar' => 'pic".png',
			'id_attach' => 0,
			'member_name' => 'a"b',
		]);

		$this->assertSame('https://forum.example/avatars/pic&quot;.png', $avatar['href']);
		$this->assertSame($avatar['href'], $avatar['url']);
		$this->assertStringContainsString('alt="a&quot;b"', $avatar['image']);
	}

	public function testGravatarUploadedAndEmptyBranchesRemainUnchangedForSafeNames(): void
	{
		$gravatar = determineAvatar([
			'avatar' => 'gravatar',
			'email_address' => 'alice@example.com',
			'id_attach' => 0,
			'member_name' => 'alice',
		]);
		$gravatarUrl = '//www.gravatar.com/avatar/' . md5('alice@example.com') . '?s=64';
		$this->assertSame($gravatarUrl, $gravatar['href']);
		$this->assertStringContainsString('alt="alice"', $gravatar['image']);

		$uploaded = determineAvatar([
			'avatar' => '',
			'attachment_type' => 1,
			'filename' => 'avatar.png',
			'id_attach' => 9,
			'member_name' => 'alice',
		]);
		$this->assertSame('https://forum.example/uploads/avatar.png', $uploaded['href']);
		$this->assertSame('', $uploaded['url']);
		$this->assertStringContainsString('alt="alice"', $uploaded['image']);

		$empty = determineAvatar([
			'avatar' => '',
			'filename' => '',
			'id_attach' => 0,
			'member_name' => 'alice',
		]);
		$this->assertSame('', $empty['name']);
		$this->assertSame('', $empty['image']);
		$this->assertSame('', $empty['href']);
		$this->assertSame('', $empty['url']);
	}
}