/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0
 */

/**
 * Fills the board index like-stats block in the background.
 *
 * The server renders the last cached payload immediately.  This script is only
 * loaded when that payload is stale (or has never been generated), so the
 * like queries never hold up the board index request.
 */
(function() {
	'use strict';

	// These are emitted as top level let variables, not window properties.
	if (typeof elk_likestats_refresh === 'undefined')
	{
		return;
	}

	const labels = {
		topics: typeof elk_likestats_label_topics === 'undefined' ? '' : elk_likestats_label_topics,
		messages: typeof elk_likestats_label_messages === 'undefined' ? '' : elk_likestats_label_messages,
		boards: typeof elk_likestats_label_boards === 'undefined' ? '' : elk_likestats_label_boards
	};

	// Build a line of links, never trusting the server data as HTML.
	function renderList (container, label, items)
	{
		if (!Array.isArray(items) || items.length === 0)
		{
			return;
		}

		const line = document.createElement('p'),
			heading = document.createElement('span');

		line.className = 'inline';
		heading.className = 'event bbc_strong';
		heading.textContent = label + ':';
		line.appendChild(heading);
		line.appendChild(document.createTextNode(' '));

		items.forEach(function(item, index) {
			if (index > 0)
			{
				line.appendChild(document.createTextNode(', '));
			}

			var link = document.createElement('a');
			link.href = item.href;
			link.textContent = item.name || item.subject || '';
			line.appendChild(link);
			line.appendChild(document.createTextNode(' (' + item.like_count + ')'));
		});

		container.appendChild(line);
	}

	function render (container, data)
	{
		// Replace the stale markup rendered by the template.
		container.textContent = '';

		// Match the order used by the template: topics, messages, boards.
		renderList(container, labels.topics || '', data.topics);
		renderList(container, labels.messages || '', data.messages);
		renderList(container, labels.boards || '', data.boards);
	}

	function refresh ()
	{
		const container = document.getElementById('likestats_content');

		if (container === null)
		{
			return;
		}

		const url = elk_prepareScriptUrl(elk_scripturl) +
			'action=likes;sa=likestats;area=indexstats;api=json;' +
			elk_session_var + '=' + elk_session_id;

		fetch(url, {
			method: 'POST',
			cache: 'no-cache',
			credentials: 'same-origin',
			headers: {
				'X-Requested-With': 'XMLHttpRequest',
				'Accept': 'application/json',
				'Content-Type': 'application/x-www-form-urlencoded'
			}
		})
			.then(function(response) {
				if (!response.ok)
				{
					throw new Error('HTTP error ' + response.status);
				}

				return response.json();
			})
			.then(function(response) {
				if (response.result === true && response.data !== null && typeof response.data === 'object')
				{
					render(container, response.data);
				}
			})
			.catch(function() {
				// Keep the cached data (or the header only) if the refresh failed.
			})
			.then(function() {
				const loading = container.querySelector('.likestats_loading');

				if (loading !== null)
				{
					loading.parentNode.removeChild(loading);
				}
			});
	}

	function schedule ()
	{
		// Let the board index paint first; the data is decorative.
		if (typeof window.requestIdleCallback === 'function')
		{
			window.requestIdleCallback(refresh, {timeout: 2000});
		}
		else
		{
			window.setTimeout(refresh, 0);
		}
	}

	if (document.readyState === 'loading')
	{
		document.addEventListener('DOMContentLoaded', schedule, {once: true});
	}
	else
	{
		schedule();
	}
}());
