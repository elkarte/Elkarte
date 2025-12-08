<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

use BBC\ParserWrapper;
use ElkArte\Helper\Util;
use ElkArte\Helper\ValuesContainer;

/**
 * This class holds all the data belonging to a certain member.
 */
class Member extends ValuesContainer
{
	/** @var bool If context has been loaded or not */
	protected $loaded = false;

	/** @var array Basically the content of $modSettings['displayFields'] */
	protected $display_fields = [];

	/**
	 * Constructor
	 *
	 * @param array $data
	 * @param string $set The set of data loaded
	 * @param ParserWrapper $bbc_parser
	 *
	 */
	public function __construct($data, protected $set, protected $bbc_parser)
	{
		parent::__construct($data);
	}

	/**
	 * Adds data to a member
	 *
	 * @param string $type
	 * @param array $data
	 * @param array $display_fields Basically the content of $modSettings['displayFields']
	 */
	public function append($type, $data, $display_fields): void
	{
		$this->data[$type] = $this->data[$type] ?? [];
		$this->data[$type] = array_merge($this->data[$type], $data);

		$this->display_fields[$type] = $this->display_fields[$type] ?? [];
		$this->display_fields[$type] = array_merge($this->display_fields[$type], $display_fields);
	}

	/**
	 * Returns a certain data
	 *
	 * @param string $item
	 * @return array|null Anything set for that index
	 */
	public function get($item): ?array
	{
		return $this->data[$item] ?? null;
	}

	/**
	 * Prepares / Cleans / Organizes user data so that it can be used in the templates (and not only)
	 *
	 * Similar in concept to pre 2.0 loadMemberContext()
	 * $member = MembersList::get($mem);
	 * $member->loadContext(true|false);
	 * Data will be available as $member['name'] for example.
	 *
	 * @param bool $display_custom_fields
	 * @return bool
	 */
	public function loadContext($display_custom_fields = true): bool
	{
		if ($this->loaded)
		{
			return true;
		}

		// We can't load guests or members not loaded by loadMemberData()!
		if (empty($this->data['id_member']))
		{
			return false;
		}

		$this->prepareBasics();
		$this->loadBasics();
		$this->loadExtended();
		if ($display_custom_fields)
		{
			$this->loadOptions();
		}

		call_integration_hook('integrate_member_context', [$this, $display_custom_fields]);

		$this->loaded = true;

		return true;
	}

	/**
	 * Prepares signature, icons, and little basic stuff so it's presentable.
	 */
	protected function prepareBasics(): void
	{
		$this->data['signature'] = censor($this->data['signature']);

		// TODO: We should look into a censoring toggle for custom fields

		// Set things up to be used beforehand.
		$this->data['signature'] = str_replace(["\n", "\r"], ['<br />', ''], $this->data['signature']);
		$this->data['signature_raw'] = $this->data['signature'];
		$this->data['signature'] = $this->bbc_parser->parseSignature($this->data['signature'], true);

		$this->data['is_online'] = (!empty($this->data['show_online']) || allowedTo('moderate_forum')) && $this->data['is_online'] > 0;
		$this->data['icons'] = empty($this->data['icons']) ? ['', ''] : explode('#', $this->data['icons']);

		// Set up the buddy status here (One whole in_array call saved :P)
		$this->data['buddy'] = in_array($this->data['id_member'], User::$info->buddies, true);
	}

	/**
	 * These values are always loaded
	 */
	protected function loadBasics(): void
	{
		global $txt;

		$this->data['username'] = $this->data['member_name'];
		$this->data['name'] = $this->data['real_name'];
		$this->data['id'] = $this->data['id_member'];
		$this->data['href'] = getUrl('profile', ['action' => 'profile', 'u' => $this->data['id_member'], 'name' => $this->data['real_name']]);
		$this->data['link'] = '<a href="' . $this->data['href'] . '" title="' . $txt['profile_of'] . ' ' . trim($this->data['real_name']) . '">' . $this->data['real_name'] . '</a>';
		$this->data['email'] = $this->data['email_address'];
		$this->data['show_email'] = showEmailAddress($this->data['id_member']);
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
	 * Loads the huge array of content for the templates (context)
	 */
	protected function loadExtended(): void
	{
		global $modSettings, $txt, $settings, $context;

		if ($this->set !== MemberLoader::SET_MINIMAL)
		{
			$buddy_list = empty($this->data['buddy_list']) ? [] : explode(',', $this->data['buddy_list']);
			$style_color = empty($this->data['member_group_color']) ? '' : 'style="color:' . $this->data['member_group_color'] . ';"';
			$send_pm_url = getUrl('action', ['action' => 'pm', 'sa' => 'send', 'u' => $this->data['id_member']]);
			$online_status = $this->data['is_online'] ? 'online' : 'offline';

			$this->data = array_merge($this->data, [
				'username_color' => '<span ' . $style_color . '>' . $this->data['member_name'] . '</span>',
				'name_color' => '<span ' . $style_color . '>' . $this->data['real_name'] . '</span>',
				'link_color' => '<a href="' . $this->data['href'] . '" title="' . $txt['profile_of'] . ' ' . $this->data['real_name'] . '" ' . $style_color . '>' . $this->data['real_name'] . '</a>',
				'is_buddy' => $this->data['buddy'],
				'is_reverse_buddy' => in_array(User::$info->id, $buddy_list),
				'buddies' => $buddy_list,
				'title' => empty($modSettings['titlesEnable']) ? '' : $this->data['usertitle'],
				'website' => [
					'title' => $this->data['website_title'],
					'url' => $this->data['website_url'],
				],
				'birth_date' => empty($this->data['birthdate']) || $this->data['birthdate'] === '0001-01-01'
					? '0000-00-00'
					: (str_starts_with($this->data['birthdate'], '0004')
						? '0000' . substr($this->data['birthdate'], 4)
						: $this->data['birthdate']),
				'real_posts' => $this->data['posts'],
				'posts' => $this->data['posts'],
				'avatar' => determineAvatar($this->data),
				'last_login' => empty($this->data['last_login']) ? $txt['never'] : standardTime($this->data['last_login']),
				'last_login_timestamp' => empty($this->data['last_login']) ? 0 : forum_time(false, $this->data['last_login']),
				'karma' => [
					'good' => $this->data['karma_good'],
					'bad' => $this->data['karma_bad'],
					'allow' => User::$info->is_guest === false && !empty($modSettings['karmaMode']) && User::$info->id != $this->data['id_member'] && allowedTo('karma_edit') &&
						(User::$info->posts >= $modSettings['karmaMinPosts'] || User::$info->is_admin),
				],
				'likes' => [
					'given' => $this->data['likes_given'],
					'received' => $this->data['likes_received']
				],
				'ip' => htmlspecialchars($this->data['member_ip'], ENT_COMPAT, 'UTF-8'),
				'ip2' => htmlspecialchars($this->data['member_ip2'], ENT_COMPAT, 'UTF-8'),
				'online' => [
					'is_online' => $this->data['is_online'],
					'text' => Util::htmlspecialchars($txt[$online_status]),
					'member_online_text' => sprintf($txt['member_is_' . $online_status], Util::htmlspecialchars($this->data['real_name'])),
					'href' => $send_pm_url,
					'link' => '<a href="' . $send_pm_url . '">' . $txt[$online_status] . '</a>',
					'label' => $txt[$online_status]
				],
				'language' => Util::ucwords(strtr(basename($this->data['lngfile'], '.php'), ['_' => ' '])),
				'is_activated' => $this->data['is_activated'] ?? 1,
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
				'warning_status' => !empty($modSettings['warning_mute']) &&	$modSettings['warning_mute'] <= $this->data['warning']
					? 'mute'
					: (!empty($modSettings['warning_moderate']) && $modSettings['warning_moderate'] <= $this->data['warning']
						? 'moderate'
						: (!empty($modSettings['warning_watch']) && $modSettings['warning_watch'] <= $this->data['warning']
							? 'watch'
							: ''
					)
				),
				'local_time' => standardTime(time() + ($this->data['time_offset'] - User::$info->time_offset) * 3600, false),
				'custom_fields' => [],
			]);
		}
	}

	/**
	 * Loads any additional data (custom fields)
	 */
	protected function loadOptions(): void
	{
		global $txt, $settings, $scripturl;

		// Are we also loading the members' custom fields into context?
		if (empty($this->display_fields['options']))
		{
			return;
		}

		foreach ($this->display_fields['options'] as $custom)
		{
			if (empty($this->data['options'][$custom['colname']]) || !isset($custom['title']) || trim($custom['title']) === '')
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
			elseif (isset($custom['type']) && $custom['type'] === 'check')
			{
				$value = $value ? $txt['yes'] : $txt['no'];
			}

			// Enclosing the user input within some other text?
			if (!empty($custom['enclose']))
			{
				$replacements = [
					'{SCRIPTURL}' => $scripturl,
					'{IMAGES_URL}' => $settings['images_url'],
					'{DEFAULT_IMAGES_URL}' => $settings['default_images_url'],
					'{INPUT}' => $value,
				];

				if (in_array($custom['type'], ['radio', 'select']))
				{
					$replacements['{KEY}'] = $this->data['options'][$custom['colname'] . '_key'];
				}

				$value = strtr($custom['enclose'], $replacements);
			}

			$this->data['custom_fields'][] = [
				'title' => $custom['title'],
				'colname' => $custom['colname'],
				'value' => $value,
				'placement' => empty($custom['placement']) ? 0 : $custom['placement'],
			];
		}
	}

	/**
	 * Stores the data of the user into an array
	 *
	 * @return array
	 */
	public function toArray()
	{
		return [
			'set' => $this->set,
			'data' => $this->data,
		];
	}
}
