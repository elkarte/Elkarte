/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Beta 2
 *
 * Ajaxifying likes - WIP
 */

/**
 * Simply invoke the constructor by calling dragDropAttachment
 *
 */
(function() {
	function likePosts() {}

	likePosts.prototype = function() {
		var likeUnlikePosts = function(e, mId, tId, ssId, ssVar) {
			var messageId = parseInt(mId, 10),
				topicId = parseInt(tId, 10),
				sessionId = ssId,
				sessionVar = ssVar,
				isLike = false;

			var check = $(e.target).attr('class');
			if (check.indexOf('unlike_button') >= 0) isLike = true;

			var values = {
				'action': 'likes',
				'sa': (isLike === false) ? 'likepost' : 'unlikepost',
				'topic': topicId,
				'msg': messageId,
			};
			values[sessionVar] = sessionId;

			$.ajax({
				url: elk_scripturl,
				type: 'GET',
				data: values,
				error: function(err) {
					console.log('error');
					// console.log(err);
				},
				success: function(resp) {
					console.log('success');
					// console.log(resp);
				},
			});
		};

		return {
			likeUnlikePosts: likeUnlikePosts
		};
	}();

	// instead of this, we can use namespace too
	this.likePosts = likePosts;
}());
