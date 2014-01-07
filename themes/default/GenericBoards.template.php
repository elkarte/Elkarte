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
 * Main template for displaying the list of boards
 *
 * @param int $boards
 * @param string $id
 */
function template_list_boards($boards, $id)
{
	global $context, $settings, $txt, $scripturl;

	echo '
			<ul class="category_boards" id="', $id, '">';

	// Each board in each category's boards has:
	// new (is it new?), id, name, description, moderators (see below), link_moderators (just a list.),
	// children (see below.), link_children (easier to use.), children_new (are they new?),
	// topics (# of), posts (# of), link, href, and last_post. (see below.)
	foreach ($boards as $board)
	{
		echo '
				<li class="board_row ', (!empty($board['children'])) ? 'parent_board' : '', '" id="board_', $board['id'], '">
					<div class="board_info">
						<a class="icon_anchor" href="', ($board['is_redirect'] || $context['user']['is_guest'] ? $board['href'] : $scripturl . '?action=unread;board=' . $board['id'] . '.0;children'), '">';

		// If the board or children is new, show an indicator.
		if ($board['new'] || $board['children_new'])
			echo '
							<span class="board_icon ', $board['new'] ? 'on_board' : 'on2_board', '" title="', $txt['new_posts'], '"></span>';

		// Is it a redirection board?
		elseif ($board['is_redirect'])
			echo '
							<span class="board_icon redirect_board" title="*"></span>';

		// No new posts at all! The agony!!
		else
			echo '
							<span class="board_icon off_board" title="', $txt['old_posts'], '"></span>';

		echo '
						</a>
						<h3 class="board_name">
							<a href="', $board['href'], '" id="b', $board['id'], '">', $board['name'], '</a>';

		// Has it outstanding posts for approval? @todo - Might change presentation here.
		if ($board['can_approve_posts'] && ($board['unapproved_posts'] || $board['unapproved_topics']))
			echo '
							<a href="', $scripturl, '?action=moderate;area=postmod;sa=', ($board['unapproved_topics'] > 0 ? 'topics' : 'posts'), ';brd=', $board['id'], ';', $context['session_var'], '=', $context['session_id'], '" title="', sprintf($txt['unapproved_posts'], $board['unapproved_topics'], $board['unapproved_posts']), '" class="moderation_link"><img class="icon" src="', $settings['images_url'], '/icons/field_invalid.png" alt="(!)" /></a>';

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
		{
			echo '
						<p class="board_lastpost">';
			
			if (!empty($settings['avatars_on_indexes']))
				echo '
							<span class="board_avatar"><a href="', $board['last_post']['member']['href'], '">', $board['last_post']['member']['avatar']['image'], '</a></span>';
			echo '
							', $board['last_post']['last_post_message'], '
						</p>';
		}

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
					$child['link'] = '<a href="' . $child['href'] . '" ' . ($child['new'] ? 'class="board_new_posts" ' : '') . 'title="' . ($child['new'] ? $txt['new_posts'] : $txt['old_posts']) . ' (' . $txt['board_topics'] . ': ' . comma_format($child['topics']) . ', ' . $txt['posts'] . ': ' . comma_format($child['posts']) . ')">' . $child['name'] . ($child['new'] ? '</a> <a ' . ($child['new'] ? 'class="new_posts" ' : '') . 'href="' . $scripturl . '?action=unread;board=' . $child['id'] . '" title="' . $txt['new_posts'] . ' (' . $txt['board_topics'] . ': ' . comma_format($child['topics']) . ', ' . $txt['posts'] . ': ' . comma_format($child['posts']) . ')"><span class="new_posts">' . $txt['new'] . '</span>' : '') . '</a>';
				else
					$child['link'] = '<a href="' . $child['href'] . '" title="' . comma_format($child['posts']) . ' ' . $txt['redirects'] . '">' . $child['name'] . '</a>';

				// Has it posts awaiting approval?
				if ($child['can_approve_posts'] && ($child['unapproved_posts'] || $child['unapproved_topics']))
					$child['link'] .= ' <a href="' . $scripturl . '?action=moderate;area=postmod;sa=' . ($child['unapproved_topics'] > 0 ? 'topics' : 'posts') . ';brd=' . $child['id'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '" title="' . sprintf($txt['unapproved_posts'], $child['unapproved_topics'], $child['unapproved_posts']) . '" class="moderation_link"><img class="icon" src="' . $settings['images_url'] . '/icons/field_invalid.png" alt="(!)" /></a>';

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
			</ul>';
}