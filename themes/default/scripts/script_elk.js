/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 */

/**
 * This file contains javascript utility functions specific to ElkArte
 */

/**
 * We like the globals cuz they is good to us
 */

/** global: notification_topic_notice, notification_board_notice, txt_mark_as_read_confirm, oRttime */
/** global: $editor_data, elk_scripturl, elk_smiley_url, elk_session_var, elk_session_id, elk_images_url */
/** global: poll_add, poll_remove, poll_add, XMLHttpRequest, ElkInfoBar */

/**
 * Sets code blocks such that resize vertical works as expect.  Done this way to avoid
 * page jumps to named anchors missing the target.
 */
function elk_codefix()
{
	$('.bbc_code').each(function()
	{
		var $this = $(this);

		// If it has a scroll bar, allow the user to resize it vertically
		if ($this.get(0).scrollHeight > $this.innerHeight()) {
			$this.css('height', $this.height());
			$this.css('max-height', 'none');
		}
		else {
			$this.css('resize', 'none');
		}
	});
}

/**
 * Turn a regular url button in to an ajax request
 *
 * @param {string} btn string representing this, generally the anchor link tag <a class="" href="" onclick="">
 * @param {string} confirmation_msg_variable var name of the text sting to display in the "are you sure" box
 * @param {function} onSuccessCallback optional, a callback executed on successfully execute the AJAX call
 */
function toggleButtonAJAX(btn, confirmation_msg_variable, onSuccessCallback)
{
	$.ajax({
		type: 'GET',
		url: btn.href + ';xml;api',
		context: document.body,
		beforeSend: ajax_indicator(true)
	})
	.done(function(request) {
		if (request === '')
			return;

		var oElement = $(request).find('elk')[0];

		// No errors
		if (oElement.getElementsByTagName('error').length === 0)
		{
			var text = oElement.getElementsByTagName('text'),
				url = oElement.getElementsByTagName('url'),
				confirm_elem = oElement.getElementsByTagName('confirm');

			// Update the page so button/link/confirm/etc reflect the new on or off status
			if (confirm_elem.length === 1)
				var confirm_text = confirm_elem[0].firstChild.nodeValue.removeEntities();

			$('.' + btn.className.replace(/(list|link)level\d/g, '').trim()).each(function() {
				// @todo: the span should be moved somewhere in themes.js?
				if (text.length === 1)
					$(this).html('<span>' + text[0].firstChild.nodeValue.removeEntities() + '</span>');

				if (url.length === 1)
					$(this).attr('href', url[0].firstChild.nodeValue.removeEntities());

				// Replaces the confirmation var text with the new one from the response to allow swapping on/off
				// @todo this appears to be the start of a confirmation dialog... needs finished.
				if (typeof (confirm_text) !== 'undefined')
					confirmation_msg_variable = confirm_text.replace(/[\\']/g, '\\$&');
			});
		}
		else
		{
			// Error returned from the called function, show an alert
			if (oElement.getElementsByTagName('text').length !== 0)
				alert(oElement.getElementsByTagName('text')[0].firstChild.nodeValue.removeEntities());

			if (oElement.getElementsByTagName('url').length !== 0)
				window.location.href = oElement.getElementsByTagName('url')[0].firstChild.nodeValue;
		}

		if (typeof (onSuccessCallback) !== 'undefined')
			onSuccessCallback(btn, request, oElement.getElementsByTagName('error'));
	})
	.fail(function() {
		// ajax failure code
	})
	.always(function() {
		// turn off the indicator
		ajax_indicator(false);
	});

	return false;
}

/**
 * Helper function: displays and removes the ajax indicator and
 * hides some page elements inside "container_id"
 * Used by some (one at the moment) ajax buttons
 *
 * @todo it may be merged into the function if not used anywhere else
 *
 * @param {HTMLElement|string} btn string representing this, generally the anchor link tag <a class="" href="" onclick="">
 * @param {string} container_id  css ID of the data container
 */
function toggleHeaderAJAX(btn, container_id)
{
	// Show ajax is in progress
	ajax_indicator(true);
	var body_template = '<div class="board_row centertext">{body}</div>';

	$.ajax({
		type: 'GET',
		url: btn.href + ';xml;api',
		context: document.body,
		beforeSend: ajax_indicator(true)
		})
		.done(function(request) {
			if (request === '')
				return;

			var oElement = $(request).find('elk')[0];

			// No errors
			if (oElement.getElementsByTagName('error').length === 0)
			{
				var text_elem = oElement.getElementsByTagName('text'),
					body_elem = oElement.getElementsByTagName('body');

				$('#' + container_id + ' .pagesection').remove();
				$('#' + container_id + ' .topic_listing').remove();
				$('#' + container_id + ' .topic_sorting').remove();
				if (text_elem.length === 1)
					$('#' + container_id + ' #unread_header').html(text_elem[0].firstChild.nodeValue.removeEntities());
				if (body_elem.length === 1)
					$(body_template.replace('{body}', body_elem[0].firstChild.nodeValue.removeEntities())).insertAfter('#unread_header');
			}
		})
		.fail(function() {
			// ajax failure code
		})
		.always(function() {
			// turn off the indicator
			ajax_indicator(false);
		});
}

/**
 * Ajaxify the "notify" button in Display
 *
 * @param {string} btn string representing this, generally the anchor link tag <a class="" href="" onclick="">
 */
function notifyButton(btn)
{
	if (typeof (notification_topic_notice) !== 'undefined' && !confirm(notification_topic_notice))
		return false;

	return toggleButtonAJAX(btn, 'notification_topic_notice', function(btn, request, errors) {
		var toggle = 0;

		if (errors.length > 0)
			return;

		// This is a "turn notifications on"
		if (btn.href.indexOf('sa=on') !== -1)
			toggle = 1;
		else
			toggle = 0;

		$("input[name='notify']").val(toggle);
	});
}

/**
 * Ajaxify the "notify" button in MessageIndex
 *
 * @param {string} btn string representing this, generally the anchor link tag <a class="" href="" onclick="">
 */
function notifyboardButton(btn)
{
	if (typeof (notification_board_notice) !== 'undefined' && !confirm(notification_board_notice))
		return false;

	toggleButtonAJAX(btn, 'notification_board_notice');
	return false;
}

/**
 * Ajaxify the "unwatch" button in Display
 *
 * @param {string} btn string representing this, generally the anchor link tag <a class="" href="" onclick="">
 */
function unwatchButton(btn)
{
	toggleButtonAJAX(btn);
	return false;
}

/**
 * Ajaxify the "mark read" button in MessageIndex
 *
 * @param {string} btn string representing this, generally the anchor link tag <a class="" href="" onclick="">
 */
function markboardreadButton(btn)
{
	if (!confirm(txt_mark_as_read_confirm))
	{
		return false;
	}

	toggleButtonAJAX(btn);

	// Remove all the "new" icons next to the topics subjects
	$('.new_posts').remove();

	return false;
}

/**
 * Ajaxify the "mark all messages as read" button in BoardIndex
 *
 * @param {string} btn string representing this, generally the anchor link tag <a class="" href="" onclick="">
 */
function markallreadButton(btn)
{
	if (!confirm(txt_mark_as_read_confirm))
	{
		return false;
	}

	toggleButtonAJAX(btn);

	// Remove all the "new" icons next to the topics subjects
	$('.new_posts').remove();

	// Turn the board icon class to off
	$('.board_icon').each(function() {
		$(this).removeClass('i-board-new i-board-sub').addClass('i-board-off');
	});

	$('.board_new_posts').removeClass('board_new_posts');

	return false;
}

/**
 * Ajaxify the "mark all messages as read" button in Recent
 *
 * @param {string} btn string representing this, generally the anchor link tag <a class="" href="" onclick="">
 */
function markunreadButton(btn)
{
	if (!confirm(txt_mark_as_read_confirm))
	{
		return false;
	}

	toggleHeaderAJAX(btn, 'main_content_section');

	return false;
}

/**
 * This function changes the relative time around the page real-timeish
 */
var relative_time_refresh = 0;
function updateRelativeTime()
{
	// In any other case no more than one hour
	relative_time_refresh = 3600000;

	$('time').each(function() {
		var oRelativeTime = new relativeTime($(this).data('timestamp') * 1000, oRttime.referenceTime),
			time_text = '';

		if (oRelativeTime.seconds())
		{
			$(this).text(oRttime.now);
			relative_time_refresh = Math.min(relative_time_refresh, 10000);
		}
		else if (oRelativeTime.minutes())
		{
			time_text = oRelativeTime.deltaTime > 1 ? oRttime.minutes : oRttime.minute;
			$(this).text(time_text.replace('%s', oRelativeTime.deltaTime));
			relative_time_refresh = Math.min(relative_time_refresh, 60000);
		}
		else if (oRelativeTime.hours())
		{
			time_text = oRelativeTime.deltaTime > 1 ? oRttime.hours : oRttime.hour;
			$(this).text(time_text.replace('%s', oRelativeTime.deltaTime));
			relative_time_refresh = Math.min(relative_time_refresh, 3600000);
		}
		else if (oRelativeTime.days())
		{
			time_text = oRelativeTime.deltaTime > 1 ? oRttime.days : oRttime.day;
			$(this).text(time_text.replace('%s', oRelativeTime.deltaTime));
			relative_time_refresh = Math.min(relative_time_refresh, 3600000);
		}
		else if (oRelativeTime.weeks())
		{
			time_text = oRelativeTime.deltaTime > 1 ? oRttime.weeks : oRttime.week;
			$(this).text(time_text.replace('%s', oRelativeTime.deltaTime));
			relative_time_refresh = Math.min(relative_time_refresh, 3600000);
		}
		else if (oRelativeTime.months())
		{
			time_text = oRelativeTime.deltaTime > 1 ? oRttime.months : oRttime.month;
			$(this).text(time_text.replace('%s', oRelativeTime.deltaTime));
			relative_time_refresh = Math.min(relative_time_refresh, 3600000);
		}
		else if (oRelativeTime.years())
		{
			time_text = oRelativeTime.deltaTime > 1 ? oRttime.years : oRttime.year;
			$(this).text(time_text.replace('%s', oRelativeTime.deltaTime));
			relative_time_refresh = Math.min(relative_time_refresh, 3600000);
		}
	});
	oRttime.referenceTime += relative_time_refresh;

	setTimeout(function() {updateRelativeTime();}, relative_time_refresh);
}

/**
 * Function/object to handle relative times
 * sTo is optional, if omitted the relative time is
 * calculated from sFrom up to "now"
 *
 * @param {int} sFrom
 * @param {int} sTo
 */
function relativeTime(sFrom, sTo)
{
	if (typeof sTo === 'undefined')
	{
		this.dateTo = new Date();
	}
	else if (parseInt(sTo) == 'NaN')
	{
		var sToSplit = sTo.split(/\D/);
		this.dateTo = new Date(sToSplit[0], --sToSplit[1], sToSplit[2], sToSplit[3], sToSplit[4]);
	}
	else
		this.dateTo = new Date(sTo);

	if (parseInt(sFrom) == 'NaN')
	{
		var sFromSplit = sFrom.split(/\D/);
		this.dateFrom = new Date(sFromSplit[0], --sFromSplit[1], sFromSplit[2], sFromSplit[3], sFromSplit[4]);
	}
	else
		this.dateFrom = new Date(sFrom);

	this.time_text = '';
	this.past_time = (this.dateTo - this.dateFrom) / 1000;
	this.deltaTime = 0;
}

relativeTime.prototype.seconds = function()
{
	// Within the first 60 seconds it is just now.
	if (this.past_time < 60)
	{
		this.deltaTime = this.past_time;
		return true;
	}

	return false;
};

relativeTime.prototype.minutes = function()
{
	// Within the first hour?
	if (this.past_time >= 60 && Math.round(this.past_time / 60) < 60)
	{
		this.deltaTime = Math.round(this.past_time / 60);
		return true;
	}

	return false;
};

relativeTime.prototype.hours = function()
{
	// Some hours but less than a day?
	if (Math.round(this.past_time / 60) >= 60 && Math.round(this.past_time / 3600) < 24)
	{
		this.deltaTime = Math.round(this.past_time / 3600);
		return true;
	}

	return false;
};

relativeTime.prototype.days = function()
{
	// Some days ago but less than a week?
	if (Math.round(this.past_time / 3600) >= 24 && Math.round(this.past_time / (24 * 3600)) < 7)
	{
		this.deltaTime = Math.round(this.past_time / (24 * 3600));
		return true;
	}

	return false;
};

relativeTime.prototype.weeks = function()
{
	// Weeks ago but less than a month?
	if (Math.round(this.past_time / (24 * 3600)) >= 7 && Math.round(this.past_time / (24 * 3600)) < 30)
	{
		this.deltaTime = Math.round(this.past_time / (24 * 3600) / 7);
		return true;
	}

	return false;
};

relativeTime.prototype.months = function()
{
	// Months ago but less than a year?
	if (Math.round(this.past_time / (24 * 3600)) >= 30 && Math.round(this.past_time / (30 * 24 * 3600)) < 12)
	{
		this.deltaTime = Math.round(this.past_time / (30 * 24 * 3600));
		return true;
	}

	return false;
};

relativeTime.prototype.years = function()
{
	// Oha, we've passed at least a year?
	if (Math.round(this.past_time / (30 * 24 * 3600)) >= 12)
	{
		this.deltaTime = this.dateTo.getFullYear() - this.dateFrom.getFullYear();
		return true;
	}

	return false;
};

/**
 * Used to tag mentioned names when they are entered inline but NOT selected from the dropdown list
 * The name must have appeared in the dropdown and be found in that cache list
 *
 * @param {string} sForm the form that holds the container, only used for plain text QR
 * @param {string} sInput the container that atWho is attached
 */
function revalidateMentions(sForm, sInput)
{
	var cached_names,
		cached_queries,
		body,
		mentions,
		pos = -1,
		// Some random punctuation marks that may appear next to a name
		boundaries_pattern = /[ \.,;!\?'-\\\/="]/i;

	for (var i = 0, count = all_elk_mentions.length; i < count; i++)
	{
		// Make sure this mention object is for this selector, safety first
		if (all_elk_mentions[i].selector === sInput || all_elk_mentions[i].selector === '#' + sInput)
		{
			// Was this invoked as the editor plugin?
			if (all_elk_mentions[i].oOptions.isPlugin)
			{
				var $editor = $editor_data[all_elk_mentions[i].selector];

				cached_names = $editor.opts.mentionOptions.cache.names;
				cached_queries = $editor.opts.mentionOptions.cache.queries;

				// Clean up the newlines and spacing so we can find the @mentions
				body = $editor.getText().replace(/[\u00a0\r\n]/g, ' ');
				mentions = $($editor.opts.mentionOptions.cache.mentions);
			}
			// Or just our plain text quick reply box?
			else
			{
				cached_names = all_elk_mentions[i].oMention.cached_names;
				cached_queries = all_elk_mentions[i].oMention.cached_queries;

				// Keep everything separated with spaces, not newlines or no breakable
				body = document.forms[sForm][sInput].value.replace(/[\u00a0\r\n]/g, ' ');

				// The last pulldown box that atWho populated
				mentions = $(all_elk_mentions[i].oMention.mentions);
			}

			// Adding a space at the beginning to facilitate catching of mentions at the 1st char
			// and one at the end to simplify catching any last thing in the text
			body = ' ' + body + ' ';

			// First check if all those in the list are really mentioned
			$(mentions).find('input').each(function (idx, elem) {
				var name = $(elem).data('name'),
					next_char,
					prev_char,
					index = body.indexOf(name);

				// It is undefined coming from a preview
				if (typeof(name) !== 'undefined')
				{
					if (index === -1)
						$(elem).remove();
					else
					{
						next_char = body.charAt(index + name.length);
						prev_char = body.charAt(index - 1);

						if (next_char !== '' && next_char.search(boundaries_pattern) !== 0)
							$(elem).remove();
						else if (prev_char !== '' && prev_char.search(boundaries_pattern) !== 0)
							$(elem).remove();
					}
				}
			});

			for (var k = 0, ccount = cached_queries.length; k < ccount; k++)
			{
				var names = cached_names[cached_queries[k]];

				for (var l = 0, ncount = names.length; l < ncount; l++)
				{
					if(checkWordOccurrence(body, names[l].name)) {
						pos = body.indexOf(' @' + names[l].name);

						// If there is something like "{space}@username" AND the following char is a space or a punctuation mark
						if (pos !== -1 && body.charAt(pos + 2 + names[l].name.length + 1).search(boundaries_pattern) === 0)
							mentions.append($('<input type="hidden" name="uid[]" />').val(names[l].id));
					}
				}
			}
		}
	}
}

/**
 * Check whether the word exists in a given paragraph
 *
 * @param paragraph to check
 * @param word to match
 */

function checkWordOccurrence(paragraph, word){
  return new RegExp( '\\b' + word + '\\b', 'i').test(paragraph);
}

/**
 * This is called from the editor plugin or display.template to set where to
 * find the cache values for use in revalidateMentions
 *
 * @param {string} selector id of element that atWho is attached to
 * @param {object} oOptions only set when called from the plugin, contains those options
 */
var all_elk_mentions = [];
function add_elk_mention(selector, oOptions)
{
	// Global does not exist, hummm
	if (all_elk_mentions.hasOwnProperty(selector))
		return;

	// No options means its attached to the plain text box
	if (typeof oOptions === 'undefined')
		oOptions = {};
	oOptions.selector = selector;

	// Add it to the stack
	all_elk_mentions[all_elk_mentions.length] = {
		selector: selector,
		oOptions: oOptions
	};
}

/**
 * Drag and drop to reorder ID's via UI Sortable
 *
 * @param {object} $
 */
(function($) {
	'use strict';
	$.fn.elkSortable = function(oInstanceSettings) {
		$.fn.elkSortable.oDefaultsSettings = {
			opacity: 0.7,
			cursor: 'move',
			axis: 'y',
			scroll: true,
			containment: 'parent',
			delay: 150,
			handle: '', // Restricts sort start click to the specified element, like category_header
			href: '', // If an error occurs redirect here
			tolerance: 'intersect', // mode to use for testing whether the item is hovering over another item.
			setorder: 'serialize', // how to return the data, really only supports serialize and inorder
			placeholder: '', // css class used to style the landing zone
			preprocess: '', // This function is called at the start of the update event (when the item is dropped) must in in global space
			tag: '#table_grid_sortable', // ID(s) of the container to work with, single or comma separated
			connect: '', // Use to group all related containers with a common CSS class
			sa: '', // Subaction that the xmlcontroller should know about
			title: '', // Title of the error box
			error: '', // What to say when we don't know what happened, like connection error
			token: '' // Security token if needed
		};

		// Account for any user options
		var oSettings = $.extend({}, $.fn.elkSortable.oDefaultsSettings, oInstanceSettings || {});

		if (typeof oSettings.infobar === 'undefined')
		{
			oSettings.infobar = new ElkInfoBar('sortable_bar', {error_class: 'errorbox', success_class: 'infobox'});
		}

		// Divs to hold our responses
		$("<div id='errorContainer'><div/>").appendTo('body');

		$('#errorContainer').css({'display': 'none'});

		// Find all oSettings.tag and attach the UI sortable action
		$(oSettings.tag).sortable({
			opacity: oSettings.opacity,
			cursor: oSettings.cursor,
			axis: oSettings.axis,
			handle: oSettings.handle,
			containment: oSettings.containment,
			connectWith: oSettings.connect,
			placeholder: oSettings.placeholder,
			tolerance: oSettings.tolerance,
			delay: oSettings.delay,
			scroll: oSettings.scroll,
			helper: function(e, ui) {
				// Fist create a helper container
				var $originals = ui.children(),
					$helper = ui.clone(),
					$clone;

				// Replace the helper elements with spans, normally this is a <td> -> <span>
				// Done to make this container agnostic.
				$helper.children().each(function() {
					$(this).replaceWith(function(){
						return $("<span />", {html: $(this).html()});
					});
				});

				// Set the width of each helper cell span to be the width of the original cells
				$helper.children().each(function(index) {
					// Set helper cell sizes to match the original sizes
					return $(this).width($originals.eq(index).width()).css('display', 'inline-block');
				});

				// Next to overcome an issue where page scrolling does not work, we add the new agnostic helper
				// element to the body, and hide it
				$('body').append('<div id="clone" class="' + oSettings.placeholder + '">' + $helper.html() + '</div>');
				$clone = $('#clone');
				$clone.hide();

				// Append the clone element to the actual container we are working in and show it
				setTimeout(function() {
					$clone.appendTo(ui.parent());
					$clone.show();
				}, 1);

				// The above append process allows page scrolls to work while dragging the clone element
				return $clone;
			},
			update: function(e, ui) {
				// Called when an element is dropped in a new location
				var postdata = '',
					moved = ui.item.attr('id'),
					order = [],
					receiver = ui.item.parent().attr('id');

				// Calling a pre processing function?
				if (oSettings.preprocess !== '')
					window[oSettings.preprocess]();

				// How to post the sorted data
				if (oSettings.setorder === 'inorder')
				{
					// This will get the order in 1-n as shown on the screen
					$(oSettings.tag).find('li').each(function() {
						var aid = $(this).attr('id').split('_');
						order.push({name: aid[0] + '[]', value: aid[1]});
					});
					postdata = $.param(order);
				}
				// Get all id's in all the sortable containers
				else
				{
					$(oSettings.tag).each(function() {
						// Serialize will be 1-n of each nesting / connector
						if (postdata === "")
							postdata += $(this).sortable(oSettings.setorder);
						else
							postdata += "&" + $(this).sortable(oSettings.setorder);
					});
				}

				// Add in our security tags and additional options
				postdata += '&' + elk_session_var + '=' + elk_session_id;
				postdata += '&order=reorder';
				postdata += '&moved=' + moved;
				postdata += '&received=' + receiver;

				if (oSettings.token !== '')
					postdata += '&' + oSettings.token.token_var + '=' + oSettings.token.token_id;

				// And with the post data prepared, lets make the ajax request
				$.ajax({
					type: "POST",
					url: elk_scripturl + "?action=xmlhttp;sa=" + oSettings.sa + ";xml",
					dataType: "xml",
					data: postdata
				})
				.fail(function(jqXHR, textStatus, errorThrown) {
					oSettings.infobar.isError();
					oSettings.infobar.changeText(textStatus).showBar();
					// Reset the interface?
					if (oSettings.href !== '')
						setTimeout(function() {
							window.location.href = elk_scripturl + oSettings.href;
						}, 1000);
				})
				.done(function(data, textStatus, jqXHR) {
					var $_errorContent = $('#errorContent'),
						$_errorContainer = $('#errorContainer');

					if ($(data).find("error").length !== 0)
					{
						// Errors get a modal dialog box and redirect on close
						$_errorContainer.append('<p id="errorContent"></p>');
						$_errorContent.html($(data).find("error").text());
						$_errorContent.dialog({
							autoOpen: true,
							title: oSettings.title,
							modal: true,
							close: function(event, ui) {
								// Redirecting due to the error, that's a good idea
								if (oSettings.href !== '')
									window.location.href = elk_scripturl + oSettings.href;
							}
						});
					}
					else if ($(data).find("elk").length !== 0)
					{
						// Valid responses get the unobtrusive slider
						oSettings.infobar.isSuccess();
						oSettings.infobar.changeText($(data).find('elk > orders > order').text()).showBar();
					}
					else
					{
						// Something "other" happened ...
						$_errorContainer.append('<p id="errorContent"></p>');
						$_errorContent.html(oSettings.error + ' : ' + textStatus);
						$_errorContent.dialog({autoOpen: true, title: oSettings.title, modal: true});
					}
				})
				.always(function(data, textStatus, jqXHR) {
					if ($(data).find("elk > tokens > token").length !== 0)
					{
						// Reset the token
						oSettings.token.token_id = $(data).find("tokens").find('[type="token"]').text();
						oSettings.token.token_var = $(data).find("tokens").find('[type="token_var"]').text();
					}
				});
			}
		});
	};
})(jQuery);

/**
 * Helper function used in the preprocess call for drag/drop boards
 * Sets the id of all 'li' elements to cat#,board#,childof# for use in the
 * $_POST back to the xmlcontroller
 */
function setBoardIds() {
	// For each category of board
	$("[id^=category_]").each(function() {
		var cat = $(this).attr('id').split('category_'),
			uls = $(this).find("ul");

		// First up add drop zones so we can drag and drop to each level
		if (uls.length === 1)
		{
			// A single empty ul in a category, this can happen when a cat is dragged empty
			if ($(uls).find("li").length === 0)
				$(uls).append('<li id="cbp_' + cat + ',-1,-1"></li>');
			// Otherwise the li's need a child ul so we have a "child-of" drop zone
			else
				$(uls).find("li:not(:has(ul))").append('<ul class="nolist elk_droppings"></ul>');
		}
		// All others normally
		else
			$(uls).find("li:not(:has(ul))").append('<ul class="nolist elk_droppings"></ul>');

		// Next make find all the ul's in this category that have children, update the
		// id's with information that indicates the 1-n and parent/child info
		$(this).find('ul:parent').each(function(i, ul) {

			// Get the (li) parent of this ul
			var parentList = $(this).parent('li').attr('id'),
					pli = 0;

			// No parent, then its a base node 0, else its a child-of this node
			if (typeof (parentList) !== "undefined")
			{
				pli = parentList.split(",");
				pli = pli[1];
			}

			// Now for each li in this ul
			$(this).find('li').each(function(i, el) {
				var currentList = $(el).attr('id');
				var myid = currentList.split(",");

				// Remove the old id, insert the newly computed cat,brd,childof
				$(el).removeAttr("id");
				myid = "cbp_" + cat[1] + "," + myid[1] + "," + pli;
				$(el).attr('id', myid);
			});
		});
	});
}

/**
 * Expands the ... of the page indexes
 *
 * @todo not exactly a plugin and still very bound to the theme structure
 *
 */
;(function($) {
	$.fn.expand_pages = function() {
		// Used when the user clicks on the ... to expand instead of just a hover expand
		function expand_pages($element)
		{
			var $baseAppend = $($element.closest('.linavPages')),
				boxModel = $baseAppend.prev().clone(),
				aModel = boxModel.find('a').clone(),
				expandModel = $element.clone(),
				perPage = $element.data('perpage'),
				firstPage = $element.data('firstpage'),
				lastPage = $element.data('lastpage'),
				rawBaseurl = $element.data('baseurl'),
				baseurl = elk_scripturl + $element.data('baseurl'),
				first,
				i = 0,
				oldLastPage = 0,
				perPageLimit = 10;

			// Prevent too many pages to be loaded at once.
			if ((lastPage - firstPage) / perPage > perPageLimit)
			{
				oldLastPage = lastPage;
				lastPage = firstPage + perPageLimit * perPage;
			}

			// Calculate the new pages.
			for (i = lastPage; i > firstPage; i -= perPage)
			{
				var bElem = aModel.clone(),
					boxModelClone = boxModel.clone();

				bElem.attr('href', baseurl.replace('%1$d', i - perPage)).text(i / perPage);
				boxModelClone.find('a').each(function() {
					$(this).replaceWith(bElem[0]);
				});
				$baseAppend.after(boxModelClone);

				// This is needed just to remember where to attach the new expand
				if (typeof first === 'undefined')
					first = boxModelClone;
			}
			$baseAppend.remove();

			if (oldLastPage > 0)
			{
				// This is to remove any hover_expand
				expandModel.find('#expanded_pages_container').each(function() {
					$(this).remove();
				});

				expandModel.on('click', function(e) {
					var $zhis = $(this);
					e.preventDefault();

					expand_pages($zhis);

					$zhis.off('mouseenter focus');
				})
				.on('mouseenter focus', function() {
					hover_expand($(this));
				})
				.data('perpage', perPage)
				.data('firstpage', lastPage)
				.data('lastpage', oldLastPage)
				.data('baseurl', rawBaseurl);

				first.after(expandModel);
			}
		}

		this.attr('tabindex', 0).on('click', function(e) {
			var $zhis = $(this);
			e.preventDefault();

			expand_pages($zhis);
		});
	};
})(jQuery);

/**
 * SiteTooltip, Basic JQuery function to provide styled tooltips
 *
 * - will use the hoverintent plugin if available
 * - shows the tooltip in a div with the class defined in tooltipClass
 * - moves all selector titles to a hidden div and removes the title attribute to
 *   prevent any default browser actions
 * - attempts to keep the tooltip on screen
 *
 * @param {type} $
 */
(function($) {
	'use strict';
	$.fn.SiteTooltip = function(oInstanceSettings) {
		$.fn.SiteTooltip.oDefaultsSettings = {
			followMouse: 1,
			hoverIntent: {sensitivity: 10, interval: 650, timeout: 50},
			positionTop: 12,
			positionLeft: 12,
			tooltipID: 'site_tooltip', // ID used on the outer div
			tooltipTextID: 'site_tooltipText', // as above but on the inner div holding the text
			tooltipClass: 'tooltip', // The class applied to the outer div (that displays on hover), use this in your css
			tooltipSwapClass: 'site_swaptip', // a class only used internally, change only if you have a conflict
			tooltipContent: 'html' // display captured title text as html or text
		};

		// Account for any user options
		var oSettings = $.extend({}, $.fn.SiteTooltip.oDefaultsSettings, oInstanceSettings || {});

		// Move passed selector titles to a hidden span, then remove the selector title to prevent any default browser actions
		$(this).each(function()
		{
			var sTitle = $('<span class="' + oSettings.tooltipSwapClass + '">' + this.title + '</span>').hide();
			$(this).append(sTitle).attr('title', '');
		});

		// Determine where we are going to place the tooltip, while trying to keep it on screen
		var positionTooltip = function(event)
		{
			var iPosx = 0,
				iPosy = 0,
				$_tip = $('#' + oSettings.tooltipID);

			if (!event)
				event = window.event;

			if (event.pageX || event.pageY)
			{
				iPosx = event.pageX;
				iPosy = event.pageY;
			}
			else if (event.clientX || event.clientY)
			{
				iPosx = event.clientX + document.body.scrollLeft + document.documentElement.scrollLeft;
				iPosy = event.clientY + document.body.scrollTop + document.documentElement.scrollTop;
			}

			// Position of the tooltip top left corner and its size
			var oPosition = {
					x: iPosx + oSettings.positionLeft,
					y: iPosy + oSettings.positionTop,
					w: $_tip.width(),
					h: $_tip.height()
				};

			// Display limits and window scroll position
			var oLimits = {
				x: $(window).scrollLeft(),
				y: $(window).scrollTop(),
				w: $(window).width() - 24,
				h: $(window).height() - 24
			};

			// Don't go off screen with our tooltip
			if ((oPosition.y + oPosition.h > oLimits.y + oLimits.h) && (oPosition.x + oPosition.w > oLimits.x + oLimits.w))
			{
				oPosition.x = (oPosition.x - oPosition.w) - 45;
				oPosition.y = (oPosition.y - oPosition.h) - 45;
			}
			else if ((oPosition.x + oPosition.w) > (oLimits.x + oLimits.w))
			{
				oPosition.x -= (((oPosition.x + oPosition.w) - (oLimits.x + oLimits.w)) + 24);
			}
			else if (oPosition.y + oPosition.h > oLimits.y + oLimits.h)
			{
				oPosition.y -= (((oPosition.y + oPosition.h) - (oLimits.y + oLimits.h)) + 24);
			}

			// Finally set the position we determined
			$_tip.css({'left': oPosition.x + 'px', 'top': oPosition.y + 'px'});
		};

		// Used to show a tooltip
		var showTooltip = function() {
			$('#' + oSettings.tooltipID + ' #' + oSettings.tooltipTextID).slideDown(150);
		};

		// Used to hide a tooltip
		var hideTooltip = function() {
			var $_tip = $('#' + oSettings.tooltipID);

			$_tip.fadeOut(175, function() {
				$(this).trigger("unload").remove();
			});
		};

		// Used to keep html encoded
		function htmlspecialchars(string)
		{
			return $('<span>').text(string).html();
		}

		// For all of the elements that match the selector on the page, lets set up some actions
		return this.each(function()
		{
			// If we find hoverIntent then use it
			if ($.fn.hoverIntent)
			{
				$(this).hoverIntent({
					sensitivity: oSettings.hoverIntent.sensitivity,
					interval: oSettings.hoverIntent.interval,
					over: site_tooltip_on,
					timeout: oSettings.hoverIntent.timeout,
					out: site_tooltip_off
				});
			}
			else
			{
				// Plain old hover it is
				$(this).hover(site_tooltip_on, site_tooltip_off);
			}

			// Create the on tip action
			function site_tooltip_on(event)
			{
				// If we have text in the hidden span element we created on page load
				if ($(this).children('.' + oSettings.tooltipSwapClass).text())
				{
					// Create a ID'ed div with our style class that holds the tooltip info, hidden for now
					$('body').append('<div id="' + oSettings.tooltipID + '" class="' + oSettings.tooltipClass + '"><div id="' + oSettings.tooltipTextID + '" class="hide"></div></div>');

					// Load information in to our newly created div
					var ttContent = $('#' + oSettings.tooltipTextID);

					if (oSettings.tooltipContent === 'html')
						ttContent.html($(this).children('.' + oSettings.tooltipSwapClass).html());
					else
						ttContent.text($(this).children('.' + oSettings.tooltipSwapClass).text());

					// Show then position or it may position off screen
					showTooltip();
					positionTooltip(event);
				}

				return false;
			}

			// Create the Bye bye tip
			function site_tooltip_off(event)
			{
				hideTooltip(this);
				return false;
			}

			// Create the tip move with the cursor
			if (oSettings.followMouse)
			{
				$(this).on("mousemove", function(event) {
					positionTooltip(event);

					return false;
				});
			}

			// Clear the tip on a click
			$(this).on("click", function() {
				hideTooltip(this);
				return true;
			});
		});
	};
})(jQuery);

/**
 * Error box handler class
 *
 * @param {type} oOptions
 * @returns {errorbox_handler}
 */
var error_txts = {};
function errorbox_handler(oOptions)
{
	this.opt = oOptions;
	this.oError_box = null;
	this.oErrorHandle = window;
	this.evaluate = false;
	this.init();
}

/**
 * @todo this code works well almost only with the editor I think.
 */
errorbox_handler.prototype.init = function()
{
	if (this.opt.check_id !== undefined)
		this.oChecks_on = $(document.getElementById(this.opt.check_id));
	else if (this.opt.selector !== undefined)
		this.oChecks_on = this.opt.selector;
	else if (this.opt.editor !== undefined)
	{
		this.oChecks_on = eval(this.opt.editor);
		this.evaluate = true;
	}

	this.oErrorHandle.instanceRef = this;

	if (this.oError_box === null)
		this.oError_box = $(document.getElementById(this.opt.error_box_id));

	if (this.evaluate === false)
	{
		this.oChecks_on.attr('onblur', this.opt.self + '.checkErrors()');
		this.oChecks_on.attr('onkeyup', this.opt.self + '.checkErrors()');
	}
	else
	{
		var current_error_handler = this.opt.self;
		$(function() {
			var current_error = eval(current_error_handler);
			$editor_data[current_error.opt.editor_id].addEvent(current_error.opt.editor_id, 'keyup', function() {
				current_error.checkErrors();
			});
		});
	}
};

errorbox_handler.prototype.boxVal = function()
{
	if (this.evaluate === false)
		return this.oChecks_on.val();
	else
		return this.oChecks_on();
};

/**
 * Runs the field checks as defined by the object instance
 */
errorbox_handler.prototype.checkErrors = function()
{
	var num = this.opt.error_checks.length;

	if (num !== 0)
	{
		// Adds the error checking functions
		for (var i = 0; i < num; i++)
		{
			// Get the element that holds the errors
			var $elem = $(document.getElementById(this.opt.error_box_id + "_" + this.opt.error_checks[i].code));

			// Run the efunction check on this field, then add or remove any errors
			if (this.opt.error_checks[i].efunction(this.boxVal()))
				this.addError($elem, this.opt.error_checks[i].code);
			else
				this.removeError(this.oError_box, $elem);
		}

		this.oError_box.attr("class", "errorbox");
	}

	// Hide show the error box based on if we have any errors
	if (this.oError_box.find("li").length === 0)
		this.oError_box.slideUp();
	else
		this.oError_box.slideDown();
};

/**
 * Add and error to the list
 *
 * @param {type} error_elem
 * @param {type} error_code
 */
errorbox_handler.prototype.addError = function(error_elem, error_code)
{
	if (error_elem.length === 0)
	{
		// First error, then set up the list for insertion
		if ($.trim(this.oError_box.children("#" + this.opt.error_box_id + "_list").html()) === '')
			this.oError_box.append("<ul id='" + this.opt.error_box_id + "_list'></ul>");

		// Add the error it and show it
		$(document.getElementById(this.opt.error_box_id + "_list")).append("<li style=\"display:none;\" id='" + this.opt.error_box_id + "_" + error_code + "' class='error'>" + error_txts[error_code] + "</li>");
		$(document.getElementById(this.opt.error_box_id + "_" + error_code)).slideDown();
	}
};

/**
 * Remove an error from the notice window
 *
 * @param {type} error_box
 * @param {type} error_elem
 */
errorbox_handler.prototype.removeError = function(error_box, error_elem)
{
	if (error_elem.length !== 0)
	{
		error_elem.slideUp(function() {
			error_elem.remove();

			// No errors at all then close the box
			if (error_box.find("li").length === 0)
				error_box.slideUp();
		});
	}
};

/**
 * Add a new dt/dd pair above a parent selector
 * Called most often as a callback option in config options
 * If oData is supplied, will create a select list, populated with that data
 * otherwise a standard input box.
 *
 * @param {string} parent id of the parent "add more button: we will place this before
 * @param {object} oDtName object of dt element options (type, class, size)
 * @param {object} oDdName object of the dd element options (type, class size)
 * @param {object} [oData] optional select box object, 1:{id:value,name:display name}, ...
 */
function addAnotherOption(parent, oDtName, oDdName, oData)
{
	// Some defaults to use if none are passed
	oDtName['type'] = oDtName.type || 'text';
	oDtName['class'] = oDtName['class'] || 'input_text';
	oDtName['size'] = oDtName.size || '20';

	oDdName['type'] = oDdName.type || 'text';
	oDdName['class'] = oDdName['class'] || 'input_text';
	oDdName['size'] = oDdName.size || '20';
	oData = oData || '';

	// Our new <dt> element
	var newDT = document.createElement('dt'),
		newInput = document.createElement('input');

	newInput.name = oDtName.name;
	newInput.type = oDtName.type;
	newInput.setAttribute('class', oDtName['class']);
	newInput.size = oDtName.size;
	newDT.appendChild(newInput);

	// And its matching <dd>
	var newDD = document.createElement('dd');

	// If we have data for this field make it a select
	if (oData === '')
		newInput = document.createElement('input');
	else
		newInput = document.createElement('select');

	newInput.name = oDdName.name;
	newInput.type = oDdName.type;
	newInput.size = oDdName.size;
	newInput.setAttribute('class', oDdName['class']);
	newDD.appendChild(newInput);

	// If its a select box we add in the options
	if (oData !== '')
	{
		// The options are children of the newInput select box
		var opt,
			key,
			obj;

		for (key in oData)
		{
			obj = oData[key];
			opt = document.createElement("option");
			opt.name = "option";
			opt.value = obj.id;
			opt.innerHTML = obj.name;
			newInput.appendChild(opt);
		}
	}

	// Place the new dt/dd pair before our parent
	var placeHolder = document.getElementById(parent);

	placeHolder.parentNode.insertBefore(newDT, placeHolder);
	placeHolder.parentNode.insertBefore(newDD, placeHolder);
}

/**
 * Shows the member search dropdown with the search options
 */
function toggle_mlsearch_opt()
{
	var $_mlsearch = $('#mlsearch_options');

	// If the box is already visible just forget about it
	if ($_mlsearch.is(':visible'))
		return;

	// Time to show the droppy
	$_mlsearch.fadeIn('fast');

	// A click anywhere on the page will close the droppy
	$('body').on('click', mlsearch_opt_hide);

	// Except clicking on the box itself or into the search text input
	$('#mlsearch_options, #mlsearch_input').off('click', mlsearch_opt_hide).on('click', function(ev) {
		ev.stopPropagation();
	});
}

/**
 * Hides the member search dropdown and detach the body click event
 */
function mlsearch_opt_hide()
{
	$('body').off('click', mlsearch_opt_hide);
	$('#mlsearch_options').slideToggle('fast');
}

/**
 * Called when the add/remove poll button is pressed from the post screen
 *
 * Used to add add/remove poll input area above the post new topic screen
 * Updates the message icon to the poll icon
 * Swaps poll button to match the current conditions
 *
 * @param {object} button
 * @param {int} id_board
 * @param {string} form_name
 */
function loadAddNewPoll(button, id_board, form_name)
{
	if (typeof id_board === 'undefined')
		return true;

	// Find the form and add poll to the url
	var $form = $('#post_header').closest("form"),
		$_poll_main_option = $('#poll_main, #poll_options');

	// Change the button label
	if ($(button).val() === poll_add)
	{
		$(button).val(poll_remove);

		// We usually like to have the poll icon associated to polls,
		// but only if the currently selected is the default one
		var $_pollicon = $('#icon');
		if ($_pollicon.val() === 'xx')
			$_pollicon.val('poll').change();

		// Add poll to the form action
		$form.attr('action', $form.attr('action') + ';poll');

		// If the form already exists...just show it back and go out
		if ($('#poll_main').length > 0)
		{
			$_poll_main_option.find('input').each(function() {
				if ($(this).data('required') === 'required')
					$(this).attr('required', 'required');
			});

			$_poll_main_option.toggle();
			return false;
		}
	}
	// Remove the poll section
	else
	{
		var $_icon = $('#icon');

		if ($_icon.val() === 'poll')
			$_icon.val('xx').change();

		// Remove poll to the form action
		$form.attr('action', $form.attr('action').replace(';poll', ''));

		$_poll_main_option.hide().find('input').each(function() {
			if ($(this).attr('required') === 'required')
			{
				$(this).data('required', 'required');
				$(this).removeAttr('required');
			}
		});

		$(button).val(poll_add);

		return false;
	}

	// Retrieve the poll area
	$.ajax({
		url: elk_scripturl + '?action=poll;sa=interface;xml;board=' + id_board,
		type: "GET",
		dataType: "html",
		beforeSend: ajax_indicator(true)
	})
	.done(function (data, textStatus, xhr) {
		// Find the highest tabindex already present
		var max_tabIndex = 0;
		for (var i = 0, n = document.forms[form_name].elements.length; i < n; i++)
			max_tabIndex = Math.max(max_tabIndex, document.forms[form_name].elements[i].tabIndex);

		// Inject the html
		$('#post_header').after(data);

		$('#poll_main input, #poll_options input').each(function () {
			$(this).attr('tabindex', ++max_tabIndex);
		});

		// Repeated collapse/expand of fieldsets as above
		$('#poll_main legend, #poll_options legend').on('click', function() {
			$(this).siblings().slideToggle("fast");
			$(this).parent().toggleClass("collapsed");
		}).each(function () {
			if ($(this).data('collapsed'))
			{
				$(this).siblings().css({display: "none"});
				$(this).parent().toggleClass("collapsed");
			}
		});
	})
	.always(function() {
		// turn off the indicator
		ajax_indicator(false);
	});

	return false;
}

/**
 * Attempt to prevent browsers from auto completing fields when viewing/editing other members profiles
 * or when register new member
 */
function disableAutoComplete()
{
	window.onload = function() {
		// Turn off autocomplete for these elements
		$("input[type=email], input[type=password], .input_text, .input_clear").attr("autocomplete", "off");

		// Chrome will fill out the form even with autocomplete off, so we need to empty the value as well.
		setTimeout(function() {
			$("input[type=password], .input_clear").val(" ").val("");
		}, 1);
	};
}

/**
 * A system to collect notifications from a single AJAX call and redistribute them
 * among notifiers
 */
(function() {
	var ElkNotifications = (function(opt) {
		'use strict';

		opt = (opt) ? opt : {};
		var _notifiers = [],
			start = true,
			lastTime = 0;

		var init = function(opt) {
			if (typeof opt.delay === 'undefined')
			{
				start = false;
				opt.delay = 15000;
			}

			setTimeout(function() {
				fetch();
			}, opt.delay);
		};

		var add = function(notif) {
			_notifiers.push(notif);
		};

		var send = function(request) {
			for (var i = 0; i < _notifiers.length; i++) {
				_notifiers[i].send(request);
			}
		};

		var fetch = function() {
			if (_notifiers.length === 0)
				return;

			$.ajax({
				url: elk_scripturl + "?action=mentions;sa=fetch;api=json;lastsent=" + lastTime
			})
			.done(function(request) {
				if (request !== "") {
					send(request);
					lastTime = request.timelast;
				}

				setTimeout(function() {
					fetch();
				}, opt.delay);
			});
		};

		init(opt);
		return {
			add: add
		};
	});

	// AMD / RequireJS
	if ( typeof define !== 'undefined' && define.amd) {
		define([], function() {
			return ElkNotifications;
		});
	}
	// CommonJS
	else if ( typeof module !== 'undefined' && module.exports) {
		module.exports = ElkNotifications;
	}
	// included directly via <script> tag
	else {
		this.ElkNotifications = ElkNotifications;
	}

})();

var ElkNotifier = new ElkNotifications();

/**
 * Initialize the inline attachments posting interface
 */
(function () {
	var ElkInlineAttachments = (function (selector, editor, opt) {
		'use strict';

		opt = $.extend({
			inlineSelector: '.inline_insert',
			data: 'attachid',
			addAfter: 'label',
			template: ''
		}, opt);

		var listAttachs = [],
			init = function (opt) {},
			addInterface = function ($before, attachId) {
				var $trigger, $container = $('<div class="container" />'), $over;

				if (typeof opt.trigger !== 'undefined')
				{
					$trigger = opt.trigger.clone();
				}
				else
				{
					$trigger = $('<a />');

					if (typeof opt.triggerClass !== 'undefined')
					{
						$trigger.addClass(opt.triggerClass);
					}
				}

				$container.append($trigger);
				$trigger.on('click', function (e) {
					e.preventDefault();

					if ($over != undefined)
					{
						$(document).trigger('click.ila_insert');
						return;
					}

					$over = $(opt.template).hide();
					var firstLi = false,
					    $tabs = $over.find("ul[data-group='tabs'] li");
					/*
					 * Behaviours (onSomething)
					 */
					$tabs.each(function(k, v) {
						$(this).on('click', function(e) {
							e.preventDefault();
							e.stopPropagation();

							$tabs.each(function(k, v) {
								$(this).removeClass('active');
							});
							var toShow = $(this).data('tab');
							$(this).addClass('active');
							$over.find('.container').each(function(k, v) {
								if ($(this).data('visual') == toShow)
								{
									$(this).show();
								}
								else
								{
									$(this).hide();
								}
							});
						});
						if (firstLi == false)
						{
							$(this).click();
							firstLi = true;
						}
					});
					$over.find("input[data-size='thumb']").on('change', function(e) {
						$over.find('.customsize').slideUp();
					});
					$over.find("input[data-size='full']").on('change', function(e) {
						$over.find('.customsize').slideUp();
					});
					$over.find("input[data-size='cust']").on('change', function(e) {
						$over.find('.customsize').slideDown();
					});
					$over.find(".range").on('input', function () {
						var val = $(this).val()
						$over.find(".visualizesize").val(val + 'px');
					}).trigger('input');

					$over.find('.button').on('click', function() {
						var ila_text = '[attach';
						if ($over.find("input[data-size='thumb']").is(':checked'))
						{
							ila_text = ila_text + ' type=thumb';
						}
						else if ($over.find("input[data-size='cust']").is(':checked'))
						{
							var w = $over.find('.range').val();
							// Doesn't really matter that much, but just to ensure it's not 1
							if (w > 10)
							{
								ila_text = ila_text + ' width=' + w;
							}
						}
						else if ($over.find("input[data-size='full']").is(':checked'))
						{
							ila_text = ila_text + ' type=image';
						}

						$over.find(".container[data-visual='align'] input").each(function (k, v) {
							if ($(this).is(':checked'))
							{
								if ($(this).data('align') != 'none')
								{
									ila_text = ila_text + ' align=' + $(this).data('align');
									return;
								}
							}
						});

						ila_text = ila_text + ']' + attachId + '[/attach]';
						$editor_data[editor].insertText(ila_text, false, true);
						$(document).trigger('click.ila_insert');
					});
					// Prevents removing the element to disappear when clicking on
					// anything because of the click.ila_insert event
					$over.find('*').on('click', function(e) {
						e.stopPropagation();
					});

					/*
					 * Initialization
					 */
					$over.find('.container label:first-child input').each(function(k, v) {
						$(this).change().prop('checked', true);
					});

					$container.append($over);
					$over.fadeIn(function() {
						$(document).on('click.ila_insert', function() {
							$over.fadeOut(function() {
								$over.remove();
								$over = undefined;
							});
							$(document).off('click.ila_insert');
						});
					});
				}).attr('id', 'inline_attach_' + attachId)
					.data('attachid', attachId);

				$before.after($container);
				listAttachs.push($trigger);
			},
			removeAttach = function (attachId) {
				var tmpList = [],
					i;

				for (i = 0; i < listAttachs.length; i++) {
					if (listAttachs[i].data('attachid') == attachId)
						break;

					tmpList.push(listAttachs[i]);
				}

				i++;
				for (; i < listAttachs.length; i++) {
					tmpList.push(listAttachs[i]);
				}

				listAttachs = tmpList;
				$('#inline_attach_' + attachId).remove();
			};

		init(opt);
		return {
			addInterface: addInterface,
			removeAttach: removeAttach
		};
	});

	// AMD / RequireJS
	if (typeof define !== 'undefined' && define.amd) {
		define([], function () {
			return ElkInlineAttachments;
		});
	}
	// CommonJS
	else if (typeof module !== 'undefined' && module.exports) {
		module.exports = ElkInlineAttachments;
	}
	// included directly via <script> tag
	else {
		this.ElkInlineAttachments = ElkInlineAttachments;
	}
})();

/**
 * Initialize the ajax info-bar
 */
(function () {
	var ElkInfoBar = (function (elem_id, opt) {
		'use strict';

		opt = $.extend({
			text: '',
			class: 'ajax_infobar',
			hide_delay: 4000,
			error_class: 'error',
			success_class: 'success'
		}, opt);

		var $elem = $('#' + elem_id),
			time_out = null,
			init = function (elem_id, opt) {
				clearTimeout(time_out);
				if ($elem.length === 0) {
					$elem = $('<div id="' + elem_id + '" class="' + opt.class + ' hide" />');
					$('body').append($elem);
					$elem.attr('id', elem_id);
					$elem.addClass(opt.class);
					$elem.text(opt.text);
				}
			},
			changeText = function (text) {
				clearTimeout(time_out);
				$elem.html(text);
				return this;
			},
			addClass = function (aClass) {
				$elem.addClass(aClass);
				return this;
			},
			removeClass = function (aClass) {
				$elem.removeClass(aClass);
				return this;
			},
			showBar = function() {
				clearTimeout(time_out);
				$elem.fadeIn();

				if (opt.hide_delay !== 0)
				{
					time_out = setTimeout(function() {
						hide();
					}, opt.hide_delay);
				}
				return this;
			},
			isError = function() {
				removeClass(opt.success_class);
				addClass(opt.error_class);
			},
			isSuccess = function() {
				removeClass(opt.error_class);
				addClass(opt.success_class);
			},
			hide = function () {
				clearTimeout(time_out);
				$elem.slideUp();
				return this;
			};

		// Call the init function by default
		init(elem_id, opt);

		return {
			changeText: changeText,
			addClass: addClass,
			removeClass: removeClass,
			showBar: showBar,
			isError: isError,
			isSuccess: isSuccess,
			hide: hide
		};
	});

	// AMD / RequireJS
	if (typeof define !== 'undefined' && define.amd) {
		define([], function () {
			return ElkInfoBar;
		});
	}
	// CommonJS
	else if (typeof module !== 'undefined' && module.exports) {
		module.exports = ElkInfoBar;
	}
	// included directly via <script> tag
	else {
		this.ElkInfoBar = ElkInfoBar;
	}
})();
