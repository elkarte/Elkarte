<?php

/**
 * This class is responsible for generating and sending a JSON manifest file for a Progressive Web App.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

use ElkArte\Http\Headers;

/**
 * Class ManifestMinimus
 *
 * The manifest file contains the information needed to configure how the PWA
 * will look when it is added/installed to the home screen of the device, and configures how
 * it will behave when launched.
 *
 * The minimal information needed is:
 * - The canonical name of the website
 * - A short version of that name (for icons)
 * - The theme color of the website for OS integration
 * - The background color of the website for OS integration
 * - The URL scope that the progressive web app is limited to
 * - The start URL that new instances of the progressive web app will implicitly load
 * - A human-readable description
 * - Orientation restrictions (it is unwise to change this from "any" without a hard technical limit)
 * - Any icons for your website to be used on the home screen (192 and 512 are required)
 */
class ManifestMinimus
{
	public function __construct()
	{
	}

	public function create()
	{
		$this->prepareAndSendHeaders();

		echo json_encode($this->getManifestParts(), JSON_PRETTY_PRINT);
	}

	protected function prepareAndSendHeaders()
	{
		$headers = Headers::instance();

		$expires = gmdate('D, d M Y H:i:s', time() + 86400);
		$lastModified = gmdate('D, d M Y H:i:s', time());

		$headers
			->contentType('application/manifest+json')
			->header('Expires', $expires . ' GMT')
			->header('Last-Modified', $lastModified . ' GMT')
			->header('Cache-Control', 'private, max-age=86400')
			->send();
	}

	protected function getManifestParts(): array
	{
		global $mbname;

		$manifest = [];

		$manifest['name'] = un_htmlspecialchars($mbname);
		$manifest['short_name'] = $this->getShortname();
		$manifest['description'] = $this->getDescription();
		$manifest['lang'] = $this->getLanguageCode();
		$manifest['dir'] = $this->getLanguageDirection();
		$manifest['display'] = $this->getDisplay();
		$manifest['orientation'] = $this->getOrientation();
		$manifest['id'] = $this->getId();
		$manifest['start_url'] = $this->getStartUrl();
		$manifest['scope'] = $this->getScope();
		$manifest['background_color'] = $this->getBackgroundColor();
		$manifest['theme_color'] = $this->getThemeColor();
		$manifest['icons'] = $this->getManifestIcons();

		return array_filter($manifest);
	}

	protected function getDescription()
	{
		global $settings, $mbname;

		$description = un_htmlspecialchars($settings['site_slogan'] ?? $mbname);
		$description = str_replace(['<br>', '<br />'], ' ', $description);

		return strip_tags($description);

	}

	protected function getShortname()
	{
		global $modSettings, $mbname;

		return un_htmlspecialchars($modSettings['pwa_short_name'] ?? $mbname);
	}

	protected function getLanguageCode()
	{
		global $txt;

		$lang = $txt['lang_locale'] ?? 'en-US';

		return str_replace(['.utf8', '_'], ['', '-'], trim($lang));
	}

	protected function getLanguageDirection()
	{
		global $txt;

		return !empty($txt['lang_rtl']) ? 'rtl' : 'ltr';
	}

	protected function getDisplay(): string
	{
		return 'standalone';
	}

	protected function getOrientation(): string
	{
		return 'any';
	}

	protected function getId()
	{
		return trim($this->getScope(), '/') . '?elk_pwa=1';
	}

	protected function getScope()
	{
		return $this->getStartUrl();
	}

	protected function getStartUrl()
	{
		global $boardurl;

		$parts = parse_url($boardurl);

		return empty($parts['path']) ? '/' : '/' . trim($parts['path'], '/') . '/';
	}

	protected function getBackgroundColor()
	{
		global $modSettings;

		return $modSettings['pwa_background_color'] ?? '#fafafa';
	}

	protected function getThemeColor()
	{
		global $modSettings;

		return $modSettings['pwa_theme_color'] ?? '#3d6e32';
	}

	protected function getManifestIcons(): array
	{
		global $modSettings, $settings;

		$icons = [];

		$iconSmallUrl = $modSettings['pwa_small_icon'] ?? $settings['default_images_url'] . '\icon_pwa_small.png';
		$iconUrlLarge = $modSettings['pwa_large_icon'] ?? $settings['default_images_url'] . '\icon_pwa_large.png';

		if ($iconSmallUrl)
		{
			$icon = [
				'src' => $iconSmallUrl,
				'sizes' => '192x192',
				'purpose' => 'any'
			];
			$icons[] = $icon;
		}

		if ($iconUrlLarge)
		{
			$iconLarge = [
				'src' => $iconUrlLarge,
				'sizes' => '512x512',
				'purpose' => 'any'
			];
			$icons[] = $iconLarge;
		}

		return $icons;
	}
}
