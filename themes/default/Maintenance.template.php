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
 * @version 1.0 Release Candidate 2
 */

/**
 * Start things off with some generic templates we can use
 */
function template_Maintenance_init()
{
	loadTemplate('GenericHelpers');
}

/**
 * Template for the database maintenance tasks.
 */
function template_maintain_database()
{
	global $context, $settings, $txt, $scripturl;

	// If maintenance has finished tell the user.
	if (!empty($context['maintenance_finished']))
		echo '
			<div class="successbox">
				', sprintf($txt['maintain_done'], $context['maintenance_finished']), '
			</div>';

	echo '
	<div id="manage_maintenance">
		<h2 class="category_header">', $txt['maintain_optimize'], '</h2>
		<div class="windowbg">
			<div class="content">
				<form action="', $scripturl, '?action=admin;area=maintain;sa=database;activity=optimize" method="post" accept-charset="UTF-8">
					<p>', $txt['maintain_optimize_info'], '</p>
					<div class="submitbutton">
						<input type="submit" value="', $txt['maintain_run_now'], '" class="button_submit" />
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="hidden" name="', $context['admin-maint_token_var'], '" value="', $context['admin-maint_token'], '" />
					</div>
				</form>
			</div>
		</div>
		<h3 class="category_header">
			<a href="', $scripturl, '?action=quickhelp;help=maintenance_backup" onclick="return reqOverlayDiv(this.href);" class="help">
				<img src="', $settings['images_url'], '/helptopics.png" class="icon" alt="', $txt['help'], '" />
			</a> ', $txt['maintain_backup'], '
		</h3>
		<div class="windowbg">
			<div class="content">
				<form action="', $scripturl, '?action=admin;area=maintain;sa=database;activity=backup" method="post" accept-charset="UTF-8">
					<p>', $txt['maintain_backup_info'], '</p>';

	if ($context['safe_mode_enable'])
		echo '
				<div class="errorbox">', $txt['safe_mode_enabled'], '</div>';
	else
		echo '
					<div class="', $context['suggested_method'] == 'use_external_tool' || $context['use_maintenance'] != 0 ? 'errorbox' : 'infobox', '">
					', $txt[$context['suggested_method']],
		$context['use_maintenance'] != 0 ? '<br />' . $txt['enable_maintenance' . $context['use_maintenance']] : '',
		'</div>';

	echo '
					<p>
						<label for="struct"><input type="checkbox" name="struct" id="struct" onclick="document.getElementById(\'submitDump\').disabled = !document.getElementById(\'struct\').checked &amp;&amp; !document.getElementById(\'data\').checked;" class="input_check" checked="checked" /> ', $txt['maintain_backup_struct'], '</label><br />
						<label for="data"><input type="checkbox" name="data" id="data" onclick="document.getElementById(\'submitDump\').disabled = !document.getElementById(\'struct\').checked &amp;&amp; !document.getElementById(\'data\').checked;" checked="checked" class="input_check" /> ', $txt['maintain_backup_data'], '</label><br />
						<label for="compress"><input type="checkbox" name="compress" id="compress" value="gzip"', $context['suggested_method'] == 'zipped_file' ? ' checked="checked"' : '', ' class="input_check" /> ', $txt['maintain_backup_gz'], '</label>
					</p>
					<div class="submitbutton">
						<input ', $context['use_maintenance'] == 2 ? 'disabled="disabled" ' : '', 'type="submit" value="', $txt['maintain_backup_save'], '" id="submitDump" onclick="return document.getElementById(\'struct\').checked || document.getElementById(\'data\').checked;" class="button_submit" />';

	echo '
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="hidden" name="', $context['admin-maint_token_var'], '" value="', $context['admin-maint_token'], '" />
					</div>
				</form>
			</div>
		</div>';

	// Show an option to convert the body column of the post table to MEDIUMTEXT or TEXT
	if (isset($context['convert_to']))
		echo '
		<h3 class="category_header">', $txt[$context['convert_to'] . '_title'], '</h3>
		<div class="windowbg">
			<div class="content">
				<form action="', $scripturl, '?action=admin;area=maintain;sa=database;activity=convertmsgbody" method="post" accept-charset="UTF-8">
					<p>', $txt['mediumtext_introduction'], '</p>',
					$context['convert_to_suggest'] ? '<p class="successbox">' . $txt['convert_to_suggest_text'] . '</p>' : '', '
					<div class="submitbutton">
						<input type="submit" name="evaluate_conversion" value="', $txt['maintain_run_now'], '" class="button_submit" />
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="hidden" name="', $context['admin-maint_token_var'], '" value="', $context['admin-maint_token'], '" />
					</div>
				</form>
			</div>
		</div>';

	echo '
	</div>';
}

/**
 * Template for the routine maintenance tasks.
 */
function template_maintain_routine()
{
	global $context, $txt;

	// Starts off with general maintenance procedures.
	echo '
	<div id="manage_maintenance">';

	// If maintenance has finished tell the user.
	if (!empty($context['maintenance_finished']))
		echo '
			<div class="successbox">
				', sprintf($txt['maintain_done'], $context['maintenance_finished']), '
			</div>';

	foreach ($context['routine_actions'] as $action)
	{
		echo '
		<h3 class="category_header">', $action['title'], '</h3>
		<div class="windowbg">
			<div class="content">
				<form action="', $action['url'], '" method="post" accept-charset="UTF-8">
					<p>', $action['description'], '</p>
					<div class="submitbutton">
						<input type="submit" value="', $action['submit'], '" class="button_submit" />';

		if (!empty($action['hidden']))
			foreach ($action['hidden'] as $name => $val)
				echo '
						<input type="hidden" name="', $context[$name], '" value="', $context[$val], '" />';

		echo '
					</div>
				</form>
			</div>
		</div>';
	}

	echo '
	</div>';
}

/**
 * Template for the member maintenance tasks.
 */
function template_maintain_members()
{
	global $context, $txt, $scripturl;

	echo '
	<script><!-- // --><![CDATA[
		var maintain_members_choose = \'' , $txt['maintain_members_choose'], '\',
			maintain_members_all = \'', $txt['maintain_members_all'], '\',
			reattribute_confirm = \'', addcslashes($txt['reattribute_confirm'], "'"), '\',
			reattribute_confirm_email = \'', addcslashes($txt['reattribute_confirm_email'], "'"), '\',
			reattribute_confirm_username = \'', addcslashes($txt['reattribute_confirm_username'], "'"), '\',
			warningMessage = \'\',
			membersSwap = false;

		setTimeout(function() {checkAttributeValidity();}, 500);
	// ]]></script>
	<div id="manage_maintenance">';

	// If maintenance has finished tell the user.
	template_show_error('maintenance_finished');

	echo '
		<h3 class="category_header">', $txt['maintain_reattribute_posts'], '</h3>
		<div class="windowbg">
			<div class="content">
				<form action="', $scripturl, '?action=admin;area=maintain;sa=members;activity=reattribute" method="post" accept-charset="UTF-8">
					<p><strong>', $txt['reattribute_guest_posts'], '</strong></p>
					<dl class="settings">
						<dt>
							<label for="type_email"><input type="radio" name="type" id="type_email" value="email" checked="checked" class="input_radio" />', $txt['reattribute_email'], '</label>
						</dt>
						<dd>
							<input type="text" name="from_email" id="from_email" value="" onclick="document.getElementById(\'type_email\').checked = \'checked\'; document.getElementById(\'from_name\').value = \'\';" />
						</dd>
						<dt>
							<label for="type_name"><input type="radio" name="type" id="type_name" value="name" class="input_radio" />', $txt['reattribute_username'], '</label>
						</dt>
						<dd>
							<input type="text" name="from_name" id="from_name" value="" onclick="document.getElementById(\'type_name\').checked = \'checked\'; document.getElementById(\'from_email\').value = \'\';" class="input_text" />
						</dd>
					</dl>
					<dl class="settings">
						<dt>
							<label for="to"><strong>', $txt['reattribute_current_member'], ':</strong></label>
						</dt>
						<dd>
							<input type="text" name="to" id="to" value="" class="input_text" />
						</dd>
					</dl>
					<p class="maintain_members">
						<input type="checkbox" name="posts" id="posts" checked="checked" class="input_check" />
						<label for="posts">', $txt['reattribute_increase_posts'], '</label>
					</p>
					<div class="submitbutton">
						<input type="submit" id="do_attribute" value="', $txt['reattribute'], '" onclick="if (!checkAttributeValidity()) return false;return confirm(warningMessage);" class="button_submit" />
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="hidden" name="', $context['admin-maint_token_var'], '" value="', $context['admin-maint_token'], '" />
					</div>
				</form>
			</div>
		</div>
		<h3 class="category_header">
			<a class="hdicon cat_img_helptopics help" href="', $scripturl, '?action=quickhelp;help=maintenance_members" onclick="return reqOverlayDiv(this.href);" title="', $txt['help'], '"></a> ', $txt['maintain_members'], '
		</h3>
		<div class="windowbg">
			<div class="content">
				<form action="', $scripturl, '?action=admin;area=maintain;sa=members;activity=purgeinactive" method="post" accept-charset="UTF-8" id="membersForm">
					<p><a id="membersLink"></a>', str_replace(array('{select_conditions}', '{num_days}'), array('
					<select name="del_type">
						<option value="activated" selected="selected">' . $txt['maintain_members_activated'] . '</option>
						<option value="logged">' . $txt['maintain_members_logged_in'] . '</option>
					</select>', ' <input type="text" name="maxdays" value="30" size="3" class="input_text" />'), $txt['maintain_members_since']), '</p>';

	echo '
					<fieldset id="membersPanel">
						<legend data-collapsed="true">', $txt['maintain_members_all'], '</legend>';

	foreach ($context['membergroups'] as $group)
		echo '
						<label for="groups', $group['id'], '"><input type="checkbox" name="groups[', $group['id'], ']" id="groups', $group['id'], '" checked="checked" class="input_check" /> ', $group['name'], '</label><br />';

	echo '
					</fieldset>
					<div class="submitbutton">
						<input type="submit" value="', $txt['maintain_old_remove'], '" onclick="return confirm(\'', $txt['maintain_members_confirm'], '\');" class="button_submit" />
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="hidden" name="', $context['admin-maint_token_var'], '" value="', $context['admin-maint_token'], '" />
					</div>
				</form>
			</div>
		</div>
		<h3 class="category_header">', $txt['maintain_recountposts'], '</h3>
		<div class="windowbg">
			<div class="content">
				<form action="', $scripturl, '?action=admin;area=maintain;sa=members;activity=recountposts" method="post" accept-charset="UTF-8" id="membersRecountForm">
					<p>', $txt['maintain_recountposts_info'], '</p>
					<div class="submitbutton">
						<input type="submit" value="', $txt['maintain_run_now'], '" class="button_submit" />
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="hidden" name="', $context['admin-maint_token_var'], '" value="', $context['admin-maint_token'], '" />
					</div>
				</form>
			</div>
		</div>
	</div>

	<script><!-- // --><![CDATA[
		var oAttributeMemberSuggest = new smc_AutoSuggest({
			sSelf: \'oAttributeMemberSuggest\',
			sSessionId: elk_session_id,
			sSessionVar: elk_session_var,
			sSuggestId: \'attributeMember\',
			sControlId: \'to\',
			sSearchType: \'member\',
			sTextDeleteItem: \'', $txt['autosuggest_delete_item'], '\',
			bItemList: false
		});
	// ]]></script>';
}

/**
 * Template for the topic maintenance tasks.
 */
function template_maintain_topics()
{
	global $scripturl, $txt, $context, $settings, $modSettings;

	// If maintenance has finished tell the user.
	template_show_error('maintenance_finished');

	// Bit of javascript for showing which boards to prune in an otherwise hidden list.
	echo '
	<script><!-- // --><![CDATA[
		var rotSwap = false;
			maintain_old_choose = ', JavaScriptEscape($txt['maintain_old_choose']), ',
			maintain_old_all = ', JavaScriptEscape($txt['maintain_old_all']), ';
	// ]]></script>';

	echo '
	<div id="manage_maintenance">
		<h2 class="category_header">', $txt['maintain_old'], '</h2>
		<div class="windowbg">
			<div class="content flow_auto">
				<form action="', $scripturl, '?action=admin;area=maintain;sa=topics;activity=pruneold" method="post" accept-charset="UTF-8">';

	// The otherwise hidden "choose which boards to prune".
	echo '
					<p>
						<a id="rotLink"></a><label for="maxdays">', sprintf($txt['maintain_old_since_days'], '<input type="text" id="maxdays" name="maxdays" value="30" size="3" />'), '</label>
					</p>
					<p>
						<label for="delete_type_nothing"><input type="radio" name="delete_type" id="delete_type_nothing" value="nothing" class="input_radio" /> ', $txt['maintain_old_nothing_else'], '</label><br />
						<label for="delete_type_moved"><input type="radio" name="delete_type" id="delete_type_moved" value="moved" class="input_radio" checked="checked" /> ', $txt['maintain_old_are_moved'], '</label><br />
						<label for="delete_type_locked"><input type="radio" name="delete_type" id="delete_type_locked" value="locked" class="input_radio" /> ', $txt['maintain_old_are_locked'], '</label><br />
					</p>';

	if (!empty($modSettings['enableStickyTopics']))
		echo '
					<p>
						<label for="delete_old_not_sticky"><input type="checkbox" name="delete_old_not_sticky" id="delete_old_not_sticky" class="input_check" checked="checked" /> ', $txt['maintain_old_are_not_stickied'], '</label><br />
					</p>';

	echo '
					<p>
						<a href="#rotLink" onclick="swapRot();"><img src="', $settings['images_url'], '/selected.png" alt="+" id="rotIcon" /></a> <a href="#rotLink" onclick="swapRot();" id="rotText">', $txt['maintain_old_all'], '</a>
					</p>
					<div style="display: none;" id="rotPanel" class="flow_hidden">
						<div class="floatleft grid50">';

	// This is the "middle" of the list.
	$middle = ceil(count($context['categories']) / 2);

	$i = 0;
	foreach ($context['categories'] as $category)
	{
		echo '
							<fieldset>
								<legend>', $category['name'], '</legend>
								<ul>';

		// Display a checkbox with every board.
		foreach ($category['boards'] as $board)
			echo '
									<li style="margin-', $context['right_to_left'] ? 'right' : 'left', ': ', $board['child_level'] * 1.5, 'em;">
										<label for="boards_', $board['id'], '"><input type="checkbox" name="boards[', $board['id'], ']" id="boards_', $board['id'], '" checked="checked" class="input_check" />', $board['name'], '</label>
									</li>';

		echo '
								</ul>
							</fieldset>';

		// Increase $i, and check if we're at the middle yet.
		if (++$i == $middle)
			echo '
						</div>
						<div class="floatright grid50">';
	}

	echo '
						</div>
					</div>
					<div class="submitbutton">
						<input type="submit" value="', $txt['maintain_old_remove'], '" onclick="return confirm(\'', $txt['maintain_old_confirm'], '\');" class="button_submit" />
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="hidden" name="', $context['admin-maint_token_var'], '" value="', $context['admin-maint_token'], '" />
					</div>
				</form>
			</div>
		</div>
		<h3 class="category_header">', $txt['maintain_old_drafts'], '</h3>
		<div class="windowbg">
			<div class="content">
				<form action="', $scripturl, '?action=admin;area=maintain;sa=topics;activity=olddrafts" method="post" accept-charset="UTF-8">
					<p>
						<label for="draftdays">', sprintf($txt['maintain_old_drafts_days'], ' <input type="text" id="draftdays" name="draftdays" value="' . (!empty($modSettings['drafts_keep_days']) ? $modSettings['drafts_keep_days'] : 30) . '" size="3" /> '), '</label>
					</p>
					<div class="submitbutton">
						<input type="submit" value="', $txt['maintain_old_remove'], '" onclick="return confirm(\'', $txt['maintain_old_drafts_confirm'], '\');" class="button_submit" />
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="hidden" name="', $context['admin-maint_token_var'], '" value="', $context['admin-maint_token'], '" />
					</div>
				</form>
			</div>
		</div>
		<h3 class="category_header">', $txt['move_topics_maintenance'], '</h3>
		<div class="windowbg">
			<div class="content">
				<form action="', $scripturl, '?action=admin;area=maintain;sa=topics;activity=massmove" method="post" accept-charset="UTF-8">
					<p>';

	template_select_boards('id_board_from', $txt['move_topics_from']);
	template_select_boards('id_board_to', $txt['move_topics_to']);

	echo '
					</p>
					<div class="submitbutton">
						<input type="submit" value="', $txt['move_topics_now'], '" onclick="return confirmMoveTopics(', JavaScriptEscape($txt['move_topics_confirm']), ');" class="button_submit" />
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="hidden" name="', $context['admin-maint_token_var'], '" value="', $context['admin-maint_token'], '" />
					</div>
				</form>
			</div>
		</div>
	</div>';
}

/**
 * Simple template for showing results of our optimization...
 */
function template_optimize()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="manage_maintenance">
		<h2 class="category_header">', $txt['maintain_optimize'], '</h2>
		<div class="windowbg">
			<div class="content">
				<p>
					', $txt['database_numb_tables'], '<br />
					', $txt['database_optimize_attempt'], '<br />';

	// List each table being optimized...
	foreach ($context['optimized_tables'] as $table)
		echo '
					', sprintf($txt['database_optimizing'], $table['name'], $table['data_freed']), '<br />';

	// How did we go?
	echo '
					<br />', $context['num_tables_optimized'] == 0 ? $txt['database_already_optimized'] : $context['num_tables_optimized'] . ' ' . $txt['database_optimized'];

	echo '
				</p>
				<p><a href="', $scripturl, '?action=admin;area=maintain">', $txt['maintain_return'], '</a></p>
			</div>
		</div>
	</div>';
}

/**
 * Template for maintenance conversion of messages body to several conditions
 */
function template_convert_msgbody()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="manage_maintenance">
		<h3 class="category_header">', $txt[$context['convert_to'] . '_title'], '</h3>
		<div class="windowbg">
			<div class="content">
				<p>', $txt['body_checking_introduction'], '</p>';

	if (!empty($context['exceeding_messages']))
	{
		echo '
				<p class="warningbox">', $txt['exceeding_messages'], '
				<ul>
					<li>
					', implode('</li><li>', $context['exceeding_messages']), '
					</li>
				</ul>';

		if (!empty($context['exceeding_messages_morethan']))
			echo '
				<p>', $context['exceeding_messages_morethan'], '</p>';
	}
	else
		echo '
				<p class="successbox">', $txt['convert_to_text'], '</p>';

	echo '
				<form action="', $scripturl, '?action=admin;area=maintain;sa=database;activity=convertmsgbody" method="post" accept-charset="UTF-8">
				<hr />
				<div class="submitbutton">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['admin-maint_token_var'], '" value="', $context['admin-maint_token'], '" />
					<input type="submit" name="do_conversion" value="', $txt['convert_proceed'], '" class="button_submit" />
				</div>
				</form>
			</div>
		</div>
	</div>';
}