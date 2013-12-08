/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Beta
 *
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
	this.last_call = 0,
	this.last_query = '',
	this.names = [],
	this.cached_names = [],
	this.cached_queries = [],
	this.mentions = null,
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
		tpl: "<li data-value='${atwho-at}${name}' data-id='${id}'>${name}</li>",
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
				$.ajax({
					url: elk_scripturl + "?action=suggest;suggest_type=member;search=" + query.php_to8bit().php_urlencode() + ";" + elk_session_var + "=" + elk_session_id + ";xml;time=" + this.current_call,
					type: "get",
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
				});

				// Save this information so we can reuse it
				_self.last_call = current_call;
				_self.last_query = query;

				// Save it to the cache for this mention
				_self.cached_names[query] = _self.names;
				_self.cached_queries[_self.cached_queries.length] = query;

				return _self.names;
			},
			before_insert: function (value, $li) {
				_self.mentions.append($('<input type="hidden" name="uid[]" />').val($li.data('id')).attr('data-name', $li.data('value')));

				return value;
			}
		}
	});
};