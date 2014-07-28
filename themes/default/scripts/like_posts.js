/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Release Candidate 2
 *
 * Functions to provide ajax capability to the like / unlike button
 * Makes the appropriate call in the background and updates the button text
 * and button hover title text with the new like totals / likers
 */

/**
 * Simply invoke the constructor by calling likePosts with init method
 */
(function() {
	function likePosts() {}

	likePosts.prototype = function() {
		var oTxt = {},

			/**
			 * Initiate likePosts with this method
			 * likePosts.prototype.init(params)
			 * currently passing the text from php
			 */
			init = function(params) {
				oTxt = params.oTxt;
			},

			/**
			 * This is bound to a click event on the page like/unlike buttons
			 * likePosts.prototype.likeUnlikePosts(event, messageID, topidID)
			 */
			likeUnlikePosts = function(e, mId, tId) {
				var messageId = parseInt(mId, 10),
					topicId = parseInt(tId, 10),
					subAction = '',
					check = $(e.target).attr('class');

				if (e.target.nodeName.toLowerCase() !== 'a')
					return false;

				// Set the subAction to what they are doing
				if (check.indexOf('unlike_button') >= 0)
					subAction = 'unlikepost';
				else
					subAction = 'likepost';

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
					.done(function(resp) {
						// json response from the server says success?
						if (resp.result === true) {
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
							handleError(resp);
					})
					.fail(function(err, textStatus, errorThrown) {
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
			updateUi = function(params) {
				var currentClass = (params.action === 'unlikepost') ? 'unlike_button' : 'like_button',
					nextClass = (params.action === 'unlikepost') ? 'like_button' : 'unlike_button';

				// Swap the button class as needed, update the text for the hover
				$(params.elem).removeClass(currentClass).addClass(nextClass);
				$(params.elem).html('&nbsp;' + params.text);
				$(params.elem).attr('title', params.title);

				// Update the count bubble if needed
				if (params.count !== 0)
					$(params.elem).html('<span class="likes_indicator">' + params.count + '</span>&nbsp;' + params.text);

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
			handleError = function(params) {
				var str = '<div class="floating_error"><div class="error_heading">' + oTxt.likeHeadingError + '</div><p class="error_msg">' + params.data + '</p><p class="error_btn">' + oTxt.btnText + '</p></div>';
				$('body').append(str);

				var screenWidth = $(window).width(),
					screenHeight = $(window).height(),
					popupHeight = $('.floating_error').outerHeight(),
					popupWidth = $('.floating_error').outerWidth(),
					topPopUpOffset = (screenHeight - popupHeight) / 2,
					leftPopUpOffset = (screenWidth - popupWidth) / 2;

				// Center the error popup on the screen
				$('.floating_error').css({
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
			removeOverlay = function(e) {
				if (typeof(e) === 'undefined')
					return false;
				else if ((e.type === 'keyup' && e.keyCode === 27) || e.type === 'click') {
					$('.floating_error').remove();
					$('.floating_error').unbind('click');
					$(document).unbind('click', removeOverlay);
					$(document).unbind('keyup', removeOverlay);
				}
			};

		return {
			init: init,
			likeUnlikePosts: likeUnlikePosts
		};
	}();

	// instead of this, we can use namespace too
	this.likePosts = likePosts;



	// Class for like posts stats

	function likePostStats() {}

	likePostStats.prototype = function() {
		var currentUrlFrag = null,
			allowedUrls = {},
			tabsVisitedCurrentSession = {},
			defaultHash = 'messagestats',
			txtStrings = {},

			init = function(params) {
				txtStrings = $.extend({}, params.txtStrings);
				if (params.onError === "") {
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
				}
			},

			showSpinnerOverlay = function() {
				$('#like_post_stats_overlay').show();
				$('#lp_preloader').show();
			},

			hideSpinnerOverlay = function() {
				$('#lp_preloader').hide();
				$('#like_post_stats_overlay').hide();
			},

			highlightActiveTab = function() {
				$('.like_post_stats_menu a').removeClass('active');
				$('.like_post_stats_menu #' + currentUrlFrag).addClass('active');
			},

			checkUrl = function(url) {
				showSpinnerOverlay();

				$(".message_title").off('mouseenter mousemove mouseout');
				if (typeof(url) === 'undefined' || url === '') {
					var currentHref = window.location.href.split('#');
					currentUrlFrag = (typeof(currentHref[1]) !== 'undefined') ? currentHref[1] : defaultHash;
				} else {
					currentUrlFrag = url;
				}

				if (allowedUrls.hasOwnProperty(currentUrlFrag) === false) {
					currentUrlFrag = defaultHash;
				}

				$('.like_post_stats_data').children().hide();
				highlightActiveTab();
				if (tabsVisitedCurrentSession.hasOwnProperty(currentUrlFrag) === false) {
					getDataFromServer({
						'url': currentUrlFrag,
						'uiFunc': allowedUrls[currentUrlFrag].uiFunc
					});
				} else {
					allowedUrls[currentUrlFrag].uiFunc();
				}
			},

			getDataFromServer = function(params) {
				$('.like_post_stats_error').hide().html('');


				// Make the ajax call to the likes system
				$.ajax({
					url: elk_scripturl + '?action=likes;sa=likestats;area=test;xml;api=json;',
					type: 'POST',
					dataType: 'json',
					// data: values,
					cache: false
				}).done(function(resp) {
					console.log('done');
					console.log(resp);
					// json response from the server says success?
					// if (resp.result === true) {
					// 	// Update the page with the new likes information
					// 	updateUi({
					// 		'elem': $(e.target),
					// 		'count': resp.count,
					// 		'text': resp.text,
					// 		'title': resp.title,
					// 		'action': subAction
					// 	});
					// }
					// // Some failure trying to process the request
					// else
					// 	handleError(resp);
				}).fail(function(err, textStatus, errorThrown) {
					// Some failure sending the request, this generally means some html in
					// the output from php error or access denied fatal errors etc
					// err.data = oTxt.error_occurred + ' : ' + errorThrown;
					// handleError(err);
					console.log('fail');
					console.log(err, textStatus, errorThrown);
				});

				// $.ajax({
				// 	type: "POST",
				// 	url: smf_scripturl + '?action=likepostsstats',
				// 	context: document.body,
				// 	dataType: "json",
				// 	data: {
				// 		'area': 'ajaxdata',
				// 		'sa': params.url
				// 	},
				// 	success: function(resp) {
				// 		if (typeof(resp.error) !== 'undefined' && resp.error !== '') {
				// 			genericErrorMessage({
				// 				errorMsg: resp.error
				// 			});
				// 		} else if (typeof(resp.data) !== 'undefined' && typeof(resp.data.noDataMessage) !== 'undefined' && resp.data.noDataMessage !== '') {
				// 			genericErrorMessage({
				// 				errorMsg: resp.data.noDataMessage
				// 			});
				// 		} else if (resp.response) {
				// 			tabsVisitedCurrentSession[currentUrlFrag] = resp.data;
				// 			params.uiFunc();
				// 		} else {

				// 		}
				// 	}
				// });
			},

			showMessageStats = function() {
				var data = tabsVisitedCurrentSession[currentUrlFrag],
					htmlContent = '',
					messageUrl = smf_scripturl + '?topic=' + data.id_topic + '.msg' + data.id_msg;

				$('.like_post_message_data').html('');
				htmlContent += '<a class="message_title" href="' + messageUrl + '">' + txtStrings.topic + ': ' + data.subject + '</a>' + '<span class="display_none">' + data.body + '</span>';

				htmlContent += '<div class="poster_avatar"><div class="avatar" style="background-image: url(' + encodeURI(data.member_received.avatar) + ')"></div></div>' + '<div class="poster_data">' + '<a class="poster_details" href="' + data.member_received.href + '" style="font-size: 20px;">' + data.member_received.name + '</a>' + '<div class="poster_details">' + txtStrings.totalPosts + ': ' + data.member_received.total_posts + '</div>' + '</div>';

				htmlContent += '<div class="users_liked">';
				htmlContent += '<p class="title">' + data.member_liked_data.length + ' ' + txtStrings.usersWhoLiked + '</p>';
				for (var i = 0, len = data.member_liked_data.length; i < len; i++) {
					htmlContent += '<a class="poster_details" href="' + data.member_liked_data[i].href + '"><div class="poster_avatar" style="background-image: url(' + encodeURI(data.member_liked_data[i].avatar) + ')" title="' + data.member_liked_data[i].real_name + '"></div></a>';
				}
				htmlContent += '</div>';

				$('#like_post_current_tab').text(txtStrings.mostLikedMessage);
				$('.like_post_message_data').append(htmlContent).show();
				$(".message_title").on('mouseenter', function(e) {
					e.preventDefault();
					var currText = $(this).next().html();

					$("<div class=\'subject_details\'></div>").html(currText).appendTo("body").fadeIn("slow");
				}).on('mouseout', function() {
					$(".subject_details").fadeOut("slow");
					$(".subject_details").remove();
				}).on('mousemove', function(e) {
					var mousex = e.pageX + 20,
						mousey = e.pageY + 10,
						width = $("#wrapper").width() - mousex - 50;

					$(".subject_details").css({
						top: mousey,
						left: mousex,
						width: width + "px"
					});
				});
				hideSpinnerOverlay();
			},

			showTopicStats = function() {
				var data = tabsVisitedCurrentSession[currentUrlFrag],
					htmlContent = '',
					topicUrl = smf_scripturl + '?topic=' + data.id_topic;

				$('.like_post_topic_data').html('');
				htmlContent += '<a class="topic_title" href="' + topicUrl + '">' + txtStrings.mostPopularTopicHeading1 + ' ' + data.like_count + ' ' + txtStrings.genricHeading1 + '</a>';
				htmlContent += '<p class="topic_info">' + txtStrings.mostPopularTopicSubHeading1 + ' ' + data.msg_data.length + ' ' + txtStrings.mostPopularTopicSubHeading2 + '</p>';

				for (var i = 0, len = data.msg_data.length; i < len; i++) {
					var msgUrl = topicUrl + '.msg' + data.msg_data[i].id_msg;

					htmlContent += '<div class="message_body">' + '<div class="posted_at">' + data.msg_data[i].member.name + ' : ' + txtStrings.postedAt + ' ' + data.msg_data[i].poster_time + '</div> ' + '<a class="poster_details" href="' + data.msg_data[i].member.href + '"><div class="poster_avatar" style="background-image: url(' + encodeURI(data.msg_data[i].member.avatar) + ')"></div></a><div class="content_encapsulate">' + data.msg_data[i].body + '</div><a class="read_more" href="' + msgUrl + '">' + txtStrings.readMore + '</a>' + '</div>';
				}
				$('#like_post_current_tab').text(txtStrings.mostLikedTopic);
				$('.like_post_topic_data').html(htmlContent).show();
				hideSpinnerOverlay();
			},

			showBoardStats = function(response) {
				var data = tabsVisitedCurrentSession[currentUrlFrag],
					htmlContent = '',
					boardUrl = smf_scripturl + '?board=' + data.id_board;

				$('.like_post_board_data').html('');
				htmlContent += '<a class="board_title" href="' + boardUrl + '">' + data.name + ' ' + txtStrings.mostPopularBoardHeading1 + ' ' + data.like_count + ' ' + txtStrings.genricHeading1 + '</a>';
				htmlContent += '<p class="board_info">' + txtStrings.mostPopularBoardSubHeading1 + ' ' + data.num_topics + ' ' + txtStrings.mostPopularBoardSubHeading2 + ' ' + data.topics_liked + ' ' + txtStrings.mostPopularBoardSubHeading3 + '</p>';
				htmlContent += '<p class="board_info" style="margin: 5px 0 20px;">' + txtStrings.mostPopularBoardSubHeading4 + ' ' + data.num_posts + ' ' + txtStrings.mostPopularBoardSubHeading5 + ' ' + data.msgs_liked + ' ' + txtStrings.mostPopularBoardSubHeading6 + '</p>';

				for (var i = 0, len = data.topic_data.length; i < len; i++) {
					var topicUrl = smf_scripturl + '?topic=' + data.topic_data[i].id_topic;

					htmlContent += '<div class="message_body">' + '<div class="posted_at">' + data.topic_data[i].member.name + ' : ' + txtStrings.postedAt + ' ' + data.topic_data[i].poster_time + '</div> ' + '<a class="poster_details" href="' + data.topic_data[i].member.href + '"><div class="poster_avatar" style="background-image: url(' + encodeURI(data.topic_data[i].member.avatar) + ')"></div></a><div class="content_encapsulate">' + data.topic_data[i].body + '</div><a class="read_more" href="' + topicUrl + '">' + txtStrings.readMore + '</a></div>';
				}
				$('#like_post_current_tab').text(txtStrings.mostLikedBoard);
				$('.like_post_board_data').html(htmlContent).show();
				hideSpinnerOverlay();
			},

			showMostLikesReceivedUserStats = function(response) {
				var data = tabsVisitedCurrentSession[currentUrlFrag],
					htmlContent = '';

				$('.like_post_most_liked_user_data').html('');
				htmlContent += '<div class="poster_avatar"><div class="avatar" style="background-image: url(' + encodeURI(data.member_received.avatar) + ')"></div></div>' + '<div class="poster_data">' + '<a class="poster_details" href="' + data.member_received.href + '" style="font-size: 20px;">' + data.member_received.name + '</a>' + '<div class="poster_details">' + txtStrings.totalPosts + ': ' + data.member_received.total_posts + '</div>' + '<div class="poster_details">' + txtStrings.totalLikesReceived + ': ' + data.like_count + '</div>' + '</div>';

				htmlContent += '<p class="generic_text">' + txtStrings.mostPopularUserHeading1 + '</p>';
				for (var i = 0, len = data.topic_data.length; i < len; i++) {
					var msgUrl = smf_scripturl + '?topic=' + data.topic_data[i].id_topic + '.msg' + data.topic_data[i].id_msg;

					htmlContent += '<div class="message_body">' + '<div class="posted_at">' + txtStrings.postedAt + ' ' + data.topic_data[i].poster_time + ': ' + txtStrings.likesReceived + ' (' + data.topic_data[i].like_count + ')</div><div class="content_encapsulate">' + data.topic_data[i].body + '</div><a class="read_more" href="' + msgUrl + '">' + txtStrings.readMore + '</a></div>';
				}
				$('#like_post_current_tab').text(txtStrings.mostLikedMember);
				$('.like_post_most_liked_user_data').html(htmlContent).show();
				hideSpinnerOverlay();
			},

			showMostLikesGivenUserStats = function(response) {
				var data = tabsVisitedCurrentSession[currentUrlFrag],
					htmlContent = '';

				$('.like_post_most_likes_given_user_data').html('');
				htmlContent += '<div class="poster_avatar"><div class="avatar" style="background-image: url(' + encodeURI(data.member_given.avatar) + ')"></div></div>' + '<div class="poster_data">' + '<a class="poster_details" href="' + data.member_given.href + '" style="font-size: 20px;">' + data.member_given.name + '</a>' + '<div class="poster_details">' + txtStrings.totalPosts + ': ' + data.member_given.total_posts + '</div>' + '<div class="poster_details">' + txtStrings.totalLikesGiven + ': ' + data.like_count + '</div>' + '</div>';

				htmlContent += '<p class="generic_text">' + txtStrings.mostLikeGivenUserHeading1 + '</p>';
				for (var i = 0, len = data.topic_data.length; i < len; i++) {
					var msgUrl = smf_scripturl + '?topic=' + data.topic_data[i].id_topic + '.msg' + data.topic_data[i].id_msg;

					htmlContent += '<div class="message_body">' + '<div class="posted_at">' + txtStrings.postedAt + ' ' + data.topic_data[i].poster_time + '</div><div class="content_encapsulate">' + data.topic_data[i].body + '</div><a class="read_more" href="' + msgUrl + '">' + txtStrings.readMore + '</a></div>';
				}
				$('#like_post_current_tab').text(txtStrings.mostLikeGivingMember);
				$('.like_post_most_likes_given_user_data').html(htmlContent).show();
				hideSpinnerOverlay();
			},

			genericErrorMessage = function(params) {
				$('.like_post_stats_error').html(params.errorMsg).show();
				hideSpinnerOverlay();
			};

		return {
			init: init,
			checkUrl: checkUrl
		};
	}();

	this.likePostStats = likePostStats;

	$(".like_post_stats_menu a").on("click", function(e) {
		if (e) {
			e.preventDefault();
			e.stopPropagation();
		}
		likePostStats.prototype.checkUrl(this.id);
	});
}());
