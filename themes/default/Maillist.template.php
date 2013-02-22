<?php

/**
 * @name      Elkarte Forum
 * @copyright Elkarte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 * Templates for the maillist function
 */

/**
 * Shows the details of a failed email for moderation review
 *
 * @return
 */
function template_show_email()
{
	global $txt, $context, $boardurl;

	echo '
		<div class="cat_bar">
			<h3 class="catbg">', $txt['show_notice'], '</h3>
		</div>
		<div class="title_bar">
			<h3 class="titlebg">', $context['notice_subject'], '</h3>
		</div>
		<div class="title_bar">
			<h3 class="titlebg">', $context['notice_from'], '</h3>
		</div>
		<div class="title_bar">
			<h3 class="titlebg">', $context['to'], '</h3>
		</div>
		<div class="noticebox">', $txt['email_failure'], ': ', $context['error_code'], '</div>
			<div class="content">
				<dl>
					<dt>
						<strong>', $txt['show_notice_text'], ':</strong>
					</dt>
					<dd>
						', $context['body'], '
					</dd>
				</dl>
			</div>
		</div>
		<div class="centertext"><a href="' . $boardurl . '/index.php?action=admin;area=maillist;sa=emaillist">', $txt['back'], '</a></div><br />
	</body>
</html>';
}

/**
 * Used to select a bounce template and send a bounce message to a email sender
 *
 * @return
 */
function template_bounce_email()
{
	global $txt, $settings, $context, $scripturl;

	// Build the "it bounced" javascript ....
	echo '
	<script type="text/javascript"><!-- // --><![CDATA[
		// Disable notification boxes as required.
		function modifyWarnNotify()
		{
			disable = !document.getElementById(\'warn_notify\').checked;
			document.getElementById(\'warn_sub\').disabled = disable;
			document.getElementById(\'warn_body\').disabled = disable;
			document.getElementById(\'warn_temp\').disabled = disable;
		}

		// bounce template.
		function populateNotifyTemplate()
		{
			index = document.getElementById(\'warn_temp\').value;
			if (index == -1)
				return false;

			// Otherwise see what we can do...';

	foreach ($context['bounce_templates'] as $k => $type)
		echo '
			if (index == ', $k, ')
			{
				document.getElementById(\'warn_body\').value = "', strtr($type['body'], array('"' => "'", "\n" => '\\n', "\r" => '')), '";
				document.getElementById(\'warn_sub\').value = "', strtr($type['subject'], array('"' => "'", "\n" => '\\n', "\r" => '')), '";
			}';

	echo '
		}

	// ]]></script>';

	echo '
	<form action="', $scripturl, '?action=admin;area=maillist;sa=bounce" method="post" class="flow_hidden" accept-charset="UTF-8">
		<div class="cat_bar">
			<h3 class="catbg">
				<img src="', $settings['images_url'], '/icons/notify_sm.png" alt="" class="icon" />', $txt['show_notice'], '
			</h3>
		</div>';

	// Any special messages?
	if (!empty($context['settings_message']))
		echo '
			<div class="infobox">', $context['settings_message'], '</div>';

	// The main body
	echo '
		<div class="title_bar">
			<h3 class="titlebg">', $context['notice_to'], '</h3>
		</div>
		<div class="windowbg">
			<div class="content">
				<dl class="settings">
					<dt>
						<strong>', $txt['bounce_error'], ':</strong>
					</dt>
					<dd>
						', $context['body'], '
					</dd>
				</dl>
			</div>
		</div>
		<div class="windowbg2">
			<div class="content">
				<dl class="settings">
					<dt>
						<strong>', $txt['bounce_notify'], ':</strong>
					</dt>
					<dd>
						<input type="checkbox" name="warn_notify" id="warn_notify" onclick="modifyWarnNotify();" ', $context['warning_data']['notify'] ? 'checked="checked"' : '', ' class="input_check" />
					</dd>
					<dt>
						<strong>', $txt['bounce_notify_template'], ':</strong>
					</dt>
					<dd>
						<select name="warn_temp" id="warn_temp" disabled="disabled" onchange="populateNotifyTemplate();">
							<option value="-1">', $txt['bounce_notify_template'], '</option>
							<option value="-1">------------------------------</option>';

	foreach ($context['bounce_templates'] as $id_template => $template)
		echo '
							<option value="', $id_template, '">', $template['title'], '</option>';

	echo '
						</select>
					</dd>
					<dt>
						<strong>', $txt['bounce_notify_subject'], ':</strong>
					</dt>
					<dd>
						<input type="text" name="warn_sub" id="warn_sub" value="', empty($context['warning_data']['notify_subject']) ? '' : $context['warning_data']['notify_subject'], '" size="50" style="width: 80%;" class="input_text" />
					</dd>
					<dt>
						<strong>', $txt['bounce_notify_body'], ':</strong>
					</dt>
					<dd>
						<textarea name="warn_body" id="warn_body" cols="40" rows="8">', $context['warning_data']['notify_body'], '</textarea>
					</dd>';

	echo '
				</dl>
				<div class="righttext">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="item" value="', $context['item'], '" />
					<input type="submit" name="bounce" value="', $txt['bounce_issue'], '" class="button_submit" />
				</div>
			</div>
		</div>
	</form>
	<div class="centertext"><a href="javascript:history.go(-1)">', $txt['back'], '</a></div><br />';

	// kick off the javascript.
	echo '
	<script type="text/javascript"><!-- // --><![CDATA[
		modifyWarnNotify();
	// ]]></script>';
}

/**
 * Shows email address to board selections.
 *
 * @return
 */
function template_callback_maillist_receive_email_list()
{
	global $txt, $context;

	echo '
		</dl>
		<p>', $txt['receiving_address_desc'], '</p>
		<dl class="settings">
			<dt>
				<strong>', $txt['receiving_address'], '</strong>
			</dt>
			<dd>
				<strong>', $txt['receiving_board'], '</strong>
			</dd>';

	foreach ($context['maillist_from_to_board'] as $data)
	{
		echo '
			<dt>
				<input type="text" name="emailfrom[', $data['id'], ']" value="', $data['emailfrom'], '" size="50" class="input_text" />
			</dt>
			<dd>
				<select class="input_select" name="boardto[', $data['id'], ']" >';

		foreach ($context['boards'] as $board_id => $board_name)
			echo '
					<option value="', $board_id, '"', (($data['boardto'] == $board_id) ? ' selected="selected"' : ''), '>', $board_name,  '</option>';

		echo '
				</select>
			</dd>';
	}

	// Some blank ones.
	if (empty($context['maillist_from_to_board']))
	{
		for ($count = 0; $count < 2; $count++)
		{
			echo '
			<dt>
				<input type="text" name="emailfrom[]" size="50" class="input_text" />
			</dt>
			<dd>
				<select name="boardto[', $count, ']" >';

			foreach ($context['boards'] as $board_id => $board_name)
				echo '
					<option value="', $board_id, '">', $board_name, '</option>';

				echo '
				</select>
			</dd>';
		}
	}

	echo '
		<dt id="add_more_email_placeholder" style="display: none;"></dt><dd></dd>
		<dt id="add_more_board_div" style="display: none;">
			<a href="#" onclick="addAnotherEmail(); return false;">&#171; ', $txt['reply_add_more'], ' &#187;</a>
		</dt>
		<dd></dd>';

	// build our board id and name arrays for use in the javascript
	$i = 0;
	$script = '
		var boardname = new Array();
		var boardid = new Array();';

	foreach ($context['boards'] as $board_id => $board_name)
		$script = $script . '
		boardid[' . $i . ']=' . $board_id . ';
		boardname[' . $i++ . ']="' . $board_name . '";';

	// The javascript needs to go at the end but we'll put it in this template for looks.
	if (empty($context['settings_post_javascript']))
		$context['settings_post_javascript'] = '';

	$context['settings_post_javascript'] .= '
		function createNamedElement(type, name, customFields)
		{
			var element = null;

			if (!customFields)
				customFields = "";

			// Try the IE way; this fails on standards-compliant browsers
			try
			{
				element = document.createElement("<" + type + \' name="\' + name + \'" \' + customFields + ">");
			}
			catch (e)
			{
			}
			if (!element || element.nodeName != type.toUpperCase())
			{
				// Non-IE browser; use canonical method to create named element
				element = document.createElement(type);
				element.name = name;
			}

			return element;
		}

		var placeHolder = document.getElementById(\'add_more_email_placeholder\');
		var opt = null;' . $script . '

		function addAnotherEmail()
		{
			var newDT = document.createElement("dt");

			var newInput = createNamedElement("input", "emailfrom[]");
			newInput.type = "text";
			newInput.className = "input_text";
			newInput.size = "50";
			newInput.setAttribute("class", "input_text");
			newDT.appendChild(newInput);

			var newDD = document.createElement("dd");

			newInput = createNamedElement("select", "boardto[]");
			newInput.type = "select";
			newInput.size = "1";
			newInput.setAttribute("class", "input_select");

			// add in the options as childen of the newInput select box
			for(i = 0; i < boardname.length; i++)
			{
				opt = createNamedElement("option", "option");
				opt.value = boardid[i];
				opt.innerHTML = boardname[i];
				newInput.appendChild(opt);
			}

			newDD.appendChild(newInput);

			placeHolder.parentNode.insertBefore(newDT, placeHolder);
			placeHolder.parentNode.insertBefore(newDD, placeHolder);
		}
		document.getElementById(\'add_more_board_div\').style.display = \'\';
	';
}