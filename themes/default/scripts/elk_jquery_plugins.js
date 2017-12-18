/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0-dev
 *
 * This file contains javascript plugins for use with jquery
 */

/*!
 * hoverIntent v1.9.0 // 2014.08.11 // jQuery v1.9.1+
 * http://cherne.net/brian/resources/jquery.hoverIntent.html
 *
 * You may use hoverIntent under the terms of the MIT license. Basically that
 * means you are free to use hoverIntent as long as this header is left intact.
 * Copyright 2007, 2014 Brian Cherne
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

(function(factory) {
	'use strict';
	if (typeof define === 'function' && define.amd) {
		define(['jquery'], factory);
	} else if (jQuery && !jQuery.fn.hoverIntent) {
		factory(jQuery);
	}
})(function($) {
	'use strict';

	// default configuration values
	var _cfg = {
		interval: 100,
		sensitivity: 6,
		timeout: 0
	};

	// counter used to generate an ID for each instance
	var INSTANCE_COUNT = 0;

	// current X and Y position of mouse, updated during mousemove tracking (shared across instances)
	var cX, cY;

	// saves the current pointer position coordinated based on the given mouse event
	var track = function(ev) {
		cX = ev.pageX;
		cY = ev.pageY;
	};

	// compares current and previous mouse positions
	var compare = function(ev,$el,s,cfg) {
		// compare mouse positions to see if pointer has slowed enough to trigger `over` function
		if ( Math.sqrt( (s.pX-cX)*(s.pX-cX) + (s.pY-cY)*(s.pY-cY) ) < cfg.sensitivity ) {
			$el.off('mousemove.hoverIntent'+s.namespace,track);
			delete s.timeoutId;
			// set hoverIntent state as active for this element (so `out` handler can eventually be called)
			s.isActive = true;
			// clear coordinate data
			delete s.pX; delete s.pY;
			return cfg.over.apply($el[0],[ev]);
		} else {
			// set previous coordinates for next comparison
			s.pX = cX; s.pY = cY;
			// use self-calling timeout, guarantees intervals are spaced out properly (avoids JavaScript timer bugs)
			s.timeoutId = setTimeout( function(){compare(ev, $el, s, cfg);} , cfg.interval );
		}
	};

	// triggers given `out` function at configured `timeout` after a mouseleave and clears state
	var delay = function(ev,$el,s,out) {
		delete $el.data('hoverIntent')[s.id];
		return out.apply($el[0],[ev]);
	};

	$.fn.hoverIntent = function(handlerIn,handlerOut,selector) {
		// instance ID, used as a key to store and retrieve state information on an element
		var instanceId = INSTANCE_COUNT++;

		// extend the default configuration and parse parameters
		var cfg = $.extend({}, _cfg);
		if ( $.isPlainObject(handlerIn) ) {
			cfg = $.extend(cfg, handlerIn );
		} else if ($.isFunction(handlerOut)) {
			cfg = $.extend(cfg, { over: handlerIn, out: handlerOut, selector: selector } );
		} else {
			cfg = $.extend(cfg, { over: handlerIn, out: handlerIn, selector: handlerOut } );
		}

		// A private function for handling mouse 'hovering'
		var handleHover = function(e) {
			// cloned event to pass to handlers (copy required for event object to be passed in IE)
			var ev = $.extend({},e);

			// the current target of the mouse event, wrapped in a jQuery object
			var $el = $(this);

			// read hoverIntent data from element (or initialize if not present)
			var hoverIntentData = $el.data('hoverIntent');
			if (!hoverIntentData) { $el.data('hoverIntent', (hoverIntentData = {})); }

			// read per-instance state from element (or initialize if not present)
			var state = hoverIntentData[instanceId];
			if (!state) { hoverIntentData[instanceId] = state = { id: instanceId }; }

			// state properties:
			// id = instance ID, used to clean up data
			// timeoutId = timeout ID, reused for tracking mouse position and delaying "out" handler
			// isActive = plugin state, true after `over` is called just until `out` is called
			// pX, pY = previously-measured pointer coordinates, updated at each polling interval
			// namespace = string used as namespace for per-instance event management

			// clear any existing timeout
			if (state.timeoutId) { state.timeoutId = clearTimeout(state.timeoutId); }

			// event namespace, used to register and unregister mousemove tracking
			var namespace = state.namespace = '.hoverIntent'+instanceId;

			// handle the event, based on its type
			if (e.type === 'mouseenter') {
				// do nothing if already active
				if (state.isActive) { return; }
				// set "previous" X and Y position based on initial entry point
				state.pX = ev.pageX; state.pY = ev.pageY;
				// update "current" X and Y position based on mousemove
				$el.on('mousemove.hoverIntent'+namespace,track);
				// start polling interval (self-calling timeout) to compare mouse coordinates over time
				state.timeoutId = setTimeout( function(){compare(ev,$el,state,cfg);} , cfg.interval );
			} else { // "mouseleave"
				// do nothing if not already active
				if (!state.isActive) { return; }
				// unbind expensive mousemove event
				$el.off('mousemove.hoverIntent'+namespace,track);
				// if hoverIntent state is true, then call the mouseOut function after the specified delay
				state.timeoutId = setTimeout( function(){delay(ev,$el,state,cfg.out);} , cfg.timeout );
			}
		};

		// listen for mouseenter and mouseleave
		return this.on({'mouseenter.hoverIntent':handleHover,'mouseleave.hoverIntent':handleHover}, cfg.selector);
	};
});



/*
 * jQuery Superfish Menu Plugin 1.7.9
 * Copyright (c) 2013 Joel Birch
 *
 * Dual licensed under the MIT and GPL licenses:
 *	http://www.opensource.org/licenses/mit-license.php
 *	http://www.gnu.org/licenses/gpl.html
 */

(function ($, w) {
	"use strict";

	var methods = (function () {
		// private properties and methods go here
		var c = {
				bcClass: 'sf-breadcrumb',
				menuClass: 'sf-js-enabled',
				anchorClass: 'sf-with-ul',
				menuArrowClass: 'sf-arrows'
			},
			outerClick = (function() {
				$(window).on('load', function() {
					$('body').children().on('click.superfish', function() {
						$('.sf-js-enabled').superfish('hide', 'true');
					});
				});
			})(),
			ios = (function () {
				var ios = /^(?![\w\W]*Windows Phone)[\w\W]*(iPhone|iPad|iPod)/i.test(navigator.userAgent);
				if (ios) {
					// tap anywhere on iOS to unfocus a submenu
					$('html').css('cursor', 'pointer').on('click', $.noop);
				}
				return ios;
			})(),
			wp7 = (function () {
				var style = document.documentElement.style;
				return ('behavior' in style && 'fill' in style && /iemobile/i.test(navigator.userAgent));
			})(),
			unprefixedPointerEvents = (function () {
				return (!!w.PointerEvent);
			})(),
			toggleMenuClasses = function ($menu, o, add) {
				var classes = c.menuClass,
					method;
				if (o.cssArrows) {
					classes += ' ' + c.menuArrowClass;
				}
				method = (add) ? 'addClass' : 'removeClass';
				$menu[method](classes);
			},
			setPathToCurrent = function ($menu, o) {
				return $menu.find('li.' + o.pathClass).slice(0, o.pathLevels)
					.addClass(o.hoverClass + ' ' + c.bcClass)
					.filter(function () {
						return ($(this).children(o.popUpSelector).hide().show().length);
					}).removeClass(o.pathClass);
			},
			toggleAnchorClass = function ($li, add) {
				var method = (add) ? 'addClass' : 'removeClass';
				$li.children('a')[method](c.anchorClass);
			},
			toggleTouchAction = function ($menu) {
				var msTouchAction = $menu.css('ms-touch-action');
				var touchAction = $menu.css('touch-action');
				touchAction = touchAction || msTouchAction;
				touchAction = (touchAction === 'pan-y') ? 'auto' : 'pan-y';
				$menu.css({
					'ms-touch-action': touchAction,
					'touch-action': touchAction
				});
			},
			getMenu = function ($el) {
				return $el.closest('.' + c.menuClass);
			},
			getOptions = function ($el) {
				return getMenu($el).data('sfOptions');
			},
			over = function () {
				var $this = $(this),
					o = getOptions($this);
				clearTimeout(o.sfTimer);
				$this.siblings().superfish('hide').end().superfish('show');
			},
			close = function (o) {
				o.retainPath = ($.inArray(this[0], o.$path) > -1);
				this.superfish('hide');

				if (!this.parents('.' + o.hoverClass).length) {
					o.onIdle.call(getMenu(this));
					if (o.$path.length) {
						$.proxy(over, o.$path)();
					}
				}
			},
			out = function () {
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
			touchHandler = function (e) {
				var $this = $(this),
					o = getOptions($this),
					$ul = $this.siblings(e.data.popUpSelector);

				if (o.onHandleTouch.call($ul) === false) {
					return this;
				}

				if ($ul.length > 0 && $ul.is(':hidden')) {
					$this.one('click.superfish', false);
					if (e.type === 'MSPointerDown' || e.type === 'pointerdown') {
						$this.trigger('focus');
					} else {
						$.proxy(over, $this.parent('li'))();
					}
				}
			},
			applyHandlers = function ($menu, o) {
				var targets = 'li:has(' + o.popUpSelector + ')';
				if ($.fn.hoverIntent && !o.disableHI) {
					$menu.hoverIntent(over, out, targets);
				}
				else {
					$menu
						.on('mouseenter.superfish', targets, over)
						.on('mouseleave.superfish', targets, out);
				}
				var touchevent = 'MSPointerDown.superfish';
				if (unprefixedPointerEvents) {
					touchevent = 'pointerdown.superfish';
				}
				if (!ios) {
					touchevent += ' touchend.superfish';
				}
				if (wp7) {
					touchevent += ' mousedown.superfish';
				}
				$menu
					.on('focusin.superfish', 'li', over)
					.on('focusout.superfish', 'li', out)
					.on(touchevent, 'a', o, touchHandler);
			};

		return {
			// public methods
			hide: function (instant) {
				if (this.length) {
					var $this = this,
						o = getOptions($this);
					if (!o) {
						return this;
					}
					var not = (o.retainPath === true) ? o.$path : '',
						$ul = $this.find('li.' + o.hoverClass).add(this).not(not).removeClass(o.hoverClass).children(o.popUpSelector),
						speed = o.speedOut;

					if (instant) {
						$ul.show();
						speed = 0;
					}
					o.retainPath = false;

					if (o.onBeforeHide.call($ul) === false) {
						return this;
					}

					$ul.stop(true, true).animate(o.animationOut, speed, function () {
						var $this = $(this);
						o.onHide.call($this);
					});
				}
				return this;
			},
			show: function () {
				var o = getOptions(this);
				if (!o) {
					return this;
				}
				var $this = this.addClass(o.hoverClass),
					$ul = $this.children(o.popUpSelector);

				if (o.onBeforeShow.call($ul) === false) {
					return this;
				}

				$ul.stop(true, true).animate(o.animation, o.speed, function () {
					o.onShow.call($ul);
				});
				return this;
			},
			destroy: function () {
				return this.each(function () {
					var $this = $(this),
						o = $this.data('sfOptions'),
						$hasPopUp;
					if (!o) {
						return false;
					}
					$hasPopUp = $this.find(o.popUpSelector).parent('li');
					clearTimeout(o.sfTimer);
					toggleMenuClasses($this, o);
					toggleAnchorClass($hasPopUp);
					toggleTouchAction($this);
					// remove event handlers
					$this.off('.superfish').off('.hoverIntent');
					// clear animation's inline display style
					$hasPopUp.children(o.popUpSelector).attr('style', function (i, style) {
						return style.replace(/display[^;]+;?/g, '');
					});
					// reset 'current' path classes
					o.$path.removeClass(o.hoverClass + ' ' + c.bcClass).addClass(o.pathClass);
					$this.find('.' + o.hoverClass).removeClass(o.hoverClass);
					o.onDestroy.call($this);
					$this.removeData('sfOptions');
				});
			},
			init: function (op) {
				return this.each(function () {
					var $this = $(this);
					if ($this.data('sfOptions')) {
						return false;
					}
					var o = $.extend({}, $.fn.superfish.defaults, op),
						$hasPopUp = $this.find(o.popUpSelector).parent('li');
					o.$path = setPathToCurrent($this, o);

					$this.data('sfOptions', o);

					toggleMenuClasses($this, o, true);
					toggleAnchorClass($hasPopUp, true);
					toggleTouchAction($this);
					applyHandlers($this, o);

					$hasPopUp.not('.' + c.bcClass).superfish('hide', true);

					o.onInit.call(this);
				});
			}
		};
	})();

	$.fn.superfish = function (method, args) {
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
		popUpSelector: 'ul,.sf-mega', // within menu context
		hoverClass: 'sfHover',
		pathClass: 'overrideThisToUse',
		pathLevels: 1,
		delay: 800,
		animation: {opacity: 'show'},
		animationOut: {opacity: 'hide'},
		speed: 'normal',
		speedOut: 'fast',
		cssArrows: true,
		disableHI: false,
		onInit: $.noop,
		onBeforeShow: $.noop,
		onShow: $.noop,
		onBeforeHide: $.noop,
		onHide: $.noop,
		onIdle: $.noop,
		onDestroy: $.noop,
		onHandleTouch: $.noop
	};

})(jQuery, window);

/*!
 * Superclick v1.0.0 - jQuery menu widget
 * Copyright (c) 2013 Joel Birch
 *
 * Dual licensed under the MIT and GPL licenses:
 *	http://www.opensource.org/licenses/mit-license.php
 *	http://www.gnu.org/licenses/gpl.html
 */

(function ($, w) {
	"use strict";

	var methods = (function () {
		// private properties and methods go here
		var c = {
				bcClass: 'sf-breadcrumb',
				menuClass: 'sf-js-enabled',
				anchorClass: 'sf-with-ul',
				menuArrowClass: 'sf-arrows'
			},
			outerClick = (function () {
				$(w).on('load', function () {
					$('body').children().on('click.superclick', function () {
						var $allMenus = $('.sf-js-enabled');
						$allMenus.superclick('reset');
					});
				});
			})(),
			toggleMenuClasses = function ($menu, o) {
				var classes = c.menuClass;
				if (o.cssArrows) {
					classes += ' ' + c.menuArrowClass;
				}
				$menu.toggleClass(classes);
			},
			setPathToCurrent = function ($menu, o) {
				return $menu.find('li.' + o.pathClass).slice(0, o.pathLevels)
					.addClass(o.activeClass + ' ' + c.bcClass)
						.filter(function () {
							return ($(this).children(o.popUpSelector).hide().show().length);
						}).removeClass(o.pathClass);
			},
			toggleAnchorClass = function ($li) {
				$li.children('a').toggleClass(c.anchorClass);
			},
			toggleTouchAction = function ($menu) {
				var msTouchAction = $menu.css('ms-touch-action');
				var touchAction = $menu.css('touch-action');
				touchAction = touchAction || msTouchAction;
				touchAction = (touchAction === 'pan-y') ? 'auto' : 'pan-y';
				$menu.css({
					'ms-touch-action': touchAction,
					'touch-action': touchAction
				});
			},
			clickHandler = function (e) {
				var $this = $(this),
					$popUp = $this.siblings(e.data.popUpSelector),
					func;

				if ($popUp.length) {
					var tmp = !$popUp.is(':hidden');
					func = ($popUp.is(':hidden')) ? over : out;
					$.proxy(func, $this.parent('li'))();
					return !!tmp;
				}
			},
			dblclickHandler = function(e) {
				var $this = $(this),
					$popUp = $this.siblings(e.data.popUpSelector),
					target = e.currentTarget.href;

				if ($popUp.length === 1 && target) {
					if ($popUp.not(':hidden'))
						$.proxy(out, $this.parent('li'))();
					window.location = target;
					return false;
				}
			},
			over = function () {
				var $this = $(this),
					o = getOptions($this);
				$this.siblings().superclick('hide').end().superclick('show');
			},
			out = function () {
				var $this = $(this),
					o = getOptions($this);
				$.proxy(close, $this, o)();
			},
			close = function (o) {
				o.retainPath = ($.inArray(this[0], o.$path) > -1);
				this.superclick('hide');

				if (!this.parents('.' + o.activeClass).length) {
					o.onIdle.call(getMenu(this));
					if (o.$path.length) {
						$.proxy(over, o.$path)();
					}
				}
			},
			getMenu = function ($el) {
				return $el.closest('.' + c.menuClass);
			},
			getOptions = function ($el) {
				return getMenu($el).data('sf-options');
			};

		return {
			// public methods
			hide: function (instant) {
				if (this.length) {
					var $this = this,
						o = getOptions($this);
					if (!o) {
						return this;
					}
					var not = (o.retainPath === true) ? o.$path : '',
						$popUp = $this.find('li.' + o.activeClass).add(this).not(not).removeClass(o.activeClass).children(o.popUpSelector),
						speed = o.speedOut;

					if (instant) {
						$popUp.show();
						speed = 0;
					}
					o.retainPath = false;
					o.onBeforeHide.call($popUp);
					$popUp.stop(true, true).animate(o.animationOut, speed, function () {
						var $this = $(this);
						o.onHide.call($this);
					});
				}
				return this;
			},
			show: function () {
				var o = getOptions(this);
				if (!o) {
					return this;
				}
				var $this = this.addClass(o.activeClass),
					$popUp = $this.children(o.popUpSelector);

				o.onBeforeShow.call($popUp);
				$popUp.stop(true, true).animate(o.animation, o.speed, function () {
					o.onShow.call($popUp);
				});
				return this;
			},
			destroy: function () {
				return this.each(function () {
					var $this = $(this),
						o = $this.data('sf-options'),
						$hasPopUp;
					if (!o) {
						return false;
					}
					$hasPopUp = $this.find(o.popUpSelector).parent('li');
					toggleMenuClasses($this, o);
					toggleAnchorClass($hasPopUp);
					toggleTouchAction($this);
					// remove event handlers
					$this.off('.superclick');
					// clear animation's inline display style
					$hasPopUp.children(o.popUpSelector).attr('style', function (i, style) {
						return style.replace(/display[^;]+;?/g, '');
					});
					// reset 'current' path classes
					o.$path.removeClass(o.activeClass + ' ' + c.bcClass).addClass(o.pathClass);
					$this.find('.' + o.activeClass).removeClass(o.activeClass);
					o.onDestroy.call($this);
					$this.removeData('sf-options');
				});
			},
			reset: function () {
				return this.each(function () {
					var $menu = $(this),
						o = getOptions($menu),
						$openLis = $($menu.find('.' + o.activeClass).toArray().reverse());
					$openLis.children('a').trigger('click');
				});
			},
			init: function (op) {
				return this.each(function () {
					var $this = $(this);
					if ($this.data('sf-options')) {
						return false;
					}
					var o = $.extend({}, $.fn.superclick.defaults, op),
						$hasPopUp = $this.find(o.popUpSelector).parent('li');
					o.$path = setPathToCurrent($this, o);

					$this.data('sf-options', o);

					toggleMenuClasses($this, o);
					toggleAnchorClass($hasPopUp);
					toggleTouchAction($this);
					$this.on('click.superclick', 'a', o, clickHandler);
					$hasPopUp.on('dblclick.superclick', 'a', o, dblclickHandler);

					$hasPopUp.not('.' + c.bcClass).superclick('hide', true);

					o.onInit.call(this);
				});
			}
		};
	})();

	$.fn.superclick = function (method, args) {
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
		popUpSelector: 'ul,.sf-mega', // within menu context
		activeClass: 'sfHover', // keep 'hover' in classname for compatibility reasons
		pathClass: 'overrideThisToUse',
		pathLevels: 1,
		animation: {opacity: 'show'},
		animationOut: {opacity: 'hide'},
		speed: 'normal',
		speedOut: 'fast',
		cssArrows: true,
		onInit: $.noop,
		onBeforeShow: $.noop,
		onShow: $.noop,
		onBeforeHide: $.noop,
		onHide: $.noop,
		onIdle: $.noop,
		onDestroy: $.noop
	};

})(jQuery, window);


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
			$.extend(settings, options);
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
