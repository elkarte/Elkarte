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
 * This file contains javascript associated with the user profile
 */

/**
 * Profile tabs (summary, recent, buddy), for use with jqueryUI
 */
function start_tabs()
{
	$("#tabs").tabs({
		ajaxOptions: {
			dataType: "xml",
		},
		// Called before tab content is loaded with href
		beforeLoad: function (event, ui)
		{
			// The ubiquitous ajax spinner
			ui.panel.html('<div class="centertext"><i class="icon icon-big i-oval"></i></div>');

			// Ajax call failed to retrieve content
			ui.jqXHR.fail(function (jqXHR, textStatus, errorThrown)
			{
				ui.panel.html('<div></div>');
				if ('console' in window && console.info)
				{
					console.info(textStatus);
					console.info(errorThrown);
				}
			});
		}
	});
}

/**
 * Function to detect the time offset and populate the offset box
 *
 * @param {string} currentTime
 */
function autoDetectTimeOffset(currentTime)
{
	let localTime = new Date(),
		serverTime;

	if (typeof (currentTime) !== 'string')
	{
		serverTime = currentTime;
	}
	else
	{
		serverTime = new Date(currentTime);
	}

	// Something wrong?
	if (!localTime.getTime() || !serverTime.getTime())
	{
		return 0;
	}

	// Get the difference between the two, set it up so that the sign will tell us who is ahead of who.
	let diff = Math.round((localTime.getTime() - serverTime.getTime()) / 3600000);

	// Make sure we are limiting this to one day's difference.
	diff %= 24;

	return diff;
}

/**
 * Calculates the number of available characters remaining when filling in the signature box
 */
function calcCharLeft(init, event = {})
{
	let currentSignature = document.forms.creator.signature.value,
		currentChars = 0;

	if (!document.getElementById('signatureLeft'))
	{
		return;
	}

	init = typeof init !== 'undefined' ? init : false;

	currentChars = currentSignature.replace(/\r/, "").length;

	if (currentChars > maxLength)
	{
		document.getElementById("signatureLeft").className = "error";
	}
	else
	{
		document.getElementById("signatureLeft").className = "";
	}

	let profileError = document.getElementById('profile_error');
	if (currentChars > maxLength && window.getComputedStyle(profileError).display !== 'none')
	{
		ajax_getSignaturePreview(false);
	}
	// Only hide it if the only errors were signature errors...
	// @todo with so many possible signature errors, this needs to be enhanced
	else if (currentChars <= maxLength && window.getComputedStyle(profileError).display !== 'none' && !init)
	{
		// Check if #list_errors element exist
		let errorList = document.getElementById('list_errors');
		if (errorList)
		{
			// Remove any signature errors
			let signatureErrors = errorList.querySelectorAll('.signature_error');
			signatureErrors.forEach(function (elem) {
				errorList.removeChild(elem);
			});

			let listItems = errorList.querySelectorAll('li');
			if (listItems.length === 0)
			{
				if (profileError)
				{
					profileError.style.display = "none";
					profileError.innerHTML = '';
				}
			}
		}
	}

	document.getElementById('signatureLeft').innerHTML = maxLength - currentChars;
}

/**
 * Gets the signature preview via ajax and populates the preview box
 *
 * @param {boolean} showPreview
 */
function ajax_getSignaturePreview(showPreview)
{
	showPreview = (typeof showPreview === 'undefined') ? false : showPreview;

	let postData = serialize({
		item: 'sig_preview',
		signature: document.getElementById('signature').value,
		user: document.querySelector('input[name="u"]').value
	});

	let profileError = document.getElementById('profile_error'),
		profileErrorVisible = window.getComputedStyle(profileError).display !== 'none';

	fetch(elk_prepareScriptUrl(elk_scripturl) + 'action=XmlPreview;api=xml', {
		method: 'POST',
		body: postData,
		headers: {
			'X-Requested-With': 'XMLHttpRequest',
			'Content-Type': 'application/x-www-form-urlencoded',
			'Accept': 'application/xml'
		}
	})
		.then(response => {
			if (!response.ok)
			{
				throw new Error("HTTP error " + response.status);
			}
			return response.text();
		})
		.then(request => {
			let parser = new DOMParser(),
				xmlDoc = parser.parseFromString(request, "text/xml");

			if (showPreview)
			{
				let signatures = ['current', 'preview'];
				for (let i = 0; i < signatures.length; i++)
				{
					document.getElementById(signatures[i] + "_signature").style.display = "block";
					document.getElementById(signatures[i] + "_signature_display").style.display = "block";
					document.getElementById(signatures[i] + "_signature_display").innerHTML = xmlDoc.querySelector('[type="' + signatures[i] + '"]').textContent + '<hr>';
				}
			}

			let errorElement = xmlDoc.querySelector('error');
			if (errorElement)
			{
				// Populate and show the hidden profile_error div
				if (!profileErrorVisible)
				{
					profileError.innerHTML = '<span>' + xmlDoc.querySelector('[type="errors_occurred"]').textContent + '</span><ul id="list_errors"></ul>';
					profileError.style.display = 'block';
				}
				else
				{
					let list_errors = document.getElementById('list_errors'),
						errors = list_errors.querySelectorAll('.signature_error');

					errors.forEach(error => error.remove());
				}

				let errors = xmlDoc.querySelectorAll('[type="error"]'),
					errors_list = '';

				errors.forEach(error => {
					errors_list += '<li class="signature_error">' + error.textContent + '</li>';
				});
				document.getElementById("list_errors").innerHTML = errors_list;
			}
			// No errors, clear any previous signature related ones
			else
			{
				let list_error = document.getElementById('list_errors');
				if (list_error)
				{
					let errors = list_error.querySelectorAll('.signature_error');

					errors.forEach(error => error.remove());

					// Nothing remaining, hide and clear the profile_error div
					if (!list_error.hasChildNodes())
					{
						profileError.style.display = 'none';
						profileError.innerHTML = '';
					}
				}
			}

			return false;
		})
		.catch((error) => {
			if ('console' in window && console.info)
			{
				console.info('Error: ', error);
			}
		});

	return false;
}

/**
 * Allows previewing of server stored avatars.
 *
 * @param {string} selected
 */
function changeSel(selected)
{
	if (cat.selectedIndex === -1)
	{
		return;
	}

	if (cat.options[cat.selectedIndex].value.indexOf("/") > 0)
	{
		let i,
			count = 0;

		file.style.display = "inline";
		file.disabled = false;

		for (i = file.length; i >= 0; i -= 1)
			file.options[i] = null;

		for (i = 0; i < files.length; i++)
			if (files[i].indexOf(cat.options[cat.selectedIndex].value) === 0)
			{
				let filename = files[i].substr(files[i].indexOf("/") + 1),
					showFilename = filename.substr(0, filename.lastIndexOf("."));

				showFilename = showFilename.replace(/[_]/g, " ");

				file.options[count] = new Option(showFilename, files[i]);

				if (filename === selected)
				{
					if (file.options.defaultSelected)
					{
						file.options[count].defaultSelected = true;
					}
					else
					{
						file.options[count].selected = true;
					}
				}

				count++;
			}

		if (file.selectedIndex === -1 && file.options[0])
		{
			file.options[0].selected = true;
		}

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

function init_avatars()
{
	var avatar = document.getElementById("avatar");

	// If we are using an avatar from the gallery, let's load it
	if (avatar !== null)
	{
		changeSel(selavatar);
	}

	// And now show the proper interface for the selected avatar type
	swap_avatar();
}

// Show the right avatar based on what radio button they just selected
function swap_avatar()
{
	let nodeList = document.querySelectorAll('#avatar_choices input'),
		inputs = Array.from(nodeList),
		choice;

	inputs.forEach(function (input) {
		choice = document.getElementById(input.id.replace('_choice', ''));

		if (choice !== null)
		{
			if (input.checked)
			{
				choice.style.display = 'block';
			}
			else
			{
				choice.style.display = 'none';
			}
		}
	});

	return true;
}

/**
 * Updates the avatar img preview with the selected one
 */
function showAvatar()
{
	if (file.selectedIndex === -1)
	{
		return;
	}

	let oAvatar = document.getElementById('avatar');

	oAvatar.src = avatardir + file.options[file.selectedIndex].value;
	oAvatar.alt = file.options[file.selectedIndex].text;
	oAvatar.style.width = '';
	oAvatar.style.height = '';
}

/**
 * Allows for the previewing of an externally stored avatar.
 *
 * Sets an error if the image is over size limits
 *
 * @param {string} src
 */
function previewExternalAvatar(src)
{
	let oSid = document.getElementById('external');

	// Assign the source to the image tag
	oSid.src = src;

	// Create a new image element
	let img = new Image();
	img.onload = function () {
		// You have access to naturalWidth and naturalHeight here
		if (refuse_too_large &&
			((maxWidth !== 0 && this.naturalWidth > maxWidth) || (maxHeight !== 0 && this.naturalHeight > maxHeight)))
		{
			document.getElementById('avatar_external').classList.add('error');
		}
		else
		{
			document.getElementById('avatar_external').classList.remove('error');
		}
	};

	img.src = src;
}

/**
 * Allows for the previewing of an uploaded avatar
 *
 * @param {object} src
 */
function previewUploadedAvatar(src)
{
	if (src.files && src.files[0])
	{
		let reader = new FileReader();

		reader.readAsDataURL(src.files[0]);
		reader.onload = function ()
		{
			let current_avatar = document.getElementById('current_avatar'),
				current_avatar_new = document.getElementById('current_avatar_new'),
				current_avatar_new_preview = document.getElementById('current_avatar_new_preview');

			current_avatar_new_preview.src = String(reader.result);
			current_avatar_new.classList.remove('hide');
			current_avatar.classList.add('hide');
		};
	}
}

/**
 * This function modifies the behavior of the warning notification feature.
 * It enables or disables certain elements based on the checked state of the 'warn_notify' checkbox.
 * It handles the warning template preview
 *
 * @returns {boolean} - Returns 'false' to prevent the default behavior of the event.
 */
function modifyWarnNotify()
{
	let disable = !document.getElementById('warn_notify').checked;

	document.getElementById('warn_sub').disabled = disable;
	document.getElementById('warn_body').disabled = disable;
	document.getElementById('warn_temp').disabled = disable;
	document.getElementById('new_template_link').style.display = disable ? 'none' : 'inline-block';

	document.getElementById('preview_button').style.display = disable ? 'none' : 'inline-block';
	document.getElementById('preview_button').addEventListener('click', (event) => {
		event.preventDefault();
		let postData = serialize({
			'item': 'warning_preview',
			'title': document.getElementById('warn_sub').value,
			'body': document.getElementById('warn_body').value,
			'issuing': 'true'
		});

		fetch(elk_prepareScriptUrl(elk_scripturl) + 'action=XmlPreview;api=xml', {
			method: 'POST',
			body: postData,
			headers: {
				'X-Requested-With': 'XMLHttpRequest',
				'Content-Type': 'application/x-www-form-urlencoded',
				'Accept': 'application/xml'
			}
		})
			.then(response => {
				if (!response.ok)
				{
					throw new Error("HTTP error " + response.status);
				}
				return response.text();
			})
			.then(text => new DOMParser().parseFromString(text, 'text/xml'))
			.then(request => {
				let preview = document.getElementById('box_preview'),
					preview_body = document.getElementById('body_preview'),
					profile_error = document.getElementById('profile_error'),
					errorNodeList = request.getElementsByTagName('error'),
					errors = Array.from(errorNodeList);

				// Show the preview area and populate the text
				preview.style.display = 'block';
				preview_body.innerHTML = request.getElementsByTagName('body')[0].textContent;

				if (errors.length)
				{
					profile_error.style.display = 'block';

					let errors_html = '<span>' + profile_error.querySelector("span").innerHTML + '</span><ul class="list_errors">';
					errors.forEach(error => {
						errors_html += '<li>' + error.textContent + '</li>';
					});
					errors_html += '</ul>';

					profile_error.innerHTML = errors_html;
					window.scrollTo({top: profile_error.offsetTop, behavior: 'smooth'});
				}
				else
				{
					profile_error.style.display = 'none';
					let errorList = document.getElementById('error_list');
					if (errorList)
						errorList.innerHTML = '';
					window.scrollTo({top: preview.offsetTop, behavior: 'smooth'});
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
	});
}

/**
 * onclick function, triggered in response to selecting + or - in the warning screen
 * Increases the warning level by a defined amount.  Uses jqueryUI slider
 *
 * @param {string} sliderID
 * @param {string} levelID
 * @param {int[]} levels
 */
function initWarnSlider(sliderID, levelID, levels)
{
	var $_levelID = $("#" + levelID),
		$_sliderID = $("#" + sliderID);

	$_sliderID.slider({
		range: "min",
		min: 0,
		max: 100,
		slide: function (event, ui)
		{
			$_levelID.val(ui.value);

			$(this).removeClass("watched moderated muted");

			if (ui.value >= levels[3])
			{
				$(this).addClass("muted");
			}
			else if (ui.value >= levels[2])
			{
				$(this).addClass("moderated");
			}
			else if (ui.value >= levels[1])
			{
				$(this).addClass("watched");
			}
		},
		change: function (event, ui)
		{
			$_levelID.val(ui.value);

			$(this).removeClass("watched moderated muted");

			if (ui.value >= levels[3])
			{
				$(this).addClass("muted");
			}
			else if (ui.value >= levels[2])
			{
				$(this).addClass("moderated");
			}
			else if (ui.value >= levels[1])
			{
				$(this).addClass("watched");
			}
		}
	}).slider("value", $_levelID.val());

	// Just in case someone wants to type, let's keep the two in sync
	$_levelID.on('keyup', function ()
	{
		let val = Math.max(0, Math.min(100, $(this).val()));

		$_sliderID.slider("value", val);
	});
}

/**
 * Fills the warning template box based on the one chosen by the user
 */
function populateNotifyTemplate()
{
	let index = document.getElementById('warn_temp').value;

	// No selection means no template
	if (index === -1)
	{
		return false;
	}

	// Otherwise see what we can do...
	for (let key in templates)
	{
		// Found the template, load it and stop
		if (index === key)
		{
			document.getElementById('warn_body').value = templates[key];
			break;
		}
	}
}
