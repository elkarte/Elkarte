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
 * @version 1.0 Beta
 *
 */

/**
 * Main search page. Allows the user to search the forum according to criteria.
 */
function template_searchform()
{
	global $context, $settings, $txt, $scripturl, $modSettings;

	echo '
				<form action="', $scripturl, '?action=search2" method="post" accept-charset="UTF-8" name="searchform" id="searchform" class="standard_category">
					<h2 class="category_header', !empty($settings['use_buttons']) ? ' hdicon cat_img_search' : '', '">
						', $txt['set_parameters'], '
					</h2>';

	if (!empty($context['search_errors']))
		echo '
					<p class="errorbox">', implode('<br />', $context['search_errors']['messages']), '</p>';

	// Simple Search?
	if ($context['simple_search'] && (empty($context['minmax_preferences']['asearch']) || isset($_GET['basic'])))
	{
		echo '
					<fieldset id="simple_search" class="content">
						<div id="search_term_input">
							<strong>', $txt['search_for'], ':</strong>
							<input type="text" name="search"', !empty($context['search_params']['search']) ? ' value="' . $context['search_params']['search'] . '"' : '', ' maxlength="', $context['search_string_limit'], '" size="40" class="input_text" placeholder="' . $txt['search'] . '" required="required" autofocus="autofocus" />
							', $context['require_verification'] ? '' : '&nbsp;<input type="submit" name="s_search" value="' . $txt['search'] . '" class="button_submit" />', '
						</div>';

		if (empty($modSettings['search_simple_fulltext']))
			echo '
						<p class="smalltext">', $txt['search_example'], '</p>';

		if ($context['require_verification'])
		{
			template_control_verification($context['visual_verification_id'], '
						<div class="verification">
							<strong>' . $txt['search_visual_verification_label'] . ':</strong>
							<br />', '<br />');
			echo '
							<input id="submit" type="submit" name="s_search" value="' . $txt['search'] . '" class="button_submit"/>
						</div>';
		}

		// Show the button to enable advanced search
		echo '
						<a class="linkbutton" href="', $scripturl, '?action=search;advanced" onclick="elk_setThemeOption(\'minmax_preferences\', \'1\', null, elk_session_id, elk_session_var, \';minmax_key=asearch\');this.href += \';search=\' + escape(document.forms.searchform.search.value);">', $txt['search_advanced'], '</a>
						<input type="hidden" name="advanced" value="0" />
					</fieldset>';
	}
	// Advanced search!
	else
	{
		echo '
					<fieldset id="advanced_search" class="content">
						<dl class="settings" id="search_options">
							<dt class="righttext">
								<strong><label for="searchfor">', $txt['search_for'], ':</label></strong>
							</dt>
							<dd>
								<input type="text" name="search" id="searchfor" ', !empty($context['search_params']['search']) ? ' value="' . $context['search_params']['search'] . '"' : '', ' maxlength="', $context['search_string_limit'], '" size="40" class="input_text" placeholder="' . $txt['search'] . '" required="required" autofocus="autofocus" />';

		if (empty($modSettings['search_simple_fulltext']))
			echo '
								<em class="smalltext">', $txt['search_example'], '</em>';

		echo '
							</dd>
							<dt class="righttext"><label for="searchtype">
								', $txt['search_match'], ':</label>
							</dt>
							<dd>
								<select name="searchtype" id="searchtype">
									<option value="1"', empty($context['search_params']['searchtype']) ? ' selected="selected"' : '', '>', $txt['all_words'], '</option>
									<option value="2"', !empty($context['search_params']['searchtype']) ? ' selected="selected"' : '', '>', $txt['any_words'], '</option>
								</select>
							</dd>
							<dt class="righttext"><label for="userspec">
								', $txt['by_user'], ':</label>
							</dt>
							<dd>
								<input id="userspec" type="text" name="userspec" value="', empty($context['search_params']['userspec']) ? '*' : $context['search_params']['userspec'], '" size="40" class="input_text" />
							</dd>
							<dt class="righttext"><label for="sort">
								', $txt['search_order'], ':</label>
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
								<label for="show_complete">', $txt['search_show_complete_messages'], '
									<input type="checkbox" name="show_complete" id="show_complete" value="1"', !empty($context['search_params']['show_complete']) ? ' checked="checked"' : '', ' class="input_check" />
								</label><br />
								<label for="subject_only">', $txt['search_subject_only'], '
									<input type="checkbox" name="subject_only" id="subject_only" value="1"', !empty($context['search_params']['subject_only']) ? ' checked="checked"' : '', ' class="input_check" />
								</label>
							</dd>
							<dt class="righttext between">
								', $txt['search_post_age'], ':
							</dt>
							<dd><label for="minage">
								', $txt['search_between'], '</label><input type="text" name="minage" id="minage" value="', empty($context['search_params']['minage']) ? '0' : $context['search_params']['minage'], '" size="5" maxlength="4" class="input_text" />&nbsp;<label for="maxage">', $txt['search_and'], '&nbsp;</label><input type="text" name="maxage" id="maxage" value="', empty($context['search_params']['maxage']) ? '9999' : $context['search_params']['maxage'], '" size="5" maxlength="4" class="input_text" /> ', $txt['days_word'], '
							</dd>';

		// If we allow a simple form, show a link to get back to it
		if (!empty($modSettings['simpleSearch']))
			echo '
							<dt>
							</dt>
							<dd>
								<a href="', $scripturl, '?action=search;basic" onclick="elk_setThemeOption(\'minmax_preferences\', \'0\', null, elk_session_id, elk_session_var, \';minmax_key=asearch\');this.href += \';search=\' + escape(document.forms.searchform.search.value);" class="linkbutton">', $txt['search_simple'], '</a>
							</dd>';

		echo '
						</dl>
						<input type="hidden" name="advanced" value="1" />';

		// Require an image to be typed to save spamming?
		if ($context['require_verification'])
		{
			template_control_verification($context['visual_verification_id'], '
						<p>
							<strong>' . $txt['verification'] . ':</strong>
							', '
						</p>');
		}

		// If $context['search_params']['topic'] is set, that means we're searching just one topic.
		if (!empty($context['search_params']['topic']))
			echo '
						<p>', $txt['search_specific_topic'], ' &quot;', $context['search_topic']['link'], '&quot;.</p>
						<input type="hidden" name="topic" value="', $context['search_topic']['id'], '" />';

		echo '
					</fieldset>';

		if (empty($context['search_params']['topic']))
		{
			echo '
					<fieldset class="content">
						<h3 class="secondary_header">
							<span id="category_toggle">&nbsp;
								<span id="advanced_panel_toggle" class="', empty($context['minmax_preferences']['search']) ? 'collapse' : 'expand', '" style="display: none;" title="', $txt['hide'], '"></span>
							</span>
							<a href="#" id="advanced_panel_link">', $txt['choose_board'], '</a>
						</h3>
						<div id="advanced_panel_div"', $context['boards_check_all'] ? '' : ' style="display: none;"', '>
							<ul class="ignoreboards floatleft">';

			$i = 0;
			$limit = ceil($context['num_boards'] / 2);
			foreach ($context['categories'] as $category)
			{
				echo '
								<li class="category">
									<a href="javascript:void(0);" onclick="selectBoards([', implode(', ', $category['child_ids']), '], \'searchform\'); return false;">', $category['name'], '</a>
									<ul>';

				foreach ($category['boards'] as $board)
				{
					if ($i == $limit)
						echo '
									</ul>
								</li>
							</ul>
							<ul class="ignoreboards floatright">
								<li class="category">
									<ul>';

					echo '
										<li class="board" style="margin-', $context['right_to_left'] ? 'right' : 'left', ': ', $board['child_level'], 'em;">
											<label for="brd', $board['id'], '">
												<input type="checkbox" id="brd', $board['id'], '" name="brd[', $board['id'], ']" value="', $board['id'], '"', $board['selected'] ? ' checked="checked"' : '', ' class="input_check" /> ', $board['name'], '
											</label>
										</li>';

					$i ++;
				}

				echo '
									</ul>
								</li>';
			}

			echo '
							</ul>
						</div>';

			echo '
						<div class="submitbutton">
							<span class="floatleft">
								<input type="checkbox" name="all" id="check_all" value=""', $context['boards_check_all'] ? ' checked="checked"' : '', ' onclick="invertAll(this, this.form, \'brd\');" class="input_check" />
								<label for="check_all"><em> ', $txt['check_all'], '</em></label>
							</span>
							<input type="submit" name="b_search" value="', $txt['search'], '" class="button_submit" />
						</div>
					</fieldset>';
		}

		addInlineJavascript('
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

		// Some javascript for the advanced toggling
		var oAdvancedPanelToggle = new elk_Toggle({
			bToggleEnabled: true,
			bCurrentlyCollapsed: ' . (empty($context['minmax_preferences']['search']) ? 'false' : 'true') . ',
			aSwappableContainers: [
				\'advanced_panel_div\'
			],
			aSwapClasses: [
				{
					sId: \'advanced_panel_toggle\',
					classExpanded: \'collapse\',
					titleExpanded: ' . JavaScriptEscape($txt['hide']) . ',
					classCollapsed: \'expand\',
					titleCollapsed: ' . JavaScriptEscape($txt['show']) . '
				}
			],
			aSwapLinks: [
				{
					sId: \'advanced_panel_link\',
					msgExpanded: ' . JavaScriptEscape($txt['choose_board']) . ',
					msgCollapsed: ' . JavaScriptEscape($txt['choose_board']) . '
				}
			],
			oThemeOptions: {
				bUseThemeSettings: ' . ($context['user']['is_guest'] ? 'false' : 'true') . ',
				sOptionName: \'minmax_preferences\',
				sSessionId: elk_session_id,
				sSessionVar: elk_session_var,
				sAdditionalVars: \';minmax_key=search\'
			},
		});', true);
	}

	echo '
				</form>';
}

/**
 * Displays the search results page.
 */
function template_results()
{
	global $context, $settings, $options, $txt, $scripturl, $message;

	if (!empty($context['search_ignored']))
		echo '
			<div id="search_results">
				<h3 class="category_header">
					', $txt['generic_warning'], '
				</h3>
				<p class="warningbox">', $txt['search_warning_ignored_word' . (count($context['search_ignored']) == 1 ? '' : 's')], ': ', implode(', ', $context['search_ignored']), '</p>
			</div>';

	if (isset($context['did_you_mean']) || empty($context['topics']))
	{
		echo '
				<div id="search_results">
					<h2 class="category_header">', $txt['search_adjust_query'], '</h2>
					<div class="roundframe">';

		// Did they make any typos or mistakes, perhaps?
		if (isset($context['did_you_mean']))
			echo '
						<p>', $txt['search_did_you_mean'], ' <a href="', $scripturl, '?action=search2;params=', $context['did_you_mean_params'], '">', $context['did_you_mean'], '</a>.</p>';

		echo '
						<form action="', $scripturl, '?action=search2" method="post" accept-charset="UTF-8">
							<dl class="settings">
								<dt class="righttext">
									<strong>', $txt['search_for'], ':</strong>
								</dt>
								<dd>
									<input type="text" name="search"', !empty($context['search_params']['search']) ? ' value="' . $context['search_params']['search'] . '"' : '', ' maxlength="', $context['search_string_limit'], '" size="40" class="input_text" />
								</dd>
							</dl>
							<div class="submitbutton" >
								<input type="submit" name="edit_search" value="', $txt['search_adjust_submit'], '" class="button_submit" />
								<input type="hidden" name="searchtype" value="', !empty($context['search_params']['searchtype']) ? $context['search_params']['searchtype'] : 0, '" />
								<input type="hidden" name="userspec" value="', !empty($context['search_params']['userspec']) ? $context['search_params']['userspec'] : '', '" />
								<input type="hidden" name="show_complete" value="', !empty($context['search_params']['show_complete']) ? 1 : 0, '" />
								<input type="hidden" name="subject_only" value="', !empty($context['search_params']['subject_only']) ? 1 : 0, '" />
								<input type="hidden" name="minage" value="', !empty($context['search_params']['minage']) ? $context['search_params']['minage'] : '0', '" />
								<input type="hidden" name="maxage" value="', !empty($context['search_params']['maxage']) ? $context['search_params']['maxage'] : '9999', '" />
								<input type="hidden" name="sort" value="', !empty($context['search_params']['sort']) ? $context['search_params']['sort'] : 'relevance', '" />
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

	if ($context['compact'])
	{
		// Quick moderation set to checkboxes? Oh, how fun :/.
		if (!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1)
			echo '
				<form action="', $scripturl, '?action=quickmod" method="post" accept-charset="UTF-8" name="topicForm" id="topicForm">';

		echo '
					<h3 class="category_header hdicon cat_img_search">
						<span class="floatright">';

		if (!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1)
			echo '
							<input type="checkbox" onclick="invertAll(this, this.form, \'topics[]\');" class="input_check" />';

		echo '
						</span>
						', $txt['mlist_search_results'], ':&nbsp;', $context['search_params']['search'], '
					</h3>';

		// Was anything even found?
		if (!empty($context['topics']))
			template_pagesection();
		else
			echo '
					<div class="roundframe">', $txt['find_no_results'], '</div>';

		// While we have results to show ...
		$controller = $context['get_topics'][0];
		while ($topic = $controller->{$context['get_topics'][1]}())
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

			echo '
					<div class="search_results_posts">
						<div class="', $color_class, ' core_posts">
							<div class="content flow_auto">';

			foreach ($topic['matches'] as $message)
			{
				// @todo - Clean this up a bit. Too much crud.
				echo '
								<div class="topic_details floatleft" style="width: 94%; border-bottom: 0px">
									<div class="counter">', $message['counter'], '</div>
									<h5>', $topic['board']['link'], ' / <a href="', $scripturl, '?topic=', $topic['id'], '.msg', $message['id'], '#msg', $message['id'], '">', $message['subject_highlighted'], '</a></h5>
									<span class="smalltext">&#171;&nbsp;', $txt['by'], '&nbsp;<strong>', $message['member']['link'], '</strong>&nbsp;', $txt['on'], '&nbsp;<em>', $message['time'], '</em>&nbsp;&#187;</span>
								</div>';

				if (!empty($options['display_quick_mod']))
				{
					echo '
								<div class="floatright">';

					if ($options['display_quick_mod'] == 1)
					{
						echo '
									<input type="checkbox" name="topics[]" value="', $topic['id'], '" class="input_check" />';
					}
					else
					{
						if ($topic['quick_mod']['remove'])
							echo '
									<a href="', $scripturl, '?action=quickmod;actions[', $topic['id'], ']=remove;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['quickmod_confirm'], '\');"><img src="', $settings['images_url'], '/icons/quick_remove.png" style="width:16px" alt="', $txt['remove_topic'], '" title="', $txt['remove_topic'], '" /></a>';

						if ($topic['quick_mod']['lock'])
							echo '
									<a href="', $scripturl, '?action=quickmod;actions[', $topic['id'], ']=lock;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['quickmod_confirm'], '\');"><img src="', $settings['images_url'], '/icons/quick_lock.png" style="width:16px" alt="', $txt['set_lock'], '" title="', $txt['set_lock'], '" /></a>';

						if ($topic['quick_mod']['lock'] || $topic['quick_mod']['remove'])
							echo '
									<br />';

						if ($topic['quick_mod']['sticky'])
							echo '
									<a href="', $scripturl, '?action=quickmod;actions[', $topic['id'], ']=sticky;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['quickmod_confirm'], '\');"><img src="', $settings['images_url'], '/icons/quick_sticky.png" style="width:16px" alt="', $txt['set_sticky'], '" title="', $txt['set_sticky'], '" /></a>';

						if ($topic['quick_mod']['move'])
							echo '
									<a href="', $scripturl, '?action=movetopic;topic=', $topic['id'], '.0"><img src="', $settings['images_url'], '/icons/quick_move.png" style="width:16px" alt="', $txt['move_topic'], '" title="', $txt['move_topic'], '" /></a>';
					}

					echo '
								</div>';
				}

				if ($message['body_highlighted'] != '')
					echo '
								<div class="list_posts double_height">', $message['body_highlighted'], '</div>';
			}

			echo '
							</div>
						</div>
					</div>';
		}

		// If we have results show a page index
		if (!empty($context['topics']))
		{
			echo '
					<div class="flow_auto">
						<div class="floatleft">';

			template_pagesection();

			echo '
						</div>';

			// Quick moderation enabled, then show an action area
			if (!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1)
			{
				echo '
						<div class="additional_row floatright">
							<select class="qaction" name="qaction"', $context['can_move'] ? ' onchange="this.form.move_to.disabled = (this.options[this.selectedIndex].value != \'move\');"' : '', '>
								<option value="">&nbsp;</option>';

				foreach ($context['qmod_actions'] as $qmod_action)
					if ($context['can_' . $qmod_action])
						echo '
								<option value="' . $qmod_action . '">' . (isBrowser('ie8') ? '&#187;' : '&#10148;') . '&nbsp;', $txt['quick_mod_' . $qmod_action] . '</option>';

				echo '
							</select>';

				// Show a list of boards they can move the topic to.
				if ($context['can_move'])
					echo '
									<span id="quick_mod_jump_to">&nbsp;</span>';

				echo '
									<input type="hidden" name="redirect_url" value="', $scripturl . '?action=search2;params=' . $context['params'], '" />
									<input type="submit" value="', $txt['quick_mod_go'], '" onclick="return document.forms.topicForm.qaction.value != \'\' &amp;&amp; confirm(\'', $txt['quickmod_confirm'], '\');" class="button_submit submitgo" />

						</div>';
			}

			echo'
					</div>';
		}

		if (!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1)
			echo '
					<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" />
				</form>';
	}
	else
	{
		echo '
				<h3 class="category_header hdicon cat_img_search">
					', $txt['mlist_search_results'], ':&nbsp;', $context['search_params']['search'], '
				</h3>';

		template_pagesection();

		if (empty($context['topics']))
			echo '
				<div class="information">(', $txt['search_no_results'], ')</div>';

		// while we have some results...
		$controller = $context['get_topics'][0];
		while ($topic = $controller->{$context['get_topics'][1]}())
		{
			foreach ($topic['matches'] as $message)
			{
				echo '
				<div class="search_results_posts">
					<div class="', $message['alternate'] == 0 ? 'windowbg' : 'windowbg2', ' core_posts">
						<div class="content">
							<div class="counter">', $message['counter'], '</div>
							<div class="topic_details">
								<h5>', $topic['board']['link'], ' / <a href="', $scripturl, '?topic=', $topic['id'], '.', $message['start'], ';topicseen#msg', $message['id'], '">', $message['subject_highlighted'], '</a></h5>
								<span class="smalltext">&#171;&nbsp;', $txt['message'], ' ', $txt['by'], ' <strong>', $message['member']['link'], ' </strong>', $txt['on'], '&nbsp;<em>', $message['time'], '</em>&nbsp;&#187;</span>
							</div>
							<div class="list_posts">', $message['body_highlighted'], '</div>';

				if ($topic['can_reply'] || $topic['can_mark_notify'])
					echo '
							<ul class="quickbuttons">';

				// Can we request notification of topics?
				if ($topic['can_mark_notify'])
					echo '
								<li class="listlevel1"><a href="', $scripturl . '?action=notify;topic=' . $topic['id'] . '.' . $message['start'], '" class="linklevel1 notify_button">', $txt['notify'], '</a></li>';

				// If they *can* reply?
				if ($topic['can_reply'])
					echo '
								<li class="listlevel1"><a href="', $scripturl . '?action=post;topic=' . $topic['id'] . '.' . $message['start'], '" class="linklevel1 reply_button">', $txt['reply'], '</a></li>';

				// If they *can* quote?
				if ($topic['can_quote'])
					echo '
								<li class="listlevel1"><a href="', $scripturl . '?action=post;topic=' . $topic['id'] . '.' . $message['start'] . ';quote=' . $message['id'] . '" class="linklevel1 quote_button">', $txt['quote'], '</a></li>';

				if ($topic['can_reply'] || $topic['can_mark_notify'])
					echo '
							</ul>';
				echo '
						</div>
					</div>
				</div>';
			}
		}

		template_pagesection();
	}

	// Show a jump to box for easy navigation.
	echo '
				<div class="floatright" id="search_jump_to">&nbsp;</div>';

	if (!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1 && !empty($context['topics']) && $context['can_move'])
		addInlineJavascript('
		aJumpTo[aJumpTo.length] = new JumpTo({
			sContainerId: "quick_mod_jump_to",
			sClassName: "qaction",
			sJumpToTemplate: "%dropdown_list%",
			sCurBoardName: "' . $context['jump_to']['board_name'] . '",
			sBoardChildLevelIndicator: "&#8195;",
			sBoardPrefix: "' . (isBrowser('ie8') ? '&#187;' : '&#10148;') . '&nbsp;",
			sCatClass: "jump_to_header",
			sCatPrefix: "",
			bNoRedirect: true,
			bDisabled: true,
			sCustomName: "move_to"
		});', true);

	addInlineJavascript('
		aJumpTo[aJumpTo.length] = new JumpTo({
			sContainerId: "search_jump_to",
			sJumpToTemplate: "<label class=\"smalltext\" for=\"%select_id%\">' . $context['jump_to']['label'] . ':<" + "/label> %dropdown_list%",
			iCurBoardId: 0,
			iCurBoardChildLevel: 0,
			sCurBoardName: "' . $context['jump_to']['board_name'] . '",
			sBoardChildLevelIndicator: "&#8195;",
			sBoardPrefix: "' . (isBrowser('ie8') ? '&#187;' : '&#10148;') . '&nbsp;",
			sCatClass: "jump_to_header",
			sCatPrefix: "",
			sGoButtonLabel: "' . $txt['quick_mod_go'] . '"
		});', true);
}