<?php

/**
 * TestCase class for menu subs.
 */
class TestMenuSubs extends ElkArteCommonSetupTest
{
	protected $test_areas;
	protected $test_options;
	protected $backupGlobalsBlacklist = ['user_info'];

	/**
	 * Prepare some test data, to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	public function setUp()
	{
		global $context;

		parent::setUp();
		parent::setSession();

		require_once(SUBSDIR . '/Menu.subs.php');

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
			'extra_url_parameters' => array('extra' => 'param'),
			'action' => 'section1',
			'current_area' => 'area1'
		);

		// Stuff the ballet box
		$context['right_to_left'] = false;
	}

	/**
	 * cleanup data we no longer need at the end of the tests in this class.
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	public function tearDown()
	{
		parent::tearDown();
		destroyMenu('last');
	}

	/**
	 * Testing the option hook being set
	 */
	public function testOptions()
	{
		global $context;

		$context['current_action'] = 'section1';
		$context['current_subaction'] = '';

		// Setup the menu
		$test_include_data = createMenu($this->test_areas, $this->test_options);

		$expected = array('controller' => 'Area1_Controller', 'function' => 'action_area1',
			'icon' => 'transparent.png', 'class' => 'test_img_area1', 'hidden' => false,
			'password' => false, 'subsections' => array(), 'label' => 'Area1 Label',
			'url' => 'http://127.0.0.1/index.php?action=section1;area=area1;extra=param;elk_test_session=elk_test_session',
			'permission' => array(), 'enabled' => true, 'current_action' => 'section1', 'current_area' => 'area1',
			'current_section' => 'section1', 'current_subsection' => ''
		);

		$this->assertSame($expected, $test_include_data);
	}

	/**
	 * Add new options, selecting a new area
	 */
	public function testOptionsAdd()
	{
		// Setup the menu
		$this->test_options = array_merge($this->test_options, array('action' => 'section2', 'current_area' => 'area3'));
		$test_include_data = createMenu($this->test_areas, $this->test_options);

		$expected = array(
			'controller' => 'Area3_Controller', 'function' => 'action_area3',
			'icon' => 'transparent.png', 'class' => 'test_img_area3', 'hidden' => false,
			'password' => false,
			'subsections' => array(
				'sub1' => array('label' => 'Sub One', 'counter' => '', 'url' => 'some url', 'permission' => array(), 'enabled' => true),
				'sub2' => array('label' => 'Sub Two', 'counter' => '', 'url' => '', 'permission' => array(), 'enabled' => false),
			),
			'label' => 'Area3 Label', 'url' => 'http://127.0.0.1/index.php?action=section2;area=area3;extra=param;elk_test_session=elk_test_session',
			'permission' => array(0 => 'area3 permission'),
			'enabled' => true, 'current_action' => 'section2', 'current_area' => 'area3', 'current_section' => 'section2', 'current_subsection' => 'sub1');

		$this->assertSame($expected, $test_include_data);
	}

	/**
	 * Testing the option hook being set
	 */
	public function testMenu()
	{
		global $context;

		$context['current_subaction'] = '';

		// Setup the menu
		createMenu($this->test_areas, $this->test_options);

		$result = $context['menu_data_' . $context['max_menu_id']];

		// really an options test
		$this->assertStringStartsWith(';extra=param', $result['extra_parameters']);

		// section 3 is not enabled, so it should not appear
		$this->assertArrayNotHasKey('section3', $result['sections']);

		// this subsection was disabled
		$this->assertArrayNotHasKey('sub2', $result['sections']['section2']['areas']['area3']['subsections']);

		// this subsection has a url and was enabled
		$this->assertEquals('some url', $result['sections']['section2']['areas']['area3']['subsections']['sub1']['url']);

		// section 2 areas->area2 is hidden so should not be here
		$this->assertArrayNotHasKey('area2', $result['sections']['section2']['areas']);
	}

	public function testMenuReplace()
	{
		global $context;

		$context['current_subaction'] = '';

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
		$this->test_areas = $this->mergeAreas($this->test_areas, $add);

		// Replace section 3 which was disabled
		createMenu($this->test_areas, $this->test_options);
		$result = $context['menu_data_' . $context['max_menu_id']];

		// Section 3 should now appear, and be updated
		$this->assertArrayHasKey('section3', $result['sections']);
		$this->assertEquals('ThreeNew', $result['sections']['section3']['label']);
	}

	public function testMenuAdd()
	{
		global $context;

		$context['current_subaction'] = '';

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

		// Add a new section 4
		$this->test_areas = $this->mergeAreas($this->test_areas, $add);

		// Add options to show the selection
		$this->test_options = array_merge($this->test_options, array('action' => 'section4', 'current_area' => 'area6'));

		// Create the menu
		$test_include_data = createMenu($this->test_areas, $this->test_options);

		$result = $context['menu_data_' . $context['max_menu_id']];

		// Section 4 should now appear, and area 6 selected
		$this->assertArrayHasKey('section4', $result['sections']);
		$this->assertEquals('Four', $result['sections']['section4']['label']);
		$this->assertEquals('Area6 Label', $test_include_data['label']);
	}

	private function mergeAreas(&$array1, &$array2)
	{
		$merged = $array1;
		foreach ($array2 as $key => &$value)
		{
			if (is_array($value) && isset($merged[$key]) && is_array($merged[$key]))
			{
				$merged[$key] = $this->mergeAreas($merged[$key], $value);
			}
			else
			{
				$merged[$key] = $value;
			}
		}
		return $merged;
	}
}