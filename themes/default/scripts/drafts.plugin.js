/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 */

/**
 * This file contains javascript associated with the drafts auto function as it
 * relates to an sceditor invocation
 */

(function (sceditor) {
	'use strict';

	// Editor instance
	var editor;

	function Elk_Drafts(options)
	{
		// All the passed options and defaults are loaded to the opts object
		this.opts = $.extend({}, this.defaults, options);

		// Attach mousedown events to the forms submit buttons
		this.formCheck();
	}

	/**
	 * Make the call to save this draft in the background
	 *
	 * - returns if nothing has changed since the last save or there is nothing to save
	 * - updates the display to show we are saving
	 * - loads the form data and makes the ajax request
	 */
	Elk_Drafts.prototype.draftSave = function ()
	{
		// No change since the last save, or form submitted
		if (!this.opts._bCheckDraft || elk_formSubmitted || (typeof disableDrafts !== 'undefined' && disableDrafts))
		{
			return false;
		}

		// Still saving the last one or other?
		if (this.opts._bInDraftMode === true)
		{
			this.draftCancel();
		}

		// Get the editor text, either from sceditor or from the quicktext textarea
		var sPostdata = editor.val();

		// Nothing to save?
		if (isEmptyText(sPostdata))
		{
			return false;
		}

		// Flag that we are saving a draft
		document.getElementById('throbber').style.display = 'inline';
		this.opts._bInDraftMode = true;

		// Create a clone form to populate
		var $aForm = $('#postmodify').clone();

		$aForm.find("textarea[name='message']").val(sPostdata.replace(/&#/g, "&#38;#"));
		$aForm.append($('<input />').attr('name', 'save_draft').val(true));
		$aForm.append($('<input />').attr('name', 'autosave').val(true));

		// Keep track of source or wysiwyg when using the full editor
		$aForm.append($('<input />').attr('name', 'message_mode').val(editor.inSourceMode() ? '1' : '0'));

		// Send in the request to save the data
		this.draftAjax($aForm.serialize(), "?action=post2;board=" + this.opts.iBoard + ";api=xml");
	};

	/**
	 * Make the call to save this PM draft in the background
	 *
	 * - returns if nothing has changed since the last save or there is nothing to save
	 * - updates the display to show we are saving
	 * - loads the form data and makes the ajax request
	 */
	Elk_Drafts.prototype.draftPMSave = function ()
	{
		// No change since the last PM save, or elk is doing its thing
		if (!this.opts._bCheckDraft || elk_formSubmitted)
		{
			return false;
		}

		// Still saving the last one or some other?
		if (this.opts._bInDraftMode === true)
		{
			this.draftCancel();
		}

		// Get the editor data
		var sPostdata = editor.val();

		// Nothing to save
		if (isEmptyText(sPostdata))
		{
			return false;
		}

		// Flag that we are saving the PM
		document.getElementById('throbber').style.display = 'inline';
		this.opts._bInDraftMode = true;

		// Get the to and bcc values
		var aTo = this.draftGetRecipient('recipient_to[]'),
			aBcc = this.draftGetRecipient('recipient_bcc[]');

		// Clone the form, we will use this to send
		var $aForm = $('#pmFolder').clone();

		$aForm.find("textarea[name='message']").val(sPostdata.replace(/&#/g, "&#38;#"));
		$aForm.find("input[name='subject']").val($aForm.find("input[name='subject']").val().replace(/&#/g, "&#38;#"));
		$aForm.find("input[name='replied_to']").val(parseInt($aForm.find("input[name='replied_to']").val()));
		if ($aForm.find("input[name='id_pm_draft']").length == 1)
		{
			$aForm.find("input[name='id_pm_draft']").val(parseInt($aForm.find("input[name='id_pm_draft']").val()));
		}
		else
		{
			$aForm.append($('<input />').attr('name', 'id_pm_draft').val(0));
		}
		$aForm.append($('<input />').attr('name', 'recipient_to').val(aTo));
		$aForm.append($('<input />').attr('name', 'recipient_bcc').val(aBcc));
		$aForm.append($('<input />').attr('name', 'save_draft').val(true));
		$aForm.append($('<input />').attr('name', 'autosave').val(true));

		// Keep track of source or wysiwyg when using the full editor
		$aForm.append($('<input />').attr('name', 'message_mode').val(editor.inSourceMode() ? '1' : '0'));

		// Send in (post) the document for saving
		this.draftAjax($aForm.serialize(), "?action=pm;sa=send2;api=xml");
	};

	/**
	 * Make and process the ajax response
	 *
	 * - updates the display to show last saved on
	 * - closes the draft last saved info box from a "manual" save draft click
	 * - hides the ajax saving icon
	 * - turns off _bInDraftMode so another save request can fire
	 *
	 * @type {xmlCallback}
	 * @param {string[]} post
	 * @param {string} action
	 */
	Elk_Drafts.prototype.draftAjax = function (post, action)
	{
		// Send in the request to save the data
		$.ajax({
			type: "POST",
			dataType: 'xml',
			url: elk_scripturl + action,
			data: post,
			context: this
		})
			.done(function (data, textStatus, jqXHR)
			{
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
					let draft_section = document.getElementById('draft_section');
					if (draft_section)
					{
						draft_section.style.display = 'none';
					}
				}
			})
			.always(function ()
			{
				// No matter what, we clear the saving indicator
				this.opts._bInDraftMode = false;
				document.getElementById('throbber').style.display = 'none';

				// Set the flag off so we don't save again (until a keypress indicates they changed the text)
				this.opts._bCheckDraft = false;
			});
	};

	/**
	 * Function to retrieve the to and bcc values from the pseudo arrays
	 *
	 *  - Accounts for either a single or multiple to/bcc recipients
	 *
	 * @param {string} sField name of the form elements we are getting
	 */
	Elk_Drafts.prototype.draftGetRecipient = function (sField)
	{
		var oRecipient = document.forms.pmFolder.elements[sField],
			aRecipient = [];

		if (typeof (oRecipient) !== 'undefined')
		{
			// Just one recipient
			if ('value' in oRecipient)
			{
				aRecipient.push(parseInt(oRecipient.value));
			}
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
	Elk_Drafts.prototype.draftCancel = function ()
	{
		this.opts._bInDraftMode = false;
		document.getElementById('throbber').style.display = 'none';
	};

	/**
	 * Starts the autosave timer for the current instance of the elk_draft object
	 */
	Elk_Drafts.prototype.startSaver = function ()
	{
		var oInstance = this;
		if (this.opts.bPM)
		{
			this.opts._interval_id = setInterval(function ()
			{
				oInstance.draftPMSave();
			}, this.opts.iFreq);
		}
		else
		{
			this.opts._interval_id = setInterval(function ()
			{
				oInstance.draftSave();
			}, this.opts.iFreq);
		}
	};

	/**
	 * Signals that one of the post/pm/qr form buttons was pressed
	 *
	 * - Used to prevent saving an auto draft on input button (post, save, etc)
	 */
	Elk_Drafts.prototype.formCheck = function ()
	{
		var oInstance = this,
			formID = $('#' + this.opts.sTextareaID).closest("form").attr('id');

		// Prevent autosave on post/save selection by mouse or keyboard
		var $_form_submitt =  $('#' + formID + ' [name="post"]');
		$_form_submitt.on('mousedown', oInstance, function() {
			oInstance.opts._bInDraftMode = true;
		});

		$_form_submitt.on('keydown', oInstance, function ()
		{
			oInstance.opts._bInDraftMode = true;
		});
	};

	/**
	 * Private draft vars to keep track of what/where/why
	 */
	Elk_Drafts.prototype.defaults = {
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
		 * @type {Number}
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
	Elk_Drafts.prototype.opts = {};

	/**
	 * Draft plugin interface to SCEditor
	 *
	 *  - Called from the editor as a plugin
	 *  - Monitors events so we control the elk_draft autosaver (on/off/change)
	 */
	sceditor.plugins.draft = function ()
	{
		var base = this,
			oDrafts;

		/**
		 * Initialize, called when sceditor starts and initializes plugins
		 */
		base.init = function ()
		{
			// Grab this instance for use use in oDrafts
			editor = this;

			// Init the draft instance, load in the options
			oDrafts = new Elk_Drafts(this.opts.draftOptions);
			oDrafts.base = base;

			// Start the autosave timer
			if (oDrafts.opts.iFreq > 0)
			{
				oDrafts.startSaver();
			}
		};

		/**
		 * Used to ensure the autosave timer is running when the editor window
		 * (wiz or source) re-gains focus
		 */
		base.signalFocusEvent = function ()
		{
			if (oDrafts.opts._interval_id === "")
			{
				oDrafts.startSaver();
			}
		};

		/**
		 * Moved away from the editor, where did you go?
		 * - Turns off the autosave timer when the user moves the focus away
		 * - Saves the current content before turning off the autosaver
		 */
		base.signalBlurEvent = function ()
		{
			// If we are not already in a save action, save the draft
			if (oDrafts.opts._bInDraftMode === true)
			{
				oDrafts.draftCancel();
			}
			else
			{
				// Lets save the draft then
				if (oDrafts.opts.bPM)
				{
					oDrafts.draftPMSave();
				}
				else
				{
					oDrafts.draftSave();
				}

				// Turn it off the autosave if we can, mark it as such
				if (oDrafts.opts._interval_id !== "")
				{
					clearInterval(oDrafts.opts._interval_id);
				}

				oDrafts.opts._interval_id = "";
			}
		};

		/**
		 * Informs the autosave function that some activity has occurred in the
		 * editor window since the last save ... activity being triggered whenever
		 * the editor loses focus, something is pasted/inserted and when the user
		 * stops typing for 1.5s or press space/return
		 */
		base.signalValuechangedEvent = function (oEvent)
		{
			// Prevent autosave when using the tab key to navigate to the submit buttons
			if (oEvent.keyCode === 9)
			{
				oDrafts.opts._bInDraftMode = true;
			}

			oDrafts.opts._bCheckDraft = true;
		};
	};
})(sceditor);
