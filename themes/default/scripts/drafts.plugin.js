/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Release Candidate 2
 *
 * This file contains javascript associated with the drafts auto function as it
 * relates to an sceditor invocation
 */

(function($, window, document) {
	'use strict';

	function elk_Drafts(options) {
		// All the passed options and defaults are loaded to the opts object
		this.opts = $.extend({}, this.defaults, options);

		// Attach mousedown events to the forms submit buttons
		this.formCheck();
	}

	/**
	 * Make the call to save this draft in the background
	 * - returns if nothing has changed since the last save or there is nothing to save
	 * - updates the display to show we are saving
	 * - loads the form data and makes the ajax request
	 */
	elk_Drafts.prototype.draftSave = function() {
		// No change since the last save, or form submitted
		if (!this.opts._bCheckDraft || elk_formSubmitted || (typeof disableDrafts !== 'undefined' && disableDrafts))
			return false;

		// Still saving the last one or other?
		if (this.opts._bInDraftMode === true)
			this.draftCancel();

		// Get the editor text, either from sceditor or from the quicktext textarea
		var sPostdata = base.val();

		// Nothing to save?
		if (isEmptyText(sPostdata))
			return false;

		// Flag that we are saving a draft
		document.getElementById('throbber').style.display = '';
		this.opts._bInDraftMode = true;

		// Get all the form elements that we want to save
		var aSections = [
			'topic=' + parseInt(document.forms.postmodify.elements['topic'].value),
			'id_draft=' + (('id_draft' in document.forms.postmodify.elements) ? parseInt(document.forms.postmodify.elements['id_draft'].value) : 0),
			'subject=' + escape(document.forms.postmodify['subject'].value.replace(/&#/g, "&#38;#").php_to8bit()).replace(/\+/g, "%2B"),
			'message=' + escape(sPostdata.replace(/&#/g, "&#38;#").php_to8bit()).replace(/\+/g, "%2B"),
			'icon=' + escape(document.forms.postmodify['icon'].value.replace(/&#/g, "&#38;#").php_to8bit()).replace(/\+/g, "%2B"),
			'save_draft=true',
			elk_session_var + '=' + elk_session_id
		];

		// Get the locked an/or sticky values if they have been selected or set that is
		if (this.opts.sType && this.opts.sType === 'post')
		{
			var oLock = document.getElementById('check_lock'),
				oSticky = document.getElementById('check_sticky'),
				oSmile = document.getElementById('check_smileys');

			if (oLock && oLock.checked)
				aSections[aSections.length] = 'lock=1';

			if (oSticky && oSticky.checked)
				aSections[aSections.length] = 'sticky=1';

			if (oSmile && oSmile.checked)
				aSections[aSections.length] = 'ns=1';
		}

		// Keep track of source or wysiwyg when using the full editor
		aSections[aSections.length] = 'message_mode=' + (base.inSourceMode() ? '1' : '0');

		// Send in the request to save the data
		this.draftAjax(aSections, "?action=post2;board=" + this.opts.iBoard + ";xml");
	};

	/**
	 * Make the call to save this PM draft in the background
	 * - returns if nothing has changed since the last save or there is nothing to save
	 * - updates the display to show we are saving
	 * - loads the form data and makes the ajax request
	 */
	elk_Drafts.prototype.draftPMSave = function()
	{
		// No change since the last PM save, or elk is doing its thing
		if (!this.opts._bCheckDraft || elk_formSubmitted)
			return false;

		// Still saving the last one or some other?
		if (this.opts._bInDraftMode === true)
			this.draftCancel();

		// Nothing to save
		var sPostdata = base.val();
		if (isEmptyText(sPostdata))
			return false;

		// Flag that we are saving the PM
		document.getElementById('throbber').style.display = '';
		this.opts._bInDraftMode = true;

		// Get the to and bcc values
		var aTo = this.draftGetRecipient('recipient_to[]'),
			aBcc = this.draftGetRecipient('recipient_bcc[]');

		// Get the rest of the form elements that we want to save, and load them up
		var aSections = [
			'replied_to=' + parseInt(document.forms.pmFolder.elements['replied_to'].value),
			'id_pm_draft=' + (('id_pm_draft' in document.forms.pmFolder.elements) ? parseInt(document.forms.pmFolder.elements['id_pm_draft'].value) : 0),
			'subject=' + escape(document.forms.pmFolder['subject'].value.replace(/&#/g, "&#38;#").php_to8bit()).replace(/\+/g, "%2B"),
			'message=' + escape(sPostdata.replace(/&#/g, "&#38;#").php_to8bit()).replace(/\+/g, "%2B"),
			'recipient_to=' + aTo,
			'recipient_bcc=' + aBcc,
			'save_draft=true',
			elk_session_var + '=' + elk_session_id
		];

		// Account for wysiwyg
		if (this.opts.sType && this.opts.sType === 'post')
			aSections[aSections.length] = 'message_mode=' + (base.inSourceMode() ? '1' : '0');

		// Send in (post) the document for saving
		this.draftAjax(aSections, "?action=pm;sa=send2;xml");
	};

	/**
	 * Make and process the ajax response
	 *
	 * - updates the display to show last saved on
	 * - closes the draft last saved info box from a "manual" save draft click
	 * - hides the ajax saving icon
	 * - turns off _bInDraftMode so another save request can fire
	 *
	 * @param {xml object} XMLDoc
	 */
	elk_Drafts.prototype.draftAjax = function(post, action)
	{
		// Send in the request to save the data
		$.ajax({
			type: "POST",
			dataType: 'xml',
			url: elk_scripturl + action,
			data: post.join("&"),
			context: this
		})
		.done(function(data, textStatus, jqXHR) {
			// If it is not valid then move on
			if (textStatus === 'success' && $(data).find("draft").length !== 0)
			{
				// Grab the returned draft id and saved time from the response
				this.opts._sCurDraftId = $(data).find("draft").attr('id');
				this.opts._sLastSaved = $(data).find("draft").text();

				// Update the form to show we finished, if the id is not set, then set it
				document.getElementById(this.opts.sLastID).value = this.opts._sCurDraftId;
				document.getElementById(this.opts.sLastNote).innerHTML = this.opts._sLastSaved;

				// Hide the saved draft successbox in the event they pressed the save draft button at some point
				var draft_section = document.getElementById('draft_section');
				if (draft_section)
					draft_section.style.display = 'none';
			}
		})
		.always(function() {
			// No matter what, we clear the saving indicator
			this.opts._bInDraftMode = false;
			document.getElementById('throbber').style.display = 'none';

			// Set the flag off so we don't save again (until a keypress indicates they changed the text)
			this.opts._bCheckDraft = false;
		});
	};

	/**
	 * Function to retrieve the to and bcc values from the pseudo arrays
	 *  - Accounts for either a single or multiple to/bcc recipients
	 *
	 * @param {string} sField name of the form elements we are getting
	 */
	elk_Drafts.prototype.draftGetRecipient = function(sField)
	{
		var oRecipient = document.forms.pmFolder.elements[sField],
			aRecipient = [];

		if (typeof (oRecipient) !== 'undefined')
		{
			// Just one recipient
			if ('value' in oRecipient)
				aRecipient.push(parseInt(oRecipient.value));
			else
			{
				// Or many !
				for (var i = 0, n = oRecipient.length; i < n; i++)
					aRecipient.push(parseInt(oRecipient[i].value));
			}
		}

		return aRecipient;
	};

	/**
	 * If another auto save came in with one still pending we cancel out
	 */
	elk_Drafts.prototype.draftCancel = function() {
		this.opts._bInDraftMode = false;
		document.getElementById('throbber').style.display = 'none';
	};

	/**
	 * Starts the autosave timer for the current instance of the elk_draft object
	 */
	elk_Drafts.prototype.startSaver = function() {
		var oInstance = this;
		if (this.opts.bPM)
			this.opts._interval_id = setInterval(function() {
				oInstance.draftPMSave();
			}, this.opts.iFreq);
		else
			this.opts._interval_id = setInterval(function() {
				oInstance.draftSave();
			}, this.opts.iFreq);
	};

	/**
	 * Signals that one of the post/pm/qr form buttons was pressed
	 * Used to prevent saving an auto draft on input button (post, save, etc)
	 */
	elk_Drafts.prototype.formCheck = function() {
		var oInstance = this,
			formID = $('#id_draft').closest("form").attr('id');

		// Prevent autosave on post/save selection by mouse or keyboard
		$('#' + formID + ' .button_submit').on('mousedown', oInstance, function() {
			oInstance.opts._bInDraftMode = true;
		});
		$('#' + formID + ' .button_submit').on('keydown', oInstance, function() {
			oInstance.opts._bInDraftMode = true;
		});
	};

	/**
	 * Private draft vars to keep track of what/where/why
	 */
	elk_Drafts.prototype.defaults = {
		/**
		 * If we are currently in draft saving mode
		 * @type {Boolean}
		 */
		_bInDraftMode: false,

		/**
		 * The id of the draft so it save/appends
		 * @type {String}
		 */
		_sCurDraftId: null,

		/**
		 * How often we are going to save a draft in the background
		 * @type {Integer}
		 */
		_interval_id: null,

		/**
		 * The text to place in the last saved Div, comes from the xml response
		 * @type {String}
		 */
		_sLastSaved: null,

		/**
		 * If the user has pressed a key since the last save
		 * @type {Boolean}
		 */
		_bCheckDraft: false
	};

	/**
	 * Holds all  current draft options (defaults + passed options)
	 */
	elk_Drafts.prototype.opts = {};

	/**
	 * Draft plugin interface to SCEditor
	 *  - Called from the editor as a plugin
	 *  - Monitors events so we control the elk_draft autosaver (on/off/change)
	 */
	$.sceditor.plugins.draft = function() {
		var base = this,
			oDrafts;

		/**
		 * Initialize, called when sceditor starts and initializes plugins
		 */
		base.init = function() {
			// Init the draft instance, load in the options
			oDrafts = new elk_Drafts(this.opts.draftOptions);
			oDrafts.opts.bPM = oDrafts.opts.bPM ? true : false;

			// Start the autosave timer
			if (oDrafts.opts.iFreq > 0)
				oDrafts.startSaver();
		};

		/**
		 * Used to ensure the autosave timer is running when the editor window
		 * (wiz or source) re-gains focus
		 */
		base.signalFocusEvent = function() {
			if (oDrafts.opts._interval_id === "")
				oDrafts.startSaver();
		};

		/**
		 * Moved away from the editor, where did you go?
		 * - Turns off the autosave timer when the user moves the focus away
		 * - Saves the current content before turning off the autosaver
		 */
		base.signalBlurEvent = function() {
			// If we are not already in a save action, save the draft
			if (oDrafts.opts._bInDraftMode === true)
				oDrafts.draftCancel();
			else {
				// Lets save the draft then
				if (oDrafts.opts.bPM)
					oDrafts.draftPMSave();
				else
					oDrafts.draftSave();

				// Turn it off the autosave if we can, mark it as such
				if (oDrafts.opts._interval_id !== "")
					clearInterval(oDrafts.opts._interval_id);

				oDrafts.opts._interval_id = "";
			}
		};

		/**
		 * Informs the autosave function that some activity has occurred in the
		 * editor window since the last save ... activity being any keypress
		 * in the editor which we assume means they changed it
		 */
		base.signalKeydownEvent = function(oEvent) {
			// Prevent autosave when using the tab key to navigate to the submit buttons
			if (oEvent.keyCode === 9)
				oDrafts.opts._bInDraftMode = true;

			oDrafts.opts._bCheckDraft = true;
		};
	};

})(jQuery, window, document);