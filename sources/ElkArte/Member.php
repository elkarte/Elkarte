<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

/**
 * This class holds all the data belonging to a certain member.
 */
class Member extends \ElkArte\ValuesContainer
{
	/**
	 * The set of data loaded
	 *
	 * @var string
	 */
	protected $set = '';

	/**
	 * @var \BBC\ParserWrapper
	 */
	protected $bbc_parser = null;

	/**
	 * If context has been loaded or not
	 *
	 * @var bool
	 */
	protected $loaded = false;

	/**
	 * Basically the content of $modSettings['displayFields']
	 *
	 * @var mixed[]
	 */
	protected $display_fields = [];

	/**
	 * Constructor
	 *
	 * @param mixed[] $data
	 * @param string $set
	 * @param \BBC\ParserWrapper $bbc_parser
	 */
	public function __construct($data, $set, $bbc_parser)
	{
		parent::__construct($data);
		$this->set = $set;
		$this->bbc_parser = $bbc_parser;
	}

	/**
	 * Adds data to a member
	 *
	 * @param string $type
	 * @param mixed[] $data
	 * @param mixed[] $display_fields Basically the content of $modSettings['displayFields']
	 */
	public function append($type, $data, $display_fields)
	{
		$this->data[$type] = $data;
		$this->display_fields[$type] = $display_fields;
	}

	/**
	 * Returns a certain data
	 *
	 * @param string $item
	 * @return mixed[] Anything set for that index
	 */
	public function get($item)
	{
		if (isset($this->data[$item]))
		{
			return $this->data[$item];
		}
		else
		{
			return null;
		}
	}

	/**
	 * Prepares some data that can be useful in the templates (and not only)
	 *
	 * @param bool $display_custom_fields
	 * @return bool
	 */
	public function loadContext($display_custom_fields = false)
	{
		global $user_info;

		if ($this->loaded === true)
		{
			return true;
		}

		// We can't load guests or members not loaded by loadMemberData()!
		if ($this->data['id_member'] == 0)
		{
			return false;
		}

		$this->prepareBasics();
		$this->loadBasics();
		$this->loadExtended();
		if ($display_custom_fields === true)
		{
			$this->loadOptions();
		}

		call_integration_hook('integrate_member_context', array($this, $display_custom_fields));

		$this->loaded = true;

		return true;
	}

	/**
	 * Loads any additional data (custom fields)
	 */
	protected function loadOptions()
	{
		global $txt, $settings, $scripturl;

		// Are we also loading the members custom fields into context?
		if (empty($this->display_fields))
		{
			return;
		}

		foreach ($this->display_fields as $custom)
		{
			if (!isset($custom['title']) || trim($custom['title']) == '' || empty($this->data['options'][$custom['colname']]))
			{
				continue;
			}

			$value = $this->data['options'][$custom['colname']];

			// BBC?
			if ($custom['bbc'])
			{
				$value = $this->bbc_parser->parseCustomFields($value);
			}
			// ... or checkbox?
			elseif (isset($custom['type']) && $custom['type'] == 'check')
			{
				$value = $value ? $txt['yes'] : $txt['no'];
			}

			// Enclosing the user input within some other text?
			if (!empty($custom['enclose']))
			{
				$replacements = array(
					'{SCRIPTURL}' => $scripturl,
					'{IMAGES_URL}' => $settings['images_url'],
					'{DEFAULT_IMAGES_URL}' => $settings['default_images_url'],
					'{INPUT}' => $value,
				);

				if (in_array($custom['type'], array('radio', 'select')))
				{
					$replacements['{KEY}'] = $this->data['options'][$custom['colname'] . '_key'];
				}
				$value = strtr($custom['enclose'], $replacements);
			}

			$this->data['custom_fields'][] = array(
				'title' => $custom['title'],
				'colname' => $custom['colname'],
				'value' => $value,
				'placement' => !empty($custom['placement']) ? $custom['placement'] : 0,
			);
		}
	}

	/**
	 * Loads the huge array of content for the templates (context)
	 */
	protected function loadExtended()
	{
		global $user_info, $modSettings, $txt, $settings, $context;
		if ($this->set !== \ElkArte\MemberLoader::SET_MINIMAL)
		{
			$buddy_list = !empty($this->data['buddy_list']) ? explode(',', $this->data['buddy_list']) : array();
			$style_color = !empty($this->data['member_group_color']) ? 'style="color:' . $this->data['member_group_color'] . ';"' : '';
			$send_pm_url = getUrl('action', ['action' => 'pm', 'sa' => 'send', 'u' => $this->data['id_member']]);
			$online_status = $this->data['is_online'] ? 'online' : 'offline';

			$this->data = array_merge($this->data, array(
				'username_color' => '<span ' . $style_color . '>' . $this->data['member_name'] . '</span>',
				'name_color' => '<span ' . $style_color . '>' . $this->data['real_name'] . '</span>',
				'link_color' => '<a href="' . $this->data['href'] . '" title="' . $txt['profile_of'] . ' ' . $this->data['real_name'] . '" ' . $style_color . '>' . $this->data['real_name'] . '</a>',
				'is_buddy' => $this->data['buddy'],
				'is_reverse_buddy' => in_array($user_info['id'], $buddy_list),
				'buddies' => $buddy_list,
				'title' => !empty($modSettings['titlesEnable']) ? $this->data['usertitle'] : '',
				'website' => array(
					'title' => $this->data['website_title'],
					'url' => $this->data['website_url'],
				),
				'birth_date' => empty($this->data['birthdate']) || $this->data['birthdate'] === '0001-01-01' ? '0000-00-00' : (substr($this->data['birthdate'], 0, 4) === '0004' ? '0000' . substr($this->data['birthdate'], 4) : $this->data['birthdate']),
				'real_posts' => $this->data['posts'],
				'posts' => comma_format($this->data['posts']),
				'avatar' => determineAvatar($this->data),
				'last_login' => empty($this->data['last_login']) ? $txt['never'] : standardTime($this->data['last_login']),
				'last_login_timestamp' => empty($this->data['last_login']) ? 0 : forum_time(false, $this->data['last_login']),
				'karma' => array(
					'good' => $this->data['karma_good'],
					'bad' => $this->data['karma_bad'],
					'allow' => !$user_info['is_guest'] && !empty($modSettings['karmaMode']) && $user_info['id'] != $this->data['id_member'] && allowedTo('karma_edit') &&
					($user_info['posts'] >= $modSettings['karmaMinPosts'] || $user_info['is_admin']),
				),
				'likes' => array(
					'given' => $this->data['likes_given'],
					'received' => $this->data['likes_received']
				),
				'ip' => htmlspecialchars($this->data['member_ip'], ENT_COMPAT, 'UTF-8'),
				'ip2' => htmlspecialchars($this->data['member_ip2'], ENT_COMPAT, 'UTF-8'),
				'online' => array(
					'is_online' => $this->data['is_online'],
					'text' => \ElkArte\Util::htmlspecialchars($txt[$online_status]),
					'member_online_text' => sprintf($txt['member_is_' . $online_status], \ElkArte\Util::htmlspecialchars($this->data['real_name'])),
					'href' => $send_pm_url,
					'link' => '<a href="' . $send_pm_url . '">' . $txt[$online_status] . '</a>',
					'label' => $txt[$online_status]
				),
				'language' => \ElkArte\Util::ucwords(strtr($this->data['lngfile'], array('_' => ' '))),
				'is_activated' => isset($this->data['is_activated']) ? $this->data['is_activated'] : 1,
				'is_banned' => isset($this->data['is_activated']) ? $this->data['is_activated'] >= 10 : 0,
				'options' => $this->data['options'],
				'is_guest' => false,
				'group' => $this->data['member_group'],
				'group_color' => $this->data['member_group_color'],
				'group_id' => $this->data['id_group'],
				'post_group' => $this->data['post_group'],
				'post_group_color' => $this->data['post_group_color'],
				'group_icons' => str_repeat('<img src="' . str_replace('$language', $context['user']['language'], isset($this->data['icons'][1]) ? $settings['images_url'] . '/group_icons/' . $this->data['icons'][1] : '') . '" alt="[*]" />', empty($this->data['icons'][0]) || empty($this->data['icons'][1]) ? 0 : $this->data['icons'][0]),
				'warning' => $this->data['warning'],
				'warning_status' =>
					!empty($modSettings['warning_mute'])
					&&
					$modSettings['warning_mute'] <= $this->data['warning'] ?
						'mute' :
						(
							!empty($modSettings['warning_moderate'])
							&&
							$modSettings['warning_moderate'] <= $this->data['warning'] ?
							'moderate' :
							(
								!empty($modSettings['warning_watch'])
								&&
								$modSettings['warning_watch'] <= $this->data['warning'] ?
								'watch' :
								''
							)
						),
				'local_time' => standardTime(time() + ($this->data['time_offset'] - $user_info['time_offset']) * 3600, false),
				'custom_fields' => array(),
			));
		}
	}

	/**
	 * These minimal values are always loaded
	 */
	protected function loadBasics()
	{
		global $txt;

		$this->data['username'] = $this->data['member_name'];
		$this->data['name'] = $this->data['real_name'];
		$this->data['id'] = $this->data['id_member'];

		$this->data['href'] = getUrl('profile', ['action' => 'profile', 'u' => $this->data['id_member'], 'name' => $this->data['real_name']]);
		$this->data['link'] = '<a href="' . $this->data['href'] . '" title="' . $txt['profile_of'] . ' ' . trim($this->data['real_name']) . '">' . $this->data['real_name'] . '</a>';
		$this->data['email'] = $this->data['email_address'];
		$this->data['show_email'] = showEmailAddress(!empty($this->data['hide_email']), $this->data['id_member']);
		if (empty($this->data['date_registered']))
		{
			$this->data['registered_raw'] = 0;
			$this->data['registered'] = $txt['not_applicable'];
			$this->data['registered_timestamp'] = 0;
		}
		else
		{
			$this->data['registered_raw'] = $this->data['date_registered'];
			$this->data['registered'] = standardTime($this->data['date_registered']);
			$this->data['registered_timestamp'] = forum_time(true, $this->data['date_registered']);
		}
	}

	/**
	 * Prepares signature, icons, and few basic stuff
	 */
	protected function prepareBasics()
	{
		global $user_info;

		$this->data['signature'] = censor($this->data['signature']);

		// TODO: We should look into a censoring toggle for custom fields

		// Set things up to be used before hand.
		$this->data['signature'] = str_replace(array("\n", "\r"), array('<br />', ''), $this->data['signature']);
		$this->data['signature'] = $this->bbc_parser->parseSignature($this->data['signature'], true);

		$this->data['is_online'] = (!empty($this->data['show_online']) || allowedTo('moderate_forum')) && $this->data['is_online'] > 0;
		$this->data['icons'] = empty($this->data['icons']) ? array('', '') : explode('#', $this->data['icons']);

		// Setup the buddy status here (One whole in_array call saved :P)
		$this->data['buddy'] = in_array($this->data['id_member'], $user_info['buddies']);
	}

	/**
	 * Stores the data of the user into an array
	 *
	 * @return mixed[]
	 */
	public function toArray()
	{
		return [
			'set' => $this->set,
			'data' => $this->data,
		];
	}
}
