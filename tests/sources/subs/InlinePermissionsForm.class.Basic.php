<?php

/**
 * @runTestsInSeparateProcesses
 */
class TestInlinePermissionsForm extends PHPUnit_Framework_TestCase
{
	protected $permissions = array();
	protected $temp_db = null;

	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	public function setUp()
	{
		global $settings, $boardurl, $txt, $language, $user_info, $db_prefix;

		require_once(SOURCEDIR . '/database/Db.php');
		require_once(SOURCEDIR . '/database/Db-abstract.class.php');
		if (!defined('DB_TYPE'))
		{
			require_once(SOURCEDIR . '/database/Db-mysql.class.php');
		}
		require_once(BOARDDIR . '/tests/dummies/Database.php');

		$reflectedClass = new \ReflectionClass('Database_' . DB_TYPE);
		$reflectedProperty = $reflectedClass->getProperty('_db');
		$reflectedProperty->setAccessible(true);
		$this->temp_db = $reflectedProperty->getValue();

		$reflectedProperty->setValue(null, \ElkArte\Tests\Dummies\Database::db());

		$db_prefix = 'elkarte_';
		$settings['theme_url'] = $settings['default_theme_url'] = $boardurl . '/themes/default';
		$settings['theme_dir'] = $settings['default_theme_dir'] = BOARDDIR . '/themes/default';

		$language = 'english';
		$txt = array();
		loadLanguage('index+Admin');

		$user_info = array(
			'permissions' => array(
				'manage_permissions',
			),
			'is_admin' => false
		);
		$this->permissions = array(
			// A simple test on guests and regular members
			array(
				'permissions' => array('testing'),
				'excluded' => array(),
				'result' => array(
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
				)
			),
			// A slightly more complex test adding a couple of groups
			array(
				'permissions' => array('testing'),
				'excluded' => array(),
				'database' => array(
					array(
						'query' => '
			SELECT id_group, CASE WHEN add_deny = 0 THEN \'deny\' ELSE \'on\' END AS status, permission
			FROM elkarte_permissions
			WHERE id_group IN (-1, 0)
				AND permission IN (\'testing\')',
						'result' => array(
							array(
								'id_group' => -1,
								'status' => 'on',
								'permission' => 'testing',
							),
							array(
								'id_group' => 0,
								'status' => 'off',
								'permission' => 'testing',
							),
						)
					),
					array(
						'query' => '
			SELECT mg.id_group, mg.group_name, mg.min_posts, IFNULL(p.add_deny, -1) AS status, p.permission
			FROM elkarte_membergroups AS mg
				LEFT JOIN elkarte_permissions AS p ON (p.id_group = mg.id_group AND p.permission IN (\'testing\'))
			WHERE mg.id_group NOT IN (1, 3)
				AND mg.id_parent = -2
				AND mg.min_posts = -1
			ORDER BY mg.min_posts, CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, mg.group_name',
						'result' => array(
							array(
								'id_group' => 4,
								'group_name' => 'A group',
								'min_posts' => -1,
								'status' => 1,
								'permission' => 'testing',
							),
							array(
								'id_group' => 7,
								'group_name' => 'Another group',
								'min_posts' => -1,
								'status' => 1,
								'permission' => 'testing',
							),
						)
					),
				),
				'result' => array(
					-1 => array(
						'id' => -1,
						'name' => 'Guests',
						'is_postgroup' => false,
						'status' => 'on',
					),
					0 => array(
						'id' => 0,
						'name' => 'Regular Members',
						'is_postgroup' => false,
						'status' => 'off',
					),
					4 => array(
						'id' => 4,
						'name' => 'A group',
						'is_postgroup' => false,
						'status' => 'on',
					),
					7 => array(
						'id' => 7,
						'name' => 'Another group',
						'is_postgroup' => false,
						'status' => 'on',
					),
				)
			),
			// A more complex test adding a couple of groups and excluding one of them
			array(
				'permissions' => array('testing'),
				'excluded' => array(4),
				'database' => array(
					array(
						'query' => '
			SELECT id_group, CASE WHEN add_deny = 0 THEN \'deny\' ELSE \'on\' END AS status, permission
			FROM elkarte_permissions
			WHERE id_group IN (-1, 0)
				AND permission IN (\'testing\')',
						'result' => array(
							array(
								'id_group' => -1,
								'status' => 'on',
								'permission' => 'testing',
							),
							array(
								'id_group' => 0,
								'status' => 'off',
								'permission' => 'testing',
							),
						)
					),
					array(
						'query' => '
			SELECT mg.id_group, mg.group_name, mg.min_posts, IFNULL(p.add_deny, -1) AS status, p.permission
			FROM elkarte_membergroups AS mg
				LEFT JOIN elkarte_permissions AS p ON (p.id_group = mg.id_group AND p.permission IN (\'testing\'))
			WHERE mg.id_group NOT IN (1, 3)
				AND mg.id_parent = -2
				AND mg.min_posts = -1
			ORDER BY mg.min_posts, CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, mg.group_name',
						'result' => array(
							array(
								'id_group' => 4,
								'group_name' => 'A group',
								'min_posts' => -1,
								'status' => 1,
								'permission' => 'testing',
							),
							array(
								'id_group' => 7,
								'group_name' => 'Another group',
								'min_posts' => -1,
								'status' => 1,
								'permission' => 'testing',
							),
						)
					),
				),
				'result' => array(
					-1 => array(
						'id' => -1,
						'name' => 'Guests',
						'is_postgroup' => false,
						'status' => 'on',
					),
					0 => array(
						'id' => 0,
						'name' => 'Regular Members',
						'is_postgroup' => false,
						'status' => 'off',
					),
					7 => array(
						'id' => 7,
						'name' => 'Another group',
						'is_postgroup' => false,
						'status' => 'on',
					),
				)
			),
		);
	}

	public function tearDown()
	{
		$reflectedClass = new \ReflectionClass('Database_' . DB_TYPE);
		$reflectedProperty = $reflectedClass->getProperty('_db');
		$reflectedProperty->setAccessible(true);
		$reflectedProperty->setValue($this->temp_db);
	}

	/**
	 * Looping over the tests to verify Inline_Permissions_Form::init works
	 * as expected
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function testInit()
	{
		global $context;

		$db = database();
		$permissionsForm = new Inline_Permissions_Form;

		foreach ($this->permissions as $test)
		{
			$db->removeAll();
			if (isset($test['database']))
			{
				foreach ($test['database'] as $query)
				{
					$db->addQuery($query['query'], $query['result']);
				}
			}

			$permissionsForm->setExcludedGroups($test['excluded']);
			$permissionsForm->setPermissions($test['permissions']);
			$permissionsForm->init();
			foreach ($test['permissions'] as $permission)
			{
				$this->assertEquals($test['result'], $context[$permission]);
			}
		}
	}
}
