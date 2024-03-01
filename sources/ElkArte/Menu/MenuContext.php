<?php

/**
 * The menu context class
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
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

	public function __construct()
	{
		global $modSettings;

		$this->user = User::$info;
		$this->cache = Cache::instance();
		$this->cacheTime = $modSettings['lastActive'] * 60;
	}

	/**
	 * Sets up all the top menu buttons
	 *
	 * What it does:
	 *
	 * - Defines every master item in the menu, as well as any sub-items
	 * - Sets the counter for the menu items
	 * - Ensures the chosen action is set so the menu is highlighted
	 * - Saves them in the cache if it is available and on
	 * - Places the results in $context
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

		// All the buttons we can possibly want and then some, try pulling the final list of buttons from cache first.
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

		// Add in a top section notice callback
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

			// Now we put the buttons in the context so the theme can use them.
			$menu_buttons = $this->initializeButtonProperties($buttons, $menu_count);

			if ($this->cache->levelHigherThan(1))
			{
				$this->cache->put('menu_buttons-' . implode('_', $this->user->groups) . '-' . $this->user->language, $menu_buttons, $this->cacheTime);
			}
		}

		if (!empty($menu_buttons['profile']['sub_buttons']['logout']))
		{
			$menu_buttons['profile']['sub_buttons']['logout']['href'] .= ';' . $context['session_var'] . '=' . $context['session_id'];
		}

		$context['menu_buttons'] = $menu_buttons;
	}

	/**
	 * Initializes the properties of the buttons.
	 *
	 * @param array $buttons The array of buttons.
	 * @param array $menu_count The count of menus.
	 *
	 * @return array The array of buttons with initialized properties.
	 */
	private function initializeButtonProperties($buttons, $menu_count)
	{
		$menu_buttons = [];
		foreach ($buttons as $act => $button)
		{
			if (!empty($button['show']))
			{
				$button = $this->setButtonProperties($button, $menu_count);
				$menu_buttons[$act] = $button;
			}
		}

		return $menu_buttons;
	}

	/**
	 * Set the properties of a button based on the menu count and other criteria.
	 *
	 * @param array $button The button that needs to be updated.
	 * @param array $menu_count The menu count data.
	 * @return array The updated button.
	 */
	private function setButtonProperties($button, $menu_count)
	{
		$button['active_button'] = false;

		$button = $this->setButtonActionHook($button);
		$button = $this->setButtonCounter($button, $menu_count);

		return $this->setSubButtonCounter($button, $menu_count);
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
	 * @return array The updated button.
	 */
	private function setButtonCounter($button, $menu_count)
	{
		if (isset($button['counter']) && !empty($menu_count[$button['counter']]))
		{
			$button['alttitle'] = $button['title'] . ' [' . $menu_count[$button['counter']] . ']';
			$this->addCountsToTitle($button['title'], $menu_count[$button['counter']], 0);
			$button['indicator'] = true;
		}

		return $button;
	}

	/**
	 * Sets the counter for sub buttons of a given button
	 *
	 * @param array $button The button containing sub buttons
	 * @param array $menu_count The count of items for each sub button
	 *
	 * @return array The modified button with updated counters for sub buttons
	 */
	private function setSubButtonCounter($button, $menu_count)
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

				if (isset($subButton['counter']) && !empty($menu_count[$subButton['counter']]))
				{
					$button['sub_buttons'][$key]['alttitle'] = $subButton['title'] . ' [' . $menu_count[$subButton['counter']] . ']';
					$this->addCountsToTitle($button['sub_buttons'][$key]['title'], $menu_count[$subButton['counter']], 1);

					// And any counter on its sub menu
					$button = $this->setSubButtonCounts($button, $key, $subButton, $menu_count);
				}
			}
		}

		return $button;
	}

	/**
	 * Sets the sub button counts for a given button.
	 *
	 * @param array $button The original button array.
	 * @param int $key The key of the sub button.
	 * @param array $subButton The sub button array.
	 * @param array $menu_count The count of menus.
	 *
	 * @return array The updated button array with sub button counts set.
	 */
	private function setSubButtonCounts($button, $key, $subButton, $menu_count)
	{
		if (empty($subButton['sub_buttons']))
		{
			return $button;
		}

		foreach ($subButton['sub_buttons'] as $key2 => $subButton2)
		{
			$button['sub_buttons'][$key]['sub_buttons'][$key2] = $subButton2;

			if (empty($subButton2['show']))
			{
				unset($button['sub_buttons'][$key]['sub_buttons'][$key2]);
			}
			elseif (isset($subButton2['counter']) && !empty($menu_count[$subButton2['counter']]))
			{
				$button['sub_buttons'][$key]['sub_buttons'][$key2]['alttitle'] = $subButton2['title'] . ' [' . $menu_count[$subButton2['counter']] . ']';
				$this->addCountsToTitle($button['sub_buttons'][$key]['sub_buttons'][$key2]['title'], $menu_count[$subButton2['counter']], 1);
				unset($menu_count[$subButton2['counter']]);
			}
		}

		return $button;
	}

	/**
	 * Adds counts to the title.
	 *
	 * @param string $title The title to add counts to.
	 * @param array $counts The array of counts.
	 * @param string $notice The notice to use for formatting.
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
		elseif ($context['current_action'] === 'theme')
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
