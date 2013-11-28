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
			var $this = $(this),
				height,
				width;

			// No saved data, then lets set it to autp
			if ($.isEmptyObject($this.data()))
			{
				$this.data( "original", { width: $this.css('width'), height: $this.css('height')});
				$this.css({'width': $this.css('width') === 'auto' ? null : 'auto'});
				$this.css({'height': $this.css('width') === 'auto' ? null : 'auto'});
			}
			else
			{
				// Was clicked and saved, so set it back
				height = $this.data("original").height;
				width = $this.data("original").width;
				$this.css({'width': width});
				$this.css({'height': height});

				// Remove the data
				$this.removeData();
			}
		});
	});
});

/**
 * Adds a button to the quick topic moderation after a checkbox is selected
 *
 * @param {type} sButtonStripId
 * @param {type} bUseImage
 * @param {type} oOptions
 * @returns {undefined}
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
	setInnerHTML(oNewButton, '<a class="linklevel1" href="' + oOptions.sUrl + '" ' + ('sCustom' in oOptions ? oOptions.sCustom : '') + '><span class="last"' + ('sId' in oOptions ? ' id="' + oOptions.sId + '_text"': '') + '>' + oOptions.sText + '</span></a>');

	oButtonStripList.appendChild(oNewButton);
}