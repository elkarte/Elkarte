/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 * This bits acts as middle-man between Push and the ElkNotifications
 * providing the interface required by the latter.
 */

(function() {
	this.ElkDesktop = (function(opt) {

		opt = (opt) ? opt : {};

		let canRun = true;

		const send = function(request) {
			if (canRun && request.desktop_notifications && parseInt(request.desktop_notifications.new_from_last, 10) !== 0)
			{
				if (hasPermissions())
				{
					Push.create(request.desktop_notifications.title, {
						body: request.desktop_notifications.message,
						icon: opt.icon,
						link: request.desktop_notifications.link,
						onClick: function() {
							window.focus();
							this.close();
						}
					});
				}
			}

			// Reset the flag and start the timer again
			canRun = false;
			setTimeout(function() {
				canRun = true;
			}, 30000);
		};

		const hasPermissions = function() {
			if (Push.Permission.has())
			{
				return true;
			}

			if (Push.Permission.get() === 'default')
			{
				return Push.Permission.request();
			}

			return false;
		};

		return {
			send: send
		};
	});
})();
