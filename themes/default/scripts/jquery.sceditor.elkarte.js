/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0
 * Extension functions to provide ElkArte compatibility with sceditor
 */

(function($) {
	var extensionMethods = {
		addEvent: function(id, event, func) {
			var current_event = event;
			$('#' + id).parent().on(event, 'textarea', func);

			var oIframe = $('#' + id).parent().find('iframe')[0],
				oIframeWindow = oIframe.contentWindow;

			if (oIframeWindow !== null && oIframeWindow.document)
			{
				var oIframeDoc = oIframeWindow.document;
				var oIframeBody = oIframeDoc.body;

				$(oIframeBody).on(event, func);
			}
		},
		InsertText: function(text, bClear) {
			var bIsSource = this.inSourceMode();

			if (!bIsSource)
				this.toggleSourceMode();

			var current_value = this.getSourceEditorValue(false),
				iEmpty = current_value.length;

			current_value = bClear ? text + "\n" : current_value + (iEmpty > 0 ? "\n" : "") + text + "\n";
			this.setSourceEditorValue(current_value);

			if (!bIsSource)
				this.toggleSourceMode();
		},
		getText: function(filter) {
			var current_value = '';

			if (this.inSourceMode())
				current_value = this.getSourceEditorValue(false);
			else
				current_value  = this.getWysiwygEditorValue(filter);

			return current_value;
		},
		appendEmoticon: function (code, emoticon) {
			if (emoticon === '')
				line.append($('<br />'));
			else
				line.append($('<img />')
					.attr({
						src: emoticon.url || emoticon,
						alt: code,
						title: emoticon.tooltip || emoticon
					})
					.click(function (e) {
						var start = '',
							end = '';

						if (base.opts.emoticonsCompat)
						{
							start = '<span> ';
							end   = ' </span>';
						}

						if (base.inSourceMode())
							base.sourceEditorInsertText(' ' + $(this).attr('alt') + ' ');
						else
							base.wysiwygEditorInsertHtml(start + '<img src="' + $(this).attr("src") + '" data-sceditor-emoticon="' + $(this).attr('alt') + '" />' + end);

						e.preventDefault();
					})
				);

			if (line.children().length > 0)
				content.append(line);

			$(".sceditor-toolbar").append(content);
		},
		storeLastState: function (){
			this.wasSource = this.inSourceMode();
		},
		setTextMode: function () {
			if (!this.inSourceMode())
				this.toggleSourceMode();
		},
		createPermanentDropDown: function() {
			var emoticons = $.extend({}, this.opts.emoticons.dropdown),
				popup_exists = false,
				smiley_popup = '';

			base = this;
			content = $('<div class="sceditor-insertemoticon" />');
			line = $('<div id="sceditor-smileycontainer" />');

			for (smiley_popup in this.opts.emoticons.popup)
			{
				popup_exists = true;
				break;
			}

			// For any smileys that go in the more popup
			if (popup_exists)
			{
				this.opts.emoticons.more = this.opts.emoticons.popup;
				moreButton = $('<div class="sceditor-more" />').text(this._('More')).click(function () {
					var popup_box = $('.sceditor-smileyPopup');

					if (popup_box.length > 0)
						popup_box.fadeIn('fast');
					else
					{
						var emoticons = $.extend({}, base.opts.emoticons.popup),
							titlebar = $('<div class="category_header sceditor-popup-grip"/>');

						popupContent = $('<div id="sceditor-popup" />');
						line = $('<div id="sceditor-popup-smiley" />');

						// create our popup, title bar, smiles, then the close button
						popupContent.append(titlebar);

						$.each(emoticons, base.appendEmoticon);
						if (line.children().length > 0)
							popupContent.append(line);

						closeButton = $('<div id="sceditor-popup-close" />').text('[' + base._('Close') + ']').click(function () {
							$(".sceditor-smileyPopup").fadeOut('fast');
						});

						if (typeof closeButton !== "undefined")
							popupContent.append(closeButton);

						// IE needs unselectable attr to stop it from unselecting the text in the editor.
						// The editor can cope if IE does unselect the text it's just not nice.
						if (base.ieUnselectable !== false)
						{
							content = $(content);
							content.find(':not(input,textarea)').filter(function() {return this.nodeType === 1;}).attr('unselectable', 'on');
						}

						popupContent = $('<div class="sceditor-dropdown sceditor-smileyPopup" />').append(popupContent);
						$dropdown = popupContent.appendTo('body');
						dropdownIgnoreLastClick = true;

						// position it on the screen
						$dropdown.css({
							"top": $(window).height() * 0.2,
							"left": $(window).width() * 0.5 - ($dropdown.find('#sceditor-popup-smiley').width() / 2)
						});

						// Allow the smiley window to be moved about
						$('.sceditor-smileyPopup').draggable({handle: '.sceditor-popup-grip'});

						// stop clicks within the dropdown from being handled
						$dropdown.click(function (e) {
							e.stopPropagation();
						});
					}
				});
			}

			// show the standard placement icons
			$.each(emoticons, base.appendEmoticon);

			// Show the more button on the editor if we have more
			if (typeof moreButton !== "undefined")
				content.append(moreButton);
		}
	};

	$.extend(true, $['sceditor'].prototype, extensionMethods);
})(jQuery);

/**
 * ElkArte unique commands to add to the toolbar, when a button
 * with the same name is selected, it will trigger these defiintions
 *
 * tooltip - the hover text, this is the name in the editors.xxxx.php file
 * txtExec - this is the text to insert before and after the cursor or seleted text
 *           when in the plain text part of the editor
 * exec - this is called when in the wizzy part of the editor to insert text or html tags
 * state - this is used to determine if a button should be shown as active or not
 *
 * Adds Tt, Pre, Spoiler, Footnote commands
 */
$.sceditor.command
	.set('space', {
	})
	.set('source', {
		state: function() {
			return true;
		}
	})
	.set('spoiler', {
		exec: function () {
			this.insert('[spoiler]', '[/spoiler]');
		},
		txtExec: ['[spoiler]', '[/spoiler]'],
		tooltip: 'Insert Spoiler'
	})
	.set('footnote', {
		state: function() {
			var currentNode = this.currentNode();

			return $(currentNode).is('aside') || $(currentNode).parents('aside').length > 0 ? 1 : 0;
		},
		exec: function () {
			this.insert('[footnote] ', '[/footnote]', false);
		},
		txtExec: ['[footnote]', '[/footnote]'],
		tooltip: 'Insert Footnote'
	})
	.set('tt', {
		state: function() {
			var currentNode = this.currentNode();

			return $(currentNode).is('span.tt') || $(currentNode).parents('span.tt').length > 0 ? 1 : 0;
		},
		exec: function () {
			var editor = this,
				currentNode = this.currentNode();

			if (!$(currentNode).is('span.tt') && $(currentNode).parents('span.tt').length === 0)
				this.insert('<span class="tt">', '</span>', false);
			else
				return;
		},
		txtExec: ['[tt]', '[/tt]'],
		tooltip: 'Teletype'
	})
	.set('pre', {
		state: function() {
			var currentNode = this.currentNode();

			return $(currentNode).is('pre') || $(currentNode).parents('pre').length > 0 ? 1 : 0;
		},
		exec: function () {
			var editor = this,
				currentNode = this.currentNode();

			if (!$(currentNode).is('pre') && $(currentNode).parents('pre').length === 0)
				this.insert('<pre>', '</pre>', false);
			else
				return;
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
		txtExec: ['[list]\n[li]', '[/li]\n[li][/li]\n[/list]']
	})
	.set('orderedlist', {
		txtExec:  ['[list type=decimal]\n[li]', '[/li]\n[li][/li]\n[/list]']
	})
	.set('table', {
		txtExec: ['[table]\n[tr]\n[td]', '[/td]\n[/tr]\n[/table]']
	});

/**
 * ElkArte custom bbc tags added to provide for the existing user experience
 *
 * These command define what happens to tags as to toggle from and to wizzy mode
 * It converts html back to bbc or bbc back to html.  Read the sceditor docs for more
 *
 * Adds / modifies BBC codes List, Tt, Pre, quote, footnote, code, img
 */
$.sceditor.plugins.bbcode.bbcode
	.set('tt', {
		tags: {
			tt: null,
			span: {'class': ['tt']}
		},
		format: '[tt]{0}[/tt]',
		html: '<span class="tt">{0}</span>'
	})
	.set('pre', {
		tags: {
			pre: null
		},
		isInline: false,
		format: '[pre]{0}[/pre]',
		html: '<pre>{0}</pre>'
	})
	.set('footnote', {
		tags: {
			aside: null
		},
		format: '[footnote]{0}[/footnote]',
		html: '<aside>{0}</aside>'
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
		format: function(element, content) {
			var from = '';

			if ($(element[0]).hasClass('php'))
				return '[php]' + content.replace('&#91;', '[') + '[/php]';

			if ($(element).children("cite:first").length === 1)
			{
				from = $(element).children("cite:first").text().trim();
				$(element).attr({'from': from.php_htmlspecialchars()});
				from = '=' + from;
				content = '';
				$(element).children("cite:first").remove();
				content = this.elementToBbcode($(element));
			}
			else
			{
				if (typeof $(element).attr('from') !== "undefined")
				{
					from = '=' + $(element).attr('from').php_unhtmlspecialchars();
				}
			}

			return '[code' + from + ']' + content.replace('&#91;', '[') + '[/code]';
		},
		quoteType: function(element) {
			return element;
		},
		html: function(element, attrs, content) {
			var from = '';
			if (typeof attrs.defaultattr !== "undefined")
				from = '<cite>' + attrs.defaultattr + '</cite>';

			return '<code>' + from + content.replace('[', '&#91;') + '</code>';
		}
	})
	.set('quote', {
		tags: {
			blockquote: null,
			cite: null
		},
		isInline: false,
		format: function(element, content) {
			var author = '',
				date = '',
				link = '',
				$elm  = $(element);

			if (element[0].tagName.toLowerCase() === 'cite')
				return '';

			if ($elm.attr('author'))
				author = ' author=' + $elm.attr('author').php_unhtmlspecialchars();
			if ($elm.attr('date'))
				date = ' date=' + $elm.attr('date');
			if ($elm.attr('link'))
				link = ' link=' + $elm.attr('link');

			return '[quote' + author + date + link + ']' + content + '[/quote]';
		},
		attrs: function () {
			return ['author', 'date', 'link'];
		},
		html: function(element, attrs, content) {
			var attr_author = '',
				sAuthor = '',
				attr_date = '',
				sDate = '',
				attr_link = '',
				sLink = '';

			// Author tag in the quote ?
			if (typeof attrs.author !== "undefined")
			{
				attr_author = attrs.author;
				sAuthor = bbc_quote_from + ': ' + attr_author;
			}
			// Done as [quote=someone]
			else if (typeof attrs.defaultattr !== "undefined")
			{
				// Convert it to an author tag
				attr_author = attrs.defaultattr;
				sAuthor = bbc_quote_from + ': ' + attr_author;
			}

			// Links could be in the form: link=topic=71.msg201#msg201 that would fool javascript, so we need a workaround
			for (var key in attrs)
			{
				if (key.substr(0, 4) === 'link' && attrs.hasOwnProperty(key))
				{
					attr_link = key.length > 4 ? key.substr(5) + '=' + attrs[key] : attrs[key];

					sLink = attr_link.substr(0, 7) === 'http://' ? attr_link : elk_scripturl + '?' + attr_link;
					sAuthor = sAuthor === '' ? '<a href="' + sLink + '">' + bbc_quote_from + ': ' + sLink + '</a>' : '<a href="' + sLink + '">' + sAuthor + '</a>';
				}
			}

			// A date perhaps
			if (typeof attrs.date !== "undefined")
			{
				attr_date = attrs.date;
				sDate = '<date timestamp="' + attr_date + '">' + new Date(attrs.date * 1000) + '</date>';
			}

			// Build the blockquote up with the data
			if (sAuthor === '' && sDate === '')
				sAuthor = bbc_quote;
			else
				sAuthor += sDate !== '' ? ' ' + bbc_search_on : '';

			content = '<blockquote author="' + attr_author + '" link="' + attr_link + '" date="' + attr_date + '"><cite>' + sAuthor + ' ' + sDate + '</cite>' + content + '</blockquote>';

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
		quoteType: $.sceditor.BBCodeParser.QuoteType.never,
		format: function(element, content) {
			var attribs = '',
				style = function(name) {
					return element.style ? element.style[name] : null;
				};

			// check if this is an emoticon image
			if (typeof element.attr('data-sceditor-emoticon') !== "undefined")
				return content;

			// only add width and height if one is specified
			if (element.attr('width') || style('width'))
				attribs += " width=" + $(element).width();
			if (element.attr('height') || style('height'))
				attribs += " height=" + $(element).height();

			return '[img' + attribs + ']' + element.attr('src') + '[/img]';
		},
		html: function(token, attrs, content) {
			var parts,
				attribs = '';

			// handle [img width=340 height=240]url[/img]
			if (typeof attrs.width !== "undefined")
				attribs += ' width="' + attrs.width + '"';
			if (typeof attrs.height !== "undefined")
				attribs += ' height="' + attrs.height + '"';

			return '<img' + attribs + ' src="' + content + '" />';
		}
	})
	.set('list', {
		breakStart: true,
		isInline: false,
		skipLastLineBreak: true,
		allowedChildren: ['*', 'li'],
		html: function(element, attrs, content) {
			var style = '',
				code = 'ul';

			if (attrs.type)
				style = 'style="list-style-type: ' + attrs.type + '"';
			return '<' + code + ' ' + style + '>' + content + '</' + code + '>';
		}
	})
	.set('li', {
		breakAfter: true
	})
	.set('ul', {
		tags: {
			ul: null
		},
		breakStart: true,
		format: function(element, content) {
			if ($(element[0]).css('list-style-type') === 'disc')
				return '[list]' + content + '[/list]';
			else
				return '[list type=' + $(element[0]).css('list-style-type') + ']' + content + '[/list]';
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
	}
);