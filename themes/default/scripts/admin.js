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
 * Handle the JavaScript surrounding the admin and moderation center.
 */

/**
 * We like the globals cuz they is good to us
 */

/** global: origText, valid, warningMessage, previewData, add_answer_template */
/** global: txt_add_another_answer, last_preview, txt_preview, elk_scripturl, txt_news_error_no_news, oThumbnails, elk_smiley_url */
/** global: db_vis, database_changes_area, elk_session_var, package_ftp_test, package_ftp_test_connection, package_ftp_test_failed */
/** global: elk_session_id, membersSwap, elk_images_url, maintain_members_choose, maintain_members_all */
/** global: reattribute_confirm, reattribute_confirm_email, reattribute_confirm_username, oModeratorSuggest, permission_profiles */
/** global: txt_save, txt_permissions_profile_rename, ajax_notification_cancel_text, txt_theme_remove_confirm, XMLHttpRequest */
/** global: theme_id, frames, editFilename, txt_ban_name_empty, txt_ban_restriction_empty, ElkInfoBar, txt_invalid_response */
/** global: feature_on_text, feature_off_text, core_settings_generic_error, startOptID, add_question_template, question_last_blank */

/** global: ourLanguageVersions, ourVersions, txt_add_another_answer, txt_permissions_commit, Image */

/**
 * Admin index class, it's the admin landing page with site details
 *
 * @param {object} oOptions
 */
function Elk_AdminIndex (oOptions)
{
	this.opt = oOptions;
	this.announcements = [];
	this.current = {};
	this.init_news = false;
	this.init();
}

// Initialize the admin index to handle announcement, current version and updates
Elk_AdminIndex.prototype.init = function() {
	window.adminIndexInstanceRef = this;

	window.addEventListener('load', function() {
		window.adminIndexInstanceRef.loadAdminIndex();
	});
};

Elk_AdminIndex.prototype.loadAdminIndex = function() {
	// Load the current master and your version numbers.
	if (this.opt.bLoadVersions)
	{
		this.showCurrentVersion();
	}

	// Load the text box that says there's a new version available.
	if (this.opt.bLoadUpdateNotification)
	{
		this.checkUpdateAvailable();
	}
};

// Update the announcement container with news
Elk_AdminIndex.prototype.setAnnouncement = function(announcement) {
	let oElem = document.getElementById(this.opt.sAnnouncementContainerId),
		sMessages = this.init_news ? oElem.innerHTML : '';

	announcement.body = announcement.body.replace('\r\n\r\n', '\n');

	// Some markup to html conversion
	let re = new RegExp('^#{1,4}(.*)$', 'ugm');
	announcement.body = announcement.body.replace(re, '<strong>$1</strong>');

	re = new RegExp('\\*\\*(.*)\\*\\*', 'ug');
	announcement.body = announcement.body.replace(re, '<strong>$1</strong>');

	re = new RegExp('^\\* (.*)$', 'ugm');
	announcement.body = announcement.body.replace(re, '&#x2022; $1');

	re = new RegExp('^ {0,1}- (.*)$', 'ugm');
	announcement.body = announcement.body.replace(re, '&#x2022; $1');

	sMessage = this.opt.sAnnouncementMessageTemplate.replace('%href%', announcement.html_url).replace('%subject%', announcement.name).replace('%time%', announcement.published_at.replace(/[TZ]/g, ' ')).replace('%message%', announcement.body).replace(/\n/g, '<br />').replace(/\r/g, '');

	oElem.innerHTML = sMessages + this.opt.sAnnouncementTemplate.replace('%content%', sMessage);
	this.init_news = true;
};

// Updates the current version container with the current version found in the repository
Elk_AdminIndex.prototype.showCurrentVersion = function() {
	let oElkVersionContainer = document.getElementById(this.opt.slatestVersionContainerId),
		oinstalledVersionContainer = document.getElementById(this.opt.sinstalledVersionContainerId),
		sCurrentVersion = oinstalledVersionContainer.innerHTML,
		adminIndex = this,
		elkVersion = '???',
		verCompare = new Elk_ViewVersions();

	fetch('https://api.github.com/repos/elkarte/Elkarte/releases', {
		headers: {
			Accept: 'application/json',
		},
	})
		.then(response => response.json())
		.then(data => {
			let mostRecent = {},
				previous = {};

			adminIndex.current = adminIndex.normalizeVersion(sCurrentVersion);
			data.forEach(function(elem) {
				// Skip draft releases
				if (elem.draft)
				{
					return;
				}

				let release = adminIndex.normalizeVersion(elem.tag_name);
				if (!previous.hasOwnProperty('major') || verCompare.compareVersions(sCurrentVersion, elem.tag_name.replace('-', '').substring(1)))
				{
					if ((elem.prerelease && adminIndex.current.prerelease) || (!elem.prerelease))
					{
						previous = release;
						mostRecent = elem;
					}
				}

				if (adminIndex.opt.bLoadAnnouncements)
				{
					adminIndex.setAnnouncement(elem);
				}
			});

			elkVersion = mostRecent.name.replace(/elkarte/i, '').trim();
			oElkVersionContainer.innerHTML = elkVersion;
			if (verCompare.compareVersions(sCurrentVersion, elkVersion))
			{
				oinstalledVersionContainer.innerHTML = adminIndex.opt.sVersionOutdatedTemplate.replace('%currentVersion%', sCurrentVersion);
			}
		})
		.catch(error => {
			if ('console' in window && console.error)
			{
				console.error('Error : ', error);
			}
		});
};

// Compare two different versions and return true if the first is higher than the second
Elk_AdminIndex.prototype.compareVersion = function(curVer, refVer) {
	if (curVer.major > refVer.major)
	{
		return true;
	}

	if (curVer.major < refVer.major)
	{
		return false;
	}

	if (curVer.minor > refVer.minor)
	{
		return true;
	}

	if (curVer.minor < refVer.minor)
	{
		return false;
	}

	if (curVer.micro > refVer.micro)
	{
		return true;
	}

	if (curVer.micro < refVer.micro)
	{
		return false;
	}

	if (curVer.prerelease)
	{
		if (curVer.nano > refVer.nano)
		{
			return true;
		}

		if (curVer.nano < refVer.nano)
		{
			return false;
		}
	}

	return false;
};

// Split a string representing a version number into an object
Elk_AdminIndex.prototype.normalizeVersion = function(sVersion) {
	let splitVersion = sVersion.split(/[\s-]/),
		normalVersion = {
			major: 0,
			minor: 0,
			micro: 0,
			prerelease: false,
			status: 0,
			nano: 0
		},
		prerelease = false,
		aDevConvert = {'dev': 0, 'alpha': 1, 'beta': 2, 'rc': 3, 'stable': 4};

	for (let i = 0; i < splitVersion.length; i++)
	{
		if (splitVersion[i].toLowerCase() === 'elkarte')
		{
			continue;
		}

		if (splitVersion[i].substring(0, 3).toLowerCase() === 'dev' || splitVersion[i].substring(0, 5).toLowerCase() === 'alpha' || splitVersion[i].substring(0, 4).toLowerCase() === 'beta' || splitVersion[i].substring(0, 2).toLowerCase() === 'rc')
		{
			normalVersion.prerelease = true;
			prerelease = true;

			// the tag name comes with the number attached to the beta/rc
			if (splitVersion[i].indexOf('.') > 0)
			{
				let splitPre = splitVersion[i].split('.');
				normalVersion.nano = parseFloat(splitPre[1]);
				normalVersion.nano = parseFloat(splitVersion[i].substring(splitVersion[i].indexOf('.') + 1));
				normalVersion.status = aDevConvert[splitVersion[i].substring(0, splitVersion[i].indexOf('.')).toLowerCase()];
			}
		}

		// If we have passed a "beta" or an "RC" string, no need to go further
		if (prerelease)
		{
			// Only numbers and dots means a number
			if (splitVersion[i].replace(/[\d.]/g, '') === '')
			{
				normalVersion.nano = parseFloat(splitVersion[i]);
			}

			continue;
		}

		// Likely from the tag
		if (splitVersion[i].substring(0, 1) === 'v')
		{
			splitVersion[i] = splitVersion[i].substring(1);
		}

		// Only numbers and dots means a number
		if (splitVersion[i].replace(/[\d\.]/g, '') === '')
		{
			let ver = splitVersion[i].split('.');
			normalVersion.major = parseInt(ver[0]);
			normalVersion.minor = parseInt(ver[1]);
			normalVersion.micro = ver.length > 2 ? parseInt(ver[2]) : 0;
		}
	}
	return normalVersion;
};

// Checks if a new version of ElkArte is available and if so updates the admin info box
Elk_AdminIndex.prototype.checkUpdateAvailable = function() {
	if (!('ourUpdatePackage' in window))
	{
		return;
	}

	let oContainer = document.getElementById(this.opt.sUpdateNotificationContainerId);

	// Are we setting a custom title and message?
	let sTitle = 'ourUpdateTitle' in window ? window.ourUpdateTitle : this.opt.sUpdateNotificationDefaultTitle,
		sMessage = 'ourUpdateNotice' in window ? window.ourUpdateNotice : this.opt.sUpdateNotificationDefaultMessage;

	oContainer.innerHTML = this.opt.sUpdateNotificationTemplate.replace('%title%', sTitle).replace('%message%', sMessage);

	// Parse in the package download URL if it exists in the string.
	document.getElementById('update-link').href = this.opt.sUpdateNotificationLink.replace('%package%', window.ourUpdatePackage);

	// If we decide to override life into "red" mode, do it.
	if ('elkUpdateCritical' in window)
	{
		document.getElementById('update_title').style.backgroundColor = '#DD2222';
		document.getElementById('update_title').style.color = 'white';
		document.getElementById('update_message').style.backgroundColor = '#EEBBBB';
		document.getElementById('update_message').style.color = 'black';
	}
};

/**
 * Initializes the Elk_ViewVersions object with the provided options.
 *
 * @param {Object} oOptions - The options for Elk_ViewVersions.
 * @constructor
 */
function Elk_ViewVersions (oOptions = {})
{
	this.opt = oOptions;
	this.init();
}

// initialize the version checker
Elk_ViewVersions.prototype.init = function() {
	// Load this on loading of the page.
	window.viewVersionsInstanceRef = this;
	window.addEventListener('load', function() {
		window.viewVersionsInstanceRef.loadViewVersions();
	});
};

// compare a current and target version to determine if one is newer/older
Elk_ViewVersions.prototype.compareVersions = function(sCurrent, sTarget) {
	let aVersions = [],
		aParts = [],
		aCompare = [sCurrent, sTarget],
		aDevConvert = {'dev': 0, 'alpha': 1, 'beta': 2, 'rc': 3};

	for (let i = 0; i < 2; i++)
	{
		// Clean the version and extract the version parts.
		let sClean = aCompare[i].toLowerCase().replace(/ /g, '').replace(/release candidate/g, 'rc');
		aParts = sClean.match(/(\d+)(?:\.(\d+|))?(?:\.)?(\d+|)(?:(alpha|beta|rc)\.*(\d+|)(?:\.)?(\d+|))?(?:(dev))?(\d+|)/);

		// No matches?
		if (aParts === null)
		{
			return false;
		}

		// Build an array of parts.
		aVersions[i] = [
			aParts[1] > 0 ? parseInt(aParts[1]) : 0,
			aParts[2] > 0 ? parseInt(aParts[2]) : 0,
			aParts[3] > 0 ? parseInt(aParts[3]) : 0,
			typeof (aParts[4]) === 'undefined' ? 'stable' : aDevConvert[aParts[4]],
			aParts[5] > 0 ? parseInt(aParts[5]) : 0,
			aParts[6] > 0 ? parseInt(aParts[6]) : 0,
			typeof (aParts[7]) === 'undefined' ? '' : 'dev'
		];
	}

	// Loop through each category.
	for (let i = 0; i < 7; i++)
	{
		// Is there something for us to calculate?
		if (aVersions[0][i] !== aVersions[1][i])
		{
			// Dev builds are a problematic exception.
			// (stable) dev < (stable) but (unstable) dev = (unstable)
			if (i === 3)
			{
				return aVersions[0][i] < aVersions[1][i] ? !aVersions[1][6] : aVersions[0][6];
			}

			if (i === 6)
			{
				return aVersions[0][6] ? aVersions[1][3] === 'stable' : false;
			}

			// Otherwise a simple comparison.
			return aVersions[0][i] < aVersions[1][i];
		}
	}

	// They are the same!
	return false;
};

/**
 * Adds a new word container to the censored word list
 */
function addNewWord ()
{
	setOuterHTML(document.getElementById('moreCensoredWords'), '<div class="censorWords"><input type="text" name="censor_vulgar[]" size="30" class="input_text" /> <i class="icon i-chevron-circle-right"></i> <input type="text" name="censor_proper[]" size="30" class="input_text" /><' + '/div><div id="moreCensoredWords"><' + '/div>');
}

/**
 * Will enable/disable checkboxes, according to if the BBC globally set or not.
 *
 * @param {string} section id of the container
 * @param {string} disable true or false
 */
function toggleBBCDisabled (section, disable)
{
	let elems = document.getElementById(section).getElementsByTagName('*');

	for (let i = 0; i < elems.length; i++)
	{
		if (typeof (elems[i].name) === 'undefined' || (elems[i].name.substring((section.length + 1), elems[i].name.length - 2) !== 'enabledTags') || (elems[i].name.indexOf(section) !== 0))
		{
			continue;
		}

		elems[i].disabled = disable;
	}

	document.getElementById('bbc_' + section + '_select_all').disabled = disable;
}

/**
 * Keeps the input boxes display options appropriate for the options selected
 * when adding custom profile fields
 */
function updateInputBoxes ()
{
	let curType = document.getElementById('field_type').value,
		privStatus = document.getElementById('private').value,
		stdText = ['text', 'textarea', 'email', 'url', 'color', 'date'],
		stdInput = ['text', 'email', 'url', 'color', 'date'],
		stdSelect = ['select'];

	let bIsStd = (stdInput.indexOf(curType) !== -1),
		bIsText = (stdText.indexOf(curType) !== -1),
		bIsSelect = (stdSelect.indexOf(curType) !== -1);

	// Only Text like fields can see a max length input
	document.getElementById('max_length_dt').style.display = bIsText ? '' : 'none';
	document.getElementById('max_length_dd').style.display = bIsText ? '' : 'none';

	// Textareas can get a row/col definition
	document.getElementById('dimension_dt').style.display = curType === 'textarea' ? '' : 'none';
	document.getElementById('dimension_dd').style.display = curType === 'textarea' ? '' : 'none';

	// Text like fields can be styled with bbc
	document.getElementById('bbc_dt').style.display = bIsText ? '' : 'none';
	document.getElementById('bbc_dd').style.display = bIsText ? '' : 'none';

	// And given defaults
	document.getElementById('defaultval_dt').style.display = bIsText ? '' : 'none';
	document.getElementById('defaultval_dd').style.display = bIsText ? '' : 'none';

	// Selects and radio can support a list of options
	document.getElementById('options_dt').style.display = curType === 'select' || curType === 'radio' ? '' : 'none';
	document.getElementById('options_dd').style.display = curType === 'select' || curType === 'radio' ? '' : 'none';

	// Checkboxes can have a default
	document.getElementById('default_dt').style.display = curType === 'check' ? '' : 'none';
	document.getElementById('default_dd').style.display = curType === 'check' ? '' : 'none';

	// Normal input boxes can use a validation mask as well
	document.getElementById('mask_dt').style.display = bIsStd ? '' : 'none';
	document.getElementById('mask').style.display = bIsStd ? '' : 'none';

	// And text and select fields are searchable
	document.getElementById('can_search_dt').style.display = bIsText || bIsSelect ? '' : 'none';
	document.getElementById('can_search_dd').style.display = bIsText || bIsSelect ? '' : 'none';

	// Moving to a non searchable field, be sure searchable is unselected.
	if (!bIsText && !bIsSelect)
	{
		document.getElementById('can_search_dd').checked = false;
	}

	// Using regex in the mask, give them a place to supply the regex
	document.getElementById('regex_div').style.display = bIsStd && document.getElementById('mask').value === 'regex' ? '' : 'none';
	document.getElementById('display').disabled = false;

	// Cannot show this on the topic
	if (curType === 'textarea' || privStatus >= 2)
	{
		document.getElementById('display').checked = false;
		document.getElementById('display').disabled = true;
	}
}

/**
 * Used to add additional radio button options when editing a custom profile field
 */
function addOption ()
{
	setOuterHTML(document.getElementById('addopt'), '<p><input type="radio" name="default_select" value="' + startOptID + '" id="' + startOptID + '" /><input type="text" name="select_option[' + startOptID + ']" value="" class="input_text" /></p><span id="addopt"></span>');
	startOptID++;
}

/**
 * Adds another question to the registration page
 */
function addAnotherQuestion ()
{
	let placeHolder = document.getElementById('add_more_question_placeholder');

	setOuterHTML(placeHolder, add_question_template.easyReplace({
		question_last_blank: question_last_blank,
		setup_verification_add_more_answers: txt_add_another_answer
	}));

	question_last_blank++;
}

/**
 * Every question should have an answer, even if its a lie
 *
 * @param {HTMLElement} elem
 * @param {string} question_name
 */
function addAnotherAnswer (elem, question_name)
{
	setOuterHTML(elem, add_answer_template.easyReplace({
		question_last_blank: question_name,
		setup_verification_add_more_answers: txt_add_another_answer
	}));
}

/**
 * Used to add new search engines to the known list
 *
 * @param {string} txt_name
 * @param {string} txt_url
 * @param {string} txt_word_sep
 */
function addAnotherSearch (txt_name, txt_url, txt_word_sep)
{
	let placeHolder = document.getElementById('add_more_searches'),
		newDT = document.createElement('dt'),
		newInput = document.createElement('input'),
		newLabel = document.createElement('label'),
		newDD = document.createElement('dd');

	newInput.name = 'engine_name[]';
	newInput.type = 'text';
	newInput.className = 'input_text';
	newInput.size = 50;
	newInput.setAttribute('class', 'verification_question');

	// Add the label and input box to the DOM
	newLabel.textContent = txt_name + ': ';
	newLabel.appendChild(newInput);
	newDT.appendChild(newLabel);

	// Next input box
	newInput = document.createElement('input');
	newInput.name = 'engine_url[]';
	newInput.type = 'text';
	newInput.className = 'input_text';
	newInput.size = 35;
	newInput.setAttribute('class', 'input_text verification_answer');

	// Add the new label and input box
	newLabel = document.createElement('label');
	newLabel.textContent = txt_url + ': ';
	newLabel.appendChild(newInput);
	newDD.appendChild(newLabel);
	newDD.appendChild(document.createElement('br'));

	// Rinse and repeat
	newInput = document.createElement('input');
	newInput.name = 'engine_separator[]';
	newInput.type = 'text';
	newInput.className = 'input_text';
	newInput.size = 5;
	newInput.setAttribute('class', 'input_text verification_answer');

	newLabel = document.createElement('label');
	newLabel.textContent = txt_word_sep + ': ';
	newLabel.appendChild(newInput);
	newDD.appendChild(newLabel);

	placeHolder.parentNode.insertBefore(newDT, placeHolder);
	placeHolder.parentNode.insertBefore(newDD, placeHolder);
}

/**
 * Adds another news item to the list of news.
 *
 * This method duplicates the last news item and appends it to the list of
 * news.
 * It also assigns unique IDs to the duplicated elements and sets up
 * necessary event handlers for the new item.
 *
 * @return {void} This method does not return a value.
 */
function addAnotherNews ()
{
	let last = document.querySelector('#list_news_lists_last'),
		newItem = last.cloneNode(true);

	last_preview++;

	newItem.id = 'list_news_lists_' + last_preview;
	newItem.querySelector('textarea').id = 'data_' + last_preview;
	newItem.querySelector('#preview_last').id = 'preview_' + last_preview;
	newItem.querySelector('#box_preview_last').id = 'box_preview_' + last_preview;

	last.parentNode.insertBefore(newItem, last);

	newItem.style.display = newItem.style.display === 'none' ? '' : 'none';

	make_preview_btn(last_preview);
}

/**
 * Creates a preview button for a specific preview ID.
 *
 * @param {string} preview_id - The ID of the preview element.
 */
function make_preview_btn (preview_id)
{
	// Create a preview button
	const id = document.getElementById('preview_' + preview_id);
	id.textContent = txt_preview;

	// Attach a click event to the new button to fetch a preview
	id.addEventListener('click', function(e) {
		e.preventDefault();
		fetch(elk_prepareScriptUrl(elk_scripturl) + 'action=XmlPreview;api=xml', {
			method: 'POST',
			headers: {
				'X-Requested-With': 'XMLHttpRequest',
				'Content-Type': 'application/x-www-form-urlencoded',
				'Accept': 'application/xml'
			},
			body: serialize({
				item: 'newspreview',
				news: document.getElementById('data_' + preview_id).value
			}),
		})
			.then(response => {
				if (!response.ok)
				{
					throw new Error('HTTP error ' + response.status);
				}
				return response.text();
			})
			.then(text => {
				const request = new DOMParser().parseFromString(text, 'text/xml');
				const previewBox = document.getElementById('box_preview_' + preview_id);

				if (request.querySelector('error'))
				{
					previewBox.textContent = txt_news_error_no_news;
				}
				else
				{
					previewBox.innerHTML = request.documentElement.textContent;
				}
			})
			.catch(error => {
				if ('console' in window && console.error)
				{
					console.error('Error : ', error);
				}
			});
	});

	if (!id.parentElement.classList.contains('linkbutton'))
	{
		const wrapper = document.createElement('a');
		wrapper.className = 'linkbutton floatright';
		wrapper.href = '#';
		wrapper.onclick = function() { return false; };
		id.parentNode.insertBefore(wrapper, id);
		wrapper.appendChild(id);
	}
}

/**
 * Used by manage themes to show the thumbnail of the theme variant chosen
 *
 * @param {string} sVariant
 */
function changeVariant (sVariant)
{
	document.getElementById('variant_preview').src = oThumbnails[sVariant];
}

/**
 * Used in manage paid subscriptions to show the fixed duration panel or
 * the variable duration panel, based on which radio button is selected
 */
function toggleDuration ()
{
	document.getElementById('fixed_area').slideToggle(300);
	document.getElementById('flexible_area').slideToggle(300);
}

/**
 * Used when editing the search weights for results, calculates the overall total weight
 */
function calculateNewValues ()
{
	let total = 0;
	for (let i = 1; i <= 7; i++)
	{
		total += parseInt(document.getElementById('weight' + i + '_val').value);
	}

	document.getElementById('weighttotal').innerHTML = total;
	for (let i = 1; i <= 7; i++)
	{
		document.getElementById('weight' + i).innerHTML = (Math.round(1000 * parseInt(document.getElementById('weight' + i + '_val').value) / total) / 10) + '%';
	}
}

/**
 * Toggle visibility of add smile image source options
 */
function switchType ()
{
	document.getElementById('ul_settings').style.display = document.getElementById('method-existing').checked ? 'none' : 'block';
	document.getElementById('ex_settings').style.display = document.getElementById('method-upload').checked ? 'none' : 'block';
}

/**
 * Toggle visibility of smiley set should the user want different images in a set (add smiley)
 */
function swapUploads ()
{
	document.getElementById('uploadMore').style.display = document.getElementById('uploadSmiley').disabled ? 'none' : 'block';
	document.getElementById('uploadSmiley').disabled = !document.getElementById('uploadSmiley').disabled;
}

/**
 * Close the options that should not be visible for adding a smiley
 *
 * @param {string} element
 */
function selectMethod (element)
{
	document.getElementById('method-existing').checked = element !== 'upload';
	document.getElementById('method-upload').checked = element === 'upload';
}

/**
 * Updates the smiley preview to show the current one chosen
 */
function updatePreview ()
{
	let currentImage = document.getElementById('preview'),
		selected = document.getElementById('set'),
		ext;

	ext = selected.options[selected.selectedIndex].getAttribute('data-ext');

	currentImage.src = elk_smiley_url + '/' + document.forms.smileyForm.set.value + '/' + document.forms.smileyForm.smiley_filename.value + '.' + ext;
	currentImage.alt = '☒';
}

/**
 * Called when making changes via Edit Smileys checkboxes.  Submits the form
 * or asks for validation on delete.
 *
 * @param action
 * @returns {boolean}
 */
function makeChanges (action)
{
	// No selection made
	if (action === '-1')
	{
		return false;
	}

	if (action === 'delete')
	{
		if (confirm(txt_remove))
		{
			document.forms.smileyForm.submit();
		}
	}
	else
	{
		document.forms.smileyForm.submit();
	}

	return true;
}

/**
 * Called when swapping the smiley set in Edit Smileys.  Will swap the images to the
 * ones in the chosen set.
 *
 * @param {string} newSet
 */
function changeSet (newSet)
{
	let currentImage,
		i,
		n,
		ext,
		knownSmileys = [],
		selected = document.getElementById('set');

	if (knownSmileys.length === 0)
	{
		for (i = 0, n = document.images.length; i < n; i++)
		{
			if (document.images[i].id.substring(0, 6) === 'smiley')
			{
				knownSmileys[knownSmileys.length] = document.images[i].id.substring(6);
			}
		}
	}

	ext = selected.options[selected.selectedIndex].getAttribute('data-ext');

	for (i = 0; i < knownSmileys.length; i++)
	{
		currentImage = document.getElementById('smiley' + knownSmileys[i]);

		currentImage.src = elk_smiley_url + '/' + newSet + '/' + document.forms.smileyForm['smileys[' + knownSmileys[i] + '][filename]'].value + '.' + ext;
	}
}

/**
 * Used in package manager to swap the visibility of database changes
 */
function swap_database_changes ()
{
	db_vis = !db_vis;
	database_changes_area.style.display = db_vis ? '' : 'none';

	return false;
}

/**
 * Test the given form credentials to test if an FTP connection can be made
 */
function testFTP ()
{
	ajax_indicator(true);

	// What we need to post.
	let oPostData = {
		0: 'ftp_server',
		1: 'ftp_port',
		2: 'ftp_username',
		3: 'ftp_password',
		4: 'ftp_path'
	};

	let sPostData = '';
	for (let i = 0; i < 5; i++)
	{
		sPostData = sPostData + (sPostData.length === 0 ? '' : '&') + oPostData[i] + '=' + document.getElementById(oPostData[i]).value.php_urlencode();
	}

	// Post the data out.
	sendXMLDocument(elk_prepareScriptUrl(elk_scripturl) + 'action=admin;area=packages;sa=ftptest;api=xml;' + elk_session_var + '=' + elk_session_id, sPostData, testFTPResults);
}

/**
 * Generate a "test ftp" button.
 */
function generateFTPTest ()
{
	// Don't ever call this twice!
	if (generatedButton)
	{
		return false;
	}

	generatedButton = true;

	// No XML?
	if (!document.getElementById('test_ftp_placeholder') && !document.getElementById('test_ftp_placeholder_full'))
	{
		return false;
	}

	// create our test button to call testFTP on click
	let ftpTest = document.createElement('input');
	ftpTest.type = 'button';
	ftpTest.className = 'submit';
	ftpTest.onclick = testFTP;

	// Set the button value based on which form we are on
	if (document.getElementById('test_ftp_placeholder'))
	{
		ftpTest.value = package_ftp_test;
		document.getElementById('test_ftp_placeholder').appendChild(ftpTest);
	}
	else
	{
		ftpTest.value = package_ftp_test_connection;
		document.getElementById('test_ftp_placeholder_full').appendChild(ftpTest);
	}

	return true;
}

/**
 * Callback function of the testFTP function
 *
 * @param {type} oXMLDoc
 */
function testFTPResults (oXMLDoc)
{
	ajax_indicator(false);

	// This assumes it went wrong!
	let wasSuccess = false,
		message = package_ftp_test_failed,
		results = oXMLDoc.getElementsByTagName('results')[0].getElementsByTagName('result');

	// Results show we were a success
	if (results.length > 0)
	{
		if (parseInt(results[0].getAttribute('success')) === 1)
		{
			wasSuccess = true;
		}

		message = results[0].firstChild.nodeValue;
	}

	// place the informative box on screen so the user knows if things went well or poorly
	document.getElementById('ftp_error_div').style.display = '';
	document.getElementById('ftp_error_div').className = wasSuccess ? 'successbox' : 'errorbox';
	document.getElementById('ftp_error_message').innerHTML = message;
}

/**
 * Used when edit the boards and groups access to them
 *
 * @param {type} operation
 * @param {type} brd_list
 */
function select_in_category (operation, brd_list)
{
	for (let brd in brd_list)
	{
		if (!brd_list.hasOwnProperty(brd))
		{
			continue;
		}

		document.getElementById(operation + '_brd' + brd_list[brd]).checked = true;
	}
}

/**
 * Server Settings > Caching, toggles input fields on/off as appropriate for
 * a given cache engine selection
 */
function showCache ()
{
	let cacheAccelerator = document.getElementById('cache_accelerator');

	// Hide all the settings
	let allOptions = cacheAccelerator.querySelectorAll('option');
	allOptions.forEach(function(option) {
		let settingsElementsToHide = document.querySelectorAll('[id^=' + option.value + '_]');

		settingsElementsToHide.forEach(function(element) {
			element.style.display = 'none';
		});
	});

	// Show the settings of the selected engine
	let selectedOptionVal = cacheAccelerator.value,
		settingsElementsToShow = document.querySelectorAll('[id^=' + selectedOptionVal + '_]');

	settingsElementsToShow.forEach(function(element) {
		element.style.display = 'block';
	});
}

/**
 * Server Settings > Caching, toggles input fields on/off as appropriate for
 * a given cache engine selection
 */
function toggleCache ()
{
	let memcache = document.getElementById('cache_memcached').parentNode,
		cachedir = document.getElementById('cachedir').parentNode;

	// Show the memcache server box only if memcache has been selected
	if (cache_type.value.substring(0, 8) === 'memcache')
	{
		memcache.slideDown();
		memcache.previousElementSibling.slideDown(100);
	}
	else
	{
		memcache.slideUp();
		memcache.previousElementSibling.slideUp(100);
	}

	// don't show the directory if its not filebased
	if (cache_type.value === 'filebased')
	{
		cachedir.slideDown();
		cachedir.previousElementSibling.slideDown(100);
	}
	else
	{
		cachedir.slideUp(100);
		cachedir.previousElementSibling.slideUp(100);
	}
}

/**
 * Hides local / subdomain cookie options in the ACP based on selected choices
 * area=serversettings;sa=cookie
 */
function hideGlobalCookies ()
{
	let bUseLocal = document.getElementById('localCookies').checked,
		bUseGlobal = !bUseLocal && document.getElementById('globalCookies').checked;

	// Show/Hide the areas based on what they have chosen
	if (bUseLocal)
	{
		document.getElementById('setting_globalCookies').parentNode.slideUp(100);
		document.getElementById('globalCookies').parentNode.slideUp(100);
	}
	else
	{
		document.getElementById('setting_globalCookies').parentNode.slideDown(100);
		document.getElementById('globalCookies').parentNode.slideDown(100);
	}

	// Global selected means we need to reveal the domain input box
	if (bUseGlobal)
	{
		document.getElementById('setting_globalCookiesDomain').parentNode.slideDown(100);
		document.getElementById('globalCookiesDomain').parentNode.slideDown(100);
	}
	else
	{
		document.getElementById('setting_globalCookiesDomain').parentNode.slideUp(100);
		document.getElementById('globalCookiesDomain').parentNode.slideUp(100);
	}
}

/**
 * Attachments Settings
 */
function toggleSubDir ()
{
	let auto_attach = document.getElementById('automanage_attachments'),
		use_sub_dir = document.getElementById('use_subdirectories_for_attachments'),
		dir_elem = document.getElementById('basedirectory_for_attachments'),
		setting_use_sub_dir = document.getElementById('setting_use_subdirectories_for_attachments'),
		setting_dir_elem = document.getElementById('setting_basedirectory_for_attachments');

	use_sub_dir.disabled = !Boolean(auto_attach.selectedIndex);
	if (use_sub_dir.disabled)
	{
		use_sub_dir.slideUp();
		setting_use_sub_dir.parentNode.slideUp();

		dir_elem.slideUp();
		setting_dir_elem.parentNode.slideUp();
	}
	else
	{
		use_sub_dir.slideDown();
		setting_use_sub_dir.parentNode.slideDown();

		dir_elem.slideDown();
		setting_dir_elem.parentNode.slideDown();
	}

	toggleBaseDir();
}

/**
 * Called by toggleSubDir as part of manage attachments
 */
function toggleBaseDir ()
{
	let auto_attach = document.getElementById('automanage_attachments'),
		sub_dir = document.getElementById('use_subdirectories_for_attachments'),
		dir_elem = document.getElementById('basedirectory_for_attachments');

	if (auto_attach.selectedIndex === 0)
	{
		dir_elem.disabled = 1;
	}
	else
	{
		dir_elem.disabled = !sub_dir.checked;
	}
}

/**
 * Called from purgeinactive users maintenance task, used to show or hide
 * the membergroup list.  If collapsed will select all the member groups if expanded
 * unselect them so the user can choose.
 */
function swapMembers ()
{
	let membersForm = document.getElementById('membersForm');
	membersSwap = !membersSwap;
	document.getElementById('membersIcon').src = elk_images_url + (membersSwap ? '/selected_open.png' : '/selected.png');
	document.getElementById('membersText').innerHTML = membersSwap ? maintain_members_choose : maintain_members_all;

	// Check or uncheck them all based on if we are expanding or collasping the area
	for (let i = 0; i < membersForm.length; i++)
	{
		if (membersForm.elements[i].type.toLowerCase() === 'checkbox')
		{
			membersForm.elements[i].checked = !membersSwap;
		}
	}

	return false;
}

/**
 * Called from reattribute member posts to build the confirmation message for the action
 * Keeps the action button (reattribute) disabled until all necessary fields have been filled
 */
function checkAttributeValidity ()
{
	origText = reattribute_confirm;
	valid = true;

	// Do all the fields!
	if (!document.getElementById('to').value)
	{
		valid = false;
	}

	warningMessage = origText.replace(/%member_to%/, document.getElementById('to').value);

	// Using email address to find the member
	if (document.getElementById('type_email').checked)
	{
		if (!document.getElementById('from_email').value)
		{
			valid = false;
		}

		warningMessage = warningMessage.replace(/%type%/, '', reattribute_confirm_email).replace(/%find%/, document.getElementById('from_email').value);
	}
	// Or the user name
	else
	{
		if (!document.getElementById('from_name').value)
		{
			valid = false;
		}

		warningMessage = warningMessage.replace(/%type%/, '', reattribute_confirm_username).replace(/%find%/, document.getElementById('from_name').value);
	}

	document.getElementById('do_attribute').disabled = !valid;

	// Keep checking for a valid form so we can activate the submit button
	setTimeout(function() {
		checkAttributeValidity();
	}, 500);

	return valid;
}

/**
 * Enable/disable fields when transferring attachments
 *
 * @returns {undefined}
 */
function transferAttachOptions ()
{
	let autoSelect = document.getElementById('auto'),
		autoValue = parseInt(autoSelect.options[autoSelect.selectedIndex].value, 10),
		toSelect = document.getElementById('to'),
		toValue = parseInt(toSelect.options[toSelect.selectedIndex].value, 10);

	toSelect.disabled = autoValue !== 0;
	autoSelect.disabled = toValue !== 0;
}

/**
 * Updates the move confirmation text so its descriptive for the current items
 * being moved.
 *
 * @param {string} confirmText
 */
function confirmMoveTopics (confirmText)
{
	let from = document.getElementById('id_board_from'),
		to = document.getElementById('id_board_to');

	if (from.options[from.selectedIndex].disabled || from.options[to.selectedIndex].disabled)
	{
		return false;
	}

	return confirm(confirmText.replace(/%board_from%/, from.options[from.selectedIndex].text.replace(/^\u2003+\u27A4/, '')).replace(/%board_to%/, to.options[to.selectedIndex].text.replace(/^\u2003+\u27A4/, '')));
}

/**
 * Hide the search methods area if using sphinx(ql) search
 */
function showhideSearchMethod ()
{
	let searchSphinxQl = document.getElementById('search_index_sphinxql').checked,
		searchSphinx = document.getElementById('search_index_sphinx').checked,
		searchhide = searchSphinxQl || searchSphinx,
		searchMethod = document.getElementById('search_method');

	if (searchhide)
	{
		searchMethod.slideUp();
	}
	else
	{
		searchMethod.slideDown();
	}
}

/**
 * Used in manageMembergroups to enable disable form elements based on allowable choices
 * If post based group is selected, it will disable moderation selection, visibility, group description
 * and enable post count input box
 *
 * @param {boolean} isChecked
 */
function swapPostGroup (isChecked)
{
	let min_posts_text = document.getElementById('min_posts_text'),
		group_desc_text = document.getElementById('group_desc_text'),
		group_hidden_text = document.getElementById('group_hidden_text'),
		group_moderators_text = document.getElementById('group_moderators_text');

	document.forms.groupForm.min_posts.disabled = !isChecked;
	min_posts_text.style.color = isChecked ? '' : '#888888';

	document.forms.groupForm.group_desc_input.disabled = isChecked;
	group_desc_text.style.color = isChecked ? '#888888' : '';

	document.forms.groupForm.group_hidden_input.disabled = isChecked;
	group_hidden_text.style.color = isChecked ? '#888888' : '';

	document.forms.groupForm.group_moderators.disabled = isChecked;
	group_moderators_text.style.color = isChecked ? '#888888' : '';

	// Disable the moderator autosuggest box as well
	if (typeof (oModeratorSuggest) !== 'undefined')
	{
		oModeratorSuggest.oTextHandle.disabled = !!isChecked;
	}
}

/**
 * Handles the AJAX preview of the warning templates
 */
function ajax_getTemplatePreview ()
{
	let thisDocument = document,
		thisBody = document.body,
		templateTitle = thisDocument.getElementById('template_title'),
		templateBody = thisDocument.getElementById('template_body'),
		user = thisDocument.querySelector('input[name="u"]');

	fetch(elk_prepareScriptUrl(elk_scripturl) + 'action=XmlPreview;api=xml', {
		method: 'POST',
		body: serialize({
			item: 'warning_preview',
			title: templateTitle ? templateTitle.value : '',
			body: templateBody ? templateBody.value : '',
			user: user ? user.value : ''
		}),
		headers: {
			'X-Requested-With': 'XMLHttpRequest',
			'Content-Type': 'application/x-www-form-urlencoded',
			'Accept': 'application/xml'
		}
	})
		.then(response => response.text())
		.then(data => {
			let parser = new DOMParser(),
				xmlDoc = parser.parseFromString(data, 'text/xml');

			thisDocument.getElementById('box_preview').style.display = 'block';
			thisDocument.getElementById('template_preview').innerHTML = xmlDoc.getElementsByTagName('body')[0].childNodes[0].nodeValue;

			let _errors = thisDocument.querySelector('#errors'),
				errors = xmlDoc.getElementsByTagName('error');
			if (errors[0] && errors[0].childNodes[0])
			{
				_errors.style.display = 'block';
				let errorsHtml = '';
				let errors = xmlDoc.getElementsByTagName('error');

				Array.from(errors).forEach((error) => {
					errorsHtml += error.childNodes[0].nodeValue + '<br />';
				});

				thisDocument.getElementById('error_list').innerHTML = errorsHtml;
				thisBody.scrollTo({top: _errors.offsetTop, behavior: 'smooth'});
			}
			else
			{
				_errors.style.display = 'none';
				thisDocument.getElementById('error_list').innerHTML = '';
				thisBody.scrollTo({top: thisDocument.getElementById('box_preview').offsetTop, behavior: 'smooth'});
			}

			return false;
		})
		.catch(error => {
			if ('console' in window && console.error)
			{
				console.error('Error : ', error);
			}
		});

	return false;
}

/**
 * Sets up all the js events for edit and save board-specific permission profiles
 */
function initEditProfileBoards ()
{
	// Selecting Edit All will open the board permission selection for every board.
	document.querySelectorAll('.edit_all_board_profiles').forEach(function(element) {
		element.addEventListener('click', function(e) {
			e.preventDefault();
			document.querySelectorAll('.edit_board').forEach(function(innerElement) {
				innerElement.click();
			});
		});
	});

	document.querySelectorAll('.edit_board').forEach(function(element) {
		element.style.display = 'block';
		element.addEventListener('click', function(e) {
			let icon = this,
				board_id = icon.getAttribute('data-boardid'),
				board_profile = Number(icon.getAttribute('data-boardprofile')),
				target = document.getElementById('edit_board_' + board_id),
				select = document.createElement('select');

			select.setAttribute('name', 'boardprofile[' + board_id + ']');
			select.addEventListener('change', function() {
				let selectedOption = this.options[this.selectedIndex];

				if (Number(selectedOption.value) === board_profile)
				{
					icon.children[0].classList.add('i-check');
					icon.children[0].classList.remove('i-modify', 'i-warn');
					icon.classList.add('nochanges');
					icon.classList.remove('changed');
				}
				else
				{
					icon.children[0].classList.add('i-warn');
					icon.children[0].classList.remove('i-modify', 'i-check');
					icon.classList.add('changed');
					icon.classList.remove('nochanges');
				}
			});

			e.preventDefault();

			permission_profiles.forEach(function(profile) {
				let option = document.createElement('option');
				option.value = Number(profile.id);
				option.text = profile.name;

				if (profile.id === board_profile)
				{
					option.selected = true;
				}
				select.appendChild(option);
			});

			if (target)
			{
				target.parentNode.replaceChild(select, target);
				select.dispatchEvent(new Event('change'));

				document.querySelector('.edit_all_board_profiles').outerHTML = '<input type="submit" name="save_changes" value="' + txt_save + '">';

				icon.removeEventListener('click', handleIconClick);
				icon.addEventListener('click', handleIconClick);
			}
		});
	});

	function handleIconClick (e)
	{
		e.preventDefault();
		if (this.classList.contains('changed'))
		{
			document.querySelector('input[name="save_changes"]').click();
		}
	}
}

/**
 * Creates the image and attaches the event to convert the name of the permission
 * profile into an input to change its name and back.
 *
 * It also removes the "Rename all" and "Remove Selected" buttons
 * and the "Delete" column for consistency
 */
function initEditPermissionProfiles ()
{
	// We need a variable to be sure we are going to create only 1 cancel button
	let run_once = false,
		$cancel = null;

	document.querySelectorAll('.rename_profile').forEach((profile) => {
		const newButton = document.createElement('a');
		newButton.className = 'js-ed edit_board';
		newButton.href = '#';

		const newIcon = document.createElement('i');
		newIcon.className = 'icon icon-small i-modify';

		newButton.appendChild(newIcon);
		newButton.addEventListener('click', function(ev) {
			ev.preventDefault();

			// If we have already created the cancel let's skip it
			if (!run_once)
			{
				run_once = true;

				// Create a cancel button that restores the UI on click
				$cancel = document.createElement('a');
				$cancel.className = 'js-ed-rm linkbutton';
				$cancel.href = '#';
				$cancel.textContent = ajax_notification_cancel_text;
				$cancel.addEventListener('click', function(ev) {
					ev.preventDefault();

					document.querySelectorAll('.js-ed').forEach((el) => { el.style.removeProperty('display'); });
					document.querySelectorAll('.js-ed-rm').forEach((el) => { el.remove(); });
					document.getElementById('rename').value = txt_permissions_profile_rename;

					run_once = false;
				});
			}

			const input = document.createElement('input');
			input.type = 'text';
			input.className = 'js-ed-rm input_text';
			input.name = 'rename_profile[' + profile.dataset.pid + ']';
			input.value = profile.textContent;

			profile.after(input);
			profile.classList.add('js-ed');
			profile.style.display = 'none';

			// These will have to pop back hitting cancel, so let's prepare them
			document.getElementById('rename').classList.add('js-ed');
			document.getElementById('rename').value = txt_permissions_commit;
			document.getElementById('rename').before($cancel);

			document.getElementById('delete').classList.add('js-ed');
			document.getElementById('delete').style.display = 'none';

			document.querySelectorAll('.perm_profile_delete').forEach((element) => {
				element.classList.add('js-ed');
				element.style.display = 'none';
			});

			this.style.display = 'none';
		});

		profile.after(newButton);
	});
}

/**
 * Attach the AJAX handling of things to the various themes to remove
 * Used in ManageThemes (template_list_themes)
 *
 * @todo did not test this after refactor from jquery ... did not have any themes installed!
 */
function initDeleteThemes ()
{
	document.querySelectorAll('.delete_theme').forEach(item => {
		item.addEventListener('click', function(event) {
			event.preventDefault();

			const theme_id = this.dataset.theme_id;
			const pattern = new RegExp(elk_session_var + '=' + elk_session_id + ';(.*)$');

			let base_url = this.getAttribute('href'),
				tokens = pattern.exec(base_url)[1].split('='),
				token = tokens[1],
				token_var = tokens[0];

			if (confirm(txt_theme_remove_confirm))
			{
				fetch(base_url + ';api=xml', {
					method: 'GET',
					headers: {
						'X-Requested-With': 'XMLHttpRequest',
						'Accept': 'application/xml'
					}
				})
					.then(response => {
						if (!response.ok)
						{
							throw new Error('HTTP error ' + response.status);
						}
						return response.text();
					})
					.then(responseText => {
						let parser = new DOMParser(),
							xmlDoc = parser.parseFromString(responseText, 'text/xml');

						if (xmlDoc.getElementsByTagName('error').length === 0)
						{
							let new_token = xmlDoc.getElementsByTagName('token')[0].childNodes[0].nodeValue,
								new_token_var = xmlDoc.getElementsByTagName('token_var')[0].childNodes[0].nodeValue;

							document.querySelector('.theme_' + theme_id).style.display = 'none';
							document.querySelectorAll('.delete_theme').forEach(item => {
								let href = item.getAttribute('href');
								item.setAttribute('href', href.replace(token_var + '=' + token, new_token_var + '=' + new_token));
							});
						}
						// @todo Improve error handling
						else
						{
							let error = xmlDoc.getElementsByTagName('text')[0].childNodes[0].nodeValue;
							throw new Error('HTTP error ' + error);
						}
					})
					.catch(error => {
						ajax_indicator(false);
						window.location = base_url;
					})
					.finally(() => {
						// Turn off the indicator
						ajax_indicator(false);
					});
			}
		});
	});
}

/**
 * Callback (onBeforeUpdate) used by the AutoSuggest, used when adding new bans
 *
 * @param {object} oAutoSuggest
 */
function onUpdateName (oAutoSuggest)
{
	document.getElementById('user_check').checked = true;
	return true;
}

/**
 * Validates that the ban form is filled out properly before submitting
 * Used when editing bans
 *
 * @param {object} aForm this form object to check
 */
function confirmBan (aForm)
{
	if (aForm.ban_name.value === '')
	{
		alert(txt_ban_name_empty);
		return false;
	}

	if (aForm.partial_ban.checked && !(aForm.cannot_post.checked || aForm.cannot_register.checked || aForm.cannot_login.checked))
	{
		alert(txt_ban_restriction_empty);
		return false;
	}

	return true;
}

// Enable/disable some fields when working with bans.
const fUpdateStatus = function() {
	document.getElementById('expire_date').disabled = !document.getElementById('expires_one_day').checked;
	document.getElementById('cannot_post').disabled = document.getElementById('full_ban').checked;
	document.getElementById('cannot_register').disabled = document.getElementById('full_ban').checked;
	document.getElementById('cannot_login').disabled = document.getElementById('full_ban').checked;
};

/**
 * Used when setting up subscriptions, used to toggle the currency code divs
 * based on which currencies are chosen.
 */
function toggleCurrencyOther ()
{
	let otherOn = document.getElementById('paid_currency').value === 'other',
		currencydd = document.getElementById('custom_currency_code_div_dd');

	if (otherOn)
	{
		document.getElementById('custom_currency_code_div').style.display = '';
		document.getElementById('custom_currency_symbol_div').style.display = '';

		if (currencydd)
		{
			document.getElementById('custom_currency_code_div_dd').style.display = '';
			document.getElementById('custom_currency_symbol_div_dd').style.display = '';
		}
	}
	else
	{
		document.getElementById('custom_currency_code_div').style.display = 'none';
		document.getElementById('custom_currency_symbol_div').style.display = 'none';

		if (currencydd)
		{
			document.getElementById('custom_currency_symbol_div_dd').style.display = 'none';
			document.getElementById('custom_currency_code_div_dd').style.display = 'none';
		}
	}
}

/**
 * Used to ajax-ively preview the templates of bounced emails (template_bounce_template)
 */
function ajax_getEmailTemplatePreview ()
{
	fetch(elk_prepareScriptUrl(elk_scripturl) + 'action=XmlPreview;api=xml', {
		method: 'POST',
		headers: {
			'X-Requested-With': 'XMLHttpRequest',
			'Content-Type': 'application/x-www-form-urlencoded',
			'Accept': 'application/xml'
		},
		body: serialize({
			item: 'bounce_preview',
			title: document.getElementById('template_title').value,
			body: document.getElementById('template_body').value
		})
	})
		.then(response => {
			if (!response.ok)
			{
				throw new Error('HTTP error ' + response.status);
			}
			return response.text();
		})
		.then(str => (new window.DOMParser()).parseFromString(str, 'text/xml'))
		.then(data => {
			document.getElementById('preview_section').style.display = 'block';
			document.getElementById('preview_body').innerHTML = data.getElementsByTagName('body')[0].textContent;
			document.getElementById('preview_subject').innerHTML = data.getElementsByTagName('subject')[0].textContent;

			const errorsElem = data.getElementsByTagName('error');
			if (errorsElem.length)
			{
				let errors_html = '';

				document.getElementById('errors').style.display = '';
				document.getElementById('errors').className = parseInt(data.getElementsByTagName('errors')[0].getAttribute('serious')) === 0 ? 'warningbox' : 'errorbox';
				for (let i = 0; i < errorsElem.length; i++)
				{
					errors_html += errorsElem[i].textContent + '<br />';
				}
				document.getElementById('error_list').innerHTML = errors_html;
			}
			else
			{
				document.getElementById('errors').style.display = 'none';
				document.getElementById('error_list').innerHTML = '';
			}
			document.documentElement.scrollTo({
				top: document.getElementById('preview_section').offsetTop,
				behavior: 'smooth'
			});

		})
		.catch((error) => {
			if ('console' in window && console.error)
			{
				console.error('Error : ', error);
			}
		});

	return false;
}

/**
 * Used to ajax-ively preview a word censor
 * Does no checking, it either gets a result or does nothing
 */
function ajax_getCensorPreview ()
{
	fetch(elk_prepareScriptUrl(elk_scripturl) + 'action=admin;area=postsettings;sa=censor;api=json', {
		method: 'POST',
		headers: {
			'X-Requested-With': 'XMLHttpRequest',
			'Content-Type': 'application/x-www-form-urlencoded',
			'Accept': 'application/json'
		},
		body: serialize({
			censortest: document.getElementById('censortest').value
		})
	})
		.then(response => {
			if (!response.ok)
			{
				throw new Error('HTTP error ' + response.status);
			}
			return response.json();
		})
		.then(request => {
			// Show the censored text section, populated with the response
			document.getElementById('censor_result').style.display = 'block';
			document.getElementById('censor_result').innerHTML = request.censor;

			// Update the token
			document.getElementById('token').setAttribute('name', request.token_val);
			document.getElementById('token').value = request.token;

			// Clear the box
			document.getElementById('censortest').value = '';
		})
		.catch((error) => {
			if ('console' in window && console.error)
			{
				console.error('Error : ', error);
			}
		});

	return false;
}

/**
 * Used to show/hide sub options for the various notifications
 * action=admin;area=featuresettings;sa=mention
 */
function prepareNotificationOptions () {
	let headers = Array.from(document.querySelectorAll('input[id^=\'notifications\'][id$=\'[enable]\']'));

	headers.forEach(function(header) {
		header.addEventListener('change', function() {
			let top = this.closest('dl'),
				hparent = this.parentNode;

			// Enabling the notification, slide it into view and make sure the checkboxes are enabled
			if (this.checked)
			{
				Array.from(top.querySelectorAll('dt:not(:first-child)')).forEach(function(el) {
					el.slideDown();
				});

				Array.from(top.querySelectorAll('dd:not(:nth-child(2))')).forEach(function(el) {
					el.slideDown();
					Array.from(el.querySelectorAll('input')).forEach(function(inputEl) {
						inputEl.disabled = false;
					});
				});
			}
			// Notification type is not enabled, close that area, disable checkboxes
			else
			{
				Array.from(top.querySelectorAll('dt:not(:first-child)')).forEach(function(el) {
					el.slideUp();
				});

				Array.from(top.querySelectorAll('dd:not(:nth-child(2))')).forEach(function(el) {
					el.slideUp();
					Array.from(el.querySelectorAll('input')).forEach(function(inputEl) {
						inputEl.disabled = true;
					});
				});
			}

			hparent.style.display = 'block';
			hparent.previousElementSibling.style.display = 'block';
		});

		let event = new Event('change', {'bubbles': true, 'cancelable': true});
		header.dispatchEvent(event);
	});
}

/**
 * Ajax function to clear CSS and JS hives.  Called from action=admin;area=featuresettings;sa=basic
 */
function cleanHives (event)
{
	let infoBar = new ElkInfoBar('bar_clean_hives');

	event.preventDefault();
	fetch(elk_prepareScriptUrl(elk_scripturl) + 'action=admin;area=featuresettings;sa=basic;api=json', {
		method: 'POST',
		headers: {
			'X-Requested-With': 'XMLHttpRequest',
			'Content-Type': 'application/x-www-form-urlencoded',
			'Accept': 'application/json'
		},
		body: serialize({cleanhives: true})
	})
		.then(response => {
			if (!response.ok)
			{
				throw new Error('HTTP error ' + response.status);
			}
			return response.json();
		})
		.then(request => {
			infoBar.changeText(request.response);
			if (request.success === true)
			{
				infoBar.isSuccess();
			}
			else
			{
				infoBar.isError();
			}
			infoBar.showBar();
		})
		.catch(error => {
			infoBar.isError();
			infoBar.changeText(txt_invalid_response);
			infoBar.showBar();
		});

	return false;
}

/**
 * Enable / disable "core" features of the software. Called from action=admin;area=corefeatures
 */
function coreFeatures ()
{
	if (document.getElementById('core_features') === null)
	{
		return;
	}

	// Hide the standard form elements (checkboxes, submit button), this is all ajax now
	document.querySelectorAll('.core_features_hide').forEach((element) => {
		element.classList.add('hide');
	});
	document.getElementById('core_features_submit').parentElement.classList.add('hide');

	token_name = token_name || document.getElementById('core_features_token').getAttribute('name');
	token_value = token_value || document.getElementById('core_features_token').getAttribute('value');

	// Attach our action to the core features power button image
	document.querySelectorAll('.core_features_img').forEach((element) => {
		element.style.display = 'block';
		element.style.cursor = 'pointer';

		let sImageText = element.classList.contains('i-switch-on') ? feature_on_text : feature_off_text;
		element.setAttribute('title', sImageText);
		element.setAttribute('alt', sImageText);

		// Clicked on the on/off image
		element.addEventListener('click', function() {
			let cc = this,
				cf = this.getAttribute('id').substring(7),
				new_state = !document.getElementById('feature_' + cf).checked,
				ajax_infobar = new ElkInfoBar('core_features_bar', {error_class: 'errorbox', success_class: 'successbox'});

			// Set the form checkbox to the new state
			document.getElementById('feature_' + cf).checked = new_state;

			// Prepare the form data to send in the request
			let data = new FormData();
			data.append('save', 'save');
			data.append('feature_id', cf);
			data.append(document.getElementById('core_features_session').getAttribute('name'), document.getElementById('core_features_session').value);
			data.append(token_name, token_value);
			document.querySelectorAll('.core_features_status_box').forEach(box => {
				data.append(box.getAttribute('name'), box.checked ? '1' : '0');
			});

			// Make the on/off request via ajax
			fetch(elk_prepareScriptUrl(elk_scripturl) + 'action=xmlhttp;sa=corefeatures;api=xml', {
				method: 'POST',
				body: serialize(data),
				headers: {
					'X-Requested-With': 'XMLHttpRequest',
					'Content-Type': 'application/x-www-form-urlencoded',
					'Accept': 'application/xml'
				}
			})
				.then(response => {
					if (!response.ok)
					{
						throw new Error('Network response was not ok');
					}
					return response.text();
				})
				.then(data => {
					const xmlDoc = new window.DOMParser().parseFromString(data, 'text/xml');

					if (xmlDoc.getElementsByTagName('errors')[0].getElementsByTagName('error').length > 0)
					{
						ajax_infobar.isError();
						ajax_infobar.changeText(xmlDoc.getElementsByTagName('errors')[0].getElementsByTagName('error')[0].textContent);
					}
					else if (xmlDoc.getElementsByTagName('elk').length > 0)
					{
						// Enable to disable the link to the feature
						document.getElementById('feature_link_' + cf).innerHTML = xmlDoc.getElementsByTagName('corefeatures')[0].getElementsByTagName('corefeature')[0].textContent;

						// Toggle the switch and its hover text
						cc.classList.toggle('i-switch-on');
						cc.classList.toggle('i-switch-off');
						cc.setAttribute('title', new_state ? feature_on_text : feature_off_text);
						cc.setAttribute('alt', new_state ? feature_on_text : feature_off_text);

						token_name = xmlDoc.getElementsByTagName('tokens')[0].querySelector('[type="token"]').textContent;
						token_value = xmlDoc.getElementsByTagName('tokens')[0].querySelector('[type="token_var"]').textContent;

						let message = xmlDoc.getElementsByTagName('messages')[0].getElementsByTagName('message')[0].textContent;
						ajax_infobar.changeText(message);
					}
					else
					{
						ajax_infobar.isError();
						ajax_infobar.changeText(core_settings_generic_error);
					}
				})
				.catch(error => {
					ajax_infobar.changeText('Fetch Error :-S', error);
				})
				.finally(() => {
					ajax_infobar.showBar();
				});
		});
	});
}

function confirmAgreement (text)
{
	let checkbox = document.getElementById('checkboxAcceptAgreement');

	if (checkbox.checked)
	{
		return confirm(text);
	}

	return true;
}

/**
 * Add a new dt/dd pair above a parent selector
 *
 * - Called most often as a callback option in config options
 * - If oData is supplied, will create a select list, populated with that data
 * otherwise a standard input box.
 *
 * @param {string} parent id of the parent "add more button: we will place this before
 * @param {object} oDtName object of dt element options (type, class, size)
 * @param {object} oDdName object of the dd element options (type, class size)
 * @param {object} [oData] optional select box object, 1:{id:value,name:display name}, ...
 */
function addAnotherOption (parent, oDtName, oDdName, oData)
{
	// Some defaults to use if none are passed
	oDtName.type = oDtName.type || 'text';
	oDtName['class'] = oDtName['class'] || 'input_text';
	oDtName.size = oDtName.size || '20';

	oDdName.type = oDdName.type || 'text';
	oDdName['class'] = oDdName['class'] || 'input_text';
	oDdName.size = oDdName.size || '20';
	oData = oData || '';

	// Our new <dt> element
	let newDT = document.createElement('dt'),
		newInput = document.createElement('input');

	newInput.name = oDtName.name;
	newInput.type = oDtName.type;
	newInput.setAttribute('class', oDtName['class']);
	newInput.size = oDtName.size;
	newDT.appendChild(newInput);

	// And its matching <dd>
	let newDD = document.createElement('dd');

	// If we have data for this field make it a select
	if (oData === '')
	{
		newInput = document.createElement('input');
	}
	else
	{
		newInput = document.createElement('select');
	}

	newInput.name = oDdName.name;
	newInput.type = oDdName.type;
	newInput.size = oDdName.size;
	newInput.setAttribute('class', oDdName['class']);
	newDD.appendChild(newInput);

	// If its a select box we add in the options
	if (oData !== '')
	{
		// The options are children of the newInput select box
		let opt,
			key,
			obj;

		for (key in oData)
		{
			obj = oData[key];
			opt = document.createElement('option');
			opt.name = 'option';
			opt.value = obj.id;
			opt.innerHTML = obj.name;
			newInput.appendChild(opt);
		}
	}

	// Place the new dt/dd pair before our parent
	let placeHolder = document.getElementById(parent);
	placeHolder.parentNode.insertBefore(newDT, placeHolder);
	placeHolder.parentNode.insertBefore(newDD, placeHolder);
}

/**
 * Drag and drop to reorder ID's via UI Sortable
 *
 * @param {object} $
 */
(function($) {
	'use strict';
	$.fn.elkSortable = function(oInstanceSettings) {
		$.fn.elkSortable.oDefaultsSettings = {
			opacity: 0.7,
			cursor: 'move',
			axis: 'y',
			scroll: true,
			containment: 'parent',
			delay: 150,
			handle: '', // Restricts sort start click to the specified element, like category_header
			href: '', // If an error occurs redirect here
			tolerance: 'intersect', // mode to use for testing whether the item is hovering over another item.
			setorder: 'serialize', // how to return the data, really only supports serialize and inorder
			placeholder: '', // css class used to style the landing zone
			preprocess: '', // This function is called at the start of the update event (when the item is dropped) must in in global space
			tag: '#table_grid_sortable', // ID(s) of the container to work with, single or comma separated
			connect: '', // Use to group all related containers with a common CSS class
			sa: '', // Subaction that the xmlcontroller should know about
			title: '', // Title of the error box
			error: '', // What to say when we don't know what happened, like connection error
			token: '' // Security token if needed
		};

		// Account for any user options
		var oSettings = $.extend({}, $.fn.elkSortable.oDefaultsSettings, oInstanceSettings || {});

		if (typeof oSettings.infobar === 'undefined')
		{
			oSettings.infobar = new ElkInfoBar('sortable_bar', {error_class: 'errorbox', success_class: 'infobox'});
		}

		// Divs to hold our responses
		$('<div id=\'errorContainer\'><div/>').appendTo('body');

		$('#errorContainer').css({'display': 'none'});

		// Find all oSettings.tag and attach the UI sortable action
		$(oSettings.tag).sortable({
			opacity: oSettings.opacity,
			cursor: oSettings.cursor,
			axis: oSettings.axis,
			handle: oSettings.handle,
			containment: oSettings.containment,
			connectWith: oSettings.connect,
			placeholder: oSettings.placeholder,
			tolerance: oSettings.tolerance,
			delay: oSettings.delay,
			scroll: oSettings.scroll,
			helper: function(e, ui) {
				// Fist create a helper container
				var $originals = ui.children(),
					$helper = ui.clone(),
					$clone;

				// Replace the helper elements with spans, normally this is a <td> -> <span>
				// Done to make this container agnostic.
				$helper.children().each(function() {
					$(this).replaceWith(function() {
						return $('<span />', {html: $(this).html()});
					});
				});

				// Set the width of each helper cell span to be the width of the original cells
				$helper.children().each(function(index) {
					// Set helper cell sizes to match the original sizes
					return $(this).width($originals.eq(index).width()).css('display', 'inline-block');
				});

				// Next to overcome an issue where page scrolling does not work, we add the new agnostic helper
				// element to the body, and hide it
				$('body').append('<div id="clone" class="' + oSettings.placeholder + '">' + $helper.html() + '</div>');
				$clone = $('#clone');
				$clone.hide();

				// Append the clone element to the actual container we are working in and show it
				setTimeout(function() {
					$clone.appendTo(ui.parent());
					$clone.show();
				}, 1);

				// The above append process allows page scrolls to work while dragging the clone element
				return $clone;
			},
			update: function(e, ui) {
				// Called when an element is dropped in a new location
				var postdata = '',
					moved = ui.item.attr('id'),
					order = [],
					receiver = ui.item.parent().attr('id');

				// Calling a pre processing function?
				if (oSettings.preprocess !== '')
				{
					window[oSettings.preprocess]();
				}

				// How to post the sorted data
				if (oSettings.setorder === 'inorder')
				{
					// This will get the order in 1-n as shown on the screen
					$(oSettings.tag).find('li').each(function() {
						var aid = $(this).attr('id').split('_');
						order.push({name: aid[0] + '[]', value: aid[1]});
					});
					postdata = $.param(order);
				}
				// Get all id's in all the sortable containers
				else
				{
					$(oSettings.tag).each(function() {
						// Serialize will be 1-n of each nesting / connector
						if (postdata === '')
						{
							postdata += $(this).sortable(oSettings.setorder);
						}
						else
						{
							postdata += '&' + $(this).sortable(oSettings.setorder);
						}
					});
				}

				// Add in our security tags and additional options
				postdata += '&' + elk_session_var + '=' + elk_session_id;
				postdata += '&order=reorder';
				postdata += '&moved=' + moved;
				postdata += '&received=' + receiver;

				if (oSettings.token !== '')
				{
					postdata += '&' + oSettings.token.token_var + '=' + oSettings.token.token_id;
				}

				// And with the post data prepared, lets make the ajax request
				$.ajax({
					type: 'POST',
					url: elk_prepareScriptUrl(elk_scripturl) + 'action=xmlhttp;sa=' + oSettings.sa + ';api=xml',
					dataType: 'xml',
					data: postdata
				})
					.fail(function(jqXHR, textStatus, errorThrown) {
						if ('console' in window && console.info)
						{
							console.info(errorThrown);
						}

						oSettings.infobar.isError();
						oSettings.infobar.changeText(textStatus).showBar();
						// Reset the interface?
						if (oSettings.href !== '')
						{
							setTimeout(function() {
								window.location.href = elk_scripturl + oSettings.href;
							}, 1000);
						}
					})
					.done(function(data, textStatus, jqXHR) {
						var $_errorContent = $('#errorContent'),
							$_errorContainer = $('#errorContainer');

						if ($(data).find('error').length !== 0)
						{
							// Errors get a modal dialog box and redirect on close
							$_errorContainer.append('<p id="errorContent"></p>');
							$_errorContent.html($(data).find('error').text());
							$_errorContent.dialog({
								autoOpen: true,
								title: oSettings.title,
								modal: true,
								close: function(event, ui) {
									// Redirecting due to the error, that's a good idea
									if (oSettings.href !== '')
									{
										window.location.href = elk_scripturl + oSettings.href;
									}
								}
							});
						}
						else if ($(data).find('elk').length !== 0)
						{
							// Valid responses get the unobtrusive slider
							oSettings.infobar.isSuccess();
							oSettings.infobar.changeText($(data).find('elk > orders > order').text()).showBar();
						}
						else
						{
							// Something "other" happened ...
							$_errorContainer.append('<p id="errorContent"></p>');
							$_errorContent.html(oSettings.error + ' : ' + textStatus);
							$_errorContent.dialog({autoOpen: true, title: oSettings.title, modal: true});
						}
					})
					.always(function(data, textStatus, jqXHR) {
						if ($(data).find('elk > tokens > token').length !== 0)
						{
							// Reset the token
							oSettings.token.token_id = $(data).find('tokens').find('[type="token"]').text();
							oSettings.token.token_var = $(data).find('tokens').find('[type="token_var"]').text();
						}
					});
			}
		});
	};
})(jQuery);

/**
 * Helper function used in the preprocess call for drag/drop boards
 * Sets the id of all 'li' elements to cat#,board#,childof# for use in the
 * $_POST back to the xmlcontroller
 */
function setBoardIds ()
{
	// For each category of board
	$('[id^=category_]').each(function() {
		var cat = $(this).attr('id').split('category_'),
			uls = $(this).find('ul');

		// First up add drop zones so we can drag and drop to each level
		if (uls.length === 1)
		{
			// A single empty ul in a category, this can happen when a cat is dragged empty
			if ($(uls).find('li').length === 0)
			{
				$(uls).append('<li id="cbp_' + cat + ',-1,-1"></li>');
			}
			// Otherwise the li's need a child ul so we have a "child-of" drop zone
			else
			{
				$(uls).find('li:not(:has(ul))').append('<ul class="nolist elk_droppings"></ul>');
			}
		}
		// All others normally
		else
		{
			$(uls).find('li:not(:has(ul))').append('<ul class="nolist elk_droppings"></ul>');
		}

		// Next make find all the ul's in this category that have children, update the
		// id's with information that indicates the 1-n and parent/child info
		$(this).find('ul:parent').each(function(i, ul) {
			// Get the (li) parent of this ul
			var parentList = $(this).parent('li').attr('id'),
				pli = 0;

			// No parent, then its a base node 0, else its a child-of this node
			if (typeof (parentList) !== 'undefined')
			{
				pli = parentList.split(',');
				pli = pli[1];
			}

			// Now for each li in this ul
			$(this).find('li').each(function(i, el) {
				var currentList = $(el).attr('id');
				var myid = currentList.split(',');

				// Remove the old id, insert the newly computed cat,brd,childof
				$(el).removeAttr('id');
				myid = 'cbp_' + cat[1] + ',' + myid[1] + ',' + pli;
				$(el).attr('id', myid);
			});
		});
	});
}

/**
 * Generates a preview image based on the value of the provided element.
 * If the element is undefined or empty, no preview image will be generated.
 *
 * @param {string} elem - The id of the element to generate the preview for.
 */
function pwaPreview(elem) {
	if (typeof elem === 'undefined')
	{
		return;
	}

	let oSelection = document.getElementById(elem);
	if (oSelection && oSelection.value !== '')
	{
		let img = new Image();
		img.src = oSelection.value;
		img.id = elem + '_preview';
		img.style.height = '45px';
		img.style.margin = '0 20px';

		img.onload = function() {
			let oldImage = document.getElementById(img.id);
			if (oldImage)
			{
				oldImage.remove();
			}
			let imgHTML = '<img src="' + img.src + '" id="' + img.id + '" style="height:' + img.style.height + ';margin:' + img.style.margin + '"/>';
			oSelection.insertAdjacentHTML('afterend', imgHTML);
		};
	}
}