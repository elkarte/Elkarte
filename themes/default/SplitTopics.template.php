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
 */

/**
 * Show an interface to ask the user the options for split topics.
 */
function template_ask()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="split_topics">
		<form action="', $scripturl, '?action=splittopics;sa=execute;topic=', $context['current_topic'], '.0" method="post" accept-charset="UTF-8">
			<input type="hidden" name="at" value="', $context['message']['id'], '" />
			<div class="cat_bar">
				<h3 class="catbg">', $txt['split'], '</h3>
			</div>
			<div class="windowbg">
				<div class="content">
					<div class="split_topics">
						<p>
							<strong><label for="subname">', $txt['subject_new_topic'], '</label>:</strong>
							<input type="text" name="subname" id="subname" value="', $context['message']['subject'], '" size="25" class="input_text" autofocus="autofocus" />
						</p>
						<ul class="reset split_topics">
							<li>
								<input type="radio" id="onlythis" name="step2" value="onlythis" checked="checked" class="input_radio" /> <label for="onlythis">', $txt['split_this_post'], '</label>
							</li>
							<li>
								<input type="radio" id="afterthis" name="step2" value="afterthis" class="input_radio" /> <label for="afterthis">', $txt['split_after_and_this_post'], '</label>
							</li>
							<li>
								<input type="radio" id="selective" name="step2" value="selective" class="input_radio" /> <label for="selective">', $txt['select_split_posts'], '</label>
							</li>
						</ul>
						<hr class="hrcolor" />
						<label for="messageRedirect"><input type="checkbox" name="messageRedirect" id="messageRedirect" onclick="document.getElementById(\'reasonArea\').style.display = this.checked ? \'block\' : \'none\';" class="input_check" /> ', $txt['splittopic_notification'], '.</label>
						<fieldset id="reasonArea" style="margin-top: 1ex; display: none;', '">
							<dl class="settings">
								<dt>
									', $txt['moved_why'], '
								</dt>
								<dd>
									<textarea name="reason" rows="4" cols="40">', $txt['splittopic_default'], '</textarea>
								</dd>
							</dl>
						</fieldset>';
	if (!empty($context['can_move']))
		echo '
						<p>
							<label for="move_new_topic"><input type="checkbox" name="move_new_topic" id="move_new_topic" onclick="document.getElementById(\'board_list\').style.display = this.checked ? \'\' : \'none\';" class="input_check" /> ',$txt['splittopic_move'] , '.</label>', template_select_boards('board_list'), '
							<script><!-- // --><![CDATA[
								document.getElementById(\'board_list\').style.display = \'none\';
							// ]]></script>
						</p>';
	echo '
						<div class="auto_flow">
							<input type="submit" value="', $txt['split'], '" class="button_submit" />
							<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						</div>
					</div>
				</div>
			</div>
		</form>
	</div>';
}

/**
 * Split topics main page.
 */
function template_main()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="split_topics">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['split'], '</h3>
		</div>
		<div class="windowbg">
			<div class="content">
				<p>', $txt['split_successful'], '</p>
				<ul class="reset">
					<li>
						<a href="', $scripturl, '?board=', $context['current_board'], '.0">', $txt['message_index'], '</a>
					</li>
					<li>
						<a href="', $scripturl, '?topic=', $context['old_topic'], '.0">', $txt['origin_topic'], '</a>
					</li>
					<li>
						<a href="', $scripturl, '?topic=', $context['new_topic'], '.0">', $txt['new_topic'], '</a>
					</li>
				</ul>
			</div>
		</div>
	</div>';
}

/**
 * Interface to allow selection of messages to split.
 */
function template_select()
{
	global $context, $settings, $txt, $scripturl;

	echo '
	<div id="split_topics">
		<form action="', $scripturl, '?action=splittopics;sa=splitSelection;board=', $context['current_board'], '.0" method="post" accept-charset="UTF-8">
			<div id="not_selected" class="floatleft">
				<div class="cat_bar">
					<h3 class="catbg">', $txt['split'], ' - ', $txt['select_split_posts'], '</h3>
				</div>
				<div class="information">
					', $txt['please_select_split'], '
				</div>
				<div class="pagesection">
					<span id="pageindex_not_selected">', $context['not_selected']['page_index'], '</span>
				</div>
				<ul id="messages_not_selected" class="split_messages smalltext reset">';

	foreach ($context['not_selected']['messages'] as $message)
		echo '
					<li class="windowbg', $message['alternate'] ? '2' : '', '" id="not_selected_', $message['id'], '">
						<div class="content">
							<div class="message_header">
								<a class="split_icon floatright" href="', $scripturl, '?action=splittopics;sa=selectTopics;subname=', $context['topic']['subject'], ';topic=', $context['topic']['id'], '.', $context['not_selected']['start'], ';start2=', $context['selected']['start'], ';move=down;msg=', $message['id'], '" onclick="return select(\'down\', ', $message['id'], ');"><img src="', $settings['images_url'], '/split_select.png" alt="-&gt;" /></a>
								<strong>', $message['subject'], '</strong> ', $txt['by'], ' <strong>', $message['poster'], '</strong><br />
								<em>', $message['time'], '</em>
							</div>
							<div class="post">', $message['body'], '</div>
						</div>
					</li>';

	echo '
					<li class="dummy" />
				</ul>
			</div>
			<div id="selected" class="floatright">
				<div class="cat_bar">
					<h3 class="catbg">
						', $txt['split_selected_posts'], ' (<a href="', $scripturl, '?action=splittopics;sa=selectTopics;subname=', $context['topic']['subject'], ';topic=', $context['topic']['id'], '.', $context['not_selected']['start'], ';start2=', $context['selected']['start'], ';move=reset;msg=0" onclick="return select(\'reset\', 0);">', $txt['split_reset_selection'], '</a>)
					</h3>
				</div>
				<div class="information">
					', $txt['split_selected_posts_desc'], '
				</div>
				<div class="pagesection">
					<span id="pageindex_selected">', $context['selected']['page_index'], '</span>
				</div>
				<ul id="messages_selected" class="split_messages smalltext reset">';

	if (!empty($context['selected']['messages']))
	{
		foreach ($context['selected']['messages'] as $message)
			echo '
					<li class="windowbg', $message['alternate'] ? '2' : '', '" id="selected_', $message['id'], '">
						<div class="content">
							<div class="message_header">
								<a class="split_icon floatleft" href="', $scripturl, '?action=splittopics;sa=selectTopics;subname=', $context['topic']['subject'], ';topic=', $context['topic']['id'], '.', $context['not_selected']['start'], ';start2=', $context['selected']['start'], ';move=up;msg=', $message['id'], '" onclick="return select(\'up\', ', $message['id'], ');"><img src="', $settings['images_url'], '/split_deselect.png" alt="&lt;-" /></a>
								<strong>', $message['subject'], '</strong> ', $txt['by'], ' <strong>', $message['poster'], '</strong><br />
								<em>', $message['time'], '</em>
							</div>
							<div class="post">', $message['body'], '</div>
						</div>
					</li>';
	}

	echo '
					<li class="dummy" />
				</ul>
			</div>
			<br class="clear" />
			<div class="flow_auto">
				<input type="hidden" name="topic" value="', $context['current_topic'], '" />
				<input type="hidden" name="subname" value="', $context['new_subject'], '" />
				<input type="hidden" name="move_to_board" value="', $context['move_to_board'], '" />
				<input type="hidden" name="reason" value="', $context['reason'], '" />
				<input type="submit" value="', $txt['split'], '" class="button_submit" />
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			</div>
		</form>
	</div>

	<script><!-- // --><![CDATA[
		var start = new Array();
		start[0] = ', $context['not_selected']['start'], ';
		start[1] = ', $context['selected']['start'], ';

		function select(direction, msg_id)
		{
			if (window.XMLHttpRequest)
			{
				getXMLDocument(smf_prepareScriptUrl(smf_scripturl) + "action=splittopics;sa=selectTopics;subname=', $context['topic']['subject'], ';topic=', $context['topic']['id'], '." + start[0] + ";start2=" + start[1] + ";move=" + direction + ";msg=" + msg_id + ";xml", onDocReceived);
				return false;
			}
			else
				return true;
		}

		function onDocReceived(XMLDoc)
		{
			var i, j, pageIndex;
			for (i = 0; i < 2; i++)
			{
				pageIndex = XMLDoc.getElementsByTagName("pageIndex")[i];
				setInnerHTML(document.getElementById("pageindex_" + pageIndex.getAttribute("section")), pageIndex.firstChild.nodeValue);
				start[i] = pageIndex.getAttribute("startFrom");
			}
			var numChanges = XMLDoc.getElementsByTagName("change").length;
			var curChange, curSection, curAction, curId, curList, curData, newItem, sInsertBeforeId;
			for (i = 0; i < numChanges; i++)
			{
				curChange = XMLDoc.getElementsByTagName("change")[i];
				curSection = curChange.getAttribute("section");
				curAction = curChange.getAttribute("curAction");
				curId = curChange.getAttribute("id");
				curList = document.getElementById("messages_" + curSection);
				if (curAction == "remove")
					curList.removeChild(document.getElementById(curSection + "_" + curId));
				// Insert a message.
				else
				{
					// By default, insert the element at the end of the list.
					sInsertBeforeId = null;
					// Loop through the list to try and find an item to insert after.
					oListItems = curList.getElementsByTagName("LI");
					for (j = 0; j < oListItems.length; j++)
					{
						if (parseInt(oListItems[j].id.substr(curSection.length + 1)) < curId)
						{
							// This would be a nice place to insert the row.
							sInsertBeforeId = oListItems[j].id;
							// We\'re done for now. Escape the loop.
							j = oListItems.length + 1;
						}
					}

					// Let\'s create a nice container for the message.
					newItem = document.createElement("LI");
					newItem.className = "windowbg2";
					newItem.id = curSection + "_" + curId;
					newItem.innerHTML = "<div class=\\"content\\"><div class=\\"message_header\\"><a class=\\"split_icon float" + (curSection == "selected" ? "left" : "right") + "\\" href=\\"" + smf_prepareScriptUrl(smf_scripturl) + "action=splittopics;sa=selectTopics;subname=', $context['topic']['subject'], ';topic=', $context['topic']['id'], '.', $context['not_selected']['start'], ';start2=', $context['selected']['start'], ';move=" + (curSection == "selected" ? "up" : "down") + ";msg=" + curId + "\\" onclick=\\"return select(\'" + (curSection == "selected" ? "up" : "down") + "\', " + curId + ");\\"><img src=\\"', $settings['images_url'], '/split_" + (curSection == "selected" ? "de" : "") + "select.png\\" alt=\\"" + (curSection == "selected" ? "&lt;-" : "-&gt;") + "\\" /></a><strong>" + curChange.getElementsByTagName("subject")[0].firstChild.nodeValue + "</strong> ', $txt['by'], ' <strong>" + curChange.getElementsByTagName("poster")[0].firstChild.nodeValue + "</strong><br /><em>" + curChange.getElementsByTagName("time")[0].firstChild.nodeValue + "</em></div><div class=\\"post\\">" + curChange.getElementsByTagName("body")[0].firstChild.nodeValue + "</div></div>";

					// So, where do we insert it?
					if (typeof sInsertBeforeId == "string")
						curList.insertBefore(newItem, document.getElementById(sInsertBeforeId));
					else
						curList.appendChild(newItem);
				}
			}
			// After all changes, make sure the window backgrounds are still correct for both lists.
			applyWindowClasses(document.getElementById("messages_selected"));
			applyWindowClasses(document.getElementById("messages_not_selected"));
		}
	// ]]></script>';
}