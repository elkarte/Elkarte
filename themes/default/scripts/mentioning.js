/*!
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 beta 3
 */

/**
 * This file contains javascript associated with the mentioning function as it
 * relates to a plain text box (no sceditor invocation) eg quick reply no editor
 */

/**
 * The constructor for the plain text box atWho function
 *
 * @param {object} oOptions
 */
function elk_mentions(oOptions)
{
	this.last_call = 0;
	this.names = [];
	this.cached_names = [];
	this.cached_queries = [];
	this.mentions = null;
	this.$atwho = null;

	this.opt = oOptions;
	this.attachAtWho();
}

/**
 * Invoke the atWho functions on the object specified
 *
 * @returns {undefined}
 */
elk_mentions.prototype.attachAtWho = function ()
{
	var _self = this;

	// We need a container to display the names in
	_self.$atwho = $(this.opt.selector);
	_self.mentions = $('<div style="display:none" />');
	_self.$atwho.after(this.mentions);

	// Attach the atwho to our container
	_self.$atwho.atwho({
		at: "@",
		limit: 7,
		maxLen: 25,
		displayTpl: "<li data-value='${atwho-at}${name}' data-id='${id}'>${name}</li>",
		callbacks: {
			filter: function (query, items, search_key) {
				var current_call = parseInt(new Date().getTime() / 1000);

				// Let be easy-ish on the server, don't go looking until we have at least two characters
				if (query.length < 2)
					return [];

				// No slamming the server either
				if (_self.last_call !== 0 && _self.last_call + 1 > current_call)
					return _self.names;

				// Already cached this query, then use it
				if (typeof(_self.cached_names[query]) !== 'undefined')
					return _self.cached_names[query];

				// Fine then, you really do need a list so lets request one
				if (elk_formSubmitted)
					return [];

				// Well then lets make a find member suggest call
				_self.names = [];

				// What we want
				var obj = {
						"suggest_type": "member",
						"search": query.php_urlencode(),
						"time": current_call
					};
				obj[elk_session_var] = elk_session_id;

				// And how to ask for it
				$.ajax({
					url: elk_scripturl + "?action=suggest;xml",
					data: obj,
					type: "post",
					async: false
				})
				.done(function(request) {
					$(request).find('item').each(function (idx, item) {
						// New request, lets start fresh
						if (typeof(_self.names[_self.names.length]) === 'undefined')
							_self.names[_self.names.length] = {};

						// Add each one to the dropdown list for view/selection
						_self.names[_self.names.length - 1].id = $(item).attr('id');
						_self.names[_self.names.length - 1].name = $(item).text();
					});
				})
				.fail(function(jqXHR, textStatus, errorThrown) {
					if ('console' in window) {
						window.console.info('Error:', textStatus, errorThrown.name);
						window.console.info(jqXHR.responseText);
					}
				});

				// Save this information so we can reuse it
				_self.last_call = current_call;

				// Save it to the cache for this mention
				_self.cached_names[query] = _self.names;
				_self.cached_queries[_self.cached_queries.length] = query;

				return _self.names;
			},
			beforeInsert: function (value, $li) {
				_self.mentions.append($('<input type="hidden" name="uid[]" />').val($li.data('id')).attr('data-name', $li.data('value')));

				return value;
			},
			matcher: function(flag, subtext, should_start_with_space) {
				var match,
					regexp;

				flag = flag.replace(/[\-\[\]\/\{}\(\)\*\+\?\.\\\^\$\|]/g, "\\$&");

				if (should_start_with_space)
					flag = '(?:^|\\s)' + flag;

				regexp = new RegExp(flag + '([^ <>&"\'=\\\\\n]*)$|' + flag + '([^\\x00-\\xff]*)$', 'gi');
				match = regexp.exec(subtext.replace());
				if (match)
					return match[2] || match[1];
				else
					return null;
			},
			highlighter: function(li, query) {
				var regexp;

				if (!query)
					return li;

				// regexp from http://phpjs.org/functions/preg_quote/
				query = (query + '').replace(new RegExp('[.\\\\+*?\\[\\^\\]$(){}=!<>|:\\-]', 'g'), '\\$&');
				regexp = new RegExp(">\\s*(\\w*)(" + query.replace("+", "\\+") + ")(\\w*)\\s*<", 'ig');
				return li.replace(regexp, function(str, $1, $2, $3) {
					return '> ' + $1 + '<strong>' + $2 + '</strong>' + $3 + ' <';
				});
			}
		}
	});
};