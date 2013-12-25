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
 * Handle the JavaScript surrounding the admin and moderation center.
 */

/**
 * 	Admin index class with the following methods
 * 	elk_AdminIndex(oOptions)
 * 	{
 * 		public init()
 * 		public loadAdminIndex()
 * 		public setAnnouncements()
 * 		public showCurrentVersion()
 * 		public checkUpdateAvailable()
 * 	}
 *
 * @param {object} oOptions
 */
function elk_AdminIndex(oOptions)
{
	this.opt = oOptions;
	this.init();
}

// Initialize the admin index to handle annoucment, currentversion and updates
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
	// Load the text box containing the latest news items.
	if (this.opt.bLoadAnnouncements)
		this.setAnnouncements();

	// Load the current master and your version numbers.
	if (this.opt.bLoadVersions)
		this.showCurrentVersion();

	// Load the text box that sais there's a new version available.
	if (this.opt.bLoadUpdateNotification)
		this.checkUpdateAvailable();
};

// Update the announcement container with news
elk_AdminIndex.prototype.setAnnouncements = function ()
{
	if (!('ourAnnouncements' in window) || !('length' in window.ourAnnouncements))
		return;

	var sMessages = '';
	for (var i = 0; i < window.ourAnnouncements.length; i++)
		sMessages += this.opt.sAnnouncementMessageTemplate.replace('%href%', window.ourAnnouncements[i].href).replace('%subject%', window.ourAnnouncements[i].subject).replace('%time%', window.ourAnnouncements[i].time).replace('%message%', window.ourAnnouncements[i].message);

	document.getElementById(this.opt.sAnnouncementContainerId).innerHTML = this.opt.sAnnouncementTemplate.replace('%content%', sMessages);
};

// Updates the current version container with the current version found in current-version.js
elk_AdminIndex.prototype.showCurrentVersion = function ()
{
	if (!('elkVersion' in window))
		return;

	var oElkVersionContainer = document.getElementById(this.opt.sOurVersionContainerId),
		oYourVersionContainer = document.getElementById(this.opt.sYourVersionContainerId),
		sCurrentVersion = oYourVersionContainer.innerHTML;

	oElkVersionContainer.innerHTML = window.elkVersion;
	if (sCurrentVersion !== window.elkVersion)
		oYourVersionContainer.innerHTML = this.opt.sVersionOutdatedTemplate.replace('%currentVersion%', sCurrentVersion);
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
		aCompare = new Array(sCurrent, sTarget);

	for (var i = 0; i < 2; i++)
	{
		// Clean the version and extract the version parts.
		var sClean = aCompare[i].toLowerCase().replace(/ /g, '');
		aParts = sClean.match(/(\d+)(?:\.(\d+|))?(?:\.)?(\d+|)(?:(alpha|beta|rc)(\d+|)(?:\.)?(\d+|))?(?:(dev))?(\d+|)/);

		// No matches?
		if (aParts === null)
			return false;

		// Build an array of parts.
		aVersions[i] = [
			aParts[1] > 0 ? parseInt(aParts[1]) : 0,
			aParts[2] > 0 ? parseInt(aParts[2]) : 0,
			aParts[3] > 0 ? parseInt(aParts[3]) : 0,
			typeof(aParts[4]) === 'undefined' ? 'stable' : aParts[4],
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
		defaults: '??',
		Languages: '??',
		Templates: '??'
	};
	var oHighCurrent = {
		sources: '??',
		admin: '??',
		controllers: '??',
		database: '??',
		subs: '??',
		defaults: '??',
		Languages: '??',
		Templates: '??'
	};
	var oLowVersion = {
		sources: false,
		admin: false,
		controllers: false,
		database: false,
		subs: false,
		defaults: false,
		Languages: false,
		Templates: false
	};

	var sSections = [
		'sources',
		'admin',
		'controllers',
		'database',
		'subs',
		'defaults',
		'Languages',
		'Templates'
	];

	for (var i = 0, n = sSections.length; i < n; i++)
	{
		// Collapse all sections.
		var oSection = document.getElementById(sSections[i]);
		if (typeof(oSection) === 'object' && oSection !== null)
			oSection.style.display = 'none';

		// Make all section links clickable.
		var oSectionLink = document.getElementById(sSections[i] + '-link');
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
		if (!document.getElementById('our' + sFilename))
			continue;

		var sYourVersion = document.getElementById('your' + sFilename).innerHTML,
			sCurVersionType;

		for (var sVersionType in oLowVersion)
			if (sFilename.substr(0, sVersionType.length) === sVersionType)
			{
				sCurVersionType = sVersionType;
				break;
			}

		// use compareVersion to determine which version is >< the other
		if (typeof(sCurVersionType) !== 'undefined')
		{
			if ((this.compareVersions(oHighYour[sCurVersionType], sYourVersion) || oHighYour[sCurVersionType] === '??') && !oLowVersion[sCurVersionType])
				oHighYour[sCurVersionType] = sYourVersion;

			if (this.compareVersions(oHighCurrent[sCurVersionType], ourVersions[sFilename]) || oHighCurrent[sCurVersionType] === '??')
				oHighCurrent[sCurVersionType] = ourVersions[sFilename];

			if (this.compareVersions(sYourVersion, ourVersions[sFilename]))
			{
				oLowVersion[sCurVersionType] = sYourVersion;
				document.getElementById('your' + sFilename).style.color = 'red';
			}
		}
		else if (this.compareVersions(sYourVersion, ourVersions[sFilename]))
			oLowVersion[sCurVersionType] = sYourVersion;

		document.getElementById('our' + sFilename).innerHTML = ourVersions[sFilename];
		document.getElementById('your' + sFilename).innerHTML = sYourVersion;
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

			sYourVersion = document.getElementById('your' + sFilename + this.opt.aKnownLanguages[i]).innerHTML;
			document.getElementById('your' + sFilename + this.opt.aKnownLanguages[i]).innerHTML = sYourVersion;

			if ((this.compareVersions(oHighYour.Languages, sYourVersion) || oHighYour.Languages === '??') && !oLowVersion.Languages)
				oHighYour.Languages = sYourVersion;

			if (this.compareVersions(oHighCurrent.Languages, ourLanguageVersions[sFilename]) || oHighCurrent.Languages === '??')
				oHighCurrent.Languages = ourLanguageVersions[sFilename];

			if (this.compareVersions(sYourVersion, ourLanguageVersions[sFilename]))
			{
				oLowVersion.Languages = sYourVersion;
				document.getElementById('your' + sFilename + this.opt.aKnownLanguages[i]).style.color = 'red';
			}
		}
	}

	// Set the column titles based on the files each contain
	document.getElementById('yoursources').innerHTML = oLowVersion.sources ? oLowVersion.sources : oHighYour.sources;
	document.getElementById('oursources').innerHTML = oHighCurrent.sources;
	if (oLowVersion.sources)
		document.getElementById('yoursources').style.color = 'red';

	document.getElementById('youradmin').innerHTML = oLowVersion.sources ? oLowVersion.sources : oHighYour.sources;
	document.getElementById('ouradmin').innerHTML = oHighCurrent.sources;
	if (oLowVersion.sources)
		document.getElementById('youradmin').style.color = 'red';

	document.getElementById('yourcontrollers').innerHTML = oLowVersion.sources ? oLowVersion.sources : oHighYour.sources;
	document.getElementById('ourcontrollers').innerHTML = oHighCurrent.sources;
	if (oLowVersion.sources)
		document.getElementById('yourcontrollers').style.color = 'red';

	document.getElementById('yourdatabase').innerHTML = oLowVersion.sources ? oLowVersion.sources : oHighYour.sources;
	document.getElementById('ourdatabase').innerHTML = oHighCurrent.sources;
	if (oLowVersion.sources)
		document.getElementById('yourdatabase').style.color = 'red';

	document.getElementById('yoursubs').innerHTML = oLowVersion.sources ? oLowVersion.sources : oHighYour.sources;
	document.getElementById('oursubs').innerHTML = oHighCurrent.sources;
	if (oLowVersion.sources)
		document.getElementById('yoursubs').style.color = 'red';

	document.getElementById('yourdefault').innerHTML = oLowVersion.defaults ? oLowVersion.defaults : oHighYour.defaults;
	document.getElementById('ourdefault').innerHTML = oHighCurrent.defaults;
	if (oLowVersion.defaults)
		document.getElementById('yourdefaults').style.color = 'red';

	// Custom theme in use?
	if (document.getElementById('Templates'))
	{
		document.getElementById('yourTemplates').innerHTML = oLowVersion.Templates ? oLowVersion.Templates : oHighYour.Templates;
		document.getElementById('ourTemplates').innerHTML = oHighCurrent.Templates;

		if (oLowVersion.Templates)
			document.getElementById('yourTemplates').style.color = 'red';
	}

	document.getElementById('yourLanguages').innerHTML = oLowVersion.Languages ? oLowVersion.Languages : oHighYour.Languages;
	document.getElementById('ourLanguages').innerHTML = oHighCurrent.Languages;
	if (oLowVersion.Languages)
		document.getElementById('yourLanguages').style.color = 'red';
};

/**
 * Adds a new word container to the censored word list
 */
function addNewWord()
{
	setOuterHTML(document.getElementById('moreCensoredWords'), '<div class="censorWords"><input type="text" name="censor_vulgar[]" size="30" class="input_text" /> => <input type="text" name="censor_proper[]" size="30" class="input_text" /><' + '/div><div id="moreCensoredWords"><' + '/div>');
}

/**
 * Will enable/disable checkboxes, according to if the BBC globally set or not.
 *
 * @param {string} section id of the container
 * @param {string} disable true or false
 */
function toggleBBCDisabled(section, disable)
{
	elems = document.getElementById(section).getElementsByTagName('*');
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
	curType = document.getElementById("field_type").value;
	privStatus = document.getElementById("private").value;
	document.getElementById("max_length_dt").style.display = curType === "text" || curType === "textarea" ? "" : "none";
	document.getElementById("max_length_dd").style.display = curType === "text" || curType === "textarea" ? "" : "none";
	document.getElementById("dimension_dt").style.display = curType === "textarea" ? "" : "none";
	document.getElementById("dimension_dd").style.display = curType === "textarea" ? "" : "none";
	document.getElementById("bbc_dt").style.display = curType === "text" || curType === "textarea" ? "" : "none";
	document.getElementById("bbc_dd").style.display = curType === "text" || curType === "textarea" ? "" : "none";
	document.getElementById("options_dt").style.display = curType === "select" || curType === "radio" ? "" : "none";
	document.getElementById("options_dd").style.display = curType === "select" || curType === "radio" ? "" : "none";
	document.getElementById("default_dt").style.display = curType === "check" ? "" : "none";
	document.getElementById("default_dd").style.display = curType === "check" ? "" : "none";
	document.getElementById("mask_dt").style.display = curType === "text" ? "" : "none";
	document.getElementById("mask").style.display = curType === "text" ? "" : "none";
	document.getElementById("can_search_dt").style.display = curType === "text" || curType === "textarea" ? "" : "none";
	document.getElementById("can_search_dd").style.display = curType === "text" || curType === "textarea" ? "" : "none";
	document.getElementById("regex_div").style.display = curType === "text" && document.getElementById("mask").value === "regex" ? "" : "none";
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
	setOuterHTML(document.getElementById("addopt"), '<br /><input type="radio" name="default_select" value="' + startOptID + '" id="' + startOptID + '" class="input_radio" /><input type="text" name="select_option[' + startOptID + ']" value="" class="input_text" /><span id="addopt"></span>');
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
 * @param {string} elem
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
	var $new_item = $("#list_news_lists_last").clone();

	last_preview++;
	$new_item.attr('id', 'list_news_lists_' + last_preview);
	$new_item.find('textarea').attr('id', 'data_' + last_preview);
	$new_item.find('#preview_last').attr('id', 'preview_' + last_preview);
	$new_item.find('#box_preview_last').attr('id', 'box_preview_' + last_preview);

	$("#list_news_lists_last").before($new_item);
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

	$id.text(txt_preview).click(function () {
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
	for (var i = 1; i <= 6; i++)
	{
		total += parseInt(document.getElementById('weight' + i + '_val').value);
	}

	document.getElementById('weighttotal').innerHTML = total;
	for (i = 1; i <= 6; i++)
	{
		document.getElementById('weight' + i).innerHTML = (Math.round(1000 * parseInt(document.getElementById('weight' + i + '_val').value) / total) / 10) + '%';
	}
}

/**
 * Toggle visibility of add smile image source options
 */
function switchType()
{
	document.getElementById("ul_settings").style.display = document.getElementById("method-existing").checked ? "none" : "";
	document.getElementById("ex_settings").style.display = document.getElementById("method-upload").checked ? "none" : "";
}

/**
 * Toggle visibility of smiley set should the user want differnt images in a set (add smiley)
 */
function swapUploads()
{
	document.getElementById("uploadMore").style.display = document.getElementById("uploadSmiley").disabled ? "none" : "";
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
	for (i = 0; i < 5; i++)
		sPostData = sPostData + (sPostData.length === 0 ? "" : "&") + oPostData[i] + "=" + escape(document.getElementById(oPostData[i]).value);

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
	ftpTest.className = "right_submit";
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
		if (results[0].getAttribute('success') === 1)
			wasSuccess = true;
		message = results[0].firstChild.nodeValue;
	}

	// place the informative box on screen so the user knows if things went well or poorly
	document.getElementById("ftp_error_div").style.display = "";
	document.getElementById("ftp_error_div").className = wasSuccess ? "successbox" : "errorbox";
	document.getElementById("ftp_error_message").innerHTML = message;
}

/**
 * Part of package manager, expands a folders contents to show permission levels of files it contains
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
	else
	{
		ajax_indicator(true);
		getXMLDocument(elk_prepareScriptUrl(elk_scripturl) + 'action=admin;area=packages;onlyfind=' + escape(folderReal) + ';sa=perms;xml;' + elk_session_var + '=' + elk_session_id, onNewFolderReceived);
	}

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
 * @param {type} cat_id
 * @param {type} elem
 * @param {type} brd_list
 */
function select_in_category(cat_id, elem, brd_list)
{
	for (var brd in brd_list)
		document.getElementById(elem.value + '_brd' + brd_list[brd]).checked = true;

	elem.selectedIndex = 0;
}

/**
 * Server Settings > Caching, toggles input fields on/off as appropriate for
 * a given cache engine selection
 */
function toggleCache ()
{
	var memcache = document.getElementById('cache_memcached'),
		cachedir = document.getElementById('cachedir'),
		cacheuid = document.getElementById('cache_uid'),
		cachepassword = document.getElementById('cache_password');

	// Show the memcache server box only if memcache has been selected
	if (cache_type.value !== "memcached")
	{
		$(memcache).slideUp();
		$(memcache).parent().prev().slideUp(100);
	}
	else
	{
		$(memcache).slideDown();
		$(memcache).parent().prev().slideDown(100);
	}

	// don't show the directory if its not filebased
	if (cache_type.value === "filebased")
	{
		$(cachedir).slideDown();
		$(cachedir).parent().prev().slideDown(100);
	}
	else
	{
		$(cachedir).slideUp(100);
		$(cachedir).parent().prev().slideUp(100);
	}

	// right now only xcache needs the uid/password
	if (cache_type.value === "xcache")
	{
		$(cacheuid).slideDown(100);
		$(cacheuid).parent().prev().slideDown(100);
		$(cachepassword).slideDown(100);
		$(cachepassword).parent().slideDown(100);
		$(cachepassword).parent().prev().slideDown(100);
	}
	else
	{
		$(cacheuid).slideUp(100);
		$(cacheuid).parent().prev().slideUp(100);
		$(cachepassword).slideUp(100);
		$(cachepassword).parent().slideUp(100);
		$(cachepassword).parent().prev().slideUp(100);
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
	{
		dir_elem.disabled = 1;
	}
	else
		dir_elem.disabled = !sub_dir.checked;
}


/**
 * Called from purgeinactive users maintance task, used to show or hide
 * the membergroup list.  If collapsed will select all the member groups if expanded
 * unslect them so the user can choose.
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

	document.getElementById('do_attribute').disabled = valid ? false : true;

	// Keep checking for a valid form so we can activate the submit button
	setTimeout(function() {checkAttributeValidity();}, 500);

	return valid;
}

/**
 * Function for showing which boards to prune in an otherwise hidden list.
 * Used by topic maintenance task, will select all boards when collapsed or allow
 * specific boards to be chosen when expanded
 */
function swapRot()
{
	rotSwap = !rotSwap;

	// Toggle icon
	document.getElementById("rotIcon").src = elk_images_url + (rotSwap ? "/selected_open.png" : "/selected.png");
	document.getElementById("rotText").innerHTML = rotSwap ? maintain_old_choose : maintain_old_all;

	// Toggle panel
	$("#rotPanel").slideToggle(300);

	// Toggle checkboxes
	var rotPanel = document.getElementById("rotPanel"),
		oBoardCheckBoxes = rotPanel.getElementsByTagName("input");

	for (var i = 0; i < oBoardCheckBoxes.length; i++)
	{
		if (oBoardCheckBoxes[i].type.toLowerCase() === "checkbox")
			oBoardCheckBoxes[i].checked = !rotSwap;
	}
}

/**
 * Used in manageMembergroups to enabel disable form elements based on allowable choices
 * If post based group is selected, it will disable moderation selection, visability, group description
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
		oModeratorSuggest.oTextHandle.disabled = isChecked ? true : false;
}

/**
 * Sets up all the js events for edit and save board-specific permission
 * profiles
 */
function initEditProfileBoards()
{
	$('.edit_all_board_profiles').click(function(e) {
		e.preventDefault();

		$('.edit_board').click();
	});
	$('.edit_board').show().click(function(e) {
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
					})
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
	});
}