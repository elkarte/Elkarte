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
			hoverIntent: {sensitivity: 10, interval: 750, timeout: 50},
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

/*!
 * hoverIntent r7 // 2013.03.11 // jQuery 1.9.1+
 * http://cherne.net/brian/resources/jquery.hoverIntent.html
 *
 * You may use hoverIntent under the terms of the MIT license. Basically that
 * means you are free to use hoverIntent as long as this header is left intact.
 * Copyright 2007, 2013 Brian Cherne
 */

/* hoverIntent is similar to jQuery's built-in "hover" method except that
 * instead of firing the handlerIn function immediately, hoverIntent checks
 * to see if the user's mouse has slowed down (beneath the sensitivity
 * threshold) before firing the event. The handlerOut function is only
 * called after a matching handlerIn.
 *
 * // basic usage ... just like .hover()
 * .hoverIntent( handlerIn, handlerOut )
 * .hoverIntent( handlerInOut )
 *
 * // basic usage ... with event delegation!
 * .hoverIntent( handlerIn, handlerOut, selector )
 * .hoverIntent( handlerInOut, selector )
 *
 * // using a basic configuration object
 * .hoverIntent( config )
 *
 * @param  handlerIn   function OR configuration object
 * @param  handlerOut  function OR selector for delegation OR undefined
 * @param  selector    selector OR undefined
 * @author Brian Cherne <brian(at)cherne(dot)net>
 */

;(function($) {
    $.fn.hoverIntent = function(handlerIn,handlerOut,selector) {

        // default configuration values
        var cfg = {
            interval: 50,
            sensitivity: 8,
            timeout: 1
        };

        if ( typeof handlerIn === "object" ) {
            cfg = $.extend(cfg, handlerIn );
        } else if ($.isFunction(handlerOut)) {
            cfg = $.extend(cfg, { over: handlerIn, out: handlerOut, selector: selector } );
        } else {
            cfg = $.extend(cfg, { over: handlerIn, out: handlerIn, selector: handlerOut } );
        }

        // instantiate variables
        // cX, cY = current X and Y position of mouse, updated by mousemove event
        // pX, pY = previous X and Y position of mouse, set by mouseover and polling interval
        var cX, cY, pX, pY;

        // A private function for getting mouse position
        var track = function(ev) {
            cX = ev.pageX;
            cY = ev.pageY;
        };

        // A private function for comparing current and previous mouse position
        var compare = function(ev,ob) {
            ob.hoverIntent_t = clearTimeout(ob.hoverIntent_t);
            // compare mouse positions to see if they've crossed the threshold
            if ( ( Math.abs(pX-cX) + Math.abs(pY-cY) ) < cfg.sensitivity ) {
                $(ob).off("mousemove.hoverIntent",track);
                // set hoverIntent state to true (so mouseOut can be called)
                ob.hoverIntent_s = 1;
                return cfg.over.apply(ob,[ev]);
            } else {
                // set previous coordinates for next time
                pX = cX; pY = cY;
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
        var handleHover = function(e) {
            // copy objects to be passed into t (required for event object to be passed in IE)
            var ev = jQuery.extend({},e);
            var ob = this;

            // cancel hoverIntent timer if it exists
            if (ob.hoverIntent_t) { ob.hoverIntent_t = clearTimeout(ob.hoverIntent_t); }

            // if e.type == "mouseenter"
            if (e.type == "mouseenter") {
                // set "previous" X and Y position based on initial entry point
                pX = ev.pageX; pY = ev.pageY;
                // update "current" X and Y position based on mousemove
                $(ob).on("mousemove.hoverIntent",track);
                // start polling interval (self-calling timeout) to compare mouse coordinates over time
                if (ob.hoverIntent_s != 1) { ob.hoverIntent_t = setTimeout( function(){compare(ev,ob);} , cfg.interval );}

                // else e.type == "mouseleave"
            } else {
                // unbind expensive mousemove event
                $(ob).off("mousemove.hoverIntent",track);
                // if hoverIntent state is true, then call the mouseOut function after the specified delay
                if (ob.hoverIntent_s == 1) { ob.hoverIntent_t = setTimeout( function(){delay(ev,ob);} , cfg.timeout );}
            }
        };

        // listen for mouseenter and mouseleave
        return this.on({'mouseenter.hoverIntent':handleHover,'mouseleave.hoverIntent':handleHover}, cfg.selector);
    };
})(jQuery);

/*
 * Superfish v1.7.2 - jQuery menu widget
 * Copyright (c) 2013 Joel Birch
 *
 * Licensed under the MIT license:
 * http://www.opensource.org/licenses/mit-license.php
 */

;(function($) {
	"use strict";

	var methods = (function(){
		// private properties and methods go here
		var c = {
				bcClass: 'sf-breadcrumb',
				menuClass: 'sf-js-enabled',
				anchorClass: 'sf-with-ul',
				menuArrowClass: 'sf-arrows'
			},
			ios = (function(){
				var ios = /iPhone|iPad|iPod/i.test(navigator.userAgent);
				if (ios) {
					// iOS clicks only bubble as far as body children
					$(window).load(function() {
						$('body').children().on('click', $.noop);
					});
				}
				return ios;
			})(),
			wp7 = (function() {
				var style = document.documentElement.style;
				return ('behavior' in style && 'fill' in style && /iemobile/i.test(navigator.userAgent));
			})(),
			toggleMenuClasses = function($menu, o) {
				var classes = c.menuClass;
				if (o.cssArrows) {
					classes += ' ' + c.menuArrowClass;
				}
				$menu.toggleClass(classes);
			},
			setPathToCurrent = function($menu, o) {
				return $menu.find('li.' + o.pathClass).slice(0, o.pathLevels)
					.addClass(o.hoverClass + ' ' + c.bcClass)
						.filter(function() {
							return ($(this).children('ul').hide().show().length);
						}).removeClass(o.pathClass);
			},
			toggleAnchorClass = function($li) {
				$li.children('a').toggleClass(c.anchorClass);
			},
			toggleTouchAction = function($menu) {
				var touchAction = $menu.css('ms-touch-action');
				touchAction = (touchAction === 'pan-y') ? 'auto' : 'pan-y';
				$menu.css('ms-touch-action', touchAction);
			},
			applyHandlers = function($menu,o) {
				var targets = 'li:has(ul)';
				if ($.fn.hoverIntent && !o.disableHI) {
					$menu.hoverIntent(over, out, targets);
				}
				else {
					$menu
						.on('mouseenter.superfish', targets, over)
						.on('mouseleave.superfish', targets, out);
				}
				var touchevent = 'MSPointerDown.superfish';
				if (!ios) {
					touchevent += ' touchend.superfish';
				}
				if (wp7) {
					touchevent += ' mousedown.superfish';
				}
				$menu
					.on('focusin.superfish', 'li', over)
					.on('focusout.superfish', 'li', out)
					.on(touchevent, 'a', touchHandler);
			},
			touchHandler = function(e) {
				var $this = $(this),
					$ul = $this.siblings('ul');

				if ($ul.length > 0 && $ul.is(':hidden')) {
					$this.one('click.superfish', false);
					if (e.type === 'MSPointerDown') {
						$this.trigger('focus');
					} else {
						$.proxy(over, $this.parent('li'))();
					}
				}
			},
			over = function() {
				var $this = $(this),
					o = getOptions($this);
				clearTimeout(o.sfTimer);
				$this.siblings().superfish('hide').end().superfish('show');
			},
			out = function() {
				var $this = $(this),
					o = getOptions($this);
				if (ios) {
					$.proxy(close, $this, o)();
				}
				else {
					clearTimeout(o.sfTimer);
					o.sfTimer = setTimeout($.proxy(close, $this, o), o.delay);
				}
			},
			close = function(o) {
				o.retainPath = ( $.inArray(this[0], o.$path) > -1);
				this.superfish('hide');

				if (!this.parents('.' + o.hoverClass).length) {
					o.onIdle.call(getMenu(this));
					if (o.$path.length) {
						$.proxy(over, o.$path)();
					}
				}
			},
			getMenu = function($el) {
				return $el.closest('.' + c.menuClass);
			},
			getOptions = function($el) {
				return getMenu($el).data('sf-options');
			};

		return {
			// public methods
			hide: function(instant) {
				if (this.length) {
					var $this = this,
						o = getOptions($this);
						if (!o) {
							return this;
						}
					var not = (o.retainPath === true) ? o.$path : '',
						$ul = $this.find('li.' + o.hoverClass).add(this).not(not).removeClass(o.hoverClass).children('ul'),
						speed = o.speedOut;

					if (instant) {
						$ul.show();
						speed = 0;
					}
					o.retainPath = false;
					o.onBeforeHide.call($ul);
					$ul.stop(true, true).animate(o.animationOut, speed, function() {
						var $this = $(this);
						o.onHide.call($this);
					});
				}
				return this;
			},
			show: function() {
				var o = getOptions(this);
				if (!o) {
					return this;
				}
				var $this = this.addClass(o.hoverClass),
					$ul = $this.children('ul');

				o.onBeforeShow.call($ul);
				$ul.stop(true, true).animate(o.animation, o.speed, function() {
					o.onShow.call($ul);
				});
				return this;
			},
			destroy: function() {
				return this.each(function(){
					var $this = $(this),
						o = $this.data('sf-options'),
						$liHasUl = $this.find('li:has(ul)');
					if (!o) {
						return false;
					}
					clearTimeout(o.sfTimer);
					toggleMenuClasses($this, o);
					toggleAnchorClass($liHasUl);
					toggleTouchAction($this);
					// remove event handlers
					$this.off('.superfish').off('.hoverIntent');
					// clear animation's inline display style
					$liHasUl.children('ul').attr('style', function(i, style){
						return style.replace(/display[^;]+;?/g, '');
					});
					// reset 'current' path classes
					o.$path.removeClass(o.hoverClass + ' ' + c.bcClass).addClass(o.pathClass);
					$this.find('.' + o.hoverClass).removeClass(o.hoverClass);
					o.onDestroy.call($this);
					$this.removeData('sf-options');
				});
			},
			init: function(op){
				return this.each(function() {
					var $this = $(this);
					if ($this.data('sf-options')) {
						return false;
					}
					var o = $.extend({}, $.fn.superfish.defaults, op),
						$liHasUl = $this.find('li:has(ul)');
					o.$path = setPathToCurrent($this, o);

					$this.data('sf-options', o);

					toggleMenuClasses($this, o);
					toggleAnchorClass($liHasUl);
					toggleTouchAction($this);
					applyHandlers($this, o);

					$liHasUl.not('.' + c.bcClass).superfish('hide',true);

					o.onInit.call(this);
				});
			}
		};
	})();

	$.fn.superfish = function(method, args) {
		if (methods[method]) {
			return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
		}
		else if (typeof method === 'object' || ! method) {
			return methods.init.apply(this, arguments);
		}
		else {
			return $.error('Method ' +  method + ' does not exist on jQuery.fn.superfish');
		}
	};

	$.fn.superfish.defaults = {
		hoverClass: 'sfhover',
		pathClass: 'overrideThisToUse',
		pathLevels: 1,
		delay: 800,
		animation: {opacity:'show', height:'show', width:'show'},
		animationOut: {opacity:'hide'},
		speed: 'normal',
		speedOut: 'fast',
		cssArrows: false,
		disableHI: false,
		onInit: $.noop,
		onBeforeShow: $.noop,
		onShow: $.noop,
		onBeforeHide: $.noop,
		onHide: $.noop,
		onIdle: $.noop,
		onDestroy: $.noop
	};

	// soon to be deprecated
	$.fn.extend({
		hideSuperfishUl: methods.hide,
		showSuperfishUl: methods.show
	});

})(jQuery);

/*
 * Superclick v1.0.0 - jQuery menu widget
 * Copyright (c) 2013 Joel Birch
 *
 * Licensed under the MIT license:
 * 	http://www.opensource.org/licenses/mit-license.php
 */

;(function($) {

	var methods = (function(){
		// private properties and methods go here
		var c = {
				bcClass: 'sf-breadcrumb',
				menuClass: 'sf-js-enabled',
				anchorClass: 'sf-with-ul',
				menuArrowClass: 'sf-arrows'
			},
			outerClick = (function() {
				$(window).load(function() {
					$('body').children().on('click.superclick', function() {
						var $allMenus = $('.sf-js-enabled');
						$allMenus.superclick('reset');
					});
				});
			})(),
			toggleMenuClasses = function($menu, o) {
				var classes = c.menuClass;
				if (o.cssArrows) {
					classes += ' ' + c.menuArrowClass;
				}
				$menu.toggleClass(classes);
			},
			setPathToCurrent = function($menu, o) {
				return $menu.find('li.' + o.pathClass).slice(0, o.pathLevels)
					.addClass(o.activeClass + ' ' + c.bcClass)
						.filter(function() {
							return ($(this).children('ul').hide().show().length);
						}).removeClass(o.pathClass);
			},
			toggleAnchorClass = function($li) {
				$li.children('a').toggleClass(c.anchorClass);
			},
			toggleTouchAction = function($menu) {
				var touchAction = $menu.css('ms-touch-action');
				touchAction = (touchAction === 'pan-y') ? 'auto' : 'pan-y';
				$menu.css('ms-touch-action', touchAction);
			},
			clickHandler = function(e) {
				var $this = $(this),
					$ul = $this.siblings('ul'),
					func;

				if ($ul.length) {
					func = ($ul.is(':hidden')) ? over : out;
					$.proxy(func, $this.parent('li'))();
					return false;
				}
			},
			over = function() {
				var $this = $(this),
					o = getOptions($this);
				$this.siblings().superclick('hide').end().superclick('show');
			},
			out = function() {
				var $this = $(this),
					o = getOptions($this);
				$.proxy(close, $this, o)();
			},
			close = function(o) {
				o.retainPath = ( $.inArray(this[0], o.$path) > -1);
				this.superclick('hide');

				if (!this.parents('.' + o.activeClass).length) {
					o.onIdle.call(getMenu(this));
					if (o.$path.length) {
						$.proxy(over, o.$path)();
					}
				}
			},
			getMenu = function($el) {
				return $el.closest('.' + c.menuClass);
			},
			getOptions = function($el) {
				return getMenu($el).data('sf-options');
			};

		return {
			// public methods
			hide: function(instant) {
				if (this.length) {
					var $this = this,
						o = getOptions($this);
						if (!o) {
							return this;
						}
					var not = (o.retainPath === true) ? o.$path : '',
						$ul = $this.find('li.' + o.activeClass).add(this).not(not).removeClass(o.activeClass).children('ul'),
						speed = o.speedOut;

					if (instant) {
						$ul.show();
						speed = 0;
					}
					o.retainPath = false;
					o.onBeforeHide.call($ul);
					$ul.stop(true, true).animate(o.animationOut, speed, function() {
						var $this = $(this);
						o.onHide.call($this);
					});
				}
				return this;
			},
			show: function() {
				var o = getOptions(this);
				if (!o) {
					return this;
				}
				var $this = this.addClass(o.activeClass),
					$ul = $this.children('ul');

				o.onBeforeShow.call($ul);
				$ul.stop(true, true).animate(o.animation, o.speed, function() {
					o.onShow.call($ul);
				});
				return this;
			},
			destroy: function() {
				return this.each(function(){
					var $this = $(this),
						o = $this.data('sf-options'),
						$liHasUl = $this.find('li:has(ul)');
					if (!o) {
						return false;
					}
					toggleMenuClasses($this, o);
					toggleAnchorClass($liHasUl);
					toggleTouchAction($this);
					// remove event handlers
					$this.off('.superclick');
					// clear animation's inline display style
					$liHasUl.children('ul').attr('style', function(i, style){
						return style.replace(/display[^;]+;?/g, '');
					});
					// reset 'current' path classes
					o.$path.removeClass(o.activeClass + ' ' + c.bcClass).addClass(o.pathClass);
					$this.find('.' + o.activeClass).removeClass(o.activeClass);
					o.onDestroy.call($this);
					$this.removeData('sf-options');
				});
			},
			reset: function() {
				return this.each(function(){
					var $menu = $(this),
						o = getOptions($menu),
						$openLis = $( $menu.find('.' + o.activeClass).toArray().reverse() );
					$openLis.children('a').trigger('click');
				});
			},
			init: function(op){
				return this.each(function() {
					var $this = $(this);
					if ($this.data('sf-options')) {
						return false;
					}
					var o = $.extend({}, $.fn.superclick.defaults, op),
						$liHasUl = $this.find('li:has(ul)');
					o.$path = setPathToCurrent($this, o);

					$this.data('sf-options', o);

					toggleMenuClasses($this, o);
					toggleAnchorClass($liHasUl);
					toggleTouchAction($this);
					$this.on('click.superclick', 'a', clickHandler);

					$liHasUl.not('.' + c.bcClass).superclick('hide',true);

					o.onInit.call(this);
				});
			}
		};
	})();

	$.fn.superclick = function(method, args) {
		if (methods[method]) {
			return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
		}
		else if (typeof method === 'object' || ! method) {
			return methods.init.apply(this, arguments);
		}
		else {
			return $.error('Method ' +  method + ' does not exist on jQuery.fn.superclick');
		}
	};

	$.fn.superclick.defaults = {
		activeClass: 'sfhover', // keep 'hover' in classname for compatibility reasons
		pathClass: 'overrideThisToUse',
		pathLevels: 1,
		animation: {opacity:'show'},
		animationOut: {opacity:'hide'},
		speed: 'normal',
		speedOut: 'fast',
		cssArrows: false,
		onInit: $.noop,
		onBeforeShow: $.noop,
		onShow: $.noop,
		onBeforeHide: $.noop,
		onHide: $.noop,
		onIdle: $.noop,
		onDestroy: $.noop
	};

})(jQuery);

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 * Expands the ... of the page indexes
 * @todo not exactly a plugin and still very bound to the theme structure
 *
 */
;(function($) {
	$.fn.expand_pages = function() {
		var $container,
			lastPositions = new Array();

		function hover_expand($element)
		{
			var $expanded_pages_li = $element,
				baseurl = eval($element.data('baseurl')),
				perpage = $element.data('perpage'),
				firstpage = $element.data('firstpage'),
				lastpage = $element.data('lastpage'),
				$exp_pages = $('<li id="expanded_pages" />'),
				pages = 0,
				container_width = $element.outerWidth() * 2,
				width_elements = 3,
				$scroll_left = null,
				$scroll_right = null;

			var aModel = $element.closest('.linavPages').prev().find('a').clone();

			if (typeof(lastPositions[firstpage]) == 'undefined')
				lastPositions[firstpage] = 0;

			$container = $('<ul id="expanded_pages_container" />');

			for (var i = firstpage; i < lastpage; i += perpage)
			{
				pages++;
				var bElem = aModel.clone();

				bElem.attr('href', baseurl.replace('%1$d', i)).text(i / perpage + 1);
				$exp_pages.append(bElem);
			}

			if (pages > width_elements)
			{
				$container.append($('<li />').append(aModel.clone()
				.attr('id', 'pages_scroll_left')
				.attr('href', '#').text('<').click(function(ev) {
					ev.stopPropagation();
					ev.preventDefault();
				}).hover(
					function() {
						$exp_pages.animate({
							'margin-left': 0
						}, 200 * pages);
					},
					function() {
						$exp_pages.stop();
						lastPositions[firstpage] = $exp_pages.css('margin-left');
					}
				)));
			}

			$container.append($exp_pages);
			$element.parent().superfish({
				delay : 300,
				speed: 175,
				onHide: function () {
					$container.remove();
				}
			});

			$element.append($container);

			if (pages > width_elements)
			{
				$container.append($('<li />').append(aModel.clone()
				.attr('id', 'pages_scroll_right')
				.attr('href', '#').text('>').click(function(ev) {
					ev.stopPropagation();
					ev.preventDefault();
				}).hover(
					function() {
						var $pages = $exp_pages.find('a'),
							move = 0;

						for (var i = 0, count = $exp_pages.find('a').length; i < count; i++)
							move += $($pages[i]).outerWidth();

						move = (move + $container.find('#pages_scroll_left').outerWidth()) - ($container.outerWidth() - $container.find('#pages_scroll_right').outerWidth());

						$exp_pages.animate({
							'margin-left': -move
						}, 200 * pages);
					},
					function() {
						$exp_pages.stop();
						lastPositions[firstpage] = $exp_pages.css('margin-left');
					}
				)));
			}

			// @todo this seems broken
			$exp_pages.find('a').each(function() {
				if (width_elements > -1)
					container_width += $element.outerWidth();

				if (width_elements <= 0 || pages >= width_elements)
				{
					$container.css({
						'margin-left': -container_width / 2
					}).width(container_width);
				}

				if (width_elements < 0)
					return false;

				width_elements--;
			}).click(function (ev) {
				$expanded_pages_li.attr('onclick', '').unbind('click');
			});
			$exp_pages.css({
				'height': $element.outerHeight(),
				'padding-left': $container.find('#pages_scroll_left').outerWidth(),
				'margin-left': lastPositions[firstpage]
			});

		};

		function expand_pages($element)
		{
			var $baseAppend = $($element.closest('.linavPages')),
				boxModel = $baseAppend.prev().clone(),
				aModel = boxModel.find('a').clone(),
				expandModel = $element.clone(),
				perPage = $element.data('perpage'),
				firstPage = $element.data('firstpage'),
				lastPage = $element.data('lastpage'),
				rawBaseurl = $element.data('baseurl'),
				baseurl = eval($element.data('baseurl')),
				first;

			var i, oldLastPage = 0,
				perPageLimit = 10;

			// Prevent too many pages to be loaded at once.
			if ((lastPage - firstPage) / perPage > perPageLimit)
			{
				oldLastPage = lastPage;
				lastPage = firstPage + perPageLimit * perPage;
			}

			// Calculate the new pages.
			for (i = lastPage; i > firstPage; i -= perPage)
			{
				var bElem = aModel.clone(),
					boxModelClone = boxModel.clone();

				bElem.attr('href', baseurl.replace('%1$d', i - perPage)).text(i / perPage);
				boxModelClone.find('a').each(function() {
					$(this).replaceWith(bElem[0]);
				});
				$baseAppend.after(boxModelClone);

				// This is needed just to remember where to attach the new expand
				if (typeof first == 'undefined')
					first = boxModelClone;
			}
			$baseAppend.remove();

			if (oldLastPage > 0)
			{
				// This is to remove any hover_expand
				expandModel.find('#expanded_pages_container').each(function() {
					$(this).remove();
				});

				expandModel.click(function(e) {
					var $zhis = $(this);
					e.preventDefault();

					expand_pages($zhis);

					$zhis.unbind('mouseenter focus');
				})
				.bind('mouseenter focus', function() {
					hover_expand($(this))
				})
				.data('perpage', perPage)
				.data('firstpage', lastPage)
				.data('lastpage', oldLastPage)
				.data('baseurl', rawBaseurl);

				first.after(expandModel);
			}
		}

		this.attr('tabindex', 0)
		.click(function(e) {
			var $zhis = $(this);
			e.preventDefault();

			expand_pages($zhis);

			$zhis.unbind('mouseenter focus');
		})
		.bind('mouseenter focus', function() {
			hover_expand($(this))
		});
	};
})(jQuery);