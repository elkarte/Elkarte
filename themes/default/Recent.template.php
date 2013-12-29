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
 * Recent posts page.
 */
function template_recent()
{
	global $context, $txt, $scripturl;

	template_pagesection();

	echo '
		<div id="recentposts" class="forumposts">
			<h3 class="category_header hdicon cat_img_posts">', $txt['recent_posts'], '</h3>';

	// @todo - I'm sure markup could be cleaned up a bit more here. CSS needs a bit of a tweak too.
	foreach ($context['posts'] as $post)
	{
		echo '
			<div class="', $post['alternate'] == 0 ? 'windowbg' : 'windowbg2', ' core_posts">
				<div class="content">
					<div class="counter">', $post['counter'], '</div>
					<div class="topic_details">
						<h5>', $post['board']['link'], ' / ', $post['link'], '</h5>
						<span class="smalltext">', $txt['last_post'], ' ', $txt['by'], ' <strong>', $post['poster']['link'], ' </strong> - ', $post['time'], '</span>
					</div>
					<div class="inner">', $post['message'], '</div>';

		if ($post['can_reply'] || $post['can_mark_notify'] || $post['can_delete'])
			echo '
					<ul class="quickbuttons">';

		// How about... even... remove it entirely?!
		if ($post['can_delete'])
			echo '
						<li class="listlevel1"><a href="', $scripturl, '?action=deletemsg;msg=', $post['id'], ';topic=', $post['topic'], ';recent;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['remove_message'], '?\');" class="linklevel1 remove_button">', $txt['remove'], '</a></li>';

		// Can we request notification of topics?
		if ($post['can_mark_notify'])
			echo '
						<li class="listlevel1"><a href="', $scripturl, '?action=notify;topic=', $post['topic'], '.', $post['start'], '" class="linklevel1 notify_button">', $txt['notify'], '</a></li>';

		// If they *can* reply?
		if ($post['can_reply'])
			echo '
						<li class="listlevel1"><a href="', $scripturl, '?action=post;topic=', $post['topic'], '.', $post['start'], '" class="linklevel1 reply_button">', $txt['reply'], '</a></li>';

		// If they *can* quote?
		if ($post['can_quote'])
			echo '
						<li class="listlevel1"><a href="', $scripturl, '?action=post;topic=', $post['topic'], '.', $post['start'], ';quote=', $post['id'], '" class="linklevel1 quote_button">', $txt['quote'], '</a></li>';

		if ($post['can_reply'] || $post['can_mark_notify'] || $post['can_delete'])
			echo '
					</ul>';

		echo '
				</div>
			</div>';
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
	global $context, $settings, $txt, $scripturl, $modSettings;

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
							', $context['showing_all_topics'] ? $txt['unread_topics_all'] : $txt['unread_topics_visit'];

		echo '
							<span class="sort_by_container">
								<ul id="sort_by" class="topic_sorting">';

		// Show a "select all" box for quick moderation?
		if ($context['showCheckboxes'])
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
								</ul>
							</span>';

		echo '
						</h2>
						<ul class="topic_listing" id="unread">';

		foreach ($context['topics'] as $topic)
		{
			// Calculate the color class of the topic.
			$color_class = '';
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
									<p class="topic_icons">
										<img src="', $topic['first_post']['icon_url'], '" alt="" /><img src="', $settings['images_url'], '/icons/profile_sm.png" alt="" class="fred" />
									</p>
									<div class="topic_name">
										<a class="new_posts" href="', $topic['new_href'], '" id="newicon', $topic['first_post']['id'], '">' . $txt['new'] . '</a>
										<h4>
											', $topic['is_sticky'] ? '<strong>' : '', '<span class="preview" title="', $topic[(!empty($settings['message_index_preview']) && $settings['message_index_preview'] == 2 ? 'last_post' : 'first_post')]['preview'], '"><span id="msg_' . $topic['first_post']['id'] . '">', $topic['first_post']['link'], '</span></span>', $topic['is_sticky'] ? '</strong>' : '', '
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
										<a href="', $topic['last_post']['href'], '"><img src="', $settings['images_url'], '/icons/last_post.png" alt="', $txt['last_post'], '" title="', $txt['last_post'], '" /></a>
										', $topic['last_post']['time'], '<br />
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
					<div id="topic_icons" class="description">
						<p class="floatleft">', !empty($modSettings['enableParticipation']) && $context['user']['is_logged'] ? '
							<img src="' . $settings['images_url'] . '/icons/profile_sm.png" alt="" class="centericon" /> ' . $txt['participation_caption'] : '<img src="' . $settings['images_url'] . '/post/xx.png" alt="" class="centericon" /> ' . $txt['normal_topic'], '<br />
							' . ($modSettings['pollMode'] == '1' ? '<img src="' . $settings['images_url'] . '/topic/normal_poll.png" alt="" class="centericon" /> ' . $txt['poll'] : '') . '
						</p>
						<p>
							<img src="' . $settings['images_url'] . '/icons/quick_lock.png" alt="" class="centericon" /> ' . $txt['locked_topic'] . '<br />' . ($modSettings['enableStickyTopics'] == '1' ? '
							<img src="' . $settings['images_url'] . '/icons/quick_sticky.png" alt="" class="centericon" /> ' . $txt['sticky_topic'] . '<br />' : '') . '
						</p>
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
	global $context, $settings, $txt, $scripturl, $modSettings;

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
							', $txt['unread_replies'];

		echo '
							<span class="sort_by_container">
								<ul id="sort_by" class="topic_sorting" >';
		if ($context['showCheckboxes'])
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

		// Show a "select all" box for quick moderation?
		echo '
									</li>
								</ul>
							</span>';

		echo '
						</h2>
						<ul class="topic_listing" id="unread">';

		foreach ($context['topics'] as $topic)
		{
			// Calculate the color class of the topic.
			$color_class = '';
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
									<p class="topic_icons">
										<img src="', $topic['first_post']['icon_url'], '" alt="" /><img src="', $settings['images_url'], '/icons/profile_sm.png" alt="" class="fred" />
									</p>
									<div class="topic_name">';

			// The new icons look better if they aren't all over the page.
			echo '
										<a class="new_posts" href="', $topic['new_href'], '" id="newicon', $topic['first_post']['id'], '">' . $txt['new'] . '</a>
										<h4>
											', $topic['is_sticky'] ? '<strong>' : '', '<span title="', $topic[(!empty($settings['message_index_preview']) && $settings['message_index_preview'] == 2 ? 'last_post' : 'first_post')]['preview'], '"><span id="msg_' . $topic['first_post']['id'] . '">', $topic['first_post']['link'], '</span></span>', $topic['is_sticky'] ? '</strong>' : '', '
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
										<a href="', $topic['last_post']['href'], '"><img src="', $settings['images_url'], '/icons/last_post.png" alt="', $txt['last_post'], '" title="', $txt['last_post'], '" /></a>
										', $topic['last_post']['time'], '<br />
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
					<div id="topic_icons" class="description">
						<p class="floatleft">', !empty($modSettings['enableParticipation']) && $context['user']['is_logged'] ? '
							<img src="' . $settings['images_url'] . '/icons/profile_sm.png" alt="" class="centericon" /> ' . $txt['participation_caption'] : '<img src="' . $settings['images_url'] . '/post/xx.png" alt="" class="centericon" /> ' . $txt['normal_topic'], '<br />
							' . ($modSettings['pollMode'] == '1' ? '<img src="' . $settings['images_url'] . '/topic/normal_poll.png" alt="" class="centericon" /> ' . $txt['poll'] : '') . '
						</p>
						<p>
							<img src="' . $settings['images_url'] . '/icons/quick_lock.png" alt="" class="centericon" /> ' . $txt['locked_topic'] . '<br />' . ($modSettings['enableStickyTopics'] == '1' ? '
							<img src="' . $settings['images_url'] . '/icons/quick_sticky.png" alt="" class="centericon" /> ' . $txt['sticky_topic'] . '<br />' : '') . '
						</p>
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