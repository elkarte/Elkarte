<?php

/**
 * TestCase class for menu class.
 */
class TestMenu extends PHPUnit_Framework_TestCase
{
	protected $test_areas;
	protected $test_options;
	private $menu;

	/**
	 * Prepare some test data, to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	protected function setUp()
	{
		global $context, $user_info;

		$this->test_areas = array(
			'section1' => array(
				'title' => 'One',
				'permission' => array('admin_forum'),
				'areas' => array(
					'area1' => array(
						'label' => 'Area1 Label',
						'controller' => 'Area1_Controller',
						'function' => 'action_area1',
						'icon' => 'transparent.png',
						'class' => 'test_img_area1',
					),
				),
			),
			'section2' => array(
				'title' => 'Two',
				'permission' => array('admin_forum'),
				'areas' => array(
					'area2' => array(
						'label' => 'Area2 Label',
						'controller' => 'Area2_Controller',
						'function' => 'action_area2',
						'icon' => 'transparent.png',
						'class' => 'test_img_area2',
						'custom_url' => 'custom_url',
						'hidden' => true,
					),
					'area3' => array(
						'permission' => 'area3 permission',
						'label' => 'Area3 Label',
						'controller' => 'Area3_Controller',
						'function' => 'action_area3',
						'icon' => 'transparent.png',
						'class' => 'test_img_area3',
						'subsections' => array(
							'sub1' => array('Sub One', 'url' => 'some url', 'active' => true),
							'sub2' => array('Sub Two', 'enabled' => false)),
					),
					'area4' => array(
						'label' => 'Area4 Label',
						'controller' => 'Area4_Controller',
						'function' => 'action_area4',
						'icon' => 'transparent.png',
						'class' => 'test_img_area4',
						'enabled' => false,
					),
				),
			),
			'section3' => array(
				'title' => 'Three',
				'permission' => array('admin_forum'),
				'enabled' => false,
				'areas' => array(
					'area5' => array(
						'label' => 'Area5 Label',
						'controller' => 'Area5_Controller',
						'function' => 'action_area5',
						'icon' => 'transparent.png',
						'class' => 'test_img_area5',
					),
				),
			)
		);

		// Set our menu options
		$this->test_options = array(
			'hook' => 'test',
			'default_include_dir' => ADMINDIR,
			'extra_url_parameters' => array('extra' => 'param')
		);

		// Stuff the ballet box
		$context['session_var'] = 'abcde';
		$context['session_id'] = '123456789';
		$context['right_to_left'] = false;

		// Your the admin now
		$user_info['is_admin'] = true;
		$context['current_action'] = 'section1';
	}

	/**
	 * cleanup data we no longer need at the end of the tests in this class.
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	protected function tearDown()
	{
	}

	/**
	 * Testing the option hook being set
	 */
	public function testOptions()
	{
		global $context;

		$context['current_action'] = 'section1';

		// Setup the menu
		$this->menu = new Menu;
		$this->menu->addOptions($this->test_options);
		$this->menu->addAreas($this->test_areas);
		$test_include_data = $this->menu->prepareMenu();

		$expected = array(
			'label' => 'Area1 Label', 'controller' => 'Area1_Controller', 'function' => 'action_area1',
			'icon' => 'transparent.png', 'class' => 'test_img_area1', 'current_action' => 'section1',
			'current_area' => 'area1', 'current_section' => 'section1', 'current_subsection' => '');

		$this->assertSame($expected, $test_include_data);
	}

	public function testOptionsAdd()
	{
		// Setup the menu
		$this->menu = new Menu;
		$this->menu->addOptions($this->test_options);
		$this->menu->addAreas($this->test_areas);
		$this->menu->addOptions(array('action' => 'section2', 'current_area' => 'area3'));
		$test_include_data = $this->menu->prepareMenu();

		$expected = array(
			'label' => 'Area2 Label', 'controller' => 'Area2_Controller', 'function' => 'action_area2',
			'icon' => 'transparent.png', 'class' => 'test_img_area2', 'custom_url' => 'custom_url', 'hidden' => true,
			'current_action' => 'section2', 'current_area' => 'area3', 'current_section' => 'section2', 'current_subsection' => '');

		$this->assertSame($expected, $test_include_data);
	}

	/**
	 * Testing the option hook being set
	 */
	public function testMenu()
	{
		global $context;

		// Setup the menu
		$this->menu = new Menu;
		$this->menu->addOptions($this->test_options);
		$this->menu->addAreas($this->test_areas);
		$test_include_data = $this->menu->prepareMenu();
		$this->menu->setContext();

		$result = $context['menu_data_' . $context['max_menu_id']];

		// really an options test
		$this->assertStringStartsWith(';extra=param', $result['extra_parameters']);

		// section 3 is not enabled, so it should not appear
		$this->assertArrayNotHasKey('section3', $result['sections']);

		// this subsection was disabled
		$this->assertEquals(true, $result['sections']['section2']['areas']['area3']['subsections']['sub2']['disabled']);

		// this subsection has a url and was enabled
		$this->assertEquals('some url', $result['sections']['section2']['areas']['area3']['subsections']['sub1']['url']);
		$this->assertEquals(true, $result['sections']['section2']['areas']['area3']['subsections']['sub1']['is_first']);

		// section 2 areas->area2 is hidden so should not be here
		$this->assertArrayNotHasKey('area2', $result['sections']['section2']['areas']);
	}

	public function testMenuReplace()
	{
		global $context;

		$add = array('section3' => array(
			'title' => 'ThreeNew',
			'permission' => array('admin_forum'),
			'enabled' => true,
			'areas' => array(
				'area5' => array(
					'label' => 'Area5 Label',
					'controller' => 'Area5_Controller',
					'function' => 'action_area5',
					'icon' => 'transparent.png',
					'class' => 'test_img_area5',
				),
			),
		));

		// Setup the menu
		$this->menu = new Menu;
		$this->menu->addOptions($this->test_options);
		$this->menu->addAreas($this->test_areas);

		// replace section 3 which was disabled
		$this->menu->addAreas($add);

		$test_include_data = $this->menu->prepareMenu();
		$this->menu->setContext();

		$result = $context['menu_data_' . $context['max_menu_id']];

		// Section 3 should now appear, and be updated
		$this->assertArrayHasKey('section3', $result['sections']);
		$this->assertEquals('ThreeNew', $result['sections']['section3']['title']);
	}

	public function testMenuAdd()
	{
		global $context;

		$add = array(
			'section4' => array(
				'title' => 'Four',
				'permission' => array('admin_forum'),
				'areas' => array(
					'area6' => array(
						'label' => 'Area6 Label',
						'controller' => 'Area6_Controller',
						'function' => 'action_area6',
						'icon' => 'transparent.png',
						'class' => 'test_img_area6',
					),
				),
			),
		);

		// Setup the menu
		$this->menu = new Menu;
		$this->menu->addOptions($this->test_options);
		$this->menu->addAreas($this->test_areas);

		// Add options to show the selection
		$this->menu->addOptions(array('action' => 'section4', 'current_area' => 'area6'));

		// Add a new section 4
		$this->menu->addAreas($add);

		// Go to it
		$test_include_data = $this->menu->prepareMenu();
		$this->menu->setContext();

		$result = $context['menu_data_' . $context['max_menu_id']];

		// Section 4 should now appear, and area 6 selected
		$this->assertArrayHasKey('section4', $result['sections']);
		$this->assertEquals('Four', $result['sections']['section4']['title']);
		$this->assertEquals('Area6 Label', $test_include_data['label']);
	}
}