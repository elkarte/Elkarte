/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 */

/**
 * This file contains javascript associated with a autosuggest control.
 */

/**
 * The autosuggest class, used to display a selection list of members
 *
 * @param {object} oOptions
 */
function smc_AutoSuggest(oOptions)
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
	this.sRetrieveURL = 'sRetrieveURL' in this.opt ? this.opt.sRetrieveURL : '%scripturl%action=suggest;xml';

	// How many objects can we show at once?
	this.iMaxDisplayQuantity = 'iMaxDisplayQuantity' in this.opt ? this.opt.iMaxDisplayQuantity : 12;

	// How many characters shall we start searching on?
	this.iMinimumSearchChars = 'iMinimumSearchChars' in this.opt ? this.opt.iMinimumSearchChars : 2;

	// Should selected items be added to a list?
	this.bItemList = 'bItemList' in this.opt ? this.opt.bItemList : false;

	// Are there any items that should be added in advance?
	this.aListItems = 'aListItems' in this.opt ? this.opt.aListItems : [];

	this.sItemTemplate = 'sItemTemplate' in this.opt ? this.opt.sItemTemplate : '<input type="hidden" name="%post_name%[]" value="%item_id%" /><a href="%item_href%" class="extern" onclick="window.open(this.href, \'_blank\'); return false;">%item_name%</a>&nbsp;<img src="%images_url%/pm_recipient_delete.png" alt="%delete_text%" title="%delete_text%" />';
	this.sTextDeleteItem = 'sTextDeleteItem' in this.opt ? this.opt.sTextDeleteItem : '';
	this.oCallback = {};
	this.bDoAutoAdd = false;
	this.iItemCount = 0;
	this.oHideTimer = null;
	this.bPositionComplete = false;

	this.oXmlRequestHandle = null;

	// Just make sure the page is loaded before calling the init.
	window.addEventListener("load", this.init.bind(this));
}

// Initialize our autosuggest object, adds events and containers to the element we monitor
smc_AutoSuggest.prototype.init = function()
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
			this.oItemList = document.getElementById(this.opt.sItemListContainerId);
		else
		{
			this.oItemList = document.createElement('div');
			this.oTextHandle.parentNode.insertBefore(this.oItemList, this.oTextHandle.nextSibling);
		}
	}

	// Items provided to add to the top of the selection list?
	if (this.aListItems.length > 0)
		for (var i = 0, n = this.aListItems.length; i < n; i++)
			this.addItemLink(this.aListItems[i].sItemId, this.aListItems[i].sItemName);

	return true;
};

/**
 * Handle keypress events for the suggest controller
 *
 * @param oEvent
 */
smc_AutoSuggest.prototype.handleKey = function(oEvent)
{
	// Grab the event object, one way or the other
	if (!oEvent)
		oEvent = window.event;

	// Get the keycode of the key that was pressed.
	var iKeyPress = 0;
	if ('which' in oEvent)
		iKeyPress = oEvent.which;
	else if ('keyCode' in oEvent)
		iKeyPress = oEvent.keyCode;

	// Check what key they have pressed
	switch (iKeyPress)
	{
		// Tab.
		case 9:
			if (this.aDisplayData.length > 0)
			{
				if (this.oSelectedDiv !== null)
					this.itemClicked(this.oSelectedDiv);
				else
					this.handleSubmit();
			}

			// Continue to the next control.
			return true;
		break;

		// Was it an Enter key - if so assume they are trying to select something.
		case 13:
			if (this.aDisplayData.length > 0 && this.oSelectedDiv !== null)
			{
				this.itemClicked(this.oSelectedDiv);

				// Do our best to stop it submitting the form!
				return false;
			}
			else
				return true;
		break;

		// Up/Down arrow?
		case 38:
		case 40:
			if (this.aDisplayData.length && this.oSuggestDivHandle.style.visibility !== 'hidden')
			{
				// Loop through the display data trying to find our entry.
				var bPrevHandle = false,
					oToHighlight = null;

				for (var i = 0; i < this.aDisplayData.length; i++)
				{
					// If we're going up and yet the top one was already selected don't go around.
					if (this.oSelectedDiv !== null && this.oSelectedDiv === this.aDisplayData[i] && i === 0 && iKeyPress === 38)
					{
						oToHighlight = this.oSelectedDiv;
						break;
					}

					// If nothing is selected and we are going down then we select the first one.
					if (this.oSelectedDiv === null && iKeyPress === 40)
					{
						oToHighlight = this.aDisplayData[i];
						break;
					}

					// If the previous handle was the actual previously selected one and we're hitting down then this is the one we want.
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
					oToHighlight = bPrevHandle;

				// Remove any old highlighting.
				if (this.oSelectedDiv !== null)
					this.itemMouseOut(this.oSelectedDiv);

				// Mark what the selected div now is.
				this.oSelectedDiv = oToHighlight;
				this.itemMouseOver(this.oSelectedDiv);
			}
		break;
	}
	return true;
};

// Functions for integration.
smc_AutoSuggest.prototype.registerCallback = function(sCallbackType, sCallback)
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

// User hit submit?
smc_AutoSuggest.prototype.handleSubmit = function()
{
	var bReturnValue = true,
		oFoundEntry = null;

	// Do we have something that matches the current text?
	for (var i = 0; i < this.aCache.length; i++)
	{
		if (this.sLastSearch.toLowerCase() === this.aCache[i].sItemName.toLowerCase().substr(0, this.sLastSearch.length))
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
					bReturnValue = false;
				else {
					oFoundEntry = {
						sItemId: this.aCache[i].sItemId,
						sItemName: this.aCache[i].sItemName
					};
				}
			}
		}
	}

	if (oFoundEntry === null || bReturnValue === false)
		return bReturnValue;
	else
	{
		this.addItemLink(oFoundEntry.sItemId, oFoundEntry.sItemName, true);
		return false;
	}
};

// Positions the box correctly on the window.
smc_AutoSuggest.prototype.positionDiv = function()
{
	// Only do it once.
	if (this.bPositionComplete)
		return true;

	this.bPositionComplete = true;

	// Put the div under the text box.
	var aParentPos = elk_itemPos(this.oTextHandle);

	this.oSuggestDivHandle.style.left = aParentPos[0] + 'px';
	this.oSuggestDivHandle.style.top = (aParentPos[1] + this.oTextHandle.offsetHeight) + 'px';
	this.oSuggestDivHandle.style.width = this.oTextHandle.clientWidth + 'px';

	return true;
};

// Do something after clicking an item.
smc_AutoSuggest.prototype.itemClicked = function(oEvent)
{
	// Is there a div that we are populating?
	if (this.bItemList)
		this.addItemLink(oEvent.target.sItemId, oEvent.target.innerHTML);
	// Otherwise clear things down.
	else
		this.oTextHandle.value = oEvent.target.innerHTML.php_unhtmlspecialchars();

	this.oRealTextHandle.value = this.oTextHandle.value;
	this.autoSuggestActualHide();
	this.oSelectedDiv = null;
};

// Remove the last searched for name from the search box.
smc_AutoSuggest.prototype.removeLastSearchString = function ()
{
	// Remove the text we searched for from the div.
	var sTempText = this.oTextHandle.value.toLowerCase(),
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
					break;
			}
			else
				break;
		}

		// Now remove anything from iStartString upwards.
		this.oTextHandle.value = this.oTextHandle.value.substr(0, iStartString);
	}
	// Just take it all.
	else
		this.oTextHandle.value = '';
};

// Add a result if not already done.
smc_AutoSuggest.prototype.addItemLink = function (sItemId, sItemName, bFromSubmit)
{
	// Increase the internal item count.
	this.iItemCount ++;

	// If there's a callback then call it.
	if (typeof(this.oCallback.onBeforeAddItem) === 'function')
	{
		// If it returns false the item must not be added.
		if (!this.oCallback.onBeforeAddItem.call(this, sItemId))
			return;
	}

	var oNewDiv = document.createElement('div');
	oNewDiv.id = 'suggest_' + this.opt.sSuggestId + '_' + sItemId;
	oNewDiv.innerHTML = this.sItemTemplate.replace(/%post_name%/g, this.opt.sPostName).replace(/%item_id%/g, sItemId).replace(/%item_href%/g, elk_prepareScriptUrl(elk_scripturl) + this.opt.sURLMask.replace(/%item_id%/g, sItemId)).replace(/%item_name%/g, sItemName).replace(/%images_url%/g, elk_images_url).replace(/%delete_text%/g, this.sTextDeleteItem);

	oNewDiv.getElementsByTagName('img')[0].addEventListener("click", this.deleteAddedItem.bind(this));
	this.oItemList.appendChild(oNewDiv);

	// If there's a registered callback, call it.
	if (typeof(this.oCallback.onAfterAddItem) === 'function')
		this.oCallback.onAfterAddItem.call(this, oNewDiv.id);

	// Clear the div a bit.
	this.removeLastSearchString();

	// If we came from a submit, and there's still more to go, turn on auto add for all the other things.
	this.bDoAutoAdd = this.oTextHandle.value !== '' && bFromSubmit;

	// Update the fellow..
	this.autoSuggestUpdate();
};

// Delete an item that has been added, if at all?
smc_AutoSuggest.prototype.deleteAddedItem = function (oEvent)
{
	var oDiv = oEvent.target.parentNode;

	// Remove the div if it exists.
	if (typeof(oDiv) === 'object' && oDiv !== null)
	{
		this.oItemList.removeChild(oDiv);

		// Decrease the internal item count.
		this.iItemCount --;

		// If there's a registered callback, call it.
		if (typeof(this.oCallback.onAfterDeleteItem) === 'function')
			this.oCallback.onAfterDeleteItem.call(this);
	}

	return false;
};

// Hide the box.
smc_AutoSuggest.prototype.autoSuggestHide = function ()
{
	// Delay to allow events to propagate through....
	this.oHideTimer = setTimeout(this.autoSuggestActualHide.bind(this), 250);
};

// Do the actual hiding after a timeout.
smc_AutoSuggest.prototype.autoSuggestActualHide = function()
{
	this.oSuggestDivHandle.style.display = 'none';
	this.oSuggestDivHandle.style.visibility = 'hidden';
	this.oSelectedDiv = null;
};

// Show the box.
smc_AutoSuggest.prototype.autoSuggestShow = function()
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

// Populate the actual div.
smc_AutoSuggest.prototype.populateDiv = function(aResults)
{
	// Cannot have any children yet.
	while (this.oSuggestDivHandle.childNodes.length > 0)
	{
		// Tidy up the events etc too.
		this.oSuggestDivHandle.childNodes[0].onmouseover = null;
		this.oSuggestDivHandle.childNodes[0].onmouseout = null;
		this.oSuggestDivHandle.childNodes[0].onclick = null;

		this.oSuggestDivHandle.removeChild(this.oSuggestDivHandle.childNodes[0]);
	}

	// Something to display?
	if (typeof(aResults) === 'undefined')
	{
		this.aDisplayData = [];
		return false;
	}

	var aNewDisplayData = [];
	for (var i = 0; i < (aResults.length > this.iMaxDisplayQuantity ? this.iMaxDisplayQuantity : aResults.length); i++)
	{
		// Create the sub element
		var oNewDivHandle = document.createElement('div');

		oNewDivHandle.sItemId = aResults[i].sItemId;
		oNewDivHandle.className = 'auto_suggest_item';
		oNewDivHandle.innerHTML = aResults[i].sItemName;
		//oNewDivHandle.style.width = this.oTextHandle.style.width;

		this.oSuggestDivHandle.appendChild(oNewDivHandle);

		// Attach some events to it so we can do stuff.
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

// Callback function for the XML request, should contain the list of users that match
smc_AutoSuggest.prototype.onSuggestionReceived = function (oXMLDoc)
{
	var sQuoteText = '',
		aItems = oXMLDoc.getElementsByTagName('item');

	// Go through each item received
	this.aCache = [];
	for (var i = 0; i < aItems.length; i++)
	{
		this.aCache[i] = {
			sItemId: aItems[i].getAttribute('id'),
			sItemName: aItems[i].childNodes[0].nodeValue
		};

		// If we're doing auto add and we found the exact person, then add them!
		if (this.bDoAutoAdd && this.sLastSearch === this.aCache[i].sItemName)
		{
			var oReturnValue = {
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
		this.autoSuggestHide();
	else
		this.autoSuggestShow();

	return true;
};

// Get a new suggestion.
smc_AutoSuggest.prototype.autoSuggestUpdate = function ()
{
	// If there's a callback then call it.
	if (typeof(this.oCallback.onBeforeUpdate) === 'function')
	{
		// If it returns false the item must not be added.
		if (!this.oCallback.onBeforeUpdate.call(this))
			return false;
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
		return true;
	this.sLastDirtySearch = this.oTextHandle.value;

	// We're only actually interested in the last string.
	var sSearchString = this.oTextHandle.value.replace(/^("[^"]+",[ ]*)+/, '').replace(/^([^,]+,[ ]*)+/, '');
	if (sSearchString.substr(0, 1) === '"')
		sSearchString = sSearchString.substr(1);

	// Stop replication ASAP.
	var sRealLastSearch = this.sLastSearch;
	this.sLastSearch = sSearchString;

	// Either nothing or we've completed a sentence.
	if (sSearchString === '' || sSearchString.substr(sSearchString.length - 1) === '"')
	{
		this.populateDiv();
		return true;
	}

	// Nothing?
	if (sRealLastSearch === sSearchString)
		return true;
	// Too small?
	else if (sSearchString.length < this.iMinimumSearchChars)
	{
		this.aCache = [];
		this.autoSuggestHide();
		return true;
	}
	else if (sSearchString.substr(0, sRealLastSearch.length) === sRealLastSearch)
	{
		// Instead of hitting the server again, just narrow down the results...
		var aNewCache = [],
			j = 0,
			sLowercaseSearch = sSearchString.toLowerCase();

		for (var k = 0; k < this.aCache.length; k++)
		{
			if (this.aCache[k].sItemName.substr(0, sSearchString.length).toLowerCase() === sLowercaseSearch)
				aNewCache[j++] = this.aCache[k];
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
	if (typeof(this.oXmlRequestHandle) === 'object' && this.oXmlRequestHandle !== null)
		this.oXmlRequestHandle.abort();

	// Clean the text handle.
	sSearchString = sSearchString.php_urlencode();

	// Get the document.
	var obj = {
			"suggest_type": this.opt.sSearchType,
			"search": sSearchString,
			"time": new Date().getTime()
		},
		postString;

	// Post values plus session
	postString = "jsonString=" + JSON.stringify(obj) + "&" + this.opt.sSessionVar + "=" + this.opt.sSessionId;

	sendXMLDocument.call(this, this.sRetrieveURL.replace(/%scripturl%/g, elk_prepareScriptUrl(elk_scripturl)), postString, this.onSuggestionReceived);

	return true;
};
