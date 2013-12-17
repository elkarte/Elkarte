<?php

/**
 * Primary site dispatch controller, sends the request to the function or method
 * registered to handle it.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Beta
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Dispatch the request to the function or method registered to handle it.
 * Try first the critical functionality (maintenance, no guest access)
 * Then, in order:
 *   forum's main actions: board index, message index, display topic
 *   the current/legacy file/functions registered by ElkArte core
 * Fall back to naming patterns:
 *   filename=[action].php function=[sa]
 *   filename=[action].controller.php method=action_[sa]
 *   filename=[action]-Controller.php method=action_[sa]
 *
 * An addon files to handle custom actions will be called if they follow
 * any of these patterns.
 */
class Site_Dispatcher
{
	/**
	 * File name to load
	 * @var string
	 */
	private $_file_name;

	/**
	 * Function or method to call
	 * @var string
	 */
	private $_function_name;

	/**
	 * Class name, for object oriented controllers
	 * @var string
	 */
	private $_controller_name;

	/**
	 * Name of pre_dispatch function, for procedural controllers
	 * @var string
	 */
	private $_pre_dispatch_func;

	/**
	 * Create an instance and initialize it.
	 * This does all the work to figure out which file and function/method needs called.
	 */
	public function __construct()
	{
		global $board, $topic, $modSettings, $settings, $user_info, $maintenance;

		// Default action of the forum: board index
		// Everytime we don't know what to do, we'll do this :P
		$default_action = array(
			'file' => CONTROLLERDIR . '/BoardIndex.controller.php',
			'controller' => 'BoardIndex_Controller',
			'function' => 'action_boardindex'
		);

		// Maintenance mode: you're out of here unless you're admin
		if (!empty($maintenance) && !allowedTo('admin_forum'))
		{
			// You can only login
			if (isset($_GET['action']) && ($_GET['action'] == 'login2' || $_GET['action'] == 'logout'))
			{
				$this->_file_name = CONTROLLERDIR . '/Auth.controller.php';
				$this->_controller_name = 'Auth_Controller';
				$this->_function_name = $_GET['action'] == 'login2' ? 'action_login2' : 'action_logout';
			}
			// "maintenance mode" page
			else
			{
				$this->_file_name = CONTROLLERDIR . '/Auth.controller.php';
				$this->_controller_name = 'Auth_Controller';
				$this->_function_name = 'action_maintenance_mode';
			}
		}
		// If guest access is disallowed, a guest is kicked out... politely. :P
		elseif (empty($modSettings['allow_guestAccess']) && $user_info['is_guest'] && (!isset($_GET['action']) || !in_array($_GET['action'], array('coppa', 'login', 'login2', 'register', 'register2', 'reminder', 'activate', 'help', 'quickhelp', 'mailq', 'verificationcode', 'openidreturn'))))
		{
			$this->_file_name = CONTROLLERDIR . '/Auth.controller.php';
			$this->_controller_name = 'Auth_Controller';
			$this->_function_name = 'action_kickguest';
		}
		elseif (empty($_GET['action']))
		{
			// Home page: board index
			if (empty($board) && empty($topic))
			{
				// Reminder: hooks need to account for multiple addons setting this hook.
				call_integration_hook('integrate_frontpage', array(&$default_action));

				// Was it, wasn't it....
				if (empty($this->_function_name))
				{
					$this->_file_name = $default_action['file'];
					if (isset($default_action['controller']))
						$this->_controller_name = $default_action['controller'];
					$this->_function_name = $default_action['function'];
				}
			}
			// ?board=b message index
			elseif (empty($topic))
			{
				$this->_file_name = CONTROLLERDIR . '/MessageIndex.controller.php';
				$this->_controller_name = 'MessageIndex_Controller';
				$this->_function_name = 'action_messageindex';
			}
			// board=b;topic=t topic display
			else
			{
				$this->_file_name = CONTROLLERDIR . '/Display.controller.php';
				$this->_controller_name = 'Display_Controller';
				$this->_function_name = 'action_display';
			}
		}

		// Now this return won't be cool, but lets do it
		if (!empty($this->_file_name) && !empty($this->_function_name))
			return;

		// Start with our nice and cozy err... *cough*
		// Format:
		// $_GET['action'] => array($file, $function)
		// $_GET['action'] => array($file, $class, $method)
		$actionArray = array(
			'activate' => array('Register.controller.php', 'Register_Controller', 'action_activate'),
			'admin' => array('Admin.controller.php', 'Admin_Controller', 'action_index'),
			'attachapprove' => array('ModerateAttachments.controller.php', 'ModerateAttachments_Controller', 'action_attachapprove'),
			'buddy' => array('Members.controller.php', 'Members_Controller', 'action_buddy'),
			'collapse' => array('BoardIndex.controller.php', 'BoardIndex_Controller', 'action_collapse'),
			'contact' => array('Register.controller.php', 'Register_Controller', 'action_contact'),
			'coppa' => array('Register.controller.php', 'Register_Controller', 'action_coppa'),
			'deletemsg' => array('RemoveTopic.controller.php', 'RemoveTopic_Controller', 'action_deletemsg'),
			'dlattach' => array('Attachment.controller.php', 'Attachment_Controller', 'action_index'),
			'unwatchtopic' => array('Notify.controller.php', 'Notify_Controller', 'action_unwatchtopic'),
			'editpoll' => array('Poll.controller.php', 'Poll_Controller', 'action_editpoll'),
			'editpoll2' => array('Poll.controller.php', 'Poll_Controller', 'action_editpoll2'),
			'findmember' => array('Members.controller.php', 'Members_Controller', 'action_findmember'),
			'quickhelp' => array('Help.controller.php', 'Help_Controller', 'action_quickhelp'),
			'jsmodify' => array('Post.controller.php', 'Post_Controller', 'action_jsmodify'),
			'jsoption' => array('ManageThemes.controller.php', 'ManageThemes_Controller', 'action_jsoption'),
			'loadeditorlocale' => array('subs/Editor.subs.php', 'action_loadlocale'),
			'lockvoting' => array('Poll.controller.php', 'Poll_Controller', 'action_lockvoting'),
			'login' => array('Auth.controller.php', 'Auth_Controller', 'action_login'),
			'login2' => array('Auth.controller.php', 'Auth_Controller', 'action_login2'),
			'logout' => array('Auth.controller.php', 'Auth_Controller', 'action_logout'),
			'markasread' => array('Markasread.controller.php', 'MarkRead_Controller', 'action_index'),
			'mergetopics' => array('MergeTopics.controller.php', 'MergeTopics_Controller', 'action_index'),
			'memberlist' => array('Memberlist.controller.php', 'Memberlist_Controller', 'action_index'),
			'moderate' => array('ModerationCenter.controller.php', 'ModerationCenter_Controller', 'action_index'),
			'karma' => array('Karma.controller.php', 'Karma_Controller', ''),
			'movetopic' => array('MoveTopic.controller.php', 'MoveTopic_Controller', 'action_movetopic'),
			'movetopic2' => array('MoveTopic.controller.php', 'MoveTopic_Controller', 'action_movetopic2'),
			'notify' => array('Notify.controller.php', 'Notify_Controller', 'action_notify'),
			'notifyboard' => array('Notify.controller.php', 'Notify_Controller', 'action_notifyboard'),
			'openidreturn' => array('OpenID.controller.php', 'OpenID_Controller', 'action_openidreturn'),
			'xrds' => array('OpenID.controller.php', 'OpenID_Controller', 'action_xrds'),
			'pm' => array('PersonalMessage.controller.php', 'PersonalMessage_Controller', 'action_index'),
// 			'post' => array('Post.controller.php', 'Post_Controller', 'action_post'),
			'post2' => array('Post.controller.php', 'Post_Controller', 'action_post2'),
			'profile' => array('Profile.controller.php', 'Profile_Controller', 'action_index'),
			'quotefast' => array('Post.controller.php', 'Post_Controller', 'action_quotefast'),
			'quickmod' => array('MessageIndex.controller.php', 'MessageIndex_Controller', 'action_quickmod'),
			'quickmod2' => array('Display.controller.php', 'Display_Controller', 'action_quickmod2'),
			'recent' => array('Recent.controller.php', 'Recent_Controller', 'action_recent'),
			'register' => array('Register.controller.php', 'Register_Controller', 'action_register'),
			'register2' => array('Register.controller.php', 'Register_Controller', 'action_register2'),
			'removepoll' => array('Poll.controller.php', 'Poll_Controller', 'action_removepoll'),
			'removetopic2' => array('RemoveTopic.controller.php', 'RemoveTopic_Controller', 'action_removetopic2'),
			'reporttm' => array('Emailuser.controller.php', 'Emailuser_Controller', 'action_reporttm'),
			'restoretopic' => array('RemoveTopic.controller.php', 'RemoveTopic_Controller', 'action_restoretopic'),
			'search' => array('Search.controller.php', 'Search_Controller', 'action_plushsearch1'),
			'search2' => array('Search.controller.php', 'Search_Controller', 'action_plushsearch2'),
			'suggest' => array('Suggest.controller.php', 'Suggest_Controller', 'action_suggest'),
			'spellcheck' => array('Post.controller.php', 'Post_Controller', 'action_spellcheck'),
			'splittopics' => array('SplitTopics.controller.php', 'SplitTopics_Controller', 'action_splittopics'),
			'stats' => array('Stats.controller.php', 'Stats_Controller', 'action_stats'),
			'theme' => array('ManageThemes.controller.php', 'ManageThemes_Controller', 'action_thememain'),
			'trackip' => array('ProfileHistory.controller.php', 'ProfileHistory_Controller', 'action_trackip'),
			'unread' => array('Recent.controller.php', 'Recent_Controller', 'action_unread'),
			'unreadreplies' => array('Recent.controller.php', 'Recent_Controller', 'action_unread'),
			'verificationcode' => array('Register.controller.php', 'Register_Controller', 'action_verificationcode'),
			'viewprofile' => array('Profile.controller.php', 'Profile_Controller', 'action_index'),
			'vote' => array('Poll.controller.php', 'Poll_Controller', 'action_vote'),
			'viewquery' => array('AdminDebug.controller.php', 'AdminDebug_Controller', 'action_viewquery'),
			'viewadminfile' => array('AdminDebug.controller.php', 'AdminDebug_Controller', 'action_viewadminfile'),
			'.xml' => array('News.controller.php', 'News_Controller', 'action_showfeed'),
			'xmlhttp' => array('Xml.controller.php', 'Xml_Controller', 'action_index'),
			'xmlpreview' => array('Xmlpreview.controller.php', 'XmlPreview_Controller', 'action_index'),
		);

		$adminActions = array('admin', 'jsoption', 'theme', 'viewadminfile', 'viewquery');

		// Allow to extend or change $actionArray through a hook
		call_integration_hook('integrate_actions', array(&$actionArray, &$adminActions));

		// Is it in core legacy actions?
		if (isset($actionArray[$_GET['action']]))
		{
			// Admin files have their own place
			$path = in_array($_GET['action'], $adminActions) ? ADMINDIR : CONTROLLERDIR;

			// Is it an object oriented controller?
			if (isset($actionArray[$_GET['action']][2]))
			{
				$this->_file_name = $path . '/' . $actionArray[$_GET['action']][0];
				$this->_controller_name = $actionArray[$_GET['action']][1];

				// If the method is coded in, use it
				if (!empty($actionArray[$_GET['action']][2]))
					$this->_function_name = $actionArray[$_GET['action']][2];
				// Otherwise fall back to naming patterns
				elseif (isset($_GET['sa']) && preg_match('~^\w+$~', $_GET['sa']))
					$this->_function_name = 'action_' . $_GET['sa'];
				else
					$this->_function_name = 'action_index';
			}
			// Then it's one of our legacy functions
			else
			{
				$this->_file_name = $path . '/' . $actionArray[$_GET['action']][0];
				$this->_function_name = $actionArray[$_GET['action']][1];
			}
		}
		// Fall back to naming patterns.
		// addons can use any of them, and it should Just Work (tm).
		elseif (preg_match('~^[a-zA-Z_\\-]+$~', $_GET['action']))
		{
			// action=drafts => Drafts.php
			// sa=save, sa=load, or sa=savepm => action_save(), action_load()
			// ... if it's not there yet, no problem.
			if (file_exists(CONTROLLERDIR . '/' . ucfirst($_GET['action']) . '.php'))
			{
				$this->_file_name = CONTROLLERDIR . '/' . ucfirst($_GET['action']) . '.php';

				// Procedural controller... we might need to pre dispatch to its main function
				// i.e. for action=mergetopics it was MergeTopics(), now it's mergetopics()
				$this->_pre_dispatch_func = 'pre_' . $_GET['action'];

				// Then, figure out the function for the subaction
				if (isset($_GET['sa']) && preg_match('~^\w+$~', $_GET['sa']))
					$this->_function_name = 'action_' . $_GET['sa'];
				else
					$this->_function_name = 'action_' . $_GET['action'];
			}
			// Or... an addon can do just this!
			// action=gallery => Gallery.controller.php
			// sa=upload => action_upload()
			elseif (file_exists(CONTROLLERDIR . '/' . ucfirst($_GET['action']) . '.controller.php'))
			{
				$this->_file_name = CONTROLLERDIR . '/' . ucfirst($_GET['action']) . '.controller.php';
				$this->_controller_name = ucfirst($_GET['action']) . '_Controller';
				if (isset($_GET['sa']) && preg_match('~^\w+$~', $_GET['sa']))
					$this->_function_name = 'action_' . $_GET['sa'];
				else
					$this->_function_name = 'action_index';
			}
		}

		// The file and function weren't found yet?
		if (empty($this->_file_name) || empty($this->_function_name))
		{
			// Catch the action with the theme?
			// @todo remove this?
			if (!empty($settings['catch_action']))
			{
				$this->_file_name = SUBSDIR . '/Themes.subs.php';
				$this->_function_name = 'WrapAction';
			}
			else
			{
				// We still haven't found what we're looking for...
				$this->_file_name = $default_action['file'];
				if (isset($default_action['controller']))
					$this->_controller_name = $default_action['controller'];
				$this->_function_name = $default_action['function'];
			}
		}

		if (isset($_REQUEST['api']))
			$this->_function_name .= '_api';
	}

	/**
	 * Relay control to the respective function or method.
	 */
	public function dispatch()
	{
		require_once($this->_file_name);

		if (!empty($this->_controller_name))
		{
			$controller = new $this->_controller_name();

			// Pre-dispatch (load templates and stuff)
			if (method_exists($controller, 'pre_dispatch'))
				$controller->pre_dispatch();

			$hook = substr($this->_function_name, -1) == 2 ? substr($this->_function_name, 0, -1) : $this->_function_name;
			call_integration_hook('integrate_' . $hook . '_before');

			// 3, 2, ... and go
			if (method_exists($controller, $this->_function_name))
				$controller->{$this->_function_name}();
			elseif (method_exists($controller, 'action_index'))
				$controller->action_index();
			// Fall back
			elseif (function_exists($this->_function_name))
			{
				call_user_func($this->_function_name);
			}
			else
			{
				// Things went pretty bad, huh?
				// board index :P
				require_once(CONTROLLERDIR . '/BoardIndex.controller.php');
				call_integration_hook('integrate_action_boardindex_before');
				$controller = new BoardIndex_Controller();
				$this->action_boardindex();
				call_integration_hook('integrate_action_boardindex_after');
			}
			call_integration_hook('integrate_' . $hook . '_after');
		}
		else
		{
			// pre-dispatch (load templates and stuff)
			// For procedural controllers, we know this name (instance var)
			if (!empty($this->_pre_dispatch_func) && function_exists($this->_pre_dispatch_func))
				call_user_func($this->_pre_dispatch_func);

			// It must be a good ole' function
			call_user_func($this->_function_name);
		}
	}
}