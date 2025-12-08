/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 */

/**
 * SiteTooltip, Basic JavaScript function to provide styled tooltips
 *
 * - shows the tooltip in a div with the class defined in tooltipClass
 * - moves all selector titles to a hidden div and removes the title attribute to
 *   prevent default browser actions
 * - attempts to keep the tooltip on screen, prefers it "on top" but will move it below if there
 * is not enough screen room above
 *
 */
class SiteTooltip
{
	constructor (settings = {})
	{
		this.defaults = {
			tooltipID: 'site_tooltip', // ID used on the outer div
			tooltipTextID: 'site_tooltipText', // ID on the inner div holding the text
			tooltipClass: 'tooltip', // The class applied to the sibling span, defaults provides a fade cover
			tooltipSwapClass: 'site_swaptip', // a class only used internally, change only if you have a conflict
			tooltipContent: 'html' // display captured title text as html or text
		};

		// Account for any user options
		this.settings = Object.assign({}, this.defaults, settings);
	}

	/**
	 * Creates tooltips for elements.
	 *
	 * @param {string} elem - The CSS selector of the elements to create tooltips for.
	 *
	 * @returns {null} - Returns null if the device is mobile or touch-enabled.
	 */
	create (elem)
	{
		// No need here
		if (is_mobile === true || is_touch === true)
		{
			return null;
		}

		// Move passed selector titles to a hidden span, then remove the selector title to prevent any default browser actions
		for (let el of document.querySelectorAll(elem))
		{
			let title = el.getAttribute('title');

			el.removeAttribute('title');
			el.setAttribute('data-title', title);
			el.addEventListener('mouseenter', this.showTooltip.bind(this));
			el.addEventListener('mouseleave', this.hideTooltip.bind(this));
		}
	}

	/**
	 * Positions the tooltip element relative to the provided event target.
	 *
	 * @param {Event} event - The event object that triggered the tooltip placement.
	 */
	positionTooltip (event)
	{
		let tooltip = document.getElementById(this.settings.tooltipID);
		if (!tooltip)
		{
			return;
		}

		let rect = event.target.getBoundingClientRect();
		let tooltipHeight = tooltip.offsetHeight;

		let x = rect.left;
		// Initially trying to position above the element
		let y = window.scrollY + rect.top - tooltipHeight - 5;

		// Don't position above if it falls off-screen, instead move it below
		if (rect.top - (tooltipHeight + 5) < 0)
		{
			y = window.scrollY + rect.bottom + 5;
		}

		tooltip.style.cssText = 'left:' + x + 'px; top:' + y + 'px';
	}

	/**
	 * Displays a tooltip on hover over an element.
	 *
	 * @param {Event} event - The event object.
	 */
	showTooltip (event)
	{
		if (this.tooltipTimeout)
		{
			clearTimeout(this.tooltipTimeout);
		}

		// Represents the timeout for showing a tooltip.
		this.tooltipTimeout = setTimeout(function() {
			let title = event.target.getAttribute('data-title');
			if (title)
			{
				// <div id="site_tooltip"><div id="site_tooltipText"><span class="tooltip"
				let tooltip = document.createElement('div');
				tooltip.id = this.settings.tooltipID;

				let tooltipText = document.createElement('div');
				tooltipText.id = this.settings.tooltipTextID;

				let span = document.createElement('span');
				span.className = this.settings.tooltipClass;

				// Create our element and append it to the body.
				tooltip.appendChild(tooltipText);
				tooltip.appendChild(span);
				document.getElementsByTagName('body')[0].appendChild(tooltip);

				// Load the tooltip content with our data-title
				if (this.settings.tooltipContent === 'html')
				{
					// Regular expression to match content inside .bbc_code_inline span
					let regex = new RegExp('(<span class="bbc_code_inline">).*?(<\/span>)', 's');

					title = title.replace(regex, function(match, p1, p2)
					{
						let content = match.slice(p1.length, -p2.length);
						let replacedContent = content.replace(/</g, "&lt;");
						return p1 + replacedContent + p2;
					});

					tooltipText.innerHTML = title;
				}
				else
				{
					tooltipText.innerText = title;
				}

				tooltip.style.display = 'block';
				this.positionTooltip(event);
			}
		}.bind(this), 1000);
	}

	/**
	 * Hides the tooltip.
	 *
	 * @param {Event} event - The event object.
	 */
	hideTooltip (event)
	{
		if (this.tooltipTimeout)
		{
			clearTimeout(this.tooltipTimeout);
		}

		let tooltip = document.getElementById(this.settings.tooltipID);
		if (tooltip)
		{
			tooltip.parentElement.removeChild(tooltip);
		}
	}
}

window.SiteTooltip = SiteTooltip;
