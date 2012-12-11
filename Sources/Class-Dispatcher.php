<?php

/**
 * @name      Dialogo Forum
 * @copyright Dialogo Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 */

if (!defined('DIALOGO'))
	die('Hacking attempt...');

/**
 * Dispatch the request to the function or method registered to handle it.
 * Try first the critical functionality (maintenance, no guest access)
 * Then, in order:
 *   forum's main actions: board index, message index, display topic
 *   the current/legacy file/functions registered by Dialogo core
 * Fall back to naming patterns:
 *   filename=[action].php function=[sa]
 *   filename=[action].controller.php method=action_[sa]
 *   filename=[action]-Controller.php method=action_[sa]
 *
 * An add-on files to handle custom actions will be called if they follow
 * any of these patterns.
 */
class site_Dispatcher
{
	// file name to load
	private $_file_name;
	// function or method to call
	private $_function_name;
	// class name, for object oriented controllers
	private $_controller_name;
	// name of pre_dispatch function, for procedural controllers
	private $_pre_dispatch_func;

	/**
	 * Create an instance and initialize it.
	 * This does all the work to figure out which file and function/method needs called.
	 */
	public function __construct()
	{
		global $board, $topic, $sourcedir, $modSettings, $settings, $user_info, $maintenance;

		// default action of the forum: board index
		// everytime we don't know what to do, we'll do this :P
		$default_action = array(
			'file' => $sourcedir . '/BoardIndex.php',
			'function' => 'BoardIndex'
		);

		// Maintenance mode: you're out of here unless you're admin
		if (!empty($maintenance) && !allowedTo('admin_forum'))
		{
			// You can only login
			if (isset($_GET['action']) && ($_GET['action'] == 'login2' || $_GET['action'] == 'logout'))
			{
				$this->_file_name = $sourcedir . '/LogInOut.php';
				$this->_function_name = $_GET['action'] == 'login2' ? 'Login2' : 'Logout';
			}
			// "maintenance mode" page
			else
			{
				$this->_file_name = $sourcedir . '/Subs-Auth.php';
				$this->_function_name = 'InMaintenance';
			}
		}
		// If guest access is disallowed, a guest is kicked out... politely. :P
		elseif (empty($modSettings['allow_guestAccess']) && $user_info['is_guest'] && (!isset($_GET['action']) || !in_array($_GET['action'], array('coppa', 'login', 'login2', 'register', 'register2', 'reminder', 'activate', 'help', 'mailq', 'verificationcode', 'openidreturn'))))
		{
			$this->_file_name = $sourcedir . '/Subs-Auth.php';
			$this->_function_name = 'KickGuest';
		}
		elseif (empty($_GET['action']))
		{
			// home page: board index
			if (empty($board) && empty($topic))
			{
				// @todo Unless we have a custom home page registered...

				// was it, wasn't it....
				if (empty($this->_function_name))
				{
					$this->_file_name = $default_action['file'];
					$this->_function_name = $default_action['function'];
				}
			}
			// ?board=b message index
			elseif (empty($topic))
			{
				$this->_file_name = $sourcedir . '/MessageIndex.php';
				$this->_function_name = 'MessageIndex';
			}
			// board=b;topic=t topic display
			else
			{
				$this->_file_name = $sourcedir . '/Display.php';
				$this->_function_name = 'Display';
			}
		}

		// now this return won't be cool, but lets do it
		if (!empty($this->_file_name) && !empty($this->_function_name))
			return;

		// Start with our nice and cozy err... *cough*
		// $_GET['action'] => array($file, $function).
		$actionArray = array(
			'activate' => array('Register.php', 'Activate'),
			'admin' => array('Admin.php', 'AdminMain'),
			'announce' => array('Post.php', 'AnnounceTopic'),
			'attachapprove' => array('ManageAttachments.php', 'ApproveAttach'),
			'buddy' => array('Subs-Members.php', 'BuddyListToggle'),
			'calendar' => array('Calendar.php', 'CalendarMain'),
			'collapse' => array('BoardIndex.php', 'CollapseCategory'),
			'contact' => array('Register.php', 'ContactForm'),
			'coppa' => array('Register.php', 'CoppaForm'),
			'credits' => array('Who.php', 'Credits'),
			'deletemsg' => array('RemoveTopic.php', 'DeleteMessage'),
			'dlattach' => array('Attachment.php', 'Download'),
			'disregardtopic' => array('Notify.php', 'TopicDisregard'),
			'editpoll' => array('Poll.php', 'EditPoll'),
			'editpoll2' => array('Poll.php', 'EditPoll2'),
			'emailuser' => array('SendTopic.php', 'EmailUser'),
			'findmember' => array('Subs-Auth.php', 'JSMembers'),
			'groups' => array('Groups.php', 'Groups'),
			'help' => array('Help.php', 'ShowHelp'),
			'helpadmin' => array('Help.php', 'ShowAdminHelp'),
			'jsmodify' => array('Post.php', 'JavaScriptModify'),
			'jsoption' => array('Themes.php', 'SetJavaScript'),
			'loadeditorlocale' => array('Subs-Editor.php', 'loadLocale'),
			'lock' => array('Topic.php', 'LockTopic'),
			'lockvoting' => array('Poll.php', 'LockVoting'),
			'login' => array('LogInOut.php', 'Login'),
			'login2' => array('LogInOut.php', 'Login2'),
			'logout' => array('LogInOut.php', 'Logout'),
			'markasread' => array('Subs-Boards.php', 'MarkRead'),
			'mergetopics' => array('SplitTopics.php', 'MergeTopics'),
			'mlist' => array('Memberlist.php', 'Memberlist'),
			'moderate' => array('ModerationCenter.php', 'ModerationMain'),
			'modifykarma' => array('Karma.php', 'ModifyKarma'),
			'movetopic' => array('MoveTopic.php', 'MoveTopic'),
			'movetopic2' => array('MoveTopic.php', 'MoveTopic2'),
			'notify' => array('Notify.php', 'Notify'),
			'notifyboard' => array('Notify.php', 'BoardNotify'),
			'openidreturn' => array('Subs-OpenID.php', 'OpenIDReturn'),
			'pm' => array('PersonalMessage.php', 'MessageMain'),
			'post' => array('Post.php', 'Post'),
			'post2' => array('Post.php', 'Post2'),
			'printpage' => array('Printpage.php', 'PrintTopic'),
			'profile' => array('Profile.php', 'ModifyProfile'),
			'quotefast' => array('Post.php', 'QuoteFast'),
			'quickmod' => array('MessageIndex.php', 'QuickModeration'),
			'quickmod2' => array('Display.php', 'QuickInTopicModeration'),
			'recent' => array('Recent.php', 'RecentPosts'),
			'register' => array('Register.php', 'Register'),
			'register2' => array('Register.php', 'Register2'),
			'reminder' => array('Reminder.php', 'RemindMe'),
			'removepoll' => array('Poll.php', 'RemovePoll'),
			'removetopic2' => array('RemoveTopic.php', 'RemoveTopic2'),
			'reporttm' => array('SendTopic.php', 'ReportToModerator'),
			'requestmembers' => array('Subs-Auth.php', 'RequestMembers'),
			'restoretopic' => array('RemoveTopic.php', 'RestoreTopic'),
			'search' => array('Search.php', 'PlushSearch1'),
			'search2' => array('Search.php', 'PlushSearch2'),
			'sendtopic' => array('SendTopic.php', 'EmailUser'),
			'suggest' => array('Subs-Editor.php', 'AutoSuggestHandler'),
			'spellcheck' => array('Subs-Post.php', 'SpellCheck'),
			'splittopics' => array('SplitTopics.php', 'SplitTopics'),
			'stats' => array('Stats.php', 'DisplayStats'),
			'sticky' => array('Topic.php', 'Sticky'),
			'theme' => array('Themes.php', 'ThemesMain'),
			'trackip' => array('Profile-View.php', 'trackIP'),
			'unread' => array('Recent.php', 'UnreadTopics'),
			'unreadreplies' => array('Recent.php', 'UnreadTopics'),
			'verificationcode' => array('Register.php', 'VerificationCode'),
			'viewprofile' => array('Profile.php', 'ModifyProfile'),
			'vote' => array('Poll.php', 'Vote'),
			'viewquery' => array('ViewQuery.php', 'ViewQuery'),
			'viewsmfile' => array('Admin.php', 'DisplayAdminFile'),
			'who' => array('Who.php', 'Who'),
			'.xml' => array('News.php', 'ShowXmlFeed'),
			'xmlhttp' => array('Xml.php', 'XMLhttpMain'),
		);

		// allow to extend or change $actionArray through a hook
		call_integration_hook('integrate_actions', array(&$actionArray));

		// Is it in core legacy actions?
		if (isset($actionArray[$_GET['action']]))
		{
			$this->_file_name = $sourcedir . '/' . $actionArray[$_GET['action']][0];
			$this->_function_name = $actionArray[$_GET['action']][1];
		}
		// fall back to naming patterns.
		// add-ons can use any of them, and it should Just Work (tm).
		elseif (preg_match('~^[a-zA-Z_\\-]+$~', $_GET['action']))
		{
			// i.e. action=help => Help.php...
			// if the function name fits the pattern, that'd be 'index'...
			if (file_exists($sourcedir . '/' . ucfirst($_GET['action']) . '.php'))
			{
				$this->_file_name = $sourcedir . '/' . ucfirst($_GET['action']) . '.php';

				// procedural controller... we might need to pre dispatch to its main function
				// i.e. for action=mergetopics it was MergeTopics(), now it's mergetopics()
				$this->_pre_dispatch_func = 'pre_' . $_GET['action'];

				// then, figure out the function for the subaction
				if (isset($_GET['sa']) && preg_match('~^\w+$~', $_GET['sa']))
					$this->_function_name = 'action_' . $_GET['sa'];
				else
					$this->_function_name = 'action_' . $_GET['action'];
			}
			// action=drafts => Drafts.controller.php
			// sa=save, sa=load, or sa=savepm => action_save(), action_load()
			// ... if it ain't there yet, no problem.
			elseif (file_exists($sourcedir . '/' . ucfirst($_GET['action']) . '.controller.php'))
			{
				$this->_file_name = $sourcedir . '/' . ucfirst($_GET['action'] . '.controller.php');
				$this->_controller_name = ucfirst($_GET['action']) . '_Controller';
				if (isset($_GET['sa']) && preg_match('~^\w+$~', $_GET['sa']))
					$this->_function_name = 'action_' . $_GET['sa'];
				else
					$this->_function_name = 'action_index';
			}
			// or... an add-on can do just this!
			// action=gallery => Gallery-Controller.php
			// sa=upload => action_upload()
			elseif (file_exists($sourcedir . '/' . ucfirst($_GET['action']) . '-Controller.php'))
			{
				$this->_file_name = $sourcedir . '/' . ucfirst($_GET['action']) . '-Controller.php';
				$this->_controller_name = ucfirst($_GET['action']) . '_Controller';
				if (isset($_GET['sa']) && preg_match('~^\w+$~', $_GET['sa']))
					$this->_function_name = 'action_' . $_GET['sa'];
				else
					$this->_function_name = 'action_index';
			}
		}

		// the file and function weren't found yet?
		if (empty($this->_file_name) || empty($this->_function_name))
		{
			// Catch the action with the theme?
			// @todo remove this?
			if (!empty($settings['catch_action']))
			{
				$this->_file_name = $sourcedir . '/Themes.php';
				$this->_function_name = 'WrapAction';
			}
			else
			{
				// we still haven't found what we're looking for...
				$this->_file_name = $default_action['file'];
				$this->_function_name = $default_action['function'];
			}
		}
	}

	/**
	 * Relay control to the respective function or method.
	 */
	public function dispatch()
	{
		require_once ($this->_file_name);

		if (!empty($this->_controller_name))
		{
			$controller = new $this->_controller_name();

			// pre-dispatch (load templates and stuff)
			if (method_exists($controller, 'pre_dispatch'))
				$controller->pre_dispatch();

			if (method_exists($controller, $this->_function_name))
				$controller->{$this->_function_name}();
			elseif (method_exists($this->controller, 'index'))
				$controller->index();
			// fall back
			else
			{
				// things went pretty bad, huh?
				// board index :P
				require_once($sourcedir . '/BoardIndex.php');
				return 'BoardIndex';
			}
		}
		else
		{
			// pre-dispatch (load templates and stuff)
			// for procedural controllers, we know this name (instance var)
			if (!empty($this->_pre_dispatch_func) && function_exists($this->_pre_dispatch_func))
				call_user_func($this->_pre_dispatch_func);

			// it must be a good ole' function
			call_user_func($this->_function_name);
		}
	}
}
