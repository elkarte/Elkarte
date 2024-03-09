/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 */

/**
 * This function changes the relative time around the page real-timeish
 */
function updateRelativeTime() {
	const timeElements = document.querySelectorAll('time');

	let relative_time_refresh = 3600000;

	timeElements.forEach(function(timeElement) {
		let oRelativeTime = new relativeTime(timeElement.getAttribute('data-timestamp') * 1000, oRttime.referenceTime),
			time_text = '';

		if (oRelativeTime.seconds()) {
			timeElement.textContent = oRttime.now;
			relative_time_refresh = Math.min(relative_time_refresh, 10000);
		}
		else if (oRelativeTime.minutes())
		{
			time_text = oRelativeTime.deltaTime > 1 ? oRttime.minutes : oRttime.minute;
			timeElement.textContent = time_text.replace('%s', oRelativeTime.deltaTime);
			relative_time_refresh = Math.min(relative_time_refresh, 60000);
		}
		else if (oRelativeTime.hours())
		{
			time_text = oRelativeTime.deltaTime > 1 ? oRttime.hours : oRttime.hour;
			timeElement.textContent = time_text.replace('%s', oRelativeTime.deltaTime);
			relative_time_refresh = Math.min(relative_time_refresh, 3600000);
		}
		else if (oRelativeTime.days())
		{
			time_text = oRelativeTime.deltaTime > 1 ? oRttime.days : oRttime.day;
			timeElement.textContent = time_text.replace('%s', oRelativeTime.deltaTime);
			relative_time_refresh = Math.min(relative_time_refresh, 3600000);
		}
		else if (oRelativeTime.weeks())
		{
			time_text = oRelativeTime.deltaTime > 1 ? oRttime.weeks : oRttime.week;
			timeElement.textContent = time_text.replace('%s', oRelativeTime.deltaTime);
			relative_time_refresh = Math.min(relative_time_refresh, 3600000);
		}
		else if (oRelativeTime.months())
		{
			time_text = oRelativeTime.deltaTime > 1 ? oRttime.months : oRttime.month;
			timeElement.textContent = time_text.replace('%s', oRelativeTime.deltaTime);
			relative_time_refresh = Math.min(relative_time_refresh, 3600000);
		}
		else if (oRelativeTime.years())
		{
			time_text = oRelativeTime.deltaTime > 1 ? oRttime.years : oRttime.year;
			timeElement.textContent = time_text.replace('%s', oRelativeTime.deltaTime);
			relative_time_refresh = Math.min(relative_time_refresh, 3600000);
		}
	});

	oRttime.referenceTime += relative_time_refresh;

	setTimeout(function() {
		updateRelativeTime();
	}, relative_time_refresh);
}

/**
 * Function/object to handle relative times
 *
 * sTo is optional, if omitted the relative time is calculated from sFrom up to "now"
 *
 * @param {int} sFrom
 * @param {int} sTo
 */
function relativeTime(sFrom, sTo)
{
	// helper function to reduce code repetition
	const createDate = (s) => {
		let date = new Date(s);

		if (isNaN(date.getTime()))
		{
			const sSplit = s.split(/\D/);
			date = new Date(sSplit[0], --sSplit[1], sSplit[2], sSplit[3], sSplit[4]);
		}

		return date;
	};

	try
	{
		this.dateFrom = createDate(sFrom);
		this.dateTo = sTo ? createDate(sTo) : new Date();
		if (isNaN(this.dateFrom.getTime()) || isNaN(this.dateTo.getTime()))
		{
			throw new Error('Invalid date');
		}
	}
	catch (error)
	{
		if ('console' in window && console.error)
		{
			console.error('Invalid date provided', error);
		}
		return;
	}

	this.past_time = (this.dateTo - this.dateFrom) / 1000;
	this.deltaTime = 0;
}

relativeTime.prototype.seconds = function () {
	// Within the first 60 seconds it is just now.
	if (this.past_time < 60)
	{
		this.deltaTime = this.past_time;
		return true;
	}

	return false;
};

relativeTime.prototype.minutes = function () {
	// Within the first hour?
	if (this.past_time >= 60 && Math.round(this.past_time / 60) < 60)
	{
		this.deltaTime = Math.round(this.past_time / 60);
		return true;
	}

	return false;
};

relativeTime.prototype.hours = function () {
	// Some hours but less than a day?
	if (Math.round(this.past_time / 60) >= 60 && Math.round(this.past_time / 3600) < 24)
	{
		this.deltaTime = Math.round(this.past_time / 3600);
		return true;
	}

	return false;
};

relativeTime.prototype.days = function () {
	// Some days ago but less than a week?
	if (Math.round(this.past_time / 3600) >= 24 && Math.round(this.past_time / (24 * 3600)) < 7)
	{
		this.deltaTime = Math.round(this.past_time / (24 * 3600));
		return true;
	}

	return false;
};

relativeTime.prototype.weeks = function () {
	// Weeks ago but less than a month?
	if (Math.round(this.past_time / (24 * 3600)) >= 7 && Math.round(this.past_time / (24 * 3600)) < 30)
	{
		this.deltaTime = Math.round(this.past_time / (24 * 3600) / 7);
		return true;
	}

	return false;
};

relativeTime.prototype.months = function () {
	// Months ago but less than a year?
	if (Math.round(this.past_time / (24 * 3600)) >= 30 && Math.round(this.past_time / (30 * 24 * 3600)) < 12)
	{
		this.deltaTime = Math.round(this.past_time / (30 * 24 * 3600));
		return true;
	}

	return false;
};

relativeTime.prototype.years = function () {
	// Oha, we've passed at least a year?
	if (Math.round(this.past_time / (30 * 24 * 3600)) >= 12)
	{
		this.deltaTime = this.dateTo.getFullYear() - this.dateFrom.getFullYear();
		return true;
	}

	return false;
};
