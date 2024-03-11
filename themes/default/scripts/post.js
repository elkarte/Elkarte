/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 */

/** global: $editor_data, elk_scripturl, elk_session_var, elk_session_id */
/** global: poll_add, poll_remove, XMLHttpRequest */

/**
 * This file contains javascript associated with the posting and previewing
 */

/**
 * A q&d wrapper function to call the correct preview function
 * @todo could make this a class to be cleaner
 */

// These are variables the xml response is going to need
var bPost;

function previewControl ()
{
	// Lets make a background preview request
	bPost = false;

	// call the needed preview function
	switch (preview_area)
	{
		case 'pm':
			previewPM();
			break;
		case 'news':
			previewNews();
			break;
		case 'post':
			bPost = true;
			previewPost();
			break;
	}
	return false;
}

/**
 * Used to preview a post
 */
function previewPost ()
{
	// @todo Currently not sending poll options and option checkboxes.
	var textFields = [
		'subject', post_box_name, elk_session_var, 'icon', 'guestname', 'email', 'evtitle', 'question', 'topic'
	];
	var numericFields = [
		'board', 'topic', 'last_msg',
		'eventid', 'calendar', 'year', 'month', 'day',
		'poll_max_votes', 'poll_expire', 'poll_change_vote', 'poll_hide'
	];
	var checkboxFields = [
		'ns'
	];

	// Get the values from the form
	var x = [];
	x = getFields(textFields, numericFields, checkboxFields, form_name);

	sendXMLDocument(elk_prepareScriptUrl(elk_scripturl) + 'action=post2' + (current_board ? ';board=' + current_board : '') + (make_poll ? ';poll' : '') + ';preview;api=xml', x.join('&'), onDocSent);

	// Show the preview section and load it with "pending results" text, onDocSent will finish things off
	document.getElementById('preview_section').style.display = 'block';
	document.getElementById('preview_subject').innerHTML = txt_preview_title;
	document.getElementById('preview_body').innerHTML = txt_preview_fetch;

	return false;
}

/**
 * Used to preview a PM
 */
function previewPM ()
{
	// define what we want to get from the form
	let textFields = ['subject', post_box_name, 'to', 'bcc'],
		numericFields = ['recipient_to[]', 'recipient_bcc[]'],
		checkboxFields = ['outbox'];

	// And go get them
	let x = getFields(textFields, numericFields, checkboxFields, form_name);

	// Send in document for previewing
	sendXMLDocument(elk_prepareScriptUrl(elk_scripturl) + 'action=pm;sa=send2;preview;api=xml', x.join('&'), onDocSent);

	// Show the preview section and load it with "pending results" text, onDocSent will finish things off
	document.getElementById('preview_section').style.display = 'block';
	document.getElementById('preview_subject').innerHTML = txt_preview_title;
	document.getElementById('preview_body').innerHTML = txt_preview_fetch;

	return false;
}

/**
 * Used to preview a News item
 */
function previewNews ()
{
	// define what we want to get from the form
	var textFields = ['subject', post_box_name],
		numericFields = [],
		checkboxFields = ['send_html', 'send_pm'];

	// And go get them
	var x = [];
	x = getFields(textFields, numericFields, checkboxFields, form_name);
	x[x.length] = 'item=newsletterpreview';

	// Send in document for previewing
	sendXMLDocument(elk_prepareScriptUrl(elk_scripturl) + 'action=XmlPreview;api=xml', x.join('&'), onDocSent);

	// Show the preview section and load it with "pending results" text, onDocSent will finish things off
	document.getElementById('preview_section').style.display = 'block';
	document.getElementById('preview_subject').innerHTML = txt_preview_title;
	document.getElementById('preview_body').innerHTML = txt_preview_fetch;

	return false;
}

/**
 * Gets the form data for the selected fields so they can be posted via ajax
 *
 * @param {string[]} textFields
 * @param {string[]} numericFields
 * @param {string[]} checkboxFields
 * @param {string} form_name
 */
function getFields (textFields, numericFields, checkboxFields, form_name)
{
	var fields = [],
		i = 0,
		n = 0;

	// Get all the text fields
	for (i = 0, n = textFields.length; i < n; i++)
	{
		if (textFields[i] in document.forms[form_name])
		{
			// Handle the editor.
			if (textFields[i] === post_box_name && typeof $editor_data[post_box_name] !== 'undefined')
			{
				fields[fields.length] = textFields[i] + '=' + $editor_data[post_box_name].val().replace(/&#/g, '&#38;#').php_urlencode();
				fields[fields.length] = 'message_mode=' + $editor_data[post_box_name].inSourceMode();
			}
			else
			{
				fields[fields.length] = textFields[i] + '=' + document.forms[form_name][textFields[i]].value.replace(/&#/g, '&#38;#').php_urlencode();
			}
		}
	}

	// All of the numeric fields
	for (i = 0, n = numericFields.length; i < n; i++)
	{
		if (numericFields[i] in document.forms[form_name])
		{
			if ('value' in document.forms[form_name][numericFields[i]])
			{
				fields[fields.length] = numericFields[i] + '=' + parseInt(document.forms[form_name].elements[numericFields[i]].value);
			}
			else
			{
				for (var j = 0, num = document.forms[form_name][numericFields[i]].length; j < num; j++)
				{
					fields[fields.length] = numericFields[i] + '=' + parseInt(document.forms[form_name].elements[numericFields[i]][j].value);
				}
			}
		}
	}

	// And the checkboxes
	for (i = 0, n = checkboxFields.length; i < n; i++)
	{
		if (checkboxFields[i] in document.forms[form_name] && document.forms[form_name].elements[checkboxFields[i]].checked)
		{
			fields[fields.length] = checkboxFields[i] + '=' + document.forms[form_name].elements[checkboxFields[i]].value;
		}
	}

	// And some security
	fields[fields.length] = elk_session_var + '=' + elk_session_id;

	return fields;
}

/**
 * Callback function of the XMLhttp request
 *
 * @param {object} XMLDoc
 */
function onDocSent (XMLDoc)
{
	var i = 0,
		n = 0,
		numErrors = 0,
		numCaptions = 0,
		$editor;

	if (!XMLDoc || !XMLDoc.getElementsByTagName('elk')[0])
	{
		document.forms[form_name].preview.onclick = function() {
			return true;
		};
		document.forms[form_name].preview.click();
		return true;
	}

	// Read the preview section data from the xml response
	var preview = XMLDoc.getElementsByTagName('elk')[0].getElementsByTagName('preview')[0];

	// Load in the subject
	document.getElementById('preview_subject').innerHTML = preview.getElementsByTagName('subject')[0].firstChild.nodeValue;

	// Load in the body
	var bodyText = '';
	for (i = 0, n = preview.getElementsByTagName('body')[0].childNodes.length; i < n; i++)
	{
		bodyText += preview.getElementsByTagName('body')[0].childNodes[i].nodeValue;
	}

	document.getElementById('preview_body').innerHTML = bodyText;
	document.getElementById('preview_body').className = 'post';

	// Show a list of errors (if any).
	var errors = XMLDoc.getElementsByTagName('elk')[0].getElementsByTagName('errors')[0],
		errorList = '',
		errorCode = '',
		error_area = 'post_error',
		error_list = error_area + '_list',
		error_post = false;

	// @todo: this should stay together with the rest of the error handling or
	// should use errorbox_handler (at the moment it cannot be used because is not enough generic)
	for (i = 0, numErrors = errors.getElementsByTagName('error').length; i < numErrors; i++)
	{
		errorCode = errors.getElementsByTagName('error')[i].attributes.getNamedItem('code').value;
		if (errorCode === 'no_message' || errorCode === 'long_message')
		{
			error_post = true;
		}
		errorList += '<li id="' + error_area + '_' + errorCode + '" class="error">' + errors.getElementsByTagName('error')[i].firstChild.nodeValue + '</li>';
	}

	let oError_box = document.getElementById(error_area);
	let checkUl = oError_box.querySelector('#'.error_list);
	if (!checkUl)
	{
		oError_box.innerHTML = '<ul id=\'' + error_list + '\'></ul>';
	}

	// Add the error it and show it
	if (numErrors === 0)
	{
		oError_box.style.display = 'none';
	}
	else
	{
		document.getElementById(error_list).innerHTML = errorList;
		oError_box.className = parseInt(errors.getAttribute('serious')) === 0 ? 'warningbox' : 'errorbox';
		oError_box.style.display = '';
	}

	// Show a warning if the topic has been locked.
	if (bPost)
	{
		document.getElementById('lock_warning').style.display = parseInt(errors.getAttribute('topic_locked')) === 1 ? '' : 'none';
	}

	// Adjust the color of captions if the given data is erroneous.
	let captions = errors.getElementsByTagName('caption');
	for (i = 0, numCaptions = errors.getElementsByTagName('caption').length; i < numCaptions; i++)
	{
		if (document.getElementById('caption_' + captions[i].getAttribute('name')))
		{
			document.getElementById('caption_' + captions[i].getAttribute('name')).className = captions[i].getAttribute('class');
		}
	}

	$editor = $editor_container[post_box_name];

	if (error_post)
	{
		$editor.find('textarea, iframe').addClass('border_error');
	}
	else
	{
		$editor.find('textarea, iframe').removeClass('border_error');
	}

	// If this is a post preview, then we have some extra work to do
	if (bPost)
	{
		// Set the new last message id.
		if ('last_msg' in document.forms[form_name])
		{
			document.forms[form_name].last_msg.value = XMLDoc.getElementsByTagName('elk')[0].getElementsByTagName('last_msg')[0].firstChild.nodeValue;
		}

		var new_replies = [],
			ignored_replies = [],
			ignoring = null,
			newPosts = XMLDoc.getElementsByTagName('elk')[0].getElementsByTagName('new_posts')[0] ? XMLDoc.getElementsByTagName('elk')[0].getElementsByTagName('new_posts')[0].getElementsByTagName('post') : {length: 0},
			numNewPosts = newPosts.length;

		if (numNewPosts !== 0)
		{
			let newPostsHTML = '<span id="new_replies"><' + '/span>';
			for (i = 0; i < numNewPosts; i++)
			{
				new_replies[new_replies.length] = newPosts[i].getAttribute('id');

				ignoring = false;
				if (newPosts[i].getElementsByTagName('is_ignored')[0].firstChild.nodeValue !== '0')
				{
					ignored_replies[ignored_replies.length] = ignoring = newPosts[i].getAttribute('id');
				}

				newPostsHTML += '<div class="content forumposts"><div class="postarea2" id="msg' + newPosts[i].getAttribute('id') + '"><div class="keyinfo">';
				newPostsHTML += '<h3 class="floatleft"><span>' + txt_posted_by + '</span>&nbsp;' + newPosts[i].getElementsByTagName('poster')[0].firstChild.nodeValue + '&nbsp;-&nbsp;' + newPosts[i].getElementsByTagName('time')[0].firstChild.nodeValue;
				newPostsHTML += ' <span class="new_posts" id="image_new_' + newPosts[i].getAttribute('id') + '">' + txt_new + '</span></h3>';

				if (can_quote)
				{
					newPostsHTML += '<ul class="quickbuttons" id="msg_' + newPosts[i].getAttribute('id') + '_quote"><li class="listlevel1"><a href="#postmodify" onmousedown="return insertQuoteFast(' + newPosts[i].getAttribute('id') + ');" class="linklevel1 quote_button">' + txt_bbc_quote + '</a></li></ul>';
				}

				newPostsHTML += '</div>';

				if (ignoring)
				{
					newPostsHTML += '<div id="msg_' + newPosts[i].getAttribute('id') + '_ignored_prompt">' + txt_ignoring_user + '<a href="#" id="msg_' + newPosts[i].getAttribute('id') + '_ignored_link" class="hide linkbutton">' + show_ignore_user_post + '</a></div>';
				}

				newPostsHTML += '<div class="messageContent" id="msg_' + newPosts[i].getAttribute('id') + '_body">' + newPosts[i].getElementsByTagName('message')[0].firstChild.nodeValue + '</div></div></div>';
			}
			setOuterHTML(document.getElementById('new_replies'), newPostsHTML);
		}

		// Remove the new image from old-new replies!
		for (i = 0; i < new_replies.length; i++)
		{
			document.getElementById('image_new_' + new_replies[i]).style.display = 'none';
		}

		let numIgnoredReplies = ignored_replies.length;
		if (numIgnoredReplies !== 0)
		{
			for (i = 0; i < numIgnoredReplies; i++)
			{
				aIgnoreToggles[ignored_replies[i]] = new elk_Toggle({
					bToggleEnabled: true,
					bCurrentlyCollapsed: true,
					aSwappableContainers: [
						'msg_' + ignored_replies[i] + '_body',
						'msg_' + ignored_replies[i] + '_quote'
					],
					aSwapLinks: [
						{
							sId: 'msg_' + ignored_replies[i] + '_ignored_link',
							msgExpanded: '',
							msgCollapsed: show_ignore_user_post
						}
					]
				});
			}
		}
	}

	let element = document.getElementById('preview_section');
	window.scrollTo({top: element.offsetTop, behavior: 'smooth'});

	// Preview video links if the feature is available
	if (typeof $.fn.linkifyvideo === 'function')
	{
		$().linkifyvideo(oEmbedtext, 'preview_body');
	}

	// Spoilers, Sweetie
	document.querySelectorAll('.spoilerheader').forEach(element => {
		element.addEventListener('click', function() {
			element.nextElementSibling.children[0].slideToggle(250);
		});
	});

	// Show more quote blocks
	if (typeof elk_quotefix === 'function')
	{
		elk_quotefix();
	}

	// Fix and Prettify code blocks
	if (typeof elk_codefix === 'function')
	{
		elk_codefix();
	}

	if (typeof prettyPrint === 'function')
	{
		prettyPrint();
	}

	// Prevent lightbox or default action on the preview
	$('[data-lightboximage]').on('click.elk_lb', function(e) {
		e.preventDefault();
	});
}

/**
 * Add additional poll option fields
 */
function addPollOption ()
{
	let pollTabIndex;

	if (pollOptionNum === 0)
	{
		for (let i = 0, n = document.forms[form_name].elements.length; i < n; i++)
		{
			if (document.forms[form_name].elements[i].id.substring(0, 8) === 'options-')
			{
				pollOptionNum++;
				pollTabIndex = document.forms[form_name].elements[i].tabIndex;
			}
		}
	}

	pollOptionNum++;
	pollOptionId++;
	pollTabIndex++;

	setOuterHTML(document.getElementById('pollMoreOptions'), '<li><label for="options-' + pollOptionId + '">' + txt_option + ' ' + pollOptionNum + '</label>: <input type="text" name="options[' + pollOptionId + ']" id="options-' + pollOptionId + '" value="" size="80" maxlength="255" tabindex="' + pollTabIndex + '" class="input_text" /></li><li id="pollMoreOptions"></li>');
}

/**
 * Add additional attachment selection boxes
 */
function addAttachment ()
{
	/** global: allowed_attachments */
	allowed_attachments -= 1;
	/** global: current_attachment */
	current_attachment += 1;

	if (allowed_attachments <= 0)
	{
		return alert(txt_more_attachments_error);
	}

	setOuterHTML(document.getElementById('moreAttachments'), '<dd class="smalltext"><input type="file" size="60" name="attachment[]" id="attachment' + current_attachment + '" class="input_file" /> (<a href="javascript:void(0);" onclick="cleanFileInput(\'attachment' + current_attachment + '\');">' + txt_clean_attach + '<\/a>)' + '<\/dd><dd class="smalltext" id="moreAttachments"><a href="#" onclick="addAttachment(); return false;">(' + txt_more_attachments + ')</a></dd>');

	return true;
}

/**
 * A function used to clear the attachments on post page.  For security reasons
 * browsers don't let you set the value of a file input, even to an empty string
 * so this work around lets the user clear a choice.
 *
 * @param {string} idElement
 */
function cleanFileInput (idElement)
{
	let oElement = document.getElementById(idElement),
		parentForm = document.createElement('form');

	oElement.parentNode.insertBefore(parentForm, oElement);
	parentForm.appendChild(oElement);
	parentForm.reset();

	parentForm.parentNode.insertBefore(oElement, parentForm);
	parentForm.parentNode.removeChild(parentForm);
}

/**
 * Insert a quote to the editor via ajax.  Scroll the editor into view
 *
 * @param {string} messageid
 */
function insertQuoteFast (messageid)
{
	getXMLDocument(elk_prepareScriptUrl(elk_scripturl) + 'action=quotefast;quote=' + messageid + ';api=xml;pb=' + post_box_name + ';mode=0', onDocReceived);

	return true;
}

/**
 * callback for the quotefast function
 *
 * @param {object} XMLDoc
 */
function onDocReceived (XMLDoc)
{
	let text = '',
		$editor = $editor_data[post_box_name];

	for (let i = 0, n = XMLDoc.getElementsByTagName('quote')[0].childNodes.length; i < n; i++)
	{
		text += XMLDoc.getElementsByTagName('quote')[0].childNodes[i].nodeValue;
	}

	$editor.insert(text);

	// In wizzy mode, we need to move the cursor out of the quote block
	let
		rangeHelper = $editor.getRangeHelper(),
		parent = rangeHelper.parentNode();

	if (parent && parent.nodeName === 'BLOCKQUOTE')
	{
		let range = rangeHelper.selectedRange();

		range.setStartAfter(parent);
		rangeHelper.selectRange(range);
	}
	else
	{
		$editor.insert('\n');
	}

	document.getElementById('editor_toolbar_container').scrollIntoView();

	ajax_indicator(false);
}

/**
 * The actual message icon selector, shows the chosen icon on the post screen
 */
function showimage ()
{
	document.images.icons.src = icon_urls[document.forms.postmodify.icon.options[document.forms.postmodify.icon.selectedIndex].value];
}

/**
 * When using Go Back due to fatal_error, allows the form to be re-submitted with change
 */
function reActivate ()
{
	document.forms.postmodify.message.readOnly = false;
}

/**
 * Function to request a set of drafts for a topic
 */
function loadDrafts ()
{
	let textFields = [],
		numericFields = ['board', 'topic'],
		checkboxFields = [],
		formValues = [];

	// Get the values from the form
	formValues = getFields(textFields, numericFields, checkboxFields, form_name);
	formValues[formValues.length] = 'load_drafts=1';

	sendXMLDocument(elk_prepareScriptUrl(elk_scripturl) + 'action=post2;api=xml', formValues.join('&'), onDraftsReturned);
}

/**
 * Callback used by loadDrafts, loads the draft section area with data and shows the box
 *
 * @param oXMLDoc
 * @returns {boolean}
 */
function onDraftsReturned (oXMLDoc)
{
	let drafts = oXMLDoc.getElementsByTagName('drafts')[0].getElementsByTagName('draft'),
		thisDL = document.getElementById('draft_selection'),
		subject,
		time,
		link,
		n,
		i;

	// No place ot add the data !
	if (thisDL === null)
	{
		return false;
	}

	// Make sure the list is empty
	while (thisDL.childNodes.length > 1)
	{
		thisDL.removeChild(thisDL.lastChild);
	}

	// Add each draft to the selection area
	for (i = 0, n = drafts.length; i < n; i++)
	{
		let newDT = document.createElement('dt'),
			newDD = document.createElement('dd');

		subject = drafts[i].getElementsByTagName('subject')[0].textContent;
		time = drafts[i].getElementsByTagName('time')[0].textContent;
		link = drafts[i].getElementsByTagName('link')[0].textContent;

		newDT.innerHTML = link;
		newDD.innerHTML = time;

		thisDL.appendChild(newDT);
		thisDL.appendChild(newDD);
	}

	// Show the selection div and navigate to it
	if (n > 0)
	{
		let container = document.getElementById('postDraftContainer');
		container.classList.remove('hide');
		container.scrollIntoView();
	}

	return false;
}

/**
 * Dynamic checks for empty subject or body on post submit.
 *
 * - These are also checked server side but this provides a nice current page reminder.
 * - If empty fields are found will use errorbox_handler to populate error(s)
 * - If empty adds listener to fields to clear errors as they are fixed
 *
 * @returns {boolean} if false will block post submit
 */
function onPostSubmit ()
{
	let body = $editor_data[post_box_name].val().trim(),
		subject = document.getElementById('post_subject').value.trim();

	let error = new errorbox_handler({
		error_box_id: 'post_error',
		error_code: 'no_message',
	});

	// Clear or set
	error.checkErrors(body === '');
	if (body === '')
	{
		$editor_data[post_box_name].addEvent(post_box_name, 'keyup', function() {
			onPostSubmit();
		});
	}

	error = new errorbox_handler({
		error_box_id: 'post_error',
		error_code: 'no_subject',
	});

	// Clear or set
	error.checkErrors(subject === '');
	if (subject === '')
	{
		document.getElementById('post_subject').setAttribute('onkeyup', 'onPostSubmit()');
	}

	return subject !== '' && body !== '';
}

/**
 * Called when the add/remove poll button is pressed from the post screen
 *
 * Used to add/remove poll input area above the post new topic screen
 * Updates the message icon to the poll icon
 * Swaps poll button to match the current conditions
 *
 * @param {object} button
 * @param {int} id_board
 * @param {string} form_name
 */
function loadAddNewPoll (button, id_board, form_name)
{
	if (typeof id_board === 'undefined')
	{
		return true;
	}

	// Find the form and add poll to the url
	let form = document.querySelector('#post_header').closest('form'),
		poll_main_option = document.querySelectorAll('#poll_main, #poll_options');

	// Change the button label
	if (button.value === poll_add)
	{
		button.value = poll_remove;

		// We usually like to have the poll icon associated to polls,
		// but only if the currently selected is the default one
		let pollIcon = document.querySelector('#icon');
		if (pollIcon.value === 'xx')
		{
			pollIcon.value = 'poll';
			pollIcon.dispatchEvent(new Event('change'));
		}

		// Add poll to the form action
		form.setAttribute('action', form.getAttribute('action') + ';poll');

		// If the form already exists...just show it back and go out
		if (document.querySelector('#poll_main'))
		{
			poll_main_option.forEach(elem => {
				elem.querySelectorAll('input').forEach(function(input) {
					if (input.dataset.required === 'required')
					{
						input.setAttribute('required', 'required');
					}
				});

				elem.style.display = 'block';
			});

			return false;
		}
	}
	// Remove the poll section
	else
	{
		let icon = document.querySelector('#icon');
		if (icon.value === 'poll')
		{
			icon.value = 'xx';
			icon.dispatchEvent(new Event('change'));
		}

		// Remove poll to the form action
		form.setAttribute('action', form.getAttribute('action').replace(';poll', ''));

		poll_main_option.forEach(elem => {
			elem.style.display = 'none';

			elem.querySelectorAll('input').forEach(function(input) {
				if (input.getAttribute('required') === 'required')
				{
					input.dataset.required = 'required';
					input.removeAttribute('required');
				}
			});
		});

		button.value = poll_add;

		return false;
	}

	// Retrieve the poll area
	let max_tabIndex = 0;

	ajax_indicator(true);
	fetch(elk_prepareScriptUrl(elk_scripturl) + 'action=poll;sa=interface;board=' + id_board, {
		method: 'GET',
		headers: {
			'X-Requested-With': 'XMLHttpRequest',
		}
	})
		.then(response => {
			if (!response.ok)
			{
				throw new Error('HTTP error ' + response.status);
			}
			return response.text();
		})
		.then(data => {
			// Find the highest tabindex already present
			for (let i = 0, n = document.forms[form_name].elements.length; i < n; i++)
			{
				max_tabIndex = Math.max(max_tabIndex, document.forms[form_name].elements[i].tabIndex);
			}

			// Inject the html
			document.querySelector('#post_header').insertAdjacentHTML('afterend', data);
			let inputs = document.querySelectorAll('#poll_main input, #poll_options input');
			for (let input of inputs)
			{
				input.tabIndex = ++max_tabIndex;
			}

			// Repeated collapse/expand of fieldsets as above
			let legend = document.querySelectorAll('#poll_main legend, #poll_options legend');
			for (let lg of legend)
			{
				lg.addEventListener('click', function() {
					this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'none' ? 'inline-block' : 'none';
					this.parentElement.classList.toggle('collapsed');
				});

				if (lg.dataset.collapsed)
				{
					lg.nextElementSibling.style.display = 'none';
					lg.parentElement.classList.toggle('collapsed');
				}
			}
		})
		.catch(error => {
			if ('console' in window && console.error)
			{
				console.error('Error : ', error);
			}
		})
		.finally(() => {
			ajax_indicator(false);
		});

	return false;
}
