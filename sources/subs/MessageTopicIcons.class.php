<?php

/**
 * DB and general functions for working with the message index
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 dev
 *
 */

if (!defined('ELK'))
	die('No access...');

class MessageTopicIcons extends ElkArte\ValuesContainer
{
	const IMAGE_URL = 'images_url';
	const DEFAULT_URL = 'default_images_url';

	protected $_check = false;
	protected $_theme_dir = '';
	protected $_default_icon = 'xx';
	/**
	 * This simple function returns the message topic icon array.
	 */
	public function __construct($icon_check = false, $theme_dir = '', $default = 'xx')
	{
		$this->_check = $icon_check;
		$this->_theme_dir = $theme_dir;
		$this->_default_icon = $default;

		$this->_loadIcons();
	}

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
				$this->data[$icon] = $settings[file_exists($this->_theme_dir . '/images/post/' . $icon . '.png') ? MessageTopicIcons::IMAGE_URL : MessageTopicIcons::DEFAULT_URL] . '/post/' . $icon . '.png';
			}
			else
			{
				$this->data[$icon] = $settings[MessageTopicIcons::IMAGE_URL] . '/post/' . $icon . '.png';
			}
		}
	}
}