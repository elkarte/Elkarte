/*!
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 beta 3
 */

/**
 * This file contains javascript associated with the atwho function as it
 * relates to an sceditor invocation
 */
var disableDrafts = false;

(function($, window, document) {
	'use strict';

	// Editor instance
	var editor,
		rangeHelper;

	function elk_Mentions(options) {
		// All the passed options and defaults are loaded to the opts object
		this.opts = $.extend({}, this.defaults, options);
	}

	elk_Mentions.prototype.attachAtWho = function(oMentions, $element, oIframeWindow) {
		var mentioned = $('#mentioned');

		// Create / use a container to hold the results
		if (mentioned.length === 0)
			$('#' + oMentions.opts.editor_id).after(oMentions.opts._mentioned);
		else
			oMentions.opts._mentioned = mentioned;

		oMentions.opts.cache.mentions = this.opts._mentioned;

		$element.atwho({
			at: "@",
			limit: 8,
			maxLen: 25,
			displayTpl: "<li data-value='${atwho-at}${name}' data-id='${id}'>${name}</li>",
			acceptSpaceBar: true,
			callbacks: {
				filter: function (query, items, search_key) {
					// Already cached this query, then use it
					if (typeof oMentions.opts.cache.names[query] !== 'undefined') {
						return oMentions.opts.cache.names[query];
					}

					return [];
				},
				// Well then lets make a find member suggest call
				remoteFilter: function(query, callback) {
					// Let be easy-ish on the server, don't go looking until we have at least two characters
					if (query.length < 2)
						return;

					// No slamming the server either
					var current_call = parseInt(new Date().getTime() / 1000);
					if (oMentions.opts._last_call !== 0 && oMentions.opts._last_call + 0.5 > current_call) {
						callback(oMentions.opts._names);
						return;
					}

					// What we want
					var obj = {
						"suggest_type": "member",
						"search": query.php_urlencode(),
						"time": current_call
					};

					// Make the request
					suggest(obj, function() {
						// Update the time gate
						oMentions.opts._last_call = current_call;

						// Update the cache with the values for reuse in local filter
						oMentions.opts.cache.names[query] = oMentions.opts._names;

						// Update the query cache for use in revalidateMentions
						oMentions.opts.cache.queries[oMentions.opts.cache.queries.length] = query;

						callback(oMentions.opts._names);
					});
				},
				beforeInsert: function(value, $li) {
					oMentions.addUID($li.data('id'), $li.data('value'));

					return value;
				},
				matcher: function(flag, subtext, should_startWithSpace, acceptSpaceBar) {
					var _a, _y, match, regexp, space;

					flag = flag.replace(/[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/g, "\\$&");

					if (should_startWithSpace) {
						flag = '(?:^|\\s)' + flag;
					}

					// Allow À - ÿ
					_a = decodeURI("%C3%80");
					_y = decodeURI("%C3%BF");

					// Allow first last name entry?
					space = acceptSpaceBar ? "\ " : "";

					// regexp = new RegExp(flag + '([^ <>&"\'=\\\\\n]*)$|' + flag + '([^\\x00-\\xff]*)$', 'gi');
					regexp = new RegExp(flag + "([A-Za-z" + _a + "-" + _y + "0-9_" + space + "\\[\\]\'\.\+\-]*)$|" + flag + "([^\\x00-\\xff]*)$", 'gi');
					match = regexp.exec(subtext);

					if (match) {
						return match[2] || match[1];
					}
					else {
						return null;
					}
				},
				highlighter: function(li, query) {
					var regexp;

					if (!query)
						return li;

					// Preg Quote regexp from http://phpjs.org/functions/preg_quote/
					query = query.replace(new RegExp('[.\\\\+*?\\[\\^\\]$(){}=!<>|:\\-]', 'g'), '\\$&');

					regexp = new RegExp(">\\s*(\\w*)(" + query.replace("+", "\\+") + ")(\\w*)\\s*<", 'ig');
					return li.replace(regexp, function(str, $1, $2, $3) {
						return '> ' + $1 + '<strong>' + $2 + '</strong>' + $3 + ' <';
					});
				},
				beforeReposition: function (offset) {
					// We only need to adjust when in wysiwyg
					if (editor.inSourceMode())
						return offset;

					// Lets get the caret position so we can add the mentions box there
					var corrected_offset = findAtPosition();

					offset.top = corrected_offset.top;
					offset.left = corrected_offset.left;

					return offset;
				}
			}
		});

		// Use atwho selection box show/hide events to prevent autosave from firing
		$(oIframeWindow).on("shown.atwho", function(event, offset) {
			disableDrafts = true;
		});

		$(oIframeWindow).on("hidden.atwho", function(event, offset) {
			disableDrafts = false;
		});

		/**
		 * Makes the ajax call for data, returns to callback function when done.
		 *
		 * @param obj values to pass to action suggest
		 * @param callback function to call when we have completed our call
		 */
		function suggest(obj, callback)
		{
			var postString = "jsonString=" + JSON.stringify(obj) + "&" + elk_session_var + "=" + elk_session_id;

			oMentions.opts._names = [];

			$.ajax({
				url: elk_scripturl + "?action=suggest;xml",
				type: "post",
				data: postString,
				dataType: "xml"
			})
			.done(function(data) {
				$(data).find('item').each(function (idx, item) {
					if (typeof oMentions.opts._names[oMentions.opts._names.length] === 'undefined')
						oMentions.opts._names[oMentions.opts._names.length] = {};

					oMentions.opts._names[oMentions.opts._names.length - 1] = {
						"id": $(item).attr('id'),
						"name": $(item).text()
					};
				});

				callback();
			})
			.fail(function(jqXHR, textStatus, errorThrown) {
				if ('console' in window) {
					window.console.info('Error:', textStatus, errorThrown.name);
					window.console.info(jqXHR.responseText);
				}

				callback();
			});
		}

		/**
		 * Determine the caret position inside of sceditor's iframe
		 *
		 * What it does:
		 * - Caret.js does not seem to return the correct position for (FF & IE) when
		 * the iframe has vertically scrolled.
		 * - This is an sceditor specific function to return a screen caret position
		 * - Called just before At.js adds the mentions dropdown box
		 * - Finds the @mentions tag and adds an invisible zero width space before it
		 * - Gets the location offset() in the iframe "window" of the added space
		 * - Adjusts for the iframe scroll
		 * - Adds in the iframe container location offset() to main window
		 * - Removes the space, restores the editor range.
		 *
		 * @returns {{}}
		 */
		function findAtPosition() {
			// Get sceditor's RangeHelper for use
			rangeHelper = editor.getRangeHelper();

			// Save the current state
			rangeHelper.saveRange();

			var start = rangeHelper.getMarker('sceditor-start-marker'),
				parent = start.parentNode,
				prev = start.previousSibling,
				offset = {},
				atPos,
				placefinder;

			// Create a placefinder span containing a 'ZERO WIDTH SPACE' Character
			placefinder = start.ownerDocument.createElement('span');
			$(placefinder).text("200B").addClass('placefinder');

			// Look back and find the mentions @ tag, so we can insert our span ahead of it
			while (prev) {
				atPos = (prev.nodeValue || '').lastIndexOf('@');

				// Found the start of @mention
				if (atPos > -1) {
					parent.insertBefore(placefinder, prev.splitText(atPos + 1));
					break;
				}

				prev = prev.previousSibling;
			}

			// If we were successful in adding the placefinder
			if (placefinder.parentNode) {
				var $_placefinder = $(placefinder);

				// offset() returns the top offset inside the total iframe, so we need the vertical scroll
				// value to adjust back to main window position
				//	wizzy_height = $('#' + oMentions.opts.editor_id).parent().find('iframe').height(),
				//	wizzy_window = $('#' + oMentions.opts.editor_id).parent().find('iframe').contents().height(),
				var	wizzy_scroll = $('#' + oMentions.opts.editor_id).parent().find('iframe').contents().scrollTop();

				// Determine its Location in the iframe
				offset = $_placefinder.offset();

				// If we have scrolled, then we also need to account for those offsets
				offset.top -= wizzy_scroll;
				offset.top += $_placefinder.height();

				// Remove our placefinder
				$_placefinder.remove();
			}

			// Put things back just like we found them
			rangeHelper.restoreRange();

			// Add in the iframe's offset to get the final location.
			if (offset) {
				var iframeOffset = editor.getContentAreaContainer().offset();

				// Some fudge for the kids
				offset.top += iframeOffset.top + 5;
				offset.left += iframeOffset.left + 5;
			}

			return offset;
		}
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
		_mentioned: $('<div id="mentioned" style="display: none;" />')
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

		base.init = function() {
			// Grab this instance for use use in oMentions
			editor = this;
		};

		/**
		 * Initialize, called when sceditor starts and initializes plugins
		 */
		base.signalReady = function() {
			// Init the mention instance, load in the options
			oMentions = new elk_Mentions(this.opts.mentionOptions);

			var $option_eid = $('#' + oMentions.opts.editor_id);

			// Adds the selector to the list of known "mentioner"
			add_elk_mention(oMentions.opts.editor_id, {isPlugin: true});
			oMentions.attachAtWho(oMentions, $option_eid.parent().find('textarea'));

			// Using wysiwyg, then lets attach atwho to it
			var instance = $option_eid.sceditor('instance');
			if (!instance.opts.runWithoutWysiwygSupport)
			{
				// We need to monitor the iframe window and body to text input
				var oIframe = $option_eid.parent().find('iframe')[0],
					oIframeWindow = oIframe.contentWindow,
					oIframeBody = $(oIframe.contentDocument.body);

					oMentions.attachAtWho(oMentions, oIframeBody, oIframeWindow);
			}
		};
	};
})(jQuery, window, document);