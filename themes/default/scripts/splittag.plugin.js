/**
 * This file contains javascript associated with the splittag function as it
 * relates to an sceditor sourcemode instance
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Original code author: Aziz
 * License: http://opensource.org/licenses/Zlib
 *
 * @version 1.0
 *
 */

(function($, window, document) {
	/**
	 * Splittag plugin interface to SCEditor
	 *  - Called from the editor as a plugin
	 *  - Monitors events so we control the elk_draft autosaver (on/off/change)
	 */
	$.sceditor.plugins.splittag = function() {
		var base = this,
			oSplitTags;

		/**
		 * Initialize, called when sceditor starts and initializes plugins
		 */
		base.signalReady = function() {
			// Start an instance of our splittag function
			oSplitTags = new elk_SplitTags();
		};

		/**
		 * Monitor for a ctrl+enter keypress, this is our keystroke cue to split the tag(s)
		 */
		base.signalKeypressEvent = function(e) {
			if ((e.keyCode === 10 || e.keyCode === 13) && e.ctrlKey)
				oSplitTags.split();
		};
	};

	/**
	 * Class used to split contents and tags at the cursor position
	 */
	function elk_SplitTags()
	{
		// Array to hold our bbc tags
		this.tagStack = [];

		// Regex to find bbc tags and attr's
		// [1] open tag name, [2] open tag attribs, [3] closing tag name
		this.regex = /\[(?:([a-z]+)([^\]]*)|(\/[a-z]+))\]/gi;
	}

	/**
	 * "Splits" the text at the caret position by closing any open bbc tags in front of
	 * the caret position and then re-opening them past the caret position
	 * e.g. | represents the caret
	 * [quote]ElkArte is |cool[/quote] => [quote]ElkArte is [/quote]|[quote]cool[/quote]
	 */
	elk_SplitTags.prototype.split = function () {
		// Right now this is a non wizzy function
		if (base.inSourceMode() === false)
			return;
		// Splits tags starting at the current cursor position.
		else
		{
			var i = 0,
				tagTextStart = "",
				tagTextEnd = "",
				contents = base.val(),
				editor = $(".sceditor-container").find("textarea")[0],
				startPos = Math.min(editor.selectionEnd, contents.length),
				endPos = Math.min(editor.selectionStart, contents.length);

			// Determine what bbc tag(s) we may be inside of
			this.parseTags(contents, endPos);

			// Inside tag(s), we need to insert close/open tags to split at this point
			if (this.tagStack.length !== 0)
			{
				// Traverse in reverse and build closing tags.
				for (i = this.tagStack.length - 1; i >= 0; i--)
					tagTextStart += '[/' + this.tagStack[i].name + ']';

				// Traverse forward and build opening tags (with attr's)
				for (i = 0; i < this.tagStack.length; i++)
				{
					if (i === 0)
						tagTextEnd += "\n";
					tagTextEnd += '[' + this.tagStack[i].name + this.tagStack[i].attributes + "]";
				}

				// Did someone select text that they expect to be wrapped in tags as well?
				if (startPos !== endPos)
					base.insertText(tagTextStart + tagTextEnd, tagTextStart + tagTextEnd);
				// Insert the new close/open tags at the cursor position
				else
					base.insertText(tagTextStart, tagTextEnd);
			}
		}
	};

	/**
	 * Search a string for open bbc tags (ahead of the endPos in the string) and returns them
	 *
	 * It does no checking to verify that a tag has a matching closing tag in the stack, its a simple
	 * queue stack.  It also does not check if the tag is valid child of any tag before it. etc.
	 *
	 * [quote]this[b]is[i]a te|st[/i][/b][/quote] (| = caret pos) Returns: [i][b][quote]
	 *		Improper syntax will result in wrong results
	 * [quote]this[b]is[i]a [/b]te|st[/i][/quote] (| = caret pos) Returns: [b][quote]
	 *
	 * @param {string} text Text inside the current editor window
	 * @param {int} endPos caret position in the text
	 */
	elk_SplitTags.prototype.parseTags = function(text, endPos) {
		// Start off empty
		this.tagStack = [];

		// All the text before the cursor position
		text = text.slice(0, endPos);

		// Run our BBC regex on the leading text
		while (matches = this.regex.exec(text))
		{
			// Closing tag [/bbcName] found, remove one from the stack
			// We could attempt to find a matching open in the stack as well ...
			if (matches[3])
				this.tagStack.pop();
			// Opening tag [bbcName], add it to the stack
			else
				this.tagStack.push({"name": matches[1], "attributes": matches[2]});
		}

		// Return what's left in the stack, these are the "open" tags
		return this.tagStack;
	};

})(jQuery, window, document);