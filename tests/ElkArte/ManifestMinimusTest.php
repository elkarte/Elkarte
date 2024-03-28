<?php

namespace ElkArte;

use tests\ElkArteCommonSetupTest;

/**
 * Class ManifestMinimusTest
 * @package ElkArte
 *
 * PHPUnit test case for the ElkArte\ManifestMinimus class.
 */
class ManifestMinimusTest extends ElkArteCommonSetupTest
{
    /**
     * The ManifestMinimus instance for testing.
     *
     * @var ManifestMinimus
     */
    protected $manifestMinimus;

    /**
     * Setting up before every test case
     */
    protected function setUp(): void
    {
        // Define global variables used in ManifestMinimus
        global $mbname, $modSettings, $settings, $txt, $boardurl;

        // Set initial values
        $mbname = 'Test Forum';
        $modSettings = ['pwa_short_name' => 'Test', 'pwa_background_color' => '#fafafa', 'pwa_theme_color' => '#3d6e32', 'pwa_small_icon' => 'icon_small.png', 'pwa_large_icon' => 'icon_large.png'];
        $settings = ['site_slogan' => 'The best test forum', 'default_images_url' => 'default_images'];
        $txt = ['lang_locale' => 'en-US.utf8', 'lang_rtl' => 0];
        $boardurl = 'http://www.testforum.com/path';

	    require_once(SOURCEDIR . '/Subs.php');
        $this->manifestMinimus = new ManifestMinimus();
    }

    /**
     * Testing create method in ManifestMinimus.
     *
     * This test verifies the correctness of the 'create' method in
     * the ManifestMinimus class. It checks if a correct JSON is
     * echoed.
     */
    public function testCreate()
    {
        // Start output buffering.
        ob_start();

        // Call `create` method.
        $this->manifestMinimus->create();

        // Retrieve and clean output buffer.
        $output = ob_get_clean();

        // Decode JSON output to an array.
        $outputDecoded = json_decode($output, true);

        // Verify JSON decoded output is an array.
        $this->assertTrue(is_array($outputDecoded));

        // Writing assertions for conditions to be true.
        $this->assertArrayHasKey('name', $outputDecoded);
        $this->assertArrayHasKey('short_name', $outputDecoded);
        $this->assertArrayHasKey('description', $outputDecoded);
        $this->assertArrayHasKey('lang', $outputDecoded);
        $this->assertArrayHasKey('dir', $outputDecoded);
        $this->assertArrayHasKey('display', $outputDecoded);
        $this->assertArrayHasKey('orientation', $outputDecoded);
        $this->assertArrayHasKey('id', $outputDecoded);
        $this->assertArrayHasKey('start_url', $outputDecoded);
        $this->assertArrayHasKey('scope', $outputDecoded);
        $this->assertArrayHasKey('background_color', $outputDecoded);
        $this->assertArrayHasKey('theme_color', $outputDecoded);
        $this->assertArrayHasKey('icons', $outputDecoded);
    }
}