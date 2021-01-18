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

/** global: elk_session_var, elk_session_id, ila_filename, elk_scripturl  */

/**
 * Extension functions to provide ElkArte compatibility with sceditor
 */
const itemCodes = ["*:disc", "@:disc", "+:square", "x:square", "#:decimal", "0:decimal", "O:circle", "o:circle"];
(function ($)
{
	var extensionMethods = {
		addEvent: function (id, event, func)
		{
			var current_event = event,
				$_id = $('#' + id);

			$_id.parent().on(event, 'textarea', func);

			var oIframe = $_id.parent().find('iframe')[0],
				oIframeWindow = oIframe.contentWindow;

			if (oIframeWindow !== null && oIframeWindow.document)
			{
				var oIframeDoc = oIframeWindow.document,
					oIframeBody = oIframeDoc.body;

				$(oIframeBody).on(event, func);
			}
		},
		appendEmoticon: function (code, emoticon)
		{
			if (emoticon === '')
			{
				line.append($('<br />'));
			}
			else
			{
				$img = $('<img />')
					.attr({
						src: emoticon.url || emoticon,
						alt: code,
						title: emoticon.tooltip || emoticon
					})
					.on('click', function (e)
					{
						var start = '',
							end = '';

						if (base.opts.emoticonsCompat)
						{
							start = '<span> ';
							end = ' </span>';
						}

						if (base.inSourceMode())
						{
							base.sourceEditorInsertText(' ' + $(this).attr('alt') + ' ');
						}
						else
						{
							base.wysiwygEditorInsertHtml(start + '<img src="' + $(this).attr("src") + '" data-sceditor-emoticon="' + $(this).attr('alt') + '" />' + end);
						}

						e.preventDefault();
					});
				line.append($('<span class="smiley"></span>').append($img));
			}
		},
		createPermanentDropDown: function ()
		{
			var emoticons = $.extend({}, this.opts.emoticons.dropdown);

			base = this;
			content = $('<div class="sceditor-insertemoticon" />');
			line = $('<div id="sceditor-smileycontainer" />');

			// For any smileys that go in the more popup
			if (!$.isEmptyObject(this.opts.emoticons.popup))
			{
				this.opts.emoticons.more = this.opts.emoticons.popup;
				moreButton = $('<div class="sceditor-more" />').text(this._('More')).on('click', function ()
				{
					var popup_box = $('.sceditor-smileyPopup');

					if (popup_box.length > 0)
					{
						popup_box.fadeIn('fast');
					}
					else
					{
						var emoticons = $.extend({}, base.opts.emoticons.popup),
							titlebar = $('<div class="category_header sceditor-popup-grip"/>');

						popupContent = $('<div id="sceditor-popup" />');
						line = $('<div id="sceditor-popup-smiley" />');

						// create our popup, title bar, smiles, then the close button
						popupContent.append(titlebar);

						// Add in all the smileys / lines
						$.each(emoticons, base.appendEmoticon);
						if (line.children().length > 0)
						{
							popupContent.append(line);
						}

						closeButton = $('<div id="sceditor-popup-close" />').text('[' + base._('Close') + ']').on('click', function ()
						{
							$(".sceditor-smileyPopup").fadeOut('fast');
						});

						if (typeof closeButton !== 'undefined')
						{
							popupContent.append(closeButton);
						}

						// IE needs unselectable attr to stop it from unselecting the text in the editor.
						// The editor can cope if IE does unselect the text it's just not nice.
						if (base.ieUnselectable !== false)
						{
							content = $(content);
							content.find(':not(input,textarea)').filter(function ()
							{
								return this.nodeType === 1;
							}).attr('unselectable', 'on');
						}

						// Show the smiley popup
						$dropdown = $('<div class="sceditor-dropdown sceditor-smileyPopup" />')
							.append(popupContent)
							.appendTo($('body'))
							.css({
								"top": $(window).height() * 0.2,
								"left": $(window).width() * 0.5 - (popupContent.find('#sceditor-popup-smiley').width() / 2)
							});
						dropdownIgnoreLastClick = true;

						// Allow the smiley window to be moved about
						$('.sceditor-smileyPopup').draggable({handle: '.sceditor-popup-grip'});

						// stop clicks within the dropdown from being handled
						$dropdown.on('click', function (e)
						{
							e.stopPropagation();
						});
					}
				});
			}

			// show the standard placement icons
			$.each(emoticons, base.appendEmoticon);

			if (line.children().length > 0)
			{
				content.append(line);
			}

			$(".sceditor-toolbar").append(content);

			// Show the more button on the editor if we have more
			if (typeof moreButton !== 'undefined')
			{
				content.append(moreButton);
			}
		}
	};

	// Define our editor create function, used to add our extension methods
	sceditor.createEx = function (textarea, options)
	{
		// Call the create function as normal
		sceditor.create(textarea, options);

		// Extend sceditor utility functions with our methods
		let instance = sceditor.instance(textarea);
		if (instance)
		{
			sceditor.utils.extend(instance.constructor.prototype, extensionMethods);
		}
	};
})(jQuery);

/**
 * ElkArte unique commands to add to the toolbar, when a button
 * with the same name is selected, it will trigger these definitions
 *
 * tooltip - the hover text, this is the name in the editors.(language).php file
 * txtExec - this is the text to insert before and after the cursor or selected text
 *           when in the plain text part of the editor
 * exec - this is called when in the wizzy part of the editor to insert text or html tags
 * state - this is used to determine if a button should be shown as active or not
 *
 * Adds Tt, Pre, Spoiler, Footnote commands
 */
sceditor.command
	.set('space', {})
	.set('spoiler', {
		state: function ()
		{
			let currentNode = this.currentNode(),
				currentRange = this.getRangeHelper();

			// We don't have a node since we don't render the tag in the wizzy editor
			// however we can spot check to see if the cursor is inside the tags.
			if (currentRange.selectedRange())
			{
				let end = currentRange.selectedRange().startOffset,
					text = typeof currentNode !== 'undefined' ? currentNode.textContent : '';

				// Left and right text from the cursor position and tag positions
				let left = text.substr(0, end),
					right = text.substr(end),
					l1 = left.lastIndexOf("[spoiler]"),
					l2 = left.lastIndexOf("[/spoiler]"),
					r1 = right.indexOf("[spoiler]"),
					r2 = right.indexOf("[/spoiler]");

				// Inside spoiler tags
				if ((l1 > -1 && l1 > l2) || (r2 > -1 && (r1 === -1 || (r1 > r2))))
				{
					return 1;
				}
			}

			return 0;
		},
		exec: function ()
		{
			this.insert('[spoiler]', '[/spoiler]');
		},
		txtExec: ['[spoiler]', '[/spoiler]'],
		tooltip: 'Insert Spoiler'
	})
	.set('footnote', {
		state: function ()
		{
			let currentNode = this.currentNode(),
				currentRange = this.getRangeHelper();

			// We don't have an html node since we don't render the tag in the editor
			// but we can do a spot check to see if the cursor is placed between plain tags.  This
			// will miss with [footnote]nested [b]tags[b][/footnote] but its nicer than nothing.
			if (currentRange.selectedRange())
			{
				let end = currentRange.selectedRange().startOffset,
					text = typeof currentNode !== 'undefined' ? currentNode.textContent : '';

				// Left and right text from the cursor position and tag positions
				let left = text.substr(0, end),
					right = text.substr(end),
					l1 = left.lastIndexOf("[footnote]"),
					l2 = left.lastIndexOf("[/footnote]"),
					r1 = right.indexOf("[footnote]"),
					r2 = right.indexOf("[/footnote]");

				// Inside footnote tags
				if ((l1 > -1 && l1 > l2) || (r2 > -1 && (r1 === -1 || (r1 > r2))))
				{
					return 1;
				}
			}

			return 0;
		},
		exec: function ()
		{
			this.insert('[footnote] ', '[/footnote]');
		},
		txtExec: ['[footnote]', '[/footnote]'],
		tooltip: 'Insert Footnote'
	})
	.set('tt', {
		state: function ()
		{
			let currentNode = this.currentNode();

			if (currentNode && currentNode.nodeType === 3)
			{
				currentNode = currentNode.parentNode;
			}

			return (currentNode && (currentNode.classList.contains('bbc_tt') && currentNode.tagName.toLowerCase() === 'span')) ? 1 : 0;
		},
		exec: function ()
		{
			let editor = this,
				rangeHelper = editor.getRangeHelper(),
				tt,
				range,
				blank;

			// Set our markers and make a copy
			rangeHelper.saveRange();
			range = rangeHelper.cloneSelected();

			// Find the span.bbc_tt node if we are in one that is
			tt = range.commonAncestorContainer;
			while (tt && (tt.nodeType !== 1 || (tt.tagName.toLowerCase() !== "span" && !tt.classList.contains('bbc_tt'))))
			{
				tt = tt.parentNode;
			}

			// If we found one, we are in it and the user requested to end this TT
			if (tt)
			{
				// Place the markers at the end of the TT span
				range.setEndAfter(tt);
				range.collapse(false);

				// Stuff in a new spacer node at that position
				blank = tt.ownerDocument.createElement('span');
				blank.innerHTML = '&nbsp;';
				range.insertNode(blank);

				// Move the caret to the new empty node
				let range_new = document.createRange();
				range_new.setStartAfter(blank);

				// Set sceditor to this new range
				rangeHelper.selectRange(range_new);
				editor.focus()

				return;
			}

			// Otherwise, a new TT span for then
			rangeHelper.restoreRange();
			return editor.insert('<span class="bbc_tt">', '</span>', false);
		},
		txtExec: ['[tt]', '[/tt]'],
		tooltip: 'Teletype'
	})
	.set('pre', {
		state: function ()
		{
			let currentNode = this.currentNode();

			if (currentNode && currentNode.nodeType === 3)
			{
				currentNode = currentNode.parentNode;
			}

			return (currentNode && currentNode.tagName.toLowerCase() === 'pre') ? 1 : 0;
		},
		exec: function ()
		{
			let editor = this,
				rangeHelper = editor.getRangeHelper(),
				pre,
				range,
				blank;

			// Set our markers and make a copy
			rangeHelper.saveRange();
			range = rangeHelper.cloneSelected();

			// Find the pre.bbc_pre node if we are in one that is
			pre = range.commonAncestorContainer;
			while (pre && (pre.nodeType !== 1 || (pre.tagName.toLowerCase() !== "pre" && !pre.classList.contains('bbc_pre'))))
			{
				pre = pre.parentNode;
			}

			// If we found one, we are in it and the user requested to end this PRE
			if (pre)
			{
				// Place the markers at the end of the pre
				range.setEndAfter(pre);
				range.collapse(false);

				// Stuff in a new block node at that position
				blank = pre.ownerDocument.createElement('p');
				blank.innerHTML = '&nbsp;';
				range.insertNode(blank);

				// Move the caret to the new empty block node
				let range_new = document.createRange();
				range_new.setStartAfter(blank);

				// Set sceditor to this new range
				rangeHelper.selectRange(range_new);
				editor.focus()

				return;
			}

			// Otherwise, a new pre span for then
			rangeHelper.restoreRange();
			return editor.insert('<pre class="bbc_pre">', '</pre>', false);
		},
		txtExec: ['[pre]', '[/pre]'],
		tooltip: 'Preformatted Text'
	})
	/*
	 * ElkArte modifications to existing commands so they display as we like
	 *
	 * Makes changes to the text inserted for Bulletlist, OrderedList and Table
	 */
	.set('bulletlist', {
		txtExec: function(called, selected)
		{
			// Selected some text to turn into a list?
			if (selected)
			{
				let content = '';

				$.each(selected.split(/\r?\n/), function ()
				{
					content += (content ? '\n' : '') + '[li]' + this + '[/li]';
				});

				return this.insertText('[list]\n' + content + '\n[/list]');
			}

			this.insertText('[list]\n[li]', ' [/li]\n[li] [/li]\n[/list]');
		}
	})
	.set('orderedlist', {
		txtExec: function (caller, selected)
		{
			if (selected)
			{
				let content = '';

				$.each(selected.split(/\r?\n/), function ()
				{
					content += (content ? '\n' : '') + '[li]' + this + '[/li]';
				});

				return this.insertText('[list type=decimal]\n' + content + '\n[/list]');
			}

			this.insertText('[list type=decimal]\n[li] ', '[/li]\n[li] [/li]\n[/list]');
		}
	})
	.set('table', {
		txtExec: ['[table]\n[tr]\n[td]', '[/td]\n[/tr]\n[/table]']
	});

/**
 * ElkArte custom bbc tags added to provide for the existing user experience
 *
 * These command define what happens to tags as we toggle from and to wizzy mode
 * It converts html back to bbc or bbc back to html.  Read the sceditor docs for more
 *
 * Adds / modifies BBC codes List, Tt, Pre, quote, footnote, code, img
 */
sceditor.formats.bbcode
	.set('tt', {
		tags: {
			tt: null,
			span: {'class': ['bbc_tt']}
		},
		format: '[tt]{0}[/tt]',
		html: '<span class="bbc_tt">{0}</span>'
	})
	.set('pre', {
		tags: {
			pre: null,
			pre: {'class': ['bbc_pre']}
		},
		isInline: false,
		format: '[pre]{0}[/pre]',
		html: '<pre class="bbc_pre">{0}</pre>'
	})
	.set('member', {
		isInline: true,
		format: function (element, content)
		{
			return '[member=' + element.getAttribute('data-mention') + ']' + content.replace('@', '') + '[/member]';
		},
		html: function (token, attrs, content)
		{
			if (typeof attrs.defaultattr === 'undefined' || attrs.defaultattr.length === 0)
			{
				attrs.defaultattr = content;
			}

			return '<a href="' + elk_scripturl + '?action=profile;u=' + attrs.defaultattr + '" class="mention" data-mention="' + attrs.defaultattr + '">@' + content.replace('@', '') + '</a>';
		}
	})
	.set('me', {
		tags: {
			me: {
				'data-me': null
			}
		},
		isInline: true,
		quoteType: sceditor.BBCodeParser.QuoteType.always,
		format: function (element, content)
		{
			return '[me=' + element.getAttribute('data-me') + ']' + content.replace(element.getAttribute('data-me') + ' ', '') + '[/me]';
		},
		html: function (token, attrs, content)
		{
			if (typeof attrs.defaultattr === 'undefined' || attrs.defaultattr.length === 0)
			{
				attrs.defaultattr = '';
			}

			return '<me data-me="' + attrs.defaultattr + '">' + attrs.defaultattr + ' ' + content + '</me>';
		}
	})
	.set('attachurl', {
		allowsEmpty: false,
		quoteType: sceditor.BBCodeParser.QuoteType.never,
		format: function (element, content)
		{
			return '[attachurl]' + content + '[/attachurl]';
		},
		html: function (token, attrs, content)
		{
			// @todo new action to return real filename?
			return '<a href="' + elk_scripturl + '?action=dlattach;attach=' + content + ';' + elk_session_var + '=' + elk_session_id + '" data-ila="' + content + '">(<i class="icon i-paperclip"></i>&nbsp;' + ila_filename + ')</a>';
		}
	})
	.set('attach', {
		allowsEmpty: false,
		quoteType: sceditor.BBCodeParser.QuoteType.never,
		format: function (element, content)
		{
			/**
			 * This function is not used because no specific html tag is associated,
			 * instead the 'img'.format takes care of finding the ILA images and process
			 * them accordingly to return the [attach] tag.
			 */
			let attribs = '',
				params = function (names)
				{
					names.forEach(function (name)
					{
						if (element.hasAttribute(name))
						{
							attribs += ' ' + name + '=' + element.getAttribute(name);
						}
						else if (element.style[name])
						{
							attribs += ' ' + name + '=' + element.style[name];
						}
					});
				};

			params(['width', 'height', 'align', 'type']);

			return '[attach' + attribs + ']' + content + '[/attach]';
		},
		html: function (token, attrs, content)
		{
			let attribs = '',
				align = '',
				thumb = '',
				params = function (names)
				{
					names.forEach(function (name)
					{
						if (typeof attrs[name] !== 'undefined')
						{
							attribs += ' ' + name + '=' + attrs[name];
						}
					});
				};

			params(['width', 'height', 'align', 'type']);
			a_attribs = attribs;
			if (typeof attrs.align !== 'undefined')
			{
				align = ' class="img_bbc float' + attrs.align + '"';
			}

			if (typeof attrs.type !== 'undefined')
			{
				thumb = ';' + attrs.type;
			}

			return '<img' + attribs + align + ' src="' + elk_scripturl + '?action=dlattach;attach=' + content + thumb + ';' + elk_session_var + '=' + elk_session_id + '" data-ila="' + content + '" />';
		}
	})
	.set('center', {
		tags: {
			center: null
		},
		styles: {
			'text-align': ['center', '-webkit-center', '-moz-center', '-khtml-center']
		},
		isInline: true,
		format: '[center]{0}[/center]',
		html: '<span style="display:block;text-align:center">{0}</span>'
	})
	/*
	 * ElkArte modified tags, modified so they support the existing paradigm
	 *
	 * Changes the way existing editor tags work
	 * Modifies code, quote, list, ul, ol, li
	 */
	.set('code', {
		tags: {
			code: null
		},
		isInline: false,
		allowedChildren: ['#', '#newline'],
		format: function (element, content)
		{
			let from = '';

			if (element.children("cite:first").length === 1)
			{
				from = element.children("cite:first").text().trim();
				element.getAttribute({'from': from.php_htmlspecialchars()});
				from = '=' + from;
				element.children("cite:first").remove();
				content = this.elementToBbcode(element);
			}
			else if (element.hasAttribute('from'))
			{
				from = '=' + element.getAttribute('from').php_unhtmlspecialchars();
			}

			return '[code' + from + ']' + content.replace('&#91;', '[') + '[/code]';
		},
		quoteType: function (element)
		{
			return element;
		},
		html: function (element, attrs, content)
		{
			let from = '';

			if (typeof attrs.defaultattr !== 'undefined')
			{
				from = '<cite>' + attrs.defaultattr + '</cite>';
			}

			return '<code>' + from + content.replace('[', '&#91;') + '</code>';
		}
	})
	.set('quote', {
		tags: {
			blockquote: null,
			cite: null
		},
		isInline: false,
		format: function (element, content)
		{
			let author = '',
				date = '',
				link = '';

			if (element.tagName.toLowerCase() === 'cite')
			{
				return '';
			}

			if (element.hasAttribute('author'))
			{
				author = ' author=' + element.getAttribute('author').php_unhtmlspecialchars();
			}

			if (element.hasAttribute('date'))
			{
				date = ' date=' + element.getAttribute('date');
			}

			if (element.hasAttribute('link'))
			{
				link = ' link=' + element.getAttribute('link');
			}

			if (author === '' && date === '' && link !== '')
			{
				link = '=' + element.getAttribute('link');
			}

			return '[quote' + author + link + date + ']' + content + '[/quote]';
		},
		html: function (element, attrs, content)
		{
			let attr_author = '',
				sAuthor = '',
				attr_date = '',
				sDate = '',
				attr_link = '',
				sLink = '';

			// Author tag in the quote ?
			if (typeof attrs.author !== 'undefined')
			{
				attr_author = attrs.author;
				sAuthor = bbc_quote_from + ': ' + attr_author;
			}
			// Done as [quote=someone]
			else if (typeof attrs.defaultattr !== 'undefined')
			{
				// Convert it to an author tag
				attr_link = attrs.defaultattr;
				sLink = attr_link.substr(0, 8) === 'https://' ? attr_link : elk_scripturl + '?' + attr_link;
				sAuthor = '<a href="' + sLink + '">' + bbc_quote_from + ': ' + sLink + '</a>';
			}

			// Links could be in the form: link=topic=71.msg201#msg201 that would fool javascript, so we need a workaround
			for (let key in attrs)
			{
				if (key.substr(0, 4) === 'link' && attrs.hasOwnProperty(key))
				{
					attr_link = key.length > 4 ? key.substr(5) + '=' + attrs[key] : attrs[key];

					sLink = attr_link.substr(0, 7) === 'https://' ? attr_link : elk_scripturl + '?' + attr_link;
					sAuthor = sAuthor === '' ? '<a href="' + sLink + '">' + bbc_quote_from + ': ' + sLink + '</a>' : '<a href="' + sLink + '">' + sAuthor + '</a>';
				}
			}

			// A date perhaps
			if (typeof attrs.date !== 'undefined')
			{
				attr_date = attrs.date;
				sDate = '<date timestamp="' + attr_date + '">' + new Date(attrs.date * 1000) + '</date>';
			}

			// Build the blockquote up with the data
			if (sAuthor === '' && sDate === '')
			{
				sAuthor = bbc_quote;
			}
			else
			{
				sAuthor += sDate !== '' ? ' ' + bbc_search_on : '';
			}

			content = '<blockquote author="' + attr_author + '" link="' + attr_link + '" date="' + attr_date + '"><cite>' + sAuthor + ' ' + sDate + '</cite>' + content + '</blockquote>&nbsp;';

			return content;
		}
	})
	.set('img', {
		tags: {
			img: {
				src: null
			}
		},
		allowsEmpty: true,
		quoteType: sceditor.BBCodeParser.QuoteType.never,
		allowedChildren: ['#'],
		format: function (element, content)
		{
			let attribs = '',
				params = function (names)
				{
					names.forEach(function (name)
					{
						if (element.hasAttribute(name))
						{
							attribs += ' ' + name + '=' + element.getAttribute(name);
						}
						else if (element.style.name)
						{
							attribs += ' ' + name + '=' + element.style.name;
						}
					});
				};

			// check if this is an emoticon image
			if (element.hasAttribute('data-sceditor-emoticon'))
			{
				return content;
			}

			// check if this is an ILA ?
			if (element.hasAttribute('data-ila'))
			{
				params(['width', 'height', 'align', 'type']);

				return '[attach' + attribs + ']' + element.getAttribute('data-ila') + '[/attach]';
			}

			// normal image then
			params(['width', 'height', 'title', 'alt']);

			return '[img' + attribs + ']' + element.getAttribute('src') + '[/img]';
		},
		html: function (token, attrs, content)
		{
			let attribs = '',
				params = function (names)
				{
					names.forEach(function (name)
					{
						if (typeof attrs[name] !== 'undefined')
						{
							attribs += ' ' + name + '="' + attrs[name] + '"';
						}
					});
				};

			// handle [img alt=alt title=title width=123 height=123]url[/img]
			params(['width', 'height', 'alt', 'title']);
			return '<img' + attribs + ' src="' + content + '" />';
		}
	})
	.set('list', {
		breakStart: true,
		isInline: false,
		skipLastLineBreak: true,
		allowedChildren: ['#', '*', 'li'],
		html: function (element, attrs, content)
		{
			let style = '',
				code = 'ul';

			if (attrs.type)
			{
				style = ' style="list-style-type: ' + attrs.type + '"';
			}

			return '<' + code + style + '>' + content.replace(/<\/li><br \/>/g, '</li>') + '</' + code + '>';
		}
	})
	.set('li', {
		breakAfter: false,
		isInline: false,
		closedBy: ['/ul', '/ol', '/list', 'li', '*', '@', '+', 'x', '#', '0', 'o', 'O'],
		html: '<li data-itemcode="li">{0}</li>',
		format: function (element, content) {
			let token = 'li',
				itemCodes = ['*', '@', '+', 'x', '#', '0', 'o', 'O'];

			if (element.hasAttribute('data-itemcode') && itemCodes.indexOf(element.getAttribute('data-itemcode')) !== -1)
			{
				token = element.getAttribute('data-itemcode');
			}

			return '[' + token + ']' + content + (token === 'li' ? '[/li]' : '');
		},
	})
	.set('ul', {
		tags: {
			ul: null
		},
		breakStart: true,
		format: function (element, content)
		{
			let type = element.style['list-style-type'];

			if (type === 'disc' || type === '')
			{
				return '[list]' + content + '[/list]';
			}

			return '[list type=' + type + ']' + content + '[/list]';
		},
		isInline: false,
		skipLastLineBreak: true,
		html: '<ul>{0}</ul>'
	})
	.set('ol', {
		tags: {
			ol: null
		},
		breakStart: true,
		isInline: false,
		skipLastLineBreak: true,
		format: '[list type=decimal]{0}[/list]',
		html: '<ol>{0}</ol>'
	})
	.set('url', {
		allowsEmpty: true,
		tags: {
			a: {
				href: null
			}
		},
		quoteType: sceditor.BBCodeParser.QuoteType.never,
		format: function (element, content)
		{
			let url = element.getAttribute('href');

			// return the type of link we are currently dealing with
			if (url.substr(0, 7) === 'mailto:')
			{
				return '[email="' + url.substr(7) + '"]' + content + '[/email]';
			}

			if (element.hasAttribute('data-mention'))
			{
				return '[member=' + element.getAttribute('data-mention') + ']' + content.replace('@', '') + '[/member]';
			}

			if (element.hasAttribute('data-ila'))
			{
				return '[attachurl]' + element.getAttribute('data-ila') + '[/attachurl]';
			}

			return '[url=' + url + ']' + content + '[/url]';
		},
		html: function (token, attrs, content)
		{
			attrs.defaultattr = sceditor.escapeEntities(attrs.defaultattr, true) || content;

			return '<a target="_blank" rel="noopener noreferrer" href="' + sceditor.escapeUriScheme(attrs.defaultattr) + '" class="bbc_link">' + content + '</a>';
		}
	});

// All the lovely item [*] codes done in a loop
itemCodes.forEach(function ( code)
{
	code = code.split(":");
	sceditor.formats.bbcode
		.set(code[0], {
			tags: {
				li: {
					'data-itemcode': [code[0]]
				}
			},
			isInline: false,
			closedBy: ['/ul', '/ol', '/list', 'li', '*', '@', '+', 'x', '#', '0', 'o', 'O'],
			excludeClosing: true,
			html: '<li style="list-style-type:' + code[1] + '" data-itemcode="' + code[0] + '">{0}</li>',
			format: '[' + code[0] + ']{0}',
		});
});