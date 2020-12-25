<?php

/**
 * General class for setting the message icon array and returning index values
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1.7
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
		$this->_loadCustomIcons();
		$this->_merge_all_icons();
		$this->_loadIcons();
	}

	/**
	 * Use a passed custom array or fetch the ones available for the board in use
	 */
	private function _loadCustomIcons()
	{
		global $board;

		// Are custom even an option?
		if ($this->_allowCustomIcons() && empty($this->_custom_icons))
		{
			// Fetch any additional ones
			require_once(SUBSDIR . '/MessageIcons.subs.php');
			$this->_custom_icons = getMessageIcons(empty($board) ? 0 : $board);
		}
	}

	/**
	 * This function merges in any custom icons with our standard ones.
	 *
	 * @return array
	 */
	private function _merge_all_icons()
	{
		// Are custom even an option?
		if ($this->_allowCustomIcons())
		{
			// Merge in additional ones
			$custom_icons = array_map(function ($element) {
				return $element['name'];
			}, $this->_custom_icons);

			$this->_stable_icons = array_merge($this->_stable_icons, $custom_icons);
		}
	}

	/**
	 * Simply checks the ACP status of custom icons
	 *
	 * @return bool
	 */
	private function _allowCustomIcons()
	{
		global $modSettings;

		return !empty($modSettings['messageIcons_enable']);
	}

	/**
	 * Return the icon specified by idx
	 *
	 * @param int|string $idx
	 * @return string
	 */
	public function __get($idx)
	{
		// Not a standard topic icon
		if (!isset($this->data[$idx]))
		{
			$this->_setUrl($idx);
		}

		return $this->data[$idx]['url'];
	}

	/**
	 * Return the icon URL specified by idx
	 *
	 * @param int|string $idx
	 * @return string
	 */
	public function getIconURL($idx)
	{
		$this->_checkValue($idx);

		return $this->data[$idx]['url'];
	}

	/**
	 * Return the name of the icon specified by idx
	 *
	 * @param int|string $idx
	 * @return string
	 */
	public function getIconName($idx)
	{
		$this->_checkValue($idx);

		return $this->data[$idx]['name'];
	}

	/**
	 * If the icon does not exist, sets a default
	 *
	 * @param $idx
	 */
	private function _checkValue($idx)
	{
		// Not a standard topic icon
		if (!isset($this->data[$idx]))
		{
			$this->data[$idx]['url'] = $this->data[$this->_default_icon]['url'];
			$this->data[$idx]['name'] = $this->_default_icon;
		}
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
			$this->data[$icon]['url'] = $settings[file_exists($this->_theme_dir . '/images/post/' . $icon . '.png')
				 ? self::IMAGE_URL
				 : self::DEFAULT_URL] . '/post/' . $icon . '.png';
		}
		else
		{
			$this->data[$icon]['url'] = $settings[self::IMAGE_URL] . '/post/' . $icon . '.png';
		}

		$this->data[$icon]['name'] = $icon;
	}
}