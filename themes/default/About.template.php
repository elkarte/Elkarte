<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 Beta 1
 *
 */

/**
 * Display the credits page.
 */
function template_credits()
{
	global $context, $txt;

	// The most important part - the credits :P.
	echo '
<div id="credits">
	<h2 class="category_header">', $txt['credits'], '</h2>';

	foreach ($context['credits'] as $section)
	{
		if (isset($section['pretext']))
		{
			echo '
	<div class="content">
		', $section['pretext'], '
	</div>';
		}

		if (isset($section['title']))
		{
			echo '
	<h2 class="category_header">', $section['title'], '</h2>';
		}

		echo '
	<div class="content">
		<dl>';

		foreach ($section['groups'] as $group)
		{
			if (isset($group['title']))
			{
				echo '
			<dt>
				<strong>', $group['title'], '</strong>
			</dt>
			<dd>';
			}

			// Try to make this read nicely.
			if (count($group['members']) <= 2)
			{
				echo implode(' ' . $txt['credits_and'] . ' ', $group['members']);
			}
			else
			{
				$last_peep = array_pop($group['members']);
				echo implode(', ', $group['members']), ' ', $txt['credits_and'], ' ', $last_peep;
			}

			echo '
			</dd>';
		}

		echo '
		</dl>';

		if (isset($section['posttext']))
		{
			echo '
		<p><em>', $section['posttext'], '</em></p>';
		}

		echo '
	</div>';
	}

	// Other software and graphics
	if (!empty($context['credits_software_graphics']))
	{
		echo '
	<h2 class="category_header">', $txt['credits_software_graphics'], '</h2>
	<div class="content">';

		foreach ($context['credits_software_graphics'] as $section => $credits)
		{
			echo '
		<dl>
			<dt>
				<strong>', $txt['credits_' . $section], '</strong>
			</dt>
			<dd>', implode('</dd><dd>', $credits), '</dd>
		</dl>';
		}

		echo '
	</div>';
	}

	// Addons credits, copyright, license
	if (!empty($context['credits_addons']))
	{
		echo '
	<h2 class="category_header">', $txt['credits_addons'], '</h2>
	<div class="content">';

		echo '
		<dl>
			<dt>
				<strong>', $txt['credits_addons'], '</strong>
			</dt>
			<dd>', implode('</dd><dd>', $context['credits_addons']), '</dd>
		</dl>';

		echo '
	</div>';
	}

	// ElkArte !
	echo '
	<h2 class="category_header">', $txt['credits_copyright'], '</h2>
	<div class="content">
		<dl>
			<dt>
				<strong>', $txt['credits_forum'], '</strong>
			</dt>
			<dd>', $context['copyrights']['elkarte'];

	echo '
			</dd>
		</dl>';

	if (!empty($context['copyrights']['addons']))
	{
		echo '
		<dl>
			<dt>
				<strong>', $txt['credits_addons'], '</strong>
			</dt>
			<dd>', implode('</dd><dd>', $context['copyrights']['addons']), '</dd>
		</dl>';
	}

	echo '
	</div>
</div>';
}

/**
 * An easily printable form for giving permission to access the forum for a minor.
 */
function template_coppa_form()
{
	global $context, $txt;

	// Show the form (As best we can)
	echo '
		<table class="table_grid">
			<tr>
				<td class="lefttext">', $context['forum_contacts'], '</td>
			</tr>
			<tr>
				<td class="righttext">
					<em>', $txt['coppa_form_address'], '</em>: ', $context['ul'], '<br />
					', $context['ul'], '<br />
					', $context['ul'], '<br />
					', $context['ul'], '
				</td>
			</tr>
			<tr>
				<td class="righttext">
					<em>', $txt['coppa_form_date'], '</em>: ', $context['ul'], '
					<br /><br />
				</td>
			</tr>
			<tr>
				<td class="lefttext">
					', $context['coppa_body'], '
				</td>
			</tr>
		</table>';
}


/**
 * Template for giving instructions about COPPA activation.
 */
function template_coppa()
{
	global $context, $txt;

	// Formulate a nice complicated message!
	echo '
		<h2 class="category_header">', $context['page_title'], '</h2>
		<div class="content">
			<p>', $context['coppa']['body'], '</p>
			<p>
				<span>
					<a class="linkbutton new_win" href="', getUrl('action', ['action' => 'about', 'sa' => 'coppa', 'form', 'member' => $context['coppa']['id']]), '" target="_blank">', $txt['coppa_form_link_popup'], '</a> | <a class="linkbutton" href="', getUrl('action', ['action' => 'about', 'sa' => 'coppa', 'form' , 'dl', 'member' => $context['coppa']['id']]), '">', $txt['coppa_form_link_download'], '</a>
				</span>
			</p>
			<p>', $context['coppa']['many_options'] ? $txt['coppa_send_to_two_options'] : $txt['coppa_send_to_one_option'], '</p>
			<ol style="list-style-type: decimal;margin:0 1em;">';

	// Can they send by post?
	if (!empty($context['coppa']['post']))
	{
		echo '
				<li> ', $txt['coppa_send_by_post'], '
					<p class="coppa_contact">
						', $context['coppa']['post'], '
					</p>
				</li>';

		// Can they send by fax??
		if (!empty($context['coppa']['fax']))
		{
			echo '
				<li>', $txt['coppa_send_by_fax'], '
					<p>
					', $context['coppa']['fax'], '
					</p>
				</li>';
		}

		// Offer an alternative Phone Number?
		if ($context['coppa']['phone'])
		{
			echo '
				<li>', $context['coppa']['phone'], '</li>';
		}

		echo '
			</ol>
		</div>';
	}
}
