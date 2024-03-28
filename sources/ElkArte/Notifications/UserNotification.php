<?php

/**
 * Notifies the user about mentions and alike.
 *
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

namespace ElkArte\Notifications;

use ElkArte\AbstractModel;
use ElkArte\Database\QueryInterface;
use ElkArte\Helper\DataValidator;
use ElkArte\Languages\Txt;
use ElkArte\UserInfo;

/**
 * Class UserNotification
 *
 * @package ElkArte
 */
class UserNotification extends AbstractModel
{
	/** @var string[] All the shapes the icon can be. */
	protected $_valid_types = [
		'circle',
		'rectangle',
	];

	/** @var string[] The positions the icon can be placed at. */
	protected $_valid_positions = [
		'up',
		'down',
		'left',
		'upleft',
	];

	/**
	 * Construct, Load the language file and make db/user info available to the class
	 *
	 * @param QueryInterface $db
	 * @param UserInfo|\ElkArte\Helper\ValuesContainer $user
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

		// Can only do this over secure connections
		if (!empty($this->_modSettings['usernotif_desktop_enable']) && detectServer()->supportsSSL())
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
		call_integration_hook('integrate_adjust_favicon_number', [&$number]);

		loadJavascriptFile(['ext/favico.js', 'favicon-notify.js'], ['defer' => true]);

		$notif_opt = [];
		$rules = [
			'usernotif_favicon_bgColor',
			'usernotif_favicon_textColor',
			'usernotif_favicon_type',
			'usernotif_favicon_position',
		];
		foreach ($rules as $key)
		{
			if ($this->settingExists($key))
			{
				$notif_opt[] = '
					' . JavaScriptEscape(str_replace('usernotif_favicon_', '', $key)) . ': ' . JavaScriptEscape($this->_modSettings[$key]);
			}
		}

		theme()->addInlineJavascript('
			document.addEventListener("DOMContentLoaded", function() {
				ElkNotifier.add(new ElkFavicon({
					number: ' . $number . ',
					fontStyle: "bolder",
					animation: "none"' . (empty($notif_opt) ? '' : ',' . implode(',', $notif_opt)) . '
				}));
			});', true);
	}

	/**
	 * Validates if a setting exists.
	 *
	 * @param string $key modSettings key
	 *
	 * @return bool
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
		loadJavascriptFile(['ext/push.min.js', 'desktop-notify.js'], ['defer' => true]);
		theme()->addInlineJavascript('
			document.addEventListener("DOMContentLoaded", function() {
				Push.config({serviceWorker: "elkServiceWorker.js"}); 
				
				var linkElements = document.head.getElementsByTagName("link"),
					iconHref = null;
			
				// Loop through the link elements to find the shortcut icon
				for (var i = 0; i < linkElements.length; i++) {
					if (linkElements[i].getAttribute("rel") === "icon") {
						iconHref = linkElements[i].getAttribute("href");
						break;
					}
				}
			
				// Grab the site icon to use in the desktop notification widget
				ElkNotifier.add(new ElkDesktop(
					{"icon": iconHref}
				));
			});', true);
	}

	/**
	 * Returns the configurations for the feature.
	 *
	 * @return array
	 */
	public function addConfig()
	{
		global $txt;

		$types = [];
		foreach ($this->_valid_types as $val)
		{
			$types[$val] = $txt['usernotif_favicon_shape_' . $val];
		}

		$positions = [];
		foreach ($this->_valid_positions as $val)
		{
			$positions[$val] = $txt['usernotif_favicon_' . $val];
		}

		return [
			['title', 'usernotif_title'],
			['check', 'usernotif_desktop_enable'],
			['check', 'usernotif_favicon_enable'],
			['select', 'usernotif_favicon_type', $types],
			['select', 'usernotif_favicon_position', $positions],
			['color', 'usernotif_favicon_bgColor'],
			['color', 'usernotif_favicon_textColor'],
		];
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
		$validation_rules = [
			'usernotif_favicon_bgColor' => 'valid_color',
			'usernotif_favicon_textColor' => 'valid_color',
			'usernotif_favicon_type' => 'contains[' . implode(',', $this->_valid_types) . ']',
			'usernotif_favicon_position' => 'contains[' . implode(',', $this->_valid_positions) . ']',
		];

		// Cleanup the inputs! :D
		$validator->validation_rules($validation_rules);
		$validator->validate($post);
		foreach (array_keys($validation_rules) as $key)
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
