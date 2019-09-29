/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 */

/**
 * Functions to provide ajax capability to the like / unlike button
 * Makes the appropriate call in the background and updates the button text
 * and button hover title text with the new like totals / likers
 */

/**
 * Simply invoke the constructor by calling likePosts with init method
 */
(function ()
{
	function likePosts()
	{
	}

	likePosts.prototype = function ()
	{
		var oTxt = {},

			/**
			 * Initiate likePosts with this method
			 * likePosts.prototype.init(params)
			 * currently passing the text from php
			 */
			init = function (params)
			{
				oTxt = params.oTxt;
			},

			/**
			 * This is bound to a click event on the page like/unlike buttons
			 * likePosts.prototype.likeUnlikePosts(event, messageID, topidID)
			 */
			likeUnlikePosts = function (e, mId, tId)
			{
				var messageId = parseInt(mId, 10),
					topicId = parseInt(tId, 10),
					subAction = '',
					check = $(e.target).attr('class');

				if (e.target.nodeName.toLowerCase() !== 'a')
				{
					return false;
				}

				// Set the subAction to what they are doing
				if (check.indexOf('unlike_button') >= 0)
				{
					if (!confirm(likemsg_are_you_sure))
					{
						return;
					}

					subAction = 'unlikepost';
				}
				else
				{
					subAction = 'likepost';
				}

				// Need to know what we are liking of course
				var values = {
					'topic': topicId,
					'msg': messageId
				};

				// Make the ajax call to the likes system
				$.ajax({
					url: elk_scripturl + '?action=likes;sa=' + subAction + ';xml;api=json;' + elk_session_var + '=' + elk_session_id,
					type: 'POST',
					dataType: 'json',
					data: values,
					cache: false
				})
					.done(function (resp)
					{
						// json response from the server says success?
						if (resp.result === true)
						{
							// Update the page with the new likes information
							updateUi({
								'elem': $(e.target),
								'count': resp.count,
								'text': resp.text,
								'title': resp.title,
								'action': subAction
							});
						}
						// Some failure trying to process the request
						else
						{
							handleError(resp);
						}
					})
					.fail(function (err, textStatus, errorThrown)
					{
						// Some failure sending the request, this generally means some html in
						// the output from php error or access denied fatal errors etc
						err.data = oTxt.error_occurred + ' : ' + errorThrown;
						handleError(err);
					});
			},

			/**
			 * Does the actual update to the page the user is viewing
			 *
			 * @param {object} params object of new values from the ajax request
			 */
			updateUi = function (params)
			{
				var currentClass = (params.action === 'unlikepost') ? 'unlike_button' : 'like_button',
					nextClass = (params.action === 'unlikepost') ? 'like_button' : 'unlike_button';

				// Swap the button class as needed, update the text for the hover
				$(params.elem).removeClass(currentClass).addClass(nextClass);
				$(params.elem).html('&nbsp;' + params.text);
				$(params.elem).attr('title', params.title);

				// Update the count bubble if needed
				if (params.count !== 0)
				{
					$(params.elem).html('<span class="likes_indicator">' + params.count + '</span>&nbsp;' + params.text);
				}

				// Changed the title text, update the tooltips
				$("." + nextClass).SiteTooltip({
					hoverIntent: {
						sensitivity: 10,
						interval: 150,
						timeout: 50
					}
				});
			},

			/**
			 * Show a non modal error box when something goes wrong with
			 * sending the request or processing it
			 *
			 * @param {type} params
			 */
			handleError = function (params)
			{
				var str = '<div class="floating_error"><div class="error_heading">' + oTxt.likeHeadingError + '</div><p class="error_msg">' + params.data + '</p><p class="error_btn">' + oTxt.btnText + '</p></div>';

				$('body').append(str);

				var screenWidth = $(window).width(),
					screenHeight = $(window).height(),
					$floating_error = $('.floating_error'),
					popupHeight = $floating_error.outerHeight(),
					popupWidth = $floating_error.outerWidth(),
					topPopUpOffset = (screenHeight - popupHeight) / 2,
					leftPopUpOffset = (screenWidth - popupWidth) / 2;

				// Center the error popup on the screen
				$floating_error.css({
					top: topPopUpOffset + 'px',
					left: leftPopUpOffset + 'px'
				});

				$(document).one('click keyup', removeOverlay);
			},

			/**
			 * Clear the error box from the screen by click or escape key
			 *
			 * @param {type} e
			 */
			removeOverlay = function (e)
			{
				if (typeof (e) === 'undefined')
				{
					return false;
				}
				else if ((e.type === 'keyup' && e.keyCode === 27) || e.type === 'click')
				{
					var $floating_error = $('.floating_error');
					$floating_error.remove();
					$floating_error.off('click');
					$(document).off('click', removeOverlay);
					$(document).off('keyup', removeOverlay);
				}
			};

		return {
			init: init,
			likeUnlikePosts: likeUnlikePosts
		};
	}();

	// instead of this, we can use namespace too
	this.likePosts = likePosts;

	/**
	 * Class for like posts stats
	 *
	 * Simply invoke the constructor by calling likePostStats with init method
	 */
	function likePostStats()
	{
	}

	likePostStats.prototype = function ()
	{
		var currentUrlFrag = null,
			allowedUrls = {},
			tabsVisitedCurrentSession = {},
			defaultHash = 'messagestats',
			txtStrings = {},

			// Initialize, load in text strings, etc
			init = function (params)
			{
				txtStrings = $.extend({}, params.txtStrings);
				allowedUrls = {
					'messagestats': {
						'uiFunc': showMessageStats
					},
					'topicstats': {
						'uiFunc': showTopicStats
					},
					'boardstats': {
						'uiFunc': showBoardStats
					},
					'mostlikesreceiveduserstats': {
						'uiFunc': showMostLikesReceivedUserStats
					},
					'mostlikesgivenuserstats': {
						'uiFunc': showMostLikesGivenUserStats
					}
				};

				checkUrl();
			},

			// Show the ajax spinner
			showSpinnerOverlay = function ()
			{
				$('<div id="lp_preloader"><i class="icon icon-spin icon-xl i-spinner"></i><div>').appendTo('#like_post_stats_overlay');
				$('#like_post_stats_overlay').show();
			},

			// Hide the ajax spinner
			hideSpinnerOverlay = function ()
			{
				$('#lp_preloader').remove();
				$('#like_post_stats_overlay').hide();
			},

			// Set the active stats tab
			highlightActiveTab = function ()
			{
				$('.like_post_stats_menu a').removeClass('active');
				$('#' + currentUrlFrag).addClass('active');
			},

			// Check the url for a valid like stat tab, load data if its not been done yet
			checkUrl = function (url)
			{
				// Busy doing something
				showSpinnerOverlay();

				// No tab sent, use the current hash
				if (typeof (url) === 'undefined' || url === '')
				{
					var currentHref = window.location.href.split('#');

					currentUrlFrag = (typeof (currentHref[1]) !== 'undefined') ? currentHref[1] : defaultHash;
				}
				else
				{
					currentUrlFrag = url;
				}

				// Ensure this is a valid hash value for our like tabs, otherwise set a default
				if (allowedUrls.hasOwnProperty(currentUrlFrag) === false)
				{
					currentUrlFrag = defaultHash;
				}

				// Hide whatever is there
				$('.like_post_stats_data').children().hide();
				highlightActiveTab();

				// If we have not loaded this tabs data, then fetch it
				if (tabsVisitedCurrentSession.hasOwnProperty(currentUrlFrag) === false)
				{
					getDataFromServer({
						'url': currentUrlFrag,
						'uiFunc': allowedUrls[currentUrlFrag].uiFunc
					});
				}
				else
				{
					allowedUrls[currentUrlFrag].uiFunc();
				}
			},

			// Fetch the specific tab data via ajax from the server
			getDataFromServer = function (params)
			{
				$('.like_post_stats_error').hide().html('');

				// Make the ajax call to the likes system
				$.ajax({
					url: elk_scripturl + '?action=likes;sa=likestats;area=' + params.url + ';xml;api=json;',
					type: 'POST',
					dataType: 'json',
					cache: false
				})
					.done(function (resp)
					{
						if (typeof (resp.error) !== 'undefined' && resp.error !== '')
						{
							genericErrorMessage({
								errorMsg: resp.error
							});
						}
						else if (typeof (resp.data) !== 'undefined' && typeof (resp.data.noDataMessage) !== 'undefined' && resp.data.noDataMessage !== '')
						{
							genericErrorMessage({
								errorMsg: resp.data.noDataMessage
							});
						}
						else if (resp.result === true)
						{
							tabsVisitedCurrentSession[currentUrlFrag] = resp.data;
							params.uiFunc();
						}
						else
						{
							genericErrorMessage(resp);
						}
					})
					.fail(function (err, textStatus, errorThrown)
					{
						// Some failure sending the request, this generally means some html in
						// the output from php error or access denied fatal errors etc
						// err.data = oTxt.error_occurred + ' : ' + errorThrown;
						// handleError(err);
						if ('console' in window)
						{
							window.console.info('fail:', textStatus, errorThrown.name);
							window.console.info(err.responseText);
						}
					})
					.always(function ()
					{
						// All done
						hideSpinnerOverlay();
					});
			},

			// Show the most liked messages
			showMessageStats = function ()
			{
				var data = tabsVisitedCurrentSession[currentUrlFrag],
					htmlContent = '',
					messageUrl = '',
					$like_post_message_data = $('.like_post_message_data');

				// Clear anything that was in place
				$like_post_message_data.html('');

				// Build the new html to add to the page
				for (var i = 0, len = data.length; i < len; i++)
				{
					messageUrl = elk_scripturl + '?topic=' + data[i].id_topic + '.msg' + data[i].id_msg;

					htmlContent += '' +
						'<div class="content forumposts">' +
						'   <div class="like_stats_avatar">' +
						'       <img class="avatar avatarresize" alt="" src="' + encodeURI(data[i].member_received.avatar) + '" />' +
						'   </div>' +
						'   <div class="like_stats_details">' +
						'       <a class="poster_details largetext" href="' + data[i].member_received.href + '">' + data[i].member_received.name + '</a>' +
						'       <div class="poster_details">' + txtStrings.totalPosts + ': ' + data[i].member_received.total_posts + '</div>' +
						'   </div>' +
						'   <div class="like_stats_subject largetext">' +
						'      <a class="message_title" title="' + data[i].preview + '" href="' + messageUrl + '">' + txtStrings.topic + ': ' + data[i].subject + '</a>' +
						'   </div>' +
						'   <div class="separator"></div>' +
						'   <div class="well">' +
						'       <p>' + txtStrings.usersWhoLiked.easyReplace({1: data[i].member_liked_data.length}) + '</p>';

					// All the members that liked this masterpiece of internet jibba jabba
					for (var j = 0, likerslen = data[i].member_liked_data.length; j < likerslen; j++)
					{
						htmlContent += '' +
							'   <div class="like_stats_likers">' +
							'       <a href="' + data[i].member_liked_data[j].href + '">' +
							'           <img class="avatar" alt="" src="' + encodeURI(data[i].member_liked_data[j].avatar) + '" title="' + data[i].member_liked_data[j].real_name + '"/>' +
							'       </a> ' +
							'   </div>';

					}

					htmlContent += '' +
						'   </div>' +
						'</div>';
				}

				// Set the category header div (below the tabs) text
				$('#like_post_current_tab_desc').text(txtStrings.mostLikedMessage);

				// Show the htmlContent we built
				$like_post_message_data.append(htmlContent).show();

				// Hover subject link to show message body preview
				$('.message_title').SiteTooltip({hoverIntent: {sensitivity: 10, interval: 500, timeout: 50}});

				// All done with this
				hideSpinnerOverlay();
			},

			// The most liked Topics !
			showTopicStats = function ()
			{
				var data = tabsVisitedCurrentSession[currentUrlFrag],
					topicUrl = '',
					msgUrl = '',
					htmlContent = '',
					expand_txt = [],
					collapse_txt = [],
					$like_post_topic_data = $('.like_post_topic_data');

				// Clear the area
				$like_post_topic_data.html('');

				// For each of the top X topics, output the info
				for (var i = 0, len = data.length; i < len; i++)
				{
					topicUrl = elk_scripturl + '?topic=' + data[i].id_topic;

					// Start with the topic info
					htmlContent += '' +
						'<div class="content forumposts">' +
						'   <a class="largetext" href="' + topicUrl + '">' + data[i].msg_data[0].subject + '</a> ' + txtStrings.mostPopularTopicHeading1.easyReplace({1: data[i].like_count}) +
						'   <p class="panel_toggle secondary_header">' +
						'       <span class="topic_toggle">&nbsp' +
						'           <span id="topic_toggle_img_' + i + '" class="chevricon i-chevron-up" title=""></span>' +
						'       </span>' +
						'       <a href="#" id="topic_toggle_link_' + i + '">' + txtStrings.mostPopularTopicSubHeading1.easyReplace({
							1: data[i].msg_data.length,
							2: txtStrings.showPosts
						}) + '</a>' +
						'   </p>' +
						'   <div id="topic_container_' + i + '" class="hide">';

					// Expand / collapse text strings for this area
					collapse_txt[i] = txtStrings.mostPopularTopicSubHeading1.easyReplace({
						1: data[i].msg_data.length,
						2: txtStrings.showPosts
					});
					expand_txt[i] = txtStrings.mostPopularTopicSubHeading1.easyReplace({
						1: data[i].msg_data.length,
						2: txtStrings.hidePosts
					});

					// Posts from the topic itself
					for (var j = 0, topiclen = data[i].msg_data.length; j < topiclen; j++)
					{
						msgUrl = topicUrl + '.msg' + data[i].msg_data[j].id_msg + '#msg' + data[i].msg_data[j].id_msg;

						htmlContent += '' +
							'   <div class="content forumposts">' +
							'       <div class="topic_details">' +
							'   	    <img class="like_stats_small_avatar" alt="" src="' + encodeURI(data[i].msg_data[j].member.avatar) + '"/>' +
							'           <h5 class="like_stats_likers">' +
							data[i].msg_data[j].member.name + ' : ' + txtStrings.postedAt + ' ' + data[i].msg_data[j].html_time +
							'           </h5>' +
							'       </div>' +
							'       <div class="inner">' + data[i].msg_data[j].body + '</div>' +
							'       <a class="linkbutton_right" href="' + msgUrl + '">' + txtStrings.readMore + '</a>' +
							'       <div class="separator"></div>' +
							'   </div>';
					}

					htmlContent += '' +
						'   </div>' +
						'</div>';
				}

				// Load and show the content
				$('#like_post_current_tab_desc').text(txtStrings.mostLikedTopic);
				$like_post_topic_data.html(htmlContent).show();

				// Add in the toggle functions
				createCollapsibleContent(data.length, expand_txt, collapse_txt, 'topic');

				// All done with this request
				hideSpinnerOverlay();
			},

			// The single most liked board, like ever
			showBoardStats = function (response)
			{
				var data = tabsVisitedCurrentSession[currentUrlFrag],
					boardUrl = elk_scripturl + '?board=' + data.id_board,
					$like_post_board_data = $('.like_post_board_data');

				// Start off clean
				$like_post_board_data.html('');

				// First a bit about the board
				var htmlContent = '' +
					'<div class="content forumposts">' +
					'	<p>' +
					'       <a class="largetext" href="' + boardUrl + '">' + data.name + '</a> ' + txtStrings.mostPopularBoardHeading1 + ' ' + data.like_count + ' ' + txtStrings.genricHeading1 +
					'   </p>' +
					'   <p>' +
					txtStrings.mostPopularBoardSubHeading1 + ' ' + data.num_topics + ' ' + txtStrings.mostPopularBoardSubHeading2 + ' ' + data.topics_liked + ' ' + txtStrings.mostPopularBoardSubHeading3 +
					'   </p>' +
					'   <p>' +
					txtStrings.mostPopularBoardSubHeading4 + ' ' + data.num_posts + ' ' + txtStrings.mostPopularBoardSubHeading5 + ' ' + data.msgs_liked + ' ' + txtStrings.mostPopularBoardSubHeading6 +
					'   </p>' +
					'</div>';

				// And show some of the topics from it
				for (var i = 0, len = data.topic_data.length; i < len; i++)
				{
					var topicUrl = elk_scripturl + '?topic=' + data.topic_data[i].id_topic;

					htmlContent += '' +
						'<div class="content forumposts">' +
						'	<div class="topic_details">' +
						'	    <img class="like_stats_small_avatar" alt="" src="' + encodeURI(data.topic_data[i].member.avatar) + '"/>' +
						'       <h5 class="like_stats_likers">' +
						'           <a href="' + data.topic_data[i].member.href + '">' +
						data.topic_data[i].member.name +
						'           </a> : ' + txtStrings.postedAt + ' ' + data.topic_data[i].html_time +
						'       </h5>' +
						'   </div>' +
						'   <div class="inner">' + data.topic_data[i].body + '</div>' +
						'   <a class="linkbutton floatright" href="' + topicUrl + '">' + txtStrings.readMore + '</a>' +
						'</div>';
				}

				// Load and show
				$('#like_post_current_tab_desc').text(txtStrings.mostLikedBoard);
				$like_post_board_data.html(htmlContent).show();

				// Done with ajax
				hideSpinnerOverlay();
			},

			// Data for all the narcissists out there !
			showMostLikesReceivedUserStats = function (response)
			{
				var data = tabsVisitedCurrentSession[currentUrlFrag],
					msgUrl = '',
					htmlContent = '',
					expand_txt = [],
					collapse_txt = [],
					$like_post_most_liked_user_data = $('.like_post_most_liked_user_data');

				// Clean the screen
				$like_post_most_liked_user_data.html('').off();

				// For each member returned
				for (var i = 0, len = data.length; i < len; i++)
				{
					// Start off with a bit about them and why they are great
					htmlContent += '' +
						'<div class="content forumposts">' +
						'   <div class="like_stats_avatar">' +
						'       <img class="avatar avatarresize" alt="" src="' + encodeURI(data[i].member_received.avatar) + '" />' +
						'   </div>' +
						'   <div class="like_stats_details">' +
						'       <a class="poster_details largetext" href="' + data[i].member_received.href + '">' + data[i].member_received.name + '</a>' +
						'       <div class="poster_details">' + txtStrings.totalPosts + ': ' + data[i].member_received.total_posts + '</div>' +
						'       <div class="poster_details">' + txtStrings.totalLikesReceived + ': ' + data[i].like_count + '</div>' +
						'   </div>' +
						'   <p class="panel_toggle secondary_header">' +
						'       <span class="liked_toggle">&nbsp' +
						'           <span id="liked_toggle_img_' + i + '" class="chevricon i-chevron-up" title=""></span>' +
						'       </span>' +
						'       <a href="#" id="liked_toggle_link_' + i + '">' + txtStrings.mostPopularUserHeading1.easyReplace({1: txtStrings.showPosts}) + '</a>' +
						'   </p>' +
						'   <div id="liked_container_' + i + '" class="hide">';

					// Expand / collapse text strings for this area
					collapse_txt[i] = txtStrings.mostPopularUserHeading1.easyReplace({
						1: txtStrings.showPosts
					});
					expand_txt[i] = txtStrings.mostPopularUserHeading1.easyReplace({
						1: txtStrings.hidePosts
					});

					for (var j = 0, msglen = data[i].post_data.length; j < msglen; j++)
					{
						msgUrl = elk_scripturl + '?topic=' + data[i].post_data[j].id_topic + '.msg' + data[i].post_data[j].id_msg;

						htmlContent += '' +
							'       <div class="content forumposts">' +
							'	        <div class="topic_details">' +
							'                <h5 class="like_stats_likers">' +
							txtStrings.postedAt + ' ' + data[i].post_data[j].html_time + ' - ' + data[i].post_data[j].like_count + ' ' + txtStrings.likesReceived +
							'               </h5>' +
							'           </div>' +
							'          <div class="inner">' + data[i].post_data[j].body + '</div>' +
							'          <a class="linkbutton_right" href="' + msgUrl + '">' + txtStrings.readMore + '</a>' +
							'          <div class="separator"></div>' +
							'       </div>';
					}

					htmlContent += '' +
						'   </div>' +
						'</div>';
				}

				// Load the template with the data
				$('#like_post_current_tab_desc').text(txtStrings.mostLikedMember);
				$like_post_most_liked_user_data.html(htmlContent).show();

				// Add in the toggle functions
				createCollapsibleContent(data.length, expand_txt, collapse_txt, 'liked');

				// Show we are done
				hideSpinnerOverlay();
			},

			// Data for all the +1, me too, etc users as well
			showMostLikesGivenUserStats = function (response)
			{
				var data = tabsVisitedCurrentSession[currentUrlFrag],
					htmlContent = '',
					msgUrl = '',
					expand_txt = [],
					collapse_txt = [],
					$like_post_most_likes_given_user_data = $('.like_post_most_likes_given_user_data');

				// Clear the div of any previous content
				$like_post_most_likes_given_user_data.html('');

				// For each member returned
				for (var i = 0, len = data.length; i < len; i++)
				{
					htmlContent += '' +
						'<div class="content forumposts">' +
						'   <div class="like_stats_avatar">' +
						'       <img class="avatar avatarresize" alt="" src="' + encodeURI(data[i].member_given.avatar) + '" />' +
						'   </div>' +
						'   <div class="like_stats_details">' +
						'       <a class="poster_details largetext" href="' + data[i].member_given.href + '">' + data[i].member_given.name + '</a>' +
						'       <div class="poster_details">' + txtStrings.totalPosts + ': ' + data[i].member_given.total_posts + '</div>' +
						'       <div class="poster_details">' + txtStrings.totalLikesGiven + ': ' + data[i].like_count + '</div>' +
						'   </div>' +
						'   <p class="panel_toggle secondary_header">' +
						'       <span class="liker_toggle">&nbsp' +
						'           <span id="liker_toggle_img_' + i + '" class="chevricon i-chevron-up" title=""></span>' +
						'       </span>' +
						'       <a href="#" id="liker_toggle_link_' + i + '">' + txtStrings.mostLikeGivenUserHeading1.easyReplace({1: txtStrings.showPosts}) + '</a>' +
						'   </p>' +
						'   <div id="liker_container_' + i + '" class="hide">';

					// Expand / collapse text strings for this area
					collapse_txt[i] = txtStrings.mostLikeGivenUserHeading1.easyReplace({
						1: txtStrings.showPosts
					});
					expand_txt[i] = txtStrings.mostLikeGivenUserHeading1.easyReplace({
						1: txtStrings.hidePosts
					});

					for (var j = 0, postlen = data[i].post_data.length; j < postlen; j++)
					{
						msgUrl = elk_scripturl + '?topic=' + data[i].post_data[j].id_topic + '.msg' + data[i].post_data[j].id_msg;

						htmlContent += '' +
							'   <div class="content forumposts">' +
							'	    <div class="topic_details">' +
							'           <h5 class="like_stats_likers">' +
							txtStrings.postedAt + ' ' + data[i].post_data[j].html_time +
							'           </h5>' +
							'       </div>' +
							'       <div class="inner">' + data[i].post_data[j].body + '</div>' +
							'       <a class="linkbutton_right" href="' + msgUrl + '">' + txtStrings.readMore + '</a>' +
							'   	<div class="separator"></div>' +
							'   </div>';
					}

					htmlContent += '' +
						'   </div>' +
						'</div>';
				}

				// Load it to the page
				$('#like_post_current_tab_desc').text(txtStrings.mostLikeGivingMember);
				$like_post_most_likes_given_user_data.html(htmlContent).show();

				// Add in the toggle functions
				createCollapsibleContent(data.length, expand_txt, collapse_txt, 'liker');

				// Done!
				hideSpinnerOverlay();
			},

			// Attach the toggle class to each hidden div
			createCollapsibleContent = function (count, expand_txt, collapse_txt, prefix)
			{
				for (var section = 0; section < count; section++)
				{
					new elk_Toggle({
						bToggleEnabled: true,
						bCurrentlyCollapsed: true,
						aSwappableContainers: [
							prefix + '_container_' + section
						],
						aSwapClasses: [
							{
								sId: prefix + '_toggle_img_' + section,
								classExpanded: 'chevricon i-chevron-up',
								titleExpanded: 'Hide',
								classCollapsed: 'chevricon i-chevron-down',
								titleCollapsed: 'Show'
							}
						],
						aSwapLinks: [
							{
								sId: prefix + '_toggle_link_' + section,
								msgExpanded: expand_txt[section],
								msgCollapsed: collapse_txt[section]
							}
						]
					});
				}
			},

			genericErrorMessage = function (params)
			{
				$('.like_post_stats_error').html(params.errorMsg).show();
				hideSpinnerOverlay();
			};

		return {
			init: init,
			checkUrl: checkUrl
		};
	}();

	this.likePostStats = likePostStats;

	// Setup the menu to act as ajax tabs
	$(function ()
	{
		$(".like_post_stats_menu a").on("click", function (e)
		{
			if (e)
			{
				e.preventDefault();
				e.stopPropagation();
			}
			likePostStats.prototype.checkUrl(this.id);
		});
	});
}());
