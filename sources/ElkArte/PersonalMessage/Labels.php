<?php

/**
 * This file deals with label actions related to personal messages.
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
use ElkArte\Cache\Cache;
use ElkArte\Helper\Util;

/**
 * Class Labels
 * It allows managing personal message labels
 *
 * @package ElkArte\PersonalMessage
 */
class Labels extends AbstractController
{
	/**
	 * Default action for the class
	 */
	public function action_index()
	{
		$this->action_manlabels();
	}

	/**
	 * This function handles adding, deleting, and editing labels on messages.
	 */
	public function action_manlabels(): void
	{
		global $context;

		require_once(SUBSDIR . '/PersonalMessage.subs.php');

		$this->_initLabelsContext();

		// Add all existing labels to the array to save, slashing them as necessary...
		$the_labels = $this->_getExistingLabels($context['labels']);

		// Submitting changes?
		if ($this->_req->hasPost('add') || $this->_req->hasPost('delete') || $this->_req->hasPost('save'))
		{
			$this->_handleLabelSubmissions($the_labels);
		}
	}

	/**
	 * Set up the context for the manage labels page.
	 */
	private function _initLabelsContext(): void
	{
		global $txt, $context;

		// Build the link tree elements...
		$context['breadcrumbs'][] = [
			'url' => getUrl('action', ['action' => 'pm', 'sa' => 'manlabels']),
			'name' => $txt['pm_manage_labels']
		];

		// Some things for the template
		$context['page_title'] = $txt['pm_manage_labels'];
		$context['sub_template'] = 'labels';
	}

	/**
	 * Get the existing labels from the context, excluding the inbox (-1).
	 *
	 * @param array $labels
	 * @return array
	 */
	private function _getExistingLabels(array $labels): array
	{
		$the_labels = [];
		foreach ($labels as $label)
		{
			if ($label['id'] !== -1)
			{
				$the_labels[$label['id']] = $label['name'];
			}
		}

		return $the_labels;
	}

	/**
	 * Handle the submission of label changes (add, delete, save).
	 *
	 * @param array $the_labels
	 */
	private function _handleLabelSubmissions(array $the_labels): void
	{
		checkSession();

		// This will be for updating messages.
		$message_changes = [];
		$new_labels = [];

		// Will most likely need this.
		loadRules();

		// Adding a new label?
		if ($this->_req->hasPost('add'))
		{
			$this->_addNewLabel($the_labels);
		}
		// Deleting an existing label?
		elseif ($this->_req->hasPost('delete') && $this->_req->hasPost('delete_label'))
		{
			$message_changes = $this->_deleteLabels($the_labels, $new_labels);
		}
		// The hardest one to deal with... changes.
		elseif ($this->_req->hasPost('save'))
		{
			$message_changes = $this->_saveLabels($the_labels, $new_labels);
		}

		// Save the label status.
		require_once(SUBSDIR . '/Members.subs.php');
		updateMemberData($this->user->id, ['message_labels' => implode(',', $the_labels)]);

		// Update all the messages currently with any label changes in them!
		$this->_updateMessagesAndRules($message_changes, $new_labels);

		// Make sure we're not caching this!
		Cache::instance()->remove('labelCounts:' . $this->user->id);

		// To make the changes appear right away, redirect.
		redirectexit('action=pm;sa=manlabels');
	}

	/**
	 * Add a new label to the list.
	 *
	 * @param array $the_labels
	 */
	private function _addNewLabel(array &$the_labels): void
	{
		$label = $this->_req->getPost('label', 'trim|strval', '');
		$label = strtr(Util::htmlspecialchars($label), [',' => '&#044;']);

		if (Util::strlen($label) > 30)
		{
			$label = Util::substr($label, 0, 30);
		}

		if ($label !== '')
		{
			$the_labels[] = $label;
		}
	}

	/**
	 * Delete labels from the list.
	 *
	 * @param array $the_labels
	 * @param array $new_labels
	 * @return array message changes
	 */
	private function _deleteLabels(array &$the_labels, array &$new_labels): array
	{
		$delete_label = $this->_req->getPost('delete_label', null, []);
		$message_changes = [];
		$i = 0;

		foreach (array_keys($the_labels) as $id)
		{
			if (isset($delete_label[$id]))
			{
				unset($the_labels[$id]);
				$message_changes[$id] = true;
			}
			else
			{
				$new_labels[$id] = $i++;
			}
		}

		return $message_changes;
	}

	/**
	 * Save changes to existing labels.
	 *
	 * @param array $the_labels
	 * @param array $new_labels
	 * @return array message changes
	 */
	private function _saveLabels(array &$the_labels, array &$new_labels): array
	{
		$label_name = $this->_req->getPost('label_name', null, []);
		$message_changes = [];
		$i = 0;

		foreach (array_keys($the_labels) as $id)
		{
			if ($id === -1)
			{
				continue;
			}

			if (isset($label_name[$id]))
			{
				// Prepare the label name
				$prepared = trim(strtr(Util::htmlspecialchars($label_name[$id]), [',' => '&#044;']));

				// Has to fit in the database as well
				if (Util::strlen($prepared) > 30)
				{
					$prepared = Util::substr($prepared, 0, 30);
				}

				if ($prepared !== '')
				{
					$the_labels[(int) $id] = $prepared;
					$new_labels[$id] = $i++;
				}
				else
				{
					unset($the_labels[(int) $id]);
					$message_changes[(int) $id] = true;
				}
			}
			else
			{
				$new_labels[$id] = $i++;
			}
		}

		return $message_changes;
	}

	/**
	 * Update messages and rules when labels are changed or deleted.
	 *
	 * @param array $message_changes
	 * @param array $new_labels
	 */
	private function _updateMessagesAndRules(array $message_changes, array $new_labels): void
	{
		global $context;

		if (empty($message_changes))
		{
			return;
		}

		$searchArray = array_keys($message_changes);

		if (!empty($new_labels))
		{
			for ($i = max($searchArray) + 1, $n = max(array_keys($new_labels)); $i <= $n; $i++)
			{
				$searchArray[] = $i;
			}
		}

		updateLabelsToPM($searchArray, $new_labels, $this->user->id);

		// Now do the same the rules - check through each rule.
		$rule_changes = [];
		foreach ($context['rules'] as $k => $rule)
		{
			// Each action...
			foreach ($rule['actions'] as $k2 => $action)
			{
				if ($action['t'] !== 'lab' || !in_array($action['v'], $searchArray, true))
				{
					continue;
				}

				$rule_changes[] = $rule['id'];

				// If we're here, we have a label which is either changed or gone...
				if (isset($new_labels[$action['v']]))
				{
					$context['rules'][$k]['actions'][$k2]['v'] = $new_labels[$action['v']];
				}
				else
				{
					unset($context['rules'][$k]['actions'][$k2]);
				}
			}
		}

		// If we have rules to change, do so now.
		if (!empty($rule_changes))
		{
			$this->_updateRules($rule_changes);
		}
	}

	/**
	 * Update or delete PM rules.
	 *
	 * @param array $rule_changes
	 */
	private function _updateRules(array $rule_changes): void
	{
		global $context;

		$rule_changes = array_unique($rule_changes);

		// Update/delete as appropriate.
		foreach ($rule_changes as $k => $id)
		{
			if (!empty($context['rules'][$id]['actions']))
			{
				updatePMRuleAction($id, $this->user->id, $context['rules'][$id]['actions']);
				unset($rule_changes[$k]);
			}
		}

		// Anything left here means it's lost all actions...
		if (!empty($rule_changes))
		{
			deletePMRules($this->user->id, $rule_changes);
		}
	}
}
