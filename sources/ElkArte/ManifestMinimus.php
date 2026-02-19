<?php

/**
 * This class is responsible for generating and sending a JSON manifest file for a Progressive Web App.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte;

use ElkArte\Http\Headers;

/**
 * Class ManifestMinimus
 *
 * The manifest file contains the information needed to configure how the PWA
 * will look when it is added/installed to the home screen of the device and configures how
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

	public function create(): void
	{
		$this->prepareAndSendHeaders();

		echo json_encode($this->getManifestParts(), JSON_PRETTY_PRINT);
	}

	protected function prepareAndSendHeaders(): void
	{
		$headers = Headers::instance();

		$expires = gmdate('D, d M Y H:i:s', time() + 86400);
		$lastModified = gmdate('D, d M Y H:i:s', time());

		$headers
			->contentType('application/manifest+json')
			->header('Expires', $expires . ' GMT')
			->header('Last-Modified', $lastModified . ' GMT')
			->header('Cache-Control', 'public, max-age=3600')
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
		$manifest['screenshots'] = $this->getScreenshots();

		return array_filter($manifest);
	}

	protected function getDescription(): string
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

	protected function getLanguageDirection(): string
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

	protected function getId(): string
	{
		return trim($this->getScope(), '/') . '?elk_pwa=1';
	}

	protected function getScope(): string
	{
		return $this->getStartUrl();
	}

	protected function getStartUrl(): string
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

		// Ensure URL paths use forward slashes for web delivery
		$base = rtrim($settings['default_images_url'], '/');
		$iconSmallUrl = $modSettings['pwa_small_icon'] ?? $base . '/logos/icon_pwa_small.png';
		$iconUrlLarge = $modSettings['pwa_large_icon'] ?? $base . '/logos/icon_pwa_large.png';

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

	protected function getScreenshots(): array
	{
		global $modSettings, $settings;

		$screenshots = [];

		// Ensure URL paths use forward slashes for web delivery
		$base = rtrim($settings['default_images_url'], '/');
		$mobileScreenshot = $modSettings['pwa_mobile_screenshot'] ?? $base . '/logos/screenshot_mobile.png';
		$desktopScreenshot = $modSettings['pwa_desktop_screenshot'] ?? $base . '/logos/screenshot_desktop.png';

		// Mobile screenshot (required for richer install UI)
		if ($mobileScreenshot)
		{
			$screenshot = [
				'src' => $mobileScreenshot,
				'sizes' => '390x844',
				'type' => 'image/png',
				'form_factor' => 'narrow',
				'label' => 'Mobile view of the forum'
			];
			$screenshots[] = $screenshot;
		}

		// Desktop screenshot (optional but recommended)
		if ($desktopScreenshot)
		{
			$screenshotDesktop = [
				'src' => $desktopScreenshot,
				'sizes' => '1920x1080',
				'type' => 'image/png',
				'form_factor' => 'wide',
				'label' => 'Desktop view of the forum'
			];
			$screenshots[] = $screenshotDesktop;
		}

		return $screenshots;
	}
}
