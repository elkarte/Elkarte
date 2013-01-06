<?php
/**
 * @name      Dialogo Forum
 * @copyright Dialogo Forum contributors
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
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	echo '
	<form action="', $scripturl, '?action=search2" method="post" accept-charset="', $context['character_set'], '" name="searchform" id="searchform">';

	if (!empty($context['search_errors']))
		echo '
		<p>', implode('<br />', $context['search_errors']['messages']), '</p>';

		echo '
		<fieldset id="simple_search">
			<div data-role="fieldcontain">
				<label for="search">', $txt['search_for'], ':</label>
				<input id="search" type="text" name="search"', !empty($context['search_params']['search']) ? ' value="' . $context['search_params']['search'] . '"' : '', ' maxlength="', $context['search_string_limit'], '" size="40" class="input_text" />
				', $context['require_verification'] ? '' : '<div><input type="submit" name="s_search" value="' . $txt['search'] . '" class="button_submit" /></div>
			</div>';

		if ($context['require_verification'])
			echo '
			<div class="verification>
				<h4>', $txt['search_visual_verification_label'], ':</h4>
				<br />', template_control_verification($context['visual_verification_id'], 'all'), '<br />
				<input id="submit" type="submit" name="s_search" value="' . $txt['search'] . '" class="button_submit" />
			</div>';

		echo '
			<input type="hidden" name="advanced" value="0" />
		</fieldset>';

	echo '
	</form>';
}

function template_results()
{
	global $context, $settings, $options, $txt, $scripturl, $message;

	if (isset($context['did_you_mean']) || empty($context['topics']))
	{
		echo '
	<div>
		<h3>', $txt['search_adjust_query'], '</h3>';

		// Did they make any typos or mistakes, perhaps?
		if (isset($context['did_you_mean']))
			echo '
		<p>', $txt['search_did_you_mean'], ' <a href="', $scripturl, '?action=search2;params=', $context['did_you_mean_params'], '">', $context['did_you_mean'], '</a>.</p>';

		echo '
		<form action="', $scripturl, '?action=search2" method="post" accept-charset="', $context['character_set'], '">
			<strong>', $txt['search_for'], ':</strong>
			<input type="text" name="search"', !empty($context['search_params']['search']) ? ' value="' . $context['search_params']['search'] . '"' : '', ' maxlength="', $context['search_string_limit'], '" size="40" class="input_text" />
			<input type="submit" name="edit_search" value="', $txt['search_adjust_submit'], '" class="button_submit" />
			<br class="clear_right" />
			<input type="hidden" name="searchtype" value="', !empty($context['search_params']['searchtype']) ? $context['search_params']['searchtype'] : 0, '" />
			<input type="hidden" name="userspec" value="', !empty($context['search_params']['userspec']) ? $context['search_params']['userspec'] : '', '" />
			<input type="hidden" name="show_complete" value="', !empty($context['search_params']['show_complete']) ? 1 : 0, '" />
			<input type="hidden" name="subject_only" value="', !empty($context['search_params']['subject_only']) ? 1 : 0, '" />
			<input type="hidden" name="minage" value="', !empty($context['search_params']['minage']) ? $context['search_params']['minage'] : '0', '" />
			<input type="hidden" name="maxage" value="', !empty($context['search_params']['maxage']) ? $context['search_params']['maxage'] : '9999', '" />
			<input type="hidden" name="sort" value="', !empty($context['search_params']['sort']) ? $context['search_params']['sort'] : 'relevance', '" />';

		if (!empty($context['search_params']['brd']))
			foreach ($context['search_params']['brd'] as $board_id)
				echo '
			<input type="hidden" name="brd[', $board_id, ']" value="', $board_id, '" />';

		echo '
		</form>
	</div>';
	}

	echo '
	<div>
		<h3>
			<img src="' . $settings['images_url'] . '/buttons/search.png" alt="?" class="centericon" />&nbsp;', $txt['mlist_search_results'],':&nbsp;',$context['search_params']['search'],'
		</h3>
	</div>';

	// was anything even found?
	if (!empty($context['topics']))
		echo'
	<div class="pagesection">
		<span>', $txt['pages'], ': ', $context['page_index'], '</span>
	</div>';
	else
		echo '
		<div>', $txt['find_no_results'], '</div>';

	// while we have results to show ...
	while ($topic = $context['get_topics']())
	{
		echo '
		<ul data-role="listview">';

		foreach ($topic['matches'] as $message)
		{
			echo '
			<li data-role="list-divider">
				<p>', $message['counter'], '</p>
				<h4>', $topic['board']['name'], ' / ', $message['subject_highlighted'], '</h4>
			</li>
			<li>
				<a href="', $scripturl, '?topic=', $topic['id'], '.msg', $message['id'], '#msg', $message['id'], '">';
				
			if ($message['body_highlighted'] != '')
				echo $message['body_highlighted'];

			echo '
				</a>
				<span>&#171;&nbsp;',$txt['by'],'&nbsp;<strong>', $message['member']['link'], '</strong>&nbsp;',$txt['on'],'&nbsp;<em>', $message['time'], '</em>&nbsp;&#187;</span>
				', $topic['board']['link'], '
			</li>';
		}

		echo '
		</ul>';
	}
	
	if (!empty($context['topics']))
		echo '
	<div class="pagesection">
		<span>', $txt['pages'], ': ', $context['page_index'], '</span>
	</div>';

}

?>