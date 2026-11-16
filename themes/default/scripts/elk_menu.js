/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 2
 */

/**
 * Menu functions to allow touchscreen / keyboard interaction in place of hover/mouse
 *
 * @param {string|HTMLElement} menuRef CSS selector of the top-level UL, or the UL element itself
 */
function elkMenu (menuRef)
{
    // Accept either a selector string or a concrete element
	this.menu = null;
    if (typeof menuRef === 'string')
    {
        this.menu = document.querySelector(menuRef);
    }
    else if (menuRef && menuRef.nodeType === 1)
    {
        this.menu = menuRef;
    }

    if (this.menu !== null)
    {
        // Prevent double-initialization on the same menu element
        if (!this.menu.dataset.elkMenuInit)
        {
            this.menu.dataset.elkMenuInit = '1';
            this.initMenu();
        }
    }
}

/**
 * Set up the menu to work with click / keyboard events instead of :hover
 */
elkMenu.prototype.initMenu = function() {
	// Removing this class prevents the standard hover effect, assuming the CSS is set up correctly
	this.menu.classList.remove('no_js');
	if (this.menu.parentElement)
	{
		this.menu.parentElement.classList.remove('no_js');
	}

	// The subMenus (ul.menulevel#)
	let subMenu = this.menu.querySelectorAll('a + ul');

	// Initial aria-hidden = true for all subMenus
	subMenu.forEach(function(item) {
		item.setAttribute('aria-hidden', 'true');
	});

	// Document level events to close dropdowns on page click or ESC
	// Attach these only once per document to avoid duplicate handlers when multiple menus are initialized
	if (!document.body.dataset.elkMenuDocHandlers)
	{
		document.body.dataset.elkMenuDocHandlers = '1';
		this.docKeydown();
		this.docClick();
	}

	// Keyboard handler avoids handling a nested item's key twice.
	this.keysAsClick(this.menu);
	this.menu.addEventListener('focusout', function(event) {
		if (!this.menu.contains(event.relatedTarget))
		{
			this.resetMenu(this.menu);
		}
	}.bind(this));

	// Set up the subMenus (menulevel2, menulevel3) to open when clicked
	this.submenuReveal(subMenu);
};

/**
 * Keep the main menu dropdowns on screen when their triggers become icons.
 */
elkMenu.fitMainMenu = function(mainMenu) {
	if (!mainMenu || mainMenu.id !== 'main_menu')
	{
		return;
	}

	let narrow = window.matchMedia('(max-width: 51.5em)').matches,
		viewport = document.documentElement,
		left = getComputedStyle(viewport).direction === 'rtl' ? window.innerWidth - viewport.clientWidth : 0;

	mainMenu.querySelectorAll(':scope > li > .menulevel2').forEach(function(menu) {
		menu.style.translate = '';
		if (narrow)
		{
			let bounds = menu.getBoundingClientRect(),
				offset = Math.max(left + 8 - bounds.left, Math.min(0, left + viewport.clientWidth - 8 - bounds.right));

			menu.style.translate = offset + 'px 0';
		}
	});
};

/**
 * CLose menu when clicked outside it's structure
 */
elkMenu.prototype.docClick = function() {
    document.body.addEventListener('click', function(e) {
        // For each initialized menu on the page, decide if it should be closed
        document.querySelectorAll('[data-elk-menu-init="1"]').forEach(function(menuEl) {
            // Clicked outside of this menu instance
            if (!menuEl.contains(e.target))
            {
                this.resetMenu(menuEl);
                return;
            }

            // Clicked inside the menu hierarchy, but not on a link (on UL)
            if (e.target && e.target.tagName && e.target.tagName.toLowerCase() === 'ul')
            {
                this.resetMenu(menuEl);
            }
        }.bind(this));
    }.bind(this));
};

/**
 * Pressed the escape key, close any open dropdowns.  This will fire
 * if a menu/submenu does not capture the keyboard event first, as if they
 * are not open or lost focus.
 */
elkMenu.prototype.docKeydown = function() {
    document.body.addEventListener('keydown', function(e) {
        e = e || window.e;
        if (e.key === 'Escape')
        {
            // Close all initialized menus on Escape
            document.querySelectorAll('[data-elk-menu-init="1"]').forEach(function(menuEl) {
                this.resetMenu(menuEl);
            }.bind(this));
        }
    }.bind(this));
};

/**
 * Sets class and aria values for open or closed submenus.  Prevents default
 * action on links that are both disclose and navigation by revealing on the
 * first click and then following on the second.
 *
 * @param {NodeListOf} subMenu
 */
elkMenu.prototype.submenuReveal = function(subMenu) {
	// All the subMenus menulevel2, menulevel3
	Array.prototype.forEach.call(subMenu, function(menu) {
		// The menu items container LI and link LI > A
		let parentLi = menu.parentNode,
			subLink = parentLi ? parentLi.querySelector('a') : null;

		if (!parentLi || !subLink)
		{
			return; // malformed structure, skip
		}

		// Initial aria and role for each submenu trigger
		this.SetItemAttribute(subLink, '', {
			'role': 'button',
			'aria-pressed': 'false',
			'aria-expanded': 'false',
			'aria-haspopup': 'true'
		});

		// The click event listener for opening sub menus
		subLink.addEventListener('click', function(e) {
			// Reset all sublinks in this menu
			this.resetSubLinks(subLink);

			// If it's not open, let's show it as selected
			if (!e.currentTarget.classList.contains('open'))
			{
				// Don't follow the menuLink (if any) when first opening the submenu
				e.preventDefault();

				e.currentTarget.setAttribute('aria-pressed', 'true');
				e.currentTarget.setAttribute('aria-expanded', 'true');
			}

			// Reset all the submenus in this menu
			this.resetSubMenus(subLink);

			// Grab the selected UL submenu
			let currentMenu = subLink.parentNode ? subLink.parentNode.querySelector('ul:first-of-type') : null;

			// Open its link and list
			parentLi.classList.add('open');
			e.currentTarget.classList.add('open');

			// Open the UL menu
			if (currentMenu)
			{
				currentMenu.classList.remove('un_selected');
				currentMenu.classList.add('selected');
				currentMenu.setAttribute('aria-hidden', 'false');
				elkMenu.fitMainMenu(this.menu);
			}
		}.bind(this));
	}.bind(this));
};

/**
 * Reset the current level submenu(s) as closed
 *
 * @param {HTMLElement} subLink
 */
elkMenu.prototype.resetSubMenus = function(subLink) {
	if (!subLink)
	{
		return;
	}

	// Determine the UL scope of the current level
	let levelUl = subLink.closest ? subLink.closest('ul') : (subLink.parentNode && subLink.parentNode.parentNode ? subLink.parentNode.parentNode : null);
	if (!levelUl)
	{
		return;
	}

	let subMenus = levelUl.querySelectorAll('li > a + ul:first-of-type');
	subMenus.forEach(function(menu) {
		// Remove open from the LI and LI A for this menu
		let parent = menu.parentNode;
		parent.classList.remove('open');
		parent.querySelector('a').classList.remove('open');

		// Remove open and selected for this menu
		menu.classList.remove('open', 'selected');
		menu.classList.add('un_selected');
		menu.setAttribute('aria-hidden', 'true');
	});
};

/**
 * Resets any links that are not pointing at an open submenu
 *
 * @param {HTMLElement} subLink the .menulevel# link that has been clicked
 */
elkMenu.prototype.resetSubLinks = function(subLink) {
	if (!subLink)
	{
		return;
	}

	// The all closed menus at this level
	let levelUl = subLink.closest ? subLink.closest('ul') : (subLink.parentNode && subLink.parentNode.parentNode ? subLink.parentNode.parentNode : null);
	if (!levelUl)
	{
		return;
	}

	let subMenus = levelUl.querySelectorAll('li > a + ul:not(.open)');
	subMenus.forEach(function(menu) {
		// links to closed menus are no longer active
		let thisLink = menu.parentNode.querySelector('a');
		if (thisLink)
		{
			thisLink.setAttribute('aria-pressed', 'false');
			thisLink.setAttribute('aria-expanded', 'false');
		}
	});
};

/*
 * Reset all aria labels to initial closed state, remove all added
 * open and selected classes from this menu.
 */
elkMenu.prototype.resetMenu = function(menu) {
	this.SetItemAttribute(menu, '[aria-hidden="false"]', {'aria-hidden': 'true'});
	this.SetItemAttribute(menu, '[aria-haspopup][aria-expanded="true"]', {'aria-expanded': 'false'});
	this.SetItemAttribute(menu, '[aria-pressed="true"]', {'aria-pressed': 'false'});

	menu.querySelectorAll('.selected').forEach(function(item) {
		item.classList.remove('selected');
		item.classList.add('un_selected');
	});

	menu.querySelectorAll('.open').forEach(function(item) {
		item.classList.remove('open');
	});
};

/**
 * Helper function to set attributes
 *
 * @param {HTMLElement} menu
 * @param {string} selector used to target specific attribute of menu
 * @param {object} attrs
 */
elkMenu.prototype.SetItemAttribute = function(menu, selector, attrs) {
	if (selector === '')
	{
		Object.keys(attrs).forEach(key => menu.setAttribute(key, attrs[key]));
		return;
	}

	if (!menu)
	{
		return;
	}

	menu.querySelectorAll(selector).forEach(function(item) {
		Object.keys(attrs).forEach(key => item.setAttribute(key, attrs[key]));
	});
};

/**
 * Allow for proper aria keydown events and keyboard navigation
 *
 * @param {HTMLElement} el
 */
elkMenu.prototype.keysAsClick = function(el) {
	if (!el || !el.addEventListener)
	{
		return;
	}

	el.addEventListener('keydown', function(event) {
		this.keysCallback(event, el);
	}.bind(this), true);
};

/**
 * Callback for keyAsClick
 *
 * @param {KeyboardEvent} event
 * @param {HTMLElement} el
 */
elkMenu.prototype.keysCallback = function(event, el) {
	let keys = [' ', 'Enter', 'ArrowUp', 'ArrowLeft', 'ArrowDown', 'ArrowRight', 'Home', 'End', 'Escape', 'Spacebar'],
		item = event.target.closest('a, button');

	if (!keys.includes(event.key) || !item || !el.contains(item))
	{
		return;
	}

	let menu = item.closest('ul');
	if (!menu)
	{
		return;
	}

	let links = Array.from(menu.querySelectorAll(':scope > li > a, :scope > li > button')).filter(function(link) {
			return link.getClientRects().length && getComputedStyle(link).visibility !== 'hidden';
		}),
		index = links.indexOf(item),
		submenu = item.nextElementSibling;
	if (!submenu || !submenu.matches('.menulevel2, .menulevel3'))
	{
		submenu = null;
	}

	event.stopPropagation();
	event.preventDefault();
	if (event.key === 'Escape' || (event.key === 'ArrowLeft' && menu !== this.menu))
	{
		if (menu !== this.menu)
		{
			let parent = menu.parentElement,
				trigger = parent.querySelector(':scope > a');
			this.resetMenu(parent);
			parent.classList.remove('open');
			if (trigger)
			{
				trigger.focus();
			}
		}
		else
		{
			this.resetMenu(this.menu);
		}
		return;
	}

	if (event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar')
	{
		item.click();
		return;
	}

	if (submenu && (event.key === 'ArrowRight' || event.key === 'ArrowDown' || (event.key === 'ArrowUp' && menu === this.menu)))
	{
		if (!item.classList.contains('open'))
		{
			item.click();
		}
		let children = submenu.querySelectorAll(':scope > li > a');
		if (children.length)
		{
			children[event.key === 'ArrowUp' ? children.length - 1 : 0].focus();
		}
		return;
	}

	if (links.length && index !== -1)
	{
		let next = event.key === 'Home' ? 0 : event.key === 'End' ? links.length - 1
			: Math.max(0, Math.min(links.length - 1, index + (event.key === 'ArrowUp' || event.key === 'ArrowLeft' ? -1 : 1)));
		links[next].focus();
	}
};

// Position both hover and click menus without changing their interaction mode.
document.addEventListener('DOMContentLoaded', function() {
	let mainMenu = document.getElementById('main_menu');

	if (mainMenu)
	{
		let fit = function() { elkMenu.fitMainMenu(mainMenu); };

		fit();
		window.addEventListener('resize', fit);
		mainMenu.addEventListener('mouseover', fit);
		mainMenu.addEventListener('focusin', fit);
	}
});
