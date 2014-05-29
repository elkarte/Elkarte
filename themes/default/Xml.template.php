<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (elk)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Release Candidate 1
 *
 */

/**
 * @deprecated since 1.0
 */
function template_sendbody()
{
	global $context;

	echo '<', '?xml version="1.0" encoding="UTF-8"?', '>
<elk>
	<message view="', $context['view'], '">', cleanXml($context['message']), '</message>
</elk>';
}

/**
 * Returns the text of a post in response to a quote request for loading into the current editing text box
 */
function template_quotefast()
{
	global $context;

	echo '<', '?xml version="1.0" encoding="UTF-8"?', '>
<elk>
	<quote>', cleanXml($context['quote']['xml']), '</quote>
</elk>';
}

/**
 * Returns a message text and subject for use in the quick modify screen
 */
function template_modifyfast()
{
	global $context;

	echo '<', '?xml version="1.0" encoding="UTF-8"?', '>
<elk>
	<subject><![CDATA[', cleanXml($context['message']['subject']), ']]></subject>
	<message id="msg_', $context['message']['id'], '"><![CDATA[', cleanXml($context['message']['body']), ']]></message>
</elk>';
}

/**
 * Returns updated message details so the topic display can be updated after a quick edit is completed
 */
function template_modifydone()
{
	global $context, $txt;

	echo '<', '?xml version="1.0" encoding="UTF-8"?', '>
<elk>
	<message id="msg_', $context['message']['id'], '">';
	if (empty($context['message']['errors']))
	{
		echo '
		<modified><![CDATA[', empty($context['message']['modified']['time']) ? '' : cleanXml(sprintf($txt['last_edit_by'], $context['message']['modified']['time'], $context['message']['modified']['name'])), ']]></modified>
		<subject is_first="', $context['message']['first_in_topic'] ? '1' : '0', '"><![CDATA[', cleanXml($context['message']['subject']), ']]></subject>
		<body><![CDATA[', $context['message']['body'], ']]></body>';
	}
	else
		echo '
		<error in_subject="', $context['message']['error_in_subject'] ? '1' : '0', '" in_body="', cleanXml($context['message']['error_in_body']) ? '1' : '0', '"><![CDATA[', implode('<br />', $context['message']['errors']), ']]></error>';

	echo '
	</message>
</elk>';
}

/**
 * When done modifying a topic title, updates the board listing
 */
function template_modifytopicdone()
{
	global $context, $txt;

	echo '<', '?xml version="1.0" encoding="UTF-8"?', '>
<elk>
	<message id="msg_', $context['message']['id'], '">';
	if (empty($context['message']['errors']))
	{
		echo '
		<modified><![CDATA[', empty($context['message']['modified']['time']) ? '' : cleanXml('&#171; <em>' . sprintf($txt['last_edit_by'], $context['message']['modified']['time'], $context['message']['modified']['name']) . '</em> &#187;'), ']]></modified>';
		if (!empty($context['message']['subject']))
			echo '
		<subject><![CDATA[', cleanXml($context['message']['subject']), ']]></subject>';
	}
	else
		echo '
		<error in_subject="', $context['message']['error_in_subject'] ? '1' : '0', '"><![CDATA[', cleanXml(implode('<br />', $context['message']['errors'])), ']]></error>';
	echo '
	</message>
</elk>';
}

/**
 * Used to return a post preview
 */
function template_post()
{
	global $context;

	echo '<', '?xml version="1.0" encoding="UTF-8"?', '>
<elk>
	<preview>
		<subject><![CDATA[', $context['preview_subject'], ']]></subject>
		<body><![CDATA[', $context['preview_message'], ']]></body>
	</preview>
	<errors serious="', empty($context['errors']['type']) || $context['errors']['type'] != 'serious' ? '0' : '1', '" topic_locked="', $context['locked'] ? '1' : '0', '">';

	if (!empty($context['post_error']['errors']))
		foreach ($context['post_error']['errors'] as $key => $message)
			echo '
		<error code="', cleanXml($key), '"><![CDATA[', cleanXml($message), ']]></error>';

	echo '
		<caption name="guestname" class="', isset($context['post_error']['long_name']) || isset($context['post_error']['no_name']) || isset($context['post_error']['bad_name']) ? 'error' : '', '" />
		<caption name="email" class="', isset($context['post_error']['no_email']) || isset($context['post_error']['bad_email']) ? 'error' : '', '" />
		<caption name="evtitle" class="', isset($context['post_error']['no_event']) ? 'error' : '', '" />
		<caption name="subject" class="', isset($context['post_error']['no_subject']) ? 'error' : '', '" />
		<caption name="question" class="', isset($context['post_error']['no_question']) ? 'error' : '', '" />
	</errors>
	<last_msg>', isset($context['topic_last_message']) ? $context['topic_last_message'] : '0', '</last_msg>';

	if (!empty($context['previous_posts']))
	{
		echo '
	<new_posts>';
		foreach ($context['previous_posts'] as $post)
			echo '
		<post id="', $post['id'], '">
			<time><![CDATA[', $post['time'], ']]></time>
			<poster><![CDATA[', cleanXml($post['poster']), ']]></poster>
			<message><![CDATA[', cleanXml($post['body']), ']]></message>
			<is_ignored>', $post['is_ignored'] ? '1' : '0', '</is_ignored>
		</post>';
		echo '
	</new_posts>';
	}

	echo '
</elk>';
}

/**
 * Returns a preview, used by personal messages, newsletters, bounce templates, etc
 */
function template_generic_preview()
{
	global $context, $txt;

	echo '<', '?xml version="1.0" encoding="UTF-8"?', '>
<elk>
	<preview>
		<subject><![CDATA[', empty($context['preview_subject']) ? $txt['not_applicable'] : $context['preview_subject'], ']]></subject>
		<body><![CDATA[', $context['preview_message'], ']]></body>
	</preview>
	<errors serious="', empty($context['error_type']) || $context['error_type'] != 'serious' ? '0' : '1', '">';

	if (!empty($context['post_error']['errors']))
		foreach ($context['post_error']['errors'] as $key => $message)
			echo '
		<error code="', cleanXml($key), '"><![CDATA[', cleanXml($message), ']]></error>';

	// This is the not so generic section, mainly used by PM preview, can be used by others as well
	echo '
		<caption name="to" class="', isset($context['post_error']['no_to']) ? 'error' : '', '" />
		<caption name="bbc" class="', isset($context['post_error']['no_bbc']) ? 'error' : '', '" />
		<caption name="subject" class="', isset($context['post_error']['no_subject']) ? 'error' : '', '" />
		<caption name="question" class="', isset($context['post_error']['no_question']) ? 'error' : '', '" />',
		isset($context['post_error']['no_message']) || isset($context['post_error']['long_message']) ? '<post_error />' : '', '
	</errors>';

	echo '
</elk>';
}

/**
 * Returns additional statistics when a year/month is expanded
 */
function template_stats()
{
	global $context, $modSettings;

	echo '<', '?xml version="1.0" encoding="UTF-8"?', '>
<elk>';
	foreach ($context['yearly'] as $year)
		foreach ($year['months'] as $month)
		{
			echo '
	<month id="', $month['date']['year'], $month['date']['month'], '">';
			foreach ($month['days'] as $day)
				echo '
		<day date="', $day['year'], '-', $day['month'], '-', $day['day'], '" new_topics="', $day['new_topics'], '" new_posts="', $day['new_posts'], '" new_members="', $day['new_members'], '" most_members_online="', $day['most_members_online'], '"', empty($modSettings['hitStats']) ? '' : ' hits="' . $day['hits'] . '"', ' />';
			echo '
	</month>';
		}
	echo '
</elk>';
}

/**
 * Breaking up is not so hard to do
 */
function template_split()
{
	global $context;

	echo '<', '?xml version="1.0" encoding="UTF-8"?', '>
<elk>
	<pageIndex section="not_selected" startFrom="', $context['not_selected']['start'], '"><![CDATA[', $context['not_selected']['page_index'], ']]></pageIndex>
	<pageIndex section="selected" startFrom="', $context['selected']['start'], '"><![CDATA[', $context['selected']['page_index'], ']]></pageIndex>';
	foreach ($context['changes'] as $change)
	{
		if ($change['type'] == 'remove')
			echo '
	<change id="', $change['id'], '" curAction="remove" section="', $change['section'], '" />';
		else
			echo '
	<change id="', $change['id'], '" curAction="insert" section="', $change['section'], '">
		<subject><![CDATA[', cleanXml($change['insert_value']['subject']), ']]></subject>
		<time><![CDATA[', cleanXml($change['insert_value']['time']), ']]></time>
		<body><![CDATA[', cleanXml($change['insert_value']['body']), ']]></body>
		<poster><![CDATA[', cleanXml($change['insert_value']['poster']), ']]></poster>
	</change>';
	}

	echo '
</elk>';
}

/**
 * Return search results
 */
function template_results()
{
	global $context, $txt;
	echo '<', '?xml version="1.0" encoding="UTF-8"?', '>
<elk>';

	if (empty($context['topics']))
		echo '
		<noresults>', $txt['search_no_results'], '</noresults>';
	else
	{
		echo '
		<results>';

		while ($topic = $context['get_topics']())
		{
			echo '
			<result>
				<id>', $topic['id'], '</id>
				<relevance>', $topic['relevance'], '</relevance>
				<board>
					<id>', $topic['board']['id'], '</id>
					<name>', cleanXml($topic['board']['name']), '</name>
					<href>', $topic['board']['href'], '</href>
				</board>
				<category>
					<id>', $topic['category']['id'], '</id>
					<name>', cleanXml($topic['category']['name']), '</name>
					<href>', $topic['category']['href'], '</href>
				</category>
				<messages>';

			foreach ($topic['matches'] as $message)
			{
				echo '
					<message>
						<id>', $message['id'], '</id>
						<subject><![CDATA[', cleanXml($message['subject_highlighted'] != '' ? $message['subject_highlighted'] : $message['subject']), ']]></subject>
						<body><![CDATA[', cleanXml($message['body_highlighted'] != '' ? $message['body_highlighted'] : $message['body']), ']]></body>
						<time>', $message['time'], '</time>
						<timestamp>', $message['timestamp'], '</timestamp>
						<start>', $message['start'], '</start>

						<author>
							<id>', $message['member']['id'], '</id>
							<name>', cleanXml($message['member']['name']), '</name>
							<href>', $message['member']['href'], '</href>
						</author>
					</message>';
			}

			echo '
				</messages>
			</result>';
		}

		echo '
		</results>';
	}

	echo '
</elk>';
}

/**
 * Build the jump to box
 */
function template_jump_to()
{
	global $context;

	echo '<', '?xml version="1.0" encoding="UTF-8"?', '>
<elk>';

	foreach ($context['categories'] as $category)
	{
		echo '
	<item type="category" id="', $category['id'], '"><![CDATA[', cleanXml($category['name']), ']]></item>';
		foreach ($category['boards'] as $board)
			echo '
	<item type="board" id="', $board['id'], '" childlevel="', $board['child_level'], '"><![CDATA[', cleanXml($board['name']), ']]></item>';
	}

	echo '
</elk>';
}

/**
 * Loads the message icons for changing them via the quick edit
 */
function template_message_icons()
{
	global $context;

	echo '<', '?xml version="1.0" encoding="UTF-8"?', '>
<elk>';

	foreach ($context['icons'] as $icon)
		echo '
	<icon value="', $icon['value'], '" url="', $icon['url'], '"><![CDATA[', cleanXml($icon['name']), ']]></icon>';

	echo '
</elk>';
}

/**
 * Returns if the username is valid or not, used during registration
 */
function template_check_username()
{
	global $context;

	echo '<', '?xml version="1.0" encoding="UTF-8"?', '>
<elk>
	<username valid="', $context['valid_username'] ? 1 : 0, '">', cleanXml($context['checked_username']), '</username>
</elk>';
}

/**
 * @todo ... maybe emanuele can explain :D
 */
function template_generic_xml_buttons()
{
	global $context;

	$tag = empty($context['xml_data']['error']) ? 'button' : 'error';

	echo '<', '?xml version="1.0" encoding="UTF-8"?', '>
<elk>
	<', $tag, '>';

	foreach ($context['xml_data'] as $key => $val)
	{
		if ($key != 'error')
			echo '
			<', $key, '><![CDATA[', cleanXml($val), ']]></', $key, '>';
	}

	echo '
	</', $tag, '>
</elk>';
}

/**
 * This prints XML in it's most generic form.
 */
function template_generic_xml()
{
	global $context;

	echo '<', '?xml version="1.0" encoding="UTF-8"?', '>';

	// Show the data.
	template_generic_xml_recursive($context['xml_data'], 'elk', '', -1);
}

/**
 * Recursive function for displaying generic XML data.
 *
 * @param array $xml_data
 * @param string $parent_ident
 * @param string $child_ident
 * @param int $level
 */
function template_generic_xml_recursive($xml_data, $parent_ident, $child_ident, $level)
{
	// This is simply for neat indentation.
	$level++;

	echo "\n" . str_repeat("\t", $level), '<', $parent_ident, '>';

	foreach ($xml_data as $key => $data)
	{
		// A group?
		if (is_array($data) && isset($data['identifier']))
			template_generic_xml_recursive($data['children'], $key, $data['identifier'], $level);
		// An item...
		elseif (is_array($data) && isset($data['value']))
		{
			echo "\n", str_repeat("\t", $level), '<', $child_ident;

			if (!empty($data['attributes']))
				foreach ($data['attributes'] as $k => $v)
					echo ' ' . $k . '="' . $v . '"';

			echo '><![CDATA[', cleanXml($data['value']), ']]></', $child_ident, '>';
		}
	}

	echo "\n", str_repeat("\t", $level), '</', $parent_ident, '>';
}

/**
 * Output before webslices content
 */
function template_webslice_header_above()
{
	global $settings;

	echo '
	<link rel="stylesheet" href="', $settings['default_theme_url'], '/css/wireless.css" />';
}

/**
 * This shows a webslice of the recent posts.
 */
function template_webslice_recent_posts()
{
	global $context, $scripturl, $txt;

	// @todo test if this works
	echo '
	<div style="width: 100%; height: 100%; border: 1px solid black; padding: 0; margin: 0 0 0 0; font: 100.01%/100% Verdana, Helvetica, sans-serif;">
		<div style="background: #080436; color: #ffffff; padding: 4px;">
			', cleanXml($txt['recent_posts']), '
		</div>';

	// Recent posts bit
	$alternate = 0;
	foreach ($context['recent_posts_data'] as $item)
	{
		echo '
		<div style="background: ', $alternate ? '#ECEDF3' : '#F6F6F6', '; font-size: 90%; padding: 2px;">
			<strong><a href="', $item['link'], '">', cleanXml($item['subject']), '</a></strong> ', cleanXml($txt['by']), ' ', cleanXml(!empty($item['poster']['link']) ? '<a href="' . $item['poster']['link'] . '">' . $item['poster']['name'] . '</a>' : $item['poster']['name']), '
		</div>';
		$alternate = !$alternate;
	}

	echo '
	</div>
	<div style="width: 100%; height: 100%; border: 0; padding: 0; margin: 0 0 0 0; font: 100.01%/100% Verdana, Helvetica, sans-serif;">
		<div style="font-size: xx-small;" class="righttext">';

	// Send alerts for the logged in user if they have new PMs
	if ($context['user']['is_guest'])
		echo '
			<a href="', $scripturl, '?action=login">', $txt['login'], '</a>';
	else
		echo '
			', cleanXml($context['user']['name']), cleanXml(!empty($context['can_pm_read']) ? ', ' . (empty($context['user']['messages']) ? $txt['msg_alert_no_messages'] : (($context['user']['messages'] == 1 ? sprintf($txt['msg_alert_one_message'], $scripturl . '?action=pm') : sprintf($txt['msg_alert_many_message'], $scripturl . '?action=pm', $context['user']['messages'])) . ', ' . ($context['user']['unread_messages'] == 1 ? $txt['msg_alert_one_new'] : sprintf($txt['msg_alert_many_new'], $context['user']['unread_messages'])))) : '');
	echo '
		</div>
	</div>';
}

/**
 * Returns an xml response to a draft autosave request
 * provides the id of the draft saved and the time it was saved in the response
 */
function template_xml_draft()
{
	global $context, $txt;

	echo '<', '?xml version="1.0" encoding="UTF-8"?', '>
<drafts>
	<draft id="', $context['id_draft'], '"><![CDATA[', $txt['draft_saved_on'], ': ', standardTime($context['draft_saved_on']), ']]></draft>
</drafts>';
}