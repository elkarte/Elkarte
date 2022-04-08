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
		let oTxt = {},

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
				let messageId = parseInt(mId, 10),
					topicId = parseInt(tId, 10),
					subAction = '',
					check = $(e.target).attr('class');

				if (e.target.nodeName.toLowerCase() !== 'a')
				{
					return false;
				}

				// Set the subAction to what they are doing
				if (check.indexOf('unreact_button') >= 0)
				{
					if (!confirm(oTxt.are_you_sure))
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
				let values = {
					'topic': topicId,
					'msg': messageId
				};

				// Make the ajax call to the likes system
				$.ajax({
					url: elk_scripturl + '?action=likes;sa=' + subAction + ';api=json;' + elk_session_var + '=' + elk_session_id,
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
				let currentClass = (params.action === 'unlikepost') ? 'unreact_button' : 'react_button',
					nextClass = (params.action === 'unlikepost') ? 'react_button' : 'unreact_button';

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
				$("." + nextClass).SiteTooltip();
			},

			/**
			 * Show a non modal error box when something goes wrong with
			 * sending the request or processing it
			 *
			 * @param {type} params
			 */
			handleError = function (params)
			{
				new elk_Popup({
					heading: oTxt.likeHeadingError,
					content: params.data,
					icon: 'i-exclamation colorize-exclamation'
				});
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
		let currentUrlFrag = null,
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
				$('<div id="lp_preloader"><i class="icon icon-xl i-concentric"></i><div>').appendTo('#like_post_stats_overlay');
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

			// Check the url for a valid like stat tab, load data if not done yet
			checkUrl = function (url)
			{
				// Busy doing something
				showSpinnerOverlay();

				// No tab sent, use the current hash
				if (typeof (url) === 'undefined' || url === '')
				{
					let currentHref = window.location.href.split('#');

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
					url: elk_scripturl + '?action=likes;sa=likestats;area=' + params.url + ';api=json;' + elk_session_var + '=' + elk_session_id,
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
				let data = tabsVisitedCurrentSession[currentUrlFrag],
					htmlContent = '',
					messageUrl = '',
					$like_post_message_data = $('.like_post_message_data');

				// Clear anything that was in place
				$like_post_message_data.html('');

				// Build the new html to add to the page
				data.forEach((point) =>
				{
					messageUrl = elk_scripturl + '?topic=' + point.id_topic + '.msg' + point.id_msg;

					htmlContent += '' +
						'<div class="content forumposts">' +
						'   <div class="like_stats_avatar">' +
						'       <img class="avatar avatarresize" alt="" src="' + encodeURI(point.member_received.avatar) + '" />' +
						'   </div>' +
						'   <div class="like_stats_details">' +
						'       <a class="poster_details largetext" href="' + point.member_received.href + '">' + point.member_received.name + '</a>' +
						'       <div class="poster_details">' + txtStrings.totalPosts + ': ' + point.member_received.total_posts + '</div>' +
						'   </div>' +
						'   <div class="like_stats_subject largetext">' +
						'      <a class="message_title" title="' + point.preview + '" href="' + messageUrl + '">' + txtStrings.topic + ': ' + point.subject + '</a>' +
						'   </div>' +
						'   <div class="separator"></div>' +
						'   <div class="well">' +
						'       <p>' + txtStrings.usersWhoLiked.easyReplace({1: point.member_liked_data.length}) + '</p>';

					// All the members that liked this masterpiece of internet jibba jabba
					point.member_liked_data.forEach((member_liked_data) =>
					{
						htmlContent += '' +
							'   <div class="like_stats_likers">' +
							'       <a href="' + member_liked_data.href + '">' +
							'           <img class="avatar" alt="" src="' + encodeURI(member_liked_data.avatar) + '" title="' + member_liked_data.real_name + '"/>' +
							'       </a> ' +
							'   </div>';

					});

					htmlContent += '' +
						'   </div>' +
						'</div>';
				});

				// Set the category header div (below the tabs) text
				$('#like_post_current_tab_desc').text(txtStrings.mostLikedMessage);

				// Show the htmlContent we built
				$like_post_message_data.append(htmlContent).show();

				// Hover subject link to show message body preview
				$('.message_title').SiteTooltip();

				// All done with this
				hideSpinnerOverlay();
			},

			// The most liked Topics !
			showTopicStats = function ()
			{
				let data = tabsVisitedCurrentSession[currentUrlFrag],
					topicUrl = '',
					msgUrl = '',
					htmlContent = '',
					expand_txt = [],
					collapse_txt = [],
					$like_post_topic_data = $('.like_post_topic_data');

				// Clear the area
				$like_post_topic_data.html('');

				// For each of the top X topics, output the info
				data.forEach((point, index) =>
				{
					topicUrl = elk_scripturl + '?topic=' + point.id_topic;

					// Start with the topic info
					htmlContent += '' +
						'<div class="content forumposts">' +
						'   <a class="largetext" href="' + topicUrl + '">' + point.msg_data[0].subject + '</a> ' + txtStrings.mostPopularTopicHeading1.easyReplace({1: point.like_count}) +
						'   <p class="panel_toggle secondary_header">' +
						'       <span class="topic_toggle">&nbsp' +
						'           <span id="topic_toggle_img_' + index + '" class="chevricon i-chevron-up" title=""></span>' +
						'       </span>' +
						'       <a href="#" id="topic_toggle_link_' + index + '">' + txtStrings.mostPopularTopicSubHeading1.easyReplace({
							1: point.msg_data.length,
							2: txtStrings.showPosts
						}) + '</a>' +
						'   </p>' +
						'   <div id="topic_container_' + index + '" class="hide">';

					// Expand / collapse text strings for this area
					collapse_txt[index] = txtStrings.mostPopularTopicSubHeading1.easyReplace({
						1: point.msg_data.length,
						2: txtStrings.showPosts
					});
					expand_txt[index] = txtStrings.mostPopularTopicSubHeading1.easyReplace({
						1: point.msg_data.length,
						2: txtStrings.hidePosts
					});

					// Posts from the topic itself
					point.msg_data.forEach((msg_data) =>
					{
						msgUrl = topicUrl + '.msg' + msg_data.id_msg + '#msg' + msg_data.id_msg;

						htmlContent += '' +
							'   <div class="content forumposts">' +
							'       <div class="topic_details">' +
							'   	    <img class="like_stats_small_avatar" alt="" src="' + encodeURI(msg_data.member.avatar) + '"/>' +
							'           <h5 class="like_stats_likers">' +
							msg_data.member.name + ' : ' + txtStrings.postedAt + ' ' + msg_data.html_time +
							'           </h5>' +
							'       </div>' +
							'       <div class="messageContent">' + msg_data.body + '</div>' +
							'       <a class="linkbutton floatright" href="' + msgUrl + '">' + txtStrings.readMore + '</a>' +
							'       <div class="separator"></div>' +
							'   </div>';
					});

					htmlContent += '' +
						'   </div>' +
						'</div>';
				});

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
				let data = tabsVisitedCurrentSession[currentUrlFrag],
					boardUrl = elk_scripturl + '?board=' + data.id_board,
					$like_post_board_data = $('.like_post_board_data');

				// Start off clean
				$like_post_board_data.html('');

				// First a bit about the board
				let htmlContent = '' +
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
				data.topic_data.forEach((data_topic) =>
				{
					let topicUrl = elk_scripturl + '?topic=' + data_topic.id_topic;

					htmlContent += '' +
						'<div class="content forumposts">' +
						'	<div class="topic_details">' +
						'	    <img class="like_stats_small_avatar" alt="" src="' + encodeURI(data_topic.member.avatar) + '"/>' +
						'       <h5 class="like_stats_likers">' +
						'           <a href="' + data_topic.member.href + '">' +
						data_topic.member.name +
						'           </a> : ' + txtStrings.postedAt + ' ' + data_topic.html_time +
						'       </h5>' +
						'   </div>' +
						'   <div class="messageContent">' + data_topic.body + '</div>' +
						'   <a class="linkbutton floatright" href="' + topicUrl + '">' + txtStrings.readMore + '</a>' +
						'</div>';
				});

				// Load and show
				$('#like_post_current_tab_desc').text(txtStrings.mostLikedBoard);
				$like_post_board_data.html(htmlContent).show();

				// Done with ajax
				hideSpinnerOverlay();
			},

			// Data for all the narcissists out there !
			showMostLikesReceivedUserStats = function (response)
			{
				let data = tabsVisitedCurrentSession[currentUrlFrag],
					msgUrl = '',
					htmlContent = '',
					expand_txt = [],
					collapse_txt = [],
					$like_post_most_liked_user_data = $('.like_post_most_liked_user_data');

				// Clean the screen
				$like_post_most_liked_user_data.html('').off();

				// For each member returned
				data.forEach((point, index) =>
				{
					// Start off with a bit about them and why they are great
					htmlContent += '' +
						'<div class="content forumposts">' +
						'   <div class="like_stats_avatar">' +
						'       <img class="avatar avatarresize" alt="" src="' + encodeURI(point.member_received.avatar) + '" />' +
						'   </div>' +
						'   <div class="like_stats_details">' +
						'       <a class="poster_details largetext" href="' + point.member_received.href + '">' + point.member_received.name + '</a>' +
						'       <div class="poster_details">' + txtStrings.totalPosts + ': ' + point.member_received.total_posts + '</div>' +
						'       <div class="poster_details">' + txtStrings.totalLikesReceived + ': ' + point.like_count + '</div>' +
						'   </div>' +
						'   <p class="panel_toggle secondary_header">' +
						'       <span class="liked_toggle">&nbsp' +
						'           <span id="liked_toggle_img_' + index + '" class="chevricon i-chevron-up" title=""></span>' +
						'       </span>' +
						'       <a href="#" id="liked_toggle_link_' + index + '">' + txtStrings.mostPopularUserHeading1.easyReplace({1: txtStrings.showPosts}) + '</a>' +
						'   </p>' +
						'   <div id="liked_container_' + index + '" class="hide">';

					// Expand / collapse text strings for this area
					collapse_txt[index] = txtStrings.mostPopularUserHeading1.easyReplace({
						1: txtStrings.showPosts
					});
					expand_txt[index] = txtStrings.mostPopularUserHeading1.easyReplace({
						1: txtStrings.hidePosts
					});

					point.post_data.forEach((post_data) =>
					{
						msgUrl = elk_scripturl + '?topic=' + post_data.id_topic + '.msg' + post_data.id_msg;

						htmlContent += '' +
							'       <div class="content forumposts">' +
							'	        <div class="topic_details">' +
							'                <h5 class="like_stats_likers">' +
							txtStrings.postedAt + ' ' + post_data.html_time + ' - ' + post_data.like_count + ' ' + txtStrings.likesReceived +
							'               </h5>' +
							'           </div>' +
							'          <div class="messageContent">' + post_data.body + '</div>' +
							'          <a class="linkbutton floatright" href="' + msgUrl + '">' + txtStrings.readMore + '</a>' +
							'          <div class="separator"></div>' +
							'       </div>';
					});

					htmlContent += '' +
						'   </div>' +
						'</div>';
				});

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
				let data = tabsVisitedCurrentSession[currentUrlFrag],
					htmlContent = '',
					msgUrl = '',
					expand_txt = [],
					collapse_txt = [],
					$like_post_most_likes_given_user_data = $('.like_post_most_likes_given_user_data');

				// Clear the div of any previous content
				$like_post_most_likes_given_user_data.html('');

				// For each member returned
				data.forEach((point, index) =>
				{
					htmlContent += '' +
						'<div class="content forumposts">' +
						'   <div class="like_stats_avatar">' +
						'       <img class="avatar avatarresize" alt="" src="' + encodeURI(point.member_given.avatar) + '" />' +
						'   </div>' +
						'   <div class="like_stats_details">' +
						'       <a class="poster_details largetext" href="' + point.member_given.href + '">' + point.member_given.name + '</a>' +
						'       <div class="poster_details">' + txtStrings.totalPosts + ': ' + point.member_given.total_posts + '</div>' +
						'       <div class="poster_details">' + txtStrings.totalLikesGiven + ': ' + point.like_count + '</div>' +
						'   </div>' +
						'   <p class="panel_toggle secondary_header">' +
						'       <span class="liker_toggle">&nbsp' +
						'           <span id="liker_toggle_img_' + index + '" class="chevricon i-chevron-up" title=""></span>' +
						'       </span>' +
						'       <a href="#" id="liker_toggle_link_' + index + '">' + txtStrings.mostLikeGivenUserHeading1.easyReplace({1: txtStrings.showPosts}) + '</a>' +
						'   </p>' +
						'   <div id="liker_container_' + index + '" class="hide">';

					// Expand / collapse text strings for this area
					collapse_txt[index] = txtStrings.mostLikeGivenUserHeading1.easyReplace({
						1: txtStrings.showPosts
					});
					expand_txt[index] = txtStrings.mostLikeGivenUserHeading1.easyReplace({
						1: txtStrings.hidePosts
					});

					point.post_data.forEach((post_data) =>
					{
						msgUrl = elk_scripturl + '?topic=' + post_data.id_topic + '.msg' + post_data.id_msg;

						htmlContent += '' +
							'   <div class="content forumposts">' +
							'	    <div class="topic_details">' +
							'           <h5 class="like_stats_likers">' +
							txtStrings.postedAt + ' ' + post_data.html_time +
							'           </h5>' +
							'       </div>' +
							'       <div class="messageContent">' + post_data.body + '</div>' +
							'       <a class="linkbutton floatright" href="' + msgUrl + '">' + txtStrings.readMore + '</a>' +
							'   	<div class="separator"></div>' +
							'   </div>';
					});

					htmlContent += '' +
						'   </div>' +
						'</div>';
				});

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
				for (let section = 0; section < count; section++)
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
