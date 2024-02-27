/*!
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1.10
 */

// Editor startup options and adjustments, css, smiley box, validate wizzy, move into view
$.sceditor.plugins.initialLoad = function() {
	var base = this,
		editor,
		MAX_RETRIES = 300;

	var isEditorLoaded = function(selector, callback) {
		var retries = 0;
		function checkSelector() {
			if(document.querySelector(selector) !== null || retries >= MAX_RETRIES) {
				callback();
			} else {
				retries++;
				requestAnimationFrame(checkSelector);
			}
		}
		checkSelector();
	};

	base.editorLoaded = function()
	{
		editor.createPermanentDropDown();
		editor.css("code {white-space: pre;}");
	}

	base.signalReady = function() {
		editor = this;

		// signalReady can be called before the extensionMethods are available
		isEditorLoaded(".sceditor-toolbar", base.editorLoaded);

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
