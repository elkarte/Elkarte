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
		global $txt, $context;

		$configVars = array(
				array('text', 'time_format'),
				array('float', 'time_offset', 'subtext' => 'setting_time_offset_note', 6, 'postinput' => 'hours', 'text_label' => 'setting_time_offset'),
				array('check', 'who_enabled'),
				array('int', 'lastActive', 6, 'postinput' => 'minutes'),
			'',
				array('select', 'enable_contactform', array('disabled' => 'contact_form_disabled', 'registration' => 'contact_form_registration', 'menu' => 'contact_form_menu')),
		);
		$settingsForm = new Settings_Form(new SettingsFormAdapterDb);
		$settingsForm->setConfigVars($configVars);
		$settingsForm->prepare();
		foreach ($configVars as $configVar)
		{
			if (is_array($configVar))
			{
				$this->assertTrue(isset($context['config_vars'][$configVar[1]]));
				$this->assertSame($configVar[0], $context['config_vars'][$configVar[1]]['type']);
				$this->assertSame($configVar[1], $context['config_vars'][$configVar[1]]['name']);
				$this->assertSame($txt[$configVar[1]], $context['config_vars'][$configVar[1]]['label']);
			}
		}
	}
}
