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
 * Recent posts page.
 */
function template_main()
{
	global $context, $settings, $txt, $scripturl;
		template_pagesection(false, false, 'go_down');
	echo '
		<div id="recentposts" class="forumposts">';
		//template_pagesection(false, false, 'go_down');
		echo '
			<h3 class="catbg">
				<img src="', $settings['images_url'], '/post/xx.png" alt="" class="icon" />',$txt['recent_posts'],'
			</h3>';
	//template_pagesection(false, false, 'go_down');
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

		// If they *can* reply?
		if ($post['can_reply'])
			echo '
						<li class="listlevel1"><a href="', $scripturl, '?action=post;topic=', $post['topic'], '.', $post['start'], '" class="linklevel1 reply_button"><span>', $txt['reply'], '</span></a></li>';

		// If they *can* quote?
		if ($post['can_quote'])
			echo '
						<li class="listlevel1"><a href="', $scripturl, '?action=post;topic=', $post['topic'], '.', $post['start'], ';quote=', $post['id'], '" class="linklevel1 quote_button"><span>', $txt['quote'], '</span></a></li>';

		// Can we request notification of topics?
		if ($post['can_mark_notify'])
			echo '
						<li class="listlevel1"><a href="', $scripturl, '?action=notify;topic=', $post['topic'], '.', $post['start'], '" class="linklevel1 notify_button"><span>', $txt['notify'], '</span></a></li>';

		// How about... even... remove it entirely?!
		if ($post['can_delete'])
			echo '
						<li class="listlevel1"><a href="', $scripturl, '?action=deletemsg;msg=', $post['id'], ';topic=', $post['topic'], ';recent;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['remove_message'], '?\');" class="linklevel1 remove_button"><span>', $txt['remove'], '</span></a></li>';

		if ($post['can_reply'] || $post['can_mark_notify'] || $post['can_delete'])
			echo '
					</ul>';

		echo '
				</div>
			</div>';
	}

	//template_pagesection();

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
		template_pagesection('recent_buttons', 'right', 'go_down');

		if ($context['showCheckboxes'])
			echo '
					<form action="', $scripturl, '?action=quickmod" method="post" accept-charset="UTF-8" name="quickModForm" id="quickModForm" style="margin: 0;">
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="hidden" name="qaction" value="markread" />
						<input type="hidden" name="redirect_url" value="action=unread', (!empty($context['showing_all_topics']) ? ';all' : ''), $context['querystring_board_limits'], '" />';

		// [WIP] There is trial code here to hide the topic icon column. Colspan can be cleaned up later.
		echo '
						<h2 class="category_header" id="unread_header">
							', $context['showing_all_topics'] ? $txt['unread_topics_all'] : $txt['unread_topics_visit'], ' 
						</h2>
						<ul class="topic_listing" id="unread">
							<li class="topic_sorting_row">
								<h3>
									Sort by: <a href="', $scripturl, '?action=unread', $context['showing_all_topics'] ? ';all' : '', $context['querystring_board_limits'], ';sort=subject', $context['sort_by'] == 'subject' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['subject'], $context['sort_by'] == 'subject' ? ' <img class="sort" src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.png" alt="" />' : '', '</a>
											/ <a href="', $scripturl, '?action=unread', $context['showing_all_topics'] ? ';all' : '', $context['querystring_board_limits'], ';sort=replies', $context['sort_by'] == 'replies' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['replies'], $context['sort_by'] == 'replies' ? ' <img class="sort" src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.png" alt="" />' : '', '</a>
											 / <a href="', $scripturl, '?action=unread', $context['showing_all_topics'] ? ';all' : '', $context['querystring_board_limits'], ';sort=last_post', $context['sort_by'] == 'last_post' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['last_post'], $context['sort_by'] == 'last_post' ? ' <img class="sort" src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.png" alt="" />' : '', '</a>
								</h3>';

		// Show a "select all" box for quick moderation?
		if ($context['showCheckboxes'])
			echo '
								<p>
									<input type="checkbox" onclick="invertAll(this, this.form, \'topics[]\');" class="input_check" />
								</p>';

		echo '
							</li>';

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

			echo '
										<a href="', $topic['new_href'], '" id="newicon', $topic['first_post']['id'], '"><span class="new_posts">' . $txt['new'] . '</span></a>
										<h4>
											', $topic['is_sticky'] ? '<strong>' : '', '<span class="preview" title="', $topic[(!empty($settings['message_index_preview']) && $settings['message_index_preview_first'] == 2 ? 'last_post' : 'first_post')]['preview'], '"><span id="msg_' . $topic['first_post']['id'] . '">', $topic['first_post']['link'], '</span></span>', $topic['is_sticky'] ? '</strong>' : '', '
										</h4>
									</div>
									<p class="topic_starter">
										', $txt['started_by'], ' ', $topic['first_post']['member']['link'], '
										<span class="small_pagelinks" id="pages' . $topic['first_post']['id'] . '">', $topic['pages'], '</span>
									</p>
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

		template_pagesection('recent_buttons', 'right');

		if ($context['showCheckboxes'])
			echo '
					</form>';
	}
	else
		echo '
					<h2 class="category_header">
						', $context['showing_all_topics'] ? $txt['msg_alert_none'] : $txt['unread_topics_visit_none'], '
					</h2>';

	echo '
					<div id="topic_icons" class="description">
						<p class="floatleft">', !empty($modSettings['enableParticipation']) && $context['user']['is_logged'] ? '
							<img src="' . $settings['images_url'] . '/icons/profile_sm.png" alt="" class="centericon" /> ' . $txt['participation_caption'] : '<img src="' . $settings['images_url'] . '/post/xx.png" alt="" class="centericon" /> ' . $txt['normal_topic'], '<br />
							'. ($modSettings['pollMode'] == '1' ? '<img src="' . $settings['images_url'] . '/topic/normal_poll.png" alt="" class="centericon" /> ' . $txt['poll'] : '') . '
						</p>
						<p>
							<img src="' . $settings['images_url'] . '/icons/quick_lock.png" alt="" class="centericon" /> ' . $txt['locked_topic'] . '<br />' . ($modSettings['enableStickyTopics'] == '1' ? '
							<img src="' . $settings['images_url'] . '/icons/quick_sticky.png" alt="" class="centericon" /> ' . $txt['sticky_topic'] . '<br />' : '') . '
						</p>
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
		template_pagesection('recent_buttons', 'right', 'go_down');

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
						<ul class="topic_listing" id="unread">
							<li class="topic_sorting_row">
								<h3>
									Sort by: <a href="', $scripturl, '?action=unreadreplies', $context['querystring_board_limits'], ';sort=subject', $context['sort_by'] === 'subject' && $context['sort_direction'] === 'up' ? ';desc' : '', '">', $txt['subject'], $context['sort_by'] === 'subject' ? ' <img class="sort" src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.png" alt="" />' : '', '</a>
											/ <a href="', $scripturl, '?action=unreadreplies', $context['querystring_board_limits'], ';sort=replies', $context['sort_by'] === 'replies' && $context['sort_direction'] === 'up' ? ';desc' : '', '">', $txt['replies'], $context['sort_by'] === 'replies' ? ' <img class="sort" src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.png" alt="" />' : '', '</a>
											 / <a href="', $scripturl, '?action=unreadreplies', $context['querystring_board_limits'], ';sort=last_post', $context['sort_by'] === 'last_post' && $context['sort_direction'] === 'up' ? ';desc' : '', '">', $txt['last_post'], $context['sort_by'] === 'last_post' ? ' <img class="sort" src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.png" alt="" />' : '', '</a>
								</h3>';

		// Show a "select all" box for quick moderation?
		if ($context['showCheckboxes'])
			echo '
								<p>
									<input type="checkbox" onclick="invertAll(this, this.form, \'topics[]\');" class="input_check" />
								</p>';

		echo '
							</li>';

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

			// [WIP] MEthinks the orange icons look better if they aren't all over the page.
			echo '
										<a href="', $topic['new_href'], '" id="newicon', $topic['first_post']['id'], '"><span class="new_posts">' . $txt['new'] . '</span></a>
										<h4>
											', $topic['is_sticky'] ? '<strong>' : '', '<span title="', $topic[(!empty($settings['message_index_preview']) && $settings['message_index_preview_first'] == 2 ? 'last_post' : 'first_post')]['preview'], '"><span id="msg_' . $topic['first_post']['id'] . '">', $topic['first_post']['link'], '</span></span>', $topic['is_sticky'] ? '</strong>' : '', '
										</h4>
									</div>
									<p class="topic_starter">
										', $topic['first_post']['started_by'], '
										<span class="small_pagelinks" id="pages' . $topic['first_post']['id'] . '">', $topic['pages'], '</span>
									</p>
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

		template_pagesection('recent_buttons', 'right');

		if ($context['showCheckboxes'])
			echo '
					</form>';
	}
	else
		echo '
					<h2 class="category_header">
						', $context['showing_all_topics'] ? $txt['msg_alert_none'] : $txt['unread_topics_visit_none'], '
					</h2>';

	echo '
					<div id="topic_icons" class="description">
						<p class="floatleft">', !empty($modSettings['enableParticipation']) && $context['user']['is_logged'] ? '
							<img src="' . $settings['images_url'] . '/icons/profile_sm.png" alt="" class="centericon" /> ' . $txt['participation_caption'] : '<img src="' . $settings['images_url'] . '/post/xx.png" alt="" class="centericon" /> ' . $txt['normal_topic'], '<br />
							'. ($modSettings['pollMode'] == '1' ? '<img src="' . $settings['images_url'] . '/topic/normal_poll.png" alt="" class="centericon" /> ' . $txt['poll'] : '') . '
						</p>
						<p>
							<img src="' . $settings['images_url'] . '/icons/quick_lock.png" alt="" class="centericon" /> ' . $txt['locked_topic'] . '<br />' . ($modSettings['enableStickyTopics'] == '1' ? '
							<img src="' . $settings['images_url'] . '/icons/quick_sticky.png" alt="" class="centericon" /> ' . $txt['sticky_topic'] . '<br />' : '') . '
						</p>
					</div>';
}