<?php

/**
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
 * @version 1.0 Alpha
 */

/**
 * Template for the profile side bar - goes before any other profile template.
 */
function template_profile_above()
{
	global $context, $settings;

	echo '
	<script src="', $settings['default_theme_url'], '/scripts/profile.js"></script>';

	// Prevent Chrome from auto completing fields when viewing/editing other members profiles
	if (isBrowser('is_chrome') && !$context['user']['is_owner'])
		echo '
	<script><!-- // --><![CDATA[
		disableAutoComplete();
	// ]]></script>';

	// If an error occurred while trying to save previously, give the user a clue!
	echo '
					', template_error_message();

	// If the profile was update successfully, let the user know this.
	if (!empty($context['profile_updated']))
		echo '
					<div class="infobox">
						', $context['profile_updated'], '
					</div>';
}

/**
 * Template for showing all the drafts of the user.
 */
function template_showDrafts()
{
	global $context, $settings, $scripturl, $txt;

	template_pagesection(false, false, 'go_down');

	// No drafts? Just show an informative message.
	if (empty($context['drafts']))
		echo '
		<div class="tborder windowbg2 padding centertext">
			', $txt['draft_none'], '
		</div>';
	else
	{
		// For every draft to be displayed, give it its own div, and show the important details of the draft.
		foreach ($context['drafts'] as $draft)
		{
			echo '
			<div class="', $draft['alternate'] === 0 ? 'windowbg2' : 'windowbg', ' core_posts">
				<div class="content">
					<div class="counter">', $draft['counter'], '</div>
					<div class="topic_details">
						<h5><strong><a href="', $scripturl, '?board=', $draft['board']['id'], '.0">', $draft['board']['name'], '</a> / ', $draft['topic']['link'], '</strong>&nbsp;&nbsp;';

			if (!empty($draft['sticky']))
				echo '<img src="', $settings['images_url'], '/icons/quick_sticky.png" alt="', $txt['sticky_topic'], '" title="', $txt['sticky_topic'], '" />';

			if (!empty($draft['locked']))
				echo '<img src="', $settings['images_url'], '/icons/quick_lock.png" alt="', $txt['locked_topic'], '" title="', $txt['locked_topic'], '" />';

			echo '
						</h5>
						<span class="smalltext">&#171;&nbsp;<strong>', $txt['draft_saved_on'], ':</strong> ', ($draft['age'] > 0 ? sprintf($txt['draft_days_ago'], $draft['age']) : $draft['time']), (!empty($draft['remaining']) ? ', ' . sprintf($txt['draft_retain'], $draft['remaining']) : ''), '&#187;</span>
					</div>
					<div class="list_posts">
						', $draft['body'], '
					</div>
					<div class="floatright">
						<ul class="quickbuttons">
							<li>
								<a href="', $scripturl, '?action=post;', (empty($draft['topic']['id']) ? 'board=' . $draft['board']['id'] : 'topic=' . $draft['topic']['id']), '.0;id_draft=', $draft['id_draft'], '" class="reply_button"><span>', $txt['draft_edit'], '</span></a>
							</li>
							<li>
								<a href="', $scripturl, '?action=profile;u=', $context['member']['id'], ';area=showdrafts;delete=', $draft['id_draft'], ';start=', $context['start'], ';', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['draft_remove'], '?\');" class="remove_button"><span>', $txt['draft_delete'], '</span></a>
							</li>
						</ul>
					</div>
				</div>
			</div>';
		}
	}

	// Show page numbers.
	template_pagesection();
}

/**
 * Template for the password box/save button stuck at the bottom of every profile page.
 */
function template_profile_save()
{
	global $context, $txt;

	echo '

					<hr class="hrcolor clear" style="width: 100%; height: 1px" />';

	// Only show the password box if it's actually needed.
	if ($context['require_password'])
		echo '
					<dl>
						<dt>
							<strong', isset($context['modify_error']['bad_password']) || isset($context['modify_error']['no_password']) ? ' class="error"' : '', '>', $txt['current_password'], ': </strong><br />
							<span class="smalltext">', $txt['required_security_reasons'], '</span>
						</dt>
						<dd>
							<input type="password" name="oldpasswrd" size="20" style="margin-right: 4ex;" class="input_password" placeholder="', $txt['current_password'], '" />
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

/**
 * Small template for showing an error message upon a save problem in the profile.
 */
function template_error_message()
{
	global $context, $txt;

	echo '
		<div class="errorbox" ', empty($context['post_errors']) ? 'style="display:none" ' : '', 'id="profile_error">';

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