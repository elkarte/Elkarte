<?php

/**
 * Show number of notifications in the favicon.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 dev
 *
 */

class Favicon_Notification
{
	protected $_valid_types = array(
		'circle',
		'rectangle',
	);
	protected $_valid_positions = array(
		'up',
		'down',
		'left',
		'upleft',
	);

	public function __construct($modSettings)
	{
		$this->_modSettings = $modSettings;

		loadLanguage('Faviconotif');
	}

	public function present()
	{
		global $user_info;

		loadJavascriptFile('favico.js');

		$notif_opt = array();
		$rules = array(
			'faviconotif_bgColor',
			'faviconotif_textColor',
			'faviconotif_type',
			'faviconotif_position',
		);
		foreach ($rules as $key)
		{
			if ($this->settingExists($key))
			{
				$notif_opt[] = '
					' . JavaScriptEscape(str_replace('faviconotif_', '', $key)) . ': ' . JavaScriptEscape($this->_modSettings[$key]);
			}
		}

		$number = $user_info['mentions'];
		call_integration_hook('integrate_adjust_favicon_number', array(&$number));

		addInlineJavascript('
			var mentions;
			$(document).ready(function() {
				mentions = new Favico({
					fontStyle: \'bolder\',
					animation: \'none\'' . (!empty($notif_opt) ? ',' . implode(',', $notif_opt) : '') . '
				});
				if (' . $number . ' > 0)
					mentions.badge(' . $number . ');
				elk_fetch_menstions();
			});', true);
	}

	protected function settingExists($key)
	{
		return isset($this->_modSettings[$key]) && $this->_modSettings[$key] !== '';
	}

	public function addConfig()
	{
		global $txt;

		$types = array();
		foreach ($this->_valid_types as $val)
			$types[$val] = $txt['faviconotif_shape_' . $val];
		$positions = array();
		foreach ($this->_valid_positions as $val)
			$positions[$val] = $txt['faviconotif_' . $val];

		$config_vars = array(
			array('title', 'faviconotif_title'),
			array('check', 'faviconotif_enable'),
		);
		$config_vars[] = array(
			'select',
			'faviconotif_type',
			$types
		);
		$config_vars[] = array(
			'select',
			'faviconotif_position',
			$positions
		);
		$config_vars[] = array('color', 'faviconotif_bgColor');
		$config_vars[] = array('color', 'faviconotif_textColor');

		return $config_vars;
	}

	public function validate($post)
	{
		$validator = new Data_Validator();
		$validation_rules = array(
			'faviconotif_bgColor' => 'valid_color',
			'faviconotif_textColor' => 'valid_color',
			'faviconotif_type' => 'contains[' . implode(',', $this->_valid_types) . ']',
			'faviconotif_position' => 'contains[' . implode(',', $this->_valid_positions) . ']',
		);

		// Cleanup the inputs! :D
		$validator->validation_rules($validation_rules);
		$validator->validate($post);
		foreach ($validation_rules as $key => $val)
		{
			$validation_errors = $validator->validation_errors($key);
			if (empty($validation_errors))
				$post[$key] = $validator->{$key};
			else
				$post[$key] = !empty($post[$key]) && isset($this->_modSettings[$key]) ? $this->_modSettings[$key] : '';
		}

		return $post;
	}
}