<?php

namespace tests\ElkArte\UrlGenerators;

use ElkArte\UrlGenerator\UrlGenerator;
use PHPUnit\Framework\TestCase;

class UrlGeneratorTest extends TestCase
{
    private string $scripturl = 'http://example.com/index.php';

    private function makeGenerator(string $mode): UrlGenerator
    {
        $gen = new UrlGenerator([
            'generator' => $mode,
            'scripturl' => $this->scripturl,
            'replacements' => [],
        ]);

        // Register the specific builders we want to test
        $gen->register('Board');
        $gen->register('Topic');

        return $gen;
    }

    public function testStandardBoardAndTopic(): void
    {
        $gen = $this->makeGenerator('Standard');

        // Board id=1, name is ignored in standard mode
        $this->assertSame(
            $this->scripturl . '?board=1',
            $gen->get('board', ['board' => 1, 'name' => 'General Category'])
        );

        // With pagination start
        $this->assertSame(
            $this->scripturl . '?board=1.10',
            $gen->get('board', ['board' => 1, 'name' => 'General Category', 'start' => 10])
        );

        // Topic id=1, subject is ignored in standard mode
        $this->assertSame(
            $this->scripturl . '?topic=1',
            $gen->get('topic', ['topic' => 1, 'subject' => 'Welcome to ElkArte!'])
        );

        // Topic with pagination start
        $this->assertSame(
            $this->scripturl . '?topic=1.20',
            $gen->get('topic', ['topic' => 1, 'subject' => 'Welcome to ElkArte!', 'start' => 20])
        );
    }

    public function testSemanticBoardAndTopic(): void
    {
        $gen = $this->makeGenerator('Semantic');

        // Board id=1, name => slug "General-Category"
        $this->assertSame(
            $this->scripturl . '?b/General-Category-1',
            $gen->get('board', ['board' => 1, 'name' => 'General Category'])
        );

        // With pagination start appended after id
        $this->assertSame(
            $this->scripturl . '?b/General-Category-1.15',
            $gen->get('board', ['board' => 1, 'name' => 'General Category', 'start' => 15])
        );

        // Topic subject contains '!' which is urlencoded in the slug
        $this->assertSame(
            $this->scripturl . '?t/Welcome-to-ElkArte%21-1',
            $gen->get('topic', ['topic' => 1, 'subject' => 'Welcome to ElkArte!'])
        );

        // Topic with pagination start appended after id
        $this->assertSame(
            $this->scripturl . '?t/Welcome-to-ElkArte%21-1.5',
            $gen->get('topic', ['topic' => 1, 'subject' => 'Welcome to ElkArte!', 'start' => 5])
        );
    }

    public function testQuerylessBoardAndTopic(): void
    {
        $gen = $this->makeGenerator('Queryless');

        // Board id=1, default start .0 when not provided
        $this->assertSame(
            $this->scripturl . '?board,1.0.html',
            $gen->get('board', ['board' => 1, 'name' => 'General Category'])
        );

        // With pagination start
        $this->assertSame(
            $this->scripturl . '?board,1.30.html',
            $gen->get('board', ['board' => 1, 'name' => 'General Category', 'start' => 30])
        );

        // Topic id=1, default start .0 when not provided
        $this->assertSame(
            $this->scripturl . '?topic,1.0.html',
            $gen->get('topic', ['topic' => 1, 'subject' => 'Welcome to ElkArte!'])
        );

        // Topic with pagination start
        $this->assertSame(
            $this->scripturl . '?topic,1.40.html',
            $gen->get('topic', ['topic' => 1, 'subject' => 'Welcome to ElkArte!', 'start' => 40])
        );
    }
}
