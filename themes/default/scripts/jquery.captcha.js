/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 beta 3
 */

/**
 * This file contains javascript associated with the captcha visual verification stuffs.
 */

(function($) {
	$.fn.Elk_Captcha = function(options) {
		var settings = {
			// By default the letter count is five.
			'letterCount' : 5,
			'uniqueID' : '',
			'imageURL' : '',
			'useLibrary' : false,
			'refreshevent': 'click',
			'playevent': 'click',
			'admin': false
		};

		$.extend(settings, options);

		return this.each(function() {
			$this = $(this);

			if ($this.data('type') == 'sound')
			{
				// Maybe a voice is here to spread light?
				$this.on(settings.playevent, function(e) {
					e.preventDefault();

					// Don't follow the link if the popup worked, which it would have done!
					popupFailed = reqWin(settings.imageURL + ";sound", 400, 300);
					if (!popupFailed)
					{
						if (is_ie && e.cancelBubble)
							e.cancelBubble = true;
						else if (e.stopPropagation)
						{
							e.stopPropagation();
							e.preventDefault();
						}
					}

					return popupFailed;
				});
			}
			else
			{
				$this.on(settings.refreshevent, function(e) {
					e.preventDefault();

					var uniqueID = settings.uniqueID ? '_' + settings.uniqueID : '',
						new_url = '',
						i = 0;

					// The admin area is a bit different unfortunately
					if (settings.admin)
					{
						settings.imageURL = $('#verification_image' + uniqueID).attr('src').replace(/.$/, '') + $this.val();
						new_url = String(settings.imageURL);
					}
					else
					{
						// Make sure we are using a new rand code.
						new_url = String(settings.imageURL);
						new_url = new_url.substr(0, new_url.indexOf("rand=") + 5);

						// Quick and dirty way of converting decimal to hex
						var hexstr = "0123456789abcdef";
						for (i = 0; i < 32; i++)
							new_url = new_url + hexstr.substr(Math.floor(Math.random() * 16), 1);
					}

					if (settings.useLibrary)
					{
						$('#verification_image' + uniqueID).attr('src', new_url);
					}
					else if (document.getElementById("verification_image" + uniqueID))
					{
						for (i = 1; i <= settings.letterCount; i++)
							if (document.getElementById("verification_image" + uniqueID + "_" + i))
								document.getElementById("verification_image" + uniqueID + "_" + i).src = new_url + ";letter=" + i;
					}
				});
			}
		});
	};
})( jQuery );