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
 * Used to display child boards.
 */
function template_display_child_boards_above()
{
	global $context, $txt, $scripturl, $settings;

	echo '
	<div id="board_', $context['current_board'], '_childboards" class="forum_category">
		<h2 class="category_header">
			', $txt['parent_boards'], '
		</h2>
		<ul class="category_boards" id="board_', $context['current_board'], '_children">';

	foreach ($context['boards'] as $board)
	{
		echo '
			<li class="board_row ', (!empty($board['children'])) ? 'parent_board' : '', '" id="board_', $board['id'], '">
				<div class="board_info">
					<a class="icon_anchor" href="', ($board['is_redirect'] || $context['user']['is_guest'] ? $board['href'] : $scripturl . '?action=unread;board=' . $board['id'] . '.0;children'), '">';

		// If the board or children is new, show an indicator.
		if ($board['new'] || $board['children_new'])
			echo '
						<img class="board_icon" src="', $settings['images_url'], '/', $context['theme_variant_url'], 'on', $board['new'] ? '' : '2', '.png" alt="', $txt['new_posts'], '" title="', $txt['new_posts'], '" />';
		// Is it a redirection board?
		elseif ($board['is_redirect'])
			echo '
						<img class="board_icon" src="', $settings['images_url'], '/', $context['theme_variant_url'], 'redirect.png" alt="*" title="*" />';
		// No new posts at all! The agony!!
		else
			echo '
						<img class="board_icon" src="', $settings['images_url'], '/', $context['theme_variant_url'], 'off.png" alt="', $txt['old_posts'], '" title="', $txt['old_posts'], '" />';

		echo '
					</a>
					<h3 class="board_name">
						<a href="', $board['href'], '" id="b', $board['id'], '">', $board['name'], '</a>';

		// Has it outstanding posts for approval?
		if ($board['can_approve_posts'] && ($board['unapproved_posts'] || $board['unapproved_topics']))
			echo '
						<a href="', $scripturl, '?action=moderate;area=postmod;sa=', ($board['unapproved_topics'] > 0 ? 'topics' : 'posts'), ';brd=', $board['id'], ';', $context['session_var'], '=', $context['session_id'], '" title="', sprintf($txt['unapproved_posts'], $board['unapproved_topics'], $board['unapproved_posts']), '" class="moderation_link">(!)</a>';

		echo '
					</h3>
					<p class="board_description">', $board['description'], '</p>';

		// Show the "Moderators: ". Each has name, href, link, and id. (but we're gonna use link_moderators.)
		if (!empty($board['moderators']))
			echo '
					<p class="moderators">', count($board['moderators']) === 1 ? $txt['moderator'] : $txt['moderators'], ': ', implode(', ', $board['link_moderators']), '</p>';

		// Show some basic information about the number of posts, etc.
		echo '
				</div>
				<div class="board_latest">
					<p class="board_stats">
						', comma_format($board['posts']), ' ', $board['is_redirect'] ? $txt['redirects'] : $txt['posts'], '
						', $board['is_redirect'] ? '' : '<br /> ' . comma_format($board['topics']) . ' ' . $txt['board_topics'], '
					</p>';

		// @todo - Last post message still needs some work. Probably split the language string into three chunks.
		// Example:
		// <chunk>Re: Nunc aliquam justo e...</chunk>  <chunk>by Whoever</chunk> <chunk>Last post: Today at 08:00:37 am</chunk>
		// That should still allow sufficient scope for any language, if done sensibly.
		if (!empty($board['last_post']['id']))
			echo '
					<p class="board_lastpost">
						', $board['last_post']['last_post_message'], '
					</p>';

		echo '
				</div>
			</li>';

		// Show the "Child Boards: ". (there's a link_children but we're going to bold the new ones...)
		if (!empty($board['children']))
		{
			// Sort the links into an array with new boards bold so it can be imploded.
			$children = array();

			// Each child in each board's children has:
			// id, name, description, new (is it new?), topics (#), posts (#), href, link, and last_post.
			foreach ($board['children'] as $child)
			{
				if (!$child['is_redirect'])
					$child['link'] = '<a href="' . $child['href'] . '" ' . ($child['new'] ? 'class="board_new_posts" ' : '') . 'title="' . ($child['new'] ? $txt['new_posts'] : $txt['old_posts']) . ' (' . $txt['board_topics'] . ': ' . comma_format($child['topics']) . ', ' . $txt['posts'] . ': ' . comma_format($child['posts']) . ')">' . $child['name'] . ($child['new'] ? '</a> <a  ' . ($child['new'] ? 'class="new_posts" ' : '') . 'href="' . $scripturl . '?action=unread;board=' . $child['id'] . '" title="' . $txt['new_posts'] . ' (' . $txt['board_topics'] . ': ' . comma_format($child['topics']) . ', ' . $txt['posts'] . ': ' . comma_format($child['posts']) . ')"><span class="new_posts">' . $txt['new'] . '</span>' : '') . '</a>';
				else
					$child['link'] = '<a href="' . $child['href'] . '" title="' . comma_format($child['posts']) . ' ' . $txt['redirects'] . '">' . $child['name'] . '</a>';

				// Has it posts awaiting approval?
				if ($child['can_approve_posts'] && ($child['unapproved_posts'] | $child['unapproved_topics']))
					$child['link'] .= ' <a href="' . $scripturl . '?action=moderate;area=postmod;sa=' . ($child['unapproved_topics'] > 0 ? 'topics' : 'posts') . ';brd=' . $child['id'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '" title="' . sprintf($txt['unapproved_posts'], $child['unapproved_topics'], $child['unapproved_posts']) . '" class="moderation_link">(!)</a>';

				$children[] = $child['link'];
			}

			// New <li> for child boards (if any). Can be styled to look like part of previous <li>.
			// Use h4 tag here for better a11y. Use <ul> for list of child boards.
			// Having child board links in <li>'s will allow "tidy child boards" via easy CSS tweaks. ;)
			echo '
			<li class="childboard_row" id="board_', $board['id'], '_children">
				<ul class="childboards">
					<li>
						<h4>', $txt['parent_boards'], ':</h4>
					</li>
					<li>
						', implode('</li><li> - ', $children), '
					</li>
				</ul>
			</li>';
		}
	}

	echo '
		</ul>
	</div>';
}

/**
 * Header bar and extra details above topic listing.
 */
function template_pages_and_buttons_above()
{
	global $context, $settings, $txt, $options;

	if ($context['no_topic_listing'])
		return;

	template_pagesection('normal_buttons', 'right', 'go_down');

	if (!empty($context['description']) || !empty($context['moderators']))
	{
		echo '
		<div id="description_board">
			<h2 class="category_header">', $context['name'];

		if (!empty($context['moderators']))
			echo '
				<span class="moderators">(', count($context['moderators']) === 1 ? $txt['moderator'] : $txt['moderators'], ': ', implode(', ', $context['link_moderators']), '.)</span>';

		echo '
				<ul class="topic_sorting" id="sort_by">';
		if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] == 1)
			echo '
					<li class="listlevel1 quickmod_select_all">
						<input type="checkbox" onclick="invertAll(this, document.getElementById(\'quickModForm\'), \'topics[]\');" class="input_check" />
					</li>';
		echo '
					<li class="listlevel1 topic_sorting_row">', $txt['sort_by'], ': ', $context['topics_headers'][$context['sort_by']]['link'], '
						<ul class="menulevel2" id="sortby">';
		foreach ($context['topics_headers'] as $key => $value)
			echo '
							<li class="listlevel2 sort_by_item" id="sort_by_item_', $key, '"><a href="', $value['url'], '" class="linklevel2">', $txt[$key], ' ', $value['sort_dir_img'], '</a></li>';
		echo '
						</ul>';

			// Show a "select all" box for quick moderation?
		echo '
					</li>
				</ul>';

		echo '
			</h2>';

		if (!empty($context['description']) || !empty($settings['display_who_viewing']))
			echo '
			<p>';

		if (!empty($context['description']))
			echo '
			', $context['description'], '&nbsp;';

		// @todo - Thought the who is stuff was better here. Presentation still WIP.
		if (!empty($settings['display_who_viewing']))
		{
			echo '
			<span class="whoisviewing">';

			if ($settings['display_who_viewing'] == 1)
				echo count($context['view_members']), ' ', count($context['view_members']) === 1 ? $txt['who_member'] : $txt['members'];
			else
				echo empty($context['view_members_list']) ? '0 ' . $txt['members'] : implode(', ', $context['view_members_list']) . (empty($context['view_num_hidden']) || $context['can_moderate_forum'] ? '' : ' (+ ' . $context['view_num_hidden'] . ' ' . $txt['hidden'] . ')');

			echo $txt['who_and'], $context['view_num_guests'], ' ', $context['view_num_guests'] == 1 ? $txt['guest'] : $txt['guests'], $txt['who_viewing_board'];

			echo '
			</span>';
		}

		if (!empty($context['description']) || !empty($settings['display_who_viewing']))
			echo'
			</p>';

		echo '
		</div>';
	}
}

/**
 * The actual topic listing.
 */
function template_main()
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

		// Are there actually any topics to show?
		if (!empty($context['topics']))
		{
		}
		// No topics.... just say, "sorry bub".
		else
			echo '
				<strong>', $txt['topic_alert_none'], '</strong>';

		echo '
			</li>';

		// If this person can approve items and we have some awaiting approval tell them.
		if (!empty($context['unapproved_posts_message']))
		{
			echo '
			<li class="basic_row">
				<div class="noticebox" style="margin-bottom:0">! ', $context['unapproved_posts_message'], '</div>
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
					<div class="topic_name" ', (!empty($topic['quick_mod']['modify']) ? 'id="topic_' . $topic['first_post']['id'] . '"  ondblclick="oQuickModifyTopic.modify_topic(\'' . $topic['id'] . '\', \'' . $topic['first_post']['id'] . '\');"' : ''), '>';

			// Is this topic new? (assuming they are logged in!)
			if ($topic['new'] && $context['user']['is_logged'])
				echo '
						<a href="', $topic['new_href'], '" id="newicon' . $topic['first_post']['id'] . '"><span class="new_posts">' . $txt['new'] . '</span></a>';

			echo '
						<h4>
							', $topic['is_sticky'] ? '<strong>' : '', '<span class="preview" title="', $topic[(!empty($settings['message_index_preview']) && $settings['message_index_preview_first'] == 2 ? 'last_post' : 'first_post')]['preview'], '"><span id="msg_' . $topic['first_post']['id'] . '">', $topic['first_post']['link'], ($context['can_approve_posts'] && !$topic['approved'] ? '&nbsp;&nbsp;<em><img src="' . $settings['images_url'] . '/admin/post_moderation_moderate.png" style="width:16px" alt="' . $txt['awaiting_approval'] . '" title="' . $txt['awaiting_approval'] . '" />(' . $txt['awaiting_approval'] . ')</em>' : ''), '</span></span>', $topic['is_sticky'] ? '</strong>' : '', '
						</h4>
					</div>
					<p class="topic_starter">
						', $txt['started_by'], ' ', $topic['first_post']['member']['link'], !empty($topic['pages']) ? '
						<span class="small_pagelinks" id="pages' . $topic['first_post']['id'] . '">' . $topic['pages'] . '</span>' : '', '
					</p>
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
					<p class="topic_lastpost">
						<a href="', $topic['last_post']['href'], '"><img src="', $settings['images_url'], '/icons/last_post.png" alt="', $txt['last_post'], '" title="', $txt['last_post'], '" /></a>
						', $topic['last_post']['time'], '<br />
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
					<option value="">--------</option>';

			foreach ($context['qmod_actions'] as $qmod_action)
				if ($context['can_' . $qmod_action])
					echo '
					<option value="' . $qmod_action . '">' . $txt['quick_mod_' . $qmod_action] . '</option>';

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
function template_pages_and_buttons_below()
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
				if (typeof(window.XMLHttpRequest) != "undefined")
					aJumpTo[aJumpTo.length] = new JumpTo({
						sContainerId: "quick_mod_jump_to",
						sClassName: "qaction",
						sJumpToTemplate: "%dropdown_list%",
						iCurBoardId: ', $context['current_board'], ',
						iCurBoardChildLevel: ', $context['jump_to']['child_level'], ',
						sCurBoardName: "', $context['jump_to']['board_name'], '",
						sBoardChildLevelIndicator: "==",
						sBoardPrefix: "=> ",
						sCatSeparator: "-----------------------------",
						sCatPrefix: "",
						bNoRedirect: true,
						bDisabled: true,
						sCustomName: "move_to"
					});';

	echo '
				if (typeof(window.XMLHttpRequest) != "undefined")
					aJumpTo[aJumpTo.length] = new JumpTo({
						sContainerId: "message_index_jump_to",
						sJumpToTemplate: "<label class=\"smalltext\" for=\"%select_id%\">', $context['jump_to']['label'], ':<" + "/label> %dropdown_list%",
						iCurBoardId: ', $context['current_board'], ',
						iCurBoardChildLevel: ', $context['jump_to']['child_level'], ',
						sCurBoardName: "', $context['jump_to']['board_name'], '",
						sBoardChildLevelIndicator: "==",
						sBoardPrefix: "=> ",
						sCatSeparator: "-----------------------------",
						sCatPrefix: "",
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
