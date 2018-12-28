<?php

/**
 * Integration system for drafts into Post controller
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Modules\Drafts;

use ElkArte\Errors\ErrorContext;
use ElkArte\Exceptions\ControllerRedirectException;

/**
 * Class \ElkArte\Modules\Drafts\Post
 *
 * Events and functions for post based drafts
 */
class Post extends \ElkArte\Modules\AbstractModule
{
	/**
	 * Autosave enabled
	 * @var bool
	 */
	protected static $_autosave_enabled = false;

	/**
	 * Allowed to save drafts?
	 * @var bool
	 */
	protected static $_drafts_save = false;

	/**
	 * How often to autosave if enabled
	 * @var int
	 */
	protected static $_autosave_frequency = 30000;

	/**
	 * Subject length that we can save
	 * @var int
	 */
	protected static $_subject_length = 24;

	/**
	 * @var \ElkArte\EventManager
	 */
	protected static $_eventsManager = null;

	/**
	 * Loading draft into the editor?
	 * @var mixed
	 */
	protected $_loading_draft = false;

	/**
	 * {@inheritdoc }
	 */
	public static function hooks(\ElkArte\EventManager $eventsManager)
	{
		global $modSettings;

		$return = array();
		if (!empty($modSettings['drafts_enabled']) && !empty($modSettings['drafts_post_enabled']))
		{
			self::$_eventsManager = $eventsManager;

			self::$_autosave_enabled = !empty($modSettings['drafts_autosave_enabled']);

			if (!empty($modSettings['drafts_autosave_frequency']))
				self::$_autosave_frequency = (int) $modSettings['drafts_autosave_frequency'] * 1000;

			if (!empty($modSettings['draft_subject_length']))
				self::$_subject_length = (int) $modSettings['draft_subject_length'];

			self::$_drafts_save = allowedTo('post_draft');

			$return = array(
				array('prepare_modifying', array('\\ElkArte\\Modules\\Drafts\\Post', 'prepare_modifying'), array('really_previewing')),
				array('finalize_post_form', array('\\ElkArte\\Modules\\Drafts\\Post', 'finalize_post_form'), array('editorOptions', 'board', 'topic', 'template_layers')),

				array('prepare_save_post', array('\\ElkArte\\Modules\\Drafts\\Post', 'prepare_save_post'), array()),
				array('before_save_post', array('\\ElkArte\\Modules\\Drafts\\Post', 'before_save_post'), array()),
				array('after_save_post', array('\\ElkArte\\Modules\\Drafts\\Post', 'after_save_post'), array('msgOptions')),
			);
		}

		return $return;
	}

	/**
	 * Make sure its a preview and not saving a draft
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
	 * @param ElkArte\Theme\TemplateLayers $template_layers
	 */
	public function finalize_post_form(&$editorOptions, $board, $topic, $template_layers)
	{
		global $context, $user_info, $options, $txt;

		// Are post drafts enabled?
		$context['drafts_save'] = self::$_drafts_save;
		$context['drafts_autosave'] = self::$_drafts_save && self::$_autosave_enabled && allowedTo('post_autosave_draft');

		// Build a list of drafts that they can load into the editor
		if (!empty(self::$_drafts_save))
		{
			theme()->getTemplates()->loadLanguageFile('Drafts');

			$this->_prepareDraftsContext($user_info['id'], $topic);

			if (!empty($this->_loading_draft))
			{
				$editorOptions['value'] = $context['message'];
			}

			if (!empty($context['drafts_autosave']) && !empty($options['drafts_autosave_enabled']))
			{
				if (!isset($editorOptions['plugin_addons']))
					$editorOptions['plugin_addons'] = array();
				if (!isset($editorOptions['plugin_options']))
					$editorOptions['plugin_options'] = array();

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

				loadJavascriptFile('drafts.plugin.js', array('defer' => true));
			}
			$context['shortcuts_text'] = $txt['shortcuts_drafts' . (isBrowser('is_firefox') ? '_firefox' : '')];

			if (!isset($editorOptions['buttons']))
				$editorOptions['buttons'] = array();

			$editorOptions['buttons'][] = array(
				'name' => 'save_draft',
				'value' => $txt['draft_save'],
				'options' => 'onclick="return confirm(' . JavaScriptEscape($txt['draft_save_note']) . ') && submitThisOnce(this);" accesskey="d"',
			);

			if (!empty($context['drafts']))
				$template_layers->add('load_drafts', 100);
		}
	}

	/**
	 * When the prepare_save_post event fires, checks if it was
	 * in response to a save draft event
	 */
	public function prepare_save_post()
	{
		// Drafts enabled and needed?
		if (isset($_POST['save_draft']) || isset($_POST['id_draft']))
			require_once(SUBSDIR . '/Drafts.subs.php');
	}

	/**
	 * Does the actual saving of a Draft to the DB
	 *
	 * @throws ControllerRedirectException
	 * @throws \ElkArte\Exceptions\Exception
	 */
	public function before_save_post()
	{
		global $context, $board, $user_info;

		// If drafts are enabled, then pass this off
		if (isset($_POST['save_draft']))
		{
			// Can you be, should you be ... here?
			if (!empty(self::$_drafts_save))
			{
				// Prepare and clean the data, load the draft array
				$draft = array(
					'id_draft' => empty($_POST['id_draft']) ? 0 : (int) $_POST['id_draft'],
					'topic_id' => empty($_REQUEST['topic']) ? 0 : (int) $_REQUEST['topic'],
					'board' => $board,
					'icon' => empty($_POST['icon']) ? 'xx' : preg_replace('~[\./\\\\*:"\'<>]~', '', $_POST['icon']),
					'smileys_enabled' => isset($_POST['ns']) ? 0 : 1,
					'locked' => isset($_POST['lock']) ? (int) $_POST['lock'] : 0,
					'sticky' => isset($_POST['sticky']) ? (int) $_POST['sticky'] : 0,
					'subject' => strtr(\ElkArte\Util::htmlspecialchars($_POST['subject']), array("\r" => '', "\n" => '', "\t" => '')),
					'body' => \ElkArte\Util::htmlspecialchars($_POST['message'], ENT_QUOTES, 'UTF-8', true),
					'id_member' => $user_info['id'],
					'is_usersaved' => (int) empty($_REQUEST['autosave']),
				);

				self::$_eventsManager->trigger('before_save_draft', array('draft' => &$draft));

				saveDraft($draft, isset($_REQUEST['xml']));

				// Cleanup
				unset($_POST['save_draft']);

				// Be ready for surprises
				$post_errors = ErrorContext::context('post', 1);

				// If we were called from the autosave function, send something back
				if (!empty($context['id_draft']) && isset($_REQUEST['xml']) && !$post_errors->hasError('session_timeout'))
				{
					theme()->getTemplates()->load('Xml');
					$context['sub_template'] = 'xml_draft';
					$context['draft_saved_on'] = time();
					obExit();
				}

				throw new ControllerRedirectException('\\ElkArte\\Controller\\Post', 'action_post');
			}
		}
	}

	/**
	 * Fired after the saving of a post, attempts to remove any drafts that are associated with it
	 */
	public function after_save_post()
	{
		global $user_info;

		// If we had a draft for this, its time to remove it since it was just posted
		if (!empty($_POST['id_draft']))
			deleteDrafts($_POST['id_draft'], $user_info['id']);
	}

	/**
	 * Loads in a group of post drafts for the user.
	 *
	 * What it does:
	 *
	 * - Loads a specific draft for current use in the postbox if selected.
	 * - Used in the posting screens to allow draft selection
	 * - Will load a draft if selected is supplied via post
	 *
	 * @param int $member_id
	 * @param int|bool $id_topic if set, load drafts for the specified topic
	 * @return false|null
	 */
	protected function _prepareDraftsContext($member_id, $id_topic = false)
	{
		global $scripturl, $context, $txt;

		$context['drafts'] = array();

		// Need a member
		if (empty($member_id))
			return false;

		// We haz drafts
		theme()->getTemplates()->loadLanguageFile('Drafts');
		require_once(SUBSDIR . '/Drafts.subs.php');

		// has a specific draft has been selected?  Load it up if there is not already a message already in the editor
		if (isset($_REQUEST['id_draft']) && empty($_POST['subject']) && empty($_POST['message']))
			$this->_loading_draft = loadDraft((int) $_REQUEST['id_draft'], 0, true, true);

		// load all the drafts for this user that meet the criteria
		$order = 'poster_time DESC';
		$user_drafts = load_user_drafts($member_id, 0, $id_topic, $order);

		// Add them to the context draft array for template display
		foreach ($user_drafts as $draft)
		{
			$short_subject = empty($draft['subject']) ? $txt['drafts_none'] : \ElkArte\Util::shorten_text(stripslashes($draft['subject']), self::$_subject_length);
			$context['drafts'][] = array(
				'subject' => censor($short_subject),
				'poster_time' => standardTime($draft['poster_time']),
				'link' => '<a href="' . $scripturl . '?action=post;board=' . $draft['id_board'] . ';' . (!empty($draft['id_topic']) ? 'topic=' . $draft['id_topic'] . '.0;' : '') . 'id_draft=' . $draft['id_draft'] . '">' . (!empty($draft['subject']) ? $draft['subject'] : $txt['drafts_none']) . '</a>',
			);
		}

		return true;
	}
}
