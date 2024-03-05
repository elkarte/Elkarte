/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 */

(function (sceditor) {
	'use strict';

	// Core Editor startup options, css, smiley box, validate wizzy, move into view when needed
	sceditor.plugins.initialLoad = function() {
		let base = this;
		const MAX_RETRIES = 300;
		const isEditorLoaded = async selector => {
			let retries = 0;
			while (document.querySelector(selector) === null && retries < MAX_RETRIES)
			{
				await new Promise(resolve => requestAnimationFrame(resolve));
				retries++;
			}
			return true;
		};

		base.signalReady = function() {
			let editor = this;

			// signalReady can be called before the extensionMethods are available
			isEditorLoaded('.sceditor-toolbar').then((selector) => {
				editor.createPermanentDropDown();
				editor.css("code {white-space: pre;}");
			});

			if (sceditor.isWysiwygSupported === false)
			{
				document.querySelectorAll(".sceditor-button-source").forEach((elem) => {elem.style.display = "none";});
			}

			// Move the editor into view
			if (document.getElementById("dropdown_menu_1") !== null || document.getElementById("preview_section") !== null)
			{
				// Do not scroll this menu off-screen when present
				document.getElementById("skipnav").scrollIntoView();
			}
		};
	};
}(sceditor));
