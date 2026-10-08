<?php

/**
 * The menu context class
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\Menu;

use ElkArte\Cache\Cache;
use ElkArte\Helper\HttpReq;
use ElkArte\Helper\ValuesContainer;
use ElkArte\User;

/**
 * Class MenuContext
 *
 * The MenuContext class is responsible for setting up the context for the menu on each page load.
 */
class MenuContext
{
	/** @var ValuesContainer details of the user to which we are building the menu */
	private $user;

	/** @var Cache|object The cache variable. */
	private $cache;

	/** @var int cache age */
	private $cacheTime;

	/** @var bool if the action needs to call a hook to determine the real action */
	private $needs_action_hook;

	/**
	 * Constructor method to initialize class properties and dependencies.
	 *
	 * @return void
	 */
	public function __construct()
	{
		global $modSettings;

		$this->user = User::$info;
		$this->cache = Cache::instance();
		$this->cacheTime = $modSettings['lastActive'] * 60;
	}

	/**
	 * Sets up the top menu buttons
	 *
	 * What it does:
	 *
	 * - Defines every master item in the menu, as well as any sub-items.
	 * - Sets the counter for the menu items.
	 * - Ensures the chosen action is set so the menu is highlighted.
	 * - Saves them in the cache if it is available and on.
	 * - Places the results in $context.
	 */
	public function setupMenuContext()
	{
		global $context;

		$this->setupUserPermissions();

		call_integration_hook('integrate_setup_allow');

		$this->setupHeaderCallbacks();

		// Update the Moderation menu items with action item totals
		if ($context['allow_moderation_center'])
		{
			// Get the numbers for the menu ...
			require_once(SUBSDIR . '/Moderation.subs.php');
			$menu_count = loadModeratorMenuCounts();
		}

		$menu_count['unread_messages'] = $context['user']['unread_messages'];
		$menu_count['mentions'] = $context['user']['mentions'];

		// All the buttons we can possibly want and then some, try pulling the final list of buttons from the cache first.
		$this->setupMenuButtons($menu_count);

		$this->setupCurrentAction();

		// Not all actions are simple.
		if (!empty($this->needs_action_hook))
		{
			call_integration_hook('integrate_current_action', [&$current_action]);
		}
	}

	/**
	 * Sets up some core menu item permissions based on the user
	 */
	private function setupUserPermissions()
	{
		global $context, $modSettings;

		$context['allow_search'] = empty($modSettings['allow_guestAccess']) ? $this->user->is_guest === false && allowedTo('search_posts') : (allowedTo('search_posts'));
		$context['allow_admin'] = allowedTo(['admin_forum', 'manage_boards', 'manage_permissions', 'moderate_forum', 'manage_membergroups', 'manage_bans', 'send_mail', 'edit_news', 'manage_attachments', 'manage_smileys']);
		$context['allow_edit_profile'] = $this->user->is_guest === false && allowedTo(['profile_view_own', 'profile_view_any', 'profile_identity_own', 'profile_identity_any', 'profile_extra_own', 'profile_extra_any', 'profile_remove_own', 'profile_remove_any', 'moderate_forum', 'manage_membergroups', 'profile_title_own', 'profile_title_any']);
		$context['allow_memberlist'] = allowedTo('view_mlist');
		$context['allow_calendar'] = allowedTo('calendar_view') && !empty($modSettings['cal_enabled']);
		$context['allow_moderation_center'] = $context['user']['can_mod'];
		$context['allow_pm'] = allowedTo('pm_read');
	}

	/**
	 * Sets up the header callbacks.
	 *
	 * @return void
	 */
	private function setupHeaderCallbacks()
	{
		global $context;

		if ($context['allow_search'])
		{
			$context['theme_header_callbacks'] = elk_array_insert($context['theme_header_callbacks'], 'login_bar', ['search_bar'], 'after');
		}

		// Add in the top section notice callback
		$context['theme_header_callbacks'][] = 'header_bar';
	}

	/**
	 * Set up the menu buttons.
	 *
	 * @param array $menu_count The count of menus.
	 *
	 * @return void
	 */
	private function setUpMenuButtons($menu_count)
	{
		global $context, $modSettings;

		// Check the cache
		if ((time() - $this->cacheTime <= $modSettings['settings_updated'])
			|| ($menu_buttons = $this->cache->get('menu_buttons-' . implode('_', $this->user->groups) . '-' . $this->user->language, $this->cacheTime)) === null)
		{
			// Start things up: this is what we know by default
			require_once(SUBSDIR . '/Menu.subs.php');
			$buttons = loadDefaultMenuButtons();

			// Allow editing menu buttons easily.
			call_integration_hook('integrate_menu_buttons', [&$buttons, &$menu_count]);

			$menu_buttons = $this->initializeButtonProperties($buttons);

			if ($this->cache->levelHigherThan(1))
			{
				$this->cache->put('menu_buttons-' . implode('_', $this->user->groups) . '-' . $this->user->language, $menu_buttons, $this->cacheTime);
			}
		}

		// Now we apply the unique counters to the cached buttons.
		$menu_buttons = $this->applyDynamicButtonProperties($menu_buttons, $menu_count);

		// Now we put the buttons in the context so the theme can use them.
		$context['menu_buttons'] = $menu_buttons;
	}

	/**
	 * Initializes the static properties and structure of the buttons for caching.
	 *
	 * @param array $buttons The array of buttons.
	 *
	 * @return array The array of buttons with initialized properties.
	 */
	private function initializeButtonProperties($buttons)
	{
		$menu_buttons = [];
		foreach ($buttons as $act => $button)
		{
			if (!empty($button['show']))
			{
				$button = $this->setButtonProperties($button);
				$menu_buttons[$act] = $button;
			}
		}

		return $menu_buttons;
	}

	/**
	 * Set the static properties of a button and clean inactive sub-buttons.
	 *
	 * @param array $button The button that needs to be updated.
	 * @return array The updated button.
	 */
	private function setButtonProperties($button)
	{
		$button['active_button'] = false;

		$button = $this->setButtonActionHook($button);

		return $this->cleanSubButtons($button);
	}

	/**
	 * Cleans inactive sub buttons from a given button structure.
	 *
	 * @param array $button The button containing sub buttons.
	 * @return array The cleaned button.
	 */
	private function cleanSubButtons($button)
	{
		if (isset($button['sub_buttons']))
		{
			foreach ($button['sub_buttons'] as $key => $subButton)
			{
				if (empty($subButton['show']))
				{
					unset($button['sub_buttons'][$key]);
					continue;
				}

				if (!empty($subButton['sub_buttons']))
				{
					foreach ($subButton['sub_buttons'] as $key2 => $subButton2)
					{
						if (empty($subButton2['show']))
						{
							unset($button['sub_buttons'][$key]['sub_buttons'][$key2]);
						}
					}
				}
			}
		}

		return $button;
	}

	/**
	 * Applies dynamic properties (per-user counters, badges, links, and tokens) to the buttons.
	 *
	 * @param array $menu_buttons The cached or initialized menu buttons.
	 * @param array $menu_count The menu count data.
	 * @return array The updated menu buttons.
	 */
	private function applyDynamicButtonProperties($menu_buttons, $menu_count)
	{
		global $context, $modSettings;

		foreach ($menu_buttons as &$button)
		{
			$this->setButtonCounter($button, $menu_count);

			if (!empty($button['sub_buttons']))
			{
				$this->setSubButtonCounter($button['sub_buttons'], $menu_count);
			}
		}
		unset($button);

		if (isset($menu_buttons['pm']))
		{
			$menu_buttons['pm']['data-icon'] = !empty($menu_count['unread_messages']) ? 'i-menu-pm-on' : 'i-menu-pm-off';
		}

		if (isset($menu_buttons['mentions']))
		{
			$menu_buttons['mentions']['data-icon'] = !empty($menu_count['mentions']) ? 'i-menu-mentions-on' : 'i-menu-mentions-off';
		}

		if (!empty($menu_buttons['profile']))
		{
			if (!empty($modSettings['displayMemberNames']))
			{
				$menu_buttons['profile']['title'] = $this->user->name;
			}

			// Set the profile button's links to the user's profile
			$menu_buttons['profile']['href'] = getUrl('profile', ['action' => 'profile', 'u' => $this->user->id, 'name' => $this->user->name]);
			if (!empty($menu_buttons['profile']['sub_buttons']['account']))
			{
				$menu_buttons['profile']['sub_buttons']['account']['href'] = getUrl('profile', ['action' => 'profile', 'area' => 'account', 'u' => $this->user->id, 'name' => $this->user->name]);
			}

			if (!empty($menu_buttons['profile']['sub_buttons']['drafts']))
			{
				$menu_buttons['profile']['sub_buttons']['drafts']['href'] = getUrl('profile', ['action' => 'profile', 'area' => 'showdrafts', 'u' => $this->user->id, 'name' => $this->user->name]);
			}

			if (!empty($menu_buttons['profile']['sub_buttons']['forumprofile']))
			{
				$menu_buttons['profile']['sub_buttons']['forumprofile']['href'] = getUrl('profile', ['action' => 'profile', 'area' => 'forumprofile', 'u' => $this->user->id, 'name' => $this->user->name]);
			}

			if (!empty($menu_buttons['profile']['sub_buttons']['theme']))
			{
				$menu_buttons['profile']['sub_buttons']['theme']['href'] = getUrl('profile', ['action' => 'profile', 'area' => 'theme', 'u' => $this->user->id, 'name' => $this->user->name]);
			}

			if (!empty($menu_buttons['profile']['sub_buttons']['logout']))
			{
				$menu_buttons['profile']['sub_buttons']['logout']['href'] .= ';' . $context['session_var'] . '=' . $context['session_id'];
			}
		}

		return $menu_buttons;
	}

	/**
	 * Sets the action hook flag for the button.
	 *
	 * @param array $button The button that needs to be checked.
	 * @return array The updated button.
	 */
	private function setButtonActionHook($button)
	{
		if (isset($button['action_hook']))
		{
			$this->needs_action_hook = true;
		}

		return $button;
	}

	/**
	 * Set the counter and indicator of the button based on the menu count.
	 *
	 * @param array $button The button that needs to be updated.
	 * @param array $menu_count The menu count data.
	 * @return void
	 */
	private function setButtonCounter(&$button, $menu_count)
	{
		if (isset($button['counter']) && !empty($menu_count[$button['counter']]))
		{
			$button['alttitle'] = $button['title'] . ' [' . $menu_count[$button['counter']] . ']';
			$this->addCountsToTitle($button['title'], $menu_count[$button['counter']], 0);
			$button['indicator'] = true;
		}
		elseif (isset($button['counter'], $menu_count[$button['counter']]) && $menu_count[$button['counter']] === 0)
		{
			$button['alttitle'] = $button['title'];
			// If the counter is set but is zero, add a hidden indicator to simplify ajax update the counter
			$this->addCountsToTitle($button['title'], $menu_count[$button['counter']], -1);
		}
	}

	/**
	 * Sets the counter for sub buttons of a given button structure.
	 *
	 * @param array $sub_buttons Array of sub buttons.
	 * @param array $menu_count The count of items for each sub button.
	 * @param int $level Nesting level for formatting.
	 * @return void
	 */
	private function setSubButtonCounter(&$sub_buttons, $menu_count, $level = 1)
	{
		foreach ($sub_buttons as &$subButton)
		{
			if (isset($subButton['counter']) && !empty($menu_count[$subButton['counter']]))
			{
				$subButton['alttitle'] = $subButton['title'] . ' [' . $menu_count[$subButton['counter']] . ']';
				$this->addCountsToTitle($subButton['title'], $menu_count[$subButton['counter']], min($level, 2));
			}

			if (!empty($subButton['sub_buttons']))
			{
				$this->setSubButtonCounter($subButton['sub_buttons'], $menu_count, $level + 1);
			}
		}
		unset($subButton);
	}

	/**
	 * Adds counts to the title.
	 *
	 * @param string $title The title to add counts to.
	 * @param int $counts The array of counts.
	 * @param int $notice The menu_numeric_notice index to use for formatting.
	 *
	 * @return void Does not return anything.
	 */
	private function addCountsToTitle(&$title, $counts, $notice)
	{
		global $settings;

		if (!empty($settings['menu_numeric_notice'][$notice]))
		{
			$title .= sprintf($settings['menu_numeric_notice'][$notice], $counts);
		}
	}

	/**
	 * Sets up the current action.
	 *
	 * @return void
	 * @global array $context The global context array.
	 *
	 */
	private function setupCurrentAction()
	{
		global $context;

		if (isset($context['menu_buttons'][$context['current_action']]))
		{
			$current_action = $context['current_action'];
		}
		elseif ($context['current_action'] === 'profile')
		{
			$current_action = 'pm';
		}
		elseif ($context['current_action'] === 'themes')
		{

			$sa = HttpReq::instance()->getRequest('sa', 'trim', '');
			$current_action = $sa === 'pick' ? 'profile' : 'admin';
		}
		else
		{
			$current_action = 'home';
		}

		// Set the current action
		$context['current_action'] = $current_action;

		// Not all actions are simple.
		if (!empty($this->needs_action_hook))
		{
			call_integration_hook('integrate_current_action', [&$current_action]);
		}

		if (isset($context['menu_buttons'][$current_action]))
		{
			$context['menu_buttons'][$current_action]['active_button'] = true;
		}
	}
}
