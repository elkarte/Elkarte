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
 * This file contains javascript associated with the current theme
 */

$(function ()
{
	// Menu drop downs
	if (use_click_menu)
	{
		$('#main_menu, ul.admin_menu, ul.sidebar_menu, ul.poster, ul.quickbuttons, #sort_by').superclick({
			speed: 150,
			animation: {opacity: 'show', height: 'toggle'},
			speedOut: 0,
			activeClass: 'sfhover'
		});
	}
	else
	{
		$('#main_menu, ul.admin_menu, ul.sidebar_menu, ul.poster, ul.quickbuttons, #sort_by').superfish({
			delay: 300,
			speed: 175,
			hoverClass: 'sfhover'
		});
	}

	// Smooth scroll to top.
	$("a[href='#top']").on("click", function (e)
	{
		e.preventDefault();
		$("html,body").animate({scrollTop: 0}, 1200);
	});

	// Smooth scroll to bottom.
	$("a[href='#bot']").on("click", function (e)
	{
		e.preventDefault();

		// Don't scroll all the way down to the footer, just the content bottom
		var link = $('#bot'),
			link_y = link.height();

		$("html,body").animate({scrollTop: link.offset().top + link_y - $(window).height()}, 1200);
	});

	// Tooltips
	if ((!is_mobile && !is_touch) || use_click_menu)
	{
		$('.preview').SiteTooltip({hoverIntent: {sensitivity: 10, interval: 750, timeout: 50}});
	}

	// Find all nested linked images and turn off the border
	$('a.bbc_link img.bbc_img').parent().css('border', '0');

	// Fix code blocks so they are as compact as possible
	if (typeof elk_codefix === 'function')
	{
		elk_codefix();
	}

	// Enable the ... page expansion
	$('.expand_pages').expand_pages();

	// Collapsible fieldsets, pure candy
	$(document).on('click', 'legend', function ()
	{
		$(this).siblings().slideToggle("fast");
		$(this).parent().toggleClass("collapsed");
	});

	$('legend', function ()
	{
		if ($(this).data('collapsed'))
		{
			$(this).click();
		}
	});

	// Spoiler
	$('.spoilerheader').on('click', function ()
	{
		$(this).next().children().slideToggle("fast");
	});

	// Attachment thumbnail expand on click, you can turn off this namespaced click
	// event with $('[data-lightboximage]').off('click.elk_lb');
	$('[data-lightboximage]').on('click.elk_lb', function (e)
	{
		e.preventDefault();
		expandThumbLB($(this).data('lightboximage'), $(this).data('lightboxmessage'));
	});

	// BBC [img] element toggle for height and width styles of an image.
	$('img').each(function ()
	{
		// Not a resized image? Skip it.
		if ($(this).hasClass('bbc_img resized') === false)
		{
			return true;
		}

		$(this).css({'cursor': 'pointer'});

		// Note to addon authors, if you want to enable your own click events to bbc images
		// you can turn off this namespaced click event with $("img").off("click.elk_bbc")
		$(this).on("click.elk_bbc", function ()
		{
			var $this = $(this);

			// No saved data, then lets set it to auto
			if ($.isEmptyObject($this.data('bbc_img')))
			{
				$this.data('bbc_img', {
					width: $this.css('width'),
					height: $this.css('height'),
					'max-width': $this.css('max-width'),
					'max-height': $this.css('max-height')
				});
				$this.css({'width': $this.css('width') === 'auto' ? null : 'auto'});
				$this.css({'height': $this.css('height') === 'auto' ? null : 'auto'});

				// Override default css to allow the image to expand fully, add a div to expand in
				$this.css({'max-height': 'none'});
				$this.css({'max-width': '100%'});
				$this.wrap('<div style="overflow:auto;display:inline-block;"></div>');
			}
			else
			{
				// Was clicked and saved, so set it back
				$this.css({'width': $this.data("bbc_img").width});
				$this.css({'height': $this.data("bbc_img").height});
				$this.css({'max-width': $this.data("bbc_img")['max-width']});
				$this.css({'max-height': $this.data("bbc_img")['max-height']});

				// Remove the data
				$this.removeData('bbc_img');

				// Remove the div we added to allow the image to overflow expand in
				$this.unwrap();
				$this.css({'max-width': '100%'});
			}
		});
	});

	$('.hamburger_30').on('click', function (e)
	{
		e.preventDefault();
		var id = $(this).data('id');
		$('#' + id).addClass('visible');
		$(this).addClass('visible');
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
	{
		oNewButton.id = oOptions.sId;
	}

	oNewButton.innerHTML = '<a class="linklevel1" href="' + oOptions.sUrl + '" ' + ('sCustom' in oOptions ? oOptions.sCustom : '') + '><span class="last"' + ('sId' in oOptions ? ' id="' + oOptions.sId + '_text"' : '') + '>' + oOptions.sText + '</span></a>';

	if (oOptions.aEvents)
	{
		oOptions.aEvents.forEach(function (e)
		{
			oNewButton.addEventListener(e[0], e[1]);
		});
	}

	oButtonStripList.appendChild(oNewButton);
}
