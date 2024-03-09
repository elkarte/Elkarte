/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 */

/** global: elk_session_var, elk_session_id, elk_scripturl, sceditor  */

/**
 * This file contains javascript associated with the @mentions function as it
 * relates to an sceditor invocation
 */
var disableDrafts = false;

(function (sceditor)
{
	'use strict';

	// Editor instance
	var editor;

	function Elk_Mentions(options)
	{
		// All the passed options and defaults are loaded to the opts object
		this.opts = $.extend({}, this.defaults, options);
	}

	Elk_Mentions.prototype.attachAtWho = function (oMentions, $element, oIframeWindow)
	{
		let mentioned = $('#mentioned'),
			corrected_offset = {};

		// Create / use a container to hold the results
		if (mentioned.length === 0)
		{
			$('#' + oMentions.opts.editor_id).after(oMentions.opts._mentioned);
		}
		else
		{
			oMentions.opts._mentioned = mentioned;
		}

		oMentions.opts.cache.mentions = this.opts._mentioned;

		$element.atwho({
			at: "@",
			limit: 8,
			maxLen: 25,
			displayTpl: "<li data-value='${atwho-at}${name}' data-id='${id}'>${name}</li>",
			acceptSpaceBar: true,
			callbacks: {
				filter: function (query, items, search_key)
				{
					// Already cached this query, then use it
					if (typeof oMentions.opts.cache.names[query] !== 'undefined')
					{
						return oMentions.opts.cache.names[query];
					}

					return items;
				},
				// Well then lets make a find member suggest call
				remoteFilter: function (query, callback)
				{
					// Let be easy-ish on the server, don't go looking until we have at least two characters
					if (typeof query === 'undefined' || query.length < 2 || query.length > 25)
					{
						return;
					}

					// No slamming the server either
					let current_call = Math.round(new Date().getTime());
					if (oMentions.opts._last_call !== 0 && oMentions.opts._last_call + 150 > current_call)
					{
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
					suggest(obj, function ()
					{
						// Update the time gate
						oMentions.opts._last_call = current_call;

						// Update the cache with the values for reuse in local filter
						oMentions.opts.cache.names[query] = oMentions.opts._names;

						// Update the query cache for use in revalidateMentions
						oMentions.opts.cache.queries[oMentions.opts.cache.queries.length] = query;

						callback(oMentions.opts._names);
					});
				},
				beforeInsert: function (value, $li)
				{
					// Set up for a new pull-down box/location
					corrected_offset = {};

					oMentions.addUID($li.data('id'), $li.data('value'));

					return value;
				},
				matcher: function (flag, subtext, should_startWithSpace, acceptSpaceBar)
				{
					let match, space, regex_matcher;

					if (!subtext || subtext.length < 3)
						return null;

					if (should_startWithSpace)
					{
						flag = '(?:^|\\s)' + flag;
					}

					// Allow first last name entry?
					space = acceptSpaceBar ? "\ " : "";

					// regexp = new RegExp(flag + '([^ <>&"\'=\\\\\n]*)$|' + flag + '([^\\x00-\\xff]*)$', 'gi');
					regex_matcher = new RegExp(flag + "([\\p{L}0-9_" + space + "\\[\\]\'\.\+\-]*)$", 'um');
					match = regex_matcher.exec(subtext);

					if (match)
					{
						return match[1];
					}
					else
					{
						return null;
					}
				},
				highlighter: function (li, query)
				{
					let regex_highlight;

					if (!query)
					{
						return li;
					}

					// Preg Quote regexp from http://phpjs.org/functions/preg_quote/
					query = query.replace(new RegExp('[.\\\\+*?\\[^\\]$(){}=!<>|:\\-]', 'g'), '\\$&');

					regex_highlight = new RegExp(">\\s*(\\w*)(" + query.replace("+", "\\+") + ")(\\w*)\\s*<", 'ig');
					return li.replace(regex_highlight, function (str, $1, $2, $3)
					{
						return '> ' + $1 + '<strong>' + $2 + '</strong>' + $3 + ' <';
					});
				},
				beforeReposition: function (offset)
				{
					// We only need to adjust when in wysiwyg
					if (editor.inSourceMode())
					{
						return offset;
					}

					if (Object.keys(corrected_offset).length === 0)
					{
						// Get the caret position, so we can add the mentions box there
						corrected_offset = editor.findCursorPosition('@');
					}

					offset.top = corrected_offset.top;
					offset.left = corrected_offset.left;

					return offset;
				},
				afterMatchFailed: function (at, el)
				{
					// Clear the offset
					corrected_offset = {};
				}
			}
		});

		// Use atwho selection box show/hide events to prevent autosave from firing
		if (Object.keys(oIframeWindow).length)
		{
			$(oIframeWindow).on("shown.atwho", function (event, offset)
			{
				disableDrafts = true;
			});

			$(oIframeWindow).on("hidden.atwho", function (event, offset)
			{
				disableDrafts = false;
			});
		}

		/**
		 * Makes the fetch call for data, returns to callback function when done.
		 *
		 * @param obj values to pass to action suggest
		 * @param callback function to call when we have completed our call
		 */
		function suggest(obj, callback)
		{
			let postString = serialize(obj) + "&" + elk_session_var + "=" + elk_session_id;

			oMentions.opts._names = [];
			fetch(elk_prepareScriptUrl(elk_scripturl) + "action=suggest;api=xml", {
				method: "POST",
				body: postString,
				headers: {
					'X-Requested-With': 'XMLHttpRequest',
					'Content-Type': 'application/x-www-form-urlencoded'
				}
			})
				.then(response => {
					if (!response.ok)
					{
						throw new Error("HTTP error " + response.status);
					}
					return response.text();
				})
				.then(str => new window.DOMParser().parseFromString(str, "text/xml"))
				.then(data => {
					let items = data.querySelectorAll('item');
					items.forEach((item, idx) => {
						oMentions.opts._names[idx] = {
							"id": item.getAttribute('id'),
							"name": item.textContent
						};
					});
					console.log(callback);
					callback();
				})
				.catch(function (error) {
					if ('console' in window && console.info)
					{
						console.info('Error: ', error.message);
					}
					callback();
				});
		}
	};

	/**
	 * Called when a name is selected from the mentions list
	 */
	Elk_Mentions.prototype.addUID = function (user_id, name)
	{
		this.opts._mentioned.append($('<input type="hidden" name="uid[]" />').val(user_id).attr('data-name', name));
	};

	/**
	 * Private mention vars
	 */
	Elk_Mentions.prototype.defaults = {
		_names: [],
		_last_call: 0,
		_mentioned: $('<div id="mentioned" style="display: none;" />')
	};

	/**
	 * Holds all current mention (defaults + passed options)
	 */
	Elk_Mentions.prototype.opts = {};

	/**
	 * Mentioning plugin interface to SCEditor
	 *  - Called from the editor as a plugin
	 *  - Monitors events so we control the elk_mention
	 */
	sceditor.plugins.mention = function ()
	{
		var base = this,
			oMentions;

		base.init = function ()
		{
			// Grab this instance for use use in oMentions
			editor = this;
		};

		/**
		 * Initialize, called when sceditor starts and initializes plugins
		 */
		base.signalReady = function ()
		{
			// Init the mention instance, load in the options
			oMentions = new Elk_Mentions(this.opts.mentionOptions);

			let original_textarea = document.getElementById(oMentions.opts.editor_id),
				instance = sceditor.instance(original_textarea),
				sceditor_textarea = instance.getContentAreaContainer().nextSibling;

			// Adds the selector to the list of known "mentioner"
			add_elk_mention(oMentions.opts.editor_id, {isPlugin: true});
			oMentions.attachAtWho(oMentions, $(sceditor_textarea), {});

			// Using wysiwyg, then lets attach atwho to it
			if (!instance.opts.runWithoutWysiwygSupport)
			{
				// We need to monitor the iframe window and body to text input
				let oIframe = instance.getContentAreaContainer(),
					oIframeWindow = oIframe.contentWindow,
					oIframeBody = oIframe.contentDocument.body;

				oMentions.attachAtWho(oMentions, $(oIframeBody), oIframeWindow);
			}
		};
	};
})(sceditor);
