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
 */

/**
 * Start things off with some generic templates we can use
 */
function template_Maintenance_init()
{
	theme()->getTemplates()->load('GenericHelpers');
}

/**
 * Template for the database maintenance tasks.
 */
function template_maintain_database()
{
	global $context, $txt, $scripturl;

	// If maintenance has finished tell the user.
	if (!empty($context['maintenance_finished']))
		echo '
			<div class="successbox">
				', sprintf($txt['maintain_done'], $context['maintenance_finished']), '
			</div>';

	echo '
	<div id="manage_maintenance">
		<h2 class="category_header">', $txt['maintain_optimize'], '</h2>
		<div class="content">
			<form action="', $scripturl, '?action=admin;area=maintain;sa=database;activity=optimize" method="post" accept-charset="UTF-8">
				<p>', $txt['maintain_optimize_info'], '</p>
				<div class="submitbutton">
					<input type="submit" value="', $txt['maintain_run_now'], '" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['admin-maint_token_var'], '" value="', $context['admin-maint_token'], '" />
				</div>
			</form>
		</div>
		<h2 class="category_header">
			<a href="', $scripturl, '?action=quickhelp;help=maintenance_backup" onclick="return reqOverlayDiv(this.href);" class="helpicon i-help"><s>', $txt['help'], '</s></a> ', $txt['maintain_backup'], '
		</h2>
		<div class="content">
			<form action="', $scripturl, '?action=admin;area=maintain;sa=database;activity=backup" method="post" accept-charset="UTF-8">
				<p>', $txt['maintain_backup_info'], '</p>';

	if ($context['safe_mode_enable'])
		echo '
			<div class="errorbox">', $txt['safe_mode_enabled'], '</div>';
	else
		echo '
				<div class="', $context['suggested_method'] == 'use_external_tool' || $context['use_maintenance'] != 0 ? 'errorbox' : 'infobox', '">
					', $txt[$context['suggested_method']], $context['use_maintenance'] != 0 ? '
					<br />
					' . $txt['enable_maintenance' . $context['use_maintenance']] : '', '
				</div>
				<p>
					<label for="struct"><input type="checkbox" name="struct" id="struct" onclick="document.getElementById(\'submitDump\').disabled = !document.getElementById(\'struct\').checked &amp;&amp; !document.getElementById(\'data\').checked;" checked="checked" /> ', $txt['maintain_backup_struct'], '</label><br />
					<label for="data"><input type="checkbox" name="data" id="data" onclick="document.getElementById(\'submitDump\').disabled = !document.getElementById(\'struct\').checked &amp;&amp; !document.getElementById(\'data\').checked;" checked="checked" /> ', $txt['maintain_backup_data'], '</label><br />
					<label for="compress"><input type="checkbox" name="compress" id="compress" value="gzip"', $context['suggested_method'] == 'zipped_file' ? ' checked="checked"' : '', ' /> ', $txt['maintain_backup_gz'], '</label>
					</p>';

	if (empty($context['skip_security']))
	{
		echo '
				<div class="infobox">', $txt['security_database_download'], '</div>';
		template_control_chmod();
	}

	echo '
				<div class="submitbutton">
					<input id="submitDump" ', $context['use_maintenance'] == 2 ? 'disabled="disabled" ' : '', 'type="submit" value="', $txt['maintain_backup_save'], '" onclick="return document.getElementById(\'struct\').checked || document.getElementById(\'data\').checked;" />';

	echo '
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['admin-maint_token_var'], '" value="', $context['admin-maint_token'], '" />
				</div>
			</form>
		</div>';

	// Show an option to convert the body column of the post table to MEDIUMTEXT or TEXT
	if (isset($context['convert_to']))
		echo '
		<h2 class="category_header">', $txt[$context['convert_to'] . '_title'], '</h2>
		<div class="content">
			<form action="', $scripturl, '?action=admin;area=maintain;sa=database;activity=convertmsgbody" method="post" accept-charset="UTF-8">
				<p>', $txt['mediumtext_introduction'], '</p>',
				$context['convert_to_suggest'] ? '<p class="successbox">' . $txt['convert_to_suggest_text'] . '</p>' : '', '
				<div class="submitbutton">
					<input type="submit" name="evaluate_conversion" value="', $txt['maintain_run_now'], '" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['admin-maint_token_var'], '" value="', $context['admin-maint_token'], '" />
				</div>
			</form>
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
		<h2 class="category_header">', $action['title'], '</h2>
		<div class="content">
			<form action="', $action['url'], '" method="post" accept-charset="UTF-8">
				<p>', $action['description'], '</p>
				<div class="submitbutton">
					<input type="submit" value="', $action['submit'], '" />';

		if (!empty($action['hidden']))
			foreach ($action['hidden'] as $name => $val)
				echo '
						<input type="hidden" name="', $context[$name], '" value="', $context[$val], '" />';

		echo '
				</div>
			</form>
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
	<script>
		var maintain_members_choose = \'', $txt['maintain_members_choose'], '\',
			maintain_members_all = \'', $txt['maintain_members_all'], '\',
			reattribute_confirm = \'', addcslashes($txt['reattribute_confirm'], "'"), '\',
			reattribute_confirm_email = \'', addcslashes($txt['reattribute_confirm_email'], "'"), '\',
			reattribute_confirm_username = \'', addcslashes($txt['reattribute_confirm_username'], "'"), '\',
			warningMessage = \'\',
			membersSwap = false;

		setTimeout(function() {checkAttributeValidity();}, 500);
	</script>
	<div id="manage_maintenance">';

	// If maintenance has finished tell the user.
	template_show_error('maintenance_finished');

	echo '
		<h2 class="category_header">', $txt['maintain_reattribute_posts'], '</h2>
		<div class="content">
			<form action="', $scripturl, '?action=admin;area=maintain;sa=members;activity=reattribute" method="post" accept-charset="UTF-8">
				<p><strong>', $txt['reattribute_guest_posts'], '</strong></p>
				<dl class="settings">
					<dt>
						<label for="type_email"><input type="radio" name="type" id="type_email" value="email" checked="checked" />', $txt['reattribute_email'], '</label>
					</dt>
					<dd>
						<input type="text" name="from_email" id="from_email" value="" onclick="document.getElementById(\'type_email\').checked = \'checked\'; document.getElementById(\'from_name\').value = \'\';" />
					</dd>
					<dt>
						<label for="type_name"><input type="radio" name="type" id="type_name" value="name" />', $txt['reattribute_username'], '</label>
					</dt>
					<dd>
						<input type="text" name="from_name" id="from_name" value="" onclick="document.getElementById(\'type_name\').checked = \'checked\'; document.getElementById(\'from_email\').value = \'\';" class="input_text" />
					</dd>
				</dl>
				<dl class="settings">
					<dt>
						<label for="to">', $txt['reattribute_current_member'], ':</label>
					</dt>
					<dd>
						<input type="text" name="to" id="to" value="" class="input_text" />
					</dd>
				</dl>
				<p class="maintain_members">
					<input type="checkbox" name="posts" id="posts" checked="checked" />
					<label for="posts">', $txt['reattribute_increase_posts'], '</label>
				</p>
				<div class="submitbutton">
					<input type="submit" id="do_attribute" value="', $txt['reattribute'], '" onclick="if (!checkAttributeValidity()) return false;return confirm(warningMessage);" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['admin-maint_token_var'], '" value="', $context['admin-maint_token'], '" />
				</div>
			</form>
		</div>
		<h2 class="category_header">
			<a class="hdicon cat_img_helptopics help" href="', $scripturl, '?action=quickhelp;help=maintenance_members" onclick="return reqOverlayDiv(this.href);" title="', $txt['help'], '"></a> ', $txt['maintain_members'], '
		</h2>
		<form action="', $scripturl, '?action=admin;area=maintain;sa=members;activity=purgeinactive" method="post" accept-charset="UTF-8" id="membersForm">
			<div class="content">
				<a id="membersLink"></a>',
				str_replace(array('{select_conditions}', '{num_days}'), array('
					<select name="del_type">
						<option value="activated" selected="selected">' . $txt['maintain_members_activated'] . '</option>
						<option value="logged">' . $txt['maintain_members_logged_in'] . '</option>
					</select>', ' <input type="text" name="maxdays" value="30" size="3" class="input_text" />'
				),
				$txt['maintain_members_since']), '
			</div>';

	echo '
			<fieldset id="membersPanel">
				<legend data-collapsed="true">', $txt['maintain_members_all'], '</legend>';

	foreach ($context['membergroups'] as $group)
		echo '
				<label for="groups', $group['id'], '"><input type="checkbox" name="groups[', $group['id'], ']" id="groups', $group['id'], '" checked="checked" /> ', $group['name'], '</label><br />';

	echo '
			</fieldset>
			<div class="submitbutton">
				<input type="submit" value="', $txt['maintain_old_remove'], '" onclick="return confirm(\'', $txt['maintain_members_confirm'], '\');" />
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input type="hidden" name="', $context['admin-maint_token_var'], '" value="', $context['admin-maint_token'], '" />
			</div>
		</form>
		<h2 class="category_header">', $txt['maintain_recountposts'], '</h2>
		<form action="', $scripturl, '?action=admin;area=maintain;sa=members;activity=recountposts" method="post" accept-charset="UTF-8" id="membersRecountForm">
			<div class="content">
				', $txt['maintain_recountposts_info'], '
			</div>
			<div class="submitbutton">
				<input type="submit" value="', $txt['maintain_run_now'], '" />
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input type="hidden" name="', $context['admin-maint_token_var'], '" value="', $context['admin-maint_token'], '" />
			</div>
		</form>
	</div>

	<script>
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
	</script>';
}

/**
 * Template for the topic maintenance tasks.
 */
function template_maintain_topics()
{
	global $context;

	// If maintenance has finished tell the user.
	template_show_error('maintenance_finished');

	echo '
	<div id="manage_maintenance">';

	foreach ($context['topics_actions'] as $key => $maintenace)
	{
		echo '
		<h2 class="category_header">', $maintenace['title'], '</h2>
		<form name="', $key, ' " action="', $maintenace['url'], '" method="post" accept-charset="UTF-8">
			<div class="content">';

		$function = 'template_maintain_topics_' . $key;
		$function();

		echo '
			</div>
			<div class="submitbutton">
				<input type="submit" value="', $maintenace['submit'], '" ', !empty($maintenace['confirm']) ? 'onclick="return confirm(\'' . $maintenace['confirm'] . '\');"' : '', ' />';

		if (!empty($maintenace['hidden']))
			foreach ($maintenace['hidden'] as $name => $val)
				echo '
				<input type="hidden" name="', $context[$name], '" value="', $context[$val], '" />';

		echo '
			</div>
		</form>';
	}
	echo '
	</div>';
}

function template_maintain_topics_pruneold()
{
	global $txt;

	// The otherwise hidden "choose which boards to prune".
	echo '
					<p>
						<label for="maxdays">', sprintf($txt['maintain_old_since_days'], '<input type="text" id="maxdays" name="maxdays" value="30" size="3" />'), '</label>
					</p>
					<p>
						<label for="delete_type_nothing"><input type="radio" name="delete_type" id="delete_type_nothing" value="nothing" /> ', $txt['maintain_old_nothing_else'], '</label><br />
						<label for="delete_type_moved"><input type="radio" name="delete_type" id="delete_type_moved" value="moved" checked="checked" /> ', $txt['maintain_old_are_moved'], '</label><br />
						<label for="delete_type_locked"><input type="radio" name="delete_type" id="delete_type_locked" value="locked" /> ', $txt['maintain_old_are_locked'], '</label><br />
					</p>
					<p>
						<label for="delete_old_not_sticky"><input type="checkbox" name="delete_old_not_sticky" id="delete_old_not_sticky" checked="checked" /> ', $txt['maintain_old_are_not_stickied'], '</label><br />
					</p>
					<fieldset id="pick_boards" class="content">';

	template_pick_boards('pruneold', 'boards');

	echo '
					</fieldset>';
}

function template_maintain_topics_olddrafts()
{
	global $txt, $modSettings;

	echo '
					<p>
						<label for="draftdays">', sprintf($txt['maintain_old_drafts_days'], ' <input type="text" id="draftdays" name="draftdays" value="' . (!empty($modSettings['drafts_keep_days']) ? $modSettings['drafts_keep_days'] : 30) . '" size="3" /> '), '</label>
					</p>';
}

function template_maintain_topics_massmove()
{
	global $txt;

	echo '
					<p>';

	template_select_boards('id_board_from', $txt['move_topics_from']);
	template_select_boards('id_board_to', $txt['move_topics_to']);

	echo '
					</p>';
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
			<p>
				<a href="', $scripturl, '?action=admin;area=maintain">', $txt['maintain_return'], '</a>
			</p>
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
		<h2 class="category_header">', $txt[$context['convert_to'] . '_title'], '</h2>
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
				<input type="submit" name="do_conversion" value="', $txt['convert_proceed'], '" />
			</div>
			</form>
		</div>
	</div>';
}
