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

/** global: elk_session_var, elk_session_id, elk_scripturl */

/**
 * This file contains javascript associated with an auto suggest control.
 */

/**
 * The auto suggest class, used to display a selection list of members
 *
 * @param {object} oOptions
 */
function elk_AutoSuggest(oOptions)
{
	this.opt = oOptions;

	// Store the handle to the text box.
	this.oTextHandle = document.getElementById(this.opt.sControlId);
	this.oRealTextHandle = null;
	this.oSuggestDivHandle = null;

	this.sLastSearch = '';
	this.sLastDirtySearch = '';
	this.oSelectedDiv = null;
	this.aCache = [];
	this.aDisplayData = [];
	this.sRetrieveURL = this.opt.sRetrieveURL || '%scripturl%action=suggest;api=xml';

	// How many objects can we show at once?
	this.iMaxDisplayQuantity = this.opt.iMaxDisplayQuantity || 12;

	// How many characters shall we start searching on?
	this.iMinimumSearchChars = this.opt.iMinimumSearchChars || 2;

	// Should selected items be added to a list?
	this.bItemList = this.opt.bItemList || false;

	// Are there any items that should be added in advance?
	this.aListItems = this.opt.aListItems || [];

	this.sItemTemplate = this.opt.sItemTemplate || '<input type="hidden" name="%post_name%[]" value="%item_id%" /><a href="%item_href%" class="extern" onclick="window.open(this.href, \'_blank\'); return false;">%item_name%</a>&nbsp;<i class="icon icon-small i-remove" title="%delete_text%"><s>%delete_text%</s></i>';
	this.sTextDeleteItem = this.opt.sTextDeleteItem || '';
	this.oCallback = {};
	this.bDoAutoAdd = false;
	this.iItemCount = 0;
	this.oHideTimer = null;
	this.bPositionComplete = false;

	this.oXmlRequestHandle = null;

	// Just make sure the page is loaded before calling the init.
	window.addEventListener("load", this.init.bind(this));
}

/**
 * Initialize our auto suggest object, adds events and containers to the element we monitor
 */
elk_AutoSuggest.prototype.init = function ()
{
	// Create a div that'll contain the results later on.
	this.oSuggestDivHandle = document.createElement('div');
	this.oSuggestDivHandle.className = 'auto_suggest_div';
	document.body.appendChild(this.oSuggestDivHandle);

	// Create a backup text input.
	this.oRealTextHandle = document.createElement('input');
	this.oRealTextHandle.type = 'hidden';
	this.oRealTextHandle.name = this.oTextHandle.name;
	this.oRealTextHandle.value = this.oTextHandle.value;
	this.oTextHandle.form.appendChild(this.oRealTextHandle);

	// Disable autocomplete in any browser by obfuscating the name.
	this.oTextHandle.name = 'dummy_' + Math.floor(Math.random() * 1000000);
	this.oTextHandle.autocomplete = 'off';

	// Set up all the event monitoring
	this.oTextHandle.onkeydown = this.handleKey.bind(this);
	this.oTextHandle.onkeyup = this.autoSuggestUpdate.bind(this);
	this.oTextHandle.onchange = this.autoSuggestUpdate.bind(this);
	this.oTextHandle.onblur = this.autoSuggestHide.bind(this);
	this.oTextHandle.onfocus = this.autoSuggestUpdate.bind(this);

	// Adding items to a list, then we need a place to insert them
	if (this.bItemList)
	{
		if ('sItemListContainerId' in this.opt)
		{
			this.oItemList = document.getElementById(this.opt.sItemListContainerId);
		}
		else
		{
			this.oItemList = document.createElement('div');
			this.oTextHandle.parentNode.insertBefore(this.oItemList, this.oTextHandle.nextSibling);
		}
	}

	// Items provided to add to the top of the selection list?
	if (this.aListItems.length > 0)
	{
		for (let i = 0, n = this.aListItems.length; i < n; i++)
		{
			this.addItemLink(this.aListItems[i].sItemId, this.aListItems[i].sItemName, false);
		}
	}

	return true;
};

/**
 * Handle keypress events for the suggest controller
 */
elk_AutoSuggest.prototype.handleKey = function (oEvent) {
	let iKeyPress = this.getKeyPress(oEvent);

	switch (iKeyPress)
	{
		case 9: // Tab
			return this.handleTabKey();
		case 13: // Enter
			return this.handleEnterKey();
		case 38: // Up arrow
		case 40: // Down arrow
			return this.handleArrowKey(iKeyPress);
	}

	return true;
};

/**
 * Gets the key pressed from the event handler.
 */
elk_AutoSuggest.prototype.getKeyPress = function (oEvent) {
	return 'which' in oEvent ? oEvent.which : oEvent.keyCode;
};

/**
 * Handles the tab key press event for the AutoSuggest component.
 */
elk_AutoSuggest.prototype.handleTabKey = function () {
	if (this.aDisplayData.length > 0)
	{
		if (this.oSelectedDiv !== null)
		{
			this.itemClicked(this.oSelectedDiv);
		}
		else
		{
			this.handleSubmit();
		}
	}

	return true;
};

/**
 * Handles the enter key press event for the auto-suggest feature.
 * Triggers the selectItem() method to select the highlighted item
 * when the enter key is pressed.
 */
elk_AutoSuggest.prototype.handleEnterKey = function () {
	if (this.aDisplayData.length > 0 && this.oSelectedDiv !== null)
	{
		this.itemClicked(this.oSelectedDiv);

		// Do our best to stop it submitting the form!
		return false;
	}

	return true;
};

/**
 * Handles the up/down arrow key events for the AutoSuggest component.
 */
elk_AutoSuggest.prototype.handleArrowKey = function (iKeyPress) {
	if (this.aDisplayData.length && this.oSuggestDivHandle.style.visibility !== 'hidden')
	{
		// Loop through the display data trying to find our entry.
		let bPrevHandle = false,
			oToHighlight = null;

		for (let i = 0; i < this.aDisplayData.length; i++)
		{
			// If we're going up and yet the top one was already selected don't go around.
			if (this.oSelectedDiv !== null && this.oSelectedDiv === this.aDisplayData[i] && i === 0 && iKeyPress === 38)
			{
				oToHighlight = this.oSelectedDiv;
				break;
			}

			// If nothing is selected, and we are going down then we select the first one.
			if (this.oSelectedDiv === null && iKeyPress === 40)
			{
				oToHighlight = this.aDisplayData[i];
				break;
			}

			// If the previous handle was the actual previously selected one, and we're hitting down then this is the one we want.
			if (bPrevHandle !== false && bPrevHandle === this.oSelectedDiv && iKeyPress === 40)
			{
				oToHighlight = this.aDisplayData[i];
				break;
			}

			// If we're going up and this is the previously selected one then we want the one before, if there was one.
			if (bPrevHandle !== false && this.aDisplayData[i] === this.oSelectedDiv && iKeyPress === 38)
			{
				oToHighlight = bPrevHandle;
				break;
			}

			// Make the previous handle this!
			bPrevHandle = this.aDisplayData[i];
		}

		// If we don't have one to highlight by now then it must be the last one that we're after.
		if (oToHighlight === null)
		{
			oToHighlight = bPrevHandle;
		}

		// Remove any old highlighting.
		if (this.oSelectedDiv !== null)
		{
			this.itemMouseOut(this.oSelectedDiv);
		}

		// Mark what the selected div now is.
		this.oSelectedDiv = oToHighlight;
		this.itemMouseOver(this.oSelectedDiv);
	}

	return true;
};

/**
 * Handles the mouse over event for an item in the auto suggest menu.
 */
elk_AutoSuggest.prototype.itemMouseOver = function (oCurElement)
{
	this.oSelectedDiv = oCurElement;
	oCurElement.className = 'auto_suggest_item_hover';
};

/**
 * Handle the mouse out event on an item in the auto suggest dropdown.
 */
elk_AutoSuggest.prototype.itemMouseOut = function (oCurElement)
{
	oCurElement.className = 'auto_suggest_item';
};

/**
 * Registers a callback function to be called when the auto-suggest results are available.
  */
elk_AutoSuggest.prototype.registerCallback = function (sCallbackType, sCallback)
{
	switch (sCallbackType)
	{
		case 'onBeforeAddItem':
			this.oCallback.onBeforeAddItem = sCallback;
			break;

		case 'onAfterAddItem':
			this.oCallback.onAfterAddItem = sCallback;
			break;

		case 'onAfterDeleteItem':
			this.oCallback.onAfterDeleteItem = sCallback;
			break;

		case 'onBeforeUpdate':
			this.oCallback.onBeforeUpdate = sCallback;
			break;
	}
};

/**
 * Handle form submission for the AutoSuggest component.
 *
 * @returns {boolean} - Returns false to prevent the default form submission behavior.
 */
elk_AutoSuggest.prototype.handleSubmit = function ()
{
	let bReturnValue = true,
		oFoundEntry = null;

	// Do we have something that matches the current text?
	for (let i = 0; i < this.aCache.length; i++)
	{
		if (this.sLastSearch.toLowerCase() === this.aCache[i].sItemName.toLowerCase().substring(0, this.sLastSearch.length))
		{
			// Exact match?
			if (this.sLastSearch.length === this.aCache[i].sItemName.length)
			{
				// This is the one!
				oFoundEntry = {
					sItemId: this.aCache[i].sItemId,
					sItemName: this.aCache[i].sItemName
				};

				break;
			}
			// Not an exact match, but it'll do for now.
			else
			{
				// If we have two matches don't find anything.
				if (oFoundEntry !== null)
				{
					bReturnValue = false;
				}
				else
				{
					oFoundEntry = {
						sItemId: this.aCache[i].sItemId,
						sItemName: this.aCache[i].sItemName
					};
				}
			}
		}
	}

	if (oFoundEntry === null || bReturnValue === false)
	{
		return bReturnValue;
	}

	this.addItemLink(oFoundEntry.sItemId, oFoundEntry.sItemName, true);
	return false;
};

/**
 * Positions the suggestion dropdown div based on the input element's position.
 */
elk_AutoSuggest.prototype.positionDiv = function ()
{
	// Only do it once.
	if (this.bPositionComplete)
	{
		return true;
	}

	this.bPositionComplete = true;

	// Put the div under the text box.
	let aParentPos = elk_itemPos(this.oTextHandle);

	this.oSuggestDivHandle.style.left = aParentPos[0] + 'px';
	this.oSuggestDivHandle.style.top = (aParentPos[1] + this.oTextHandle.offsetHeight) + 'px';
	this.oSuggestDivHandle.style.width = this.oTextHandle.clientWidth + 'px';

	return true;
};

/**
 * Called when an item in the auto suggest list is clicked.
 */
elk_AutoSuggest.prototype.itemClicked = function (oEvent)
{
	let target = 'target' in oEvent ? oEvent.target : oEvent;

	// Is there a div that we are populating?
	if (this.bItemList)
	{
		this.addItemLink(target.sItemId, target.innerHTML, false);
	}
	// Otherwise clear things down.
	else
	{
		this.oTextHandle.value = target.innerHTML.php_unhtmlspecialchars();
	}

	this.oRealTextHandle.value = this.oTextHandle.value;
	this.autoSuggestActualHide();
	this.oSelectedDiv = null;
};

/**
 * Removes the last entered search string from the auto-suggest component.
 */
elk_AutoSuggest.prototype.removeLastSearchString = function ()
{
	// Remove the text we searched for from the div.
	let sTempText = this.oTextHandle.value.toLowerCase(),
		iStartString = sTempText.indexOf(this.sLastSearch.toLowerCase());

	// Just attempt to remove the bits we just searched for.
	if (iStartString !== -1)
	{
		while (iStartString > 0)
		{
			if (sTempText.charAt(iStartString - 1) === '"' || sTempText.charAt(iStartString - 1) === ',' || sTempText.charAt(iStartString - 1) === ' ')
			{
				iStartString--;
				if (sTempText.charAt(iStartString - 1) === ',')
				{
					break;
				}
			}
			else
			{
				break;
			}
		}

		// Now remove anything from iStartString upwards.
		this.oTextHandle.value = this.oTextHandle.value.substring(0, iStartString);
	}
	// Just take it all.
	else
	{
		this.oTextHandle.value = '';
	}
};

/**
 * Adds an item link to the item list.
 *
 * @param {string} sItemId - The ID of the item.
 * @param {string} sItemName - The name of the item.
 * @param {boolean} bFromSubmit - Specifies whether the call is from a submit action.
 */
elk_AutoSuggest.prototype.addItemLink = function (sItemId, sItemName, bFromSubmit)
{
	// Increase the internal item count.
	this.iItemCount++;

	// If there's a callback then call it.
	if (typeof this.oCallback.onBeforeAddItem === 'function')
	{
		// If it returns false the item must not be added.
		if (!this.oCallback.onBeforeAddItem.call(this, sItemId))
		{
			return;
		}
	}

	let oNewDiv = document.createElement('div');
	oNewDiv.id = 'suggest_' + this.opt.sSuggestId + '_' + sItemId;
	oNewDiv.innerHTML = this.sItemTemplate
		.replace(/%post_name%/g, this.opt.sPostName)
		.replace(/%item_id%/g, sItemId)
		.replace(/%item_href%/g, elk_prepareScriptUrl(elk_scripturl) + this.opt.sURLMask.replace(/%item_id%/g, sItemId))
		.replace(/%item_name%/g, sItemName)
		.replace(/%images_url%/g, elk_images_url).replace(/%delete_text%/g, this.sTextDeleteItem);
	oNewDiv.getElementsByClassName('icon-small')[0].addEventListener("click", this.deleteAddedItem.bind(this));
	this.oItemList.appendChild(oNewDiv);

	// If there's a registered callback, call it.
	if (typeof this.oCallback.onAfterAddItem === 'function')
	{
		this.oCallback.onAfterAddItem.call(this, oNewDiv.id);
	}

	// Clear the div a bit.
	this.removeLastSearchString();

	// If we came from a submit, and there's still more to go, turn on auto add for all the other things.
	this.bDoAutoAdd = this.oTextHandle.value !== '' && bFromSubmit;

	// Update the fellow.
	this.autoSuggestUpdate();
};

/**
 * Deletes the added item from the auto-suggest component.
  */
elk_AutoSuggest.prototype.deleteAddedItem = function (oEvent)
{
	let oDiv;

	// A registerCallback, e.g. PM preventing duplicate entries
	if (typeof oEvent === 'string')
	{
		oDiv = document.getElementById('suggest_' + this.opt.sSuggestId + '_' + oEvent);
	}

	// Or the remove button
	if (typeof oEvent === 'object')
	{
		oDiv = oEvent.target.parentNode;
	}

	// Remove the div if it exists.
	if (oDiv !== null)
	{
		this.oItemList.removeChild(oDiv);

		// Decrease the internal item count.
		this.iItemCount--;

		// If there's a registered callback, call it.
		if (typeof this.oCallback.onAfterDeleteItem === 'function')
		{
			this.oCallback.onAfterDeleteItem.call(this);
		}
	}

	return false;
};

/**
 * Hide the auto suggest suggestions.
 */
elk_AutoSuggest.prototype.autoSuggestHide = function ()
{
	// Delay to allow events to propagate through....
	this.oHideTimer = setTimeout(this.autoSuggestActualHide.bind(this), 350);
};

/**
 * Hides the actual auto-suggest dropdown after a timeout
 */
elk_AutoSuggest.prototype.autoSuggestActualHide = function ()
{
	this.oSuggestDivHandle.style.display = 'none';
	this.oSuggestDivHandle.style.visibility = 'hidden';
	this.oSelectedDiv = null;
};

/**
 * Shows the auto suggest dropdown when triggered by the user.
 */
elk_AutoSuggest.prototype.autoSuggestShow = function ()
{
	if (this.oHideTimer)
	{
		clearTimeout(this.oHideTimer);
		this.oHideTimer = false;
	}

	this.positionDiv();
	this.oSuggestDivHandle.style.visibility = 'visible';
	this.oSuggestDivHandle.style.display = '';
};

/**
 * Populates the auto-suggest dropdown div with suggestions based on the provided data.
 */
elk_AutoSuggest.prototype.populateDiv = function (aResults)
{
	// Cannot have any children yet.
	while (this.oSuggestDivHandle.childNodes.length > 0)
	{
		// Tidy up the events etc. too.
		this.oSuggestDivHandle.childNodes[0].onmouseover = null;
		this.oSuggestDivHandle.childNodes[0].onmouseout = null;
		this.oSuggestDivHandle.childNodes[0].onclick = null;

		this.oSuggestDivHandle.removeChild(this.oSuggestDivHandle.childNodes[0]);
	}

	// Something to display?
	if (typeof aResults === 'undefined')
	{
		this.aDisplayData = [];
		return false;
	}

	let aNewDisplayData = [];
	for (let i = 0; i < (aResults.length > this.iMaxDisplayQuantity ? this.iMaxDisplayQuantity : aResults.length); i++)
	{
		// Create the sub element
		let oNewDivHandle = document.createElement('div');

		oNewDivHandle.sItemId = aResults[i].sItemId;
		oNewDivHandle.className = 'auto_suggest_item';
		oNewDivHandle.innerHTML = aResults[i].sItemName;
		//oNewDivHandle.style.width = this.oTextHandle.style.width;

		this.oSuggestDivHandle.appendChild(oNewDivHandle);

		// Attach some events to it, so we can do stuff.
		oNewDivHandle.onmouseover = function ()
		{
			this.className = 'auto_suggest_item_hover';
		};
		oNewDivHandle.onmouseout = function ()
		{
			this.className = 'auto_suggest_item';
		};
		oNewDivHandle.onclick = this.itemClicked.bind(this);

		aNewDisplayData[i] = oNewDivHandle;
	}

	this.aDisplayData = aNewDisplayData;

	return true;
};

/**
 * Callback function for the XML request, should contain the list of users that match
 */
elk_AutoSuggest.prototype.onSuggestionReceived = function (oXMLDoc)
{
	if (oXMLDoc === false)
	{
		return;
	}

	let aItems = oXMLDoc.getElementsByTagName('item');

	// Go through each item received
	this.aCache = [];
	for (let i = 0; i < aItems.length; i++)
	{
		this.aCache[i] = {
			sItemId: aItems[i].getAttribute('id'),
			sItemName: aItems[i].childNodes[0].nodeValue
		};

		// If we're doing auto add, and we found the exact person, then add them!
		if (this.bDoAutoAdd && this.sLastSearch === this.aCache[i].sItemName)
		{
			let oReturnValue = {
				sItemId: this.aCache[i].sItemId,
				sItemName: this.aCache[i].sItemName
			};

			this.aCache = [];
			return this.addItemLink(oReturnValue.sItemId, oReturnValue.sItemName, true);
		}
	}

	// Check we don't try to keep auto updating!
	this.bDoAutoAdd = false;

	// Populate the div.
	this.populateDiv(this.aCache);

	// Make sure we can see it - if we can.
	if (aItems.length === 0)
	{
		this.autoSuggestHide();
	}
	else
	{
		this.autoSuggestShow();
	}

	return true;
};

/**
 * Update the suggestions in the auto suggest dropdown based on the user input
 */
elk_AutoSuggest.prototype.autoSuggestUpdate = function ()
{
	// If there's a callback then call it.
	if (typeof this.oCallback.onBeforeUpdate === 'function')
	{
		// If it returns false the item must not be added.
		if (!this.oCallback.onBeforeUpdate.call(this))
		{
			return false;
		}
	}

	this.oRealTextHandle.value = this.oTextHandle.value;

	if (isEmptyText(this.oTextHandle))
	{
		this.aCache = [];
		this.populateDiv();
		this.autoSuggestHide();

		return true;
	}

	// Nothing changed?
	if (this.oTextHandle.value === this.sLastDirtySearch)
	{
		return true;
	}
	this.sLastDirtySearch = this.oTextHandle.value;

	// We're only actually interested in the last string.
	let sSearchString = this.oTextHandle.value.replace(/^("[^"]+",[ ]*)+/, '').replace(/^([^,]+,[ ]*)+/, '');
	if (sSearchString.substring(0, 1) === '"')
	{
		sSearchString = sSearchString.substring(1);
	}

	// Stop replication ASAP.
	let sRealLastSearch = this.sLastSearch;
	this.sLastSearch = sSearchString;

	// Either nothing or we've completed a sentence.
	if (sSearchString === '' || sSearchString.substring(sSearchString.length - 1) === '"')
	{
		this.populateDiv();
		return true;
	}

	// Nothing?
	if (sRealLastSearch === sSearchString)
	{
		return true;
	}

	// Too small?
	if (sSearchString.length < this.iMinimumSearchChars)
	{
		this.aCache = [];
		this.autoSuggestHide();
		return true;
	}

	if (sSearchString.substring(0, sRealLastSearch.length) === sRealLastSearch)
	{
		// Instead of hitting the server again, just narrow down the results...
		let aNewCache = [],
			j = 0,
			sLowercaseSearch = sSearchString.toLowerCase();

		for (let k = 0; k < this.aCache.length; k++)
		{
			if (this.aCache[k].sItemName.slice(0, sSearchString.length).toLowerCase() === sLowercaseSearch)
			{
				aNewCache[j++] = this.aCache[k];
			}
		}

		this.aCache = [];
		if (aNewCache.length !== 0)
		{
			this.aCache = aNewCache;

			// Repopulate.
			this.populateDiv(this.aCache);

			// Check it can be seen.
			this.autoSuggestShow();

			return true;
		}
	}

	// In progress means destroy!
	if (typeof this.oXmlRequestHandle === 'object' && this.oXmlRequestHandle !== null)
	{
		this.oXmlRequestHandle.abort();
	}

	// Clean the text handle.
	sSearchString = sSearchString.php_urlencode();

	// Get the document.
	let obj = {
			"suggest_type": this.opt.sSearchType,
			"search": sSearchString,
			"time": new Date().getTime()
		},
		postString;

	// Post values plus session
	postString = serialize(obj) + "&" + this.opt.sSessionVar + "=" + this.opt.sSessionId;

	sendXMLDocument.call(this, this.sRetrieveURL.replace(/%scripturl%/g, elk_prepareScriptUrl(elk_scripturl)), postString, this.onSuggestionReceived);

	return true;
};
