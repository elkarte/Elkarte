<?php
/**
 * @name      Elkarte Forum
 * @copyright Elkarte Forum contributors
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

function template_main()
{
	global $context, $settings, $options, $scripturl, $modSettings, $txt, $board_info;

	if (!empty($context['boards']) && (!empty($options['show_children']) || $context['start'] == 0))
	{
		echo '
			<ul data-role="listview" data-inset="true">
				<li data-role="list-divider">', $txt['parent_boards'], '</li>';
				
		foreach ($context['boards'] as $board)
		{
			echo '
				<li>
					<a href="', ($board['is_redirect'] || $context['user']['is_guest'] ? $board['href'] : $scripturl . '?board=' . $board['id'] . '.0'), '">';

			// If the board or children is new, show an indicator.
			if ($board['new'] || $board['children_new'])
				echo '
							<img src="', $settings['images_url'], '/' .$context['theme_variant_url'], 'on', $board['new'] ? '' : '2', '.png" alt="', $txt['new_posts'], '" title="', $txt['new_posts'], '" />';
			// Is it a redirection board?
			elseif ($board['is_redirect'])
				echo '
							<img src="', $settings['images_url'], '/' .$context['theme_variant_url'], 'redirect.png" alt="*" title="*" />';
			// No new posts at all! The agony!!
			else
				echo '
							<img src="', $settings['images_url'], '/' .$context['theme_variant_url'], 'off.png" alt="', $txt['old_posts'], '" title="', $txt['old_posts'], '" />';

			echo '
						<h3>', $board['name'], '</h3>
						<p>', $board['description'] , '</p>
					</a>
				</li>';


			// Show the "Child Boards: ". (there's a link_children but we're going to bold the new ones...)
			if (!empty($board['children']))
			{
				// Sort the links into an array with new boards bold so it can be imploded.
				$children = array();
				/* Each child in each board's children has:
						id, name, description, new (is it new?), topics (#), posts (#), href, link, and last_post. */
				foreach ($board['children'] as $child)
				{
					echo '
					<li class="child">
						<a href="', $child['href'] , '">';
					if ($child['new'] || !empty($child['children_new']))
						echo '
							<img src="', $settings['images_url'], '/' .$context['theme_variant'], '/new_some.png" alt="', $txt['new_posts'], '" title="', $txt	['new_posts'], '" border="0" />';
					// Is it a redirection board?
					elseif ($child['is_redirect'])
						echo '
							<img src="', $settings['images_url'], '/' .$context['theme_variant'], '/new_redirect.png" alt="*" title="*" border="0" />';						
					else
						echo '
							<img src="', $settings['images_url'], '/' .$context['theme_variant'], '/new_none.png" alt="*" title="', $txt['old_posts'], '" border="0" alt="', $txt['old_posts'], '"/>';

					echo '
							<h3>', $child['name'], '</h3>
						</a>
					</li>';
				}
					
			}
		}
		
		echo '
		</ul>';
	}

	// Build the message index button array.
	$context['normal_buttons'] = array(
		'new_topic' => array('test' => allowedTo('can_post_new'), 'text' => 'new_topic', 'image' => 'new_topic.png', 'lang' => true, 'url' => $scripturl . '?action=post;board=' . $context['current_board'] . '.0', 'active' => true),
		'post_poll' => array('test' => allowedTo('can_post_poll'), 'text' => 'new_poll', 'image' => 'new_poll.png', 'lang' => true, 'url' => $scripturl . '?action=post;board=' . $context['current_board'] . '.0;poll'),
		'notify' => array('test' => allowedTo('can_mark_notify'), 'text' => $context['is_marked_notify'] ? 'unnotify' : 'notify', 'image' => ($context['is_marked_notify'] ? 'un' : ''). 'notify.png', 'lang' => true, 'custom' => 'onclick="return confirm(\'' . ($context['is_marked_notify'] ? $txt['notification_disable_board'] : $txt['notification_enable_board']) . '\');"', 'url' => $scripturl . '?action=notifyboard;sa=' . ($context['is_marked_notify'] ? 'off' : 'on') . ';board=' . $context['current_board'] . '.' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id']),
		'markread' => array('test' => $context['user']['is_logged'], 'text' => 'mark_read_short', 'image' => 'markread.png', 'lang' => true, 'url' => $scripturl . '?action=markasread;sa=board;board=' . $context['current_board'] . '.0;' . $context['session_var'] . '=' . $context['session_id']),
	);

	// They can only mark read if they are logged in and it's enabled!
	if (!$context['user']['is_logged'] || !$settings['show_mark_read'])
		unset($context['normal_buttons']['markread']);
	
	if (!$context['no_topic_listing'])
	{
		echo '
		<ul data-role="listview" data-inset="true">
			<li data-role="list-divider" data-theme="b">
				<h4>', $board_info['name'], '</h4>
				<span style="position: absolute; right: 10px; top: 10px">
					<select name="topic_options" id="topic_options" data-icon="gear" data-iconpos="notext" data-select-menu="true" data-native-menu="false">
						<option>', $txt['mobile_post_options'], '</option>';
					foreach ($context['normal_buttons'] as $button => $val)
					{
						if ($val['test'])
							echo '
						<option value="', $val['url'] , '">', $txt[$val['text']], '</option>';
					}				
		echo '
					</select>
				</span>
			</li>';

		// Are there actually any topics to show?
		if (empty($context['topics']))
			echo '
				<li><strong>', $txt['msg_alert_none'], '</strong></li>';

		$stickyShown = false;
		$topicShown = false;
		
		foreach ($context['topics'] as $topic)
		{
			// Is the topic sticky and no title has been shown yet?
			if ($topic['is_sticky'] && !$stickyShown)
			{
				$stickyShown = true;
				echo '
				<li data-role="list-divider">', $txt['sticky_topic'], '</li>';
			}
			elseif (!$topic['is_sticky'] && !$topicShown)
			{
				$topicShown = true;
				echo '
				<li data-role="list-divider">', $txt['topics'], '</li>';
			}
						
			echo '
				<li>
					<a href="', $topic['first_post']['href'], '">
						<img class="ui-li-icon" src="', $settings['images_url'], '/topic/', $topic['class'], '.png" alt="" />
						<h4>', $topic['first_post']['subject'] , '</h4>';

			echo '
						<p>
							', $txt['started_by'], ' ', $topic['first_post']['member']['name'], '<br />
							', $txt['last_post'] ,' ', $txt['by'] ,' ', $topic['last_post']['member']['name'] ,' ', $topic['last_post']['time'], '<br />
						</p>';
			// Is this topic new? (assuming they are logged in!)
			if ($topic['new'] && $context['user']['is_logged'])
				echo '
						<a class="ui-li-aside" href="', $topic['new_href'], '" id="newicon' . $topic['first_post']['id'] . '">' . $txt['new'] . '</a>';
			echo '
					</a>
				</li>';
		}

		echo '
		</ul>
		<script>
			$(document).on("pageinit", function () {
				$("#topic_options").bind("change", function () {
					// Get the value of the selected option
					var url = $(this).val();
					if (url) {
						window.location.href = url;
					}
					return false;
				});
			});
		</script>';
	}

}

?>