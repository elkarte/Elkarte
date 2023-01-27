/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 1.1.9
 *
 * Original code from Aziz, redone and refactored for ElkArte
 */

/** global: elk_session_id, elk_session_var, elk_scripturl */

/**
 * This javascript searches the message for video links and replaces them
 * with a clickable preview thumbnail of the video.  Once the image is clicked
 * the video is embedded in to the page to play.
 *
 * Currently, works with YouTube, Vimeo, TikTok and DailyMotion
 *
 */
(function ($)
{
	'use strict';

	/**
	 * @param {object} oInstanceSettings holds the text strings to use in the html created
	 * @param {int} msgid optional to only search for links in a specific id
	 */
	$.fn.linkifyvideo = function (oInstanceSettings, msgid)
	{
		let oDefaultsSettings = {
			embed_limit: 25,
			preview_image: '',
			ctp_video: '',
			hide_video: '',
			youtube: '',
			vimeo: '',
			dailymotion: '',
			tiktok: ''
		};

		// Account for user options
		let oSettings = $.extend({}, oDefaultsSettings, oInstanceSettings || {});

		/**
		 * Replaces the image with the created embed code to show the video
		 * Called from click event attached to the image
		 *
		 * @param {string} tag anchor tag we are replacing with the embed tag
		 * @param {string} eURL the load or place source link
		 */
		function showEmbed(tag, eURL)
		{
			$(tag).html(embed_html.replace('{src}', eURL));
		}

		/**
		 * Shows the video image and sets up the link
		 * Sets click event to load video sites embed code
		 *
		 * @param {object} a videoID link
		 * @param {string} src source of image
		 * @param {string} eURLa play link
		 */
		function getIMG(a, src, eURLa)
		{
			return $('' +
				'<div class="elk_video">' +
				'   <a href="' + a.href + '">' +
				'       <img class="elk_video_preview" alt="' + oSettings.preview_image + '" ' + 'title="' + oSettings.ctp_video + '" src="' + src + '"/>' +
				'   </a>' +
				'</div>')
				.on('click', function (e)
					{
						e.preventDefault();
						let tag = this;
						showEmbed(tag, eURLa);
					}
				);
		}

		/**
		 * Returns a linked preview image.  Click on the image to load the player.
		 *
		 * @param {string} a link tag of the video
		 * @param {string} src link of the preview image
		 * @param {string} eURLa single click event play video
		 */
		function embedIMG(a, src, eURLa)
		{
			return getIMG(a, src, eURLa);
		}

		/**
		 * Creates and inserts a document fragment.  Doing this vs inner/outer HTML ensures that any script
		 * tags in the embed code will execute.
		 *
		 * @param {Element} a the link we are working with
		 * @param {object} data the data from the ajax call
		 */
		function createFragment(a, data)
		{
			// Since data.html may contain a script tag that needs to run, we have to add it like this
			let parent = a.parentNode,
				frag = document.createRange().createContextualFragment('<div class="elk_video">' + data.html + '</div>');

			parent.parentNode.appendChild(frag);
			parent.nextSibling.outerHTML = '';
		}

		// The embed code
		let domain_regex = /^[^:]*:\/\/(?:www\.)?([^\/]+)(\/.*)$/,
			embedded_count = 0,
			embed_html = '<iframe width="640" height="360" style="border: none;margin: 0 auto;aspect-ratio 16 / 9;max-width: 100%; max-height: 360px;" src="{src}" frameborder="0" data-autoplay="true" allow="fullscreen" loading="lazy" type="text/html"></iframe>',
			handlers = {},
			imgHandlers = {},
			logos = {
				tiktok: "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 48 48'%3E%3Cpath fill='%23fff' fill-opacity='.01' d='M0 0h48v48H0z'/%3E%3Cpath fill='%232F88FF' stroke='%23000' stroke-linejoin='round' stroke-width='3.833' d='M21.358 19.14c-5.888-.284-9.982 1.815-12.28 6.299-3.446 6.724-.597 17.728 10.901 17.728 11.499 0 11.831-11.111 11.831-12.276V17.876c2.46 1.557 4.533 2.495 6.221 2.813 1.688.317 2.76.458 3.219.422v-6.476c-1.561-.188-2.911-.547-4.05-1.076-1.709-.794-5.096-2.997-5.096-6.226.002.016.002-.817 0-2.499h-7.118c-.021 15.816-.021 24.502 0 26.058.032 2.334-1.779 5.6-5.45 5.6-3.672 0-5.482-3.263-5.482-5.367 0-1.288.442-3.155 2.271-4.538 1.085-.82 2.59-1.147 5.033-1.147V19.14Z'/%3E%3C/svg%3E",
				vimeo: "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 455.731 455.731'%3E%3Cpath fill='%231ab7ea' d='M0 0h455.731v455.731H0z'/%3E%3Cpath fill='%23fff' d='m49.642 157.084 17.626 22.474s22.033-17.186 29.965-17.186c4.927 0 15.423 5.729 22.033 25.558 6.61 19.83 34.441 122.62 36.134 127.351 7.607 21.26 17.626 60.811 48.473 66.54s70.065-25.558 91.657-48.473c21.592-22.914 106.64-120.741 110.165-179.349 3.26-54.191-14.517-66.765-22.474-71.828-14.542-9.254-38.778-12.338-61.692-4.407s-57.726 33.931-66.98 80.2c0 0 31.287-11.457 42.744-.441s8.373 35.253-1.322 53.32-37.015 59.93-47.151 61.252c-10.135 1.322-18.067-18.508-19.389-23.796-1.322-5.288-18.067-77.997-24.236-120.3s-33.049-49.354-45.829-49.354c-12.779.001-34.812 9.696-109.724 78.439z'/%3E%3C/svg%3E",
				dailymotion: "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'%3E%3Cpath fill='%230066DC' fill-rule='evenodd' d='M0 512h512V0H0v512Zm441.5-68.635h-76.314v-29.928c-23.443 22.945-47.385 31.424-79.308 31.424-32.421 0-60.354-10.474-83.797-31.424-30.926-27.433-46.887-63.346-46.887-105.245 0-38.407 14.965-72.823 42.896-99.758 24.94-24.44 55.367-36.91 89.284-36.91 32.422 0 57.361 10.973 75.318 33.917V88.724L441.5 72.395v370.97Zm-141.157-202.01c-37.41 0-66.339 30.426-66.339 66.338 0 37.41 28.93 65.841 69.332 65.841 33.918 0 62.349-27.932 62.349-64.843 0-38.406-28.431-67.336-65.342-67.336Z'/%3E%3C/svg%3E",
			};

		// Get a TikTok video thumbnail and embed data
		imgHandlers.getTikTokEmbed = function(eURL, callback)
		{
			$.getJSON(eURL, {format: 'json'},
			function(data)
			{
				if (typeof data.html === 'undefined')
				{
					data.thumbnail_url = logos.tiktok;
					data.html = '';
				}

				callback(data);
			});
		};

		// Get a dailymotion video thumbnail
		imgHandlers.getDailymotionIMG = function(eURL, callback)
		{
			$.getJSON(eURL, {},
			function(data)
			{
				if (typeof data.thumbnail_480_url !== 'undefined')
				{
					callback(data.thumbnail_480_url);
				}
				else
				{
					callback(logos.dailymotion);
				}
			});
		};

		// Get a Vimeo video thumbnail
		imgHandlers.getVimeoIMG = function(videoID, callback)
		{
			$.getJSON(videoID, {format: 'json'},
			function(data)
			{
				if (typeof data[0].thumbnail_large !== 'undefined')
				{
					callback(data[0].thumbnail_large);
				}
				else
				{
					callback(logos.vimeo);
				}
			});
		};

		// Youtube and variants
		handlers['youtube.com'] = function (path, a)
		{
			let videoID = path.match(/\bv[=/]([^&#?$]+)/i) || path.match(/#p\/(?:a\/)?[uf]\/\d+\/([^?$]+)/i) || path.match(/(?:\/)([\w-]{11})/i);

			if (!videoID || !(videoID = videoID[1]))
			{
				return;
			}

			// There are two types of YouTube timestamped links
			// http://youtu.be/lLOE3fBZcUU?t=1m37s when you click share underneath the video
			// http://youtu.be/lLOE3fBZcUU?t=97 when you right click on a video and choose "Copy video URL at current time"
			// For embedding, you need to use "?start=97" instead, so we have to convert t=1m37s to seconds while also supporting t=97
			let startAt = path.match(/t=(?:([1-9]{1,2})h)?(?:([1-9]{1,2})m)?(?:([1-9]+)s?)/),
				startAtPar = '';

			if (startAt)
			{
				let startAtSeconds = 0;

				// Hours
				if (typeof startAt[1] !== 'undefined')
				{
					startAtSeconds += parseInt(startAt[1]) * 3600;
				}

				// Minutes
				if (typeof startAt[2] !== 'undefined')
				{
					startAtSeconds += parseInt(startAt[2]) * 60;
				}

				// Seconds
				if (typeof startAt[3] !== 'undefined')
				{
					startAtSeconds += parseInt(startAt[3]);
				}

				startAtPar = '&start=' + startAtSeconds.toString();
			}

			let embedURL = '//www.youtube-nocookie.com/embed/' + videoID + '?rel=0' + startAtPar,
				tag = embedIMG(a, '//i.ytimg.com/vi/' + videoID + '/sddefault.jpg', embedURL + '&autoplay=1');

			return [oSettings.youtube, tag];
		};
		handlers['m.youtube.com'] = handlers['youtube.com'];
		handlers['youtu.be'] = handlers['youtube.com'];

		// Vimeo
		handlers['vimeo.com'] = function (path, a)
		{
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
			imgHandlers.getVimeoIMG(imgURL, function (img)
			{
				$(a).parent().next().find("img").attr("src", img);
			});

			return [oSettings.vimeo, tag];
		};

		// Dailymotion
		handlers['dailymotion.com'] = function (path, a)
		{
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
			imgHandlers.getDailymotionIMG(imgURL, function (img)
			{
				$(a).parent().next().find('img').attr('src', img);
			});

			return [oSettings.dailymotion, tag];
		};

		// TikTok
		handlers['tiktok.com'] = function (path, a)
		{
			let videoID = path.match(/^\/@([0-9A-Za-z_\-.]*)\/video\/([0-9]*)/i);

			if (!videoID)
			{
				return;
			}

			let embedURL = '//www.tiktok.com/oembed?url=https://www.tiktok.com/@' + videoID[1] + '/video/' + videoID[2],
				tag;

			imgHandlers.getTikTokEmbed(embedURL, function (data)
			{
				$(a).parent().next().find('img').attr('src', data.thumbnail_url);
				a.embedURL = data.html;
			});

			tag = embedIMG(a, logos.tiktok, embedURL);

			// Change the default click event to one that replaces the markup, also prepare for a 9/16 video
			tag.off('click', '**' , false);
			tag.on('click', function (e)
			{
				e.preventDefault();
				let load = $(a).parent();
				load.addClass('portrait');

				load.next().replaceWith('<div class="elk_video portrait">' + a.embedURL + '</div>');
			});

			return [oSettings.tiktok, tag];
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
			'   <i class="icon icon-small i-chevron-up" alt=">"></i>' +
			'</a>')
			.on('click', function()
			{
				let $img = $(this).find("i"), // The open / close icon
					$vid = $(this).parent().next(); // The immediate elk_video div

				// Toggle slide the video and change the icon
				$img.attr("class", "icon icon-small " + ($vid.is(":hidden") !== true ? "i-chevron-down" : "i-chevron-up"));
				$vid.slideToggle();
			});

		// Loop though each link
		links.forEach((link) =>
		{
			let tag = link,
				text = tag.innerText || tag.textContent || '';

			// Ignore in sentences
			if (tag.previousSibling && tag.previousSibling.nodeName === '#text' && tag.previousSibling.nodeValue !== ' ')
			{
				return;
			}

			// Ignore in quotes and signatures
			if ("bbc_quote;signature".indexOf(tag.parentNode.className) !== -1)
			{
				return;
			}

			// No href or inner text not equal to href attr then we move along
			if (tag.href === "" || tag.href.indexOf(text) !== 0)
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

				args = handler(m[2], tag);
				if (args)
				{
					embedded_count++;
					$(tag).wrap('<div class="elk_video_container">');
					$(tag).wrap('<div class="elk_videoheader">').text(args[0]).after(showhideBtn.clone(true));
					$(tag).parent().after(args[1]);
				}
			}
		});
	};
})(jQuery);
