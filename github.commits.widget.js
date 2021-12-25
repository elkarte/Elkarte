/*

 https://github.com/alexanderbeletsky/github-commits-widget

 # Legal Info (MIT License)

 Copyright (c) 2012 Alexander Beletsky

 Permission is hereby granted, free of charge, to any person obtaining a copy
 of this software and associated documentation files (the "Software"), to deal
 in the Software without restriction, including without limitation the rights
 to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 copies of the Software, and to permit persons to whom the Software is
 furnished to do so, subject to the following conditions:

 The above copyright notice and this permission notice shall be included in
 all copies or substantial portions of the Software.

 THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 THE SOFTWARE.

 */

(function ($) {
	function widget(element, options, callback) {
		this.element = element;
		this.options = options;
		this.callback = $.isFunction(callback) ? callback : $.noop;
	}

	widget.prototype = (function () {

		function getCommits(user, repo, branch, callback) {
			$.ajax({
				url: "https://api.github.com/repos/" + user + "/" + repo + "/commits?sha=" + branch,
				dataType: 'jsonp',
				success: callback
			});
		}

		function _widgetRun(widget) {
			if (!widget.options) {
				widget.element.append('<span class="error">Options for widget are not set.</span>');

				return;
			}

			var callback = widget.callback,
				element = widget.element,
				user = widget.options.user,
				repo = widget.options.repo,
				branch = widget.options.branch,
				avatarSize = widget.options.avatarSize || 20,
				last = widget.options.last === undefined ? 0 : widget.options.last,
				limitMessage = widget.options.limitMessageTo === undefined ? 0 : widget.options.limitMessageTo;

			getCommits(user, repo, branch, function (data) {
				var commits = data.data,
					totalCommits = (last < commits.length ? last : commits.length);

				element.empty();

				var list = $('<ul class="github-commits-list">').appendTo(element);
				for (var c = 0; c < totalCommits; c++) {
					var cur = commits[c],
						li = $('<li class="clearfix">'),
						e_avatar = $('<div class="github-avatar pull-left">'),
						e_author = $('<div class="github-author">');

					// Add avatar & github link if possible
					if (cur.author !== null) {
						e_avatar.append(avatar(cur.author.avatar_url, avatarSize));
						e_author.append(author(cur.author.login));
					}
					else //otherwise just list the name
					{
						e_avatar.append(cur.commit.committer.name);
					}

					// Add commit message
					e_author.append(' ' + when(cur.commit.committer.date) + '<br/>');
					e_author.append(message(cur.commit.message, cur.sha));

					// Add it
					li.append(e_avatar);
					li.append(e_author);
					list.append(li);
				}

				callback(element);

				function avatar(hash, size) {
					return $('<img>')
						.attr('class', 'github-avatar img-thumbnail')
						.attr('style', 'max-width:' + size + 'px;')
						.attr('src', hash);
				}

				function author(login) {
					return $('<a>')
						.attr("href", 'https://github.com/' + login)
						.html('<b class="commit-author">' + login + '</b> /');
				}

				function message(commitMessage, sha) {
					var originalCommitMessage = commitMessage;

					// Don't show signed off by
					commitMessage = commitMessage.split("Signed-off-by")[0];

					// Cut it if longer than allowed
					if (limitMessage > 0 && commitMessage.length > limitMessage) {
						commitMessage = commitMessage.substr(0, limitMessage) + '...';
					}

					var link = $('<a class="github-commit"></a>')
						.attr("title", originalCommitMessage)
						.attr("href", 'https://github.com/' + user + '/' + repo + '/commit/' + sha)
						.text(commitMessage);

					return link;
				}

				function when(commitDate) {
					var commitTime = new Date(commitDate).getTime(),
						todayTime = new Date().getTime(),
						differenceInDays = Math.floor(((todayTime - commitTime) / (24 * 3600 * 1000)));

					if (differenceInDays === 0) {
						var differenceInHours = Math.floor(((todayTime - commitTime) / (3600 * 1000)));

						if (differenceInHours === 0) {
							var differenceInMinutes = Math.floor(((todayTime - commitTime) / (600 * 1000)));
							if (differenceInMinutes === 0) {
								return 'just now';
							}

							return 'about ' + differenceInMinutes + ' minutes ago';
						}

						return 'about ' + differenceInHours + ' hours ago';
					} else if (differenceInDays == 1) {
						return 'yesterday';
					}
					return differenceInDays + ' days ago';
				}
			});
		}

		return {
			run: function () {
				_widgetRun(this);
			}
		};

	})();

	$.fn.githubInfoWidget = function (options, callback) {
		this.each(function () {
			new widget($(this), options, callback)
				.run();
		});
		return this;
	};

})(jQuery);
