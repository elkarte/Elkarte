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
 * @version 1.0
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
	<script><!-- // --><![CDATA[
		var token_name,
			token_value,
			feature_on_text = ', JavaScriptEscape($txt['core_settings_switch_off']), ',
			feature_off_text = ', JavaScriptEscape($txt['core_settings_switch_on']), ';

		$(document).ready(function() {
			$(".core_features_hide").css(\'display\', \'none\');
			$(".core_features_img").css({\'cursor\': \'pointer\', \'display\': \'\'}).each(function() {
				var sImageText = $(this).hasClass(\'on\') ? feature_on_text : feature_off_text;
				$(this).attr({ title: sImageText, alt: sImageText });
			});
			$("#core_features_submit").css(\'display\', \'none\');

			if (!token_name)
				token_name = $("#core_features_token").attr("name");

			if (!token_value)
				token_value = $("#core_features_token").attr("value");

			$(".core_features_img").click(function(){
				var cc = $(this),
					cf = $(this).attr("id").substring(7),
					imgs = new Array("', $settings['images_url'], '/admin/switch_off.png", "', $settings['images_url'], '/admin/switch_on.png"),
					new_state = !$("#feature_" + cf).attr("checked"),
					ajax_infobar = document.createElement(\'div\');

				$(ajax_infobar).css({\'position\': \'fixed\', \'top\': \'0\', \'left\': \'0\', \'width\': \'100%\', \'z-index\': 10, \'display\': \'none\'});
				$("body").append(ajax_infobar);
				$("#feature_" + cf).attr("checked", new_state);

				data = {save: "save", feature_id: cf};
				data[$("#core_features_session").attr("name")] = $("#core_features_session").val();
				data[token_name] = token_value;

				$(".core_features_status_box").each(function(){
					data[$(this).attr("name")] = !$(this).attr("checked") ? 0 : 1;
				});

				// Launch AJAX request.
				$.ajax({
					// The link we are accessing.
					url: elk_scripturl + "?action=xmlhttp;sa=corefeatures;xml",

					// The type of request.
					type: "post",

					// The type of data that is getting returned.
					data: data,
				})
				.done(function(request) {
					if ($(request).find("errors").find("error").length !== 0)
					{
						$(ajax_infobar).attr(\'class\', \'errorbox\');
						$(ajax_infobar).html($(request).find("errors").find("error").text()).slideDown(\'fast\');
					}
					else if ($(request).find("elk").length !== 0)
					{
						$("#feature_link_" + cf).html($(request).find("corefeatures").find("corefeature").text());
						cc.attr({
							"src": imgs[new_state ? 1 : 0],
							"title": new_state ? feature_on_text : feature_off_text,
							"alt": new_state ? feature_on_text : feature_off_text
						});
						$("#feature_link_" + cf).fadeOut().fadeIn();
						$(ajax_infobar).attr(\'class\', \'successbox\');
						var message = new_state ? ' . JavaScriptEscape($txt['core_settings_activation_message']) . ' : ' . JavaScriptEscape($txt['core_settings_deactivation_message']) . ';
						$(ajax_infobar).html(message.replace(\'{core_feature}\', $(request).find("corefeatures").find("corefeature").text())).slideDown();
						setTimeout(function() {
							$(ajax_infobar).slideUp();
						}, 4000);

						token_name = $(request).find("tokens").find(\'[type="token"]\').text();
						token_value = $(request).find("tokens").find(\'[type="token_var"]\').text();
					}
					else
					{
						$(ajax_infobar).attr(\'class\', \'errorbox\');
						$(ajax_infobar).html(' . JavaScriptEscape($txt['core_settings_generic_error']) . ').slideDown(\'fast\');
					}
				})
				.fail(function(error) {
						$(ajax_infobar).html(error).slideDown(\'fast\');
				})
			});
		});
	// ]]></script>
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
			<div style="display:none" id="activation_message" class="errorbox"></div>';

	// Loop through all the shiny features.
	foreach ($context['features'] as $id => $feature)
		echo '
			<div class="content features">
				<img class="features_image" src="', $feature['image'], '" alt="', $feature['title'], '" />
				<div class="features_switch" id="js_feature_', $id, '">
					<label class="core_features_hide" for="feature_', $id, '">', $txt['core_settings_enabled'], '<input class="core_features_status_box" type="checkbox" name="feature_', $id, '" id="feature_', $id, '"', $feature['enabled'] ? ' checked="checked"' : '', ' /></label>
					<img class="core_features_img ', $feature['state'], '" src="', $settings['images_url'], '/admin/switch_', $feature['state'], '.png" alt="', $feature['state'], '" id="switch_', $id, '" style="display:none" />
				</div>
				<h3 id="feature_link_' . $id . '">', ($feature['enabled'] && $feature['url'] ? '<a href="' . $feature['url'] . '">' . $feature['title'] . '</a>' : $feature['title']), '</h3>
				<p>', $feature['desc'], '</p>
				<hr />
			</div>';

	echo '
			<div class="righttext">
				<input id="core_features_session" type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input id="core_features_token" type="hidden" name="', $context['admin-core_token_var'], '" value="', $context['admin-core_token'], '" />
				<input id="core_features_submit" type="submit" value="', $txt['save'], '" name="save" class="right_submit" />
			</div>
		</form>
	</div>';
}