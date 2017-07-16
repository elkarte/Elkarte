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
 * @version 1.1 Release Candidate 1
 *
 */

/**
 * Helper function to subdivide the boards in a number of sets with
 * approximately the same number of boards each, but preserving the
 * grouping in categories
 *
 * @param int[] $categories contains the number of boards for each category
 * @param int $total_boards total number of boards present
 * @return int[]
 */
function optimizeBoardsSubdivision($categories, $total_boards)
{
	$num_groups = 2;
	$optimal_boards = round($total_boards / $num_groups);
	$groups = array(0 => array());
	$group_totals = array(0 => 0, 1 => 0);
	$current_streak = 0;
	$current_group = 0;

	foreach ($categories as $cat => $boards_count)
	{
		$groups[$current_group][] = $cat;
		// The +1 here are to take in consideration category headers
		// that visuallt account for about one additional board
		$group_totals[$current_group] += $boards_count + 1;
		$current_streak += $boards_count + 1;

		// Start a new streak
		if ($current_streak > $optimal_boards && $current_group < ($num_groups - 1))
		{
			$current_streak = 0;
			$current_group++;
			$groups[$current_group] = array();
			$group_totals[$current_group] = 0;
		}
	}

	// The current difference of elements from left and right
	$diff_current = $group_totals[0] - $group_totals[1];

	// If we have less on the right, let's try picking one from the left
	if ($diff_current > 0)
	{
		$last_group = array_pop($groups[0]);
		// Same as above, +1 for cat header
		$diff_alternate = $diff_current - 2 * ($categories[$last_group] + 1);

		if (abs($diff_alternate) < $diff_current)
			array_unshift($groups[1], $last_group);
		else
			$groups[0][] = $last_group;
	}
	// If we have less on the left, let's try picking one from the right
	elseif ($diff_current < 0)
	{
		$first_group = array_shift($groups[1]);
		// Same as above, +1 for cat header
		$diff_alternate = $diff_current + 2 * ($categories[$first_group] + 1);

		if (abs($diff_alternate) < abs($diff_current))
			$groups[0][] = $first_group;
		else
			array_unshift($groups[1], $first_group);
	}

	return $groups;
}

/**
 * Main template for displaying the list of boards
 *
 * @param array $boards
 * @param string $id
 */
function template_list_boards(array $boards, $id)
{
	global $context, $txt, $scripturl;

	echo '
			<ul class="category_boards" id="', $id, '">';

	// Each board in each category's boards has:
	// new (is it new?), id, name, description, moderators (see below), link_moderators (just a list.),
	// children (see below.), link_children (easier to use.), children_new (are they new?),
	// topics (# of), posts (# of), link, href, and last_post. (see below.)
	foreach ($boards as $board)
	{
		echo '
				<li class="board_row', (!empty($board['children'])) ? ' parent_board' : '', $board['is_redirect'] ? ' board_row_redirect' : '', '" id="board_', $board['id'], '">
					<div class="board_info">
						<a class="icon_anchor" href="', ($board['is_redirect'] || $context['user']['is_guest'] ? $board['href'] : $scripturl . '?action=unread;board=' . $board['id'] . '.0;children'), '">';

		// If the board or children is new, show an indicator.
		if ($board['new'] || $board['children_new'])
			echo '
							<span class="board_icon ', $board['new'] ? 'i-board-new' : 'i-board-sub', '" title="', $txt['new_posts'], '"></span>';

		// Is it a redirection board?
		elseif ($board['is_redirect'])
			echo '
							<span class="board_icon i-board-redirect" title="', sprintf($txt['redirect_board_to'], Util::htmlspecialchars($board['name'])), '"></span>';

		// No new posts at all! The agony!!
		else
			echo '
							<span class="board_icon i-board-off" title="', $txt['old_posts'], '"></span>';

		echo '
						</a>
						<h3 class="board_name">
							<a href="', $board['href'], '" id="b', $board['id'], '">', $board['name'], '</a>';

		// Has it outstanding posts for approval? @todo - Might change presentation here.
		if ($board['can_approve_posts'] && ($board['unapproved_posts'] || $board['unapproved_topics']))
			echo '
							<a href="', $scripturl, '?action=moderate;area=postmod;sa=', ($board['unapproved_topics'] > 0 ? 'topics' : 'posts'), ';brd=', $board['id'], ';', $context['session_var'], '=', $context['session_id'], '" title="', sprintf($txt['unapproved_posts'], $board['unapproved_topics'], $board['unapproved_posts']), '" class="moderation_link"><i class="icon i-alert"></i></a>';

		echo '
						</h3>
						<h4 class="board_description">', $board['description'], '</h4>';

		// Show the "Moderators: ". Each has name, href, link, and id. (but we're gonna use link_moderators.)
		if (!empty($board['moderators']))
			echo '
						<p class="moderators">', count($board['moderators']) === 1 ? $txt['moderator'] : $txt['moderators'], ': ', implode(', ', $board['link_moderators']), '</p>';

		// Show some basic information about the number of posts, etc.
		echo '
					</div>
					<div class="board_latest">
						<aside class="board_stats">
							', comma_format($board['posts']), ' ', $board['is_redirect'] ? $txt['redirects'] : $txt['posts'], $board['is_redirect'] ? '' : '<br /> ' . comma_format($board['topics']) . ' ' . $txt['board_topics'], '
						</aside>';

		// @todo - Last post message still needs some work. Probably split the language string into three chunks.
		// Example:
		// <chunk>Re: Nunc aliquam justo e...</chunk>  <chunk>by Whoever</chunk> <chunk>Last post: Today at 08:00:37 am</chunk>
		// That should still allow sufficient scope for any language, if done sensibly.
		if (!empty($board['last_post']['id']))
		{
			echo '
						<p class="board_lastpost">';

			if (!empty($board['last_post']['member']['avatar']))
				echo '
							<span class="board_avatar"><a href="', $board['last_post']['member']['href'], '"><img class="avatar" src="', $board['last_post']['member']['avatar']['href'], '" alt="" /></a></span>';
			else
				echo '
							<span class="board_avatar"><a href="#"></a></span>';
			echo '
							', $board['last_post']['last_post_message'], '
						</p>';
		}

		echo '
					</div>
				</li>';

		// Show the "Sub-boards: ". (there's a link_children but we're going to bold the new ones...)
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
					$child['link'] .= ' <a href="' . $scripturl . '?action=moderate;area=postmod;sa=' . ($child['unapproved_topics'] > 0 ? 'topics' : 'posts') . ';brd=' . $child['id'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '" title="' . sprintf($txt['unapproved_posts'], $child['unapproved_topics'], $child['unapproved_posts']) . '" class="moderation_link"><i class="icon i-alert"></i></a>';

				$children[] = $child['link'];
			}

			// New <li> for sub-boards (if any). Can be styled to look like part of previous <li>.
			// Use h4 tag here for better a11y. Use <ul> for list of sub-boards.
			// Having sub-board links in <li>'s will allow "tidy sub-boards" via easy CSS tweaks. ;)
			echo '
				<li class="childboard_row" id="board_', $board['id'], '_children">
					<ul class="childboards">
						<li>
							<h4>', $txt['parent_boards'], ':</h4>
						</li>
						<li>
							', implode('</li><li>', $children), '
						</li>
					</ul>
				</li>';
		}
	}

	echo '
			</ul>';
}

/**
 * A template that displays a list of boards subdivided by categories, with
 * a checkbox to allow select them, toggle selection on the category title
 *
 * @param string $form_name The name of the form that contains the list
 * @param string $input_names Name that should be assigned to the inputs
 * @param bool $select_all if true the a "select all" option is shown
 */
function template_pick_boards($form_name, $input_names = 'brd', $select_all = true)
{
	global $context, $txt;

	if ($select_all)
		echo '
						<h3 class="secondary_header panel_toggle">
							<span>
								<span id="advanced_panel_toggle" class="chevricon i-chevron-', $context['boards_check_all'] ? 'down' : 'up', ' hide" title="', $txt['hide'], '"></span>
							</span>
							<a href="#" id="advanced_panel_link">', $txt['choose_board'], '</a>
						</h3>
						<div id="advanced_panel_div"', $context['boards_check_all'] ? ' class="hide"' : '', '>';

	// Make two nice columns of boards, link each category header to toggle select all boards in each
	$group_cats = optimizeBoardsSubdivision($context['boards_in_category'], $context['num_boards']);

	foreach ($group_cats as $groups)
	{
		echo '
							<ul class="ignoreboards floatleft">';
		foreach ($groups as $cat_id)
		{
			$category = $context['categories'][$cat_id];
			echo '
								<li class="category">
									<a href="javascript:void(0);" onclick="selectBoards([', implode(', ', $category['child_ids']), '], \'', $form_name, '\', \'', $input_names, '\'); return false;">', $category['name'], '</a>
									<ul>';

			foreach ($category['boards'] as $board)
			{
				echo '
										<li class="board" style="margin-', $context['right_to_left'] ? 'right' : 'left', ': ', $board['child_level'], 'em;">
											<label for="', $input_names, $board['id'], '">
												<input type="checkbox" id="', $input_names, $board['id'], '" name="', $input_names, '[', $board['id'], ']" value="', $board['id'], '"', $board['selected'] ? ' checked="checked"' : '', ' /> ', $board['name'], '
											</label>
										</li>';
			}

			echo '
									</ul>
								</li>';
		}
		echo '
							</ul>';
	}

	// Provide an easy way to select all boards
	if ($select_all)
	{
		echo '
						</div>
						<div class="submitbutton">
							<span class="floatleft">
								<input type="checkbox" name="all" id="check_all" value=""', $context['boards_check_all'] ? ' checked="checked"' : '', ' onclick="invertAll(this, this.form, \'', $input_names, '\');" />
								<label for="check_all">
									<em> ', $txt['check_all'], '</em>
								</label>
							</span>
						</div>';

		// And now all the JS to make this work
		addInlineJavascript('
		// Some javascript for the advanced board select toggling
		var oAdvancedPanelToggle = new elk_Toggle({
			bToggleEnabled: true,
			bCurrentlyCollapsed: ' . ($context['boards_check_all'] ? 'true' : 'false') . ',
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
					msgExpanded: ' . JavaScriptEscape($txt['choose_board']) . ',
					msgCollapsed: ' . JavaScriptEscape($txt['choose_board']) . '
				}
			],
		});', true);
	}
}
