<?php

/**
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

use ElkArte\Cache\Cache;
use ElkArte\Helper\Util;

/**
 * This function reads from the database the addons credits,
 * and returns them in an array for display in credits section of the site.
 * The addons copyright, license, title information are those saved from <license>
 * and <credits> tags in package.xml.
 *
 * @return array
 */
function addonsCredits()
{
	$db = database();

	$cache = Cache::instance();
	$credits = [];
	if (!$cache->getVar($credits, 'addons_credits', 86400))
	{
		$db->fetchQuery('
			SELECT 
				version, name, credits
			FROM {db_prefix}log_packages
			WHERE install_state = {int:installed_adds}
				AND credits != {string:empty}
				AND SUBSTRING(filename, 1, 9) != {string:old_patch_name}
				AND SUBSTRING(filename, 1, 9) != {string:patch_name}',
			[
				'installed_adds' => 1,
				'old_patch_name' => 'smf_patch',
				'patch_name' => 'elk_patch',
				'empty' => '',
			]
		)->fetch_callback(
			function ($row) use (&$credits) {
				global $txt;

				$credit_info = Util::unserialize($row['credits']);

				$copyright = empty($credit_info['copyright']) ? '' : $txt['credits_copyright'] . ' &copy; ' . Util::htmlspecialchars($credit_info['copyright']);
				$license = empty($credit_info['license']) ? '' : $txt['credits_license'] . ': ' . Util::htmlspecialchars($credit_info['license']);
				$version = $txt['credits_version'] . '' . $row['version'];
				$title = (empty($credit_info['title']) ? $row['name'] : Util::htmlspecialchars($credit_info['title'])) . ': ' . $version;

				// Build this one out and stash it away
				$name = empty($credit_info['url']) ? $title : '<a href="' . $credit_info['url'] . '">' . $title . '</a>';
				$credits[] = $name . (!empty($license) ? ' | ' . $license : '') . (!empty($copyright) ? ' | ' . $copyright : '');

			}
		);

		$cache->put('addons_credits', $credits, 86400);
	}

	return $credits;
}

/**
 * Prepare credits for display.
 *
 * - This is a helper function, used by admin panel for credits and support page, and by the credits page.
 */
function prepareCreditsData()
{
	global $txt;

	$credits = [];

	// Don't blink. Don't even blink. Blink and you're dead.
	$credits['credits'] = [
		[
			'pretext' => $txt['credits_intro'],
			'title' => $txt['credits_contributors'],
			'groups' => [
				[
					'title' => $txt['credits_groups_contrib'],
					'members' => [
						$txt['credits_contrib_list'],
					],
				],
				[
					'title' => $txt['credits_groups_translators'],
					'members' => [
						$txt['credits_translators_message'],
					],
				],
			],
		],
	];

	// Give credit to any graphic library's, software library's, plugins etc
	$credits['credits_software_graphics'] = [
		'graphics' => [
			'<a href="https://icomoon.io">IcoMoon Free Icons</a> | These icons are licensed under <a href="https://creativecommons.org/licenses/by/4.0/">CC BY-SA 4.0</a>',
			'<a href="https://github.com/googlefonts/noto-emoji">Noto Emoji</a> | &copy; Googlefonts | Licensed under <a href="https://www.apache.org/licenses/LICENSE-2.0">Apache License, Version 2.0</a>',
			'<a href="https://openmoji.org">OpenMoji</a> | &copy; OpenMoji | Licensed under <a href="https://creativecommons.org/licenses/by-sa/4.0/">Creative Commons Attribution-ShareAlike 4.0</a>',
			'<a href="https://github.com/KDE/oxygen-icons">Oxygen Icons</a> | These icons are licensed under <a href="https://creativecommons.org/licenses/by-sa/3.0/">CC BY-SA 3.0</a>',
			'<a href="https://github.com/jdecked/twemoji">Twitter Emoji</a> | &copy; Twitter, Inc and other contributors | Licensed under <a href="https://github.com/jdecked/twemoji/blob/main/LICENSE">MIT</a>',],
		'fonts' => [
			'<a href="https://fonts.google.com/specimen/Open+Sans">Open Sans</a> | &copy; Open Sans Project Authors | Licensed under <a href="https://openfontlicense.org/open-font-license-official-text/">SIL Open Font License, Version 1.1</a>',
		],
		'software' => [
			'<a href="https://ichord.github.com/At.js">At.js</a> | &copy; Chord Luo | Licensed under <a href="https://opensource.org/licenses/MIT">The MIT License (MIT)</a>',
			'<a href="https://lab.ejci.net/favico.js/">Favico.js</a> | &copy; Miroslav Magda | Licensed under <a href="https://opensource.org/licenses/MIT">The MIT License (MIT)</a>',
			'<a href="https://code.google.com/p/google-code-prettify/">Google Code Prettify</a> | Licensed under <a href="https://opensource.org/licenses/Apache-2.0">Apache License, Version 2.0</a>',
			'<a href="https://jquery.com/">JQuery</a> | &copy; jQuery Foundation and other contributors | Licensed under <a href="https://opensource.org/licenses/MIT">The MIT License (MIT)</a>',
			'<a href="https://jqueryui.com/">JQuery UI</a> | &copy; jQuery Foundation and other contributors | Licensed under <a href="https://opensource.org/licenses/MIT">The MIT License (MIT)</a>',
			'<a href="https://github.com/wikimedia/mediawiki-libs-Minify">JavaScriptMinifier</a> &copy Paul Copperman | Licensed under <a href="https://www.apache.org/licenses/LICENSE-2.0">Apache License, Version 2.0</a>',
			'<a href="https://github.com/mailcheck">MailCheck</a> | &copy; Received Inc | Licensed under <a href="https://opensource.org/licenses/MIT">The MIT License (MIT)</a>',
			'<a href="https://github.com/mneofit/multiselect">Multiselect</a> | &copy; Mikhail Neofitov | Licensed under <a href="https://opensource.org/licenses/MIT">The MIT License (MIT)</a>',
			'<a href="https://github.com/michelf/php-markdown">PHP Markdown Lib</a> | &copy; Michel Fortin | Licensed under <a href="https://github.com/michelf/php-markdown/blob/lib/License.md">BSD-style open source</a>',
			'<a href="https://github.com/ttsvetko/HTML5-Desktop-Notifications">Push.js</a> | &copy; Tyler Nickerson | Licensed under <a href="https://opensource.org/licenses/MIT">The MIT License (MIT)</a>',
			'<a href="https://www.sceditor.com/">SCEditor</a> | &copy; Sam Clarke | Licensed under <a href="https://opensource.org/licenses/MIT">The MIT License (MIT)</a>',
			'<a href="https://sourceforge.net/projects/simplehtmldom/">Simple HTML DOM</a> | Licensed under <a href="https://opensource.org/licenses/MIT">The MIT License (MIT)</a>',
			'<a href="https://www.simplemachines.org/">Simple Machines</a> | &copy; Simple Machines | Licensed under <a href="https://www.simplemachines.org/about/smf/license.php">The BSD License</a>',
			'<a href="https://github.com/Frenzie/elk-quick-quote">Quick Quote</a> | &copy; Frans de Jonge | Licensed under <a href="https://opensource.org/licenses/BSD-3-Clause">The BSD License</a>',
		],
	];

	// Add-ons authors: to add credits, the simpler and better way is to add in your package.xml the <credits> <license> tags.
	// Support for addons that use the <credits> tag via the package manager
	$credits['credits_addons'] = addonsCredits();

	// An alternative for addons credits is to use a hook.
	call_integration_hook('integrate_credits', [&$credits]);

	// Copyright information
	$credits['copyrights']['elkarte'] = '&copy; 2012 - ' . Util::strftime('%Y', time()) . ' ElkArte Forum contributors';

	return $credits;
}
