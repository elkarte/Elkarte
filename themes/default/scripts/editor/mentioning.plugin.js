/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 2
 */

/** global: elk_session_var, elk_session_id, elk_scripturl, sceditor  */

/**
 * This file contains JavaScript associated with the @mentions function as it
 * relates to a sceditor invocation
 */
var disableDrafts = false;

(function (sceditor)
{
	'use strict';

	// Editor instance
	let editor;

	function Elk_Mentions(options)
	{
		// All the passed options and defaults are loaded to the opts object
		this.opts = $.extend({}, this.defaults, options);
	}

	Elk_Mentions.prototype.attachAtWho = function (element, oIframeWindow)
	{
		let mentioned = $('#mentioned');

		// Create / use a container to hold the results
		if (mentioned.length === 0)
		{
			$('#' + this.opts.editor_id).after(this.opts._mentioned);
		}
		else
		{
			this.opts._mentioned = mentioned;
		}

		this.opts.cache.mentions = this.opts._mentioned;

		let self = this;

		// Copy of a result list with each member id present only once
		const dedupe = function (list)
		{
			const seen = new Set();
			return (list || []).filter((item) => {
				if (seen.has(item.id))
				{
					return false;
				}
				seen.add(item.id);
				return true;
			});
		};

		AtWho.attach(element, {
			at: "@",
			limit: 10,
			maxLen: 25,
			displayTpl: "<li data-value='${atwho-at}${name}' data-id='${id}'>${name}</li>",
			acceptSpaceBar: true,
			callbacks: {
				filter: function (query, items, search_key)
				{
					// Already cached this query, then use it
					if (typeof self.opts.cache.names[query] !== 'undefined')
					{
						return dedupe(self.opts.cache.names[query]);
					}

					return dedupe(items);
				},
				// Well then let's make a find member suggest call
				remoteFilter: function (query, callback)
				{
					// Let be easy-ish on the server, don't go looking until we have at least two characters
					if (typeof query === 'undefined' || query.length < 2 || query.length > 25)
					{
						return;
					}

					// We allow spaces in the mention name, but stop after 2
					let spaces = query.match(/ /g);
					if (spaces && spaces.length > 1)
					{
						return;
					}

					// No slamming the server either
					let current_call = Math.round(new Date().getTime());
					if (self.opts._last_call !== 0 && self.opts._last_call + 150 > current_call)
					{
						// Throttled, reuse what we already know for this query (if anything)
						if (typeof self.opts.cache.names[query] !== 'undefined')
						{
							callback(dedupe(self.opts.cache.names[query]));
						}
						return;
					}

					// What we want
					var obj = {
						"suggest_type": "member",
						"search": query.php_urlencode(),
						"time": current_call
					};

					// Make the request
					suggest(self, obj, function (names)
					{
						// Update the time gate
						self.opts._last_call = current_call;

						// Update the cache with the values for reuse in local filter
						self.opts.cache.names[query] = names;

						// Update the query cache for use in revalidateMentions
						self.opts.cache.queries[self.opts.cache.queries.length] = query;

						callback(dedupe(names));
					});
				},
                beforeInsert: function (value, li)
				{
					self.addUID(li.dataset.id, li.dataset.value);

					return value;
				},
				matcher: function (flag, subtext, should_startWithSpace, acceptSpaceBar)
				{
					let match, space, regex_matcher;

					if (!subtext || subtext.length < 3)
					{
						return null;
					}

					if (should_startWithSpace)
					{
						flag = '(?:^|\\s)' + flag;
					}

					// Allow first last name entry?
					space = acceptSpaceBar ? "\ " : "";

					// No multiline flag: '$' must match only at the caret, never at an earlier line end
					// regexp = new RegExp(flag + '([^ <>&"\'=\\\\\n]*)$|' + flag + '([^\\x00-\\xff]*)$', 'gi');
					regex_matcher = new RegExp(flag + "([\\p{L}0-9_" + space + "\\[\\]\'\.\+\-]*)$", 'u');
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
					return offset;
				},
				afterMatchFailed: function (at, el)
				{
					// Leave an already inserted mention untouched
					if (el && (el.classList.contains('atwho-inserted') || el.hasAttribute('data-atwho-inserted-text')))
					{
						return false;
					}
				}
			}
		});

		// Use atwho selection box show/hide events to prevent autosave from firing
		if (oIframeWindow)
		{
			oIframeWindow.addEventListener('shown', function (e)
			{
				if (e.namespace === 'atwho')
				{
					disableDrafts = true;
				}
			});

			oIframeWindow.addEventListener('hidden', function (e)
			{
				if (e.namespace === 'atwho')
				{
					disableDrafts = false;
				}
			});
		}

		/**
		 * Sends a suggestion request to the server and retrieves the results.
		 *
		 * @param {Elk_Mentions} self - The context of the suggest function.
		 * @param {Object} obj - The object containing the suggestions parameters.
		 * @param {Function} callback - The function to be called after retrieving the suggestions.
		 */
		function suggest(self, obj, callback)
		{
			let postString = serialize(obj) + "&" + elk_session_var + "=" + elk_session_id;

			// Per request list, never shared between overlapping requests
			const names = [];
			const seen = new Set();
			fetch(elk_prepareScriptUrl(elk_scripturl) + "action=suggest;api=xml", {
				method: "POST",
				body: postString,
				headers: {
					'X-Requested-With': 'XMLHttpRequest',
					'Content-Type': 'application/x-www-form-urlencoded',
					'Accept': 'application/xml'
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
						const id = item.getAttribute('id');
						if (seen.has(id))
						{
							return;
						}
						seen.add(id);
						names.push({
							"id": id,
							"name": item.textContent
						});
					});
					callback(names);
				})
				.catch(function (error) {
					if ('console' in window && console.error)
					{
						console.error('Error: ', error.message);
					}
					callback(names);
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
		let base = this,
			oMentions;

		base.init = function ()
		{
			// Grab this instance for use in oMentions
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
			oMentions.attachAtWho(sceditor_textarea, null);

			// Using wysiwyg, then let's attach atwho to it
			if (!instance.opts.runWithoutWysiwygSupport)
			{
				// We need to monitor the iframe window and body to text input
				let oIframe = instance.getContentAreaContainer(),
					oIframeWindow = oIframe.contentWindow,
					oIframeBody = oIframe.contentDocument.body;

				oMentions.attachAtWho(oIframeBody, oIframeWindow);
			}
		};
	};
})(sceditor);
