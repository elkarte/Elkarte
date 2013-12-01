/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Beta
 *
 * This file contains javascript associated with the captcha visual verification stuffs.
 */

/**
 * Captcha Class
 * Use to display the Captchabox
 *
 * @param {string} imageURL
 * @param {string} uniqueID
 * @param {boolean} useLibrary
 * @param {int} letterCount
 */
function elkCaptcha(imageURL, uniqueID, useLibrary, letterCount)
{
	// By default the letter count is five.
	this.letterCount = !letterCount ? 5 : letterCount;
	this.uniqueID = uniqueID ? '_' + uniqueID : '';
	this.imageURL = imageURL;
	this.useLibrary = useLibrary ? true : false;

	this.autoCreate();
}

// Automatically get the captcha event handlers in place and the like.
elkCaptcha.prototype.autoCreate = function()
{
	_self = this;

	// Is there anything to cycle images with - if so attach the refresh image function?
	var cycleHandle = document.getElementById('visual_verification' + this.uniqueID + '_refresh');
	if (cycleHandle)
	{
		createEventListener(cycleHandle);
		cycleHandle.addEventListener('click', function() {_self.refreshImages();}, false);
	}

	// Maybe a voice is here to spread light?
	var soundHandle = document.getElementById('visual_verification' + this.uniqueID + '_sound');
	if (soundHandle)
	{
		createEventListener(soundHandle);
		soundHandle.addEventListener('click', function(event) {_self.playSound(event);}, false);
	}
};

// Change the images.
elkCaptcha.prototype.refreshImages = function()
{
	// Make sure we are using a new rand code.
	var new_url = String(this.imageURL);
	new_url = new_url.substr(0, new_url.indexOf("rand=") + 5);

	// Quick and dirty way of converting decimal to hex
	var hexstr = "0123456789abcdef";
	for (var i = 0; i < 32; i++)
		new_url = new_url + hexstr.substr(Math.floor(Math.random() * 16), 1);

	if (this.useLibrary && document.getElementById("verification_image" + this.uniqueID))
	{
		document.getElementById("verification_image" + this.uniqueID).src = new_url;
	}
	else if (document.getElementById("verification_image" + this.uniqueID))
	{
		for (i = 1; i <= letterCount; i++)
			if (document.getElementById("verification_image" + this.uniqueID + "_" + i))
				document.getElementById("verification_image" + this.uniqueID + "_" + i).src = new_url + ";letter=" + i;
	}

	return false;
};

// Request a sound... play it Mr Soundman...
elkCaptcha.prototype.playSound = function(ev)
{
	if (!ev)
		ev = window.event;

	// Don't follow the link if the popup worked, which it would have done!
	popupFailed = reqWin(this.imageURL + ";sound", 400, 300);
	if (!popupFailed)
	{
		if (is_ie && ev.cancelBubble)
			ev.cancelBubble = true;
		else if (ev.stopPropagation)
		{
			ev.stopPropagation();
			ev.preventDefault();
		}
	}

	return popupFailed;
};