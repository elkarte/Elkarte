<?php

/**
 * @name      Inline Attachments (ILA)
 * @license   Mozilla Public License version 1.1 http://www.mozilla.org/MPL/1.1/.
 * @author    Spuds
 * @copyright (c) 2014 Spuds
 *
 * @version 1.1 beta 1
 *
 * Based on original code by mouser http://www.donationcoder.com
 * Updated/Modified/etc with permission
 *
 */

if (!defined('ELK'))
	die('No access...');

class Ila_Integrate
{
	/**
	 * Register ILA hooks to the system
	 * @return array
	 */
	public static function register()
	{
		global $modSettings;

		if (empty($modSettings['attachment_inline_enabled']))
			return array();

		// $hook, $function, $file
		return array(
			array('integrate_bbc_codes', 'Ila_Integrate::integrate_bbc_codes'),
			array('integrate_pre_parsebbc', 'Ila_Integrate::integrate_pre_parsebbc'),
			array('integrate_post_parsebbc', 'Ila_Integrate::integrate_post_parsebbc'),
			array('integrate_prepare_display_context', 'Ila_Integrate::integrate_prepare_display_context'),
		);
	}

	/**
	 * Register ACP config hooks for setting values
	 * @return array
	 */
	public static function settingsRegister()
	{
		// $hook, $function, $file
		return array(
			array('integrate_modify_attachment_settings', 'Ila_Integrate::integrate_modify_attachment_settings'),
		);
	}

	/**
	 * - Adds in new BBC code tags for use with inline images
	 *
	 * @param mixed[] $codes
	 */
	public static function integrate_bbc_codes(&$codes)
	{
		// Add in our new codes, if found on to the end of this array
		// here mostly used to null them out should they not be rendered
		$codes = array_merge($codes, array(
			array(
				'tag' => 'attachimg',
				'type' => 'closed',
				'content' => '',
			),
			array(
				'tag' => 'attachurl',
				'type' => 'closed',
				'content' => '',
			),
			array(
				'tag' => 'attachmini',
				'type' => 'closed',
				'content' => '',
			),
			array(
				'tag' => 'attachthumb',
				'type' => 'closed',
				'content' => '',
			))
		);

		return;
	}

	/**
	 * Subs hook, integrate_pre_parsebbc
	 *
	 * - Allow addons access before entering the main parse_bbc loop
	 * - Prevents parseBBC from working on these tags at all
	 *
	 * @param string $message
	 * @param smixed[] $smileys
	 * @param string $cache_id
	 * @param string[]|null $parse_tags
	 */
	public static function integrate_pre_parsebbc(&$message, &$smileys, &$cache_id, &$parse_tags)
	{
		global $context;

		// Enabled and we have ila tags, then hide them from parsebbc where appropriate
		if (empty($parse_tags) && empty($context['uninstalling']) && stripos($message, '[attach') !== false)
		{
			require_once(SUBSDIR . '/InLineAttachment.class.php');
			ila_hide_bbc($message);
		}
	}

	/**
	 * Subs hook, integrate_post_parsebbc
	 *
	 * - Allow addons access to what parse_bbc created, here we call ILA to render its tags
	 *
	 * @param string $message
	 * @param mixed[] $smileys
	 * @param string $cache_id
	 * @param string[]|null $parse_tags
	 */
	public static function integrate_post_parsebbc(&$message, &$smileys, &$cache_id, &$parse_tags)
	{
		global $context;

		// Enabled and we have tags, time to render them
		if (empty($parse_tags) && empty($context['uninstalling']) && stripos($message, '[attach') !== false)
		{
			$ila_parser = new In_Line_Attachment($message, $cache_id);
			$message = $ila_parser->ila_parse_bbc();
		}
	}

	/**
	 * Display controller hook, called from prepareDisplayContext_callback integrate_prepare_display_context
	 *
	 * - Drops attachments from the message if they were rendered inline
	 *
	 * @param mixed[] $output
	 */
	public static function integrate_prepare_display_context(&$output)
	{
		global $context;

		if (empty($context['ila_dont_show_attach_below'][$output['id']]))
			return;

		// If the attachment has been used inline, drop it so its not shown below the message as well
		foreach ($output['attachment'] as $id => $attachcheck)
		{
			if (array_key_exists($attachcheck['id'], $context['ila_dont_show_attach_below'][$output['id']]))
				unset($output['attachment'][$id]);
		}
	}

	/**
	 * - Defines our settings array and uses our settings class to manage the data
	 *
	 * @param array $config_vars
	 */
	public static function integrate_modify_attachment_settings(&$config_vars)
	{
		$config_vars[] = array('title', 'attachment_inline_title');
		$config_vars[] = array('check', 'attachment_inline_enabled');
		$config_vars[] = array('check', 'attachment_inline_basicmenu');
	}
}