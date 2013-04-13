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
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Prepares an array of the forum news items for display in the template
 *
 * @return array
 */
function list_getNews()
{
	global $modSettings;

	$admin_current_news = array();

	// Ready the current news.
	foreach (explode("\n", $modSettings['news']) as $id => $line)
		$admin_current_news[$id] = array(
			'id' => $id,
			'unparsed' => un_preparsecode($line),
			'parsed' => preg_replace('~<([/]?)form[^>]*?[>]*>~i', '<em class="smalltext">&lt;$1form&gt;</em>', parse_bbc($line)),
		);

	$admin_current_news['last'] = array(
		'id' => 'last',
		'unparsed' => '<div id="moreNewsItems"></div>
		<noscript><textarea rows="3" cols="65" name="news[]" style="' . (isBrowser('is_ie8') ? 'width: 635px; max-width: 85%; min-width: 85%' : 'width: 85%') . ';"></textarea></noscript>',
		'parsed' => '<div id="moreNewsItems_preview"></div>',
	);

	return $admin_current_news;
}