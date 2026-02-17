<?php

/**
 * The main abstract theme class
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\Themes;

use ElkArte\Helper\HttpReq;
use ElkArte\Helper\ValuesContainer;
use ElkArte\Themes\Traits\ContextManagement;
use ElkArte\Themes\Traits\FeatureIntegration;
use ElkArte\Themes\Traits\TemplateRendering;

/**
 * Class Theme
 */
abstract class Theme
{
	// Abstract Class Traits
	use TemplateRendering;
	use ContextManagement;
	use FeatureIntegration;

	/** @var string */
	public const DEFAULT_EXPIRES = 'Mon, 26 Jul 1997 05:00:00 GMT';

	/** @var int */
	public const ALL = -1;

	/** @var array */
	private const CONTENT_TYPES = [
		'fatal_error' => 'text/html',
		'json' => 'application/json',
		'xml' => 'text/xml',
		'generic_xml' => 'text/xml',
		'html' => 'text/html',
	];

	/** @var ValuesContainer */
	public $user;

	/** @var HttpReq user input variables */
	public $_req;

	/** @var int The id of the theme being used */
	protected $id;

	/** @var array */
	protected $links = [];

	/** @var string[] Holds base actions that we do not want crawled / indexed */
	public $no_index_actions = [];

	/** @var bool Right to left language support */
	protected $rtl;

	/** @var Templates */
	private $templates;

	/** @var TemplateLayers */
	private $layers;

	/** @var Javascript */
	public $javascript;

	/** @var Css */
	public $css;

	/** @var AssetManager */
	private $assetManager;

	/** @var HeadersManager */
	private $headersManager;

	/**
	 * Theme constructor.
	 *
	 * @param int $id
	 * @param ValuesContainer $user
	 * @param Directories $dirs
	 */
	public function __construct(int $id, ValuesContainer $user, Directories $dirs)
	{
		global $settings;

		$this->id = $id;
		$this->user = $user;
		$this->layers = new TemplateLayers();
		$this->templates = new Templates($dirs);
		$this->no_index_actions = [
			'profile',
			'search',
			'calendar',
			'memberlist',
			'help',
			'who',
			'stats',
			'login',
			'reminder',
			'register',
			'contact',
			'admin',
			'moderate',
			'printpage'
		];
		$this->_req = HttpReq::instance();

		// Theme posse
		$this->javascript = new Javascript();
		$this->css = new Css();

		// Initialize helper classes
		$this->assetManager = new AssetManager($this->javascript, $this->css);
		$this->headersManager = new HeadersManager($this->_req);
	}

	/**
	 * The following are expected in the custom Theme.php (or just use the default)
	 */
	abstract public function getSettings();


	/**
	 * Load the base JS that gives ElkArte functionality
	 *
	 * What it does:
	 *  - Loads core JavaScript files
	 *  - Sets up default JS variables
	 *  - Initializes PWA, video embedding, code prettify, relative times
	 *  - Handles scheduled mail sending
	 */
	public function loadThemeJavascript(): void
	{
		global $modSettings;

		// Delegate base JavaScript loading to AssetManager
		$this->assetManager->loadThemeJavascript();

		// PWA?
		$this->assetManager->progressiveWebApp();

		// Auto video embedding enabled, then load the needed JS
		$this->autoEmbedVideo();

		// Prettify code tags? Load the needed JS and CSS.
		$this->addCodePrettify();

		// Relative times for posts?
		$this->relativeTimes();

		// If we think we have mail to send, let's offer up some possibilities... robots get pain (Now with scheduled task support!)
		if (empty($modSettings['next_task_time']) || $modSettings['next_task_time'] < time() ||
			(!empty($modSettings['mail_next_send']) && $modSettings['mail_next_send'] < time() && empty($modSettings['mail_queue_use_cron'])))
		{
			$this->doScheduledSendMail();
		}
	}

	/**
	 * Get the layers associated with the current theme
	 */
	public function getLayers(): TemplateLayers
	{
		return $this->layers;
	}

	/**
	 * Get the templates associated with the current theme
	 */
	public function getTemplates(): Templates
	{
		return $this->templates;
	}

	/**
	 * Turn on/off RTL language support
	 *
	 * @param $toggle
	 *
	 * @return $this
	 */
	public function setRTL($toggle): self
	{
		$this->rtl = (bool) $toggle;

		return $this;
	}

	/**
	 * Get the value of 'api' from the request
	 *
	 * What it does:
	 *  - Retrieves the value of the 'api' parameter from the request.
	 *  - Requires that the request was made via AJAX. Validated by checking for
	 * 'HTTP_X_REQUESTED_WITH' header which much be set in fetch API and/or XMLHttpRequest with
	 * setRequestHeader('X-Requested-With', automatically set by jQuery requests.
	 *
	 * @return string|false The value of the 'api' parameter from the request, trimmed.
	 */
	public function getRequestAPI(): string|false
	{
		return $this->headersManager->getRequestAPI();
	}

	/**
	 * Add a block of inline JavaScript code to be executed later
	 *
	 * @param string $javascript
	 * @param bool $defer = false, define if the script should load in <head> or before the closing <html> tag
	 */
	public function addInlineJavascript($javascript, $defer = false): void
	{
		$this->javascript->addInlineJavascript($javascript, $defer);
	}

	/**
	 * Add a JavaScript variable for output later (for feeding text strings and similar to JS)
	 *
	 * @param array $vars array of vars to include in the output done as 'varname' => 'var value'
	 * @param bool $escape = false, whether to escape the value
	 */
	public function addJavascriptVar($vars, $escape = false): void
	{
		$this->javascript->addJavascriptVar($vars, $escape);
	}

	/**
	 * Clean (delete) the hives (cache) for CSS and JS files
	 *
	 * @param string $type (Optional) The type of hives to clean. Default is 'all'. Possible values are 'all', 'css', 'js'.
	 * @return bool Returns true if the hives are successfully cleaned, otherwise false.
	 */
	public function cleanHives($type = 'all'): bool
	{
		return $this->assetManager->cleanHives($type);
	}

	/**
	 * Load a variant CSS file if found.  Fallback if not, and it exists in this
	 * theme's directory
	 *
	 * @param string $cssFile
	 * @param bool $fallBack
	 */
	public function loadVariant($cssFile, $fallBack = true): void
	{
		$this->assetManager->loadVariant($cssFile, $fallBack);
	}

	/**
	 * Return the instance of /ElkArte/Themes/Css
	 */
	public function themeCss(): Css
	{
		return $this->css;
	}

	/**
	 * Return the instance of /ElkArte/Themes/Javascript
	 */
	public function themeJs(): Javascript
	{
		return $this->javascript;
	}

	/**
	 * Load theme variant CSS
	 */
	public function loadThemeVariant(): void
	{
		$this->assetManager->loadThemeVariant($this->_req);
	}
}
