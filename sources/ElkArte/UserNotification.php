<?php

/**
 * Notifies the user about mentions and alike.
 * The version provided shows the number of notifications in the favicon
 * and sends a desktop notification if a new notification is present.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

use ElkArte\Languages\Txt;

/**
 * Class UserNotification
 *
 * @package ElkArte
 */
class UserNotification extends AbstractModel
{
	/**
	 * All the shapes the icon can be.
	 *
	 * @var string[]
	 */
	protected $_valid_types = array(
		'circle',
		'rectangle',
	);

	/**
	 * The positions the icon can be placed at.
	 *
	 * @var string[]
	 */
	protected $_valid_positions = array(
		'up',
		'down',
		'left',
		'upleft',
	);

	/**
	 * Construct, Load the language file and make db/user info available to the class
	 *
	 * @param \ElkArte\Database\QueryInterface $db
	 * @param \ElkArte\UserInfo|\ElkArte\ValuesContainer $user
	 */
	public function __construct($db, $user)
	{
		parent::__construct($db, $user);
		Txt::load('UserNotifications');
	}

	/**
	 * Loads up the needed interfaces (favicon or desktop notifications).
	 */
	public function present()
	{
		if (!empty($this->_modSettings['usernotif_favicon_enable']))
		{
			$this->_addFaviconNumbers($this->user->mentions);
		}

		if (!empty($this->_modSettings['usernotif_desktop_enable']))
		{
			$this->_addDesktopNotifications();
		}
	}

	/**
	 * Prepares the javascript for adding the nice number to the favicon.
	 *
	 * @param int $number the number to show
	 */
	protected function _addFaviconNumbers($number)
	{
		call_integration_hook('integrate_adjust_favicon_number', array(&$number));

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

		theme()->addInlineJavascript('
			$(function() {
				ElkNotifier.add(new ElkFavicon({
					number: ' . $number . ',
					fontStyle: \'bolder\',
					animation: \'none\'' . (!empty($notif_opt) ? ',' . implode(',', $notif_opt) : '') . '
				}));
			});', true);
	}

	/**
	 * Validates if a setting exists.
	 *
	 * @param string $key modSettings key
	 *
	 * @return bool
	 * @todo Really needed?
	 *
	 */
	protected function settingExists($key)
	{
		return isset($this->_modSettings[$key]) && $this->_modSettings[$key] !== '';
	}

	/**
	 * Prepares the javascript for desktop notifications.  The service worker is used on
	 * mobile devices (at least chrome) and needs to be in the root for proper global access.
	 */
	protected function _addDesktopNotifications()
	{
		loadJavascriptFile('push.min.js');
		loadJavascriptFile('desktop-notify.js');
		theme()->addInlineJavascript('
			$(function() {
				Push.config({serviceWorker: "./elkServiceWorker.min.js"}); 
				
				ElkNotifier.add(new ElkDesktop(
					{\'icon\': $(\'head\').find("link[rel=\'shortcut icon\']").attr("href")}
				));
			});', true);
	}

	/**
	 * Returns the configurations for the feature.
	 *
	 * @return mixed[]
	 */
	public function addConfig()
	{
		global $txt;

		$types = array();
		foreach ($this->_valid_types as $val)
		{
			$types[$val] = $txt['usernotif_favicon_shape_' . $val];
		}

		$positions = array();
		foreach ($this->_valid_positions as $val)
		{
			$positions[$val] = $txt['usernotif_favicon_' . $val];
		}

		return array(
			array('title', 'usernotif_title'),
			array('check', 'usernotif_desktop_enable'),
			array('check', 'usernotif_favicon_enable'),
			array('select', 'usernotif_favicon_type', $types),
			array('select', 'usernotif_favicon_position', $positions),
			array('color', 'usernotif_favicon_bgColor'),
			array('color', 'usernotif_favicon_textColor'),
		);
	}

	/**
	 * Validates the input when saving the settings.
	 *
	 * @param array|object $post An array containing the settings (usually $_POST)
	 *
	 * @return array|object
	 */
	public function validate($post)
	{
		$validator = new DataValidator();
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
			{
				$post[$key] = $validator->{$key};
			}
			else
			{
				$post[$key] = !empty($post[$key]) && isset($this->_modSettings[$key]) ? $this->_modSettings[$key] : '';
			}
		}

		return $post;
	}
}
