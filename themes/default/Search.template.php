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
 * @version 1.1
 *
 */

/**
 * Lotsa boards we (may) have here!
 */
function template_Search_init()
{
	theme()->getTemplates()->load('GenericBoards');
}

/**
 * Main search page. Allows the user to search the forum according to criteria.
 */
function template_searchform()
{
	global $context, $settings, $txt, $scripturl, $modSettings;

	echo '
				<form id="searchform" action="', $scripturl, '?action=search;sa=results" method="post" accept-charset="UTF-8" name="searchform">
					<h2 class="category_header', !empty($settings['use_buttons']) ? ' hdicon cat_img_search' : '', '">
						', $txt['set_parameters'], '
					</h2>';

	// Any search errors to inform the user about
	if (!empty($context['search_errors']))
		echo '
					<p class="errorbox">', implode('<br />', $context['search_errors']['messages']), '</p>';

	// Start off showing our basic search form
	echo '
					<fieldset id="simple_search" class="content">
						<div id="search_term_input">
							<label for="search">
								<strong>', $txt['search_for'], '</strong>
							</label>:
							<input type="search" id="search" class="input_text" name="search" value="', $context['search_params']['search'], '" maxlength="', $context['search_string_limit'], '" size="40" placeholder="' . $txt['search'] . '" required="required" autofocus="autofocus" />', '
							<input id="submit" type="submit" name="s_search" value="' . $txt['search'] . '" />
						</div>';

	if (empty($modSettings['search_simple_fulltext']))
		echo '
						<p class="smalltext">', $txt['search_example'], '</p>';

	// Does the search require a visual verification screen to annoy them?
	if (!empty($context['require_verification']))
	{
		template_verification_controls($context['visual_verification_id'], '
						<div class="verification">
							<strong>' . $txt['search_visual_verification_label'] . ':</strong>
							<br />', '
						</div>');
	}

	// All of the advanced options, this div is collapsed by the JS when available
	echo '
						<div id="advanced_search">
							<dl id="search_options">
								<dt class="righttext"><label for="searchtype">
									', $txt['search_match'], ':</label>
								</dt>
								<dd>
									<select name="searchtype" id="searchtype">
										<option value="1"', empty($context['search_params']['searchtype']) ? ' selected="selected"' : '', '>', $txt['all_words'], '</option>
										<option value="2"', !empty($context['search_params']['searchtype']) ? ' selected="selected"' : '', '>', $txt['any_words'], '</option>
									</select>
								</dd>
								<dt class="righttext">
									<label for="userspec">', $txt['by_user'], ':</label>
								</dt>
								<dd>
									<input id="userspec" type="text" name="userspec" value="', $context['search_params']['userspec'], '" size="40" class="input_text" />
								</dd>
								<dt class="righttext">
									<label for="sort">', $txt['search_order'], ':</label>
								</dt>
								<dd>
									<select id="sort" name="sort">
										<option value="relevance|desc">', $txt['search_orderby_relevant_first'], '</option>
										<option value="num_replies|desc">', $txt['search_orderby_large_first'], '</option>
										<option value="num_replies|asc">', $txt['search_orderby_small_first'], '</option>
										<option value="id_msg|desc">', $txt['search_orderby_recent_first'], '</option>
										<option value="id_msg|asc">', $txt['search_orderby_old_first'], '</option>
									</select>
								</dd>
								<dt class="righttext options">
									', $txt['search_options'], ':
								</dt>
								<dd class="options">
									<input type="checkbox" name="show_complete" id="show_complete" value="1"', !empty($context['search_params']['show_complete']) ? ' checked="checked"' : '', ' />
									<label for="show_complete">', $txt['search_show_complete_messages'], '</label><br />
									<input type="checkbox" name="subject_only" id="subject_only" value="1"', !empty($context['search_params']['subject_only']) ? ' checked="checked"' : '', ' />
									<label for="subject_only">', $txt['search_subject_only'], '</label>
								</dd>
								<dt class="righttext between">
									', $txt['search_post_age'], ':
								</dt>
								<dd>
									<label for="minage">', $txt['search_between'], '</label>&nbsp;
									<input type="text" name="minage" id="minage" value="', $context['search_params']['minage'], '" size="5" maxlength="4" class="input_text" />&nbsp;
									<label for="maxage">', $txt['search_and'], '&nbsp;</label>
									<input type="text" name="maxage" id="maxage" value="', $context['search_params']['maxage'], '" size="5" maxlength="4" class="input_text" /> ', $txt['days_word'], '
								</dd>
							</dl>
						</div>
						<a id="upshrink_link" href="', $scripturl, '?action=search;advanced" class="linkbutton hide">', $txt['search_simple'], '</a>';

	// Set the initial search style for the form
	echo '
						<input id="advanced" type="hidden" name="advanced" value="1" />';

	// If $context['search_params']['topic'] is set, that means we're searching just one topic.
	if (!empty($context['search_params']['topic']))
		echo '
						<p>', $txt['search_specific_topic'], ' &quot;', $context['search_topic']['link'], '&quot;.</p>
						<input type="hidden" name="topic" value="', $context['search_topic']['id'], '" />';

	echo '
					</fieldset>';

	// This starts our selection area to allow searching by specific boards
	if (empty($context['search_params']['topic']))
	{
		echo '
					<fieldset id="pick_boards" class="content">';

		template_pick_boards('searchform');

		echo '
					</fieldset>';
	}

	echo '
				</form>';

	// And now all the JS to make this work
	theme()-addInlineJavascript('
		createEventListener(window);
		window.addEventListener("load", initSearch, false);

		var oAddMemberSuggest = new smc_AutoSuggest({
			sSelf: \'oAddMemberSuggest\',
			sSessionId: elk_session_id,
			sSessionVar: elk_session_var,
			sControlId: \'userspec\',
			sSearchType: \'member\',
			bItemList: false
		});

		// Set the search style
		document.getElementById(\'advanced\').value = "' . (empty($context['minmax_preferences']['asearch']) ? '1' : '0') . '";

		// And allow for the collapsing of the advanced search options
		var oSearchToggle = new elk_Toggle({
			bToggleEnabled: true,
			bCurrentlyCollapsed: ' . (empty($context['minmax_preferences']['asearch']) ? 'false' : 'true') . ',
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
				sAdditionalVars: \';minmax_key=asearch\'
			},
		});', true);
}

/**
 * Displays the search results page.
 */
function template_results()
{
	global $context, $settings, $options, $txt, $scripturl, $message;

	// Let them know if we ignored a word in the search
	if (!empty($context['search_ignored']))
		echo '
		<div id="search_results">
			<h2 class="category_header">
				', $txt['generic_warning'], '
			</h2>
			<p class="warningbox">', $txt['search_warning_ignored_word' . (count($context['search_ignored']) == 1 ? '' : 's')], ': ', implode(', ', $context['search_ignored']), '</p>
		</div>';

	// Or perhaps they made a spelling error, lets give them a hint
	if (!empty($context['did_you_mean']) || empty($context['topics']))
	{
		echo '
			<div id="search_results">
				<h2 class="category_header">', $txt['search_adjust_query'], '</h2>
				<div class="well">';

		// Did they make any typos or mistakes, perhaps?
		if (isset($context['did_you_mean']))
			echo '
					<p>', $txt['search_did_you_mean'], ' <a href="', $scripturl, '?action=search;sa=results;params=', $context['did_you_mean_params'], '">', $context['did_you_mean'], '</a>.</p>';

		echo '
					<form action="', $scripturl, '?action=search;sa=results" method="post" accept-charset="UTF-8">
						<dl class="settings">
							<dt class="righttext">
								<label for="search"><strong>', $txt['search_for'], ':</strong></label>
							</dt>
							<dd>
								<input type="text" id="search" name="search" value="', $context['search_params']['search'], '" maxlength="', $context['search_string_limit'], '" size="40" class="input_text" />
							</dd>
						</dl>
						<div class="submitbutton" >
							<input type="submit" name="edit_search" value="', $txt['search_adjust_submit'], '" />
							<input type="hidden" name="searchtype" value="', $context['search_params']['searchtype'], '" />
							<input type="hidden" name="userspec" value="', $context['search_params']['userspec'], '" />
							<input type="hidden" name="show_complete" value="', $context['search_params']['show_complete'], '" />
							<input type="hidden" name="subject_only" value="', $context['search_params']['subject_only'], '" />
							<input type="hidden" name="minage" value="', $context['search_params']['minage'], '" />
							<input type="hidden" name="maxage" value="', $context['search_params']['maxage'], '" />
							<input type="hidden" name="sort" value="', $context['search_params']['sort'], '" />
						</div>';

		if (!empty($context['search_params']['brd']))
			foreach ($context['search_params']['brd'] as $board_id)
				echo '
						<input type="hidden" name="brd[', $board_id, ']" value="', $board_id, '" />';

		echo '
					</form>
				</div>
			</div>
			<br />';
	}

	// Quick moderation set to checkboxes? Oh, how fun :/.
	if (!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1)
		echo '
			<form id="topicForm" class="search_results_posts', $context['compact'] ? ' compact_view' : '', '" action="', $scripturl, '?action=quickmod" method="post" accept-charset="UTF-8" name="topicForm">';

	echo '
				<h2 class="category_header hdicon cat_img_search">
					<span class="floatright">';

	if (!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1)
		echo '
						<input type="checkbox" onclick="invertAll(this, this.form, \'topics[]\');" />';

	echo '
					</span>
					', $txt['mlist_search_results'], ':&nbsp;', $context['search_params']['search'], '
				</h2>';

	// Was anything even found?
	if (!empty($context['topics']))
		template_pagesection();
	else
		echo '
				<div class="well">', $txt['find_no_results'], '</div>';

	if ($context['compact'])
		echo '
				<ul class="topic_listing compact_view search_results_posts">';
	else
		echo '
				<ul class="forumposts topic_listing search_results_posts">';

	// While we have results to show ...
	$controller = $context['get_topics'][0];
	while ($topic = $controller->{$context['get_topics'][1]}())
	{
		if ($context['compact'])
		{
			// We start with locked and sticky topics.
			if ($topic['is_sticky'] && $topic['is_locked'])
				$color_class = 'locked_row sticky_row';
			// Sticky topics should get a different color, too.
			elseif ($topic['is_sticky'])
				$color_class = 'sticky_row';
			// Locked topics get special treatment as well.
			elseif ($topic['is_locked'])
				$color_class = 'locked_row';
			// Last, but not least: regular topics.
			else
				$color_class = 'basic_row';
		}
		else
			$color_class = 'basic_row';

		foreach ($topic['matches'] as $message)
		{
			echo '
					<li class="', $color_class, '">
						<div class="topic_details">
							<div class="counter">', $message['counter'], '</div>
							<h5>', $topic['board']['link'], ' / <a href="', $scripturl, '?topic=', $topic['id'], '.msg', $message['id'], '#msg', $message['id'], '">', $message['subject_highlighted'], '</a></h5>
							<span class="smalltext">&#171;&nbsp;', $txt['by'], '&nbsp;<strong>', $message['member']['link'], '</strong> ', $txt['on'], '&nbsp;<em>', $message['time'], '</em>&nbsp;&#187;</span>';
			echo '
						</div>';

			if (!$context['compact'] || $message['body_highlighted'] != '')
				echo '
						<div class="topic_body">', $message['body_highlighted'], '</div>';

			if (!empty($topic['buttons']))
				template_quickbutton_strip($topic['buttons'], $topic['tests']);

			if (!empty($options['display_quick_mod']))
			{
				echo '
						<p class="topic_moderation">';

				if ($options['display_quick_mod'] == 1)
				{
					echo '
							<input type="checkbox" name="topics[]" value="', $topic['id'], '" />';
				}
				else
				{
					if ($topic['quick_mod']['remove'])
						echo '
							<a href="', $scripturl, '?action=quickmod;actions%5B', $topic['id'], '%5D=remove;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['quickmod_confirm'], '\');"><img src="', $settings['images_url'], '/icons/quick_remove.png" style="width: 16px;" alt="', $txt['remove_topic'], '" title="', $txt['remove_topic'], '" /></a>';

					if ($topic['quick_mod']['lock'])
						echo '
							<a href="', $scripturl, '?action=quickmod;actions%5B', $topic['id'], '%5D=lock;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['quickmod_confirm'], '\');"><img src="', $settings['images_url'], '/icons/quick_lock.png" style="width: 16px;" alt="', $txt[$topic['is_locked'] ? 'set_unlock' : 'set_lock'], '" title="', $txt[$topic['is_locked'] ? 'set_unlock' : 'set_lock'], '" /></a>';

					if ($topic['quick_mod']['lock'] || $topic['quick_mod']['remove'])
						echo '
							<br />';

					if ($topic['quick_mod']['sticky'])
						echo '
							<a href="', $scripturl, '?action=quickmod;actions%5B', $topic['id'], '%5D=sticky;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['quickmod_confirm'], '\');"><img src="', $settings['images_url'], '/icons/quick_sticky.png" style="width: 16px;" alt="', $txt[$topic['is_sticky'] ? 'set_nonsticky' : 'set_sticky'], '" title="', $txt[$topic['is_sticky'] ? 'set_nonsticky' : 'set_sticky'], '" /></a>';

					if ($topic['quick_mod']['move'])
						echo '
							<a href="', $scripturl, '?action=movetopic;topic=', $topic['id'], '.0"><img src="', $settings['images_url'], '/icons/quick_move.png" style="width: 16px;" alt="', $txt['move_topic'], '" title="', $txt['move_topic'], '" /></a>';
				}

				echo '
						</p>';
			}

			echo '
					</li>';
		}
	}

	echo '
				</ul>';

	// If we have results show a page index
	if (!empty($context['topics']))
		template_pagesection();

	// Quick moderation enabled, then show an action area
	if (!empty($context['topics']) && !empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1)
	{
		echo '
				<div class="pagesection floatright">
					<select class="qaction" name="qaction"', $context['can_move'] ? ' onchange="this.form.move_to.disabled = (this.options[this.selectedIndex].value != \'move\');"' : '', '>
						<option value="">&nbsp;</option>';

		foreach ($context['qmod_actions'] as $qmod_action)
			if ($context['can_' . $qmod_action])
				echo '
						<option value="' . $qmod_action . '">&#10148;&nbsp;', $txt['quick_mod_' . $qmod_action] . '</option>';

		echo '
					</select>';

		// Show a list of boards they can move the topic to.
		if ($context['can_move'])
			echo '
					<span id="quick_mod_jump_to">&nbsp;</span>';

		echo '
					<input type="hidden" name="redirect_url" value="', $scripturl . '?action=search;sa=results;params=' . $context['params'], '" />
					<input type="submit" value="', $txt['quick_mod_go'], '" onclick="return document.forms.topicForm.qaction.value != \'\' &amp;&amp; confirm(\'', $txt['quickmod_confirm'], '\');" />
					<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" />
				</div>
			</form>';
	}

	// Show a jump to box for easy navigation.
	echo '
			<div class="floatright" id="search_jump_to">&nbsp;</div>';

	if (!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1 && !empty($context['topics']) && $context['can_move'])
		theme()-addInlineJavascript('
		aJumpTo[aJumpTo.length] = new JumpTo({
			sContainerId: "quick_mod_jump_to",
			sClassName: "qaction",
			sJumpToTemplate: "%dropdown_list%",
			sCurBoardName: "' . $context['jump_to']['board_name'] . '",
			sBoardChildLevelIndicator: "&#8195;",
			sBoardPrefix: "&#10148;&nbsp;",
			sCatClass: "jump_to_header",
			sCatPrefix: "",
			bNoRedirect: true,
			bDisabled: true,
			sCustomName: "move_to"
		});', true);

	theme()-addInlineJavascript('
		aJumpTo[aJumpTo.length] = new JumpTo({
			sContainerId: "search_jump_to",
			sJumpToTemplate: "<label class=\"smalltext\" for=\"%select_id%\">' . $context['jump_to']['label'] . ':<" + "/label> %dropdown_list%",
			iCurBoardId: 0,
			iCurBoardChildLevel: 0,
			sCurBoardName: "' . $context['jump_to']['board_name'] . '",
			sBoardChildLevelIndicator: "&#8195;",
			sBoardPrefix: "&#10148;&nbsp;",
			sCatClass: "jump_to_header",
			sCatPrefix: "",
			sGoButtonLabel: "' . $txt['quick_mod_go'] . '"
		});', true);
}