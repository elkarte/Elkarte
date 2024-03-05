/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 */

/**
 * This file contains javascript associated with the mentioning function as it
 * relates to a plain text box as found in quick modify/edit
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
 */
elk_mentions.prototype.attachAtWho = function () {
	var _self = this;

	// We need a container to display the names in
	_self.$atwho = $(this.opt.selector);
	_self.mentions = $('<div style="display:none" />');
	_self.$atwho.after(this.mentions);

	// Attach the atwho to our container
	_self.$atwho.atwho({
		at: "@",
		limit: 8,
		maxLen: 25,
		displayTpl: "<li data-value='${atwho-at}${name}' data-id='${id}'>${name}</li>",
		callbacks: {
			filter: function (query, items, search_key) {
				// Already cached this query, then use it
				if (typeof _self.cached_names[query] !== 'undefined')
				{
					return _self.cached_names[query];
				}

				return items;
			},
			remoteFilter: function (query, callback) {
				// Let be easy-ish on the server, don't go looking until we have at least two characters
				if (typeof query === 'undefined' || query.length < 2 || query.length > 25)
				{
					return [];
				}

				// No slamming the server either
				let current_call = Math.round(new Date().getTime());
				if (_self.last_call !== 0 && _self.last_call + 150 > current_call)
				{
					return _self.names;
				}

				// Already cached this query, then use it
				if (typeof (_self.cached_names[query]) !== 'undefined')
				{
					return _self.cached_names[query];
				}

				// Fine then, you really do need a list so lets request one
				if (elk_formSubmitted)
				{
					return [];
				}

				// What we want
				let obj = {
					"suggest_type": "member",
					"search": query.php_urlencode(),
					"time": current_call
				};

				// And now ask for a list of names
				suggest(obj, function () {
					// Update the time gate
					_self.last_call = current_call;

					// Update the cache
					_self.cached_names[query] = _self.names;
					_self.cached_queries[_self.cached_queries.length] = query;

					callback(_self.names);
				});
			},
			beforeInsert: function (value, $li) {
				_self.mentions.append($('<input type="hidden" name="uid[]" />').val($li.data('id')).attr('data-name', $li.data('value')));

				return value;
			},
			matcher: function (flag, subtext, should_start_with_space, acceptSpaceBar) {
				let match, regex_matcher, space;

				if (!subtext || subtext.length < 3)
					return null;

				if (should_start_with_space)
				{
					flag = '(?:^|\\s)' + flag;
				}

				// Allow first last name entry?
				space = acceptSpaceBar ? "\ " : "";

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
			highlighter: function (li, query) {
				let regex_highlight;

				if (!query)
				{
					return li;
				}

				// Preg Quote regexp from http://phpjs.org/functions/preg_quote/
				query = query.replace(new RegExp('[.\\\\+*?\\[^\\]$(){}=!<>|:\\-]', 'g'), '\\$&');

				regex_highlight = new RegExp(">\\s*(\\w*)(" + query.replace("+", "\\+") + ")(\\w*)\\s*<", 'ig');
				return li.replace(regex_highlight, function (str, $1, $2, $3) {
					return '> ' + $1 + '<strong>' + $2 + '</strong>' + $3 + ' <';
				});
			}
		}
	});

	function suggest(obj, callback)
	{
		let postString = serialize(obj) + "&" + elk_session_var + "=" + elk_session_id;

		fetch(elk_scripturl + "?action=suggest;api=xml", {
			method: 'POST',
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
				_self.names = Array.from(data.getElementsByTagName('item')).map((item, idx) => {
					return {
						"id": item.getAttribute('id'),
						"name": item.textContent
					};
				});
				callback();
			})
			.catch((error) => {
				if ('console' in window && console.info)
				{
					window.console.info('Error:', error);
				}
				callback();
			});
	}
};
