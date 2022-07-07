/*!
 * @package   Quick Quote
 * @copyright Frenzie : Frans de Jonge
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 */

/**
 * This particular function was based on My Opera Enhancements and is
 * licensed under the BSD license.
 *
 * Some specific refactoring done for ElkArte core inclusion
 */
function Elk_QuickQuote(oOptions)
{
	'use strict';

	this.opts = Object.assign({}, this.defaults, oOptions);

	this.pointerDirection = 'right';
	this.startPointerX = 0;
	this.endPointerX = 0;

	this.init();
}

/**
 * Get things rolling
 */
Elk_QuickQuote.prototype.init = function ()
{
	this.treeToBBCode.defaults = {
		strong: {before: '[b]', after: '[/b]'},
		b: {before: '[b]', after: '[/b]'},
		i: {before: '[i]', after: '[/i]'},
		em: {before: '[i]', after: '[/i]'},
		s: {before: '[s]', after: '[/s]'},
		sup: {before: '[sup]', after: '[/sup]'},
		sub: {before: '[sub]', after: '[/sub]'},
		pre: {before: '[code]', after: '[/code]'},
		br: {before: '\n', after: ''}
	};

	this.postSelector = document.getElementById('topic_summary') ? '.postarea2' : '.postarea';

	// Check if passive is supported, should be for most browsers since 2016
	let supportsPassive = false;
	try {
		let opts = Object.defineProperty({}, 'passive', {get: function() {supportsPassive = true;}});
		window.addEventListener('test', null, opts);
	} catch (e) {}

	// Pointer event capabilities
	let hasPointerEvents = (('PointerEvent' in window) || (window.navigator && 'msPointerEnabled' in window.navigator));
	this.mouseDown = hasPointerEvents ? 'pointerdown' : is_touch ? 'touchstart' : 'mousedown';
	this.mouseUp = hasPointerEvents ? 'pointerup' : is_touch ? 'touchend' : 'mouseup';

	// Initialize Quick Quote, set event listener to all messageContent areas
	document.querySelectorAll('.messageContent').forEach((message) =>
	{
		message.addEventListener(this.mouseDown, this.getEventStartPosition.bind(this), supportsPassive ? {passive: true} : false);
		message.addEventListener(this.mouseUp, this.getEventEndPosition.bind(this), supportsPassive ? {passive: true} : false);
		message.addEventListener(this.mouseUp, this.prepareQuickQuoteButton.bind(this), supportsPassive ? {passive: true} : false);

		// Needed for android touch chrome as the mouseUp event is held by the context menu ribbon
		if (is_touch)
		{
			message.addEventListener('contextmenu', this.prepareQuickQuoteButton.bind(this), false);
		}
	});
};

/**
 * Get the X coordinate of the pointer down event
 *
 * @param {TouchEvent|MouseEvent} event
 */
Elk_QuickQuote.prototype.getEventStartPosition = function (event)
{
	if (typeof event.changedTouches !== 'undefined')
	{
		this.startPointerX = event.changedTouches[0].pageX;
	}
	else
	{
		this.startPointerX = event.clientX;
	}
};

/**
 * Get the X coordinate of the pointer up event, Set right or left for movement
 *
 * @param {TouchEvent|MouseEvent} event
 */
Elk_QuickQuote.prototype.getEventEndPosition = function (event)
{
	if (typeof event.changedTouches !== 'undefined')
	{
		this.endPointerX = event.changedTouches[0].pageX;
	}
	else
	{
		this.endPointerX = event.clientX;
	}

	this.pointerDirection = this.endPointerX > this.startPointerX ? 'right' : 'left';
};

/**
 * Determine the window position of the selected text.  If the selection
 * can not be determined (multi click or other) then the event location would be used.
 *
 * @param {PointerEvent} event
 * @return {Object} Returns the x and y position
 */
Elk_QuickQuote.prototype.getEventPosition = function (event)
{
	// Set an approximate position as a backup
	let posRight = window.innerWidth - event.pageX - 10,
		posLeft = event.pageX,
		posBottom = event.pageY + 15,
		posTop = event.pageY - 5;

	let selectionRange = window.getSelection().getRangeAt(0).cloneRange(),
		relativePos = document.body.parentNode.getBoundingClientRect();

	// Collapse on start or end based on pointer movement direction
	selectionRange.collapse(this.pointerDirection === 'left');
	let selectionBox = selectionRange.getClientRects();

	if (selectionBox.length > 0)
	{
		posRight = -Math.round(selectionBox[0].right - relativePos.right);
		posLeft = Math.round(selectionBox[0].left - relativePos.left);

		posBottom = Math.round(selectionBox[0].bottom - relativePos.top + 5);
		posTop = Math.round(selectionBox[0].top - relativePos.top - 5);
	}

	return {
		right: posRight,
		left: posLeft,
		bottom: posBottom,
		top: posTop
	};
};

/**
 * Positions the button close to the mouse/touch up location for best
 * user interaction
 *
 * @param {PointerEvent} event The event
 * @param {HTMLElement} button The element to position
 */
Elk_QuickQuote.prototype.setButtonPosition = function (event, button)
{
	let clickCoords = this.getEventPosition(event),
		buttonBottom = clickCoords.bottom + button.offsetHeight,
		windowBottom = window.scrollY + window.innerHeight;

	// Don't go off the bottom of the viewport
	if (buttonBottom > windowBottom)
	{
		button.style.top = clickCoords.top - button.offsetHeight + 'px';
	}
	else
	{
		button.style.top = clickCoords.bottom + 'px';
	}

	// For touch devices we need to account for selection bounding handles.  There is not a consistent
	// way to disable the default selection menu, so positioning below the text + handles is the
	// only available option
	if (is_touch)
	{
		button.style.top = clickCoords.bottom + 25 + 'px';
	}

	// Don't go outside our message area
	// @todo simplify
	let postPos = event.currentTarget.getBoundingClientRect();
	if (this.pointerDirection === 'right')
	{
		if (clickCoords.left - button.offsetWidth < postPos.left)
		{
			let shift = postPos.left - (clickCoords.left - button.offsetWidth);
			button.style.right = Math.round(clickCoords.right - shift - 10) + 'px';
		}
		else
		{
			button.style.right = clickCoords.right + "px";
		}
	}
	else
	{
		if (clickCoords.left + button.offsetWidth > postPos.right)
		{
			let shift = (clickCoords.left + button.offsetWidth) - postPos.right;
			button.style.right = Math.round(clickCoords.right - shift - 10) + "px";
		}
		else
		{
			button.style.right = clickCoords.right - button.offsetWidth + "px";
		}
	}
};

/**
 * Transverses tree under node and set a flag telling whether element is hidden or not
 *
 * @param {object} node
 */
Elk_QuickQuote.prototype.setHiddenFlag = function (node)
{
	if (!node)
	{
		return;
	}

	if (typeof node.item === 'function')
	{
		node.forEach((asNode) =>
		{
			this.setHiddenFlag(asNode);
		});
	}
	else if (this.isHidden(node) !== '')
	{
		node.setAttribute('userjsishidden', 'true');
	}
	else
	{
		if (node.removeAttribute)
		{
			node.removeAttribute('userjsishidden');
		}

		if (node.childNodes)
		{
			this.setHiddenFlag(node.childNodes);
		}
	}
};

/**
 * Tells if element should be considered as not visible
 *
 * @param {Node} node
 * @returns {string}
 */
Elk_QuickQuote.prototype.isHidden = function (node)
{
	if (node && node.nodeType === Node.ELEMENT_NODE)
	{
		let compStyles = getComputedStyle(node, '');

		if (node.nodeName.toLowerCase() === 'br') return '';
		if (compStyles.display === 'none') return 'display:none';
		if (compStyles.visibility === 'hidden') return 'visibility:hidden';
		if (parseFloat(compStyles.opacity) < 0.1) return 'opacity';
		if (node.offsetHeight < 4) return 'offsetHeight';
		if (node.offsetWidth < 4) return 'offsetWidth';

		return '';
	}

	return '';
};

/**
 * Compares CSS properties against a predefined array
 *
 * @param {object} node
 * @param {array} props
 * @returns {{start: string, end: string}}
 */
Elk_QuickQuote.prototype.checkCSSProps = function (node, props)
{
	let start = '',
		end = '',
		value;

	props.forEach((prop) =>
	{
		// Check for class name
		if (typeof prop.isClass !== 'undefined')
		{
			value = node.classList.contains(prop.name) ? prop.name : '';
		}
		// Or style attribute
		else
		{
			value = this.trim(node.style[prop.name] || '', ' "');
		}

		if ((prop.forceValue && value === prop.forceValue) || (!prop.forceValue && value))
		{
			start += prop.before.replace('@value', (prop.values ? prop.values[value] : null) || value);
			end += prop.after;
		}
	});

	return {start: start, end: end};
};

/**
 * Parses the tree into bbcode
 *
 * @param {object} node
 * @returns {string}
 */
Elk_QuickQuote.prototype.treeToBBCode = function (node)
{
	let checked,
		start,
		end,
		bb = [],
		props = [];

	if (typeof node.item === 'function')
	{
		node.forEach((asNode) =>
		{
			bb.push(this.treeToBBCode(asNode));
		});

		return bb.join('');
	}

	if (node.getAttribute && node.getAttribute('userjsishidden') === 'true')
	{
		return '';
	}

	switch (node.nodeType)
	{
		// nodeType 1, like div, p, ul
		case Node.ELEMENT_NODE:
			let nodeName = node.nodeName.toLowerCase(),
				def = this.treeToBBCode.defaults[nodeName];

			// Generic wrap behavior for basic BBC tags like [b], [i], [u]
			if (def)
			{
				bb.push(def.before || '');
				bb.push(this.treeToBBCode(node.childNodes));
				bb.push(def.after || '');
			}
			// Special Processing cases
			else
			{
				switch (nodeName)
				{
					case 'a':
						if (node.href.indexOf('mailto:') === 0)
						{
							bb.push('[email=' + node.href.substring(7) + ']');
							bb.push(this.treeToBBCode(node.childNodes));
							bb.push('[/email]');
						}
						else if (node.className.indexOf("attach") >= 0)
						{
							bb.push('[attach=' + node.href + ']');
							bb.push(this.treeToBBCode(node.childNodes));
							bb.push('[/attach]');
						}
						else
						{
							bb.push('[url=' + node.href + ']');
							bb.push(this.treeToBBCode(node.childNodes));
							bb.push('[/url]');
						}
						break;
					case 'div':
						props = [
							{name: 'textAlign', forceValue: 'left', before: '[left]', after: '[/left]'},
							{name: 'textAlign', forceValue: 'right', before: '[right]', after: '[/right]'},
							{name: 'centertext', before: '[center]', after: '[/center]', isClass: true},
						];
						checked = this.checkCSSProps(node, props);

						bb.push(checked.start);
						bb.push(this.treeToBBCode(node.childNodes));
						bb.push(checked.end);
						break;
					case 'img':
						let smileyCode = this.getSmileyCode(node);

						bb.push(smileyCode ? ' ' + smileyCode + ' ' : '[img]' + node.src + '[/img]');
						break;
					case 'ul':
						props = [
							{
								name: 'listStyleType',
								forceValue: 'decimal',
								before: '[list type=decimal]',
								after: '[/list]'
							},
						];
						checked = this.checkCSSProps(node, props);

						bb.push((checked.start !== '') ? checked.start : '[list]');

						let lis = node.querySelectorAll('li');

						lis.forEach((li) =>
						{
							bb.push('\n  [*] ' + this.trim(this.treeToBBCode(li)));
						});

						bb.push('[/list]');
						break;
					case 'span':
						// Check for css properties
						props = [
							{name: 'textDecoration', forceValue: 'underline', before: '[u]', after: '[/u]'},
							{name: 'color', before: '[color=@value]', after: '[/color]'},
							{name: 'fontFamily', before: '[font=@value]', after: '[/font]'},
							{name: 'bbc_tt', before: '[tt]', after: '[/tt]', isClass: true},
							{
								name: 'fontSize', before: '[size=@value]', after: '[/size]', values: {
									'xx-small': 1,
									'x-small': 2,
									'small': 3,
									'medium': 4,
									'large': 5,
									'x-large': 6,
									'xx-large': 7
								}
							}
						];

						checked = this.checkCSSProps(node, props);
						start = checked.start;
						end = checked.end;

						bb.push(start);
						bb.push(this.treeToBBCode(node.childNodes));
						bb.push(end);
						break;
					case 'p':
						bb.push(this.treeToBBCode(node.childNodes));
						break;
					case 'blockquote':
						if (node.classList.contains("bbc_quote"))
						{
							let author = node.getAttribute('data-quoted'),
								datetime = node.getAttribute('data-datetime'),
								link = node.getAttribute('data-link');

							bb.push('[quote' +
								(author ? ' author=' + author : '') +
								((link && datetime) ? ' link=' + link + ' date=' + datetime : '') +
								']\n');
							bb.push(this.treeToBBCode(node.childNodes));
							bb.push('\n[/quote]\n');
						}
						else
						{
							bb.push(this.treeToBBCode(node.childNodes));
						}
						break;
					default:
						bb.push(this.treeToBBCode(node.childNodes));
						break;
				}
			}
			break;
		case Node.DOCUMENT_NODE:// 9
		case Node.DOCUMENT_FRAGMENT_NODE:// 11
			bb.push(this.treeToBBCode(node.childNodes));
			break;
		case Node.TEXT_NODE:// 3
		case Node.CDATA_SECTION_NODE:// 4
			let text = node.nodeValue,
				codecheck = document.evaluate('ancestor::pre', node, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue;

			if (!codecheck)
			{
				text = text.replace(/\n[ \t]+/g, '\n');
			}
			bb.push(text);
			break;
	}

	return bb.join('').replace(/quote\]\n\n\[\//g, 'quote]\n[\/');
};

/**
 * Trim string by whitespace or specific characters
 *
 * @param {string} str
 * @param {string|null} charToReplace
 * @returns {string}
 */
Elk_QuickQuote.prototype.trim = function (str, charToReplace)
{
	if (charToReplace)
	{
		return String(str).replace(new RegExp('^[' + charToReplace + ']+|[' + charToReplace + ']+$', 'g'), '');
	}

	return str.trim();
};

/**
 * Returns smiley code
 *
 * @param {Object} img
 * @returns {string}
 */
Elk_QuickQuote.prototype.getSmileyCode = function (img)
{
	if (img.alt && img.className && img.classList.contains('smiley'))
	{
		// Alternative text corresponds to smiley and emoji code
		return img.alt;
	}

	return '';
};

/**
 * Called when the quick quote button is pressed, passed a PointerEvent
 *
 * @param {PointerEvent} event
 */
Elk_QuickQuote.prototype.executeQuickQuote = function (event)
{
	event.preventDefault();
	event.stopImmediatePropagation();

	let startTag = event.target.startTag,
		endTag = event.target.endTag;

	// isCollapsed is true for an empty selection
	let selection = window.getSelection().isCollapsed ? null : window.getSelection().getRangeAt(0);

	// Always clear out the button
	this.removeQuickQuote(event, true);

	if (selection)
	{
		let selectionAncestor = selection.commonAncestorContainer,
			selectionContents,
			postAncestor = document.evaluate('ancestor-or-self::section[contains(@class,"messageContent")]', selectionAncestor, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue;

		this.setHiddenFlag(selectionAncestor);

		if (selectionAncestor.nodeType !== 3 && selectionAncestor.nodeType !== 4)
		{
			// Most likely an element node
			selectionContents = selectionAncestor.cloneNode(false);
			selectionContents.appendChild(selection.cloneContents());
		}
		else
		{
			// Plain text
			selectionContents = selection.cloneContents();
		}

		if (postAncestor)
		{
			// Clone tree upwards. Some BBCode requires more context
			// than just the current node, like lists.
			let newSelectionContents;
			while (selectionAncestor !== postAncestor)
			{
				selectionAncestor = selectionAncestor.parentNode;

				// If in a blockquote, grab the cite details
				if (selectionAncestor.nodeName.toLowerCase() === 'blockquote' &&
					selectionAncestor.classList.contains('bbc_quote'))
				{
					this.handleQuote(selectionAncestor);
				}

				newSelectionContents = selectionAncestor.cloneNode(false);

				newSelectionContents.appendChild(selectionContents);

				selectionContents = newSelectionContents;
			}
		}

		let selectedText = this.trim(this.treeToBBCode(selectionContents));

		if (typeof oQuickReply === 'undefined' || oQuickReply.bIsFull)
		{
			// Full Editor
			let $editor = $editor_data[post_box_name],
				text = startTag + selectedText + endTag;

			// Add the text to the editor
			$editor.insert(this.trim(text));

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
		}
		else
		{
			// Just the textarea
			let textarea = document.querySelector('#postmodify').message,
				newText = (textarea.value ? textarea.value + '\n' : '') + startTag + selectedText + endTag + '\n';

			textarea.value = newText;

			// Reading again, to get normalized white-space
			newText = textarea.value;
			textarea.setSelectionRange(newText.length, newText.length);

			// Needed for Webkit/Blink
			textarea.blur();
			textarea.focus();
		}

		// Move to the editor
		if (typeof oQuickReply !== 'undefined')
		{
			document.getElementById(oQuickReply.opt.sJumpAnchor).scrollIntoView();
		}
		else
		{
			document.getElementById("editor_toolbar_container").scrollIntoView();
		}
	}
};

/**
 * Extracts the cite data and places them in the blockquote data- attributes
 *
 * @param {Element} selectionAncestor
 */
Elk_QuickQuote.prototype.handleQuote = function(selectionAncestor)
{
	let data_quoted = '',
		data_link = '',
		data_datetime = '';

	let cite = selectionAncestor.firstChild;

	// Extract the cite details
	if (cite.textContent.includes(':'))
	{
		data_quoted = cite.textContent.split(':')[1].trim();
	}

	if (data_quoted.includes(String.fromCharCode(160)))
	{
		data_quoted = data_quoted.split(String.fromCharCode(160))[0].trim();
	}

	if (cite.firstElementChild && cite.firstElementChild.hasAttribute('href'))
	{
		data_link = new URL(cite.firstElementChild.getAttribute('href')).search.substring(1);
	}

	if (cite.querySelector('time') !== null)
	{
		data_datetime = cite.querySelector('time').getAttribute('data-timestamp');
	}

	// Set what we found as data attributes of the blockquote
	selectionAncestor.setAttribute('data-quoted', data_quoted);
	selectionAncestor.setAttribute('data-link', data_link);
	selectionAncestor.setAttribute('data-datetime', data_datetime);
};

/**
 * Called when the user selects some text.  It prepares the Quick Quote Button
 * action
 *
 * @param {PointerEvent} event
 */
Elk_QuickQuote.prototype.prepareQuickQuoteButton = function (event)
{
	// The message that this event is attached to
	let postArea = event.currentTarget;

	// The link button to show, poster and time of post being quoted
	let msgid = parseInt(postArea.getAttribute('data-msgid')),
		link = document.getElementById('button_float_qq_' + msgid),
		username,
		time_unix;

	// If there is some text selected
	if (!window.getSelection().isCollapsed && link.classList.contains('hide'))
	{
		// Show and then position the button
		link.classList.remove('hide');
		this.setButtonPosition(event, link);

		// Topic Display, Grab the name from the aside area
		if (this.postSelector === '.postarea')
		{
			username = (postArea.parentNode.previousElementSibling.querySelector('.name').textContent).trim();
			time_unix = postArea.parentNode.querySelector('time').getAttribute('data-forumtime');
		}
		// Topic Summary on post page
		else
		{
			username = (postArea.parentNode.querySelector('.name').textContent).trim();
			time_unix = postArea.parentNode.querySelector('time').getAttribute('data-forumtime');
		}

		// Build the quick quote wrapper and set the button click event
		link.startTag = '[quote' +
			(username ? ' author=' + username : '') +
			((msgid && time_unix) ? ' link=msg=' + msgid + ' date=' + time_unix : '') + ']\n';
		link.endTag = '\n[/quote]';

		// Save the function pointers (due to bind) so we can remove the EventListeners
		this.execute = this.executeQuickQuote.bind(this);
		this.remove = this.removeQuickQuote.bind(this);

		// Button click
		link.addEventListener(this.mouseDown, this.execute, true);

		// Provide a way to escape should they click anywhere in the window
		document.addEventListener('click', this.remove, false);
	}
};

/**
 * Removes all QQ button click listeners and hides them all.
 *
 * @param {PointerEvent} event
 * @param {boolean} always
 */
Elk_QuickQuote.prototype.removeQuickQuote = function (event, always = false)
{
	event.stopImmediatePropagation();

	// Nothing selected, reset the UI and listeners
	if (window.getSelection().isCollapsed || always)
	{
		document.removeEventListener('click', this.remove, false);

		let topicContents = document.querySelectorAll('.messageContent'),
			link;

		// Reset the UI on de-selection
		topicContents.forEach((message) =>
		{
			link = message.parentElement.querySelector('.quick_quote_button');
			link.classList.add('hide');
			link.removeEventListener(this.mouseDown, this.execute, true);
		});
	}
};
