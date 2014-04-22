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
 * @version 1.0 Release Candidate 1
 *
 */

/**
 * We need some help to proprerly display things
 */
function template_Recent_init()
{
	loadTemplate('GenericMessages');
}

/**
 * Recent posts page.
 */
function template_recent()
{
	global $context, $txt;

	template_pagesection();

	echo '
		<div id="recentposts" class="forumposts">
			<h3 class="category_header hdicon cat_img_posts">', $txt['recent_posts'], '</h3>';

	foreach ($context['posts'] as $post)
	{
		$post['class'] = $post['alternate'] == 0 ? 'windowbg' : 'windowbg2';
		$post['title'] = $post['board']['link'] . ' / ' . $post['link'];
		$post['date'] = $txt['last_post'] . ' ' . $txt['by'] . ' <strong>' . $post['poster']['link'] . ' </strong> - ' . $post['html_time'];

		template_simple_message($post);
	}

	echo '
		</div>';

	template_pagesection();
}

/**
 * Unread posts page.
 */
function template_unread()
{
	global $context, $txt, $scripturl;

	$message_icon_sprite = array('clip' => '', 'lamp' => '', 'poll' => '', 'question' => '', 'xx' => '', 'moved' => '', 'exclamation' => '', 'thumbup' => '', 'thumbdown' => '');

	if (!empty($context['topics']))
	{
		template_pagesection('recent_buttons', 'right');

		if ($context['showCheckboxes'])
			echo '
					<form action="', $scripturl, '?action=quickmod" method="post" accept-charset="UTF-8" name="quickModForm" id="quickModForm" style="margin: 0;">
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="hidden" name="qaction" value="markread" />
						<input type="hidden" name="redirect_url" value="action=unread', (!empty($context['showing_all_topics']) ? ';all' : ''), $context['querystring_board_limits'], '" />';

		echo '
						<h2 class="category_header" id="unread_header">
							', $context['showing_all_topics'] ? $txt['unread_topics_all'] : $txt['unread_topics_visit'], '
						</h2>
						<ul id="sort_by" class="topic_sorting topic_sorting_recent">';

		// Show a "select all" box for quick moderation?
		if ($context['showCheckboxes'])
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
								</ul>';

		echo '
							</li>
						</ul>
						<ul class="topic_listing" id="unread">';

		foreach ($context['topics'] as $topic)
		{
			// Calculate the color class of the topic.
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
							<li class="', $color_class, '">
								<div class="topic_info">
									<p class="topic_icons', isset($message_icon_sprite[$topic['first_post']['icon']]) ? ' topicicon img_' . $topic['first_post']['icon'] : '', '">';

							if (!isset($message_icon_sprite[$topic['first_post']['icon']]))
								echo '
										<img src="', $topic['first_post']['icon_url'], '" alt="" />';

							echo '
										', $topic['is_posted_in'] ? '<span class="fred topicicon img_profile"></span>' : '', '
									</p>
									<div class="topic_name">
										<h4>
											<a class="new_posts" href="', $topic['new_href'], '" id="newicon', $topic['first_post']['id'], '">' . $txt['new'] . '</a>
											', $topic['is_sticky'] ? '<strong>' : '', '<span class="preview" title="', $topic['default_preview'], '"><span id="msg_' . $topic['first_post']['id'] . '">', $topic['first_post']['link'], '</span></span>', $topic['is_sticky'] ? '</strong>' : '', '
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
										', $topic['views'], ' ', $txt['views'], '
									</p>
									<p class="topic_lastpost">
										<a class="topicicon img_last_post" href="', $topic['last_post']['href'], '" title="', $txt['last_post'], '"></a>
										', $topic['last_post']['html_time'], '<br />
										', $txt['by'], ' ', $topic['last_post']['member']['link'], '
									</p>
								</div>';

			if ($context['showCheckboxes'])
				echo '
								<p class="topic_moderation" >
									<input type="checkbox" name="topics[]" value="', $topic['id'], '" class="input_check" />
								</p>';

			echo '
							</li>';
		}

		echo '
						</ul>';

		if ($context['showCheckboxes'])
			echo '
					</form>';

		template_pagesection('recent_buttons', 'right');

		echo '
					<div id="topic_icons" class="description">', template_basicicons_legend(), '
					</div>';
	}
	else
		echo '
					<div class="forum_category">
						<h2 class="category_header">
							', $txt['topic_alert_none'], '
						</h2>
						<div class="board_row centertext">
							', $context['showing_all_topics'] ? '<strong>' . $txt['find_no_results'] . '</strong>' : $txt['unread_topics_visit_none'], '
						</div>
					</div>';
}

/**
 * Interface to show unread replies to your posts.
 */
function template_replies()
{
	global $context, $txt, $scripturl;

	$message_icon_sprite = array('clip' => '', 'lamp' => '', 'poll' => '', 'question' => '', 'xx' => '', 'moved' => '', 'exclamation' => '', 'thumbup' => '', 'thumbdown' => '');

	if (!empty($context['topics']))
	{
		template_pagesection('recent_buttons', 'right');

		if ($context['showCheckboxes'])
			echo '
					<form action="', $scripturl, '?action=quickmod" method="post" accept-charset="UTF-8" name="quickModForm" id="quickModForm" style="margin: 0;">
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="hidden" name="qaction" value="markread" />
						<input type="hidden" name="redirect_url" value="action=unreadreplies', (!empty($context['showing_all_topics']) ? ';all' : ''), $context['querystring_board_limits'], '" />';

		// [WIP] There is trial code here to hide the topic icon column. Colspan can be cleaned up later.
		echo '
						<h2 class="category_header" id="unread_header">
							', $txt['unread_replies'], '
						</h2>
						<ul id="sort_by" class="topic_sorting topic_sorting_recent">';

		if ($context['showCheckboxes'])
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
								</ul>';

		// Show a "select all" box for quick moderation?
		echo '
							</li>
						</ul>
						<ul class="topic_listing" id="unread">';

		foreach ($context['topics'] as $topic)
		{
			// Calculate the color class of the topic.
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
							<li class="', $color_class, '">
								<div class="topic_info">
									<p class="topic_icons', isset($message_icon_sprite[$topic['first_post']['icon']]) ? ' topicicon img_' . $topic['first_post']['icon'] : '', '">';

							if (!isset($message_icon_sprite[$topic['first_post']['icon']]))
								echo '
										<img src="', $topic['first_post']['icon_url'], '" alt="" />';

							echo '
										', $topic['is_posted_in'] ? '<span class="fred topicicon img_profile"></span>' : '', '
									</p>
									<div class="topic_name">';

			// The new icons look better if they aren't all over the page.
			echo '
										<h4>
											<a class="new_posts" href="', $topic['new_href'], '" id="newicon', $topic['first_post']['id'], '">' . $txt['new'] . '</a>
											', $topic['is_sticky'] ? '<strong>' : '', '<span class="preview" title="', $topic['default_preview'], '"><span id="msg_' . $topic['first_post']['id'] . '">', $topic['first_post']['link'], '</span></span>', $topic['is_sticky'] ? '</strong>' : '', '
										</h4>
									</div>
									<div class="topic_starter">
										', $topic['first_post']['started_by'], !empty($topic['pages']) ? '
										<ul class="small_pagelinks" id="pages' . $topic['first_post']['id'] . '" role="menubar">' . $topic['pages'] . '</ul>' : '', '
									</div>
								</div>
								<div class="topic_latest">
									<p class="topic_stats">
										', $topic['replies'], ' ', $txt['replies'], '
										<br />
										', $topic['views'], ' ', $txt['views'], '
									</p>
									<p class="topic_lastpost">
										<a class="topicicon img_last_post" href="', $topic['last_post']['href'], ' title="', $txt['last_post'], '"></a>
										', $topic['last_post']['html_time'], '<br />
										', $txt['by'], ' ', $topic['last_post']['member']['link'], '
									</p>
								</div>';

			if ($context['showCheckboxes'])
				echo '
								<p class="topic_moderation">
									<input type="checkbox" name="topics[]" value="', $topic['id'], '" class="input_check" />
								</p>';

			echo '
							</li>';
		}

		echo '
						</ul>';

		if ($context['showCheckboxes'])
			echo '
					</form>';

		template_pagesection('recent_buttons', 'right');

		echo '
					<div id="topic_icons" class="description">', template_basicicons_legend(), '
					</div>';
	}
	else
		echo '
					<div class="forum_category">
						<h2 class="category_header">
							', $txt['topic_alert_none'], '
						</h2>
						<div class="board_row centertext">
							', $context['showing_all_topics'] ? '<strong>' . $txt['find_no_results'] . '</strong>' : $txt['unread_topics_visit_none'], '
						</div>
					</div>';
}