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
 * This file contains javascript associated with the new fader
 */

/**
 * Loops over the news items, fading them out as it rotates through them
 *
 * sFaderControlId: id of the news box containing all of the items
 * sItemTemplate: item template
 * iFadeDelay: fader delay between news items
 * iFadeSpeed: speed of the fade in/out effect
 *
 * @param {type} oOptions
 */
function elk_NewsFader(oOptions)
{
	var aFaderItems = oOptions.aFaderItems || [],
		iFadeIndex = 0,
		iFadeDelay = oOptions.iFadeDelay || 5000,
		iFadeSpeed = oOptions.iFadeSpeed || 650,
		sItemTemplate = oOptions.sItemTemplate || '%1$s',
		sControlId = '#' + oOptions.sFaderControlId,
		fadeIn = function()
		{
			iFadeIndex++;
			if (iFadeIndex >= aFaderItems.length)
				iFadeIndex = 0;

			$(sControlId + ' li').html(sItemTemplate.replace('%1$s', aFaderItems[iFadeIndex])).fadeTo(iFadeSpeed, 0.99, function() {
				// Restore ClearType in IE.
				this.style.filter = '';
				fadeOut();
			});
		},
		fadeOut = function()
		{
			setTimeout(function() {
				$(sControlId + ' li').fadeTo(iFadeSpeed, 0, fadeIn);
			}, iFadeDelay);
		};

	// Create the news  array from the list items in the news container
	if (!aFaderItems.length)
	{
		$(sControlId + ' li').each(function() {
			aFaderItems.push($(this).html());
		});
	}

	$(sControlId).html('<li>' + sItemTemplate.replace('%1$s', aFaderItems[0]) + '</li>');

	fadeOut();
}