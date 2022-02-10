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

/**
 * Turn on and off certain key features.
 */
function template_core_features()
{
	global $context, $txt, $settings, $scripturl;

	// @todo move all this javascript to a file
	echo '
	<div id="admincenter">';

	if ($context['is_new_install'])
	{
		echo '
		<h2 id="section_header" class="category_header">
			', $txt['core_settings_welcome_msg'], '
		</h2>
		<div class="information">
			', $txt['core_settings_welcome_msg_desc'], '
		</div>';
	}

	echo '
		<form id="core_features" action="', $scripturl, '?action=admin;area=corefeatures" method="post" accept-charset="UTF-8">
			<div id="activation_message" class="errorbox hide"></div>';

	// Loop through all the shiny features.
	foreach ($context['features'] as $id => $feature)
	{
		echo '
			<div class="features">
				<img class="features_image" src="', $feature['image'], '" alt="', $feature['title'], '" />
				<div class="features_switch" id="js_feature_', $id, '">
					<label class="core_features_hide" for="feature_', $id, '">', $txt['core_settings_enabled'], '<input class="core_features_status_box" type="checkbox" name="feature_', $id, '" id="feature_', $id, '"', $feature['enabled'] ? ' checked="checked"' : '', ' /></label>
					<i id="switch_', $id, '" class="core_features_img icon i-switch-', $feature['state'], ' hide" /></i>
				</div>
				<h3 id="feature_link_' . $id . '">', ($feature['enabled'] && $feature['url'] ? '<a href="' . $feature['url'] . '">' . $feature['title'] . '</a>' : $feature['title']), '</h3>
				<p>', $feature['desc'], '</p>
				<hr />
			</div>';
	}

	echo '
			<div class="righttext">
				<input id="core_features_session" type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input id="core_features_token" type="hidden" name="', $context['admin-core_token_var'], '" value="', $context['admin-core_token'], '" />
				<input id="core_features_submit" type="submit" value="', $txt['save'], '" name="save" class="right_submit" />
			</div>
		</form>
	</div>';
}
