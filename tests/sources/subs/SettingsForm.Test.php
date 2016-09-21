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
		loadLanguage('ManageSettings', 'english', true, true);
	}

	public function testInit()
	{
		$this->doInit();
	}

	/**
	 * Looping over the tests to verify
	 * Settings_Form::init works as expected.
	 */
	public function doInit()
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
}
