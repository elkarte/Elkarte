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

namespace ElkArte\Profile;

use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Cache\Cache;
use ElkArte\Controller\Likes;
use ElkArte\EventManager;
use ElkArte\Exceptions\Exception;
use ElkArte\Languages\Txt;
use ElkArte\Member;
use ElkArte\MembersList;
use ElkArte\Menu\Menu;
use ElkArte\User;
use ElkArte\Util;

/**
 * Has the job of showing and editing people's profiles.
 */
class Profile extends AbstractController
{
	/** @var bool If the save was successful or not */
	private $completedSave = false;

	/** @var null If this was a request to save an update */
	private $isSaving;

	/** @var null What it says, on completion */
	private $_force_redirect;

	/** @var array|bool Holds the output of createMenu for the profile areas */
	private $_profile_include_data;

	/** @var string The current area chosen from the menu */
	private $_current_area;

	/** @var string The current subsection, if any, of the area chosen */
	private $_current_subsection;

	/** @var int Member id for the history being viewed */
	private $_memID = 0;

	/** @var Member The \ElkArte\Member object is stored here to avoid some global */
	private $_profile;

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
	 * @see AbstractController::action_index
	 */
	public function action_index()
	{
		global $txt, $context, $cur_profile, $profile_vars, $post_errors;

		// Don't reload this as we may have processed error strings.
		if (empty($post_errors))
		{
			Txt::load('Profile');
		}

		theme()->getTemplates()->load('Profile');

		// Trigger profile pre-load event
		$this->_events->trigger('pre_load', ['post_errors' => $post_errors]);

		// A little bit about this member
		$context['id_member'] = $this->_memID;
		$cur_profile = $this->_profile;

		// Let's have some information about this member ready, too.
		$context['member'] = $this->_profile;
		$context['member']->loadContext();

		// Is this their own profile or are they looking at someone else?
		$context['user']['is_owner'] = $this->_memID === (int) $this->user->id;

		// Create the menu of profile options
		$this->_define_profile_menu();

		// Is there an updated message to show?
		if (isset($this->_req->query->updated))
		{
			$context['push_alert'] = $_SESSION['push_enabled'] ?? null;
			unset($_SESSION['push_enabled']);
			$context['profile_updated'] = $txt['profile_updated_own'];
		}

		// If it said no permissions that meant it wasn't valid!
		if (empty($this->_profile_include_data['permission']))
		{
			throw new Exception('no_access', false);
		}

		// Choose the right permission set and do a pat check for good measure.
		$this->_profile_include_data['permission'] = $this->_profile_include_data['permission'][$context['user']['is_owner'] ? 'own' : 'any'];
		isAllowedTo($this->_profile_include_data['permission']);

		// Set the selected item - now it's been validated.
		$this->_current_area = $this->_profile_include_data['current_area'];
		$this->_current_subsection = $this->_profile_include_data['current_subsection'] ?? '';
		$context['menu_item_selected'] = $this->_current_area;

		// Before we go any further, let's work on the area we've said is valid.
		// Note this is done here just in case we ever compromise the menu function in error!
		$context['do_preview'] = isset($this->_req->post->preview_signature);
		$this->isSaving = $this->_req->getRequest('save', 'trim', null);

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
			redirectexit('action=profile;area=' . $this->_current_area . (empty($this->_current_subsection) ? '' : ';sa=' . $this->_current_subsection) . ';updated');
		}
		elseif (!empty($this->_force_redirect))
		{
			redirectexit('action=profile' . ($context['user']['is_owner'] ? '' : ';u=' . $this->_memID) . ';area=' . $this->_current_area);
		}

		// Set the page title if it's not already set...
		if (!isset($context['page_title']))
		{
			$context['page_title'] = $txt['profile'] . (isset($txt[$this->_current_area]) ? ' - ' . $txt[$this->_current_area] : '');
		}

		// And off we go,
		$action = new Action();
		$action->initialize(['action' => $this->_profile_include_data]);
		$action->dispatch('action');
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

		$profile_areas = [
			'info' => [
				'title' => $txt['profileInfo'],
				'areas' => [
					'summary' => [
						'label' => $txt['summary'],
						'controller' => ProfileInfo::class,
						'function' => 'action_summary',
						// From the summary it's possible to activate an account, so we need the token
						'token' => 'profile-aa%u',
						'token_type' => 'get',
						'permission' => [
							'own' => 'profile_view_own',
							'any' => 'profile_view_any',
						],
					],
					'statistics' => [
						'label' => $txt['statPanel'],
						'controller' => ProfileInfo::class,
						'function' => 'action_statPanel',
						'permission' => [
							'own' => 'profile_view_own',
							'any' => 'profile_view_any',
						],
					],
					'showposts' => [
						'label' => $txt['showPosts'],
						'controller' => ProfileInfo::class,
						'function' => 'action_showPosts',
						'subsections' => [
							'messages' => [$txt['showMessages'], ['profile_view_own', 'profile_view_any']],
							'topics' => [$txt['showTopics'], ['profile_view_own', 'profile_view_any']],
							'unwatchedtopics' => [$txt['showUnwatched'], ['profile_view_own', 'profile_view_any'], 'enabled' => $modSettings['enable_unwatch'] && $context['user']['is_owner']],
							'attach' => [$txt['showAttachments'], ['profile_view_own', 'profile_view_any']],
						],
						'permission' => [
							'own' => 'profile_view_own',
							'any' => 'profile_view_any',
						],
					],
					'showlikes' => [
						'label' => $txt['likes_show'],
						'controller' => Likes::class,
						'function' => 'action_showProfileLikes',
						'enabled' => !empty($modSettings['likes_enabled']) && $context['user']['is_owner'],
						'subsections' => [
							'given' => [$txt['likes_given'], ['profile_view_own']],
							'received' => [$txt['likes_received'], ['profile_view_own']],
						],
						'permission' => [
							'own' => 'profile_view_own',
							'any' => [],
						],
					],
					'permissions' => [
						'label' => $txt['showPermissions'],
						'controller' => ProfileInfo::class,
						'function' => 'action_showPermissions',
						'permission' => [
							'own' => 'manage_permissions',
							'any' => 'manage_permissions',
						],
					],
					'history' => [
						'label' => $txt['history'],
						'controller' => ProfileHistory::class,
						'function' => 'action_index',
						'subsections' => [
							'activity' => [$txt['trackActivity'], 'moderate_forum'],
							'ip' => [$txt['trackIP'], 'moderate_forum'],
							'edits' => [$txt['trackEdits'], 'moderate_forum', 'enabled' => featureEnabled('ml') && !empty($modSettings['userlog_enabled'])],
							'logins' => [$txt['trackLogins'], ['profile_view_own', 'moderate_forum']],
						],
						'permission' => [
							'own' => 'moderate_forum',
							'any' => 'moderate_forum',
						],
					],
					'viewwarning' => [
						'label' => $txt['profile_view_warnings'],
						'enabled' => featureEnabled('w') && !empty($modSettings['warning_enable']) && $this->_profile['warning'] && (!empty($modSettings['warning_show']) && ($context['user']['is_owner'] || $modSettings['warning_show'] == 2)),
						'controller' => ProfileInfo::class,
						'function' => 'action_viewWarning',
						'permission' => [
							'own' => 'profile_view_own',
							'any' => 'issue_warning',
						],
					],
				],
			],
			'edit_profile' => [
				'title' => $txt['profileEdit'],
				'areas' => [
					'account' => [
						'label' => $txt['account'],
						'controller' => ProfileOptions::class,
						'function' => 'action_account',
						'enabled' => $context['user']['is_admin'] || ((int) $this->_profile['id_group'] !== 1 && !in_array(1, array_map('intval', explode(',', $this->_profile['additional_groups'])), true)), 'sc' => 'post',
						'token' => 'profile-ac%u',
						'password' => true,
						'permission' => [
							'own' => ['profile_identity_any', 'profile_identity_own', 'manage_membergroups'],
							'any' => ['profile_identity_any', 'manage_membergroups'],
						],
					],
					'forumprofile' => [
						'label' => $txt['forumprofile'],
						'controller' => ProfileOptions::class,
						'function' => 'action_forumProfile',
						'sc' => 'post',
						'token' => 'profile-fp%u',
						'permission' => [
							'own' => ['profile_extra_any', 'profile_extra_own', 'profile_title_own', 'profile_title_any'],
							'any' => ['profile_extra_any', 'profile_title_any'],
						],
					],
					'theme' => [
						'label' => $txt['theme'],
						'controller' => ProfileOptions::class,
						'function' => 'action_themepick',
						'sc' => 'post',
						'token' => 'profile-th%u',
						'permission' => [
							'own' => ['profile_extra_any', 'profile_extra_own'],
							'any' => ['profile_extra_any'],
						],
					],
					'pick' => [
						'label' => $txt['theme'],
						'controller' => ProfileOptions::class,
						'function' => 'action_pick',
						'hidden' => true,
						'sc' => 'post',
						'token' => 'profile-th%u',
						'permission' => [
							'own' => ['profile_extra_any', 'profile_extra_own'],
							'any' => ['profile_extra_any'],
						],
					],
					'notification' => [
						'label' => $txt['notifications'],
						'controller' => ProfileOptions::class,
						'function' => 'action_notification',
						'sc' => 'post',
						'token' => 'profile-nt%u',
						'subsections' => [
							'settings' => [$txt['notify_settings']],
							'boards' => [$txt['notify_boards']],
							'topics' => [$txt['notify_topics']],
						],
						'permission' => [
							'own' => ['profile_extra_any', 'profile_extra_own'],
							'any' => ['profile_extra_any'],
						],
					],
					// Without profile_extra_own, settings are accessible from the PM section.
					// @todo at some point decouple it from PMs
					'contactprefs' => [
						'label' => $txt['contactprefs'],
						'controller' => ProfileOptions::class,
						'function' => 'action_pmprefs',
						'enabled' => allowedTo(['profile_extra_own', 'profile_extra_any']),
						'sc' => 'post',
						'token' => 'profile-pm%u',
						'permission' => [
							'own' => ['pm_read'],
							'any' => ['profile_extra_any'],
						],
					],
					'ignoreboards' => [
						'label' => $txt['ignoreboards'],
						'controller' => ProfileOptions::class,
						'function' => 'action_ignoreboards',
						'enabled' => !empty($modSettings['allow_ignore_boards']),
						'sc' => 'post',
						'token' => 'profile-ib%u',
						'permission' => [
							'own' => ['profile_extra_any', 'profile_extra_own'],
							'any' => ['profile_extra_any'],
						],
					],
					'lists' => [
						'label' => $txt['editBuddyIgnoreLists'],
						'controller' => ProfileOptions::class,
						'function' => 'action_editBuddyIgnoreLists',
						'enabled' => !empty($modSettings['enable_buddylist']) && $context['user']['is_owner'],
						'sc' => 'post',
						'token' => 'profile-bl%u',
						'subsections' => [
							'buddies' => [$txt['editBuddies']],
							'ignore' => [$txt['editIgnoreList']],
						],
						'permission' => [
							'own' => ['profile_extra_any', 'profile_extra_own'],
							'any' => [],
						],
					],
					'groupmembership' => [
						'label' => $txt['groupmembership'],
						'controller' => ProfileOptions::class,
						'function' => 'action_groupMembership',
						'enabled' => !empty($modSettings['show_group_membership']) && $context['user']['is_owner'],
						'sc' => 'request',
						'token' => 'profile-gm%u',
						'token_type' => 'request',
						'permission' => [
							'own' => ['profile_view_own'],
							'any' => ['manage_membergroups'],
						],
					],
				],
			],
			'profile_action' => [
				'title' => $txt['profileAction'],
				'areas' => [
					'sendpm' => [
						'label' => $txt['profileSendIm'],
						'custom_url' => getUrl('action', ['action' => 'pm', 'sa' => 'send']),
						'permission' => [
							'own' => [],
							'any' => ['pm_send'],
						],
					],
					'issuewarning' => [
						'label' => $txt['profile_issue_warning'],
						'enabled' => featureEnabled('w') && !empty($modSettings['warning_enable']) && (!$context['user']['is_owner'] || $context['user']['is_admin']),
						'controller' => ProfileAccount::class,
						'function' => 'action_issuewarning',
						'token' => 'profile-iw%u',
						'permission' => [
							'own' => [],
							'any' => ['issue_warning'],
						],
					],
					'banuser' => [
						'label' => $txt['profileBanUser'],
						'custom_url' => getUrl('admin', ['action' => 'admin', 'area' => 'ban', 'sa' => 'add']),
						'enabled' => (int) $this->_profile['id_group'] !== 1 && !in_array(1, array_map('intval', explode(',', $this->_profile['additional_groups'])), true),
						'permission' => [
							'own' => [],
							'any' => ['manage_bans'],
						],
					],
					'subscriptions' => [
						'label' => $txt['subscriptions'],
						'controller' => ProfileSubscriptions::class,
						'function' => 'action_subscriptions',
						'enabled' => !empty($modSettings['paid_enabled']),
						'permission' => [
							'own' => ['profile_view_own'],
							'any' => ['moderate_forum'],
						],
					],
					'deleteaccount' => [
						'label' => $txt['deleteAccount'],
						'controller' => ProfileAccount::class,
						'function' => 'action_deleteaccount',
						'sc' => 'post',
						'token' => 'profile-da%u',
						'password' => true,
						'permission' => [
							'own' => ['profile_remove_any', 'profile_remove_own'],
							'any' => ['profile_remove_any'],
						],
					],
					'activateaccount' => [
						'controller' => ProfileAccount::class,
						'function' => 'action_activateaccount',
						'sc' => 'get',
						'token' => 'profile-aa%u',
						'token_type' => 'get',
						'permission' => [
							'own' => [],
							'any' => ['moderate_forum'],
						],
					],
				],
			],
		];

		// Set a few options for the menu.
		$menuOptions = [
			'disable_url_session_check' => true,
			'hook' => 'profile',
			'extra_url_parameters' => [
				'u' => $context['id_member'],
			],
		];

		// Actually create the menu!
		$this->_profile_include_data = (new Menu())
			->addMenuData($profile_areas)
			->addOptions($menuOptions)
			->prepareMenu()
			->setContext()
			->getIncludeData();

		unset($profile_areas);

		// Make a note of the Unique ID for this menu.
		$context['profile_menu_id'] = $context['max_menu_id'];
		$context['profile_menu_name'] = 'menu_data_' . $context['profile_menu_id'];
	}

	/**
	 * Does session and token checks for the areas that require those
	 */
	private function _check_access()
	{
		global $context;

		// Check the session, if required, and they are trying to save
		$this->completedSave = false;
		if (isset($this->_profile_include_data['sc']) && ($this->isSaving !== null || $context['do_preview']))
		{
			checkSession($this->_profile_include_data['sc']);
			$this->completedSave = true;
		}

		// Does this require admin/moderator session validating?
		if ($this->isSaving !== null && !$context['user']['is_owner'])
		{
			validateSession();
		}

		// Do we need to perform a token check?
		if (!empty($this->_profile_include_data['token']))
		{
			$token_name = str_replace('%u', $context['id_member'], $this->_profile_include_data['token']);
			$token_type = $this->_profile_include_data['tokenType'] ?? 'post';

			if (!in_array($token_type, ['request', 'post', 'get']))
			{
				$token_type = 'post';
			}

			if ($this->isSaving !== null)
			{
				validateToken($token_name, $token_type);
			}

			createToken($token_name, $token_type);
			$context['token_check'] = $token_name;
		}
	}

	/**
	 * Just builds the link tree based on where were are in the profile section
	 * and whose profile is being viewed, etc.
	 */
	private function _build_profile_linktree()
	{
		global $context, $txt;

		$context['linktree'][] = [
			'url' => getUrl('profile', ['action' => 'profile', 'u' => $this->_memID, 'name' => $this->_profile['real_name']]),
			'name' => sprintf($txt['profile_of_username'], $context['member']['name']),
		];

		if (!empty($this->_profile_include_data['label']))
		{
			$context['linktree'][] = [
				'url' => getUrl('profile', ['action' => 'profile', 'area' => $this->_profile_include_data['current_area'], 'u' => $this->_memID, 'name' => $this->_profile['real_name']]),
				'name' => $this->_profile_include_data['label'],
			];
		}

		if (empty($this->_current_subsection))
		{
			return;
		}

		if (!isset($this->_profile_include_data['subsections'][$this->_current_subsection]))
		{
			return;
		}

		if ($this->_profile_include_data['subsections'][$this->_current_subsection]['label'] === $this->_profile_include_data['label'])
		{
			return;
		}

		$context['linktree'][] = [
			'url' => getUrl('profile', ['action' => 'profile', 'area' => $this->_profile_include_data['current_area'], 'sa' => $this->_current_subsection, 'u' => $this->_memID, 'name' => $this->_profile['real_name']]),
			'name' => $this->_profile_include_data['subsections'][$this->_current_subsection]['label'],
		];
	}

	/**
	 * Save profile updates
	 */
	private function _save_updates()
	{
		global $txt, $context, $modSettings, $post_errors, $profile_vars;

		// All the subActions that require a user password in order to validate.
		$check_password = $context['user']['is_owner'] && !empty($this->_profile_include_data['password']);
		$context['require_password'] = $check_password;

		// These will get populated soon!
		$post_errors = [];
		$profile_vars = [];

		if ($this->completedSave)
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
				redirectexit('action=profile' . ($context['user']['is_owner'] ? '' : ';u=' . $this->_memID) . ';area=groupmembership' . (empty($msg) ? '' : ';msg=' . $msg));
			}
			// Authentication changes?
			elseif ($this->_current_area === 'authentication')
			{
				$controller = new ProfileOptions(new EventManager());
				$controller->setUser(User::$info);
				$controller->pre_dispatch();
				$controller->action_authentication(true);
			}
			elseif (in_array($this->_current_area, ['account', 'forumprofile', 'theme', 'contactprefs']))
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

				$profileFields = new ProfileFields();
				$profileFields->saveProfileFields($fields['fields'], $fields['hook']);
			}
			elseif (empty($post_errors))
			{
				// @todo yes this is also ugly, but saveProfileChanges needs to be updated first
				$_POST = (array) $this->_req->post;

				$this->_force_redirect = true;
				saveProfileChanges($profile_vars, $this->_memID);
			}

			call_integration_hook('integrate_profile_save', [&$profile_vars, &$post_errors, $this->_memID]);

			// There was a problem, let them try to re-enter.
			if (!empty($post_errors))
			{
				// Load the language file so we can give a nice explanation of the errors.
				Txt::load('Errors');
				$context['post_errors'] = $post_errors;
			}
			elseif (!empty($profile_vars))
			{
				// If we've changed the password, notify any integration that may be listening in.
				if (isset($profile_vars['passwd']))
				{
					call_integration_hook('integrate_reset_pass', [$this->_profile['member_name'], $this->_profile['member_name'], $this->_req->post->passwrd2]);
				}

				require_once(SUBSDIR . '/Members.subs.php');
				updateMemberData($this->_memID, $profile_vars);

				// What if this is the newest member?
				if ((int) $modSettings['latestMember'] === $this->_memID)
				{
					require_once(SUBSDIR . '/Members.subs.php');
					updateMemberStats();
				}
				elseif (isset($profile_vars['real_name']))
				{
					updateSettings(['memberlist_updated' => time()]);
				}

				// If the member changed his/her birth date, update calendar statistics.
				if (isset($profile_vars['birthdate']) || isset($profile_vars['real_name']))
				{
					updateSettings([
						'calendar_updated' => time(),
					]);
				}

				// Anything worth logging?
				if (!empty($context['log_changes']) && !empty($modSettings['userlog_enabled']) && featureEnabled('ml'))
				{
					$log_changes = [];
					foreach ($context['log_changes'] as $k => $v)
					{
						$log_changes[] = [
							'action' => $k,
							'log_type' => 'user',
							'extra' => array_merge($v, [
								'applicator' => $this->user->id,
								'member_affected' => $this->_memID,
							]),
						];
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
				MembersList::load($this->_memID, false, 'profile');
			}
		}
	}

	/**
	 * If a password validation before a change is needed, this is the function to do it
	 *
	 * @param bool $check_password if this profile update requires a password verification
	 * @throws Exception
	 */
	private function _check_password($check_password)
	{
		global $post_errors, $context;

		if ($check_password)
		{
			// You didn't even enter a password!
			if (trim($this->_req->post->oldpasswrd) === '')
			{
				$post_errors[] = 'no_password';
			}

			// Since the password got modified due to all the $_POST cleaning, lets undo it so we can get the correct password
			$this->_req->post->oldpasswrd = un_htmlspecialchars($this->_req->post->oldpasswrd);

			// Does the integration want to check passwords?
			$good_password = in_array(true, call_integration_hook('integrate_verify_password', [$this->_profile['member_name'], $this->_req->post->oldpasswrd, false]), true);

			// Start up the password checker, we have work to do
			require_once(SUBSDIR . '/Auth.subs.php');

			// Bad password!!!
			if (!$good_password && !validateLoginPassword($this->_req->post->oldpasswrd, $this->user->passwd, $this->_profile['member_name']))
			{
				$post_errors[] = 'bad_password';
			}

			// Warn other elements not to jump the gun and do custom changes!
			if (in_array('bad_password', $post_errors, true))
			{
				$context['password_auth_failed'] = true;
			}
		}
	}
}
