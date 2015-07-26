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
			'usernotif_favicon_bgColor',
			'usernotif_favicon_textColor',
			'usernotif_favicon_type',
			'usernotif_favicon_position',
		);
		foreach ($rules as $key)
		{
			if ($this->settingExists($key))
			{
				$notif_opt[] = '
					' . JavaScriptEscape(str_replace('usernotif_favicon_', '', $key)) . ': ' . JavaScriptEscape($this->_modSettings[$key]);
			}
		}

		$number = $user_info['mentions'];
		call_integration_hook('integrate_adjust_favicon_number', array(&$number));

		addInlineJavascript('
			$(document).ready(function() {
				ElkNotifier.add(new ElkFavicon({
					number: ' . $number . ',
					fontStyle: \'bolder\',
					animation: \'none\'' . (!empty($notif_opt) ? ',' . implode(',', $notif_opt) : '') . '
				}));
			});', true);

		if (!empty($this->_modSettings['usernotif_desktop_enable']))
		{
			loadJavascriptFile('desktop-notify.js');
			addInlineJavascript('
				$(document).ready(function() {
					ElkNotifier.add(new ElkDesktop());
				});', true);
		}
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
			$types[$val] = $txt['usernotif_favicon_shape_' . $val];
		$positions = array();
		foreach ($this->_valid_positions as $val)
			$positions[$val] = $txt['usernotif_favicon_' . $val];

		$config_vars = array(
			array('title', 'usernotif_title'),
			array('check', 'usernotif_desktop_enable'),
			array('check', 'usernotif_favicon_enable'),
		);
		$config_vars[] = array(
			'select',
			'usernotif_favicon_type',
			$types
		);
		$config_vars[] = array(
			'select',
			'usernotif_favicon_position',
			$positions
		);
		$config_vars[] = array('color', 'usernotif_favicon_bgColor');
		$config_vars[] = array('color', 'usernotif_favicon_textColor');

		return $config_vars;
	}

	public function validate($post)
	{
		$validator = new Data_Validator();
		$validation_rules = array(
			'usernotif_favicon_bgColor' => 'valid_color',
			'usernotif_favicon_textColor' => 'valid_color',
			'usernotif_favicon_type' => 'contains[' . implode(',', $this->_valid_types) . ']',
			'usernotif_favicon_position' => 'contains[' . implode(',', $this->_valid_positions) . ']',
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