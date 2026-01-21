/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD https://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 */

/** global: elk_scripturl, elk_session_var, elk_session_id, sceditor */

/*
 * Add Klipy functionality to SCEditor
 */
(function (sceditor) {
	'use strict';

	// Editor instance
	let editor;

	/**
	 * Constructor for the Elk_Klipy class.
	 *
	 * @param {Object} options - The options for Elk_Klipy.
	 * @param {string} [options.next] - The next page position, set from controller
	 * @param {boolean} [options.load_more=false] - Whether to enable the "Load More" feature, done automatically.
	 * @param {string} [options.query] - The initial search query.
	 * @param {HTMLElement} [options.dropDown] - The dropdown element for displaying search/trending results.
	 */
	function Elk_Klipy(options)
	{
		this.defaults = {
			next: null,
			load_more: false,
			query: null,
			dropDown: (() => {
				const divElement = document.createElement('div');

				divElement.innerHTML = `
					<input type="text" id="klipy_search" placeholder="Search" \>
					<div id="klipy_results"></div>
					<a href="https://klipy.com" target="_blank">
						<div id="klipy_attribution_mark"></div>
					</a>`;

				return divElement;
			})()
		};

		this.opts = Object.assign({}, this.defaults, options || {});

		// Set up our event listeners, onscroll, keyup and click
		const self = this;
		this.opts.dropDown.querySelector('#klipy_results').onscroll = (event) => {
			self.scrolling(event.target);
		};

		this.opts.dropDown.querySelector('#klipy_search').addEventListener('keyup', (event) => {
			self.search(event.target.value);
		});

		this.opts.dropDown.querySelector('#klipy_results').addEventListener('click', (e) => {
			if(e.target && e.target.nodeName === "IMG") {
				self.insert(e);
			}
		});
	}

	/**
	 * Perform a debounced search for GIFs using the Klipy API.
	 *
	 * @param {string} query - The search query.
	 */
	Elk_Klipy.prototype.search = debounce(function (query) {
		if (query && query.length > 2)
		{
			this.opts.query = encodeURIComponent(query);
			let url = elk_prepareScriptUrl(elk_scripturl) + 'action=klipy;sa=search;q=' + this.opts.query + ';' + elk_session_var + '=' + elk_session_id + ';api=json';

			fetchDocument(url, (oJsonDoc) => this.onKlipyResponse(oJsonDoc), 'json');
		}

		this.reset(true);
	}, 150);

	/**
	 * Adds GIF grouping to the dropdown.
	 */
	Elk_Klipy.prototype.addGIF = function (data, loadMore) {
		let klipys = data.klipy,
			list = document.querySelector('.klipy-imagelist');

		if (!list)
		{
			list = document.createElement('div');
			list.className = 'klipy-imagelist';
		}

		if (Object.keys(klipys).length === 0 && !loadMore)
		{
			this.reset(true);
			this.opts.dropDown.querySelector('#klipy_results').appendChild(list);

			return;
		}

		for (let key in klipys)
		{
			if (klipys.hasOwnProperty(key))
			{
				let gifDetails = klipys[key],
					linkElement,
					spanElement,
					imgElement;

				// <a class="klipy-item"><span class="klipy-cover"><img>
				linkElement = document.createElement('a');
				linkElement.className = 'klipy-item';

				spanElement = document.createElement('span');
				spanElement.className = 'klipy-cover';

				imgElement = document.createElement('img');
				imgElement.src = gifDetails.src;
				imgElement.alt = gifDetails.title;
				imgElement.id = key;
				imgElement.dataset.insert = gifDetails.insert;
				imgElement.dataset.thumbnail = gifDetails.thumbnail;

				spanElement.appendChild(imgElement);
				linkElement.appendChild(spanElement);
				list.appendChild(linkElement);
			}
		}

		this.opts.dropDown.querySelector('#klipy_results').appendChild(list);
	};

	/**
	 * Triggers a debounced load more action
	 * When the klipy dropdown is scrolled towards the end of the existing list fetch more.
	 * Overlays a spinner while the next group of GIFs is fetched from the server.
	 */
	Elk_Klipy.prototype.scrolling = debounce(function (that) {
		let offset = 35;
		if (that.scrollHeight - (that.scrollTop + offset) <= that.clientHeight)
		{
			this.showSpinnerOverlay();
			this.loadMore();
		}
	}, 150);

	/**
	 * Loads more GIFs into the Elk Klipy instance, trending or search results
	 */
	Elk_Klipy.prototype.loadMore = function () {
		let sa = 'sa=trending;';
		if (this.opts.next !== '')
		{
			this.opts.load_more = true;

			if (this.opts.query !== null)
			{
				sa = 'sa=search;q=' + this.opts.query + ';';
			}

			let url = elk_prepareScriptUrl(elk_scripturl) + 'action=klipy;' + sa + elk_session_var + '=' + elk_session_id + ';page=' + this.opts.next + ';api=json';
			fetchDocument(url, (oJsonDoc) => this.onKlipyResponse(oJsonDoc), 'json');
		}
	};

	/**
	 * Resets the Elk_Klipy instance.  Clears previous results, clear search box
	 */
	Elk_Klipy.prototype.reset = function (resultsOnly) {
		this.opts.dropDown.querySelector('#klipy_results').innerHTML = '';

		if (resultsOnly)
		{
			return;
		}

		this.opts.dropDown.querySelector('#klipy_search').value = '';
		this.opts.query = null;
		this.opts.next = null;
	};

	/**
	 * Retrieves the trending GIFs from Klipy API via promise to onKlipyResponse
	 */
	Elk_Klipy.prototype.getTrending = function () {
		let url = elk_prepareScriptUrl(elk_scripturl) + 'action=klipy;sa=trending;' + elk_session_var + '=' + elk_session_id + ';api=json';
		fetchDocument(url, (oJsonDoc) => this.onKlipyResponse(oJsonDoc), 'json');
	};

	/**
	 * Callback function for handling the response from the Klipy API.  Used by getTrending and search functions
	 */
	Elk_Klipy.prototype.onKlipyResponse = function (oJsonDoc) {
		if (typeof oJsonDoc.klipy !== 'undefined')
		{
			this.addGIF(oJsonDoc, this.opts.load_more);

			this.opts.next = oJsonDoc.data.next || '';
		}

		this.hideSpinnerOverlay();
	};

	/**
	 * Displays a spinner overlay on the Elk_Klipy instance's container element while it's fetching results
	 */
	Elk_Klipy.prototype.showSpinnerOverlay = function () {
		let parentDiv = document.getElementById('klipy_results');
		if (parentDiv)
		{
			parentDiv.innerHTML += '<div id="klipy-overlay"><div class="overlay-spinner"><i class="icon icon-lg i-concentric"></i></div></div>';
		}
	};

	/**
	 * Hides the spinner overlay.
	 */
	Elk_Klipy.prototype.hideSpinnerOverlay = function () {
		let element = document.getElementById('klipy-overlay');
		if (element)
		{
			element.parentNode.removeChild(element);
		}
	};

	/**
	 * Inserts a GIF image into the editor, txt or wizzy, specified by the given event and callback.
	 */
	Elk_Klipy.prototype.insert = function (event) {
		let insert = event.target.dataset.insert;

		this.callback(insert);

		editor.closeDropDown(true);
		this.reset();
	};

	/**
	 * The options object for Elk_Klipy.
	 */
	Elk_Klipy.prototype.opts = {};

	/**
	 * Klipy plugin interface to SCEditor
	 *
	 *  - Called from the editor as a plugin
	 *  - Monitors events, so we control the GIF's
	 */
	sceditor.plugins.klipy = function () {
		let base = this,
			oKlipy;

		/**
		 * Called before signalReady as part of the editor startup process
		 */
		base.init = function () {
			// Grab this instance for use in Klipy
			editor = this;

			oKlipy = new Elk_Klipy({});
			oKlipy.editor = editor;

			// Add the command, will also show our toolbar button
			if (!editor.commands.klipy)
			{
				editor.commands.klipy = {
					txtExec: base.klipyTxt,
					exec: base.klipyWizzy,
					tooltip: "Insert Klipy"
				};
			}
		};

		/**
		 * Set up and display the Klipy dropdown.
		 */
		base.klipyDropDown = function (caller, callback) {
			oKlipy.reset();
			oKlipy.getTrending();
			oKlipy.callback = callback;

			// button clicked, Css (sceditor-klipy), HTML element(s) to use
			editor.createDropDown(caller, 'klipy', oKlipy.opts.dropDown);

			document.querySelector('#klipy_search').focus();
		};

		/**
		 * Callback to insert a chosen GIF from the Klipy dropdown when in wizzy mode
		 */
		base.klipyWizzy = function (caller) {
			base.klipyDropDown(caller, function (gif) {
				editor.insert('[img]' + gif + '[/img]');
			});
		};

		/**
		 * Callback to insert a chosen GIF from the Klipy dropdown when in txt/bbc mode
		 */
		base.klipyTxt = function (caller) {
			base.klipyDropDown(caller, function (gif) {
				editor.insertText('[img]' + gif + '[/img]');
			});
		};
	};
})(sceditor);
