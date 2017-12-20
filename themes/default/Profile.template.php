<?php

/**
 * @name      ElkArte Forum
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

/**
 * We need some trick to proprerly display things
 */
function template_Profile_init()
{
	theme()->getTemplates()->load('GenericMessages');
}

/**
 * Template for the profile header - goes before any other profile template.
 */
function template_profile_above()
{
	global $context;

	// Prevent browssers from auto completing fields when viewing/editing other members profiles
	if (!$context['user']['is_owner'])
		theme()->addInlineJavascript('disableAutoComplete();', true);

	// If an error occurred while trying to save previously, give the user a clue!
	template_error_message();

	// If the profile was update successfully, let the user know this.
	if (!empty($context['profile_updated']))
		echo '
					<div class="successbox">
						', $context['profile_updated'], '
					</div>';
}

/**
 * Template for showing all the drafts of the user.
 */
function template_showDrafts()
{
	global $context, $settings, $txt, $scripturl;

	if (!empty($context['drafts']))
		template_pagesection();

	echo '
		<div id="recentposts" class="profile_center">
			<form action="', $scripturl, '?action=profile;u=' . $context['member']['id'] . ';area=showdrafts;delete" method="post" accept-charset="UTF-8" name="draftForm" id="draftForm" >
				<h2 class="category_header">
					<span class="floatright">
						<input type="checkbox" onclick="invertAll(this, this.form, \'delete[]\');" />
					</span>
					', $txt['drafts'], '
				</h2>';

	// No drafts? Just show an informative message.
	if (empty($context['drafts']))
		echo '
			<div class="information centertext">
				', $txt['draft_none'], '
			</div>';
	else
	{
		// For every draft to be displayed show the important details.
		foreach ($context['drafts'] as $draft)
		{
			$draft['title'] = '<strong>' . $draft['board']['link'] . ' / ' . $draft['topic']['link'] . '</strong>&nbsp;&nbsp;';

			if (!empty($draft['sticky']))
				$draft['title'] .= '<img src="' . $settings['images_url'] . '/icons/quick_sticky.png" alt="' . $txt['sticky_topic'] . '" title="' . $txt['sticky_topic'] . '" />';

			if (!empty($draft['locked']))
				$draft['title'] .= '<img src="' . $settings['images_url'] . '/icons/quick_lock.png" alt="' . $txt['locked_topic'] . '" title="' . $txt['locked_topic'] . '" />';

			$draft['date'] = '&#171; <strong>' . $txt['draft_saved_on'] . ':</strong> ' . ($draft['age'] > 0 ? sprintf($txt['draft_days_ago'], $draft['age']) : $draft['time']) . (!empty($draft['remaining']) ? ', ' . sprintf($txt['draft_retain'], $draft['remaining']) : '') . ' &#187;';
			$draft['class'] = 'content';

			template_simple_message($draft);
		}
	}

	// Show page numbers
	if (!empty($context['drafts']))
	{
		template_pagesection(false, '', array('extra' => '
			<div class="floatright">
				<input type="submit" name="delete_selected" value="' . $txt['quick_mod_remove'] . '" class="right_submit" onclick="return confirm(' . JavaScriptEscape($txt['draft_remove_selected'] . '?') . ');" />
				<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" />
			</div>'
		));
	}

	echo '
			</form>
		</div>';
}

/**
 * Template for the password box/save button stuck at the bottom of every profile page.
 */
function template_profile_save()
{
	global $context, $txt;

	// Only show the password box if it's actually needed.
	if ($context['require_password'])
		echo '
					<dl class="clear">
						<dt>
							<span', isset($context['modify_error']['bad_password']) || isset($context['modify_error']['no_password']) ? ' class="error"' : '', '><label for="oldpasswrd">', $txt['current_password'], '</label>: </span><br />
							<span class="smalltext">', $txt['required_security_reasons'], '</span>
						</dt>
						<dd>
							<input type="password" id="oldpasswrd" name="oldpasswrd" size="20" class="input_password" placeholder="', $txt['current_password'], '" />
						</dd>
					</dl>';

	echo '
					<div class="submitbutton">';

	if (!empty($context['token_check']))
		echo '
						<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '" />';

	// The button shouldn't say "Change profile" unless we're changing the profile...
	if (!empty($context['submit_button_text']))
		echo '
						<input type="submit" name="save" value="', $context['submit_button_text'], '" />';
	else
		echo '
						<input type="submit" name="save" value="', $txt['change_profile'], '" />';

	echo '
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="hidden" name="u" value="', $context['id_member'], '" />
						<input type="hidden" name="sa" value="', $context['menu_item_selected'], '" />
					</div>';
}

/**
 * Small template for showing an error message upon a save problem in the profile.
 */
function template_error_message()
{
	global $context, $txt;

	echo '
		<div id="profile_error" class="errorbox', empty($context['post_errors']) ? ' hide"' : '"', '>';

	if (!empty($context['post_errors']))
	{
		echo '
			<span>', !empty($context['custom_error_title']) ? $context['custom_error_title'] : $txt['profile_errors_occurred'], ':</span>
			<ul id="list_errors">';

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

/**
 * Simple load some theme variables common to several warning templates.
 */
function template_load_warning_variables()
{
	global $modSettings, $context;

	$context['warningBarWidth'] = 200;

	// Setup the colors - this is a little messy for theming.
	$context['colors'] = array(
		0 => 'green',
		$modSettings['warning_watch'] => 'green',
		$modSettings['warning_moderate'] => 'orange',
		$modSettings['warning_mute'] => 'red',
	);

	// Work out the starting color.
	$context['current_color'] = $context['colors'][0];
	foreach ($context['colors'] as $limit => $color)
		if ($context['member']['warning'] >= $limit)
			$context['current_color'] = $color;
}
