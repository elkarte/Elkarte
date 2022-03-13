<?php

/**
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

/**
 * Loads the template of the poster area
 */
function template_PersonalMessage_init()
{
	theme()->getTemplates()->load('GenericMessages');
}

/**
 * This is the main sidebar for the personal messages section.
 */
function template_pm_above()
{
	global $context, $txt;

	// The every helpful javascript!
	echo '
					<script>
						let allLabels = {},
							currentLabels = {},
							txt_pm_msg_label_remove = "', $txt['pm_msg_label_remove'], '",
							txt_pm_msg_label_apply = "', $txt['pm_msg_label_apply'], '";
					</script>
					<section id="personal_messages">';

	// Show the capacity bar, if available.
	if (!empty($context['limit_bar']))
	{
		echo '
						<h2 class="category_header flow_flex">
							<span>', $txt['pm_capacity'], ':</span>
							<span class="capacity_bar">
								<span class="', $context['limit_bar']['percent'] > 85 ? 'full' : ($context['limit_bar']['percent'] > 40 ? 'filled' : 'empty'), '" style="width: ', $context['limit_bar']['percent'] / 10, 'em;"></span>
							</span>
							<span class="flow_flex_right', $context['limit_bar']['percent'] > 90 ? ' alert' : '', '">', $context['limit_bar']['text'], '</span>
						</h2>';
	}

	// Message sent? Show a small indication.
	if (isset($context['pm_sent']))
	{
		echo '
						<aside class="successbox">
							', $txt['pm_sent'], '
						</aside>';
	}

	if (!empty($context['pm_form_url']))
	{
		echo '
						<form action="', $context['pm_form_url'], '" method="post" accept-charset="UTF-8" name="pmFolder">';
	}
}

/**
 * The end of the index bar, for personal messages page.
 */
function template_pm_below()
{
	global $context;

	echo '
							<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />', !empty($context['pm_form_url']) ? '
						</form>' : '', '
					</section>';
}

/**
 * Messages folder, used to viewing a listing of messages
 */
function template_folder()
{
	global $context, $scripturl, $options, $txt;

	$start = true;
	$reset = true;

	echo '
					<div id="forumposts">';

	// Do we have some messages to display?
	$controller = $context['get_pmessage'][0];
	while (($message = $controller->{$context['get_pmessage'][1]}($reset)))
	{
		$reset = false;

		// Show the helpful titlebar - generally.
		if ($start && $context['display_mode'] != 1)
		{
			echo '
						<h2 class="category_header">
							', $context['display_mode'] == 0 ? $txt['messages'] : $txt['conversation'] . ': ' . $message['subject'], '
						</h2>';
			$start = false;
		}

		echo '
						<a class="pm_anchor" id="msg_', $message['id'], '"></a>
						<article class="post_wrapper forumposts">';

		// Showing the sidebar posting area?
		if (empty($options['hide_poster_area']))
		{
			echo '
							<aside>
								<ul class="poster">', template_build_poster_div($message), '</ul>
							</aside>';
		}

		echo '
							<div class="postarea', empty($options['hide_poster_area']) ? '' : '2', '">
								<div class="keyinfo">
									', (!empty($options['hide_poster_area']) ? '<ul class="poster poster2">' . template_build_poster_div($message) . '</ul>' : ''), '
									<span id="post_subject_', $message['id'], '" class="post_subject">', $message['subject'], '</span>
									<h5 id="info_', $message['id'], '">';

		// @todo - above needs fixing re document outlining (a11y stuffz).
		// Show who the message was sent to.
		echo '
										<strong>', $txt['sent_to'], ': </strong>';

		// People it was sent directly to....
		if (!empty($message['recipients']['to']))
		{
			echo
			implode(', ', $message['recipients']['to']);
		}
		// Otherwise, we're just going to say "some people"...
		elseif ($context['folder'] != 'sent')
		{
			echo
			'(', $txt['pm_undisclosed_recipients'], ')';
		}

		echo '
										<strong> ', $txt['on'], ': </strong>', $message['time'];

		// If we're in the sent items folder, show who it was sent to besides the "To:" people.
		if (!empty($message['recipients']['bcc']))
		{
			echo '
										<br /><strong> ', $txt['pm_bcc'], ': </strong>', implode(', ', $message['recipients']['bcc']);
		}

		if (!empty($message['is_replied_to']))
		{
			echo '
										<br />', $txt['pm_is_replied_to'];
		}

		echo '
									</h5>
								</div>';

		// Done with the information about the poster... on to the post itself.
		echo '
								<div class="messageContent">', $message['body'], '</div>';

		// Show our quick buttons like quote and reply
		echo '
								<nav>
									<ul class="quickbuttons no_js">';

		// Showing all then give a remove item checkbox
		if (empty($context['display_mode']))
		{
			echo '
										<li class="listlevel1 quickmod_check">
											<input type="checkbox" name="pms[]" id="deletedisplay', $message['id'], '" value="', $message['id'], '" onclick="document.getElementById(\'deletelisting', $message['id'], '\').checked = this.checked;" />
										</li>';
		}

		// Maybe there is something...more :P (this is the more button)
		if (!empty($context['additional_pm_drop_buttons']))
		{
			echo '
										<li class="listlevel1 subsections" aria-haspopup="true">
											<a class="linklevel1 post_options">', $txt['post_options'], '</a>
											<ul class="menulevel2">';

			foreach ($context['additional_pm_drop_buttons'] as $key => $button)
			{
				echo '
												<li class="listlevel2">
													<a href="' . $button['href'] . '" class="linklevel2 ', $key, '">' . $button['text'] . '</a>
												</li>';
			}

			echo '
											</ul>
										</li>';
		}

		// Remove is always an option
		echo '
										<li class="listlevel1">
											<a href="', $scripturl, '?action=pm;sa=pmactions;pm_actions%5B', $message['id'], '%5D=delete;f=', $context['folder'], ';start=', $context['start'], $context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '', ';', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', addslashes($txt['remove_message']), '?\');" class="linklevel1 remove_button">', $txt['delete'], '</a>
										</li>';

		// Show reply buttons if you have the permission to send PMs.
		if ($context['can_send_pm'])
		{
			// You can't really reply if the member is gone.
			if (!$message['member']['is_guest'])
			{
				// Is there than more than one recipient you can reply to?
				if ($message['number_recipients'] > 1)
				{
					echo '
										<li class="listlevel1">
											<a href="', $scripturl, '?action=pm;sa=send;f=', $context['folder'], $context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '', ';pmsg=', $message['id'], ';quote;u=all" class="linklevel1 reply_all_button">', $txt['reply_to_all'], '</a></li>';
				}

				echo '
										<li class="listlevel1">
											<a href="', $scripturl, '?action=pm;sa=send;f=', $context['folder'], $context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '', ';pmsg=', $message['id'], ';u=', $message['member']['id'], '" class="linklevel1 reply_button">', $txt['reply'], '</a>
										</li>
										<li class="listlevel1">
											<a href="', $scripturl, '?action=pm;sa=send;f=', $context['folder'], $context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '', ';pmsg=', $message['id'], ';quote', $context['folder'] == 'sent' ? '' : ';u=' . $message['member']['id'], '" class="linklevel1 quote_button">', $txt['quote'], '</a>
										</li>';
			}
			// This is for "forwarding" - even if the member is gone.
			else
			{
				echo '
										<li class="listlevel1">
											<a href="', $scripturl, '?action=pm;sa=send;f=', $context['folder'], $context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '', ';pmsg=', $message['id'], ';quote" class="linklevel1 quote_button">', $txt['reply_quote'], '</a>
										</li>';
			}
		}

		// Anything else added by mods for example?
		if (!empty($context['additional_quick_pm_buttons']))
		{
			foreach ($context['additional_quick_pm_buttons'] as $key => $button)
			{
				echo '
										<li class="listlevel1">
											<a href="' . $button['href'] . '" class="linklevel1 ', $key, '">' . $button['text'] . '</a>
										</li>';
			}
		}

		echo '
									</ul>
								</nav>';

		// Add a selection box if we have labels enabled.
		if ($context['folder'] !== 'sent' && !empty($context['currently_using_labels']) && $context['display_mode'] != 2)
		{
			echo '
								<div class="labels">';

			// Add the label drop down box.
			if (!empty($context['currently_using_labels']))
			{
				echo '
									<select name="pm_actions[', $message['id'], ']" onchange="if (this.options[this.selectedIndex].value) form.submit();">
										<option value="">', $txt['pm_msg_label_title'], ':</option>
										<option value="" disabled="disabled">' . str_repeat('&#8212;', strlen($txt['pm_msg_label_title'])) . '</option>';

				// Are there any labels which can be added to this?
				if (!$message['fully_labeled'])
				{
					echo '
										<option value="" disabled="disabled">', $txt['pm_msg_label_apply'], ':</option>';

					foreach ($context['labels'] as $label)
					{
						if (!isset($message['labels'][$label['id']]))
						{
							echo '
										<option value="', $label['id'], '">&nbsp;', $label['name'], '</option>';
						}
					}
				}

				// ... and are there any that can be removed?
				if (!empty($message['labels']) && (count($message['labels']) > 1 || !isset($message['labels'][-1])))
				{
					echo '
										<option value="" disabled="disabled">', $txt['pm_msg_label_remove'], ':</option>';

					foreach ($message['labels'] as $label)
					{
						echo '
										<option value="', $label['id'], '">&nbsp;', $label['name'], '</option>';
					}
				}

				echo '
									</select>
									<noscript>
										<input type="submit" value="', $txt['pm_apply'], '" />
									</noscript>';
			}

			echo '
								</div>';
		}

		// Are there any custom profile fields for above the signature?
		// Show them if signatures are enabled and you want to see them.
		if (!empty($message['member']['custom_fields']) && empty($options['show_no_signatures']) && $context['signature_enabled'])
		{
			$shown = false;
			foreach ($message['member']['custom_fields'] as $custom)
			{
				if ($custom['placement'] != 2 || empty($custom['value']))
				{
					continue;
				}

				if (!$shown)
				{
					$shown = true;
					echo '
								<div class="custom_fields_above_signature">
									<ul>';
				}

				echo '
										<li>', $custom['value'], '</li>';
			}

			if ($shown)
			{
				echo '
									</ul>
								</div>';
			}
		}

		// Show the member's signature?
		if (!empty($message['member']['signature']) && empty($options['show_no_signatures']) && $context['signature_enabled'])
		{
			echo '
								<div class="signature">', $message['member']['signature'], '</div>';
		}

		echo '
							</div>
						</article>';
	}

	echo '
					</div>';

}

/**
 * Used to display items above the page, like page navigation
 */
function template_pm_pages_and_buttons_above()
{
	global $context;

	// Show a few buttons if we are in conversation mode and outputting the first message.
	if ($context['display_mode'] == 2)
	{
		template_pagesection('conversation_buttons', 'right', array('page_index' => false));
	}
	else
	{
		template_pagesection();
	}
}

/**
 * Used to display items below the page, like page navigation
 */
function template_pm_pages_and_buttons_below()
{
	global $context, $txt;

	if (empty($context['display_mode']))
	{
		template_pagesection(false, '', array('extra' => '<span class="flow_flex_right"><input type="submit" name="del_selected" value="' . $txt['quickmod_delete_selected'] . '" style="font-weight: normal;" onclick="if (!confirm(\'' . $txt['delete_selected_confirm'] . '\')) return false;" /></span>'));
	}
	// Show a few buttons if we are in conversation mode and outputting the first message.
	elseif ($context['display_mode'] == 2 && isset($context['conversation_buttons']))
	{
		template_pagesection('conversation_buttons', 'right', array('page_index' => false));
	}
}

/**
 * Just list all the personal message subjects - to make templates easier.
 * Unfortunately a bit ugly at the moment
 */
function template_subject_list_above()
{
	global $context;

	// If we are not in single display mode show the subjects on the top!
	if ($context['display_mode'] != 1)
	{
		template_subject_list();

		echo '
					<hr class="new_post_separator" />';
	}
}

/**
 * Template layer to show items below the subject list
 */
function template_subject_list_below()
{
	global $context;

	// Individual messages = button list!
	if ($context['display_mode'] == 1)
	{
		template_subject_list();
	}
}

/**
 * Template layer to show the PM subject listing
 */
function template_subject_list()
{
	global $context, $settings, $txt, $scripturl;

	echo '
					<table class="table_grid">
						<thead>
							<tr class="table_head">
								<th class="pm_icon grid4">
									<a href="', $scripturl, '?action=pm;view;f=', $context['folder'], ';start=', $context['start'], ';sort=', $context['sort_by'], ($context['sort_direction'] === 'up' ? ';asc' : ';desc'), ($context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : ''), '">
										<i class="icon i-shuffle" title="', $txt['pm_change_view'], '"><s>', $txt['pm_change_view'], '</s></i>
									</a>
								</th>
								<th class="pm_date grid20">
									<a href="', $scripturl, '?action=pm;f=', $context['folder'], ';start=', $context['start'], ';sort=date', ($context['sort_by'] === 'date' && $context['sort_direction'] === 'up' ? ';desc' : ';asc'), $context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '', '">
										', $txt['date'], $context['sort_by'] === 'date' ? ' <i class="icon icon-small i-sort-numeric-' . $context['sort_direction'] . '"></i>' : '', '
									</a>
								</th>
								<th class="pm_subject">
									<a href="', $scripturl, '?action=pm;f=', $context['folder'], ';start=', $context['start'], ';sort=subject', ($context['sort_by'] === 'subject' && $context['sort_direction'] === 'up' ? ';desc' : ';asc'), $context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '', '">
										', $txt['subject'], $context['sort_by'] === 'subject' ? ' <i class="icon icon-small i-sort-alpha-' . $context['sort_direction'] . '"></i>' : '', '
									</a>
								</th>
								<th class="pm_from grid20">
									<a href="', $scripturl, '?action=pm;f=', $context['folder'], ';start=', $context['start'], ';sort=name', ($context['sort_by'] === 'name' && $context['sort_direction'] === 'up' ? ';desc' : ';asc'), $context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '', '">
										', ($context['from_or_to'] === 'from' ? $txt['from'] : $txt['to']), $context['sort_by'] === 'name' ? ' <i class="icon icon-small i-sort-alpha-' . $context['sort_direction'] . '"></i>' : '', '
									</a>
								</th>
								<th class="pm_quickmod grid4">
									<input type="checkbox" onclick="invertAll(this, this.form);" />
								</th>
							</tr>
						</thead>
						<tbody>';

	if (!$context['show_delete'])
	{
		echo '
							<tr class="standard_row">
								<td colspan="5">', $txt['pm_alert_none'], '</td>
							</tr>';
	}

	// Use the query callback to get the subject list
	$controller = $context['get_psubject'][0];
	while (($message = $controller->{$context['get_psubject'][1]}()))
	{
		$discussion_url = $context['display_mode'] == 0 || $context['current_pm'] == $message['id'] ? '' : ($scripturl . '?action=pm;pmid=' . $message['id'] . ';kstart;f=' . $context['folder'] . ';start=' . $context['start'] . ';sort=' . $context['sort_by'] . ($context['sort_direction'] == 'up' ? ';asc' : ';desc') . ($context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : ''));

		echo '
							<tr class="standard_row">
								<td class="pm_icon">
									<script>
										currentLabels[', $message['id'], '] = {';

		if (!empty($message['labels']))
		{
			$first = true;
			foreach ($message['labels'] as $label)
			{
				echo $first ? '' : ',', '
										"', $label['id'], '": "', $label['name'], '"';
				$first = false;
			}
		}

		echo '
										};
									</script>
									', $message['is_replied_to'] ? '<i class="icon i-reply"><s>' . $txt['pm_replied'] . '</s></i>' : '<i class="icon i-comment-blank"></i>', '</td>
								<td class="pm_date">', $message['time'], '</td>
								<td class="pm_subject">',
									$context['display_mode'] != 0 && $context['current_pm'] == $message['id'] ? '<img src="' . $settings['images_url'] . '/selected.png" alt="*" />' : '',
									$message['is_unread'] ? '<a href="' . $discussion_url . '#msg_' . $message['id'] . '" class="new_posts">' . $txt['new'] . '</a>' : '', '
									<a href="', $discussion_url, '#msg_', $message['id'], '">
										', $message['subject'], '
									</a>
								</td>
								<td class="pm_from">
									', ($context['from_or_to'] == 'from' ? $message['member']['link'] : (empty($message['recipients']['to']) ? '' : implode(', ', $message['recipients']['to']))), '
								</td>
								<td class="pm_quickmod">
									<input type="checkbox" name="pms[]" id="deletelisting', $message['id'], '" value="', $message['id'], '"', $message['is_selected'] ? ' checked="checked"' : '', ' onclick="if (document.getElementById(\'deletedisplay', $message['id'], '\')) document.getElementById(\'deletedisplay', $message['id'], '\').checked = this.checked;" />
								</td>
							</tr>';
	}

	echo '
						</tbody>
					</table>';

	$extra = '
					<ul class="label_pms">';

	if ($context['show_delete'])
	{
		if (!empty($context['currently_using_labels']) && $context['folder'] != 'sent')
		{
			$extra .= '
						<li>
							<select name="pm_action" onchange="if (this.options[this.selectedIndex].value) this.form.submit();" onfocus="loadLabelChoices();">
								<option value="">' . $txt['pm_sel_label_title'] . ':</option>
								<option value="" disabled="disabled">' . str_repeat('&#8212;', strlen($txt['pm_sel_label_title'])) . '</option>';

			$extra .= '
								<option value="" disabled="disabled">' . $txt['pm_msg_label_apply'] . ':</option>';

			foreach ($context['labels'] as $label)
			{
				if ($label['id'] != $context['current_label_id'])
				{
					$extra .= '
								<option value="add_' . $label['id'] . '">&#10148;&nbsp;' . $label['name'] . '</option>';
				}
			}

			$extra .= '
								<option value="" disabled="disabled">' . $txt['pm_msg_label_remove'] . ':</option>';

			foreach ($context['labels'] as $label)
			{
				$extra .= '
								<option value="rem_' . $label['id'] . '">&#10148;&nbsp;' . $label['name'] . '</option>';
			}

			$extra .= '
							</select>
							<noscript>
								<input type="submit" value="' . $txt['pm_apply'] . '" class="right_submit" />
							</noscript>
						</li>';
		}

		$extra .= '
						<li><input type="submit" name="del_selected" value="' . $txt['quickmod_delete_selected'] . '" onclick="if (!confirm(\'' . $txt['delete_selected_confirm'] . '\')) return false;" class="right_submit" /></li>';
	}

	$extra .= '
					</ul>';

	template_pagesection(false, '', array('extra' => $extra));
}

/**
 * Page to search in PMs.
 */
function template_search()
{
	global $context, $scripturl, $txt;

	echo '
	<form id="searchform" action="', $scripturl, '?action=pm;sa=search2" method="post" accept-charset="UTF-8" role="search" name="searchform" >
		<h2 class="category_header">', $txt['pm_search_title'], '</h2>';

	// Any search errors we need to let them know about
	if (!empty($context['search_errors']))
	{
		echo '
		<p class="errorbox">', implode('<br />', $context['search_errors']['messages']), '</p>';
	}

	// Start with showing the basic search input box
	echo '
		<fieldset id="simple_search" class="content">
			<div id="search_term_input">
				<label for="search">
					<strong>', $txt['pm_search_text'], ':</strong>
				</label>
				<input type="search" id="search" class="input_text" name="search"', !empty($context['search_params']['search']) ? ' value="' . $context['search_params']['search'] . '"' : '', ' size="40" placeholder="', $txt['search'], '" required="required" autofocus="autofocus" />
				<input type="submit" name="pm_search" value="', $txt['pm_search_go'], '" />
			</div>';

	// Now all the advanced options, hidden or shown by JS based on the users minmax choices
	echo '
			<div id="advanced_search">
				<dl id="search_options">
					<dt class="righttext">
						<label for="searchtype">', $txt['search_match'], ':</label>
					</dt>
					<dd>
						<select name="searchtype">
							<option value="1"', empty($context['search_params']['searchtype']) ? ' selected="selected"' : '', '>', $txt['pm_search_match_all'], '</option>
							<option value="2"', !empty($context['search_params']['searchtype']) ? ' selected="selected"' : '', '>', $txt['pm_search_match_any'], '</option>
						</select>
					</dd>
					<dt>
						<label for="userspec">', $txt['pm_search_user'], ':</label>
					</dt>
					<dd>
						<input type="text" name="userspec" value="', empty($context['search_params']['userspec']) ? '*' : $context['search_params']['userspec'], '" size="40" class="input_text" />
					</dd>
					<dt>
						<label for="sort">', $txt['pm_search_order'], ':</label>
					</dt>
					<dd>
						<select name="sort" id="sort">
							<option value="relevance|desc">', $txt['pm_search_orderby_relevant_first'], '</option>
							<option value="id_pm|desc">', $txt['pm_search_orderby_recent_first'], '</option>
							<option value="id_pm|asc">', $txt['pm_search_orderby_old_first'], '</option>
						</select>
					</dd>
					<dt class="options">
						', $txt['pm_search_options'], ':
					</dt>
					<dd class="options">
						<input type="checkbox" name="show_complete" id="show_complete" value="1"', !empty($context['search_params']['show_complete']) ? ' checked="checked"' : '', ' />
						<label for="show_complete">', $txt['pm_search_show_complete'], '</label><br />
						<input type="checkbox" name="subject_only" id="subject_only" value="1"', !empty($context['search_params']['subject_only']) ? ' checked="checked"' : '', ' />
						<label for="subject_only">', $txt['pm_search_subject_only'], '</label><br />
						<input type="checkbox" name="sent_only" id="sent_only" value="1"', !empty($context['search_params']['sent_only']) ? ' checked="checked"' : '', ' />
						<label for="sent_only">', $txt['pm_search_sent_only'], '</label>
					</dd>
					<dt class="righttext between">
						', $txt['pm_search_post_age'], ':
					</dt>
					<dd>
						<label for="minage">', $txt['pm_search_between'], '</label>&nbsp;
						 <input type="text" id="minage" name="minage" value="', empty($context['search_params']['minage']) ? '0' : $context['search_params']['minage'], '" size="5" maxlength="5" class="input_text" />
						<label for="maxage">', $txt['pm_search_between_and'], '&nbsp;</label>
						<input type="text" name="maxage" id="maxage" value="', empty($context['search_params']['maxage']) ? '9999' : $context['search_params']['maxage'], '" size="5" maxlength="5" class="input_text" />', $txt['pm_search_between_days'], '
					</dd>
				</dl>
			</div>
			<a id="upshrink_link" href="', $scripturl, '?action=search;advanced" class="linkbutton hide">', $txt['pm_search_simple'], '</a>';

	// Set the initial search style for the form
	echo '
			<input id="advanced" type="hidden" name="advanced" value="1" />
		</fieldset>';

	// Do we have some labels setup? If so offer to search by them!
	if ($context['currently_using_labels'])
	{
		echo '
		<fieldset class="content">
			<h3 class="secondary_header panel_toggle">
					<i id="advanced_panel_toggle" class="chevricon i-chevron-', empty($context['minmax_preferences']['pm']) ? 'up' : 'down', ' hide" title="', $txt['hide'], '"></i>
				<a href="#" id="advanced_panel_link">', $txt['pm_search_choose_label'], '</a>
			</h3>
			<div id="advanced_panel_div" class="well', empty($context['minmax_preferences']['pm']) ? '"' : ' hide"', '>
				<ul id="searchLabelsExpand">';

		foreach ($context['search_labels'] as $label)
		{
			echo '
					<li>
						<label for="searchlabel_', $label['id'], '"><input type="checkbox" id="searchlabel_', $label['id'], '" name="searchlabel[', $label['id'], ']" value="', $label['id'], '" ', $label['checked'] ? 'checked="checked"' : '', ' />
						', $label['name'], '</label>
					</li>';
		}

		echo '
				</ul>
			</div>
			<div class="submitbuttons">
				<input type="checkbox" name="all" id="check_all" value="" ', $context['check_all'] ? 'checked="checked"' : '', ' onclick="invertAll(this, this.form, \'searchlabel\');" />
 				<em> <label for="check_all">', $txt['check_all'], '</label></em>
			</div>
		</fieldset>';

		// And now some javascript for the advanced label toggling
		theme()->addInlineJavascript('
			createEventListener(window);
			window.addEventListener("load", initSearch, false);

			var oAdvancedPanelToggle = new elk_Toggle({
				bToggleEnabled: true,
				bCurrentlyCollapsed: ' . (empty($context['minmax_preferences']['pm']) ? 'false' : 'true') . ',
				aSwappableContainers: [
					\'advanced_panel_div\'
				],
				aSwapClasses: [
					{
						sId: \'advanced_panel_toggle\',
						classExpanded: \'chevricon i-chevron-up\',
						titleExpanded: ' . JavaScriptEscape($txt['hide']) . ',
						classCollapsed: \'chevricon i-chevron-down\',
						titleCollapsed: ' . JavaScriptEscape($txt['show']) . '
					}
				],
				aSwapLinks: [
					{
						sId: \'advanced_panel_link\',
						msgExpanded: ' . JavaScriptEscape($txt['pm_search_choose_label']) . ',
						msgCollapsed: ' . JavaScriptEscape($txt['pm_search_choose_label']) . '
					}
				],
				oThemeOptions: {
					bUseThemeSettings: ' . ($context['user']['is_guest'] ? 'false' : 'true') . ',
					sOptionName: \'minmax_preferences\',
					sSessionId: elk_session_id,
					sSessionVar: elk_session_var,
					sAdditionalVars: \';minmax_key=pm\'
				},
			});', true);
	}

	// And the JS to make the advanced / basic form work
	theme()->addInlineJavascript('
		// Set the search style
		document.getElementById(\'advanced\').value = "' . (empty($context['minmax_preferences']['pmsearch']) ? '1' : '0') . '";

		// And allow for the collapsing of the advanced search options
		var oSearchToggle = new elk_Toggle({
			bToggleEnabled: true,
			bCurrentlyCollapsed: ' . (empty($context['minmax_preferences']['pmsearch']) ? 'false' : 'true') . ',
			funcOnBeforeCollapse: function () {
				document.getElementById(\'advanced\').value = \'0\';
			},
			funcOnBeforeExpand: function () {
				document.getElementById(\'advanced\').value = \'1\';
			},
			aSwappableContainers: [
				\'advanced_search\'
			],
			aSwapLinks: [
				{
					sId: \'upshrink_link\',
					msgExpanded: ' . JavaScriptEscape($txt['search_simple']) . ',
					msgCollapsed: ' . JavaScriptEscape($txt['search_advanced']) . '
				}
			],
			oThemeOptions: {
				bUseThemeSettings: ' . ($context['user']['is_guest'] ? 'false' : 'true') . ',
				sOptionName: \'minmax_preferences\',
				sSessionId: elk_session_id,
				sSessionVar: elk_session_var,
				sAdditionalVars: \';minmax_key=pmsearch\'
			},
		});', true);

	echo '
	</form>';
}

/**
 * Template for the results of search in PMs.
 */
function template_search_results()
{
	global $context, $scripturl, $txt;

	echo '
		<div class="search_results_posts', empty($context['search_params']['show_complete']) ? ' compact_view' : '', '">
			<h2 class="category_header hdicon cat_img_search">
				', $txt['mlist_search_results'], ':&nbsp;', $context['search_params']['search'], '
			</h2>';

	// Was anything even found?
	if (!empty($context['personal_messages']))
	{
		template_pagesection();
	}
	else
	{
		echo '
			<div class="well">', $txt['find_no_results'], '</div>';
	}

	// Showing complete results?
	if (empty($context['search_params']['show_complete']))
	{
		echo '
			<table class="table_grid">
				<thead>
					<tr class="table_head">
						<th class="lefttext grid30">', $txt['date'], '</th>
						<th class="lefttext grid50">', $txt['subject'], '</th>
						<th class="lefttext grid20">', $txt['from'], '</th>
					</tr>
				</thead>
				<tbody>';
	}
	else
	{
		echo '
			<ul class="forumposts topic_listing search_results_posts">';
	}

	// Print each message out...
	foreach ($context['personal_messages'] as $message)
	{
		if (!empty($context['search_params']['show_complete']))
		{
			echo '
				<li class="basic_row">
					<div class="topic_details">
						<div class="counter">', $message['counter'], '</div>
						<h5>
							<a href="', $message['href'], '">', $message['subject'], '</a>
							<span class="smalltext"> ', $txt['search_on'], ': ', $message['time'], '
						</h5>
						<span class="smalltext">', $txt['from'], ': ', $message['member']['link'], ', ', $txt['to'], ': ';

			// Show the recipients.
			// @todo This doesn't deal with the sent item searching quite right for bcc.
			if (!empty($message['recipients']['to']))
			{
				echo implode(', ', $message['recipients']['to']);
			}
			// Otherwise, we're just going to say "some people"...
			elseif ($context['folder'] !== 'sent')
			{
				echo '(', $txt['pm_undisclosed_recipients'], ')';
			}

			echo '
						</span>
					</div>
					<div class="topic_body">
						', $message['body'], '
					</div>';

			if ($context['can_send_pm'])
			{
				echo '
					<nav>
						<ul class="quickbuttons">';

				// You can only reply if they are not a guest...
				if (!$message['member']['is_guest'])
				{
					echo '
							<li class="listlevel1"><a class="linklevel1 reply_button" href="', $scripturl, '?action=pm;sa=send;f=', $context['folder'], $context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '', ';pmsg=', $message['id'], ';u=', $message['member']['id'], '">', $txt['reply'], '</a></li>
							<li class="listlevel1"><a class="linklevel1 quote_button" href="', $scripturl, '?action=pm;sa=send;f=', $context['folder'], $context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '', ';pmsg=', $message['id'], ';quote;u=', $context['folder'] == 'sent' ? '' : $message['member']['id'], '">', $txt['quote'], '</a></li>';
				}
				// This is for "forwarding" - even if the member is gone.
				else
				{
					echo '
							<li class="listlevel1"><a class="linklevel1 quote_button" href="', $scripturl, '?action=pm;sa=send;f=', $context['folder'], $context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '', ';pmsg=', $message['id'], ';quote">', $txt['quote'], '</a></li>';
				}
				echo '
						</ul>
					</nav>';
			}

			echo '
					</li>';
		}
		// Otherwise just a simple list!
		else
		{
			// @todo No context at all of the search?
			echo '
					<tr>
						<td>', $message['time'], '</td>
						<td>', $message['link'], '</td>
						<td>', $message['member']['link'], '</td>
					</tr>';
		}
	}

	if (!empty($context['search_params']['show_complete']))
	{
		echo '
			</ul>';
	}
	else
	{
		echo '
				</tbody>
			</table>';
	}

	// If we have results show a page index
	if (!empty($context['personal_messages']))
	{
		template_pagesection();
	}

	echo '
		</div>';
}

/**
 * Show send a new pm form, including the editor, preview section and load
 * drafts if enabled.
 */
function template_send()
{
	global $context, $scripturl, $modSettings, $txt, $options;

	// Show which messages were sent successfully and which failed.
	if (!empty($context['send_log']))
	{
		echo '
			<div class="forumposts">
				<h2 class="category_header">', $txt['pm_send_report'], '</h2>
				<div class="content">';

		if (!empty($context['send_log']['sent']))
		{
			foreach ($context['send_log']['sent'] as $log_entry)
			{
				echo '
					<span class="error">', $log_entry, '</span><br />';
			}
		}

		if (!empty($context['send_log']['failed']))
		{
			foreach ($context['send_log']['failed'] as $log_entry)
			{
				echo '
					<span class="error">', $log_entry, '</span><br />';
			}
		}

		echo '
				</div>
			</div>';
	}

	// Show the preview of the personal message.
	echo '
		<div id="preview_section"', isset($context['preview_message']) ? '' : ' class="hide"', '>
			<h2 class="category_header">
				<span id="preview_subject">', empty($context['preview_subject']) ? '' : $context['preview_subject'], '</span>
			</h2>
			<div id="preview_body">
				', empty($context['preview_message']) ? '<br />' : $context['preview_message'], '
			</div>
		</div>';

	// Main message editing box.
	echo '
	<form id="pmFolder" action="', $scripturl, '?action=pm;sa=send2" method="post" accept-charset="UTF-8" name="pmFolder" onsubmit="submitonce(this);smc_saveEntities(\'pmFolder\', [\'subject\', \'message\']);">
		<h2 class="category_header hdicon cat_img_write">
			', $txt['new_message'], '
		</h2>';

	echo '
		<div class="content forumposts">
			<div class="editor_wrapper">';

	// If there were errors for sending the PM, show them.
	template_show_error('post_error');

	if (!empty($modSettings['drafts_pm_enabled']))
	{
		echo '
				<div id="draft_section" class="successbox', isset($context['draft_saved']) ? '"' : ' hide"', '>',
					sprintf($txt['draft_pm_saved'], $scripturl . '?action=pm;sa=showpmdrafts'), '
				</div>';
	}

	echo '
				<dl id="post_header">';

	// To and bcc. Include a button to search for members.
	echo '
					<dt>
						<label for="to_control"', (isset($context['post_error']['no_to']) || isset($context['post_error']['bad_to']) ? ' class="error"' : ''), ' id="caption_to">', $txt['pm_to'], ':</label>
					</dt>';

	// Autosuggest will be added by the javascript later on.
	echo '
					<dd id="pm_to" class="clear_right">
						<input type="text" name="to" id="to_control" value="', $context['to_value'], '" tabindex="', $context['tabindex']++, '" size="40" style="width: 130px;" class="input_text" />';

	// A link to add BCC, only visible with javascript enabled.
	echo '
						<span id="bcc_link_container" class="smalltext hide"></span>';

	// A div that'll contain the items found by the autosuggest.
	echo '
						<div id="to_item_list_container"></div>';

	echo '
					</dd>';

	// This BCC row will be hidden by default if javascript is enabled.
	echo '
					<dt id="bcc_div" class="clear_left" >
						<label for="bcc_control"', (isset($context['post_error']['no_to']) || isset($context['post_error']['bad_bcc']) ? ' class="error"' : ''), ' id="caption_bbc">', $txt['pm_bcc'], ':</label>
					</dt>
					<dd id="bcc_div2">
						<input type="text" name="bcc" id="bcc_control" value="', $context['bcc_value'], '" tabindex="', $context['tabindex']++, '" size="40" style="width: 130px;" class="input_text" />
						<div id="bcc_item_list_container"></div>
					</dd>';

	// The subject of the PM.
	echo '
					<dt class="clear_left">
						<label for="subject"', (isset($context['post_error']['no_subject']) ? ' class="error"' : ''), ' id="caption_subject">', $txt['subject'], ':</label>
					</dt>
					<dd id="pm_subject">
						<input type="text" id="subject" name="subject" value="', $context['subject'], '" tabindex="', $context['tabindex']++, '" size="80" maxlength="80"', isset($context['post_error']['no_subject']) ? ' class="error"' : ' class="input_text"', ' placeholder="', $txt['subject'], '" required="required" />
					</dd>
				</dl>';

	// Show BBC buttons, smileys and textbox.
	template_control_richedit($context['post_box_name'], 'smileyBox_message', 'bbcBox_message');

	// Require an image to be typed to save spamming?
	if (!empty($context['require_verification']))
	{
		template_verification_controls($context['visual_verification_id'], '
				<div class="post_verification">
					<strong>' . $txt['pm_visual_verification_label'] . ':</strong>
					', '
				</div>');
	}

	// Send, Preview buttons.
	echo '
				<div id="post_confirm_buttons" class="submitbutton">';

	template_control_richedit_buttons($context['post_box_name']);

	echo '
				</div>';

	// Show the draft last saved on area
	if (!empty($context['drafts_autosave']) && !empty($options['drafts_autosave_enabled']))
	{
		echo '
		 		<div class="draftautosave">
					<span id="throbber" class="hide"><i class="icon i-oval"></i>&nbsp;</span>
					<span id="draft_lastautosave"></span>
				</div>';
	}

	echo '
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '" />
				<input type="hidden" name="replied_to" value="', !empty($context['quoted_message']['id']) ? $context['quoted_message']['id'] : 0, '" />
				<input type="hidden" name="pm_head" value="', !empty($context['quoted_message']['pm_head']) ? $context['quoted_message']['pm_head'] : 0, '" />
				<input type="hidden" name="f" value="', $context['folder'] ?? '', '" />
				<input type="hidden" name="l" value="', $context['current_label_id'] ?? -1, '" />';

	// If the admin enabled the pm drafts feature, show a draft selection box
	if (!empty($context['drafts_pm_save']) && !empty($context['drafts']))
	{
		echo '
				<h3 id="postDraftOptionsHeader" class="category_header panel_toggle">
					<i id="postDraftExpand" class="chevricon i-chevron-', empty($context['minmax_preferences']['pmdraft']) ? 'up' : 'down', ' hide" title="', $txt['hide'], '"></i>
					<a href="#" id="postDraftExpandLink">', $txt['draft_load'], '</a>
				</h3>
				<div id="postDraftOptions" class="load_drafts padding', empty($context['minmax_preferences']['pmdraft']) ? '"' : ' hide"', '>
					<dl class="settings">
						<dt><strong>', $txt['subject'], '</strong></dt>
						<dd><strong>', $txt['draft_saved_on'], '</strong></dd>';

		foreach ($context['drafts'] as $draft)
		{
			echo '
						<dt>', $draft['link'], '</dt>
						<dd>', $draft['poster_time'], '</dd>';
		}

		echo '
					</dl>
				</div>';
	}

	echo '
			</div>
		</div>
	</form>';

	// The vars used to preview a personal message without loading a new page.
	echo '
	<script>
		var form_name = "pmFolder",
			preview_area = "pm",
			txt_preview_title = "', $txt['preview_title'], '",
			txt_preview_fetch = "', $txt['preview_fetch'], '";
	</script>';

	// Code for showing and hiding drafts
	if (!empty($context['drafts']))
	{
		theme()->addInlineJavascript('
		var oSwapDraftOptions = new elk_Toggle({
			bToggleEnabled: true,
			bCurrentlyCollapsed: ' . (empty($context['minmax_preferences']['pmdraft']) ? 'false' : 'true') . ',
			aSwappableContainers: [
				\'postDraftOptions\',
			],
			aSwapClasses: [
				{
					sId: \'postDraftExpand\',
					classExpanded: \'chevricon i-chevron-up\',
					titleExpanded: ' . JavaScriptEscape($txt['hide']) . ',
					classCollapsed: \'chevricon i-chevron-down\',
					titleCollapsed: ' . JavaScriptEscape($txt['show']) . '
				}
			],
			aSwapLinks: [
				{
					sId: \'postDraftExpandLink\',
					msgExpanded: ' . JavaScriptEscape($txt['draft_hide']) . ',
					msgCollapsed: ' . JavaScriptEscape($txt['draft_load']) . '
				}
			],
			oThemeOptions: {
				bUseThemeSettings: ' . ($context['user']['is_guest'] ? 'false' : 'true') . ',
				sOptionName: \'minmax_preferences\',
				sSessionId: elk_session_id,
				sSessionVar: elk_session_var,
				sAdditionalVars: \';minmax_key=pmdraft\'
			},
		});', true);
	}

	// Show the message you're replying to.
	if ($context['reply'])
	{
		echo '

	<div class="forumposts">
		<h2 class="category_header">', $txt['subject'], ': ', $context['quoted_message']['subject'], '</h2>
		<div class="content">
			<div class="clear">
				<span class="smalltext floatright">', $txt['on'], ': ', $context['quoted_message']['time'], '</span>
				<strong>', $txt['from'], ': ', $context['quoted_message']['member']['name'], '</strong>
			</div>
			<hr />
			', $context['quoted_message']['body'], '
		</div>
	</div>';
	}

	echo '
		<script>
			new elk_PersonalMessageSend({
				sSessionId: elk_session_id,
				sSessionVar: elk_session_var,
				sTextDeleteItem: ', JavaScriptEscape($txt['autosuggest_delete_item']), ',
				sToControlId: \'to_control\',
				aToRecipients: [';

	foreach ($context['recipients']['to'] as $i => $member)
	{
		echo '
					{
						sItemId: ', JavaScriptEscape($member['id']), ',
						sItemName: ', JavaScriptEscape($member['name']), '
					}', $i == count($context['recipients']['to']) - 1 ? '' : ',';
	}

	echo '
				],
				aBccRecipients: [';

	foreach ($context['recipients']['bcc'] as $i => $member)
	{
		echo '
					{
						sItemId: ', JavaScriptEscape($member['id']), ',
						sItemName: ', JavaScriptEscape($member['name']), '
					}', $i == count($context['recipients']['bcc']) - 1 ? '' : ',';
	}

	echo '
				],
				sBccControlId: \'bcc_control\',
				sBccDivId: \'bcc_div\',
				sBccDivId2: \'bcc_div2\',
				sBccLinkId: \'bcc_link\',
				sBccLinkContainerId: \'bcc_link_container\',
				bBccShowByDefault: ', empty($context['recipients']['bcc']) && empty($context['bcc_value']) ? 'false' : 'true', ',
				sShowBccLinkTemplate: ', JavaScriptEscape('
					<a href="#" id="bcc_link">' . $txt['make_bcc'] . '</a> <a href="' . $scripturl . '?action=quickhelp;help=pm_bcc" onclick="return reqOverlayDiv(this.href);" class="helpicon i-help"><s>' . $txt['help'] . '</s></a>'
				), '
			});
		</script>';
}

/**
 * This template asks the user whether they wish to empty out their folder/messages.
 */
function template_ask_delete()
{
	global $context, $scripturl, $txt;

	echo '
		<h2 class="category_header">', ($context['delete_all'] ? $txt['delete_message'] : $txt['delete_all']), '</h2>
		<div class="content">
			<p>', $txt['delete_all_confirm'], '</p><br />
			<strong><a href="', $scripturl, '?action=pm;sa=removeall2;f=', $context['folder'], ';', $context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '', ';', $context['session_var'], '=', $context['session_id'], '">', $txt['yes'], '</a> - <a href="javascript:window.location.assign(document.referrer);">', $txt['no'], '</a></strong>
		</div>';
}

/**
 * This template asks the user what messages they want to prune.
 */
function template_prune()
{
	global $context, $scripturl, $txt;

	echo '
	<form id="prune_pm" action="', $scripturl, '?action=pm;sa=prune" method="post" accept-charset="UTF-8" onsubmit="return confirm(\'', $txt['pm_prune_warning'], '\');">
		<h2 class="category_header">', $txt['pm_prune'], '</h2>
		<div class="content">
			<p>
				<label for="age">', sprintf($txt['pm_prune_desc'], '<input type="text" id="age" name="age" size="3" value="14" class="input_text" />'), '</label>
			</p>
			<div class="submitbutton">
				<input type="submit" value="', $txt['delete'], '" />
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			</div>
		</div>
	</form>';
}

/**
 * Here we allow the user to setup labels, remove labels and change rules for labels (i.e, do quite a bit)
 */
function template_labels()
{
	global $context, $scripturl, $txt;

	echo '
	<form class="flow_auto" action="', $scripturl, '?action=pm;sa=manlabels" method="post" accept-charset="UTF-8">
		<h2 class="category_header">', $txt['pm_manage_labels'], '</h2>
		<div class="description">
			', $txt['pm_labels_desc'], '
		</div>
		<table class="table_grid">
		<thead>
			<tr class="table_head">
				<th class="lefttext">
					', $txt['pm_label_name'], '
				</th>
				<th style="width: 4%;">';

	if (count($context['labels']) > 2)
	{
		echo '
					<input type="checkbox" onclick="invertAll(this, this.form);" />';
	}

	echo '
				</th>
			</tr>
		</thead>
		<tbody>';

	if (count($context['labels']) < 2)
	{
		echo '
			<tr>
				<td colspan="2" class="centertext">', $txt['pm_labels_no_exist'], '</td>
			</tr>';
	}
	else
	{
		foreach ($context['labels'] as $label)
		{
			if ($label['id'] == -1)
			{
				continue;
			}

			echo '
			<tr>
				<td>
					<input type="text" name="label_name[', $label['id'], ']" value="', $label['name'], '" size="30" maxlength="30" class="input_text" />
				</td>
				<td style="width: 4%;">
					<input type="checkbox" name="delete_label[', $label['id'], ']" />
				</td>
			</tr>';
		}
	}

	echo '
		</tbody>
		</table>';

	if (!count($context['labels']) < 2)
	{
		echo '
		<div class="submitbutton">
			<input type="submit" name="save" value="', $txt['save'], '" />
			<input type="submit" name="delete" value="', $txt['quickmod_delete_selected'], '" onclick="return confirm(\'', $txt['pm_labels_delete'], '\');" />
		</div>';
	}

	echo '
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
	</form>
	<br />
	<form class="flow_auto" action="', $scripturl, '?action=pm;sa=manlabels" method="post" accept-charset="UTF-8" style="margin-top: 1ex;">
		<h2 class="category_header">', $txt['pm_label_add_new'], '</h2>
		<div class="content">
			<dl class="settings">
				<dt>
					<label for="add_label">', $txt['pm_label_name'], ':</label>
				</dt>
				<dd>
					<input type="text" id="add_label" name="label" value="" size="30" maxlength="30" class="input_text" />
				</dd>
			</dl>
			<div class="submitbutton">
				<input type="submit" name="add" value="', $txt['pm_label_add_new'], '" />
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			</div>
		</div>
	</form>';
}

/**
 * Template for reporting a personal message.
 */
function template_report_message()
{
	global $context, $txt, $scripturl;

	echo '
	<form action="', $scripturl, '?action=pm;sa=report;l=', $context['current_label_id'], '" method="post" accept-charset="UTF-8">
		<h2 class="category_header">', $txt['pm_report_title'], '</h2>
		<div class="description">
			', $txt['pm_report_desc'], '
		</div>
		<div class="content">
			<dl class="settings">
				<dt>
					', $txt['pm_report_reason'], ':
				</dt>
				<dd>
					<textarea id="reason" name="reason" rows="4" cols="70" style="width: 95%;"></textarea>
				</dd>
			</dl>
			<div class="submitbutton">
				<input type="submit" name="report" value="', $txt['pm_report_message'], '" />
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input type="hidden" name="pmsg" value="', $context['pm_id'], '" />
			</div>
		</div>
	</form>';
}

/**
 * Little template just to say "Yep, it's been reported".
 */
function template_report_message_complete()
{
	global $context, $txt, $scripturl;

	echo '
		<h2 class="category_header hdicon cat_img_moderation">', $txt['pm_report_title'], '</h2>
		<div class="well">
			<div class="content">
				<p>', $txt['pm_report_done'], '</p>
				<a class="linkbutton floatright" href="', $scripturl, '?action=pm;l=', $context['current_label_id'], '">', $txt['pm_report_return'], '</a>
			</div>
		</div>';
}

/**
 * Manage rules.
 */
function template_rules()
{
	global $context, $txt, $scripturl;

	echo '
	<form action="', $scripturl, '?action=pm;sa=manrules" method="post" accept-charset="UTF-8" name="manRules" id="manrules">
		<h2 class="category_header">', $txt['pm_manage_rules'], '</h2>
		<div class="description">
			', $txt['pm_manage_rules_desc'], '
		</div>
		<table class="table_grid">
		<thead>
			<tr class="table_head">
				<th class="lefttext">
					', $txt['pm_rule_title'], '
				</th>
				<th style="width: 4%;">';

	if (!empty($context['rules']))
	{
		echo '
					<input type="checkbox" onclick="invertAll(this, this.form);" />';
	}

	echo '
				</th>
			</tr>
		</thead>
		<tbody>';

	if (empty($context['rules']))
	{
		echo '
			<tr>
				<td colspan="2" class="centertext">
					', $txt['pm_rules_none'], '
				</td>
			</tr>';
	}

	foreach ($context['rules'] as $rule)
	{
		echo '
			<tr>
				<td>
					<a href="', $scripturl, '?action=pm;sa=manrules;add;rid=', $rule['id'], '">', $rule['name'], '</a>
				</td>
				<td style="width: 4%;">
					<input type="checkbox" name="delrule[', $rule['id'], ']" />
				</td>
			</tr>';
	}

	echo '
		</tbody>
		</table>
		<div class="submitbutton">
			<a class="linkbutton" href="', $scripturl, '?action=pm;sa=manrules;add;rid=0">', $txt['pm_add_rule'], '</a>';

	if (!empty($context['rules']))
	{
		echo '
			<a class="linkbutton" href="', $scripturl, '?action=pm;sa=manrules;apply;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['pm_js_apply_rules_confirm'], '\');">', $txt['pm_apply_rules'], '</a>';
	}

	if (!empty($context['rules']))
	{
		echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			<input type="submit" name="delselected" value="', $txt['pm_delete_selected_rule'], '" onclick="return confirm(\'', $txt['pm_js_delete_rule_confirm'], '\');" />';
	}

	echo '
		</div>
	</form>';
}

/**
 * Template for adding/editing a rule.
 */
function template_add_rule()
{
	global $context, $txt, $scripturl;

	echo '
	<form action="', $scripturl, '?action=pm;sa=manrules;save;rid=', $context['rid'], '" method="post" accept-charset="UTF-8" name="addrule" id="addrule" class="flow_hidden">
		<h2 class="category_header">', $context['rid'] == 0 ? $txt['pm_add_rule'] : $txt['pm_edit_rule'], '</h2>
		<div class="content">
			<dl class="addrules">
				<dt class="floatleft">
					<label for="rule_name">', $txt['pm_rule_name'], ':</label><br />
					<span class="smalltext">', $txt['pm_rule_name_desc'], '</span>
				</dt>
				<dd class="floatleft">
					<input type="text" id="rule_name" name="rule_name" value="', empty($context['rule']['name']) ? $txt['pm_rule_name_default'] : $context['rule']['name'], '" size="50" class="input_text" />
				</dd>
			</dl>
			<fieldset id="criteria">
				<legend>', $txt['pm_rule_criteria'], '</legend>';

	// For each criteria print it out.
	$isFirst = true;
	foreach ($context['rule']['criteria'] as $k => $criteria)
	{
		if (!$isFirst && $criteria['t'] == '')
		{
			echo '
				<div id="removeonjs1">';
		}
		elseif (!$isFirst)
		{
			echo '
				<br />';
		}

		echo '
					<select class="criteria" name="ruletype[', $k, ']" id="ruletype', $k, '" data-optnum="', $k, '">
						<option value="">', $txt['pm_rule_criteria_pick'], ':</option>';

		foreach ($context['known_rules'] as $rule)
		{
			echo '
						<option value="', $rule, '" ', $criteria['t'] == $rule ? 'selected="selected"' : '', '>', $txt['pm_rule_' . $rule], '</option>';
		}

		echo '
					</select>
					<span id="defdiv', $k, '" ', !in_array($criteria['t'], array('gid', 'bud')) ? '' : 'class="hide"', '>
						<input type="text" name="ruledef[', $k, ']" id="ruledef', $k, '" value="', in_array($criteria['t'], array('mid', 'sub', 'msg')) ? $criteria['v'] : '', '" class="input_text" />
					</span>
					<span id="defseldiv', $k, '" ', $criteria['t'] == 'gid' ? '' : 'class="hide"', '>
						<select class="criteria" name="ruledefgroup[', $k, ']" id="ruledefgroup', $k, '">
							<option value="">', $txt['pm_rule_sel_group'], '</option>';

		foreach ($context['groups'] as $id => $group)
		{
			echo '
							<option value="', $id, '" ', $criteria['t'] == 'gid' && $criteria['v'] == $id ? 'selected="selected"' : '', '>', $group, '</option>';
		}

		echo '
						</select>
					</span>';

		// If this is the dummy we add a means to hide for non js users.
		if ($isFirst)
		{
			$isFirst = false;
		}
		elseif ($criteria['t'] == '')
		{
			echo '
				</div>';
		}
	}

	echo '
				<span id="criteriaAddHere"></span>
				<br />
				<a id="addonjs1" class="linkbutton hide" href="#" onclick="addCriteriaOption(); return false;">', $txt['pm_rule_criteria_add'], '</a>
				<br /><br />
				', $txt['pm_rule_logic'], ':
				<select name="rule_logic" id="logic">
					<option value="and" ', $context['rule']['logic'] == 'and' ? 'selected="selected"' : '', '>', $txt['pm_rule_logic_and'], '</option>
					<option value="or" ', $context['rule']['logic'] == 'or' ? 'selected="selected"' : '', '>', $txt['pm_rule_logic_or'], '</option>
				</select>
			</fieldset>
			<fieldset id="actions">
				<legend>', $txt['pm_rule_actions'], '</legend>';

	// As with criteria - add a dummy action for "expansion".
	$context['rule']['actions'][] = array('t' => '', 'v' => '');

	// Print each action.
	$isFirst = true;
	foreach ($context['rule']['actions'] as $k => $action)
	{
		if (!$isFirst && $action['t'] == '')
		{
			echo '
				<div id="removeonjs2">';
		}
		elseif (!$isFirst)
		{
			echo '
				<br />';
		}

		echo '
					<select name="acttype[', $k, ']" id="acttype', $k, '" data-actnum="', $k, '">
						<option value="">', $txt['pm_rule_sel_action'], ':</option>
						<option value="lab" ', $action['t'] == 'lab' ? 'selected="selected"' : '', '>', $txt['pm_rule_label'], '</option>
						<option value="del" ', $action['t'] == 'del' ? 'selected="selected"' : '', '>', $txt['pm_rule_delete'], '</option>
					</select>
					<span id="labdiv', $k, '">
						<select name="labdef[', $k, ']" id="labdef', $k, '">
							<option value="">', $txt['pm_rule_sel_label'], '</option>';

		foreach ($context['labels'] as $label)
		{
			if ($label['id'] != -1)
			{
				echo '
							<option value="', ($label['id'] + 1), '" ', $action['t'] == 'lab' && $action['v'] == $label['id'] ? 'selected="selected"' : '', '>', $label['name'], '</option>';
			}
		}

		echo '
						</select>
					</span>';

		if ($isFirst)
		{
			$isFirst = false;
		}
		elseif ($action['t'] == '')
		{
			echo '
				</div>';
		}
	}

	echo '
				<span id="actionAddHere"></span><br />
				<a href="#" id="addonjs2" class="linkbutton hide" onclick="addActionOption(); return false;">', $txt['pm_rule_add_action'], '</a>
			</fieldset>
		</div>
		<h2 class="category_header">', $txt['pm_rule_description'], '</h2>
		<div class="information">
			<div id="ruletext">', $txt['pm_rule_js_disabled'], '</div>
		</div>
		<div class="submitbutton">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			<input type="submit" name="save" value="', $txt['pm_rule_save'], '" />
		</div>
	</form>';

	// Now setup all the bits!
	echo '
	<script>
		initUpdateRulesActions();';

	// If this isn't a new rule and we have JS enabled remove the JS compatibility stuff.
	if ($context['rid'])
	{
		echo '
			document.getElementById("removeonjs1").className = "hide";
			document.getElementById("removeonjs2").className = "hide";';
	}

	echo '
			document.getElementById("addonjs1").style.display = "";
			document.getElementById("addonjs2").style.display = "";';

	echo '
		</script>';
}

/**
 * Template for showing all the PM drafts of the user.
 */
function template_showPMDrafts()
{
	global $context, $scripturl, $txt;

	echo '
		<h2 class="category_header hdicon cat_img_talk">
			', $txt['drafts_show'], '
		</h2>';
	template_pagesection();

	// No drafts? Just show an informative message.
	if (empty($context['drafts']))
	{
		echo '
		<div class="information centertext">
			', $txt['draft_none'], '
		</div>';
	}
	else
	{
		// For every draft to be displayed, give it its own div, and show the important details of the draft.
		foreach ($context['drafts'] as $draft)
		{
			echo '
		<div class="content">
			<div class="counter">', $draft['counter'], '</div>
			<div class="topic_details">
				<h5>
					<strong>', $draft['subject'], '</strong>&nbsp;
				</h5>
				<span class="smalltext">&#171;&nbsp;<strong>', $txt['draft_saved_on'], ':</strong> ', sprintf($txt['draft_days_ago'], $draft['age']), (!empty($draft['remaining']) ? ', ' . sprintf($txt['draft_retain'], $draft['remaining']) : ''), '&#187;</span>
				<br />
				<span class="smalltext">&#171;&nbsp;<strong>', $txt['to'], ':</strong> ', implode(', ', $draft['recipients']['to']), '&nbsp;&#187;</span>
				<br />
				<span class="smalltext">&#171;&nbsp;<strong>', $txt['pm_bcc'], ':</strong> ', implode(', ', $draft['recipients']['bcc']), '&nbsp;&#187;</span>
			</div>
			<div class="messageContent">
				', $draft['body'], '
			</div>
			<nav>
				<ul class="quickbuttons">
					<li class="listlevel1">
						<a href="', $scripturl, '?action=pm;sa=showpmdrafts;id_draft=', $draft['id_draft'], ';', $context['session_var'], '=', $context['session_id'], '" class="linklevel1 reply_button">', $txt['draft_edit'], '</a>
					</li>
					<li class="listlevel1">
						<a href="', $scripturl, '?action=pm;sa=showpmdrafts;delete=', $draft['id_draft'], ';', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['draft_remove'], '?\');" class="linklevel1 remove_button">', $txt['draft_delete'], '</a>
					</li>
				</ul>
			</nav>	
		</div>';
		}
	}

	// Show page numbers.
	template_pagesection();
}