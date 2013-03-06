/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Alpha
 *
 * This file contains javascript associated with the current theme
 */

$(document).ready(function() {
	// menu drop downs
	if (use_click_menu)
		$('.dropmenu, ul.quickbuttons').superfish({useClick: true, delay : 50, speed: 175, animation: {opacity:'show', height:'toggle'}});
	else
		$('.dropmenu, ul.quickbuttons').superfish({useClick: use_click_menu, delay : 300, speed: 175});

	// Smooth scroll navigation
	$('.topbottom').bind('click', function(event) {
		event.preventDefault();

		// Position to the id pagetop or pagebot
		var link = $('#page' + this.hash.substring(1)),
			link_y = link.height() + 15;

		$('html,body').animate({scrollTop:link.offset().top + link_y - $(window).height()}, 1500);
	});

	// tooltips
	$('.preview').SiteTooltip();

	// find all nested linked images and turn off the border
	$('a.bbc_link img.bbc_img').parent().css('border', '0');

	// Set a auto height so small code blocks collaspe, set a height for larger ones
	// and let resize or overflow do its thing as normal
	$('.bbc_code').each(function()
	{
		$(this).height("auto");
		if ($(this).height() > 200)
			$(this).css('height', '20em');
	});
});

// Toggles the element height and width styles of an image.
function smc_toggleImageDimensions()
{
	var oImages = document.getElementsByTagName('IMG');
	for (oImage in oImages)
	{
		// Not a resized image? Skip it.
		if (oImages[oImage].className === undefined || oImages[oImage].className.indexOf('bbc_img resized') === -1)
			continue;

		oImages[oImage].style.cursor = 'pointer';
		oImages[oImage].onclick = function() {
			this.style.width = this.style.height = this.style.width === 'auto' ? null : 'auto';
		};
	}
}

// Add a load event for the function above.
addLoadEvent(smc_toggleImageDimensions);

// Adds a button to a certain button strip.
function smf_addButton(sButtonStripId, bUseImage, oOptions)
{
	var oButtonStrip = document.getElementById(sButtonStripId);
	var aItems = oButtonStrip.getElementsByTagName('span');

	// Remove the 'last' class from the last item.
	if (aItems.length > 0)
	{
		var oLastSpan = aItems[aItems.length - 1];
		oLastSpan.className = oLastSpan.className.replace(/\s*last/, 'position_holder');
	}

	// Add the button.
	var oButtonStripList = oButtonStrip.getElementsByTagName('ul')[0];
	var oNewButton = document.createElement('li');
	if ('sId' in oOptions)
		oNewButton.id = oOptions.sId;
	setInnerHTML(oNewButton, '<a href="' + oOptions.sUrl + '" ' + ('sCustom' in oOptions ? oOptions.sCustom : '') + '><span class="last"' + ('sId' in oOptions ? ' id="' + oOptions.sId + '_text"': '') + '>' + oOptions.sText + '</span></a>');

	oButtonStripList.appendChild(oNewButton);
}

var error_txts = {};
function errorbox_handler(oOptions)
{
	this.opt = oOptions;
	this.oError_box = null;
	this.oErrorHandle = window;
	this.evaluate = false;
	this.init();
}

errorbox_handler.prototype.init = function ()
{
	if (this.opt.check_id !== undefined)
		this.oChecks_on = $(document.getElementById(this.opt.check_id));
	else if (this.opt.selector !== undefined)
		this.oChecks_on = this.opt.selector;
	else if (this.opt.editor !== undefined)
	{
		this.oChecks_on = eval(this.opt.editor);
		this.evaluate = true;
	}

	this.oErrorHandle.instanceRef = this;

	if (this.oError_box === null)
		this.oError_box = $(document.getElementById(this.opt.error_box_id));

	if (this.evaluate === false)
	{
		this.oChecks_on.attr('onblur', this.opt.self + '.checkErrors()');
		this.oChecks_on.attr('onkeyup', this.opt.self + '.checkErrors()');
	}
	else
	{
		var current_error_handler = this.opt.self;
		$(document).ready(function () {
			var current_error = eval(current_error_handler);
			$('#' + current_error.opt.editor_id).data("sceditor").addEvent(current_error.opt.editor_id, 'keyup', function () {
				current_error.checkErrors();
			});
		});
	}
}

errorbox_handler.prototype.boxVal = function ()
{
	if (this.evaluate === false)
		return this.oChecks_on.val();
	else
		return this.oChecks_on();
}

// Runs the field checks as defined by the object instance
errorbox_handler.prototype.checkErrors = function ()
{
	var num = this.opt.error_checks.length;
	if (num !== 0)
	{
		// Adds the error checking functions
		for (var i = 0; i < num; i++)
		{
			// Get the element that holds the errors
			var $elem = $(document.getElementById(this.opt.error_box_id + "_" + this.opt.error_checks[i].code));

			// Run the efunction check on this field, then add or remove any errors
			if (this.opt.error_checks[i].efunction(this.boxVal()))
				this.addError($elem, this.opt.error_checks[i].code);
			else
				this.removeError(this.oError_box, $elem);
		}

		this.oError_box.attr("class", "noticebox");
	}

	// Hide show the error box based on if we have any errors
	if (this.oError_box.find("li").length === 0)
		this.oError_box.slideUp();
	else
		this.oError_box.slideDown();
}

// Add and error to the list
errorbox_handler.prototype.addError = function(error_elem, error_code)
{
	if (error_elem.length === 0)
	{
		// First error, then set up the list for insertion
		if ($.trim(this.oError_box.children("#" + this.opt.error_box_id + "_list").html()) === '')
			this.oError_box.append("<ul id='" + this.opt.error_box_id + "_list'></ul>");

		// Add the error it and show it
		$(document.getElementById(this.opt.error_box_id + "_list")).append("<li style=\"display:none\" id='" + this.opt.error_box_id + "_" + error_code + "' class='error'>" + error_txts[error_code] + "</li>");
		$(document.getElementById(this.opt.error_box_id + "_" + error_code)).slideDown();
	}
}

// Remove an error from the notice window
errorbox_handler.prototype.removeError = function (error_box, error_elem)
{
	if (error_elem.length !== 0)
	{
		error_elem.slideUp(function() {
			error_elem.remove();

			// No errors at all then close the box
			if (error_box.find("li").length === 0)
				error_box.slideUp();
		});
	}
}