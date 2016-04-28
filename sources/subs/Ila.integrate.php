<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 beta 1
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Class Ila_Integrate
 */
class Ila_Integrate
{
	/**
	 * Register ILA hooks to the system
	 *
	 * @return array
	 */
	public static function register()
	{
		global $modSettings;

		if (empty($modSettings['attachment_inline_enabled']))
			return array();

		// $hook, $function, $file
		return array(
			array('integrate_additional_bbc', 'Ila_Integrate::integrate_additional_bbc'),
			array('integrate_before_prepare_display_context', 'Ila_Integrate::integrate_before_prepare_display_context'),
		);
	}

	/**
	 * Register ACP config hooks for setting values
	 *
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
	public static function integrate_additional_bbc(&$codes)
	{
		global $scripturl;

		// Generally we don't want to render inside of these tags ...
		// @todo move quote to ACP as option?
		$disallow = array(
			'quote' => 1,
			'code' => 1,
			'nobbc' => 1,
			'html' => 1,
			'php' => 1,
		);

		// Add ILA codes
		$codes = array_merge($codes, array(
			// Require a width with optional height/align to allow use of full image and/or ;thumb
			array(
				\BBC\Codes::ATTR_TAG => 'attach',
				\BBC\Codes::ATTR_TYPE => \BBC\Codes::TYPE_UNPARSED_CONTENT,
				\BBC\Codes::ATTR_PARAM => array(
					'width' => array(
						\BBC\Codes::PARAM_ATTR_OPTIONAL => false,
						\BBC\Codes::PARAM_ATTR_VALIDATE => Ila_Integrate::validate_width(),
						\BBC\Codes::PARAM_ATTR_MATCH => '(\d+)',
					),
					'height' => array(
						\BBC\Codes::PARAM_ATTR_OPTIONAL => true,
						\BBC\Codes::PARAM_ATTR_VALUE => 'max-height:$1px;',
						\BBC\Codes::PARAM_ATTR_MATCH => '(\d+)',
					),
					'align' => array(
						\BBC\Codes::PARAM_ATTR_OPTIONAL => true,
						\BBC\Codes::PARAM_ATTR_VALUE => 'float$1',
						\BBC\Codes::PARAM_ATTR_MATCH => '(right|left|center)',
					),
				),
				\BBC\Codes::ATTR_CONTENT => '<a id="link_$1" data-lightboximage="$1" href="' . $scripturl . '?action=dlattach;attach=$1;image"><img src="' . $scripturl . '?action=dlattach;attach=$1{width}{height}" alt="" class="bbc_img {align}" /></a>',
				\BBC\Codes::ATTR_VALIDATE => Ila_Integrate::validate_options(),
				\BBC\Codes::ATTR_DISALLOW_PARENTS => $disallow,
				\BBC\Codes::ATTR_DISABLED_CONTENT => '<a href="' . $scripturl . '?action=dlattach;attach=$1">(' . $scripturl . '?action=dlattach;attach=$1)</a>',
				\BBC\Codes::ATTR_BLOCK_LEVEL => false,
				\BBC\Codes::ATTR_AUTOLINK => false,
				\BBC\Codes::ATTR_LENGTH => 6,
			),
			// Require a height with option width/align to allow removal of ;thumb
			array(
				\BBC\Codes::ATTR_TAG => 'attach',
				\BBC\Codes::ATTR_TYPE => \BBC\Codes::TYPE_UNPARSED_CONTENT,
				\BBC\Codes::ATTR_PARAM => array(
					'width' => array(
						\BBC\Codes::PARAM_ATTR_OPTIONAL => true,
						\BBC\Codes::PARAM_ATTR_VALUE => 'width:100%;max-width:$1px;',
						\BBC\Codes::PARAM_ATTR_MATCH => '(\d+)',
					),
					'height' => array(
						\BBC\Codes::PARAM_ATTR_OPTIONAL => false,
						\BBC\Codes::PARAM_ATTR_VALIDATE => Ila_Integrate::validate_height(),
						\BBC\Codes::PARAM_ATTR_MATCH => '(\d+)',
					),
					'align' => array(
						\BBC\Codes::PARAM_ATTR_OPTIONAL => true,
						\BBC\Codes::PARAM_ATTR_VALUE => 'float$1',
						\BBC\Codes::PARAM_ATTR_MATCH => '(right|left|center)',
					),
				),
				\BBC\Codes::ATTR_CONTENT => '<a id="link_$1" data-lightboximage="$1" href="' . $scripturl . '?action=dlattach;attach=$1;image"><img src="' . $scripturl . '?action=dlattach;attach=$1{height}{width}" alt="" class="bbc_img {align}" /></a>',
				\BBC\Codes::ATTR_VALIDATE => Ila_Integrate::validate_options(),
				\BBC\Codes::ATTR_DISALLOW_PARENTS => $disallow,
				\BBC\Codes::ATTR_DISABLED_CONTENT => '<a href="' . $scripturl . '?action=dlattach;attach=$1">(' . $scripturl . '?action=dlattach;attach=$1)</a>',
				\BBC\Codes::ATTR_BLOCK_LEVEL => false,
				\BBC\Codes::ATTR_AUTOLINK => false,
				\BBC\Codes::ATTR_LENGTH => 6,
			),
			// Just an align ?
			array(
				\BBC\Codes::ATTR_TAG => 'attach',
				\BBC\Codes::ATTR_TYPE => \BBC\Codes::TYPE_UNPARSED_CONTENT,
				\BBC\Codes::ATTR_PARAM => array(
					'align' => array(
						\BBC\Codes::PARAM_ATTR_OPTIONAL => true,
						\BBC\Codes::PARAM_ATTR_VALUE => 'float$1',
						\BBC\Codes::PARAM_ATTR_MATCH => '(right|left|center)',
					),
				),
				\BBC\Codes::ATTR_CONTENT => '<a id="link_$1" data-lightboximage="$1" href="' . $scripturl . '?action=dlattach;attach=$1;image"><img src="' . $scripturl . '?action=dlattach;attach=$1;thumb" alt="" class="bbc_img {align}" /></a>',
				\BBC\Codes::ATTR_VALIDATE => Ila_Integrate::validate_options(),
				\BBC\Codes::ATTR_DISALLOW_PARENTS => $disallow,
				\BBC\Codes::ATTR_DISABLED_CONTENT => '<a href="' . $scripturl . '?action=dlattach;attach=$1">(' . $scripturl . '?action=dlattach;attach=$1)</a>',
				\BBC\Codes::ATTR_BLOCK_LEVEL => false,
				\BBC\Codes::ATTR_AUTOLINK => false,
				\BBC\Codes::ATTR_LENGTH => 6,
			),
			// Just a simple attach
			array(
				\BBC\Codes::ATTR_TAG => 'attach',
				\BBC\Codes::ATTR_TYPE => \BBC\Codes::TYPE_UNPARSED_CONTENT,
				\BBC\Codes::ATTR_CONTENT => '$1',
				\BBC\Codes::ATTR_VALIDATE => Ila_Integrate::validate_plain(),
				\BBC\Codes::ATTR_DISALLOW_PARENTS => $disallow,
				\BBC\Codes::ATTR_DISABLED_CONTENT => '<a href="' . $scripturl . '?action=dlattach;attach=$1">(' . $scripturl . '?action=dlattach;attach=$1)</a>',
				\BBC\Codes::ATTR_BLOCK_LEVEL => false,
				\BBC\Codes::ATTR_AUTOLINK => false,
				\BBC\Codes::ATTR_LENGTH => 6,
			),
// 			array(
// 				'tag' => 'attachurl',
// 				'type' => 'closed',
// 				'content' => '',
// 			),
// 			array(
// 				'tag' => 'attachmini',
// 				'type' => 'closed',
// 				'content' => '',
// 			),
// 			array(
// 				'tag' => 'attachthumb',
// 				'type' => 'closed',
// 				'content' => '',
// 			)
		));

		return;
	}

	/**
	 * Subs hook, integrate_pre_parsebbc
	 *
	 * What it does:
	 * - Allow addons access before entering the main parse_bbc loop
	 * - Prevents parseBBC from working on these tags at all
	 *
	 * @param string $message
	 * @param mixed[] $smileys
	 * @param string $cache_id
	 * @param string[]|null $parse_tags
	 */
	public static function integrate_pre_parsebbc(&$message, &$smileys, &$cache_id, &$parse_tags)
	{
		global $context;

		// Enabled and we have ila tags, then hide them from parsebbc where appropriate
		if (empty($parse_tags) && empty($context['uninstalling']) && stripos($message, '[attach') !== false)
		{
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

			$ila_parser = new In_Line_Attachment($message, $msg_id);
			$message = $ila_parser->hide_bbc();
		}
	}

	/**
	 * Display controller hook, called from prepareDisplayContext_callback integrate_before_prepare_display_context
	 *
	 * What it does:
	 * - Drops attachments from the message if they were rendered inline
	 *
	 * @param mixed[] $message
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
	 * Settings hook for the admin panel
	 *
	 * What it does:
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

	/**
	 * Used when the optional width parameter is set
	 *
	 * - Determines the best image, full or thumbnail, based on ILA width desired
	 * - Used as PARAM_ATTR_VALIDATE function
	 *
	 * @return Closure
	 */
	public static function validate_width()
	{
		global $modSettings;

		return function ($data) use ($modSettings)
		{
			// These may look odd, and they are, but its a way to set or not ;thumb to the url
			if (!empty($modSettings['attachmentThumbWidth']) && $data <= $modSettings['attachmentThumbWidth'])
			{
				return ';thumb" style="width:100%;max-width:' . $data . 'px;';
			}
			else
			{
				return '" style="width:100%;max-width:' . $data . 'px;';
			}
		};
	}

	/**
	 * Used when the optional height parameter is set and no width is set
	 *
	 * - Determines the best image, full or thumbnail, based on desired ILA height
	 * - Used as PARAM_ATTR_VALIDATE function
	 *
	 * @return Closure
	 */
	public static function validate_height()
	{
		global $modSettings;

		return function ($data) use ($modSettings)
		{
			// These may look odd, and they are, but its a way to set or not ;thumb to the url
			if (!empty($modSettings['attachmentThumbHeight']) && $data <= $modSettings['attachmentThumbHeight'])
			{
				return ';thumb" style="max-height:' . $data . 'px;';
			}
			else
			{
				return '" style="max-height:' . $data . 'px;';
			}
		};
	}

	/**
	 * This is prevents a little repetition and provides a some control for "plain" tags
	 *
	 * - Determines if the ILA is an image or not
	 * - Keeps track of attachment usage to prevent displaying below the post
	 *
	 * @return Closure
	 */
	public static function validate_plain()
	{
		global $user_info, $scripturl;

		return function (&$tag, &$data, $disabled) use($user_info, $scripturl)
		{
			global $context;

			$num = $data;
			$is_image = true;

			// Not a preview, then sanitize the attach id and determine the actual type
			if (strpos($data, 'post_tmp_' . $user_info['id']) === false)
			{
				require_once(SUBSDIR . '/Attachments.subs.php');

				$num = (int) $data;
				$is_image = isAttachmentImage($num) !== false;
			}

			// An image will get the light box treatment
			if ($is_image)
			{
				$data = '<a id="link_' . $num . '" data-lightboximage="' . $num . '" href="' . $scripturl . '?action=dlattach;attach=' . $num . ';image' . '"><img src="' . $scripturl . '?action=dlattach;attach=' . $num . ';thumb" alt="" class="bbc_img" /></a>';
			}
			else
			{
				$data = '<a href="' . $scripturl . '?action=dlattach;attach=' . $num . '"><img src="' . $scripturl . '?action=dlattach;attach=' . $num . ';thumb" alt="" class="bbc_img" /></a>';
			}

			$context['ila_dont_show_attach_below'][] = $num;
			$context['ila_dont_show_attach_below'] = array_unique($context['ila_dont_show_attach_below']);
		};
	}

	/**
	 * For tags with options (width / height / align)
	 *
	 * - Keeps track of attachment usage to prevent displaying below the post
	 *
	 * @return Closure
	 */
	public static function validate_options()
	{
		global $user_info;

		return function (&$tag, &$data, $disabled) use ($user_info)
		{
			global $context;

			// Not a preview, then sanitize the attach id
			if (strpos($data, 'post_tmp_' . $user_info['id']) === false)
			{
				$data = (int) $data;
			}

			$context['ila_dont_show_attach_below'][] = $data;
			$context['ila_dont_show_attach_below'] = array_unique($context['ila_dont_show_attach_below']);
		};
	}
}