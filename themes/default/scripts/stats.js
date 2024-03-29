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
 * This file contains javascript associated with the displaying statistics and the like
 */

/**
 * Stats object for expanding / collapsing the forum year/month stats section
 *
 * @param {object} oOptions
 */
function elk_StatsCenter (oOptions)
{
	this.opt = oOptions;
	this.oTable = null;
	this.oYears = {};
	this.bIsLoading = false;

	this.init();
}

// Start up the stats area, current year/month expanded, all others collapsed and ready for action
elk_StatsCenter.prototype.init = function() {
	this.oTable = document.getElementById(this.opt.sTableId);

	// Is the table actually present?
	if (typeof (this.oTable) !== 'object')
	{
		return;
	}

	// Find all months and years defined in the table.
	let aRows = this.oTable.getElementsByTagName('tr'),
		aResults = [],
		sYearId = null,
		oCurYear = null,
		sMonthId = null,
		oCurMonth = null;

	for (let i = 0, n = aRows.length; i < n; i++)
	{
		// Check if the current row represents a year.
		if ((aResults = this.opt.reYearPattern.exec(aRows[i].id)) !== null)
		{
			// The id is part of the pattern match.
			sYearId = aResults[1];

			// Setup the object that'll have the state information of the year.
			this.oYears[sYearId] = {
				oCollapseImage: document.getElementById(this.opt.sYearImageIdPrefix + sYearId),
				oMonths: {}
			};

			// Create a shortcut, makes things more readable.
			oCurYear = this.oYears[sYearId];

			// Use the collapse image to determine the current state.
			oCurYear.bIsCollapsed = oCurYear.oCollapseImage.src.indexOf(this.opt.sYearImageCollapsed) >= 0;

			// Setup the toggle element for the year.
			oCurYear.oToggle = new elk_Toggle({
				bToggleEnabled: true,
				bCurrentlyCollapsed: oCurYear.bIsCollapsed,
				instanceRef: this,
				sYearId: sYearId,
				funcOnBeforeCollapse: function() {
					this.opt.instanceRef.onBeforeCollapseYear(this);
				},
				aSwappableContainers: [],
				aSwapImages: [
					{
						sId: this.opt.sYearImageIdPrefix + sYearId,
						srcExpanded: elk_images_url + '/' + this.opt.sYearImageExpanded,
						altExpanded: '&#9650;',
						srcCollapsed: elk_images_url + '/' + this.opt.sYearImageCollapsed,
						altCollapsed: '&#9660;'
					}
				],
				aSwapLinks: [
					{
						sId: this.opt.sYearLinkIdPrefix + sYearId,
						msgExpanded: sYearId,
						msgCollapsed: sYearId
					}
				]
			});
		}
		// Or maybe the current row represents a month.
		else if ((aResults = this.opt.reMonthPattern.exec(aRows[i].id)) !== null)
		{
			// Set the id to the matched pattern.
			sMonthId = aResults[1];

			// Initialize the month as a child object of the year.
			oCurYear.oMonths[sMonthId] = {
				oCollapseImage: document.getElementById(this.opt.sMonthImageIdPrefix + sMonthId)
			};

			// Create a shortcut to the current month.
			oCurMonth = oCurYear.oMonths[sMonthId];

			// Determine whether the month is currently collapsed or expanded..
			oCurMonth.bIsCollapsed = oCurMonth.oCollapseImage.src.indexOf(this.opt.sMonthImageCollapsed) >= 0;

			let sLinkText = document.getElementById(this.opt.sMonthLinkIdPrefix + sMonthId).innerHTML;

			// Setup the toggle element for the month.
			oCurMonth.oToggle = new elk_Toggle({
				bToggleEnabled: true,
				bCurrentlyCollapsed: oCurMonth.bIsCollapsed,
				instanceRef: this,
				sMonthId: sMonthId,
				funcOnBeforeCollapse: function() {
					this.opt.instanceRef.onBeforeCollapseMonth(this);
				},
				funcOnBeforeExpand: function() {
					this.opt.instanceRef.onBeforeExpandMonth(this);
				},
				aSwappableContainers: [],
				aSwapImages: [
					{
						sId: this.opt.sMonthImageIdPrefix + sMonthId,
						srcExpanded: elk_images_url + '/' + this.opt.sMonthImageExpanded,
						altExpanded: '&#9650;',
						srcCollapsed: elk_images_url + '/' + this.opt.sMonthImageCollapsed,
						altCollapsed: '&#9660;'
					}
				],
				aSwapLinks: [
					{
						sId: this.opt.sMonthLinkIdPrefix + sMonthId,
						msgExpanded: sLinkText,
						msgCollapsed: sLinkText
					}
				]
			});

			oCurYear.oToggle.opt.aSwappableContainers[oCurYear.oToggle.opt.aSwappableContainers.length] = aRows[i].id;
		}
		// Must be a day then
		else if ((aResults = this.opt.reDayPattern.exec(aRows[i].id)) !== null)
		{
			oCurMonth.oToggle.opt.aSwappableContainers[oCurMonth.oToggle.opt.aSwappableContainers.length] = aRows[i].id;
			oCurYear.oToggle.opt.aSwappableContainers[oCurYear.oToggle.opt.aSwappableContainers.length] = aRows[i].id;
		}
	}

	// Collapse all collapsed years!
	for (i = 0; i < this.opt.aCollapsedYears.length; i++)
	{
		this.oYears[this.opt.aCollapsedYears[i]].oToggle.toggle();
	}
};

// Helper function for year collapsing, close the months first
elk_StatsCenter.prototype.onBeforeCollapseYear = function(oToggle) {
	// Tell ElkArte that all underlying months have disappeared.
	for (let sMonth in this.oYears[oToggle.opt.sYearId].oMonths)
	{
		if (this.oYears[oToggle.opt.sYearId].oMonths[sMonth].oToggle.opt.aSwappableContainers.length > 0)
		{
			this.oYears[oToggle.opt.sYearId].oMonths[sMonth].oToggle.changeState(true);
		}
	}
};

// Helper function, called before the month is collapsed
elk_StatsCenter.prototype.onBeforeCollapseMonth = function(oToggle) {
	if (!oToggle.bCollapsed)
	{
		// Tell ElkArte that the state has changed.
		getXMLDocument(elk_prepareScriptUrl(elk_scripturl) + 'action=stats;collapse=' + oToggle.opt.sMonthId + ';api=xml');

		// Remove the month rows from the year toggle.
		let aNewContainers = [],
			oYearToggle = this.oYears[oToggle.opt.sMonthId.substr(0, 4)].oToggle;

		for (let i = 0, n = oYearToggle.opt.aSwappableContainers.length; i < n; i++)
		{
			if (!in_array(oYearToggle.opt.aSwappableContainers[i], oToggle.opt.aSwappableContainers))
			{
				aNewContainers[aNewContainers.length] = oYearToggle.opt.aSwappableContainers[i];
			}
		}

		oYearToggle.opt.aSwappableContainers = aNewContainers;
	}
};

// Helper function, called before the month is expanded out, makes the ajax call to get the data
elk_StatsCenter.prototype.onBeforeExpandMonth = function(oToggle) {
	// Ignore if we're still loading the previous batch.
	if (this.bIsLoading)
	{
		return;
	}

	if (oToggle.opt.aSwappableContainers.length === 0)
	{
		// Make the ajax call
		sendXMLDocument.call(this, elk_prepareScriptUrl(elk_scripturl) + 'action=stats;expand=' + oToggle.opt.sMonthId + ';api=xml', '', this.onDocReceived);

		if ('ajax_indicator' in window)
		{
			ajax_indicator(true);
		}

		this.bIsLoading = true;
	}
	// Silently let ElkArte know this one is expanded.
	else
	{
		getXMLDocument(elk_prepareScriptUrl(elk_scripturl) + 'action=stats;expand=' + oToggle.opt.sMonthId + ';api=xml');
	}
};

// Callback for the ajax call to get the statistical information for the expanded section
elk_StatsCenter.prototype.onDocReceived = function(oXMLDoc) {
	// Loop through all the months we got from the XML.
	let aMonthNodes = oXMLDoc.getElementsByTagName('month');

	// All the data to expand
	let sMonthId,
		iStart,
		sYearId,
		aDayNodes,
		iDayIndex,
		oCurRow,
		oCurCell,
		sCurData,
		iCellIndex;

	for (let iMonthIndex = 0, iNumMonths = aMonthNodes.length; iMonthIndex < iNumMonths; iMonthIndex++)
	{
		sMonthId = aMonthNodes[iMonthIndex].getAttribute('id');
		iStart = document.getElementById('tr_month_' + sMonthId).rowIndex + 1;
		sYearId = sMonthId.substr(0, 4);

		// Within the current months, check out all the days.
		aDayNodes = aMonthNodes[iMonthIndex].getElementsByTagName('day');
		for (iDayIndex = 0, iNumDays = aDayNodes.length; iDayIndex < iNumDays; iDayIndex++)
		{
			oCurRow = this.oTable.insertRow(iStart + iDayIndex);
			oCurRow.className = this.opt.sDayRowClassname;
			oCurRow.id = this.opt.sDayRowIdPrefix + aDayNodes[iDayIndex].getAttribute('date');

			for (iCellIndex = 0, iNumCells = this.opt.aDataCells.length; iCellIndex < iNumCells; iCellIndex++)
			{
				oCurCell = oCurRow.insertCell(-1);

				if (this.opt.aDataCells[iCellIndex] === 'date')
				{
					oCurCell.style.paddingLeft = '6ex';
				}
				else
				{
					oCurCell.style.textAlign = 'center';
				}

				sCurData = aDayNodes[iDayIndex].getAttribute(this.opt.aDataCells[iCellIndex]);
				oCurCell.appendChild(document.createTextNode(sCurData));
			}

			// Add these day rows to the toggle objects in case of collapse.
			this.oYears[sYearId].oMonths[sMonthId].oToggle.opt.aSwappableContainers[this.oYears[sYearId].oMonths[sMonthId].oToggle.opt.aSwappableContainers.length] = oCurRow.id;
			this.oYears[sYearId].oToggle.opt.aSwappableContainers[this.oYears[sYearId].oToggle.opt.aSwappableContainers.length] = oCurRow.id;
		}
	}

	this.bIsLoading = false;
	if (typeof (window.ajax_indicator) === 'function')
	{
		ajax_indicator(false);
	}
};
