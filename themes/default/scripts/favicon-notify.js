/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 * These bits act as middle-man between the Favico and the ElkNotifications providing the interface
 * required by the latter.  It also handles the menu badge indicators.
 */

(function() {
	this.ElkFavicon = (function(opt) {

		opt = (opt) ? opt : {};

		// Holds the favico class
		let favico = new Favico(opt);

		// Set the favicon count indicator on startup
		const init = function(opt) {
			if (opt.number > 0)
			{
				// Set the favicon count indicator on startup
				favico.badge(opt.number);
			}

			if (opt.mentions > 0)
			{
				// Set the mentions menu badge indicator on startup
				document.querySelector('#button_mentions .pm_indicator').innerHTML = opt.mentions;
				document.querySelector('#button_mentions .pm_indicator').style.display = 'block';
			}

			if (opt.pm_unread > 0)
			{
				// Set the personal messages menu badge indicator on startup
				document.querySelector('#button_pm .pm_indicator').innerHTML = opt.pm_unread;
				document.querySelector('#button_pm .pm_indicator').style.display = 'block';
			}
		};

		// Update the indicator when new items are added or removed.
		const send = function(request) {
			// Determine counts for mentions and personal messages
			let mentionCount = 0,
				pmCount = 0;

			if (typeof request.mentions !== 'undefined')
			{
				mentionCount = parseInt(request.mentions, 10) || 0;
			}
			if (typeof request.pm_unread !== 'undefined')
			{
				pmCount = parseInt(request.pm_unread, 10) || 0;
			}

			const total = mentionCount + pmCount;

			if (total > 0)
			{
				// Update the favicon with the aggregate total
				favico.badge(total);

				// Update the mentions menu indicator if present
				let mentionEl = document.querySelector('#button_mentions .pm_indicator');
				if (mentionEl && typeof request.mentions !== 'undefined')
				{
					mentionEl.innerHTML = mentionCount;
					mentionEl.style.display = mentionCount > 0 ? 'block' : 'none';
				}

				// Update the personal messages menu indicator if present
				let pmEl = document.querySelector('#button_pm .pm_indicator');
				if (pmEl && typeof request.pm_unread !== 'undefined')
				{
					pmEl.innerHTML = pmCount;
					pmEl.style.display = pmCount > 0 ? 'block' : 'none';
				}
			}
			else
			{
				favico.reset();
				let mentionElement = document.querySelector('#button_mentions .pm_indicator');
				if (mentionElement)
				{
					mentionElement.style.display = 'none';
				}
				let pmElement = document.querySelector('#button_pm .pm_indicator');
				if (pmElement)
				{
					pmElement.style.display = 'none';
				}
			}
		};

		init(opt);
		return {
			send: send
		};
	});
})();
