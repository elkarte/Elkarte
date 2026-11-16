/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 2
 */

/** global: titles, year, monthdata, yeardata, request, colors, yearDataset, yearStats */

/**
 * Returns the config object for chart.js, used in forum/profile statistics
 *
 * @param {object} bar_data
 * @param {array} tooltips
 * @returns {object}
 */
function barConfig (bar_data, tooltips)
{
	return {
		type: 'bar',
		data: bar_data,
		options: {
			indexAxis: 'y',
			categoryPercentage: 0.9,
			elements: {
				bar: {
					borderWidth: 0,
				}
			},
			responsive: true,
			maintainAspectRatio: false,
			scales: {
				y: {
					grid: {display: false},
					// Keep most of the canvas available for bars on narrow screens.
					afterFit: function(scaleInstance) {
						scaleInstance.width = Math.min(200, scaleInstance.chart.width * 0.4);
					},
					ticks: {
						font: {
							size: 15,
						},
						callback: function(value) {
							const label = this.getLabelForValue(value),
								maxWidth = Math.min(200, this.chart.width * 0.4) - 20,
								ctx = this.chart.ctx;
							let text = label;

							ctx.save();
							ctx.font = Chart.helpers.toFont(this.options.ticks.font).string;
							if (ctx.measureText(text).width > maxWidth)
							{
								while (text.length > 0 && ctx.measureText(text + '…').width > maxWidth)
								{
									text = text.slice(0, -1);
								}
								text += '…';
							}
							ctx.restore();

							return text;
						}
					},
				},
				x: {
					ticks: {
						display: true,
					},
				}
			},
			plugins: {
				title: {
					display: false
				},
				legend: {
					display: false
				},
				tooltip: {
					callbacks: {
						label: function(context) {
							return tooltips[context.dataIndex];
						}
					}
				}
			}
		},
	};
}

/**
 * Click events are tied to each chart button, the data attribute is read and
 * the appropriate dataset is then fed to chart.js.
 *
 * Request and Year are set as `global` values in the showLineChart() function in stats.template
 */
function setYearClickEvents ()
{
	document.querySelectorAll('.stats_button').forEach(item => {
		item.addEventListener('click', event => {
			year = event.target.getAttribute('data-year') || year;
			request = event.target.getAttribute('data-title') || request;

			let newDataset = {
				label: titles[request],
				data: year === 'all' ? Object.values(yeardata[request]) : Object.values(monthdata[request][year]),
				backgroundColor: [
					'rgba(' + colors[request] + ', 0.1)',
				],
				borderColor: [
					'rgba(' + colors[request] + ', 1)',
				],
				borderWidth: 1,
				pointStyle: 'circle',
				pointRadius: 4,
				tension: 0.2,
				fill: 'origin'
			};

			// Out with the old and in with the new
			yearDataset.labels = year === 'all' ? Object.values(yeardata.axis_labels) : Object.values(monthdata.axis_labels[year]);
			yearDataset.datasets.pop();
			yearDataset.datasets.push(newDataset);
			yearStats.update();
		});
	});
}
