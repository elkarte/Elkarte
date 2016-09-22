<?php

class TestInlinePermissionsForm extends PHPUnit_Framework_TestCase
{
	protected $permissionsForm;
	protected $permissionsObject;
	protected $config_vars = array();
	protected $results = array();
	private $illegal_permissions = array();
	private $illegal_guest_permissions = array();

	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	public function setUp()
	{
		global $settings, $boardurl, $txt, $language, $user_info, $db_prefix;

		// All this to initialize a language.
		// @todo Surely there must be a better way...
		$settings['theme_url'] = $settings['default_theme_url'] = $boardurl . '/themes/default';
		$settings['theme_dir'] = $settings['default_theme_dir'] = BOARDDIR . '/themes/default';
		$language = 'english';
		$txt = array();
		loadLanguage('Admin', 'english', true, true);

		// Elevate the user.
		$user_info['permissions'][] = 'manage_permissions';

		// Make sure they can't do certain things,
		// unless they have the right permissions.
		$this->permissionsObject = new Permissions;
		$this->illegal_permissions = $this->permissionsObject->getIllegalPermissions();

		$this->config_vars = array(
			array('permissions', 'my_dummy_permission1'),
			array('permissions', 'my_dummy_permission2', 'excluded_groups' => array(-1, 0, 2)),
			array('permissions', 'my_dummy_permission3', 'excluded_groups' => array(-1, 0)),
			array('permissions', 'my_dummy_permission4', 'excluded_groups' => array(-1)),
			array('permissions', 'admin_forum'), // Illegal permission
			array('permissions', 'send_mail'), // Illegal guest permission
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

		$this->permissionsForm = new ElkArte\sources\subs\SettingsFormAdapter\InlinePermissions;
		$this->permissionsForm->setPermissions($this->config_vars);

		// Load the permission settings that guests cannot have
		$this->illegal_guest_permissions = array_intersect(
			array_map(
				function ($permission)
				{
					return str_replace(array('_any', '_own'), '', $permission[1]);
				}, $this->config_vars
			), $this->permissionsObject->getIllegalGuestPermissions()
		);
	}

	public function testInit()
	{
		$this->doInit();
	}

	/**
	 * Looping over the tests to verify
	 * InlinePermissionsAdapter::prepare works as expected.
	 */
	public function doInit($result = array())
	{
		global $context;

		$this->permissionsForm->prepare();
		foreach ($this->config_vars as $permission)
		{
			if (!isset($result[$permission[1]]))
			{
				$result[$permission[1]] = $this->results;
			}
			if (isset($permission['excluded_groups']))
			{
				foreach ($permission['excluded_groups'] as $group)
				{
					if (isset($result[$permission[1]][$group]))
					{
						unset($result[$permission[1]][$group]);
					}
				}
			}

			// Is this permission one that guests can't have?
			if (in_array($permission[1], $this->illegal_guest_permissions))
			{
				unset($result[$permission[1]][-1]);
				$this->assertFalse(isset($context['permissions'][$permission[1]][-1]));
			}

			// Is this permission outright disabled?
			if (in_array($permission[1], $this->illegal_permissions))
			{
				unset($result[$permission[1]]);
				$this->assertFalse(isset($context['permissions'][$permission[1]]));
				continue;
			}

			$this->assertEquals($result[$permission[1]], $context['permissions'][$permission[1]]);
		}
	}

	/**
	 * Looping over the tests to verify
	 * InlinePermissionsAdapter::save works as expected.
	 */
	public function testSave()
	{
		global $context;

		// Dummy data for setting permissions...
		$_POST = array(
			'my_dummy_permission1' => array(-1 => 'on', 0 => 'on', 2 => 'on'),
			'my_dummy_permission3' => array(2 => 'on'),
		);
		$this->permissionsForm->save();
		foreach ($_POST as $permission => $groupList)
		{
			$result[$permission] = $this->results;
			foreach ($groupList as $group => $value)
			{
				$result[$permission][$group]['status'] = $value;
			}
		}
		$this->doInit($result);
	}
}
