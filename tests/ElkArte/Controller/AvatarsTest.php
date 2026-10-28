<?php

namespace ElkArte\Controller;

use ElkArte\Helper\HttpReq;
use tests\ElkArteCommonSetupTest;

class AvatarsTest extends ElkArteCommonSetupTest
{
	protected function setUp(): void
	{
		global $context, $modSettings, $profile_vars;

		parent::setUp();
		$context['id_member'] = 0;
		$modSettings['custom_avatar_dir'] = BOARDDIR . '/avatars';
		$modSettings['avatar_external_enabled'] = 1;
		$modSettings['avatar_download_external'] = 0;
		$modSettings['avatar_max_width'] = 0;
		$modSettings['avatar_max_height'] = 0;
		$profile_vars = [];
	}

	public function testValidExternalUrlIsStoredEntityEncoded(): void
	{
		global $profile_vars;

		$this->setAvatarUrl('https://example.com/pic.png?a=1&amp;b=2');

		$this->assertTrue((new Avatars())->processExternalUrl());
		$this->assertSame('https://example.com/pic.png?a=1&amp;b=2', $profile_vars['avatar']);
	}

	public function testBareSchemeClearsAvatar(): void
	{
		global $profile_vars;

		$this->setAvatarUrl('https://');

		$this->assertTrue((new Avatars())->processExternalUrl());
		$this->assertSame('', $profile_vars['avatar']);
	}

	public function testInvalidExternalUrlsAreRejected(): void
	{
		$invalidUrls = [
			'https://attacker.example/a"' . "\t" . 'onerror="x"',
			'https://attacker.example/a&quot;' . "\t" . 'onerror=&quot;x&quot;',
			'https://user:password@example.com/avatar.png',
			'https:///avatar.png',
			'https://example.com/a' . "\t" . 'onerror=x',
			'https://example.com/a%0Aonerror=x',
			'https://example.com/a`onerror=x',
			'https://example.com/a\\b.png',
			'https://example.com/' . str_repeat('a', 240),
		];

		foreach ($invalidUrls as $url)
		{
			$this->setAvatarUrl($url);

			$this->assertSame('bad_avatar', (new Avatars())->processValue('external'), 'Expected URL to be rejected: ' . $url);
		}
	}

	private function setAvatarUrl(string $url): void
	{
		$req = HttpReq::instance();
		$req->post->userpicpersonal = $url;
	}
}