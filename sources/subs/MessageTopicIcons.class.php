<?php

/**
 * General class for setting the message icon array and returning index values
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 Release Candidate 1
 *
 */

/**
 * Class MessageTopicIcons
 */
class MessageTopicIcons extends ElkArte\ValuesContainer
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
	 * This simple function returns the message topic icon array.
	 *
	 * @param bool|false $icon_check
	 * @param string $theme_dir
	 * @param string $default
	 */
	public function __construct($icon_check = false, $theme_dir = '', $default = 'xx')
	{
		parent::__construct();

		// Load passed parameters to the class properties
		$this->_check = $icon_check;
		$this->_theme_dir = $theme_dir;
		$this->_default_icon = $default;

		$this->_loadIcons();
	}

	/**
	 * Return the icon specified by idx, or the default icon for invalid names
	 *
	 * @param int|string $idx
	 */
	public function __get($idx)
	{
		if (isset($this->data[$idx]))
		{
			return $this->data[$idx];
		}
		else
		{
			return $this->data[$this->_default_icon];
		}
	}

	/**
	 * This simple function returns the message topic icon array.
	 */
	protected function _loadIcons()
	{
		global $settings;

		// Setup the default topic icons...
		$stable_icons = array(
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

		// Allow addons to add to the message icon array
		call_integration_hook('integrate_messageindex_icons', array(&$stable_icons));

		$this->data = array();
		foreach ($stable_icons as $icon)
		{
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
}