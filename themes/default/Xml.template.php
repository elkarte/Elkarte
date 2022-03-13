<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

use ElkArte\Util;

/**
 * Returns the text of a post in response to a quote request for loading into the current editing text box
 */
function template_quotefast()
{
	global $context;

	echo '<?xml version="1.0" encoding="UTF-8"?>
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

	echo '<?xml version="1.0" encoding="UTF-8"?>
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

	echo '<?xml version="1.0" encoding="UTF-8"?>
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
	{
		echo '
		<error in_subject="', $context['message']['error_in_subject'] ? '1' : '0', '" in_body="', cleanXml($context['message']['error_in_body']) ? '1' : '0', '"><![CDATA[', implode('<br />', $context['message']['errors']), ']]></error>';
	}

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

	echo '<?xml version="1.0" encoding="UTF-8"?>
<elk>
	<message id="msg_', $context['message']['id'], '">';
	if (empty($context['message']['errors']))
	{
		echo '
		<modified><![CDATA[', empty($context['message']['modified']['time']) ? '' : cleanXml('&#171; <em>' . sprintf($txt['last_edit_by'], $context['message']['modified']['time'], $context['message']['modified']['name']) . '</em> &#187;'), ']]></modified>';
		if (!empty($context['message']['subject']))
		{
			echo '
		<subject><![CDATA[', cleanXml($context['message']['subject']), ']]></subject>';
		}
	}
	else
	{
		echo '
		<error in_subject="', $context['message']['error_in_subject'] ? '1' : '0', '"><![CDATA[', cleanXml(implode('<br />', $context['message']['errors'])), ']]></error>';
	}
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

	echo '<?xml version="1.0" encoding="UTF-8"?>
<elk>
	<preview>
		<subject><![CDATA[', $context['preview_subject'], ']]></subject>
		<body><![CDATA[', $context['preview_message'], ']]></body>
	</preview>
	<errors serious="', empty($context['errors']['type']) || $context['errors']['type'] != 'serious' ? '0' : '1', '" topic_locked="', $context['locked'] ? '1' : '0', '">';

	if (!empty($context['post_error']['errors']))
	{
		foreach ($context['post_error']['errors'] as $key => $message)
		{
			echo '
		<error code="', cleanXml($key), '"><![CDATA[', cleanXml($message), ']]></error>';
		}
	}

	echo '
		<caption name="guestname" class="', isset($context['post_error']['long_name']) || isset($context['post_error']['no_name']) || isset($context['post_error']['bad_name']) ? 'error' : '', '" />
		<caption name="email" class="', isset($context['post_error']['no_email']) || isset($context['post_error']['bad_email']) ? 'error' : '', '" />
		<caption name="evtitle" class="', isset($context['post_error']['no_event']) ? 'error' : '', '" />
		<caption name="subject" class="', isset($context['post_error']['no_subject']) ? 'error' : '', '" />
		<caption name="question" class="', isset($context['post_error']['no_question']) ? 'error' : '', '" />
	</errors>
	<last_msg>', $context['topic_last_message'] ?? '0', '</last_msg>';

	if (!empty($context['previous_posts']))
	{
		echo '
	<new_posts>';
		foreach ($context['previous_posts'] as $post)
		{
			echo '
		<post id="', $post['id'], '">
			<time><![CDATA[', $post['time'], ']]></time>
			<poster><![CDATA[', cleanXml($post['poster']), ']]></poster>
			<message><![CDATA[', cleanXml($post['body']), ']]></message>
			<is_ignored>', $post['is_ignored'] ? '1' : '0', '</is_ignored>
		</post>';
		}
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

	echo '<?xml version="1.0" encoding="UTF-8"?>
<elk>
	<preview>
		<subject><![CDATA[', empty($context['preview_subject']) ? $txt['not_applicable'] : $context['preview_subject'], ']]></subject>
		<body><![CDATA[', $context['preview_message'], ']]></body>
	</preview>
	<errors serious="', empty($context['error_type']) || $context['error_type'] != 'serious' ? '0' : '1', '">';

	if (!empty($context['post_error']['errors']))
	{
		foreach ($context['post_error']['errors'] as $key => $message)
		{
			echo '
		<error code="', cleanXml($key), '"><![CDATA[', cleanXml($message), ']]></error>';
		}
	}

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

	echo '<?xml version="1.0" encoding="UTF-8"?>
<elk>';
	foreach ($context['yearly'] as $year)
	{
		foreach ($year['months'] as $month)
		{
			echo '
	<month id="', $month['date']['year'], $month['date']['month'], '">';
			foreach ($month['days'] as $day)
			{
				echo '
		<day date="', $day['year'], '-', $day['month'], '-', $day['day'], '" new_topics="', $day['new_topics'], '" new_posts="', $day['new_posts'], '" new_members="', $day['new_members'], '" most_members_online="', $day['most_members_online'], '"', empty($modSettings['hitStats']) ? '' : ' hits="' . $day['hits'] . '"', ' />';
			}
			echo '
	</month>';
		}
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

	echo '<?xml version="1.0" encoding="UTF-8"?>
<elk>
	<pageIndex section="not_selected" startFrom="', $context['not_selected']['start'], '"><![CDATA[', $context['not_selected']['page_index'], ']]></pageIndex>
	<pageIndex section="selected" startFrom="', $context['selected']['start'], '"><![CDATA[', $context['selected']['page_index'], ']]></pageIndex>';
	foreach ($context['changes'] as $change)
	{
		if ($change['type'] == 'remove')
		{
			echo '
	<change id="', $change['id'], '" curAction="remove" section="', $change['section'], '" />';
		}
		else
		{
			echo '
	<change id="', $change['id'], '" curAction="insert" section="', $change['section'], '">
		<subject><![CDATA[', cleanXml($change['insert_value']['subject']), ']]></subject>
		<time><![CDATA[', cleanXml($change['insert_value']['time']), ']]></time>
		<body><![CDATA[', cleanXml($change['insert_value']['body']), ']]></body>
		<poster><![CDATA[', cleanXml($change['insert_value']['poster']), ']]></poster>
	</change>';
		}
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
	echo '<?xml version="1.0" encoding="UTF-8"?>
<elk>';

	if (empty($context['topics']))
	{
		echo '
		<noresults>', $txt['search_no_results'], '</noresults>';
	}
	else
	{
		echo '
		<results>';

		while (($topic = $context['get_topics']()))
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

	echo '<?xml version="1.0" encoding="UTF-8"?>
<elk>';

	foreach ($context['categories'] as $category)
	{
		echo '
	<item type="category" id="', $category['id'], '"><![CDATA[', cleanXml($category['name']), ']]></item>';
		foreach ($category['boards'] as $board)
		{
			echo '
	<item type="board" id="', $board['id'], '" childlevel="', $board['child_level'], '"><![CDATA[', cleanXml($board['name']), ']]></item>';
		}
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

	echo '<?xml version="1.0" encoding="UTF-8"?>
<elk>';

	foreach ($context['icons'] as $icon)
	{
		echo '
	<icon value="', $icon['value'], '" url="', $icon['url'], '"><![CDATA[', cleanXml($icon['name']), ']]></icon>';
	}

	echo '
</elk>';
}

/**
 * Returns if the username is valid or not, used during registration
 */
function template_check_username()
{
	global $context;

	echo '<?xml version="1.0" encoding="UTF-8"?>
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

	echo '<?xml version="1.0" encoding="UTF-8"?>
<elk>
	<', $tag, '>';

	foreach ($context['xml_data'] as $key => $val)
	{
		if ($key != 'error')
		{
			echo '
			<', $key, '><![CDATA[', cleanXml($val), ']]></', $key, '>';
		}
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

	echo '<?xml version="1.0" encoding="UTF-8"?>';

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
		{
			template_generic_xml_recursive($data['children'], $key, $data['identifier'], $level);
		}
		// An item...
		elseif (is_array($data) && isset($data['value']))
		{
			echo "\n", str_repeat("\t", $level), '<', $child_ident;

			if (!empty($data['attributes']))
			{
				foreach ($data['attributes'] as $k => $v)
				{
					echo ' ' . $k . '="' . $v . '"';
				}
			}

			echo '><![CDATA[', cleanXml($data['value']), ']]></', $child_ident, '>';
		}
	}

	echo "\n", str_repeat("\t", $level), '</', $parent_ident, '>';
}

/**
 * Formats data retrieved in other functions into xml format.
 * Additionally formats data based on the specific format passed.
 * This function is recursively called to handle sub arrays of data.
 *
 * @param mixed[] $data the array to output as xml data
 * @param int $i the amount of indentation to use.
 * @param string|null $tag if specified, it will be used instead of the keys of data.
 * @param string $xml_format one of rss, rss2, rdf, atom
 */
function template_xml_news($data, $i, $tag = null, $xml_format = 'rss')
{
	require_once(SUBSDIR . '/News.subs.php');

	// For every array in the data...
	foreach ($data as $key => $val)
	{
		// Skip it, it's been set to null.
		if ($val === null)
		{
			continue;
		}

		// If a tag was passed, use it instead of the key.
		$key = $tag ?? $key;

		// First let's indent!
		echo "\n", str_repeat("\t", $i);

		// Grr, I hate kludges... almost worth doing it properly, here, but not quite.
		if ($xml_format == 'atom' && $key == 'link')
		{
			echo '<link rel="alternate" type="text/html" href="', fix_possible_url($val), '" />';
			continue;
		}

		// If it's empty/0/nothing simply output an empty tag.
		if ($val == '')
		{
			echo '<', $key, ' />';
		}
		elseif ($xml_format == 'atom' && $key == 'category')
		{
			echo '<', $key, ' term="', $val, '" />';
		}
		else
		{
			// Beginning tag.
			if ($xml_format == 'rdf' && $key == 'item' && isset($val['link']))
			{
				echo '<', $key, ' rdf:about="', fix_possible_url($val['link']), '">';
				echo "\n", str_repeat("\t", $i + 1);
				echo '<dc:format>text/html</dc:format>';
			}
			elseif ($xml_format == 'atom' && $key == 'summary')
			{
				echo '<', $key, ' type="html">';
			}
			else
			{
				echo '<', $key, '>';
			}

			if (is_array($val))
			{
				// An array.  Dump it, and then indent the tag.
				template_xml_news($val, $i + 1, null, $xml_format);
				echo "\n", str_repeat("\t", $i), '</', $key, '>';
			}
			// A string with returns in it.... show this as a multiline element.
			elseif (strpos($val, "\n") !== false || strpos($val, '<br />') !== false)
			{
				echo "\n", fix_possible_url($val), "\n", str_repeat("\t", $i), '</', $key, '>';
			}
			// A simple string.
			else
			{
				echo fix_possible_url($val), '</', $key, '>';
			}
		}
	}
}

/**
 * Main Atom feed template
 */
function template_rdf()
{
	global $context, $scripturl, $txt;

	echo '<?xml version="1.0" encoding="UTF-8"?' . '>
	<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns="http://purl.org/rss/1.0/">
		<channel rdf:about="', $scripturl, '">
			<title>', $context['feed_title'], '</title>
			<link>', $scripturl, '</link>
			<description><![CDATA[', strip_tags($txt['xml_rss_desc']), ']]></description>
			<items>
				<rdf:Seq>';

	foreach ($context['recent_posts_data'] as $item)
	{
		echo '
					<rdf:li rdf:resource="', $item['link'], '" />';
	}

	echo '
				</rdf:Seq>
			</items>
		</channel>
	';

	template_xml_news($context['recent_posts_data'], 1, 'item', $context['xml_format']);

	echo '
	</rdf:RDF>';
}

/**
 * Main Atom feed template
 */
function template_feedatom()
{
	global $context, $scripturl, $txt;

	echo '<?xml version="1.0" encoding="UTF-8"?' . '>
	<feed xmlns="http://www.w3.org/2005/Atom">
		<title>', $context['feed_title'], '</title>
		<link rel="alternate" type="text/html" href="', $scripturl, '" />
		<link rel="self" type="application/rss+xml" href="', $scripturl, '?type=atom;action=.xml', $context['url_parts'], '" />
		<id>', $scripturl, '</id>
		<icon>', $context['favicon'] . '</icon>
		<logo>', $context['header_logo_url_html_safe'], '</logo>

		<updated>', Util::gmstrftime('%Y-%m-%dT%H:%M:%SZ'), '</updated>
		<subtitle><![CDATA[', strip_tags(un_htmlspecialchars($txt['xml_rss_desc'])), ']]></subtitle>
		<generator uri="https://www.elkarte.net" version="', strtr(FORUM_VERSION, array('ElkArte' => '')), '">ElkArte</generator>
		<author>
			<name>', strip_tags(un_htmlspecialchars($context['forum_name'])), '</name>
		</author>';

	template_xml_news($context['recent_posts_data'], 2, 'entry', $context['xml_format']);

	echo '
	</feed>';
}

/**
 * Main RSS feed template (0.92 and 2.0)
 */
function template_feedrss()
{
	global $context, $scripturl, $txt;

	echo '<?xml version="1.0" encoding="UTF-8"?' . '>
	<rss version=', $context['xml_format'] == 'rss2' ? '"2.0" xmlns:dc="http://purl.org/dc/elements/1.1/"' : '"0.92"', ' xml:lang="', strtr($txt['lang_locale'], '_', '-'), '">
		<channel>
			<title>', $context['feed_title'], '</title>
			<link>', $scripturl, '</link>
			<description><![CDATA[', un_htmlspecialchars(strip_tags($txt['xml_rss_desc'])), ']]></description>
			<generator>ElkArte</generator>
			<ttl>30</ttl>
			<image>
				<url>', $context['header_logo_url_html_safe'], '</url>
				<title>', $context['feed_title'], '</title>
				<link>', $scripturl, '</link>
			</image>';

	// Output all of the associative array, start indenting with 2 tabs, and name everything "item".
	template_xml_news($context['recent_posts_data'], 2, 'item', $context['xml_format']);

	// Output the footer of the xml.
	echo '
		</channel>
	</rss>';
}

/**
 * Returns xml response to a draft autosave request
 * provides the id of the draft saved and the time it was saved in the response
 */
function template_xml_draft()
{
	global $context, $txt;

	echo '<?xml version="1.0" encoding="UTF-8"?>
<drafts>
	<draft id="', $context['id_draft'], '"><![CDATA[', $txt['draft_saved_on'], ': ', standardTime($context['draft_saved_on']), ']]></draft>
</drafts>';
}
