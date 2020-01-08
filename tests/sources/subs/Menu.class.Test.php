<?php

use ElkArte\Menu\Menu;
use ElkArte\Menu\MenuArea;
use ElkArte\Menu\MenuSection;
use ElkArte\Menu\MenuSubsection;

abstract class BaseMenuTest extends ElkArteCommonSetupTest
{
	public function testMenu()
	{
		global $context;

		$this->createMenu();

		// These are long-ass arrays, y'all!
		$result = $context['menu_data_' . $context['max_menu_id']];

		$this->assertArrayNotHasKey('section3', $result['sections']);
		$this->assertCount(3, $result['sections']);
		$this->assertCount(1, $result['sections']['section2']['areas']);
		$this->assertCount(3, $result['sections']['section2']['areas']['area3']['subsections']);
		$this->assertArrayNotHasKey('area2', $result['sections']['section2']['areas']);
		$this->assertArrayNotHasKey('sub3', $result['sections']['section2']['areas']['area3']['subsections']);
	}

	/**
	 * @dataProvider optionsProvider
	 */
	public function testOptions($k, $expectedKey, $expectedVal)
	{
		global $context;

		$this->createMenu();

		$result = $context['menu_data_' . $context['max_menu_id']];
		foreach ([
			$result['base_url'],
			$result['sections']['section2']['url'],
			$result['sections']['section2']['areas']['area3']['url'],
			$result['sections']['section2']['areas']['area3']['subsections']['sub1']['url'],
		] as $key => $url)
		{
			if ($k === $key)
			{
				parse_str(parse_url(strtr($url, ';', '&'), PHP_URL_QUERY), $result);
				$this->assertArrayHasKey($expectedKey, $result, $key . ': ' . $url . implode($result));
				$this->assertContains($expectedVal, $result, $key . ': ' . $url . implode($result));
				break;
			}
		}
	}

	public function optionsProvider()
	{
		global $context;

		return [
			[0, 'action', 'random'],
			[1, 'extra', 'param'],
			[1, 'action', 'random'],
			[1, $context['session_var'], $context['session_id']],
			[2, 'extra', 'param'],
			[2, 'action', 'random'],
			[2, $context['session_var'], $context['session_id']],
			[2, 'area', 'area3'],
			[3, 'extra', 'param'],
			[3, 'action', 'random'],
			[3, $context['session_var'], $context['session_id']],
			[3, 'area', 'area3'],
			[3, 'sa', 'sub1'],
		];
	}

	public function testCurrentlySelected()
	{
		global $context;

		$this->createMenu();
		$result = $context['menu_data_' . $context['max_menu_id']];
		$this->assertSame(1, $context['max_menu_id']);
		$this->assertSame('random', $result['current_action']);
		$this->assertSame('section1', $result['current_section']);
		$this->assertSame('area1', $result['current_area']);

		$this->prepareMenu();
		$this->addOptions(['action' => 'section2', 'current_area' => 'area3']);
		$this->createMenu();
		$result = $context['menu_data_' . $context['max_menu_id']];
		$this->assertSame(2, $context['max_menu_id']);
		$this->assertSame('section2', $result['current_action']);
		$this->assertSame('area3', $result['current_area']);
		$this->assertSame('sub2', $result['current_subsection']);

		$this->assertTrue(isset($context['menu_data_2']));
		$this->assertContains('generic_menu_dropdown', theme()->getLayers()->getLayers());
		$this->tearDown();
		$this->assertTrue(isset($context['menu_data_1']));
		$this->assertSame(1, $context['max_menu_id']);
		$this->assertContains('generic_menu_dropdown', theme()->getLayers()->getLayers());
		$this->tearDown();
		$this->assertFalse(isset($context['menu_data_1']));
		$this->assertSame(0, $context['max_menu_id']);
		$this->assertNotContains('generic_menu_dropdown', theme()->getLayers()->getLayers());
	}

	public function testAreaSelect()
	{
		global $context;

		$this->addOptions(['current_area' => 'area2']);
		$this->createMenu();
		$result = $context['menu_data_' . $context['max_menu_id']];
		$this->assertSame('area3', $result['current_area']);
		$this->assertSame('sub2', $result['current_subsection']);
	}

	public function testSaDefault()
	{
		global $context;

		$this->addOptions(['current_area' => 'area2']);
		$this->createMenu();
		$result = $context['menu_data_' . $context['max_menu_id']];
		$this->assertSame('area3', $result['current_area']);
		$this->assertSame('sub2', $result['current_subsection']);
	}

	public function testCounter()
	{
		global $context;

		$this->addOptions(['counters' => ['c' => 11]]);
		$context['current_subaction'] = 'sub4';
		$this->createMenu();
		$result = $context['menu_data_' . $context['max_menu_id']];
		$this->assertSame('Sub Four <span>[<strong>11</strong>]</span>', $result['sections']['section2']['areas']['area3']['subsections']['sub4']['label']);
	}
}

class MenuTest extends BaseMenuTest
{
	private $req;
	private $sections = [];
	private $options = [];
	private $menuObjects = [];

	public function setUp()
	{
		global $context;

		// Load in the common items so the system thinks we have an active login
		parent::setUp();

		$context['current_action'] = 'random';

		// Reset the menu counter. PHPUnit creates a copy of global
		// state at boot and applies it for each test.
		unset($context['max_menu_id']);

		$this->sections = [
		[
			'section1',
			MenuSection::buildFromArray(
				[
					'title' => 'One',
					'permission' => ['admin_forum'],
					'areas' => [
						'area1' => MenuArea::buildFromArray(
							[
								'label' => 'Area1 Label',
								'function' => function ()
								{
								},
							]
						),
					],
				]
			)
		], [
			'section2',
			MenuSection::buildFromArray(
				[
					'title' => 'Two',
					'permission' => ['admin_forum'],
					'areas' => [
						'area2' => MenuArea::buildFromArray(
							[
								'label' => 'Area2 Label',
								'function' => function ()
								{
								},
								'url' => 'url',
								'select' => 'area3',
								'hidden' => true,
							]
						),
						'area3' => MenuArea::buildFromArray(
							[
								'permission' => 'area3 permission',
								'label' => 'Area3 Label',
								'function' => function ()
								{
								},
								'subsections' => [
									'sub1' => MenuSubsection::buildFromArray(['Sub One', ['admin_forum']]),
									'sub2' => MenuSubsection::buildFromArray(['Sub Two', ['admin_forum'], true]),
									'sub3' => MenuSubsection::buildFromArray(
										['Sub Three', ['admin_forum'], 'enabled' => false]
									),
									'sub4' => MenuSubsection::buildFromArray(
										['Sub Four', ['admin_forum'], 'active' => ['sub1'], 'counter' => 'c']
									),
								],
							]
						),
						'area4' => MenuArea::buildFromArray(
							[
								'label' => 'Area4 Label',
								'function' => function ()
								{
								},
								'enabled' => false,
							]
						),
					],
				]
			)
		], [
			'section3',
			MenuSection::buildFromArray(
				[
					'title' => 'Three',
					'permission' => ['admin_forum'],
					'enabled' => false,
					'areas' => [
						'area5' => MenuArea::buildFromArray(
							[
								'label' => 'Area5 Label',
								'function' => function ()
								{
								},
								'icon' => 'transparent.png',
								'class' => 'admin_img_support',
							]
						),
					],
				]
			)
		], [
			'section4',
			MenuSection::buildFromArray(
				[
					'title' => 'Four',
					'permission' => ['admin_forum'],
					'areas' => [
						'area6' => MenuArea::buildFromArray(
							[
								'label' => 'Area6 Label',
								'function' => function ()
								{
								},
							]
						),
						'area7' => MenuArea::buildFromArray(
							[
								'label' => 'Area7 Label',
								'function' => function ()
								{
								},
							]
						),
					],
				]
			)
		]];
		$this->prepareMenu();
		$this->addOptions(['extra_url_parameters' => ['extra' => 'param']]);
	}

	/**
	 * Always clean everything!
	 */
	public function tearDown()
	{
		parent::tearDown();

		if (count($this->menuObjects) > 0)
		{
			$this->menuObjects[count($this->menuObjects) - 1]->destroy();
			unset($this->menuObjects[count($this->menuObjects) - 1]);
		}
	}

	public function addOptions($menuOptions)
	{
		$this->options = array_merge($this->options, $menuOptions);
		$this->menuObjects[count($this->menuObjects) - 1]->addOptions($this->options);
	}

	public function prepareMenu()
	{
		$this->req = new \ElkArte\HttpReq;
		$this->menuObjects[] = new Menu($this->req);
		foreach ($this->sections as list ($section_id, $section))
		{
			$this->menuObjects[count($this->menuObjects) - 1]->addSection($section_id, $section);
		}
	}

	/**
	 * Create a menu
	 *
	 * @return array
	 */
	public function createMenu()
	{
		global $context;

		$include_data = $this->menuObjects[count($this->menuObjects) - 1]->prepareMenu();
		$this->menuObjects[count($this->menuObjects) - 1]->setContext();
		$this->assertGreaterThanOrEqual(1, $context['max_menu_id']);

		return $include_data;
	}

	/**
	 * @expectedException Exception
	 */
	public function testEmpty()
	{
		(new Menu())->prepareMenu();
	}

	/**
	 * @expectedException Exception
	 */
	public function testFail()
	{
		\ElkArte\User::$info->is_admin = false;
		\ElkArte\User::$info->permissions = [];

		$this->createMenu();
	}

	public function testMisc()
	{
		foreach ($this->sections as list (, $section))
		{
			$this->assertInstanceOf('ElkArte\Menu\MenuSection', $section);
			foreach ($section->getAreas() as $area)
			{
				$this->assertInstanceOf('ElkArte\Menu\MenuArea', $area);
				foreach ($area->getSubsections() as $sub)
				{
					$this->assertInstanceOf('ElkArte\Menu\MenuSubsection', $sub);
				}
				$this->assertTrue(is_callable($area->getFunction(), true));
				$this->assertTrue(is_string($area->getController()));
			}
		}
	}
}
