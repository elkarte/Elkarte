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
			array('integrate_post_bbc_parser', 'Ila_Integrate::integrate_post_bbc_parser'),
			array('integrate_before_prepare_display_context', 'Ila_Integrate::integrate_before_prepare_display_context'),
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
			if (!isset($context['attach_source']))
				$context['attach_source'] = 0;

			if (!empty($_REQUEST['id_draft']))
			{
				// Previewing a draft
				$msg_id = (int) $_REQUEST['id_draft'];
			}
			else
			{
				// Previewing a modified message, check for a value in $_REQUEST['msg']
				$msg_id = empty($cache_id) ? (isset($_REQUEST['msg']) ? (int) $_REQUEST['msg'] : -1) : preg_replace('~[^\d]~', '', $cache_id);
			}

			$ila_parser = new In_Line_Attachment($message, $msg_id, $context['attach_source']);
			$message = $ila_parser->hide_bbc();
		}
	}

	/**
	 * Subs hook, integrate_post_bbc_parser
	 *
	 * - Allow addons access to what parse_bbc created, here we call ILA to render its tags
	 *
	 * @param string $message
	 * @param mixed[] $smileys
	 * @param string $cache_id
	 * @param string[]|null $parse_tags
	 */
	public static function integrate_post_bbc_parser(&$message)
	{
		global $context;

		// Enabled and we have tags, time to render them
		if (empty($context['uninstalling']) && stripos($message, '[attach') !== false)
		{
			if (!empty($_REQUEST['id_draft']))
			{
				// Previewing a draft
				$msg_id = (int) $_REQUEST['id_draft'];
			}
			else
			{
				// Previewing a modified message, check for a value in $_REQUEST['msg']
				$msg_id = isset($_REQUEST['msg']) ? (int) $_REQUEST['msg'] : -1;
			}

			$ila_parser = new In_Line_Attachment($message, $cache_id);
			$message = $ila_parser->ila_parse_bbc();
		}
	}

	/**
	 * Display controller hook, called from prepareDisplayContext_callback integrate_before_prepare_display_context
	 *
	 * - Drops attachments from the message if they were rendered inline
	 *
	 * @param mixed[] $output
	 */
	public static function integrate_before_prepare_display_context(&$message)
	{
		global $context, $attachments;

		if (empty($context['ila_dont_show_attach_below']) || empty($attachments[$message['id_msg']]))
			return;

		// If the attachment has been used inline, drop it so its not shown below the message as well
		foreach ($attachments[$message['id_msg']] as $id => $attachcheck)
		{
			if (in_array($attachcheck['id_attach'], $context['ila_dont_show_attach_below']))
				unset($attachments[$message['id_msg']][$id]);
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