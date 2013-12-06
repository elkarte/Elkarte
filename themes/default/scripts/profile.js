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
 * This file contains javascript associated with the user profile
 */

/**
 * Function to detect the time offset and populate the offset box
 *
 * @param {string} currentTime
 */
var localTime = new Date();
function autoDetectTimeOffset(currentTime)
{
	var serverTime;

	if (typeof(currentTime) !== 'string')
		serverTime = currentTime;
	else
		serverTime = new Date(currentTime);

	// Something wrong?
	if (!localTime.getTime() || !serverTime.getTime())
		return 0;

	// Get the difference between the two, set it up so that the sign will tell us who is ahead of who.
	var diff = Math.round((localTime.getTime() - serverTime.getTime())/3600000);

	// Make sure we are limiting this to one day's difference.
	diff %= 24;

	return diff;
}

/**
 * Prevent Chrome from auto completing fields when viewing/editing other members profiles
 */
function disableAutoComplete()
{
	if (document.addEventListener)
		document.addEventListener("DOMContentLoaded", disableAutoCompleteNow, false);
}

/**
 * Once DOMContentLoaded is triggered, call the function
 */
function disableAutoCompleteNow()
{
	for (var i = 0, n = document.forms.length; i < n; i++)
	{
		var die = document.forms[i].elements;
		for (var j = 0, m = die.length; j < m; j++)
			// Only bother with text/password fields?
			if (die[j].type === "text" || die[j].type === "password")
				die[j].setAttribute("autocomplete", "off");
	}
}

/**
 * Calculates the number of available characters remaining when filling in the
 * signature box
 */
function calcCharLeft()
{
	var oldSignature = "",
		currentSignature = document.forms.creator.signature.value,
		currentChars = 0;

	if (!document.getElementById("signatureLeft"))
		return;

	if (oldSignature !== currentSignature)
	{
		oldSignature = currentSignature;

		currentChars = currentSignature.replace(/\r/, "").length;
		if (is_opera)
			currentChars = currentSignature.replace(/\r/g, "").length;

		if (currentChars > maxLength)
			document.getElementById("signatureLeft").className = "error";
		else
			document.getElementById("signatureLeft").className = "";

		if (currentChars > maxLength && !$("#profile_error").is(":visible"))
			ajax_getSignaturePreview(false);
		else if (currentChars <= maxLength && $("#profile_error").is(":visible"))
		{
			$("#profile_error").css({display:"none"});
			$("#profile_error").html('');
		}
	}

	document.getElementById("signatureLeft").innerHTML = maxLength - currentChars;
}

/**
 * Gets the signature preview via ajax and populates the preview box
 *
 * @param {boolean} showPreview
 */
function ajax_getSignaturePreview(showPreview)
{
	showPreview = (typeof showPreview === 'undefined') ? false : showPreview;
	$.ajax({
		type: "POST",
		url: elk_scripturl + "?action=xmlpreview;xml",
		data: {item: "sig_preview", signature: $("#signature").val(), user: $('input[name="u"]').attr("value")},
		context: document.body
	})
	.done(function(request) {
		var i = 0;
		if (showPreview)
		{
			var signatures = new Array("current", "preview");
			for (i = 0; i < signatures.length; i++)
			{
				$("#" + signatures[i] + "_signature").css({display:""});
				$("#" + signatures[i] + "_signature_display").css({display:""}).html($(request).find('[type="' + signatures[i] + '"]').text() + '<hr />');
			}
		}

		if ($(request).find("error").text() !== '')
		{
			if (!$("#profile_error").is(":visible"))
				$("#profile_error").css({display: "", position: "fixed", top: 0, left: 0, width: "100%"});

			var errors = $(request).find('[type="error"]'),
				errors_html = '<span>' + $(request).find('[type="errors_occurred"]').text() + '</span><ul>';

			for (i = 0; i < errors.length; i++)
				errors_html += '<li>' + $(errors).text() + '</li>';

			errors_html += '</ul>';
			$(document).find("#profile_error").html(errors_html);
		}
		else
		{
			$("#profile_error").css({display:"none"});
			$("#profile_error").html('');
		}
		return false;
	});

	return false;
}

/**
 * Allows previewing of server stored avatars stored.
 *
 * @param {type} selected
 */
function changeSel(selected)
{
	if (cat.selectedIndex === -1)
		return;

	if (cat.options[cat.selectedIndex].value.indexOf("/") > 0)
	{
		var i,
			count = 0;

		file.style.display = "inline";
		file.disabled = false;

		for (i = file.length; i >= 0; i = i - 1)
			file.options[i] = null;

		for (i = 0; i < files.length; i++)
			if (files[i].indexOf(cat.options[cat.selectedIndex].value) === 0)
			{
				var filename = files[i].substr(files[i].indexOf("/") + 1);
				var showFilename = filename.substr(0, filename.lastIndexOf("."));
				showFilename = showFilename.replace(/[_]/g, " ");

				file.options[count] = new Option(showFilename, files[i]);

				if (filename === selected)
				{
					if (file.options.defaultSelected)
						file.options[count].defaultSelected = true;
					else
						file.options[count].selected = true;
				}

				count++;
			}

		if (file.selectedIndex === -1 && file.options[0])
			file.options[0].selected = true;

		showAvatar();
	}
	else
	{
		file.style.display = "none";
		file.disabled = true;
		document.getElementById("avatar").src = avatardir + cat.options[cat.selectedIndex].value;
		document.getElementById("avatar").style.width = "";
		document.getElementById("avatar").style.height = "";
	}
}

/**
 * Updates the avatar img preview with the selected one
 */
function showAvatar()
{
	if (file.selectedIndex === -1)
		return;

	oAvatar = document.getElementById("avatar");

	oAvatar.src = avatardir + file.options[file.selectedIndex].value;
	oAvatar.alt = file.options[file.selectedIndex].text;
	oAvatar.alt += file.options[file.selectedIndex].text === size ? "!" : "";
	oAvatar.style.width = "";
	oAvatar.style.height = "";
}

/**
 * Allows for the previewing of an externally stored avatar
 *
 * @param {string} src
 * @param {string} sid
 */
function previewExternalAvatar(src, sid)
{
	sid = (typeof(sid) === 'undefined') ? "avatar" : sid;

	if (!document.getElementById(sid))
		return;

	var tempImage = new Image();

	tempImage.src = src;
	if (maxWidth !== 0 && tempImage.width > maxWidth)
	{
		document.getElementById(sid).style.height = parseInt((maxWidth * tempImage.height) / tempImage.width) + "px";
		document.getElementById(sid).style.width = maxWidth + "px";
	}
	else if (maxHeight !== 0 && tempImage.height > maxHeight)
	{
		document.getElementById(sid).style.width = parseInt((maxHeight * tempImage.width) / tempImage.height) + "px";
		document.getElementById(sid).style.height = maxHeight + "px";
	}
	document.getElementById(sid).src = src;
}

/**
 * Sets the warning bar based on user +/- button click
 * Also responds to mousedown/move/click events inside the warning bar to set the level
 * Determines the right color for the bar and sets it
 * Sets the warning level notification text
 *
 * @param {object} curEvent
 * @param {boolean} isMove
 * @param {int} changeAmount
 */
function setWarningBarPos(curEvent, isMove, changeAmount)
{
	// Are we passing the amount to change it by?
	if (changeAmount)
	{
		if (document.getElementById('warning_level').value === 'SAME')
			percent = currentLevel + changeAmount;
		else
			percent = parseInt(document.getElementById('warning_level').value) + changeAmount;
	}
	// If not then it's a mouse thing.
	else
	{
		if (!curEvent)
			curEvent = window.event;

		// If it's a movement check the button state first!
		if (isMove)
		{
			if (!curEvent.button || curEvent.button !== 1)
				return false;
		}

		// Get the position of the container.
		contain = document.getElementById('warning_progress');
		position = 0;
		while (contain !== null)
		{
			position += contain.offsetLeft;
			contain = contain.offsetParent;
		}

		// Where is the mouse?
		if (curEvent.pageX)
		{
			mouse = curEvent.pageX;
		}
		else
		{
			mouse = curEvent.clientX;
			mouse += document.documentElement.scrollLeft !== "undefined" ? document.documentElement.scrollLeft : document.body.scrollLeft;
		}

		// Is this within bounds?
		if (mouse < position || mouse > position + barWidth)
			return;

		percent = Math.round(((mouse - position) / barWidth) * 100);

		// Round percent to the nearest 5 - by kinda cheating!
		percent = Math.round(percent / 5) * 5;
	}

	// What are the limits?
	percent = Math.max(percent, minLimit);
	percent = Math.min(percent, maxLimit);

	// Set up the warning progress bar
	size = barWidth * (percent / 100);
	document.getElementById('warning_text').innerHTML = percent + "%";
	document.getElementById('warning_level').value = percent;
	document.getElementById('warning_progress').style.width = size + "px";

	// Get the right color.
	var key;
	for (key in colors)
	{
		if (percent >= key)
			color = colors[key];
	}

	document.getElementById('warning_progress').style.backgroundColor = color;
	document.getElementById('warning_progress').style.backgroundImage = "none";

	// Set the right text so its clear what the level will restrict
	for (key in effectTexts)
	{
		if (percent >= key)
			effectText = effectTexts[key];
	}

	document.getElementById('cur_level_div').innerHTML = effectText;
}

/**
 * Disable notification boxes as required.  This is in response to slecting the
 * notify user checkbox in the issue a warning screen
 */
function modifyWarnNotify()
{
	disable = !document.getElementById('warn_notify').checked;
	document.getElementById('warn_sub').disabled = disable;
	document.getElementById('warn_body').disabled = disable;
	document.getElementById('warn_temp').disabled = disable;
	document.getElementById('new_template_link').style.display = disable ? 'none' : '';
	document.getElementById('preview_button').style.display = disable ? 'none' : '';
}

/**
 * onclick function, triggerd in response to slecting + or - in the warning screen
 * Increases the warning level by a defined amount
 *
 * @param {int} amount
 */
function changeWarnLevel(amount)
{
	setWarningBarPos(false, false, amount);
}

/**
 * Fills the warning template box based on the one chosen by the user
 */
function populateNotifyTemplate()
{
	// no selection means no template
	index = document.getElementById('warn_temp').value;
	if (index === -1)
		return false;

	// Otherwise see what we can do...
	for (var key in templates)
	{
		// Found the template, load it and stop
		if (index === key)
		{
			document.getElementById('warn_body').value = templates[key];
			break;
		}
	}
}