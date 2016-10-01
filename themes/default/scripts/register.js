/*!
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
 * This file contains javascript associated with the registration screen
 */

/**
 * Elk Registration class
 *
 * @param {String} formID name of the registration form
 * @param {Number} passwordDifficultyLevel
 * @param {Array.} regTextStrings
 */
function elkRegister(formID, passwordDifficultyLevel, regTextStrings)
{
	this.verificationFields = [];
	this.verificationFieldLength = 0;
	this.textStrings = regTextStrings ? regTextStrings : [];
	this.passwordLevel = passwordDifficultyLevel ? passwordDifficultyLevel : 0;

	// Setup all the fields!
	this.autoSetup(formID);
}

// This is a field which requires some form of verification check.
elkRegister.prototype.addVerificationField = function(fieldType, fieldID)
{
	// Check the field exists.
	if (!document.getElementById(fieldID))
		return;

	// Get the handles.
	var inputHandle = document.getElementById(fieldID),
		imageHandle = document.getElementById(fieldID + '_img') ? document.getElementById(fieldID + '_img') : false,
		divHandle = document.getElementById(fieldID + '_div') ? document.getElementById(fieldID + '_div') : false;

	// What is the event handler?
	var eventHandler = false;
	if (fieldType === 'pwmain')
		eventHandler = 'refreshMainPassword';
	else if (fieldType === 'pwverify')
		eventHandler = 'refreshVerifyPassword';
	else if (fieldType === 'username')
		eventHandler = 'refreshUsername';
	else if (fieldType === 'reserved')
		eventHandler = 'refreshMainPassword';

	// Store this field.
	var vFieldIndex = fieldType === 'reserved' ? fieldType + this.verificationFieldLength : fieldType;
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
		inputHandle.addEventListener('keyup', function(event) {_self[eventHandler].call(_self, event);}, false);
		this[eventHandler]();

		// Username will auto check on blur!
		inputHandle.addEventListener('blur', function(event) {_self.autoCheckUsername.call(_self, event);}, false);
	}

	// Make the div visible!
	if (divHandle)
		divHandle.style.display = 'inline';
};

// A button to trigger a username search
elkRegister.prototype.addUsernameSearchTrigger = function(elementID)
{
	var buttonHandle = document.getElementById(elementID),
		_self = this;

	// Attach the event to this element.
	createEventListener(buttonHandle);
	buttonHandle.addEventListener('click', function(event) {_self.checkUsername.call(_self, event);}, false);
};

// This function will automatically pick up all the necessary verification fields and initialise their visual status.
elkRegister.prototype.autoSetup = function(formID)
{
	if (!document.getElementById(formID))
		return false;

	var curElement,
		curType;

	for (var i = 0, n = document.getElementById(formID).elements.length; i < n; i++)
	{
		curElement = document.getElementById(formID).elements[i];

		// Does the ID contain the keyword 'autov'?
		if (curElement.id.indexOf('autov') !== -1 && (curElement.type === 'text' || curElement.type === 'password'))
		{
			// This is probably it - but does it contain a field type?
			curType = 0;

			// Username can only be done with XML.
			if (curElement.id.indexOf('username') !== -1)
				curType = 'username';
			else if (curElement.id.indexOf('pwmain') !== -1)
				curType = 'pwmain';
			else if (curElement.id.indexOf('pwverify') !== -1)
				curType = 'pwverify';
			// This means this field is reserved and cannot be contained in the password!
			else if (curElement.id.indexOf('reserve') !== -1)
				curType = 'reserved';

			// If we're happy let's add this element!
			if (curType)
				this.addVerificationField(curType, curElement.id);

			// If this is the username do we also have a button to find the user?
			if (curType === 'username' && document.getElementById(curElement.id + '_link'))
				this.addUsernameSearchTrigger(curElement.id + '_link');
		}
	}

	return true;
};

// What is the password state?
elkRegister.prototype.refreshMainPassword = function(called_from_verify)
{
	if (!this.verificationFields.pwmain)
		return false;

	var curPass = this.verificationFields.pwmain[1].value,
		stringIndex = '';

	// Is it a valid length?
	if ((curPass.length < 8 && this.passwordLevel >= 1) || curPass.length < 4)
		stringIndex = 'password_short';

	// More than basic?
	if (this.passwordLevel >= 1)
	{
		// If there is a username check it's not in the password!
		if (this.verificationFields.username && this.verificationFields.username[1].value && curPass.indexOf(this.verificationFields.username[1].value) !== -1)
			stringIndex = 'password_reserved';

		// Any reserved fields?
		for (var i in this.verificationFields)
		{
			if (this.verificationFields[i][4] === 'reserved' && this.verificationFields[i][1].value && curPass.indexOf(this.verificationFields[i][1].value) !== -1)
				stringIndex = 'password_reserved';
		}

		// Finally - is it hard and as such requiring mixed cases and numbers?
		if (this.passwordLevel > 1)
		{
			if (curPass === curPass.toLowerCase())
				stringIndex = 'password_numbercase';

			if (!curPass.match(/(\D\d|\d\D)/))
				stringIndex = 'password_numbercase';
		}
	}

	var isValid = stringIndex === '';
	if (stringIndex === '')
		stringIndex = 'password_valid';

	// Set the image.
	this.setVerificationImage(this.verificationFields.pwmain[2], isValid, this.textStrings[stringIndex] ? this.textStrings[stringIndex] : '');
	this.verificationFields.pwmain[1].className = this.verificationFields.pwmain[5] + ' ' + (isValid ? 'valid_input' : 'invalid_input');

	// As this has changed the verification one may have too!
	if (this.verificationFields.pwverify && !called_from_verify)
		this.refreshVerifyPassword();

	return isValid;
};

// Check that the verification password matches the main one!
elkRegister.prototype.refreshVerifyPassword = function()
{
	// Can't do anything without something to check again!
	if (!this.verificationFields.pwmain)
		return false;

	// Check and set valid status!
	var isValid = this.verificationFields.pwmain[1].value === this.verificationFields.pwverify[1].value && this.refreshMainPassword(true),
		alt = this.textStrings[isValid === 1 ? 'password_valid' : 'password_no_match'] ? this.textStrings[isValid === 1 ? 'password_valid' : 'password_no_match'] : '';

	this.setVerificationImage(this.verificationFields.pwverify[2], isValid, alt);
	this.verificationFields.pwverify[1].className = this.verificationFields.pwverify[5] + ' ' + (isValid ? 'valid_input' : 'invalid_input');

	return true;
};

// If the username is changed just revert the status of whether it's valid!
elkRegister.prototype.refreshUsername = function()
{
	if (!this.verificationFields.username)
		return false;

	// Restore the class name.
	if (this.verificationFields.username[1].className)
		this.verificationFields.username[1].className = this.verificationFields.username[5];

	// Check the image is correct.
	var alt = this.textStrings.username_check ? this.textStrings.username_check : '';
	this.setVerificationImage(this.verificationFields.username[2], 'check', alt);

	// Check the password is still OK.
	this.refreshMainPassword();

	return true;
};

// This is a pass through function that ensures we don't do any of the AJAX notification stuff.
elkRegister.prototype.autoCheckUsername = function()
{
	this.checkUsername(true);
};

// Check whether the username exists?
elkRegister.prototype.checkUsername = function(is_auto)
{
	if (!this.verificationFields.username)
		return false;

	// Get the username and do nothing without one!
	var curUsername = this.verificationFields.username[1].value;
	if (!curUsername)
		return false;

	if (!is_auto)
		ajax_indicator(true);

	// Request a search on that username.
	var checkName = curUsername.php_urlencode();
	sendXMLDocument.call(this, elk_prepareScriptUrl(elk_scripturl) + 'action=register;sa=usernamecheck;xml;username=' + checkName, null, this.checkUsernameCallback);

	return true;
};

// Callback for getting the username data.
elkRegister.prototype.checkUsernameCallback = function(XMLDoc)
{
	var isValid = true;

	if (XMLDoc.getElementsByTagName("username"))
		isValid = parseInt(XMLDoc.getElementsByTagName("username")[0].getAttribute("valid"));

	// What to alt?
	var alt = this.textStrings[isValid === 1 ? 'username_valid' : 'username_invalid'] ? this.textStrings[isValid === 1 ? 'username_valid' : 'username_invalid'] : '';

	this.verificationFields.username[1].className = this.verificationFields.username[5] + ' ' + (isValid === 1 ? 'valid_input' : 'invalid_input');
	this.setVerificationImage(this.verificationFields.username[2], isValid === 1, alt);

	ajax_indicator(false);
};

// Set the image to be the correct type.
elkRegister.prototype.setVerificationImage = function(imageHandle, imageIcon, alt)
{
	if (!imageHandle)
		return false;

	if (!alt)
		alt = '*';

	var curImage = imageIcon ? (imageIcon === 'check' ? 'field_check.png' : 'field_valid.png') : 'field_invalid.png';
	imageHandle.src = elk_images_url + '/icons/' + curImage;
	imageHandle.alt = alt;
	imageHandle.title = alt;

	return true;
};


/**
 * Sets up the form fields based on the chosen authentication method, openID or password
 */
function updateAuthMethod()
{
	var currentAuthMethod,
		currentForm;

	// What authentication method is being used?
	if (!document.getElementById('auth_openid') || !document.getElementById('auth_openid').checked)
		currentAuthMethod = 'passwd';
	else
		currentAuthMethod = 'openid';

	// No openID?
	if (!document.getElementById('auth_openid'))
		return true;

	currentForm = document.getElementById('auth_openid').form.id;

	document.forms[currentForm].openid_url.disabled = currentAuthMethod !== 'openid';
	document.forms[currentForm].elk_autov_pwmain.disabled = currentAuthMethod !== 'passwd';
	document.forms[currentForm].elk_autov_pwverify.disabled = currentAuthMethod !== 'passwd';
	document.getElementById('elk_autov_pwmain_div').style.display = currentAuthMethod === 'passwd' ? 'inline' : 'none';
	document.getElementById('elk_autov_pwverify_div').style.display = currentAuthMethod === 'passwd' ? 'inline' : 'none';

	if (currentAuthMethod === 'passwd')
	{
		verificationHandle.refreshMainPassword();
		verificationHandle.refreshVerifyPassword();
		document.forms[currentForm].openid_url.style.backgroundColor = '';
		document.getElementById('password1_group').style.display = 'block';
		document.getElementById('password2_group').style.display = 'block';
		document.getElementById('openid_group').style.display = 'none';
	}
	else
	{
		document.forms[currentForm].elk_autov_pwmain.style.backgroundColor = '';
		document.forms[currentForm].elk_autov_pwverify.style.backgroundColor = '';
		document.forms[currentForm].openid_url.style.backgroundColor = '#FFF0F0';
		document.getElementById('password1_group').style.display = 'none';
		document.getElementById('password2_group').style.display = 'none';
		document.getElementById('openid_group').style.display = 'block';
	}

	return true;
}

/**
 * Used when the admin registers a new member, enable or disables the email activation
 */
function onCheckChange()
{
	if (document.forms.postForm.emailActivate.checked || document.forms.postForm.password.value === '')
	{
		document.forms.postForm.emailPassword.disabled = true;
		document.forms.postForm.emailPassword.checked = true;
	}
	else
		document.forms.postForm.emailPassword.disabled = false;
}