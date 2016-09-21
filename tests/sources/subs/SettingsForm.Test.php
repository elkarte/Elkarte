<?php

class TestSettingsForm extends PHPUnit_Framework_TestCase
{
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
	}

	/**
	 * Looping over the tests to verify
	 * Settings_Form::init works as expected.
	 */
	public function testInit()
	{
		global $context;

		$configVars = array(
			array('text', 'name1'),
			array('int', 'name2'),
			array('float', 'name3'),
			array('large_text', 'name4'),
			array('check', 'name5'),
			array('select', 'name6', array('value' => 'display')),
			array('select', 'name6m', array('value1' => 'display1', 'value2' => 'display2'), 'multiple' => true),
			array('password', 'name7'),
			array('permissions', 'name8'),
			array('bbc', 'name9')
		);
		$settingsForm = new Settings_Form(new SettingsFormAdapterDb);
		$settingsForm->setConfigVars($configVars);
		$settingsForm->prepare();

		$this->assertSame($configVars, $settingsForm->getConfigVars());
		$this->assertInstanceOf('SettingsFormAdapter', $settingsForm->getAdapter());
		$this->assertCount(1, $context['config_vars'][$configVars[5][1]]['data']);
		$this->assertContains('value', $context['config_vars'][$configVars[5][1]]['data'][0]);
		$this->assertCount(2, $context['config_vars'][$configVars[6][1]]['data']);
		$this->assertContains('value1', $context['config_vars'][$configVars[6][1]]['data'][0]);
		$this->assertCount(35, $context['config_vars'][$configVars[9][1]]['data']);
		$this->assertContains(array('tag' => 'b', 'show_help' => false), $context['config_vars'][$configVars[9][1]]['data']);
		$context['config_vars'][$configVars[6][1]]['name'] = str_replace('[]', '', $context['config_vars'][$configVars[6][1]]['name']);
		foreach ($configVars as $configVar)
		{
			if (is_array($configVar) && $configVar[0] != 'permissions')
			{
				$this->assertTrue(isset($context['config_vars'][$configVar[1]]));
				$this->assertSame($configVar[0], $context['config_vars'][$configVar[1]]['type']);
				$this->assertSame($configVar[1], $context['config_vars'][$configVar[1]]['name']);
			}
		}
	}

	/**
	 * Looping over the tests to verify
	 * Settings_Form::save works as expected.
	 */
	public function testSave()
	{
		global $context, $modSettings;

		$configVars = array(
			array('text', 'name1'),
			array('int', 'name2'),
			array('float', 'name3'),
			array('large_text', 'name4'),
			array('check', 'name5'),
			array('select', 'name6', array('value' => 'display')),
			array('select', 'name6m', array('value1' => 'display1', 'value2' => 'display2'), 'multiple' => true),
			array('password', 'name7'),
			array('permissions', 'name8'),
			array('bbc', 'name9')
		);
		$configValues = array(
			'name1' => 'value',
			'name2' => '5',
			'name3' => '4.6',
			'name4' => 'value',
			'name5' => '1',
			'name6' => 'value',
			'name6m' => array('value1', 'value2'),
			'name7' => array('value', 'value'),
			'name8' => array(0),
			'name9_enabledTags' => array('b', 'i')
		);
		$settingsForm = new Settings_Form(new SettingsFormAdapterDb);
		$settingsForm->setConfigVars($configVars);
		$settingsForm->setconfigValues($configValues);
		$settingsForm->save();
		$modSettings['bbc_disabled_' . $configVars[9][1]] = $configValues['name9_enabledTags'];
		$settingsForm->prepare();
		foreach ($configValues as $varName => $configValue)
		{
			if (!is_array($configValue))
			{
				$this->assertTrue(isset($context['config_vars'][$varName]));
				$this->assertSame($configValue, $context['config_vars'][$varName]['value']);
			}
		}
		$this->assertSame('value', $context['config_vars'][$configVars[5][1]]['value']);
		$this->assertContains('value1', $context['config_vars'][$configVars[6][1]]['value']);
		$this->assertSame(array('b', 'i'), $context['config_vars'][$configVars[9][1]]['disabled_tags']);
		$this->assertEquals(array(
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
				'status' => 'on',
			),
			2 => array(
				'id' => '2',
				'name' => 'Global Moderator',
				'is_postgroup' => false,
				'status' => 'off',
			),
		), $context['permissions'][$configVars[8][1]]);
	}
}
