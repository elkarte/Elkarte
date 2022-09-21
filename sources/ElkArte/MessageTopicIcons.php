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
	public const IMAGE_URL = 'images_url';
	public const DEFAULT_URL = 'default_images_url';

	/** @var bool Whether to check if the icon exists in the expected location */
	protected $_check = false;

	/** @var string Theme directory path */
	protected $_theme_dir = '';

	/** @var string Default icon code */
	protected $_default_icon = 'xx';

	/** @var array Icons passed to the class, merged with site defined */
	protected $_custom_icons = [];

	/** @var array Icons to load in addition to the default */
	protected $_icons = [];

	/**
	 * This simple function returns the message topic icon array.
	 *
	 * @param bool|false $icon_check
	 * @param string $theme_dir
	 * @param array topic icons to load in addition to default
	 * @param string $default
	 */
	public function __construct($icon_check = false, $theme_dir = '', $custom = [], $default = 'xx')
	{
		parent::__construct();

		// Load passed parameters to the class properties
		$this->_check = $icon_check;
		$this->_theme_dir = $theme_dir;
		$this->_default_icon = $default;
		$this->_custom_icons = $custom;

		// Merge in additional ones
		$this->_loadSiteIcons();
		$this->_merge_all_icons();
		$this->_loadIcons();
	}

	/**
	 * Load in  site icons, default or custom message icons.
	 */
	private function _loadSiteIcons()
	{
		global $board;

		require_once(SUBSDIR . '/MessageIcons.subs.php');
		$this->_icons = getMessageIcons(empty($board) ? 0 : $board);
	}

	/**
	 * This function merges in any passed custom icons with our site defined ones.
	 */
	private function _merge_all_icons()
	{
		// Merge in additional ones
		$custom_icons = array_map(static function ($element) {
			return $element['value'];
		}, $this->_custom_icons);

		$this->_icons = array_merge($this->_icons, $custom_icons);
	}

	/**
	 * Return the icon specified by key, or the default icon for invalid names
	 *
	 * @param int|string $key
	 *
	 * @return string
	 */
	public function __get($key)
	{
		// If not a known icon, set a default
		if (!isset($this->data[$key]))
		{
			$this->_setUrl($key);
		}

		return $this->data[$key];
	}

	/**
	 * Return the icon URL specified by idx
	 *
	 * @param int|string $key
	 * @return string
	 */
	public function getIconURL($key)
	{
		$this->_checkValue($key);

		return $this->data[$key]['url'];
	}

	/**
	 * Return the name of the icon specified by key
	 *
	 * @param int|string $key
	 * @return string
	 */
	public function getIconName($key)
	{
		$this->_checkValue($key);

		return htmlspecialchars($this->data[$key]['name']);
	}

	/**
	 * Return the Value of the icon specified by key
	 *
	 * @param int|string $key
	 * @return string
	 */
	public function getIconValue($key)
	{
		$this->_checkValue($key);

		return $this->data[$key]['value'];
	}

	/**
	 * If the icon does not exist, set a default
	 *
	 * @param $key
	 */
	private function _checkValue($key)
	{
		// Not a known topic icon, set the xx default
		if (!isset($this->data[$key]))
		{
			$this->data[$key]['url'] = $this->data[$this->_default_icon]['url'];
			$this->data[$key]['value'] = $this->_default_icon;
		}
	}

	/**
	 * This simple function sets the message topic icon array.
	 */
	protected function _loadIcons()
	{
		// Allow addons to add to the message icon array
		call_integration_hook('integrate_messageindex_icons', [&$this->_icons]);

		$this->data = $this->_icons;
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
	}
}
