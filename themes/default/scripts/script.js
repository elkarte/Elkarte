/*!
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1
 */

/**
 * This file contains javascript utility functions
 */

var elk_formSubmitted = false,
	lastKeepAliveCheck = new Date().getTime(),
	ajax_indicator_ele = null;

// Some very basic browser detection
var ua = navigator.userAgent.toLowerCase(),
	is_opera = !!window.opera, // Opera 8.0-12, past that it behaves like chrome
	is_ff = typeof InstallTrigger !== 'undefined' || ((ua.indexOf('iceweasel') !== -1 || ua.indexOf('icecat') !== -1 || ua.indexOf('shiretoko') !== -1 || ua.indexOf('minefield') !== -1) && !is_opera),
	is_safari = Object.prototype.toString.call(window.HTMLElement).indexOf('Constructor') > 0, // Safari 3+
	is_chrome = !!window.chrome, // Chrome 1+, Opera 15+
	is_ie = !!document.documentMode, // IE8+
	is_webkit = ua.indexOf('applewebkit') !== -1;
	is_osx = navigator.platform.toUpperCase().indexOf('MAC') >= 0,
	is_mobile = navigator.userAgent.indexOf('Mobi') !== -1, // Common mobile including Mozilla, Safari, IE, Opera, Chrome
	is_touch = 'ontouchstart' in window || window.DocumentTouch && document instanceof DocumentTouch;

// Versions of ie < 9 do not have this built in
if (!('getElementsByClassName' in document))
{
	document.getElementsByClassName = function(className)
	{
		return $('.' + className);
	};
}

/**
 * Load an XML document using XMLHttpRequest.
 *
 * @callback xmlCallback
 * @param {string} sUrl
 * @param {function} funcCallback
 */
function getXMLDocument(sUrl, funcCallback)
{
	var oMyDoc = new XMLHttpRequest(),
		bAsync = typeof(funcCallback) !== 'undefined',
		oCaller = this;

	if (bAsync)
	{
		oMyDoc.onreadystatechange = function () {
			if (oMyDoc.readyState !== 4)
				return;

			if (oMyDoc.responseXML !== null && oMyDoc.status === 200)
				funcCallback.call(oCaller, oMyDoc.responseXML);
			else
				funcCallback.call(oCaller, false);
		};
	}

	oMyDoc.open('GET', sUrl, bAsync);
	oMyDoc.send(null);

	return oMyDoc;
}

/**
 * Send a post form to the server using XMLHttpRequest.
 *
 * @param {string} sUrl
 * @param {string} sContent
 * @param {string} funcCallback
 */
function sendXMLDocument(sUrl, sContent, funcCallback)
{
	var oSendDoc = new window.XMLHttpRequest(),
		oCaller = this;

	if (typeof(funcCallback) !== 'undefined')
	{
		oSendDoc.onreadystatechange = function () {
			if (oSendDoc.readyState !== 4)
				return;

			if (oSendDoc.responseXML !== null && oSendDoc.status === 200)
				funcCallback.call(oCaller, oSendDoc.responseXML);
			else
				funcCallback.call(oCaller, false);
		};
	}

	oSendDoc.open('POST', sUrl, true);
	if ('setRequestHeader' in oSendDoc)
		oSendDoc.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
	oSendDoc.send(sContent);

	return true;
}

/**
 * All of our specialized string handling functions are defined here
 * php_strtr, php_strtolower, php_urlencode, php_htmlspecialchars
 * php_unhtmlspecialchars, php_addslashes, removeEntities, easyReplace
 */

/**
 * Character-level replacement function.
 * @param {string} sFrom
 * @param {string} sTo
 */
String.prototype.php_strtr = function (sFrom, sTo)
{
	return this.replace(new RegExp('[' + sFrom + ']', 'g'), function (sMatch) {
		return sTo.charAt(sFrom.indexOf(sMatch));
	});
};

/**
 * Simulate PHP's strtolower (in SOME cases PHP uses ISO-8859-1 case folding).
 * @returns {String.prototype@call;php_strtr}
 */
String.prototype.php_strtolower = function ()
{
	return typeof(elk_iso_case_folding) === 'boolean' && elk_iso_case_folding === true ? this.php_strtr(
		'ABCDEFGHIJKLMNOPQRSTUVWXYZ\x8a\x8c\x8e\x9f\xc0\xc1\xc2\xc3\xc4\xc5\xc6\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf\xd0\xd1\xd2\xd3\xd4\xd5\xd6\xd7\xd8\xd9\xda\xdb\xdc\xdd\xde',
		'abcdefghijklmnopqrstuvwxyz\x9a\x9c\x9e\xff\xe0\xe1\xe2\xe3\xe4\xe5\xe6\xe7\xe8\xe9\xea\xeb\xec\xed\xee\xef\xf0\xf1\xf2\xf3\xf4\xf5\xf6\xf7\xf8\xf9\xfa\xfb\xfc\xfd\xfe'
	) : this.php_strtr('ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
};

/**
 * Simulate php's urlencode function
 */
String.prototype.php_urlencode = function()
{
	return encodeURIComponent(this).replace(/!/g, '%21').replace(/'/g, '%27').replace(/\(/g, '%28').replace(/\)/g, '%29').replace(/\*/g, '%2A').replace(/%20/g, '+');
};

/**
 * Simulate php htmlspecialchars function
 */
String.prototype.php_htmlspecialchars = function()
{
	return this.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
};

/**
 * Simulate php unhtmlspecialchars function
 */
String.prototype.php_unhtmlspecialchars = function()
{
	return this.replace(/&quot;/g, '"').replace(/&gt;/g, '>').replace(/&lt;/g, '<').replace(/&amp;/g, '&');
};

/**
 * Simulate php addslashes function
 */
String.prototype.php_addslashes = function()
{
	return this.replace(/\\/g, '\\\\').replace(/'/g, '\\\'');
};

/**
 * Callback function for the removeEntities function
 */
String.prototype._replaceEntities = function(sInput, sDummy, sNum)
{
	return String.fromCharCode(parseInt(sNum));
};

/**
 * Removes entities from a string and replaces them with a character code
 */
String.prototype.removeEntities = function()
{
	return this.replace(/&(amp;)?#(\d+);/g, this._replaceEntities);
};

/**
 * String replace function, searches a string for x and replaces it with y
 *
 * @param {object} oReplacements object of search:replace terms
 */
String.prototype.easyReplace = function (oReplacements)
{
	var sResult = this;

	for (var sSearch in oReplacements) {
		sSearch = sSearch.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
		sResult = sResult.replace(new RegExp('%' + sSearch + '%', 'g'), oReplacements[sSearch]);
	}

	return sResult;
};

/**
 * Simulate php str_repeat function
 *
 * @param {string} sString
 * @param {int} iTime
 */
function php_str_repeat(sString, iTime)
{
	if (iTime < 1)
		return '';
	else
		return sString + php_str_repeat(sString, iTime - 1);
}

/**
 * Opens a new window
 *
 * @param {string} desktopURL
 * @param {int} alternateWidth
 * @param {int} alternateHeight
 * @param {boolean} noScrollbars
 */
function reqWin(desktopURL, alternateWidth, alternateHeight, noScrollbars)
{
	if ((alternateWidth && self.screen.availWidth * 0.8 < alternateWidth) || (alternateHeight && self.screen.availHeight * 0.8 < alternateHeight))
	{
		noScrollbars = false;
		alternateWidth = Math.min(alternateWidth, self.screen.availWidth * 0.8);
		alternateHeight = Math.min(alternateHeight, self.screen.availHeight * 0.8);
	}
	else
		noScrollbars = typeof(noScrollbars) === 'boolean' && noScrollbars === true;

	window.open(desktopURL, 'requested_popup', 'toolbar=no,location=no,status=no,menubar=no,scrollbars=' + (noScrollbars ? 'no' : 'yes') + ',width=' + (alternateWidth ? alternateWidth : 480) + ',height=' + (alternateHeight ? alternateHeight : 220) + ',resizable=no');

	// Return false so the click won't follow the link ;).
	return false;
}

/**
 * Open a overlay div on the screen
 *
 * @param {string} desktopURL
 * @param {string} [sHeader]
 * @param {string} [sIcon]
 */
function reqOverlayDiv(desktopURL, sHeader, sIcon)
{
	// Set up our div details
	var sAjax_indicator = '<div class="centertext"><i class="icon icon-spin icon-big i-spinner"></i></div>';

	sIcon = typeof(sIcon) === 'string' ? sIcon : 'i-help';
	sHeader = typeof(sHeader) === 'string' ? sHeader : help_popup_heading_text;

	// Create the div that we are going to load
	var oContainer = new smc_Popup({heading: sHeader, content: sAjax_indicator, icon: sIcon}),
		oPopup_body = $('#' + oContainer.popup_id).find('.popup_content');

	// Load the help page content (we just want the text to show)
	$.ajax({
		url: desktopURL,
		type: "GET",
		dataType: "html"
	})
	.done(function (data, textStatus, xhr) {
		var help_content = $('<div id="temp_help">').html(data).find('a[href$="self.close();"]').hide().prev('br').hide().parent().html();

		oPopup_body.html(help_content);
	})
	.fail(function (xhr, textStatus, errorThrown) {
		oPopup_body.html(textStatus);
	});

	return false;
}

/**
 * smc_Popup class.
 *
 * @param {object} oOptions
 */
function smc_Popup(oOptions)
{
	this.opt = oOptions;
	this.popup_id = this.opt.custom_id ? this.opt.custom_id : 'elk_popup';
	this.show();
}

// Show the popup div & prepare the close events
smc_Popup.prototype.show = function ()
{
	var popup_class = 'popup_window ' + (this.opt.custom_class ? this.opt.custom_class : 'content'),
		icon = this.opt.icon ? '<i class="icon ' + this.opt.icon + ' icon-top"></i> ' : '';

	// Todo: opt.icon should be a string referencing the desired icon. Will require changing all callers.

	// Create the div that will be shown - max-height added here - essential anyway,
	// so better here than in the CSS.
	// Mind you, I still haven't figured out why it should be essential. Cargo cult coding FTW. :P
	// Create the div that will be shown
	$('body').append('<div id="' + this.popup_id + '" class="popup_container"><div class="' + popup_class + '" style="max-height: none;"><h3 class="popup_heading"><a href="javascript:void(0);" class="hide_popup icon i-close" title="Close"></a>' + icon + this.opt.heading + '</h3><div class="popup_content">' + this.opt.content + '</div></div></div>');

	// Show it
	this.popup_body = $('#' + this.popup_id).children('.popup_window');
	this.popup_body.parent().fadeIn(300);

	// Let the css know its now available
	this.popup_body.addClass("in");

	// Trigger hide on escape or mouse click
	var popup_instance = this;
	$(document).mouseup(function (e) {
		if ($('#' + popup_instance.popup_id).has(e.target).length === 0)
			popup_instance.hide();
	})
	.keyup(function(e){
		if (e.keyCode === 27)
			popup_instance.hide();
	});

	$('#' + this.popup_id).find('.hide_popup').on('click', function (){ return popup_instance.hide(); });

	return false;
};

// Hide the popup
smc_Popup.prototype.hide = function ()
{
	$('#' + this.popup_id).fadeOut(300, function(){ $(this).remove(); });

	return false;
};

/**
 * Replaces the currently selected text with the passed text.
 * Used by topic.js when inserting a quote into the plain text quick reply (not the editor QR)
 *
 * @param {string} text
 * @param {object} oTextHandle
 */
function replaceText(text, oTextHandle)
{
	// Standards compliant text range replace.
	if ('selectionStart' in oTextHandle)
	{
		var begin = oTextHandle.value.substr(0, oTextHandle.selectionStart),
			end = oTextHandle.value.substr(oTextHandle.selectionEnd),
			scrollPos = oTextHandle.scrollTop,
			goForward = 0;

		oTextHandle.value = begin + text + end;
		if (oTextHandle.setSelectionRange)
		{
			oTextHandle.focus();

			if (is_opera && text.match(/\n/g) !== null)
				goForward = text.match(/\n/g).length;

			oTextHandle.setSelectionRange(begin.length + text.length + goForward, begin.length + text.length + goForward);
		}
		oTextHandle.scrollTop = scrollPos;
	}
	// Just put it on the end.
	else
	{
		oTextHandle.value += text;
		oTextHandle.focus(oTextHandle.value.length - 1);
	}
}

/**
 * Checks if the passed input's value is nothing.
 *
 * @param {string|object} theField
 */
function isEmptyText(theField)
{
	var theValue;

	// Copy the value so changes can be made..
	if (typeof(theField) === 'string')
		theValue = theField;
	else
		theValue = theField.value;

	// Strip whitespace off the left side.
	while (theValue.length > 0 && (theValue.charAt(0) === ' ' || theValue.charAt(0) === '\t'))
		theValue = theValue.substring(1, theValue.length);

	// Strip whitespace off the right side.
	while (theValue.length > 0 && (theValue.charAt(theValue.length - 1) === ' ' || theValue.charAt(theValue.length - 1) === '\t'))
		theValue = theValue.substring(0, theValue.length - 1);

	return theValue === '';
}

// Only allow form submission ONCE.
function submitonce(theform)
{
	elk_formSubmitted = true;
}

function submitThisOnce(oControl, bReadOnly)
{
	// oControl might also be a form.
	var oForm = 'form' in oControl ? oControl.form : oControl,
		aTextareas = oForm.getElementsByTagName('textarea');

	bReadOnly = typeof bReadOnly == 'undefined' ? true : bReadOnly;
	for (var i = 0, n = aTextareas.length; i < n; i++)
		aTextareas[i].readOnly = bReadOnly;

	// If in a second the form is not gone, there may be a problem somewhere
	// (e.g. HTML5 required attribute), so release the textareas
	window.setTimeout(function() {submitThisOnce(oControl, false);}, 1000);
	return !elk_formSubmitted;
}

function getInnerHTML(oElement)
{
	if (oElement)
		return oElement.innerHTML;
}

/**
 * Set the "outer" HTML of an element.
 *
 * @param {HTMLElement} oElement
 * @param {string} sToValue
 */
function setOuterHTML(oElement, sToValue)
{
	if ('outerHTML' in oElement)
		oElement.outerHTML = sToValue;
	else
	{
		var range = document.createRange();
		range.setStartBefore(oElement);
		oElement.parentNode.replaceChild(range.createContextualFragment(sToValue), oElement);
	}
}

/**
 * Checks for variable in theArray, returns true or false
 *
 * @param {string} variable
 * @param {string[]} theArray
 */
function in_array(variable, theArray)
{
	for (var i in theArray)
		if (theArray[i] == variable)
			return true;

	return false;
}

/**
 * Checks for variable in theArray and returns the array key
 *
 * @param {string} variable
 * @param {Array.} theArray
 */
function array_search(variable, theArray)
{
	for (var i in theArray)
		if (theArray[i] == variable)
			return i;

	return null;
}

/**
 * Find a specific radio button in its group and select it.
 *
 * @param {HTMLInputElement} oRadioGroup
 * @param {type} sName
 */
function selectRadioByName(oRadioGroup, sName)
{
	if (!('length' in oRadioGroup))
		return oRadioGroup.checked = true;

	for (var i = 0, n = oRadioGroup.length; i < n; i++)
		if (oRadioGroup[i].value === sName)
			return oRadioGroup[i].checked = true;

	return false;
}

/**
 * Selects all the form objects with a single click
 *
 * @param {object} oInvertCheckbox
 * @param {object} oForm
 * @param {string} sMask
 * @param {string} sValue
 */
function selectAllRadio(oInvertCheckbox, oForm, sMask, sValue)
{
	for (var i = 0; i < oForm.length; i++)
		if (oForm[i].name !== undefined && oForm[i].name.substr(0, sMask.length) == sMask && oForm[i].value == sValue)
			oForm[i].checked = true;
}

/**
 * Invert all check boxes at once by clicking a single checkbox.
 *
 * @param {object} oInvertCheckbox
 * @param {HTMLFormElement} oForm
 * @param {string} [sMask]
 * @param {boolean} [bIgnoreDisabled]
 */
function invertAll(oInvertCheckbox, oForm, sMask, bIgnoreDisabled)
{
	for (var i = 0; i < oForm.length; i++)
	{
		if (!('name' in oForm[i]) || (typeof(sMask) === 'string' && oForm[i].name.substr(0, sMask.length) !== sMask && oForm[i].id.substr(0, sMask.length) !== sMask))
			continue;

		if (!oForm[i].disabled || (typeof(bIgnoreDisabled) === 'boolean' && bIgnoreDisabled))
			oForm[i].checked = oInvertCheckbox.checked;
	}
}

/**
 * Keep the session alive - always!
 */
function elk_sessionKeepAlive()
{
	var curTime = new Date().getTime();

	// Prevent a Firefox bug from hammering the server.
	if (elk_scripturl && curTime - lastKeepAliveCheck > 900000)
	{
		var tempImage = new Image();
		tempImage.src = elk_prepareScriptUrl(elk_scripturl) + 'action=keepalive;time=' + curTime;
		lastKeepAliveCheck = curTime;
	}

	window.setTimeout(function() {elk_sessionKeepAlive();}, 1200000);
}
window.setTimeout(function() {elk_sessionKeepAlive();}, 1200000);

/**
 * Set a theme option through javascript. / ajax
 *
 * @param {string} option name being set
 * @param {string} value of the option
 * @param {string|null} theme its being set or null for all
 * @param {string|null} additional_vars to use in the url request that will be sent
 */
function elk_setThemeOption(option, value, theme, additional_vars)
{
	if (additional_vars === null || typeof(additional_vars) === 'undefined')
		additional_vars = '';

	var tempImage = new Image();
	tempImage.src = elk_prepareScriptUrl(elk_scripturl) + 'action=jsoption;var=' + option + ';val=' + value + ';' + elk_session_var + '=' + elk_session_id + additional_vars + (theme === null ? '' : '&th=' + theme) + ';time=' + (new Date().getTime());
}

/**
 * Password hashing for user
 *
 * @param {type} doForm
 * @param {type} cur_session_id
 * @param {type} token
 */
function hashLoginPassword(doForm, cur_session_id, token)
{
	// Don't have our hash lib available?
	if (typeof(hex_sha256) === 'undefined')
		return;

	// Are they using an email address?
	if (doForm.user.value.indexOf('@') !== -1)
		return;

	doForm.passwrd.autocomplete = 'off';

	// Fill in the hidden fields with our sha hash
	doForm.hash_passwrd.value = hex_sha256(doForm.user.value.php_strtolower() + doForm.passwrd.value);

	// If the form also contains the old hash input fill it to smooth transitions
	if ('old_hash_passwrd' in doForm && typeof(hex_sha1) !== 'undefined')
		doForm.old_hash_passwrd.value = hex_sha1(hex_sha1(doForm.user.value.php_strtolower() + doForm.passwrd.value) + cur_session_id + (typeof token === 'undefined' ? '' : token));

	doForm.passwrd.value = doForm.passwrd.value.replace(/./g, '*');
}

/**
 * Password hashing for admin login
 *
 * @param {type} doForm
 * @param {type} username
 * @param {type} cur_session_id
 * @param {type} token
 */
function hashAdminPassword(doForm, username, cur_session_id, token)
{
	// Missing sha256.js?
	if (typeof(hex_sha256) === 'undefined')
		return;

	doForm.admin_hash_pass.value = hex_sha256(username.php_strtolower() + doForm.admin_pass.value);
	doForm.admin_pass.value = doForm.admin_pass.value.replace(/./g, '*');
}

/**
 * Hashing for the moderation login
 *
 * @param {type} doForm
 * @param {type} username
 * @param {type} cur_session_id
 * @param {type} token
 */
function hashModeratePassword(doForm, username, cur_session_id, token)
{
	// Missing sha256.js?
	if (typeof(hex_sha256) === 'undefined')
		return;

	doForm.moderate_hash_pass.value = hex_sha256(username.php_strtolower() + doForm.moderate_pass.value);
	doForm.moderate_pass.value = doForm.moderate_pass.value.replace(/./g, '*');
}

/**
 * Used by elk_Toggle to add an image to the swap/toggle array
 *
 * @param {string} sSrc
 */
function smc_preCacheImage(sSrc)
{
	if (!('smc_aCachedImages' in window))
		window.smc_aCachedImages = [];

	if (!in_array(sSrc, window.smc_aCachedImages))
	{
		var oImage = new Image();
		oImage.src = sSrc;
	}
}

/**
 * smc_Cookie class.
 *
 * @param {object} oOptions
 */
function smc_Cookie(oOptions)
{
	this.opt = oOptions;
	this.oCookies = {};
	this.init();
}

smc_Cookie.prototype.init = function()
{
	if ('cookie' in document && document.cookie !== '')
	{
		var aCookieList = document.cookie.split(';');
		for (var i = 0, n = aCookieList.length; i < n; i++)
		{
			var aNameValuePair = aCookieList[i].split('=');
			this.oCookies[aNameValuePair[0].replace(/^\s+|\s+$/g, '')] = decodeURIComponent(aNameValuePair[1]);
		}
	}
};

smc_Cookie.prototype.get = function(sKey)
{
	return sKey in this.oCookies ? this.oCookies[sKey] : null;
};

smc_Cookie.prototype.set = function(sKey, sValue)
{
	document.cookie = sKey + '=' + encodeURIComponent(sValue);
};

/**
 * elk_Toggle class.
 *
 * Collapses a section of the page
 * Swaps the collapsed section class or image to indicate the state
 * Updates links to indicate state and allow reversal of the action
 * Saves state in a cookie and/or in a theme setting option so the last state
 * is remembered for the user.
 *
 * @param {object} oOptions
 * @returns {elk_Toggle}
 */
function elk_Toggle(oOptions)
{
	this.opt = oOptions;
	this.bCollapsed = false;
	this.oCookie = null;
	this.init();
}

// Initialize the toggle class
elk_Toggle.prototype.init = function()
{
	var i = 0,
		n = 0;

	// The master switch can disable this toggle fully.
	if ('bToggleEnabled' in this.opt && !this.opt.bToggleEnabled)
		return;

	// If cookies are enabled and they were set, override the initial state.
	if ('oCookieOptions' in this.opt && this.opt.oCookieOptions.bUseCookie)
	{
		// Initialize the cookie handler.
		this.oCookie = new smc_Cookie({});

		// Check if the cookie is set.
		var cookieValue = this.oCookie.get(this.opt.oCookieOptions.sCookieName);
		if (cookieValue !== null)
			this.opt.bCurrentlyCollapsed = cookieValue === '1';
	}

	// If the init state is set to be collapsed, collapse it.
	if (this.opt.bCurrentlyCollapsed)
		this.changeState(true, true);

	// Initialize the images to be clickable.
	if ('aSwapImages' in this.opt)
	{
		for (i = 0, n = this.opt.aSwapImages.length; i < n; i++)
		{
			var oImage = document.getElementById(this.opt.aSwapImages[i].sId);
			if (typeof(oImage) === 'object' && oImage !== null)
			{
				// Display the image in case it was hidden.
				if (getComputedStyle(oImage).getPropertyValue("display") === 'none')
					oImage.style.display = 'inline';

				oImage.instanceRef = this;
				oImage.onclick = function () {this.instanceRef.toggle();this.blur();};
				oImage.style.cursor = 'pointer';

				// Pre-load the collapsed image.
				smc_preCacheImage(this.opt.aSwapImages[i].srcCollapsed);
			}
		}
	}
	// No images to swap, perhaps they want to swap the class?
	else if ('aSwapClasses' in this.opt)
	{
		for (i = 0, n = this.opt.aSwapClasses.length; i < n; i++)
		{
			var oContainer = document.getElementById(this.opt.aSwapClasses[i].sId);
			if (typeof(oContainer) === 'object' && oContainer !== null)
			{
				// Display the image in case it was hidden.
				if (getComputedStyle(oContainer).getPropertyValue("display") === 'none')
					oContainer.style.display = 'block';

				oContainer.instanceRef = this;

				oContainer.onclick = function () {
					this.instanceRef.toggle();
					this.blur();
				};
				oContainer.style.cursor = 'pointer';
			}
		}
	}

	// Initialize links.
	if ('aSwapLinks' in this.opt)
	{
		for (i = 0, n = this.opt.aSwapLinks.length; i < n; i++)
		{
			var oLink = document.getElementById(this.opt.aSwapLinks[i].sId);
			if (typeof(oLink) === 'object' && oLink !== null)
			{
				// Display the link in case it was hidden.
				if (getComputedStyle(oLink).getPropertyValue("display") === 'none')
					oLink.style.display = 'inline-block';

				oLink.instanceRef = this;
				oLink.onclick = function () {
					this.instanceRef.toggle();
					this.blur();
					return false;
				};
			}
		}
	}
};

/**
 * Collapse or expand the section.
 *
 * @param {boolean} bCollapse
 * @param {boolean} [bInit]
 */
elk_Toggle.prototype.changeState = function(bCollapse, bInit)
{
	var i = 0,
		n = 0,
		oContainer;

	// Default bInit to false.
	bInit = typeof(bInit) !== 'undefined';

	// Handle custom function hook before collapse.
	if (!bInit && bCollapse && 'funcOnBeforeCollapse' in this.opt)
	{
		this.tmpMethod = this.opt.funcOnBeforeCollapse;
		this.tmpMethod();
		delete this.tmpMethod;
	}
	// Handle custom function hook before expand.
	else if (!bInit && !bCollapse && 'funcOnBeforeExpand' in this.opt)
	{
		this.tmpMethod = this.opt.funcOnBeforeExpand;
		this.tmpMethod();
		delete this.tmpMethod;
	}

	// Loop through all the items that need to be toggled.
	if ('aSwapImages' in this.opt)
	{
		// Swapping images on a click
		for (i = 0, n = this.opt.aSwapImages.length; i < n; i++)
		{
			var oImage = document.getElementById(this.opt.aSwapImages[i].sId);
			if (typeof(oImage) === 'object' && oImage !== null)
			{
				// Only (re)load the image if it's changed.
				var sTargetSource = bCollapse ? this.opt.aSwapImages[i].srcCollapsed : this.opt.aSwapImages[i].srcExpanded;
				if (oImage.src != sTargetSource)
					oImage.src = sTargetSource;

				oImage.alt = oImage.title = bCollapse ? this.opt.aSwapImages[i].altCollapsed : this.opt.aSwapImages[i].altExpanded;
			}
		}
	}
	else if ('aSwapClasses' in this.opt)
	{
		// Or swapping the classes
		for (i = 0, n = this.opt.aSwapClasses.length; i < n; i++)
		{
			oContainer = document.getElementById(this.opt.aSwapClasses[i].sId);
			if (typeof(oContainer) === 'object' && oContainer !== null)
			{
				// Only swap the class if the state changed
				var sTargetClass = bCollapse ? this.opt.aSwapClasses[i].classCollapsed : this.opt.aSwapClasses[i].classExpanded;
				if (oContainer.className !== sTargetClass)
					oContainer.className = sTargetClass;

				// And show the new title
				oContainer.title = oContainer.title = bCollapse ? this.opt.aSwapClasses[i].titleCollapsed : this.opt.aSwapClasses[i].titleExpanded;
			}
		}
	}

	// Loop through all the links that need to be toggled.
	if ('aSwapLinks' in this.opt)
	{
		for (i = 0, n = this.opt.aSwapLinks.length; i < n; i++)
		{
			var oLink = document.getElementById(this.opt.aSwapLinks[i].sId);
			if (typeof(oLink) === 'object' && oLink !== null)
				oLink.innerHTML = bCollapse ? this.opt.aSwapLinks[i].msgCollapsed : this.opt.aSwapLinks[i].msgExpanded;
		}
	}

	// Now go through all the sections to be collapsed.
	for (i = 0, n = this.opt.aSwappableContainers.length; i < n; i++)
	{
		if (this.opt.aSwappableContainers[i] === null)
			continue;

		oContainer = document.getElementById(this.opt.aSwappableContainers[i]);
		if (typeof(oContainer) === 'object' && oContainer !== null)
		{
			if (bCollapse)
				$(oContainer).slideUp();
			else
				$(oContainer).slideDown();
		}
	}

	// Update the new state.
	this.bCollapsed = bCollapse;

	// Update the cookie, if desired.
	if ('oCookieOptions' in this.opt && this.opt.oCookieOptions.bUseCookie)
		this.oCookie.set(this.opt.oCookieOptions.sCookieName, this.bCollapsed ? '1' : '0');

	if (!bInit && 'oThemeOptions' in this.opt && this.opt.oThemeOptions.bUseThemeSettings)
		elk_setThemeOption(this.opt.oThemeOptions.sOptionName, this.bCollapsed ? '1' : '0', 'sThemeId' in this.opt.oThemeOptions ? this.opt.oThemeOptions.sThemeId : null, 'sAdditionalVars' in this.opt.oThemeOptions ? this.opt.oThemeOptions.sAdditionalVars : null);
};

elk_Toggle.prototype.toggle = function()
{
	// Change the state by reversing the current state.
	this.changeState(!this.bCollapsed);
};

/**
 * Creates and shows or hides the sites ajax in progress indicator
 *
 * @param {boolean} turn_on
 * @returns {undefined}
 */
function ajax_indicator(turn_on)
{
	if (ajax_indicator_ele === null)
	{
		ajax_indicator_ele = document.getElementById('ajax_in_progress');

		if (ajax_indicator_ele === null && typeof(ajax_notification_text) !== null)
		{
			create_ajax_indicator_ele();
		}
	}

	if (ajax_indicator_ele !== null)
	{
		ajax_indicator_ele.style.display = turn_on ? 'block' : 'none';
	}
}

/**
 * Creates the ajax notification div and adds it to the current screen
 */
function create_ajax_indicator_ele()
{
	// Create the div for the indicator.
	ajax_indicator_ele = document.createElement('div');

	// Set the id so it'll load the style properly.
	ajax_indicator_ele.id = 'ajax_in_progress';

	// Add the image in and link to turn it off.
	var cancel_link = document.createElement('a');
	cancel_link.href = 'javascript:ajax_indicator(false)';

	var cancel_img = document.createElement('img');
	cancel_img.src = elk_images_url + '/icons/quick_remove.png';

	if (typeof(ajax_notification_cancel_text) !== 'undefined')
	{
		cancel_img.alt = ajax_notification_cancel_text;
		cancel_img.title = ajax_notification_cancel_text;
	}

	// Add the cancel link and image to the indicator.
	cancel_link.appendChild(cancel_img);
	ajax_indicator_ele.appendChild(cancel_link);

	// Set the text.  (Note:  You MUST append here and not overwrite.)
	ajax_indicator_ele.innerHTML += ajax_notification_text;

	// Finally attach the element to the body.
	document.body.appendChild(ajax_indicator_ele);
}

/**
 * Creates and event listener object for a given object
 * Object events can then be added with addEventListener
 *
 * @param {HTMLElement} oTarget
 */
function createEventListener(oTarget)
{
	if (!('addEventListener' in oTarget))
	{
		if (oTarget.attachEvent)
		{
			oTarget.addEventListener = function (sEvent, funcHandler, bCapture) {
				oTarget.attachEvent('on' + sEvent, funcHandler);
			};

			oTarget.removeEventListener = function (sEvent, funcHandler, bCapture) {
				oTarget.detachEvent('on' + sEvent, funcHandler);
			};
		}
		else
		{
			oTarget.addEventListener = function (sEvent, funcHandler, bCapture) {
				oTarget['on' + sEvent] = funcHandler;
			};

			oTarget.removeEventListener = function (sEvent, funcHandler, bCapture) {
				oTarget['on' + sEvent] = null;
			};
		}
	}
}


/**
 * This function will retrieve the contents needed for the jump to boxes.
 */
function grabJumpToContent() {
	getXMLDocument(elk_prepareScriptUrl(elk_scripturl) + 'action=xmlhttp;sa=jumpto;xml', onJumpReceived);

	return false;
}

/**
 * Callback function for loading the jumpto box
 *
 * @param {object} oXMLDoc
 */
function onJumpReceived(oXMLDoc) {
	var aBoardsAndCategories = [],
		i,
		n,
		items = oXMLDoc.getElementsByTagName('elk')[0].getElementsByTagName('item');

	for (i = 0, n = items.length; i < n; i++)
	{
		aBoardsAndCategories[aBoardsAndCategories.length] = {
			id: parseInt(items[i].getAttribute('id')),
			isCategory: items[i].getAttribute('type') === 'category',
			name: items[i].firstChild.nodeValue.removeEntities(),
			is_current: false,
			childLevel: parseInt(items[i].getAttribute('childlevel'))
		};
	}

	for (i = 0, n = aJumpTo.length; i < n; i++)
		aJumpTo[i].fillSelect(aBoardsAndCategories);
}

/**
 * JumpTo class.
 *
 * Passed object of options can contain:
 * sContainerId: container id to place the list in
 * sClassName: class name to assign items added to the dropdown
 * sJumpToTemplate: html template to wrap the %dropdown_list%
 * iCurBoardId: id of the board current active
 * iCurBoardChildLevel: child level of the currently active board
 * sCurBoardName: name of the currently active board
 * sBoardChildLevelIndicator: text/characters used to indent
 * sBoardPrefix: arrow head
 * sCatPrefix: Prefix to use in from of the categories
 * bNoRedirect: boolean for redirect
 * bDisabled: boolean for disabled
 * sCustomName: custom name to prefix for the select name=""
 * sGoButtonLabel: name for the goto button
 *
 * @param {type} oJumpToOptions
 */
// This'll contain all JumpTo objects on the page.
var aJumpTo = [];
function JumpTo(oJumpToOptions)
{
	this.opt = oJumpToOptions;
	this.dropdownList = null;
	this.showSelect();

	createEventListener(this.dropdownList);

	if (is_mobile && is_touch)
		this.dropdownList.addEventListener('touchstart', grabJumpToContent);
	else
		this.dropdownList.addEventListener('mouseenter', grabJumpToContent);
}

// Remove all the options in the select. Method of the JumpTo class.
JumpTo.prototype.removeAll = function ()
{
//	var dropdownList = document.getElementById(this.opt.sContainerId + '_select');
for (var i = this.dropdownList.options.length; i > 0; i--)
		this.dropdownList.remove(i - 1);
};

// Show the initial select box (onload). Method of the JumpTo class.
JumpTo.prototype.showSelect = function ()
{
	var sChildLevelPrefix = '';

	for (var i = this.opt.iCurBoardChildLevel; i > 0; i--)
		sChildLevelPrefix += this.opt.sBoardChildLevelIndicator;

	if (sChildLevelPrefix !== '')
		sChildLevelPrefix += this.opt.sBoardPrefix;

	document.getElementById(this.opt.sContainerId).innerHTML = this.opt.sJumpToTemplate.replace(/%select_id%/, this.opt.sContainerId + '_select').replace(/%dropdown_list%/, '<select ' + (this.opt.bDisabled === true ? 'disabled="disabled" ' : '') + (this.opt.sClassName !== undefined ? 'class="' + this.opt.sClassName + '" ' : '') + 'name="' + (this.opt.sCustomName !== undefined ? this.opt.sCustomName : this.opt.sContainerId + '_select') + '" id="' + this.opt.sContainerId + '_select"><option value="' + (this.opt.bNoRedirect !== undefined && this.opt.bNoRedirect === true ? this.opt.iCurBoardId : '?board=' + this.opt.iCurBoardId + '.0') + '">' + sChildLevelPrefix + this.opt.sCurBoardName.removeEntities() + '</option></select>&nbsp;' + (this.opt.sGoButtonLabel !== undefined ? '<input type="button" class="button_submit" value="' + this.opt.sGoButtonLabel + '" onclick="window.location.href = \'' + elk_prepareScriptUrl(elk_scripturl) + 'board=' + this.opt.iCurBoardId + '.0\';" />' : ''));
	this.dropdownList = document.getElementById(this.opt.sContainerId + '_select');
};

// Fill the jump to box with entries. Method of the JumpTo class.
JumpTo.prototype.fillSelect = function (aBoardsAndCategories)
{
	this.removeAll();

	if (is_mobile && is_touch)
		this.dropdownList.removeEventListener('touchstart', grabJumpToContent);
	else
		this.dropdownList.removeEventListener('mouseenter', grabJumpToContent);

	// Create a document fragment that'll allowing inserting big parts at once.
	var oListFragment = document.createDocumentFragment(),
		oOptgroupFragment = document.createElement('optgroup');

	// Loop through all items to be added.
	for (var i = 0, n = aBoardsAndCategories.length; i < n; i++)
	{
		var j,
			sChildLevelPrefix = '',
			oOption,
			oText;

		if (aBoardsAndCategories[i].isCategory)
		{
			oOptgroupFragment = document.createElement('optgroup');
			oOptgroupFragment.label = aBoardsAndCategories[i].name;
			oListFragment.appendChild(oOptgroupFragment);
			continue;
		}
		else
		{
			for (j = aBoardsAndCategories[i].childLevel, sChildLevelPrefix = ''; j > 0; j--)
				sChildLevelPrefix += this.opt.sBoardChildLevelIndicator;

			if (sChildLevelPrefix !== '')
				sChildLevelPrefix += this.opt.sBoardPrefix;
		}

		oOption = document.createElement('option');
		oText = document.createElement('span');
		oText.innerHTML = sChildLevelPrefix + aBoardsAndCategories[i].name;

		if (aBoardsAndCategories[i].id === this.opt.iCurBoardId)
			oOption.selected = 'selected';

		oOption.appendChild(oText);

		if (!this.opt.bNoRedirect)
		{
			oOption.value = '?board=' + aBoardsAndCategories[i].id + '.0';
		}
		else
		{
			oOption.value = aBoardsAndCategories[i].id;
		}

		oOptgroupFragment.appendChild(oOption);
	}

	// Add the remaining items after the currently selected item.
	this.dropdownList.appendChild(oListFragment);

	// Add an onchange action
	if (!this.opt.bNoRedirect)
		this.dropdownList.onchange = function() {
			if (this.selectedIndex > 0 && this.options[this.selectedIndex].value)
				window.location.href = elk_scripturl + this.options[this.selectedIndex].value.substr(elk_scripturl.indexOf('?') === -1 || this.options[this.selectedIndex].value.substr(0, 1) !== '?' ? 0 : 1);
		};

	// Handle custom function hook before showing the new select.
	if ('funcOnBeforeCollapse' in this.opt)
	{
		this.tmpMethod = this.opt.funcOnBeforeCollapse;
		this.tmpMethod(this);
		delete this.tmpMethod;
	}
};

/**
 * IconList object.
 *
 * Allows clicking on a icon to expand out the available options to change
 * Change is done via ajax
 * Used for topic icon and member group icon selections
 *
 * Available options
 *	sBackReference:
 *	sIconIdPrefix:
 *	bShowModify:
 *	iBoardId:
 *	iTopicId:
 *	sAction:
 *	sLabelIconList:
 *
 * The following are style elements that can be passed
 *	sBoxBackground:
 *	sBoxBackgroundHover:
 *	iBoxBorderWidthHover:
 *	sBoxBorderColorHover:
 *	sContainerBackground:
 *	sContainerBorder:
 *	sItemBorder:
 *	sItemBorderHover:
 *	sItemBackground:
 *	sItemBackgroundHover:
 *
 * @param {object} oOptions
 */
// A global array containing all IconList objects.
var aIconLists = [];
function IconList(oOptions)
{
	this.opt = oOptions;
	this.bListLoaded = false;
	this.oContainerDiv = null;
	this.funcMousedownHandler = null;
	this.funcParent = this;
	this.iCurMessageId = 0;
	this.iCurTimeout = 0;

	// Set a default Action
	if (!('sAction' in this.opt) || this.opt.sAction === null)
		this.opt.sAction = 'messageicons;board=' + this.opt.iBoardId;

	this.initIcons();
}

// Replace all message icons by icons with hoverable and clickable div's.
IconList.prototype.initIcons = function ()
{
	for (var i = document.images.length - 1, iPrefixLength = this.opt.sIconIdPrefix.length; i >= 0; i--)
		if (document.images[i].id.substr(0, iPrefixLength) === this.opt.sIconIdPrefix)
			setOuterHTML(document.images[i], '<div title="' + this.opt.sLabelIconList + '" onclick="' + this.opt.sBackReference + '.openPopup(this, ' + document.images[i].id.substr(iPrefixLength) + ')" onmouseover="' + this.opt.sBackReference + '.onBoxHover(this, true)" onmouseout="' + this.opt.sBackReference + '.onBoxHover(this, false)" style="background: ' + this.opt.sBoxBackground + '; cursor: pointer; padding: 2px; margin: 0 auto; vertical-align: top"><img src="' + document.images[i].src + '" alt="' + document.images[i].alt + '" id="' + document.images[i].id + '" style="vertical-align: top; margin: 0 auto; padding: 0 2px;" /></div>');
};

// Event for the mouse hovering over the original icon.
IconList.prototype.onBoxHover = function (oDiv, bMouseOver)
{
	oDiv.style.border = bMouseOver ? this.opt.iBoxBorderWidthHover + 'px solid ' + this.opt.sBoxBorderColorHover : '';
	oDiv.style.background = bMouseOver ? this.opt.sBoxBackgroundHover : this.opt.sBoxBackground;
	oDiv.style.padding = bMouseOver ? (2 - this.opt.iBoxBorderWidthHover) + 'px' : '2px';
};

// Show the list of icons after the user clicked the original icon.
IconList.prototype.openPopup = function (oDiv, iMessageId)
{
	this.iCurMessageId = iMessageId;

	if (!this.bListLoaded && this.oContainerDiv === null)
	{
		// Create a container div.
		this.oContainerDiv = document.createElement('div');
		this.oContainerDiv.id = 'iconList';
		this.oContainerDiv.style.display = 'none';
		this.oContainerDiv.style.cursor = 'pointer';
		this.oContainerDiv.style.position = 'absolute';
		this.oContainerDiv.style.background = this.opt.sContainerBackground;
		this.oContainerDiv.style.border = this.opt.sContainerBorder;
		this.oContainerDiv.style.padding = '6px 0px';
		document.body.appendChild(this.oContainerDiv);

		// Start to fetch its contents.
		ajax_indicator(true);
		sendXMLDocument.call(this, elk_prepareScriptUrl(elk_scripturl) + 'action=xmlhttp;sa=' + this.opt.sAction + ';xml', '', this.onIconsReceived);

		createEventListener(document.body);
	}

	// Set the position of the container.
	var aPos = elk_itemPos(oDiv);

	this.oContainerDiv.style.top = (aPos[1] + oDiv.offsetHeight) + 'px';
	this.oContainerDiv.style.left = (aPos[0] - 1) + 'px';
	this.oClickedIcon = oDiv;

	if (this.bListLoaded)
		this.oContainerDiv.style.display = 'block';

	document.body.addEventListener('mousedown', this.onWindowMouseDown, false);
};

// Setup the list of icons once it is received through xmlHTTP.
IconList.prototype.onIconsReceived = function (oXMLDoc)
{
	var icons = oXMLDoc.getElementsByTagName('elk')[0].getElementsByTagName('icon'),
		sItems = '';

	for (var i = 0, n = icons.length; i < n; i++)
		sItems += '<span onmouseover="' + this.opt.sBackReference + '.onItemHover(this, true)" onmouseout="' + this.opt.sBackReference + '.onItemHover(this, false);" onmousedown="' + this.opt.sBackReference + '.onItemMouseDown(this, \'' + icons[i].getAttribute('value') + '\');" style="padding: 2px 3px; line-height: 20px; border: ' + this.opt.sItemBorder + '; background: ' + this.opt.sItemBackground + '"><img src="' + icons[i].getAttribute('url') + '" alt="' + icons[i].getAttribute('name') + '" title="' + icons[i].firstChild.nodeValue + '" style="vertical-align: middle" /></span>';

	this.oContainerDiv.innerHTML = sItems;
	this.oContainerDiv.style.display = 'block';
	this.bListLoaded = true;

	if (is_ie)
		this.oContainerDiv.style.width = this.oContainerDiv.clientWidth + 'px';

	ajax_indicator(false);
};

// Event handler for hovering over the icons.
IconList.prototype.onItemHover = function (oDiv, bMouseOver)
{
	oDiv.style.background = bMouseOver ? this.opt.sItemBackgroundHover : this.opt.sItemBackground;
	oDiv.style.border = bMouseOver ? this.opt.sItemBorderHover : this.opt.sItemBorder;

	if (this.iCurTimeout !== 0)
		window.clearTimeout(this.iCurTimeout);

	if (bMouseOver)
		this.onBoxHover(this.oClickedIcon, true);
	else
		this.iCurTimeout = window.setTimeout(this.opt.sBackReference + '.collapseList();', 500);
};

// Event handler for clicking on one of the icons.
IconList.prototype.onItemMouseDown = function (oDiv, sNewIcon)
{
	if (this.iCurMessageId !== 0)
	{
		ajax_indicator(true);
		this.tmpMethod = getXMLDocument;
		var oXMLDoc = this.tmpMethod(elk_prepareScriptUrl(elk_scripturl) + 'action=jsmodify;topic=' + this.opt.iTopicId + ';msg=' + this.iCurMessageId + ';' + elk_session_var + '=' + elk_session_id + ';icon=' + sNewIcon + ';xml');
		delete this.tmpMethod;
		ajax_indicator(false);

		var oMessage = oXMLDoc.responseXML.getElementsByTagName('elk')[0].getElementsByTagName('message')[0];
		if (oMessage.getElementsByTagName('error').length === 0)
		{
			if ((this.opt.bShowModify && oMessage.getElementsByTagName('modified').length !== 0) && (document.getElementById('modified_' + this.iCurMessageId) !== null))
				document.getElementById('modified_' + this.iCurMessageId).innerHTML = oMessage.getElementsByTagName('modified')[0].childNodes[0].nodeValue;

			this.oClickedIcon.getElementsByTagName('img')[0].src = oDiv.getElementsByTagName('img')[0].src;
		}
	}
	else
	{
		this.oClickedIcon.getElementsByTagName('img')[0].src = oDiv.getElementsByTagName('img')[0].src;
		if ('sLabelIconBox' in this.opt)
			document.getElementById(this.opt.sLabelIconBox).value = sNewIcon;
	}
};

// Event handler for clicking outside the list (will make the list disappear).
IconList.prototype.onWindowMouseDown = function ()
{
	for (var i = aIconLists.length - 1; i >= 0; i--)
	{
		aIconLists[i].funcParent.tmpMethod = aIconLists[i].collapseList;
		aIconLists[i].funcParent.tmpMethod();
		delete aIconLists[i].funcParent.tmpMethod;
	}
};

// Collapse the list of icons.
IconList.prototype.collapseList = function()
{
	this.onBoxHover(this.oClickedIcon, false);
	this.oContainerDiv.style.display = 'none';
	this.iCurMessageId = 0;
	document.body.removeEventListener('mousedown', this.onWindowMouseDown, false);
};

/**
 * Short function for finding the actual screen position of an item.
 * Used for example to position the suggest member name box
 *
 * @param {object} itemHandle
 */
function elk_itemPos(itemHandle)
{
	var itemX = 0,
		itemY = 0;

	if ('offsetParent' in itemHandle)
	{
		itemX = itemHandle.offsetLeft;
		itemY = itemHandle.offsetTop;

		while (itemHandle.offsetParent && typeof(itemHandle.offsetParent) === 'object')
		{
			itemHandle = itemHandle.offsetParent;
			itemX += itemHandle.offsetLeft;
			itemY += itemHandle.offsetTop;
		}
	}
	else if ('x' in itemHandle)
	{
		itemX = itemHandle.x;
		itemY = itemHandle.y;
	}

	return [itemX, itemY];
}

/**
 * This function takes the script URL and prepares it to allow the query string to be appended to it.
 *
 * @param {string} sUrl
 */
function elk_prepareScriptUrl(sUrl)
{
	return sUrl.indexOf('?') === -1 ? sUrl + '?' : sUrl + (sUrl.charAt(sUrl.length - 1) === '?' || sUrl.charAt(sUrl.length - 1) === '&' || sUrl.charAt(sUrl.length - 1) === ';' ? '' : ';');
}

/**
 * Load Event function, adds new events to the window onload control
 *
 * @param {object} fNewOnload function object to call
 *
 * @deprecated aince 2.0; use window.addEventListener("load", fNewOnload)
 */
function addLoadEvent(fNewOnload)
{
	window.addEventListener("load", fNewOnload);
}

/**
 * Get the text in a code tag by selecting the [select] in the code header
 *
 * @param {object} oCurElement
 * @param {boolean} bActOnElement the passed element contains the code
 */
function elkSelectText(oCurElement, bActOnElement)
{
	var oCodeArea = null;

	// The place we're looking for is one div up, and next door - if it's auto detect.
	if (typeof(bActOnElement) === 'boolean' && bActOnElement)
		oCodeArea = document.getElementById(oCurElement);
	else
		oCodeArea = oCurElement.parentNode.nextSibling;

	// Did not find it, bail
	if (typeof(oCodeArea) !== 'object' || oCodeArea === null)
		return false;

	// Start off with internet explorer < 9.
	if ('createTextRange' in document.body)
	{
		var oCurRange = document.body.createTextRange();
		oCurRange.moveToElementText(oCodeArea);
		oCurRange.select();
	}
	// All the rest
	else if (window.getSelection)
	{
		var oCurSelection = window.getSelection(),
			curRange = document.createRange();

			curRange.selectNodeContents(oCodeArea);
			oCurSelection.removeAllRanges();
			oCurSelection.addRange(curRange);
	}

	return false;
}

/**
 * A function needed to discern HTML entities from non-western characters.
 *
 * @param {string} sFormName
 * @param {Array.} aElementNames
 * @param {string} sMask
 */
function smc_saveEntities(sFormName, aElementNames, sMask) {
	var i = 0,
		n = 0;

	if (typeof(sMask) === 'string') {
		for (i = 0, n = document.forms[sFormName].elements.length; i < n; i++) {
			if (document.forms[sFormName].elements[i].id.substr(0, sMask.length) === sMask) {
				aElementNames[aElementNames.length] = document.forms[sFormName].elements[i].name;
			}
		}
	}

	for (i = 0, n = aElementNames.length; i < n; i++) {
		if (aElementNames[i] in document.forms[sFormName]) {
			// Handle the editor.
			if (typeof post_box_name !== 'undefined' && aElementNames[i] === post_box_name && $editor_data[post_box_name] !== undefined) {
				document.forms[sFormName][aElementNames[i]].value = $editor_data[post_box_name].val().replace(/&#/g, '&#38;#');
				$editor_data[post_box_name].val(document.forms[sFormName][aElementNames[i]].value);
			}
			else {
				document.forms[sFormName][aElementNames[i]].value = document.forms[sFormName][aElementNames[i]].value.replace(/&#/g, '&#38;#');
			}
		}
	}
}

/**
 * Enable / Disable the "Only show the results after the poll has expired."
 * based on if they have entered a time limit or not
 *
 * @returns {undefined}
 */
function pollOptions()
{
	var expire_time = document.getElementById('poll_expire');

	if (isEmptyText(expire_time) || expire_time.value === 0)
	{
		document.forms[form_name].poll_hide[2].disabled = true;
		if (document.forms[form_name].poll_hide[2].checked)
			document.forms[form_name].poll_hide[1].checked = true;
	}
	else
		document.forms[form_name].poll_hide[2].disabled = false;
}

/**
 * Generate the number of days in a given month for a given year
 * Used to populate the day pulldown in the calendar
 *
 * @param {int} [offset] optional
 */
function generateDays(offset)
{
	// Work around JavaScript's lack of support for default values...
	offset = typeof(offset) !== 'undefined' ? offset : '';

	var days = 0,
		selected = 0,
		dayElement = document.getElementById("day" + offset),
		yearElement = document.getElementById("year" + offset),
		monthElement = document.getElementById("month" + offset),
		monthLength = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

	monthLength[1] = 28;
	if (yearElement.options[yearElement.selectedIndex].value % 4 === 0)
		monthLength[1] = 29;

	selected = dayElement.selectedIndex;
	while (dayElement.options.length)
		dayElement.options[0] = null;

	days = monthLength[monthElement.value - 1];

	for (var i = 1; i <= days; i++)
		dayElement.options[dayElement.length] = new Option(i, i);

	if (selected < days)
		dayElement.selectedIndex = selected;
}

/**
 * Enable/disable the board selection list when a calendar event is linked, or not, to a post
 *
 * @param {string} form
 */
function toggleLinked(form)
{
	form.board.disabled = !form.link_to_board.checked;
}

/**
 * load event for search for and PM search, un escapes any existing search
 * value for back button or change search etc.
 */
function initSearch()
{
	if (document.forms.searchform.search.value.indexOf("%u") !== -1)
		document.forms.searchform.search.value = decodeURI(document.forms.searchform.search.value);
}

/**
 * Checks or unchecks the list of available boards
 *
 * @param {type} ids
 * @param {string} aFormName
 * @param {string} sInputName
 */
function selectBoards(ids, aFormName, sInputName)
{
	var toggle = true,
		i = 0;

	for (var f = 0, max = document.forms.length; f < max; f++)
	{
		if (document.forms[f].name == aFormName)
		{
			aForm = document.forms[f];
			break;
		}
	}
	if (typeof aForm == 'undefined')
		return;

	for (i = 0; i < ids.length; i++)
		toggle = toggle && aForm[sInputName + '[' + ids[i] + ']'].checked;

	for (i = 0; i < ids.length; i++)
		aForm[sInputName + '[' + ids[i] + ']'].checked = !toggle;
}

/**
 * Expands or collapses a container
 *
 * @param {string} id
 * @param {string} icon
 * @param {int} speed
 */
function expandCollapse(id, icon, speed)
{
	var	oId = $('#' + id);

	icon = icon || false;
	speed = speed || 300;

	// Change the icon on the box as well?
	if (icon)
		$('#' + icon).attr("src", elk_images_url + (oId.is(":hidden") !== true ? "/selected.png" : "/selected_open.png"));

	// Open or collapse the content id
	oId.slideToggle(speed);
}

/**
 * Highlight a selection box by adding the highlight2 class
 *
 * @param {string} container_id
 */
function initHighlightSelection(container_id)
{
	$('#' + container_id + ' [name="def_language"]').on('click', function (ev) {
		$('#' + container_id + ' .standard_row').removeClass('highlight2');
		$(this).parent().parent().addClass('highlight2');
	});
}

/**
 * Auto submits a paused form, such as a maintenance task
 *
 * @param {int} countdown
 * @param {string} txt_message
 * @param {string} [formName=autoSubmit]
 */
function doAutoSubmit(countdown, txt_message, formName)
{
	var formID = typeof(formName) !== 'undefined' ? formName : "autoSubmit";

	if (countdown === 0)
		document.forms[formID].submit();
	else if (countdown === -1)
		return;

	document.forms[formID].cont.value = txt_message + ' (' + countdown + ')';
	countdown--;

	setTimeout(function() {doAutoSubmit(countdown, txt_message, formID);}, 1000);
}
