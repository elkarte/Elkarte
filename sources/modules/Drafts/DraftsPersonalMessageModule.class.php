<?php

/**
 * Integration system for drafts into PersonalMessage controller
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

class Drafts_PersonalMessage_Module implements ElkArte\sources\modules\Module_Interface
{
	protected static $_autosave_enabled = false;
	protected static $_autosave_frequency = 30000;
	protected static $_subject_length = 24;

	/**
	 * {@inheritdoc }
	 */
	public static function hooks(\Event_Manager $eventsManager)
	{
		global $modSettings, $context;

		loadLanguage('Drafts');
		require_once(SUBSDIR . '/Drafts.subs.php');

		// Are PM drafts enabled?
		$context['drafts_pm_save'] = !empty($modSettings['drafts_enabled']) && !empty($modSettings['drafts_pm_enabled']) && allowedTo('pm_draft');
		$context['drafts_autosave'] = !empty($context['drafts_pm_save']) && !empty($modSettings['drafts_autosave_enabled']) && allowedTo('pm_autosave_draft');

		if (!empty($modSettings['drafts_enabled']) && !empty($modSettings['drafts_post_enabled']))
		{
			self::$_autosave_enabled = !empty($modSettings['drafts_autosave_enabled']);

			add_integration_function('integrate_sa_pm_index', 'Drafts_PersonalMessage_Module::integrate_sa_pm_index', '', false);
			add_integration_function('integrate_pm_areas', 'Drafts_PersonalMessage_Module::integrate_pm_areas', '', false);

			if (!empty($modSettings['drafts_autosave_frequency']))
				self::$_autosave_frequency = (int) $modSettings['drafts_autosave_frequency'] * 1000;

			if (!empty($modSettings['draft_subject_length']))
				self::$_subject_length = (int) $modSettings['draft_subject_length'];

			return array(
				array('prepare_send_context', array('Drafts_PersonalMessage_Module', 'prepare_send_context'), array('pmsg', 'editorOptions')),
				array('before_sending', array('Drafts_PersonalMessage_Module', 'before_sending'), array('recipientList')),
			);
		}
		else
			return array();
	}

	public static function integrate_pm_areas(&$pm_areas)
	{
		global $scripturl, $txt;

		$pm_areas['folders']['areas'] = elk_array_insert($pm_areas['folders']['areas'], 'sent', array(
			'drafts' => array(
				'label' => $txt['drafts_show'],
				'custom_url' => $scripturl . '?action=pm;sa=showpmdrafts',
				'permission' => 'pm_draft',
				'enabled' => true,
			)), 'after');
	}

	public static function integrate_sa_pm_index(&$subActions)
	{
		$subActions['showpmdrafts'] = array('controller' => 'Draft_Controller', 'function' => 'action_showPMDrafts', 'permission' => 'pm_read');
	}

	public function prepare_send_context(&$pmsg, &$editorOptions)
	{
		global $context, $user_info, $options, $txt;

		// If drafts are enabled, lets generate a list of drafts that they can load in to the editor
		if (!empty($context['drafts_pm_save']))
		{
			$this->_prepareDraftsContext($user_info['id'], $pmsg);
		}

		if (!empty($context['drafts_pm_save']) && !empty($options['drafts_autosave_enabled']))
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
					iBoard: 0,
					iFreq: ' . self::$_autosave_frequency . ',
					sLastID: \'id_pm_draft\',
					sTextareaID: \'' . $editorOptions['id'] . '\',
					bPM: true
				}';

			loadJavascriptFile('drafts.plugin.js', array('defer' => true));
			loadLanguage('Post');

			// Our not so concise shortcut line
			if (!isset($context['shortcuts_text']))
				$context['shortcuts_text'] = $txt['shortcuts_drafts' . (isBrowser('is_firefox') ? '_firefox' : '')];

			if (!isset($editorOptions['buttons']))
				$editorOptions['buttons'] = array();
			if (!isset($editorOptions['hidden_fields']))
				$editorOptions['hidden_fields'] = array();

			$editorOptions['buttons'][] = array(
				'name' => 'save_draft',
				'value' => $txt['draft_save'],
				'options' => 'onclick="submitThisOnce(this);" accesskey="d"',
			);
			$editorOptions['hidden_fields'][] = array(
				'name' => 'id_pm_draft',
				'value' => empty($context['id_pm_draft']) ? 0 : $context['id_pm_draft'],
			);
		}
	}

	public function before_sending($recipientList)
	{
		global $context, $user_info;

		// Want to save this as a draft and think about it some more?
		if ($context['drafts_pm_save'] && isset($_POST['save_draft']))
		{
			// Ajax calling
			if (!isset($context['drafts_pm_save']))
				$context['drafts_pm_save'] = !empty($modSettings['drafts_enabled']) && !empty($modSettings['drafts_pm_enabled']) && allowedTo('pm_draft');

			// PM survey says ... can you stay or must you go
			if (!empty($context['drafts_pm_save']) && isset($_POST['id_pm_draft']))
			{
				// Prepare the data
				$draft = array(
					'id_pm_draft' => empty($_POST['id_pm_draft']) ? 0 : (int) $_POST['id_pm_draft'],
					'reply_id' => empty($_POST['replied_to']) ? 0 : (int) $_POST['replied_to'],
					'body' => Util::htmlspecialchars($_POST['message'], ENT_QUOTES, 'UTF-8', true),
					'subject' => strtr(Util::htmlspecialchars($_POST['subject']), array("\r" => '', "\n" => '', "\t" => '')),
					'id_member' => $user_info['id'],
					'is_usersaved' => 1,
				);

				if (isset($_REQUEST['xml']))
				{
					$recipientList['to'] = isset($_POST['recipient_to']) ? explode(',', $_POST['recipient_to']) : array();
					$recipientList['bcc'] = isset($_POST['recipient_bcc']) ? explode(',', $_POST['recipient_bcc']) : array();
				}

				savePMDraft($recipientList, $draft, isset($_REQUEST['xml']));
				throw new Controller_Redirect_Exception('', '');
			}
		}
	}

	public function message_sent($failed)
	{
		global $context, $user_info;

		// If we had a PM draft for this one, then its time to remove it since it was just sent
		if (!$failed && $context['drafts_pm_save'] && !empty($_POST['id_pm_draft']))
			deleteDrafts($_POST['id_pm_draft'], $user_info['id']);
	}

	/**
	 * Loads in a group of PM drafts for the user.
	 *
	 * What it does:
	 * - Loads a specific draft for current use in pm editing box if selected.
	 * - Used in the posting screens to allow draft selection
	 * - Will load a draft if selected is supplied via post
	 *
	 * @param int $member_id
	 * @param int|false $id_pm = false if set, it will try to load drafts for this id
	 * @return false|null
	 */
	protected function _prepareDraftsContext($member_id, $id_pm = false)
	{
		global $scripturl, $context, $txt;

		$context['drafts'] = array();

		// Need a member
		if (empty($member_id))
			return false;

		// We haz drafts
		loadLanguage('Drafts');
		require_once(SUBSDIR . '/Drafts.subs.php');

		// Has a specific draft has been selected?  Load it up if there is not already a message already in the editor
		if (isset($_REQUEST['id_draft']) && empty($_POST['subject']) && empty($_POST['message']))
			loadDraft((int) $_REQUEST['id_draft'], 1, true, true);

		// Load all the drafts for this user that meet the criteria
		$order = 'poster_time DESC';
		$user_drafts = load_user_drafts($member_id, 1, $id_pm, $order);

		// Add them to the context draft array for template display
		foreach ($user_drafts as $draft)
		{
			$short_subject = empty($draft['subject']) ? $txt['drafts_none'] : Util::shorten_text(stripslashes($draft['subject']), self::$_subject_length);
			$context['drafts'][] = array(
				'subject' => censorText($short_subject),
				'poster_time' => standardTime($draft['poster_time']),
					'link' => '<a href="' . $scripturl . '?action=pm;sa=send;id_draft=' . $draft['id_draft'] . '">' . (!empty($draft['subject']) ? $draft['subject'] : $txt['drafts_none']) . '</a>',
				);
		}
	}
}