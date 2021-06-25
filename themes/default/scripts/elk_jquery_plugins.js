/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 * This file contains javascript plugins for use with jquery
 */

/*!
 * @name      ElkArte news fader
 * @copyright ElkArte Forum contributors
 * @license   MIT http://www.opensource.org/licenses/mit-license.php
 */

/**
 * Inspired by Paul Mason's tutorial:
 * http://paulmason.name/item/simple-jquery-carousel-slider-tutorial
 *
 * Licensed under the MIT license:
 * http://www.opensource.org/licenses/mit-license.php
 */
;(function($) {
	$.fn.Elk_NewsFader = function(options) {
		var settings = {
			'iFadeDelay': 5000,
			'iFadeSpeed': 1000
		},
		iFadeIndex = 0,
		$news = $(this).find('li');

		if ($news.length > 1)
		{
			settings = $.extend(settings, options);
			$news.hide();
			$news.eq(0).fadeIn(settings.iFadeSpeed);

			setInterval(function() {
				$($news[iFadeIndex]).fadeOut(settings.iFadeSpeed, function() {
					iFadeIndex++;

					if (iFadeIndex == $news.length)
						iFadeIndex = 0;

					$($news[iFadeIndex]).fadeIn(settings.iFadeSpeed);
				});
			}, settings.iFadeSpeed + settings.iFadeDelay);
		}

		return this;
	};
})(jQuery);
