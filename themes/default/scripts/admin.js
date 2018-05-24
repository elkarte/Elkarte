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
 * Handle the JavaScript surrounding the admin and moderation center.
 */

/**
 * We like the globals cuz they is good to us
 */

/** global: previewTimeout, origText, valid, warningMessage, previewData, refreshPreviewCache, add_answer_template */
/** global: txt_add_another_answer, last_preview, txt_preview, elk_scripturl, txt_news_error_no_news, oThumbnails, elk_smiley_url */
/** global: db_vis, database_changes_area, elk_session_var, package_ftp_test, package_ftp_test_connection, package_ftp_test_failed */
/** global: onNewFolderReceived, elk_session_id, membersSwap, elk_images_url, maintain_members_choose, maintain_members_all */
/** global: reattribute_confirm, reattribute_confirm_email, reattribute_confirm_username, oModeratorSuggest, permission_profiles */
/** global: txt_save, txt_permissions_profile_rename, ajax_notification_cancel_text, txt_theme_remove_confirm, XMLHttpRequest */
/** global: theme_id, frames, editFilename, txt_ban_name_empty, txt_ban_restriction_empty, ElkInfoBar, txt_invalid_response */
/** global: feature_on_text, feature_off_text, core_settings_generic_error, startOptID, add_question_template, question_last_blank */
/** global: ourLanguageVersions, ourVersions, txt_add_another_answer, txt_permissions_commit, Image */

/**
 * Admin index class with the following methods
 * elk_AdminIndex(oOptions)
 * {
 *		public init()
 *		public loadAdminIndex()
 *		public setAnnouncements()
 *		public showCurrentVersion()
 *		public checkUpdateAvailable()
 * }
 *
 * @param {object} oOptions
 */
function elk_AdminIndex(oOptions)
{
	this.opt = oOptions;
	this.announcements = [];
	this.current = {};
	this.init_news = false;
	this.init();
}

// Initialize the admin index to handle announcement, current version and updates
elk_AdminIndex.prototype.init = function ()
{
	window.adminIndexInstanceRef = this;

	var fHandlePageLoaded = function () {
		window.adminIndexInstanceRef.loadAdminIndex();
	};

	addLoadEvent(fHandlePageLoaded);
};

elk_AdminIndex.prototype.loadAdminIndex = function ()
{
	// Load the current master and your version numbers.
	if (this.opt.bLoadVersions)
		this.showCurrentVersion();

	// Load the text box that says there's a new version available.
	if (this.opt.bLoadUpdateNotification)
		this.checkUpdateAvailable();
};

// Update the announcement container with news
elk_AdminIndex.prototype.setAnnouncement = function (announcement)
{
	var oElem = document.getElementById(this.opt.sAnnouncementContainerId),
		sMessages = this.init_news ? oElem.innerHTML : '',
		sMessage = '';

	sMessage = this.opt.sAnnouncementMessageTemplate.replace('%href%', announcement.html_url).replace('%subject%', announcement.name).replace('%time%', announcement.published_at.replace(/[TZ]/g, ' ')).replace('%message%', announcement.body).replace(/\n/g, '<br />').replace(/\r/g, '');

	oElem.innerHTML = sMessages + this.opt.sAnnouncementTemplate.replace('%content%', sMessage);
	this.init_news = true;
};

// Updates the current version container with the current version found in the repository
elk_AdminIndex.prototype.showCurrentVersion = function ()
{
	var oElkVersionContainer = document.getElementById(this.opt.slatestVersionContainerId),
		oinstalledVersionContainer = document.getElementById(this.opt.sinstalledVersionContainerId),
		sCurrentVersion = oinstalledVersionContainer.innerHTML,
		adminIndex = this,
		elkVersion = '???',
		verCompare = new elk_ViewVersions();

	$.getJSON('https://api.github.com/repos/elkarte/Elkarte/releases', {format: "json"},
	function(data, textStatus, jqXHR) {
		var mostRecent = {},
			previous = {};
		adminIndex.current = adminIndex.normalizeVersion(sCurrentVersion);

		$.each(data, function(idx, elem) {
			// No drafts, thank you
			if (elem.draft)
				return;

			var release = adminIndex.normalizeVersion(elem.tag_name);

			if (!previous.hasOwnProperty('major') || verCompare.compareVersions(sCurrentVersion, elem.tag_name.replace('-', '').substring(1)))
			{
				// Using a preprelease? Then you may need to know a new one is out!
				if ((elem.prerelease && adminIndex.current.prerelease) || (!elem.prerelease))
				{
					previous = release;
					mostRecent = elem;
				}
			}

			// Load the text box containing the latest news items.
			if (adminIndex.opt.bLoadAnnouncements)
				adminIndex.setAnnouncement(elem);
		});
		elkVersion = mostRecent.name.replace(/elkarte/i, '').trim();

		oElkVersionContainer.innerHTML = elkVersion;
		if (verCompare.compareVersions(sCurrentVersion, elkVersion))
			oinstalledVersionContainer.innerHTML = adminIndex.opt.sVersionOutdatedTemplate.replace('%currentVersion%', sCurrentVersion);
	});
};

// Compare two different versions and return true if the firs is higher than the second
elk_AdminIndex.prototype.compareVersion = function (curVer, refVer)
{
	if (curVer.major > refVer.major)
		return true;
	else if (curVer.major < refVer.major)
		return false;

	if (curVer.minor > refVer.minor)
		return true;
	else if (curVer.minor < refVer.minor)
		return false;

	if (curVer.micro > refVer.micro)
		return true;
	else if (curVer.micro < refVer.micro)
		return false;

	if (curVer.prerelease)
	{
		if (curVer.nano > refVer.nano)
			return true;
		else if (curVer.nano < refVer.nano)
			return false;
	}

	return false;
};

// Split a string representing a version number into an object
elk_AdminIndex.prototype.normalizeVersion = function (sVersion)
{
	var splitVersion = sVersion.split(/[\s-]/),
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

	for (var i = 0; i < splitVersion.length; i++)
	{
		if (splitVersion[i].toLowerCase() === 'elkarte')
			continue;

		if (splitVersion[i].substring(0, 3).toLowerCase() === 'dev' || splitVersion[i].substring(0, 5).toLowerCase() === 'alpha' || splitVersion[i].substring(0, 4).toLowerCase() === 'beta' || splitVersion[i].substring(0, 2).toLowerCase() === 'rc')
		{
			normalVersion.prerelease = true;
			prerelease = true;

			// the tag name comes with the number attached to the beta/rc
			if (splitVersion[i].indexOf('.') > 0)
			{
				var splitPre = splitVersion[i].split('.');
				normalVersion.nano = parseFloat(splitPre[1]);
				normalVersion.nano = parseFloat(splitVersion[i].substr(splitVersion[i].indexOf('.') + 1));
				normalVersion.status = aDevConvert[splitVersion[i].substr(0, splitVersion[i].indexOf('.')).toLowerCase()];
			}
		}

		// If we have passed a "beta" or an "RC" string, no need to go further
		if (prerelease)
		{
			// Only numbers and dots means a number
			if (splitVersion[i].replace(/[\d\.]/g, '') === '')
				normalVersion.nano = parseFloat(splitVersion[i]);

			continue;
		}

		// Likely from the tag
		if (splitVersion[i].substring(0, 1) === 'v')
			splitVersion[i] = splitVersion[i].substring(1);

		// Only numbers and dots means a number
		if (splitVersion[i].replace(/[\d\.]/g, '') === '')
		{
			var ver = splitVersion[i].split('.');
			normalVersion.major = parseInt(ver[0]);
			normalVersion.minor = parseInt(ver[1]);
			normalVersion.micro = ver.length > 2 ? parseInt(ver[2]) : 0;
		}
	}
	return normalVersion;
};

// Checks if a new version of ElkArte is available and if so updates the admin info box
elk_AdminIndex.prototype.checkUpdateAvailable = function ()
{
	if (!('ourUpdatePackage' in window))
		return;

	var oContainer = document.getElementById(this.opt.sUpdateNotificationContainerId);

	// Are we setting a custom title and message?
	var sTitle = 'ourUpdateTitle' in window ? window.ourUpdateTitle : this.opt.sUpdateNotificationDefaultTitle,
		sMessage = 'ourUpdateNotice' in window ? window.ourUpdateNotice : this.opt.sUpdateNotificationDefaultMessage;

	oContainer.innerHTML = this.opt.sUpdateNotificationTemplate.replace('%title%', sTitle).replace('%message%', sMessage);

	// Parse in the package download URL if it exists in the string.
	document.getElementById('update-link').href = this.opt.sUpdateNotificationLink.replace('%package%', window.ourUpdatePackage);

	// If we decide to override life into "red" mode, do it.
	if ('elkUpdateCritical' in window)
	{
		document.getElementById('update_title').style.backgroundColor = '#dd2222';
		document.getElementById('update_title').style.color = 'white';
		document.getElementById('update_message').style.backgroundColor = '#eebbbb';
		document.getElementById('update_message').style.color = 'black';
	}
};

/*
	elk_ViewVersions(oOptions)
	{
		public init()
		public loadViewVersions
		public swapOption(oSendingElement, sName)
		public compareVersions(sCurrent, sTarget)
		public determineVersions()
	}
*/
function elk_ViewVersions (oOptions)
{
	this.opt = oOptions;
	this.oSwaps = {};
	this.init();
}

// initialize the version checker
elk_ViewVersions.prototype.init = function ()
{
	// Load this on loading of the page.
	window.viewVersionsInstanceRef = this;
	var fHandlePageLoaded = function () {
		window.viewVersionsInstanceRef.loadViewVersions();
	};
	addLoadEvent(fHandlePageLoaded);
};

// Load all the file versions
elk_ViewVersions.prototype.loadViewVersions = function ()
{
	this.determineVersions();
};

elk_ViewVersions.prototype.swapOption = function (oSendingElement, sName)
{
	// If it is undefined, or currently off, turn it on - otherwise off.
	this.oSwaps[sName] = !(sName in this.oSwaps) || !this.oSwaps[sName];
	if (this.oSwaps[sName])
		$("#" + sName).show(300);
	else
		$("#" + sName).hide(300);

	// Unselect the link and return false.
	oSendingElement.blur();

	return false;
};

// compare a current and target version to determine if one is newer/older
elk_ViewVersions.prototype.compareVersions = function (sCurrent, sTarget)
{
	var aVersions = [],
		aParts = [],
		aCompare = [sCurrent, sTarget],
		aDevConvert = {'dev': 0, 'alpha': 1, 'beta': 2, 'rc': 3};

	for (var i = 0; i < 2; i++)
	{
		// Clean the version and extract the version parts.
		var sClean = aCompare[i].toLowerCase().replace(/ /g, '').replace(/release candidate/g, 'rc');
		aParts = sClean.match(/(\d+)(?:\.(\d+|))?(?:\.)?(\d+|)(?:(alpha|beta|rc)\.*(\d+|)(?:\.)?(\d+|))?(?:(dev))?(\d+|)/);

		// No matches?
		if (aParts === null)
			return false;

		// Build an array of parts.
		aVersions[i] = [
			aParts[1] > 0 ? parseInt(aParts[1]) : 0,
			aParts[2] > 0 ? parseInt(aParts[2]) : 0,
			aParts[3] > 0 ? parseInt(aParts[3]) : 0,
			typeof(aParts[4]) === 'undefined' ? 'stable' : aDevConvert[aParts[4]],
			aParts[5] > 0 ? parseInt(aParts[5]) : 0,
			aParts[6] > 0 ? parseInt(aParts[6]) : 0,
			typeof(aParts[7]) !== 'undefined' ? 'dev' : ''
		];
	}

	// Loop through each category.
	for (i = 0; i < 7; i++)
	{
		// Is there something for us to calculate?
		if (aVersions[0][i] !== aVersions[1][i])
		{
			// Dev builds are a problematic exception.
			// (stable) dev < (stable) but (unstable) dev = (unstable)
			if (i === 3)
				return aVersions[0][i] < aVersions[1][i] ? !aVersions[1][6] : aVersions[0][6];
			else if (i === 6)
				return aVersions[0][6] ? aVersions[1][3] === 'stable' : false;
			// Otherwise a simple comparison.
			else
				return aVersions[0][i] < aVersions[1][i];
		}
	}

	// They are the same!
	return false;
};

// For each area of ElkArte, determine the current and installed versions
elk_ViewVersions.prototype.determineVersions = function ()
{
	var oHighYour = {
		sources: '??',
		admin: '??',
		controllers: '??',
		database: '??',
		subs: '??',
		'default': '??',
		Languages: '??',
		Templates: '??'
	};
	var oHighCurrent = {
		sources: '??',
		admin: '??',
		controllers: '??',
		database: '??',
		subs: '??',
		'default': '??',
		Languages: '??',
		Templates: '??'
	};
	var oLowVersion = {
		sources: false,
		admin: false,
		controllers: false,
		database: false,
		subs: false,
		'default': false,
		Languages: false,
		Templates: false
	};

	var sSections = [
		'sources',
		'admin',
		'controllers',
		'database',
		'subs',
		'default',
		'Languages',
		'Templates'
	];

	var sCurVersionType = '',
		sinstalledVersion,
		oSection,
		oSectionLink;

	for (var i = 0, n = sSections.length; i < n; i++)
	{
		// Collapse all sections.
		oSection = document.getElementById(sSections[i]);

		if (typeof(oSection) === 'object' && oSection !== null)
			oSection.style.display = 'none';

		// Make all section links clickable.
		oSectionLink = document.getElementById(sSections[i] + '-link');
		if (typeof(oSectionLink) === 'object' && oSectionLink !== null)
		{
			oSectionLink.instanceRef = this;
			oSectionLink.sSection = sSections[i];
			oSectionLink.onclick = function () {
				this.instanceRef.swapOption(this, this.sSection);
				return false;
			};
		}
	}

	if (!('ourVersions' in window))
		window.ourVersions = {};

	// for each file in the detailed-version.js
	for (var sFilename in window.ourVersions)
	{
		if (!window.ourVersions.hasOwnProperty(sFilename))
			continue;

		if (!document.getElementById('our' + sFilename))
			continue;

		sCurVersionType = '';

		sinstalledVersion = document.getElementById('your' + sFilename).innerHTML;

		for (var sVersionType in oLowVersion)
		{
			if (!oLowVersion.hasOwnProperty(sVersionType))
			{
				continue;
			}

			if (sFilename.substr(0, sVersionType.length) === sVersionType)
			{
				sCurVersionType = sVersionType;
				break;
			}
		}

		if (sCurVersionType === '')
			continue;

		// use compareVersion to determine which version is >< the other
		if (typeof(sCurVersionType) !== 'undefined')
		{
			if ((this.compareVersions(oHighYour[sCurVersionType], sinstalledVersion) || oHighYour[sCurVersionType] === '??') && !oLowVersion[sCurVersionType])
				oHighYour[sCurVersionType] = sinstalledVersion;

			if (this.compareVersions(oHighCurrent[sCurVersionType], ourVersions[sFilename]) || oHighCurrent[sCurVersionType] === '??')
				oHighCurrent[sCurVersionType] = ourVersions[sFilename];

			if (this.compareVersions(sinstalledVersion, ourVersions[sFilename]))
			{
				oLowVersion[sCurVersionType] = sinstalledVersion;
				document.getElementById('your' + sFilename).style.color = 'red';
			}
		}
		else if (this.compareVersions(sinstalledVersion, ourVersions[sFilename]))
			oLowVersion[sCurVersionType] = sinstalledVersion;

		document.getElementById('our' + sFilename).innerHTML = ourVersions[sFilename];
		document.getElementById('your' + sFilename).innerHTML = sinstalledVersion;
	}

	if (!('ourLanguageVersions' in window))
		window.ourLanguageVersions = {};

	for (sFilename in window.ourLanguageVersions)
	{
		for (i = 0; i < this.opt.aKnownLanguages.length; i++)
		{
			if (!document.getElementById('our' + sFilename + this.opt.aKnownLanguages[i]))
				continue;

			document.getElementById('our' + sFilename + this.opt.aKnownLanguages[i]).innerHTML = ourLanguageVersions[sFilename];

			sinstalledVersion = document.getElementById('your' + sFilename + this.opt.aKnownLanguages[i]).innerHTML;
			document.getElementById('your' + sFilename + this.opt.aKnownLanguages[i]).innerHTML = sinstalledVersion;

			if ((this.compareVersions(oHighYour.Languages, sinstalledVersion) || oHighYour.Languages === '??') && !oLowVersion.Languages)
				oHighYour.Languages = sinstalledVersion;

			if (this.compareVersions(oHighCurrent.Languages, ourLanguageVersions[sFilename]) || oHighCurrent.Languages === '??')
				oHighCurrent.Languages = ourLanguageVersions[sFilename];

			if (this.compareVersions(sinstalledVersion, ourLanguageVersions[sFilename]))
			{
				oLowVersion.Languages = sinstalledVersion;
				document.getElementById('your' + sFilename + this.opt.aKnownLanguages[i]).style.color = 'red';
			}
		}
	}

	// Set the column titles based on the files each contain
	for (i = 0, n = sSections.length; i < n; i++)
	{
		if (sSections[i] === 'Templates')
			continue;

		document.getElementById('your' + sSections[i]).innerHTML = oLowVersion[sSections[i]] ? oLowVersion[sSections[i]] : oHighYour[sSections[i]];
		document.getElementById('our' + sSections[i]).innerHTML = oHighCurrent[sSections[i]];
		if (oLowVersion[sSections[i]])
			document.getElementById('your' + sSections[i]).style.color = 'red';
	}

	// Custom theme in use?
	if (document.getElementById('Templates'))
	{
		document.getElementById('yourTemplates').innerHTML = oLowVersion.Templates ? oLowVersion.Templates : oHighYour.Templates;
		document.getElementById('ourTemplates').innerHTML = oHighCurrent.Templates;

		if (oLowVersion.Templates)
			document.getElementById('yourTemplates').style.color = 'red';
	}
};

/**
 * Adds a new word container to the censored word list
 */
function addNewWord()
{
	setOuterHTML(document.getElementById('moreCensoredWords'), '<div class="censorWords"><input type="text" name="censor_vulgar[]" size="30" class="input_text" /> <i class="icon i-chevron-circle-right"></i> <input type="text" name="censor_proper[]" size="30" class="input_text" /><' + '/div><div id="moreCensoredWords"><' + '/div>');
}

/**
 * Will enable/disable checkboxes, according to if the BBC globally set or not.
 *
 * @param {string} section id of the container
 * @param {string} disable true or false
 */
function toggleBBCDisabled(section, disable)
{
	var elems = document.getElementById(section).getElementsByTagName('*');

	for (var i = 0; i < elems.length; i++)
	{
		if (typeof(elems[i].name) === "undefined" || (elems[i].name.substr((section.length + 1), (elems[i].name.length - 2 - (section.length + 1))) !== "enabledTags") || (elems[i].name.indexOf(section) !== 0))
			continue;

		elems[i].disabled = disable;
	}
	document.getElementById("bbc_" + section + "_select_all").disabled = disable;
}

/**
 * Keeps the input boxes display options appropriate for the options selected
 * when adding custom profile fields
 */
function updateInputBoxes()
{
	var curType = document.getElementById("field_type").value,
		privStatus = document.getElementById("private").value,
		stdText = ['text', 'textarea', 'email', 'url', 'color', 'date'],
		stdInput = ['text', 'email', 'url', 'color', 'date'],
		stdSelect = ['select'];

	var bIsStd = (stdInput.indexOf(curType) !== -1),
		bIsText = (stdText.indexOf(curType) !== -1),
		bIsSelect = (stdSelect.indexOf(curType) !== -1);

	// Only Text like fields can see a max length input
	document.getElementById("max_length_dt").style.display = bIsText ? "" : "none";
	document.getElementById("max_length_dd").style.display = bIsText ? "" : "none";

	// Textareas can get a row/col definition
	document.getElementById("dimension_dt").style.display = curType === "textarea" ? "" : "none";
	document.getElementById("dimension_dd").style.display = curType === "textarea" ? "" : "none";

	// Text like fields can be styled with bbc
	document.getElementById("bbc_dt").style.display = bIsText ? "" : "none";
	document.getElementById("bbc_dd").style.display = bIsText ? "" : "none";

	// And given defaults
	document.getElementById("defaultval_dt").style.display = bIsText ? "" : "none";
	document.getElementById("defaultval_dd").style.display = bIsText ? "" : "none";

	// Selects and radio can support a list of options
	document.getElementById("options_dt").style.display = curType === "select" || curType === "radio" ? "" : "none";
	document.getElementById("options_dd").style.display = curType === "select" || curType === "radio" ? "" : "none";

	// Checkboxes can have a default
	document.getElementById("default_dt").style.display = curType === "check" ? "" : "none";
	document.getElementById("default_dd").style.display = curType === "check" ? "" : "none";

	// Normal input boxes can use a validation mask as well
	document.getElementById("mask_dt").style.display = bIsStd ? "" : "none";
	document.getElementById("mask").style.display = bIsStd ? "" : "none";

	// And text and select fields are searchable
	document.getElementById("can_search_dt").style.display = bIsText || bIsSelect ? "" : "none";
	document.getElementById("can_search_dd").style.display = bIsText || bIsSelect ? "" : "none";

	// Moving to a non searchable field, be sure searchable is unselected.
	if (!bIsText && !bIsSelect)
		document.getElementById("can_search_dd").checked = false;

	// Using regex in the mask, give them a place to supply the regex
	document.getElementById("regex_div").style.display = bIsStd && document.getElementById("mask").value === "regex" ? "" : "none";
	document.getElementById("display").disabled = false;

	// Cannot show this on the topic
	if (curType === "textarea" || privStatus >= 2)
	{
		document.getElementById("display").checked = false;
		document.getElementById("display").disabled = true;
	}
}

/**
 * Used to add additional radio button options when editing a custom profile field
 */
function addOption()
{
	setOuterHTML(document.getElementById("addopt"), '<p><input type="radio" name="default_select" value="' + startOptID + '" id="' + startOptID + '" /><input type="text" name="select_option[' + startOptID + ']" value="" class="input_text" /></p><span id="addopt"></span>');
	startOptID++;
}

/**
 * Adds another question to the registration page
 */
function addAnotherQuestion()
{
	var placeHolder = document.getElementById('add_more_question_placeholder');

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
function addAnotherAnswer(elem, question_name)
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
function addAnotherSearch(txt_name, txt_url, txt_word_sep)
{
	var placeHolder = document.getElementById('add_more_searches'),
		newDT = document.createElement("dt"),
		newInput = document.createElement("input"),
		newLabel = document.createElement("label"),
		newDD = document.createElement("dd");

	newInput.name = "engine_name[]";
	newInput.type = "text";
	newInput.className = "input_text";
	newInput.size = "50";
	newInput.setAttribute("class", "verification_question");

	// Add the label and input box to the DOM
	newLabel.textContent = txt_name + ': ';
	newLabel.appendChild(newInput);
	newDT.appendChild(newLabel);

	// Next input box
	newInput = document.createElement("input");
	newInput.name = "engine_url[]";
	newInput.type = "text";
	newInput.className = "input_text";
	newInput.size = "35";
	newInput.setAttribute("class", "input_text verification_answer");

	// Add the new label and input box
	newLabel = document.createElement("label");
	newLabel.textContent = txt_url + ': ';
	newLabel.appendChild(newInput);
	newDD.appendChild(newLabel);
	newDD.appendChild(document.createElement("br"));

	// Rinse and repeat
	newInput = document.createElement("input");
	newInput.name = "engine_separator[]";
	newInput.type = "text";
	newInput.className = "input_text";
	newInput.size = "5";
	newInput.setAttribute("class", "input_text verification_answer");

	newLabel = document.createElement("label");
	newLabel.textContent = txt_word_sep + ': ';
	newLabel.appendChild(newInput);
	newDD.appendChild(newLabel);

	placeHolder.parentNode.insertBefore(newDT, placeHolder);
	placeHolder.parentNode.insertBefore(newDD, placeHolder);
}

/**
 * News admin page
 */
function addAnotherNews()
{
	var last = $("#list_news_lists_last"),
		$new_item = last.clone();

	last_preview++;
	$new_item.attr('id', 'list_news_lists_' + last_preview);
	$new_item.find('textarea').attr('id', 'data_' + last_preview);
	$new_item.find('#preview_last').attr('id', 'preview_' + last_preview);
	$new_item.find('#box_preview_last').attr('id', 'box_preview_' + last_preview);

	last.before($new_item);
	$new_item.toggle();
	make_preview_btn(last_preview);
}

/**
 * Makes the preview button when in manage news
 *
 * @param {string} preview_id
 */
function make_preview_btn (preview_id)
{
	var $id = $("#preview_" + preview_id);

	$id.text(txt_preview).on('click', function () {
		$.ajax({
			type: "POST",
			url: elk_scripturl + "?action=xmlpreview;xml",
			data: {item: "newspreview", news: $("#data_" + preview_id).val()},
			context: document.body
		})
		.done(function(request) {
			if ($(request).find("error").text() === '')
				$(document).find("#box_preview_" + preview_id).html($(request).text());
			else
				$(document).find("#box_preview_" + preview_id).text(txt_news_error_no_news);
		});
	});

	if (!$id.parent().hasClass('linkbutton_right'))
		$id.wrap('<a class="linkbutton_right" href="javascript:void(0);"></a>');
}

/**
 * Used by manage themes to show the thumbnail of the theme variant chosen
 *
 * @param {string} sVariant
 */
function changeVariant(sVariant)
{
	document.getElementById('variant_preview').src = oThumbnails[sVariant];
}

/**
 * The idea here is simple: don't refresh the preview on every keypress, but do refresh after they type.
 *
 * @returns {undefined}
 */
function setPreviewTimeout()
{
	if (previewTimeout)
	{
		window.clearTimeout(previewTimeout);
		previewTimeout = null;
	}

	previewTimeout = window.setTimeout(function() {refreshPreview(true); previewTimeout = null;}, 500);
}

/**
 * Used in manage paid subscriptions to show the fixed duration panel or
 * the variable duration panel, based on which radio button is selected
 *
 * @param {type} toChange
 */
function toggleDuration(toChange)
{
	$("#fixed_area").slideToggle(300);
	$("#flexible_area").slideToggle(300);
}

/**
 * Used when editing the search weights for results, calculates the overall total weight
 */
function calculateNewValues()
{
	var total = 0;
	for (var i = 1; i <= 7; i++)
	{
		total += parseInt(document.getElementById('weight' + i + '_val').value);
	}

	document.getElementById('weighttotal').innerHTML = total;
	for (i = 1; i <= 7; i++)
	{
		document.getElementById('weight' + i).innerHTML = (Math.round(1000 * parseInt(document.getElementById('weight' + i + '_val').value) / total) / 10) + '%';
	}
}

/**
 * Toggle visibility of add smile image source options
 */
function switchType()
{
	document.getElementById("ul_settings").style.display = document.getElementById("method-existing").checked ? "none" : "block";
	document.getElementById("ex_settings").style.display = document.getElementById("method-upload").checked ? "none" : "block";
}

/**
 * Toggle visibility of smiley set should the user want different images in a set (add smiley)
 */
function swapUploads()
{
	document.getElementById("uploadMore").style.display = document.getElementById("uploadSmiley").disabled ? "none" : "block";
	document.getElementById("uploadSmiley").disabled = !document.getElementById("uploadSmiley").disabled;
}

/**
 * Close the options that should not be visible for adding a smiley
 *
 * @param {string} element
 */
function selectMethod(element)
{
	document.getElementById("method-existing").checked = element !== "upload";
	document.getElementById("method-upload").checked = element === "upload";
}

/**
 * Updates the smiley preview to show the current one chosen
 */
function updatePreview()
{
	var currentImage = document.getElementById("preview");
	currentImage.src = elk_smiley_url + "/" + document.forms.smileyForm.set.value + "/" + document.forms.smileyForm.smiley_filename.value;
}

/**
 * Used in package manager to swap the visibility of database changes
 */
function swap_database_changes()
{
	db_vis = !db_vis;
	database_changes_area.style.display = db_vis ? "" : "none";

	return false;
}

/**
 * Test the given form credentials to test if an FTP connection can be made
 */
function testFTP()
{
	ajax_indicator(true);

	// What we need to post.
	var oPostData = {
		0: "ftp_server",
		1: "ftp_port",
		2: "ftp_username",
		3: "ftp_password",
		4: "ftp_path"
	};

	var sPostData = "";
	for (var i = 0; i < 5; i++)
		sPostData = sPostData + (sPostData.length === 0 ? "" : "&") + oPostData[i] + "=" + document.getElementById(oPostData[i]).value.php_urlencode();

	// Post the data out.
	sendXMLDocument(elk_prepareScriptUrl(elk_scripturl) + 'action=admin;area=packages;sa=ftptest;xml;' + elk_session_var + '=' + elk_session_id, sPostData, testFTPResults);
}

/**
 * Generate a "test ftp" button.
 */
function generateFTPTest()
{
	// Don't ever call this twice!
	if (generatedButton)
		return false;

	generatedButton = true;

	// No XML?
	if (!document.getElementById("test_ftp_placeholder") && !document.getElementById("test_ftp_placeholder_full"))
		return false;

	// create our test button to call testFTP on click
	var ftpTest = document.createElement("input");
	ftpTest.type = "button";
	ftpTest.className = "submit";
	ftpTest.onclick = testFTP;

	// Set the button value based on which form we are on
	if (document.getElementById("test_ftp_placeholder"))
	{
		ftpTest.value = package_ftp_test;
		document.getElementById("test_ftp_placeholder").appendChild(ftpTest);
	}
	else
	{
		ftpTest.value = package_ftp_test_connection;
		document.getElementById("test_ftp_placeholder_full").appendChild(ftpTest);
	}

	return true;
}

/**
 * Callback function of the testFTP function
 *
 * @param {type} oXMLDoc
 */
function testFTPResults(oXMLDoc)
{
	ajax_indicator(false);

	// This assumes it went wrong!
	var wasSuccess = false,
		message = package_ftp_test_failed,
		results = oXMLDoc.getElementsByTagName('results')[0].getElementsByTagName('result');

	// Results show we were a success
	if (results.length > 0)
	{
		if (parseInt(results[0].getAttribute('success')) === 1)
			wasSuccess = true;
		message = results[0].firstChild.nodeValue;
	}

	// place the informative box on screen so the user knows if things went well or poorly
	document.getElementById("ftp_error_div").style.display = "";
	document.getElementById("ftp_error_div").className = wasSuccess ? "successbox" : "errorbox";
	document.getElementById("ftp_error_message").innerHTML = message;
}

/**
 * Part of package manager, expands a folders contents to show
 * permission levels of files it contains.
 * Will use an ajax call to get any permissions it has not loaded
 *
 * @param {type} folderIdent
 * @param {type} folderReal
 */
function expandFolder(folderIdent, folderReal)
{
	// See if it already exists.
	var possibleTags = document.getElementsByTagName("tr");
	var foundOne = false;

	for (var i = 0; i < possibleTags.length; i++)
	{
		if (possibleTags[i].id.indexOf("content_" + folderIdent + ":-:") === 0)
		{
			possibleTags[i].style.display = possibleTags[i].style.display === "none" ? "" : "none";
			foundOne = true;
		}
	}

	// Got something then we're done.
	if (foundOne)
	{
		return false;
	}

	// Otherwise we need to get the wicked thing.
	ajax_indicator(true);
	getXMLDocument(elk_prepareScriptUrl(elk_scripturl) + 'action=admin;area=packages;onlyfind=' + folderReal.php_urlencode() + ';sa=perms;xml;' + elk_session_var + '=' + elk_session_id, onNewFolderReceived);

	return false;
}

/**
 * Wrapper function to call expandFolder
 */
function dynamicExpandFolder()
{
	expandFolder(this.ident, this.path);

	return false;
}

/**
 * Used when edit the boards and groups access to them
 *
 * @param {type} operation
 * @param {type} brd_list
 */
function select_in_category(operation, brd_list)
{
	for (var brd in brd_list) {
		if (!brd_list.hasOwnProperty(brd))
			continue;

		document.getElementById(operation + '_brd' + brd_list[brd]).checked = true;
	}
}

/**
 * Server Settings > Caching, toggles input fields on/off as appropriate for
 * a given cache engine selection
 */
$(function() {
	$('#cache_accelerator').change(function() {
		// Hide all the settings
		$('#cache_accelerator').find('option').each(function() {
			$('[id^=' + $(this).val() + '_]').hide();
		});

		// Show the settings of the selected engine
		$('[id^=' + $(this).val() + '_]').show();
	})
	// Trigger a change action so that the form is properly initialized
	.change();
});

/**
 * Server Settings > Caching, toggles input fields on/off as appropriate for
 * a given cache engine selection
 */
function toggleCache ()
{
	var memcache = $('#cache_memcached').parent(),
		cachedir = $('#cachedir').parent(),
		cacheuid = $('#cache_uid').parent(),
		cachepassword = $('#cache_password').parent(),
		cacheconfirm = $('#cache_password_confirm').parent();

	// Show the memcache server box only if memcache has been selected
	if (cache_type.value.substr(0, 8) !== "memcache")
	{
		memcache.slideUp();
		memcache.prev().slideUp(100);
	}
	else
	{
		memcache.slideDown();
		memcache.prev().slideDown(100);
	}

	// don't show the directory if its not filebased
	if (cache_type.value === "filebased")
	{
		cachedir.slideDown();
		cachedir.prev().slideDown(100);
	}
	else
	{
		cachedir.slideUp(100);
		cachedir.prev().slideUp(100);
	}

	// right now only xcache needs the uid/password
	if (cache_type.value === "xcache")
	{
		cacheuid.slideDown(100);
		cacheuid.prev().slideDown(100);
		cachepassword.slideDown(100);
		cachepassword.prev().slideDown(100);
		cacheconfirm.slideDown(100);
		cacheconfirm.prev().slideDown(100);
	}
	else
	{
		cacheuid.slideUp(100);
		cacheuid.prev().slideUp(100);
		cachepassword.slideUp(100);
		cachepassword.prev().slideUp(100);
		cacheconfirm.slideUp(100);
		cacheconfirm.prev().slideUp(100);
	}
}

/**
 * Hides local / subdomain cookie options in the ACP based on selected choices
 * area=serversettings;sa=cookie
 */
function hideGlobalCookies()
{
	var bUseLocal = document.getElementById("localCookies").checked,
		bUseGlobal = !bUseLocal && document.getElementById("globalCookies").checked;

	// Show/Hide the areas based on what they have chosen
	if (!bUseLocal)
	{
		$("#setting_globalCookies").parent().slideDown();
		$("#globalCookies").parent().slideDown();
	}
	else
	{
		$("#setting_globalCookies").parent().slideUp();
		$("#globalCookies").parent().slideUp();
	}

	// Global selected means we need to reveal the domain input box
	if (bUseGlobal)
	{
		$("#setting_globalCookiesDomain").closest("dt").slideDown();
		$("#globalCookiesDomain").closest("dd").slideDown();
	}
	else
	{
		$("#setting_globalCookiesDomain").closest("dt").slideUp();
		$("#globalCookiesDomain").closest("dd").slideUp();
	}
}

/**
 * Attachments Settings
 */
function toggleSubDir ()
{
	var auto_attach = document.getElementById('automanage_attachments'),
		use_sub_dir = document.getElementById('use_subdirectories_for_attachments'),
		dir_elem = document.getElementById('basedirectory_for_attachments');

	use_sub_dir.disabled = !Boolean(auto_attach.selectedIndex);
	if (use_sub_dir.disabled)
	{
		$(use_sub_dir).slideUp();
		$('#setting_use_subdirectories_for_attachments').parent().slideUp();

		$(dir_elem).slideUp();
		$('#setting_basedirectory_for_attachments').parent().slideUp();
	}
	else
	{
		$(use_sub_dir).slideDown();
		$('#setting_use_subdirectories_for_attachments').parent().slideDown();

		$(dir_elem).slideDown();
		$('#setting_basedirectory_for_attachments').parent().slideDown();
	}
		toggleBaseDir();
}

/**
 * Called by toggleSubDir as part of manage attachments
 */
function toggleBaseDir ()
{
	var auto_attach = document.getElementById('automanage_attachments'),
		sub_dir = document.getElementById('use_subdirectories_for_attachments'),
		dir_elem = document.getElementById('basedirectory_for_attachments');

	if (auto_attach.selectedIndex === 0)
		dir_elem.disabled = 1;
	else
		dir_elem.disabled = !sub_dir.checked;
}


/**
 * Called from purgeinactive users maintenance task, used to show or hide
 * the membergroup list.  If collapsed will select all the member groups if expanded
 * unselect them so the user can choose.
 */
function swapMembers()
{
	var membersForm = document.getElementById('membersForm');

	// Make it close smoothly
	$("#membersPanel").slideToggle(300);

	membersSwap = !membersSwap;
	document.getElementById("membersIcon").src = elk_images_url + (membersSwap ? "/selected_open.png" : "/selected.png");
	document.getElementById("membersText").innerHTML = membersSwap ? maintain_members_choose : maintain_members_all;

	// Check or uncheck them all based on if we are expanding or collasping the area
	for (var i = 0; i < membersForm.length; i++)
	{
		if (membersForm.elements[i].type.toLowerCase() === "checkbox")
			membersForm.elements[i].checked = !membersSwap;
	}

	return false;
}

/**
 * Called from reattribute member posts to build the confirm message for the action
 * Keeps the action button (reattribute) disabled until all necessary fields have been filled
 */
function checkAttributeValidity()
{
	origText = reattribute_confirm;
	valid = true;

	// Do all the fields!
	if (!document.getElementById('to').value)
		valid = false;

	warningMessage = origText.replace(/%member_to%/, document.getElementById('to').value);

	// Using email address to find the member
	if (document.getElementById('type_email').checked)
	{
		if (!document.getElementById('from_email').value)
			valid = false;

		warningMessage = warningMessage.replace(/%type%/, '', reattribute_confirm_email).replace(/%find%/, document.getElementById('from_email').value);
	}
	// Or the user name
	else
	{
		if (!document.getElementById('from_name').value)
			valid = false;

		warningMessage = warningMessage.replace(/%type%/, '', reattribute_confirm_username).replace(/%find%/, document.getElementById('from_name').value);
	}

	document.getElementById('do_attribute').disabled = !valid;

	// Keep checking for a valid form so we can activate the submit button
	setTimeout(function() {checkAttributeValidity();}, 500);

	return valid;
}

/**
 * Enable/disable fields when transferring attachments
 *
 * @returns {undefined}
 */
function transferAttachOptions()
{
	var autoSelect = document.getElementById("auto"),
		autoValue = parseInt(autoSelect.options[autoSelect.selectedIndex].value, 10),
		toSelect = document.getElementById("to"),
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
function confirmMoveTopics(confirmText)
{
	var from = document.getElementById('id_board_from'),
		to = document.getElementById('id_board_to');

	if (from.options[from.selectedIndex].disabled || from.options[to.selectedIndex].disabled)
		return false;

	return confirm(confirmText.replace(/%board_from%/, from.options[from.selectedIndex].text.replace(/^\u2003+\u27A4/, '')).replace(/%board_to%/, to.options[to.selectedIndex].text.replace(/^\u2003+\u27A4/, '')));
}

/**
 * Hide the search methods area if using sphinx(ql) search
 */
function showhideSearchMethod()
{
	var searchSphinxQl = document.getElementById('search_index_sphinxql').checked,
		searchSphinx = document.getElementById('search_index_sphinx').checked,
		searchhide = searchSphinxQl || searchSphinx,
		searchMethod = $('#search_method');

	if (searchhide)
		searchMethod.slideUp();
	else
		searchMethod.slideDown();
}

/**
 * Used in manageFeatures to show / hide custom level input elements based on the checkbox choices
 * Will show or hide the jquery and jqueryui custom input fields for admins that like to roll the dice
 */
function showhideJqueryOptions()
{
	var jqBase = document.getElementById('jquery_default').checked,
		jqUi = document.getElementById('jqueryui_default').checked,
		jqBase_val = $('#jquery_version'),
		jqUi_val = $('#jqueryui_version');

	// Show the jquery custom level box only if the option has been selected
	// yes the dt dd stuff makes it this ugly
	if (jqBase === false)
	{
		// dd and the dt
		jqBase_val.parent().slideUp();
		jqBase_val.parent().prev().slideUp();
	}
	else
	{
		jqBase_val.parent().slideDown();
		jqBase_val.parent().prev().slideDown();
	}

	// And the same for the UI areas as well
	if (jqUi === false)
	{
		// The parent is the dd and the sibling is its dt
		jqUi_val.parent().slideUp();
		jqUi_val.parent().prev().slideUp();
	}
	else
	{
		jqUi_val.parent().slideDown();
		jqUi_val.parent().prev().slideDown();
	}
}

/**
 * Used in manageMembergroups to enable disable form elements based on allowable choices
 * If post based group is selected, it will disable moderation selection, visibility, group description
 * and enable post count input box
 *
 * @param {boolean} isChecked
 */
function swapPostGroup(isChecked)
{
	var min_posts_text = document.getElementById('min_posts_text'),
		group_desc_text = document.getElementById('group_desc_text'),
		group_hidden_text = document.getElementById('group_hidden_text'),
		group_moderators_text = document.getElementById('group_moderators_text');

	document.forms.groupForm.min_posts.disabled = !isChecked;
	min_posts_text.style.color = isChecked ? "" : "#888";

	document.forms.groupForm.group_desc_input.disabled = isChecked;
	group_desc_text.style.color = !isChecked ? "" : "#888";

	document.forms.groupForm.group_hidden_input.disabled = isChecked;
	group_hidden_text.style.color = !isChecked ? "" : "#888";

	document.forms.groupForm.group_moderators.disabled = isChecked;
	group_moderators_text.style.color = !isChecked ? "" : "#888";

	// Disable the moderator autosuggest box as well
	if (typeof(oModeratorSuggest) !== 'undefined')
		oModeratorSuggest.oTextHandle.disabled = !!isChecked;
}

/**
 * Handles the AJAX preview of the warning templates
 */
function ajax_getTemplatePreview()
{
	$.ajax({
		type: "POST",
		url: elk_scripturl + '?action=xmlpreview;xml',
		data: {
			item: "warning_preview",
			title: $("#template_title").val(),
			body: $("#template_body").val(),
			user: $('input[name="u"]').attr("value")
		},
		context: document.body
	})
	.done(function(request) {
		$("#box_preview").css({display:"block"});
		$("#template_preview").html($(request).find('body').text());

		var $_errors = $("#errors");
		if ($(request).find("error").text() !== '')
		{
			$_errors.css({display:"block"});

			var errors_html = '',
			errors = $(request).find('error').each(function() {
				errors_html += $(this).text() + '<br />';
			});

			$(document).find("#error_list").html(errors_html);
			$('html, body').animate({ scrollTop: $_errors.offset().top }, 'slow');
		}
		else
		{
			$_errors.css({display:"none"});
			$("#error_list").html('');
			$('html, body').animate({ scrollTop: $("#box_preview").offset().top }, 'slow');
		}

		return false;
	});

	return false;
}

/**
 * Sets up all the js events for edit and save board-specific permission
 * profiles
 */
function initEditProfileBoards()
{
	$('.edit_all_board_profiles').on('click', function(e) {
		e.preventDefault();

		$('.edit_board').off('click.elkarte');
	});

	$('.edit_board').show().on('click.elkarte', function(e) {
		var $icon = $(this),
			board_id = $icon.data('boardid'),
			board_profile = $icon.data('boardprofile'),
			$target = $('#edit_board_' + board_id),
			$select = $('<select />')
				.attr('name', 'boardprofile[' + board_id + ']')
				.change(function() {
					$(this).find('option:selected').each(function() {
						if ($(this).attr('value') == board_profile)
							$icon.addClass('nochanges').removeClass('changed');
						else
							$icon.addClass('changed').removeClass('nochanges');
					});
				});

		e.preventDefault();
		$(permission_profiles).each(function(key, value) {
			var $opt = $('<option />').attr('value', value.id).text(value.name);

			if (value.id == board_profile)
				$opt.attr('selected', 'selected');

			$select.append($opt);
		});

		$target.replaceWith($select);
		$select.change();

		$('.edit_all_board_profiles').replaceWith($('<input type="submit" class="right_submit" />')
			.attr('name', 'save_changes')
			.attr('value', txt_save)
		);
		$icon.off('click.elkarte').on('click', function(e) {
			e.preventDefault();
			if ($(this).hasClass('changed'))
				$('input[name="save_changes"]').off('click');
		});
	});
}

/**
 * Creates the image and attaches the event to convert the name of the permission
 * profile into an input to change its name and back.
 *
 * It also removes the "Rename all" and "Remove Selected" buttons
 * and the "Delete" column for consistency
 */
function initEditPermissionProfiles()
{
	// We need a variable to be sure we are going to create only 1 cancel button
	var run_once = false;

	$('.rename_profile').each(function() {
		var $this_profile = $(this);

		$this_profile.after($('<a class="js-ed edit_board" />').attr('href', '#').on('click', function(ev) {
			ev.preventDefault();

			// If we have already created the cancel let's skip it
			if (!run_once)
			{
				var $cancel;

				run_once = true;
				$cancel = $('<a class="js-ed-rm linkbutton" />').on('click', function(ev) {
					ev.preventDefault();

					// js-ed is hopefully a class introduced by this function only
					// Any element with this class will be restored when cancel is clicked
					$('.js-ed').show();

					// js-ed-rm is again a class introduced by this function
					// Any element with this class will be removed when cancelling
					$('.js-ed-rm').remove();

					// The cancel button is removed as well,
					// so we need to generate it again later (if we need it again)
					run_once = false;

					$('#rename').val(txt_permissions_profile_rename);
				}).text(ajax_notification_cancel_text).attr('href', '#');
			}

			$this_profile.after($('<input type="text" class="js-ed-rm input_text" />')
				.attr('name', 'rename_profile[' + $this_profile.data('pid') + ']')
				.val($this_profile.text()));

			// These will have to pop back hitting cancel, so let's prepare them
			$('#rename').addClass('js-ed').val(txt_permissions_commit).before($cancel);
			$this_profile.addClass('js-ed').hide();
			$('#delete').addClass('js-ed').hide();
			$('.perm_profile_delete').addClass('js-ed').hide();
			$(this).hide();
		}));
	});
}

/**
 * Attach the AJAX handling of things to the various themes to remove
 * Used in ManageThemes (template_list_themes)
 */
function initDeleteThemes()
{
	$(".delete_theme").on("click", function (event) {
		event.preventDefault();
		var theme_id = $(this).data("theme_id"),
			base_url = $(this).attr("href"),
			pattern = new RegExp(elk_session_var + "=" + elk_session_id + ";(.*)$"),
			tokens = pattern.exec(base_url)[1].split("="),
			token = tokens[1],
			token_var = tokens[0];

		if (confirm(txt_theme_remove_confirm))
		{
			$.ajax({
				type: "GET",
				url: base_url + ";api;xml",
				beforeSend: ajax_indicator(true)
			})
			.done(function(request) {
				if ($(request).find("error").length === 0)
				{
					var new_token = $(request).find("token").text(),
						new_token_var = $(request).find("token_var").text();

					$(".theme_" + theme_id).slideToggle("slow", function () {
						$(this).remove();
					});

					$(".delete_theme").each(function () {
						$(this).attr("href", $(this).attr("href").replace(token_var + "=" + token, new_token_var + "=" + new_token));
					});
				}
				// @todo improve error handling
				else
				{
					alert($(request).find("text").text());
					// Redirect to the delete theme page, though it will result in a token verification error
					window.location = base_url;
				}
			})
			.fail(function(request) {
				window.location = base_url;
			})
			.always(function() {
				// turn off the indicator
				ajax_indicator(false);
			});
		}
	});
}

/**
 * These two functions (navigatePreview and refreshPreview) are used in ManageThemes
 * (template_edit_style) to create a preview of the site with the changed stylesheets
 *
 * @param {string} url
 */
function navigatePreview(url)
{
	var myDoc = new XMLHttpRequest();

	myDoc.onreadystatechange = function ()
	{
		if (myDoc.readyState !== 4)
			return;

		if (myDoc.responseText !== null && myDoc.status === 200)
		{
			previewData = myDoc.responseText;
			document.getElementById('css_preview_box').style.display = "block";

			// Revert to the theme they actually use ;).
			var tempImage = new Image();
			tempImage.src = elk_prepareScriptUrl(elk_scripturl) + 'action=admin;area=theme;sa=edit;theme=' + theme_id + ';preview;' + (new Date().getTime());

			refreshPreviewCache = null;
			refreshPreview(false);
		}
	};

	var anchor = "";
	if (url.indexOf("#") !== -1)
	{
		anchor = url.substr(url.indexOf("#"));
		url = url.substr(0, url.indexOf("#"));
	}

	myDoc.open("GET", url + (url.indexOf("?") === -1 ? "?" : ";") + 'theme=' + theme_id + anchor, true);
	myDoc.send(null);
}

/**
 * Used when editing a stylesheet.  Allows for the preview to be updated to reflect
 * changes made to the css in the editor.
 *
 * @param {boolean} check
 */
function refreshPreview(check)
{
	var identical = document.forms.stylesheetForm.entire_file.value == refreshPreviewCache;

	// Don't reflow the whole thing if nothing changed!!
	if (check && identical)
		return;

	refreshPreviewCache = document.forms.stylesheetForm.entire_file.value;

	// Replace the paths for images.
	refreshPreviewCache = refreshPreviewCache.replace(/url\(\.\.\/images/gi, "url(" + elk_images_url);

	// Try to do it without a complete reparse.
	if (identical)
	{
		try
		{
			if (is_ie)
			{
				var sheets = frames['css_preview_box'].document.styleSheets;
				for (var j = 0; j < sheets.length; j++)
				{
					if (sheets[j].id === 'css_preview_box')
						sheets[j].cssText = document.forms.stylesheetForm.entire_file.value;
				}
			}
			else
			{
				frames['css_preview_box'].document.getElementById("css_preview_sheet").innerHTML = document.forms.stylesheetForm.entire_file.value;
			}
		}
		catch (e)
		{
			identical = false;
		}
	}

	// This will work most of the time... could be done with an after-apply, maybe.
	if (!identical)
	{
		var data = previewData,
			preview_sheet = document.forms.stylesheetForm.entire_file.value,
			stylesheetMatch = new RegExp('<link rel="stylesheet"[^>]+href="[^"]+' + editFilename + '[^>]*>'),
			iframe;

		// Replace the paths for images.
		preview_sheet = preview_sheet.replace(/url\(\.\.\/images/gi, "url(" + elk_images_url);
		data = data.replace(stylesheetMatch, '<style type="text/css" id="css_preview_sheet">' + preview_sheet + "<" + "/style>");

		iframe = document.getElementById("css_preview_box");
		iframe.contentWindow.document.open();
		iframe.contentWindow.document.write(data);
		iframe.contentWindow.document.close();

		// Next, fix all its links so we can handle them and reapply the new css!
		iframe.onload = function ()
		{
			var fixLinks = frames["css_preview_box"].document.getElementsByTagName("a");
			for (var i = 0; i < fixLinks.length; i++)
			{
				if (fixLinks[i].onclick)
					continue;

				fixLinks[i].onclick = function ()
				{
					window.parent.navigatePreview(this.href);
					return false;
				};
			}
		};
	}
}

/**
 * Callback (onBeforeUpdate) used by the AutoSuggest, used when adding new bans
 *
 * @param {object} oAutoSuggest
 */
function onUpdateName(oAutoSuggest)
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
function confirmBan(aForm)
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
var fUpdateStatus = function ()
{
	document.getElementById("expire_date").disabled = !document.getElementById("expires_one_day").checked;
	document.getElementById("cannot_post").disabled = document.getElementById("full_ban").checked;
	document.getElementById("cannot_register").disabled = document.getElementById("full_ban").checked;
	document.getElementById("cannot_login").disabled = document.getElementById("full_ban").checked;
};

/**
 * Used when setting up subscriptions, used to toggle the currency code divs
 * based on which currencies are chosen.
 */
function toggleCurrencyOther()
{
	var otherOn = document.getElementById("paid_currency").value === 'other',
		currencydd = document.getElementById("custom_currency_code_div_dd");

	if (otherOn)
	{
		document.getElementById("custom_currency_code_div").style.display = "";
		document.getElementById("custom_currency_symbol_div").style.display = "";

		if (currencydd)
		{
			document.getElementById("custom_currency_code_div_dd").style.display = "";
			document.getElementById("custom_currency_symbol_div_dd").style.display = "";
		}
	}
	else
	{
		document.getElementById("custom_currency_code_div").style.display = "none";
		document.getElementById("custom_currency_symbol_div").style.display = "none";

		if (currencydd)
		{
			document.getElementById("custom_currency_symbol_div_dd").style.display = "none";
			document.getElementById("custom_currency_code_div_dd").style.display = "none";
		}
	}
}

/**
 * Used to ajax-ively preview the templates of bounced emails (template_bounce_template)
 */
function ajax_getEmailTemplatePreview()
{
	$.ajax({
		type: "POST",
		url: elk_scripturl + "?action=xmlpreview;xml",
		data: {
			item: "bounce_preview",
			title: $("#template_title").val(),
			body: $("#template_body").val()
		},
		context: document.body
	})
	.done(function(request) {
		// Show the preview section, populated with the response
		$("#preview_section").css({display: "block"});
		$("#preview_body").html($(request).find('body').text());
		$("#preview_subject").html($(request).find('subject').text());

		// Any error we need to let them know about?
		if ($(request).find("error").text() !== '')
		{
			var errors_html = '',
				$_errors = $("#errors"),
				errors;

			// Build the error string
			errors = $(request).find('error').each(function() {
				errors_html += $(this).text() + '<br />';
			});

			// Add it to the error div, set the class level, and show it
			$(document).find("#error_list").html(errors_html);
			$_errors.css({display: ""});
			$_errors.attr('class', parseInt($(request).find('errors').attr('serious')) === 0 ? 'warningbox' : 'errorbox');
		}
		else
		{
			$("#errors").css({display: "none"});
			$("#error_list").html('');
		}

		// Navigate to the preview
		$('html, body').animate({ scrollTop: $('#preview_section').offset().top }, 'slow');

		return false;
	});

	return false;
}

/**
 * Used to ajax-ively preview a word censor
 * Does no checking, it either gets a result or does nothing
 */
function ajax_getCensorPreview()
{
	$.ajax({
		type: 'POST',
		dataType: 'json',
		url: elk_scripturl + "?action=admin;area=postsettings;sa=censor;xml",
		data: {
			censortest: $("#censortest").val()
		}
	})
	.done(function(request) {
		if (request.result === true) {
			// Show the censored text section, populated with the response
			$("#censor_result").css({display: "block"}).html(request.censor);

			// Update the token
			$("#token").attr({name:request.token_val, value:request.token});

			// Clear the box
			$('#censortest').attr({value:''}).val('');
		}
	});

	return false;
}

/**
 * Used to show/hide sub options for the various notifications
 * action=admin;area=featuresettings;sa=mention
 */
$(function() {
	var $headers = $("#mention").find("input[id^='notifications'][id$='[notification]']");

	$headers.change(function() {
		var $top = $(this).closest('dl'),
			$hparent = $(this).parent();

		if (this.checked)
		{
			$top.find('dt:not(:first-child)').fadeIn();
			$top.find('dd:not(:nth-child(2))').each(function() {
				$(this).fadeIn();
				$(this).find('input').prop('disabled', false);
			});
		}
		else
		{
			$top.find('dt:not(:first-child)').hide();
			$top.find('dd:not(:nth-child(2))').each(function() {
				$(this).hide();
				$(this).find('input').prop('disabled', true);
			});
		}

		$hparent.show();
		$hparent.prev().show();
	});

	$headers.change();
});

/**
 * Ajax function to clear CSS and JS hives.  Called from action=admin;area=featuresettings;sa=basic
 * Remove Hives button.
 */
$(function() {
	$('#clean_hives').on('click', function () {
		var infoBar = new ElkInfoBar('bar_clean_hives');

		$.ajax({
			type: 'POST',
			dataType: 'json',
			url: elk_scripturl + "?action=admin;area=featuresettings;sa=basic;xml;api=json",
			data: {
				cleanhives: true
			}
		})
		.done(function(request) {
			infoBar.changeText(request.response);

			if (request.success === true) {
				infoBar.isSuccess();
			}
			else {
				infoBar.isError();
			}
		})
		.fail(function(request) {
			infoBar.isError();
			infoBar.changeText(txt_invalid_response);
		})
		.always(function(request) {
			infoBar.showBar();
		});

		return false;
	});
});

/**
 * Enable / disable "core" features of the software. Called from action=admin;area=corefeatures
 */
$(function() {
	if ($('#core_features').length === 0)
	{
		return;
	}

	$(".core_features_hide").css('display', 'none');
	$(".core_features_img").show().css({'cursor': 'pointer'}).each(function() {
		var sImageText = $(this).hasClass('on') ? feature_on_text : feature_off_text;
		$(this).attr({ title: sImageText, alt: sImageText });
	});
	$("#core_features_submit").css('display', 'none');

	if (!token_name)
		token_name = $("#core_features_token").attr("name");

	if (!token_value)
		token_value = $("#core_features_token").attr("value");

	// Attach our action to the core features power button
	$(".core_features_img").click(function() {
		var cc = $(this),
			cf = $(this).attr("id").substring(7),
			imgs = new Array(elk_images_url + "/admin/switch_off.png", elk_images_url + "/admin/switch_on.png"),
			new_state = !$("#feature_" + cf).attr("checked"),
			ajax_infobar = new ElkInfoBar('core_features_bar', {error_class: 'errorbox', success_class: 'successbox'}),
			data;

		$("#feature_" + cf).attr("checked", new_state);

		data = {save: "save", feature_id: cf};
		data[$("#core_features_session").attr("name")] = $("#core_features_session").val();
		data[token_name] = token_value;

		$(".core_features_status_box").each(function(){
			data[$(this).attr("name")] = !$(this).attr("checked") ? 0 : 1;
		});

		// Launch AJAX request.
		$.ajax({
			// The link we are accessing.
			url: elk_scripturl + "?action=xmlhttp;sa=corefeatures;xml",

			// The type of request.
			type: "post",

			// The type of data that is getting returned.
			data: data
		})
		.done(function(request) {
			if ($(request).find("errors").find("error").length !== 0)
			{
				ajax_infobar.isError();
				ajax_infobar.changeText($(request).find("errors").find("error").text()).showBar();
			}
			else if ($(request).find("elk").length !== 0)
			{
				$("#feature_link_" + cf).html($(request).find("corefeatures").find("corefeature").text());
				cc.attr({
					"src": imgs[new_state ? 1 : 0],
					"title": new_state ? feature_on_text : feature_off_text,
					"alt": new_state ? feature_on_text : feature_off_text
				});
				$("#feature_link_" + cf).fadeOut().fadeIn();
				ajax_infobar.isSuccess();
				var message = $(request).find("messages").find("message").text();
				ajax_infobar.changeText(message).showBar();

				token_name = $(request).find("tokens").find('[type="token"]').text();
				token_value = $(request).find("tokens").find('[type="token_var"]').text();
			}
			else
			{
				ajax_infobar.isError();
				ajax_infobar.changeText(core_settings_generic_error).showBar();
			}
		})
		.fail(function(error) {
			ajax_infobar.changeText(error).showBar();
		});
	});
});

function confirmAgreement(text) {
	if ($('#checkboxAcceptAgreement').is(':checked')) {
		return confirm(text);
	}
	return true;
}
