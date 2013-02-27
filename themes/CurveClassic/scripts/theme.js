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
 */

$(document).ready(function() {
	// menu drop downs
	$('.dropmenu').superfish({delay : 600, speed: 250, sensitivity : 8, interval : 50, timeout : 1});

	// tooltips
	$('.preview').SiteTooltip();

	// find all nested linked images and turn off the border
	$('a.bbc_link img.bbc_img').parent().css('border', '0');
});

// The purpose of this code is to fix the height of overflow: auto blocks, because some browsers can't figure it out for themselves.
function smf_codeBoxFix()
{
	var codeFix = document.getElementsByTagName('code');
	for (var i = codeFix.length - 1; i >= 0; i--)
	{
		if (is_webkit && codeFix[i].offsetHeight < 20)
			codeFix[i].style.height = (codeFix[i].offsetHeight + 20) + 'px';

		else if (is_ff && (codeFix[i].scrollWidth > codeFix[i].clientWidth || codeFix[i].clientWidth == 0))
			codeFix[i].style.overflow = 'scroll';

		else if ('currentStyle' in codeFix[i] && codeFix[i].currentStyle.overflow == 'auto' && (codeFix[i].currentStyle.height == '' || codeFix[i].currentStyle.height == 'auto') && (codeFix[i].scrollWidth > codeFix[i].clientWidth || codeFix[i].clientWidth == 0) && (codeFix[i].offsetHeight != 0))
			codeFix[i].style.height = (codeFix[i].offsetHeight + 24) + 'px';
	}
}

// Add a fix for code stuff?
if (is_ie || is_webkit || is_ff)
	addLoadEvent(smf_codeBoxFix);

// Toggles the element height and width styles of an image.
function smc_toggleImageDimensions()
{
	var oImages = document.getElementsByTagName('IMG');
	for (oImage in oImages)
	{
		// Not a resized image? Skip it.
		if (oImages[oImage].className == undefined || oImages[oImage].className.indexOf('bbc_img resized') == -1)
			continue;

		oImages[oImage].style.cursor = 'pointer';
		oImages[oImage].onclick = function() {
			this.style.width = this.style.height = this.style.width == 'auto' ? null : 'auto';
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
	setInnerHTML(oNewButton, '<a href="' + oOptions.sUrl + '" ' + ('sCustom' in oOptions ? oOptions.sCustom : '') + '><span class="last"' + ('sId' in oOptions ? ' id="' + oOptions.sId + '"': '') + '>' + oOptions.sText + '</span></a>');

	oButtonStripList.appendChild(oNewButton);
}

var error_txts = {};
function errorbox_handler(oOptions)
{
	this.opt = oOptions;
	this.error_box = null;
	this.oErrorHandle = window;
	this.eval = false;
	this.init();
}

errorbox_handler.prototype.init = function ()
{
	
	if (this.opt.check_id != undefined)
		this.checks_on = $(document.getElementById(this.opt.check_id));
	else if (this.opt.selector != undefined)
		this.checks_on = this.opt.selector;
	else if (this.opt.editor != undefined)
	{
		this.checks_on = eval(this.opt.editor);
		this.eval = true;
	}

	this.oErrorHandle.instanceRef = this;

	if (this.error_box == null)
		this.error_box = $(document.getElementById(this.opt.error_box_id));

	if (this.eval === false)
	{
		this.checks_on.attr('onblur', this.opt.self + '.checkErrors()');
		this.checks_on.attr('onkeyup', this.opt.self + '.checkErrors()');
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
	if (this.eval === false)
		return this.checks_on.val();
	else
		return this.checks_on();
}

errorbox_handler.prototype.checkErrors = function ()
{
	if (this.opt.error_checks.length != 0)
	{
		// Adds the error checking functions
		for (var i = 0; i < this.opt.error_checks.length; i++)
		{
			var $elem = $(document.getElementById(this.opt.error_box_id + "_" + this.opt.error_checks[i].code));
			if (this.opt.error_checks[i].function(this.boxVal()))
				this.addError($elem, this.opt.error_checks[i].code);
			else
				this.removeError($elem, this.opt.error_checks[i].code);
		}

		this.error_box.attr("class", "errorbox");
	}
	if (this.error_box.find("li").length == 0)
		this.error_box.slideUp();
	else
		this.error_box.slideDown();
}

errorbox_handler.prototype.addError = function (error_elem, error_code)
{
	if (error_elem.length == 0)
	{
		if ($.trim(this.error_box.children("#" + this.opt.error_box_id + "_list").html()) == '')
			this.error_box.append("<ul id='" + this.opt.error_box_id + "_list'></ul>");
		$(document.getElementById(this.opt.error_box_id + "_list")).append("<li style=\"display:none\" id='" + this.opt.error_box_id + "_" + error_code + "' class='error'>" + error_txts[error_code] + "</li>");
		$(document.getElementById(this.opt.error_box_id + "_" + error_code)).slideDown();
	}
}

errorbox_handler.prototype.removeError = function (error_elem, error_code)
{
	if (error_elem.length != 0)
		error_elem.slideUp(function() {
			error_elem.remove();
		});
}
