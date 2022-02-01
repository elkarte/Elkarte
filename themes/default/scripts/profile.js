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

$(function ()
{
	// Profile options changing karma
	$('#karma_good, #karma_bad').on('keyup', function ()
	{
		var good = parseInt($('#karma_good').val()),
			bad = parseInt($('#karma_bad').val());

		$('#karmaTotal').text((isNaN(good) ? 0 : good) - (isNaN(bad) ? 0 : bad));
	});
	$('.toggle_notify').on('change', function ()
	{
		if (this.checked)
		{
			$('#' + this.id + '_method').fadeIn('fast');
		}
		else
		{
			$('#' + this.id + '_method').fadeOut('fast');
		}
	}).trigger('change');
});

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
				if ('console' in window)
				{
					window.console.info(textStatus);
					window.console.info(errorThrown);
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
var localTime = new Date();

function autoDetectTimeOffset(currentTime)
{
	var serverTime;

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
	var diff = Math.round((localTime.getTime() - serverTime.getTime()) / 3600000);

	// Make sure we are limiting this to one day's difference.
	diff %= 24;

	return diff;
}

/**
 * Calculates the number of available characters remaining when filling in the
 * signature box
 */
let oldSignature = "";
function calcCharLeft(init)
{
	var currentSignature = document.forms.creator.signature.value,
		currentChars = 0;

	if (!document.getElementById("signatureLeft"))
	{
		return;
	}

	init = typeof init !== 'undefined' ? init : false;

	if (oldSignature !== currentSignature)
	{
		oldSignature = currentSignature;
		currentChars = currentSignature.replace(/\r/, "").length;

		if (currentChars > maxLength)
		{
			document.getElementById("signatureLeft").className = "error";
		}
		else
		{
			document.getElementById("signatureLeft").className = "";
		}

		let $_profile_error = $("#profile_error");
		if (currentChars > maxLength && !$_profile_error.is(":visible"))
		{
			ajax_getSignaturePreview(false);
		}
		else if (currentChars <= maxLength && $_profile_error.is(":visible") && !init)
		{
			$_profile_error.css({display: "none"});
			$_profile_error.html('');
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
		url: elk_scripturl + "?action=XmlPreview;api=xml",
		data: {item: "sig_preview", signature: $("#signature").val(), user: $('input[name="u"]').attr("value")},
		context: document.body
	})
		.done(function (request)
		{
			var i = 0;

			if (showPreview)
			{
				var signatures = ["current", "preview"];
				for (i = 0; i < signatures.length; i++)
				{
					$("#" + signatures[i] + "_signature").css({display: "block"});
					$("#" + signatures[i] + "_signature_display").css({display: "block"}).html($(request).find('[type="' + signatures[i] + '"]').text() + '<hr />');
				}

				$('.spoilerheader').on('click', function ()
				{
					$(this).next().children().slideToggle("fast");
				});
			}

			var $_profile_error = $("#profile_error");

			if ($(request).find("error").text() !== '')
			{
				if (!$_profile_error.is(":visible"))
				{
					$_profile_error.css({
						display: "",
						position: "fixed",
						top: 0,
						left: 0,
						width: "100%",
						'z-index': '100'
					});
				}

				var errors = $(request).find('[type="error"]'),
					errors_html = '<span>' + $(request).find('[type="errors_occurred"]').text() + '</span><ul>';

				for (i = 0; i < errors.length; i++)
					errors_html += '<li>' + $(errors).text() + '</li>';

				errors_html += '</ul>';
				$(document).find("#profile_error").html(errors_html);
			}
			else
			{
				$_profile_error.css({display: "none"});
				$_profile_error.html('');
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
	{
		return;
	}

	if (cat.options[cat.selectedIndex].value.indexOf("/") > 0)
	{
		var i,
			count = 0;

		file.style.display = "inline";
		file.disabled = false;

		for (i = file.length; i >= 0; i -= 1)
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
	$('#avatar_choices').find('input').each(function ()
	{
		var choice_id = $(this).attr('id');

		if ($(this).is(':checked'))
		{
			$('#' + choice_id.replace('_choice', '')).css({display: 'block'});
		}
		else
		{
			$('#' + choice_id.replace('_choice', '')).css({display: 'none'});
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

	var oAvatar = document.getElementById("avatar");

	oAvatar.src = avatardir + file.options[file.selectedIndex].value;
	oAvatar.alt = file.options[file.selectedIndex].text;
	oAvatar.style.width = "";
	oAvatar.style.height = "";
}

/**
 * Allows for the previewing of an externally stored avatar
 *
 * @param {string} src
 */
function previewExternalAvatar(src)
{
	var oSid = document.getElementById("external");

	// Assign the source to the image tag
	oSid.src = src;

	// Create an in-memory element to measure the real size of the image
	$('<img />').on('load', function ()
	{
		if (refuse_too_large && ((maxWidth !== 0 && this.width > maxWidth) || (maxHeight !== 0 && this.height > maxHeight)))
		{
			$('#avatar_external').addClass('error');
		}
		else
		{
			$('#avatar_external').removeClass('error');
		}
	}).attr('src', src);
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
				current_avatar_new = document.getElementById('current_avatar_new');

			current_avatar_new.src = String(reader.result);
			current_avatar_new.classList.remove('hide');
			current_avatar.classList.add('hide');
		};
	}
}

/**
 * Disable notification boxes as required.  This is in response to selecting the
 * notify user checkbox in the issue a warning screen
 */
function modifyWarnNotify()
{
	var disable = !document.getElementById('warn_notify').checked;

	document.getElementById('warn_sub').disabled = disable;
	document.getElementById('warn_body').disabled = disable;
	document.getElementById('warn_temp').disabled = disable;
	document.getElementById('new_template_link').style.display = disable ? 'none' : 'inline-block';
	document.getElementById('preview_button').style.display = disable ? 'none' : 'inline-block';

	$("#preview_button").on('click', function ()
	{
		$.ajax({
			type: "POST",
			url: elk_scripturl + "?action=XmlPreview;api=xml",
			data: {
				item: "warning_preview",
				title: $("#warn_sub").val(),
				body: $("#warn_body").val(),
				issuing: true
			},
			context: document.body
		})
			.done(function (request)
			{
				var $_preview = $("#box_preview"),
					$_profile_error = $("#profile_error");

				$_preview.show();
				$_preview.html($(request).find('body').text());

				if ($(request).find("error").text() !== '')
				{
					$_profile_error.show();
					var errors_html = '<span>' + $_profile_error.find("span").html() + '</span>' + '<ul class="list_errors">';

					$(request).find('error').each(function ()
					{
						errors_html += '<li>' + $(this).text() + '</li>';
					});
					errors_html += '</ul>';
					$_profile_error.html(errors_html);
					$('html, body').animate({scrollTop: $_profile_error.offset().top}, 'slow');
				}
				else
				{
					$_profile_error.hide();
					$("#error_list").html('');
					$('html, body').animate({scrollTop: $("#box_preview").offset().top}, 'slow');
				}

				return false;
			});

		return false;
	});
}

/**
 * onclick function, triggered in response to selecting + or - in the warning screen
 * Increases the warning level by a defined amount
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

	// Just in case someone wants to type, let's keep the two in synch
	$_levelID.on('keyup', function ()
	{
		var val = Math.max(0, Math.min(100, $(this).val()));

		$_sliderID.slider("value", val);
	});
}

/**
 * Fills the warning template box based on the one chosen by the user
 */
function populateNotifyTemplate()
{
	var index = document.getElementById('warn_temp').value;

	// No selection means no template
	if (index === -1)
	{
		return false;
	}

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
