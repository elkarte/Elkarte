<?php

/**
 * Integration system for drafts into Post controller
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

namespace ElkArte\Modules\Drafts;

use ElkArte\Errors\ErrorContext;
use ElkArte\EventManager;
use ElkArte\Exceptions\ControllerRedirectException;
use ElkArte\HttpReq;
use ElkArte\Modules\AbstractModule;
use ElkArte\Languages\Txt;
use ElkArte\Util;

/**
 * Class \ElkArte\Modules\Drafts\Post
 *
 * Events and functions for post based drafts
 */
class Post extends AbstractModule
{
	/** @var bool Autosave enabled */
	protected static $_autosave_enabled = false;

	/** @var bool Allowed to save drafts? */
	protected static $_drafts_save = false;

	/** @var int How often to autosave if enabled */
	protected static $_autosave_frequency = 30000;

	/** @var int Subject length that we can save */
	protected static $_subject_length = 32;

	/** @var \ElkArte\EventManager */
	protected static $_eventsManager;

	/** @var mixed Loading draft into the editor? */
	protected $_loading_draft = false;

	/**
	 * {@inheritdoc}
	 */
	public static function hooks(EventManager $eventsManager)
	{
		global $modSettings;

		$eventHooks = [];
		if (!empty($modSettings['drafts_enabled']) && !empty($modSettings['drafts_post_enabled']))
		{
			self::$_eventsManager = $eventsManager;

			self::$_autosave_enabled = !empty($modSettings['drafts_autosave_enabled']);

			if (!empty($modSettings['drafts_autosave_frequency']))
			{
				self::$_autosave_frequency = (int) $modSettings['drafts_autosave_frequency'] * 1000;
			}

			if (!empty($modSettings['draft_subject_length']))
			{
				self::$_subject_length = (int) $modSettings['draft_subject_length'];
			}

			self::$_drafts_save = allowedTo('post_draft');

			$eventHooks = [
				['prepare_modifying', ['\\ElkArte\\Modules\\Drafts\\Post', 'prepare_modifying'], ['really_previewing']],
				['finalize_post_form', ['\\ElkArte\\Modules\\Drafts\\Post', 'finalize_post_form'], ['editorOptions', 'board', 'topic', 'template_layers']],
				['prepare_save_post', ['\\ElkArte\\Modules\\Drafts\\Post', 'prepare_save_post'], []],
				['before_save_post', ['\\ElkArte\\Modules\\Drafts\\Post', 'before_save_post'], []],
				['after_save_post', ['\\ElkArte\\Modules\\Drafts\\Post', 'after_save_post'], ['msgOptions']],
			];
		}

		return $eventHooks;
	}

	/**
	 * Make sure we are doing a preview and not saving a draft
	 *
	 * @param bool $really_previewing
	 */
	public function prepare_modifying(&$really_previewing)
	{
		$really_previewing = $really_previewing && !isset($_REQUEST['save_draft']);
	}

	/**
	 * Get the post editor setup to work with drafts
	 *
	 * What it does:
	 *
	 * - Loads draft plugin to the editor options
	 * - Loads available drafts that can be loaded in to the editor
	 * - Updates the editor shortcut lines
	 * - Adds save draft button
	 *
	 * @param array $editorOptions
	 * @param int $board
	 * @param int $topic
	 * @param \ElkArte\Themes\TemplateLayers $template_layers
	 */
	public function finalize_post_form(&$editorOptions, $board, $topic, $template_layers)
	{
		global $context, $options, $txt;

		// Are post drafts enabled?
		$context['drafts_save'] = self::$_drafts_save;
		$context['drafts_autosave'] = self::$_drafts_save && self::$_autosave_enabled && allowedTo('post_autosave_draft');
		$context['hasDrafts'] = false;
		$context['drafts'] = [];

		// Build a list of drafts that they can load into the editor
		if (!empty(self::$_drafts_save))
		{
			Txt::load('Drafts');

			$haveDrafts = $this->_user_has_drafts($this->user->id, $topic);
			//$this->_prepareDraftsContext($this->user->id, $topic);

			if (!empty($this->_load_draft()))
			{
				$editorOptions['value'] = $context['message'];
			}

			// A little auto save action?
			if (!empty($context['drafts_autosave']) && !empty($options['drafts_autosave_enabled']))
			{
				$editorOptions['plugin_addons'] = $editorOptions['plugin_addons'] ?? [];
				$editorOptions['plugin_options'] = $editorOptions['plugin_options'] ?? [];

				// @todo remove
				$context['drafts_autosave_frequency'] = self::$_autosave_frequency;

				$editorOptions['plugin_addons'][] = 'draft';
				$editorOptions['plugin_options'][] = '
					draftOptions: {
						sLastNote: \'draft_lastautosave\',
						sSceditorID: \'' . $editorOptions['id'] . '\',
						sType: \'post\',
						iBoard: ' . (empty($board) ? 0 : $board) . ',
						iFreq: ' . self::$_autosave_frequency . ',
						sLastID: \'id_draft\',
						sTextareaID: \'' . $editorOptions['id'] . '\',
						id_draft: ' . (empty($context['id_draft']) ? 0 : $context['id_draft']) . '
					}';

				loadJavascriptFile('drafts.plugin.js', ['defer' => true]);
			}

			$context['shortcuts_text'] = $context['shortcuts_text'] ?? $txt['shortcuts_drafts'];

			// We may be first in line
			$editorOptions['buttons'] = $editorOptions['buttons'] ?? [];
			$editorOptions['hidden_fields'] = $editorOptions['hidden_fields'] ?? [];

			$editorOptions['buttons'][] = [
				'name' => 'save_draft',
				'value' => $txt['draft_save'],
				'options' => 'onclick="return confirm(' . JavaScriptEscape($txt['draft_save_note']) . ') && submitThisOnce(this);" accesskey="d"',
			];

			// Have drafts available, show a load button
			if ($haveDrafts)
			{
				$context['hasDrafts'] = $haveDrafts;
				$editorOptions['buttons'][] = [
					'name' => 'load_draft',
					'value' => $txt['draft_load'],
					'options' => 'onclick="return event.ctrlKey || loadDrafts();" accesskey="l"',
				];
			}

			$editorOptions['hidden_fields'][] = [
				'name' => 'id_draft',
				'value' => empty($context['id_draft']) ? 0 : $context['id_draft'],
			];

			if ($haveDrafts)
			{
				unset($context['minmax_preferences']['draft']);
				$template_layers->add('load_drafts', 100);
			}
		}
	}

	/**
	 * Checks if any drafts exist for this member/topic
	 *
	 * @param $member_id
	 * @param $id_topic
	 * @return int number of drafts found
	 */
	protected function _user_has_drafts($member_id, $id_topic)
	{
		if (empty($member_id))
		{
			return 0;
		}

		require_once(SUBSDIR . '/Drafts.subs.php');

		return count_user_drafts($member_id, 0, $id_topic);
	}

	/**
	 * If a draft has been selected, will use loadDraft function to fetch it into the editor
	 *
	 * @return bool true if a draft is laoded
	 */
	protected function _load_draft()
	{
		require_once(SUBSDIR . '/Drafts.subs.php');
		$req = HttpReq::instance();

		$subject = $req->getPost('subject', 'trim', '');
		$message = $req->getPost('message', 'trim', '');
		$id_draft = $req->getRequest('id_draft', 'intval', 0);

		// Has a specific draft has been selected?  Load it up if there is not
		// already a message already in the editor
		if (!empty($id_draft) && empty($subject) && empty($message))
		{
			$this->_loading_draft = loadDraft($id_draft, 0, true, true);

			return !empty($this->_loading_draft);
		}

		return false;
	}

	/**
	 * Loads in a group of post drafts for the user.
	 *
	 * What it does:
	 *
	 * - Loads a specific draft for current use in the editor if selected.
	 * - Used in the posting screens to allow draft selection
	 * - Will load a draft if selected is supplied via post
	 *
	 * @param int $member_id
	 * @param int|bool $id_topic if set, load drafts for the specified topic
	 * @return bool|null
	 */
	protected function _prepareDraftsContext($member_id, $id_topic = false)
	{
		global $scripturl, $context, $txt;

		// Need a member
		if (empty($member_id))
		{
			return false;
		}

		// We haz drafts?
		Txt::load('Drafts');
		require_once(SUBSDIR . '/Drafts.subs.php');

		// A draft has been selected?
		if ($this->_load_draft())
		{
			return true;
		}

		// load the recent drafts for this user that meet the topic criteria
		$order = 'poster_time DESC';
		$user_drafts = load_user_drafts($member_id, 0, $id_topic, $order, 10);

		if (empty($user_drafts))
		{
			return false;
		}

		// Add them to the context draft array for template display
		foreach ($user_drafts as $draft)
		{
			$short_subject = empty($draft['subject']) ? $txt['drafts_none'] : Util::shorten_text(stripslashes($draft['subject']), self::$_subject_length);
			$context['drafts'][] = [
				'subject' => censor($short_subject),
				'poster_time' => standardTime($draft['poster_time']),
				'link' => '<a href="' . $scripturl . '?action=post;board=' . $draft['id_board'] . ';' . (!empty($draft['id_topic']) ? 'topic=' . $draft['id_topic'] . '.0;' : '') . 'id_draft=' . $draft['id_draft'] . ';#post_subject">' . (!empty($draft['subject']) ? $draft['subject'] : $txt['drafts_none']) . '</a>',
			];
		}

		return true;
	}

	/**
	 * When the prepare_save_post event fires, checks if it was
	 * in response to a save or load draft event
	 */
	public function prepare_save_post()
	{
		// Drafts enabled and needed?
		if (isset($_POST['save_draft']) || isset($_POST['id_draft']) || isset($_POST['load_drafts']))
		{
			require_once(SUBSDIR . '/Drafts.subs.php');
		}
	}

	/**
	 * Call the appropriate draft action, save, load or nothing
	 */
	public function before_save_post()
	{
		// If drafts are enabled, then pass this off
		if (isset($_POST['save_draft']) && !empty(self::$_drafts_save))
		{
			$this->_save_draft();
		}
		elseif (isset($_POST['load_drafts']) && !empty(self::$_drafts_save))
		{
			$this->_load_drafts();
		}
	}

	/**
	 * Does the actual saving of a Draft to the DB
	 *
	 * @throws ControllerRedirectException
	 * @throws \ElkArte\Exceptions\Exception
	 */
	private function _save_draft()
	{
		global $context, $board;

		$req = HttpReq::instance();

		// Prepare and clean the data, load the draft array
		$icon = $req->getPost('icon', 'trim|strval', 'xx');
		$subject = $req->getPost('subject', '\\ElkArte\\Util::htmlspecialchars', '');
		$message = $req->getPost('message', 'trim', '');

		$draft = [
			'id_draft' => $req->getPost('id_draft', 'intval', 0),
			'topic_id' => $req->getRequest('topic', 'intval', 0),
			'board' => $board,
			'icon' => preg_replace('~[\./\\\\*:"\'<>]~', '', $icon),
			'smileys_enabled' => isset($_POST['ns']) ? 0 : 1,
			'locked' => isset($_POST['lock']) ? (int) $_POST['lock'] : 0,
			'sticky' => isset($_POST['sticky']) ? (int) $_POST['sticky'] : 0,
			'subject' => strtr($subject, ["\r" => '', "\n" => '', "\t" => '']),
			'body' => Util::htmlspecialchars($message, ENT_QUOTES, 'UTF-8', true),
			'id_member' => $this->user->id,
			'is_usersaved' => (int) empty($_REQUEST['autosave']),
		];

		self::$_eventsManager->trigger('before_save_draft', ['draft' => &$draft]);

		saveDraft($draft, isset($_REQUEST['xml']));

		// Cleanup
		unset($_POST['save_draft']);

		// Be ready for surprises
		$post_errors = ErrorContext::context('post', 1);

		// If we were called from the autosave function, send something back
		if (!empty($context['id_draft']) && $this->getApi() !== false && !$post_errors->hasError('session_timeout'))
		{
			theme()->getTemplates()->load('Xml');
			$context['sub_template'] = 'xml_draft';
			$context['draft_saved_on'] = time();
			obExit();
		}

		throw new ControllerRedirectException('\\ElkArte\\Controller\\Post', 'action_post');
	}

	/**
	 * Loads the drafts for the current topic/member for display
	 *
	 * @throws ControllerRedirectException
	 * @throws \ElkArte\Exceptions\Exception
	 */
	private function _load_drafts()
	{
		global $context;

		$req = HttpReq::instance();
		$post_errors = ErrorContext::context('post', 1);
		$topic = $req->getPost('topic', 'intval', 0);

		// Validate the request
		if (!empty($topic) && $this->getApi() !== false && !$post_errors->hasError('session_timeout'))
		{
			$this->_prepareDraftsContext($this->user->id, $topic);

			// Cleanup? post controller still super global centric
			unset($_POST['load_draft']);

			// Return the draft listing to the calling JS
			theme()->getTemplates()->load('Xml');
			$context['sub_template'] = 'xml_load_draft';
			obExit();
		}

		// Should not be here
		throw new ControllerRedirectException('\\ElkArte\\Controller\\Post', 'action_post');
	}

	/**
	 * Fired after the saving of a post, attempts to remove any drafts that are associated with it
	 */
	public function after_save_post()
	{
		$req = HttpReq::instance();
		$id_draft = $req->getPost('id_draft', 'intval', 0);

		// If we had a draft for this, it is time to remove it since it was just posted
		if (!empty($id_draft))
		{
			deleteDrafts($id_draft, $this->user->id);
		}
	}
}
