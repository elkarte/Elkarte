<?php

/**
 * The besocial theme
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\Themes\Besocial;

use ElkArte\Themes\Theme as BaseTheme;

/**
 * Class Theme
 *
 * - Extends the abstract theme class
 * - Override any base methods here if this theme requires custom behavior
 * - Themes can override specific trait methods to customize behavior while keeping the rest of the functionality intact.
 * - Example of a custom theme extending the base theme:
 *
 * class CustomTheme extends Theme
 * {
 *      public function getSettings()
 *      {
 *          // Custom theme settings
 *      }
 *
 *      // Can override any trait methods as needed
 *      public function setupThemeContext($forceload = false): void
 *      {
 *          // Custom context setup
 *          parent::setupThemeContext($forceload);
 *
 *          // Additional custom logic
 *      }
 * }
 *
 * @package Themes\BesocialTheme
 */
class Theme extends BaseTheme
{
	/**
	 * Initialize the template... mainly little settings.
	 *
	 * @return array Theme settings
	 */
	public function getSettings()
	{
		return [
			/*
			 * Specifies whether images from default theme shall be
			 * fetched instead of the current theme when using
			 * templates from the default theme.
			 *
			 * - if this is 'always', images from the default theme will be used.
			 * - if this is 'defaults', images from the default theme will only be used with default templates.
			 * - if this is 'never' or isn't set at all, images from the default theme will not be used.
			 *
			 * This doesn't apply when custom templates are being
			 * used; nor does it apply to the default theme.
			 */
			'use_default_images' => 'never',

			/*
			 * The version this template/theme is for. This should
			 * be the version of the forum it was created for.
			 */
			'theme_version' => '2.0',

			/*
			 * Whether this theme requires the optional theme strings
			 * file to be loaded. (ThemeStrings.[language].php)
			 */
			'require_theme_strings' => false,

			/*
			 * Specify the color variants. Each variant has its own
			 * directory, where additional CSS files may be loaded.
			 */
			'theme_variants' => [
				'besocial'
			],

			/*
			 * Provides avatars for use on various indexes.
			 *
			 * Possible values:
			 * - 0 or not set, no avatars are available
			 * - 1 avatar of the poster of the last message
			 * - 2 avatar of the poster of the first message
			 * - 3 both avatars
			 *
			 * Since grabbing the avatar requires some work, it is
			 * better to set the variable to a sensible value
			 * depending on the needs of the theme.
			 */
			'avatars_on_indexes' => 1,

			/*
			 * This is used in the main menus to create a number next
			 * to the title of the menu to indicate the number of
			 * unread messages, moderation reports, etc. You can
			 * style each menu level indicator as desired.
			 */
			'menu_numeric_notice' => [
				// Empty top level menu entries
				-1 => ' <span class="pm_indicator" style="display: none">%1$s</span>',
				// Top level menu entries
				0 => ' <span class="pm_indicator">%1$s</span>',
				// First dropdown
				1 => ' <span>[<strong>%1$s</strong>]</span>',
				// Second level dropdown
				2 => ' <span>[<strong>%1$s</strong>]</span>',
			],

			// This array deals with page indexes.
			'page_index_template' => [
				'base_link' => '<li class="linavPages"><a class="navPages" href="{base_link}">%2$s</a></li>',
				'previous_page' => '<span class="previous_page">{prev_txt}</span>',
				'current_page' => '<li class="linavPages"><strong class="current_page">%1$s</strong></li>',
				'next_page' => '<span class="next_page">{next_txt}</span>',
				'expand_pages' => '<li class="linavPages expand_pages" {custom}> <a href="#">&#8230;</a> </li>',
				'all' => '<span class="linavPages all_pages">{all_txt}</span>',
				'none' => '<li class="hide"><a href="#"></a></li>',
			],

			'mentions' => [
				'mentioner_template' => '<a href="{mem_url}" class="mentionavatar">{avatar_img}{mem_name}</a>',
			]
		];
	}
}
