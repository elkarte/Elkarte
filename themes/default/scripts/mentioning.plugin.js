/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Beta
 *
 * This file contains javascript associated with the atwho function as it
 * relates to an sceditor invocation
 */

(function($, window, document) {
	'use strict';

	function elk_Mentions(options) {
		// All the passed options and defaults are loaded to the opts object
		this.opts = $.extend({}, this.defaults, options);
	};

	elk_Mentions.prototype.attachAtWho = function(oMentions, $element, oIframeWindow) {
		var mentioned = document.getElementById('mentioned');

		if (mentioned === null)
			$('#' + oMentions.opts.editor_id).after(oMentions.opts._mentioned);
		else
			oMentions.opts._mentioned = $(mentioned);

		oMentions.opts.cache.mentions = this.opts._mentioned;

		$element.atwho({
			at: "@",
			limit: 7,
			cWindow: oIframeWindow,
			tpl: "<li data-value='${atwho-at}${name}' data-id='${id}'>${name}</li>",
			callbacks: {
				filter: function (query, items, search_key) {
					var current_call = parseInt(new Date().getTime() / 1000);

					// Let be easy-ish on the server, don't go looking until we have at least two characters
					if (query.length < 2)
						return [];

					// No slamming the server either
					if (oMentions.opts._last_call !== 0 && oMentions.opts._last_call + 1 > current_call)
						return oMentions.opts._names;

					// Already cached this query, then use it
					if (typeof oMentions.opts.cache.names[query] !== 'undefined')
						return oMentions.opts.cache.names[query];

					// Well then lets make a find member suggest call
					oMentions.opts._names = [];
					$.ajax({
						url: elk_scripturl + "?action=suggest;suggest_type=member;search=" + query.php_to8bit().php_urlencode() + ";" + elk_session_var + "=" + elk_session_id + ";xml;time=" + current_call,
						type: "get",
						async: false
					})
					.done(function(request) {
						$(request).find('item').each(function (idx, item) {
							if (typeof oMentions.opts._names[oMentions.opts._names.length] === 'undefined')
								oMentions.opts._names[oMentions.opts._names.length] = {};

							oMentions.opts._names[oMentions.opts._names.length - 1].id = $(item).attr('id');
							oMentions.opts._names[oMentions.opts._names.length - 1].name = $(item).text();
						});
					});

					// Save this information so we can reuse it
					oMentions.opts._last_call = current_call;

					// Update the cache with the values
					oMentions.opts.cache.names[query] = oMentions.opts._names;
					oMentions.opts.cache.queries[oMentions.opts.cache.queries.length] = query;

					return oMentions.opts._names;
				},
				before_insert: function(value, $li) {
					oMentions.addUID($li.data('id'), $li.data('value'));

					// Opera apparently doesn't remove the @ before value is inserted, so...let's remove it here
					if (is_opera && !base.inSourceMode())
						return value.replace($li.data('value'), $li.data('value').substring(1));

					return value;
				},
				matcher: function(flag, subtext, should_start_with_space) {
					var match, regexp;
					flag = flag.replace(/[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/g, "\\$&");
					if (should_start_with_space) {
						flag = '(?:^|\\s)' + flag;
					}
					regexp = new RegExp(flag + '([^ <>&"\'=\\\\\n]*)$|' + flag + '([^\\x00-\\xff]*)$', 'gi');
					match = regexp.exec(subtext.replace());
					if (match) {
						return match[2] || match[1];
					} else {
						return null;
					}
				},
				highlighter: function(li, query) {
					var regexp;
					if (!query) {
						return li;
					}
					// regexp from http://phpjs.org/functions/preg_quote/
					query = (query + '').replace(new RegExp('[.\\\\+*?\\[\\^\\]$(){}=!<>|:\\-]', 'g'), '\\$&');
					regexp = new RegExp(">\\s*(\\w*)(" + query.replace("+", "\\+") + ")(\\w*)\\s*<", 'ig');
					return li.replace(regexp, function(str, $1, $2, $3) {
						return '> ' + $1 + '<strong>' + $2 + '</strong>' + $3 + ' <';
					});
				}
			}
		});

		// This hook is triggered when atWho places a slection list on the screen, we use
		// it to properly place it next to the @text
		$(oIframeWindow).on("reposition.atwho", function(event, offset) {
			// We only need this for the wysiwyg window
			if (base.inSourceMode())
				return;

			// offset contains the top left values of the offset to the iframe
			// we need to convert that to main window coordinates
			var oIframe = $('#' + oMentions.opts.editor_id).parent().find('iframe').offset(),
				iLeft = oIframe.left + offset.left,
				iTop = oIframe.top,
				select_height = 0;

			// atWho adds 3 select areas, presumably for different positing on screen (above below etc)
			// This finds the active one and gets the container height
			// @todo find something better than this
			// @todo 64 is the character code @
			$('#at-view-64.atwho-view').each(function(index, element) {
				if ($(this).outerHeight() > 0)
					select_height += $(this).height() + 10;
			});

			// Now should we show the selection box above or below?
			var iWindowHeight = $(window).height(),
				iDocViewTop = $(window).scrollTop(),
				iSelectionPosition = iTop + offset.top - $(window).scrollTop(),
				iAvailableSpace = iWindowHeight - (iSelectionPosition - iDocViewTop);

		   if (iAvailableSpace >= select_height)
		   {
			   // Enough space below
			   iTop = iTop + offset.top + select_height - $(window).scrollTop();
		   }
		   else
		   {
			   // Place it above instead
			   // @todo should check if this is more space than below
			   iTop= iTop + offset.top - $(window).scrollTop();
		   }

			// Move the select box
			offset = {left: iLeft, top: iTop};
			$('#at-view-64.atwho-view').offset(offset);
		});
	};

	elk_Mentions.prototype.addUID = function(user_id, name) {
		this.opts._mentioned.append($('<input type="hidden" name="uid[]" />').val(user_id).attr('data-name', name));
	};

	/**
	 * Private mention vars
	 */
	elk_Mentions.prototype.defaults = {
		_names: [],
		_last_call: 0,
		_mentioned: $('<div style="display:none" />')
	};

	/**
	 * Holds all current mention (defaults + passed options)
	 */
	elk_Mentions.prototype.opts = {};

	/**
	 * Mentioning plugin interface to SCEditor
	 *  - Called from the editor as a plugin
	 *  - Monitors events so we control the elk_mention
	 */
	$.sceditor.plugins.mention = function() {
		var base = this,
			oMentions;

		/**
		 * Initialize, called when sceditor starts and initializes plugins
		 */
		base.signalReady = function() {
			// Init the mention instance, load in the options
			oMentions = new elk_Mentions(this.opts.mentionOptions);

			// Adds the selector to the list of known "mentioner"
			add_elk_mention(oMentions.opts.editor_id, {isPlugin: true});
			oMentions.attachAtWho(oMentions, $('#' + oMentions.opts.editor_id).parent().find('textarea'));

			// Using wysiwyg, then lets attach atwho to it
			var instance = $('#' + oMentions.opts.editor_id).sceditor('instance');
			if (!instance.opts.runWithoutWysiwygSupport)
			{
				// We need to monitor the iframe window and body to text input
				var oIframe = $('#' + oMentions.opts.editor_id).parent().find('iframe')[0],
					oIframeWindow = oIframe.contentWindow,
					oIframeBody = $('#' + oMentions.opts.editor_id).parent().find('iframe').contents().find('body')[0];

					oMentions.attachAtWho(oMentions, $(oIframeBody), oIframeWindow);
			}
		};
	};
})(jQuery, window, document);