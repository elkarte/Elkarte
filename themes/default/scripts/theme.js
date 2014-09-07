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
 * @version 1.0
 *
 * This file contains javascript associated with the current theme
 */

$(document).ready(function() {
	// Menu drop downs
	if (use_click_menu)
		$('#main_menu, ul.admin_menu, ul.sidebar_menu, ul.poster, ul.quickbuttons, #sort_by').superclick({speed: 150, animation: {opacity:'show', height:'toggle'}, speedOut: 0, activeClass: 'sfhover'});
	else
		$('#main_menu, ul.admin_menu, ul.sidebar_menu, ul.poster, ul.quickbuttons, #sort_by').superfish({delay : 300, speed: 175, hoverClass: 'sfhover'});

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
			$(this).click();
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

		// Note to addon authors, if you want to enable your own click events to bbc images
		// you can turn off this namespaced click event with $("img").off("click.elk_bbc")
		$(this).on( "click.elk_bbc", function() {
			var $this = $(this);

			// No saved data, then lets set it to auto
			if ($.isEmptyObject($this.data()))
			{
				$this.data("bbc_img", {width: $this.css('width'), height: $this.css('height')});
				$this.css({'width': $this.css('width') === 'auto' ? null : 'auto'});
				$this.css({'height': $this.css('width') === 'auto' ? null : 'auto'});

				// Overide default css to allow the image to expand fully, add a div to exand in
				$this.css({'max-width': 'none'});
				$this.wrap('<div style="overflow: auto"></div>');
			}
			else
			{
				// Was clicked and saved, so set it back
				$this.css({'width': $this.data("bbc_img").width});
				$this.css({'height': $this.data("bbc_img").height});

				// Remove the data
				$this.removeData();

				// Remove the div we added to allow the image to overflow expand in
				$this.unwrap();
				$this.css({'max-width': '100%'});

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