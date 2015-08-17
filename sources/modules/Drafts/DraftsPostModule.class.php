<?php

/**
 * Integration system for drafts into Post controller
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 dev
 *
 */

if (!defined('ELK'))
	die('No access...');

class Drafts_Post_Module implements ElkArte\sources\modules\Module_Interface
{
	protected static $_autosave_enabled = false;
	protected static $_autosave_frequency = 30000;
	protected static $_subject_length = 24;

	/**
	 * {@inheritdoc }
	 */
	public static function hooks(\Event_Manager $eventsManager)
	{
		global $modSettings;

		if (!empty($modSettings['drafts_enabled']) && !empty($modSettings['drafts_post_enabled']))
		{
			self::$_autosave_enabled = !empty($modSettings['drafts_autosave_enabled']);

			if (!empty($modSettings['drafts_autosave_frequency']))
				self::$_autosave_frequency = (int) $modSettings['drafts_autosave_frequency'] * 1000;

			if (!empty($modSettings['draft_subject_length']))
				self::$_subject_length = (int) $modSettings['draft_subject_length'];

			return array(
				array('prepare_modifying', array('Drafts_Post_Module', 'prepare_modifying'), array('really_previewing')),
				array('finalize_post_form', array('Drafts_Post_Module', 'finalize_post_form'), array('editorOptions', 'board', 'topic', 'template_layers')),

				array('prepare_save_post', array('Drafts_Post_Module', 'prepare_save_post'), array()),
				array('before_save_post', array('Drafts_Post_Module', 'before_save_post'), array()),
				array('after_save_post', array('Drafts_Post_Module', 'after_save_post'), array()),
			);
		}
		else
			return array();
	}

	public function prepare_modifying(&$really_previewing)
	{
		$really_previewing = $really_previewing && !isset($_REQUEST['save_draft']);
	}

	public function finalize_post_form(&$editorOptions, $board, $topic, $template_layers)
	{
		global $context, $user_info, $options, $txt;

		// Are post drafts enabled?
		$context['drafts_save'] = allowedTo('post_draft');
		$context['drafts_autosave'] = $context['drafts_save'] && self::$_autosave_enabled && allowedTo('post_autosave_draft');

		// Build a list of drafts that they can load into the editor
		if (!empty($context['drafts_save']))
		{
			loadLanguage('Drafts');
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

			$this->_prepareDraftsContext($user_info['id'], $topic);

			if (!empty($context['drafts']))
				$template_layers->add('load_drafts', 100);
		}
	}

	public function prepare_save_post()
	{
		// Drafts enabled and needed?
		if (isset($_POST['save_draft']) || isset($_POST['id_draft']))
			require_once(SUBSDIR . '/Drafts.subs.php');
	}

	public function before_save_post()
	{
		// If drafts are enabled, then pass this off
		if (isset($_POST['save_draft']))
		{
			saveDraft();
			throw new Controller_Redirect_Exception('Post_Controller', 'action_post');
		}
	}

	public function after_save_post()
	{
		global $user_info;

		// If we had a draft for this, its time to remove it since it was just posted
		if (!empty($_POST['id_draft']))
			deleteDrafts($_POST['id_draft'], $user_info['id']);
	}

	/**
	 * Loads in a group of post drafts for the user.
	 * Loads a specific draft for current use in the postbox if selected.
	 * Used in the posting screens to allow draft selection
	 * Will load a draft if selected is supplied via post
	 *
	 * @param int $member_id
	 * @param int|false $id_topic if set, load drafts for the specified topic
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
		loadLanguage('Drafts');
		require_once(SUBSDIR . '/Drafts.subs.php');

		// has a specific draft has been selected?  Load it up if there is not already a message already in the editor
		if (isset($_REQUEST['id_draft']) && empty($_POST['subject']) && empty($_POST['message']))
			loadDraft((int) $_REQUEST['id_draft'], 0, true, true);

		// load all the drafts for this user that meet the criteria
		$order = 'poster_time DESC';
		$user_drafts = load_user_drafts($member_id, 0, $id_topic, $order);

		// Add them to the context draft array for template display
		foreach ($user_drafts as $draft)
		{
			$short_subject = empty($draft['subject']) ? $txt['drafts_none'] : Util::shorten_text(stripslashes($draft['subject']), self::$_subject_length);
			$context['drafts'][] = array(
				'subject' => censorText($short_subject),
				'poster_time' => standardTime($draft['poster_time']),
				'link' => '<a href="' . $scripturl . '?action=post;board=' . $draft['id_board'] . ';' . (!empty($draft['id_topic']) ? 'topic='. $draft['id_topic'] .'.0;' : '') . 'id_draft=' . $draft['id_draft'] . '">' . (!empty($draft['subject']) ? $draft['subject'] : $txt['drafts_none']) . '</a>',
			);
		}
	}
}