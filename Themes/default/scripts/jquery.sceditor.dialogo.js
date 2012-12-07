/**
 * Extension functions to provide Dialogo compatibility with sceditor
 */
(function($) {
	var extensionMethods = {
		InsertText: function(text, bClear) {
			var bIsSource = this.inSourceMode();

			if(!bIsSource)
				this.toggleSourceMode();

			var current_value = bClear ? text + "\n" : this.getSourceEditorValue(false) + "\n" + text + "\n";
			this.setSourceEditorValue(current_value);

			if(!bIsSource)
				this.toggleSourceMode();
		},
		getText: function(filter) {
			var current_value = '';

			if(this.inSourceMode())
				current_value = this.getSourceEditorValue(false);
			else
				current_value  = this.getWysiwygEditorValue(filter);

			return current_value;
		},
		appendEmoticon: function (code, emoticon) {
			if(emoticon === '')
				line.append($('<br />'));
			else
				line.append($('<img />')
					.attr({
						src: emoticon.url || emoticon,
						alt: code,
						title: emoticon.tooltip || emoticon,
					})
					.click(function (e) {
						var	start = '', end = '';

						if(base.opts.emoticonsCompat)
						{
							start = '<span> ';
							end   = ' </span>';
						}

						if(base.inSourceMode())
							base.sourceEditorInsertText(' ' + $(this).attr('alt') + ' ');
						else
							base.wysiwygEditorInsertHtml(start + '<img src="' + $(this).attr("src") + '" data-sceditor-emoticon="' + $(this).attr('alt') + '" />' + end);

						e.preventDefault();
					})
				);

			if(line.children().length > 0)
				content.append(line);

			$(".sceditor-toolbar").append(content);
		},
		storeLastState: function (){
			this.wasSource = this.inSourceMode();
		},
		setTextMode: function () {
			if(!this.inSourceMode())
				this.toggleSourceMode();
		},
		createPermanentDropDown: function() {
			var	emoticons	= $.extend({}, this.opts.emoticons.dropdown);
			var popup_exists = false;
			base = this;

			content = $('<div class="sceditor-insertemoticon" />');
			line = $('<div />');

			for(smiley_popup in this.opts.emoticons.popup)
			{
				popup_exists = true;
				break;
			}

			if(popup_exists)
			{
				this.opts.emoticons.more = this.opts.emoticons.popup;
				moreButton = $('<div class="sceditor-more-button" />').attr({'class': "sceditor-more"}).text('[' + this._('More') + ']').click(function () {
					if($(".sceditor-smileyPopup").length > 0)
					{
						$(".sceditor-smileyPopup").fadeIn('fast');
					}
					else
					{
						var emoticons = $.extend({}, base.opts.emoticons.popup);
						var popup_position;
						var titlebar = $('<div class="catbg sceditor-popup-grip"/>');

						popupContent = $('<div id="sceditor-popup"/>');
						allowHide = true;
						line = $('<div id="sceditor-popup-smiley"/>');
						adjheight = 0;

						popupContent.append(titlebar);
						closeButton = $('<span />').text('[' + base._('Close') + ']').click(function () {
							$(".sceditor-smileyPopup").fadeOut('fast');
						});

						$.each(emoticons, base.appendEmoticon);

						if(line.children().length > 0)
							popupContent.append(line);
						if(typeof closeButton !== "undefined")
							popupContent.append(closeButton);

						// IE needs unselectable attr to stop it from unselecting the text in the editor.
						// The editor can cope if IE does unselect the text it's just not nice.
						if(base.ieUnselectable !== false) {
							content = $(content);
							content.find(':not(input,textarea)').filter(function() {return this.nodeType === 1;}).attr('unselectable', 'on');
						}

						$dropdown = $('<div class="sceditor-dropdown sceditor-smileyPopup" />').append(popupContent);
						$dropdown.appendTo($('body'));
						dropdownIgnoreLastClick = true;
						adjheight = closeButton.height() + titlebar.height();
						$dropdown.css({
							position: "fixed",
							top: $(window).height() * 0.2,
							left: $(window).width() * 0.5 - ($dropdown.find('#sceditor-popup-smiley').width() / 2),
							"max-width": "50%",
							"max-height": "50%",
						}).find('#sceditor-popup-smiley').css({
							height: $dropdown.height() - adjheight,
							"overflow": "auto"
						});

						$('.sceditor-smileyPopup').animaDrag({ 
							speed: 150, 
							interval: 120, 
							during: function(e) {
								$(this).height(this.startheight);
								$(this).width(this.startwidth);
							},
							before: function(e) {
								this.startheight = $(this).innerHeight();
								this.startwidth = $(this).innerWidth();
							},
							grip: '.sceditor-popup-grip' 
						});

						// stop clicks within the dropdown from being handled
						$dropdown.click(function (e) {
							e.stopPropagation();
						});
					}
				});
			}
			$.each(emoticons, base.appendEmoticon);
			if(typeof moreButton !== "undefined")
				content.append(moreButton);
		}
	};

	$.extend(true, $['sceditor'].prototype, extensionMethods);
})(jQuery);

/**
 * Dialogo unique commands to add to the toolbar
 *
 * Adds FTP, Glow, Shadow, Tt, Pre and Move commands
 */
$.sceditor.command.set(
	'ftp', {
		exec: function (caller) {
			var	editor  = this,
			content = $(this._('<form><div><label for="link">{0}</label> <input type="text" id="link" value="ftp://" /></div>' +
					'<div><label for="des">{1}</label> <input type="text" id="des" value="" /></div></form>',
				this._("URL:"),
				this._("Description (optional):")
			))
			.submit(function () {return false;});

			content.append($(
				this._('<div><input type="button" class="button" value="{0}" /></div>',
					this._("Insert")
				)).click(function (e) {
				var val = $(this).parent("form").find("#link").val(),
					description = $(this).parent("form").find("#des").val();

				if(val !== "" && val !== "ftp://") {
					// needed for IE to reset the last range
					editor.focus();

					if(!editor.getRangeHelper().selectedHtml() || description)
					{
						if(!description)
							description = val;

						editor.wysiwygEditorInsertHtml('<a href="' + val + '">' + description + '</a>');
					}
					else
						editor.execCommand("createlink", val);
				}

				editor.closeDropDown(true);
				e.preventDefault();
			}));

			editor.createDropDown(caller, "insertlink", content);
		},
		txtExec: ["[ftp]", "[/ftp]"],
		tooltip: 'Insert FTP Link',
	}
);

$.sceditor.command.set(
	'glow',	{
		exec: function () {
			this.wysiwygEditorInsertHtml('[glow=red,2,300]', '[/glow]');
		},
		txtExec: ["[glow=red,2,300]", "[/glow]"],
		tooltip: 'Glow',
	}
);

$.sceditor.command.set(
	'shadow', {
		exec: function () {
			this.wysiwygEditorInsertHtml('[shadow=red,left]', '[/shadow]');
		},
		txtExec: ["[shadow=red,left]", "[/shadow]"],
		tooltip: 'Shadow',
	}
);

$.sceditor.command.set(
	'tt', {
		exec: function () {
			this.wysiwygEditorInsertHtml('<tt>', '</tt>');
		},
		txtExec: ["[tt]", "[/tt]"],
		tooltip: 'Teletype',
	}
);

$.sceditor.command.set(
	'pre', {
		exec: function () {
			this.wysiwygEditorInsertHtml('<pre>', '</pre>');
		},
		txtExec: ["[pre]", "[/pre]"],
		tooltip: 'Preformatted Text',
	}
);

$.sceditor.command.set(
	'move', {
		exec: function () {
			this.wysiwygEditorInsertHtml('<marquee>', '</marquee>');
		},
		txtExec: ["[move]", "[/move]"],
		tooltip: 'Move',
	}
);

/**
 * Dialogo modifications to existing commands so they display as we like
 *
 * Makes changes to the text inserted for Bulletlist, OrderedList and Table
 */
$.sceditor.command.set(
	'bulletlist', {
		txtExec: ["[list]\n[li]", "[/li]\n[li][/li]\n[/list]"],
	}
);

$.sceditor.command.set(
	'orderedlist', {
		txtExec:  ["[list type=decimal]\n[li]", "[/li]\n[li][/li]\n[/list]"],
	}
);

$.sceditor.command.set(
	'table', {
		txtExec: ["[table]\n[tr]\n[td]", "[/td]\n[/tr]\n[/table]"],
	}
);

/**
 * Dialogo custom bbc tags added to provide for the existing user experience
 *
 * Adds BBC codes Abbr, Acronym, Bdo, List, Tt, Pre, Php, Move
 * Adds bbc colors Black, Red, Blue, Green, White
 */
$.sceditorBBCodePlugin.bbcode.set(
	'abbr', {
		tags: {
			abbr: {
				title: null
			}
		},
		format: function(element, content) {
			return '[abbr=' + element.attr('title') + ']' + content + '[/abbr]';
		},
		html: function(element, attrs, content) {
			if(typeof attrs.defaultattr === "undefined" || attrs.defaultattr.length === 0)
				return content;

			return '<abbr title="' + attrs.defaultattr + '">' + content + '</abbr>';
		}
	}
);

$.sceditorBBCodePlugin.bbcode.set(
	'acronym', {
		tags: {
			acronym: {
				title: null
			}
		},
		format: function(element, content) {
			return '[acronym=' + element.attr('title') + ']' + content + '[/acronym]';
		},
		html: function(element, attrs, content) {
			if(typeof attrs.defaultattr === "undefined" || attrs.defaultattr.length === 0)
				return content;

			return '<acronym title="' + attrs.defaultattr + '">' + content + '</acronym>';
		}
	}
);

$.sceditorBBCodePlugin.bbcode.set(
	'bdo', {
		tags: {
			bdo: {
				dir: null
			}
		},
		format: function(element, content) {
			return '[bdo=' + element.attr('dir') + ']' + content + '[/bdo]';
		},
		html: function(element, attrs, content) {
			if(typeof attrs.defaultattr === "undefined" || attrs.defaultattr.length === 0)
				return content;
			if(attrs.defaultattr != 'rtl' && attrs.defaultattr != 'ltr')
				return '[bdo=' + attrs.defaultattr + ']' + content + '[/bdo]';

			return '<bdo dir="' + attrs.defaultattr + '">' + content + '</bdo>';
		}
	}
);

$.sceditorBBCodePlugin.bbcode.set(
	'black', {
		isInline: true,
		format: '[black]{0}[/black]',
		html: '<font color="black">{0}</font>'
	}
);

$.sceditorBBCodePlugin.bbcode.set(
	'blue', {
		isInline: true,
		format: '[blue]{0}[/blue]',
		html: '<font color="blue">{0}</font>'
	}
);

$.sceditorBBCodePlugin.bbcode.set(
	'green', {
		isInline: true,
		format: '[green]{0}[/green]',
		html: '<font color="green">{0}</font>'
	}
);

$.sceditorBBCodePlugin.bbcode.set(
	'red', {
		isInline: true,
		format: '[red]{0}[/red]',
		html: '<font color="red">{0}</font>'
	}
);

$.sceditorBBCodePlugin.bbcode.set(
	'white', {
		isInline: true,
		format: '[white]{0}[/white]',
		html: '<font color="white">{0}</font>'
	}
);

$.sceditorBBCodePlugin.bbcode.set(
	'tt', {
		tags: {
			tt: null
		},
		format: "[tt]{0}[/tt]",
		html: '<tt>{0}</tt>'
	}
);

$.sceditorBBCodePlugin.bbcode.set(
	'php', {
		isInline: false,
		format: "[php]{0}[/php]",
		html: '<code class="php">{0}</code>'
	}
);

$.sceditorBBCodePlugin.bbcode.set(
	'pre', {
		tags: {
			pre: null
		},
		isInline: false,
		format: "[pre]{0}[/pre]",
		html: "<pre>{0}</pre>\n"
	}
);

$.sceditorBBCodePlugin.bbcode.set(
	'move', {
		tags: {
			marquee: null
		},
		format: "[move]{0}[/move]",
		html: '<marquee>{0}</marquee>'
	}
);

/**
 * Dialogo modified tags, modified so they support the existing paradigm
 *
 * Changes the way existing editor tags work 
 * Modifies code, quote, list, ul, ol, li
 */
$.sceditorBBCodePlugin.bbcode.set(
	'code', {
		tags: {
			code: null
		},
		isInline: false,
		allowedChildren: ['#', '#newline'],
		format: function(element, content) {
			var from = '';

			if($(element[0]).hasClass('php'))
				return '[php]' + content.replace('&#91;', '[') + '[/php]';

			if($(element).children("cite:first").length === 1)
			{
				from = $(element).children("cite:first").text();
				$(element).attr({'from': from.php_htmlspecialchars()});
				from = '=' + from;
				content = '';
				$(element).children("cite:first").remove();
				content = this.elementToBbcode($(element));
			}
			else
			{
				if(typeof $(element).attr('from') !== "undefined")
				{
					from = '=' + $(element).attr('from').php_unhtmlspecialchars();
				}
			}

			return '[code' + from + ']' + content.replace('&#91;', '[') + '[/code]';
		},
		html: function(element, attrs, content) {
			var from = '';
			if(typeof attrs.defaultattr !== "undefined")
				from = '<cite>' + attrs.defaultattr + '</cite>';

			return '<code>' + from + content.replace('[', '&#91;') + '</code>'
		}
	}
);

$.sceditorBBCodePlugin.bbcode.set(
	'quote', {
		tags: {
			blockquote: null,
			cite: null
		},
		breakBefore: false,
		isInline: false,
		format: function(element, content) {
			var author = '',
				date = '',
				link = '',
				$elm  = $(element);

			if(element[0].tagName.toLowerCase() === 'cite')
				return '';

			if($elm.attr('author'))
				author = ' author=' + $elm.attr('author').php_unhtmlspecialchars();
			if($elm.attr('date'))
				date = ' date=' + $elm.attr('date');
			if($elm.attr('link'))
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
			if(typeof attrs.author !== "undefined")
			{
				attr_author = attrs.author;
				sAuthor = bbc_quote_from + ': ' + attr_author;
			}

			// Links could be in the form: link=topic=71.msg201#msg201 that would fool javascript, so we need a workaround
			for(var key in attrs)
			{
				if(key.substr(0, 4) === 'link' && attrs.hasOwnProperty(key))
				{
					attr_link = key.length > 4 ? key.substr(5) + '=' + attrs[key] : attrs[key];

					sLink = attr_link.substr(0, 7) === 'http://' ? attr_link : smf_scripturl + '?' + attr_link;
					sAuthor = sAuthor === '' ? '<a href="' + sLink + '">' + bbc_quote_from + ': ' + sLink + '</a>' : '<a href="' + sLink + '">' + sAuthor + '</a>';
				}
			}

			// A date perhaps
			if(typeof attrs.date !== "undefined")
			{
				attr_date = attrs.date;
				sDate = '<date timestamp="' + attr_date + '">' + new Date(attrs.date * 1000) + '</date>';
			}

			// build the blockquote up with the data
			if(sAuthor === '' && sDate === '')
				sAuthor = bbc_quote;
			else
				sAuthor += sDate !== '' ? ' ' + bbc_search_on : '';

			content = '<blockquote author="' + attr_author + '" date="' + attr_date + '" link="' + attr_link + '"><cite>' + sAuthor + ' ' + sDate + '</cite>' + content + '</blockquote>';

			return content;
		}
	}
);

$.sceditorBBCodePlugin.bbcode.set(
	'list', {
		breakStart: true,
		isInline: false,
		skipLastLineBreak: true,
		allowedChildren: ['*', 'li'],
		html: function(element, attrs, content) {
			var style = '',
				code = 'ul';

			if(attrs.type)
				style = 'style="list-style-type: ' + attrs.type + '"';
			return '<' + code + ' ' + style + '>' + content + '</' + code + '>';
		}
	}
);

$.sceditorBBCodePlugin.bbcode.set(
	'li', {
		breakAfter: true,
	}
);

$.sceditorBBCodePlugin.bbcode.set(
	'ul', {
		tags: {
			ul: null
		},
		breakStart: true,
		format: function(element, content) {
			if($(element[0]).css('list-style-type') === 'disc')
				return '[list]' + content + '[/list]';
			else
				return '[list type=' + $(element[0]).css('list-style-type') + ']' + content + '[/list]';
		},
		isInline: false,
		skipLastLineBreak: true,
		html: '<ul>{0}</ul>'
	}
);

$.sceditorBBCodePlugin.bbcode.set(
	'ol', {
		tags: {
			ol: null
		},
		breakStart: true,
		isInline: false,
		skipLastLineBreak: true,
		format: "[list type=decimal]{0}[/list]",
		html: '<ol>{0}</ol>'
	}
);
