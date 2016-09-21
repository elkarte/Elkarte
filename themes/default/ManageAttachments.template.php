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
 * @version 1.1 beta 2
 *
 */

/**
 * Forum maintenance page.
 */
function template_maintenance()
{
	global $context, $scripturl, $txt, $modSettings;

	echo '
	<div id="manage_attachments">
		<h2 class="category_header hdicon cat_img_stats_info">', $txt['attachment_stats'], '</h2>
		<div class="content">
			<dl class="settings">
				<dt><label>', $txt['attachment_total'], ':</label></dt>
				<dd>', $context['num_attachments'], '</dd>
				<dt><label>', $txt['attachment_manager_total_avatars'], ':</label></dt>
				<dd>', $context['num_avatars'], '</dd>
				<dt><label>', $txt['attachmentdir_size'], ':</label></dt>
				<dd>', $context['attachment_total_size'], ' ', $txt['kilobyte'], '</dd>
				<dt><label>', $txt['attach_current_dir'], ':</label></dt>
				<dd>', $context['attach_dirs'][$modSettings['currentAttachmentUploadDir']], '</dd>
				<dt><label>', $txt['attachmentdir_size_current'], ':</label></dt>
				<dd>', $context['attachment_current_size'], ' ', $txt['kilobyte'], '</dd>
				<dt><label>', $txt['attachment_space'], ':</label></dt>
				<dd>', isset($context['attachment_space']) ? $context['attachment_space'] . ' ' . $txt['kilobyte'] : $txt['attachmentdir_size_not_set'], '</dd>
				<dt><label>', $txt['attachmentdir_files_current'], ':</label></dt>
				<dd>', $context['attachment_current_files'], '</dd>
				<dt><label>', $txt['attachment_files'], ':</label></dt>
				<dd>', isset($context['attachment_files']) ? $context['attachment_files'] : $txt['attachmentdir_files_not_set'], '</dd>
			</dl>
		</div>
		<div class="separator"></div>
		<h2 class="category_header">', $txt['attachment_integrity_check'], '</h2>
		<div class="content">
			<form action="', $scripturl, '?action=admin;area=manageattachments;sa=repair;', $context['session_var'], '=', $context['session_id'], '" method="post" accept-charset="UTF-8">
				<p>', $txt['attachment_integrity_check_desc'], '</p>
				<div class="submitbutton">
					<input type="submit" name="repair" value="', $txt['attachment_check_now'], '" />
				</div>
			</form>
		</div>
		<div class="separator"></div>
		<h2 class="category_header">', $txt['attachment_pruning'], '</h2>
		<div class="content">
			<form action="', $scripturl, '?action=admin;area=manageattachments" method="post" accept-charset="UTF-8" onsubmit="return confirm(\'', $txt['attachment_pruning_warning'], '\');">
				<label for="age">', sprintf($txt['attachment_remove_old'], ' <input type="text" id="age" name="age" value="25" size="4" class="input_text" /> '), '</label><br />
				<label for="age_notice">', $txt['attachment_pruning_message'], '</label>: <input type="text" id="age_notice" name="notice" value="', $txt['attachment_delete_admin'], '" size="40" class="input_text" /><br />
				<div class="submitbutton">
					<input type="submit" name="remove" value="', $txt['remove'], '" />
					<input type="hidden" name="type" value="attachments" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="sa" value="byAge" />
				</div>
			</form>
			<hr />
			<form action="', $scripturl, '?action=admin;area=manageattachments" method="post" accept-charset="UTF-8" onsubmit="return confirm(\'', $txt['attachment_pruning_warning'], '\');">
				<label for="size">', sprintf($txt['attachment_remove_size'], ' <input type="text" name="size" id="size" value="100" size="4" class="input_text" /> '), '</label><br />
				<label for="size_notice">', $txt['attachment_pruning_message'], '</label>: <input type="text" id="size_notice" name="notice" value="', $txt['attachment_delete_admin'], '" size="40" class="input_text" /><br />
				<div class="submitbutton">
					<input type="submit" name="remove" value="', $txt['remove'], '" />
					<input type="hidden" name="type" value="attachments" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="sa" value="bySize" />
				</div>
			</form>
			<hr />
			<form action="', $scripturl, '?action=admin;area=manageattachments" method="post" accept-charset="UTF-8" onsubmit="return confirm(\'', $txt['attachment_pruning_warning'], '\');">
				<label for="avatar_age">', sprintf($txt['attachment_manager_avatars_older'], '
 					<input type="text" id="avatar_age" name="age" value="45" size="4" class="input_text" /> '), '
				</label>
				<div class="submitbutton">
					<input type="submit" name="remove" value="', $txt['remove'], '" />
					<input type="hidden" name="type" value="avatars" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="sa" value="byAge" />
				</div>
			</form>
		</div>
		<div class="separator"></div>';

	// Transfer Attachments section
	echo '
		<h2 id="transfer" class="category_header">', $txt['attachment_transfer'], '</h2>';

	// Any results to show
	if (!empty($context['results']))
		echo '
		<div class="successbox">', $context['results'], '</div>';

	// Lots-o-options
	echo '
		<div class="content">
			<form action="', $scripturl, '?action=admin;area=manageattachments;sa=transfer" method="post" accept-charset="UTF-8">
				<p class="infobox">', $txt['attachment_transfer_desc'], '</p>
				<dl class="settings">
					<dt>
						<label for="from">', $txt['attachment_transfer_from'], '</label>
					</dt>
					<dd>
						<select id="from" name="from">
							<option value="0">', $txt['attachment_transfer_select'], '</option>';

	foreach ($context['attach_dirs'] as $id => $dir)
		echo '
								<option value="', $id, '">', $dir, '</option>';

	echo '
						</select>
					</dd>
					<dt>
						<label for="auto">', $txt['attachment_transfer_auto'], '</label>
					</dt>
					<dd>
						<select id="auto" name="auto" onchange="transferAttachOptions();">
							<option value="0">', $txt['attachment_transfer_auto_select'], '</option>
							<option value="-1">', $txt['attachment_transfer_forum_root'], '</option>';

	if (!empty($context['base_dirs']))
		foreach ($context['base_dirs'] as $id => $dir)
			echo '
							<option value="', $id, '">', $dir, '</option>';
	else
		echo '
							<option value="0" disabled="disabled">', $txt['attachment_transfer_no_base'], '</option>';

	echo '
						</select>
					</dd>
					<dt>
						<label for="to">', $txt['attachment_transfer_to'], '</label>
					</dt>
					<dd>
						<select id="to" name="to" onchange="transferAttachOptions();" >
							<option value="0">', $txt['attachment_transfer_select'], '</option>';

	foreach ($context['attach_dirs'] as $id => $dir)
		echo '
							<option value="', $id, '">', $dir, '</option>';

	echo '
						</select>
					</dd>';

	// If there are directory limits to impose, give the option to enforce it
	if (!empty($modSettings['attachmentDirFileLimit']))
		echo '
					<dt>
						<a href="' . $scripturl . '?action=quickhelp;help=attachment_transfer_empty" onclick="return reqOverlayDiv(this.href);" class="helpicon i-help"><s>' . $txt['help'] . '</s></a>', $txt['attachment_transfer_empty'], '</a>
					</dt>
					<dd>
						<input type="checkbox" name="empty_it"', $context['checked'] ? ' checked="checked"' : '', ' />
					</dd>';

	echo '
				</dl>
				<div class="submitbutton">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="submit" onclick="start_progress()" name="transfer" value="', $txt['attachment_transfer_now'], '" />
				</div>
				<div id="progress_msg"></div>
				<div id="show_progress"></div>
			</form>
			<script>
				function start_progress() {
					setTimeout(function() {show_msg();}, 1000);
				}

				function show_msg() {
					$(\'#progress_msg\').html(\'<div><i class="icon icon-spin i-spinner"></i>&nbsp;', $txt['attachment_transfer_progress'], '<\/div>\');
					show_progress();
				}

				function show_progress() {
					$(\'#show_progress\').load("progress.php");
					setTimeout(function() {show_progress();}, 1500);
				}

			</script>
		</div>
	</div>';
}

/**
 * Repair attachments page
 */
function template_attachment_repair()
{
	global $context, $txt, $scripturl;

	// If we've completed just let them know!
	if ($context['completed'])
	{
		echo '
	<div id="manage_attachments">
		<div class="content">
			', $txt['repair_attachments_complete_desc'], '
		</div>
	</div>';
	}
	// What about if no errors were even found?
	elseif (!$context['errors_found'])
	{
		echo '
	<div id="manage_attachments">
		<h2 class="category_header">', $txt['repair_attachments_complete'], '</h2>
		<div class="content">
			', $txt['repair_attachments_no_errors'], '
		</div>
	</div>';
	}
	// Otherwise, I'm sad to say, we have a problem!
	else
	{
		echo '
	<form id="admin_form_wrapper" action="', $scripturl, '?action=admin;area=manageattachments;sa=repair;fixErrors=1;step=0;substep=0;', $context['session_var'], '=', $context['session_id'], '" method="post" accept-charset="UTF-8">
		<h2 class="category_header">', $txt['repair_attachments'], '</h2>
		<div class="content">
			<p>', $txt['repair_attachments_error_desc'], '</p>';

		// Loop through each error reporting the status
		foreach ($context['repair_errors'] as $error => $number)
		{
			if (!empty($number))
				echo '
			<input type="checkbox" name="to_fix[]" id="', $error, '" value="', $error, '" />
			<label for="', $error, '">', sprintf($txt['attach_repair_' . $error], $number), '</label>
			<br />';
		}

		echo '
			<div class="submitbutton">
				<input type="submit" value="', $txt['repair_attachments_continue'], '" />
				<input type="submit" name="cancel" value="', $txt['repair_attachments_cancel'], '" />
			</div>
		</div>
	</form>';
	}
}

/**
 * Section on the page for attachments directories paths.
 */
function template_attach_paths()
{
	global $modSettings;

	if (!empty($modSettings['attachment_basedirectories']))
		template_show_list('base_paths');

	template_show_list('attach_paths');
}
