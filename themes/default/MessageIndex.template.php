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
 * @version 1.0.7
 *
 */

/**
 * Loads the template used to display boards
 */
function template_MessageIndex_init()
{
	loadTemplate('GenericBoards');
}

/**
 * Used to display sub-boards.
 */
function template_display_child_boards_above()
{
	global $context, $txt;

	echo '
	<div id="board_', $context['current_board'], '_childboards" class="forum_category">
		<h2 class="category_header">
			', $txt['parent_boards'], '
		</h2>';

	template_list_boards($context['boards'], 'board_' . $context['current_board'] . '_children');

	echo '
	</div>';
}

/**
 * Header bar and extra details above topic listing
 *  - board description
 *  - who is viewing
 *  - sort container
 */
function template_topic_listing_above()
{
	global $context, $settings, $txt, $options;

	if ($context['no_topic_listing'])
		return;

	template_pagesection('normal_buttons', 'right');

	echo '
		<div id="description_board">
			<h2 class="category_header">', $context['name'], '</h2>
			<div class="generalinfo">';

	// Show the board description
	if (!empty($context['description']))
		echo '
				<div id="boarddescription">
					', $context['description'], '
				</div>';

	if (!empty($context['moderators']))
		echo '
				<div class="moderators">', count($context['moderators']) === 1 ? $txt['moderator'] : $txt['moderators'], ': ', implode(', ', $context['link_moderators']), '.</div>';

	echo '
				<div id="whoisviewing">';

	// If we are showing who is viewing this topic, build it out
	if (!empty($settings['display_who_viewing']))
	{
		if ($settings['display_who_viewing'] == 1)
			echo count($context['view_members']), ' ', count($context['view_members']) === 1 ? $txt['who_member'] : $txt['members'];
		else
			echo empty($context['view_members_list']) ? '0 ' . $txt['members'] : implode(', ', $context['view_members_list']) . (empty($context['view_num_hidden']) || $context['can_moderate_forum'] ? '' : ' (+ ' . $context['view_num_hidden'] . ' ' . $txt['hidden'] . ')');

		echo $txt['who_and'], $context['view_num_guests'], ' ', $context['view_num_guests'] == 1 ? $txt['guest'] : $txt['guests'], $txt['who_viewing_board'];
	}

	// Sort topics mumbo-jumbo
	echo '
					<ul id="sort_by" class="topic_sorting">';

	if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] == 1)
		echo '
						<li class="listlevel1 quickmod_select_all">
							<input type="checkbox" onclick="invertAll(this, document.getElementById(\'quickModForm\'), \'topics[]\');" class="input_check" />
						</li>';

	$current_header = $context['topics_headers'][$context['sort_by']];
	echo '
						<li class="listlevel1 topic_sorting_row">
							<a class="sort topicicon img_sort', $context['sort_direction'], '" href="', $current_header['url'], '" title="', $context['sort_title'], '"></a>
						</li>';

	echo '
						<li class="listlevel1 topic_sorting_row">', $txt['sort_by'], ': <a href="', $current_header['url'], '">', $txt[$context['sort_by']], '</a>
							<ul class="menulevel2" id="sortby">';

	foreach ($context['topics_headers'] as $key => $value)
		echo '
								<li class="listlevel2 sort_by_item" id="sort_by_item_', $key, '"><a href="', $value['url'], '" class="linklevel2">', $txt[$key], ' ', $value['sort_dir_img'], '</a></li>';

	echo '
							</ul>
						</li>
					</ul>
				</div>
			</div>
		</div>';
}

/**
 * The actual topic listing.
 */
function template_topic_listing()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	if (!$context['no_topic_listing'])
	{
		// We know how to sprite these
		$message_icon_sprite = array('clip' => '', 'lamp' => '', 'poll' => '', 'question' => '', 'xx' => '', 'moved' => '', 'exclamation' => '', 'thumbup' => '', 'thumbdown' => '');

		// If Quick Moderation is enabled start the form.
		if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] > 0 && !empty($context['topics']))
			echo '
	<form action="', $scripturl, '?action=quickmod;board=', $context['current_board'], '.', $context['start'], '" method="post" accept-charset="UTF-8" class="clear" name="quickModForm" id="quickModForm">';

		// If this person can approve items and we have some awaiting approval tell them.
		if (!empty($context['unapproved_posts_message']))
			echo '
		<div class="warningbox">', $context['unapproved_posts_message'], '</div>';

		echo '
		<ul class="topic_listing" id="messageindex">';

		// No topics.... just say, "sorry bub".
		if (empty($context['topics']))
			echo '
			<li class="basic_row">
				<div class="topic_info">
					<div class="topic_name">
						<h4>
							<strong>', $txt['topic_alert_none'], '</strong>
						</h4>
					</div>
				</div>
			</li>';

		foreach ($context['topics'] as $topic)
		{
			// Is this topic pending approval, or does it have any posts pending approval?
			if ($context['can_approve_posts'] && $topic['unapproved_posts'])
				$color_class = !$topic['approved'] ? 'approvetopic_row' : 'approve_row';
			// We start with locked and sticky topics.
			elseif ($topic['is_sticky'] && $topic['is_locked'])
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
			<li class="', $color_class, '">
				<div class="topic_info">
					<p class="topic_icons', isset($message_icon_sprite[$topic['first_post']['icon']]) ? ' topicicon img_' . $topic['first_post']['icon'] : '', '">';

			if (!isset($message_icon_sprite[$topic['first_post']['icon']]))
				echo '
						<img src="', $topic['first_post']['icon_url'], '" alt="" />';

			echo '
						', $topic['is_posted_in'] ? '<span class="fred topicicon img_profile"></span>' : '', '
					</p>
					<div class="topic_name" ', (!empty($topic['quick_mod']['modify']) ? 'id="topic_' . $topic['first_post']['id'] . '"  ondblclick="oQuickModifyTopic.modify_topic(\'' . $topic['id'] . '\', \'' . $topic['first_post']['id'] . '\');"' : ''), '>
						<h4>';

			// Is this topic new? (assuming they are logged in!)
			if ($topic['new'] && $context['user']['is_logged'])
				echo '
							<a class="new_posts" href="', $topic['new_href'], '" id="newicon' . $topic['first_post']['id'] . '">' . $txt['new'] . '</a>';

			// Is this an unapproved topic and they can approve it?
			if ($context['can_approve_posts'] && !$topic['approved'])
				echo '<span class="require_approval">' . $txt['awaiting_approval'] . '</span>';

			echo '
							', $topic['is_sticky'] ? '<strong>' : '', '<span class="preview" title="', $topic['default_preview'], '"><span id="msg_' . $topic['first_post']['id'] . '">', $topic['first_post']['link'], '</span></span>', $topic['is_sticky'] ? '</strong>' : '', '
						</h4>
					</div>
					<div class="topic_starter">
						', sprintf($txt['topic_started_by'], $topic['first_post']['member']['link']), !empty($topic['pages']) ? '
						<ul class="small_pagelinks" id="pages' . $topic['first_post']['id'] . '" role="menubar">' . $topic['pages'] . '</ul>' : '', '
					</div>
				</div>
				<div class="topic_latest">
					<p class="topic_stats">
					', $topic['replies'], ' ', $txt['replies'], '<br />
					', $topic['views'], ' ', $txt['views'];

			// Show likes?
			if (!empty($modSettings['likes_enabled']))
				echo '<br />
					', $topic['likes'], ' ', $txt['likes'];

			echo '
					</p>
					<p class="topic_lastpost">';

			if (!empty($settings['avatars_on_indexes']))
				echo '
						<span class="board_avatar"><a href="', $topic['last_post']['member']['href'], '"><img class="avatar" src="', $topic['last_post']['member']['avatar']['href'], '" alt="" /></a></span>';

			echo '
						<a class="topicicon img_last_post', '" href="', $topic['last_post']['href'], '" title="', $txt['last_post'], '"></a>
						', $topic['last_post']['html_time'], '<br />
						', $txt['by'], ' ', $topic['last_post']['member']['link'], '
					</p>
				</div>';

			// Show the quick moderation options?
			if (!empty($context['can_quick_mod']))
			{
				echo '
				<p class="topic_moderation', $options['display_quick_mod'] == 1 ? '' : '_alt', '" >';

				if ($options['display_quick_mod'] == 1)
					echo '
						<input type="checkbox" name="topics[]" value="', $topic['id'], '" class="input_check" />';
				else
				{
					// Check permissions on each and show only the ones they are allowed to use.
					if ($topic['quick_mod']['remove'])
						echo '<a class="topicicon img_remove" href="', $scripturl, '?action=quickmod;board=', $context['current_board'], '.', $context['start'], ';actions%5B', $topic['id'], '%5D=remove;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['quickmod_confirm'], '\');" title="', $txt['remove_topic'], '"></a>';

					if ($topic['quick_mod']['lock'])
						echo '<a class="topicicon img_locked" href="', $scripturl, '?action=quickmod;board=', $context['current_board'], '.', $context['start'], ';actions%5B', $topic['id'], '%5D=lock;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['quickmod_confirm'], '\');" title="', $txt[$topic['is_locked'] ? 'set_unlock' : 'set_lock'], '"></a>';

					if ($topic['quick_mod']['lock'] || $topic['quick_mod']['remove'])
						echo '<br />';

					if ($topic['quick_mod']['sticky'])
						echo '<a class="topicicon img_sticky" href="', $scripturl, '?action=quickmod;board=', $context['current_board'], '.', $context['start'], ';actions%5B', $topic['id'], '%5D=sticky;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['quickmod_confirm'], '\');" title="', $txt[$topic['is_sticky'] ? 'set_nonsticky' : 'set_sticky'], '"></a>';

					if ($topic['quick_mod']['move'])
						echo '<a class="topicicon img_move" href="', $scripturl, '?action=movetopic;current_board=', $context['current_board'], ';board=', $context['current_board'], '.', $context['start'], ';topic=', $topic['id'], '.0" title="', $txt['move_topic'], '"></a>';
				}

				echo '
				</p>';
			}

			echo '
			</li>';
		}

		echo '
		</ul>';

		if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] == 1 && !empty($context['topics']))
		{
			echo '
			<div class="qaction_row">
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
				<input type="submit" value="', $txt['quick_mod_go'], '" onclick="return document.forms.quickModForm.qaction.value != \'\' &amp;&amp; confirm(\'', $txt['quickmod_confirm'], '\');" class="button_submit" />
			</div>';
		}

		// Finish off the form - again.
		if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] > 0 && !empty($context['topics']))
			echo '
	<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" />
	</form>';
	}
}

/**
 * The lower icons and jump to.
 */
function template_topic_listing_below()
{
	global $context, $txt, $options;

	if ($context['no_topic_listing'])
		return;

	template_pagesection('normal_buttons', 'right');

	// Show breadcrumbs at the bottom too.
	theme_linktree();

	echo '
	<div id="topic_icons" class="description">
		<div class="qaction_row" id="message_index_jump_to">&nbsp;</div>';

	if (!$context['no_topic_listing'])
		template_basicicons_legend();

	echo '
			<script><!-- // --><![CDATA[';

	if (!empty($context['using_relative_time']))
		echo '
				$(\'.topic_latest\').addClass(\'relative\');';

	if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] == 1 && !empty($context['topics']) && $context['can_move'])
		echo '
				aJumpTo[aJumpTo.length] = new JumpTo({
					sContainerId: "quick_mod_jump_to",
					sClassName: "qaction",
					sJumpToTemplate: "%dropdown_list%",
					iCurBoardId: ', $context['current_board'], ',
					iCurBoardChildLevel: ', $context['jump_to']['child_level'], ',
					sCurBoardName: "', $context['jump_to']['board_name'], '",
					sBoardChildLevelIndicator: "&#8195;",
					sBoardPrefix: "', isBrowser('ie8') ? '&#187; ' : '&#10148; ', '",
					sCatClass: "jump_to_header",
					sCatPrefix: "",
					bNoRedirect: true,
					bDisabled: true,
					sCustomName: "move_to"
				});';

	echo '
				aJumpTo[aJumpTo.length] = new JumpTo({
					sContainerId: "message_index_jump_to",
					sJumpToTemplate: "<label class=\"smalltext\" for=\"%select_id%\">', $context['jump_to']['label'], ':<" + "/label> %dropdown_list%",
					iCurBoardId: ', $context['current_board'], ',
					iCurBoardChildLevel: ', $context['jump_to']['child_level'], ',
					sCurBoardName: "', $context['jump_to']['board_name'], '",
					sBoardChildLevelIndicator: "&#8195;",
					sBoardPrefix: "', isBrowser('ie8') ? '&#187; ' : '&#10148; ', '",
					sCatPrefix: "",
					sCatClass: "jump_to_header",
					sGoButtonLabel: "', $txt['quick_mod_go'], '"
				});
			// ]]></script>
	</div>';

	// Javascript for inline editing.
	echo '
	<script><!-- // --><![CDATA[
		var oQuickModifyTopic = new QuickModifyTopic({
			aHidePrefixes: Array("lockicon", "stickyicon", "pages", "newicon"),
			bMouseOnDiv: false,
		});
	// ]]></script>';
}