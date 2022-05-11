/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 * Original code from Aziz, redone and refactored for ElkArte
 */

/**
 * This javascript searches the message for video links and replaces them
 * with a clickable preview thumbnail of the video.  Once the image is clicked
 * the video is embedded in to the page to play.
 *
 * Currently, works with YouTube, Vimeo, TikTok, and DailyMotion
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
			dailymotion: ''
		};

		// Account for user options
		let oSettings = $.extend({}, oDefaultsSettings, oInstanceSettings || {});

		/**
		 * Takes the generic embed tag and inserts the proper video source
		 *
		 * @param {string} source source string to use in the embed src call
		 */
		function getEmbed(source)
		{
			return embed_html.replace('{src}', source);
		}

		/**
		 * Replaces the image with the created embed code to show the video
		 * Called from click / double click events attached to the image
		 *
		 * @param {string} tag anchor tag we are replacing with the embed tag
		 * @param {string} eURL the load or place source link
		 */
		function showFlash(tag, eURL)
		{
			$(tag).html(getEmbed(eURL));
		}

		/**
		 * Shows the video image and sets up the link
		 * Sets up single click event to play videoID and double click to load it
		 *
		 * @param {object} a videoID link
		 * @param {string} src source of image
		 * @param {string} eURL load link
		 * @param {string} eURLa play link
		 */
		function getIMG(a, src, eURL, eURLa)
		{
			return $('' +
				'<div class="elk_video">' +
					'<a href="' + a.href + '">' +
						'<img class="elk_video_preview" alt="' + oSettings.preview_image + '" ' + 'title="' + oSettings.ctp_video + '" src="' + src + '"/>' +
					'</a>' +
				'</div>')
				.on('dblclick', function (e)
				{
					// double click loads the video but does not autoplay
					e.preventDefault();

					// clear the single click event
					clearTimeout(this.tagID);
					showFlash(this, eURL);
				})
				.on('click', function (e)
				{
					// single click to begin playing the video
					e.preventDefault();
					let tag = this;

					this.tagID = setTimeout(function ()
					{
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
			let img = 'assets.vimeo.com/images/logo_vimeo_land.png';

			$.getJSON('http://www.vimeo.com/api/v2/video/' + videoID + '.json?callback=?', {format: "json"},
				function (data)
				{
					if (typeof data[0].thumbnail_large !== "undefined")
					{
						callback(data[0].thumbnail_large);
					}
					else
					{
						callback(img);
					}
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
			let img = 'assets.vimeo.com/images/logo_vimeo_land.png';

			$.getJSON('https://api.dailymotion.com/video/' + videoID + '?fields=thumbnail_480_url', {},
				function (data)
				{
					if (typeof data.thumbnail_480_url !== "undefined")
					{
						callback(data.thumbnail_480_url);
					}
					else
					{
						callback(img);
					}
				}
			);
		}

		/**
		 * Get a TikTok video thumbnail and associated data
		 *
		 * @param {array} videoID
		 * @param {string} callback function to call once the json results are returned.
		 */
		function getTikTokIMG(videoID, callback)
		{
			let img = 'sf-tb-sg.ibytedtos.com/obj/ttfe-malisg/tiktok-logo.png';

			$.getJSON('//www.tiktok.com/oembed?url=https://www.tiktok.com/@' + videoID[1] + '/video/' + videoID[2], {},
				function (data)
				{
					if (typeof data.thumbnail_url !== "undefined")
					{
						callback(data);
					}
					else
					{
						callback(img);
					}
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

		let domain_regex = /^[^:]*:\/\/(?:www\.)?([^\/]+)(\/.*)$/,
			embedded_count = 0,
			embed_html = '<iframe width="640" height="360" src="{src}" allow="fullscreen" type="text/html"></iframe>',
			handlers = {};

		// Youtube and variants link handler
		handlers['youtube.com'] = function (path, a, embed)
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
				tag = embedOrIMG(embed, a, '//i.ytimg.com/vi/' + videoID + '/sddefault.jpg', embedURL, embedURL + '&autoplay=1');
			return [oSettings.youtube, tag];
		};
		handlers['m.youtube.com'] = handlers['youtube.com'];
		handlers['youtu.be'] = handlers['youtube.com'];

		// Vimeo link handler
		handlers['vimeo.com'] = function (path, a, embed)
		{
			let videoID = path.match(/^\/(\d+)/i);
			if (!videoID || !(videoID = videoID[1]))
			{
				return;
			}

			let embedURL = '//player.vimeo.com/video/' + videoID,
				tag,
				img = '//assets.vimeo.com/images/logo_vimeo_land.png';

			// Get the preview image or embed tag
			if (!embed)
			{
				// We need to use a callback to get the thumbnail
				getVimeoIMG(videoID, function (img)
				{
					$(a).parent().next().find("img").attr("src", img);
				});

				// This is to show something while we wait for our callback to return
				tag = embedOrIMG(embed, a, img, embedURL, embedURL + '?autoplay=1');
			}
			else
			{
				tag = embedOrIMG(embed, a, img, embedURL, embedURL + '?autoplay=1');
			}

			return [oSettings.vimeo, tag];
		};

		// Dailymotion link handler
		handlers['dailymotion.com'] = function (path, a, embed)
		{
			let videoID = path.match(/^\/(?:video|swf)\/([a-z0-9]{1,18})/i);

			if (!videoID || !(videoID = videoID[1]))
			{
				return;
			}

			let embedURL = '//dailymotion.com/embed/video/' + videoID,
				tag,
				img = '//dailymotion.com/thumbnail/video/' + videoID;

			// Get the preview image or embed tag
			if (!embed)
			{
				// We use a callback to get the thumbnail we want
				getDailymotionIMG(videoID, function (img)
				{
					$(a).parent().next().find("img").attr("src", img);
				});

				// This is to show something while we wait for our callback to return
				tag = embedOrIMG(embed, a, img, embedURL, embedURL + '?related=0&autoplay=1');
			}
			else
			{
				tag = embedOrIMG(embed, a, img, embedURL + '?related=0', embedURL + '?related=0&autoplay=1');
			}

			return [oSettings.dailymotion, tag];
		};

		// tiktok link handler
		handlers['tiktok.com'] = function (path, a, embed)
		{
			let videoID = path.match(/^\/@([0-9A-Za-z_]*)\/video\/([0-9]*)/i);

			if (!videoID)
			{
				return;
			}

			let embedURL = 'https://www.tiktok.com/' + videoID[1] + '/video/' + videoID[2],
				tag,
				img = '//sf-tb-sg.ibytedtos.com/obj/ttfe-malisg/tiktok-logo.png',
				data = {};

			// Get the preview image or embed tag
			if (!embed)
			{
				// We use a callback to get the html.  Normally we get the preview image and use a
				// href on the image, but TikTok has its own embed jazz-o-matic
				getTikTokIMG(videoID, function (data)
				{
					$(a).parent().next().replaceWith('<div class=elk_video">' + data.html + '</div>');
				});

				// This is to show something while we wait for our callback to return
				tag = embedOrIMG(embed, a, img, embedURL, embedURL);
			}
			else
			{
				tag = embedOrIMG(embed, a, img, embedURL, embedURL);
			}

			return [oSettings.tiktok, tag];
		};

		// Get the links in the id="msg_1234 divs.
		let links;

		if (typeof msgid !== "undefined")
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
				'<i class="icon icon-small i-caret-up" alt=">"></i>' +
			'</a>')
			.on('click', function () {
				let $img = $(this).find("i"), // The open / close icon
					$vid = $(this).parent().next(); // The immediate elk_video div

				// Toggle slide the video and change the icon
				$img.attr("class", "icon icon-small " + ($vid.is(":hidden") !== true ? "i-caret-down" : "i-caret-up"));
				$vid.slideToggle();
			});

		// Loop though each link old skool style for speed
		links.forEach((link) =>
		{
			let tag = link,
				text = tag.innerText || tag.textContent || "";

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
			if (embedded_count < oSettings.embed_limit && m !== null && typeof handlers[m[1]] !== "undefined" && handlers[m[1]] !== null)
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

				args = handler(m[2], tag, false);
				if (args)
				{
					embedded_count++;
					$(tag).wrap('<div class="elk_video_container">');
					$(tag).wrap('<div class="elk_video_header">').text(args[0]).after(showhideBtn.clone(true));
					$(tag).parent().parent().append(args[1]);
				}
			}
		});
	};
})(jQuery);
