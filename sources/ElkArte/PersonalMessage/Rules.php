<?php

/**
 * This file deals with the rule actions related to personal messages.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\PersonalMessage;

use ElkArte\AbstractController;
use ElkArte\Exceptions\Exception;
use ElkArte\Helper\Util;

/**
 * Class Rules
 * It allows managing personal message rules
 *
 * @package ElkArte\PersonalMessage
 */
class Rules extends AbstractController
{
	/**
	 * Default action for the class
	 */
	public function action_index()
	{
		$this->action_manrules();
	}

	/**
	 * List and allow adding/entering all man rules
	 *
	 * @uses sub template rules
	 */
	public function action_manrules(): void
	{
		global $txt, $context;

		require_once(SUBSDIR . '/PersonalMessage.subs.php');

		// Applying all rules?
		if ($this->_req->hasQuery('apply'))
		{
			$this->action_applyRules();
		}

		// Editing a specific rule?
		if ($this->_req->hasQuery('add'))
		{
			$this->action_addRule();

			return;
		}

		// Saving?
		if ($this->_req->hasQuery('save'))
		{
			$this->action_saveRule();
		}

		// Deleting?
		if ($this->_req->hasPost('delselected'))
		{
			$this->action_deleteRules();
		}

		// The link tree - gotta have this :o
		$context['breadcrumbs'][] = [
			'url' => getUrl('action', ['action' => 'pm', 'sa' => 'manrules']),
			'name' => $txt['pm_manage_rules']
		];

		$context['page_title'] = $txt['pm_manage_rules'];
		$context['sub_template'] = 'rules';

		// Load them... load them!!
		loadRules();

		// Likely to need all the groups!
		require_once(SUBSDIR . '/Membergroups.subs.php');
		$context['groups'] = accessibleGroups();
	}

	/**
	 * Apply all rules to the current PMs
	 */
	public function action_applyRules(): void
	{
		checkSession('get');

		applyRules(true);
		redirectexit('action=pm;sa=manrules');
	}

	/**
	 * Setting up the UI for adding or editing a rule
	 */
	public function action_addRule(): void
	{
		global $context;

		require_once(SUBSDIR . '/PersonalMessage.subs.php');
		loadRules();

		require_once(SUBSDIR . '/Membergroups.subs.php');
		$context['groups'] = accessibleGroups();

		$rid = $this->_req->getQuery('rid', 'intval', 0);
		$context['rid'] = isset($context['rules'][$rid]) ? $rid : 0;
		$context['sub_template'] = 'add_rule';

		$this->_setupJSVars();

		// Current rule information...
		if ($context['rid'])
		{
			$context['rule'] = $context['rules'][$context['rid']];
			$this->_getCriteriaMemberNames();
		}
		else
		{
			$context['rule'] = [
				'id' => '',
				'name' => '',
				'criteria' => [],
				'actions' => [],
				'logic' => 'and',
			];
		}

		// Add a dummy criteria to allow expansion for none js users.
		$context['rule']['criteria'][] = ['t' => '', 'v' => ''];
	}

	/**
	 * Sets up the JavaScript variables for the PM rule UI
	 */
	private function _setupJSVars(): void
	{
		global $txt, $context;

		// Any known rule
		$js_rules = [];
		foreach ($context['known_rules'] as $rule)
		{
			$js_rules[$rule] = $txt['pm_rule_' . $rule];
		}

		// Any known label
		$js_labels = [];
		foreach ($context['labels'] as $label)
		{
			if ($label['id'] !== -1)
			{
				$js_labels[$label['id'] + 1] = $label['name'];
			}
		}

		theme()->addJavascriptVar([
			'criteriaNum' => 0,
			'actionNum' => 0,
		]);

		// Oh my, we have a lot of text strings for this
		theme()->addJavascriptVar([
			'groups' => json_encode($context['groups']),
			'labels' => json_encode($js_labels),
			'rules' => json_encode($js_rules),
			'txt_pm_readable_and' => $txt['pm_readable_and'],
			'txt_pm_readable_or' => $txt['pm_readable_or'],
			'txt_pm_readable_member' => $txt['pm_readable_member'],
			'txt_pm_readable_group' => $txt['pm_readable_group'],
			'txt_pm_readable_subject ' => $txt['pm_readable_subject'],
			'txt_pm_readable_body' => $txt['pm_readable_body'],
			'txt_pm_readable_buddy' => $txt['pm_readable_buddy'],
			'txt_pm_readable_label' => $txt['pm_readable_label'],
			'txt_pm_readable_delete' => $txt['pm_readable_delete'],
			'txt_pm_readable_start' => $txt['pm_readable_start'],
			'txt_pm_readable_end' => $txt['pm_readable_end'],
			'txt_pm_readable_then' => $txt['pm_readable_then'],
			'txt_pm_rule_not_defined' => $txt['pm_rule_not_defined'],
			'txt_pm_rule_criteria_pick' => $txt['pm_rule_criteria_pick'],
			'txt_pm_rule_sel_group' => $txt['pm_rule_sel_group'],
			'txt_pm_rule_sel_action' => $txt['pm_rule_sel_action'],
			'txt_pm_rule_label' => $txt['pm_rule_label'],
			'txt_pm_rule_delete' => $txt['pm_rule_delete'],
			'txt_pm_rule_sel_label' => $txt['pm_rule_sel_label'],
		], true);
	}

	/**
	 * Gets member names for rule criteria when editing a rule
	 */
	private function _getCriteriaMemberNames(): void
	{
		global $context;

		$members = [];

		// Need to get member names!
		foreach ($context['rule']['criteria'] as $k => $criteria)
		{
			if ($criteria['t'] !== 'mid' || empty($criteria['v']))
			{
				continue;
			}

			$members[(int) $criteria['v']] = $k;
		}

		if (!empty($members))
		{
			require_once(SUBSDIR . '/Members.subs.php');
			$result = getBasicMemberData(array_keys($members));
			foreach ($result as $row)
			{
				$context['rule']['criteria'][$members[$row['id_member']]]['v'] = $row['member_name'];
			}
		}
	}

	/**
	 * Saving a PM rule
	 */
	public function action_saveRule(): void
	{
		global $context;

		checkSession();

		require_once(SUBSDIR . '/PersonalMessage.subs.php');
		loadRules();

		$rid = $this->_req->getQuery('rid', 'intval', 0);
		$context['rid'] = isset($context['rules'][$rid]) ? $rid : 0;

		// Name is easy!
		$ruleName = $this->_req->getPost('rule_name', 'trim|Util::htmlspecialchars', '');
		if (empty($ruleName))
		{
			throw new Exception('pm_rule_no_name', false);
		}

		$criteria = $this->_getCriteria();
		$doDelete = 0;
		$actions = $this->_getActions($doDelete);

		if (empty($criteria) || (empty($actions) && !$doDelete))
		{
			throw new Exception('pm_rule_no_criteria', false);
		}

		// What are we storing?
		$criteria = serialize($criteria);
		$actions = serialize($actions);

		// Logic?
		$rule_logic = $this->_req->getPost('rule_logic', 'trim|strval', '');
		$isOr = $rule_logic === 'or' ? 1 : 0;

		// Create the rule?
		if (empty($context['rid']))
		{
			addPMRule($this->user->id, $ruleName, $criteria, $actions, $doDelete, $isOr);
		}
		else
		{
			updatePMRule($this->user->id, $context['rid'], $ruleName, $criteria, $actions, $doDelete, $isOr);
		}

		redirectexit('action=pm;sa=manrules');
	}

	/**
	 * Processes and validates rule criteria from the request
	 *
	 * @return array
	 */
	private function _getCriteria(): array
	{
		$ruletype = $this->_req->getPost('ruletype', null, []);
		$ruledefgroup = $this->_req->getPost('ruledefgroup', null, []);
		$ruledef = $this->_req->getPost('ruledef', null, []);

		if (empty($ruletype))
		{
			return [];
		}

		$criteria = [];
		foreach ($ruletype as $ind => $type)
		{
			// Check everything is here...
			if ($type === 'gid' && (!isset($ruledefgroup[$ind])))
			{
				continue;
			}

			if ($type !== 'bud' && !isset($ruledef[$ind]))
			{
				continue;
			}

			// Members need to be found.
			if ($type === 'mid')
			{
				require_once(SUBSDIR . '/Members.subs.php');
				$name = trim((string) ($ruledef[$ind] ?? ''));
				$member = getMemberByName($name, true);
				if (empty($member))
				{
					continue;
				}

				$criteria[] = ['t' => 'mid', 'v' => $member['id_member']];
			}
			elseif ($type === 'bud')
			{
				$criteria[] = ['t' => 'bud', 'v' => 1];
			}
			elseif ($type === 'gid')
			{
				$criteria[] = ['t' => 'gid', 'v' => (int) $ruledefgroup[$ind]];
			}
			elseif (in_array($type, ['sub', 'msg']) && trim((string) ($ruledef[$ind] ?? '')) !== '')
			{
				$criteria[] = ['t' => $type, 'v' => Util::htmlspecialchars(trim((string) $ruledef[$ind]))];
			}
		}

		return $criteria;
	}

	/**
	 * Processes and validates rule actions from the request
	 *
	 * @param int $doDelete
	 * @return array
	 */
	private function _getActions(int &$doDelete): array
	{
		$acttype = $this->_req->getPost('acttype', null, []);
		$labdef = $this->_req->getPost('labdef', null, []);

		$doDelete = 0;
		if (empty($acttype))
		{
			return [];
		}

		$actions = [];
		foreach ($acttype as $ind => $type)
		{
			// Picking a valid label?
			if ($type === 'lab' && !isset($labdef[$ind]))
			{
				continue;
			}

			// Record what we're doing.
			if ($type === 'del')
			{
				$doDelete = 1;
			}
			elseif ($type === 'lab')
			{
				$actions[] = ['t' => 'lab', 'v' => (int) $labdef[$ind] - 1];
			}
		}

		return $actions;
	}

	/**
	 * Deleting selected PM rules
	 */
	public function action_deleteRules(): void
	{
		checkSession();

		$delrule = $this->_req->getPost('delrule', null, []);
		if (empty($delrule))
		{
			redirectexit('action=pm;sa=manrules');
		}

		$toDelete = array_map('intval', array_keys($delrule));

		require_once(SUBSDIR . '/PersonalMessage.subs.php');
		deletePMRules($this->user->id, $toDelete);

		redirectexit('action=pm;sa=manrules');
	}
}
