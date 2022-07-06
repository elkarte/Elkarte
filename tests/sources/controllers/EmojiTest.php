<?php

use ElkArte\AdminController\ManageEmojiModule;
use ElkArte\Emoji;
use ElkArte\HttpReq;

/**
 * TestCase class for the Emoji Controller
 */
class TestEmoji extends ElkArteCommonSetupTest
{
	protected $backupGlobalsBlacklist = ['user_info'];

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	protected function setUp(): void
	{
		global $modSettings, $settings;

		parent::setUp();

		// Not running from the web, so need to point to the actual file
		$settings['default_theme_dir'] = '/home/runner/work/Elkarte/Elkarte/elkarte/themes/default';

		// Unpack the emoji set
		$modSettings['emoji_selection'] = 'tw-emoji';
		$req = HttpReq::instance();
		$req->post->emoji_selection = 'tw-emoji';
		ManageEmojiModule::integrate_save_smiley_settings();
	}

	/**
	 * Test the showing the Likes Listing
	 */
	public function testEmoji2Image()
	{
		global $modSettings;

		$modSettings['emoji_selection'] = 'tw-emoji';
		$emoji = Emoji::instance();

		$result = $emoji->emojiNameToImage(':smiley:');

		// Let us see that beautiful smile
		$this->assertEquals('<img class="smiley emoji tw-emoji" src="http://127.0.0.1/smileys/tw-emoji/1f603.svg" alt="&#58;smiley&#58;" title="Smiley" data-emoji-name="&#58;smiley&#58;" data-emoji-code="1f603" />', $result);

		$result = $emoji->emojiNameToImage(':face_exhaling:', true);

		// Let us see that beautiful smile
		$this->assertEquals('&#x1f62e;&#x200d;&#x1f4a8;', $result);
	}
}
