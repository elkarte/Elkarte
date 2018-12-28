<?php

/**
 * The likes stats pages
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause  (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */
function template_lp_stats()
{
	global $context, $txt;

	echo '
		<h2 class="category_header">
			', $txt['like_post_stats'], '
		</h2>

		<p class="description">
			', $context['like_posts']['tab_desc'], '
		</p>';

	echo '
		<ul id="adm_submenus" class="like_post_stats_menu" role="menubar">';

	// Print out all the items in this tab.
	foreach ($context['lp_stats_tabs'] as $tab)
	{
		echo '
			<li class="listlevel1" role="menuitem">
				<a class="linklevel1" href="" id="', $tab['id'], '">
					', $tab['label'], '
				</a>
			</li>';
	}

	echo '
		</ul>
		<div class="recentposts">';

	// Now a container to be filled by JS
	echo '
			<h2 class="category_header" id="like_post_current_tab_desc">_</h2>
			<div class="like_post_stats_data individual_data">
				<div class="like_post_message_data"></div>
				<div class="like_post_topic_data"></div>
				<div class="like_post_board_data"></div>
				<div class="like_post_most_liked_user_data"></div>
				<div class="like_post_most_likes_given_user_data"></div>
				<div class="like_post_stats_error infobox"></div>
			</div>';

	// The ajax indicator overlay
	echo '
			<div id="like_post_stats_overlay"></div>
			<div id="lp_preloader"></div>';

	echo '
		</div>';

	echo '<script>
		$(function() {
			likePostStats.prototype.init({
				txtStrings: {
					topic: ' . JavaScriptEscape($txt['like_post_topic']) . ',
					message: ' . JavaScriptEscape($txt['like_post_message']) . ',
					board: ' . JavaScriptEscape($txt['like_post_board']) . ',
					totalPosts: ' . JavaScriptEscape($txt['like_post_total_posts']) . ',
					postedAt: ' . JavaScriptEscape($txt['like_post_posted_at']) . ',
					readMore: ' . JavaScriptEscape($txt['like_post_read_more']) . ',
					genricHeading1: ' . JavaScriptEscape($txt['like_post_generic_heading1']) . ',
					totalLikesReceived: ' . JavaScriptEscape($txt['like_post_total_likes_received']) . ',
					mostLikedMessage: ' . JavaScriptEscape($txt['like_post_tab_mlm']) . ',
					mostLikedTopic: ' . JavaScriptEscape($txt['like_post_tab_mlt']) . ',
					mostLikedBoard: ' . JavaScriptEscape($txt['like_post_tab_mlb']) . ',
					mostLikedMember: ' . JavaScriptEscape($txt['like_post_tab_mlmember']) . ',
					mostLikeGivingMember: ' . JavaScriptEscape($txt['like_post_tab_mlgmember']) . ',
					usersWhoLiked: ' . JavaScriptEscape($txt['like_post_users_who_liked']) . ',
					mostPopularTopicHeading1: ' . JavaScriptEscape($txt['like_post_most_popular_topic_heading1']) . ',
					mostPopularTopicSubHeading1: ' . JavaScriptEscape($txt['like_post_most_popular_topic_sub_heading1']) . ',
					mostPopularBoardHeading1: ' . JavaScriptEscape($txt['like_post_most_popular_board_heading1']) . ',
					mostPopularBoardSubHeading1: ' . JavaScriptEscape($txt['like_post_most_popular_board_sub_heading1']) . ',
					mostPopularBoardSubHeading2: ' . JavaScriptEscape($txt['like_post_most_popular_board_sub_heading2']) . ',
					mostPopularBoardSubHeading3: ' . JavaScriptEscape($txt['like_post_most_popular_board_sub_heading3']) . ',
					mostPopularBoardSubHeading4: ' . JavaScriptEscape($txt['like_post_most_popular_board_sub_heading4']) . ',
					mostPopularBoardSubHeading5: ' . JavaScriptEscape($txt['like_post_most_popular_board_sub_heading5']) . ',
					mostPopularBoardSubHeading6: ' . JavaScriptEscape($txt['like_post_most_popular_board_sub_heading6']) . ',
					mostPopularUserHeading1: ' . JavaScriptEscape($txt['like_post_most_popular_user_heading1']) . ',
					likesReceived: ' . JavaScriptEscape($txt['like_post_liked_by_others']) . ',
					totalLikesGiven: ' . JavaScriptEscape($txt['like_post_total_likes_given']) . ',
					showPosts: ' . JavaScriptEscape($txt['like_post_show']) . ',
					hidePosts: ' . JavaScriptEscape($txt['like_post_hide']) . ',
					mostLikeGivenUserHeading1: ' . JavaScriptEscape($txt['like_post_most_like_given_user_heading1']) . '
				}
			});
		})
	</script>';
}
