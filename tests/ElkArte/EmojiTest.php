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
		// $settings['default_theme_dir'] = '/home/runner/work/Elkarte/Elkarte/elkarte/themes/default';
		$settings['default_theme_dir'] = BOARDDIR . '/themes/default';
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
	 * Test :emoji: to image conversion
	 */
	public function testEmoji2Image()
	{
		global $modSettings;

		$modSettings['emoji_selection'] = 'tw-emoji';
		updateSettings(array('emoji_selection' => 'tw-emoji'));

		$req = HttpReq::instance();
		$req->post->emoji_selection = 'tw-emoji';

		$emoji = Emoji::instance();
		$emoji->smileys_url = htmlspecialchars($modSettings['smileys_url']) . '/tw-emoji';

		// Use Reflection to access the protected _modSettings property
		$reflection = new \ReflectionClass($emoji);
		$property = $reflection->getProperty('_modSettings');
		$property->setAccessible(true);

		// Retrieve the ValuesContainer object and set the value
		$valuesContainer = $property->getValue($emoji);
		$valuesContainer['emoji_selection'] = 'tw-emoji';

		$result = $emoji->emojiNameToImage(':smiley:');

		// Let us see that beautiful smile (this should be tw-emoji but a previous instance is out there
		$this->assertEquals('<img class="smiley emoji tw-emoji" src="http://127.0.0.1/smileys/tw-emoji/1f603.svg" alt="&#58;smiley&#58;" title="Smiley" data-emoji-name="&#58;smiley&#58;" data-emoji-code="1f603" />', $result);

		$result = $emoji->emojiNameToImage(':face_exhaling:', true);

		// Let us see that beautiful smile
		$this->assertEquals('&#x1f62e;&#x200d;&#x1f4a8;', $result);
	}

	/**
	 * Test finding emoji shortcode names by unicode hex code
	 */
	public function testFindEmojiByCode()
	{
		$emoji = Emoji::instance();

		// Exact match
		$this->assertEquals('grinning', $emoji->findEmojiByCode('1f600'));
		// Case-insensitive
		$this->assertEquals('grinning', $emoji->findEmojiByCode('1F600'));
		// First defined match for shared codepoints
		$this->assertEquals('thumbsup', $emoji->findEmojiByCode('1f44d'));
		// Variation selector -fe0f lookup when tag defined with -fe0f
		$this->assertEquals('small_airplane', $emoji->findEmojiByCode('1f6e9'));
		$this->assertEquals('small_airplane', $emoji->findEmojiByCode('1f6e9-fe0f'));
		// Strip trailing -fe0f when tag defined without -fe0f
		$this->assertEquals('ship', $emoji->findEmojiByCode('1f6a2'));
		$this->assertEquals('ship', $emoji->findEmojiByCode('1f6a2-fe0f'));
		// Non-existent or empty
		$this->assertFalse($emoji->findEmojiByCode(''));
		$this->assertFalse($emoji->findEmojiByCode('nonexistent_emoji_code'));
	}

	/**
	 * Test unicodeCharacterToNumber conversion
	 */
	public function testUnicodeCharacterToNumber()
	{
		$emoji = Emoji::instance();

		// Basic emoji
		$this->assertEquals('1f600', $emoji->unicodeCharacterToNumber('😀'));

		// Emoji with skin tone modifier (modifier stripped)
		$this->assertEquals('1f44d', $emoji->unicodeCharacterToNumber('👍🏽'));

		// Complex ZWJ sequence
		$this->assertEquals('1f62e-200d-1f4a8', $emoji->unicodeCharacterToNumber('😮‍💨'));

		// Flag sequence
		$this->assertEquals('1f1fa-1f1f8', $emoji->unicodeCharacterToNumber('🇺🇸'));

		// Keycap sequence
		$this->assertEquals('0031-fe0f-20e3', $emoji->unicodeCharacterToNumber('1️⃣'));
	}

	/**
	 * Test emojiFromUni parsing and pre-filter behavior
	 */
	public function testEmojiFromUni()
	{
		global $modSettings;

		$modSettings['emoji_selection'] = 'tw-emoji';
		$emoji = Emoji::instance();
		$emoji->smileys_url = 'http://127.0.0.1/smileys/tw-emoji';

		// Non-emoji text with typography/foreign characters remains unchanged
		$plainText = '“Hello world,” he said — with an en-dash – and curly apostrophe’s and ellipsis…';
		$this->assertEquals($plainText, $emoji->emojiFromUni($plainText));

		$foreignText = 'Привет, мир! こんにちは世界！ مرحبا بالعالم！';
		$this->assertEquals($foreignText, $emoji->emojiFromUni($foreignText));

		// Text with emoji converted to img tag
		$result = $emoji->emojiFromUni('Hello 😀 world');
		$this->assertStringContainsString('data-emoji-code="1f600"', $result);
		$this->assertStringContainsString('src="http://127.0.0.1/smileys/tw-emoji/1f600.svg"', $result);
	}

	/**
	 * Test getCodeByName mapping shortcodes to hex codes
	 */
	public function testGetCodeByName()
	{
		$emoji = Emoji::instance();

		// Shortcode without colons
		$this->assertEquals('1f600', $emoji->getCodeByName('grinning'));
		$this->assertEquals('1f44d', $emoji->getCodeByName('thumbsup'));
		$this->assertEquals('1f44d', $emoji->getCodeByName('+1'));

		// Shortcode with colons
		$this->assertEquals('1f600', $emoji->getCodeByName(':grinning:'));
		$this->assertEquals('1f44d', $emoji->getCodeByName(':+1:'));

		// Non-existent shortcode
		$this->assertNull($emoji->getCodeByName('not_a_real_emoji_name'));
	}
}
