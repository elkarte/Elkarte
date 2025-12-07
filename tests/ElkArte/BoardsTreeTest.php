<?php

namespace ElkArte;

use tests\ElkArteCommonSetupTest;

class BoardsTreeTest extends ElkArteCommonSetupTest
{
    private BoardsTree $tree;

    protected function setUp(): void
    {
        parent::setUp();

        // Instantiate using the live DB connection provided by the testbed
        $this->tree = new BoardsTree(\database());
    }

    public function testCategoriesAreLoaded(): void
    {
        $cats = $this->tree->getCategories();

        // Testbed has 1 category with id 1 named 'General Category'
        $this->assertArrayHasKey(1, $cats);
        $this->assertSame('General Category', $cats[1]['node']['name'] ?? null);
        $this->assertSame(1, $cats[1]['node']['id'] ?? null);
    }

    public function testBoardsAreLoaded(): void
    {
        $boards = $this->tree->getBoards();

        // Testbed has 1 board with id 1 named 'General Discussion'
        $this->assertArrayHasKey(1, $boards);
        $this->assertSame('General Discussion', $boards[1]['name'] ?? null);
        $this->assertSame(1, $boards[1]['id'] ?? null);
        $this->assertSame(1, $boards[1]['category'] ?? null);
    }

    public function testCategoryAndBoardExistenceChecks(): void
    {
        $this->assertTrue($this->tree->categoryExists(1));
        $this->assertFalse($this->tree->categoryExists(999));

        $this->assertTrue($this->tree->boardExists(1));
        $this->assertFalse($this->tree->boardExists(999));
    }

    public function testGetCategoryNodeById(): void
    {
        $cat = $this->tree->getCategoryNodeById(1);
        $this->assertSame('General Category', $cat['node']['name'] ?? null);

        $this->expectException(\ElkArte\Exceptions\Exception::class);
        $this->tree->getCategoryNodeById(999);
    }

    public function testGetBoardsInCategory(): void
    {
        $boardsInCat = $this->tree->getBoardsInCat(1);
        $this->assertIsArray($boardsInCat);
        $this->assertContains(1, $boardsInCat);

        $this->expectException(\ElkArte\Exceptions\Exception::class);
        $this->tree->getBoardsInCat(999);
    }

    public function testGetBoardByIdAndChildren(): void
    {
        $board = $this->tree->getBoardById(1);
        $this->assertSame('General Discussion', $board['name'] ?? null);
        $this->assertSame(0, $board['parent'] ?? null, 'Root board should have parent 0');

        // With a single board in the testbed there are no children
        $children = $this->tree->allChildsOf(1);
        $this->assertIsArray($children);
        $this->assertCount(0, $children);
    }

    public function testIsChildOfOnSingleBoardSetup(): void
    {
        // Board 1 is root (parent 0), so it is not a child of anyone
        $this->assertFalse($this->tree->isChildOf(1, 0));
        $this->assertFalse($this->tree->isChildOf(1, 999));
    }
}
