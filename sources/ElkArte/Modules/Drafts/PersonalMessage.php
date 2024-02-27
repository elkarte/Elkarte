<?php

/**
 * Integration system for drafts into PersonalMessage controller
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

use ElkArte\Controller\Draft;
use ElkArte\EventManager;
use ElkArte\Exceptions\ControllerRedirectException;
use ElkArte\Exceptions\PmErrorException;
use ElkArte\Languages\Txt;
use ElkArte\Modules\AbstractModule;
use ElkArte\Util;
use ElkArte\ValuesContainer;

/**
 * Class \ElkArte\Modules\Drafts\PersonalMessage
 *
 * Prepares the draft functions for the personal message page
 */
class PersonalMessage extends AbstractModule
{
	/** @var bool Autosave enabled */
	protected static $_autosave_enabled = false;

	/** @var int How often to autosave if enabled */
	protected static $_autosave_frequency = 30000;

	/** @var int Subject length */
	protected static $_subject_length = 32;

	/** @var EventManager */
	protected static $_eventsManager;

	/** @var ValuesContainer */
	protected $_loaded_draft;

	/**
	 * Add PM draft hooks and events to the system
	 *
	 * {@inheritDoc}
	 */
	public static function hooks(EventManager $eventsManager)
	{
		global $modSettings, $context;

		Txt::load('Drafts');
		require_once(SUBSDIR . '/Drafts.subs.php');

		// Are PM drafts enabled?
		$context['drafts_pm_save'] = !empty($modSettings['drafts_enabled']) && !empty($modSettings['drafts_pm_enabled']) && allowedTo('pm_draft');
		$context['drafts_autosave'] = !empty($context['drafts_pm_save']) && !empty($modSettings['drafts_autosave_enabled']) && allowedTo('pm_autosave_draft');

		if (!empty($modSettings['drafts_enabled']) && !empty($modSettings['drafts_pm_enabled']))
		{
			self::$_eventsManager = $eventsManager;
			self::$_autosave_enabled = !empty($modSettings['drafts_autosave_enabled']);

			add_integration_function('integrate_sa_pm_index', '\\ElkArte\\Modules\\Drafts\\PersonalMessage::integrate_sa_pm_index', '', false);
			add_integration_function('integrate_pm_areas', '\\ElkArte\\Modules\\Drafts\\PersonalMessage::integrate_pm_areas', '', false);

			if (!empty($modSettings['drafts_autosave_frequency']))
			{
				self::$_autosave_frequency = (int) $modSettings['drafts_autosave_frequency'] * 1000;
			}

			if (!empty($modSettings['draft_subject_length']))
			{
				self::$_subject_length = (int) $modSettings['draft_subject_length'];
			}

			// Events
			return [
				['before_set_context', [PersonalMessage::class, 'before_set_context'], ['pmsg']],
				['prepare_send_context', [PersonalMessage::class, 'prepare_send_context'], ['editorOptions']],
				['before_sending', [PersonalMessage::class, 'before_sending'], ['recipientList']],
				['message_sent', [PersonalMessage::class, 'message_sent'], ['failed']],
			];
		}
		return [];
	}

	/**
	 * Insert the show drafts button in the PM menu
	 *
	 * @param array $pm_areas
	 */
	public static function integrate_pm_areas(&$pm_areas)
	{
		global $scripturl, $txt;

		$pm_areas['folders']['areas'] = elk_array_insert($pm_areas['folders']['areas'], 'sent', [
			'showpmdrafts' => [
				'label' => $txt['drafts_show'],
				'custom_url' => $scripturl . '?action=pm;sa=showpmdrafts',
				'permission' => 'pm_draft',
				'enabled' => true,
			]], 'after');
	}

	/**
	 * Add the draft controller show drafts to the available subactions
	 *
	 * @param array $subActions
	 */
	public static function integrate_sa_pm_index(&$subActions)
	{
		$subActions['showpmdrafts'] = [
			'controller' => Draft::class,
			'function' => 'action_showPMDrafts',
			'permission' => 'pm_read'
		];
	}

	/**
	 * Displays a list of available drafts or selects a draft for adding to the editor
	 *
	 * @param int $pmsg
	 *
	 * @throws PmErrorException
	 */
	public function before_set_context($pmsg)
	{
		global $context;

		// If drafts are enabled, lets generate a list of drafts that they can load in to the editor
		if (!empty($context['drafts_pm_save']))
		{
			// Has a specific draft has been selected?  Load it up if there is not already a message already in the editor
			if (isset($_REQUEST['id_draft']) && empty($_POST['subject']) && empty($_POST['message']))
			{
				$this->_loadDraft($this->user->id, (int) $_REQUEST['id_draft']);
				throw new PmErrorException($this->_loaded_draft->to_list, $this->_loaded_draft);
			}

			$this->_prepareDraftsContext($this->user->id, $pmsg);
		}
	}

	/**
	 * Loads in a group of PM drafts for the user.
	 *
	 * What it does:
	 *
	 * - Loads a specific draft for current use in pm editing box if selected.
	 * - Used in the posting screens to allow draft selection
	 * - Will load a draft if selected is supplied via post
	 *
	 * @param int $member_id
	 * @param int $id_draft The draft id
	 *
	 * @return false|null
	 */
	protected function _loadDraft($member_id, $id_draft)
	{
		// Need a member
		if (empty($member_id) || empty($id_draft))
		{
			return false;
		}

		// We haz drafts
		Txt::load('Drafts');
		require_once(SUBSDIR . '/Drafts.subs.php');

		// Load the draft and add it to a object container
		$this->_loaded_draft = new ValuesContainer(loadDraft($id_draft, 1, true, true));
	}

	/**
	 * Loads in a group of PM drafts for the user.
	 *
	 * What it does:
	 *
	 * - Loads a specific draft for current use in pm editing box if selected.
	 * - Used in the posting screens to allow draft selection
	 * - Will load a draft if selected is supplied via post
	 *
	 * @param int $member_id
	 * @param int|bool $id_pm = false if set, it will try to load drafts for this id
	 *
	 * @return bool
	 */
	protected function _prepareDraftsContext($member_id, $id_pm = false)
	{
		global $scripturl, $context, $txt;

		$context['drafts'] = [];

		// Need a member
		if (empty($member_id))
		{
			return false;
		}

		// We haz drafts
		Txt::load('Drafts');
		require_once(SUBSDIR . '/Drafts.subs.php');

		// Load all the drafts for this user that meet the criteria
		$order = 'poster_time DESC';
		$user_drafts = load_user_drafts($member_id, 1, $id_pm, $order);

		// Add them to the context draft array for template display
		foreach ($user_drafts as $draft)
		{
			$short_subject = empty($draft['subject'])
				? $txt['drafts_none']
				: Util::shorten_text(stripslashes($draft['subject']), self::$_subject_length);
			$context['drafts'][] = [
				'subject' => censor($short_subject),
				'poster_time' => standardTime($draft['poster_time']),
				'link' => '<a href="' . $scripturl . '?action=pm;sa=send;id_draft=' . $draft['id_draft'] . '">' . (empty($draft['subject'])
					? $txt['drafts_none']
					: $draft['subject']) . '</a>',
			];
		}

		return true;
	}

	/**
	 * Activates the draft plugin for use in the editor
	 *
	 * @param array $editorOptions
	 */
	public function prepare_send_context(&$editorOptions)
	{
		global $context, $options, $txt;

		// PM drafts enabled, then we need to tell the editor before it initialises
		if (!empty($context['drafts_pm_save']) && !empty($options['drafts_autosave_enabled']))
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
					iBoard: 0,
					iFreq: ' . self::$_autosave_frequency . ',
					sLastID: \'id_pm_draft\',
					sTextareaID: \'' . $editorOptions['id'] . '\',
					bPM: true
				}';

			loadJavascriptFile('drafts.plugin.js', ['defer' => true]);
			Txt::load('Post');

			// Our not so concise shortcut line
			$context['shortcuts_text'] = $context['shortcuts_text'] ?? $txt['shortcuts_drafts'];

			$editorOptions['buttons'] = $editorOptions['buttons'] ?? [];
			$editorOptions['hidden_fields'] = $editorOptions['hidden_fields'] ?? [];

			$editorOptions['buttons'][] = [
				'name' => 'save_draft',
				'value' => $txt['draft_save'],
				'options' => 'onclick="submitThisOnce(this);" accesskey="d"',
			];

			$editorOptions['hidden_fields'][] = [
				'name' => 'id_pm_draft',
				'value' => empty($context['id_pm_draft']) ? 0 : $context['id_pm_draft'],
			];
		}
	}

	/**
	 * Saves a draft, either in reaction to autosave or pressing of save draft button
	 *
	 * @param array $recipientList
	 *
	 * @throws ControllerRedirectException
	 */
	public function before_sending($recipientList)
	{
		global $context, $modSettings;

		// Ajax calling
		if (!isset($context['drafts_pm_save']))
		{
			$context['drafts_pm_save'] = !empty($modSettings['drafts_enabled']) && !empty($modSettings['drafts_pm_enabled']) && allowedTo('pm_draft');
		}

		// Want to save this as a draft and think about it some more?
		if (isset($_POST['save_draft'], $_POST['id_pm_draft']) && !empty($context['drafts_pm_save']))
		{
			// Prepare the data
			$draft = [
				'id_pm_draft' => empty($_POST['id_pm_draft']) ? 0 : (int) $_POST['id_pm_draft'],
				'reply_id' => empty($_POST['replied_to']) ? 0 : (int) $_POST['replied_to'],
				'body' => Util::htmlspecialchars($_POST['message'], ENT_QUOTES, 'UTF-8', true),
				'subject' => strtr(Util::htmlspecialchars($_POST['subject']), ["\r" => '', "\n" => '', "\t" => '']),
				'id_member' => $this->user->id,
				'is_usersaved' => (int) empty($_REQUEST['autosave']),
			];

			if ($this->getApi() !== false)
			{
				$recipientList['to'] = isset($_POST['recipient_to']) ? explode(',', $_POST['recipient_to']) : [];
				$recipientList['bcc'] = isset($_POST['recipient_bcc']) ? explode(',', $_POST['recipient_bcc']) : [];
			}

			// Trigger any before_savepm_draft events
			self::$_eventsManager->trigger('before_savepm_draft', ['draft' => &$draft, 'recipientList' => &$recipientList]);

			// Now save the draft
			savePMDraft($recipientList, $draft, !empty($this->getApi()));
			throw new ControllerRedirectException('', '');
		}
	}

	/**
	 * If it sent, then remove the draft from the system
	 *
	 * @param $failed
	 */
	public function message_sent($failed)
	{
		global $context;
		// If we had a PM draft for this one, then its time to remove it since it was just sent
		if ($failed)
		{
			return;
		}

		if (!$context['drafts_pm_save'])
		{
			return;
		}

		if (empty($_POST['id_pm_draft']))
		{
			return;
		}

		deleteDrafts((int) $_POST['id_pm_draft'], $this->user->id);
	}
}
