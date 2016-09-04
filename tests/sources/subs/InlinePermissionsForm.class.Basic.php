<?php


class TestInlinePermissionsForm extends PHPUnit_Framework_TestCase
{
	protected $permissionsForm;
	protected $config_vars = array();
	protected $results = array();

	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	public function setUp()
	{
		global $settings, $boardurl, $txt, $language, $user_info, $db_prefix;

		$settings['theme_url'] = $settings['default_theme_url'] = $boardurl . '/themes/default';
		$settings['theme_dir'] = $settings['default_theme_dir'] = BOARDDIR . '/themes/default';
		$language = 'english';
		$txt = array();
		loadLanguage('index+Admin');
		$user_info['permissions'][] = 'manage_permissions';

		$this->config_vars = array(
			array('permissions', 'my_dummy_permission1'),
			array('permissions', 'my_dummy_permission2', 'excluded_groups' => array(-1, 0, 2)),
			array('permissions', 'my_dummy_permission3', 'excluded_groups' => array(-1, 0)),
			array('permissions', 'my_dummy_permission4', 'excluded_groups' => array(-1)),
		);
		$this->results = array(
			-1 => array(
				'id' => -1,
				'name' => 'Guests',
				'is_postgroup' => false,
				'status' => 'off',
			),
			0 => array(
				'id' => 0,
				'name' => 'Regular Members',
				'is_postgroup' => false,
				'status' => 'off',
			),
			2 => array(
				'id' => '2',
				'name' => 'Global Moderator',
				'is_postgroup' => false,
				'status' => 'off',
			),
		);

		$this->permissionsForm = new Inline_Permissions_Form;
		$this->permissionsForm->setPermissions($this->config_vars);
	}

	public function testInit()
	{
		$this->doInit();
	}

	/**
	 * Looping over the tests to verify
	 * Inline_Permissions_Form::init works as expected.
	 */
	public function doInit()
	{
		global $context;

		$this->permissionsForm->init();
		foreach ($this->config_vars as $test)
		{
			foreach ($this->config_vars as $permission)
			{
				$result = $this->results;
				if (isset($permission['excluded_groups']))
				{
					foreach ($permission['excluded_groups'] as $group)
					{
						if (isset($result[$group]))
							unset($result[$group]);
					}
				}
				$this->assertEquals($result, $context[$permission[1]]);
			}
		}
	}

	/**
	 * Looping over the tests to verify
	 * Inline_Permissions_Form::save works as expected.
	 */
	public function testSave()
	{
		global $context;

		// We need this for the in-line permissions
		createToken('admin-mp');
		$_POST = array(
			'my_dummy_permission1' => array(-1 => 'on', 0 => 'on', 2 => 'on'),
			'my_dummy_permission3' => array(2 => 'on'),
		);
		$this->permissionsForm->save();
		foreach ($_POST as $permission => $groupList)
		{
			$result = $this->results;
			foreach ($groupList as $group => $value)
			{
				$result[$group]['status'] = $value;
			}
		}
		$this->doInit();
	}
}
