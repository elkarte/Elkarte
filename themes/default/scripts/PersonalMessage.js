/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 */

/**
 * This file contains javascript surrounding personal messages send form.
 */

/**
 * Personal message Send controller
 *
 * sSelf: instance name
 * sSessionId:
 * sSessionVar:
 * sTextDeleteItem: text string to show when
 * sToControlId: id of the to auto suggest input
 * aToRecipients: array of members its going to
 * aBccRecipients: array of member to BCC
 * sBccControlId: id of the bcc auto suggest input
 * sBccDivId: container holding the bbc input
 * sBccDivId2: container holding the bcc chosen name
 * sBccLinkId: link to show/hide the bcc names
 * sBccLinkContainerId: container for the above
 * bBccShowByDefault: boolean to show it on or off
 * sShowBccLinkTemplate:
 *
 * @param {type} oOptions
 */
function elk_PersonalMessageSend (oOptions)
{
	this.opt = oOptions;
	this.oBccDiv = null;
	this.oBccDiv2 = null;
	this.oToAutoSuggest = null;
	this.oBccAutoSuggest = null;
	this.init();
}

// Initialise the PM recipient selection area
elk_PersonalMessageSend.prototype.init = function() {
	if (!this.opt.bBccShowByDefault)
	{
		// Hide the BCC control.
		this.oBccDiv = document.getElementById(this.opt.sBccDivId);
		this.oBccDiv.style.display = 'none';
		this.oBccDiv2 = document.getElementById(this.opt.sBccDivId2);
		this.oBccDiv2.style.display = 'none';

		// Show the link to bet the BCC control back.
		let oBccLinkContainer = document.getElementById(this.opt.sBccLinkContainerId);

		oBccLinkContainer.style.display = 'inline';
		oBccLinkContainer.innerHTML = this.opt.sShowBccLinkTemplate;

		// Make the link show the BCC control.
		let oBccLink = document.getElementById(this.opt.sBccLinkId);
		oBccLink.onclick = function() {
			this.showBcc();
			return false;
		}.bind(this);
	}

	this.oToAutoSuggest = new elk_AutoSuggest({
		sSessionId: this.opt.sSessionId,
		sSessionVar: this.opt.sSessionVar,
		sSuggestId: 'to_suggest',
		sControlId: this.opt.sToControlId,
		sSearchType: 'member',
		sPostName: 'recipient_to',
		sURLMask: 'action=profile;u=%item_id%',
		sTextDeleteItem: this.opt.sTextDeleteItem,
		bItemList: true,
		sItemListContainerId: 'to_item_list_container',
		aListItems: this.opt.aToRecipients
	});
	this.oToAutoSuggest.registerCallback('onBeforeAddItem', this.callbackAddItem.bind(this));

	this.oBccAutoSuggest = new elk_AutoSuggest({
		sSessionId: this.opt.sSessionId,
		sSessionVar: this.opt.sSessionVar,
		sSuggestId: 'bcc_suggest',
		sControlId: this.opt.sBccControlId,
		sSearchType: 'member',
		sPostName: 'recipient_bcc',
		sURLMask: 'action=profile;u=%item_id%',
		sTextDeleteItem: this.opt.sTextDeleteItem,
		bItemList: true,
		sItemListContainerId: 'bcc_item_list_container',
		aListItems: this.opt.aBccRecipients
	});
	this.oBccAutoSuggest.registerCallback('onBeforeAddItem', this.callbackAddItem.bind(this));
};

// Show the bbc fields
elk_PersonalMessageSend.prototype.showBcc = function() {
	// No longer hide it, show it to the world!
	this.oBccDiv.style.display = 'block';
	this.oBccDiv2.style.display = 'block';
};

// Prevent items to be added twice or to both the 'To' and 'Bcc'.
elk_PersonalMessageSend.prototype.callbackAddItem = function(sSuggestId) {
	this.oToAutoSuggest.deleteAddedItem(sSuggestId);
	this.oBccAutoSuggest.deleteAddedItem(sSuggestId);

	return true;
};

/**
 * Populate the label selection pulldown after a message is selected
 */
function loadLabelChoices ()
{
	var listing = document.forms.pmFolder.elements,
		theSelect = document.forms.pmFolder.pm_action,
		toAdd = {length: 0},
		toRemove = {length: 0};

	if (theSelect.childNodes.length === 0)
	{
		return;
	}

	// This is done this way for internationalization reasons.
	if (!('-1' in allLabels))
	{
		for (let o = 0; o < theSelect.options.length; o++)
		{
			if (theSelect.options[o].value.substring(0, 4) === 'rem_')
			{
				allLabels[theSelect.options[o].value.substring(4)] = theSelect.options[o].text;
			}
		}
	}

	for (let i = 0; i < listing.length; i++)
	{
		if (listing[i].name !== 'pms[]' || !listing[i].checked)
		{
			continue;
		}

		var alreadyThere = [],
			x;

		for (x in currentLabels[listing[i].value])
		{
			if (!(x in toRemove))
			{
				toRemove[x] = allLabels[x];
				toRemove.length++;
			}
			alreadyThere[x] = allLabels[x];
		}

		for (x in allLabels)
		{
			if (!(x in alreadyThere))
			{
				toAdd[x] = allLabels[x];
				toAdd.length++;
			}
		}
	}

	while (theSelect.options.length > 2)
	{
		theSelect.options[2] = null;
	}

	if (toAdd.length !== 0)
	{
		theSelect.options[theSelect.options.length] = new Option(txt_pm_msg_label_apply);
		theSelect.options[theSelect.options.length - 1].innerHTML = txt_pm_msg_label_apply;
		theSelect.options[theSelect.options.length - 1].className = 'jump_to_header';
		theSelect.options[theSelect.options.length - 1].disabled = true;

		for (let i in toAdd)
		{
			if (i !== 'length')
			{
				theSelect.options[theSelect.options.length] = new Option(toAdd[i], 'add_' + i);
			}
		}
	}

	if (toRemove.length !== 0)
	{
		theSelect.options[theSelect.options.length] = new Option(txt_pm_msg_label_remove);
		theSelect.options[theSelect.options.length - 1].innerHTML = txt_pm_msg_label_remove;
		theSelect.options[theSelect.options.length - 1].className = 'jump_to_header';
		theSelect.options[theSelect.options.length - 1].disabled = true;

		for (let i in toRemove)
		{
			if (i !== 'length')
			{
				theSelect.options[theSelect.options.length] = new Option(toRemove[i], 'rem_' + i);
			}
		}
	}
}

/**
 * Rebuild the rule description!
 * @todo: string concatenation is bad for internationalization
 */
function rebuildRuleDesc ()
{
	// Start with nothing.
	var text = '',
		joinText = '',
		actionText = '',
		hadBuddy = false,
		foundCriteria = false,
		foundAction = false,
		curNum,
		curVal,
		curDef;

	// GLOBAL strings, convert to objects
	/** global: groups */
	if (typeof groups === 'string')
	{
		groups = JSON.parse(groups);
	}
	/** global: labels */
	if (typeof labels === 'string')
	{
		labels = JSON.parse(labels);
	}
	/** global: rules */
	if (typeof rules === 'string')
	{
		rules = JSON.parse(rules);
	}

	for (var i = 0; i < document.forms.addrule.elements.length; i++)
	{
		if (document.forms.addrule.elements[i].id.substr(0, 8) === 'ruletype')
		{
			if (foundCriteria)
			{
				joinText = document.getElementById('logic').value === 'and' ? ' ' + txt_pm_readable_and + ' ' : ' ' + txt_pm_readable_or + ' ';
			}
			else
			{
				joinText = '';
			}

			foundCriteria = true;

			curNum = document.forms.addrule.elements[i].id.match(/\d+/);
			curVal = document.forms.addrule.elements[i].value;

			if (curVal === 'gid')
			{
				curDef = document.getElementById('ruledefgroup' + curNum).value.php_htmlspecialchars();
			}
			else if (curVal !== 'bud')
			{
				curDef = document.getElementById('ruledef' + curNum).value.php_htmlspecialchars();
			}
			else
			{
				curDef = '';
			}

			// What type of test is this?
			if (curVal === 'mid' && curDef)
			{
				text += joinText + txt_pm_readable_member.replace('{MEMBER}', curDef);
			}
			else if (curVal === 'gid' && curDef && groups[curDef])
			{
				text += joinText + txt_pm_readable_group.replace('{GROUP}', groups[curDef]);
			}
			else if (curVal === 'sub' && curDef)
			{
				text += joinText + txt_pm_readable_subject.replace('{SUBJECT}', curDef);
			}
			else if (curVal === 'msg' && curDef)
			{
				text += joinText + txt_pm_readable_body.replace('{BODY}', curDef);
			}
			else if (curVal === 'bud' && !hadBuddy)
			{
				text += joinText + txt_pm_readable_buddy;
				hadBuddy = true;
			}
		}

		if (document.forms.addrule.elements[i].id.substr(0, 7) === 'acttype')
		{
			if (foundAction)
			{
				joinText = ' ' + txt_pm_readable_and + ' ';
			}
			else
			{
				joinText = '';
			}

			foundAction = true;

			curNum = document.forms.addrule.elements[i].id.match(/\d+/);
			curVal = document.forms.addrule.elements[i].value;

			if (curVal === 'lab')
			{
				curDef = document.getElementById('labdef' + curNum).value.php_htmlspecialchars();
			}
			else
			{
				curDef = '';
			}

			// Now pick the actions.
			if (curVal === 'lab' && curDef && labels[curDef])
			{
				actionText += joinText + txt_pm_readable_label.replace('{LABEL}', labels[curDef]);
			}
			else if (curVal === 'del')
			{
				actionText += joinText + txt_pm_readable_delete;
			}
		}
	}

	// If still nothing make it default!
	if (text === '' || !foundCriteria)
	{
		text = txt_pm_rule_not_defined;
	}
	else
	{
		if (actionText !== '')
		{
			text += ' ' + txt_pm_readable_then + ' ' + actionText;
		}
		text = txt_pm_readable_start + text + txt_pm_readable_end;
	}

	// Set the actual HTML!
	document.getElementById('ruletext').innerHTML = text;
}

/**
 * Initializes the update rules actions.
 *
 * @returns {void}
 */
function initUpdateRulesActions ()
{
	// Maintain the personal message rule options to comply with the rule choice
	let criteria = document.getElementById('criteria');
	criteria.addEventListener('change', function(event) {
		if (event.target.name.startsWith('ruletype'))
		{
			let optNum = event.target.getAttribute('data-optnum'),
				selectBox = document.getElementById('ruletype' + optNum);

			if (selectBox.value === 'gid')
			{
				document.getElementById('defdiv' + optNum).style.display = 'none';
				document.getElementById('defseldiv' + optNum).style.display = 'inline';
			}
			else if (selectBox.value === 'bud' || selectBox.value === '')
			{
				document.getElementById('defdiv' + optNum).style.display = 'none';
				document.getElementById('defseldiv' + optNum).style.display = 'none';
			}
			else
			{
				document.getElementById('defdiv' + optNum).style.display = 'inline';
				document.getElementById('defseldiv' + optNum).style.display = 'none';
			}
		}
	});

	/**
	 * Maintains the personal message rule action options to conform with the action choice
	 * so that the form only makes available the proper choice
	 */
	let actions = document.getElementById('actions');
	actions.addEventListener('change', function(e) {
		let targetEl = e.target,
			name = targetEl.getAttribute('name');

		if (name && name.startsWith('acttype'))
		{
			let optNum = targetEl.getAttribute('data-actnum');

			if (document.getElementById('acttype' + optNum).value === 'lab')
			{
				document.getElementById('labdiv' + optNum).style.display = 'inline';
			}
			else
			{
				document.getElementById('labdiv' + optNum).style.display = 'none';
			}
		}
	});

	// Trigger a change on the existing in order to let the function run
	Array.from(criteria.querySelectorAll('[name^="ruletype"]')).forEach(function(elem) {
		elem.dispatchEvent(new Event('change'));
	});
	Array.from(criteria.querySelectorAll('[name^="acttype"]')).forEach(function(elem) {
		elem.dispatchEvent(new Event('change'));
	});

	// Make sure the description is rebuilt every time something changes, even on elements not yet existing
	addMultipleListeners(criteria, 'change keyup', function(e) {
			let nameAttr = e.target.getAttribute('name');
			if (
				nameAttr.startsWith('ruletype') ||
				nameAttr.startsWith('ruledefgroup') ||
				nameAttr.startsWith('ruledef') ||
				nameAttr.startsWith('acttype') ||
				nameAttr.startsWith('labdef') ||
				e.target.id === 'logic'
			)
			{
				rebuildRuleDesc();
			}
		}
	);

	// Make sure the description is rebuilt every time something changes, even on elements not yet existing
	['criteria', 'actions'].forEach(function(id) {
		let el = document.getElementById(id);
		addMultipleListeners(el, 'change keyup', function(e) {
				let nameAttr = e.target.getAttribute('name');
				if (
					nameAttr.startsWith('ruletype') ||
					nameAttr.startsWith('ruledefgroup') ||
					nameAttr.startsWith('ruledef') ||
					nameAttr.startsWith('acttype') ||
					nameAttr.startsWith('labdef') ||
					e.target.id === 'logic'
				)
				{
					rebuildRuleDesc();
				}
			}
		);
	});

	// Rebuild once at the beginning to ensure everything is correct
	rebuildRuleDesc();
}

/**
 * Adds multiple event listeners to an element.
 *
 * @param {HTMLElement} element - The element to attach the event listeners to.
 * @param {string} events - A string containing one or more space-separated event types, e.g., "click mouseover".
 * @param {Function} handler - The function to be called when the event is triggered.
 *
 * @return {void}
 */
function addMultipleListeners (element, events, handler)
{
	events.split(' ').forEach(e => element.addEventListener(e, handler, false));
}

/**
 * Add a new rule criteria for PM filtering
 */
function addCriteriaOption ()
{
	console.log(criteriaNum);
	if (criteriaNum === 0)
	{
		for (let i = 0; i < document.forms.addrule.elements.length; i++)
		{
			if (document.forms.addrule.elements[i].id.substring(0, 8) === 'ruletype')
			{
				criteriaNum++;
			}
		}
	}
	criteriaNum++;

	// Global strings, convert to objects
	/** global: groups */
	if (typeof groups === 'string')
	{
		groups = JSON.parse(groups);
	}
	/** global: labels */
	if (typeof labels === 'string')
	{
		labels = JSON.parse(labels);
	}
	/** global: rules */
	if (typeof rules === 'string')
	{
		rules = JSON.parse(rules);
	}

	// rules select
	var rules_option = '',
		index = '';

	for (index in rules)
	{
		if (rules.hasOwnProperty(index))
		{
			rules_option += '<option value="' + index + '">' + rules[index] + '</option>';
		}
	}

	// group selections
	var group_option = '';

	for (index in groups)
	{
		group_option += '<option value="' + index + '">' + groups[index] + '</option>';
	}

	setOuterHTML(document.getElementById('criteriaAddHere'), '<br />' +
		'<select class="criteria" name="ruletype[' + criteriaNum + ']" id="ruletype' + criteriaNum + '" data-optnum="' + criteriaNum + '">' +
		'   <option value="">' + txt_pm_rule_criteria_pick + ':</option>' + rules_option + '' +
		'</select>&nbsp;' +
		'<span id="defdiv' + criteriaNum + '" class="hide">' +
		'<input type="text" name="ruledef[' + criteriaNum + ']" id="ruledef' + criteriaNum + '" value="" />' +
		'</span>' +
		'<span id="defseldiv' + criteriaNum + '" class="hide">' +
		'<select class="criteria" name="ruledefgroup[' + criteriaNum + ']" id="ruledefgroup' + criteriaNum + '">' +
		'   <option value="">' + txt_pm_rule_sel_group + '</option>' + group_option +
		'</select>' +
		'</span>' +
		'<span id="criteriaAddHere"></span>');

	return false;
}

/**
 * Add a new action for a defined PM rule
 */
function addActionOption ()
{
	if (actionNum === 0)
	{
		for (let i = 0; i < document.forms.addrule.elements.length; i++)
		{
			if (document.forms.addrule.elements[i].id.substr(0, 7) === 'acttype')
			{
				actionNum++;
			}
		}
	}
	actionNum++;

	// Label selections
	var label_option = '',
		index = '';

	if (typeof labels === 'string')
	{
		labels = JSON.parse(labels);
	}
	for (index in labels)
	{
		label_option += '<option value="' + index + '">' + labels[index] + '</option>';
	}

	setOuterHTML(document.getElementById('actionAddHere'), '<br />' +
		'<select name="acttype[' + actionNum + ']" id="acttype' + actionNum + '" data-actnum="' + actionNum + '">' +
		'   <option value="">' + txt_pm_rule_sel_action + ':</option>' +
		'   <option value="lab">' + txt_pm_rule_label + '</option>' +
		'   <option value="del">' + txt_pm_rule_delete + '</option>' +
		'</select>&nbsp;' +
		'<span id="labdiv' + actionNum + '" class="hide">' +
		'<select name="labdef[' + actionNum + ']" id="labdef' + actionNum + '">' +
		'   <option value="">' + txt_pm_rule_sel_label + '</option>' + label_option +
		'</select></span>' +
		'<span id="actionAddHere"></span>');
}
