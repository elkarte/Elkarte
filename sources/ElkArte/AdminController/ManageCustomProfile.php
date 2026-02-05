<?php

/**
 * Manage custom profile fields administration page.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\AdminController;

use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Exceptions\Exception;
use ElkArte\Helper\Util;
use ElkArte\Languages\Txt;

/**
 * Custom Profile fields administration controller.
 * This class allows modifying custom profile fields for the forum.
 */
class ManageCustomProfile extends AbstractController
{
	/**
	 * Pre-dispatch, called before other methods.
	 */
	public function pre_dispatch()
	{
		// We need this in a few places, so it's easier to have it loaded here
		require_once(SUBSDIR . '/ManageFeatures.subs.php');

		Txt::load('Help+ManageSettings');
	}

	/**
	 * Default action for this controller.
	 */
	public function action_index()
	{
		$subActions = [
			'profile' => [$this, 'action_profile', 'enabled' => featureEnabled('cp'), 'permission' => 'admin_forum'],
			'profileedit' => [$this, 'action_profileedit', 'enabled' => featureEnabled('cp'), 'permission' => 'admin_forum'],
		];

		// Set up the action control
		$action = new Action('modify_profile');

		// By default, do the profile list
		$subAction = $action->initialize($subActions, 'profile');
		$action->dispatch($subAction);
	}

	/**
	 * Show all the custom profile fields available to the user.
	 *
	 * - Allows for drag/drop sorting of custom profile fields
	 * - Accessed with ?action=admin;area=featuresettings;sa=profile
	 *
	 * @uses sub template show_custom_profile
	 */
	public function action_profile(): void
	{
		global $txt, $context;

		theme()->getTemplates()->load('ManageFeatures');
		$context['page_title'] = $txt['custom_profile_title'];
		$context['sub_template'] = 'show_custom_profile';

		// What about standard fields they can tweak?
		$standard_fields = ['website', 'posts', 'warning_status', 'date_registered', 'action'];

		// What fields can't you put on the registration page?
		$context['fields_no_registration'] = ['posts', 'warning_status', 'date_registered', 'action'];

		// Are we saving any standard field changes?
		if ($this->_req->hasPost('save'))
		{
			checkSession();
			validateToken('admin-scp');

			$changes = [];

			// Do the active ones first.
			$disable_fields = array_flip($standard_fields);
			if (!empty($this->_req->post->active))
			{
				foreach ($this->_req->post->active as $value)
				{
					if (isset($disable_fields[$value]))
					{
						unset($disable_fields[$value]);
					}
				}
			}

			// What we have left!
			$changes['disabled_profile_fields'] = empty($disable_fields) ? '' : implode(',', array_keys($disable_fields));

			// Things we want to show on registration?
			$reg_fields = [];
			if (!empty($this->_req->post->reg))
			{
				foreach ($this->_req->post->reg as $value)
				{
					if (!in_array($value, $standard_fields))
					{
						continue;
					}

					if (isset($disable_fields[$value]))
					{
						continue;
					}

					$reg_fields[] = $value;
				}
			}

			// What we have left!
			$changes['registration_fields'] = empty($reg_fields) ? '' : implode(',', $reg_fields);

			updateSettings($changes);
		}

		createToken('admin-scp');

		// Create a listing for all our standard fields
		$listOptions = [
			'id' => 'standard_profile_fields',
			'title' => $txt['standard_profile_title'],
			'base_href' => getUrl('admin', ['action' => 'admin', 'area' => 'featuresettings', 'sa' => 'profile']),
			'get_items' => [
				'function' => 'list_getProfileFields',
				'params' => [
					true,
				],
			],
			'columns' => [
				'field' => [
					'header' => [
						'value' => $txt['standard_profile_field'],
					],
					'data' => [
						'db' => 'label',
						'style' => 'width: 60%;',
					],
				],
				'active' => [
					'header' => [
						'value' => $txt['custom_edit_active'],
						'class' => 'centertext',
					],
					'data' => [
						'function' => static function ($rowData) {
							$isChecked = $rowData['disabled'] ? '' : ' checked="checked"';
							$onClickHandler = $rowData['can_show_register'] ? sprintf('onclick="document.getElementById(\'reg_%1$s\').disabled = !this.checked;"', $rowData['id']) : '';

							return sprintf('<input type="checkbox" name="active[]" id="active_%1$s" value="%1$s" class="input_check" %2$s %3$s />', $rowData['id'], $isChecked, $onClickHandler);
						},
						'style' => 'width: 20%;',
						'class' => 'centertext',
					],
				],
				'show_on_registration' => [
					'header' => [
						'value' => $txt['custom_edit_registration'],
						'class' => 'centertext',
					],
					'data' => [
						'function' => static function ($rowData) {
							$isChecked = $rowData['on_register'] && !$rowData['disabled'] ? ' checked="checked"' : '';
							$isDisabled = $rowData['can_show_register'] ? '' : ' disabled="disabled"';

							return sprintf('<input type="checkbox" name="reg[]" id="reg_%1$s" value="%1$s" class="input_check" %2$s %3$s />', $rowData['id'], $isChecked, $isDisabled);
						},
						'style' => 'width: 20%;',
						'class' => 'centertext',
					],
				],
			],
			'form' => [
				'href' => getUrl('admin', ['action' => 'admin', 'area' => 'featuresettings', 'sa' => 'profile']),
				'name' => 'standardProfileFields',
				'token' => 'admin-scp',
			],
			'additional_rows' => [
				[
					'position' => 'below_table_data',
					'value' => '<input type="submit" name="save" value="' . $txt['save'] . '" class="right_submit" />',
				],
			],
		];
		createList($listOptions);

		// And now we do the same for all of our custom ones
		$token = createToken('admin-sort');
		$listOptions = [
			'id' => 'custom_profile_fields',
			'title' => $txt['custom_profile_title'],
			'base_href' => getUrl('admin', ['action' => 'admin', 'area' => 'featuresettings', 'sa' => 'profile']),
			'default_sort_col' => 'vieworder',
			'no_items_label' => $txt['custom_profile_none'],
			'items_per_page' => 25,
			'sortable' => true,
			'get_items' => [
				'function' => 'list_getProfileFields',
				'params' => [
					false,
				],
			],
			'get_count' => [
				'function' => 'list_getProfileFieldSize',
			],
			'columns' => [
				'vieworder' => [
					'header' => [
						'value' => '',
						'class' => 'hide',
					],
					'data' => [
						'db' => 'vieworder',
						'class' => 'hide',
					],
					'sort' => [
						'default' => 'vieworder',
					],
				],
				'field_name' => [
					'header' => [
						'value' => $txt['custom_profile_fieldname'],
					],
					'data' => [
						'function' => static fn($rowData) => sprintf('<a href="%1$s">%2$s</a><div class="smalltext">%3$s</div>', getUrl('admin', ['action' => 'admin', 'area' => 'featuresettings', 'sa' => 'profileedit', 'fid' => (int) $rowData['id_field']]), $rowData['field_name'], $rowData['field_desc']),
						'style' => 'width: 65%;',
					],
					'sort' => [
						'default' => 'field_name',
						'reverse' => 'field_name DESC',
					],
				],
				'field_type' => [
					'header' => [
						'value' => $txt['custom_profile_fieldtype'],
					],
					'data' => [
						'function' => static function ($rowData) {
							global $txt;

							$textKey = sprintf('custom_profile_type_%1$s', $rowData['field_type']);

							return $txt[$textKey] ?? $textKey;
						},
						'style' => 'width: 10%;',
					],
					'sort' => [
						'default' => 'field_type',
						'reverse' => 'field_type DESC',
					],
				],
				'cust' => [
					'header' => [
						'value' => $txt['custom_profile_active'],
						'class' => 'centertext',
					],
					'data' => [
						'function' => static function ($rowData) {
							$isChecked = $rowData['active'] === '1' ? ' checked="checked"' : '';

							return sprintf('<input type="checkbox" name="cust[]" id="cust_%1$s" value="%1$s" class="input_check"%2$s />', $rowData['id_field'], $isChecked);
						},
						'style' => 'width: 8%;',
						'class' => 'centertext',
					],
					'sort' => [
						'default' => 'active DESC',
						'reverse' => 'active',
					],
				],
				'placement' => [
					'header' => [
						'value' => $txt['custom_profile_placement'],
					],
					'data' => [
						'function' => static function ($rowData) {
							global $txt;

							$placement = 'custom_profile_placement_';
							switch ((int) $rowData['placement'])
							{
								case 0:
									$placement .= 'standard';
									break;
								case 1:
									$placement .= 'withicons';
									break;
								case 2:
									$placement .= 'abovesignature';
									break;
								case 3:
									$placement .= 'aboveicons';
									break;
							}

							return $txt[$placement];
						},
						'style' => 'width: 5%;',
					],
					'sort' => [
						'default' => 'placement DESC',
						'reverse' => 'placement',
					],
				],
				'modify' => [
					'data' => [
						'sprintf' => [
							'format' => '<a href="' . getUrl('admin', ['action' => 'admin', 'area' => 'featuresettings', 'sa' => 'profileedit']) . ';fid=%1$s">' . $txt['modify'] . '</a>',
							'params' => [
								'id_field' => false,
							],
						],
						'style' => 'width: 5%;',
					],
				],
			],
			'form' => [
				'href' => getUrl('admin', ['action' => 'admin', 'area' => 'featuresettings', 'sa' => 'profileedit']),
				'name' => 'customProfileFields',
				'token' => 'admin-scp',
			],
			'additional_rows' => [
				[
					'class' => 'submitbutton flow_flex_additional_row',
					'position' => 'below_table_data',
					'value' => '
						<input type="submit" name="onoff" value="' . $txt['save'] . '" />
						<input type="submit" name="new" value="' . $txt['custom_profile_make_new'] . '" />',
				],
				[
					'position' => 'top_of_list',
					'value' => '<p class="infobox">' . $txt['custom_profile_sort'] . '</p>',
				],
			],
			'javascript' => '
				$().elkSortable({
					sa: "profileorder",
					error: "' . $txt['admin_order_error'] . '",
					title: "' . $txt['admin_order_title'] . '",
					placeholder: "ui-state-highlight",
					href: "?action=admin;area=featuresettings;sa=profile",
					token: {token_var: "' . $token['admin-sort_token_var'] . '", token_id: "' . $token['admin-sort_token'] . '"}
				});
			',
		];

		createList($listOptions);
	}

	/**
	 * Edit some profile fields?
	 *
	 * - Accessed with ?action=admin;area=featuresettings;sa=profileedit
	 *
	 * @uses sub template edit_profile_field
	 */
	public function action_profileedit(): void
	{
		global $txt, $context;

		theme()->getTemplates()->load('ManageFeatures');

		// Sort out the context!
		$context['fid'] = $this->_req->getQuery('fid', 'intval', 0);
		$context[$context['admin_menu_name']]['current_subsection'] = 'profile';
		$context['page_title'] = $context['fid'] ? $txt['custom_edit_title'] : $txt['custom_add_title'];
		$context['sub_template'] = 'edit_profile_field';

		// Any error messages to show?
		if ($this->_req->hasQuery('msg'))
		{
			Txt::load('Errors');
			$msg_key = $this->_req->getQuery('msg', 'trim|strval', '');
			if (isset($txt['custom_option_' . $msg_key]))
			{
				$context['custom_option__error'] = $txt['custom_option_' . $msg_key];
			}
		}

		// Load the profile language for section names.
		Txt::load('Profile');

		// Load up the profile field if one was supplied
		if ($context['fid'])
		{
			$context['field'] = getProfileField($context['fid']);
		}

		// Set up the default values as needed.
		if (empty($context['field']))
		{
			$context['field'] = [
				'name' => '',
				'colname' => '???',
				'desc' => '',
				'profile_area' => 'forumprofile',
				'reg' => false,
				'display' => false,
				'memberlist' => false,
				'type' => 'text',
				'max_length' => 255,
				'rows' => 4,
				'cols' => 30,
				'bbc' => false,
				'default_check' => false,
				'default_select' => '',
				'default_value' => '',
				'options' => ['', '', ''],
				'active' => true,
				'private' => false,
				'can_search' => false,
				'mask' => 'nohtml',
				'regex' => '',
				'enclose' => '',
				'placement' => 0,
			];
		}

		// All the JavaScript for this page... everything else is in admin.js
		theme()->addJavascriptVar(['startOptID' => count($context['field']['options'])]);
		theme()->addInlineJavascript('updateInputBoxes();', true);

		// Are we toggling which ones are active?
		if (isset($this->_req->post->onoff))
		{
			checkSession();
			validateToken('admin-scp');

			// Enable and disable custom fields as required.
			$enabled = [0];
			if (isset($this->_req->post->cust) && is_array($this->_req->post->cust))
			{
				foreach ($this->_req->post->cust as $id)
				{
					$enabled[] = (int) $id;
				}
			}

			updateRenamedProfileStatus($enabled);
		}
		// Are we saving?
		elseif ($this->_req->hasPost('save'))
		{
			checkSession();
			validateToken('admin-ecp');

			// Everyone needs a name - even the (bracket) unknown...
			if (trim($this->_req->post->field_name) === '')
			{
				redirectexit('action=admin;area=featuresettings;sa=profileedit;fid=' . (int) $context['fid'] . ';msg=need_name');
			}

			// Regex, you say?  Do a very basic test to see if the pattern is valid
			if (!empty($this->_req->post->regex) && @preg_match($this->_req->post->regex, 'dummy') === false)
			{
				redirectexit('action=admin;area=featuresettings;sa=profileedit;fid=' . (int) $context['fid'] . ';msg=regex_error');
			}

			$this->_req->post->field_name = $this->_req->getPost('field_name', 'Util::htmlspecialchars');
			$this->_req->post->field_desc = $this->_req->getPost('field_desc', 'Util::htmlspecialchars');

			$rows = isset($this->_req->post->rows) ? (int) $this->_req->post->rows : 4;
			$cols = isset($this->_req->post->cols) ? (int) $this->_req->post->cols : 30;

			// Checkboxes...
			$show_reg = $this->_req->getPost('reg', 'intval', 0);
			$show_display = isset($this->_req->post->display) ? 1 : 0;
			$show_memberlist = isset($this->_req->post->memberlist) ? 1 : 0;
			$bbc = isset($this->_req->post->bbc) ? 1 : 0;
			$show_profile = $this->_req->post->profile_area;
			$active = isset($this->_req->post->active) ? 1 : 0;
			$private = $this->_req->getPost('private', 'intval', 0);
			$can_search = isset($this->_req->post->can_search) ? 1 : 0;

			// Some masking stuff...
			$mask = $this->_req->getPost('mask', 'strval', '');
			if ($mask === 'regex' && isset($this->_req->post->regex))
			{
				$mask .= $this->_req->post->regex;
			}

			$field_length = $this->_req->getPost('max_length', 'intval', 255);
			$enclose = $this->_req->getPost('enclose', 'strval', '');
			$placement = $this->_req->getPost('placement', 'intval', 0);

			// Select options?
			$field_options = '';
			$newOptions = [];

			// Set default
			$default = '';

			switch ($this->_req->post->field_type)
			{
				case 'check':
					$default = isset($this->_req->post->default_check) ? 1 : '';
					break;
				case 'select':
				case 'radio':
					if (!empty($this->_req->post->select_option))
					{
						foreach ($this->_req->post->select_option as $k => $v)
						{
							// Clean, clean, clean...
							$v = Util::htmlspecialchars($v);
							$v = strtr($v, [',' => '']);

							// Nada, zip, etc...
							if (trim($v) === '')
							{
								continue;
							}

							// Otherwise, save it boy.
							$field_options .= $v . ',';

							// This is just for working out what happened with old options...
							$newOptions[$k] = $v;

							// Is it default?
							if (!isset($this->_req->post->default_select))
							{
								continue;
							}

							if ($this->_req->post->default_select != $k)
							{
								continue;
							}

							$default = $v;
						}

						if (isset($_POST['default_select']) && $_POST['default_select'] === 'no_default')
						{
							$default = 'no_default';
						}

						$field_options = substr($field_options, 0, -1);
					}

					break;
				default:
					$default = $this->_req->post->default_value ?? '';
			}

			// Come up with the unique name?
			if (empty($context['fid']))
			{
				$colname = Util::substr(strtr($this->_req->post->field_name, [' ' => '']), 0, 12);
				preg_match('~([\w_-]+)~', $colname, $matches);

				// If there is nothing to the name, then let's start our own - for foreign languages etc.
				if (isset($matches[1]))
				{
					$colname = 'cust_' . strtolower($matches[1]);
					$initial_colname = 'cust_' . strtolower($matches[1]);
				}
				else
				{
					$colname = 'cust_' . mt_rand(1, 9999999999);
					$initial_colname = 'cust_' . mt_rand(1, 9999999999);
				}

				$unique = ensureUniqueProfileField($colname, $initial_colname);

				// Still not a unique column name? Leave it up to the user, then.
				if (!$unique)
				{
					throw new Exception('custom_option_not_unique');
				}

				// And create a new field
				$new_field = [
					'col_name' => $colname,
					'field_name' => $this->_req->post->field_name,
					'field_desc' => $this->_req->post->field_desc,
					'field_type' => $this->_req->post->field_type,
					'field_length' => $field_length,
					'field_options' => $field_options,
					'show_reg' => $show_reg,
					'show_display' => $show_display,
					'show_memberlist' => $show_memberlist,
					'show_profile' => $show_profile,
					'private' => $private,
					'active' => $active,
					'default_value' => $default,
					'rows' => $rows,
					'cols' => $cols,
					'can_search' => $can_search,
					'bbc' => $bbc,
					'mask' => $mask,
					'enclose' => $enclose,
					'placement' => $placement,
					'vieworder' => list_getProfileFieldSize() + 1,
				];
				addProfileField($new_field);
			}
			// Work out what to do with the user data otherwise...
			else
			{
				// Anything going to check or select is pointless keeping - as is anything coming from check!
				if (($this->_req->post->field_type === 'check' && $context['field']['type'] !== 'check')
					|| (($this->_req->post->field_type === 'select' || $this->_req->post->field_type === 'radio') && $context['field']['type'] !== 'select' && $context['field']['type'] !== 'radio')
					|| ($context['field']['type'] === 'check' && $this->_req->post->field_type !== 'check'))
				{
					deleteProfileFieldUserData($context['field']['colname']);
				}
				// Otherwise - if the select is edited may need to adjust!
				elseif ($this->_req->post->field_type === 'select' || $this->_req->post->field_type === 'radio')
				{
					$optionChanges = $context['field']['options'];
					$takenKeys = [];

					// Work out what's changed!
					foreach ($optionChanges as $k => $option)
					{
						if (trim($option) === '')
						{
							continue;
						}

						// Still exists?
						if (in_array($option, $newOptions))
						{
							$takenKeys[] = $k;
						}
					}

					// Finally - have we renamed it - or is it really gone?
					foreach ($optionChanges as $k => $option)
					{
						// Just been renamed?
						if (in_array($k, $takenKeys))
						{
							continue;
						}

						if (empty($newOptions[$k]))
						{
							continue;
						}

						updateRenamedProfileField($k, $newOptions, $context['field']['colname'], $option);
					}
				}

				// @todo Maybe we should adjust based on new text length limits?

				// And finally update an existing field
				$field_data = [
					'field_length' => $field_length,
					'show_reg' => $show_reg,
					'show_display' => $show_display,
					'show_memberlist' => $show_memberlist,
					'private' => $private,
					'active' => $active,
					'can_search' => $can_search,
					'bbc' => $bbc,
					'current_field' => $context['fid'],
					'field_name' => $this->_req->post->field_name,
					'field_desc' => $this->_req->post->field_desc,
					'field_type' => $this->_req->post->field_type,
					'field_options' => $field_options,
					'show_profile' => $show_profile,
					'default_value' => $default,
					'mask' => $mask,
					'enclose' => $enclose,
					'placement' => $placement,
					'rows' => $rows,
					'cols' => $cols,
				];

				updateProfileField($field_data);

				// Just clean up any old selects - these are a pain!
				if (($this->_req->post->field_type === 'select' || $this->_req->post->field_type === 'radio') && !empty($newOptions))
				{
					deleteOldProfileFieldSelects($newOptions, $context['field']['colname']);
				}
			}
		}
		// Deleting?
		elseif (isset($this->_req->post->delete) && $context['field']['colname'])
		{
			checkSession();
			validateToken('admin-ecp');

			// Delete the old data first, then the field.
			deleteProfileFieldUserData($context['field']['colname']);
			deleteProfileField($context['fid']);
		}

		// Rebuild display cache etc.
		if (isset($this->_req->post->delete) || isset($this->_req->post->save) || isset($this->_req->post->onoff))
		{
			checkSession();

			// Update the display cache
			updateDisplayCache();
			redirectexit('action=admin;area=featuresettings;sa=profile');
		}

		createToken('admin-ecp');
	}
}
