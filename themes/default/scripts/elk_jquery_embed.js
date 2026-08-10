/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 * Original code from Aziz, redone and refactored for ElkArte
 */

/** global: elk_session_id, elk_session_var, elk_scripturl */

/**
 * This JavaScript searches the message for video links and replaces them
 * with a clickable preview thumbnail of the video.  Once the image is clicked
 * the video is embedded in to the page to play.
 *
 * Currently, works with YouTube, Vimeo, TikTok, Twitter, Facebook, Instagram and DailyMotion
 *
 */
(function($) {
	'use strict';

	/**
	 * @param {object} oInstanceSettings holds the text strings to use in the html created
	 * @param {int} msgid optional to only search for links in a specific id
	 */
	$.fn.linkifyvideo = function(oInstanceSettings, msgid) {
		let oDefaultsSettings = {
			embed_limit: 25,
			preview_image: '',
			ctp_video: '',
			hide_video: '',
			youtube: '',
			vimeo: '',
			dailymotion: '',
			tiktok: '',
			twitter: '',
			facebook: '',
			instagram: ''
		};

		// Account for user options
		let oSettings = $.extend({}, oDefaultsSettings, oInstanceSettings || {});

		/**
		 * Replaces the image with the created embed code to show the video
		 * Called from click event attached to the image
		 *
		 * @param {string} tag anchor tag we are replacing with the embed tag
		 * @param {string} eURL the load or place source link
		 * @param {boolean} bAspect if to use a tall vs wide
		 */
		function showEmbed (tag, eURL, bAspect)
		{
			if (bAspect)
			{
				$(tag).html(embed_html.replace('{src}', eURL));
			}
			else
			{
				$(tag).html(embed_html_916.replace('{src}', eURL));
			}
		}

		/**
		 * Shows the video image and sets up the link
		 * Sets click event to load video sites embed code
		 *
		 * @param {object} a videoID link
		 * @param {string} src source of image
		 * @param {string} eURLa play link
		 * @param {boolean} bAspect false to use a 9/16 iframe vs 16x9
		 */
		function getIMG (a, src, eURLa, bAspect)
		{
			return $('' +
				'<div class="elk_video">' +
				'   <a href="' + a.href + '">' +
				'       <img class="elk_video_preview" alt="' + oSettings.preview_image + '" ' + 'title="' + oSettings.ctp_video + '" src="' + src + '"/>' +
				'   </a>' +
				'</div>')
				.on('click', function(e) {
						e.preventDefault();
						let tag = this;
						showEmbed(tag, eURLa, bAspect);
					}
				);
		}

		/**
		 * Returns a linked preview image.  Click on the image to load the player.
		 *
		 * @param {string} a link tag of the video
		 * @param {string} src link of the preview image
		 * @param {string} eURLa single click event play video
		 * @param {boolean} bAspect use a wide vs tall ratio
		 */
		function embedIMG (a, src, eURLa, bAspect = true)
		{
			return getIMG(a, src, eURLa, bAspect);
		}

		/**
		 * Creates and inserts a document fragment.  Doing this vs inner/outer HTML ensures that any script
		 * tags in the embed code will execute.
		 *
		 * @param {Element} a the link we are working with
		 * @param {object} data the data from the ajax call
		 */
		function createFragment (a, data)
		{
			// Since data.html may contain a script tag that needs to run, we have to add it like this
			let parent = a.parentNode,
				frag = document.createRange().createContextualFragment('<div class="elk_video">' + data.html + '</div>');

			parent.parentNode.appendChild(frag);
			parent.nextSibling.outerHTML = '';
		}

		/**
		 * Converts a given time value from an array to seconds using a multiplier.
		 *
		 * @param {Array} timeArray - The array containing time values.
		 * @param {number} timeIndex - The index of the time value to convert.
		 * @param {number} multiplier - The multiplier to apply to the converted time value.
		 * @return {number} - The converted time value in seconds, or 0 if the time array is undefined or the timeIndex is out of range.
		 */
		function convertToSeconds (timeArray, timeIndex, multiplier)
		{
			if (typeof timeArray[timeIndex] !== 'undefined')
			{
				return parseInt(timeArray[timeIndex]) * multiplier;
			}

			return 0;
		}

		// The embed code
		let domain_regex = /^[^:]*:\/\/(?:www\.)?([^\/]+)(\/.*)$/,
			embedded_count = 0,
			provider_class = '',
			embed_html = '<iframe width="640" height="360" src="{src}" data-autoplay="true" allow="fullscreen" loading="lazy" type="text/html"></iframe>',
			embed_html_916 = '<iframe width="480" height="800" src="{src}" allow="fullscreen" loading="lazy" type="text/html"></iframe>',
			handlers = {},
			imgHandlers = {},
			logos = {
				tiktok: 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 48 48\'%3E%3Cpath fill=\'%23fff\' fill-opacity=\'.01\' d=\'M0 0h48v48H0z\'/%3E%3Cpath fill=\'%232F88FF\' stroke=\'%23000\' stroke-linejoin=\'round\' stroke-width=\'3.833\' d=\'M21.358 19.14c-5.888-.284-9.982 1.815-12.28 6.299-3.446 6.724-.597 17.728 10.901 17.728 11.499 0 11.831-11.111 11.831-12.276V17.876c2.46 1.557 4.533 2.495 6.221 2.813 1.688.317 2.76.458 3.219.422v-6.476c-1.561-.188-2.911-.547-4.05-1.076-1.709-.794-5.096-2.997-5.096-6.226.002.016.002-.817 0-2.499h-7.118c-.021 15.816-.021 24.502 0 26.058.032 2.334-1.779 5.6-5.45 5.6-3.672 0-5.482-3.263-5.482-5.367 0-1.288.442-3.155 2.271-4.538 1.085-.82 2.59-1.147 5.033-1.147V19.14Z\'/%3E%3C/svg%3E',
				vimeo: 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 455.731 455.731\'%3E%3Cpath fill=\'%231ab7ea\' d=\'M0 0h455.731v455.731H0z\'/%3E%3Cpath fill=\'%23fff\' d=\'m49.642 157.084 17.626 22.474s22.033-17.186 29.965-17.186c4.927 0 15.423 5.729 22.033 25.558 6.61 19.83 34.441 122.62 36.134 127.351 7.607 21.26 17.626 60.811 48.473 66.54s70.065-25.558 91.657-48.473c21.592-22.914 106.64-120.741 110.165-179.349 3.26-54.191-14.517-66.765-22.474-71.828-14.542-9.254-38.778-12.338-61.692-4.407s-57.726 33.931-66.98 80.2c0 0 31.287-11.457 42.744-.441s8.373 35.253-1.322 53.32-37.015 59.93-47.151 61.252c-10.135 1.322-18.067-18.508-19.389-23.796-1.322-5.288-18.067-77.997-24.236-120.3s-33.049-49.354-45.829-49.354c-12.779.001-34.812 9.696-109.724 78.439z\'/%3E%3C/svg%3E',
				dailymotion: 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 512 512\'%3E%3Cpath fill=\'%230066DC\' fill-rule=\'evenodd\' d=\'M0 512h512V0H0v512Zm441.5-68.635h-76.314v-29.928c-23.443 22.945-47.385 31.424-79.308 31.424-32.421 0-60.354-10.474-83.797-31.424-30.926-27.433-46.887-63.346-46.887-105.245 0-38.407 14.965-72.823 42.896-99.758 24.94-24.44 55.367-36.91 89.284-36.91 32.422 0 57.361 10.973 75.318 33.917V88.724L441.5 72.395v370.97Zm-141.157-202.01c-37.41 0-66.339 30.426-66.339 66.338 0 37.41 28.93 65.841 69.332 65.841 33.918 0 62.349-27.932 62.349-64.843 0-38.406-28.431-67.336-65.342-67.336Z\'/%3E%3C/svg%3E',
				twitter: 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 512 512\'%3E%3Cpath d=\'M389.2 48h70.6L305.6 224.2 487 464H345L233.7 318.6 106.5 464H35.8L200.7 275.5 26.8 48H172.4L272.9 180.9 389.2 48zM364.4 421.8h39.1L151.1 88h-42L364.4 421.8z\'/%3E%3C/svg%3E',
				facebook: 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 128 128\'%3E%3Cpath fill=\'%233B5998\' d=\'M126 118a8 8 0 0 1-8 8H10a8 8 0 0 1-8-8V10a8 8 0 0 1 8-8h108a8 8 0 0 1 8 8v108z\'/%3E%3Cpath fill=\'%236D84B4\' d=\'M5.667 98.98h116.666v18.039H5.667z\'/%3E%3Cpath fill=\'%23FFF\' d=\'M93.376 117.012H72.203V65.767H61.625v-17.66h10.578V37.504c0-14.407 5.973-22.974 22.943-22.974h14.128v17.662h-8.831c-6.606 0-7.043 2.468-7.043 7.074l-.024 8.839h15.998l-1.872 17.66H93.376v51.247z\'/%3E%3C/svg%3E',
				instagram: 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 256 256\'%3E%3Cpath fill=\'%230A0A08\' d=\'M128 23.064c34.177 0 38.225.13 51.722.745 12.48.57 19.258 2.655 23.769 4.408 5.974 2.322 10.238 5.096 14.717 9.575 4.48 4.479 7.253 8.743 9.575 14.717 1.753 4.511 3.838 11.289 4.408 23.768.615 13.498.745 17.546.745 51.723 0 34.178-.13 38.226-.745 51.723-.57 12.48-2.655 19.257-4.408 23.768-2.322 5.974-5.096 10.239-9.575 14.718-4.479 4.479-8.743 7.253-14.717 9.574-4.511 1.753-11.289 3.839-23.769 4.408-13.495.616-17.543.746-51.722.746-34.18 0-38.228-.13-51.723-.746-12.48-.57-19.257-2.655-23.768-4.408-5.974-2.321-10.239-5.095-14.718-9.574-4.479-4.48-7.253-8.744-9.574-14.718-1.753-4.51-3.839-11.288-4.408-23.768-.616-13.497-.746-17.545-.746-51.723 0-34.177.13-38.225.746-51.722.57-12.48 2.655-19.258 4.408-23.769 2.321-5.974 5.095-10.238 9.574-14.717 4.48-4.48 8.744-7.253 14.718-9.575 4.51-1.753 11.288-3.838 23.768-4.408 13.497-.615 17.545-.745 51.723-.745M128 0C93.237 0 88.878.147 75.226.77c-13.625.622-22.93 2.786-31.071 5.95-8.418 3.271-15.556 7.648-22.672 14.764C14.367 28.6 9.991 35.738 6.72 44.155 3.555 52.297 1.392 61.602.77 75.226.147 88.878 0 93.237 0 128c0 34.763.147 39.122.77 52.774.622 13.625 2.785 22.93 5.95 31.071 3.27 8.417 7.647 15.556 14.763 22.672 7.116 7.116 14.254 11.492 22.672 14.763 8.142 3.165 17.446 5.328 31.07 5.95 13.653.623 18.012.77 52.775.77s39.122-.147 52.774-.77c13.624-.622 22.929-2.785 31.07-5.95 8.418-3.27 15.556-7.647 22.672-14.763 7.116-7.116 11.493-14.254 14.764-22.672 3.164-8.142 5.328-17.446 5.95-31.07.623-13.653.77-18.012.77-52.775s-.147-39.122-.77-52.774c-.622-13.624-2.786-22.929-5.95-31.07-3.271-8.418-7.648-15.556-14.764-22.672C227.4 14.368 220.262 9.99 211.845 6.72c-8.142-3.164-17.447-5.328-31.071-5.95C167.122.147 162.763 0 128 0Zm0 62.27C91.698 62.27 62.27 91.7 62.27 128c0 36.302 29.428 65.73 65.73 65.73 36.301 0 65.73-29.428 65.73-65.73 0-36.301-29.429-65.73-65.73-65.73Zm0 108.397c-23.564 0-42.667-19.103-42.667-42.667S104.436 85.333 128 85.333s42.667 19.103 42.667 42.667-19.103 42.667-42.667 42.667Zm83.686-110.994c0 8.484-6.876 15.36-15.36 15.36-8.483 0-15.36-6.876-15.36-15.36 0-8.483 6.877-15.36 15.36-15.36 8.484 0 15.36 6.877 15.36 15.36Z\'/%3E%3C/svg%3E',
				youtube: 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'-100 -100 661 661\'%3E%3Cpath d=\'M365.257 67.393H95.744C42.866 67.393 0 110.259 0 163.137v134.728c0 52.878 42.866 95.744 95.744 95.744h269.513c52.878 0 95.744-42.866 95.744-95.744V163.137c0-52.878-42.866-95.744-95.744-95.744zm-64.751 169.663-126.06 60.123c-3.359 1.602-7.239-.847-7.239-4.568V168.607c0-3.774 3.982-6.22 7.348-4.514l126.06 63.881c3.748 1.899 3.683 7.274-.109 9.082z\' style=\'fill:%23f61c0d\'/%3E%3C/svg%3E',
			};

		// Get a YouTube video thumbnail
		imgHandlers.getYoutubeIMG = function(eURL, callback) {
			fetchDocument(eURL, ytResponse, 'json');

			function ytResponse (data)
			{
				if (!data || typeof data.thumbnail_url === 'undefined')
				{
					callback(logos.youtube);
				}
				else
				{
					callback(data.thumbnail_url);
				}
			}
		};

		// Get a twitter embed html
		imgHandlers.getTwitterEmbed = function(eURL, callback) {
			fetchDocument(eURL, twResponse, 'json');

			function twResponse (data)
			{
				if (!data || typeof data.html === 'undefined')
				{
					data.html = '';
				}

				callback(data);
			}
		};

		// Get a TikTok video thumbnail and embed data
		imgHandlers.getTikTokEmbed = function(eURL, callback) {
			fetchDocument(eURL, ttResponse, 'json');

			function ttResponse (data)
			{
				if (!data || typeof data.html === 'undefined')
				{
					data.thumbnail_url = logos.tiktok;
					data.html = '';
				}

				callback(data);
			}
		};

		// Get a Facebook video thumbnail // will not work without api codes
		imgHandlers.getFacebookEmbed = function(eURL, callback) {
			fetchDocument(eURL, fbResponse, 'json');

			function fbResponse (data)
			{
				if (!data || typeof data.thumbnail_url === 'undefined')
				{
					callback(logos.facebook);
				}
				else
				{
					callback(data.thumbnail_url);
				}
			}
		};

		// Get an Instagram video thumbnail // will not work without api codes
		imgHandlers.getInstagramEmbed = function(eURL, callback) {
			fetchDocument(eURL, instaResponse, 'json');

			function instaResponse (data)
			{
				if (!data || typeof data.thumbnail_url === 'undefined')
				{
					callback(logos.instagram);
				}
				else
				{
					callback(data.thumbnail_url);
				}
			}
		};

		// Get a dailymotion video thumbnail
		imgHandlers.getDailymotionIMG = function(eURL, callback) {
			fetchDocument(eURL, dailyResponse, 'json', false);

			function dailyResponse (data)
			{
				if (!data || typeof data.thumbnail_480_url === 'undefined')
				{
					callback(logos.dailymotion);
				}
				else
				{
					callback(data.thumbnail_480_url);
				}
			}
		};

		// Get a Vimeo video thumbnail
		imgHandlers.getVimeoIMG = function(eURL, callback) {
			fetchDocument(eURL, vimeoResponse, 'json', false);

			function vimeoResponse (data)
			{
				if (!data || typeof data[0].thumbnail_large === 'undefined')
				{
					callback(logos.vimeo);
				}
				else
				{
					callback(data[0].thumbnail_large);
				}
			}
		};

		// Youtube and variants
		handlers['youtube.com'] = function(path, a) {
			let videoID = path.match(/\bv[=/]([^&#?$]+)/i) || path.match(/#p\/(?:a\/)?[uf]\/\d+\/([^?$]+)/i) || path.match(/(?:\/)([\w-]{11})/i);

			if (!videoID || !(videoID = videoID[1]))
			{
				return;
			}

			// There are two types of YouTube timestamped links
			// http://youtu.be/lLOE3fBZcUU?t=1m37s when you click share underneath the video
			// http://youtu.be/lLOE3fBZcUU?t=97 when you right-click on a video and choose "Copy video URL at current time"
			// For embedding, you need to use "?start=97" instead, so we have to convert t=1m37s to seconds while also supporting t=97
			let startAt = path.match(/t=(?:([0-9]{1,2})h)?(?:([0-9]{1,2})m)?(?:([0-9]+)s?)?/),
				startAtPar = '';

			if (startAt && (startAt[1] || startAt[2] || startAt[3]))
			{
				let startAtSeconds = 0;

				startAtSeconds += convertToSeconds(startAt, 1, 3600);  // Hours
				startAtSeconds += convertToSeconds(startAt, 2, 60);    // Minutes
				startAtSeconds += convertToSeconds(startAt, 3, 1);     // Seconds

				startAtPar = '&start=' + startAtSeconds.toString();
			}

			let embedURL = '//www.youtube-nocookie.com/embed/' + videoID + '?rel=0' + startAtPar,
				imgURL = '//www.youtube.com/oembed?url=https://www.youtube.com/watch?v=' + videoID + '&format=json',
				tag = embedIMG(a, '//i.ytimg.com/vi/' + videoID + '/sddefault.jpg', embedURL + '&autoplay=1');

			// Get the preview image / embed tag
			imgHandlers.getYoutubeIMG(imgURL, function(img) {
				$(a).parent().next().find('img').attr('src', img);
			});

			return [oSettings.youtube, tag];
		};
		handlers['m.youtube.com'] = handlers['youtube.com'];
		handlers['youtu.be'] = handlers['youtube.com'];

		// Vimeo
		handlers['vimeo.com'] = function(path, a) {
			let videoID = path.match(/^\/(\d+)/i);

			if (!videoID || !(videoID = videoID[1]))
			{
				return;
			}

			let embedURL = '//player.vimeo.com/video/' + videoID,
				imgURL = '//vimeo.com/api/v2/video/' + videoID + '.json',
				tag;

			tag = embedIMG(a, logos.vimeo, embedURL + '?autoplay=1');

			// Get the preview image / embed tag
			imgHandlers.getVimeoIMG(imgURL, function(img) {
				$(a).parent().next().find('img').attr('src', img);
			});

			return [oSettings.vimeo, tag];
		};

		// Dailymotion
		handlers['dailymotion.com'] = function(path, a) {
			let videoID = path.match(/^\/video\/([a-z0-9]{1,18})/i);

			if (!videoID || videoID[1] === '')
			{
				return;
			}

			let embedURL = '//dailymotion.com/embed/video/' + videoID[1],
				imgURL = '//api.dailymotion.com/video/' + videoID[1] + '?fields=thumbnail_480_url',
				tag;

			tag = embedIMG(a, logos.dailymotion, embedURL + '?related=0&autoplay=1');

			// Get the preview image or embed tag
			imgHandlers.getDailymotionIMG(imgURL, function(img) {
				$(a).parent().next().find('img').attr('src', img);
			});

			return [oSettings.dailymotion, tag];
		};

		// TikTok
		handlers['tiktok.com'] = function(path, a) {
			let videoID = path.match(/^\/@([0-9A-Za-z_\-.]*)\/video\/([0-9]*)/i);

			if (!videoID)
			{
				return;
			}

			let videoURL = 'https://www.tiktok.com/@' + videoID[1] + '/video/' + videoID[2],
				embedURL = elk_prepareScriptUrl(elk_scripturl) + 'action=xmlhttp;sa=videoembed;api=json;site=tiktok;videoid=' + videoURL + ';' + elk_session_var + '=' + elk_session_id,
				tag;

			imgHandlers.getTikTokEmbed(embedURL, function(data) {
				if (typeof data.thumbnail_url !== 'undefined')
				{
					$(a).parent().next().find('img').attr('src', data.thumbnail_url);
				}
				a.embedURL = data.html;
			});

			tag = embedIMG(a, logos.tiktok, embedURL);

			// Change the default click event to one that replaces the markup
			tag.off('click', '**', false);
			tag.on('click', function(e) {
				e.preventDefault();
				let load = $(a).parent();
				load.parent().addClass('portrait');

				load.next().replaceWith('<div class="elk_video">' + a.embedURL + '</div>');
			});

			return [oSettings.tiktok, tag];
		};

		// Twitter
		handlers['twitter.com'] = function(path, a) {
			let videoID = path.match(/\/status\/([0-9]{16,20})/);

			if (!videoID || videoID[1] === '')
			{
				return;
			}

			let embedURL = elk_prepareScriptUrl(elk_scripturl) + 'action=xmlhttp;sa=videoembed;api=json;site=twitter;videoid=' + videoID[1] + ';' + elk_session_var + '=' + elk_session_id,
				tag;

			tag = embedIMG(a, logos.twitter, embedURL);

			// Twitter has its own embed codes we need to load, no preview here, replace click event as well
			tag.off('click', '**', false);
			a.embedURL = embedURL;
			a.setAttribute('data-video_embed', 'getTwitterEmbed');

			return [oSettings.twitter, tag, 'portrait'];
		};
		handlers['x.com'] = handlers['twitter.com'];

		// Facebook
		handlers['facebook.com'] = function(path, a) {
			let videoID = path.match(/([\d\w._-]+)?(?:\/videos\/|\/video.php\?v=)(\d+)/i);

			if (!videoID || videoID[1] === '' || videoID[2] === '')
			{
				return;
			}

			let embedURL = '//www.facebook.com/plugins/video.php?href=https://www.facebook.com/' + videoID[1] + '/videos/' + videoID[2],
				tag;

			tag = embedIMG(a, logos.facebook, embedURL + '?related=0&autoplay=1');

			return [oSettings.facebook, tag];
		};

		// Instagram
		handlers['instagram.com'] = function(path, a) {
			let videoID = path.match(/\/(?:tv|p)\/([a-z0-9]{10,18})(?:\/\?|\/)?/i);

			if (!videoID || videoID[1] === '')
			{
				return;
			}

			let embedURL = '//www.instagram.com/p/' + videoID[1] + '/embed',
				tag;

			tag = embedIMG(a, logos.instagram, embedURL + '?related=0&autoplay=1', false);

			return [oSettings.instagram, tag, 'portrait'];
		};

		// ---------------------------------------------------------------------------
		// Get the bbc_link links in the id="msg_1234 divs.
		let links;

		if (typeof msgid !== 'undefined')
		{
			links = document.querySelectorAll('#' + msgid + ' a.bbc_link');
		}
		else
		{
			links = document.querySelectorAll('[id^=msg_] a.bbc_link');
		}

		// Create the show/hide button
		let showhideBtn = $('' +
			'<a class="floatright" title="' + oSettings.hide_video + '">' +
			'   <i class="icon icon-small i-caret-up" alt=">"></i>' +
			'</a>')
			.on('click', function() {
				let $img = $(this).find('i'), // The open / close icon
					$vid = $(this).parent().next(); // The immediate elk_video div

				// Toggle slide the video and change the icon
				$img.attr('class', 'icon icon-small ' + ($vid.is(':hidden') !== true ? 'i-caret-down' : 'i-caret-up'));
				$vid.slideToggle();
			});

		// Loop though each link
		links.forEach((link) => {
			let tag = link,
				text = tag.innerText || tag.textContent || '';

			// Already processed?
			if (tag.classList.contains('elk_video_processed'))
			{
				return;
			}

			// Ignore in sentences
			if (tag.previousSibling && tag.previousSibling.nodeName === '#text' && tag.previousSibling.nodeValue !== ' ')
			{
				return;
			}

			// Ignore in quotes and signatures
			if ('bbc_quote;signature'.indexOf(tag.parentNode.className) !== -1)
			{
				return;
			}

			// No href or inner text not equal to href attr then we move along
			if (tag.href === '' || tag.href.indexOf(text) !== 0)
			{
				return;
			}

			// Get domain and validate we know how to handle it
			let m = tag.href.match(domain_regex),
				handler = null,
				args = null;

			// One of our video provider domains?
			if (embedded_count < oSettings.embed_limit && m !== null && typeof handlers[m[1]] !== 'undefined' && handlers[m[1]] !== null)
			{
				// Call the handler and get the tag to insert
				handler = handlers[m[1]];

				// If there are video tags seperated by only a BR node, remove the BR so the video embed can
				// be side by side on a wide enough screen.
				if (tag.previousSibling && tag.previousSibling.nodeName === 'BR')
				{
					if (tag.previousSibling.previousElementSibling && tag.previousSibling.previousElementSibling.classList.contains('elk_video_container'))
					{
						tag.previousSibling.remove();
					}
				}

				args = handler(m[2], tag, provider_class);
				if (args)
				{
					tag.classList.add('elk_video_processed');
					embedded_count++;
					$(tag).wrap('<div class="elk_video_container ' + (typeof args[2] !== 'undefined' ? args[2] : '') + '">');
					$(tag).wrap('<div class="elk_video_header">').text(args[0]).after(showhideBtn.clone(true));
					$(tag).parent().parent().append(args[1]);
				}
			}
		});

		// If we have embedded videos, add the lazy load code and events
		if (embedded_count > 0)
		{
			scrollEmbed();
		}

		/**
		 * Some sites have no thumbnail, so we mimic an onclick to load the embed when the element is on screen.  This
		 * provides something other than the default logo.
		 *
		 * Note: This does now work for all sites, like instagram, due to cors errors.  For those you need to set the onclick
		 * and let the user load the embed.
		 */
		function scrollEmbed ()
		{
			let videoLinks = document.querySelectorAll('a[data-video_embed]'),
				throttleTimeout,
				found = false;

			/**
			 * Function that fires to lazy load video sites embed when in viewport
			 */
			function videoLinksListener ()
			{
				if (throttleTimeout)
				{
					clearTimeout(throttleTimeout);
				}

				// On scroll fires "a lot" so this tames it to be less abusive
				throttleTimeout = setTimeout(function() {
					videoLinks.forEach(function(a) {
						// No links remaining, drop any listeners
						if (videoLinks.length === 0)
						{
							document.removeEventListener('scroll', videoLinksListener);
							window.removeEventListener('resize', videoLinksListener);
							window.removeEventListener('orientationChange', videoLinksListener);
						}

						// Hey I see you ...
						if (isElementInViewport(a))
						{
							let func = a.getAttribute('data-video_embed');
							found = true;
							a.removeAttribute('data-video_embed');
							imgHandlers[func](a.embedURL, (data) => {
								createFragment(a, data);
							});
						}
					});

					// Once a link is found, let's not try that one again.
					if (found)
					{
						videoLinks = document.querySelectorAll('a[data-video_embed]');
					}
				}, 25);
			}

			// Scroll, rotate or resize, we check if we can see the video link.
			document.addEventListener('scroll', videoLinksListener);
			window.addEventListener('DOMContentLoaded', videoLinksListener);
			window.addEventListener('resize', videoLinksListener);
			window.addEventListener('orientationChange', videoLinksListener);
		}
	};
})(jQuery);
