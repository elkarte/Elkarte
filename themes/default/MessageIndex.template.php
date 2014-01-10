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
 * Loads the template used to display boards
 */
function template_MessageIndex_init()
{
	loadTemplate('GenericBoards');
}

/**
 * Used to display child boards.
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
 * Header bar and extra details above topic listing.
 */
function template_topic_listing_above()
{
	global $context, $settings, $txt, $options;

	if ($context['no_topic_listing'])
		return;

	template_pagesection('normal_buttons', 'right');

	if (!empty($context['description']) || !empty($context['moderators']))
	{
		echo '
		<div id="description_board">
			<h2 class="category_header">', $context['name'];

		if (!empty($context['moderators']))
			echo '
				<span class="moderators">(', count($context['moderators']) === 1 ? $txt['moderator'] : $txt['moderators'], ': ', implode(', ', $context['link_moderators']), '.)</span>';

		echo '
			</h2>';

		echo '
			<div class="generalinfo">';

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
						<a href="', $current_header['url'], '">', $current_header['sort_dir_img'], '</a>
					</li>';

		echo '
					<li class="listlevel1 topic_sorting_row">', $txt['sort_by'], ': <a href="', $current_header['url'], '">', $txt[$context['sort_by']], '</a>
						<ul class="menulevel2" id="sortby">';
		foreach ($context['topics_headers'] as $key => $value)
			echo '
							<li class="listlevel2 sort_by_item" id="sort_by_item_', $key, '"><a href="', $value['url'], '" class="linklevel2">', $txt[$key], ' ', $value['sort_dir_img'], '</a></li>';
		echo '
						</ul>';

		echo '
					</li>
				</ul>';

		if (!empty($context['description']))
			echo '
				<div id="boarddescription">
					', $context['description'], '
				</div>';

		// @todo - Thought the who is stuff was better here. Presentation still WIP.
		if (!empty($settings['display_who_viewing']))
		{
			echo '
				<div id="whoisviewing">';

			if ($settings['display_who_viewing'] == 1)
				echo count($context['view_members']), ' ', count($context['view_members']) === 1 ? $txt['who_member'] : $txt['members'];
			else
				echo empty($context['view_members_list']) ? '0 ' . $txt['members'] : implode(', ', $context['view_members_list']) . (empty($context['view_num_hidden']) || $context['can_moderate_forum'] ? '' : ' (+ ' . $context['view_num_hidden'] . ' ' . $txt['hidden'] . ')');

			echo $txt['who_and'], $context['view_num_guests'], ' ', $context['view_num_guests'] == 1 ? $txt['guest'] : $txt['guests'], $txt['who_viewing_board'];

			echo '
				</div>';
		}

		echo '
			</div>';

		echo '
		</div>';
	}
}

/**
 * The actual topic listing.
 */
function template_topic_listing()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	if (!$context['no_topic_listing'])
	{
		// If Quick Moderation is enabled start the form.
		if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] > 0 && !empty($context['topics']))
			echo '
	<form action="', $scripturl, '?action=quickmod;board=', $context['current_board'], '.', $context['start'], '" method="post" accept-charset="UTF-8" class="clear" name="quickModForm" id="quickModForm">';

		echo '
		<ul class="topic_listing" id="messageindex">
			<li class="topic_sorting_row">';

		// No topics.... just say, "sorry bub".
		if (empty($context['topics']))
			echo '
				<strong>', $txt['topic_alert_none'], '</strong>';

		echo '
			</li>';

		// If this person can approve items and we have some awaiting approval tell them.
		if (!empty($context['unapproved_posts_message']))
		{
			echo '
			<li class="basic_row">
				<div class="warningbox" style="margin-bottom:0">! ', $context['unapproved_posts_message'], '</div>
			</li>';
		}

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
					<p class="topic_icons">
						<img src="', $topic['first_post']['icon_url'], '" alt="" />
						', $topic['is_posted_in'] ? '<img src="' . $settings['images_url'] . '/icons/profile_sm.png" alt="" class="fred" />' : '', '
					</p>
					<div class="topic_name" ', (!empty($topic['quick_mod']['modify']) ? 'id="topic_' . $topic['first_post']['id'] . '"  ondblclick="oQuickModifyTopic.modify_topic(\'' . $topic['id'] . '\', \'' . $topic['first_post']['id'] . '\');"' : ''), '>
						<h4>';

			// Is this topic new? (assuming they are logged in!)
			if ($topic['new'] && $context['user']['is_logged'])
				echo '
							<a class="new_posts" href="', $topic['new_href'], '" id="newicon' . $topic['first_post']['id'] . '">' . $txt['new'] . '</a>';

			echo '
							', $topic['is_sticky'] ? '<strong>' : '', '<span class="preview" title="', $topic[(!empty($settings['message_index_preview']) && $settings['message_index_preview'] == 2 ? 'last_post' : 'first_post')]['preview'], '"><span id="msg_' . $topic['first_post']['id'] . '">', $topic['first_post']['link'], ($context['can_approve_posts'] && !$topic['approved'] ? '&nbsp;&nbsp;<em><img src="' . $settings['images_url'] . '/admin/post_moderation_moderate.png" style="width:16px" alt="' . $txt['awaiting_approval'] . '" title="' . $txt['awaiting_approval'] . '" />(' . $txt['awaiting_approval'] . ')</em>' : ''), '</span></span>', $topic['is_sticky'] ? '</strong>' : '', '
						</h4>
					</div>
					<div class="topic_starter">
						', $txt['started_by'], ' ', $topic['first_post']['member']['link'], !empty($topic['pages']) ? '
						<ul class="small_pagelinks" id="pages' . $topic['first_post']['id'] . '" role="menubar">' . $topic['pages'] . '</ul>' : '', '
					</div>
				</div>
				<div class="topic_latest">
					<p class="topic_stats">
					', $topic['replies'], ' ', $txt['replies'], '
					<br />
					', $topic['views'], ' ', $txt['views'];

			// Show likes?
			if (!empty($modSettings['likes_enabled']))
				echo ' / ', $topic['likes'], ' ', $txt['likes'];

			echo '
					</p>
					<p class="topic_lastpost">';

			if (!empty($settings['avatars_on_indexes']))
				echo '
						<span class="board_avatar"><a href="', $topic['last_post']['member']['href'], '">', $topic['last_post']['member']['avatar']['image'], '</a></span>';
	
			echo '
						<a href="', $topic['last_post']['href'], '"><img src="', $settings['images_url'], '/icons/last_post.png" alt="', $txt['last_post'], '" title="', $txt['last_post'], '" /></a>
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
						echo '<a href="', $scripturl, '?action=quickmod;board=', $context['current_board'], '.', $context['start'], ';actions[', $topic['id'], ']=remove;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['quickmod_confirm'], '\');"><img src="', $settings['images_url'], '/icons/quick_remove.png" style="width:16px;" alt="', $txt['remove_topic'], '" title="', $txt['remove_topic'], '" /></a>';

					if ($topic['quick_mod']['lock'])
						echo '<a href="', $scripturl, '?action=quickmod;board=', $context['current_board'], '.', $context['start'], ';actions[', $topic['id'], ']=lock;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['quickmod_confirm'], '\');"><img src="', $settings['images_url'], '/icons/quick_lock.png" style="width:16px" alt="', $txt['set_lock'], '" title="', $txt['set_lock'], '" /></a>';

					if ($topic['quick_mod']['lock'] || $topic['quick_mod']['remove'])
						echo '<br />';

					if ($topic['quick_mod']['sticky'])
						echo '<a href="', $scripturl, '?action=quickmod;board=', $context['current_board'], '.', $context['start'], ';actions[', $topic['id'], ']=sticky;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['quickmod_confirm'], '\');"><img src="', $settings['images_url'], '/icons/quick_sticky.png" style="width:16px" alt="', $txt['set_sticky'], '" title="', $txt['set_sticky'], '" /></a>';

					if ($topic['quick_mod']['move'])
						echo '<a href="', $scripturl, '?action=movetopic;current_board=', $context['current_board'], ';board=', $context['current_board'], '.', $context['start'], ';topic=', $topic['id'], '.0"><img src="', $settings['images_url'], '/icons/quick_move.png" style="width:16px" alt="', $txt['move_topic'], '" title="', $txt['move_topic'], '" /></a>';
				}

				echo '
				</p>';
			}

			echo '
			</li>';
		}

		if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] == 1 && !empty($context['topics']))
		{
			echo '
			<li class="qaction_row">
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
				<input type="submit" value="', $txt['quick_mod_go'], '" onclick="return document.forms.quickModForm.qaction.value != \'\' &amp;&amp; confirm(\'', $txt['quickmod_confirm'], '\');" class="button_submit submitgo" />
			</li>';
		}

		echo '
		</ul>';

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
	global $modSettings, $context, $txt, $options, $settings;

	if ($context['no_topic_listing'])
		return;

	template_pagesection('normal_buttons', 'right');

	// Show breadcrumbs at the bottom too.
	theme_linktree();

	echo '
	<div id="topic_icons" class="description">
		<p class="floatright" id="message_index_jump_to">&nbsp;</p>';

	if (!$context['no_topic_listing'])
		echo '
		<p class="floatleft">', !empty($modSettings['enableParticipation']) && $context['user']['is_logged'] ? '
			<img src="' . $settings['images_url'] . '/icons/profile_sm.png" alt="" class="centericon" /> ' . $txt['participation_caption'] : '<img src="' . $settings['images_url'] . '/post/xx.png" alt="" class="centericon" /> ' . $txt['normal_topic'], '<br />
			' . ($modSettings['pollMode'] == '1' ? '<img src="' . $settings['images_url'] . '/topic/normal_poll.png" alt="" class="centericon" /> ' . $txt['poll'] : '') . '
		</p>
		<p>
			<img src="' . $settings['images_url'] . '/icons/quick_lock.png" alt="" class="centericon" /> ' . $txt['locked_topic'] . '<br />' . ($modSettings['enableStickyTopics'] == '1' ? '
			<img src="' . $settings['images_url'] . '/icons/quick_sticky.png" alt="" class="centericon" /> ' . $txt['sticky_topic'] . '<br />' : '') . '
		</p>';

	echo '
			<script><!-- // --><![CDATA[';

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
