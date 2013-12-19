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
 * This file contains javascript associated with the current theme
 */

$(document).ready(function() {
	// Menu drop downs
	if (use_click_menu)
		$('#main_menu, ul.admin_menu, ul.sidebar_menu, ul.poster, ul.quickbuttons, #sort_by').superclick({speed: 150, animation: {opacity:'show', height:'toggle'}});
	else
		$('#main_menu, ul.admin_menu, ul.sidebar_menu, ul.poster, ul.quickbuttons, #sort_by').superfish({delay : 300, speed: 175});

	// Smooth scroll to top.
	$("a[href=#top]").on("click", function(e) {
		e.preventDefault();
		$("html,body").animate({scrollTop: 0}, 1200);
	});

	// Smooth scroll to bottom.
	$("a[href=#bot]").on("click", function(e) {
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

	// Fix code blocks so they are as compact as possible
	if (typeof elk_codefix === 'function')
		elk_codefix();

	// Enable the ... page expansion
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

	// BBC [img] element toggle for height and width styles of an image.
	$('img').each(function() {
		// Not a resized image? Skip it.
		if ($(this).hasClass('bbc_img resized') === false)
			return true;

		$(this).css({'cursor': 'pointer'});
		$(this).click(function() {
			var $this = $(this);

			// No saved data, then lets set it to autp
			if ($.isEmptyObject($this.data()))
			{
				$this.data("bbc_img", {width: $this.css('width'), height: $this.css('height')});
				$this.css({'width': $this.css('width') === 'auto' ? null : 'auto'});
				$this.css({'height': $this.css('width') === 'auto' ? null : 'auto'});
			}
			else
			{
				// Was clicked and saved, so set it back
				$this.css({'width': $this.data("bbc_img").width});
				$this.css({'height': $this.data("bbc_img").height});

				// Remove the data
				$this.removeData();
			}
		});
	});
});

/**
 * Adds a button to the quick topic moderation after a checkbox is selected
 *
 * @param {string} sButtonStripId
 * @param {boolean} bUseImage
 * @param {object} oOptions
 */
function elk_addButton(sButtonStripId, bUseImage, oOptions)
{
	var oButtonStrip = document.getElementById(sButtonStripId),
		aItems = oButtonStrip.getElementsByTagName('span');

	// Remove the 'last' class from the last item.
	if (aItems.length > 0)
	{
		var oLastSpan = aItems[aItems.length - 1];
		oLastSpan.className = oLastSpan.className.replace(/\s*last/, 'position_holder');
	}

	// Add the button.
	var oButtonStripList = oButtonStrip.getElementsByTagName('ul')[0],
		oNewButton = document.createElement('li'),
		oRole = document.createAttribute('role');

	oRole.value = 'menuitem';
	oNewButton.setAttributeNode(oRole);

	if ('sId' in oOptions)
		oNewButton.id = oOptions.sId;
	oNewButton.innerHTML = '<a class="linklevel1" href="' + oOptions.sUrl + '" ' + ('sCustom' in oOptions ? oOptions.sCustom : '') + '><span class="last"' + ('sId' in oOptions ? ' id="' + oOptions.sId + '_text"': '') + '>' + oOptions.sText + '</span></a>';

	oButtonStripList.appendChild(oNewButton);
}

function loadAddNewPoll(button, id_board, form_name)
{
	if (typeof id_board == 'undefined')
		return true;

	// Find the form and add poll to the url
	var $form = $('#post_header').closest("form");

	// change the label
	if ($(button).val() == poll_add)
	{
		$(button).val(poll_remove);

		// We usually like to have the poll icon associated to polls,
		// but only if the currently selected is the default one
		if ($('#icon').val() == 'xx')
			$('#icon').val('poll').change();

		// Add poll to the form action
		$form.attr('action', $form.attr('action') + ';poll');

		// If the form already exists...just show it back and go out
		if ($('#poll_main').length > 0)
		{
			$('#poll_main, #poll_options').find('input').each(function() {
				if ($(this).data('required') == 'required')
					$(this).attr('required', 'required');
			});

			$('#poll_main, #poll_options').toggle();
			return false;
		}
	}
	else
	{
		if ($('#icon').val() == 'poll')
			$('#icon').val('xx').change();

		// Remove poll to the form action
		$form.attr('action', $form.attr('action').replace(';poll', ''));

		$('#poll_main, #poll_options').hide().find('input').each(function() {
			if ($(this).attr('required') == 'required')
			{
				$(this).data('required', 'required')
				$(this).removeAttr('required');
			}
		});
		$(button).val(poll_add);
		return false;
	}

	// Retrieve the poll area
	$.ajax({
		url: elk_scripturl + '?action=poll;sa=interface;xml;board=' + id_board,
		type: "GET",
		dataType: "html",
		beforeSend: ajax_indicator(true)
	})
	.done(function (data, textStatus, xhr) {
		// Find the highest tabindex already present
		var max_tabIndex = 0;
		for (var i = 0, n = document.forms[form_name].elements.length; i < n; i++)
			max_tabIndex = Math.max(max_tabIndex, document.forms[form_name].elements[i].tabIndex);

		// Inject the html
		$('#post_header').after(data);

		$('#poll_main input, #poll_options input').each(function () {
			$(this).attr('tabindex', ++max_tabIndex);
		});

		// Repeated collapse/expand of fieldsets as above
		$('#poll_main legend, #poll_options legend').click(function(){
			$(this).siblings().slideToggle("fast");
			$(this).parent().toggleClass("collapsed");
		}).each(function () {
			if ($(this).data('collapsed'))
			{
				$(this).siblings().css({display: "none"});
				$(this).parent().toggleClass("collapsed");
			}
		});
	})
	.always(function() {
		// turn off the indicator
		ajax_indicator(false);
	});

	return false;
}