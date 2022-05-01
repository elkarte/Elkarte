/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 */

/**
 * Returns the config object for chart.js, used in profile stats
 *
 * @param {object} bar_data
 * @param {array} tooltips
 * @returns {{data, options: {indexAxis: string, plugins: {legend: {display: boolean}, tooltip: {callbacks: {label: (function(*): *)}}, title: {display: boolean}}, elements: {bar: {borderWidth: number}}, responsive: boolean, scales: {yAxis: {ticks: {font: {size: number}}, grid: {display: boolean}, afterFit: options.scales.yAxis.afterFit}, xAxis: {ticks: {display: boolean, stepSize: number}}}, categoryPercentage: string}, type: string}}
 */
function barConfig(bar_data, tooltips)
{
	return {
		type: "bar",
		data: bar_data,
		options: {
			indexAxis: "y",
			categoryPercentage: ".9",
			elements: {
				bar: {
					borderWidth: 0,
				}
			},
			responsive: true,
			scales: {
				y: {
					grid: {display: false},
					// Sets the yAxis to an initial 200px wide
					afterFit: function(scaleInstance) {
						scaleInstance.width = 200;
					},
					ticks: {
						font: {
							size: 15,
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
function setYearClickEvents()
{
	document.querySelectorAll(".stats_button").forEach(item => {
		item.addEventListener("click", event => {
			year = event.target.getAttribute("data-year") || year;
			request = event.target.getAttribute("data-title") || request;

			let	newDataset = {
				label: titles[request],
				data: year === "all" ? Object.values(yeardata[request]) : Object.values(monthdata[request][year]),
				backgroundColor: [
					"rgba(" + colors[request] + ", 0.1)",
				],
				borderColor: [
					"rgba(" + colors[request] + ", 1)",
				],
				borderWidth: 1,
				pointStyle: "circle",
				pointRadius: 4,
				lineTension: 0.2,
				fill: "origin"
			};

			// Out with the old and in with the new
			yearDataset.labels = year === "all" ? Object.values(yeardata.axis_labels) : Object.values(monthdata.axis_labels[year]);
			yearDataset.datasets.pop();
			yearDataset.datasets.push(newDataset);
			yearStats.update();
		});
	});
}