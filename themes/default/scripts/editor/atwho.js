/**
 * atwho.js — jQuery-free ES2018 port of at.js 1.5.6
 * Original: Copyright (c) 2022 chord.luo <chord.luo@gmail.com>, MIT
 * This port: 2026-11-01, MIT
 */

const IS_FIREFOX = typeof navigator !== 'undefined' && /firefox/i.test(navigator.userAgent);

/* ------------------------------------------------------------------ *
 * Module-private helpers
 * ------------------------------------------------------------------ */

const isPlainObject = (value) => {
	if (value === null || typeof value !== 'object')
	{
		return false;
	}
	const proto = Object.getPrototypeOf(value);
	return proto === Object.prototype || proto === null;
};

// Tiny createElement helper, scoped to the document that owns the view.
function h (doc, tag, props, ...children)
{
	const el = doc.createElement(tag);
	if (props)
	{
		for (const [key, value] of Object.entries(props))
		{
			if (key === 'class')
			{
				el.className = value;
			}
			else
			{
				el.setAttribute(key, value);
			}
		}
	}
	if (children.length)
	{
		el.append(...children);
	}
	return el;
}

// Document based offset of an element
function offset (el)
{
	const rect = el.getBoundingClientRect();
	const win = el.ownerDocument.defaultView;
	return {
		top: rect.top + win.scrollY,
		left: rect.left + win.scrollX
	};
}

// Equivalent of the classic .offset({left, top}) setter
function setOffset (el, pos)
{
	const computed = el.ownerDocument.defaultView.getComputedStyle(el);
	if (computed.position === 'static')
	{
		el.style.position = 'relative';
	}
	const cur = offset(el);
	const curLeft = parseFloat(computed.left) || 0;
	const curTop = parseFloat(computed.top) || 0;
	el.style.left = (pos.left - cur.left + curLeft) + 'px';
	el.style.top = (pos.top - cur.top + curTop) + 'px';
}

function isVisible (el)
{
	if (!el || !el.ownerDocument || !el.ownerDocument.defaultView)
	{
		return false;
	}
	const style = el.ownerDocument.defaultView.getComputedStyle(el);
	return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length) &&
		style.display !== 'none' && style.visibility !== 'hidden';
}

// ES2018 stand-in for the nullish coalescing operator
const nullish = (value, fallback) => (value == null ? fallback : value);

// Tracked listeners (stand-in for AbortController signals): record, then remove on teardown.
function on (list, target, type, handler)
{
	target.addEventListener(type, handler);
	list.push([target, type, handler]);
}

function offAll (list)
{
	list.forEach(([target, type, handler]) => target.removeEventListener(type, handler));
	list.length = 0;
}

// Node -> element, then closest() ... text nodes have no closest().
function closestElement (node, selector)
{
	const el = node && node.nodeType === 1 ? node : (node ? node.parentElement : null);
	return nullish(el ? el.closest(selector) : null, null);
}

// Replace an element with its child nodes
function replaceWithChildren (el)
{
	if (!el.parentNode)
	{
		return;
	}
	el.replaceWith(...el.childNodes);
}

function escapeRegExp (value)
{
	return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/* ---------------------------- caret helpers ---------------------------- */

const MIRROR_PROPERTIES = [
	'direction', 'boxSizing', 'width', 'height', 'overflowX', 'overflowY',
	'borderTopWidth', 'borderRightWidth', 'borderBottomWidth', 'borderLeftWidth', 'borderStyle',
	'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft',
	'fontStyle', 'fontVariant', 'fontWeight', 'fontStretch', 'fontSize', 'fontSizeAdjust',
	'lineHeight', 'fontFamily', 'textAlign', 'textTransform', 'textIndent', 'textDecoration',
	'letterSpacing', 'wordSpacing', 'tabSize', 'MozTabSize'
];

/* ------------------------------------------------------------------ *
 * Constants & default callbacks
 * ------------------------------------------------------------------ */

const KEY = {
	ESC: 'Escape',
	TAB: 'Tab',
	ENTER: 'Enter',
	CTRL: 'Control',
	SPACE: ' ',
	LEFT: 'ArrowLeft',
	UP: 'ArrowUp',
	RIGHT: 'ArrowRight',
	DOWN: 'ArrowDown',
	BACKSPACE: 'Backspace'
};
const LEGACY_KEYS = {
	Esc: KEY.ESC,
	Up: KEY.UP,
	Down: KEY.DOWN,
	Left: KEY.LEFT,
	Right: KEY.RIGHT,
	Spacebar: KEY.SPACE
};
const ARROW_KEYS = [KEY.LEFT, KEY.UP, KEY.RIGHT, KEY.DOWN];

function keyName (e)
{
	const key = e && e.key;
	if (!key)
	{
		return '';
	}
	return LEGACY_KEYS[key] || (key.length === 1 ? key.toLowerCase() : key);
}

function toDefaultHash (data)
{
	if (!Array.isArray(data))
	{
		return data;
	}
	return data.map((item) => (isPlainObject(item) ? item : {name: item}));
}

/**
 * @typedef {Object} AtWhoItemData
 * @property {string} name
 */

/**
 * @typedef {Object} AtWhoCallbacks
 * @property {(data: Array<*>|null) => AtWhoItemData[]} [beforeSave]
 * @property {(flag: string, subtext: string, shouldStartWithSpace: boolean, acceptSpaceBar: boolean) => (string|null)} [matcher]
 * @property {(query: string, data: AtWhoItemData[], searchKey: string) => AtWhoItemData[]} [filter]
 * @property {((query: string, callback: (data: AtWhoItemData[]) => void) => void)|null} [remoteFilter]
 * @property {(query: string, items: AtWhoItemData[], searchKey: string) => AtWhoItemData[]} [sorter]
 * @property {(tpl: string|Function, map: Object, mode?: string) => string} [tplEval]
 * @property {(li: string, query: string) => string} [highlighter]
 * @property {(value: string, li: HTMLElement, e: Event) => string} [beforeInsert]
 * @property {(offset: {left: number, top: number}) => void} [beforeReposition]
 * @property {(at: string, el: HTMLElement|null) => (void|false)} [afterMatchFailed]
 */

/**
 * @typedef {Object} AtWhoOptions
 * @property {string} [at]
 * @property {string} [alias]
 * @property {AtWhoItemData[]|string|null} [data]
 * @property {string} [displayTpl]
 * @property {string} [insertTpl]
 * @property {string|null} [headerTpl]
 * @property {AtWhoCallbacks} [callbacks]
 * @property {Object} [functionOverrides]
 * @property {string} [searchKey]
 * @property {string} [suffix]
 * @property {boolean} [hideWithoutSuffix]
 * @property {boolean} [startWithSpace]
 * @property {boolean} [acceptSpaceBar]
 * @property {boolean} [highlightFirst]
 * @property {number} [limit]
 * @property {number} [maxLen]
 * @property {number} [minLen]
 * @property {number} [displayTimeout]
 * @property {number|null} [delay]
 * @property {boolean} [spaceSelectsMatch]
 * @property {boolean} [tabSelectsMatch]
 * @property {Object<string, string>} [editableAtwhoQueryAttrs]
 * @property {number} [scrollDuration]
 * @property {boolean} [suspendOnComposing]
 * @property {boolean} [lookUpOnClick]
 */

const DEFAULT_CALLBACKS = {
	beforeSave: function(data) {
		return toDefaultHash(data);
	},
	matcher: function(flag, subtext, should_startWithSpace, acceptSpaceBar) {
		flag = flag.replace(/[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/g, '\\$&');
		if (should_startWithSpace)
		{
			flag = '(?:^|\\s)' + flag;
		}
		const space = acceptSpaceBar ? ' ' : '';
		const regexp = new RegExp(flag + '([A-Za-z\\u00C0-\\u00FF0-9_' + space + '\'\\.\\+\\-]*)$|' + flag + '([^\\x00-\\xff]*)$', 'gi');
		const match = regexp.exec(subtext);
		if (match)
		{
			return match[2] || match[1];
		}
		return null;
	},
	filter: function(query, data, searchKey) {
		const lowerQuery = String(query).toLowerCase();
		return data.filter((item) => String(item[searchKey]).toLowerCase().includes(lowerQuery));
	},
	remoteFilter: null,
	sorter: function(query, items, searchKey) {
		if (!query)
		{
			return items;
		}
		const lowerQuery = String(query).toLowerCase();
		const results = items.filter((item) => {
			item.atwho_order = String(item[searchKey]).toLowerCase().indexOf(lowerQuery);
			return item.atwho_order > -1;
		});
		return results.sort(function(a, b) {
			return a.atwho_order - b.atwho_order;
		});
	},
	tplEval: function(tpl, map) {
		let template = tpl;
		try
		{
			if (typeof tpl !== 'string')
			{
				template = tpl(map);
			}
			return template.replace(/\$\{([^\}]*)\}/g, function(tag, key) {
				return map[key];
			});
		}
		catch (error)
		{
			return '';
		}
	},
	highlighter: function(li, query) {
		if (!query)
		{
			return li;
		}
		const regexp = new RegExp('>\\s*([^\\<]*?)(' + escapeRegExp(query) + ')([^\\<]*)\\s*<', 'ig');
		return li.replace(regexp, function(str, $1, $2, $3) {
			return '> ' + $1 + '<strong>' + $2 + '</strong>' + $3 + ' <';
		});
	},
	beforeInsert: function(value, li, e) {
		return value;
	},
	beforeReposition: function(offset) {
		return offset;
	},
	afterMatchFailed: function(at, el) {}
};

const DEFAULTS = {
	at: undefined,
	alias: undefined,
	data: null,
	displayTpl: '<li>${name}</li>',
	insertTpl: '${atwho-at}${name}',
	headerTpl: null,
	callbacks: DEFAULT_CALLBACKS,
	functionOverrides: {},
	searchKey: 'name',
	suffix: undefined,
	hideWithoutSuffix: false,
	startWithSpace: true,
	acceptSpaceBar: false,
	highlightFirst: true,
	limit: 5,
	maxLen: 20,
	minLen: 0,
	displayTimeout: 300,
	delay: null,
	spaceSelectsMatch: false,
	tabSelectsMatch: true,
	editableAtwhoQueryAttrs: {},
	scrollDuration: 150,
	suspendOnComposing: true,
	lookUpOnClick: true
};

/* ------------------------------------------------------------------ *
 * App
 * ------------------------------------------------------------------ */

class App
{
	constructor (inputor)
	{
		this._aliasMaps = {};
		this._controllers = {};
		this._currentFlag = null;
		this._document = null;
		this._el = null;
		this._iframe = null;
		this._iframeAsRoot = false;
		this._isComposing = false;
		this._window = null;
		this.inputor = inputor;
		this._listeners = [];
		this.setupRootElement();
		this.listen();
	}

	get isComposing ()
	{
		return this._isComposing;
	}

	set isComposing (value)
	{
		this._isComposing = value;
	}

	get document ()
	{
		return this._document;
	}

	set document (value)
	{
		this._document = value;
	}

	get window ()
	{
		return this._window;
	}

	set window (value)
	{
		this._window = value;
	}

	get iframe ()
	{
		return this._iframe;
	}

	set iframe (value)
	{
		this._iframe = value;
	}

	get iframeAsRoot ()
	{
		return this._iframeAsRoot;
	}

	set iframeAsRoot (value)
	{
		this._iframeAsRoot = value;
	}

	get el ()
	{
		return this._el;
	}

	set el (value)
	{
		this._el = value;
	}

	get $inputor ()
	{
		return this.inputor;
	}

	get controllers ()
	{
		return this._controllers;
	}

	set controllers (value)
	{
		this._controllers = value;
	}

	get aliasMaps ()
	{
		return this._aliasMaps;
	}

	set aliasMaps (value)
	{
		this._aliasMaps = value;
	}

	get currentFlag ()
	{
		return this._currentFlag;
	}

	set currentFlag (value)
	{
		this._currentFlag = value;
	}

	createContainer (doc)
	{
		if (this._el)
		{
			this._el.remove();
		}
		this._el = doc.createElement('div');
		this._el.className = 'atwho-container';
		doc.body.appendChild(this._el);
		return this._el;
	}

	setupRootElement (iframe, asRoot)
	{
		if (asRoot == null)
		{
			asRoot = false;
		}
		if (iframe)
		{
			this._window = iframe.contentWindow;
			this._document = iframe.contentDocument || this._window.document;
			this._iframe = iframe;
		}
		else
		{
			this._document = this.inputor.ownerDocument;
			this._window = this._document.defaultView;
			try
			{
				this._iframe = this._window.frameElement;
			}
			catch (error)
			{
				this._iframe = null;
				if (AtWho.debug)
				{
					throw new Error('iframe auto-discovery is failed.\nPlease use `setIframe` to set the target iframe manually.\n' + error);
				}
			}
		}
		this._iframeAsRoot = asRoot;
		return this.createContainer(this._iframeAsRoot ? this._document : document);
	}

	controller (at)
	{
		let current;
		if (this._aliasMaps[at])
		{
			current = this._controllers[this._aliasMaps[at]];
		}
		else
		{
			for (const currentFlag in this._controllers)
			{
				if (currentFlag === at)
				{
					current = this._controllers[currentFlag];
					break;
				}
			}
		}
		if (current)
		{
			return current;
		}
		return this._controllers[this._currentFlag];
	}

	setContextFor (at)
	{
		this._currentFlag = at;
		return this;
	}

	reg (flag, setting)
	{
		let controller = this._controllers[flag];
		if (!controller)
		{
			const ControllerClass = this.inputor.hasAttribute('contenteditable') ? EditableController : TextareaController;
			controller = this._controllers[flag] = new ControllerClass(this, flag);
		}
		if (setting.alias)
		{
			this._aliasMaps[setting.alias] = flag;
		}
		controller.init(setting);
		return this;
	}

	listen ()
	{
		const inputor = this.inputor;
		const list = this._listeners;

		on(list, inputor, 'compositionstart', () => {
			const c = this.controller();
			if (c)
			{
				c.view.hide();
			}
			this._isComposing = true;
		});

		on(list, inputor, 'compositionend', (e) => {
			this._isComposing = false;
			setTimeout(() => this.dispatch(e));
		});

		on(list, inputor, 'keyup', (e) => this.onKeyup(e));
		on(list, inputor, 'keydown', (e) => this.onKeydown(e));

		on(list, inputor, 'blur', (e) => {
			const c = this.controller();
			if (c)
			{
				c.expectedQueryCBId = null;
				return c.view.hide(e, c.setting.displayTimeout);
			}
		});

		on(list, inputor, 'click', (e) => this.dispatch(e));

		let lastScrollTop = inputor.scrollTop;
		on(list, inputor, 'scroll', (e) => {
			const currentScrollTop = e.target.scrollTop;
			if (lastScrollTop !== currentScrollTop)
			{
				const c = this.controller();
				if (c)
				{
					c.view.hide(e);
				}
			}
			lastScrollTop = currentScrollTop;
		});

		if (this._iframe && this._window !== window)
		{
			let lastWindowScrollY = this._window.scrollY;
			on(list, this._window, 'scroll', (e) => {
				const currentScrollY = this._window.scrollY;
				if (lastWindowScrollY !== currentScrollY)
				{
					const c = this.controller();
					if (c)
					{
						c.view.hide(e);
					}
				}
				lastWindowScrollY = currentScrollY;
			});
		}
	}

	shutdown ()
	{
		for (const flag in this._controllers)
		{
			this._controllers[flag].destroy();
			delete this._controllers[flag];
		}
		offAll(this._listeners);
		if (this._el)
		{
			this._el.remove();
		}
	}

	dispatch (e)
	{
		if (e === undefined)
		{
			return;
		}
		const results = [];
		for (const flag in this._controllers)
		{
			results.push(this._controllers[flag].lookUp(e));
		}
		return results;
	}

	onKeyup (e)
	{
		switch (keyName(e))
		{
			case KEY.ESC:
			{
				e.preventDefault();
				const c = this.controller();
				if (c)
				{
					c.view.hide();
				}
				break;
			}
			case KEY.DOWN:
			case KEY.UP:
			case KEY.CTRL:
				break;
			case 'p':
			case 'n':
				if (!e.ctrlKey)
				{
					this.dispatch(e);
				}
				break;
			default:
				this.dispatch(e);
		}
	}

	onKeydown (e)
	{
		if (e.isComposing)
		{
			return;
		}
		const c = this.controller();
		const view = c ? c.view : undefined;
		if (!(view && view.visible()))
		{
			return;
		}
		switch (keyName(e))
		{
			case KEY.ESC:
				e.preventDefault();
				view.hide(e);
				break;
			case KEY.UP:
				e.preventDefault();
				view.prev();
				break;
			case KEY.DOWN:
				e.preventDefault();
				view.next();
				break;
			case 'p':
				if (!e.ctrlKey)
				{
					return;
				}
				e.preventDefault();
				view.prev();
				break;
			case 'n':
				if (!e.ctrlKey)
				{
					return;
				}
				e.preventDefault();
				view.next();
				break;
			case KEY.TAB:
			case KEY.ENTER:
			case KEY.SPACE:
				if (!view.visible())
				{
					return;
				}
				if (!c.setting.spaceSelectsMatch && keyName(e) === KEY.SPACE)
				{
					return;
				}
				if (!c.setting.tabSelectsMatch && keyName(e) === KEY.TAB)
				{
					return;
				}
				if (view.highlighted())
				{
					e.preventDefault();
					view.choose(e);
				}
				else
				{
					view.hide(e);
				}
				break;
			default:
				break;
		}
	}

	/* -- Former dispatcher methods (still callable through AtWho.method) -- */

	load (at, data)
	{
		const c = this.controller(at);
		if (c)
		{
			return c.model.load(data);
		}
	}

	isSelecting ()
	{
		const c = this.controller();
		return !!(c ? c.view.visible() : void 0);
	}

	hide ()
	{
		const c = this.controller();
		return c ? c.view.hide() : void 0;
	}

	reposition ()
	{
		const c = this.controller();
		if (c)
		{
			return c.view.reposition(c.rect());
		}
	}

	setIframe (iframe, asRoot)
	{
		this.setupRootElement(iframe, asRoot);
		return null;
	}

	run ()
	{
		return this.dispatch();
	}

	destroy ()
	{
		this.shutdown();
		appRefs.delete(this.inputor);
		return null;
	}
}

/* ------------------------------------------------------------------ *
 * Controller (base)
 * ------------------------------------------------------------------ */

class Controller
{
	uid ()
	{
		return (Math.random().toString(16) + '000000000').slice(2, 10) + Date.now();
	}

	constructor (app, at)
	{
		this._delayedCallTimeout = null;
		this._expectedQueryCBId = null;
		this._pos = 0;
		this._previousCallTime = 0;
		this.app = app;
		this.at = at;
		this.inputor = this.app.inputor;
		this._id = this.inputor.id || this.uid();
		this.setting = null;
		this.query = null;
		const groundId = 'atwho-ground-' + this._id;
		this.el = Array.from(this.app.el.children).find((c) => c.id === groundId) || null;
		if (!this.el)
		{
			this.el = this.app.el.ownerDocument.createElement('div');
			this.el.id = groundId;
			this.app.el.appendChild(this.el);
		}
		this.model = new Model(this);
		this.view = new View(this);
	}

	get id ()
	{
		return this._id;
	}

	get expectedQueryCBId ()
	{
		return this._expectedQueryCBId;
	}

	set expectedQueryCBId (value)
	{
		this._expectedQueryCBId = value;
	}

	get pos ()
	{
		return this._pos;
	}

	set pos (value)
	{
		this._pos = value;
	}

	init (setting)
	{
		this.setting = {...(this.setting || DEFAULTS), ...setting};
		this.view.init();
		return this.model.reload(this.setting.data);
	}

	destroy ()
	{
		this.trigger('beforeDestroy');
		this.model.destroy();
		this.view.destroy();
		if (this.el.parentNode)
		{
			this.el.remove();
		}
	}

	callDefault (funcName, ...args)
	{
		try
		{
			return DEFAULT_CALLBACKS[funcName].call(this, ...args);
		}
		catch (error)
		{
			throw new Error(error + ' Or maybe At.js doesn\'t have function ' + funcName);
		}
	}

	// Fires the plain event name (and, when an alias is set, the `name-alias` variant).
	// Each event carries `detail` (args), `namespace` ('atwho') and `alias`.
	trigger (name, data)
	{
		if (data == null)
		{
			data = [];
		}
		data.push(this);
		const alias = this.setting.alias;
		const names = alias ? [name, name + '-' + alias] : [name];
		let result = true;
		names.forEach((eventName) => {
			const evt = new CustomEvent(eventName, {
				bubbles: true,
				cancelable: true,
				detail: data
			});
			evt.namespace = 'atwho';
			evt.alias = alias;
			result = this.inputor.dispatchEvent(evt) && result;
		});
		return result;
	}

	callbacks (funcName)
	{
		return this.setting.callbacks[funcName] || DEFAULT_CALLBACKS[funcName];
	}

	/** @deprecated Use the typed `setting` object (`this.setting.name`) instead. */
	getOpt (name, defaultValue)
	{
		try
		{
			const value = this.setting[name];
			return typeof value === 'undefined' ? defaultValue : value;
		}
		catch (error)
		{
			return null;
		}
	}

	insertContentFor (li)
	{
		const tpl = this.setting.insertTpl;
		const itemData = {...this.view.itemDataFor(li), 'atwho-at': this.at};
		return this.callbacks('tplEval').call(this, tpl, itemData, 'onInsert');
	}

	renderView (data)
	{
		if (typeof data === 'function')
		{
			return data;
		}
		const searchKey = this.setting.searchKey;
		data = this.callbacks('sorter').call(this, this.query.text, data.slice(0, 2001), searchKey);
		return this.view.render(data.slice(0, this.setting.limit));
	}

	lookUp (e)
	{
		let query, wait;
		if (e && e.type === 'click' && !this.setting.lookUpOnClick)
		{
			return;
		}
		if (this.setting.suspendOnComposing && this.app.isComposing)
		{
			return;
		}
		query = this.catchQuery(e);
		if (!query)
		{
			this.expectedQueryCBId = null;
			return query;
		}
		this.app.setContextFor(this.at);
		wait = this.setting.delay;
		if (wait)
		{
			this._delayLookUp(query, wait);
		}
		else
		{
			this._lookUp(query);
		}
		return query;
	}

	stopDelayedCall ()
	{
		this._stopDelayedCall();
	}

	_delayLookUp (query, wait)
	{
		const now = Date.now();
		if (!this._previousCallTime)
		{
			this._previousCallTime = now;
		}
		const remaining = wait - (now - this._previousCallTime);
		if (0 < remaining && remaining < wait)
		{
			this._previousCallTime = now;
			this._stopDelayedCall();
			return (this._delayedCallTimeout = setTimeout(() => {
				this._previousCallTime = 0;
				this._delayedCallTimeout = null;
				return this._lookUp(query);
			}, wait));
		}
		this._stopDelayedCall();
		if (this._previousCallTime !== now)
		{
			this._previousCallTime = 0;
		}
		return this._lookUp(query);
	}

	_stopDelayedCall ()
	{
		if (this._delayedCallTimeout)
		{
			clearTimeout(this._delayedCallTimeout);
			return (this._delayedCallTimeout = null);
		}
	}

	_generateQueryCBId ()
	{
		return {};
	}

	_lookUp (query)
	{
		const callback = (queryCBId, data) => {
			if (queryCBId !== this.expectedQueryCBId)
			{
				return;
			}
			if (data && data.length > 0)
			{
				return this.renderView(toDefaultHash(data));
			}
			return this.view.hide();
		};
		this.expectedQueryCBId = this._generateQueryCBId();
		return this.model.query(query.text, callback.bind(this, this.expectedQueryCBId));
	}
}

/* ------------------------------------------------------------------ *
 * TextareaController
 * ------------------------------------------------------------------ */

class TextareaController extends Controller
{
	constructor (app, at)
	{
		super(app, at);
		this._lastInputRect = null;
		this._mirror = null;
		this._mirrorMetrics = null;
	}

	_buildMirror ()
	{
		const doc = this.inputor.ownerDocument;
		const mirror = doc.createElement('div');
		const style = mirror.style;
		mirror.setAttribute('aria-hidden', 'true');
		style.position = 'fixed';
		style.visibility = 'hidden';
		style.pointerEvents = 'none';
		style.top = '0';
		style.left = '0';
		style.overflow = 'hidden';
		style.whiteSpace = 'pre-wrap';
		if (this.inputor.nodeName !== 'INPUT')
		{
			style.wordWrap = 'break-word';
		}
		doc.body.appendChild(mirror);
		this._mirror = mirror;
		return mirror;
	}

	// Re-copy the textarea's computed styles only when its box size changes.
	_refreshMirror ()
	{
		const textarea = this.inputor;
		const rect = textarea.getBoundingClientRect();
		const last = this._lastInputRect;
		if (this._mirror && last && last.width === rect.width && last.height === rect.height)
		{
			return;
		}
		this._lastInputRect = {width: rect.width, height: rect.height};

		const mirror = this._mirror || this._buildMirror();
		const computed = textarea.ownerDocument.defaultView.getComputedStyle(textarea);
		const isInput = textarea.nodeName === 'INPUT';
		const style = mirror.style;

		style.whiteSpace = 'pre-wrap';
		if (!isInput)
		{
			style.wordWrap = 'break-word';
		}
		MIRROR_PROPERTIES.forEach((prop) => {
			if (isInput && prop === 'lineHeight')
			{
				style.lineHeight = computed.height;
			}
			else
			{
				style[prop] = computed[prop];
			}
		});
		if (isInput)
		{
			style.whiteSpace = 'pre';
		}
		style.overflow = 'hidden';

		let height = parseInt(computed.lineHeight, 10);
		if (Number.isNaN(height))
		{
			height = Math.round((parseInt(computed.fontSize, 10) || 14) * 1.2);
		}
		this._mirrorMetrics = {
			height: height,
			computedHeight: parseInt(computed.height, 10),
			borderTop: parseInt(computed.borderTopWidth, 10) || 0,
			borderLeft: parseInt(computed.borderLeftWidth, 10) || 0
		};
	}

	// Mirror-div technique (see textarea-caret-position). Returns page based {left, top, height}
	_caretOffset (pos)
	{
		const textarea = this.inputor;
		const doc = textarea.ownerDocument;
		const isInput = textarea.nodeName === 'INPUT';
		const value = textarea.value;
		pos = Math.max(0, Math.min(pos, value.length));

		this._refreshMirror();
		const mirror = this._mirror;
		const metrics = this._mirrorMetrics;

		if (!isInput && textarea.scrollHeight > metrics.computedHeight)
		{
			mirror.style.overflowY = 'scroll';
		}
		else
		{
			mirror.style.overflowY = '';
		}

		let before = value.substring(0, pos);
		if (isInput)
		{
			before = before.replace(/\s/g, '\u00a0');
		}
		mirror.textContent = before;

		const span = doc.createElement('span');
		span.textContent = value.substring(pos) || '.';
		mirror.appendChild(span);

		const base = offset(textarea);
		return {
			left: base.left + span.offsetLeft + metrics.borderLeft - textarea.scrollLeft,
			top: base.top + span.offsetTop + metrics.borderTop - textarea.scrollTop,
			height: metrics.height
		};
	}

	destroy ()
	{
		super.destroy();
		if (this._mirror)
		{
			this._mirror.remove();
		}
		this._mirror = null;
	}

	catchQuery ()
	{
		const content = this.inputor.value;
		const caretPos = this.inputor.selectionStart;
		const subtext = content.slice(0, caretPos);
		let query = this.callbacks('matcher').call(this, this.at, subtext, this.setting.startWithSpace, this.setting.acceptSpaceBar);
		const isString = typeof query === 'string';
		if (isString && query.length < nullish(this.setting.minLen, 0))
		{
			return;
		}
		if (isString && query.length <= nullish(this.setting.maxLen, 20))
		{
			const start = caretPos - query.length;
			const end = start + query.length;
			this.pos = start;
			query = {
				'text': query,
				'headPos': start,
				'endPos': end
			};
			this.trigger('matched', [this.at, query.text]);
		}
		else
		{
			query = null;
			this.view.hide();
		}
		return (this.query = query);
	}

	rect ()
	{
		const c = this._caretOffset(this.pos - 1);
		if (!c)
		{
			return;
		}
		if (this.app.iframe && !this.app.iframeAsRoot)
		{
			const iframeOffset = offset(this.app.iframe);
			c.left += iframeOffset.left;
			c.top += iframeOffset.top;
		}
		return {
			left: c.left,
			top: c.top,
			bottom: c.top + c.height + 2
		};
	}

	insert (content, li)
	{
		const inputor = this.inputor;
		const source = inputor.value;
		const startStr = source.slice(0, Math.max(this.query.headPos - this.at.length, 0));
		let suffix = this.setting.suffix;
		suffix = suffix === '' ? suffix : suffix || ' ';
		content += suffix;
		inputor.value = `${startStr}${content}${source.slice(this.query.endPos || 0)}`;
		const caret = startStr.length + content.length;
		inputor.setSelectionRange(caret, caret);
		if (inputor.ownerDocument.activeElement !== inputor)
		{
			inputor.focus();
		}
		return inputor.dispatchEvent(new Event('change', {bubbles: true}));
	}
}

/* ------------------------------------------------------------------ *
 * EditableController
 * ------------------------------------------------------------------ */

class EditableController extends Controller
{
	_getRange ()
	{
		const sel = this.app.window.getSelection();
		if (sel.rangeCount > 0)
		{
			return sel.getRangeAt(0);
		}
	}

	_setRange (position, node, range)
	{
		if (range == null)
		{
			range = this._getRange();
		}
		if (!(range && node))
		{
			return;
		}
		if (position === 'after')
		{
			range.setEndAfter(node);
			range.setStartAfter(node);
		}
		else
		{
			range.setEndBefore(node);
			range.setStartBefore(node);
		}
		range.collapse(false);
		return this._clearRange(range);
	}

	_clearRange (range)
	{
		if (range == null)
		{
			range = this._getRange();
		}
		const sel = this.app.window.getSelection();
		// note: ctrl_a_pressed was never assigned in the original; the guard is vestigial.
		if (this.ctrl_a_pressed == null)
		{
			sel.removeAllRanges();
			return sel.addRange(range);
		}
	}

	_movingEvent (e)
	{
		return e.type === 'click' || ARROW_KEYS.includes(keyName(e));
	}

	_unwrap (node)
	{
		const parent = node.parentNode;
		if (parent && parent.parentNode)
		{
			replaceWithChildren(parent);
		}
		const next = node.nextSibling;
		if (next && next.nodeValue)
		{
			node.nodeValue += next.nodeValue;
			next.remove();
		}
		return node;
	}

	_activeQueryEl (range)
	{
		const active = closestElement(range.startContainer, '.atwho-query, .atwho-inserted');
		if (active)
		{
			return active;
		}
		for (const query of this.inputor.querySelectorAll('.atwho-query'))
		{
			if (query.classList.contains('atwho-inserted'))
			{
				continue;
			}
			if (range.startContainer === query.parentNode && query.parentNode.childNodes[range.startOffset - 1] === query)
			{
				return query;
			}
			if (range.startContainer.nodeType === 3 && range.startOffset === 0 && range.startContainer.previousSibling === query)
			{
				return query;
			}
		}
		return null;
	}

	_retireQuery (query)
	{
		query.classList.remove('atwho-query');
		const fullText = query.getAttribute('data-atwho-inserted-text');
		if (fullText !== null)
		{
			query.classList.add('atwho-inserted');
			if (query.textContent !== fullText)
			{
				query.textContent = fullText;
			}
			return;
		}
		if (!query.textContent && query.parentNode)
		{
			query.remove();
			return;
		}
		replaceWithChildren(query);
	}

	catchQuery (e)
	{
		const doc = this.app.document;
		const range = this._getRange();
		if (!range)
		{
			return;
		}
		if (!range.collapsed)
		{
			return;
		}
		if (!e)
		{
			return;
		}
		if (keyName(e) === KEY.ENTER)
		{
			let queryEl = closestElement(range.startContainer, '.atwho-query');
			if (queryEl)
			{
				const wasEmpty = !queryEl.firstChild;
				replaceWithChildren(queryEl);
				if (wasEmpty && queryEl.parentNode)
				{
					queryEl.remove();
				}
			}
			let queries = Array.from(this.inputor.querySelectorAll('.atwho-query'));
			queries.forEach((q) => {
				q.textContent = q.textContent;
			});
			let lastContent = null;
			queries.forEach((q) => {
				if (q.lastChild)
				{
					lastContent = q.lastChild;
				}
			});
			if (lastContent && lastContent.parentNode && lastContent.parentNode.parentNode)
			{
				replaceWithChildren(lastContent.parentNode);
			}
			this._clearRange();
			return;
		}
		if (IS_FIREFOX)
		{
			if (range.startContainer === this.inputor)
			{
				this._clearRange();
				return;
			}
			if (keyName(e) === KEY.BACKSPACE && range.startContainer.nodeType === document.ELEMENT_NODE && (range.startOffset - 1) >= 0)
			{
				const rangeClone = range.cloneRange();
				rangeClone.setStart(range.startContainer, range.startOffset - 1);
				const lastCloned = rangeClone.cloneContents().lastChild;
				if (lastCloned && lastCloned.nodeType === 1 && lastCloned.matches('.atwho-inserted'))
				{
					const inserted = range.startContainer.childNodes[range.startOffset - 1];
					this._setRange('after', inserted ? inserted.lastChild : null);
				}
			}
			else if (keyName(e) === KEY.LEFT && range.startContainer.nodeType === document.TEXT_NODE)
			{
				const prevInserted = range.startContainer.previousSibling;
				if (prevInserted && prevInserted.nodeType === 1 && prevInserted.matches('.atwho-inserted') && range.startOffset === 0)
				{
					this._setRange('after', prevInserted.lastChild);
				}
			}
		}
		const active = this._activeQueryEl(range);
		this.inputor.querySelectorAll('.atwho-query').forEach((query) => {
			if (query !== active)
			{
				this._retireQuery(query);
			}
		});
		if (active && active.classList.contains('atwho-inserted'))
		{
			active.classList.add('atwho-query');
		}
		let queries = active && active.classList.contains('atwho-query') ? [active] : [];
		const queriesText = () => queries.map((q) => q.textContent).join('');
		if (queries.length > 0 && queries.some((q) => q.childNodes.length === 0) && queriesText().length === 0)
		{
			queries.forEach((q) => {
				if (q.parentNode)
				{
					q.remove();
				}
			});
		}
		const isEscape = keyName(e) === KEY.ESC;
		if (!this._movingEvent(e) && !isEscape)
		{
			queries.forEach((q) => q.classList.remove('atwho-inserted'));
		}
		if (queries.length > 0)
		{
			switch (keyName(e))
			{
				case KEY.LEFT:
					this._setRange('before', queries[0], range);
					queries.forEach((q) => q.classList.remove('atwho-query'));
					return;
				case KEY.RIGHT:
					this._setRange('after', queries[0].nextSibling, range);
					queries.forEach((q) => q.classList.remove('atwho-query'));
					return;
			}
		}
		const queryContent = queries.length > 0 && !isEscape && !this._movingEvent(e) ? queries[0].getAttribute('data-atwho-at-query') : null;
		if (queries.length > 0 && queryContent)
		{
			queries.forEach((q) => {
				q.innerHTML = '';
				q.innerHTML = queryContent;
				q.removeAttribute('data-atwho-at-query');
			});
			this._setRange('after', queries[0], range);
		}
		const rangeClone = range.cloneRange();
		rangeClone.setStart(range.startContainer, 0);
		const matched = this.callbacks('matcher').call(this, this.at, rangeClone.toString(), this.setting.startWithSpace, this.setting.acceptSpaceBar);
		const isString = typeof matched === 'string';
		if (queries.length === 0 && isString && (range.startOffset - this.at.length - matched.length) >= 0)
		{
			range.setStart(range.startContainer, range.startOffset - this.at.length - matched.length);
			const span = doc.createElement('span');
			const attrs = this.setting.editableAtwhoQueryAttrs || {};
			Object.entries(attrs).forEach(([k, v]) => span.setAttribute(k, v));
			span.classList.add('atwho-query');
			range.surroundContents(span);
			queries = [span];
			const lastNode = span.lastChild;
			if (lastNode)
			{
				if (IS_FIREFOX)
				{
					range.setStart(lastNode, lastNode.length);
					range.setEnd(lastNode, lastNode.length);
					this._clearRange(range);
				}
				else
				{
					this._setRange('after', lastNode, range);
				}
			}
		}
		if (isString && matched.length < nullish(this.setting.minLen, 0))
		{
			return;
		}
		const queryEl = queries[0] || null;
		if (isString && matched.length <= nullish(this.setting.maxLen, 20))
		{
			const query = {
				text: matched,
				el: queryEl
			};
			this.trigger('matched', [this.at, query.text]);
			return (this.query = query);
		}
		this.view.hide();
		this.query = {
			el: queryEl
		};
		if (queriesText().indexOf(this.at) >= 0)
		{
			if (this._movingEvent(e) && queries.some((q) => q.classList.contains('atwho-inserted')))
			{
				queries.forEach((q) => q.classList.remove('atwho-query'));
			}
			else if (queries.some((q) => q.hasAttribute('data-atwho-inserted-text')))
			{
				// An inserted mention is atomic: restore its full text, never rebuild it from a partial caret match
				queries.forEach((q) => {
					const full = q.getAttribute('data-atwho-inserted-text');
					if (full !== null && q.textContent !== full)
					{
						q.textContent = full;
					}
					q.classList.remove('atwho-query');
				});
			}
			else if (false !== this.callbacks('afterMatchFailed').call(this, this.at, queryEl))
			{
				const flat = queriesText();
				queries.forEach((q) => {
					q.textContent = flat;
				});
				const first = queryEl ? queryEl.firstChild : null;
				if (first)
				{
					this._setRange('after', this._unwrap(first));
				}
			}
		}
		return null;
	}

	rect ()
	{
		const el = this.query.el;
		if (!el)
		{
			return;
		}
		if (!el.getClientRects().length)
		{
			return;
		}
		const bounds = el.getBoundingClientRect();
		let rect;
		if (this.app.iframe && !this.app.iframeAsRoot)
		{
			const iframeOffset = offset(this.app.iframe);
			rect = {
				left: bounds.left + iframeOffset.left,
				top: bounds.top + iframeOffset.top
			};
		}
		else
		{
			rect = offset(el);
		}
		rect.bottom = rect.top + bounds.height;
		return rect;
	}

	insert (content, li)
	{
		const inputor = this.inputor;
		if (inputor.ownerDocument.activeElement !== inputor)
		{
			inputor.focus();
		}
		const overrides = this.setting.functionOverrides;
		if (overrides.insert)
		{
			return overrides.insert.call(this, content, li);
		}
		let suffix = this.setting.suffix;
		suffix = suffix === '' ? suffix : suffix || '\u00A0';
		const itemData = this.view.itemDataFor(li);
		const el = this.query.el;
		if (el)
		{
			el.classList.remove('atwho-query');
			el.classList.add('atwho-inserted');
			el.innerHTML = content;
			el.setAttribute('data-atwho-at-query', '' + itemData['atwho-at'] + this.query.text);
			el.setAttribute('data-atwho-inserted-text', el.textContent);
		}
		const range = this._getRange();
		if (range)
		{
			if (el)
			{
				range.setEndAfter(el);
			}
			range.collapse(false);
			const suffixNode = this.app.document.createTextNode('\u200B' + suffix);
			range.insertNode(suffixNode);
			this._setRange('after', suffixNode, range);
		}
		if (inputor.ownerDocument.activeElement !== inputor)
		{
			inputor.focus();
		}
		return inputor.dispatchEvent(new Event('change', {bubbles: true}));
	}
}

/* ------------------------------------------------------------------ *
 * Model
 * ------------------------------------------------------------------ */

class Model
{
	constructor (context)
	{
		this._items = null;
		this.context = context;
		this.at = this.context.at;
		this.storage = this.context.inputor;
	}

	destroy ()
	{
		this._items = null;
		return null;
	}

	saved ()
	{
		return this.fetch() > 0;
	}

	query (query, callback)
	{
		let items = this.fetch();
		const searchKey = this.context.setting.searchKey;
		items = this.context.callbacks('filter').call(this.context, query, items, searchKey) || [];
		const remoteFilter = this.context.callbacks('remoteFilter');
		if (items.length > 0 || (!remoteFilter && items.length === 0))
		{
			return callback(items);
		}
		return remoteFilter.call(this.context, query, callback);
	}

	fetch ()
	{
		return this._items || [];
	}

	save (items)
	{
		this._items = this.context.callbacks('beforeSave').call(this.context, items || []);
		return this._items;
	}

	load (items)
	{
		if (!(this.saved() || !items))
		{
			return this._load(items);
		}
	}

	reload (items)
	{
		return this._load(items);
	}

	_load (items)
	{
		if (typeof items === 'string')
		{
			// The URL path stays promise-based; the array path below stays
			// synchronous, exactly like the input file.
			return fetch(items, {headers: {Accept: 'application/json'}})
				.then((res) => {
					if (!res.ok)
					{
						throw new Error('HTTP error ' + res.status);
					}
					return res.json();
				})
				.then((json) => this.save(json));
		}
		return this.save(items);
	}
}

/* ------------------------------------------------------------------ *
 * View
 * ------------------------------------------------------------------ */

class View
{
	constructor (context)
	{
		this._itemData = new WeakMap();
		this._scrollToken = null;
		this._stopShowing = false;
		this._timeoutID = null;
		this.context = context;
		const doc = this.context.el.ownerDocument;
		this.el = h(doc, 'div', {class: 'atwho-view'});
		this.ul = h(doc, 'ul', {class: 'atwho-view-ul'});
		this.el.append(this.ul);
		this._listeners = [];
		this.context.el.appendChild(this.el);
		this.bindEvent();
	}

	itemDataFor (li)
	{
		return this._itemData.get(li);
	}

	init ()
	{
		const id = this.context.setting.alias || this.context.at.charCodeAt(0);
		const headerTpl = this.context.setting.headerTpl;
		if (headerTpl && this.el.children.length === 1)
		{
			this.el.insertAdjacentHTML('afterbegin', headerTpl);
		}
		this.el.setAttribute('id', 'at-view-' + id);
	}

	destroy ()
	{
		offAll(this._listeners);
		clearTimeout(this._timeoutID);
		this._timeoutID = null;
		this.el.remove();
	}

	bindEvent ()
	{
		const menu = this.ul;
		const list = this._listeners;
		let lastCoordX = 0;
		let lastCoordY = 0;

		on(list, menu, 'mousemove', (e) => {
			const li = e.target && e.target.closest ? e.target.closest('li') : null;
			if (!li || !menu.contains(li))
			{
				return;
			}
			if (lastCoordX === e.clientX && lastCoordY === e.clientY)
			{
				return;
			}
			lastCoordX = e.clientX;
			lastCoordY = e.clientY;
			if (li.classList.contains('cur'))
			{
				return;
			}
			Array.from(menu.querySelectorAll('.cur')).forEach((c) => c.classList.remove('cur'));
			li.classList.add('cur');
		});

		on(list, menu, 'click', (e) => {
			const li = e.target && e.target.closest ? e.target.closest('li') : null;
			if (!li || !menu.contains(li))
			{
				return;
			}
			Array.from(menu.querySelectorAll('.cur')).forEach((c) => c.classList.remove('cur'));
			li.classList.add('cur');
			this.choose(e);
			e.preventDefault();
		});
	}

	visible ()
	{
		return isVisible(this.el);
	}

	highlighted ()
	{
		return this.el.querySelector('.cur') !== null;
	}

	choose (e)
	{
		const li = this.el.querySelector('.cur');
		if (li)
		{
			const content = this.context.insertContentFor(li);
			this.context.stopDelayedCall();
			this.context.insert(this.context.callbacks('beforeInsert').call(this.context, content, li, e), li);
			this.context.trigger('inserted', [li, e]);
			this.hide(e);
		}
		if (this.context.setting.hideWithoutSuffix)
		{
			return (this._stopShowing = true);
		}
	}

	reposition (rect)
	{
		const win = this.context.app.iframeAsRoot ? this.context.app.window : window;
		const docEl = win.document.documentElement;
		const height = this.el.getBoundingClientRect().height;
		const width = this.el.getBoundingClientRect().width;
		if (rect.bottom + height - win.scrollY > docEl.clientHeight)
		{
			rect.bottom = rect.top - height;
		}
		const overflowOffset = docEl.clientWidth - width - 5;
		if (rect.left > overflowOffset)
		{
			rect.left = overflowOffset;
		}
		const pos = {
			left: rect.left,
			top: rect.bottom
		};
		const ref = this.context.callbacks('beforeReposition');
		if (ref != null)
		{
			ref.call(this.context, pos);
		}
		setOffset(this.el, pos);
		return this.context.trigger('reposition', [pos]);
	}

	next ()
	{
		return this._move(1);
	}

	prev ()
	{
		return this._move(-1);
	}

	_move (dir)
	{
		const current = this.el.querySelector('.cur');
		const items = this.el.querySelectorAll('li');
		if (!items.length)
		{
			return;
		}
		if (current)
		{
			current.classList.remove('cur');
		}
		let selected = null;
		if (current)
		{
			selected = dir > 0 ? current.nextElementSibling : current.previousElementSibling;
		}
		if (!selected)
		{
			selected = dir > 0 ? items[0] : items[items.length - 1];
		}
		selected.classList.add('cur');
		return this._scrollTo(Math.max(0, this._itemBottom(selected) - this.el.getBoundingClientRect().height));
	}

	_itemBottom (el)
	{
		return el.offsetTop + el.offsetHeight + (el.nextSibling ? el.nextSibling.offsetHeight : 0);
	}

	scrollTop (value)
	{
		return this._scrollTo(value);
	}

	_scrollTo (value)
	{
		const duration = this.context.setting.scrollDuration;
		const el = this.ul;
		const token = {};
		this._scrollToken = token;
		if (!duration)
		{
			el.scrollTop = value;
			return;
		}
		const start = el.scrollTop;
		const delta = value - start;
		const startTime = performance.now();
		const step = (now) => {
			if (this._scrollToken !== token)
			{
				return;
			}
			const progress = Math.min((now - startTime) / duration, 1);
			el.scrollTop = start + delta * progress;
			if (progress < 1)
			{
				requestAnimationFrame(step);
			}
		};
		requestAnimationFrame(step);
	}

	show ()
	{
		if (this._stopShowing)
		{
			this._stopShowing = false;
			return;
		}
		if (!this.visible())
		{
			this.el.style.display = 'block';
			this.el.scrollTop = 0;
			this.context.trigger('shown');
		}
		const rect = this.context.rect();
		if (rect)
		{
			return this.reposition(rect);
		}
	}

	hide (e, time)
	{
		if (!this.visible())
		{
			return;
		}
		if (typeof time !== 'number')
		{
			this.el.style.display = 'none';
			return this.context.trigger('hidden', [e]);
		}
		const callback = () => this.hide();
		clearTimeout(this._timeoutID);
		return (this._timeoutID = setTimeout(callback, time));
	}

	render (list)
	{
		if (!(Array.isArray(list) && list.length > 0))
		{
			this.hide();
			return;
		}
		const ul = this.ul;
		ul.innerHTML = '';
		const tpl = this.context.setting.displayTpl;
		const doc = this.el.ownerDocument;
		const template = doc.createElement('template');
		for (let i = 0, len = list.length; i < len; i++)
		{
			const item = {...list[i], 'atwho-at': this.context.at};
			const liHtml = this.context.callbacks('tplEval').call(this.context, tpl, item, 'onDisplay');
			const html = this.context.callbacks('highlighter').call(this.context, liHtml, this.context.query.text);
			template.innerHTML = String(html).trim();
			Array.from(template.content.childNodes).forEach((node) => {
				if (node.nodeType === 1)
				{
					this._itemData.set(node, item);
				}
				ul.appendChild(node);
			});
		}
		this.show();
		if (this.context.setting.highlightFirst)
		{
			const first = ul.querySelector('li');
			if (first)
			{
				return first.classList.add('cur');
			}
		}
	}
}

/* ------------------------------------------------------------------ *
 * Public API
 * ------------------------------------------------------------------ */

const EDITABLE_SELECTOR = 'textarea, input, [contenteditable=""], [contenteditable=true]';
const APP_METHODS = new Set([
	'load', 'isSelecting', 'hide', 'reposition', 'setIframe', 'run', 'destroy'
]);

const appRefs = new WeakMap();

function getApp (el)
{
	let app = appRefs.get(el);
	if (!app)
	{
		app = new App(el);
		appRefs.set(el, app);
	}
	return app;
}

const AtWho = {
	attach (el, options)
	{
		if (!el || !el.matches || !el.matches(EDITABLE_SELECTOR))
		{
			return null;
		}
		const app = getApp(el);
		options = options || {};
		app.reg(options.at, options);
		return app;
	},
	method (el, name, ...args)
	{
		if (!el || !el.matches || !el.matches(EDITABLE_SELECTOR))
		{
			return null;
		}
		const app = getApp(el);
		if (APP_METHODS.has(name) && app instanceof App)
		{
			return app[name](...args);
		}
		throw new Error('Method ' + name + ' does not exist on AtWho');
	},
	defaults: Object.freeze(DEFAULTS),
	debug: false
};

// The application loads this file as a classic deferred script, so a bare
// `export` would make the browser reject it. Keep the original registrations.
if (typeof define === 'function' && define.amd)
{
	define('atwho', [], () => AtWho);
}
else if (typeof module === 'object' && module.exports)
{
	module.exports = AtWho;
}
else
{
	(typeof window !== 'undefined' ? window : (typeof self !== 'undefined' ? self : this)).AtWho = AtWho;
}

/* ------------------------------------------------------------------ *
 * ## API
 *
 * AtWho.attach(inputEl, options) -> App
 * AtWho.method(inputEl, name, ...args) -> any
 * AtWho.defaults  // frozen defaults object
 * AtWho.debug     // boolean
 *
 * Events (bubbling CustomEvents on the input; detail = args, evt.namespace = 'atwho',
 * evt.alias; with an alias the `name-alias` variant is also fired):
 *   matched, inserted, shown, hidden, reposition, beforeDestroy
 * ------------------------------------------------------------------ */

