/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 */

/**
 * Menu functions to allow touchscreen / keyboard interaction in place of hover/mouse
 *
 * @param {string} menuID the selector of the top-level UL in the menu structure
 */
function elkMenu (menuID)
{
	this.menu = document.querySelector(menuID);
	if (this.menu !== null)
	{
		this.initMenu();
	}
}

/**
 * Setup the menu to work with click / keyboard events instead of :hover
 */
elkMenu.prototype.initMenu = function() {
	// Setup enter/spacebar keys to trigger a click on the "Skip to main content" link
	if (this.menu.id === 'main_menu')
	{
		this.keysAsClick(document.getElementById('skipnav'));
	}

	// Removing this class prevents the standard hover effect, assuming the CSS is set up correctly
	this.menu.classList.remove('no_js');
	this.menu.parentElement.classList.remove('no_js');

	// The subMenus (ul.menulevel#)
	let subMenu = this.menu.querySelectorAll('a + ul');

	// Initial aria-hidden = true for all subMenus
	subMenu.forEach(function(item) {
		item.setAttribute('aria-hidden', 'true');
	});

	// Document level events to close dropdowns on page click or ESC
	this.docKeydown();
	this.docClick();

	// Setup the subMenus (menulevel2, menulevel3) to open when clicked
	this.submenuReveal(subMenu);
};

/**
 * CLose menu on click outside of its structure
 */
elkMenu.prototype.docClick = function() {
	document.body.addEventListener('click', function(e) {
		// Clicked outside of this.menu
		if (!this.menu.contains(e.target))
		{
			this.resetMenu(this.menu);
		}

		// Clicked inside of the this.menu hierarchy, but not on a link
		let menuClick = e.target.closest('#' + this.menu.getAttribute('id'));
		if ((menuClick && e.target.tagName.toLowerCase() === 'ul'))
		{
			this.resetMenu(this.menu);
		}
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
			this.resetMenu(this.menu);
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
			subLink = parentLi.querySelector('a');

		// Initial aria and role for each submenu trigger
		this.SetItemAttribute(subLink, '', {
			'role': 'button',
			'aria-pressed': 'false',
			'aria-expanded': 'false'
		});

		// Setup keyboard navigation
		this.keysAsClick(menu);
		this.keysAsClick(parentLi);

		// The click event listener for opening sub menus
		subLink.addEventListener('click', function(e) {
			// Reset all sublinks in this menu
			this.resetSubLinks(subLink);

			// If its not open, lets show it as selected
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
			let currentMenu = subLink.parentNode.querySelector('ul:first-of-type');

			// Open its link and list
			parentLi.classList.add('open');
			e.currentTarget.classList.add('open');

			// Open the UL menu
			currentMenu.classList.remove('un_selected');
			currentMenu.classList.add('selected');
			currentMenu.setAttribute('aria-hidden', 'false');
		}.bind(this));
	}.bind(this));
};

/**
 * Reset the current level submenu(s) as closed
 *
 * @param {HTMLElement} subLink
 */
elkMenu.prototype.resetSubMenus = function(subLink) {
	let subMenus = subLink.parentNode.parentNode.querySelectorAll('li > a + ul:first-of-type');
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
	// The all closed menus
	let subMenus = subLink.parentNode.parentNode.querySelectorAll('li > a + ul:not(.open)');
	subMenus.forEach(function(menu) {
		// links to closed menus are no longer active
		let thisLink = menu.parentNode.querySelector('a');
		thisLink.setAttribute('aria-pressed', 'false');
		thisLink.setAttribute('aria-expanded', 'false');
	});
};

/*
 * Reset all aria labels to initial closed state, remove all added
 * open and selected classes from this menu.
 */
elkMenu.prototype.resetMenu = function(menu) {
	this.SetItemAttribute(menu, '[aria-hidden="false"]', {'aria-hidden': 'true'});
	this.SetItemAttribute(menu, '[aria-expanded="true"]', {'aria-expanded': 'false'});
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
	el.addEventListener('keydown', function(event) {
		this.keysCallback(event, el);
	}.bind(this), true);
};

/**
 * Callback for keyAsClick
 *
 * @param {KeyboardEvent} keyboardEvent
 * @param {HTMLElement} el
 */
elkMenu.prototype.keysCallback = function(keyboardEvent, el) {
	// THe keys we know how to respond to
	let keys = [' ', 'Enter', 'ArrowUp', 'ArrowLeft', 'ArrowDown', 'ArrowRight', 'Home', 'End', 'Escape'];

	if (keys.includes(keyboardEvent.key))
	{
		// What menu and links are we "in"
		let menu = keyboardEvent.target.closest('ul'),
			menuLinks = Array.prototype.slice.call(menu.querySelectorAll('a')),
			currentIndex = menuLinks.indexOf(document.activeElement);

		// Don't follow the links, don't bubble the event
		keyboardEvent.stopPropagation();
		keyboardEvent.preventDefault();
		switch (keyboardEvent.key)
		{
			case 'Escape':
				this.resetSubMenus(menu);
				break;
			case ' ':
			case 'Enter':
				menuLinks[currentIndex].click();
				break;
			case 'ArrowUp':
			case 'ArrowLeft':
				if (currentIndex > -1)
				{
					let prevIndex = Math.max(0, currentIndex - 1);
					menuLinks[prevIndex].focus();
				}
				break;
			case 'ArrowDown':
			case 'ArrowRight':
				if (currentIndex > -1)
				{
					let nextIndex = Math.min(menuLinks.length - 1, currentIndex + 1);
					menuLinks[nextIndex].focus();
				}
				break;
			case 'Home':
				menuLinks[1].focus();
				break;
			case 'End':
				menuLinks[menuLinks.length - 1].focus();
				break;
		}
	}
};
