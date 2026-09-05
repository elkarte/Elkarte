/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 */

/**
 * This file contains JavaScript associated with the current theme
 */

// Normal JS document ready event
document.addEventListener('DOMContentLoaded', function() {

	// If they touch the screen, then we switch to click menus
	window.addEventListener('touchstart', onFirstTouch, false);

	// Or if they specifically only want click menus
	if (use_click_menu)
	{
		useClickMenu();
	}

	// Fix code blocks so they are as compact as possible
	if (typeof elk_codefix === 'function')
	{
		elk_codefix();
	}

	if (typeof elk_quotefix === 'function')
	{
		elk_quotefix();
	}

	// If you want a sticky menu on scroll, add an appropriate .sticky CSS class to your theme
	// This adds / removes a .sticky class on scroll, see index_gold.css for an example.
	stickyMenu();

	// Smooth scroll to top.
	document.getElementById('gotop').addEventListener('click', function(e) {
		e.preventDefault();
		window.scrollTo({top: 0, behavior: 'smooth'});
	});

	// Smooth scroll to bottom.
	document.getElementById('gobottom').addEventListener('click', function(e) {
		e.preventDefault();

		// Don't scroll all the way down to the footer, just the content bottom
		let link = document.querySelector('#footer_section'),
			linkY = link.offsetHeight,
			heightDiff = link.getBoundingClientRect().top + linkY - window.innerHeight;

		window.scrollBy({top: heightDiff, behavior: 'smooth'});
	});

	// Find all nested linked images and turn off the border
	let elements = document.querySelectorAll('a.bbc_link img.bbc_img');
	for (let i = 0; i < elements.length; i++)
	{
		let parentElement = elements[i].parentNode;
		parentElement.style.border = '0';
	}

	// Expand the moderation hamburger icon/button view for mobile devices
	let hamburger = document.querySelector('.hamburger_30');
	if (hamburger)
	{
		hamburger.addEventListener('click', function(e) {
			let id = this.getAttribute('data-id');
			e.preventDefault();
			document.getElementById(id).classList.add('visible');
			this.classList.add('visible');
		});
	}

	// Collapsible fieldsets, pure candy
	document.querySelector('body').addEventListener('click', function(event) {
		if (event.target.matches('legend'))
		{
			let siblings = elkGetSiblings(event.target);
			siblings.forEach(sib => sib.slideToggle());
			event.target.parentNode.classList.toggle('collapsed');
		}
	});

	// For any legends with data-collapsed="true", start them collapsed
	document.querySelectorAll('legend').forEach(function(el) {
		if (el.getAttribute('data-collapsed') !== null)
		{
			el.click();
		}
	});

	// Spoiler
	document.querySelectorAll('.spoilerheader').forEach(element => {
		element.addEventListener('click', function() {
			element.nextElementSibling.children[0].slideToggle(250);
		});
	});

	// Code blocks, set the correct [Select] or [Copy] button text based on the user's browser
	if (typeof elk_initCodeButtons === 'function')
	{
		elk_initCodeButtons();
	}
});

// Jquery document ready
$(function() {
	// Enable the ... page expansion
	$('.expand_pages').expand_pages();

	// Attachment thumbnail expands on click
	// You can remove this namespaced click with $('[data-lightboximage]').off('click.elk_lb');
	$('[data-lightboximage]').on('click.elk_lb', function(e) {
		e.preventDefault();
		expandThumbLB($(this).data('lightboximage'), $(this).data('lightboxmessage'));
	});

	// BBC image inline expand on click.
	// You can turn off this namespaced click event with $('[data-bbcexpandimage]').off("click.elk_bbc")
	$('[data-bbcexpandimage]').on('click.elk_bbc', function(e) {
		let $this = $(this);

		// No saved data, then set it to auto expand
		if ($.isEmptyObject($this.data('bbc_img')))
		{
			$this.data('bbc_img', {
				width: $this.css('width'),
				height: $this.css('height'),
				'max-width': $this.css('max-width'),
				'max-height': $this.css('max-height'),
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
			// Was previously clicked and saved, so set it back
			$this.css({'width': $this.data('bbc_img').width});
			$this.css({'height': $this.data('bbc_img').height});
			$this.css({'max-width': $this.data('bbc_img')['max-width']});
			$this.css({'max-height': $this.data('bbc_img')['max-height']});

			// Remove the data
			$this.removeData('bbc_img');

			// Remove the div we added to allow the image to overflow expand in
			$this.unwrap();
			$this.css({'max-width': '100%'});
		}
	});
});

/**
 * Adds a button to the quick topic moderation after a checkbox is selected
 *
 * @param {string} sButtonStripId
 * @param {boolean} bUseImage
 * @param {object} oOptions
 */
function elk_addButton (sButtonStripId, bUseImage, oOptions)
{
	let oButtonStrip = document.getElementById(sButtonStripId),
		aItems = oButtonStrip.getElementsByTagName('span');

	// Remove the 'last' class from the last item.
	if (aItems.length > 0)
	{
		let oLastSpan = aItems[aItems.length - 1];
		oLastSpan.className = oLastSpan.className.replace(/\s*last/, 'position_holder');
	}

	// Add the button.
	let oButtonStripList = oButtonStrip.getElementsByTagName('ul')[0],
		oNewButton = document.createElement('li'),
		oRole = document.createAttribute('role');

	oRole.value = 'menuitem';
	oNewButton.setAttributeNode(oRole);

	if ('sId' in oOptions)
	{
		oNewButton.id = oOptions.sId;
	}

	oNewButton.innerHTML = '' +
		'<a class="linklevel1" href="' + oOptions.sUrl + '" ' + ('sCustom' in oOptions ? oOptions.sCustom : '') + '>' +
		('sImage' in oOptions && bUseImage ? '<i class="icon ' + oOptions.sImage + '"></i>' : '') +
		'   <span class="last"' + ('sId' in oOptions ? ' id="' + oOptions.sId + '_text"' : '') + '>' +
		oOptions.sText +
		'   </span>' +
		'</a>';

	if (oOptions.aEvents)
	{
		oOptions.aEvents.forEach(function(e) {
			oNewButton.addEventListener(e[0], e[1]);
		});
	}

	oButtonStripList.appendChild(oNewButton);
}

// Get your paws off me
function onFirstTouch ()
{
	useClickMenu();
}

// Activates click menus when a touch event is detected.
function useClickMenu ()
{
    // Click Menu drop downs
    let menus = ['#main_menu', '#sort_by', 'ul.poster', 'ul.quickbuttons', 'ul.admin_menu', 'ul.sidebar_menu', 'ul.buttonlist'];

    menus.forEach((selector) => {
        // Initialize each matching element individually so all instances work (e.g., multiple quickbuttons)
        let nodes = document.querySelectorAll(selector);
        if (nodes && nodes.length)
        {
            Array.prototype.forEach.call(nodes, function(node) {
                new elkMenu(node);
            });
        }
        else
        {
            // For unique selectors (like IDs), try initializing once by selector
            new elkMenu(selector);
        }
    });

    window.removeEventListener('touchstart', onFirstTouch, false);
}

// Adds/Removes sticky class to id='menu_nav'
function stickyMenu ()
{
	let menu = document.getElementById('menu_nav');

	if (menu)
	{
		let offset = menu.getBoundingClientRect().top + window.scrollY;

		let checkSticky = function() {
			if (!menu.classList.contains('sticky'))
			{
				offset = menu.getBoundingClientRect().top + window.scrollY;
			}

			if (window.scrollY > offset - 5)
			{
				menu.classList.add('sticky');
			}
			else if (window.scrollY < offset - 20)
			{
				menu.classList.remove('sticky');
			}
		};

		window.addEventListener('scroll', checkSticky, {passive: true});
		window.addEventListener('resize', checkSticky);

		// Run once on load to handle pre-scrolled page refreshes
		checkSticky();
	}
}
