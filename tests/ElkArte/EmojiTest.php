<?php

/**
 * TestCase class for the Emoji Controller
 */

namespace ElkArte;

use ElkArte\AdminController\ManageEmojiModule;
use ElkArte\Helper\FileFunctions;
use ElkArte\Helper\HttpReq;
use tests\ElkArteCommonSetupTest;

class EmojiTest extends ElkArteCommonSetupTest
{
	protected $backupGlobalsExcludeList = ['user_info'];

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	protected function setUp(): void
	{
		global $settings;

		parent::setUp();

		// Not running from the web, so need to point to the actual file
		$settings['default_theme_dir'] = '/home/runner/work/Elkarte/Elkarte/elkarte/themes/default';
	}

	public function testEmojiUnzip()
	{
		global $modSettings;

		// Unpack the emoji set
		$modSettings['emoji_selection'] = 'no-emoji';

		$req = HttpReq::instance();
		$req->post->emoji_selection = 'tw-emoji';

		ManageEmojiModule::integrate_save_smiley_settings();

		$check = FileFunctions::instance()->isDir(BOARDDIR . '/smileys/tw-emoji');

		$this->assertTrue($check, 'tw-emoji did not unpack');
	}

	/**
	 * Test the showing the Likes Listing
	 */
	public function testEmoji2Image()
	{
		global $modSettings;

		$modSettings['emoji_selection'] = 'tw-emoji';
		updateSettings(array('emoji_selection' => 'tw-emoji'));

		$req = HttpReq::instance();
		$req->post->emoji_selection = 'tw-emoji';

		$emoji = Emoji::instance();

		$result = $emoji->emojiNameToImage(':smiley:');

		// Let us see that beautiful smile (this should be tw-emoji but a previous instance is out there
		$this->assertEquals('<img class="smiley emoji no-emoji" src="http://127.0.0.1/smileys/no-emoji/1f603.svg" alt="&#58;smiley&#58;" title="Smiley" data-emoji-name="&#58;smiley&#58;" data-emoji-code="1f603" />', $result);

		$result = $emoji->emojiNameToImage(':face_exhaling:', true);

		// Let us see that beautiful smile
		$this->assertEquals('&#x1f62e;&#x200d;&#x1f4a8;', $result);
	}
}
