<?php

namespace ElkArte;

use tests\ElkArteCommonSetupTest;

class MetadataIntegrateTest extends ElkArteCommonSetupTest
{
    protected function setUp(): void
    {
        parent::setUp();

        global $boardurl, $mbname, $context, $settings;

        // Minimal globals commonly used by MetadataIntegrate
        $boardurl = 'http://example.com';
        $mbname = 'ElkArte Test Forum';

        // Theme/logo related
        $context['forum_name'] = $mbname;
        $context['page_title'] = 'A Topic Title';
        $context['canonical_url'] = $boardurl . '/index.php?topic=1.0';
        $context['header_logo_url_html_safe'] = $boardurl . '/mobile.png';

        // Optional slogan
        $settings['site_slogan'] = 'Just a Test Forum';
    }

    protected function tearDown(): void
    {
        global $topic;

        unset($topic);
        parent::tearDown();
    }

    public function testPrepareTopicMetadataOnFirstPage(): void
    {
        global $context, $topic;

        // Testbed has one topic available with id 1 and start should be 0
        $topic = 1;

        // Provide the display renderer callback expected by initPostData()
        $postData = [
            'body' => '<strong>Hello</strong> world! This is the first post body.',
            'href' => 'http://example.com/index.php?topic=1.msg1#msg1',
            'timestamp' => time() - 3600,
            'modified' => ['name' => '', 'timestamp' => 0],
            'member' => ['name' => 'tester', 'href' => 'http://example.com/u/1'],
        ];

        $controller = new class($postData)
        {
            private array $data;
            public function __construct(array $data) { $this->data = $data; }
            // Method name is looked up from $context['get_message'][1]
            public function get_first_post(): array { return $this->data; }
        };

        $context['get_message'] = [$controller, 'get_first_post'];

        // Execute
        MetadataIntegrate::prepare_topic_metadata(0);

        // Assert that the renderer was marked to reset to include the first post
        $this->assertTrue(!empty($context['reset_renderer']));

        // Schema for the article should be populated
        $this->assertIsArray($context['smd_article']);
        $this->assertSame('WebPage', $context['smd_article']['@type'] ?? null);
        $this->assertArrayHasKey('mainEntity', $context['smd_article']);
        $this->assertSame('DiscussionForumPosting', $context['smd_article']['mainEntity']['@type'] ?? null);

        // Open Graph should indicate an article when a topic is present
        $this->assertIsArray($context['open_graph']);
        $this->assertStringContainsString('content="article"', $context['open_graph']['type']);
    }

    public function testPrepareBasicMetadataNoTopic(): void
    {
        global $context;

        unset($context['get_message']);

        // Execute
        MetadataIntegrate::prepare_basic_metadata();

        // Site schema should always be present
        $this->assertIsArray($context['smd_site']);
        $this->assertSame('Organization', $context['smd_site']['@type'] ?? null);

        // Without post data, article schema should be empty
        $this->assertIsArray($context['smd_article']);
        $this->assertEmpty($context['smd_article']);

        // OG type should be website when no topic
        $this->assertIsArray($context['open_graph']);
        $this->assertStringContainsString('content="website"', $context['open_graph']['type']);
    }

    public function testGetLikeCountTotals(): void
    {
        global $context;

        $context['likes'] = [
            ['count' => 3],
            ['count' => 5],
            ['count' => 0],
        ];

        $mi = new MetadataIntegrate();
        $this->assertSame(8, $mi->getLikeCount());
    }
}
