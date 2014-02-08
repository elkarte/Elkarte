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
				subAction = '';

			var check = $(e.target).attr('class');
			if (check.indexOf('unlike_button') >= 0) subAction = 'unlikepost';
			else subAction = 'likepost';

			var values = {
				'topic': topicId,
				'msg': messageId,
			};
			values[sessionVar] = sessionId;

			$.ajax({
				url: elk_scripturl + '?action=likes;sa=' + subAction,
				type: 'POST',
				dataType: 'json',
				data: values,
				success: function(resp) {
					updateUi({
						'elem': $(e.target),
						'action': subAction
					});
				},
				error: function(err) {
					console.log('error');
					// console.log(err);
				},
			});
		},

			updateUi = function(params) {
				var currentClass = (params.action === 'unlikepost') ? 'unlike_button' : 'like_button';
				var nextClass = (params.action === 'unlikepost') ? 'like_button' : 'unlike_button';

				$(params.elem).removeClass(currentClass).addClass(nextClass);
			};

		return {
			likeUnlikePosts: likeUnlikePosts
		};
	}();

	// instead of this, we can use namespace too
	this.likePosts = likePosts;
}());
