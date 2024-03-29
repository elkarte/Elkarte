/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 */

/**
 * This file contains javascript associated with the registration screen
 */

/**
 * Elk Registration class
 *
 * @param {String} formID name of the registration form
 * @param {Number} passwordDifficultyLevel
 * @param {Array.} regTextStrings
 */
function elkRegister (formID, passwordDifficultyLevel, regTextStrings)
{
	this.verificationFields = [];
	this.verificationFieldLength = 0;
	this.textStrings = regTextStrings ? regTextStrings : [];
	this.passwordLevel = passwordDifficultyLevel ? passwordDifficultyLevel : 0;
	this.displayChanged = false;

	// Setup all the fields!
	this.autoSetup(formID);
}

// This is a field which requires some form of verification check.
elkRegister.prototype.addVerificationField = function(fieldType, fieldID) {
	// Check the field exists.
	if (!document.getElementById(fieldID))
	{
		return;
	}

	// Get the handles.
	let inputHandle = document.getElementById(fieldID),
		imageHandle = document.getElementById(fieldID + '_img') ? document.getElementById(fieldID + '_img') : false,
		divHandle = document.getElementById(fieldID + '_div') ? document.getElementById(fieldID + '_div') : false;

	// What is the event handler?
	let eventHandler = false;
	if (fieldType === 'pwmain')
	{
		eventHandler = 'refreshMainPassword';
	}
	else if (fieldType === 'pwverify')
	{
		eventHandler = 'refreshVerifyPassword';
	}
	else if (fieldType === 'username')
	{
		eventHandler = 'refreshUsername';
	}
	else if (fieldType === 'displayname')
	{
		eventHandler = 'refreshDisplayname';
	}
	else if (fieldType === 'reserved')
	{
		eventHandler = 'refreshMainPassword';
	}

	// Store this field.
	let vFieldIndex = fieldType === 'reserved' ? fieldType + this.verificationFieldLength : fieldType;
	this.verificationFields[vFieldIndex] = new Array(6);
	this.verificationFields[vFieldIndex][0] = fieldID;
	this.verificationFields[vFieldIndex][1] = inputHandle;
	this.verificationFields[vFieldIndex][2] = imageHandle;
	this.verificationFields[vFieldIndex][3] = divHandle;
	this.verificationFields[vFieldIndex][4] = fieldType;
	this.verificationFields[vFieldIndex][5] = inputHandle.className;

	// Keep a count to it!
	this.verificationFieldLength++;

	// Step to it!
	if (eventHandler)
	{
		var _self = this;
		createEventListener(inputHandle);
		inputHandle.addEventListener('keyup', function(event) {
			_self[eventHandler].call(_self, event);
		}, false);
		this[eventHandler]();

		// Username will auto check on blur!
		inputHandle.addEventListener('blur', function(event) {
			_self.autoCheckUsername.call(_self, event);
		}, false);
	}

	// Make the div visible!
	if (divHandle)
	{
		divHandle.style.display = 'inline';
	}
};

// A button to trigger a username search
elkRegister.prototype.addUsernameSearchTrigger = function(elementID) {
	let buttonHandle = document.getElementById(elementID),
		_self = this;

	// Attach the event to this element.
	createEventListener(buttonHandle);
	buttonHandle.addEventListener('click', function(event) {
		_self.checkUsername.call(_self, event);
	}, false);
};

// This function will automatically pick up all the necessary verification fields and initialise their visual status.
elkRegister.prototype.autoSetup = function(formID) {
	if (!document.getElementById(formID))
	{
		return false;
	}

	let curElement,
		curType;

	for (let i = 0, n = document.getElementById(formID).elements.length; i < n; i++)
	{
		curElement = document.getElementById(formID).elements[i];

		// Does the ID contain the keyword 'autov'?
		if (curElement.id.indexOf('autov') !== -1 && (curElement.type === 'text' || curElement.type === 'password'))
		{
			// This is probably it - but does it contain a field type?
			curType = 0;

			// Username can only be done with XML.
			if (curElement.id.indexOf('username') !== -1)
			{
				curType = 'username';
			}
			else if (curElement.id.indexOf('displayname') !== -1)
			{
				curType = 'displayname';
			}
			else if (curElement.id.indexOf('pwmain') !== -1)
			{
				curType = 'pwmain';
			}
			else if (curElement.id.indexOf('pwverify') !== -1)
			{
				curType = 'pwverify';
			}
			// This means this field is reserved and cannot be contained in the password!
			else if (curElement.id.indexOf('reserve') !== -1)
			{
				curType = 'reserved';
			}

			// If we're happy let's add this element!
			if (curType)
			{
				this.addVerificationField(curType, curElement.id);
			}

			// If this is the username do we also have a button to find the user?
			if (curType === 'username' && document.getElementById(curElement.id + '_link'))
			{
				this.addUsernameSearchTrigger(curElement.id + '_link');
			}
		}
	}

	return true;
};

// What is the password state?
elkRegister.prototype.refreshMainPassword = function(called_from_verify) {
	if (!this.verificationFields.pwmain)
	{
		return false;
	}

	let curPass = this.verificationFields.pwmain[1].value,
		stringIndex = '';

	// Is it a valid length?
	if ((curPass.length < 8 && this.passwordLevel >= 1) || curPass.length < 4)
	{
		stringIndex = 'password_short';
	}

	// More than basic?
	if (this.passwordLevel >= 1)
	{
		// If there is a username check it's not in the password!
		if (this.verificationFields.username && this.verificationFields.username[1].value && curPass.indexOf(this.verificationFields.username[1].value) !== -1)
		{
			stringIndex = 'password_reserved';
		}

		// Any reserved fields?
		for (let i in this.verificationFields)
		{
			if (this.verificationFields[i][4] === 'reserved' && this.verificationFields[i][1].value && curPass.indexOf(this.verificationFields[i][1].value) !== -1)
			{
				stringIndex = 'password_reserved';
			}
		}

		// Finally - is it hard and as such requiring mixed cases and numbers?
		if (this.passwordLevel > 1)
		{
			if (curPass === curPass.toLowerCase())
			{
				stringIndex = 'password_numbercase';
			}

			if (!curPass.match(/(\D\d|\d\D)/))
			{
				stringIndex = 'password_numbercase';
			}
		}
	}

	let isValid = stringIndex === '';
	if (stringIndex === '')
	{
		stringIndex = 'password_valid';
	}

	// Set the image.
	this.setVerificationImage(this.verificationFields.pwmain[2], isValid, this.textStrings[stringIndex] ? this.textStrings[stringIndex] : '');
	this.verificationFields.pwmain[1].className = this.verificationFields.pwmain[5] + ' ' + (isValid ? 'valid_input' : 'invalid_input');

	// As this has changed the verification one may have too!
	if (this.verificationFields.pwverify && !called_from_verify)
	{
		this.refreshVerifyPassword();
	}

	return isValid;
};

// Check that the verification password matches the main one!
elkRegister.prototype.refreshVerifyPassword = function() {
	// Can't do anything without something to check again!
	if (!this.verificationFields.pwmain)
	{
		return false;
	}

	// Check and set valid status!
	let isValid = this.verificationFields.pwmain[1].value === this.verificationFields.pwverify[1].value && this.refreshMainPassword(true),
		alt = this.textStrings[isValid === 1 ? 'password_valid' : 'password_no_match'] ? this.textStrings[isValid === 1 ? 'password_valid' : 'password_no_match'] : '';

	this.setVerificationImage(this.verificationFields.pwverify[2], isValid, alt);
	this.verificationFields.pwverify[1].className = this.verificationFields.pwverify[5] + ' ' + (isValid ? 'valid_input' : 'invalid_input');

	return true;
};

// If the username is changed just revert the status of whether it's valid!
elkRegister.prototype.refreshUsername = function() {
	if (!this.verificationFields.username)
	{
		return false;
	}

	if (typeof this.verificationFields.displayname !== 'undefined' && this.displayChanged === false)
	{
		this.verificationFields.displayname[1].value = this.verificationFields.username[1].value;
	}

	// Restore the class name.
	if (this.verificationFields.username[1].className)
	{
		this.verificationFields.username[1].className = this.verificationFields.username[5];
	}

	// Check the image is correct.
	let alt = this.textStrings.username_check ? this.textStrings.username_check : '';
	this.setVerificationImage(this.verificationFields.username[2], 'check', alt);

	// Check the password is still OK.
	this.refreshMainPassword();

	return true;
};

// If the displayname is changed just revert the status of whether it's valid!
elkRegister.prototype.refreshDisplayname = function(e) {
	if (!this.verificationFields.displayname)
	{
		return false;
	}

	if (typeof e !== 'undefined')
	{
		if (!(e.altKey || e.ctrlKey || e.shiftKey) && e.which > 48)
		{
			this.displayChanged = true;
		}
	}

	// Restore the class name.
	if (this.verificationFields.displayname[1].className)
	{
		this.verificationFields.displayname[1].className = this.verificationFields.displayname[5];
	}

	// Check the image is correct.
	let alt = this.textStrings.username_check ? this.textStrings.username_check : '';
	this.setVerificationImage(this.verificationFields.displayname[2], 'check', alt);

	// Check the password is still OK.
	this.refreshMainPassword();

	return true;
};

// This is a pass through function that ensures we don't do any of the AJAX notification stuff.
elkRegister.prototype.autoCheckUsername = function() {
	this.checkUsername(true);
};

// Check whether the username exists?
elkRegister.prototype.checkUsername = function(is_auto) {
	if (!this.verificationFields.username)
	{
		return false;
	}

	// Get the username and do nothing without one!
	let curUsername = this.verificationFields.username[1].value,
		curDisplayname = typeof this.verificationFields.displayname === 'undefined' ? '' : this.verificationFields.displayname[1].value;

	if (!curUsername && !curDisplayname)
	{
		return false;
	}

	if (!is_auto)
	{
		ajax_indicator(true);
	}

	// Request a search on that username.
	let checkName = curUsername.php_urlencode();
	sendXMLDocument.call(this, elk_prepareScriptUrl(elk_scripturl) + 'action=register;sa=usernamecheck;api=xml;username=' + checkName, null, this.checkUsernameCallback);
	if (curDisplayname)
	{
		var checkDisplay = curDisplayname.php_urlencode();
		sendXMLDocument.call(this, elk_prepareScriptUrl(elk_scripturl) + 'action=register;sa=usernamecheck;api=xml;username=' + checkDisplay, null, this.checkDisplaynameCallback);
	}

	return true;
};

// Callback for getting the username data.
elkRegister.prototype.checkUsernameCallback = function(XMLDoc) {
	let isValid = 1;

	if (XMLDoc.getElementsByTagName('username'))
	{
		isValid = parseInt(XMLDoc.getElementsByTagName('username')[0].getAttribute('valid'));
	}

	// What to alt?
	let alt = this.textStrings[isValid === 1 ? 'username_valid' : 'username_invalid'] ? this.textStrings[isValid === 1 ? 'username_valid' : 'username_invalid'] : '';

	this.verificationFields.username[1].className = this.verificationFields.username[5] + ' ' + (isValid === 1 ? 'valid_input' : 'invalid_input');
	this.setVerificationImage(this.verificationFields.username[2], isValid === 1, alt);

	ajax_indicator(false);
};

// Callback for getting the displayname data.
elkRegister.prototype.checkDisplaynameCallback = function(XMLDoc) {
	let isValid = 1;

	if (XMLDoc.getElementsByTagName('username'))
	{
		isValid = parseInt(XMLDoc.getElementsByTagName('username')[0].getAttribute('valid'));
	}

	// What to alt?
	let alt = this.textStrings[isValid === 1 ? 'username_valid' : 'username_invalid'] ? this.textStrings[isValid === 1 ? 'username_valid' : 'username_invalid'] : '';

	this.verificationFields.displayname[1].className = this.verificationFields.displayname[5] + ' ' + (isValid === 1 ? 'valid_input' : 'invalid_input');
	this.setVerificationImage(this.verificationFields.displayname[2], isValid === 1, alt);

	ajax_indicator(false);
};

// Set the image to be the correct type.
elkRegister.prototype.setVerificationImage = function(imageHandle, imageIcon, alt) {
	if (!imageHandle)
	{
		return false;
	}

	if (!alt)
	{
		alt = '*';
	}

	let curClass = imageIcon ? (imageIcon === 'check' ? 'i-help' : 'i-check') : 'i-warn';

	imageHandle.alt = alt;
	imageHandle.title = alt;
	imageHandle.className = 'icon ' + curClass;

	return true;
};

/**
 * Used when the admin registers a new member, enable or disables the email activation
 */
function onCheckChange ()
{
	if (document.forms.postForm.emailActivate.checked || document.forms.postForm.password.value === '')
	{
		document.forms.postForm.emailPassword.disabled = true;
		document.forms.postForm.emailPassword.checked = true;
	}
	else
	{
		document.forms.postForm.emailPassword.disabled = false;
	}
}

/**
 * Registers the language for agreement and privacy policy loading.
 *
 * @param {Event} event - The event object passed when the language is changed.
 */
function registerAgreementLanguageLoad (event)
{
	let postData = serialize({lang: event.target.value.trim()});

	ajax_indicator(true);
	fetch(elk_prepareScriptUrl(elk_scripturl) + 'action=jslocale;sa=agreement;api=json', {
		method: 'POST',
		body: postData,
		headers: {
			'X-Requested-With': 'XMLHttpRequest',
			'Content-Type': 'application/x-www-form-urlencoded',
			'Accept': 'application/json'
		}
	})
		.then(function(response) {
			if (!response.ok)
			{
				throw new Error('HTTP error ' + response.status);
			}

			return response.json();
		})
		.then(function(request) {
			if (request !== '')
			{
				document.querySelector('#agreement_box').innerHTML = request.agreement;
				document.querySelector('#privacypol_box').innerHTML = request.privacypol;
			}
		})
		.catch(function(error) {
			if ('console' in window && window.console.info)
			{
				console.log('Error:', error);
			}
		})
		.finally(function() {
			// turn off the indicator
			ajax_indicator(false);
		});
}
