/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 * This bits acts as middle-man between the notify (above) and the ElkNotifications
 * providing the interface required by the latter.
 */

(function () {
	var ElkDesktop = (function (opt) {
		'use strict';
		opt = (opt) ? opt : {};

		var send = function (request) {
			if (request.desktop_notifications.new_from_last > 0) {
				if (!hasPermissions())
					return;

				Push.create(request.desktop_notifications.title, {
					body: request.desktop_notifications.message,
					icon: opt.icon,
					link: request.desktop_notifications.link,
					data: request.desktop_notifications.message
				});
			}
		};

		var hasPermissions = function () {
			if (Push.Permission.has())
				return true;

			if (Push.Permission.get() === "default") {
				return Push.Permission.request(onGranted, onDenied);
			}

			return false;
		};

		var onGranted = function () {
			return true;
		};

		var onDenied = function () {
			return false;
		};

		return {
			send: send
		};
	});

	this.ElkDesktop = ElkDesktop;
})();
