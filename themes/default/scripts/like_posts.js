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
		var likeUnlikePost = function(params) {
			$.ajax({
				url: 'http://localhost/forum/elkarte/index.php?action=likes;sa=unlikepost;topic=2;msg=2;dad5b604146=17d230dace20ed32359d95b065512fce',
				type: 'GET',
				error: function(err) {
					console.log('error');
					console.log(err);
				},
				success: function(resp) {
					console.log('success');
					console.log(resp);
				},
			});
		};

		return {
			init: init
		};
	}();
	this.likePosts = likePosts;
}());