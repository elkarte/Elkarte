/*!
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 beta 4
 *
 * Original code from Aziz, redone and refactored for ElkArte
 */

/**
 * This javascript searches the message for video links and replaces them
 * with a clickable preview thumbnail of the video.  Once the image is clicked
 * the video is embeded in to the page to play.
 *
 * Currently works with youtube, vimeo and dailymotion
 *
 * @param {object} oInstanceSettings holds the text strings to use in the html created
 * @param {int} msgid optional to only search for links in a specific id
 */
 (function($) {
	'use strict';
	$.fn.linkifyvideo = function(oInstanceSettings, msgid) {
		var oDefaultsSettings = {
			preview_image : '',
			ctp_video : '',
			hide_video : '',
			youtube : '',
			vimeo : '',
			dailymotion : ''
		};

		// Account for user options
		var oSettings = $.extend({}, oDefaultsSettings, oInstanceSettings || {});

		/**
		 * Takes the generic embed tag and inserts the proper video source
		 *
		 * @param {string} source source string to use in the embed src call
		 */
		function getEmbed(source) {
			return embed_html.replace('{src}', source);
		}

		/**
		 * Replaces the image with the created embed code to show the video
		 * Called from click / double click events attached to the image
		 *
		 * @param {string} tag anchor tag we are replacing with the embed tag
		 * @param {string} eURL the load or place source link
		 */
		function showFlash(tag, eURL) {
			$(tag).replaceWith(getEmbed(eURL));
		}

		/**
		 * Shows the video image and sets up the link
		 * Sets up single click event to play videoID and double click to load it
		 *
		 * @param {string} a videoID link
		 * @param {string} src source of image
		 * @param {string} eURL load link
		 * @param {string} eURLa play link
		 */
		function getIMG(a, src, eURL, eURLa)
		{
			return $('<div class="elk_video"><a href="' + a.href + '"><img class="elk_previewvideo" alt="' + oSettings.preview_image + '" ' + 'title="' + oSettings.ctp_video + '" src="' + src + '"/></a></div>')
			.dblclick(function(e) {
				// double click loads the video but does not autoplay
				e.preventDefault();

				// clear the single click event
				clearTimeout(this.tagID);
				showFlash(this, eURL);
			})
			.on('click', function(e) {
				// single click to begin playing the video
				e.preventDefault();
				var tag = this;

				this.tagID = setTimeout(function() {
					showFlash(tag, eURLa);
				}, 200);
			});
		}

		/**
		 * Get a vimeo videos thumbnail
		 *
		 * @param {string} videoID
		 * @param {string} callback function to call once the json results are returned.
		 */
		function getVimeoIMG(videoID, callback)
		{
			var img = 'assets.vimeo.com/images/logo_vimeo_land.png';

			$.getJSON('http://www.vimeo.com/api/v2/video/' + videoID + '.json?callback=?', {format: "json"},
			function(data) {
				if (typeof(data[0].thumbnail_large) !== "undefined")
					callback(data[0].thumbnail_large);
				else
					callback(img);
			});
		}

		/**
		 * Get a dailymotion video thumbnail
		 *
		 * @param {string} videoID
		 * @param {string} callback function to call once the json results are returned.
		 */
		function getDailymotionIMG(videoID, callback)
		{
			var img = 'assets.vimeo.com/images/logo_vimeo_land.png';

			$.getJSON('https://api.dailymotion.com/video/' + videoID + '?fields=thumbnail_480_url', {},
				function(data) {
					if (typeof(data.thumbnail_480_url) !== "undefined")
						callback(data.thumbnail_480_url);
					else
						callback(img);
				}
			);
		}

		/**
		 * Returns either an image link or an embed link
		 *
		 * @param {boolean} embed true returns the video embed code, false the image preview
		 * @param {string} a link tag of the video
		 * @param {string} src link of the preview image
		 * @param {string} eURL double click event load video
		 * @param {string} eURLa single click event play video
		 */
		function embedOrIMG(embed, a, src, eURL, eURLa)
		{
			return embed ? getEmbed(eURL) : getIMG(a, src, eURL, eURLa);
		}

		var domain_regex = /^[^:]*:\/\/(?:www\.)?([^\/]+)(\/.*)$/,
			embed_html = '<div class="elk_video"><iframe width="640px" height="385px" style="max-width: 98%; max-height: auto;" src="{src}" frameborder="0" allowfullscreen></iframe></div>',
			handlers = {};
		handlers['youtube.com'] = function(path, a, embed) {
			var videoID = path.match(/\bv[=/]([^&#?$]+)/i) || path.match(/#p\/(?:a\/)?[uf]\/\d+\/([^?$]+)/i) || path.match(/([\w-]{11})/i);
			if (!videoID || !(videoID = videoID[1]))
				return;

			// There are two types of YouTube timestamped links
			// http://youtu.be/lLOE3fBZcUU?t=1m37s when you click share underneath the video
			// http://youtu.be/lLOE3fBZcUU?t=97 when you right click on a video and choose "Copy video URL at current time"
			// For embedding, you need to use "?start=97" instead, so we have to convert t=1m37s to seconds while also supporting t=97
			var startAt = path.match(/t=(?:([1-9]{1,2})h)?(?:([1-9]{1,2})m)?(?:([1-9]+)s?)/);
			var startAtPar = '';
			if (startAt)
			{
				var startAtSeconds = 0;

				// Hours
				if (typeof(startAt[1]) !== 'undefined')
					startAtSeconds += parseInt(startAt[1]) * 3600;
				// Minutes
				if (typeof(startAt[2]) !== 'undefined')
					startAtSeconds += parseInt(startAt[2]) * 60;
				// Seconds
				if (typeof(startAt[3]) !== 'undefined')
					startAtSeconds += parseInt(startAt[3]);

				startAtPar = '&start=' + startAtSeconds.toString();
			}

			var embedURL = '//www.youtube.com/embed/' + videoID + '?rel=0' + startAtPar,
				tag = embedOrIMG(embed, a, '//img.youtube.com/vi/' + videoID + '/0.jpg', embedURL, embedURL + '&autoplay=1' );

			return [oSettings.youtube, tag];
		};
		handlers['youtu.be'] = handlers['youtube.com'];
		handlers['vimeo.com'] = function(path, a, embed) {
			var videoID = path.match(/^\/(\d+)/i);
			if (!videoID || !(videoID = videoID[1]))
				return;

			var embedURL = '//player.vimeo.com/video/' + videoID,
				tag = null,
				img = '//assets.vimeo.com/images/logo_vimeo_land.png';

			// Get the preview image or embed tag
			if (!embed)
			{
				// We need to use a callback to get the thumbnail
				getVimeoIMG(videoID, function(img) {
					$(a).parent().next().find("img").attr("src", img);
				});

				// This is to show something while we wait for our callback to return
				tag = embedOrIMG(embed, a, img, embedURL, embedURL + '?autoplay=1');
			}
			else
				tag = embedOrIMG(embed, a, img, embedURL, embedURL + '?autoplay=1');

			return [oSettings.vimeo, tag];
		};
		handlers['dailymotion.com'] = function(path, a, embed) {
			var videoID = path.match(/^\/(?:video|swf)\/([a-z0-9]{1,18})/i);
			if (!videoID || !(videoID = videoID[1]))
				return;

			var embedURL = '//dailymotion.com/embed/video/' + videoID,
				tag = null,
				img = '//dailymotion.com/thumbnail/video/' + videoID;

			// Get the preview image or embed tag
			if (!embed)
			{
				// We use a callback to get the thumbnail we want
				getDailymotionIMG(videoID, function(img) {
					$(a).parent().next().find("img").attr("src", img);
				});

				// This is to show something while we wait for our callback to return
				tag = embedOrIMG(embed, a, img, embedURL, embedURL + '?related=0&autoplay=1');
			}
			else
				tag = embedOrIMG(embed, a, img, embedURL + '?related=0', embedURL + '?related=0&autoplay=1');

			return [oSettings.dailymotion, tag];
		};

		// Get the links in the id="msg_1234 divs.
		var links = null;
		if (typeof(msgid) !== "undefined" && msgid !== null)
			links = $('#' + msgid + ' a');
		else
			links = $('[id^=msg_] a');

		// Create the show/hide button
		var showhideBtn = $('<a class="floatright" title="' + oSettings.hide_video + '"><img src="' + elk_images_url + '/selected.png"></a>').on('click', function() {
				var $img = $(this).find("img"),
					$vid = $(this).parent().next();

				// Toggle slide the video and change the icon
				$img.attr("src", elk_images_url + ($vid.is(":hidden") !== true ? "/selected_open.png" : "/selected.png"));
				$vid.slideToggle();
			});

		// Loop though each link old skool style for speed
		for (var i = links.length - 1; i > -1; i--)
		{
			var tag = links[i],
				text = tag.innerText || tag.textContent || "";

			// Ignore in sentences
			if (tag.previousSibling && tag.previousSibling.nodeName === '#text')
				continue;

			// Ignore in quotes and signatures
			if ("bbc_quote;signature".indexOf(tag.parentNode.className) !== -1)
				continue;

			// No href or inner text not equal to href attr then we move along
			if (tag.href === "" || tag.href.indexOf(text) !== 0)
				continue;

			// Get domain and validate we know how to handle it
			var m = tag.href.match(domain_regex),
				handler = null,
				args = null;

			// One of our video provider domains?
			if (m !== null && typeof(handlers[m[1]]) !== "undefined" && handlers[m[1]] !== null)
			{
				// Call the handeler and get the tag to insert
				handler = handlers[m[1]];

				args = handler(m[2], tag, false);
				if (args)
				{
					$(tag).wrap('<div class="elk_videoheader">').text(args[0]).after(showhideBtn.clone(true));
					$(tag).parent().after(args[1]);
				}
			}
		}
	};
})(jQuery);
