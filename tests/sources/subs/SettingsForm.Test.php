<?php

class TestSettingsForm extends \PHPUnit\Framework\TestCase
{
	protected $configVars = array();
	protected $permissionResults = array();
	protected $configValues = array();
	protected $backupGlobalsBlacklist = ['user_info'];

	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	public function setUp()
	{
		global $user_info;

		theme()->getTemplates()->loadLanguageFile('Admin', 'english', true, true);

		// Elevate the user.
		$user_info['permissions'] = array_merge($user_info['permissions'], ['manage_permissions']);

		$this->configVars = array(
			array('text', 'name1'),
			array('int', 'name2'),
			array('float', 'name3'),
			array('large_text', 'name4'),
			array('check', 'name5'),
			array('select', 'name6', array('value' => 'display')),
			array('select', 'name6m', array('value1' => 'display1', 'value2' => 'display2'), 'multiple' => true),
			array('password', 'name7'),
			array('permissions', 'name8'),
			array('bbc', 'name9'),
		''
		);
		$this->permissionResults = array(
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
		$this->configValues = array(
			'name1' => 'value1',
			'name2' => '5',
			'name3' => '4.6',
			'name4' => 'value4',
			'name5' => '1',
			'name6' => 'value',
			'name6m' => array('value1', 'value2'),
			'name7' => array('value', 'value'),
			'name8' => array(0 => 'on'),
			'name9' => array('b', 'i')
		);

		// Dummy data for setting permissions...
		$_POST = array(
			'name8' => array(0 => 'on'),
		);
	}

	/**
	 * Looping over the tests to verify
	 * \ElkArte\SettingsForm\SettingsForm::prepare works as expected.
	 */
	public function testPrepare()
	{
		global $context;

		$settingsForm = new \ElkArte\SettingsForm\SettingsForm(\ElkArte\SettingsForm\SettingsForm::DB_ADAPTER);
		$settingsForm->setConfigVars($this->configVars);
		$settingsForm->prepare();
		$this->assertSame($this->configVars, $settingsForm->getConfigVars());
		$this->assertInstanceOf('ElkArte\\SettingsForm\\SettingsFormAdapter\\Adapter', $settingsForm->getAdapter());
		$this->assertCount(1, $context['config_vars'][$this->configVars[5][1]]['data']);
		$this->assertContains('value', $context['config_vars'][$this->configVars[5][1]]['data'][0]);
		$this->assertCount(2, $context['config_vars'][$this->configVars[6][1]]['data']);
		$this->assertContains('value1', $context['config_vars'][$this->configVars[6][1]]['data'][0]);
		$this->assertCount(35, $context['config_vars'][$this->configVars[9][1]]['data']);
		$this->assertContains(array('tag' => 'b', 'show_help' => false), $context['config_vars'][$this->configVars[9][1]]['data']);
		$context['config_vars'][$this->configVars[6][1]]['name'] = str_replace('[]', '', $context['config_vars'][$this->configVars[6][1]]['name']);
		foreach ($this->configVars as $configVar)
		{
			if (is_array($configVar))
			{
				$this->assertTrue(isset($context['config_vars'][$configVar[1]]));
				$this->assertSame($configVar[0], $context['config_vars'][$configVar[1]]['type']);
				$this->assertSame($configVar[1], $context['config_vars'][$configVar[1]]['name']);
			}
		}
		$this->assertEquals($this->permissionResults, $context['permissions'][$this->configVars[8][1]]);
	}

	/**
	 * Looping over the tests to verify
	 * \ElkArte\SettingsForm\SettingsForm::save works as expected.
	 */
	public function testSaveDb()
	{
		global $context, $modSettings;

		$settingsForm = new \ElkArte\SettingsForm\SettingsForm(\ElkArte\SettingsForm\SettingsForm::DB_ADAPTER);
		$settingsForm->setConfigVars($this->configVars);
		$settingsForm->setConfigValues((array) $this->configValues);
		$this->assertSame($this->configValues, $settingsForm->getConfigValues());
		$settingsForm->save();
		$modSettings['bbc_disabled_' . $this->configVars[9][1]] = $this->configValues['name9'];
		$settingsForm->prepare();
		$this->assertisSaved();
		$this->permissionResults[0]['status'] = 'on';
		$this->assertEquals($this->permissionResults, $context['permissions'][$this->configVars[8][1]]);
	}

	public function assertisSaved()
	{
		global $context;

		foreach ($this->configValues as $varName => $configValue)
		{
			if (!is_array($configValue))
			{
				$this->assertTrue(isset($context['config_vars'][$varName]));
				$this->assertSame($configValue, $context['config_vars'][$varName]['value']);
			}
		}
		$this->assertSame('value', $context['config_vars'][$this->configVars[5][1]]['value']);
		$this->assertContains('value1', $context['config_vars'][$this->configVars[6][1]]['value']);
		$this->assertSame(array('b', 'i'), $context['config_vars'][$this->configVars[9][1]]['disabled_tags']);
	}

	/**
	 * cleanup data we no longer need at the end of the tests in this class.
	 *
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	public function tearDown()
	{
		$db = database();
		$request = $db->query('', '
			DELETE FROM {db_prefix}settings
			WHERE variable IN ({array_string:setting_name})',
			array(
				'setting_name' => array_keys($this->configValues),
			)
		);
	}

	public function testSaveDbTable()
	{
		$this->assertSame('W', chr(ord($this->getMessageBody())));
		$settingsForm = new \ElkArte\SettingsForm\SettingsForm(\ElkArte\SettingsForm\SettingsForm::DBTABLE_ADAPTER);
		$settingsForm->getAdapter()->setTableName('messages');
		$settingsForm->getAdapter()->setEditId(1);
		$settingsForm->getAdapter()->setEditName('id_msg');
		$settingsForm->getAdapter()->setIndexes(array('id_msg'));
		$settingsForm->setConfigVars(array(array('text', 'body', 'mask' => array('ucfirst'))));
		$settingsForm->setConfigValues((array) array('body' => 'hi & by'));
		$settingsForm->save();
		$this->assertSame('Hi & by', $this->getMessageBody());
	}

	public function getMessageBody()
	{
		$db = database();
		$request = $db->query('', '
			SELECT body
			FROM {db_prefix}messages
			WHERE id_msg = 1');
		list ($messageBody) = $db->fetch_row($request);
		$db->free_result($request);

		return $messageBody;
	}

	public function testPrepareFile()
	{
		global $context;

		$this->configVars = array(
			array('mtitle', 'maintenance_subject', 'file', 'text', 36),
			array('enableCompressedOutput', 'enableCompressedOutput', 'db', 'check', null, 'enableCompressedOutput'),
		);
		$settingsForm = new \ElkArte\SettingsForm\SettingsForm(\ElkArte\SettingsForm\SettingsForm::FILE_ADAPTER);
		$settingsForm->setConfigVars($this->configVars);
		$settingsForm->prepare();
		foreach ($this->configVars as $configVar)
		{
			$this->assertTrue(isset($context['config_vars'][$configVar[0]]));
			$this->assertSame($configVar[3], $context['config_vars'][$configVar[0]]['type']);
			$this->assertSame($configVar[0], $context['config_vars'][$configVar[0]]['name']);
		}
		global $mtitle;
		$this->assertSame('Maintenance Mode', $mtitle);
		$this->assertSame('Maintenance Mode', $context['config_vars'][$this->configVars[0][0]]['value']);
		$this->assertEquals(0, $context['config_vars'][$this->configVars[1][0]]['value']);
	}

	public function testSaveFile()
	{
		global $context;

		$this->configVars = array(
			array('mtitle', 'maintenance_subject', 'file', 'text', 36),
			array('enableCompressedOutput', 'enableCompressedOutput', 'db', 'check', null, 'enableCompressedOutput'),
		);
		$this->configValues = array(
			'mtitle' => 'value',
			'enableCompressedOutput' => '1'
		);
		$settingsForm = new \ElkArte\SettingsForm\SettingsForm(\ElkArte\SettingsForm\SettingsForm::FILE_ADAPTER);
		$settingsForm->setConfigVars($this->configVars);
		$settingsForm->setConfigValues((array) $this->configValues);
		$settingsForm->save();

		// Reload
		global $mtitle;
		require(BOARDDIR . '/Settings.php');
		$settingsForm->setConfigVars($this->configVars);
		$settingsForm->prepare();
		$this->assertSame('value', $mtitle);
		$this->assertSame('value', $context['config_vars'][$this->configVars[0][0]]['value']);
		$this->assertEquals(1, $context['config_vars'][$this->configVars[1][0]]['value']);
	}
}
