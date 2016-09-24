<?php

/**
 * This file has the primary job of showing and editing people's profiles.
 * It also allows the user to change some of their or another's preferences,
 * and such things.
 *
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

namespace ElkArte\Controller;

use ElkArte\AbstractController;
use ElkArte\Cache\Cache;
use ElkArte\EventManager;
use ElkArte\Exceptions\Exception;
use ElkArte\MembersList;
use ElkArte\User;
use ElkArte\Util;

/**
 * Has the job of showing and editing people's profiles.
 */
class Profile extends AbstractController
{
	/**
	 * If the save was successful or not
	 *
	 * @var boolean
	 */
	private $_completed_save = false;

	/**
	 * If this was a request to save an update
	 *
	 * @var null
	 */
	private $_saving = null;

	/**
	 * What it says, on completion
	 *
	 * @var bool
	 */
	private $_force_redirect;

	/**
	 * Holds the output of createMenu for the profile areas
	 *
	 * @var array|boolean
	 */
	private $_profile_include_data;

	/**
	 * The current area chosen from the menu
	 *
	 * @var string
	 */
	private $_current_area;

	/**
	 * Member id for the history being viewed
	 *
	 * @var int
	 */
	private $_memID = 0;

	/**
	 * The \ElkArte\Member object is stored here to avoid some global
	 *
	 * @var \ElkArte\Member
	 */
	private $_profile = null;

	/**
	 * Holds the create menu object
	 * @var object
	 */
	private $_menu;

	/**
	 * Called before all other methods when coming from the dispatcher or
	 * action class.
	 */
	public function pre_dispatch()
	{
		require_once(SUBSDIR . '/Menu.subs.php');
		require_once(SUBSDIR . '/Profile.subs.php');

		$this->_memID = currentMemberID();
		MembersList::load($this->_memID, false, 'profile');
		$this->_profile = MembersList::get($this->_memID);
	}

	/**
	 * Allow the change or view of profiles.
	 *
	 * - Fires the pre_load event
	 *
	 * @see \ElkArte\AbstractController::action_index()
	 */
	public function action_index()
	{
		global $txt, $context, $cur_profile, $profile_vars, $post_errors;

		// Don't reload this as we may have processed error strings.
		if (empty($post_errors))
		{
			theme()->getTemplates()->loadLanguageFile('Profile');
		}

		theme()->getTemplates()->load('Profile');

		// Trigger profile pre-load event
		$this->_events->trigger('pre_load', array('post_errors' => $post_errors));

		// A little bit about this member
		$context['id_member'] = $this->_memID;
		$cur_profile = $this->_profile;

		// Let's have some information about this member ready, too.
		$context['member'] = $this->_profile;
		$context['member']->loadContext();

		// Is this the profile of the user himself or herself?
		$context['user']['is_owner'] = (int) $this->_memID === (int) $this->user->id;

		// Create the menu of profile options
		$this->_define_profile_menu();

		// Is there an updated message to show?
		if (isset($this->_req->query->updated))
		{
			$context['profile_updated'] = $txt['profile_updated_own'];
		}

		// If it said no permissions that meant it wasn't valid!
		if ($this->_profile_include_data && empty($this->_profile_include_data['permission']))
		{
			$this->_profile_include_data['enabled'] = false;
		}

		// No menu and guest? A warm welcome to register
		if (!$this->_profile_include_data && $this->user->is_guest)
		{
			is_not_guest();
		}

		// No menu means no access at all.
		if (!$this->_profile_include_data || (isset($this->_profile_include_data['enabled']) && $this->_profile_include_data['enabled'] === false))
		{
			throw new Exception('no_access', false);
		}

		// Make a note of the Unique ID for this menu.
		$context['profile_menu_id'] = $context['max_menu_id'];
		$context['profile_menu_name'] = 'menu_data_' . $context['profile_menu_id'];

		// Set the selected item - now it's been validated.
		$this->_current_area = $this->_profile_include_data['current_area'];
		$context['menu_item_selected'] = $this->_current_area;

		// Before we go any further, let's work on the area we've said is valid.
		// Note this is done here just in case we ever compromise the menu function in error!
		$this->_completed_save = false;
		$context['do_preview'] = isset($this->_req->post->preview_signature);

		// Are we saving data in a valid area?
		$this->_saving = $this->_req->getPost('save', 'trim', $this->_req->getQuery('save', 'trim', null));
		if (isset($this->_profile_include_data['sc']) && (isset($this->_saving) || $context['do_preview']))
		{
			checkSession($this->_profile_include_data['sc']);
			$this->_completed_save = true;
		}

		// Permissions for good measure.
		if (!empty($this->_profile_include_data['permission']))
		{
			isAllowedTo($this->_profile_include_data['permission'][$context['user']['is_owner'] ? 'own' : 'any']);
		}

		// Session validation and/or Token Checks
		$this->_check_access();

		// Build the link tree.
		$this->_build_profile_linktree();

		// Set the template for this area... if you still can :P
		// and add the profile layer.
		$context['sub_template'] = $this->_profile_include_data['function'];
		theme()->getLayers()->add('profile');

		// Need JS if we made it this far
		loadJavascriptFile('profile.js');

		// Right - are we saving - if so let's save the old data first.
		$this->_save_updates();

		// Have some errors for some reason?
		// @todo check that this can be safely removed.
		if (!empty($post_errors))
		{
			// Set all the errors so the template knows what went wrong.
			foreach ($post_errors as $error_type)
			{
				$context['modify_error'][$error_type] = true;
			}
		}
		// If it's you then we should redirect upon save.
		elseif (!empty($profile_vars) && $context['user']['is_owner'] && !$context['do_preview'])
		{
			redirectexit('action=profile;area=' . $this->_current_area . ';updated');
		}
		elseif (!empty($this->_force_redirect))
		{
			redirectexit('action=profile' . ($context['user']['is_owner'] ? '' : ';u=' . $this->_memID) . ';area=' . $this->_current_area);
		}

		// Let go to the right place
		if (isset($this->_profile_include_data['file']))
		{
			require_once($this->_profile_include_data['file']);
		}

		$this->_menu->callMenu($this->_profile_include_data);

		// Set the page title if it's not already set...
		if (!isset($context['page_title']))
		{
			$context['page_title'] = $txt['profile'] . (isset($txt[$this->_current_area]) ? ' - ' . $txt[$this->_current_area] : '');
		}
	}

	/**
	 * Define all the sections within the profile area!
	 *
	 * We start by defining the permission required - then we take this and turn
	 * it into the relevant context ;)
	 *
	 * Possible fields:
	 *   For Section:
	 *    - string $title: Section title.
	 *    - array $areas:  Array of areas within this section.
	 *
	 *   For Areas:
	 *    - string $label:      Text string that will be used to show the area in the menu.
	 *    - string $file:       Optional text string that may contain a file name that's needed for inclusion in order to display the area properly.
	 *    - string $custom_url: Optional href for area.
	 *    - string $function:   Function to execute for this section.
	 *    - bool $enabled:      Should area be shown?
	 *    - string $sc:         Session check validation to do on save - note without this save will get unset - if set.
	 *    - bool $hidden:       Does this not actually appear on the menu?
	 *    - bool $password:     Whether to require the user's password in order to save the data in the area.
	 *    - array $subsections: Array of subsections, in order of appearance.
	 *    - array $permission:  Array of permissions to determine who can access this area. Should contain arrays $own and $any.
	 */
	private function _define_profile_menu()
	{
		global $txt, $context, $modSettings;

		$profile_areas = array(
			'info' => array(
				'title' => $txt['profileInfo'],
				'areas' => array(
					'summary' => array(
						'label' => $txt['summary'],
						'controller' => '\\ElkArte\\Controller\\ProfileInfo',
						'function' => 'action_summary',
						// From the summary it's possible to activate an account, so we need the token
						'token' => 'profile-aa%u',
						'token_type' => 'get',
						'permission' => array(
							'own' => 'profile_view_own',
							'any' => 'profile_view_any',
						),
					),
					'statistics' => array(
						'label' => $txt['statPanel'],
						'controller' => '\\ElkArte\\Controller\\ProfileInfo',
						'function' => 'action_statPanel',
						'permission' => array(
							'own' => 'profile_view_own',
							'any' => 'profile_view_any',
						),
					),
					'showposts' => array(
						'label' => $txt['showPosts'],
						'controller' => '\\ElkArte\\Controller\\ProfileInfo',
						'function' => 'action_showPosts',
						'subsections' => array(
							'messages' => array($txt['showMessages'], array('profile_view_own', 'profile_view_any')),
							'topics' => array($txt['showTopics'], array('profile_view_own', 'profile_view_any')),
							'unwatchedtopics' => array($txt['showUnwatched'], array('profile_view_own', 'profile_view_any'), 'enabled' => $modSettings['enable_unwatch'] && $context['user']['is_owner']),
							'attach' => array($txt['showAttachments'], array('profile_view_own', 'profile_view_any')),
						),
						'permission' => array(
							'own' => 'profile_view_own',
							'any' => 'profile_view_any',
						),
					),
					'showlikes' => array(
						'label' => $txt['likes_show'],
						'controller' => '\\ElkArte\\Controller\\Likes',
						'function' => 'action_showProfileLikes',
						'enabled' => !empty($modSettings['likes_enabled']) && $context['user']['is_owner'],
						'subsections' => array(
							'given' => array($txt['likes_given'], array('profile_view_own')),
							'received' => array($txt['likes_received'], array('profile_view_own')),
						),
						'permission' => array(
							'own' => 'profile_view_own',
							'any' => array(),
						),
					),
					'permissions' => array(
						'label' => $txt['showPermissions'],
						'controller' => '\\ElkArte\\Controller\\ProfileInfo',
						'function' => 'action_showPermissions',
						'permission' => array(
							'own' => 'manage_permissions',
							'any' => 'manage_permissions',
						),
					),
					'history' => array(
						'label' => $txt['history'],
						'controller' => '\\ElkArte\\Controller\\ProfileHistory',
						'function' => 'action_index',
						'subsections' => array(
							'activity' => array($txt['trackActivity'], 'moderate_forum'),
							'ip' => array($txt['trackIP'], 'moderate_forum'),
							'edits' => array($txt['trackEdits'], 'moderate_forum'),
							'logins' => array($txt['trackLogins'], array('profile_view_own', 'moderate_forum')),
						),
						'permission' => array(
							'own' => 'moderate_forum',
							'any' => 'moderate_forum',
						),
					),
					'viewwarning' => array(
						'label' => $txt['profile_view_warnings'],
						'enabled' => featureEnabled('w') && !empty($modSettings['warning_enable']) && $this->_profile['warning'] && (!empty($modSettings['warning_show']) && ($context['user']['is_owner'] || $modSettings['warning_show'] == 2)),
						'controller' => '\\ElkArte\\Controller\\ProfileInfo',
						'function' => 'action_viewWarning',
						'permission' => array(
							'own' => 'profile_view_own',
							'any' => 'issue_warning',
						),
					),
				),
			),
			'edit_profile' => array(
				'title' => $txt['profileEdit'],
				'areas' => array(
					'account' => array(
						'label' => $txt['account'],
						'controller' => '\\ElkArte\\Controller\\ProfileOptions',
						'function' => 'action_account',
						'enabled' => $context['user']['is_admin'] || ($this->_profile['id_group'] != 1 && !in_array(1, explode(',', $this->_profile['additional_groups']))),
						'sc' => 'post',
						'token' => 'profile-ac%u',
						'password' => true,
						'permission' => array(
							'own' => array('profile_identity_any', 'profile_identity_own', 'manage_membergroups'),
							'any' => array('profile_identity_any', 'manage_membergroups'),
						),
					),
					'forumprofile' => array(
						'label' => $txt['forumprofile'],
						'controller' => '\\ElkArte\\Controller\\ProfileOptions',
						'function' => 'action_forumProfile',
						'sc' => 'post',
						'token' => 'profile-fp%u',
						'permission' => array(
							'own' => array('profile_extra_any', 'profile_extra_own', 'profile_title_own', 'profile_title_any'),
							'any' => array('profile_extra_any', 'profile_title_any'),
						),
					),
					'theme' => array(
						'label' => $txt['theme'],
						'controller' => '\\ElkArte\\Controller\\ProfileOptions',
						'function' => 'action_themepick',
						'sc' => 'post',
						'token' => 'profile-th%u',
						'permission' => array(
							'own' => array('profile_extra_any', 'profile_extra_own'),
							'any' => array('profile_extra_any'),
						),
					),
					'authentication' => array(
						'label' => $txt['authentication'],
						'controller' => '\\ElkArte\\Controller\\ProfileOptions',
						'function' => 'action_authentication',
						'enabled' => !empty($modSettings['enableOpenID']) || !empty($this->_profile['openid_uri']),
						'sc' => 'post',
						'token' => 'profile-au%u',
						'hidden' => empty($modSettings['enableOpenID']) && empty($this->_profile['openid_uri']),
						'password' => true,
						'permission' => array(
							'own' => array('profile_identity_any', 'profile_identity_own'),
							'any' => array('profile_identity_any'),
						),
					),
					'notification' => array(
						'label' => $txt['notifications'],
						'controller' => '\\ElkArte\\Controller\\ProfileOptions',
						'function' => 'action_notification',
						'sc' => 'post',
						'token' => 'profile-nt%u',
						'permission' => array(
							'own' => array('profile_extra_any', 'profile_extra_own'),
							'any' => array('profile_extra_any'),
						),
					),
					// Without profile_extra_own, settings are accessible from the PM section.
					// @todo at some point decouple it from PMs
					'contactprefs' => array(
						'label' => $txt['contactprefs'],
						'controller' => '\\ElkArte\\Controller\\ProfileOptions',
						'function' => 'action_pmprefs',
						'enabled' => allowedTo(array('profile_extra_own', 'profile_extra_any')),
						'sc' => 'post',
						'token' => 'profile-pm%u',
						'permission' => array(
							'own' => array('pm_read'),
							'any' => array('profile_extra_any'),
						),
					),
					'ignoreboards' => array(
						'label' => $txt['ignoreboards'],
						'controller' => '\\ElkArte\\Controller\\ProfileOptions',
						'function' => 'action_ignoreboards',
						'enabled' => !empty($modSettings['allow_ignore_boards']),
						'sc' => 'post',
						'token' => 'profile-ib%u',
						'permission' => array(
							'own' => array('profile_extra_any', 'profile_extra_own'),
							'any' => array('profile_extra_any'),
						),
					),
					'lists' => array(
						'label' => $txt['editBuddyIgnoreLists'],
						'controller' => '\\ElkArte\\Controller\\ProfileOptions',
						'function' => 'action_editBuddyIgnoreLists',
						'enabled' => !empty($modSettings['enable_buddylist']) && $context['user']['is_owner'],
						'sc' => 'post',
						'token' => 'profile-bl%u',
						'subsections' => array(
							'buddies' => array($txt['editBuddies']),
							'ignore' => array($txt['editIgnoreList']),
						),
						'permission' => array(
							'own' => array('profile_extra_any', 'profile_extra_own'),
							'any' => array(),
						),
					),
					'groupmembership' => array(
						'label' => $txt['groupmembership'],
						'controller' => '\\ElkArte\\Controller\\ProfileOptions',
						'function' => 'action_groupMembership',
						'enabled' => !empty($modSettings['show_group_membership']) && $context['user']['is_owner'],
						'sc' => 'request',
						'token' => 'profile-gm%u',
						'token_type' => 'request',
						'permission' => array(
							'own' => array('profile_view_own'),
							'any' => array('manage_membergroups'),
						),
					),
				),
			),
			'profile_action' => array(
				'title' => $txt['profileAction'],
				'areas' => array(
					'sendpm' => array(
						'label' => $txt['profileSendIm'],
						'custom_url' => getUrl('action', ['action' => 'pm', 'sa' => 'send']),
						'permission' => array(
							'own' => array(),
							'any' => array('pm_send'),
						),
					),
					'issuewarning' => array(
						'label' => $txt['profile_issue_warning'],
						'enabled' => featureEnabled('w') && !empty($modSettings['warning_enable']) && (!$context['user']['is_owner'] || $context['user']['is_admin']),
						'controller' => '\\ElkArte\\Controller\\ProfileAccount',
						'function' => 'action_issuewarning',
						'token' => 'profile-iw%u',
						'permission' => array(
							'own' => array(),
							'any' => array('issue_warning'),
						),
					),
					'banuser' => array(
						'label' => $txt['profileBanUser'],
						'custom_url' => getUrl('admin', ['action' => 'admin', 'area' => 'ban', 'sa' => 'add']),
						'enabled' => $this->_profile['id_group'] != 1 && !in_array(1, explode(',', $this->_profile['additional_groups'])),
						'permission' => array(
							'own' => array(),
							'any' => array('manage_bans'),
						),
					),
					'subscriptions' => array(
						'label' => $txt['subscriptions'],
						'controller' => '\\ElkArte\\Controller\\ProfileSubscriptions',
						'function' => 'action_subscriptions',
						'enabled' => !empty($modSettings['paid_enabled']),
						'permission' => array(
							'own' => array('profile_view_own'),
							'any' => array('moderate_forum'),
						),
					),
					'deleteaccount' => array(
						'label' => $txt['deleteAccount'],
						'controller' => '\\ElkArte\\Controller\\ProfileAccount',
						'function' => 'action_deleteaccount',
						'sc' => 'post',
						'token' => 'profile-da%u',
						'password' => true,
						'permission' => array(
							'own' => array('profile_remove_any', 'profile_remove_own'),
							'any' => array('profile_remove_any'),
						),
					),
					'activateaccount' => array(
						'controller' => '\\ElkArte\\Controller\\ProfileAccount',
						'function' => 'action_activateaccount',
						'sc' => 'get',
						'token' => 'profile-aa%u',
						'token_type' => 'get',
						'permission' => array(
							'own' => array(),
							'any' => array('moderate_forum'),
						),
					),
				),
			),
		);

		// Set a few options for the menu.
		$menuOptions = array(
			'disable_url_session_check' => true,
			'hook' => 'profile',
			'extra_url_parameters' => array(
				'u' => $context['id_member'],
			),
			'default_include_dir' => CONTROLLERDIR,
		);

		// Actually create the menu!
		$this->_menu = new Menu();
		$this->_menu->addOptions($menuOptions);
		$this->_menu->addAreas($profile_areas);
		$this->_profile_include_data = $this->_menu->prepareMenu();
		$this->_menu->setContext();
		unset($profile_areas);
	}

	/**
	 * Does session and token checks for the areas that require those
	 */
	private function _check_access()
	{
		global $context;

		// Does this require session validating?
		if (!empty($this->_profile_include_data['validate'])
			|| (isset($this->_saving) && !$context['user']['is_owner']))
		{
			validateSession();
		}

		// Do we need to perform a token check?
		if (!empty($this->_profile_include_data['token']))
		{
			if ($this->_profile_include_data['token'] !== true)
			{
				$token_name = str_replace('%u', $context['id_member'], $this->_profile_include_data['token']);
			}
			else
			{
				$token_name = 'profile-u' . $context['id_member'];
			}

			if (isset($this->_profile_include_data['token_type']) && in_array($this->_profile_include_data['token_type'], array('request', 'post', 'get')))
			{
				$token_type = $this->_profile_include_data['token_type'];
			}
			else
			{
				$token_type = 'post';
			}

			if (isset($this->_saving))
			{
				validateToken($token_name, $token_type);
			}

			createToken($token_name, $token_type);
			$context['token_check'] = $token_name;
		}
	}

	/**
	 * Just builds the link tree based on where were are in the profile section
	 * and who's profile is being viewed, etc.
	 */
	private function _build_profile_linktree()
	{
		global $context, $txt;

		$context['linktree'][] = array(
			'url' => getUrl('profile', ['action' => 'profile', 'u' => $this->_memID, 'name' => $this->_profile['real_name']]),
			'name' => sprintf($txt['profile_of_username'], $context['member']['name']),
		);

		if (!empty($this->_profile_include_data['label']))
		{
			$context['linktree'][] = array(
				'url' => getUrl('profile', ['action' => 'profile', 'area' => $this->_profile_include_data['current_area'], 'u' => $this->_memID, 'name' => $this->_profile['real_name']]),
				'name' => $this->_profile_include_data['label'],
			);
		}

		if (!empty($this->_profile_include_data['current_subsection']) && $this->_profile_include_data['subsections'][$this->_profile_include_data['current_subsection']][0] !== $this->_profile_include_data['label'])
		{
			$context['linktree'][] = array(
				'url' => getUrl('profile', ['action' => 'profile', 'area' => $this->_profile_include_data['current_area'], 'sa' => $this->_profile_include_data['current_subsection'], 'u' => $this->_memID, 'name' => $this->_profile['real_name']]),
				'name' => $this->_profile_include_data['subsections'][$this->_profile_include_data['current_subsection']][0],
			);
		}
	}

	/**
	 * Save profile updates
	 */
	private function _save_updates()
	{
		global $txt, $context, $modSettings, $post_errors, $profile_vars;

		// All the subactions that require a user password in order to validate.
		$check_password = $context['user']['is_owner'] && !empty($this->_profile_include_data['password']);
		$context['require_password'] = $check_password && empty(User::$settings['openid_uri']);

		// These will get populated soon!
		$post_errors = array();
		$profile_vars = array();

		if ($this->_completed_save)
		{
			// Clean up the POST variables.
			$post = Util::htmltrim__recursive((array) $this->_req->post);
			$post = Util::htmlspecialchars__recursive($post);
			$this->_req->post = new \ArrayObject($post, \ArrayObject::ARRAY_AS_PROPS);

			// Does the change require the current password as well?
			$this->_check_password($check_password);

			// Change the IP address in the database.
			if ($context['user']['is_owner'])
			{
				$profile_vars['member_ip'] = $this->user->ip;
			}

			// Now call the sub-action function...
			if ($this->_current_area === 'activateaccount' && empty($post_errors))
			{
				$controller = new ProfileAccount(new EventManager());
				$controller->setUser(User::$info);
				$controller->pre_dispatch();
				$controller->action_activateaccount();
			}
			elseif ($this->_current_area === 'deleteaccount' && empty($post_errors))
			{
				$controller = new ProfileAccount(new EventManager());
				$controller->setUser(User::$info);
				$controller->pre_dispatch();
				$controller->action_deleteaccount2();

				// Done
				redirectexit();
			}
			elseif ($this->_current_area === 'groupmembership' && empty($post_errors))
			{
				$controller = new ProfileOptions(new EventManager());
				$controller->setUser(User::$info);
				$controller->pre_dispatch();
				$msg = $controller->action_groupMembership2();

				// Whatever we've done, we have nothing else to do here...
				redirectexit('action=profile' . ($context['user']['is_owner'] ? '' : ';u=' . $this->_memID) . ';area=groupmembership' . (!empty($msg) ? ';msg=' . $msg : ''));
			}
			// Authentication changes?
			elseif ($this->_current_area === 'authentication')
			{
				$controller = new ProfileOptions(new EventManager());
				$controller->setUser(User::$info);
				$controller->pre_dispatch();
				$controller->action_authentication(true);
			}
			elseif (in_array($this->_current_area, array('account', 'forumprofile', 'theme', 'contactprefs')))
			{
				// @todo yes this is ugly, but saveProfileFields needs to be updated first
				$_POST = (array) $this->_req->post;

				if ($this->_current_area === 'account' && !empty($modSettings['enableOTP']))
				{
					$fields = ProfileOptions::getFields('account_otp');
				}
				else
				{
					$fields = ProfileOptions::getFields($this->_current_area);
				}

				saveProfileFields($fields['fields'], $fields['hook']);
			}
			elseif (empty($post_errors))
			{
				// @todo yes this is also ugly, but saveProfileChanges needs to be updated first
				$_POST = (array) $this->_req->post;

				$this->_force_redirect = true;
				saveProfileChanges($profile_vars, $this->_memID);
			}

			call_integration_hook('integrate_profile_save', array(&$profile_vars, &$post_errors, $this->_memID));

			// There was a problem, let them try to re-enter.
			if (!empty($post_errors))
			{
				// Load the language file so we can give a nice explanation of the errors.
				theme()->getTemplates()->loadLanguageFile('Errors');
				$context['post_errors'] = $post_errors;
			}
			elseif (!empty($profile_vars))
			{
				// If we've changed the password, notify any integration that may be listening in.
				if (isset($profile_vars['passwd']))
				{
					call_integration_hook('integrate_reset_pass', array($this->_profile['member_name'], $this->_profile['member_name'], $this->_req->post->passwrd2));
				}

				require_once(SUBSDIR . '/Members.subs.php');
				updateMemberData($this->_memID, $profile_vars);

				// What if this is the newest member?
				if ($modSettings['latestMember'] == $this->_memID)
				{
					require_once(SUBSDIR . '/Members.subs.php');
					updateMemberStats();
				}
				elseif (isset($profile_vars['real_name']))
				{
					updateSettings(array('memberlist_updated' => time()));
				}

				// If the member changed his/her birth date, update calendar statistics.
				if (isset($profile_vars['birthdate']) || isset($profile_vars['real_name']))
				{
					updateSettings(array(
						'calendar_updated' => time(),
					));
				}

				// Anything worth logging?
				if (!empty($context['log_changes']) && !empty($modSettings['modlog_enabled']))
				{
					$log_changes = array();
					foreach ($context['log_changes'] as $k => $v)
					{
						$log_changes[] = array(
							'action' => $k,
							'log_type' => 'user',
							'extra' => array_merge($v, array(
								'applicator' => $this->user->id,
								'member_affected' => $this->_memID,
							)),
						);
					}

					logActions($log_changes);
				}

				// Have we got any post save functions to execute?
				if (!empty($context['profile_execute_on_save']))
				{
					foreach ($context['profile_execute_on_save'] as $saveFunc)
					{
						$saveFunc();
					}
				}

				// Let them know it worked!
				$context['profile_updated'] = $context['user']['is_owner'] ? $txt['profile_updated_own'] : sprintf($txt['profile_updated_else'], $this->_profile['member_name']);

				// Invalidate any cached data.
				Cache::instance()->remove('member_data-profile-' . $this->_memID);
			}
		}
	}

	/**
	 * If a password validation before a change is needed, this is the function to do it
	 *
	 * @param bool $check_password if this profile update requires a password verification
	 * @throws \ElkArte\Exceptions\Exception
	 */
	private function _check_password($check_password)
	{
		global $post_errors, $context;

		if ($check_password)
		{
			// If we're using OpenID try to re-validate.
			if (!empty(User::$settings['openid_uri']))
			{
				$openID = new \ElkArte\OpenID();
				$openID->revalidate();
			}
			else
			{
				// You didn't even enter a password!
				if (trim($this->_req->post->oldpasswrd) === '')
				{
					$post_errors[] = 'no_password';
				}

				// Since the password got modified due to all the $_POST cleaning, lets undo it so we can get the correct password
				$this->_req->post->oldpasswrd = un_htmlspecialchars($this->_req->post->oldpasswrd);

				// Does the integration want to check passwords?
				$good_password = in_array(true, call_integration_hook('integrate_verify_password', array($this->_profile['member_name'], $this->_req->post->oldpasswrd, false)), true);

				// Start up the password checker, we have work to do
				require_once(SUBSDIR . '/Auth.subs.php');

				// Bad password!!!
				if (!$good_password && !validateLoginPassword($this->_req->post->oldpasswrd, $this->user->passwd, $this->_profile['member_name']))
				{
					$post_errors[] = 'bad_password';
				}

				// Warn other elements not to jump the gun and do custom changes!
				if (in_array('bad_password', $post_errors))
				{
					$context['password_auth_failed'] = true;
				}
			}
		}
	}
}
