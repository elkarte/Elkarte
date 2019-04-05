<?php

/**
 * General class for setting the message icon array and returning index values
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

/**
 * Class MessageTopicIcons
 */
class MessageTopicIcons extends ValuesContainer
{
	const IMAGE_URL = 'images_url';
	const DEFAULT_URL = 'default_images_url';

	/**
	 * Whether to check if the icon exists in the expected location
	 * @var bool
	 */
	protected $_check = false;

	/**
	 * Theme directory path
	 * @var string
	 */
	protected $_theme_dir = '';

	/**
	 * Default icon code
	 * @var string
	 */
	protected $_default_icon = 'xx';

	/**
	 * Icons that are default with ElkArte
	 * @var array
	 */
	protected $_stable_icons = 	array();

	/**
	 * Icons to load in addition to the default
	 * @var array
	 */
	protected $_custom_icons = array();

	/**
	 * This simple function returns the message topic icon array.
	 *
	 * @param bool|false $icon_check
	 * @param string $theme_dir
	 * @param array topic icons to load in addition to default
	 * @param string $default
	 */
	public function __construct($icon_check = false, $theme_dir = '', $custom = array(), $default = 'xx')
	{
		parent::__construct();

		// Load passed parameters to the class properties
		$this->_check = $icon_check;
		$this->_theme_dir = $theme_dir;
		$this->_default_icon = $default;
		$this->_custom_icons = $custom;

		// Set default icons
		$this->_loadStableIcons();

		// Merge in additional ones
		$custom_icons = array_map(function($element) {
			return $element['first_icon'];
		}, $custom);
		$this->_stable_icons = array_merge($this->_stable_icons, $custom_icons);

		$this->_loadIcons();
	}

	/**
	 * Return the icon specified by idx, or the default icon for invalid names
	 *
	 * @param int|string $idx
	 *
	 * @return string
	 */
	public function __get($idx)
	{
		// Not a standard topic icon
		if (!isset($this->data[$idx]))
		{
			$this->_setUrl($idx);
		}

		return $this->data[$idx];
	}

	/**
	 * Load the stable icon array
	 */
	protected function _loadStableIcons()
	{
		// Setup the default topic icons...
		$this->_stable_icons = array(
			'xx',
			'thumbup',
			'thumbdown',
			'exclamation',
			'question',
			'lamp',
			'smiley',
			'angry',
			'cheesy',
			'grin',
			'sad',
			'wink',
			'poll',
			'moved',
			'recycled',
			'wireless',
			'clip'
		);
	}

	/**
	 * This simple function returns the message topic icon array.
	 */
	protected function _loadIcons()
	{
		// Allow addons to add to the message icon array
		call_integration_hook('integrate_messageindex_icons', array(&$this->_stable_icons));

		$this->data = array();
		foreach ($this->_stable_icons as $icon)
		{
			$this->_setUrl($icon);
		}
	}

	/**
	 * Set the icon URL location
	 *
	 * @param string $icon
	 */
	protected function _setUrl($icon)
	{
		global $settings;

		if ($this->_check)
		{
			$this->data[$icon] = $settings[file_exists($this->_theme_dir . '/images/post/' . $icon . '.png')
				 ? self::IMAGE_URL
				 : self::DEFAULT_URL] . '/post/' . $icon . '.png';
		}
		else
		{
			$this->data[$icon] = $settings[self::IMAGE_URL] . '/post/' . $icon . '.png';
		}
	}
}
