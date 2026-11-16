/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 2
 */

/**
 * This file contains JavaScript associated with the current theme
 */

// Normal JS document ready event
document.addEventListener('DOMContentLoaded', function() {

	// The profile/admin sidebar uses a native button for touch and keyboard access.
	let sidebar = document.getElementById('menu_sidebar'),
		sidebarToggle = sidebar && sidebar.querySelector('.sidebar_menu_toggle');

	if (sidebarToggle)
	{
		let narrowSidebar = window.matchMedia('(max-width: 51.5em)'),
			setSidebarOpen = function(open) {
				sidebar.classList.toggle('is_open', open);
				sidebarToggle.setAttribute('aria-expanded', String(open));
			};
		sidebar.classList.add('sidebar_collapsible');
		setSidebarOpen(!narrowSidebar.matches);
		sidebarToggle.addEventListener('click', function() {
			setSidebarOpen(!sidebar.classList.contains('is_open'));
		});
		sidebar.addEventListener('keydown', function(event) {
			if (event.key === 'Escape' && narrowSidebar.matches)
			{
				setSidebarOpen(false);
				sidebarToggle.focus();
			}
		});
		narrowSidebar.addEventListener('change', function() {
			setSidebarOpen(!narrowSidebar.matches);
		});
	}

	// Keep hover menus for mouse users; enable explicit controls for touch/keyboard.
	window.addEventListener('touchstart', onFirstTouch, false);
	document.addEventListener('keydown', onFirstMenuKey, true);

	// Search is a native disclosure button; restore its focus when the panel closes.
	let searchButton = document.querySelector('#search_form_button button'),
		searchCheck = document.getElementById('search_form_check'),
		searchForm = document.getElementById('search_form_menu'),
		searchClose = document.getElementById('search_form_close'),
		mainMenu = document.getElementById('main_menu');

	if (searchButton && searchCheck && searchForm && searchClose && mainMenu)
	{
		let setSearchOpen = function(open, restoreFocus) {
			searchCheck.checked = open;
			searchButton.setAttribute('aria-expanded', String(open));
			searchForm.setAttribute('aria-hidden', String(!open));
			mainMenu.inert = open;
			if (open)
			{
				document.getElementById('quicksearch').focus();
			}
			else if (restoreFocus)
			{
				searchButton.focus();
			}
		};
		setSearchOpen(false, false);
		searchButton.hidden = false;
		searchButton.addEventListener('click', function() { setSearchOpen(true, false); });
		searchClose.addEventListener('click', function() { setSearchOpen(false, true); });
		searchForm.addEventListener('keydown', function(event) {
			if (event.key === 'Escape')
			{
				event.preventDefault();
				setSearchOpen(false, true);
			}
		});
	}

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

	// Keep sticky content (topic page navigation, etc.) clear of pinned header chrome.
	initializeTopicNavigationOffset();

	// Hide the sticky topic page navigation once the duplicate one at the end of the topic is on screen.
	initializeTopicPagesectionEnd();

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
		document.getElementById(hamburger.getAttribute('data-id')).classList.add('menu_collapsible');
		hamburger.setAttribute('aria-expanded', String(document.getElementById(hamburger.getAttribute('data-id')).classList.contains('visible')));
		hamburger.addEventListener('click', function(e) {
			let id = this.getAttribute('data-id');
			e.preventDefault();
			let expanded = document.getElementById(id).classList.toggle('visible');
			this.setAttribute('aria-expanded', String(expanded));
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

// A keyboard user can reach menus before any touch/click initialization occurs.
function onFirstMenuKey (event)
{
	if (event.key === 'Tab' || (event.target.closest && event.target.closest('#main_menu, #sort_by, .admin_menu, .sidebar_menu, .quickbuttons, .buttonlist, ul.poster')))
	{
		useClickMenu();
	}
}

// Activates click menus for touch, keyboard, or the user's menu preference.
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
    document.removeEventListener('keydown', onFirstMenuKey, true);
}

// Adds/Removes sticky class to id='menu_nav'
function stickyMenu ()
{
	let menu = document.getElementById('menu_nav'),
		header = document.getElementById('top_section');

	if (menu)
	{
		let rafId = null;

		// Top of an element in the page's scroll coordinates, ignoring any
		// visual shift caused by sticky/fixed positioning.  offsetTop is
		// based on the layout box, so it stays put while an element is pinned.
		let documentTop = function(element) {
			let top = 0;

			while (element)
			{
				top += element.offsetTop;
				element = element.offsetParent;
			}

			return top;
		};

		// Top of the header in the page's scroll coordinates.  A static header
		// can be measured directly (this also keeps its margins); a pinned
		// header has to be measured from its layout offset instead.
		let headerTop = function() {
			let position = getComputedStyle(header).position;

			if (position === 'static' || position === 'relative')
			{
				return header.getBoundingClientRect().top + window.scrollY;
			}

			return documentTop(header);
		};

		// Where the menu would sit if it was not fixed.  While `.sticky` is
		// active the menu is out of flow, so the header's height is exactly
		// the space above the menu's natural position.
		let measureOffset = function() {
			if (menu.classList.contains('sticky') && header)
			{
				return headerTop() + header.offsetHeight;
			}

			return menu.getBoundingClientRect().top + window.scrollY;
		};

		let checkSticky = function() {
			let offset = measureOffset();

			if (window.scrollY > offset - 5)
			{
				menu.classList.add('sticky');
			}
			else if (window.scrollY < offset - 20)
			{
				menu.classList.remove('sticky');
			}
		};

		let scheduleStickyCheck = function() {
			if (rafId !== null)
			{
				return;
			}

			rafId = window.requestAnimationFrame(function() {
				rafId = null;
				checkSticky();
			});
		};

		window.addEventListener('scroll', scheduleStickyCheck, {passive: true});
		window.addEventListener('resize', scheduleStickyCheck);

		// Collapsing or expanding the header moves the menu's natural position,
		// and the header slide animation changes its height over several frames.
		// Re-check on every layout change so the menu cannot stay pinned in front
		// of an expanded header.  Only a pinned menu needs this: an unpinned one
		// is still following the header in normal flow.
		let scheduleHeaderCheck = function() {
			if (menu.classList.contains('sticky'))
			{
				scheduleStickyCheck();
			}
		};

		if (header)
		{
			if (typeof MutationObserver !== 'undefined')
			{
				new MutationObserver(scheduleHeaderCheck).observe(header, {attributes: true, attributeFilter: ['class']});
			}

			if (typeof ResizeObserver !== 'undefined')
			{
				new ResizeObserver(scheduleHeaderCheck).observe(header, {box: 'border-box'});
			}
		}

		// Run once on load to handle pre-scrolled page refreshes
		scheduleStickyCheck();
	}
}

// `var` (not `let`) so a duplicate theme.js load can't throw a redeclaration error.
var topicNavigationFrame = null;

// Measures whatever is currently pinned to the top of the viewport (a
// sticky/fixed header or a fixed menu bar) and exposes its lower edge as
// --topic_navigation_offset.  Sticky elements can then use
// `top: var(--topic_navigation_offset, 0)` instead of hard-coding an offset.
function setTopicNavigationOffset ()
{
	if (topicNavigationFrame !== null)
	{
		return;
	}

	topicNavigationFrame = window.requestAnimationFrame(function() {
		topicNavigationFrame = null;

		let header = document.getElementById('top_section'),
			menu = document.getElementById('menu_nav'),
			headerPosition = header ? getComputedStyle(header).position : '',
			menuPosition = menu ? getComputedStyle(menu).position : '',
			offset = 0;

		// A sticky or fixed header is pinned to the top; measure its lower edge.
		if (header && (headerPosition === 'sticky' || headerPosition === 'fixed'))
		{
			offset = header.getBoundingClientRect().bottom;
		}

		// A fixed menu bar can extend further down than the header.
		if (menu && menuPosition === 'fixed')
		{
			offset = Math.max(offset, menu.getBoundingClientRect().bottom);
		}

		offset = Math.max(0, Math.ceil(offset));

		let root = document.documentElement;
		root.style.setProperty('--topic_navigation_offset', offset + 'px');

		// Compatibility for themes that still read the old variable.
		if (offset > 0)
		{
			root.style.setProperty('--collapsed_header_height', offset + 'px');
		}
		else
		{
			root.style.removeProperty('--collapsed_header_height');
		}
	});
}

// Watch for the events that change the pinned chrome: class changes catch
// scroll-triggered .sticky and header toggling, while size changes catch
// wrapped menus, font loading and responsive layouts.
function initializeTopicNavigationOffset ()
{
	if (window.elkTopicNavigationOffsetInitialized)
	{
		return;
	}

	window.elkTopicNavigationOffsetInitialized = true;

	window.addEventListener('load', setTopicNavigationOffset);
	window.addEventListener('resize', setTopicNavigationOffset);

	if (typeof MutationObserver !== 'undefined')
	{
		let topicNavigationClasses = new MutationObserver(setTopicNavigationOffset);

		['top_section', 'menu_nav'].forEach(function (id) {
			let element = document.getElementById(id);

			if (element)
			{
				topicNavigationClasses.observe(element, {attributes: true, attributeFilter: ['class']});
			}
		});
	}

	if (typeof ResizeObserver !== 'undefined')
	{
		let topicNavigationSizes = new ResizeObserver(setTopicNavigationOffset);

		['top_section', 'menu_nav'].forEach(function (id) {
			let element = document.getElementById(id);

			if (element)
			{
				topicNavigationSizes.observe(element, {box: 'border-box'});
			}
		});
	}

	setTopicNavigationOffset();
}

// Exposed so inline toggle callbacks can refresh the measurement immediately.
window.setTopicNavigationOffset = setTopicNavigationOffset;

// `var` (not `let`) so a duplicate theme.js load can't throw a redeclaration error.
var topicPagesectionFrame = null;

// Hides the sticky topic page navigation once the bar at the end of the topic,
// which carries the same page links and buttons, is on screen. Only applies
// while the bar is actually pinned, so short topics and the un-stuck mobile
// layout (where it is not sticky) are left alone.
function setTopicPagesectionEnd ()
{
	if (topicPagesectionFrame !== null)
	{
		return;
	}

	topicPagesectionFrame = window.requestAnimationFrame(function() {
		topicPagesectionFrame = null;

		let top = document.querySelector('.pagesection.topic_pagesection'),
			bottom = document.querySelector('.pagesection.topic_pagesection_end');

		if (!top || !bottom)
		{
			return;
		}

		let style = getComputedStyle(top),
			offset = parseFloat(style.top) || 0,
			// Sticky positioning pins the bar when its box reaches the offset.
			stuck = style.position === 'sticky' && top.getBoundingClientRect().top <= offset + 1,
			// 8px dead band, so the bar cannot flicker while the end bar sits on the fold.
			hidden = top.classList.contains('is_hidden'),
			limit = window.innerHeight + (hidden ? 8 : -8),
			bottomVisible = bottom.getBoundingClientRect().top <= limit;

		// Never hide it out from under the keyboard.
		top.classList.toggle('is_hidden', stuck && bottomVisible && !top.contains(document.activeElement));
	});
}

function initializeTopicPagesectionEnd ()
{
	if (window.elkTopicPagesectionInitialized)
	{
		return;
	}

	window.elkTopicPagesectionInitialized = true;

	window.addEventListener('scroll', setTopicPagesectionEnd, {passive: true});
	window.addEventListener('resize', setTopicPagesectionEnd);
	window.addEventListener('load', setTopicPagesectionEnd);

	setTopicPagesectionEnd();
}
