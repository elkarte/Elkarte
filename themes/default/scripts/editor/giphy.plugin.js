/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD https://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 */

/** global: elk_scripturl, elk_session_var, elk_session_id, sceditor */

/*
 * Add Giphy functionality to SCEditor
 */
(function (sceditor) {
	'use strict';

	// Editor instance
	let editor;

	/**
	 * Constructor for the Elk_Giphy class.
	 *
	 * @param {Object} options - The options for Elk_Giphy.
	 * @param {number} [options.limit] - The maximum number of results to display, set from controller
	 * @param {number} [options.total_count] - The total number of available results, set from controller
	 * @param {boolean} [options.load_more=false] - Whether to enable the "Load More" feature, done automatically.
	 * @param {string} [options.query] - The initial search query.
	 * @param {HTMLElement} [options.dropDown] - The dropdown element for displaying search/trending results.
	 */
	function Elk_Giphy(options)
	{
		this.defaults = {
			limit: null,
			total_count: null,
			load_more: false,
			query: null,
			dropDown: (() => {
				const divElement = document.createElement('div');

				divElement.innerHTML = `
					<input type="text" id="giphy_search" placeholder="Search" \>
					<div id="giphy_results"></div>
					<a href="https://giphy.com" target="_blank">
						<div id="giphy_attribution_mark"></div>
					</a>`;

				return divElement;
			})()
		};

		this.opts = Object.assign({}, this.defaults, options || {});

		// Setup our event listeners, onscroll, keyup and click
		const self = this;
		this.opts.dropDown.querySelector('#giphy_results').onscroll = (event) => {
			self.scrolling(event.target);
		};

		this.opts.dropDown.querySelector('#giphy_search').addEventListener('keyup', (event) => {
			self.search(event.target.value);
		});

		this.opts.dropDown.querySelector('#giphy_results').addEventListener('click', (e) => {
			if(e.target && e.target.nodeName === "IMG") {
				self.insert(e);
			}
		});
	}

	/**
	 * Perform a debounce search for GIFs using the Giphy API.
	 *
	 * @param {string} query - The search query.
	 * @returns {Promise<Array<string>>} - A promise that resolves to an array of GIF URLs.
	 */
	Elk_Giphy.prototype.search = debounce(function (query) {
		if (query && query.length > 2)
		{
			this.opts.query = encodeURIComponent(query);
			let url = elk_prepareScriptUrl(elk_scripturl) + 'action=giphy;sa=search;q=' + this.opts.query + ';' + elk_session_var + '=' + elk_session_id + ';api=json';

			fetchDocument(url, (oJsonDoc) => this.onGiphyResponse(oJsonDoc), 'json');
		}

		this.reset(true);
	}, 150);

	/**
	 * Adds GIF grouping to the dropdown.
	 */
	Elk_Giphy.prototype.addGIF = function (data, loadMore) {
		let giphies = data.giphy,
			list = document.querySelector('.giphy-imagelist');

		if (!list)
		{
			list = document.createElement('div');
			list.className = 'giphy-imagelist';
		}

		if (Object.keys(giphies).length === 0 && !loadMore)
		{
			this.reset(true);
			this.opts.dropDown.querySelector('#giphy_results').appendChild(list);

			return;
		}

		for (let key in giphies)
		{
			if (giphies.hasOwnProperty(key))
			{
				let gifDetails = giphies[key],
					linkElement,
					spanElement,
					imgElement;

				// <a class="giphy-item"><span class="giphy-cover"><img>
				linkElement = document.createElement('a');
				linkElement.className = 'giphy-item';

				spanElement = document.createElement('span');
				spanElement.className = 'giphy-cover';

				imgElement = document.createElement('img');
				imgElement.src = gifDetails.src;
				imgElement.alt = gifDetails.title;
				imgElement.id = gifDetails.key;
				imgElement.dataset.insert = gifDetails.insert;
				imgElement.dataset.thumbnail = gifDetails.thumbnail;

				spanElement.appendChild(imgElement);
				linkElement.appendChild(spanElement);
				list.appendChild(linkElement);
			}
		}

		this.opts.dropDown.querySelector('#giphy_results').appendChild(list);
	};

	/**
	 * Triggers a debounced load more action
	 * When the giphy dropdown is scrolled towards the end of the existing list fetch more.
	 * Overlays a spinner while the next group of gifs is fetched from the server.
	 */
	Elk_Giphy.prototype.scrolling = debounce(function (that) {
		let offset = 35;
		if (that.scrollHeight - (that.scrollTop + offset) <= that.clientHeight)
		{
			this.showSpinnerOverlay();
			this.loadMore();
		}
	}, 150);

	/**
	 * Loads more GIFs into the Elk Giphy instance, trending or search results
	 */
	Elk_Giphy.prototype.loadMore = function () {
		let sa = 'sa=trending;';
		if (this.opts.offset < this.opts.total_count)
		{
			this.opts.load_more = true;

			if (this.opts.query !== null)
			{
				sa = 'sa=search;q=' + this.opts.query + ';';
			}

			let url = elk_prepareScriptUrl(elk_scripturl) + 'action=giphy;' + sa + elk_session_var + '=' + elk_session_id + ';offset= ' + this.opts.offset + ';api=json';
			fetchDocument(url, (oJsonDoc) => this.onGiphyResponse(oJsonDoc), 'json');
		}
	};

	/**
	 * Resets the Elk_Giphy instance.  Clears previous results, clear search box
	 */
	Elk_Giphy.prototype.reset = function (resultsOnly) {
		this.opts.dropDown.querySelector('#giphy_results').innerHTML = '';

		if (resultsOnly)
		{
			return;
		}

		this.opts.dropDown.querySelector('#giphy_search').value = '';
		this.opts.query = null;
	};

	/**
	 * Retrieves the trending GIFs from Giphy API via promise to onGiphyResponse
	 */
	Elk_Giphy.prototype.getTrending = function () {
		let url = elk_prepareScriptUrl(elk_scripturl) + 'action=giphy;sa=trending;' + elk_session_var + '=' + elk_session_id + ';api=json';
		fetchDocument(url, (oJsonDoc) => this.onGiphyResponse(oJsonDoc), 'json');
	};

	/**
	 * Callback function for handling the response from the Giphy API.  Used by getTrending and search functions
	 */
	Elk_Giphy.prototype.onGiphyResponse = function (oJsonDoc) {
		if (typeof oJsonDoc.data.meta !== 'undefined' && oJsonDoc.data.meta.msg === "OK")
		{
			this.addGIF(oJsonDoc, this.opts.load_more);

			this.opts.offset = oJsonDoc.data.pagination.offset + oJsonDoc.data.pagination.limit;
			this.opts.limit = oJsonDoc.data.pagination.limit;
			this.opts.total_count = oJsonDoc.data.pagination.total_count;
		}

		this.hideSpinnerOverlay();
	};

	/**
	 * Displays a spinner overlay on the Elk_Giphy instance's container element while its fetching results
	 */
	Elk_Giphy.prototype.showSpinnerOverlay = function () {
		let parentDiv = document.getElementById('giphy_results');
		if (parentDiv)
		{
			parentDiv.innerHTML += '<div id="giphy-overlay"><div class="overlay-spinner"><i class="icon icon-lg i-concentric"></i></div></div>';
		}
	};

	/**
	 * Hides the spinner overlay.
	 */
	Elk_Giphy.prototype.hideSpinnerOverlay = function () {
		let element = document.getElementById('giphy-overlay');
		if (element)
		{
			element.parentNode.removeChild(element);
		}
	};

	/**
	 * Inserts a GIF image into the editor, txt or wizzy, specified by the given event and callback.
	 */
	Elk_Giphy.prototype.insert = function (event) {
		let insert = event.target.dataset.insert;

		this.callback(insert);

		editor.closeDropDown(true);
		this.reset();
	};

	/**
	 * The options object for Elk_Giphy.
	 */
	Elk_Giphy.prototype.opts = {};

	/**
	 * Giphy plugin interface to SCEditor
	 *
	 *  - Called from the editor as a plugin
	 *  - Monitors events, so we control the gif's
	 */
	sceditor.plugins.giphy = function () {
		let base = this,
			oGiphy;

		/**
		 * Called before signalReady as part of the editor startup process
		 */
		base.init = function () {
			// Grab this instance for use in Giphy
			editor = this;

			oGiphy = new Elk_Giphy();
			oGiphy.editor = editor;

			// Add the command, will also show our toolbar button
			if (!editor.commands.giphy)
			{
				editor.commands.giphy = {
					txtExec: base.giphyTxt,
					exec: base.giphyWizzy,
					tooltip: "Insert Giphy"
				};
			}
		};

		/**
		 * Set up and display the Giphy dropdown.
		 */
		base.giphyDropDown = function (caller, callback) {
			oGiphy.reset();
			oGiphy.getTrending();
			oGiphy.callback = callback;

			// button clicked, Css (sceditor-giphy), HTML element(s) to use
			editor.createDropDown(caller, 'giphy', oGiphy.opts.dropDown);

			document.querySelector('#giphy_search').focus();
		};

		/**
		 * Callback to insert a chosen GIF from the Giphy dropdown when in wizzy mode
		 */
		base.giphyWizzy = function (caller) {
			base.giphyDropDown(caller, function (gif) {
				editor.insert('[img]' + gif + '[/img]');
			});
		};

		/**
		 * Callback to insert a chosen GIF from the Giphy dropdown when in txt/bbc mode
		 */
		base.giphyTxt = function (caller) {
			base.giphyDropDown(caller, function (gif) {
				editor.insertText('[img]' + gif + '[/img]');
			});
		};
	};
})(sceditor);
