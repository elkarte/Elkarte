<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

/**
 * Builds the poster area, avatar, group icons, pulldown information menu, etc
 *
 * @param mixed[] $message
 * @param boolean $ignoring
 *
 * @return string
 */
function template_build_poster_div($message, $ignoring = false)
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	$poster_div = '';

	// Show information about the poster of this message.
	$poster_div .= '
							<li class="' . ($ignoring ? 'subsections"' : 'listlevel1 subsections"') . ' aria-haspopup="true">';

	// Show a link to the member's profile.
	if (!empty($message['member']['id']))
		$poster_div .= '
								<a class="linklevel1 name" href="' . $message['member']['href'] . '">
									' . $message['member']['name'] . '
								</a>';
	else
		$poster_div .= '
								<a class="linklevel1 name">
									' . $message['member']['name'] . '
								</a>';

	// The member info dropdown starts here.
	$poster_div .= '
								<ul id="msg_' . $message['id'] . '_extra_info" class="menulevel2' . ($ignoring ? ' hide"' : '" aria-haspopup="true"') . '>';

	// Don't show these things for guests.
	if (!$message['member']['is_guest'])
	{
		// Show the post group if and only if they have no other group or the option is on, and they are in a post group.
		if ((empty($settings['hide_post_group']) || $message['member']['group'] == '') && $message['member']['post_group'] != '')
			$poster_div .= '
									<li class="listlevel2 postgroup">' . $message['member']['post_group'] . '</li>';

		// Show how many posts they have made.
		if (!isset($context['disabled_fields']['posts']))
			$poster_div .= '
									<li class="listlevel2 postcount">' . $txt['member_postcount'] . ': ' . $message['member']['posts'] . '</li>';

		if (!isset($context['disabled_fields']['date_registered']))
			$poster_div .= '
									<li class="listlevel2 registered">' . $txt['date_joined'] . ': ' . standardTime($message['member']['registered_raw'], $txt['date_joined_format']) . '</li>';

		// Is karma display enabled?  Total or +/-?
		if ($modSettings['karmaMode'] == '1')
			$poster_div .= '
									<li class="listlevel2 karma">' . $modSettings['karmaLabel'] . ' ' . ($message['member']['karma']['good'] - $message['member']['karma']['bad']) . '</li>';
		elseif ($modSettings['karmaMode'] == '2')
			$poster_div .= '
									<li class="listlevel2 karma">' . $modSettings['karmaLabel'] . ' +' . $message['member']['karma']['good'] . '/-' . $message['member']['karma']['bad'] . '</li>';

		// Is this user allowed to modify this member's karma?
		if ($message['member']['karma']['allow'])
			$poster_div .= '
									<li class="listlevel2 karma_allow">
										<a class="linklevel2" href="' . $message['member']['karma']['applaud_url'] . '">' . $modSettings['karmaApplaudLabel'] . '</a>' .
										(empty($modSettings['karmaDisableSmite']) ? '<a class="linklevel2" href="' . $message['member']['karma']['smite_url'] . '">' . $modSettings['karmaSmiteLabel'] . '</a>' : '') . '
									</li>';

		// Any custom fields to show as or above icons?
		if (!empty($message['member']['custom_fields']))
		{
			// Show above-icon placement (replacing personal text and gender)
			foreach ($message['member']['custom_fields'] as $custom)
			{
				if ($custom['placement'] == 3 && !empty($custom['value']))
				{
					$poster_div .= '
									<li class="listlevel2 cf_aboveicons">' . $custom['value'] . '</li>';
				}
			}

			// Icon placement.
			$shown = false;
			foreach ($message['member']['custom_fields'] as $custom)
			{
				if ($custom['placement'] != 1 || empty($custom['value']))
					continue;

				if (empty($shown))
				{
					$shown = true;
					$poster_div .= '
									<li class="listlevel2 cf_icons">
										<ol>';
				}

				$poster_div .= '
											<li class="cf_icon">' . $custom['value'] . '</li>';
			}

			if ($shown)
				$poster_div .= '
										</ol>
									</li>';
		}

		// Show the website and email address buttons.
		if ($message['member']['show_profile_buttons'])
		{
			$poster_div .= '
									<li class="listlevel2 profile">
										<ol>';

			// Don't show an icon if they haven't specified a website.
			if ($message['member']['website']['url'] != '' && !isset($context['disabled_fields']['website']))
				$poster_div .= '
											<li class="cf_icon"><a href="' . $message['member']['website']['url'] . '" title="' . $message['member']['website']['title'] . '" target="_blank" class="new_win">' . ($settings['use_image_buttons'] ? '<i class="icon i-website" title="' . $message['member']['website']['title'] . '"></i>' : $txt['www']) . '</a></li>';

			// Don't show the email address if they want it hidden.
			if ($context['can_send_email'])
				$poster_div .= '
											<li>' . template_msg_email($message['id'], $message['member']) . '</li>';

			$poster_div .= '
										</ol>
									</li>';
		}

		// Any custom fields for standard placement?
		if (!empty($message['member']['custom_fields']))
		{
			foreach ($message['member']['custom_fields'] as $custom)
			{
				if (empty($custom['placement']) || empty($custom['value']))
					$poster_div .= '
									<li class="listlevel2 custom">' . $custom['title'] . ': ' . $custom['value'] . '</li>';
			}
		}
	}
	// Otherwise, show the guest's email.
	elseif (!empty($message['member']['email']) && $context['can_send_email'])
		$poster_div .= '
									<li class="listlevel2 email">' . template_msg_email($message['id']) . '</li>';

	// Stuff for the staff to wallop them with.
	$poster_div .= '
									<li class="listlevel2 report_seperator"></li>';

	// Can we issue a warning because of this post?  Remember, we can't give guests warnings.
	if ($context['can_issue_warning'] && !$message['is_message_author'] && !$message['member']['is_guest'])
	{
		$poster_div .= '
									<li class="listlevel2 warning">
										<a class="linklevel2" href="' . $scripturl . '?action=profile;area=issuewarning;u=' . $message['member']['id'] . ';msg=' . $message['id'] . '"><i class="warnicon i-warning" title="' . $txt['issue_warning_post'] . '"></i>' . $txt['warning_issue'] . '</a>';

		// Do they have a warning in place?
		if ($message['member']['can_see_warning'] && !empty($options['hide_poster_area']))
			$poster_div .= '
										<a class="linklevel2" href="' . $scripturl . '?action=profile;area=issuewarning;u=' . $message['member']['id'] . '"><i class="warnicon i-warning-' . $message['member']['warning_status'] . '" title="' . $txt['user_warn_' . $message['member']['warning_status']] . '"></i><span class="warn_' . $message['member']['warning_status'] . '">' . $txt['warn_' . $message['member']['warning_status']] . '</span></a>';

		$poster_div .= '
									</li>';
	}

	// Show the IP to this user for this post - because you can moderate?
	if (!empty($context['can_moderate_forum']) && !empty($message['member']['ip']))
		$poster_div .= '
									<li class="listlevel2 poster_ip">
										<a class="linklevel2 help" title="' . $message['member']['ip'] . '" href="' . $scripturl . '?action=' . (!empty($message['member']['is_guest']) ? 'trackip' : 'profile;area=history;sa=ip;u=' . $message['member']['id'] . ';searchip=' . $message['member']['ip']) . '">' . $message['member']['ip'] . '</a>
										<a class="helpicon i-help" href="' . $scripturl . '?action=quickhelp;help=see_admin_ip" onclick="return reqOverlayDiv(this.href);"></a>
									</li>';
	// Or, should we show it because this is you?
	elseif ($message['can_see_ip'] && !empty($message['member']['ip']))
		$poster_div .= '
									<li class="listlevel2 poster_ip">
										<a class="linklevel2 help" title="' . $message['member']['ip'] . '" href="#" onclick="return false;">' . $message['member']['ip'] . '</a>
										<a class="linklevel2 helpicon i-help"  title="' . $message['member']['ip'] . '" href="' . $scripturl . '?action=quickhelp;help=see_member_ip" onclick="return reqOverlayDiv(this.href);"><s>' . $txt['help'] . '</s></a>
									</li>';
	// Okay, are you at least logged in?  Then we can show something about why IPs are logged...
	elseif (!$context['user']['is_guest'])
		$poster_div .= '
									<li class="listlevel2 poster_ip">
										<a class="linklevel2 helpicon i-help" href="' . $scripturl . '?action=quickhelp;help=see_member_ip" onclick="return reqOverlayDiv(this.href);"><s>' . $txt['help'] . '</s></a>' . $txt['logged'] . '
									</li>';
	// Otherwise, you see NOTHING!
	else
		$poster_div .= '
									<li class="listlevel2 poster_ip">' . $txt['logged'] . '</li>';

	// Done with the detail information about the poster.
	$poster_div .= '
								</ul>
							</li>';

	// Show avatars, images, etc.?
	if (empty($options['hide_poster_area']) && !$ignoring)
	{
		if (!empty($settings['show_user_images']) && empty($options['show_no_avatars']) && !empty($message['member']['avatar']['image']))
			$poster_div .= '
							<li class="listlevel1 poster_avatar">
								<a class="linklevel1" href="' . $message['member']['href'] . '">
									' . $message['member']['avatar']['image'] . '
								</a>
							</li>';

		// Show the post group icons, but not for guests.
		if (!$message['member']['is_guest'])
			$poster_div .= '
							<li class="listlevel1 icons">' . $message['member']['group_icons'] . '</li>';

		// Show the member's primary group (like 'Administrator') if they have one.
		if (!empty($message['member']['group']))
			$poster_div .= '
							<li class="listlevel1 membergroup">' . $message['member']['group'] . '</li>';

		// Show the member's custom title, if they have one.
		if (!empty($message['member']['title']))
			$poster_div .= '
							<li class="listlevel1 title">' . $message['member']['title'] . '</li>';

		// Show online and offline buttons? PHP could do with a little bit of cleaning up here for brevity, but it works.
		// The plan is to make these buttons act sensibly, and link to your own inbox in your own posts (with new PM notification).
		// Still has a little bit of hard-coded text. This may be a place where translators should be able to write inclusive strings,
		// instead of dealing with $txt['by'] etc in the markup. Must be brief to work, anyway. Cannot ramble on at all.

		// we start with their own..
		if ($context['can_send_pm'] && $message['is_message_author'])
		{
			$poster_div .= '
							<li class="listlevel1 poster_online"><a class="linklevel1' . ($context['user']['unread_messages'] > 0 ? ' new_pm' : '') . '" href="' . $scripturl . '?action=pm">' . $txt['pm_short'] . ' ' . ($context['user']['unread_messages'] > 0 ? '<span class="pm_indicator">' . $context['user']['unread_messages'] . '</span>' : '') . '</a></li>';
		}
		// Allowed to send PMs and the message is not their own and not from a guest.
		elseif ($context['can_send_pm'] && !$message['is_message_author'] && !$message['member']['is_guest'])
		{
			if (!empty($modSettings['onlineEnable']))
				$poster_div .= '
							<li class="listlevel1 poster_online"><a class="linklevel1" href="' . $scripturl . '?action=pm;sa=send;u=' . $message['member']['id'] . '" title="' . $message['member']['online']['member_online_text'] . '">' . $txt['send_message'] . ' ' . template_member_online($message['member'], false) . '</a></li>';
			else
				$poster_div .= '
							<li class="listlevel1 poster_online"><a class="linklevel1" href="' . $scripturl . '?action=pm;sa=send;u=' . $message['member']['id'] . '">' . $txt['send_message'] . ' </a></li>';
		}
		// Not allowed to send a PM, online status disabled and not from a guest.
		elseif (!$context['can_send_pm'] && !empty($modSettings['onlineEnable']) && !$message['member']['is_guest'])

			// Are we showing the warning status?
			if (!$message['member']['is_guest'] && $message['member']['can_see_warning'])
				$poster_div .= '
							<li class="listlevel1 warning">' . ($context['can_issue_warning'] ? '<a class="linklevel1" href="' . $scripturl . '?action=profile;area=issuewarning;u=' . $message['member']['id'] . '">' : '') . '<i class="warnicon i-warning-' . $message['member']['warning_status'] . '.png" title="' . $txt['user_warn_' . $message['member']['warning_status']] . '"></i>' . ($context['can_issue_warning'] ? '</a>' : '') . '<span class="warn_' . $message['member']['warning_status'] . '">' . $txt['warn_' . $message['member']['warning_status']] . '</span></li>';
	}

	return $poster_div;
}

/**
 * Formats a very simple message view (for example search results, list of
 * posts and topics in profile, unapproved, etc.)
 *
 * @param mixed[] $msg associative array contaning the data to output:
 * - class => a class name (mandatory)
 * - counter => Usually a number used as counter next to the subject
 * - title => Usually the subject of the topic (mandatory)
 * - date => frequently the "posted on", but can be anything
 * - body => message body (mandatory)
 * - buttons => an associative array that allows create a "quickbutton" strip
 *  (see template_quickbutton_strip for details on the parameters)
 */
function template_simple_message($msg)
{
	// @todo find a better name for $msg['date']
	echo '
			<article class="', $msg['class'], ' forumposts">', !empty($msg['counter']) ? '
				<div class="counter">' . $msg['counter'] . '</div>' : '', '
				<header class="topic_details">
					<h5>
						', $msg['title'], '
					</h5>', !empty($msg['date']) ? '
					<span class="smalltext">' . $msg['date'] . '</span>' : '', '
				</header>
				<section class="inner">
					', $msg['body'], '
				</section>';

	if (!empty($msg['buttons']))
		template_quickbutton_strip($msg['buttons'], !empty($msg['tests']) ? $msg['tests'] : array());

	echo '
			</article>';
}
