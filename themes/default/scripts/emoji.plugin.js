/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

/**
 * This file contains javascript associated with the :emoji: function as it
 * relates to a sceditor invocation
 */
var disableDrafts = false;

(function (sceditor) {
	'use strict';

	// Editor instance
	let editor;

	// Populated with unicode key when shortname is found in emojies array
	let emojieskey;

	/**
	 * Load in options
	 *
	 * @param {object} options
	 */
	function Elk_Emoji(options)
	{
		// All the passed options and defaults are loaded to the opts object
		this.opts = $.extend({}, this.defaults, options);
	}

	/**
	 * Helper function to see if a :tag: emoji value exists in our array
	 * if found will populate emojis key with the corresponding value
	 *
	 * @param {string} emoji
	 */
	Elk_Emoji.prototype.emojiExists = function (emoji)
	{
		return emojies.some(function (el)
		{
			if (el.name === emoji)
			{
				emojieskey = el.key;
				return true;
			}
		});
	};

	/**
	 * Attach atwho to the passed $element, so we create a pull down list
	 *
	 * @param {object} $element
	 * @param {object} oIframeWindow
	 */
	Elk_Emoji.prototype.attachAtWho = function ($element, oIframeWindow)
	{
		/**
		 * Create the dropdown selection list
		 * Inserts the site image location when one is selected.
		 * Uses the CDN for the pulldown image to reduce site calls
		 */
		let tpl,
			emoji_url = this.opts.emoji_url,
			emoji_group = this.opts.emoji_group;

		// Use CDN calls to populate the atwho selection list
		switch(emoji_group) {
			case 'twemoji':
				//tpl = "https://twemoji.maxcdn.com/16x16/${key}.png";
				tpl = "https://twemoji.maxcdn.com/svg/${key}.svg";
				break;
			case 'emojitwo':
				tpl = "https://rawcdn.githack.com/EmojiTwo/emojitwo/d79b4477eb8f9110fc3ce7bed2cc66030a77933e/svg/${key}.svg";
				break;
			case 'noto-emoji':
				tpl = "https://rawcdn.githack.com/googlefonts/noto-emoji/e7ac893b3315181f51710de3ba16704ec95e3f51/svg/emoji_u${key}.svg";
				break;
			default:
				tpl = "http://cdn.jsdelivr.net/emojione/assets/png/${key}.png";
		}

		// Create the emoji select list and insert choice in to the editor
		$element.atwho({
			at: ":",
			data: emojies,
			maxLen: 25,
			limit: 8,
			acceptSpaceBar: true,
			displayTpl: "<li data-value=':${name}:'><img class='emoji_tpl' src='" + tpl + "' />${name}</li>",
			insertTpl: "${name} | ${key}",
			callbacks: {
				filter: function (query, items, search_key)
				{
					// Don't show the list until they have entered at least two characters
					if (query.length < 2)
					{
						return [];
					}

					return items;
				},
				beforeInsert: function (value)
				{
					tpl = value.split(" | ");

					if (editor.inSourceMode())
					{
						return ":" + tpl[0] + ":";
					}

					return "<img class='emoji' data-sceditor-emoticon=':" + tpl[0] + ":' alt=':" + tpl[1] + ":' title='" + tpl[0] + "' src='" + emoji_url + tpl[1] + ".svg' />";
				},
				tplEval: function (tpl, map)
				{
					try
					{
						return tpl.replace(/\$\{([^\}]*)\}/g, function(tag, key, pos)
						{
							return map[key];
						});
					}
					catch (_error)
					{
						if ('console' in window)
						{
							window.console.info(_error);
						}

						return "";
					}
				},
				beforeReposition: function (offset)
				{
					// We only need to adjust when in wysiwyg
					if (editor.inSourceMode())
					{
						return offset;
					}

					// Get the caret position, so we can add the emoji box there
					let corrected_offset = editor.findCursorPosition(':');

					offset.top = corrected_offset.top;
					offset.left = corrected_offset.left;

					return offset;
				}
			}
		});

		// Don't save a draft due to a emoji window open/close
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

		// Attach a click event to the toggle button, can't find a good plugin event to use
		// for this purpose
		if (Object.keys(oIframeWindow).length)
		{
			let opts = this.opts;

			$(".sceditor-button-source").on("click", function (event, offset)
			{
				// If the button has the active class, we clicked and entered wizzy mode
				if (!$(this).hasClass("active"))
				{
					Elk_Emoji.prototype.processEmoji(opts);
				}
			});
		}
	};

	/**
	 * Fetches the HTML from the editor window and updates any emoji :tags: with img tags
	 */
	Elk_Emoji.prototype.processEmoji = function (opts)
	{
		let instance, // sceditor instance
			str, // current html in the editor
			emoji_regex = new RegExp("(:([-+\\w]+):)", "gi"), // find emoji
			code_regex = new RegExp("(</code>|<code(?:[^>]+)?>)", "gi"), // split around code tags
			str_split,
			i,
			n;

		// Get the editors instance and html code from the window
		instance = sceditor.instance(document.getElementById(opts.editor_id));
		str = instance.getWysiwygEditorValue(false);

		// Only convert emoji outside <code> tags.
		str_split = str.split(code_regex);
		n = str_split.length;

		// Process the strings
		for (i = 0; i < n; i++)
		{
			// Only look for emoji outside the code tags
			if (i % 4 === 0)
			{
				// Search for emoji :tags: and replace known ones with the right image
				str_split[i] = str_split[i].replace(emoji_regex, Elk_Emoji.prototype.process).replace('{emoji_url}', opts.emoji_url);
			}
		}

		// Put it all back together
		str = str_split.join('');

		// Replace the editors html with the update html
		instance.val(str, false);
	};

	Elk_Emoji.prototype.process = function(match, tag, shortname)
	{
		// Replace all valid emoji tags with the image tag
		if (typeof shortname === 'undefined' || shortname === '' || !(Elk_Emoji.prototype.emojiExists(shortname)))
		{
			return match;
		}

		return '<img data-sceditor-emoticon="' + tag + '" class="emoji" alt="' + tag + '" title="' + shortname + '" src="{emoji_url}' + emojieskey + '.svg" />';
	};

	/**
	 * Private emoji vars
	 */
	Elk_Emoji.prototype.defaults = {_names: []};

	/**
	 * Holds all current emoji (defaults + passed options)
	 */
	Elk_Emoji.prototype.opts = {};

	/**
	 * Emoji plugin interface to SCEditor
	 *  - Called from the editor as a plugin
	 *  - Monitors events, so we control the emoji's
	 */
	sceditor.plugins.emoji = function ()
	{
		let base = this,
			oEmoji;

		/**
		 * Called before signalReady as part of the editor startup process
		 */
		base.init = function ()
		{
			// Grab this instance for use in oEmoji
			editor = this;
		};

		/**
		 * Initialize, called when sceditor starts and initializes plugins
		 */
		base.signalReady = function ()
		{
			// Set up the options
			this.opts.emojiOptions.emoji_url = elk_smileys_url.replace("default", this.opts.emojiOptions.emoji_group);
			if (typeof this.opts.emojiOptions.editor_id === 'undefined')
			{
				this.opts.emojiOptions.editor_id = post_box_name;
			}

			// Load the emoji file, then call start the instance
			let promise = base.getScript(elk_theme_url + '/scripts/emoji_tags.js');
			promise.then(
				script =>
				{
					// Init the emoji instance, load in the options
					oEmoji = new Elk_Emoji(this.opts.emojiOptions);

					let original_textarea = document.getElementById(oEmoji.opts.editor_id),
						instance = sceditor.instance(original_textarea),
						sceditor_textarea = instance.getContentAreaContainer().nextSibling;

					// Attach atwho to the editors source textarea
					oEmoji.attachAtWho($(sceditor_textarea), {});

					// Using wysiwyg, then lets attach atwho to the wysiwyg container as well
					if (!instance.opts.runWithoutWysiwygSupport)
					{
						// We need to monitor the iframe window and body to text input
						let oIframe = instance.getContentAreaContainer(),
							oIframeWindow = oIframe.contentWindow,
							oIframeBody = oIframe.contentDocument.body;

						oEmoji.attachAtWho($(oIframeBody), oIframeWindow);
					}
				},
				error => {
					if ('console' in window)
					{
						window.console.info(`Error: ${error.message}`);
					}
				}
			);
		};

		/**
		 * Simple "require_once" to load the emoji object via promise
		 *
		 * @param scriptUrl
		 */
		base.getScript = function(scriptUrl) {
			return new Promise(function(resolve, reject)
			{
				const script = document.createElement('script');

				script.src = scriptUrl;
				script.onload = () => resolve(script);
				script.onerror = () => reject(new Error(`Script load error for ${src}`));

				document.head.append(script);
			});
		};
	};
})(sceditor);
