/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 * This bits acts as middle-man between the Favico and the ElkNotifications providing the interface
 * required by the latter.
 */

(function() {
	const ElkFavicon = (function(opt) {

		opt = (opt) ? opt : {};

		// Holds the favico class
		let mentions= new Favico(opt);

		// Set the favicon count indicator on startup
		const init = function(opt) {
			if (opt.number > 0)
			{
				mentions.badge(opt.number);
				document.querySelector('#button_mentions .pm_indicator').innerHTML = opt.number;
				document.querySelector('#button_mentions .pm_indicator').style.display = 'block';
			}
		};

		// Update the indicator when new items are added or removed.
		const send = function(request) {
			if (request.mentions !== "0")
			{
				mentions.badge(request.mentions);
				let element = document.querySelector('#button_mentions .pm_indicator');
				if (element)
				{
					element.innerHTML = request.mentions;
				}
			}
			else
			{
				mentions.reset();
				document.querySelector('#button_mentions .pm_indicator').style.display = 'none';
			}
		};

		init(opt);
		return {
			send: send
		};
	});

	this.ElkFavicon = ElkFavicon;
})();
