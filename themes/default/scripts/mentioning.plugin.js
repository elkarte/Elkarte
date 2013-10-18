/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
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

	elk_Mentions.prototype.attachAtWho = function(oMentions, $element) {
		$element.atwho({
			at: "@",
			limit: 7 ,
			tpl: "<li data-value='${atwho-at}${name}' data-id='${id}'>${name}</li>",
			callbacks: {
				filter: function (query, items, search_key) {
					var current_call = parseInt(new Date().getTime() / 1000);

					if (oMentions.opts._last_call != 0 && oMentions.opts._last_call + 1 > current_call)
						return oMentions.opts._names;

					if (typeof oMentions.opts._cached_names[query] != 'undefined')
						return oMentions.opts._cached_names[query];

					oMentions.opts._names = [];
					$.ajax({
						url: elk_scripturl + "?action=suggest;suggest_type=member;search=" + query.php_to8bit().php_urlencode() + ";" + elk_session_var + "=" + elk_session_id + ";xml;time=" + current_call,
						type: "get",
						async: false
					})
					.done(function(request) {
						$(request).find('item').each(function (idx, item) {
							if (typeof oMentions.opts._names[oMentions.opts._names.length] == 'undefined')
								oMentions.opts._names[oMentions.opts._names.length] = {};
							oMentions.opts._names[oMentions.opts._names.length - 1].id = $(item).attr('id');
							oMentions.opts._names[oMentions.opts._names.length - 1].name = $(item).text();
						});
					});
					oMentions.opts._last_call = current_call;
					oMentions.opts._cached_names[query] = oMentions.opts._names;
					return oMentions.opts._names;
				},
				before_insert: function (value, $li) {
					if (typeof oMentions.opts._mentioned == 'undefined')
					{
						oMentions.opts._mentioned = $('<div style="display:none" />');
						$('#' + oMentions.opts.editor_id).after(oMentions.opts._mentioned);
					}
					oMentions.opts._mentioned.append($('<input type="hidden" name="uid[]" />').val($li.data('id')));
					return value;
				}
			}
		});
	}

	/**
	 * Private mention vars
	 */
	elk_Mentions.prototype.defaults = {
		_names: [],
		_last_call: 0,
		_cached_names: []
	};

	/**
	 * Holds all current draft mention (defaults + passed options)
	 */
	elk_Mentions.prototype.opts = {};

	/**
	 * Draft plugin interface to SCEditor
	 *  - Called from the editor as a plugin
	 *  - Monitors events so we control the elk_draft autosaver (on/off/change)
	 */
	$.sceditor.plugins.mention = function() {
		var base = this,
			oMentions;

		/**
		 * Initialize, called when sceditor starts and initializes plugins
		 */
		base.signalReady = function() {
			// Init the draft instance, load in the options
			oMentions = new elk_Mentions(this.opts.mentionOptions);

			oMentions.attachAtWho(oMentions, $('#' + oMentions.opts.editor_id).parent().find('textarea'));
			var oIframe = $('#' + oMentions.opts.editor_id).parent().find('iframe')[0],
				oIframeWindow = oIframe.contentWindow;

// 			if (oIframeWindow !== null && oIframeWindow.document)
			{
				var oIframeDoc = oIframeWindow.document;
				var oIframeBody = oIframeDoc.body;

// 				$(oIframeBody).on(event, func);
			}
			oMentions.attachAtWho(oMentions, $(oIframeBody));
		};
	};

})(jQuery, window, document);