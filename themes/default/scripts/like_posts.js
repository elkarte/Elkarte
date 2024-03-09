/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 */

/**
 * Functions to provide ajax capability to the like(react) / unlike(unreact) button
 * Makes the appropriate call in the background and updates the button text
 * and button hover title text with the new like totals / likers
 */

/**
 * Simply invoke the constructor by calling likePosts with init method
 */
(function() {
	function likePosts ()
	{
	}

	likePosts.prototype = function() {
		let oTxt = {},

			/**
			 * Initiate likePosts with this method
			 * likePosts.prototype.init(params)
			 * currently passing the text from php
			 */
			init = function(params) {
				oTxt = params.oTxt;

				document.querySelectorAll('.react_button, .unreact_button, .reacts_button')
					.forEach(button => button.removeAttribute('title'));
			},

			/**
			 * This is bound to a click event on the page like/unlike buttons
			 * likePosts.prototype.likeUnlikePosts(event, messageID, topicID)
			 */
			likeUnlikePosts = function(e, mId, tId) {
				let messageId = parseInt(mId, 10),
					topicId = parseInt(tId, 10),
					subAction = '',
					target = e.currentTarget,
					check = target.getAttribute('class');

				if (e.currentTarget.nodeName.toLowerCase() !== 'a')
				{
					return false;
				}

				target.blur();

				// Set the subAction to what they are doing
				if (check.indexOf('unreact_button') >= 0)
				{
					if (!confirm(oTxt.are_you_sure))
					{
						return false;
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
					'msg': messageId,
				};

				// Make the ajax call to the likes system
				let url = elk_prepareScriptUrl(elk_scripturl) + 'action=likes;sa=' + subAction + ';api=json;' + elk_session_var + '=' + elk_session_id;

				fetch(url, {
					method: 'POST',
					headers: {
						'X-Requested-With': 'XMLHttpRequest',
						'Accept': 'application/json',
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: serialize(values),
					cache: 'no-store'
				})
					.then(response => {
						if (!response.ok)
						{
							throw new Error('HTTP error ' + response.status);
						}
						return response.json();
					})
					.then(resp => {
						if (resp.result === true)
						{
							// Update the page with the new likes information
							updateUi({
								'count': resp.count,
								'text': resp.text,
								'title': resp.title,
								'action': subAction,
								'messageId': messageId,
								'event': target,
							});
						}
						else
						{
							// Some failure trying to process the request
							handleError(resp);
						}
					})
					.catch(error => {
						if ('console' in window && console.error)
						{
							console.error('Error : ', error);
						}
					});
			},

			/**
			 * Does the actual update to the page the user is viewing
			 *
			 * @param {object} params object of new values from the ajax request
			 */
			updateUi = function(params) {
				let likesList = document.getElementById('likes_for_' + params.messageId),
					currentClass = (params.action === 'unlikepost') ? 'unreact_button' : 'react_button',
					nextClass = (params.action === 'unlikepost') ? 'react_button' : 'unreact_button',
					icon = '<i class="icon icon-small i-' + ((params.action === 'unlikepost') ? 'thumbup' : 'thumbdown') + '"></i>';

				// Swap the button class as needed, update the button icon / text
				params.event.classList.remove(currentClass);
				params.event.classList.add(nextClass);
				params.text = icon + params.text;

				// Update the count bubble and like list line if it exists
				if (params.count !== 0)
				{
					params.event.innerHTML = '<span class="button_indicator">' + params.count + '</span>&nbsp;' + params.text;

					if (likesList)
					{
						likesList.classList.remove('hide');
						likesList.innerHTML = '<i class="icon icon-small i-thumbup"></i>&nbsp;' + params.title;
					}
				}
				else
				{
					params.event.innerHTML = params.text;

					if (likesList)
					{
						likesList.innerHTML = '';
						likesList.classList.add('hide');
					}
				}
			},

			/**
			 * Show a non-modal error box when something goes wrong with
			 * sending the request or processing it
			 *
			 * @param {type} params
			 */
			handleError = function(params) {
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
	function likePostStats ()
	{
	}

	likePostStats.prototype = function() {
		let currentUrlFrag = null,
			allowedUrls = {},
			tabsVisitedCurrentSession = {},
			defaultHash = 'messagestats',
			txtStrings = {},

			// Initialize, load in text strings, etc
			init = function(params) {
				txtStrings = Object.assign({}, params.txtStrings);
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
			showSpinnerOverlay = function() {
				let lpPreloader = document.createElement('div');

				lpPreloader.id = 'lp_preloader';
				lpPreloader.innerHTML = '<i class="icon icon-xl i-concentric"></i>';
				document.getElementById('like_post_stats_overlay').appendChild(lpPreloader);
				document.getElementById('like_post_stats_overlay').style.display = 'block';
			},

			// Hide the ajax spinner
			hideSpinnerOverlay = function() {
				let lpPreloader = document.getElementById('lp_preloader');

				if (lpPreloader)
				{
					lpPreloader.parentNode.removeChild(lpPreloader);
				}
				document.getElementById('like_post_stats_overlay').style.display = 'none';
			},

			// Set the active stats tab
			highlightActiveTab = function() {
				document.querySelectorAll('.like_post_stats_menu a').forEach(function(element) {
					element.classList.remove('active');
				});

				document.getElementById(currentUrlFrag).classList.add('active');
			},

			// Check the url for a valid like stat tab, load data if not done yet
			checkUrl = function(url) {
				// Busy doing something
				showSpinnerOverlay();

				// No tab sent, use the current hash
				if (typeof (url) === 'undefined' || url === '')
				{
					let currentHref = window.location.href.split('#');

					currentUrlFrag = (typeof (currentHref[1]) === 'undefined') ? defaultHash : currentHref[1];
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
				document.querySelectorAll('.like_post_stats_data').forEach((elem) => {
					Array.from(elem.children).forEach((child) => {
						child.style.display = 'none';
					});
				});
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
			getDataFromServer = function(params) {
				let element = document.querySelector('.like_post_stats_error');
				element.style.display = 'none';
				element.innerHTML = '';

				let url = elk_prepareScriptUrl(elk_scripturl) + 'action=likes;sa=likestats;area=' + params.url + ';api=json;' + elk_session_var + '=' + elk_session_id;

				fetch(url, {
					method: 'POST',
					cache: 'no-cache',
					headers: {
						'X-Requested-With': 'XMLHttpRequest',
						'Accept': 'application/json',
						'Content-Type': 'application/x-www-form-urlencoded',
					},
				})
					.then(response => {
						if (!response.ok)
						{
							throw new Error('HTTP error ' + response.status);
						}
						return response.json();
					})
					.then(resp => {
						if (typeof resp.error !== 'undefined' && resp.error !== '')
						{
							genericErrorMessage({
								errorMsg: resp.error
							});
						}
						else if (typeof resp.data !== 'undefined' && typeof resp.data.noDataMessage !== 'undefined' && resp.data.noDataMessage !== '')
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
					.catch(error => {
						if ('console' in window && console.info)
						{
							console.info('fail:', error.name);
						}
					})
					.finally(() => {
						// All done
						hideSpinnerOverlay();
					});
			},

			// Show the most liked messages
			showMessageStats = function() {
				let data = tabsVisitedCurrentSession[currentUrlFrag],
					htmlContent = '',
					messageUrl = '',
					like_post_message_data = document.querySelector('.like_post_message_data');

				const nFormat = new Intl.NumberFormat();

				// Clear anything that was in place
				like_post_message_data.innerHTML = '';
				like_post_message_data.style.display = 'none';

				// Build the new html to add to the page
				data.forEach((point) => {
					messageUrl = elk_prepareScriptUrl(elk_scripturl) + 'topic=' + point.id_topic + '.msg' + point.id_msg + '#new';

					htmlContent += '' +
						'<div class="content forumposts">' +
						'   <div class="like_stats_avatar">' +
						'       <img class="avatar avatarresize" alt="" src="' + encodeURI(point.member_received.avatar) + '" />' +
						'   </div>' +
						'   <div class="like_stats_details">' +
						'       <a class="poster_details largetext" href="' + point.member_received.href + '">' + point.member_received.name + '</a>' +
						'       <div class="poster_details">' + txtStrings.totalPosts + ': ' + nFormat.format(point.member_received.total_posts) + '</div>' +
						'   </div>' +
						'   <div class="like_stats_subject largetext">' +
						'      <a class="message_title" title="' + point.preview + '" href="' + messageUrl + '">' + txtStrings.topic + ': ' + point.subject + '</a>' +
						'   </div>' +
						'   <div class="separator"></div>' +
						'   <div class="well">' +
						'		<p>' + txtStrings.usersWhoLiked.easyReplace({1: point.like_count.toLocaleString(undefined, {minimumFractionDigits: 0})}) + '</p>';

					// All the members that liked this masterpiece of internet jibba jabba
					point.member_liked_data.forEach((member_liked_data) => {
						htmlContent += '' +
							'   <div class="like_stats_likers">' +
							'       <a href="' + member_liked_data.href + '">' +
							'           <img class="avatar" alt="" src="' + encodeURI(member_liked_data.avatar) + '" title="' + member_liked_data.real_name + '" loading="lazy" />' +
							'       </a> ' +
							'   </div>';
					});

					htmlContent += '' +
						'   </div>' +
						'</div>';
				});

				// Show the htmlContent we built
				document.getElementById('like_post_current_tab_desc').textContent = txtStrings.mostLikedMessage;
				like_post_message_data.innerHTML = htmlContent;
				like_post_message_data.style.display = 'block';

				// All done with this
				hideSpinnerOverlay();
			},

			// The most liked Topics !
			showTopicStats = function() {
				let data = tabsVisitedCurrentSession[currentUrlFrag],
					topicUrl = '',
					msgUrl = '',
					htmlContent = '',
					expand_txt = [],
					collapse_txt = [],
					like_post_topic_data = document.querySelector('.like_post_topic_data');

				const nFormat = new Intl.NumberFormat();

				// Clear the area
				like_post_topic_data.innerHTML = '';
				like_post_topic_data.style.display = 'none';

				// For each of the top X topics, output the info
				data.forEach((point, index) => {
					topicUrl = elk_prepareScriptUrl(elk_scripturl) + 'topic=' + point.id_topic;

					// Start with the topic info
					htmlContent += '' +
						'<div class="content forumposts">' +
						'   <a class="largetext" href="' + topicUrl + '">' + point.msg_data[0].subject + '</a> ' + txtStrings.mostPopularTopicHeading1.easyReplace({1: nFormat.format(point.like_count)}) +
						'   <p class="panel_toggle secondary_header">' +
						'       <span class="topic_toggle">&nbsp' +
						'           <span id="topic_toggle_img_' + index + '" class="chevricon i-chevron-up" title=""></span>' +
						'       </span>' +
						'       <a href="#" id="topic_toggle_link_' + index + '">' + txtStrings.mostPopularTopicSubHeading1.easyReplace({
							1: nFormat.format(point.num_messages_liked),
							2: txtStrings.showPosts
						}) + '</a>' +
						'   </p>' +
						'   <div id="topic_container_' + index + '" class="hide">';

					// Expand / collapse text strings for this area
					collapse_txt[index] = txtStrings.mostPopularTopicSubHeading1.easyReplace({
						1: nFormat.format(point.num_messages_liked),
						2: txtStrings.showPosts
					});
					expand_txt[index] = txtStrings.mostPopularTopicSubHeading1.easyReplace({
						1: nFormat.format(point.num_messages_liked),
						2: txtStrings.hidePosts
					});

					// Posts from the topic itself
					point.msg_data.forEach((msg_data) => {
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
				document.getElementById('like_post_current_tab_desc').textContent = txtStrings.mostLikedTopic;
				like_post_topic_data.innerHTML = htmlContent;
				like_post_topic_data.style.display = 'block';

				// Add in the toggle functions
				createCollapsibleContent(data.length, expand_txt, collapse_txt, 'topic');

				// All done with this request
				hideSpinnerOverlay();
			},

			// The single most liked board, like ever
			showBoardStats = function(response) {
				let data = tabsVisitedCurrentSession[currentUrlFrag],
					boardUrl = elk_prepareScriptUrl(elk_scripturl) + 'board=' + data.id_board,
					like_post_board_data = document.querySelector('.like_post_board_data');

				const nFormat = new Intl.NumberFormat();

				// Start off clean
				like_post_board_data.innerHTML = '';
				like_post_board_data.style.display = 'none';

				// First a bit about the board
				let htmlContent = '' +
					'<div class="content forumposts">' +
					'	<p>' +
					'       <a class="largetext" href="' + boardUrl + '">' + data.name + '</a> ' + txtStrings.mostPopularBoardHeading1 + ' ' + nFormat.format(data.like_count) + ' ' + txtStrings.genricHeading1 +
					'   </p>' +
					'   <p>' +
					txtStrings.mostPopularBoardSubHeading1 + ' ' + nFormat.format(data.num_topics) + ' ' + txtStrings.mostPopularBoardSubHeading2 + ' ' + nFormat.format(data.topics_liked) + ' ' + txtStrings.mostPopularBoardSubHeading3 +
					'   </p>' +
					'   <p>' +
					txtStrings.mostPopularBoardSubHeading4 + ' ' + nFormat.format(data.num_posts) + ' ' + txtStrings.mostPopularBoardSubHeading5 + ' ' + nFormat.format(data.msgs_liked) + ' ' + txtStrings.mostPopularBoardSubHeading6 +
					'   </p>' +
					'</div>';

				// And show some of the topics from it
				data.topic_data.forEach((data_topic) => {
					let topicUrl = elk_prepareScriptUrl(elk_scripturl) + 'topic=' + data_topic.id_topic;

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
				document.getElementById('like_post_current_tab_desc').textContent = txtStrings.mostLikedBoard;
				like_post_board_data.innerHTML = htmlContent;
				like_post_board_data.style.display = 'block';

				// Done with ajax
				hideSpinnerOverlay();
			},

			// Data for all the narcissists out there !
			showMostLikesReceivedUserStats = function(response) {
				let data = tabsVisitedCurrentSession[currentUrlFrag],
					msgUrl = '',
					htmlContent = '',
					expand_txt = [],
					collapse_txt = [],
					like_post_most_liked_user_data = document.querySelector('.like_post_most_liked_user_data');

				const nFormat = new Intl.NumberFormat();

				// Clean the screen
				like_post_most_liked_user_data.innerHTML = '';
				like_post_most_liked_user_data.style.display = 'none';

				// For each member returned
				data.forEach((point, index) => {
					// Start off with a bit about them and why they are great
					htmlContent += '' +
						'<div class="content forumposts">' +
						'   <div class="like_stats_avatar">' +
						'       <img class="avatar avatarresize" alt="" src="' + encodeURI(point.member_received.avatar) + '" />' +
						'   </div>' +
						'   <div class="like_stats_details">' +
						'       <a class="poster_details largetext" href="' + point.member_received.href + '">' + point.member_received.name + '</a>' +
						'       <div class="poster_details">' + txtStrings.totalPosts + ': ' + nFormat.format(point.member_received.total_posts) + '</div>' +
						'       <div class="poster_details">' + txtStrings.totalLikesReceived + ': ' + nFormat.format(point.like_count) + '</div>' +
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

					point.post_data.forEach((post_data) => {
						msgUrl = elk_prepareScriptUrl(elk_scripturl) + 'topic=' + post_data.id_topic + '.msg' + post_data.id_msg;

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
				document.getElementById('like_post_current_tab_desc').textContent = txtStrings.mostLikedMember;
				like_post_most_liked_user_data.innerHTML = htmlContent;
				like_post_most_liked_user_data.style.display = 'block';

				// Add in the toggle functions
				createCollapsibleContent(data.length, expand_txt, collapse_txt, 'liked');

				// Show we are done
				hideSpinnerOverlay();
			},

			// Data for all the +1, me too, etc users as well
			showMostLikesGivenUserStats = function(response) {
				let data = tabsVisitedCurrentSession[currentUrlFrag],
					htmlContent = '',
					msgUrl = '',
					expand_txt = [],
					collapse_txt = [],
					like_post_most_likes_given_user_data = document.querySelector('.like_post_most_likes_given_user_data');

				const nFormat = new Intl.NumberFormat();

				// Clear the div of any previous content
				like_post_most_likes_given_user_data.innerHTML = '';
				like_post_most_likes_given_user_data.style.display = 'none';

				// For each member returned
				data.forEach((point, index) => {
					htmlContent += '' +
						'<div class="content forumposts">' +
						'   <div class="like_stats_avatar">' +
						'       <img class="avatar avatarresize" alt="" src="' + encodeURI(point.member_given.avatar) + '" />' +
						'   </div>' +
						'   <div class="like_stats_details">' +
						'       <a class="poster_details largetext" href="' + point.member_given.href + '">' + point.member_given.name + '</a>' +
						'       <div class="poster_details">' + txtStrings.totalPosts + ': ' + nFormat.format(point.member_given.total_posts) + '</div>' +
						'       <div class="poster_details">' + txtStrings.totalLikesGiven + ': ' + nFormat.format(point.like_count) + '</div>' +
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

					point.post_data.forEach((post_data) => {
						msgUrl = elk_prepareScriptUrl(elk_scripturl) + 'topic=' + post_data.id_topic + '.msg' + post_data.id_msg;

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
				document.getElementById('like_post_current_tab_desc').textContent = txtStrings.mostLikeGivingMember;
				like_post_most_likes_given_user_data.innerHTML = htmlContent;
				like_post_most_likes_given_user_data.style.display = 'block';

				// Add in the toggle functions
				createCollapsibleContent(data.length, expand_txt, collapse_txt, 'liker');

				// Done!
				hideSpinnerOverlay();
			},

			// Attach the toggle class to each hidden div
			createCollapsibleContent = function(count, expand_txt, collapse_txt, prefix) {
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

			genericErrorMessage = function(params) {
				let like_post_stats_error = document.querySelector('.like_post_stats_error');
				like_post_stats_error.innerHTML = params.errorMsg;
				like_post_stats_error.style.display = 'block';
				hideSpinnerOverlay();
			};

		return {
			init: init,
			checkUrl: checkUrl
		};
	}();

	this.likePostStats = likePostStats;

	// Set the menu buttons to act as ajax tabs
	document.addEventListener("DOMContentLoaded", function() {
		const links = document.querySelectorAll('.like_post_stats_menu a');
		links.forEach(function(link) {
			link.addEventListener('click', function(e) {
				if (e)
				{
					e.preventDefault();
					e.stopPropagation();
				}
				likePostStats.prototype.checkUrl(this.id);
			});
		});
	});
}());
