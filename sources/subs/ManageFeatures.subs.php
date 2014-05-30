<?php

/**
 * This file provides utility functions and db function for the profile functions,
 * notably, but not exclusivly, deals with custom profile fields
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
 * @version 1.0 Release Candidate 1
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Loads the signature from 50 members per request
 * Used in ManageFeatures to apply signature settings to all members
 *
 * @param int $start_member
 * @return array
 */
function getSignatureFromMembers($start_member)
{
	$db = database();

	$members = array();

	$request = $db->query('', '
		SELECT id_member, signature
		FROM {db_prefix}members
		WHERE id_member BETWEEN ' . $start_member . ' AND ' . $start_member . ' + 49
			AND id_group != {int:admin_group}
			AND FIND_IN_SET({int:admin_group}, additional_groups) = 0',
		array(
			'admin_group' => 1,
		)
	);
	while ($result = $db->fetch_assoc($request))
	{
		$members[$result['id_member']]['id_member'] = $result['id_member'];
		$members[$result['id_member']]['signature'] = $result['signature'];
	}

	return $members;
}

/**
 * Updates the signature from a given member
 *
 * @param int $id_member
 * @param string $signature
 */
function updateSignature($id_member, $signature)
{
	updateMemberData($id_member, array('signature' => $signature));
}

/**
 * Callback for createList() in displaying profile fields
 * Can be used to load standard or custom fields by setting the $standardFields flag
 *
 * @param int $start
 * @param int $items_per_page
 * @param string $sort
 * @param boolean $standardFields
 */
function list_getProfileFields($start, $items_per_page, $sort, $standardFields)
{
	global $txt, $modSettings;

	$db = database();

	$list = array();

	if ($standardFields)
	{
		$standard_fields = array('location', 'gender', 'website', 'personal_text', 'posts', 'warning_status');
		$fields_no_registration = array('posts', 'warning_status');
		$disabled_fields = isset($modSettings['disabled_profile_fields']) ? explode(',', $modSettings['disabled_profile_fields']) : array();
		$registration_fields = isset($modSettings['registration_fields']) ? explode(',', $modSettings['registration_fields']) : array();

		foreach ($standard_fields as $field)
			$list[] = array(
				'id' => $field,
				'label' => isset($txt['standard_profile_field_' . $field]) ? $txt['standard_profile_field_' . $field] : (isset($txt[$field]) ? $txt[$field] : $field),
				'disabled' => in_array($field, $disabled_fields),
				'on_register' => in_array($field, $registration_fields) && !in_array($field, $fields_no_registration),
				'can_show_register' => !in_array($field, $fields_no_registration),
			);
	}
	else
	{
		// Load all the fields.
		$request = $db->query('', '
			SELECT id_field, col_name, field_name, field_desc, field_type, active, placement, vieworder
			FROM {db_prefix}custom_fields
			ORDER BY {raw:sort}, vieworder ASC
			LIMIT {int:start}, {int:items_per_page}',
			array(
				'sort' => $sort,
				'start' => $start,
				'items_per_page' => $items_per_page,
			)
		);
		while ($row = $db->fetch_assoc($request))
			$list[$row['id_field']] = $row;
		$db->free_result($request);
	}

	return $list;
}

/**
 * Callback for createList().
 */
function list_getProfileFieldSize()
{
	$db = database();

	$request = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}custom_fields',
		array(
		)
	);

	list ($numProfileFields) = $db->fetch_row($request);
	$db->free_result($request);

	return $numProfileFields;
}

/**
 * Loads the profile field properties from a given field id
 *
 * @param int $id_field
 * @return array $field
 */
function getProfileField($id_field)
{
	$db = database();

	$field = array();

	$request = $db->query('', '
		SELECT
			id_field, col_name, field_name, field_desc, field_type, field_length, field_options,
			show_reg, show_display, show_memberlist, show_profile, private, active, default_value, can_search,
			bbc, mask, enclose, placement, vieworder
		FROM {db_prefix}custom_fields
		WHERE id_field = {int:current_field}',
		array(
			'current_field' => $id_field,
		)
	);
	while ($row = $db->fetch_assoc($request))
	{
		if ($row['field_type'] == 'textarea')
			@list ($rows, $cols) = explode(',', $row['default_value']);
		else
		{
			$rows = 3;
			$cols = 30;
		}

		$field = array(
			'name' => $row['field_name'],
			'desc' => $row['field_desc'],
			'colname' => $row['col_name'],
			'profile_area' => $row['show_profile'],
			'reg' => $row['show_reg'],
			'display' => $row['show_display'],
			'memberlist' => $row['show_memberlist'],
			'type' => $row['field_type'],
			'max_length' => $row['field_length'],
			'rows' => $rows,
			'cols' => $cols,
			'bbc' => $row['bbc'] ? true : false,
			'default_check' => $row['field_type'] == 'check' && $row['default_value'] ? true : false,
			'default_select' => $row['field_type'] == 'select' || $row['field_type'] == 'radio' ? $row['default_value'] : '',
			'options' => strlen($row['field_options']) > 1 ? explode(',', $row['field_options']) : array('', '', ''),
			'active' => $row['active'],
			'private' => $row['private'],
			'can_search' => $row['can_search'],
			'mask' => $row['mask'],
			'regex' => substr($row['mask'], 0, 5) == 'regex' ? substr($row['mask'], 5) : '',
			'enclose' => $row['enclose'],
			'placement' => $row['placement'],
		);
	}
	$db->free_result($request);

	return($field);
}

/**
 * Make sure a profile field is unique
 *
 * @param string $colname
 * @param string $initial_colname
 * @param boolean $unique
 * @return boolean
 */
function ensureUniqueProfileField($colname, $initial_colname, $unique = false)
{
	$db = database();
	// Make sure this is unique.
	// @todo This may not be the most efficient way to do this.
	for ($i = 0; !$unique && $i < 9; $i ++)
	{
		$request = $db->query('', '
			SELECT id_field
			FROM {db_prefix}custom_fields
			WHERE col_name = {string:current_column}',
			array(
				'current_column' => $colname,
			)
		);
		if ($db->num_rows($request) == 0)
			$unique = true;
		else
			$colname = $initial_colname . $i;
			$db->free_result($request);
	}

	return $unique;
}

/**
 * Update the profile fields name
 *
 * @param string $key
 * @param mixed[] $newOptions
 * @param string $name
 * @param string $option
 */
function updateRenamedProfileField($key, $newOptions, $name, $option)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}custom_fields_data
		SET value = {string:new_value}
		WHERE variable = {string:current_column}
			AND value = {string:old_value}
			AND id_member > {int:no_member}',
		array(
			'no_member' => 0,
			'new_value' => $newOptions[$key],
			'current_column' => $name,
			'old_value' => $option,
		)
	);
}

/**
 * Update the custom profile fields active status on/off
 *
 * @param int[] $enabled
 */
function updateRenamedProfileStatus($enabled)
{
	$db = database();

	// Do the updates
	$db->query('', '
		UPDATE {db_prefix}custom_fields
		SET active = CASE WHEN id_field IN ({array_int:id_cust_enable}) THEN 1 ELSE 0 END',
		array(
			'id_cust_enable' => $enabled,
		)
	);
}

/**
 * Update the profile field
 *
 * @param mixed[] $field_data
 */
function updateProfileField($field_data)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}custom_fields
		SET
			field_name = {string:field_name}, field_desc = {string:field_desc},
			field_type = {string:field_type}, field_length = {int:field_length},
			field_options = {string:field_options}, show_reg = {int:show_reg},
			show_display = {int:show_display}, show_memberlist = {int:show_memberlist},
			show_profile = {string:show_profile}, private = {int:private},
			active = {int:active}, default_value = {string:default_value},
			can_search = {int:can_search}, bbc = {int:bbc}, mask = {string:mask},
			enclose = {string:enclose}, placement = {int:placement}
		WHERE id_field = {int:current_field}',
		array(
			'field_length' => $field_data['field_length'],
			'show_reg' => $field_data['show_reg'],
			'show_display' => $field_data['show_display'],
			'show_memberlist' => $field_data['show_memberlist'],
			'private' => $field_data['private'],
			'active' => $field_data['active'],
			'can_search' => $field_data['can_search'],
			'bbc' => $field_data['bbc'],
			'current_field' => $field_data['current_field'],
			'field_name' => $field_data['field_name'],
			'field_desc' => $field_data['field_desc'],
			'field_type' => $field_data['field_type'],
			'field_options' => $field_data['field_options'],
			'show_profile' => $field_data['show_profile'],
			'default_value' => $field_data['default_value'],
			'mask' => $field_data['mask'],
			'enclose' => $field_data['enclose'],
			'placement' => $field_data['placement'],
		)
	);
}

/**
 * Updates the viewing order for profile fields
 * Done as a CASE WHEN one two three ELSE 0 END in place of many updates
 *
 * @param string $replace constucted as WHEN fieldname=value THEN new viewvalue WHEN .....
 */
function updateProfileFieldOrder($replace)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}custom_fields
		SET vieworder = CASE ' . $replace . ' ELSE 0 END',
		array('')
	);
}

/**
 * Deletes selected values from old profile field selects
 *
 * @param string[] $newOptions
 * @param string $fieldname
 */
function deleteOldProfileFieldSelects($newOptions, $fieldname)
{
	$db = database();

	$db->query('', '
		DELETE FROM {db_prefix}custom_fields_data
		WHERE variable = {string:current_column}
			AND value NOT IN ({array_string:new_option_values})
			AND id_member > {int:no_member}',
		array(
			'no_member' => 0,
			'new_option_values' => $newOptions,
			'current_column' => $fieldname,
		)
	);
}

/**
 * Used to add a new custom profile field
 *
 * @param mixed[] $field
 */
function addProfileField($field)
{
	$db = database();

	$db->insert('',
		'{db_prefix}custom_fields',
		array(
			'col_name' => 'string', 'field_name' => 'string', 'field_desc' => 'string',
			'field_type' => 'string', 'field_length' => 'string', 'field_options' => 'string',
			'show_reg' => 'int', 'show_display' => 'int', 'show_memberlist' => 'int', 'show_profile' => 'string',
			'private' => 'int', 'active' => 'int', 'default_value' => 'string', 'can_search' => 'int',
			'bbc' => 'int', 'mask' => 'string', 'enclose' => 'string', 'placement' => 'int', 'vieworder' => 'int'
		),
		array(
			$field['col_name'], $field['field_name'], $field['field_desc'],
			$field['field_type'], $field['field_length'], $field['field_options'],
			$field['show_reg'], $field['show_display'], $field['show_memberlist'], $field['show_profile'],
			$field['private'], $field['active'], $field['default'], $field['can_search'],
			$field['bbc'], $field['mask'], $field['enclose'], $field['placement'], $field['vieworder']
		),
		array('id_field')
	);
}

/**
 * Delete all user data for a specified custom profile field
 *
 * @param string $name
 */
function deleteProfileFieldUserData($name)
{
	$db = database();

	// Delete the user data first.
	$db->query('', '
		DELETE FROM {db_prefix}custom_fields_data
		WHERE variable = {string:current_column}
			AND id_member > {int:no_member}',
		array(
			'no_member' => 0,
			'current_column' => $name,
		)
	);
}

/**
 * Deletes a custom profile field.
 *
 * @param int $id
 */
function deleteProfileField($id)
{
	$db = database();

	$db->query('', '
		DELETE FROM {db_prefix}custom_fields
		WHERE id_field = {int:current_field}',
		array(
			'current_field' => $id,
		)
	);
}

/**
 * Update the display cache, needed after editing or deleting a custom profile field
 */
function updateDisplayCache()
{
	$db = database();

	$request = $db->query('', '
		SELECT col_name, field_name, field_type, bbc, enclose, placement, vieworder
		FROM {db_prefix}custom_fields
		WHERE show_display = {int:is_displayed}
			AND active = {int:active}
			AND private != {int:not_owner_only}
			AND private != {int:not_admin_only}
		ORDER BY vieworder',
		array(
			'is_displayed' => 1,
			'active' => 1,
			'not_owner_only' => 2,
			'not_admin_only' => 3,
		)
	);

	$fields = array();
	while ($row = $db->fetch_assoc($request))
	{
		$fields[] = array(
			'colname' => strtr($row['col_name'], array('|' => '', ';' => '')),
			'title' => strtr($row['field_name'], array('|' => '', ';' => '')),
			'type' => $row['field_type'],
			'bbc' => $row['bbc'] ? 1 : 0,
			'placement' => !empty($row['placement']) ? $row['placement'] : 0,
			'enclose' => !empty($row['enclose']) ? $row['enclose'] : '',
		);
	}
	$db->free_result($request);
	updateSettings(array('displayFields' => serialize($fields)));
}