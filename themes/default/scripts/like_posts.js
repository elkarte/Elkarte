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
		var likeUnlikePosts = function(e, mId, tId) {
			var messageId = parseInt(mId, 10),
				topicId = parseInt(tId, 10),
				subAction = '';

			var check = $(e.target).attr('class');
			if (check.indexOf('unlike_button') >= 0) subAction = 'unlikepost';
			else subAction = 'likepost';

			var values = {
				'topic': topicId,
				'msg': messageId,
			};

			$.ajax({
				url: elk_scripturl + '?action=likes;sa=' + subAction + ';' + elk_session_var + '=' + elk_session_id,
				type: 'POST',
				dataType: 'json',
				data: values,
				cache: false,
				success: function(resp) {
					if (resp.result === true) {
						console.log(resp);
						updateUi({
							'elem': $(e.target),
							'count': resp.count,
							'newText': resp.newText,
							'action': subAction
						});
					} else {
						handleError(resp);
					}
				},
				error: function(err) {
					handleError(err);
				},
			});
		},

			updateUi = function(params) {
				var currentClass = (params.action === 'unlikepost') ? 'unlike_button' : 'like_button',
					nextClass = (params.action === 'unlikepost') ? 'like_button' : 'unlike_button',
					likeText = ((params.count !== 0) ? params.count : '') + ' ' + params.newText;

				$(params.elem).removeClass(currentClass).addClass(nextClass);
				$(params.elem).text(likeText);
			},

			handleError = function(params) {
				console.log('test');
			};

		return {
			likeUnlikePosts: likeUnlikePosts
		};
	}();

	// instead of this, we can use namespace too
	this.likePosts = likePosts;
}());
