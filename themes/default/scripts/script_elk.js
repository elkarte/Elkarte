/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 */

/**
 * This file contains javascript utility functions specific to ElkArte
 */

/**
 * We like the globals cuz they is good to us
 */

/** global: notification_topic_notice, notification_board_notice, txt_mark_as_read_confirm, oRttime */
/** global: $editor_data, elk_scripturl, elk_smiley_url, elk_session_var, elk_session_id, elk_images_url */

/** global: XMLHttpRequest, ElkInfoBar */

/**
 * Sets code blocks such that resize vertical works as expected.  Done this way to avoid
 * page jumps to named anchors missing the target.
 */
function elk_codefix ()
{
	let codeBlock = document.querySelectorAll('.bbc_code');
	codeBlock.forEach((code) => {
		let style = window.getComputedStyle(code, null),
			height = parseInt(style.getPropertyValue('height'));

		if (code.scrollHeight > height)
		{
			code.style.maxHeight = 'none';
			code.style.height = height + 'px';
		}
		else
		{
			code.style.resize = 'none';
		}
	});
}

/**
 * Removes the read more overlay from quote blocks that do not need them, and for
 * ones that do, hides so the read more input can expand it out.
 */
function elk_quotefix ()
{
	let quotes = document.querySelectorAll('.quote-read-more');

	quotes.forEach((quote) => {
		let bbc_quote = quote.querySelector('.bbc_quote');

		if (bbc_quote.scrollHeight > bbc_quote.clientHeight)
		{
			bbc_quote.style.overflow = 'hidden';
		}
		else
		{
			let check = quote.querySelector('.quote-show-more');
			if (check)
			{
				check.remove();
			}
		}
	});
}

/**
 * Turn a regular url button in to an ajax request
 *
 * @param {HTMLLinkElement} btn string representing this, generally the anchor link tag <a class="" href="" onclick="">
 * @param {string} confirmation_msg_variable var name of the text sting to display in the "are you sure" box
 * @param {function} onSuccessCallback optional, a callback executed on successfully execute the AJAX call
 */
function toggleButtonAJAX (btn, confirmation_msg_variable = '', onSuccessCallback = null)
{
	fetch(btn.href + ';api=xml', {
		method: 'GET',
		headers: {
			'X-Requested-With': 'XMLHttpRequest',
			'Accept': 'application/xml'
		}
	})
		.then(response => response.text())
		.then(body => {
			if (body === '')
			{
				return;
			}

			let parser = new DOMParser(),
				doc = parser.parseFromString(body, 'application/xml'),
				oElement = doc.getElementsByTagName('elk')[0];

			// No errors
			if (oElement.getElementsByTagName('error').length === 0)
			{
				let text = oElement.getElementsByTagName('text'),
					url = oElement.getElementsByTagName('url'),
					confirm_elem = oElement.getElementsByTagName('confirm'),
					confirm_text;

				// Update the page so button/link/confirm/etc. reflect the new on or off status
				if (confirm_elem.length === 1)
				{
					confirm_text = confirm_elem[0].firstChild.nodeValue.replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&').replace(/&quot;/g, '"').replace(/&#039;/g, '\'');
				}

				let elems = document.getElementsByClassName(btn.className.replace(/(list|link)level\d/g, '').trim());
				Array.prototype.forEach.call(elems, function(el) {
					if (text.length === 1)
					{
						el.innerHTML = '<span>' + text[0].firstChild.nodeValue.replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&').replace(/&quot;/g, '"').replace(/&#039;/g, '\'') + '</span>';
					}

					if (url.length === 1)
					{
						el.href = url[0].firstChild.nodeValue.replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&').replace(/&quot;/g, '"').replace(/&#039;/g, '\'');
					}

					// Replaces the confirmation var text with the new one from the response to allow swapping on/off
					// @todo this appears to be the start of a confirmation dialog... needs finished.
					if (confirm_text !== '')
					{
						window[confirmation_msg_variable] = confirm_text.replace(/[\\']/g, '\\$&');
					}
				});
			}
			else
			{
				// Error returned from the called function, show an alert
				if (oElement.getElementsByTagName('text').length !== 0)
				{
					alert(oElement.getElementsByTagName('text')[0].firstChild.nodeValue.replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&').replace(/&quot;/g, '"').replace(/&#039;/g, '\''));
				}

				if (oElement.getElementsByTagName('url').length !== 0)
				{
					window.location.href = oElement.getElementsByTagName('url')[0].firstChild.nodeValue;
				}
			}

			if (onSuccessCallback !== null)
			{
				onSuccessCallback(btn, body, oElement.getElementsByTagName('error'));
			}
		})
		.catch(error => {
			// ajax failure code
			if ('console' in window && console.info)
			{
				console.info('Error:', error);
			}
		})
		.finally(() => {
			// turn off the indicator
			ajax_indicator(false);
		});

	return false;
}

/**
 * Helper function: displays and removes the ajax indicator and
 * hides some page elements inside "container_id"
 * Used by some (one at the moment) ajax buttons
 *
 * @todo it may be merged into the function if not used anywhere else
 *
 * @param {HTMLLinkElement|string} btn string representing this, generally the anchor link tag <a class="" href="" onclick="">
 * @param {string} container_id  css ID of the data container
 */
function toggleHeaderAJAX (btn, container_id)
{
	let body_template = '<div class="board_row centertext">{body}</div>';

	// Start the loading indicator
	ajax_indicator(true);

	fetch(btn.href + ';api=xml', {
		method: 'GET',
		headers: {
			'X-Requested-With': 'XMLHttpRequest',
			'Accept': 'application/xml'
		}
	})
		.then(response => {
			if (!response.ok)
			{
				// HTTP status was not OK. Throw error to reject the promise.
				throw new Error('HTTP error ' + response.status);
			}
			return response.text();
		})
		.then(request => {
			if (request === '')
			{
				return;
			}

			let parser = new DOMParser(),
				xmlDoc = parser.parseFromString(request, 'text/xml'),
				oElement = xmlDoc.getElementsByTagName('elk')[0];

			if (oElement.getElementsByTagName('error').length === 0)
			{
				let text_elem = oElement.getElementsByTagName('text'),
					body_elem = oElement.getElementsByTagName('body');

				document.querySelectorAll('#' + container_id + ' .pagesection').forEach(node => node.remove());
				document.querySelectorAll('#' + container_id + ' .topic_listing').forEach(node => node.remove());
				document.querySelectorAll('#' + container_id + ' .topic_sorting').forEach(node => node.remove());

				if (text_elem.length === 1)
				{
					document.querySelector('#' + container_id + ' .category_header').innerHTML = text_elem[0].firstChild.nodeValue;
				}

				if (body_elem.length === 1)
				{
					let newElement = document.createRange().createContextualFragment(body_template.replace('{body}', body_elem[0].firstChild.nodeValue));
					document.querySelector('.category_header').parentNode.insertBefore(newElement, document.querySelector('.category_header').nextSibling);
				}
			}
		})
		.catch((error) => {
			// Handle any error
			if ('console' in window && console.info)
			{
				console.info('Error:', error);
			}
		})
		.finally(() => {
			// Stop the loading indicator
			ajax_indicator(false);
		});
}

/**
 * Ajaxify the "notify" button in Display
 *
 * @param {string} btn string representing this, generally the anchor link tag <a class="" href="" onclick="">
 */
function notifyButton (btn)
{
	if (typeof (notification_topic_notice) !== 'undefined' && !confirm(notification_topic_notice))
	{
		return false;
	}

	return toggleButtonAJAX(btn, 'notification_topic_notice', function(btn, request, errors) {
		var toggle = 0;

		if (errors.length > 0)
		{
			return;
		}

		// This is a "turn notifications on"
		if (btn.href.indexOf('sa=on') !== -1)
		{
			toggle = 1;
		}
		else
		{
			toggle = 0;
		}

		document.querySelector('input[name=\'notify\']').value = toggle;
	});
}

/**
 * Ajaxify the "notify" button in MessageIndex
 *
 * @param {HTMLLinkElement} btn string representing this, generally the anchor link tag <a class="" href="" onclick="">
 */
function notifyboardButton (btn)
{
	if (typeof (notification_board_notice) !== 'undefined' && !confirm(notification_board_notice))
	{
		return false;
	}

	toggleButtonAJAX(btn, 'notification_board_notice');

	return false;
}

/**
 * Ajaxify the "unwatch" button in Display
 *
 * @param {HTMLLinkElement} btn string representing this, generally the anchor link tag <a class="" href="" onclick="">
 */
function unwatchButton (btn)
{
	toggleButtonAJAX(btn);

	return false;
}

/**
 * Ajaxify the "mark read" button in MessageIndex
 *
 * @param {HTMLLinkElement} btn string representing this, generally the anchor link tag <a class="" href="" onclick="">
 */
function markboardreadButton (btn)
{
	if (!confirm(txt_mark_as_read_confirm))
	{
		return false;
	}

	toggleButtonAJAX(btn);

	// Remove all the "new" icons next to the topics subjects
	let elements = document.querySelectorAll('.new_posts');
	elements.forEach((element) => {
		element.remove();
	});

	return false;
}

/**
 * Ajaxify the "mark all messages as read" button in BoardIndex
 *
 * @param {HTMLLinkElement} btn string representing this, generally the anchor link tag <a class="" href="" onclick="">
 */
function markallreadButton (btn)
{
	if (!confirm(txt_mark_as_read_confirm))
	{
		return false;
	}

	toggleButtonAJAX(btn);

	// Remove all the "new" icons next to the topics subjects
	let elems = document.querySelectorAll('.new_posts');
	[].forEach.call(elems, function(el) {
		el.classList.remove('new_posts');
	});

	// Turn the board icon class to off
	elems = document.querySelectorAll('.board_icon .i-board-new');
	[].forEach.call(elems, function(el) {
		el.classList.remove('i-board-new');
		el.classList.add('i-board-off');
	});

	elems = document.querySelectorAll('.board_icon .i-board-sub');
	[].forEach.call(elems, function(el) {
		el.classList.remove('i-board-sub');
		el.classList.add('i-board-off');
	});

	return false;
}

/**
 * Ajaxify the "mark all messages as read" button in Recent and Category View
 *
 * @param {HTMLLinkElement} btn string representing this, generally the anchor link tag <a class="" href="" onclick="">
 */
function markunreadButton (btn)
{
	if (!confirm(txt_mark_as_read_confirm))
	{
		return false;
	}

	toggleHeaderAJAX(btn, 'main_content_section');

	return false;
}

/**
 * Returns the mentions from a plugin.
 */
function getMentionsFromPlugin (all_elk, boundaries_pattern)
{
	let $editor = $editor_data[all_elk.selector],
		cached_names = $editor.opts.mentionOptions.cache.names,
		cached_queries = $editor.opts.mentionOptions.cache.queries,

		// Clean up the newlines and spacing to find the @mentions
		body = $editor.val().replace(/[\u00a0\r\n]/g, ' '),
		mentions = $($editor.opts.mentionOptions.cache.mentions);

	return validateMentions(body, cached_names, cached_queries, mentions, boundaries_pattern);
}

/**
 * Retrieves mentions from plain text.
 */
function getMentionsFromPlainText (all_elk, sForm, sInput, boundaries_pattern)
{
	let cached_names = all_elk.oMention.cached_names,
		cached_queries = all_elk.oMention.cached_queries,
		// Keep everything separated with spaces, not newlines or no breakable
		body = document.forms[sForm][sInput].value.replace(/[\u00a0\r\n]/g, ' '),
		mentions = $(all_elk.oMention.mentions);

	return validateMentions(body, cached_names, cached_queries, mentions, boundaries_pattern);
}

/**
 * Validates mentions in a given body of text.
 */
function validateMentions (body, cached_names, cached_queries, mentions, boundaries_pattern)
{
	body = ' ' + body + ' ';
	removeInvalidMentions(mentions, body, boundaries_pattern);

	cached_queries.forEach(query => {
		cached_names[query].forEach(name => {
			let pos = checkWordOccurrence(body, name.name);
			if (pos !== -1)
			{
				mentions.append($('<input type="hidden" name="uid[]" />').val(name.id));
			}
		});
	});
}

/**
 * Removes invalid mentions from a given list of mentions based on the provided body and boundaries pattern.
 */
function removeInvalidMentions (mentions, body, boundaries_pattern)
{
	$(mentions).find('input').each(function(idx, elem) {
		let name = $(elem).data('name'),
			next_char,
			prev_char,
			index = body.indexOf(name);

		if (typeof name !== 'undefined')
		{
			if (index === -1)
			{
				$(elem).remove();
			}
			else
			{
				next_char = body.charAt(index + name.length);
				prev_char = body.charAt(index - 1);

				if (next_char !== '' && next_char.search(boundaries_pattern) !== 0)
				{
					$(elem).remove();
				}
				else if (prev_char !== '' && prev_char.search(boundaries_pattern) !== 0)
				{
					$(elem).remove();
				}
			}
		}
	});
}

/**
 * Check whether the word exists in a given paragraph
 */
function checkWordOccurrence (paragraph, word)
{
	return paragraph.search(new RegExp(' @\\b' + word + '\\b[ .,;!?\'\\-\\\\\\/="]', 'iu'));
}

/**
 * This is called from the editor plugin or display.template to set where to
 * find the cache values for use in revalidateMentions
 *
 * @param {string} selector id of element that atWho is attached to
 * @param {object} oOptions only set when called from the plugin, contains those options
 */
var all_elk_mentions = [];

function add_elk_mention (selector, oOptions)
{
	// Global does not exist, hummm
	if (all_elk_mentions.hasOwnProperty(selector))
	{
		return;
	}

	// No options means its attached to the plain text box
	if (typeof oOptions === 'undefined')
	{
		oOptions = {};
	}
	oOptions.selector = selector;

	// Add it to the stack
	all_elk_mentions[all_elk_mentions.length] = {
		selector: selector,
		oOptions: oOptions
	};
}

/**
 * Revalidates mentions, called from post form submittals.
 *
 * - Checks for invalid mentions, ones that were selected but then had spacing/lettering changed
 * - Used to tag mentioned names when they are entered inline but NOT selected from the dropdown list.  In that
 * case the name must have appeared in the dropdown and be found in that cache list
 */
function revalidateMentions (sForm, sInput)
{
	let boundaries_pattern = /[ .,;!?'\-\\\/="]/i;
	all_elk_mentions.forEach(mention => {
		if (mention.selector === sInput || mention.selector === '#' + sInput)
		{
			if (mention.oOptions.isPlugin)
			{
				getMentionsFromPlugin(mention, boundaries_pattern);
			}
			else
			{
				getMentionsFromPlainText(mention, sForm, sInput, boundaries_pattern);
			}
		}
	});
}

/**
 * Expands the ... of the page indexes
 */
(function($) {
	const PER_PAGE_LIMIT = 10;

	// Used when the user clicks on the ... to expand
	$.fn.expand_pages = function() {
		function expand_pages ($element)
		{
			function createPage (i)
			{
				let bElem = aModel.clone(),
					boxModelClone = boxModel.clone();

				bElem.attr('href', baseurl.replace('%1$d', i - perPage)).text(i / perPage);
				boxModelClone.find('a').each(function() {
					$(this).replaceWith(bElem[0]);
				});
				$baseAppend.after(boxModelClone);
				return boxModelClone;
			}

			let $baseAppend = $($element.closest('.linavPages')),
				boxModel = $baseAppend.prev().clone(),
				aModel = boxModel.find('a').clone(),
				expandModel = $element.clone(),
				perPage = $element.data('perpage'),
				firstPage = $element.data('firstpage'),
				lastPage = $element.data('lastpage'),
				rawBaseurl = $element.data('baseurl'),
				baseurl = $element.data('baseurl').substring(1, $element.data('baseurl').length - 1),
				first,
				i,
				oldLastPage = 0;

			// Undo javascript escape
			baseurl = baseurl.replace('\' + elk_scripturl + \'', elk_scripturl);

			// Prevent too many pages to be loaded at once.
			if ((lastPage - firstPage) / perPage > PER_PAGE_LIMIT)
			{
				oldLastPage = lastPage;
				lastPage = firstPage + PER_PAGE_LIMIT * perPage;
			}

			// Calculate the new pages.
			for (i = lastPage; i > firstPage; i -= perPage)
			{
				if (typeof first === 'undefined')
				{
					first = createPage(i);
				}
				else
				{
					createPage(i);
				}
			}

			$baseAppend.remove();
			if (oldLastPage > 0)
			{
				expandModel.on('click', function(e) {
					let $currentElement = $(this);

					e.preventDefault();
					expand_pages($currentElement);
				})
					.data('perpage', perPage)
					.data('firstpage', lastPage)
					.data('lastpage', oldLastPage)
					.data('baseurl', rawBaseurl);
				first.after(expandModel);
			}
		}

		this.attr('tabindex', 0).on('click', function(e) {
			let $currentElement = $(this);
			e.preventDefault();
			expand_pages($currentElement);
		});
	};
})(jQuery);

/**
 * SiteTooltip, Basic JavaScript function to provide styled tooltips
 *
 * - shows the tooltip in a div with the class defined in tooltipClass
 * - moves all selector titles to a hidden div and removes the title attribute to
 *   prevent any default browser actions
 * - attempts to keep the tooltip on screen
 *
 */
class SiteTooltip
{
	constructor (settings = {})
	{
		this.defaults = {
			tooltipID: 'site_tooltip', // ID used on the outer div
			tooltipTextID: 'site_tooltipText', // ID on the inner div holding the text
			tooltipClass: 'tooltip', // The class applied to the sibling span, defaults provides a fade cover
			tooltipSwapClass: 'site_swaptip', // a class only used internally, change only if you have a conflict
			tooltipContent: 'html' // display captured title text as html or text
		};

		// Account for any user options
		this.settings = Object.assign({}, this.defaults, settings);
	}

	/**
	 * Creates tooltips for elements.
	 *
	 * @param {string} elem - The CSS selector of the elements to create tooltips for.
	 *
	 * @returns {null} - Returns null if the device is mobile or touch-enabled.
	 */
	create (elem)
	{
		// No need here
		if (is_mobile || is_touch)
		{
			return null;
		}

		// Move passed selector titles to a hidden span, then remove the selector title to prevent any default browser actions
		for (let el of document.querySelectorAll(elem))
		{
			let title = el.getAttribute('title');

			el.setAttribute('data-title', title);
			el.removeAttribute('title');
			el.addEventListener('mouseenter', this.showTooltip.bind(this));
			el.addEventListener('mouseleave', this.hideTooltip.bind(this));
		}
	}

	/**
	 * Positions the tooltip element relative to the provided event target.
	 *
	 * @param {Event} event - The event object that triggered the tooltip placement.
	 */
	positionTooltip (event)
	{
		let tooltip = document.getElementById(this.settings.tooltipID);
		if (!tooltip)
		{
			return;
		}

		let rect = event.target.getBoundingClientRect();
		let tooltipHeight = tooltip.offsetHeight;
		let viewportHeight = window.innerHeight;

		let x = rect.left;
		let y = window.scrollY + rect.bottom + 5;

		// Don't position below if it fall off-screen, instead move it above
		if (rect.bottom + 5 + tooltipHeight > viewportHeight)
		{
			y -= tooltipHeight + rect.height + 20;
		}

		tooltip.style.cssText = `left:${x}px; top:${y}px`;
	}

	/**
	 * Displays a tooltip on hover over an element.
	 *
	 * @param {Event} event - The event object.
	 */
	showTooltip (event)
	{
		if (this.tooltipTimeout)
		{
			clearTimeout(this.tooltipTimeout);
		}

		// Represents the timeout for showing a tooltip.
		this.tooltipTimeout = setTimeout(function() {
			let title = event.target.getAttribute('data-title');
			if (title)
			{
				// <div id="site_tooltip"><div id="site_tooltipText"><span class="tooltip"
				let tooltip = document.createElement('div');
				tooltip.id = this.settings.tooltipID;

				let tooltipText = document.createElement('div');
				tooltipText.id = this.settings.tooltipTextID;

				let span = document.createElement('span');
				span.className = this.settings.tooltipClass;

				// Create our element and append it to the body.
				tooltip.appendChild(tooltipText);
				tooltip.appendChild(span);
				document.getElementsByTagName('body')[0].appendChild(tooltip);

				// Load the tooltip content with our data-title
				if (this.settings.tooltipContent === 'html')
				{
					tooltipText.innerHTML = title;
				}
				else
				{
					tooltipText.innerText = title;
				}

				tooltip.style.display = 'block';
				this.positionTooltip(event);
			}
		}.bind(this), 1250);
	}

	/**
	 * Hides the tooltip.
	 *
	 * @param {Event} event - The event object.
	 */
	hideTooltip (event)
	{
		if (this.tooltipTimeout)
		{
			clearTimeout(this.tooltipTimeout);
		}

		let tooltip = document.getElementById(this.settings.tooltipID);
		if (tooltip)
		{
			tooltip.parentElement.removeChild(tooltip);
		}
	}
}

/**
 * Error box handler class
 *
 * @param {type} oOptions
 * @returns {errorbox_handler}
 */
var error_txts = {};

function errorbox_handler (oOptions)
{
	this.opt = oOptions;
	this.oError_box = null;
	this.oErrorHandle = window;
	this.init();
}

errorbox_handler.prototype.init = function() {
	this.oErrorHandle.instanceRef = this;
	if (this.oError_box === null)
	{
		this.oError_box = document.getElementById(this.opt.error_box_id);
	}
};

errorbox_handler.prototype.checkErrors = function(add = false) {
	let elem = document.getElementById(this.opt.error_box_id + '_' + this.opt.error_code);
	if (add)
	{
		this.addError(elem, this.opt.error_code);
	}
	else
	{
		this.removeError(this.oError_box, elem);
	}

	this.oError_box.className = 'infobox';
	// Hide show the error box based on if we have any errors
	if (this.oError_box.querySelectorAll('li').length === 0)
	{
		this.slideUp(this.oError_box);
	}
	// Populate the error and move into view
	else
	{
		this.slideDown(this.oError_box);
		document.getElementById(this.opt.error_box_id).scrollIntoView();
	}
};

errorbox_handler.prototype.addError = function(error_elem, error_code) {
	if (!error_elem)
	{
		// First error, then set up the list for insertion
		let errorList = this.oError_box.querySelector('#' + this.opt.error_box_id + '_list');
		if (!errorList || errorList.innerHTML.trim() === '')
		{
			let ul = document.createElement('ul');
			ul.id = this.opt.error_box_id + '_list';
			this.oError_box.appendChild(ul);
		}

		let li = document.createElement('li');
		li.style.display = 'none';
		li.id = this.opt.error_box_id + '_' + error_code;
		li.innerText = error_txts[error_code];
		document.getElementById(this.opt.error_box_id + '_list').appendChild(li);
		document.getElementById(this.opt.error_box_id + '_' + error_code).style.display = 'block';
	}
};

errorbox_handler.prototype.removeError = function(error_box, error_elem) {
	if (error_elem)
	{
		error_elem.style.display = 'none';
		error_elem.parentNode.removeChild(error_elem);
		if (error_box.querySelectorAll('li').length === 0)
		{
			this.slideUp(error_box);
		}
	}
};

errorbox_handler.prototype.slideUp = function(element) {
	element.style.transition = 'opacity 0.5s';
	element.style.opacity = '0';
};

errorbox_handler.prototype.slideDown = function(element) {
	element.style.transition = 'opacity 0.5s';
	element.style.opacity = '1';
};

/**
 * Shows the member search dropdown with the search options
 */
function toggle_mlsearch_opt ()
{
	var $_mlsearch = $('#mlsearch_options');

	// If the box is already visible just forget about it
	if ($_mlsearch.is(':visible'))
	{
		return;
	}

	// Time to show the droppy
	$_mlsearch.fadeIn('fast');

	// A click anywhere on the page will close the droppy
	$('body').on('click', mlsearch_opt_hide);

	// Except clicking on the box itself or into the search text input
	$('#mlsearch_options, #mlsearch_input').off('click', mlsearch_opt_hide).on('click', function(ev) {
		ev.stopPropagation();
	});
}

/**
 * Hides the member search dropdown and detach the body click event
 */
function mlsearch_opt_hide ()
{
	$('body').off('click', mlsearch_opt_hide);
	$('#mlsearch_options').slideToggle('fast');
}

/**
 * Attempt to prevent browsers from auto completing fields
 *
 * - when viewing/editing other members profiles
 * - when registering new member
 */
function disableAutoComplete ()
{
	window.onload = function() {
		// Turn off autocomplete for these elements
		const elements = document.querySelectorAll('input[type=email], .input_text, .input_clear');
		for (let item of elements)
		{
			item.setAttribute('autocomplete', 'off');
		}

		const passwordElements = document.querySelectorAll('input[type=password]');
		for (let item of passwordElements)
		{
			item.setAttribute('autocomplete', 'new-password');
		}

		// Chrome will fill out the form even with autocomplete off, so we need to clear the values as well
		setTimeout(function() {
			let clearElements = document.querySelectorAll('input[type=password], .input_clear');
			for (let item of clearElements)
			{
				item.value = '';
			}
		}, 1);
	};
}

/**
 * A system to collect notifications from a single AJAX call and redistribute them among notifiers
 */
(function() {
	/**
	 * ElkNotifications is a module that allows sending notifications to multiple notifiers.
	 * @returns {Object} - The ElkNotifications module.
	 */
	var ElkNotifications = (function(opt) {
		'use strict';

		opt = opt || {};
		let _notifiers = [],
			start = true,
			lastTime = 0;

		let init = function(opt) {
			if (typeof opt.delay === 'undefined')
			{
				start = false;
				opt.delay = 45000;
			}

			setTimeout(function() {
				fetchData();
			}, opt.delay);
		};

		let add = function(notif) {
			_notifiers.push(notif);
		};

		let send = function(request) {
			_notifiers.forEach((notification) => {
				notification.send(request);
			});
		};

		let fetchData = function() {
			if (_notifiers.length === 0)
			{
				return;
			}

			let url = elk_prepareScriptUrl(elk_scripturl) + 'action=mentions;sa=fetch;api=json;lastsent=' + lastTime;
			fetch(url, {
				cache: 'no-store',
				headers: {
					'Content-Type': 'application/json; charset=utf-8',
					'X-Requested-With': 'XMLHttpRequest',
				}
			})
				.then(function(response) {
					if (!response.ok)
					{
						throw new Error('HTTP error ' + response.status);
					}
					return response.json();
				})
				.then(function(request) {
					if (request !== '')
					{
						send(request);
						lastTime = request.timelast;
					}
				})
				.catch(function(error) {
					if ('console' in window && console.info)
					{
						console.info('Error:', error);
					}
				})
				.finally(function() {
					setTimeout(function() {
						fetchData();
					}, opt.delay);
				});
		};

		init(opt);
		return {
			add: add
		};
	});

	// AMD / RequireJS
	if (typeof define !== 'undefined' && define.amd)
	{
		define([], function() {
			return ElkNotifications;
		});
	}
	// CommonJS
	else if (typeof module !== 'undefined' && module.exports)
	{
		module.exports = ElkNotifications;
	}
	// included directly via <script> tag
	else
	{
		this.ElkNotifications = ElkNotifications;
	}

})();

var ElkNotifier = new window.ElkNotifications({});

/**
 * Initialize the ajax info-bar
 */
(function() {
	let ElkInfoBar = (function(elem_id, opt = {}) {
		let defaults = {
			text: '',
			class: 'ajax_infobar',
			hide_delay: 4000,
			error_class: 'error',
			success_class: 'success'
		};

		let settings = Object.assign({}, defaults, opt);

		let elem = document.getElementById(elem_id),
			time_out = null,
			init = function(elem_id, settings) {
				clearTimeout(time_out);
				if (elem === null)
				{
					elem = document.createElement('div');
					elem.id = elem_id;
					elem.className = settings.class;
					elem.innerHTML = settings.text + '<span class="icon i-concentric"></span>';
					document.body.appendChild(elem);
				}
			},
			changeText = function(text) {
				clearTimeout(time_out);
				elem.innerHTML = text;
				return this;
			},
			addClass = function(aClass) {
				elem.classList.add(aClass);
				return this;
			},
			removeClass = function(aClass) {
				elem.classList.remove(aClass);
				return this;
			},
			showBar = function() {
				clearTimeout(time_out);
				elem.style.opacity = '1';

				if (settings.hide_delay !== 0)
				{
					time_out = setTimeout(function() {
						hide();
					}, settings.hide_delay);
				}
				return this;
			},
			isError = function() {
				removeClass(settings.success_class);
				addClass(settings.error_class);
			},
			isSuccess = function() {
				removeClass(settings.error_class);
				addClass(settings.success_class);
			},
			hide = function() {
				// Short delay to avoid removing opacity while it is still be added
				window.setTimeout(function() {
					elem.style.opacity = '0';
				}, 300);

				clearTimeout(time_out);
				return this;
			};

		// Call the init function by default
		init(elem_id, settings);

		return {
			changeText: changeText,
			addClass: addClass,
			removeClass: removeClass,
			showBar: showBar,
			isError: isError,
			isSuccess: isSuccess,
			hide: hide
		};
	});

	// AMD / RequireJS
	if (typeof define !== 'undefined' && define.amd)
	{
		define([], function() {
			return ElkInfoBar;
		});
	}
	// CommonJS
	else if (typeof module !== 'undefined' && module.exports)
	{
		module.exports = ElkInfoBar;
	}
	// included directly via <script> tag
	else
	{
		this.ElkInfoBar = ElkInfoBar;
	}
})();

/**
 * Define the Elk_NewsFader function
 *
 * Inspired by Paul Mason's tutorial:
 * http://paulmason.name/item/simple-jquery-carousel-slider-tutorial
 *
 * Licensed under the MIT license:
 * http://www.opensource.org/licenses/mit-license.php
 */
function Elk_NewsFader (element, options)
{
	let settings = {iFadeDelay: 5000, iFadeSpeed: 1000},
		iFadeIndex = 0,
		news = document.getElementById(element).querySelectorAll('li');

	if (news.length > 1)
	{
		// Merge custom options with default settings
		Object.assign(settings, options);

		// Hide all news items except the first one
		for (let i = 1; i < news.length; i++)
		{
			news[i].style.opacity = '0';
		}

		// Set up the interval for fading news items
		setInterval(function() {
			let currentNews = news[iFadeIndex],
				nextNews = news[(iFadeIndex + 1) % news.length];

			// Fade out current news item
			currentNews.fadeOut(settings.iFadeSpeed, function() {
				// Fade in next news item
				nextNews.fadeIn(settings.iFadeSpeed);
			});

			// Update index for the next news item
			iFadeIndex = (iFadeIndex + 1) % news.length;
		}, settings.iFadeSpeed + settings.iFadeDelay);
	}
}

/**
 * This function regularly checks for the existence of a specific function in the global scope,
 * until either the function appears or a time limit passes. This could be helpful for scripts
 * that rely on deferred or asynchronously loaded scripts.
 *
 * isFunctionLoaded(NameOfFunction).then((available) => { if (available === true) DoStuff })
 *
 * @param {string} selector - The name of the function to check.
 * @param {number} [limit] - The maximum number of retries before considering the function as not loaded. Default is 180. Every 60 is ~ 1 second wait time.
 * @returns {Promise<boolean>} - A Promise that resolves to `true` if the function is loaded, or `false` if it is not loaded within the specified limit.
 */
async function isFunctionLoaded (selector, limit)
{
	let MAX_RETRIES = limit || 180;
	let retries = 0;
	while (typeof window[selector] !== 'function' && retries < MAX_RETRIES)
	{
		await new Promise(resolve => requestAnimationFrame(resolve));
		retries++;
	}

	if (retries < MAX_RETRIES)
	{
		return true;
	}

	return false;
}

/**
 * Debounces a function by delaying its execution until a certain amount of time has passed
 * without it being called again.
 *
 * This is useful for scenarios like search inputs or scroll events, where you want to wait for
 * the user to finish typing or scrolling before executing a function.
 *
 * https://github.com/you-dont-need/You-Dont-Need-Lodash-Underscore
 *
 * @param {Function} func - The function to be debounced.
 * @param {number} wait - The time delay in milliseconds.
 * @param {boolean} immediate - Determines whether the function should be executed immediately on the leading edge.
 * @return {Function} - The debounced function.
 */
function debounce (func, wait, immediate)
{
	var timeout;

	return function() {
		var context = this, args = arguments;

		clearTimeout(timeout);
		if (immediate && !timeout)
		{
			func.apply(context, args);
		}

		timeout = setTimeout(function() {
			timeout = null;
			if (!immediate)
			{
				func.apply(context, args);
			}
		}, wait);
	};
}

/**
 * Serializes a form or object into a URL-encoded string.
 *
 * @param {HTMLFormElement|FormData|object} form - The form element, or object to serialize.
 * @returns {string} - The serialized data as a URL-encoded string.
 */
function serialize (form)
{
	// Passed a form
	if (form instanceof HTMLFormElement)
	{
		const formData = new FormData(form);

		return new URLSearchParams(formData).toString();
	}

	// Passed formData?
	if (form instanceof FormData)
	{
		return new URLSearchParams(form).toString();
	}

	// Or an object of key->pairs
	let str = [];
	for (let key in form)
	{
		if (form.hasOwnProperty(key))
		{
			str.push(encodeURIComponent(key) + '=' + encodeURIComponent(form[key]));
		}
	}

	return str.join('&');
}

/**
 * Toggles the visibility and height of the specified HTMLElement using animation.
 *
 * Mimics jQ slideToggle, slideUp, slideDown, fadeIn, fadeOut
 *
 * https://github.com/ericbutler555/plain-js-slidetoggle
 * MIT License
 */
HTMLElement.prototype.slideToggle = function(duration, callback) {
	if (this.clientHeight === 0)
	{
		_s(this, duration, callback, true);
	}
	else
	{
		_s(this, duration, callback);
	}
};

HTMLElement.prototype.slideUp = function(duration, callback) {
	_s(this, duration, callback);
};

HTMLElement.prototype.slideDown = function(duration, callback) {
	_s(this, duration, callback, true);
};

HTMLElement.prototype.fadeIn = function(duration) {
	_s2(this, duration);
};

HTMLElement.prototype.fadeOut = function(duration, callback) {
	_s2(this, duration, callback, true);
};

/**
 * Animates the height, padding, and margin of an element.
 *
 * Intended to be a replacement for jQuery slideToggle, slideUp, slideDown
 *
 * @param {HTMLElement} el - The element to animate.
 * @param {number} [duration=400] - The duration of the animation in milliseconds.
 * @param {function} [callback] - The callback function to execute after the animation finishes.
 * @param {boolean} [isDown=false] - Determines if the animation expands the element or collapses it.
 * @private
 */
function _s (el, duration, callback, isDown)
{
	duration = duration || 300;
	isDown = isDown || false;

	el.style.overflow = 'hidden';
	if (isDown)
	{
		el.style.display = 'block';
		el.style.boxSizing = 'border-box';
	}

	let elStyles = window.getComputedStyle(el),
		// Current properties
		elHeight = parseFloat(elStyles.getPropertyValue('height')),
		elPaddingTop = parseFloat(elStyles.getPropertyValue('padding-top')),
		elPaddingBottom = parseFloat(elStyles.getPropertyValue('padding-bottom')),
		elMarginTop = parseFloat(elStyles.getPropertyValue('margin-top')),
		elMarginBottom = parseFloat(elStyles.getPropertyValue('margin-bottom')),
		// Transition steps
		stepHeight = elHeight / duration,
		stepPaddingTop = elPaddingTop / duration,
		stepPaddingBottom = elPaddingBottom / duration,
		stepMarginTop = elMarginTop / duration,
		stepMarginBottom = elMarginBottom / duration,
		// Animation timing
		start,
		elapsed;

	function step (timestamp)
	{
		if (start === undefined)
		{
			start = timestamp;
		}

		elapsed = timestamp - start;

		if (isDown)
		{
			el.style.height = (stepHeight * elapsed) + 'px';
			el.style.paddingTop = (stepPaddingTop * elapsed) + 'px';
			el.style.paddingBottom = (stepPaddingBottom * elapsed) + 'px';
			el.style.marginTop = (stepMarginTop * elapsed) + 'px';
			el.style.marginBottom = (stepMarginBottom * elapsed) + 'px';
		}
		else
		{
			el.style.height = elHeight - (stepHeight * elapsed) + 'px';
			el.style.paddingTop = elPaddingTop - (stepPaddingTop * elapsed) + 'px';
			el.style.paddingBottom = elPaddingBottom - (stepPaddingBottom * elapsed) + 'px';
			el.style.marginTop = elMarginTop - (stepMarginTop * elapsed) + 'px';
			el.style.marginBottom = elMarginBottom - (stepMarginBottom * elapsed) + 'px';
		}

		if (elapsed >= duration)
		{
			el.style.height = '';
			el.style.paddingTop = '';
			el.style.paddingBottom = '';
			el.style.marginTop = '';
			el.style.marginBottom = '';
			el.style.overflow = '';

			if (!isDown)
			{
				el.style.display = 'none';
			}

			if (typeof callback === 'function')
			{
				callback();
			}
		}
		else
		{
			window.requestAnimationFrame(step);
		}
	}

	window.requestAnimationFrame(step);
}

/**
 * Animates the opacity of an element to fadeIn or fadeOut an element
 *
 * @param {HTMLElement} element - The element to animate.
 * @param {number} [duration=1000] - The duration of the animation in milliseconds.
 * @param {Function} [callback] - A function to be called when the animation completes.
 * @param {boolean} [isOut=false] - Specifies whether the animation should fade out the element.
 * @private
 * @return {void}
 */
function _s2 (element, duration, callback, isOut)
{
	duration = duration || 1000;
	isOut = isOut || false;

	let initialOpacity = 0,
		finalOpacity = 1;

	if (isOut)
	{
		initialOpacity = 1;
		finalOpacity = 0;
	}

	let opacity = initialOpacity,
		opacityChangeFactor,
		start;

	function animateOpacity (timestamp)
	{
		if (start === undefined)
		{
			start = timestamp;
		}

		let progress = timestamp - start;
		opacity = progress / duration;
		opacityChangeFactor = isOut ? 1 - opacity : opacity;

		if (isOut && opacityChangeFactor > finalOpacity)
		{
			element.style.opacity = opacityChangeFactor;
			window.requestAnimationFrame(animateOpacity);
		}
		else if (!isOut && opacityChangeFactor < finalOpacity)
		{
			element.style.opacity = opacityChangeFactor;
			window.requestAnimationFrame(animateOpacity);
		}
		else
		{
			element.style.opacity = finalOpacity;
			if (typeof callback === 'function')
			{
				callback();
			}
		}
	}

	window.requestAnimationFrame(animateOpacity);
}
