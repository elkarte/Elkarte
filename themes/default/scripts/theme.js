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
		$('#main_menu, ul.admin_menu, ul.sidebar_menu, ul.poster, ul.quickbuttons, #sort_by').superclick({speed: 150, animation: {opacity:'show', height:'toggle'}});
	else
		$('#main_menu, ul.admin_menu, ul.sidebar_menu, ul.poster, ul.quickbuttons, #sort_by').superfish({delay : 300, speed: 175});

	// Smooth scroll to top.
	$("a[href=#top]").bind("click", function(e) {
		e.preventDefault();
		$("html,body").animate({scrollTop: 0}, 1200);
	});

	// Smooth scroll to bottom.
	$("a[href=#bot]").bind("click", function(e) {
		e.preventDefault();

		// Don't scroll all the way down to the footer, just the content bottom
		var link = $('#bot'),
		link_y = link.height();

		$("html,body").animate({scrollTop:link.offset().top + link_y - $(window).height()}, 1200);
	});

	// Tooltips
	$('.preview').SiteTooltip({hoverIntent: {sensitivity: 10, interval: 750, timeout: 50}});

	// Find all nested linked images and turn off the border
	$('a.bbc_link img.bbc_img').parent().css('border', '0');

	// Fix code blocks
	if (typeof elk_codefix === 'function')
		elk_codefix();

	$('.expand_pages').expand_pages();

	// Collapsabile fieldsets, pure candy
	$('legend').click(function(){
		$(this).siblings().slideToggle("fast");
		$(this).parent().toggleClass("collapsed");
	}).each(function () {
		if ($(this).data('collapsed'))
		{
			$(this).siblings().css({display: "none"});
			$(this).parent().toggleClass("collapsed");
		}
	});

	// Spoiler
	$('.spoilerheader').click(function() {
		$(this).next().children().slideToggle("fast");
	});
});

// Toggles the element height and width styles of an image.
function elk_ToggleImageDimensions()
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
addLoadEvent(elk_ToggleImageDimensions);

// Adds a button to a certain button strip.
function elk_addButton(sButtonStripId, bUseImage, oOptions)
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
	var oRole = document.createAttribute('role');
	oRole.value = 'menuitem';
	oNewButton.setAttributeNode(oRole)

	if ('sId' in oOptions)
		oNewButton.id = oOptions.sId;
	setInnerHTML(oNewButton, '<a class="linklevel1" href="' + oOptions.sUrl + '" ' + ('sCustom' in oOptions ? oOptions.sCustom : '') + '><span class="last"' + ('sId' in oOptions ? ' id="' + oOptions.sId + '_text"': '') + '>' + oOptions.sText + '</span></a>');

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

// @todo this code works well almost only with the editor I think.
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

		this.oError_box.attr("class", "warningbox");
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

/**
 * This is called from the editor plugin or display.template to set where to
 * find the cache values for use in revalidateMentions
 *
 * @param {string} selector id of element that atWho is attached to
 * @param {object} oOptions only set when called from the pluging, contains those options
 */
var all_elk_mentions = [];
function add_elk_mention(selector, oOptions)
{
	// Global does not exist, hummm
	if (all_elk_mentions.hasOwnProperty(selector))
		return;

	// No options means its attached to the plain text box
	if (typeof oOptions === 'undefined')
		oOptions = {};
	oOptions.selector = selector;

	// Add it to the stack
	all_elk_mentions[all_elk_mentions.length] = {
		selector: selector,
		oOptions: oOptions
	};
}

/**
 * Used to tag mentioned names when they are entered inline but NOT selected from the dropdown list
 * The name must have appeared in the dropdown and be found in that cache list
 *
 * @param {string} sForm the form that holds the container, only used for plain text QR
 * @param {string} sInput the container that atWho is attached
 */
function revalidateMentions(sForm, sInput)
{
	var cached_names,
		cached_queries,
		body,
		mentions;

	for (var i = 0, count = all_elk_mentions.length; i < count; i++)
	{
		// Make sure this mention object is for this selector, saftey first
		if (all_elk_mentions[i].selector === sInput || all_elk_mentions[i].selector === '#' + sInput)
		{
			// Was this invoked as the editor plugin?
			if (all_elk_mentions[i].oOptions.isPlugin)
			{
				var $editor = $('#' + all_elk_mentions[i].selector).data("sceditor");

				cached_names = $editor.opts.mentionOptions.cache.names;
				cached_queries = $editor.opts.mentionOptions.cache.queries;

				// Clean up the newlines and spacing so we can find the @mentions
				body = $editor.getText().replace(/[\u00a0\r\n]/g, ' ');
				mentions = $($editor.opts.mentionOptions.cache.mentions);
			}
			// Or just our plain text quick reply box?
			else
			{
				cached_names = all_elk_mentions[i].oMention.cached_names;
				cached_queries = all_elk_mentions[i].oMention.cached_queries;

				// Keep everying sepeteate with spaces, not newlines or no breakable
				body = document.forms[sForm][sInput].value.replace(/[\u00a0\r\n]/g, ' ');

				// The last pulldown box that atWho populated
				mentions = $(all_elk_mentions[i].oMention.mentions);
			}

			// Adding a space at the beginning to facilitate catching of mentions at the 1st char
			// and one at the end to simplify catching any aa last thing in the text
			body = ' ' + body + ' ';

			// First check if all those in the list are really mentioned
			$(mentions).find('input').each(function (idx, elem) {
				var name = $(elem).data('name'),
					next_char,
					prev_char,
					index = body.indexOf(name);

				// It is undefined coming from a preview
				if (typeof(name) !== 'undefined')
				{
					if (index === -1)
						$(elem).remove();
					else
					{
						next_char = body.charAt(index + name.length);
						prev_char = body.charAt(index - 1);

						if (next_char !== '' && next_char.localeCompare(" ") !== 0)
							$(elem).remove();
						else if (prev_char !== '' && prev_char.localeCompare(" ") !== 0)
							$(elem).remove();
					}
				}
			});

			for (var k = 0, ccount = cached_queries.length; k < ccount; k++)
			{
				names = cached_names[cached_queries[k]];
				for (var l = 0, ncount = names.length; l < ncount; l++)
					if (body.indexOf(' @' + names[l].name + ' ') !== -1)
						mentions.append($('<input type="hidden" name="uid[]" />').val(names[l].id));
			}
		}
	}
}