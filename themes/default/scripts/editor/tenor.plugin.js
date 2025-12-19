/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD https://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 */

/** global: elk_scripturl, elk_session_var, elk_session_id, sceditor */

/*
 * Add Tenor functionality to SCEditor
 */
(function (sceditor) {
	'use strict';

	// Editor instance
	let editor;

	/**
	 * Constructor for the Elk_Tenor class.
	 *
	 * @param {Object} options - The options for Elk_Tenor.
	 * @param {string} [options.next] - The next page position, set from controller
	 * @param {boolean} [options.load_more=false] - Whether to enable the "Load More" feature, done automatically.
	 * @param {string} [options.query] - The initial search query.
	 * @param {HTMLElement} [options.dropDown] - The dropdown element for displaying search/trending results.
	 */
	function Elk_Tenor(options)
	{
		this.defaults = {
			next: null,
			load_more: false,
			query: null,
			dropDown: (() => {
				const divElement = document.createElement('div');

				divElement.innerHTML = `
					<input type="text" id="tenor_search" placeholder="Search" \>
					<div id="tenor_results"></div>
					<a href="https://tenor.com" target="_blank">
						<div id="tenor_attribution_mark"></div>
					</a>`;

				return divElement;
			})()
		};

		this.opts = Object.assign({}, this.defaults, options || {});

		// Set up our event listeners, onscroll, keyup and click
		const self = this;
		this.opts.dropDown.querySelector('#tenor_results').onscroll = (event) => {
			self.scrolling(event.target);
		};

		this.opts.dropDown.querySelector('#tenor_search').addEventListener('keyup', (event) => {
			self.search(event.target.value);
		});

		this.opts.dropDown.querySelector('#tenor_results').addEventListener('click', (e) => {
			if(e.target && e.target.nodeName === "IMG") {
				self.insert(e);
			}
		});
	}

	/**
	 * Perform a debounce search for GIFs using the Tenor API.
	 *
	 * @param {string} query - The search query.
	 */
	Elk_Tenor.prototype.search = debounce(function (query) {
		if (query && query.length > 2)
		{
			this.opts.query = encodeURIComponent(query);
			let url = elk_prepareScriptUrl(elk_scripturl) + 'action=tenor;sa=search;q=' + this.opts.query + ';' + elk_session_var + '=' + elk_session_id + ';api=json';

			fetchDocument(url, (oJsonDoc) => this.onTenorResponse(oJsonDoc), 'json');
		}

		this.reset(true);
	}, 150);

	/**
	 * Adds GIF grouping to the dropdown.
	 */
	Elk_Tenor.prototype.addGIF = function (data, loadMore) {
		let tenors = data.tenor,
			list = document.querySelector('.tenor-imagelist');

		if (!list)
		{
			list = document.createElement('div');
			list.className = 'tenor-imagelist';
		}

		if (Object.keys(tenors).length === 0 && !loadMore)
		{
			this.reset(true);
			this.opts.dropDown.querySelector('#tenor_results').appendChild(list);

			return;
		}

		for (let key in tenors)
		{
			if (tenors.hasOwnProperty(key))
			{
				let gifDetails = tenors[key],
					linkElement,
					spanElement,
					imgElement;

				// <a class="tenor-item"><span class="tenor-cover"><img>
				linkElement = document.createElement('a');
				linkElement.className = 'tenor-item';

				spanElement = document.createElement('span');
				spanElement.className = 'tenor-cover';

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

		this.opts.dropDown.querySelector('#tenor_results').appendChild(list);
	};

	/**
	 * Triggers a debounced load more action
	 * When the tenor dropdown is scrolled towards the end of the existing list fetch more.
	 * Overlays a spinner while the next group of GIFs is fetched from the server.
	 */
	Elk_Tenor.prototype.scrolling = debounce(function (that) {
		let offset = 35;
		if (that.scrollHeight - (that.scrollTop + offset) <= that.clientHeight)
		{
			this.showSpinnerOverlay();
			this.loadMore();
		}
	}, 150);

	/**
	 * Loads more GIFs into the Elk Tenor instance, trending or search results
	 */
	Elk_Tenor.prototype.loadMore = function () {
		let sa = 'sa=trending;';
		if (this.opts.next !== '')
		{
			this.opts.load_more = true;

			if (this.opts.query !== null)
			{
				sa = 'sa=search;q=' + this.opts.query + ';';
			}

			let url = elk_prepareScriptUrl(elk_scripturl) + 'action=tenor;' + sa + elk_session_var + '=' + elk_session_id + ';pos=' + this.opts.next + ';api=json';
			fetchDocument(url, (oJsonDoc) => this.onTenorResponse(oJsonDoc), 'json');
		}
	};

	/**
	 * Resets the Elk_Tenor instance.  Clears previous results, clear search box
	 */
	Elk_Tenor.prototype.reset = function (resultsOnly) {
		this.opts.dropDown.querySelector('#tenor_results').innerHTML = '';

		if (resultsOnly)
		{
			return;
		}

		this.opts.dropDown.querySelector('#tenor_search').value = '';
		this.opts.query = null;
		this.opts.next = null;
	};

	/**
	 * Retrieves the trending GIFs from Tenor API via promise to onTenorResponse
	 */
	Elk_Tenor.prototype.getTrending = function () {
		let url = elk_prepareScriptUrl(elk_scripturl) + 'action=tenor;sa=trending;' + elk_session_var + '=' + elk_session_id + ';api=json';
		fetchDocument(url, (oJsonDoc) => this.onTenorResponse(oJsonDoc), 'json');
	};

	/**
	 * Callback function for handling the response from the Tenor API.  Used by getTrending and search functions
	 */
	Elk_Tenor.prototype.onTenorResponse = function (oJsonDoc) {
		if (typeof oJsonDoc.tenor !== 'undefined')
		{
			this.addGIF(oJsonDoc, this.opts.load_more);

			this.opts.next = oJsonDoc.data.next || '';
		}

		this.hideSpinnerOverlay();
	};

	/**
	 * Displays a spinner overlay on the Elk_Tenor instance's container element while it's fetching results
	 */
	Elk_Tenor.prototype.showSpinnerOverlay = function () {
		let parentDiv = document.getElementById('tenor_results');
		if (parentDiv)
		{
			parentDiv.innerHTML += '<div id="tenor-overlay"><div class="overlay-spinner"><i class="icon icon-lg i-concentric"></i></div></div>';
		}
	};

	/**
	 * Hides the spinner overlay.
	 */
	Elk_Tenor.prototype.hideSpinnerOverlay = function () {
		let element = document.getElementById('tenor-overlay');
		if (element)
		{
			element.parentNode.removeChild(element);
		}
	};

	/**
	 * Inserts a GIF image into the editor, txt or wizzy, specified by the given event and callback.
	 */
	Elk_Tenor.prototype.insert = function (event) {
		let insert = event.target.dataset.insert;

		this.callback(insert);

		editor.closeDropDown(true);
		this.reset();
	};

	/**
	 * The options object for Elk_Tenor.
	 */
	Elk_Tenor.prototype.opts = {};

	/**
	 * Tenor plugin interface to SCEditor
	 *
	 *  - Called from the editor as a plugin
	 *  - Monitors events, so we control the GIF's
	 */
	sceditor.plugins.tenor = function () {
		let base = this,
			oTenor;

		/**
		 * Called before signalReady as part of the editor startup process
		 */
		base.init = function () {
			// Grab this instance for use in Tenor
			editor = this;

			oTenor = new Elk_Tenor({});
			oTenor.editor = editor;

			// Add the command, will also show our toolbar button
			if (!editor.commands.tenor)
			{
				editor.commands.tenor = {
					txtExec: base.tenorTxt,
					exec: base.tenorWizzy,
					tooltip: "Insert Tenor"
				};
			}
		};

		/**
		 * Set up and display the Tenor dropdown.
		 */
		base.tenorDropDown = function (caller, callback) {
			oTenor.reset();
			oTenor.getTrending();
			oTenor.callback = callback;

			// button clicked, Css (sceditor-tenor), HTML element(s) to use
			editor.createDropDown(caller, 'tenor', oTenor.opts.dropDown);

			document.querySelector('#tenor_search').focus();
		};

		/**
		 * Callback to insert a chosen GIF from the Tenor dropdown when in wizzy mode
		 */
		base.tenorWizzy = function (caller) {
			base.tenorDropDown(caller, function (gif) {
				editor.insert('[img]' + gif + '[/img]');
			});
		};

		/**
		 * Callback to insert a chosen GIF from the Tenor dropdown when in txt/bbc mode
		 */
		base.tenorTxt = function (caller) {
			base.tenorDropDown(caller, function (gif) {
				editor.insertText('[img]' + gif + '[/img]');
			});
		};
	};
})(sceditor);
