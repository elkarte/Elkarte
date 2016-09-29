/*!
 * This file contains javascript associated with the splittag function as it
 * relates to an sceditor instance
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
 * @version 1.1 beta 3
 *
 */

(function ($) {
	'use strict';

	/**
	 * Splittag plugin interface to SCEditor
	 *  - Called from the editor as a plugin
	 */
	$.sceditor.plugins.splittag = function () {
		var base = this,
			editor,
			tagStack = [],
			// Regex to find bbc tags and attr's
			// [1] open tag name, [2] open tag attribs, [3] closing tag name
			regex = /\[(?:([a-z]+)([^\]]*)|(\/[a-z]+))]/gi;

		/**
		 * Called when the plugin is registered to the editor
		 */
		base.init = function () {
			// The this variable will be set to the instance of the editor calling it
			editor = this;

			// Add handler for a ctrl+enter key press, this is our keystroke cue to split the tag(s)
			editor.addShortcut('ctrl+enter', base.split);

			// Add the command for editor button use
			if (!editor.commands.splittag) {
				editor.commands.splittag = {
					txtExec: base.split,
					exec: base.split_wizzy,
					tooltip: "Split Tag"
				};
			}
		};

		/**
		 * "Splits" the text at the caret position
		 *
		 * - Closes any open bbc tags in front of the caret position
		 * - re-opens them past the caret position,
		 * - Example | represents the caret
		 * [quote]ElkArte is |cool[/quote] => [quote]ElkArte is [/quote]|[quote]cool[/quote]
		 */
		base.split = function () {
			// Splits tags starting at the current cursor position.
			var i = 0,
				tagTextStart = "",
				tagTextEnd = "",
				contents = editor.val(null, false),
				caret = editor.sourceEditorCaret();

			// Determine what bbc tag(s) we may be inside of
			tagStack = parseTags(contents, caret.end);

			// Inside tag(s), we need to insert close/open tags to split at this point
			if (tagStack.length !== 0) {
				// Traverse in reverse and build closing tags.
				for (i = tagStack.length - 1; i >= 0; i--)
					tagTextStart += '[/' + tagStack[i].name + ']';

				// Traverse forward and build opening tags (with attr's)
				for (i = 0; i < tagStack.length; i++) {
					if (i === 0)
						tagTextEnd += "\n";
					tagTextEnd += '[' + tagStack[i].name + tagStack[i].attributes + "]";
				}

				// Did someone select text that they expect to be wrapped in tags as well?
				if (caret.start !== caret.end)
					editor.insertText(tagTextStart + tagTextEnd, tagTextStart + tagTextEnd);
				// Insert the new close/open tags at the cursor position
				else
					editor.insertText(tagTextStart, tagTextEnd);
			}
		};

		/**
		 * Search a string for open bbc tags (ahead of the iPos in the string) and returns them
		 *
		 * It does no checking to verify that a tag has a matching closing tag in the stack, its a simple
		 * queue stack.  It also does not check if the tag is valid child of any tag before it. etc.
		 *
		 * [quote]this[b]is[i]a te|st[/i][/b][/quote] (| = caret pos) Returns: [i][b][quote]
		 *        Improper syntax will result in wrong results
		 * [quote]this[b]is[i]a [/b]te|st[/i][/quote] (| = caret pos) Returns: [b][quote]
		 *
		 * @param {string} text Text inside the current editor window
		 * @param {int} iPos caret position in the text
		 */
		var parseTags = function (text, iPos) {
			// Start off empty
			tagStack = [];

			// All the text before the cursor position
			text = text.slice(0, iPos);

			// Run our BBC regex on the leading text
			var matches;
			while (matches = regex.exec(text)) {
				// Closing tag [/bbcName] found, remove one from the stack
				// We could attempt to find a matching open in the stack as well ...
				if (matches[3])
					tagStack.pop();
				// Opening tag [bbcName], add it to the stack
				else
					tagStack.push({"name": matches[1], "attributes": matches[2]});
			}

			// Return what's left in the stack, these are the "open" tags
			return tagStack;
		};

		/**
		 * Initial attempt at quote splitting in wysiwyg mode
		 *
		 * - Finds first block quote tag before the cursor
		 * - Extracts the contents from the caret to the end of the above block quote
		 * - Collapses the remaining quote range
		 * - Inserts extracted content as sibling
		 * - Copy attributes to the new sibling
		 * - Positions cursor between the two quotes for text entry
		 * - Does not currently build the cite tag for display, but does copy the
		 * attributes so toggle and post work as expected.
		 */
		base.split_wizzy = function() {
			// sceditor's RangeHelper
			var rangeHelper = editor.getRangeHelper(),
				contentAfterRangeStart,
				quote,
				range,
				blank,
				attributes;

			// Save the current state in case this goes bad
			rangeHelper.saveRange();

			// Clones will rule
			range = rangeHelper.cloneSelected();

			// Find the containing quote by walking up the DOM
			quote = range.commonAncestorContainer;
			while (quote && (quote.nodeType !== 1 || quote.tagName.toUpperCase() !== "BLOCKQUOTE")) {
				quote = quote.parentNode;
			}

			// Did we find it, did we?
			if (quote) {
				// Copy all of the quotes attributes, like author, date, etc
				attributes = $(quote).prop("attributes");

				// Place the end of the range after the blockquote, start is the cursor location.
				range.setEndAfter(quote);

				// Extract the contents of the above range, it goes in the split.
				contentAfterRangeStart = range.extractContents();

				// Apply the existing quote attributes to the new quote
				$.each(attributes, function() {
					$(contentAfterRangeStart).attr(this.name, this.value);
				});

				// Collapse the block quote range, we want to insert after this.
				range.collapse(quote);

				// If we need to split/insert inside of a quote
				// range.selectNodeContents(quote);
				// range.collapse(false);

				// Create an area to place between the two quotes for text entry
				blank = quote.ownerDocument.createElement('p');
				$(blank).html('&nbsp');

				// Insert the new elements
				range.insertNode(contentAfterRangeStart);
				range.insertNode(blank);

				// Move the caret to the split text entry point
				var range_new = document.createRange();
				range_new.setStartBefore(blank);
				rangeHelper.selectRange(range_new);
				range_new.collapse(false);
				editor.focus();
			}
			else
				rangeHelper.restoreRange();
		};
	};
})(jQuery);