/*!
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1.10
 */

// Editor startup options and adjustments, css, smiley box, validate wizzy, move into view
$.sceditor.plugins.initialLoad = function() {
	var base = this;
	const MAX_RETRIES = 120;
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
		isEditorLoaded(".sceditor-toolbar").then((selector) => {
			editor.createPermanentDropDown();
			editor.css("code {white-space: pre;}");
		});

		if ($.sceditor.isWysiwygSupported === false)
		{
			document.querySelectorAll(".sceditor-button-source").forEach((elem) => {elem.style.display = "none"});
		}

		// Move the editor into view
		if (document.getElementById("dropdown_menu_1") !== null || document.getElementById("preview_section") !== null)
		{
			document.getElementById("skipnav").scrollIntoView();
		}
	};
}
