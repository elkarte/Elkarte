<?php
/**
 * @name      Elkarte Forum
 * @copyright Elkarte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Alpha
 */

// Template for the profile side bar - goes before any other profile template.
function template_profile_above()
{
	global $context, $scripturl, $txt, $settings;
	
	echo '
	<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/script.js"></script>
	<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/profile.js"></script>';
	
	// If an error occurred while trying to save previously, give the user a clue!
	echo template_error_message();

	// If the profile was update successfully, let the user know this.
	if (!empty($context['profile_updated']))
		echo '
		<div class="ui-body ui-body-e">
			', $context['profile_updated'], '
		</div>';

	// Profile menu
	if (allowedTo('profile_identity_any'))
	{
		echo '
		<div data-role="controlgroup">
			<a data-role="button" href="', $scripturl, '?action=profile;area=summary;u=', $context['member']['id'], '">', $txt['profileInfo'], '</a>';

		if (allowedTo('profile_identity_own'))
			echo '
			<a data-role="button" href="', $scripturl, '?action=profile;area=account;u=', $context['member']['id'], '">', $txt['account'], '</a>';

		if (allowedTo('profile_identity_own'))
			echo '
			<a data-role="button" href="', $scripturl, '?action=profile;area=forumprofile;u=', $context['member']['id'], '">', $txt['profileEdit'], '</a>';

		echo '
		</div><br />';
	}
}

// Template for closing off  profile_above.
function template_profile_below()
{
}

// This template displays users details without any option to edit them.
function template_summary()
{
	global $context, $settings, $scripturl, $modSettings, $txt;

	// Display the basic information about the user
	echo '
	<ul data-role="listview">
		<li data-role="list-divider">
			<h3 class="catbg">', $txt['summary'], '</h3>
		</li>
		<li>
			<div class="avatar">
				', $context['member']['avatar']['image'], '
			</div>
			<h4>', $context['member']['name'], '</h4>
			<p>', (!empty($context['member']['group']) ? $context['member']['group'] : $context['member']['post_group']), '</p>
			<span id="userstatus">', $context['can_send_pm'] ? '<a href="' . $context['member']['online']['href'] . '" title="' . $context['member']['online']['label'] . '" rel="nofollow">' : '', $settings['use_image_buttons'] ? '<img src="' . $context['member']['online']['image_href'] . '" alt="' . $context['member']['online']['text'] . '" />' : $context['member']['online']['text'], $context['can_send_pm'] ? '</a>' : '', $settings['use_image_buttons'] ? '<span class="smalltext"> ' . $context['member']['online']['text'] . '</span>' : '', '
		</li>';
	if (!$context['user']['is_owner'] && $context['can_send_pm'])
		echo '
		<li><a href="', $scripturl, '?action=pm;sa=send;u=', $context['id_member'], '">', $txt['profile_sendpm_short'], '</a></li>';

	// What about if we allow email only via the forum??
	if ($context['member']['show_email'] === 'yes' || $context['member']['show_email'] === 'no_through_forum' || $context['member']['show_email'] === 'yes_permission_override' && $context['can_send_email'])
		echo '
		<li><a href="', $scripturl, '?action=emailuser;sa=email;uid=', $context['member']['id'], '" title="', $context['member']['show_email'] == 'yes' || $context['member']['show_email'] == 'yes_permission_override' ? $context['member']['email'] : '', '" rel="nofollow">', $txt['send_email'], ' ', $context['member']['name'], '</a></li>';

	// Don't show an icon if they haven't specified a website.
	if ($context['member']['website']['url'] !== '' && !isset($context['disabled_fields']['website']))
		echo '
		<li><a href="', $context['member']['website']['url'], '" title="' . $context['member']['website']['title'] . '" target="_blank" class="new_win">', $txt['website'], '</a></li>';

	// Are there any custom profile fields for the summary?
	if (!empty($context['custom_fields']))
	{
		foreach ($context['custom_fields'] as $field)
			if (($field['placement'] == 1 || empty($field['output_html'])) && !empty($field['value']))
				echo '
					<li class="custom_field">', $field['output_html'], '</li>';
	}

	echo '
				', !isset($context['disabled_fields']['icq']) && !empty($context['member']['icq']['link']) ? '<li><a href="' . $context['member']['icq']['href'] . '">' . $txt['icq'] . ' - ' . $context['member']['icq']['name'] . '</a></li>' : '', '
				', !isset($context['disabled_fields']['msn']) && !empty($context['member']['msn']['link']) ? '<li><a href="' . $context['member']['msn']['href'] . '">' . $txt['msn'] . ' - ' . $context['member']['msn']['name'] . '</a></li>' : '', '
				', !isset($context['disabled_fields']['aim']) && !empty($context['member']['aim']['link']) ? '<li><a href="' . $context['member']['aim']['href'] . '">' . $txt['aim'] . ' - ' . $context['member']['aim']['name'] . '</a></li>' : '', '
				', !isset($context['disabled_fields']['yim']) && !empty($context['member']['yim']['link']) ? '<li><a href="' . $context['member']['yim']['href'] . '">' . $txt['yim'] . ' - ' . $context['member']['yim']['name'] . '</a></li>' : '', '
		<li>
			<a href="', $scripturl, '?action=profile;area=showposts;u=', $context['id_member'], '">', $txt['showPosts'], '</a>
		</li>';

	// Can they add this member as a buddy?
	if (!empty($context['can_have_buddy']) && !$context['user']['is_owner'])
		echo '
		<li><a href="', $scripturl, '?action=buddy;u=', $context['id_member'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['buddy_' . ($context['member']['is_buddy'] ? 'remove' : 'add')], '</a></li>';

	if ($context['user']['is_owner'] || $context['user']['is_admin'])
		echo '
		<li>', $txt['username'], ': ', $context['member']['username'], '</li>';

	if (!isset($context['disabled_fields']['posts']))
		echo '
		<li>', $txt['profile_posts'], ': ', $context['member']['posts'], ' (', $context['member']['posts_per_day'], ' ', $txt['posts_per_day'], ')</li>';

	if ($context['can_send_email'])
	{
		// Only show the email address fully if it's not hidden - and we reveal the email.
		if ($context['member']['show_email'] == 'yes')
			echo '
		<li>', $txt['email'], ': ', $context['member']['email'], '</li>';

		// ... Or if the one looking at the profile is an admin they can see it anyway.
		elseif ($context['member']['show_email'] == 'yes_permission_override')
			echo '
		<li>', $txt['email'], ': <em><a href="', $scripturl, '?action=emailuser;sa=email;uid=', $context['member']['id'], '">', $context['member']['email'], '</a></em></li>';
	}

	if (!empty($modSettings['titlesEnable']) && !empty($context['member']['title']))
		echo '
		<li>', $txt['custom_title'], ': ', $context['member']['title'], '</li>';

	if (!empty($context['member']['blurb']))
		echo '
		<li>', $txt['personal_text'], ': ', $context['member']['blurb'], '</li>';

	// If karma enabled show the members karma.
	if ($modSettings['karmaMode'] == '1')
		echo '
		<li>', $modSettings['karmaLabel'], ' ', ($context['member']['karma']['good'] - $context['member']['karma']['bad']), '</li>';

	elseif ($modSettings['karmaMode'] == '2')
		echo '
		<li>', $modSettings['karmaLabel'], ' +', $context['member']['karma']['good'], '/-', $context['member']['karma']['bad'], '</li>';

	if (!isset($context['disabled_fields']['gender']) && !empty($context['member']['gender']['name']))
		echo '
		<li>', $txt['gender'], ': ', $context['member']['gender']['name'], '</li>';

	echo '
		<li>', $txt['age'], ': ', $context['member']['age'] . ($context['member']['today_is_birthday'] ? ' &nbsp; <img src="' . $settings['images_url'] . '/cake.png" alt="" />' : ''), '</li>';

	if (!isset($context['disabled_fields']['location']) && !empty($context['member']['location']))
		echo '
		<li>', $txt['location'], ': ', $context['member']['location'], '</li>';

	// Any custom fields for standard placement?
	if (!empty($context['custom_fields']))
	{
		foreach ($context['custom_fields'] as $field)
		{
			if ($field['placement'] != 0 || empty($field['output_html']))
				continue;

			echo '
			<li>', $field['name'], ': ', $field['output_html'], '</li>';
		}
	}

	// Can they view/issue a warning?
	if ($context['can_view_warning'] && $context['member']['warning'])
	{
		echo '
		<li>', $txt['profile_warning_level'], ':
			<a href="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=', $context['can_issue_warning'] ? 'issuewarning' : 'viewwarning', '">', $context['member']['warning'], '%</a>';

		// Can we provide information on what this means?
		if (!empty($context['warning_status']))
			echo '
			<span class="smalltext">(', $context['warning_status'], ')</span>';

		echo '
		</li>';
	}

	echo '
		<li>', $txt['date_registered'], ': ', $context['member']['registered'], '</li>';

	echo '
		<li>', $txt['local_time'], ': ', $context['member']['local_time'], '</li>';

	if (!empty($modSettings['userLanguage']) && !empty($context['member']['language']))
		echo '
		<li>', $txt['language'], ': ', $context['member']['language'], '</li>';

	echo '
		<li>', $txt['lastLoggedIn'], ': ', $context['member']['last_login'], '</li>';

	// Are there any custom profile fields for the summary?
	if (!empty($context['custom_fields']))
	{
		foreach ($context['custom_fields'] as $field)
		{
			if ($field['placement'] != 2 || empty($field['output_html']))
				continue;

			echo '
			<li>', $field['output_html'], '</li>';
		}

	}

	echo '
	</ul>';
}

// Template for showing all the posts of the user, in chronological order.
function template_showPosts()
{
	global $context,$scripturl, $txt;

	echo '
	<ul data-role="listview">
		<li data-role="list-divider">
			<h3>
				', (!isset($context['attachments']) && empty($context['is_topics']) ? $txt['showMessages'] : (!empty($context['is_topics']) ? $txt['showTopics'] : $txt['showAttachments'])), ' - ', $context['member']['name'], '
			</h3>
		</li>
	</ul>';
	
	// For every post to be displayed, give it its own div, and show the important details of the post.
	foreach ($context['posts'] as $post)
	{
		// Build the custom button array.
		$context['normal_buttons'] = array(
			'reply' => array('test' => $post['can_reply'], 'text' => 'quote', 'lang' => true, 'url' => $scripturl . '?action=post;board=' . $context['current_board'] . '.0'),
			'quote' => array('test' => $post['can_quote'], 'text' => 'reply', 'lang' => true, 'url' => $scripturl . '?action=post;board=' . $context['current_board'] . '.0;poll'),
			'notify' => array('test' => $post['can_mark_notify'], 'text' => $context['is_marked_notify'] ? 'unnotify' : 'notify', 'lang' => true, 'custom' => 'onclick="return confirm(\'' . ($context['is_marked_notify'] ? $txt['notification_disable_board'] : $txt['notification_enable_board']) . '\');"', 'url' => $scripturl . '?action=notifyboard;sa=' . ($context['is_marked_notify'] ? 'off' : 'on') . ';board=' . $context['current_board'] . '.' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id']),
			'remove' => array('test' => $post['can_delete'], 'text' => 'remove', 'image' => 'markread.png', 'lang' => true, 'url' => $scripturl . '?action=markasread;sa=board;board=' . $context['current_board'] . '.0;' . $context['session_var'] . '=' . $context['session_id']),
		);
		
		echo '
		<ul data-role="listview" data-inset="true">
			<li data-role="list-divider">
				<h4>', $post['board']['name'], ' / ', $post['subject'], '</h4>
				<p>&#171;&nbsp;<strong>', $txt['on'], ':</strong> ', $post['time'], '&nbsp;&#187;</p>';
			if (!$post['approved'])
				echo '
				<em>', $txt['post_awaiting_approval'], '</em>';
			if ($post['can_reply'] || $post['can_mark_notify'] || $post['can_delete'])
			{
				echo '
				<span style="position: absolute; right: 10px; top: 10px">
					<select name="post_options" class="post_options" data-icon="gear" data-iconpos="notext" data-select-menu="true" data-native-menu="false">
						<option>', $txt['mobile_post_options'], '</option>';
						
					foreach ($context['normal_buttons'] as $button => $val)
					{
						if ($val['test'])
							echo '
						<option value="', $val['url'] , '">', $txt[$val['text']], '</option>';
					}				
				echo '
					</select>
				</span>';
			}				
			echo '
			</li>
			<li>
				<a href="', $scripturl, '?topic=', $post['topic'], '.', $post['start'], '#msg', $post['id'], '">', $post['body'], '</a>
			</li>
		</ul>';
	}
}

// Template for showing all the buddies of the current user.
function template_editBuddies()
{
	// Not allowed in mobile for now
	return;
}

// Template for showing the ignore list of the current user.
function template_editIgnoreList()
{
	// Not allowed in mobile for now
	return;
}

// This template shows an admin information on a users IP addresses used and errors attributed to them.
function template_trackActivity()
{
	// Not allowed in mobile for now
	return;
}

// The template for trackIP, allowing the admin to see where/who a certain IP has been used.
function template_trackIP()
{
	// Not allowed in mobile for now
	return;
}

function template_showPermissions()
{
	// Not allowed in mobile for now
	return;
}

// Template for user statistics, showing graphs and the like.
function template_statPanel()
{
	// we don't want to show stats for now
	return;
}

// Template for editing profile options.
function template_edit_options()
{
	global $context, $scripturl, $txt;

	// The main header!
	echo '
	<form action="', (!empty($context['profile_custom_submit_url']) ? $context['profile_custom_submit_url'] : $scripturl . '?action=profile;area=' . $context['menu_item_selected'] . ';u=' . $context['id_member']), '" method="post" accept-charset="', $context['character_set'], '" name="creator" id="creator" enctype="multipart/form-data" onsubmit="return checkProfileSubmit();">
		<ul data-role="listview">
			<li data-role="list-divider">
				<h3>';

	// Don't say "Profile" if this isn't the profile...
	if (!empty($context['profile_header_text']))
		echo '
					', $context['profile_header_text'];
	else
		echo '
					', $txt['profile'];

	echo '
				</h3>
			</li>';

	// Have we some description?
	if ($context['page_desc'])
		echo '
			<li><p>', $context['page_desc'], '</p></li>';

	// Start the big old loop 'of love.
	foreach ($context['profile_fields'] as $key => $field)
	{
		$lastItem = $field['type'];
		if ($lastItem == 'hr')
		{
			continue;
		}
		if ($field['type'] == 'callback')
		{
			if (isset($field['callback_func']) && function_exists('template_profile_' . $field['callback_func']))
			{
				$callback_func = 'template_profile_' . $field['callback_func'];
				$callback_func();
			}
		}
		else
		{
			echo '
			<li>
				<div data-role="fieldcontain">
				<strong', !empty($field['is_error']) ? ' class="error"' : '', '>', $field['type'] !== 'label' ? '<label for="' . $key . '">' : '', $field['label'], $field['type'] !== 'label' ? '</label>' : '', '</strong>';

			// Want to put something infront of the box?
			if (!empty($field['preinput']))
				echo '
							', $field['preinput'];

			// What type of data are we showing?
			if ($field['type'] == 'label')
				echo '
							', $field['value'];

			// Maybe it's a text box - very likely!
			elseif (in_array($field['type'], array('int', 'float', 'text', 'password')))
				echo '
				<input type="', $field['type'] == 'password' ? 'password' : 'text', '" name="', $key, '" id="', $key, '" size="', empty($field['size']) ? 30 : $field['size'], '" value="', $field['value'], '" ', $field['input_attr'], ' class="input_', $field['type'] == 'password' ? 'password' : 'text', '" />';

			// You "checking" me out? ;)
			elseif ($field['type'] == 'check')
				echo '
				<select name="', $key, '" id="', $key, '" data-role="slider">
					<option value="0">', $txt['no'], '</option>
					<option value="1" ', !empty($field['value']) ? ' selected="selected"' : '', '>', $txt['yes'], '</option>
				</select>';
			
			// Always fun - select boxes!
			elseif ($field['type'] == 'select')
			{
				echo '
				<select name="', $key, '" id="', $key, '">';

				if (isset($field['options']))
				{
					// Is this some code to generate the options?
					if (!is_array($field['options']))
						$field['options'] = eval($field['options']);
					// Assuming we now have some!
					if (is_array($field['options']))
						foreach ($field['options'] as $value => $name)
							echo '
						<option value="', $value, '" ', $value == $field['value'] ? 'selected="selected"' : '', '>', $name, '</option>';
				}

				echo '
				</select>';
			}

			// Something to end with?
			if (!empty($field['postinput']))
				echo '
							', $field['postinput'];

			echo '
				</div>
			</li>';
		}
	}

	// Are there any custom profile fields?
	if (!empty($context['custom_fields']))
	{
		foreach ($context['custom_fields'] as $field)
		{
			echo '
			<li>
				<strong>', $field['name'], ': </strong>
				<span class="smalltext">', $field['desc'], '</span>
				', $field['input_html'], '
			</li>';
		}
	}

	// Only show the password box if it's actually needed.
	if ($context['require_password'])
		echo '
			<li>
				<div data-role="fieldcontain">
					<strong', isset($context['modify_error']['bad_password']) || isset($context['modify_error']['no_password']) ? ' class="error"' : '', '><label for="oldpasswrd">', $txt['current_password'], ': </label></strong><br />
					<span class="smalltext">', $txt['required_security_reasons'], '</span>
					<input type="password" name="oldpasswrd" id="oldpasswrd" size="20" style="margin-right: 4ex;" class="input_password" />
				</div>
			</li>';

	echo '
		</ul>
		<br />';

	// The button shouldn't say "Change profile" unless we're changing the profile...
	if (!empty($context['submit_button_text']))
		echo '
			<input type="submit" name="save" value="', $context['submit_button_text'], '" class="button_submit" />';
	else
		echo '
			<input type="submit" name="save" value="', $txt['change_profile'], '" class="button_submit" />';

	if (!empty($context['token_check']))
		echo '
			<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '" />';

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			<input type="hidden" name="u" value="', $context['id_member'], '" />
			<input type="hidden" name="sa" value="', $context['menu_item_selected'], '" />
	</form>';

	// Some javascript!
	echo '
		<script>
			function checkProfileSubmit()
			{';

	// If this part requires a password, make sure to give a warning.
	if ($context['require_password'])
		echo '
				// Did you forget to type your password?
				if (document.forms.creator.oldpasswrd.value == "")
				{
					alert("', $txt['required_security_reasons'], '");
					return false;
				}';

	// Any onsubmit javascript?
	if (!empty($context['profile_onsubmit_javascript']))
		echo '
				', $context['profile_javascript'];

	echo '
			}';

	// Any totally custom stuff?
	if (!empty($context['profile_javascript']))
		echo '
			', $context['profile_javascript'];

	echo '
		</script>';

	// Any final spellchecking stuff?
	if (!empty($context['show_spellchecking']))
		echo '
		<form name="spell_form" id="spell_form" method="post" accept-charset="', $context['character_set'], '" target="spellWindow" action="', $scripturl, '?action=spellcheck"><input type="hidden" name="spellstring" value="" /></form>';
}

// Personal Message settings.
function template_profile_pm_settings()
{
	// Not allowed in mobile for now
	return;
}

// Template for showing theme settings. Note: template_options() actually adds the theme specific options.
function template_profile_theme_settings()
{
	// Not allowed in mobile for now
	return;
}

function template_notification()
{
	// Not allowed in mobile for now
	return;
}

// Template for choosing group membership.
function template_groupMembership()
{
	// Not allowed in mobile for now
	return;
}

function template_ignoreboards()
{
	// Not allowed in mobile for now
	return;
}

// Simple load some theme variables common to several warning templates.
function template_load_warning_variables()
{
	// Not allowed in mobile for now
	return;
}

// Show all warnings of a user?
function template_viewWarning()
{
	// Not allowed in mobile for now
	return;
}

// Show a lovely interface for issuing warnings.
function template_issueWarning()
{
	// Not allowed in mobile for now
	return;
}

// Template to show for deleting a users account - now with added delete post capability!
function template_deleteAccount()
{
	global $context, $settings, $txt, $scripturl;

	// The main containing header.
	echo '
		<form action="', $scripturl, '?action=profile;area=deleteaccount;save" method="post" accept-charset="', $context['character_set'], '" name="creator" id="creator">
			<div class="title_bar">
				<h3 class="titlebg">
					<img src="', $settings['images_url'], '/icons/profile_sm.png" alt="" class="icon" />', $txt['deleteAccount'], '
				</h3>
			</div>';

	// If deleting another account give them a lovely info box.
	if (!$context['user']['is_owner'])
		echo '
			<p class="windowbg2 description">', $txt['deleteAccount_desc'], '</p>';
	echo '
			<div class="windowbg2">
				<div class="content">';

	// If they are deleting their account AND the admin needs to approve it - give them another piece of info ;)
	if ($context['needs_approval'])
		echo '
					<div class="errorbox">', $txt['deleteAccount_approval'], '</div>';

	// If the user is deleting their own account warn them first - and require a password!
	if ($context['user']['is_owner'])
	{
		echo '
					<div class="alert">', $txt['own_profile_confirm'], '</div>
					<div>
						<strong', (isset($context['modify_error']['bad_password']) || isset($context['modify_error']['no_password']) ? ' class="error"' : ''), '>', $txt['current_password'], ': </strong>
						<input type="password" name="oldpasswrd" size="20" class="input_password" />&nbsp;&nbsp;&nbsp;&nbsp;
						<input type="submit" value="', $txt['yes'], '" class="button_submit" />';

		if (!empty($context['token_check']))
			echo '
				<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '" />';

		echo '
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="hidden" name="u" value="', $context['id_member'], '" />
						<input type="hidden" name="sa" value="', $context['menu_item_selected'], '" />
					</div>';
	}
	// Otherwise an admin doesn't need to enter a password - but they still get a warning - plus the option to delete lovely posts!
	else
	{
		echo '
					<div class="alert">', $txt['deleteAccount_warning'], '</div>';

		// Only actually give these options if they are kind of important.
		if ($context['can_delete_posts'])
			echo '
					<div>
						', $txt['deleteAccount_posts'], ':
						<select name="remove_type">
							<option value="none">', $txt['deleteAccount_none'], '</option>
							<option value="posts">', $txt['deleteAccount_all_posts'], '</option>
							<option value="topics">', $txt['deleteAccount_topics'], '</option>
						</select>
					</div>';

		echo '
					<div>
						<label for="deleteAccount"><input type="checkbox" name="deleteAccount" id="deleteAccount" value="1" class="input_check" onclick="if (this.checked) return confirm(\'', $txt['deleteAccount_confirm'], '\');" /> ', $txt['deleteAccount_member'], '.</label>
					</div>
					<div>
						<input type="submit" value="', $txt['delete'], '" class="button_submit" />';

		if (!empty($context['token_check']))
			echo '
				<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '" />';

		echo '
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="hidden" name="u" value="', $context['id_member'], '" />
						<input type="hidden" name="sa" value="', $context['menu_item_selected'], '" />
					</div>';
	}
	echo '
				</div>
			</div>
			<br />
		</form>';
}

// Template for the password box/save button stuck at the bottom of every profile page.
function template_profile_save()
{
	global $context, $txt;

	echo '

					<hr width="100%" size="1" class="hrcolor clear" />';

	// Only show the password box if it's actually needed.
	if ($context['require_password'])
		echo '
					<dl>
						<dt>
							<strong', isset($context['modify_error']['bad_password']) || isset($context['modify_error']['no_password']) ? ' class="error"' : '', '>', $txt['current_password'], ': </strong><br />
							<span class="smalltext">', $txt['required_security_reasons'], '</span>
						</dt>
						<dd>
							<input type="password" name="oldpasswrd" size="20" style="margin-right: 4ex;" class="input_password" />
						</dd>
					</dl>';

	echo '
					<div class="righttext">
						<input type="submit" value="', $txt['change_profile'], '" class="button_submit" />
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="hidden" name="u" value="', $context['id_member'], '" />
						<input type="hidden" name="sa" value="', $context['menu_item_selected'], '" />
					</div>';
}

// Small template for showing an error message upon a save problem in the profile.
function template_error_message()
{
	global $context, $txt;

	echo '
		<div ', empty($context['post_errors']) ? 'style="display:none" ' : '', 'id="profile_error">';

	if (!empty($context['post_errors']))
	{
		echo '
			<span>', !empty($context['custom_error_title']) ? $context['custom_error_title'] : $txt['profile_errors_occurred'], ':</span>
			<ul>';

		// Cycle through each error and display an error message.
		foreach ($context['post_errors'] as $error)
			echo '
				<li>', isset($txt['profile_error_' . $error]) ? $txt['profile_error_' . $error] : $error, '</li>';

		echo '
			</ul>';
	}

	echo '
		</div>';
}

// Display a load of drop down selectors for allowing the user to change group.
function template_profile_group_manage()
{
	global $context, $txt, $scripturl;

	echo '
				<li>
					<div data-role="fieldcontain">
						<strong>', $txt['primary_membergroup'], ': </strong><br />
						<span>[<a href="', $scripturl, '?action=helpadmin;help=moderator_why_missing" onclick="return reqWin(this.href);">', $txt['moderator_why_missing'], '</a>]&nbsp;</span>
						<select name="id_group" ', ($context['user']['is_owner'] && $context['member']['group_id'] == 1 ? 'onchange="if (this.value != 1 &amp;&amp; !confirm(\'' . $txt['deadmin_confirm'] . '\')) this.value = 1;"' : ''), '>';

	// Fill the select box with all primary member groups that can be assigned to a member.
	foreach ($context['member_groups'] as $member_group)
		if (!empty($member_group['can_be_primary']))
			echo '
							<option value="', $member_group['id'], '"', $member_group['is_primary'] ? ' selected="selected"' : '', '>
								', $member_group['name'], '
							</option>';
	echo '
						</select>
					</div>
					<div data-role="collapsible" data-inset="false">
						<h3>', $txt['additional_membergroups'], ':</h3>

						<p>
							<input type="hidden" name="additional_groups[]" value="0" />';

	// For each membergroup show a checkbox so members can be assigned to more than one group.
	foreach ($context['member_groups'] as $member_group)
		if ($member_group['can_be_additional'])
			echo '
							<label for="additional_groups-', $member_group['id'], '"><input type="checkbox" name="additional_groups[]" value="', $member_group['id'], '" id="additional_groups-', $member_group['id'], '"', $member_group['is_additional'] ? ' checked="checked"' : '', ' class="input_check" /> ', $member_group['name'], '</label><br />';
	echo '
						</p>
					</div>
				</li>';
}

// Callback function for entering a birthdate!
function template_profile_birthdate()
{
	global $txt, $context;

	// Just show the pretty box!
	echo '
				<li>
					<strong>', $txt['dob'], ':</strong>
					<div data-role="fieldcontain">
						', $txt['dob_year'], '
						<input type="text" name="bday3" size="4" maxlength="4" value="', $context['member']['birth_date']['year'], '" />
					</div>
					<div data-role="fieldcontain">
						', $txt['dob_month'], '
						<input type="text" name="bday1" size="2" maxlength="2" value="', $context['member']['birth_date']['month'], '" />
					</div>
					<div data-role="fieldcontain">
						', $txt['dob_day'], '
						<input type="text" name="bday2" size="2" maxlength="2" value="', $context['member']['birth_date']['day'], '" />
					</div>
				</li>';
}

// Show the signature editing box?
function template_profile_signature_modify()
{
	global $txt, $context, $settings;

	echo '
				<li>
					<div id="current_signature"', !isset($context['member']['current_signature']) ? ' style="display:none"' : '', '>
						<strong>', $txt['current_signature'], ':</strong>
					</div>
					<div id="current_signature_display"', !isset($context['member']['current_signature']) ? ' style="display:none"' : '', '>
						', isset($context['member']['current_signature']) ? $context['member']['current_signature'] : '', '<hr />
					</div>';
	echo '
					<div id="preview_signature"', !isset($context['member']['signature_preview']) ? ' style="display:none"' : '', '>
						<strong>', $txt['signature_preview'], ':</strong>
					</div>
					<div id="preview_signature_display"', !isset($context['member']['signature_preview']) ? ' style="display:none"' : '', '>
						', isset($context['member']['signature_preview']) ? $context['member']['signature_preview'] : '', '<hr />
					</div>';

	echo '

					<strong>', $txt['signature'], ':</strong><br />
					<span class="smalltext">', $txt['sig_info'], '</span><br />
					<br />';

	if ($context['show_spellchecking'])
		echo '
					<input type="button" value="', $txt['spell_check'], '" onclick="spellCheck(\'creator\', \'signature\');" class="button_submit" />';

	echo '
					<textarea class="editor" onkeyup="calcCharLeft();" id="signature" name="signature" rows="5" cols="50" style="min-width: 50%; max-width: 99%;">', $context['member']['signature'], '</textarea><br />';

	// If there is a limit at all!
	if (!empty($context['signature_limits']['max_length']))
		echo '
					<span class="smalltext">', sprintf($txt['max_sig_characters'], $context['signature_limits']['max_length']), ' <span id="signatureLeft">', $context['signature_limits']['max_length'], '</span></span><br />';

	if (!empty($context['show_preview_button']))
		echo '
					<input type="submit" name="preview_signature" id="preview_button" value="', $txt['preview_signature'], '" class="button_submit" />';

	if ($context['signature_warning'])
		echo '
					<span class="smalltext">', $context['signature_warning'], '</span>';

	// Load the spell checker?
	if ($context['show_spellchecking'])
		echo '
					<script src="', $settings['default_theme_url'], '/scripts/spellcheck.js"></script>';

	// Some javascript used to count how many characters have been used so far in the signature.
	echo '
					<script>
						var maxLength = ', $context['signature_limits']['max_length'], ';

						$(document).on("pageinit", function () {
							calcCharLeft();
							$("#preview_button").click(function() {
								return ajax_getSignaturePreview(true);
							});
						});
					</script>
				</li>';
}

function template_profile_avatar_select()
{
	global $context, $txt, $modSettings;

	// Start with the upper menu
	echo '
				<li>
					<strong id="personal_picture"><label for="avatar_upload_box">', $txt['personal_picture'], '</label></strong>
					<input type="radio" onclick="swap_avatar(this); return true;" name="avatar_choice" id="avatar_choice_none" value="none"' . ($context['member']['avatar']['choice'] == 'none' ? ' checked="checked"' : '') . ' class="input_radio" /><label for="avatar_choice_none"' . (isset($context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '>' . $txt['no_avatar'] . '</label><br />
					', !empty($context['member']['avatar']['allow_server_stored']) ? '<input type="radio" onclick="swap_avatar(this); return true;" name="avatar_choice" id="avatar_choice_server_stored" value="server_stored"' . ($context['member']['avatar']['choice'] == 'server_stored' ? ' checked="checked"' : '') . ' class="input_radio" /><label for="avatar_choice_server_stored"' . (isset($context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '>' . $txt['choose_avatar_gallery'] . '</label><br />' : '', '
					', !empty($context['member']['avatar']['allow_external']) ? '<input type="radio" onclick="swap_avatar(this); return true;" name="avatar_choice" id="avatar_choice_external" value="external"' . ($context['member']['avatar']['choice'] == 'external' ? ' checked="checked"' : '') . ' class="input_radio" /><label for="avatar_choice_external"' . (isset($context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '>' . $txt['my_own_pic'] . '</label><br />' : '', '
					', !empty($context['member']['avatar']['allow_upload']) ? '<input type="radio" onclick="swap_avatar(this); return true;" name="avatar_choice" id="avatar_choice_upload" value="upload"' . ($context['member']['avatar']['choice'] == 'upload' ? ' checked="checked"' : '') . ' class="input_radio" /><label for="avatar_choice_upload"' . (isset($context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '>' . $txt['avatar_will_upload'] . '</label>' : '';

	// If users are allowed to choose avatars stored on the server show selection boxes to choice them from.
	if (!empty($context['member']['avatar']['allow_server_stored']))
	{
		echo '
					<div id="avatar_server_stored">
						<div>
							<select name="cat" id="cat" onchange="changeSel(\'\');" onfocus="selectRadioByName(document.forms.creator.avatar_choice, \'server_stored\');">';
		// This lists all the file categories.
		foreach ($context['avatars'] as $avatar)
			echo '
								<option value="', $avatar['filename'] . ($avatar['is_dir'] ? '/' : ''), '"', ($avatar['checked'] ? ' selected="selected"' : ''), '>', $avatar['name'], '</option>';
		echo '
							</select>
						</div>
						<div>
							<select name="file" id="file" style="display: none;" onchange="showAvatar()" onfocus="selectRadioByName(document.forms.creator.avatar_choice, \'server_stored\');" disabled="disabled"><option></option></select>
						</div>
						<div><img id="avatar" src="', !empty($context['member']['avatar']['allow_external']) && $context['member']['avatar']['choice'] == 'external' ? $context['member']['avatar']['external'] : $modSettings['avatar_url'] . '/blank.png', '" alt="Do Nothing" /></div>
						<script>
							var files = ["' . implode('", "', $context['avatar_list']) . '"];
							var avatar = document.getElementById("avatar");
							var cat = document.getElementById("cat");
							var selavatar = "' . $context['avatar_selected'] . '";
							var avatardir = "' . $modSettings['avatar_url'] . '/";
							var size = avatar.alt.substr(3, 2) + " " + avatar.alt.substr(0, 2) + String.fromCharCode(117, 98, 116);
							var file = document.getElementById("file");
							var maxHeight = ', !empty($modSettings['avatar_max_height_external']) ? $modSettings['avatar_max_height_external'] : 0, ';
							var maxWidth = ', !empty($modSettings['avatar_max_width_external']) ? $modSettings['avatar_max_width_external'] : 0, ';

							if (avatar.src.indexOf("blank.png") > -1)
								changeSel(selavatar);
							else
								previewExternalAvatar(avatar.src)

						</script>
					</div>';
	}
	
	// If the user can link to an off server avatar, show them a box to input the address.
	if (!empty($context['member']['avatar']['allow_external']))
	{
		echo '
					<div id="avatar_external">
						<div class="smalltext">', $txt['avatar_by_url'], '</div>
						<input type="text" name="userpicpersonal" size="45" value="', $context['member']['avatar']['external'], '" onfocus="selectRadioByName(document.forms.creator.avatar_choice, \'external\');" onchange="if (typeof(previewExternalAvatar) != \'undefined\') previewExternalAvatar(this.value);" class="input_text" />
					</div>';
	}

	// If the user is able to upload avatars to the server show them an upload box.
	if (!empty($context['member']['avatar']['allow_upload']))
	{
		echo '
					<div id="avatar_upload">
						<input type="file" size="44" name="attachment" id="avatar_upload_box" value="" onfocus="selectRadioByName(document.forms.creator.avatar_choice, \'upload\');" class="input_file" />
						', ($context['member']['avatar']['id_attach'] > 0 ? '<br /><br /><img src="' . $context['member']['avatar']['href'] . (strpos($context['member']['avatar']['href'], '?') === false ? '?' : '&amp;') . 'time=' . time() . '" alt="" /><input type="hidden" name="id_attach" value="' . $context['member']['avatar']['id_attach'] . '" />' : ''), '
					</div>';
	}

	echo '
					<script>
						', !empty($context['member']['avatar']['allow_server_stored']) ? '$("#avatar_server_stored").' . ($context['member']['avatar']['choice'] == 'server_stored' ? 'show()' : 'hide()') . ';' : '', '
						', !empty($context['member']['avatar']['allow_external']) ? '$("#avatar_external").' . ($context['member']['avatar']['choice'] == 'external' ? 'show()' : 'hide()') . ';' : '', '
						', !empty($context['member']['avatar']['allow_upload']) ? '$("#avatar_upload").' . ($context['member']['avatar']['choice'] == 'upload' ? 'show()' : 'hide()') . ';' : '', '

						function swap_avatar(type)
						{
							switch(type.id)
							{
								case "avatar_choice_server_stored":
									', !empty($context['member']['avatar']['allow_server_stored']) ? '$("#avatar_server_stored").show();' : '', '
									', !empty($context['member']['avatar']['allow_external']) ? '$("#avatar_external").hide();' : '', '
									', !empty($context['member']['avatar']['allow_upload']) ? '$("#avatar_upload").hide();' : '', '
									break;
								case "avatar_choice_external":
									', !empty($context['member']['avatar']['allow_server_stored']) ? '$("#avatar_server_stored").hide();' : '', '
									', !empty($context['member']['avatar']['allow_external']) ? '$("#avatar_external").show();' : '', '
									', !empty($context['member']['avatar']['allow_upload']) ? '$("#avatar_upload").hide();' : '', '
									break;
								case "avatar_choice_upload":
									', !empty($context['member']['avatar']['allow_server_stored']) ? '$("#avatar_server_stored").hide();' : '', '
									', !empty($context['member']['avatar']['allow_external']) ? '$("#avatar_external").hide();' : '', '
									', !empty($context['member']['avatar']['allow_upload']) ? '$("#avatar_upload").show();' : '', '
									break;
								case "avatar_choice_none":
									', !empty($context['member']['avatar']['allow_server_stored']) ? '$("#avatar_server_stored").hide();' : '', '
									', !empty($context['member']['avatar']['allow_external']) ? '$("#avatar_external").hide();' : '', '
									', !empty($context['member']['avatar']['allow_upload']) ? '$("#avatar_upload").hide();' : '', '
									break;
							}
						}
					</script>
				</li>';
}

// Callback for modifying karma.
function template_profile_karma_modify()
{
	global $context, $modSettings, $txt;

	echo '
				<li>
					<strong>', $modSettings['karmaLabel'], '</strong>

					', $modSettings['karmaApplaudLabel'], ' <input type="text" name="karma_good" size="4" value="', $context['member']['karma']['good'], '" onchange="setInnerHTML(document.getElementById(\'karmaTotal\'), this.value - this.form.karma_bad.value);" style="margin-right: 2ex;" class="input_text" /> ', $modSettings['karmaSmiteLabel'], ' <input type="text" name="karma_bad" size="4" value="', $context['member']['karma']['bad'], '" onchange="this.form.karma_good.onchange();" class="input_text" /><br />
					(', $txt['total'], ': <span id="karmaTotal">', ($context['member']['karma']['good'] - $context['member']['karma']['bad']), '</span>)
				</li>';
}


// Select the time format!
function template_profile_timeformat_modify()
{
	global $context, $txt, $scripturl, $settings;

	echo '
				<li>
					<strong><label for="easyformat">', $txt['time_format'], ':</label></strong><br />
					<a href="', $scripturl, '?action=helpadmin;help=time_format" onclick="return reqWin(this.href);" class="help"><img src="', $settings['images_url'], '/helptopics.png" alt="', $txt['help'], '" class="floatleft" /></a>
					<span class="smalltext">&nbsp;<label for="time_format">', $txt['date_format'], '</label></span>

					<select name="easyformat" id="easyformat" onchange="document.forms.creator.time_format.value = this.options[this.selectedIndex].value;" style="margin-bottom: 4px;">';
	// Help the user by showing a list of common time formats.
	foreach ($context['easy_timeformats'] as $time_format)
		echo '
					<option value="', $time_format['format'], '"', $time_format['format'] == $context['member']['time_format'] ? ' selected="selected"' : '', '>', $time_format['title'], '</option>';
	echo '
					</select><br />
					<input type="text" name="time_format" id="time_format" value="', $context['member']['time_format'], '" size="30" class="input_text" />
				</li>';
}

// Time offset?
function template_profile_timeoffset_modify()
{
	global $txt, $context;

	echo '
				<li>
					<strong', (isset($context['modify_error']['bad_offset']) ? ' class="error"' : ''), '><label for="time_offset">', $txt['time_offset'], ':</label></strong><br />
					<span class="smalltext">', $txt['personal_time_offset'], '</span>

					<input type="text" name="time_offset" id="time_offset" size="5" maxlength="5" value="', $context['member']['time_offset'], '" class="input_text" /> ', $txt['hours'], ' [<a href="javascript:void(0);" onclick="currentDate = new Date(', $context['current_forum_time_js'], '); document.getElementById(\'time_offset\').value = autoDetectTimeOffset(currentDate); return false;">', $txt['timeoffset_autodetect'], '</a>]<br />', $txt['current_time'], ': <em>', $context['current_forum_time'], '</em>
				</li>';
}

// Theme?
function template_profile_theme_pick()
{
	// Not allowed in mobile for now
	return;
}

// Smiley set picker.
function template_profile_smiley_pick()
{
	global $txt, $context, $modSettings, $settings;

	echo '
				<li>
					<strong><label for="smiley_set">', $txt['smileys_current'], ':</label></strong>

					<select name="smiley_set" id="smiley_set" onchange="document.getElementById(\'smileypr\').src = this.selectedIndex == 0 ? \'', $settings['images_url'], '/blank.png\' : \'', $modSettings['smileys_url'], '/\' + (this.selectedIndex != 1 ? this.options[this.selectedIndex].value : \'', !empty($settings['smiley_sets_default']) ? $settings['smiley_sets_default'] : $modSettings['smiley_sets_default'], '\') + \'/smiley.gif\';">';
	foreach ($context['smiley_sets'] as $set)
		echo '
						<option value="', $set['id'], '"', $set['selected'] ? ' selected="selected"' : '', '>', $set['name'], '</option>';
	echo '
					</select> <img id="smileypr" class="centericon" src="', $context['member']['smiley_set']['id'] != 'none' ? $modSettings['smileys_url'] . '/' . ($context['member']['smiley_set']['id'] != '' ? $context['member']['smiley_set']['id'] : (!empty($settings['smiley_sets_default']) ? $settings['smiley_sets_default'] : $modSettings['smiley_sets_default'])) . '/smiley.gif' : $settings['images_url'] . '/blank.png', '" alt=":)"  style="padding-left: 20px;" />
				</li>';
}

// Change the way you login to the forum.
function template_authentication_method()
{
	global $context, $settings, $scripturl, $modSettings, $txt;

	// The main header!
	echo '
		<script src="', $settings['default_theme_url'], '/scripts/register.js"></script>
		<form action="', $scripturl, '?action=profile;area=authentication;save" method="post" accept-charset="', $context['character_set'], '" name="creator" id="creator" enctype="multipart/form-data">
			<div class="cat_bar">
				<h3 class="catbg">
					<img src="', $settings['images_url'], '/icons/profile_sm.png" alt="" class="icon" />', $txt['authentication'], '
				</h3>
			</div>
			<p class="windowbg description">', $txt['change_authentication'], '</p>
			<div class="windowbg2">
				<div class="content">
					<dl>
						<dt>
							<input type="radio" onclick="updateAuthMethod();" name="authenticate" value="openid" id="auth_openid"', $context['auth_method'] == 'openid' ? ' checked="checked"' : '', ' class="input_radio" /><label for="auth_openid"><strong>', $txt['authenticate_openid'], '</strong></label>&nbsp;<em><a href="', $scripturl, '?action=helpadmin;help=register_openid" onclick="return reqWin(this.href);" class="help">(?)</a></em><br />
							<input type="radio" onclick="updateAuthMethod();" name="authenticate" value="passwd" id="auth_pass"', $context['auth_method'] == 'password' ? ' checked="checked"' : '', ' class="input_radio" /><label for="auth_pass"><strong>', $txt['authenticate_password'], '</strong></label>
						</dt>
						<dd>
							<dl id="auth_openid_div">
								<dt>
									<em>', $txt['authenticate_openid_url'], ':</em>
								</dt>
								<dd>
									<input type="text" name="openid_identifier" id="openid_url" size="30" tabindex="', $context['tabindex']++, '" value="', $context['member']['openid_uri'], '" class="input_text openid_login" />
								</dd>
							</dl>
							<dl id="auth_pass_div">
								<dt>
									<em>', $txt['choose_pass'], ':</em>
								</dt>
								<dd>
									<input type="password" name="passwrd1" id="smf_autov_pwmain" size="30" tabindex="', $context['tabindex']++, '" class="input_password" />
									<span id="smf_autov_pwmain_div" style="display: none;"><img id="smf_autov_pwmain_img" src="', $settings['images_url'], '/icons/field_invalid.png" alt="*" /></span>
								</dd>
								<dt>
									<em>', $txt['verify_pass'], ':</em>
								</dt>
								<dd>
									<input type="password" name="passwrd2" id="smf_autov_pwverify" size="30" tabindex="', $context['tabindex']++, '" class="input_password" />
									<span id="smf_autov_pwverify_div" style="display: none;"><img id="smf_autov_pwverify_img" src="', $settings['images_url'], '/icons/field_valid.png" alt="*" /></span>
								</dd>
							</dl>
						</dd>
					</dl>';

	if ($context['require_password'])
		echo '
					<hr width="100%" size="1" class="hrcolor clear" />
					<dl>
						<dt>
							<strong', isset($context['modify_error']['bad_password']) || isset($context['modify_error']['no_password']) ? ' class="error"' : '', '>', $txt['current_password'], ': </strong><br />
							<span class="smalltext">', $txt['required_security_reasons'], '</span>
						</dt>
						<dd>
							<input type="password" name="oldpasswrd" tabindex="', $context['tabindex']++, '" size="20" style="margin-right: 4ex;" class="input_password" />
						</dd>
					</dl>';

	echo '
					<hr class="hrcolor" />';

	if (!empty($context['token_check']))
		echo '
					<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '" />';

	echo '
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="u" value="', $context['id_member'], '" />
					<input type="hidden" name="sa" value="', $context['menu_item_selected'], '" />
					<input type="submit" value="', $txt['change_profile'], '" class="button_submit" />
					<br class="clear_right" />
				</div>
			</div>
		</form>';

	// The password stuff.
	echo '
	<script type="text/javascript"><!-- // --><![CDATA[
	var regTextStrings = {
		"password_short": "', $txt['registration_password_short'], '",
		"password_reserved": "', $txt['registration_password_reserved'], '",
		"password_numbercase": "', $txt['registration_password_numbercase'], '",
		"password_no_match": "', $txt['registration_password_no_match'], '",
		"password_valid": "', $txt['registration_password_valid'], '"
	};
	var verificationHandle = new smfRegister("creator", ', empty($modSettings['password_strength']) ? 0 : $modSettings['password_strength'], ', regTextStrings);
	var currentAuthMethod = \'passwd\';
	updateAuthMethod();
	// ]]></script>';
}

?>