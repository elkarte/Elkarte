/*!
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0 dev
 */

/**
 * This file contains javascript associated with the topic viewing including
 * Quick Modify, Quick Reply, In Topic Moderation, thumbnail expansion etc
 */

/**
 * *** QuickModifyTopic object.
 * Used to quick edit a topic subject by double clicking next to the subject name
 * in a topic listing
 *
 * @param {object} oOptions
 */
function QuickModifyTopic(oOptions)
{
	this.opt = oOptions;
	this.aHidePrefixes = this.opt.aHidePrefixes;
	this.iCurTopicId = 0;
	this.sCurMessageId = '';
	this.sBuffSubject = '';
	this.oSavetipElem = false;
	this.oCurSubjectDiv = null;
	this.oTopicModHandle = document;
	this.bInEditMode = false;
	this.bMouseOnDiv = false;
	this.init();
}

// Used to initialise the object event handlers
QuickModifyTopic.prototype.init = function ()
{
	// Detect and act on keypress
	this.oTopicModHandle.onkeydown = this.modify_topic_keypress.bind(this);

	// Used to detect when we've stopped editing.
	this.oTopicModHandle.onclick = this.modify_topic_click.bind(this);
};

// called from the double click in the div
QuickModifyTopic.prototype.modify_topic = function (topic_id, first_msg_id)
{
	// already editing
	if (this.bInEditMode)
	{
		// Same message then just return, otherwise drop out of this edit.
		if (this.iCurTopicId === topic_id)
			return;
		else
			this.modify_topic_cancel();
	}

	this.bInEditMode = true;
	this.bMouseOnDiv = true;
	this.iCurTopicId = topic_id;

	// Get the topics current subject
	ajax_indicator(true);
	sendXMLDocument.call(this, elk_prepareScriptUrl(elk_scripturl) + "action=quotefast;quote=" + first_msg_id + ";modify;xml", '', this.onDocReceived_modify_topic);
};

// Callback function from the modify_topic ajax call
QuickModifyTopic.prototype.onDocReceived_modify_topic = function (XMLDoc)
{
	// If it is not valid then clean up
	if (!XMLDoc || !XMLDoc.getElementsByTagName('message'))
	{
		this.modify_topic_cancel();
		return true;
	}

	this.sCurMessageId = XMLDoc.getElementsByTagName("message")[0].getAttribute("id");
	this.oCurSubjectDiv = document.getElementById('msg_' + this.sCurMessageId.substr(4));
	this.sBuffSubject = this.oCurSubjectDiv.innerHTML;

	// Hide the tooltip text, don't want them for this element during the edit
	if ($.isFunction($.fn.SiteTooltip))
	{
		this.oSavetipElem = this.oCurSubjectDiv.nextSibling;
		this.sSavetip = this.oSavetipElem.innerHTML;
		this.oSavetipElem.innerHTML = '';
	}

	// Here we hide any other things they want hidden on edit.
	this.set_hidden_topic_areas('none');

	// Show we are in edit mode and allow the edit
	ajax_indicator(false);
	this.modify_topic_show_edit(XMLDoc.getElementsByTagName("subject")[0].childNodes[0].nodeValue);
};

// Cancel out of an edit and return things to back to what they were
QuickModifyTopic.prototype.modify_topic_cancel = function ()
{
	this.oCurSubjectDiv.innerHTML = this.sBuffSubject;
	this.set_hidden_topic_areas('');
	this.bInEditMode = false;

	// Put back the hover text
	if (this.oSavetipElem !== false)
		this.oSavetipElem.innerHTML = this.sSavetip;

	return false;
};

// Simply restore/show any hidden bits during topic editing.
QuickModifyTopic.prototype.set_hidden_topic_areas = function (set_style)
{
	for (var i = 0; i < this.aHidePrefixes.length; i++)
	{
		if (document.getElementById(this.aHidePrefixes[i] + this.sCurMessageId.substr(4)) !== null)
			document.getElementById(this.aHidePrefixes[i] + this.sCurMessageId.substr(4)).style.display = set_style;
	}
};

// For templating, shown that an inline edit is being made.
QuickModifyTopic.prototype.modify_topic_show_edit = function (subject)
{
	// Just template the subject.
	this.oCurSubjectDiv.innerHTML = '<input type="text" name="subject" value="' + subject + '" size="60" style="width: 95%;" maxlength="80" class="input_text" autocomplete="off" /><input type="hidden" name="topic" value="' + this.iCurTopicId + '" /><input type="hidden" name="msg" value="' + this.sCurMessageId.substr(4) + '" />';

	// Attach mouse over and out events to this new div
	this.oCurSubjectDiv.onmouseout = this.modify_topic_mouseout.bind(this);
	this.oCurSubjectDiv.onmouseover = this.modify_topic_mouseover.bind(this);
};

// Yup that's right, save it
QuickModifyTopic.prototype.modify_topic_save = function (cur_session_id, cur_session_var)
{
	if (!this.bInEditMode)
		return true;

	var x = [];

	x[x.length] = 'subject=' + document.forms.quickModForm.subject.value.replace(/&#/g, "&#38;#").php_urlencode();
	x[x.length] = 'topic=' + parseInt(document.forms.quickModForm.elements.topic.value);
	x[x.length] = 'msg=' + parseInt(document.forms.quickModForm.elements.msg.value);

	// Send in the call to save the updated topic subject
	ajax_indicator(true);
	sendXMLDocument.call(this, elk_prepareScriptUrl(elk_scripturl) + "action=jsmodify;topic=" + parseInt(document.forms.quickModForm.elements.topic.value) + ";" + cur_session_var + "=" + cur_session_id + ";xml", x.join("&"), this.modify_topic_done);

	return false;
};

// Done with the edit, if all went well show the new topic title
QuickModifyTopic.prototype.modify_topic_done = function (XMLDoc)
{
	ajax_indicator(false);

	// If it is not valid then clean up
	if (!XMLDoc || !XMLDoc.getElementsByTagName('subject'))
	{
		this.modify_topic_cancel();
		return true;
	}

	var message = XMLDoc.getElementsByTagName("elk")[0].getElementsByTagName("message")[0],
		subject = message.getElementsByTagName("subject")[0],
		error = message.getElementsByTagName("error")[0];

	// No subject or other error?
	if (!subject || error)
		return false;

	this.modify_topic_hide_edit(subject.childNodes[0].nodeValue);
	this.set_hidden_topic_areas('');
	this.bInEditMode = false;

	// Redo tooltips if they are on since we just pulled the rug out on this one
	if ($.isFunction($.fn.SiteTooltip))
	{
		this.oSavetipElem.innerHTML = this.sSavetip;
		$('.preview').SiteTooltip();
	}

	return false;
};

// Done with the edit, put in new subject and link.
QuickModifyTopic.prototype.modify_topic_hide_edit = function (subject)
{
	// Re-template the subject!
	this.oCurSubjectDiv.innerHTML = '<a href="' + elk_scripturl + '?topic=' + this.iCurTopicId + '.0">' + subject + '<' +'/a>';
};

// keypress event ... like enter or escape
QuickModifyTopic.prototype.modify_topic_keypress = function (oEvent)
{
	if (typeof(oEvent.keyCode) !== "undefined" && this.bInEditMode)
	{
		if (oEvent.keyCode === 27)
		{
			this.modify_topic_cancel();
			if (typeof(oEvent.preventDefault) === "undefined")
				oEvent.returnValue = false;
			else
				oEvent.preventDefault();
		}
		else if (oEvent.keyCode === 13)
		{
			this.modify_topic_save(elk_session_id, elk_session_var);
			if (typeof(oEvent.preventDefault) === "undefined")
				oEvent.returnValue = false;
			else
				oEvent.preventDefault();
		}
	}
};

// A click event to signal the finish of the edit
QuickModifyTopic.prototype.modify_topic_click = function (oEvent)
{
	if (this.bInEditMode && !this.bMouseOnDiv)
		this.modify_topic_save(elk_session_id, elk_session_var);
};

// Moved out of the editing div
QuickModifyTopic.prototype.modify_topic_mouseout = function (oEvent)
{
	this.bMouseOnDiv = false;
};

// Moved back over the editing div
QuickModifyTopic.prototype.modify_topic_mouseover = function (oEvent)
{
	this.bMouseOnDiv = true;
	oEvent.preventDefault();
};

/**
 * QuickReply object, this allows for selecting the quote button and
 * having the quote appear in the quick reply box
 *
 * @param {type} oOptions
 */
function QuickReply(oOptions)
{
	this.opt = oOptions;
	this.bCollapsed = this.opt.bDefaultCollapsed;
	this.bIsFull = this.opt.bIsFull;

	// If the initial state is to be collapsed, collapse it.
	if (this.bCollapsed)
		this.swap(true);
}

// When a user presses quote, put it in the quick reply box (if expanded).
QuickReply.prototype.quote = function (iMessageId, xDeprecated)
{
	ajax_indicator(true);

	// Collapsed on a quote, then simply got to the full post screen
	if (this.bCollapsed)
	{
		window.location.href = elk_prepareScriptUrl(this.opt.sScriptUrl) + 'action=post;quote=' + iMessageId + ';topic=' + this.opt.iTopicId + '.' + this.opt.iStart;
		return false;
	}

	// Insert the quote
	if (this.bIsFull)
		insertQuoteFast(iMessageId);
	else
		getXMLDocument(elk_prepareScriptUrl(this.opt.sScriptUrl) + 'action=quotefast;quote=' + iMessageId + ';xml', this.onQuoteReceived);

	// Move the view to the quick reply box.
	if (navigator.appName === 'Microsoft Internet Explorer')
		window.location.hash = this.opt.sJumpAnchor;
	else
		window.location.hash = '#' + this.opt.sJumpAnchor;

	return false;
};

// This is the callback function used after the XMLhttp request.
QuickReply.prototype.onQuoteReceived = function (oXMLDoc)
{
	var sQuoteText = '';

	for (var i = 0; i < oXMLDoc.getElementsByTagName('quote')[0].childNodes.length; i++)
		sQuoteText += oXMLDoc.getElementsByTagName('quote')[0].childNodes[i].nodeValue;

	replaceText(sQuoteText, document.forms.postmodify.message);

	ajax_indicator(false);
};

// The function handling the swapping of the quick reply area
QuickReply.prototype.swap = function (bInit, bSavestate)
{
	var oQuickReplyContainer = document.getElementById(this.opt.sClassId),
		sEditorId = this.opt.sContainerId,
		bIsFull = this.opt.bIsFull;

	// Default bInit to false and bSavestate to true
	bInit = typeof(bInit) !== 'undefined';
	bSavestate = typeof(bSavestate) === 'undefined';

	// Flip our current state if not responding to an initial loading
	if (!bInit)
		this.bCollapsed = !this.bCollapsed;

	// Swap the class on the expcol image as needed
	var sTargetClass = !this.bCollapsed ? this.opt.sClassCollapsed : this.opt.sClassExpanded;
	if (oQuickReplyContainer.className !== sTargetClass)
		oQuickReplyContainer.className = sTargetClass;

	// And show the new title
	oQuickReplyContainer.title = oQuickReplyContainer.title = this.bCollapsed ? this.opt.sTitleCollapsed : this.opt.sTitleExpanded;

	// Show or hide away
	if (this.bCollapsed)
		$('#' + this.opt.sContainerId).slideUp();
	else
	{
		$('#' + this.opt.sContainerId).slideDown();
		if (bIsFull)
			$('#' + sEditorId).resize();
	}

	// Using a cookie for guests?
	if (bSavestate && 'oCookieOptions' in this.opt && this.opt.oCookieOptions.bUseCookie)
		this.oCookie.set(this.opt.oCookieOptions.sCookieName, this.bCollapsed ? '1' : '0');

	// Save the expand /collapse preference
	if (!bInit && bSavestate && 'oThemeOptions' in this.opt && this.opt.oThemeOptions.bUseThemeSettings)
		elk_setThemeOption(this.opt.oThemeOptions.sOptionName, this.bCollapsed ? '1' : '0', 'sThemeId' in this.opt.oThemeOptions ? this.opt.oThemeOptions.sThemeId : null, 'sAdditionalVars' in this.opt.oThemeOptions ? this.opt.oThemeOptions.sAdditionalVars : null);
};

/**
 * QuickModify object.
 * This will allow for the quick editing of a post via ajax
 *
 * @param {object} oOptions
 */
function QuickModify(oOptions)
{
	this.opt = oOptions;
	this.bInEditMode = false;
	this.sCurMessageId = '';
	this.oCurMessageDiv = null;
	this.oCurInfoDiv = null;
	this.oCurSubjectDiv = null;
	this.oMsgIcon = null;
	this.sMessageBuffer = '';
	this.sSubjectBuffer = '';
	this.sInfoBuffer = '';
	this.aAccessKeys = [];

	// Show the edit buttons
	var aShowQuickModify = document.getElementsByClassName(this.opt.sClassName);
	for (var i = 0, length = aShowQuickModify.length; i < length; i++)
		aShowQuickModify[i].style.display = "inline";
}

// Function called when a user presses the edit button.
QuickModify.prototype.modifyMsg = function (iMessageId)
{
	// Removes the accesskeys from the quickreply inputs and saves them in an array to use them later
	if (typeof(this.opt.sFormRemoveAccessKeys) !== 'undefined')
	{
		if (typeof(document.forms[this.opt.sFormRemoveAccessKeys]))
		{
			var aInputs = document.forms[this.opt.sFormRemoveAccessKeys].getElementsByTagName('input');
			for (var i = 0; i < aInputs.length; i++)
			{
				if (aInputs[i].accessKey !== '')
				{
					this.aAccessKeys[aInputs[i].name] = aInputs[i].accessKey;
					aInputs[i].accessKey = '';
				}
			}
		}
	}

	// First cancel if there's another message still being edited.
	if (this.bInEditMode)
		this.modifyCancel();

	// At least NOW we're in edit mode
	this.bInEditMode = true;

	// Send out the XMLhttp request to get more info
	ajax_indicator(true);
	sendXMLDocument.call(this, elk_prepareScriptUrl(elk_scripturl) + 'action=quotefast;quote=' + iMessageId + ';modify;xml', '', this.onMessageReceived);
};

// The callback function used for the XMLhttp request retrieving the message.
QuickModify.prototype.onMessageReceived = function (XMLDoc)
{
	var sBodyText = '',
		sSubjectText;

	// No longer show the 'loading...' sign.
	ajax_indicator(false);

	// Grab the message ID.
	this.sCurMessageId = XMLDoc.getElementsByTagName('message')[0].getAttribute('id');

	// Show the message icon if it was hidden and its set
	if (this.opt.sIconHide !== null)
	{
		this.oMsgIcon = document.getElementById('messageicon_' + this.sCurMessageId.replace("msg_", ""));
		if (this.oMsgIcon !== null && getComputedStyle(this.oMsgIcon).getPropertyValue("display") === 'none')
			this.oMsgIcon.style.display = 'inline';
	}

	// If this is not valid then simply give up.
	if (!document.getElementById(this.sCurMessageId))
		return this.modifyCancel();

	// Replace the body part.
	for (var i = 0; i < XMLDoc.getElementsByTagName("message")[0].childNodes.length; i++)
		sBodyText += XMLDoc.getElementsByTagName("message")[0].childNodes[i].nodeValue;

	this.oCurMessageDiv = document.getElementById(this.sCurMessageId);
	this.sMessageBuffer = this.oCurMessageDiv.innerHTML;

	// We have to force the body to lose its dollar signs thanks to IE.
	sBodyText = sBodyText.replace(/\$/g, '{&dollarfix;$}');

	// Actually create the content, with a bodge for disappearing dollar signs.
	this.oCurMessageDiv.innerHTML = this.opt.sTemplateBodyEdit.replace(/%msg_id%/g, this.sCurMessageId.substr(4)).replace(/%body%/, sBodyText).replace(/\{&dollarfix;\$\}/g, '$');

	// Save and hide the existing subject div
	if (this.opt.sIDSubject !== null)
	{
		this.oCurSubjectDiv = document.getElementById(this.opt.sIDSubject + this.sCurMessageId.substr(4));
		if (this.oCurSubjectDiv !== null)
		{
			this.oCurSubjectDiv.style.display = 'none';
			this.sSubjectBuffer = this.oCurSubjectDiv.innerHTML;
		}
	}

	// Save the info div, then open an input field on it
	sSubjectText = XMLDoc.getElementsByTagName('subject')[0].childNodes[0].nodeValue.replace(/\$/g, '{&dollarfix;$}');
	if (this.opt.sIDInfo !== null)
	{
		this.oCurInfoDiv = document.getElementById(this.opt.sIDInfo + this.sCurMessageId.substr(4));
		if (this.oCurInfoDiv !== null)
		{
			this.sInfoBuffer = this.oCurInfoDiv.innerHTML;
			this.oCurInfoDiv.innerHTML =  this.opt.sTemplateSubjectEdit.replace(/%subject%/, sSubjectText).replace(/\{&dollarfix;\$\}/g, '$');
		}
	}

	// Position the editor in the window
	location.hash = '#info_' + this.sCurMessageId.substr(this.sCurMessageId.lastIndexOf("_") + 1);

	// Handle custom function hook before showing the new select.
	if ('funcOnAfterCreate' in this.opt)
	{
		this.tmpMethod = this.opt.funcOnAfterCreate;
		this.tmpMethod(this);
		delete this.tmpMethod;
	}

	return true;
};

// Function in case the user presses cancel (or other circumstances cause it).
QuickModify.prototype.modifyCancel = function ()
{
	// Roll back the HTML to its original state.
	if (this.oCurMessageDiv)
	{
		this.oCurMessageDiv.innerHTML = this.sMessageBuffer;
		this.oCurInfoDiv.innerHTML = this.sInfoBuffer;
		this.oCurSubjectDiv.innerHTML = this.sSubjectBuffer;
		if (this.oCurSubjectDiv !== null)
		{
			this.oCurSubjectDiv.style.display = '';
		}
	}

	// Hide the message icon if we are doing that
	if (this.opt.sIconHide)
	{
		var oCurrentMsgIcon = document.getElementById('msg_icon_' + this.sCurMessageId.replace("msg_", ""));

		if (oCurrentMsgIcon !== null && oCurrentMsgIcon.src.indexOf(this.opt.sIconHide) > 0)
			this.oMsgIcon.style.display = 'none';
	}

	// No longer in edit mode, that's right.
	this.bInEditMode = false;

	// Let's put back the accesskeys to their original place
	if (typeof(this.opt.sFormRemoveAccessKeys) !== 'undefined')
	{
		if (typeof(document.forms[this.opt.sFormRemoveAccessKeys]))
		{
			var aInputs = document.forms[this.opt.sFormRemoveAccessKeys].getElementsByTagName('input');
			for (var i = 0; i < aInputs.length; i++)
			{
				if (typeof(this.aAccessKeys[aInputs[i].name]) !== 'undefined')
				{
					aInputs[i].accessKey = this.aAccessKeys[aInputs[i].name];
				}
			}
		}
	}

	return false;
};

// The function called after a user wants to save his precious message.
QuickModify.prototype.modifySave = function (sSessionId, sSessionVar)
{
	var i = 0,
		x = [],
		uIds = [];

	// We cannot save if we weren't in edit mode.
	if (!this.bInEditMode)
		return true;

	this.bInEditMode = false;

	// Let's put back the accesskeys to their original place
	if (typeof(this.opt.sFormRemoveAccessKeys) !== 'undefined')
	{
		if (typeof(document.forms[this.opt.sFormRemoveAccessKeys]))
		{
			var aInputs = document.forms[this.opt.sFormRemoveAccessKeys].getElementsByTagName('input');
			for (i = 0; i < aInputs.length; i++)
			{
				if (typeof(this.aAccessKeys[aInputs[i].name]) !== 'undefined')
				{
					aInputs[i].accessKey = this.aAccessKeys[aInputs[i].name];
				}
			}
		}
	}

	var oInputs = document.forms.quickModForm.getElementsByTagName('input');
	for (i = 0; i < oInputs.length; i++)
	{
		if (oInputs[i].name === 'uid[]')
		{
			uIds.push('uid[' + i + ']=' + parseInt(oInputs[i].value));
		}
	}

	x[x.length] = 'subject=' + document.forms.quickModForm.subject.value.replace(/&#/g, "&#38;#").php_urlencode();
	x[x.length] = 'message=' + document.forms.quickModForm.message.value.replace(/&#/g, "&#38;#").php_urlencode();
	x[x.length] = 'topic=' + parseInt(document.forms.quickModForm.elements.topic.value);
	x[x.length] = 'msg=' + parseInt(document.forms.quickModForm.elements.msg.value);
	if (uIds.length > 0)
		x[x.length] = uIds.join("&");

	// Send in the XMLhttp request and let's hope for the best.
	ajax_indicator(true);
	sendXMLDocument.call(this, elk_prepareScriptUrl(this.opt.sScriptUrl) + "action=jsmodify;topic=" + this.opt.iTopicId + ";" + elk_session_var + "=" + elk_session_id + ";xml", x.join("&"), this.onModifyDone);

	return false;
};

// Callback function of the XMLhttp request sending the modified message.
QuickModify.prototype.onModifyDone = function (XMLDoc)
{
	var oErrordiv;

	// We've finished the loading stuff.
	ajax_indicator(false);

	// If we didn't get a valid document, just cancel.
	if (!XMLDoc || !XMLDoc.getElementsByTagName('elk')[0])
	{
		// Mozilla will nicely tell us what's wrong.
		if (typeof XMLDoc.childNodes !== 'undefined' &&  XMLDoc.childNodes.length > 0 && XMLDoc.firstChild.nodeName === 'parsererror')
		{
			oErrordiv = document.getElementById('error_box');
			oErrordiv.innerHTML = XMLDoc.firstChild.textContent;
			oErrordiv.style.display = '';
		}
		else
			this.modifyCancel();
		return;
	}

	var message = XMLDoc.getElementsByTagName('elk')[0].getElementsByTagName('message')[0],
		body = message.getElementsByTagName('body')[0],
		error = message.getElementsByTagName('error')[0];

	$(document.forms.quickModForm.message).removeClass('border_error');
	$(document.forms.quickModForm.subject).removeClass('border_error');

	if (body)
	{
		// Show new body.
		var bodyText = '';
		for (var i = 0; i < body.childNodes.length; i++)
			bodyText += body.childNodes[i].nodeValue;

		this.sMessageBuffer = this.opt.sTemplateBodyNormal.replace(/%body%/, bodyText.replace(/\$/g, '{&dollarfix;$}')).replace(/\{&dollarfix;\$\}/g,'$');
		this.oCurMessageDiv.innerHTML = this.sMessageBuffer;

		// Show new subject div, update in case it changed
		var oSubject = message.getElementsByTagName('subject')[0],
			sSubjectText = oSubject.childNodes[0].nodeValue.replace(/\$/g, '{&dollarfix;$}');

		this.sSubjectBuffer = this.opt.sTemplateSubjectNormal.replace(/%subject%/, sSubjectText).replace(/\{&dollarfix;\$\}/g, '$');
		this.oCurSubjectDiv.innerHTML = this.sSubjectBuffer;
		this.oCurSubjectDiv.style.display = '';

		// Restore the info bar div
		this.oCurInfoDiv.innerHTML = this.sInfoBuffer;

		// Show this message as 'modified on x by y'.
		if (this.opt.bShowModify)
		{
			var modified_element = document.getElementById('modified_' + this.sCurMessageId.substr(4));
			modified_element.innerHTML = message.getElementsByTagName('modified')[0].childNodes[0].nodeValue;

			// Just in case it's the first time the message is modified and the element is hidden
			modified_element.style.display = 'block';
		}

		// Hide the icon if we were told to
		if (this.opt.sIconHide !== null)
		{
			var oCurrentMsgIcon = document.getElementById('msg_icon_' + this.sCurMessageId.replace("msg_", ""));
			if (oCurrentMsgIcon !== null && oCurrentMsgIcon.src.indexOf(this.opt.sIconHide) > 0)
				this.oMsgIcon.style.display = 'none';
		}

		// Re embed any video links if the feature is available
		if ($.isFunction($.fn.linkifyvideo))
			$().linkifyvideo(oEmbedtext, this.sCurMessageId);

		// Hello, Sweetie
		$('#' + this.sCurMessageId + ' .spoilerheader').click(function(){
			$(this).next().children().slideToggle("fast");
		});

		// Re-Fix code blocks
		if (typeof elk_codefix === 'function')
			elk_codefix();

		// And pretty the code
		if (typeof prettyPrint === 'function')
			prettyPrint();
	}
	else if (error)
	{
		oErrordiv = document.getElementById('error_box');
		oErrordiv.innerHTML = error.childNodes[0].nodeValue;
		oErrordiv.style.display = '';
		if (error.getAttribute('in_body') === '1')
			$(document.forms.quickModForm.message).addClass('border_error');
		if (error.getAttribute('in_subject') === '1')
			$(document.forms.quickModForm.subject).addClass('border_error');
	}
};

/**
 * Quick Moderation for the topic view
 *
 * @param {type} oOptions
 */
function InTopicModeration(oOptions)
{
	this.opt = oOptions;
	this.bButtonsShown = false;
	this.iNumSelected = 0;

	this.init();
}

InTopicModeration.prototype.init = function()
{
	// Add checkboxes to all the messages.
	for (var i = 0, n = this.opt.aMessageIds.length; i < n; i++)
	{
		// Create the checkbox.
		var oCheckbox = document.createElement('input');

		oCheckbox.type = 'checkbox';
		oCheckbox.className = 'input_check';
		oCheckbox.name = 'msgs[]';
		oCheckbox.value = this.opt.aMessageIds[i];
		oCheckbox.onclick = this.handleClick.bind(this, oCheckbox);

		// Append it to the container
		var oCheckboxContainer = document.getElementById(this.opt.sCheckboxContainerMask + this.opt.aMessageIds[i]);
		oCheckboxContainer.appendChild(oCheckbox);
		oCheckboxContainer.style.display = '';
	}
};

// They clicked a checkbox in a message so we show the button options to them
InTopicModeration.prototype.handleClick = function(oCheckbox)
{
	var oButtonStrip = document.getElementById(this.opt.sButtonStrip),
		oButtonStripDisplay = document.getElementById(this.opt.sButtonStripDisplay);

	if (!this.bButtonsShown && this.opt.sButtonStripDisplay)
	{
		// Make sure it can go somewhere.
		if (typeof(oButtonStripDisplay) === 'object' && oButtonStripDisplay !== null)
			oButtonStripDisplay.style.display = "";
		else
		{
			var oNewDiv = document.createElement('div'),
				oNewList = document.createElement('ul');

			oNewDiv.id = this.opt.sButtonStripDisplay;
			oNewDiv.className = this.opt.sButtonStripClass ? this.opt.sButtonStripClass : 'buttonlist floatbottom';

			oNewDiv.appendChild(oNewList);
			oButtonStrip.appendChild(oNewDiv);
		}

		// Add the 'remove selected items' button.
		if (this.opt.bCanRemove)
			elk_addButton(this.opt.sButtonStrip, this.opt.bUseImageButton, {
				sId: 'remove_button',
				sText: this.opt.sRemoveButtonLabel,
				sImage: this.opt.sRemoveButtonImage,
				sUrl: '#',
				aEvents: [
					['click', this.handleSubmit.bind(this, 'remove')]
				]
			});

		// Add the 'restore selected items' button.
		if (this.opt.bCanRestore)
			elk_addButton(this.opt.sButtonStrip, this.opt.bUseImageButton, {
				sId: 'restore_button',
				sText: this.opt.sRestoreButtonLabel,
				sImage: this.opt.sRestoreButtonImage,
				sUrl: '#',
				aEvents: [
					['click', this.handleSubmit.bind(this, 'restore')]
				]
			});

		// Add the 'split selected items' button.
		if (this.opt.bCanSplit)
			elk_addButton(this.opt.sButtonStrip, this.opt.bUseImageButton, {
				sId: 'split_button',
				sText: this.opt.sSplitButtonLabel,
				sImage: this.opt.sSplitButtonImage,
				sUrl: '#',
				aEvents: [
					['click', this.handleSubmit.bind(this, 'split')]
				]
			});

		// Adding these buttons once should be enough.
		this.bButtonsShown = true;
	}

	// Keep stats on how many items were selected.
	this.iNumSelected += oCheckbox.checked ? 1 : -1;

	// Show the number of messages selected in each of the buttons.
	if (this.opt.bCanRemove && !this.opt.bUseImageButton)
	{
		oButtonStrip.querySelector('#remove_button_text').innerHTML = this.opt.sRemoveButtonLabel + ' [' + this.iNumSelected + ']';
		oButtonStrip.querySelector('#remove_button').style.display = this.iNumSelected < 1 ? "none" : "";
	}

	if (this.opt.bCanRestore && !this.opt.bUseImageButton)
	{
		oButtonStrip.querySelector('#restore_button_text').innerHTML = this.opt.sRestoreButtonLabel + ' [' + this.iNumSelected + ']';
		oButtonStrip.querySelector('#restore_button').style.display = this.iNumSelected < 1 ? "none" : "";
	}

	if (this.opt.bCanSplit && !this.opt.bUseImageButton)
	{
		oButtonStrip.querySelector('#split_button_text').innerHTML = this.opt.sSplitButtonLabel + ' [' + this.iNumSelected + ']';
		oButtonStrip.querySelector('#split_button').style.display = this.iNumSelected < 1 ? "none" : "";
	}

	// Try to restore the correct position.
	var aItems = oButtonStrip.getElementsByTagName('span');
	if (aItems.length > 3)
	{
		if (this.iNumSelected < 1)
		{
			aItems[aItems.length - 3].className = aItems[aItems.length - 3].className.replace(/\s*position_holder/, 'last');
			aItems[aItems.length - 2].className = aItems[aItems.length - 2].className.replace(/\s*position_holder/, 'last');
		}
		else
		{
			aItems[aItems.length - 2].className = aItems[aItems.length - 2].className.replace(/\s*last/, 'position_holder');
			aItems[aItems.length - 3].className = aItems[aItems.length - 3].className.replace(/\s*last/, 'position_holder');
		}
	}
};

// Called when the user clicks one of the buttons that we added
InTopicModeration.prototype.handleSubmit = function (sSubmitType)
{
	var oForm = document.getElementById(this.opt.sFormId);

	// Make sure this form isn't submitted in another way than this function.
	var oInput = document.createElement('input');

	oInput.type = 'hidden';
	oInput.name = this.opt.sSessionVar;
	oInput.value = this.opt.sSessionId;
	oForm.appendChild(oInput);

	// Set the form action based on the button they clicked
	switch (sSubmitType)
	{
		case 'remove':
			if (!confirm(this.opt.sRemoveButtonConfirm))
				return false;

			oForm.action = oForm.action.replace(/;split_selection=1/, '');
			oForm.action = oForm.action.replace(/;restore_selected=1/, '');
		break;

		case 'restore':
			if (!confirm(this.opt.sRestoreButtonConfirm))
				return false;

			oForm.action = oForm.action.replace(/;split_selection=1/, '');
			oForm.action += ';restore_selected=1';
		break;

		case 'split':
			if (!confirm(this.opt.sRestoreButtonConfirm))
				return false;

			oForm.action = oForm.action.replace(/;restore_selected=1/, '');
			oForm.action += ';split_selection=1';
		break;

		default:
			return false;
		break;
	}

	oForm.submit();
	return true;
};


/**
 * Expands an attachment thumbnail when its clicked
 *
 * @param {string} thumbID
 * @param {string} messageID
 */
function expandThumbLB(thumbID, messageID) {
	var link = document.getElementById('link_' + thumbID),
		siblings = $('a[data-lightboxmessage="' + messageID + '"]'),
		navigation = [],
		xDown = null,
		yDown = null,
		$elk_expand_icon = $('<span id="elk_lb_expand"></span>'),
		$elk_next_icon = $('<span id="elk_lb_next"></span>'),
		$elk_prev_icon = $('<span id="elk_lb_prev"></span>'),
		$elk_lightbox = $('#elk_lightbox'),
		$elk_lb_content = $('#elk_lb_content'),
		ajaxIndicatorOn = function () {
			$('<div id="lightbox-loading"><i class="icon icon-spin icon-xl i-spinner"></i><div>').appendTo($elk_lb_content);
			$('html, body').addClass('elk_lb_no_scrolling');
		},
		ajaxIndicatorOff = function () {
			$('#lightbox-loading').remove();
		},
		closeLightbox = function () {
			// Close the lightbox and remove handlers
			$elk_expand_icon.off('click');
			$elk_next_icon.off('click');
			$elk_prev_icon.off('click');
			$elk_lightbox.hide();
			$elk_lb_content.html('').removeAttr('style').removeClass('expand');
			$('html, body').removeClass('elk_lb_no_scrolling');
			$(window).off('resize.lb');
			$(window).off('keydown.lb');
			$(window).off('touchstart.lb');
			$(window).off('touchmove.lb');
		},
		openLightbox = function () {
			// Load and open an image in the lightbox
			$('<img id="elk_lb_img" src="' + link.href + '">')
				.on('load', function () {
					var screenWidth = (window.innerWidth ? window.innerWidth : $(window).width()) * (is_mobile ? 0.8 : 0.9),
						screenHeight = (window.innerHeight ? window.innerHeight : $(window).height()) * 0.9;

					$(this).css({
						'max-width': Math.floor(screenWidth) + 'px',
						'max-height': Math.floor(screenHeight) + 'px'
					});

					$elk_lb_content.html($(this)).append($elk_expand_icon)
					.append($elk_next_icon).append($elk_prev_icon);

					ajaxIndicatorOff();
				})
				.on('error', function () {
					// Perhaps a message, but for now make it look like we tried and failed
					setTimeout(function () {
						ajaxIndicatorOff();
						closeLightbox();
					}, 1500);
				});
		},
		nextNav = function () {
			// Get / Set the next image ID in the array (with wrap around)
			thumbID = navigation[($.inArray(thumbID, navigation) + 1) % navigation.length];
		},
		prevNav = function () {
			// Get / Set the previous image ID in the array (with wrap around)
			thumbID =  navigation[($.inArray(thumbID, navigation) - 1 + navigation.length) % navigation.length];
		},
		navLightbox = function () {
			// Navigate to the next image and show it in the lightbox
			$elk_lb_content.html('').removeAttr('style').removeClass('expand');
			ajaxIndicatorOn();
			$elk_expand_icon.off('click');
			$elk_next_icon.off('click');
			$elk_prev_icon.off('click');
			link = document.getElementById('link_' + thumbID);
			openLightbox();
			expandLightbox();
		},
		expandLightbox = function () {
			// Add an expand the image to full size when the expand icon is clicked
			$elk_expand_icon.on('click', function () {
				$('#elk_lb_content').addClass('expand').css({
					'height': Math.floor(window.innerHeight * 0.95) + 'px',
					'width': Math.floor(window.innerWidth * 0.9) + 'px',
					'left': '0'
				});
				$('#elk_lb_img').removeAttr('style');
				$elk_expand_icon.hide();
				$(window).off('keydown.lb');
				$(window).off('touchmove.lb');
			});
			$elk_next_icon.on('click', function (event) {
				event.preventDefault();
				event.stopPropagation();
				nextNav();
				navLightbox();
			});
			$elk_prev_icon.on('click', function (event) {
				event.preventDefault();
				event.stopPropagation();
				prevNav();
				navLightbox();
			});
		};

	// Create the lightbox container only if needed
	if ($elk_lightbox.length <= 0) {
		// For easy manipulation
		$elk_lightbox = $('<div id="elk_lightbox"></div>');
		$elk_lb_content = $('<div id="elk_lb_content"></div>');

		$('body').append($elk_lightbox.append($elk_lb_content));
	}

	// Load the navigation array
	siblings.each(function () {
		navigation[navigation.length] = $(this).data('lightboximage');
	});

	// We should always have at least the thumbID
	if (navigation.length === 0) {
		navigation[navigation.length] = thumbID;
	}

	// Load and show the initial lightbox container div
	ajaxIndicatorOn();
	$elk_lightbox.fadeIn(200);
	openLightbox();
	expandLightbox();

	// Click anywhere on the page (except the expand icon) to close the lightbox
	$elk_lightbox.on('click', function (event) {
		if (event.target.id !== $elk_expand_icon.attr('id')) {
			event.preventDefault();
			closeLightbox();
		}
	});

	// Provide some keyboard navigation
	$(window).on('keydown.lb', function (event) {
		event.preventDefault();

		// escape
		if (event.keyCode === 27) {
			closeLightbox();
		}

		// left
		if (event.keyCode === 37) {
			prevNav();
			navLightbox();
		}

		// right
		if (event.keyCode === 39) {
			nextNav();
			navLightbox();
		}
	});

	// Make the image size fluid as the browser window changes
	$(window).on('resize.lb', function () {
		// Account for either a normal or expanded view
		var $_elk_lb_content = $('#elk_lb_content');

		if ($_elk_lb_content.hasClass('expand'))
			$_elk_lb_content.css({'height': window.innerHeight * 0.85, 'width': window.innerWidth * 0.9});
		else
			$('#elk_lb_img').css({'max-height': window.innerHeight * 0.9, 'max-width': window.innerWidth * 0.8});
	});

	// Swipe navigation start, record press x/y
	$(window).on('touchstart.lb', function (event) {
		xDown = event.originalEvent.touches[0].clientX;
		yDown = event.originalEvent.touches[0].clientY;
	});

	// Swipe navigation left / right detection
	$(window).on('touchmove.lb', function(event) {
		// No known start point ?
		if (!xDown || !yDown)
			return;

		// Where are we now
		var xUp = event.originalEvent.touches[0].clientX,
			yUp = event.originalEvent.touches[0].clientY,
			xDiff = xDown - xUp,
			yDiff = yDown - yUp;

		// Moved enough to know what direction they are swiping
		if (Math.abs(xDiff) > Math.abs(yDiff)) {
			if (xDiff > 0) {
				// Swipe left
				prevNav();
				navLightbox();
			} else {
				// Swipe right
				nextNav();
				navLightbox();
			}
		}

		// Reset values
		xDown = null;
		yDown = null;
	});

	return false;
}

/**
 * Expands an attachment thumbnail when its clicked
 *
 * @param {string} thumbID
 */
function expandThumb(thumbID)
{
	var img = document.getElementById('thumb_' + thumbID),
		link = document.getElementById('link_' + thumbID),
		name = link.nextSibling;

	// Some browsers will add empty text so loop to the next element node
	while (name && name.nodeType !== 1) {
		name = name.nextSibling;
	}
	var details = name.nextSibling;
	while (details && details.nodeType !== 1) {
		details = details.nextSibling;
	}

	// Save the currently displayed image attributes
	var tmp_src = img.src,
		tmp_height = img.style.height,
		tmp_width = img.style.width;

	// Set the displayed image attributes to the link attributes, this will expand in place
	img.src = link.href;
	img.style.width = link.style.width;
	img.style.height = link.style.height;

	// Swap the class name on the title/desc
	name.className = name.className.includes('_exp') ? 'attachment_name' : 'attachment_name attachment_name_exp';
	details.className = details.className.includes('_exp') ? 'attachment_details' : 'attachment_details attachment_details_exp';

	// Now place the image attributes back
	link.href = tmp_src;
	link.style.width = tmp_width;
	link.style.height = tmp_height;

	return false;
}

/**
 * Provides a way to toggle an ignored message(s) visibility
 *
 * @param {object} msgids
 * @param {string} text
 */
function ignore_toggles(msgids, text)
{
	for (var i = 0; i < msgids.length; i++)
	{
		var msgid = msgids[i];

		var discard = new elk_Toggle({
			bToggleEnabled: true,
			bCurrentlyCollapsed: true,
			aSwappableContainers: [
				'msg_' + msgid + '_extra_info',
				'msg_' + msgid,
				'msg_' + msgid + '_footer',
				'msg_' + msgid + '_quick_mod',
				'modify_button_' + msgid,
				'msg_' + msgid + '_signature'
			],
			aSwapLinks: [
				{
					sId: 'msg_' + msgid + '_ignored_link',
					msgExpanded: '',
					msgCollapsed: text
				}
			]
		});
	}
}

/**
 * Open the sendtopic overlay div
 * @todo make these... "things" look nice
 *
 * @param {type} desktopURL
 * @param {type} sHeader
 * @param {type} sIcon
 */
function sendtopicOverlayDiv(desktopURL, sHeader, sIcon)
{
	// Set up our div details
	var sAjax_indicator = '<div class="centertext"><i class="icon icon-spin i-spinner"></i></div>',
		oPopup_body;

	// TODO: Even if we weren't purging icons, this is still not the right icon for this.
	sIcon = typeof(sIcon) === 'string' ? sIcon : 'i-envelope';
	sHeader = typeof(sHeader) === 'string' ? sHeader : help_popup_heading_text;

	// Load the send topic overlay div
	$.ajax({
		url: desktopURL,
		type: "GET",
		dataType: "html"
	})
	.done(function (data) {
			var $base_obj = $('<div id="temp_help">').html(data).find('#send_topic'),
				title = '';

			$base_obj.find('h3').each(function () {
				title = $(this).text();
				$(this).remove();
			});

			var form = $base_obj.find('form'),
				url = $base_obj.find('form').attr('action');

			// Create the div that we are going to load
			var oContainer = new smc_Popup({heading: (title !== '' ? title : sHeader), content: sAjax_indicator, icon: sIcon});
			oPopup_body = $('#' + oContainer.popup_id).find('.popup_content');
			oPopup_body.html($base_obj.html());

			// Tweak the width of the popup for this special window
			$('.popup_window').css({'width': '640px'});

			sendtopicForm(oPopup_body, url, oContainer);
	})
	.fail(function (xhr, textStatus, errorThrown) {
			oPopup_body.html(textStatus);
	});

	return false;
}

/**
 * Helper function for sendtopicForm, highlights missing fields that must
 * be filled in in order to send the topic
 *
 * @param {type} $this_form
 * @param {string} classname
 * @param {boolean} focused
 */
function addRequiredElem($this_form, classname, focused)
{
	if (typeof(focused) === 'undefined')
		focused = false;

	$this_form.find('input[name="' + classname + '"]').after($('<span class="requiredfield" />').text(required_field).fadeIn());
	$this_form.find('input[name="' + classname + '"]').keyup(function () {
		$this_form.find('.' + classname + ' .requiredfield').fadeOut(function () {
			$(this).remove();
		});
	});

	if (!focused)
	{
		$this_form.find('input[name="' + classname + '"]').focus();
		focused = true;
	}
}

/**
 * Send in the send topic form
 *
 * @param {object} oPopup_body
 * @param {string} url
 * @param {object} oContainer
 */
function sendtopicForm(oPopup_body, url, oContainer)
{
	if (typeof(this_body) !== 'undefined')
		oPopup_body.html(this_body);

	var $this_form = $(oPopup_body).find('form');

	if (typeof(send_comment) !== 'undefined')
	{
		$this_form.find('input[name="comment"]').val(send_comment);
		$this_form.find('input[name="y_name"]').val(sender_name);
		$this_form.find('input[name="y_email"]').val(sender_mail);
		$this_form.find('input[name="r_name"]').val(recipient_name);
		$this_form.find('input[name="r_email"]').val(recipient_mail);
	}

	oPopup_body.find('input[name="send"]').on('click', function (event) {
		event.preventDefault();

		var data = $this_form.serialize() + '&send=1',
			sender_name = $this_form.find('input[name="y_name"]').val(),
			sender_mail = $this_form.find('input[name="y_email"]').val,
			recipient_name = $this_form.find('input[name="r_name"]').val(),
			recipient_mail = $this_form.find('input[name="r_email"]').val(),
			missing_elems = false;

		// Check for any input fields that were not filled in
		if (sender_name === '')
		{
			addRequiredElem($this_form, 'y_name', missing_elems);
			missing_elems = true;
		}

		if (sender_mail === '')
		{
			addRequiredElem($this_form, 'y_email', missing_elems);
			missing_elems = true;
		}

		if (recipient_name === '')
		{
			addRequiredElem($this_form, 'r_name', missing_elems);
			missing_elems = true;
		}

		if (recipient_mail === '')
		{
			addRequiredElem($this_form, 'r_email', missing_elems);
			missing_elems = true;
		}

		// Missing required elements, back we go
		if (missing_elems)
			return;

		// Send it to the server to validate the input
		$.ajax({
			type: 'post',
			url: url + ';api',
			data: data
		})
		.done(function (request) {
			var oElement = $(request).find('elk')[0],
				text = null;

			// No errors in the response, lets say it sent.
			if (oElement.getElementsByTagName('error').length === 0)
			{
				text = oElement.getElementsByTagName('text')[0].firstChild.nodeValue.removeEntities();
				text += '<br /><br /><input type="submit" name="send" value="' + sendtopic_back + '" class="button_submit"/><input type="submit" name="cancel" value="' + sendtopic_close + '" class="button_submit"/>';

				oPopup_body.html(text);

				// Setup the cancel button to end this entire process
				oPopup_body.find('input[name="cancel"]').each(function () {
					$(this).on('click', function (event) {
						event.preventDefault();
						oContainer.hide();
					});
				});
			}
			// Invalid data in the form, like a bad email etc, show the message text
			else
			{
				if (oElement.getElementsByTagName('text').length !== 0)
				{
					text = oElement.getElementsByTagName('text')[0].firstChild.nodeValue.removeEntities();
					text += '<br /><br /><input type="submit" name="send" value="' + sendtopic_back + '" class="button_submit"/><input type="submit" name="cancel" value="' + sendtopic_close + '" class="button_submit"/>';

					oPopup_body.html(text);
					oPopup_body.find('input[name="send"]').each(function () {
						$(this).on('click', function (event) {
							event.preventDefault();
							data = $(oPopup_body).find('form').serialize() + '&send=1';
							sendtopicForm(oPopup_body, url, oContainer);
						});
					});

					// Cancel means cancel
					oPopup_body.find('input[name="cancel"]').each(function () {
						$(this).on('click', function (event) {
							event.preventDefault();
							oContainer.hide();
						});
					});
				}

				if (oElement.getElementsByTagName('url').length !== 0)
				{
					var url_redir = oElement.getElementsByTagName('url')[0].firstChild.nodeValue;
					oPopup_body.html(sendtopic_error.replace('{href}', url_redir));
				}
			}
		})
		.fail(function() {
			oPopup_body.html(sendtopic_error.replace('{href}', url));
		});
	});
}



/**
 * Used to split a topic.
 * Allows selecting a message so it can be moved from the original to the spit topic or back
 *
 * @param {string} direction up / down / reset
 * @param {int} msg_id message id that is being moved
 */
function topicSplitselect(direction, msg_id)
{
	getXMLDocument(elk_prepareScriptUrl(elk_scripturl) + "action=splittopics;sa=selectTopics;subname=" + topic_subject + ";topic=" + topic_id + "." + start[0] + ";start2=" + start[1] + ";move=" + direction + ";msg=" + msg_id + ";xml", onTopicSplitReceived);
	return false;
}

/**
 * Callback function for topicSplitselect
 *
 * @param {xmlCallback} XMLDoc
 */
function onTopicSplitReceived(XMLDoc)
{
	var i,
		j,
		pageIndex;

	// Find the selected and not_selected page index containers
	for (i = 0; i < 2; i++)
	{
		pageIndex = XMLDoc.getElementsByTagName("pageIndex")[i];

		// Update the page container with our xml response
		document.getElementById("pageindex_" + pageIndex.getAttribute("section")).innerHTML = pageIndex.firstChild.nodeValue;
		start[i] = pageIndex.getAttribute("startFrom");
	}

	var numChanges = XMLDoc.getElementsByTagName("change").length,
		curChange,
		curSection,
		curAction,
		curId,
		curList,
		newItem,
		sInsertBeforeId,
		oListItems,
		right_arrow = '<i class="icon icon-lg i-chevron-circle-right"></i>',
		left_arrow = '<i class="icon icon-lg i-chevron-circle-left"></i>';

	// Loop through all of the changes returned in the xml response
	for (i = 0; i < numChanges; i++)
	{
		curChange = XMLDoc.getElementsByTagName("change")[i];
		curSection = curChange.getAttribute("section");
		curAction = curChange.getAttribute("curAction");
		curId = curChange.getAttribute("id");
		curList = document.getElementById("messages_" + curSection);

		// Remove it from the source list so we can insert it in the destination list
		if (curAction === "remove")
			curList.removeChild(document.getElementById(curSection + "_" + curId));
		// Insert a message.
		else
		{
			// By default, insert the element at the end of the list.
			sInsertBeforeId = null;

			// Loop through the list to try and find an item to insert after.
			oListItems = curList.getElementsByTagName("li");
			for (j = 0; j < oListItems.length; j++)
			{
				if (parseInt(oListItems[j].id.substr(curSection.length + 1)) < curId)
				{
					// This would be a nice place to insert the row.
					sInsertBeforeId = oListItems[j].id;

					// We're done for now. Escape the loop.
					j = oListItems.length + 1;
				}
			}

			// Let's create a nice container for the message.
			newItem = document.createElement("li");
			newItem.className = "";
			newItem.id = curSection + "_" + curId;
			newItem.innerHTML = '' +
				'<div class="content">' +
					'<div class="message_header">' +
						'<a class="split_icon float' + (curSection === "selected" ? "left" : "right") + '" href="' + elk_prepareScriptUrl(elk_scripturl) + 'action=splittopics;sa=selectTopics;subname=' + topic_subject + ';topic=' + topic_id + '.' + not_selected_start + ';start2=' + selected_start + ';move=' + (curSection === "selected" ? "up" : "down") + ';msg=' + curId + '" onclick="return topicSplitselect(\'' + (curSection === "selected" ? 'up' : 'down') + '\', ' + curId + ');">' +
							(curSection === "selected" ? left_arrow : right_arrow) +
						'</a>' +
						'<strong>' + curChange.getElementsByTagName("subject")[0].firstChild.nodeValue + '</strong> ' + txt_by + ' <strong>' + curChange.getElementsByTagName("poster")[0].firstChild.nodeValue + '</strong>' +
						'<br />' +
						'<em>' + curChange.getElementsByTagName("time")[0].firstChild.nodeValue + '</em>' +
					'</div>' +
					'<div class="post">' + curChange.getElementsByTagName("body")[0].firstChild.nodeValue + '</div>' +
				'</div>';

			// So, where do we insert it?
			if (typeof sInsertBeforeId === "string")
				curList.insertBefore(newItem, document.getElementById(sInsertBeforeId));
			else
				curList.appendChild(newItem);
		}
	}
}
