<?php

/**
 * This file provides utility functions and db function for the profile functions,
 * notably, but not exclusively, deals with custom profile fields
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

use ElkArte\Util;

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
			'admin_group' => 11,
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
	require_once(SUBSDIR . '/Members.subs.php');
	updateMemberData($id_member, array('signature' => $signature));
}

/**
 * Update all signatures given a new set of constraints
 *
 * @param int $applied_sigs
 * @throws \ElkArte\Exceptions\Exception
 */
function updateAllSignatures($applied_sigs)
{
	global $context, $modSettings;

	require_once(SUBSDIR . '/Members.subs.php');
	$sig_start = time();

	// This is horrid - but I suppose some people will want the option to do it.
	$done = false;
	$context['max_member'] = maxMemberID();

	// Load all the signature settings.
	list ($sig_limits, $sig_bbc) = explode(':', $modSettings['signature_settings']);
	$sig_limits = explode(',', $sig_limits);
	$disabledTags = !empty($sig_bbc) ? explode(',', $sig_bbc) : array();

	// @todo temporary since it does not work, and seriously why would you do this?
	$disabledTags[] = 'footnote';

	while (!$done)
	{
		// No changed signatures yet
		$changes = array();

		// Get a group of member signatures, 50 at a clip
		$update_sigs = getSignatureFromMembers($applied_sigs);

		if (empty($update_sigs))
		{
			$done = true;
		}

		foreach ($update_sigs as $row)
		{
			// Apply all the rules we can realistically do.
			$sig = strtr($row['signature'], array('<br />' => "\n"));

			// Max characters...
			if (!empty($sig_limits[1]))
			{
				$sig = Util::substr($sig, 0, $sig_limits[1]);
			}

			// Max lines...
			if (!empty($sig_limits[2]))
			{
				$count = 0;
				$str_len = strlen($sig);
				for ($i = 0; $i < $str_len; $i++)
				{
					if ($sig[$i] === "\n")
					{
						$count++;
						if ($count >= $sig_limits[2])
						{
							$sig = substr($sig, 0, $i) . strtr(substr($sig, $i), array("\n" => ' '));
						}
					}
				}
			}

			// Max text size
			if (!empty($sig_limits[7]) && preg_match_all('~\[size=([\d\.]+)?(px|pt|em|x-large|larger)?~i', $sig, $matches) !== false && isset($matches[2]))
			{
				// Same as parse_bbc
				$sizes = array(1 => 0.7, 2 => 1.0, 3 => 1.35, 4 => 1.45, 5 => 2.0, 6 => 2.65, 7 => 3.95);

				foreach ($matches[1] as $ind => $size)
				{
					$limit_broke = 0;

					// Just specifying as [size=x]?
					if (empty($matches[2][$ind]))
					{
						$matches[2][$ind] = 'em';
						$size = isset($sizes[(int) $size]) ? $sizes[(int) $size] : 0;
					}

					// Attempt to allow all sizes of abuse, so to speak.
					if ($matches[2][$ind] == 'px' && $size > $sig_limits[7])
					{
						$limit_broke = $sig_limits[7] . 'px';
					}
					elseif ($matches[2][$ind] == 'pt' && $size > ($sig_limits[7] * 0.75))
					{
						$limit_broke = ((int) $sig_limits[7] * 0.75) . 'pt';
					}
					elseif ($matches[2][$ind] == 'em' && $size > ((float) $sig_limits[7] / 16))
					{
						$limit_broke = ((float) $sig_limits[7] / 16) . 'em';
					}
					elseif ($matches[2][$ind] != 'px' && $matches[2][$ind] != 'pt' && $matches[2][$ind] != 'em' && $sig_limits[7] < 18)
					{
						$limit_broke = 'large';
					}

					if ($limit_broke)
					{
						$sig = str_replace($matches[0][$ind], '[size=' . $sig_limits[7] . 'px', $sig);
					}
				}
			}

			// Stupid images - this is stupidly, stupidly challenging.
			if ((!empty($sig_limits[3]) || !empty($sig_limits[5]) || !empty($sig_limits[6])))
			{
				$replaces = array();
				$img_count = 0;

				// Get all BBC tags...
				preg_match_all('~\[img(\s+width=([\d]+))?(\s+height=([\d]+))?(\s+width=([\d]+))?\s*\](?:<br />)*([^<">]+?)(?:<br />)*\[/img\]~i', $sig, $matches);

				// ... and all HTML ones.
				preg_match_all('~&lt;img\s+src=(?:&quot;)?((?:http://|ftp://|https://|ftps://).+?)(?:&quot;)?(?:\s+alt=(?:&quot;)?(.*?)(?:&quot;)?)?(?:\s?/)?&gt;~i', $sig, $matches2, PREG_PATTERN_ORDER);

				// And stick the HTML in the BBC.
				if (!empty($matches2))
				{
					foreach ($matches2[0] as $ind => $dummy)
					{
						$matches[0][] = $matches2[0][$ind];
						$matches[1][] = '';
						$matches[2][] = '';
						$matches[3][] = '';
						$matches[4][] = '';
						$matches[5][] = '';
						$matches[6][] = '';
						$matches[7][] = $matches2[1][$ind];
					}
				}

				// Try to find all the images!
				if (!empty($matches))
				{
					$image_count_holder = array();
					foreach ($matches[0] as $key => $image)
					{
						$width = -1;
						$height = -1;
						$img_count++;

						// Too many images?
						if (!empty($sig_limits[3]) && $img_count > $sig_limits[3])
						{
							// If we've already had this before we only want to remove the excess.
							if (isset($image_count_holder[$image]))
							{
								$img_offset = -1;
								$rep_img_count = 0;
								while ($img_offset !== false)
								{
									$img_offset = strpos($sig, $image, $img_offset + 1);
									$rep_img_count++;
									if ($rep_img_count > $image_count_holder[$image])
									{
										// Only replace the excess.
										$sig = substr($sig, 0, $img_offset) . str_replace($image, '', substr($sig, $img_offset));

										// Stop looping.
										$img_offset = false;
									}
								}
							}
							else
							{
								$replaces[$image] = '';
							}

							continue;
						}

						// Does it have predefined restraints? Width first.
						if ($matches[6][$key])
						{
							$matches[2][$key] = $matches[6][$key];
						}

						if ($matches[2][$key] && $sig_limits[5] && $matches[2][$key] > $sig_limits[5])
						{
							$width = $sig_limits[5];
							$matches[4][$key] *= $width / $matches[2][$key];
						}
						elseif ($matches[2][$key])
						{
							$width = $matches[2][$key];
						}

						// ... and height.
						if ($matches[4][$key] && $sig_limits[6] && $matches[4][$key] > $sig_limits[6])
						{
							$height = $sig_limits[6];
							if ($width != -1)
							{
								$width *= $height / $matches[4][$key];
							}
						}
						elseif ($matches[4][$key])
						{
							$height = $matches[4][$key];
						}

						// If the dimensions are still not fixed - we need to check the actual image.
						if (($width == -1 && $sig_limits[5]) || ($height == -1 && $sig_limits[6]))
						{
							// We'll mess up with images, who knows.
							require_once(SUBSDIR . '/Attachments.subs.php');

							$sizes = url_image_size($matches[7][$key]);
							if (is_array($sizes))
							{
								// Too wide?
								if ($sizes[0] > $sig_limits[5] && $sig_limits[5])
								{
									$width = $sig_limits[5];
									$sizes[1] *= $width / $sizes[0];
								}

								// Too high?
								if ($sizes[1] > $sig_limits[6] && $sig_limits[6])
								{
									$height = $sig_limits[6];
									if ($width == -1)
									{
										$width = $sizes[0];
									}
									$width *= $height / $sizes[1];
								}
								elseif ($width != -1)
								{
									$height = $sizes[1];
								}
							}
						}

						// Did we come up with some changes? If so remake the string.
						if ($width != -1 || $height != -1)
						{
							$replaces[$image] = '[img' . ($width != -1 ? ' width=' . round($width) : '') . ($height != -1 ? ' height=' . round($height) : '') . ']' . $matches[7][$key] . '[/img]';
						}

						// Record that we got one.
						$image_count_holder[$image] = isset($image_count_holder[$image]) ? $image_count_holder[$image] + 1 : 1;
					}

					if (!empty($replaces))
					{
						$sig = str_replace(array_keys($replaces), array_values($replaces), $sig);
					}
				}
			}

			// Try to fix disabled tags.
			if (!empty($disabledTags))
			{
				$sig = preg_replace('~\[(?:' . implode('|', $disabledTags) . ').+?\]~i', '', $sig);
				$sig = preg_replace('~\[/(?:' . implode('|', $disabledTags) . ')\]~i', '', $sig);
			}

			$sig = strtr($sig, array("\n" => '<br />'));
			call_integration_hook('integrate_apply_signature_settings', array(&$sig, $sig_limits, $disabledTags));
			if ($sig != $row['signature'])
			{
				$changes[$row['id_member']] = $sig;
			}
		}

		// Do we need to delete what we have?
		if (!empty($changes))
		{
			foreach ($changes as $id => $sig)
			{
				updateSignature($id, $sig);
			}
		}

		$applied_sigs += 50;
		if (!$done)
		{
			pauseSignatureApplySettings($applied_sigs, $sig_start);
		}
	}
}

/**
 * Callback for createList() in displaying profile fields
 * Can be used to load standard or custom fields by setting the $standardFields flag
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page The number of items to show per page
 * @param string $sort A string indicating how to sort the results
 * @param boolean $standardFields
 *
 * @return array
 */
function list_getProfileFields($start, $items_per_page, $sort, $standardFields)
{
	global $txt, $modSettings;

	$db = database();

	$list = array();

	if ($standardFields)
	{
		$standard_fields = array('website', 'posts', 'warning_status', 'date_registered');
		$fields_no_registration = array('posts', 'warning_status', 'date_registered');
		$disabled_fields = isset($modSettings['disabled_profile_fields']) ? explode(',', $modSettings['disabled_profile_fields']) : array();
		$registration_fields = isset($modSettings['registration_fields']) ? explode(',', $modSettings['registration_fields']) : array();

		foreach ($standard_fields as $field)
		{
			$list[] = array(
				'id' => $field,
				'label' => isset($txt['standard_profile_field_' . $field]) ? $txt['standard_profile_field_' . $field] : (isset($txt[$field]) ? $txt[$field] : $field),
				'disabled' => in_array($field, $disabled_fields),
				'on_register' => in_array($field, $registration_fields) && !in_array($field, $fields_no_registration),
				'can_show_register' => !in_array($field, $fields_no_registration),
			);
		}
	}
	else
	{
		// Load all the fields.
		$request = $db->query('', '
			SELECT 
				id_field, col_name, field_name, field_desc, field_type, active, placement, vieworder
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
		{
			$list[$row['id_field']] = $row;
		}
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
		array()
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

	// The fully-qualified name for rows is here because it's a reserved word in Mariadb 10.2.4+ and quoting would be different for MySQL/Mariadb and PSQL
	$request = $db->query('', '
		SELECT
			id_field, col_name, field_name, field_desc, field_type, field_length, field_options,
			show_reg, show_display, show_memberlist, show_profile, private, active, default_value, can_search,
			bbc, mask, enclose, placement, vieworder, {db_prefix}custom_fields.rows, cols
		FROM {db_prefix}custom_fields
		WHERE id_field = {int:current_field}',
		array(
			'current_field' => $id_field,
		)
	);
	while ($row = $db->fetch_assoc($request))
	{
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
			'rows' => $row['rows'],
			'cols' => $row['cols'],
			'bbc' => $row['bbc'] ? true : false,
			'default_check' => $row['field_type'] == 'check' && $row['default_value'] ? true : false,
			'default_select' => $row['field_type'] == 'select' || $row['field_type'] == 'radio' ? $row['default_value'] : '',
			'show_nodefault' => $row['field_type'] == 'select' || $row['field_type'] == 'radio',
			'default_value' => $row['default_value'],
			'options' => strlen($row['field_options']) > 1 ? explode(',', $row['field_options']) : array('', '', ''),
			'active' => $row['active'],
			'private' => $row['private'],
			'can_search' => $row['can_search'],
			'mask' => $row['mask'],
			'regex' => substr($row['mask'], 0, 5) === 'regex' ? substr($row['mask'], 5) : '',
			'enclose' => $row['enclose'],
			'placement' => $row['placement'],
		);
	}
	$db->free_result($request);

	return ($field);
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
	for ($i = 0; !$unique && $i < 9; $i++)
	{
		$request = $db->query('', '
			SELECT 
				id_field
			FROM {db_prefix}custom_fields
			WHERE col_name = {string:current_column}',
			array(
				'current_column' => $colname,
			)
		);
		if ($db->num_rows($request) == 0)
		{
			$unique = true;
		}
		else
		{
			$colname = $initial_colname . $i;
		}
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

	// The fully-qualified name for rows is here because it's a reserved word in Mariadb 10.2.4+ and quoting would be different for MySQL/Mariadb and PSQL
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
			enclose = {string:enclose}, placement = {int:placement}, {db_prefix}custom_fields.rows = {int:rows},
			cols = {int:cols}
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
			'rows' => $field_data['rows'],
			'cols' => $field_data['cols'],
		)
	);
}

/**
 * Updates the viewing order for profile fields
 * Done as a CASE WHEN one two three ELSE 0 END in place of many updates
 *
 * @param string $replace constructed as WHEN fieldname=value THEN new viewvalue WHEN .....
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
			'bbc' => 'int', 'mask' => 'string', 'enclose' => 'string', 'placement' => 'int', 'vieworder' => 'int',
			'rows' => 'int', 'cols' => 'int'
		),
		array(
			$field['col_name'], $field['field_name'], $field['field_desc'],
			$field['field_type'], $field['field_length'], $field['field_options'],
			$field['show_reg'], $field['show_display'], $field['show_memberlist'], $field['show_profile'],
			$field['private'], $field['active'], $field['default_value'], $field['can_search'],
			$field['bbc'], $field['mask'], $field['enclose'], $field['placement'], $field['vieworder'],
			$field['rows'], $field['cols']
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

	$fields = $db->fetchQuery('
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
	)->fetch_callback(
		function ($row) {
			return array(
				'colname' => strtr($row['col_name'], array('|' => '', ';' => '')),
				'title' => strtr($row['field_name'], array('|' => '', ';' => '')),
				'type' => $row['field_type'],
				'bbc' => $row['bbc'] ? 1 : 0,
				'placement' => !empty($row['placement']) ? $row['placement'] : 0,
				'enclose' => !empty($row['enclose']) ? $row['enclose'] : '',
			);
		}
	);

	updateSettings(array('displayFields' => serialize($fields)));
}

/**
 * Loads all the custom fields in the system, active or not
 */
function loadAllCustomFields()
{
	$db = database();

	// Get the names of any custom fields.
	$request = $db->query('', '
		SELECT
			col_name, field_name, bbc
		FROM {db_prefix}custom_fields',
		array()
	);
	$custom_field_titles = array();
	while ($row = $db->fetch_assoc($request))
	{
		$custom_field_titles['customfield_' . $row['col_name']] = array(
			'title' => $row['field_name'],
			'parse_bbc' => $row['bbc'],
		);
	}
	$db->free_result($request);

	return $custom_field_titles;
}

/**
 * Load all the available mention types
 *
 * What it does:
 *
 * - Scans teh subs\MentionType directory for files
 * - Calls its getType method
 *
 * @return array
 */
function getNotificationTypes()
{
	$glob = new GlobIterator(SOURCEDIR . '/ElkArte/Mentions/MentionType/Notification/*Mention.php', FilesystemIterator::SKIP_DOTS);
	$types = array();

	// For each file found, call its getType method
	foreach ($glob as $file)
	{
		$class_name = '\\ElkArte\\Mentions\\MentionType\\Notification\\' . $file->getBasename('.php');
		$types[] = $class_name::getType();
	}

	return $types;
}

/**
 * Returns the modules for the given mentions
 *
 * What it does:
 *
 * - Calls each modules static function ::getModules
 * - Called from ManageFeatures.controller as part of notification settings
 *
 * @param string[] $enabled_mentions
 *
 * @return array
 */
function getMentionsModules($enabled_mentions)
{
	$modules = array();

	foreach ($enabled_mentions as $mention)
	{
		$class_name = '\\ElkArte\\Mentions\\MentionType\\Event\\' . ucfirst($mention);
		if (class_exists($class_name))
		{
			$modules = $class_name::getModules($modules);
		}
	}

	return $modules;
}

/**
 * Loads available frontpage controllers for selection in the look/layout area of the ACP
 *
 * What it does:
 *
 * - Scans controllerdir and addonsdir for .controller.php files
 * - Checks if found files have a static frontPageOptions method
 *
 * @return array
 */
function getFrontPageControllers()
{
	global $txt;

	$classes = array();

	$glob = new GlobIterator(CONTROLLERDIR . '/*.controller.php', FilesystemIterator::SKIP_DOTS);
	$classes += scanFileSystemForControllers($glob);

	$glob = new GlobIterator(ADDONSDIR . '/*/controllers/*.controller.php', FilesystemIterator::SKIP_DOTS);
	$classes += scanFileSystemForControllers($glob, '\\ElkArte\\Addon\\');

	$config_vars = array(array('select', 'front_page', $classes));
	array_unshift($config_vars[0][2], $txt['default']);

	foreach (array_keys($classes) as $class_name)
	{
		$options = $class_name::frontPageOptions();
		if (!empty($options))
		{
			$config_vars = array_merge($config_vars, $options);
		}
	}

	return $config_vars;
}

/**
 *
 * @param \GlobIterator $iterator
 * @param string $namespace
 *
 * @return array
 */
function scanFileSystemForControllers($iterator, $namespace = '')
{
	global $txt;

	$types = array();

	foreach ($iterator as $file)
	{
		$class_name = $namespace . $file->getBasename('.php');

		if (!class_exists($class_name))
		{
			continue;
		}

		if (is_subclass_of($class_name, '\\ElkArte\\AbstractController') && $class_name::canFrontPage())
		{
			// Temporary
			if (!isset($txt[$class_name]))
			{
				continue;
			}

			$types[$class_name] = $txt[$class_name];
		}
	}

	return $types;
}

/**
 * Just pause the signature applying thing.
 *
 * @param int $applied_sigs
 * @param int $sig_start
 * @throws \ElkArte\Exceptions\Exception
 * @todo Merge with other pause functions?
 *    pausePermsSave(), pauseAttachmentMaintenance(), pauseRepairProcess()
 *
 * @todo Move to subs file
 */
function pauseSignatureApplySettings($applied_sigs, $sig_start)
{
	global $context, $txt;

	// Try get more time...
	detectServer()->setTimeLimit(600);

	// Have we exhausted all the time we allowed?
	if (time() - array_sum(explode(' ', $sig_start)) < 3)
	{
		return;
	}

	$context['continue_get_data'] = '?action=admin;area=featuresettings;sa=sig;apply;step=' . $applied_sigs . ';' . $context['session_var'] . '=' . $context['session_id'];
	$context['page_title'] = $txt['not_done_title'];
	$context['continue_post_data'] = '';
	$context['continue_countdown'] = '2';
	$context['sub_template'] = 'not_done';

	// Specific stuff to not break this template!
	$context[$context['admin_menu_name']]['current_subsection'] = 'sig';

	// Get the right percent.
	$context['continue_percent'] = round(($applied_sigs / $context['max_member']) * 100);

	// Never more than 100%!
	$context['continue_percent'] = min($context['continue_percent'], 100);

	obExit();
}
