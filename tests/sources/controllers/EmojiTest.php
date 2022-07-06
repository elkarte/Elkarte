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
		parent::setUp();

		// Unpack the emoji set
		$req = HttpReq::instance();
		$req->post->emoji_selection = 'tw-emoji';
		ManageEmojiModule::integrate_save_smiley_settings();
	}

	/**
	 * Test the showing the Likes Listing
	 */
	public function testShowLikes()
	{
		global $modSettings;

		$modSettings['emoji_selection'] = 'tw-emoji';
		$emoji = Emoji::instance();

		$result = $emoji->emojiNameToImage(':smiley:');

		// Let us see that beautiful smile
		$this->assertEquals('<img class="smiley emoji tw-emoji" src="http://127.0.0.1/smileys/tw-emoji/1f603.svg" alt=":smiley:" title="Smiley" data-emoji-name=":smiley:" data-emoji-code="1f603">', $result);
	}
}
