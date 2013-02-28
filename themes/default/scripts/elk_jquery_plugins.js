/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 * This file contains javascript plugins for use with jquery
 */

/**
 * SiteTooltip, Basic JQuery function to provide styled tooltips
 *
 * - will use the hoverintent plugin if available
 * - shows the tooltip in a div with the class defined in tooltipClass
 * - moves all selector titles to a hidden div and removes the title attribute to
 *   prevent any default browser actions
 * - attempts to keep the tooltip on screen
 *
 */
 (function($) {
	'use strict';
	$.fn.SiteTooltip = function(oInstanceSettings) {
		$.fn.SiteTooltip.oDefaultsSettings = {
			followMouse: 1,
			hoverIntent: {sensitivity: 10, interval: 300, timeout: 50},
			positionTop: 12,
			positionLeft: 12,
			tooltipID: 'site_tooltip', // ID used on the outer div
			tooltipTextID: 'site_tooltipText', // as above but on the inner div holding the text
			tooltipClass: 'tooltip', // The class applied to the outer div (that displays on hover), use this in your css
			tooltipSwapClass: 'site_swaptip', // a class only used internally, change only if you have a conflict
			tooltipContent: 'html' // display captured title text as html or text
		};

		// account for any user options
		var oSettings = $.extend({}, $.fn.SiteTooltip.oDefaultsSettings , oInstanceSettings || {});

		// move passed selector titles to a hidden span, then remove the selector title to prevent any default browser actions
		$(this).each(function()
		{
			var sTitle = $('<span class="' + oSettings.tooltipSwapClass + '">' + htmlspecialchars(this.title) + '</span>').hide();
			$(this).append(sTitle).attr('title', '');
		});

		// determine where we are going to place the tooltip, while trying to keep it on screen
		var positionTooltip = function(event)
		{
			var iPosx = 0,
				iPosy = 0;

			if (!event)
				event = window.event;

			if (event.pageX || event.pageY)
			{
				iPosx = event.pageX;
				iPosy = event.pageY;
			}
			else if (event.clientX || event.clientY)
			{
				iPosx = event.clientX + document.body.scrollLeft + document.documentElement.scrollLeft;
				iPosy = event.clientY + document.body.scrollTop + document.documentElement.scrollTop;
			}

			// Position of the tooltip top left corner and its size
			var oPosition = {
				x: iPosx + oSettings.positionLeft,
				y: iPosy + oSettings.positionTop,
				w: $('#' + oSettings.tooltipID).width(),
				h: $('#' + oSettings.tooltipID).height()
			};

			// Display limits and window scroll postion
			var oLimits = {
				x: $(window).scrollLeft(),
				y: $(window).scrollTop(),
				w: $(window).width() - 24,
				h: $(window).height() - 24
			};

			// don't go off screen with our tooltop
			if ((oPosition.y + oPosition.h > oLimits.y + oLimits.h) && (oPosition.x + oPosition.w > oLimits.x + oLimits.w))
			{
				oPosition.x = (oPosition.x - oPosition.w) - 45;
				oPosition.y = (oPosition.y - oPosition.h) - 45;
			}
			else if ((oPosition.x + oPosition.w) > (oLimits.x + oLimits.w))
			{
				oPosition.x = oPosition.x - (((oPosition.x + oPosition.w) - (oLimits.x + oLimits.w)) + 24);
			}
			else if (oPosition.y + oPosition.h > oLimits.y + oLimits.h)
			{
				oPosition.y = oPosition.y - (((oPosition.y + oPosition.h) - (oLimits.y + oLimits.h)) + 24);
			}

			// finally set the position we determined
			$('#' + oSettings.tooltipID).css({'left': oPosition.x + 'px', 'top': oPosition.y + 'px'});
		};

		// used to show a tooltip
		var showTooltip = function(){
			$('#' + oSettings.tooltipID + ' #' + oSettings.tooltipTextID).show();
		};

		// used to hide a tooltip
		var hideTooltip = function(){
			$('#' + oSettings.tooltipID).fadeOut('slow').trigger("unload").remove();
		};

		// used to keep html encoded
		function htmlspecialchars(string)
		{
			return $('<span>').text(string).html();
		}

		// for all of the elements that match the selector on the page, lets set up some actions
		return this.each(function()
		{
			// if we find hoverIntent use it
			if ($.fn.hoverIntent)
			{
				$(this).hoverIntent({
					sensitivity: oSettings.hoverIntent.sensitivity,
					interval: oSettings.hoverIntent.interval,
					over: site_tooltip_on,
					timeout: oSettings.hoverIntent.timeout,
					out: site_tooltip_off
				});
			}
			else
			{
				// plain old hover it is
				$(this).hover(site_tooltip_on, site_tooltip_off);
			}

			// create the on tip action
			function site_tooltip_on(event)
			{
				// If we have text in the hidden span element we created on page load
				if ($(this).children('.' + oSettings.tooltipSwapClass).text())
				{
					// create a ID'ed div with our style class that holds the tooltip info, hidden for now
					$('body').append('<div id="' + oSettings.tooltipID + '" class="' + oSettings.tooltipClass + '"><div id="' + oSettings.tooltipTextID + '" style="display:none;"></div></div>');

					// load information in to our newly created div
					var tt = $('#' + oSettings.tooltipID);
					var ttContent = $('#' + oSettings.tooltipID + ' #' + oSettings.tooltipTextID);

					if (oSettings.tooltipContent === 'html')
						ttContent.html($(this).children('.' + oSettings.tooltipSwapClass).html());
					else
						ttContent.text($(this).children('.' + oSettings.tooltipSwapClass).text());

					// show then position or it may postion off screen
					tt.show();
					showTooltip();
					positionTooltip(event);
				}

				return false;
			}

			// create the Bye bye tip
			function site_tooltip_off(event)
			{
				hideTooltip(this);
				return false;
			}

			// create the tip move with the cursor
			if (oSettings.followMouse)
			{
				$(this).bind("mousemove", function(event){
					positionTooltip(event);
					return false;
				});
			}

			// clear the tip on a click
			$(this).bind("click", function(){
				hideTooltip(this);
				return true;
			});

		});
	};
})(jQuery);

/**
 * hoverIntent is similar to jQuery's built-in "hover" function except that
 * instead of firing the onMouseOver event immediately, hoverIntent checks
 * to see if the user's mouse has slowed down (beneath the sensitivity
 * threshold) before firing the onMouseOver event.
 *
 * hoverIntent r6 // 2011.02.26 // jQuery 1.5.1+
 * <http://cherne.net/brian/resources/jquery.hoverIntent.html>
 *
 * hoverIntent is currently available for use in all personal or commercial
 * project under MIT license.
 *
 * // basic usage (just like .hover) receives onMouseOver and onMouseOut functions
 * $("ul li").hoverIntent( showNav , hideNav );
 *
 * // advanced usage receives configuration object only
 * $("ul li").hoverIntent({
 *	sensitivity: 7, // number = sensitivity threshold (must be 1 or higher)
 *	interval: 100,   // number = milliseconds of polling interval
 *	over: showNav,  // function = onMouseOver callback (required)
 *	timeout: 0,   // number = milliseconds delay before onMouseOut function call
 *	out: hideNav    // function = onMouseOut callback (required)
 * });
 *
 * @param  f  onMouseOver function || An object with configuration options
 * @param  g  onMouseOut function  || Nothing (use configuration options object)
 * @param  filter support for event delegation.
 * @author    Brian Cherne brian(at)cherne(dot)net
 */
;(function($) {
	'use strict';
	$.fn.hoverIntent = function (f, g, filter) {
		// default configuration options
		var cfg = {
			sensitivity: 8,
			interval: 50,
			timeout: 1
		};

		// override configuration options with user supplied object
		cfg = $.extend(cfg, g ? {over: f, out: g} : f);

		// instantiate variables
		// cX, cY = current X and Y position of mouse, updated by mousemove event
		// pX, pY = previous X and Y position of mouse, set by mouseover and polling interval
		var cX, cY,
			pX, pY;

		// A private function for getting mouse position
		var track = function (ev) {
			cX = ev.pageX;
			cY = ev.pageY;
		};

		// A private function for comparing current and previous mouse position
		var compare = function (ev, ob) {
			ob.hoverIntent_t = clearTimeout(ob.hoverIntent_t);

			// compare mouse positions to see if they've crossed the threshold
			if((Math.abs(pX-cX) + Math.abs(pY-cY) ) < cfg.sensitivity)
			{
				$(ob).unbind("mousemove", track);

				// set hoverIntent state to true (so mouseOut can be called)
				ob.hoverIntent_s = 1;
				return cfg.over.apply(ob,[ev]);
			}
			else
			{
				// set previous coordinates for next time
				pX = cX;
				pY = cY;

				// use self-calling timeout, guarantees intervals are spaced out properly (avoids JavaScript timer bugs)
				ob.hoverIntent_t = setTimeout( function(){compare(ev, ob);} , cfg.interval );
			}
		};

		// A private function for delaying the mouseOut function
		var delay = function(ev,ob) {
			ob.hoverIntent_t = clearTimeout(ob.hoverIntent_t);
			ob.hoverIntent_s = 0;

			return cfg.out.apply(ob,[ev]);
		};

		// A private function for handling mouse 'hovering'
		var handleHover = function(e)
		{
			// copy objects to be passed into t (required for event object to be passed in IE)
			var ev = jQuery.extend({},e);
			var ob = this;

			// cancel hoverIntent timer if it exists
			if(ob.hoverIntent_t)
			{
				ob.hoverIntent_t = clearTimeout(ob.hoverIntent_t);
			}

			// if e.type == "mouseenter"
			if(e.type === "mouseenter")
			{
				// set "previous" X and Y position based on initial entry point
				pX = ev.pageX;
				pY = ev.pageY;

				// update "current" X and Y position based on mousemove
				$(ob).bind("mousemove",track);

				// start polling interval (self-calling timeout) to compare mouse coordinates over time
				if (ob.hoverIntent_s !== 1)
				{
					ob.hoverIntent_t = setTimeout(function(){compare(ev,ob);}, cfg.interval);
				}
			// else e.type == "mouseleave"
			}
			else
			{
				// unbind expensive mousemove event
				$(ob).unbind("mousemove", track);

				// if hoverIntent state is true, then call the mouseOut function after the specified delay
				if(ob.hoverIntent_s === 1)
				{
					ob.hoverIntent_t = setTimeout(function(){delay(ev,ob);}, cfg.timeout);
				}
			}
		};

		// bind the function to the two event listeners
		if(filter)
			return $(this).on({
				'mouseenter': handleHover,
				'mouseleave': handleHover
			}, filter);
		else
			return this.bind('mouseenter', handleHover).bind('mouseleave', handleHover);
	};
})(jQuery);

/**
 * Superfish v1.5.8 - jQuery menu widget
 * Copyright (c) 2013 Joel Birch
 *
 * Licensed under the MIT license:
 * http://www.opensource.org/licenses/mit-license.php
 *
 * CHANGELOG: http://users.tpg.com.au/j_birch/plugins/superfish/changelog.txt
 *
 * PLEASE READ THE FOLLOWING BEFORE PLAYING AROUND WITH ANYTHING
 * This is Derivative code for use in Elkarte
 *
 * Code has been tweaked to give responsive menus without compromising a11y.
 * If contemplating changes, testing for full functionality is essential or a11y will be degraded.
 * Since a11y is the whole point of this system, degradation is not at all desirable regardless of personal preferences.
 * If you do not understand the a11y advantages of this system, please ask before making changes.
 *
 * Full functionality means:
 * 1/ hoverIntent plugin functions so that drop menus do NOT open or close instantly when cursor touches first level anchor.
 * 2/ The drop menus should only open when the cursor actually stops on the first level anchor, or is moving very slowly.
 * 3/ There should be a delay before the drop menus close on mouseout, for people with less than perfect tracking ability.
 * 4/ The drop menus must remain fully accessible via keyboard navigation (eg: the Tab key).
 */

;(function($){
	'use strict';
	$.fn.superfish = function(op) {
		var sf = $.fn.superfish,
			c = sf.c,
			over = function(e) {
				var $this = $(this),
					menu = getMenu($this);

				clearTimeout(menu.sfTimer);
				$this.showSuperfishUl().siblings().hideSuperfishUl();
			},
			out = function(e) {
				var $this = $(this),
					menu = getMenu($this),
					o = sf.op;

				var close = function() {
					$this.hideSuperfishUl();
					if (o.$path.length && $this.parents('li.' + o.hoverClass).length < 1)
					{
						$.proxy(over, o.$path, e)();
					}
				};

				if (e.type !== 'mouseleave' && e.type !== 'focusout')
				{
					clearTimeout(menu.sfTimer);
					close();
				}
				else
				{
					clearTimeout(menu.sfTimer);
					menu.sfTimer = setTimeout(close, o.delay);
				}
			},
			getMenu = function($child) {
				var menu = $child.closest('.' + c.menuClass)[0];
				sf.op = sf.o[menu.serial];
				return menu;
			},
			applyHandlers = function($menu) {
				var targets = 'li:has(ul)';
				if (!sf.op.useClick)
				{
					if ($.fn.hoverIntent && !sf.op.disableHI)
					{
						$menu.hoverIntent(over, out, targets);
					}
					else
					{
						$menu
							.on('mouseenter', targets, over)
							.on('mouseleave', targets, out);
					}
				}

				$menu
					.on('focusin', targets, over)
					.on('focusout', targets, out)
					.on('click', 'a', clickHandler)
					.on('touchstart', 'a', touchHandler);
			},
			touchHandler = function() {
				var $this = $(this);

				if (!$this.next('ul').is(':visible'))
				{
					$(this).data('follow', false);
				}
			},
			clickHandler = function(e) {
				var $a = $(this),
					$submenu = $a.next('ul'),
					follow = ($a.data('follow') === false) ? false : true;

				if ($submenu.length && (sf.op.useClick || !follow))
				{
					e.preventDefault();

					if (!$submenu.is(':visible'))
					{
						$.proxy(over, $a.parent(), e)();
					}
					else if (sf.op.useClick && follow)
					{
						$.proxy(out, $a.parent(), e)();
					}
				}
			};

		return this.addClass(c.menuClass).each(function() {
			var s = this.serial = sf.o.length,
			    o = $.extend({}, sf.defaults,op),
				$this = $(this),
				liHasUl = $this.find("li ul").parent();

			o.$path = $this.find('li.' + o.pathClass).slice(0, o.pathLevels).each(function() {
				$(this).addClass(o.hoverClass + ' ' + c.bcClass)
					.filter('li:has(ul)').removeClass(o.pathClass);
			});
			sf.o[s] = sf.op = o;

			applyHandlers($this);
			liHasUl.not('.' + c.bcClass).hideSuperfishUl();
		});
	};

	var sf = $.fn.superfish;
	sf.o = [];
	sf.op = {};
	sf.c = {
		bcClass     : 'sf-breadcrumb',
		menuClass   : 'sf-js-enabled',
		anchorClass : 'sf-with-ul'
	};
	sf.defaults = {
		hoverClass	: 'sfhover',
		pathClass	: 'overideThisToUse',
		pathLevels	: 1,
		delay		: 600,
		animation	: {opacity:'show', height:'show', width:'show'},
		speed		: '150',
		disableHI	: false,		// true disables hoverIntent detection
		useClick	: false,
	};

	$.fn.extend({
		hideSuperfishUl : function() {
			var o = sf.op,
				$this = this;

			$(['li.', o.hoverClass].join(''), this).add(this).removeClass(o.hoverClass).find('>ul').hide();

			if (sf.op.useClick)
			{
				$this.children('a').data('follow', false);
			}
			return this;
		},
		showSuperfishUl : function() {
			var o = sf.op,
				$this = this,
				$ul = this.addClass(o.hoverClass).find('>ul:hidden').css('opacity', 1);

			$ul.stop().animate(o.animation, o.speed, function() {
				$this.children('a').data('follow', true);
			});

			return this;
		}
	});
})(jQuery);

/**
 * AnimaDrag
 * Animated jQuery Drag and Drop Plugin
 * Version 0.5.1 beta
 * Author Abel Mohler
 * Released with the MIT License: http://www.opensource.org/licenses/mit-license.php
 */
(function($){
	$.fn.animaDrag = function(o, callback) {
		var defaults = {
			speed: 400,
			interval: 300,
			easing: null,
			cursor: 'move',
			boundary: document.body,
			grip: null,
			overlay: true,
			after: function(e) {},
			during: function(e) {},
			before: function(e) {},
			afterEachAnimation: function(e) {}
		}
		if(typeof callback == 'function') {
				defaults.after = callback;
		}
		o = $.extend(defaults, o || {});
		return this.each(function() {
			var id, startX, startY, draggableStartX, draggableStartY, dragging = false, Ev, draggable = this,
			grip = ($(this).find(o.grip).length > 0) ? $(this).find(o.grip) : $(this);
			if(o.boundary) {
				var limitTop = $(o.boundary).offset().top, limitLeft = $(o.boundary).offset().left,
				limitBottom = limitTop + $(o.boundary).innerHeight(), limitRight = limitLeft + $(o.boundary).innerWidth();
			}
			grip.mousedown(function(e) {
				o.before.call(draggable, e);

				var lastX, lastY;
				dragging = true;

				Ev = e;

				startX = lastX = e.pageX;
				startY = lastY = e.pageY;
				draggableStartX = $(draggable).offset().left;
				draggableStartY = $(draggable).offset().top;

				$(draggable).css({
					position: 'absolute',
					left: draggableStartX + 'px',
					top: draggableStartY + 'px',
					cursor: o.cursor,
					zIndex: '1010'
				}).addClass('anima-drag').appendTo(document.body);
				if(o.overlay && $('#anima-drag-overlay').length == 0) {
					$('<div id="anima-drag-overlay"></div>').css({
						position: 'absolute',
						top: '0',
						left: '0',
						zIndex: '1000',
						width: $(document.body).outerWidth() + 'px',
						height: $(document.body).outerHeight() + 'px'
					}).appendTo(document.body);
				}
				else if(o.overlay) {
					$('#anima-drag-overlay').show();
				}
				id = setInterval(function() {
					if(lastX != Ev.pageX || lastY != Ev.pageY) {
						var positionX = draggableStartX - (startX - Ev.pageX), positionY = draggableStartY - (startY - Ev.pageY);
						if(positionX < limitLeft && o.boundary) {
							positionX = limitLeft;
						}
						else if(positionX + $(draggable).innerWidth() > limitRight && o.boundary) {
							positionX = limitRight - $(draggable).outerWidth();
						}
						if(positionY < limitTop && o.boundary) {
							positionY = limitTop;
						}
						else if(positionY + $(draggable).innerHeight() > limitBottom && o.boundary) {
							positionY = limitBottom - $(draggable).outerHeight();
						}
						$(draggable).stop().animate({
							left: positionX + 'px',
							top: positionY + 'px'
						},                        
						{
                            duration:o.speed,
                            easing:o.easing,
                            step:function(){o.afterEachAnimation.call(draggable, Ev)}
                        });
					}
					lastX = Ev.pageX;
					lastY = Ev.pageY;
				}, o.interval);
				(e.preventDefault());
			});
			$(document).mousemove(function(e) {
				if(dragging) {
					Ev = e;
					o.during.call(draggable, e);
				}
			});
			$(document).mouseup(function(e) {
				if(dragging) {
					$(draggable).css({
						cursor: '',
						zIndex: '990'
					}).removeClass('anima-drag');
					$('#anima-drag-overlay').hide().appendTo(document.body);
					clearInterval(id);
					o.after.call(draggable, e);
					dragging = false;
				}
			});
		});
	}
})(jQuery);