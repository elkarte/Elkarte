/*!
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1
 */

/**
 * This file contains javascript associated with the drafts auto function as it
 * relates to a plain text box (no sceditor invocation)
 */

/**
 * The constructor for the plain text box auto-saver
 *
 * @param {object} oOptions
 */
function elk_DraftAutoSave(oOptions)
{
	this.opt = oOptions;
	this.bInDraftMode = false;
	this.sCurDraftId = '';
	this.oCurDraftDiv = null;
	this.interval_id = null;
	this.oDraftHandle = document.forms.postmodify["message"];
	this.sLastSaved = '';
	this.bCheckDraft = false;

	addLoadEvent(this.opt.sSelf + '.init();');
}

/**
 * Start our self calling routine
 */
elk_DraftAutoSave.prototype.init = function()
{
	if (this.opt.iFreq > 0)
	{
		// Start the autosaver timer
		this.interval_id = setInterval(this.opt.sSelf + '.draftSave();', this.opt.iFreq);

		// Set up the text area events
		this.oDraftHandle.instanceRef = this;
		this.oDraftHandle.onblur = function(oEvent) {
			return this.instanceRef.draftBlur();
		};
		this.oDraftHandle.onfocus = function(oEvent) {
			return this.instanceRef.draftFocus();
		};
		this.oDraftHandle.onkeydown = function(oEvent) {
			// Don't let tabbing to the buttons trigger autosave event
			if (oEvent.keyCode === 9)
				this.instanceRef.bInDraftMode = true;

			return this.instanceRef.draftKeypress();
		};

		// Prevent autosave when selecting post/save by mouse or keyboard
		var $_button = $('#postmodify').find('.button_submit');
		$_button .on('mousedown', this.oDraftHandle.instanceRef, function() {
			this.bInDraftMode = true;
		});
		$_button .on('onkeypress', this.oDraftHandle.instanceRef, function() {
			this.bInDraftMode = true;
		});
	}
};

/**
 * Moved away from the page, where did you go? ... till you return we pause autosaving
 *  - Handles the Blur event
 */
elk_DraftAutoSave.prototype.draftBlur = function()
{
	// Save if we are not already
	if (this.bInDraftMode !== true)
		this.draftSave();
	else
		this.draftCancel();

	if (this.interval_id !== "")
		window.clearInterval(this.interval_id);

	this.interval_id = "";
};

/**
 * Since your back we resume the autosave timer
 *  - Handles the focus event
 */
elk_DraftAutoSave.prototype.draftFocus = function()
{
	if (this.interval_id === "")
		this.interval_id = setInterval(this.opt.sSelf + '.draftSave();', this.opt.iFreq);
};

/**
 * Since your back we resume the autosave timer
 *  - Handles the keypress event
 */
elk_DraftAutoSave.prototype.draftKeypress = function()
{
	this.bCheckDraft = true;
};

/**
 * Makes the ajax call to save this draft in the background
 */
elk_DraftAutoSave.prototype.draftSave = function()
{
	// Form submitted or nothing changed since the last save
	if (elk_formSubmitted || !this.bCheckDraft)
		return false;

	// Still saving the last one or other?
	if (this.bInDraftMode)
		this.draftCancel();

	// Nothing to save?
	var sPostdata = document.forms.postmodify["message"].value;
	if (isEmptyText(sPostdata) || !('topic' in document.forms.postmodify.elements))
		return false;

	// Flag that we are saving a draft
	document.getElementById('throbber').style.display = 'inline';
	this.bInDraftMode = true;

	// Get the form elements that we want to save
	var aSections = [
		'topic=' + parseInt(document.forms.postmodify.elements['topic'].value),
		'id_draft=' + (('id_draft' in document.forms.postmodify.elements) ? parseInt(document.forms.postmodify.elements['id_draft'].value) : 0),
		'subject=' + document.forms.postmodify['subject'].value.replace(/&#/g, "&#38;#").php_urlencode(),
		'message=' + sPostdata.replace(/&#/g, "&#38;#").php_urlencode(),
		'icon=' + document.forms.postmodify['icon'].value.replace(/&#/g, "&#38;#").php_urlencode(),
		'save_draft=true',
		'autosave=true',
		elk_session_var + '=' + elk_session_id
	];

	// Send in document for saving and hope for the best
	sendXMLDocument.call(this, elk_prepareScriptUrl(elk_scripturl) + "action=post2;board=" + this.opt.iBoard + ";xml", aSections.join("&"), this.onDraftDone);

	// Save the latest for compare
	this.bCheckDraft = false;
};

/**
 * Callback function of the XMLhttp request for saving the draft message
 * @param {object} XMLDoc
 */
elk_DraftAutoSave.prototype.onDraftDone = function(XMLDoc)
{
	// If it is not valid then clean up
	if (!XMLDoc || !XMLDoc.getElementsByTagName('draft')[0])
		return this.draftCancel();

	// Grab the returned draft id and saved time from the response
	this.sCurDraftId = XMLDoc.getElementsByTagName('draft')[0].getAttribute('id');
	this.sLastSaved = XMLDoc.getElementsByTagName('draft')[0].childNodes[0].nodeValue;

	// Update the form to show we finished, if the id is not set, then set it
	document.getElementById(this.opt.sLastID).value = this.sCurDraftId;
	this.oCurDraftDiv = document.getElementById(this.opt.sLastNote);
	this.oCurDraftDiv.innerHTML = this.sLastSaved;

	// thank you sir, may I have another
	this.bInDraftMode = false;
	document.getElementById('throbber').style.display = 'none';
};

// If another auto save came in with one still pending
elk_DraftAutoSave.prototype.draftCancel = function()
{
	this.bInDraftMode = false;
	document.getElementById('throbber').style.display = 'none';
};