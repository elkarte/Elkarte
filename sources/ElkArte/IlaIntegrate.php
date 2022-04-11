<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

use BBC\Codes;

/**
 * Class IlaIntegrate
 */
class IlaIntegrate
{
	/** @var string holds the rendered html from the bbc [attach] tag */
	public static $typeTag = '';

	/**
	 * Register ILA hooks to the system.  This is called by the Hooks class, loadIntegrations()
	 *
	 * Note for addon authors.
	 * All you need to do is extend $modSettings['autoload_integrate'] with "path/filename.php" or the
	 * "namespace/class" of your integration.  Using filenameIntegrate is a nice touch but not required.
	 * You then use Hooks::instance()->enableIntegration(path/file); or as
	 * Hooks::instance()->enableIntegration(\\ElkArte\\IlaIntegrate); to add your integration.
	 * register() and settingsRegister() will both be called.
	 * You can add and remove the name based on if your addon is enabled/disabled and not worry
	 * about removing hooks (they do no use permanent)
	 *
	 * @return array
	 */
	public static function register()
	{
		global $modSettings;

		if (empty($modSettings['attachment_inline_enabled']))
		{
			return array();
		}

		// $hook, $function, $file
		return array(
			array('integrate_additional_bbc', '\\ElkArte\\IlaIntegrate::integrate_additional_bbc'),
			array('integrate_post_bbc_parser', '\\ElkArte\\IlaIntegrate::integrate_post_parser')
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
			array('integrate_modify_attachment_settings', '\\ElkArte\\IlaIntegrate::integrate_modify_attachment_settings'),
		);
	}

	/**
	 * After parse is done, we need to sub in the message id for proper lightbox navigation
	 *
	 * @param string $message
	 */
	public static function integrate_post_parser(&$message)
	{
		global $context;

		$lighbox_message = 'data-lightboxmessage="' . (!empty($context['id_msg']) ? $context['id_msg'] : '0') . '"';
		$message = str_replace('data-lightboxmessage="0"', $lighbox_message, $message);
	}

	/**
	 * - Adds in new BBC code tags for use with inline images
	 *
	 * @param mixed[] $additional_bbc
	 */
	public static function integrate_additional_bbc(&$additional_bbc)
	{
		global $modSettings, $txt;

		// Generally we don't want to render in these tags ...
		$disallow = array(
			'quote' => 1,
			'code' => 1,
			'nobbc' => 1,
			'html' => 1,
			'php' => 1,
		);

		// Why enable it to disable the tags, oh well
		$disabledBBC = empty($modSettings['disabledBBC']) ? array() : explode(',', $modSettings['disabledBBC']);
		$disabled = in_array('attach', $disabledBBC, true);
		$disabledUrl = in_array('attachurl', $disabledBBC, true);

		// Want to see them in quotes eh?
		if (!empty($modSettings['attachment_inline_quotes']))
		{
			unset($disallow['quote']);
		}

		// Add ILA codes
		$additional_bbc = array_merge($additional_bbc, array(
			// Just a simple attach [attach][/attach]
			array(
				Codes::ATTR_TAG => 'attach',
				Codes::ATTR_TYPE => Codes::TYPE_UNPARSED_CONTENT,
				Codes::ATTR_DISABLED => $disabled,
				Codes::ATTR_RESET => '',
				Codes::ATTR_CONTENT => &self::$typeTag,
				Codes::ATTR_VALIDATE => $disabled ? null : self::buildTag(),
				Codes::ATTR_DISALLOW_PARENTS => $disallow,
				Codes::ATTR_DISABLED_CONTENT => '<a href="' . getUrl('action', ['action' => 'dlattach', 'attach' => '$1']) . '">(' . $txt['link'] . '-$1)</a> ',
				Codes::ATTR_BLOCK_LEVEL => false,
				Codes::ATTR_AUTOLINK => false,
				Codes::ATTR_LENGTH => 6,
			),
			// Attach, with perhaps a type [attach type=xyz][/attach]
			array(
				Codes::ATTR_TAG => 'attach',
				Codes::ATTR_TYPE => Codes::TYPE_UNPARSED_CONTENT,
				Codes::ATTR_PARAM => array(
					'type' => array(
						Codes::PARAM_ATTR_OPTIONAL => true,
						Codes::PARAM_ATTR_VALUE => ';$1',
						Codes::PARAM_ATTR_MATCH => '(thumb|image)',
					),
				),
				Codes::ATTR_DISABLED => $disabled,
				Codes::ATTR_RESET => '~~{type}',
				Codes::ATTR_CONTENT => &self::$typeTag,
				Codes::ATTR_VALIDATE => $disabled ? null : self::buildTag(),
				Codes::ATTR_DISALLOW_PARENTS => $disallow,
				Codes::ATTR_DISABLED_CONTENT => '<a href="' . getUrl('action', ['action' => 'dlattach', 'attach' => '$1']) . '">(' . $txt['link'] . '-$1)</a> ',
				Codes::ATTR_BLOCK_LEVEL => false,
				Codes::ATTR_AUTOLINK => false,
				Codes::ATTR_LENGTH => 6,
			),
			// Require a width with optional height/align, allows either use of full image and/or ;thumb
			// [attach width=300 align=??][/attach]
			array(
				Codes::ATTR_TAG => 'attach',
				Codes::ATTR_TYPE => Codes::TYPE_UNPARSED_CONTENT,
				Codes::ATTR_PARAM => array(
					'width' => array(
						Codes::PARAM_ATTR_VALUE => 'width:100%;max-width:$1px;',
						Codes::PARAM_ATTR_MATCH => '(\d+)',
					),
					'height' => array(
						Codes::PARAM_ATTR_OPTIONAL => true,
						Codes::PARAM_ATTR_VALUE => 'max-height:$1px;',
						Codes::PARAM_ATTR_MATCH => '(\d+)',
					),
					'align' => array(
						Codes::PARAM_ATTR_OPTIONAL => true,
						Codes::PARAM_ATTR_VALUE => 'float$1;',
						Codes::PARAM_ATTR_MATCH => '(right|left|center)',
					),
				),
				Codes::ATTR_DISABLED => $disabled,
				Codes::ATTR_RESET => '{width}{height}~{align}',
				Codes::ATTR_CONTENT => &self::$typeTag,
				Codes::ATTR_VALIDATE => $disabled ? null : self::buildTag(),
				Codes::ATTR_DISALLOW_PARENTS => $disallow,
				Codes::ATTR_DISABLED_CONTENT => '<a href="' . getUrl('action', ['action' => 'dlattach', 'attach' => '$1']) . '">(' . $txt['link'] . '-$1)</a> ',
				Codes::ATTR_BLOCK_LEVEL => false,
				Codes::ATTR_AUTOLINK => false,
				Codes::ATTR_LENGTH => 6,
			),
			// Require a height with option width/align [attach height=300 align=??][/attach]
			array(
				Codes::ATTR_TAG => 'attach',
				Codes::ATTR_TYPE => Codes::TYPE_UNPARSED_CONTENT,
				Codes::ATTR_PARAM => array(
					'height' => array(
						Codes::PARAM_ATTR_VALUE => 'max-height:$1px;',
						Codes::PARAM_ATTR_MATCH => '(\d+)',
					),
					'width' => array(
						Codes::PARAM_ATTR_OPTIONAL => true,
						Codes::PARAM_ATTR_VALUE => 'width:100%;max-width:$1px;',
						Codes::PARAM_ATTR_MATCH => '(\d+)',
					),
					'align' => array(
						Codes::PARAM_ATTR_OPTIONAL => true,
						Codes::PARAM_ATTR_VALUE => 'float$1',
						Codes::PARAM_ATTR_MATCH => '(right|left|center)',
					),
				),
				Codes::ATTR_DISABLED => $disabled,
				Codes::ATTR_RESET => '{width}{height}~{align}',
				Codes::ATTR_CONTENT => &self::$typeTag,
				Codes::ATTR_VALIDATE => $disabled ? null : self::buildTag(),
				Codes::ATTR_DISALLOW_PARENTS => $disallow,
				Codes::ATTR_DISABLED_CONTENT => '<a href="' . getUrl('action', ['action' => 'dlattach', 'attach' => '$1']) . '">(' . $txt['link'] . '-$1)</a> ',
				Codes::ATTR_BLOCK_LEVEL => false,
				Codes::ATTR_AUTOLINK => false,
				Codes::ATTR_LENGTH => 6,
			),
			// Align with an optional a type? [attach align=right type=thumb][/attach]
			array(
				Codes::ATTR_TAG => 'attach',
				Codes::ATTR_TYPE => Codes::TYPE_UNPARSED_CONTENT,
				Codes::ATTR_PARAM => array(
					'align' => array(
						Codes::PARAM_ATTR_VALUE => 'float$1',
						Codes::PARAM_ATTR_MATCH => '(right|left|center)',
					),
					'type' => array(
						Codes::PARAM_ATTR_OPTIONAL => true,
						Codes::PARAM_ATTR_VALUE => ';$1',
						Codes::PARAM_ATTR_MATCH => '(thumb|image)',
					),
				),
				Codes::ATTR_DISABLED => $disabled,
				Codes::ATTR_RESET => '~{align}~{type}',
				Codes::ATTR_CONTENT => &self::$typeTag,
				Codes::ATTR_VALIDATE => $disabled ? null : self::buildTag(),
				Codes::ATTR_DISALLOW_PARENTS => $disallow,
				Codes::ATTR_DISABLED_CONTENT => '<a href="' . getUrl('action', ['action' => 'dlattach', 'attach' => '$1']) . '">(' . $txt['link'] . '-$1)</a> ',
				Codes::ATTR_BLOCK_LEVEL => false,
				Codes::ATTR_AUTOLINK => false,
				Codes::ATTR_LENGTH => 6,
			),
			// [attachurl=xx] -- no image but a link with some details
			array(
				Codes::ATTR_TAG => 'attachurl',
				Codes::ATTR_TYPE => Codes::TYPE_UNPARSED_CONTENT,
				Codes::ATTR_DISABLED => $disabledUrl,
				Codes::ATTR_CONTENT => '$1',
				Codes::ATTR_VALIDATE => $disabledUrl ? null : self::validate_url(),
				Codes::ATTR_DISALLOW_PARENTS => $disallow,
				Codes::ATTR_DISABLED_CONTENT => '<a href="' . getUrl('action', ['action' => 'dlattach', 'attach' => '$1']) . '">(' . $txt['link'] . '-$1)</a> ',
				Codes::ATTR_BLOCK_LEVEL => false,
				Codes::ATTR_AUTOLINK => false,
				Codes::ATTR_LENGTH => 9,
			),
		));
	}

	/**
	 * This provides for the control of returned tags.  The tag will be different base on
	 * - Preview, Approval Y/N, Image Y/N, File mime type and width, height, align, type attributes
	 *
	 * - Determines if the ILA is an image or not
	 * - Sets the lightbox attributes if an image is identified
	 * - Sets a pending approval image if the attachment is not approved and not a preview
	 * - Keeps track of attachment usage to prevent displaying below the post
	 * - Sets self::$typeTag which is a reference to the tag content attribute
	 *
	 * @return callable
	 */
	public static function buildTag()
	{
		global $modSettings;

		return static function (&$data, $disabled, $tag) use ($modSettings) {
			$num = $data;
			$attachment = [];
			$preview = self::isPreview($num);

			// Was this tag dynamically disabled from the parser, aka print page or other addon?
			if (in_array('attach', $disabled, true))
			{
				self::$typeTag = $tag[Codes::ATTR_DISABLED_CONTENT];
				return;
			}

			// Not a preview, then determine the actual type of attachment we are dealing with
			if (!$preview)
			{
				require_once(SUBSDIR . '/Attachments.subs.php');
				$attachment = isAttachmentImage($num);
			}

			// Grab the tags content value, at this point it will have completed parameter exchange
			$parameters = $tag[Codes::ATTR_CONTENT] ?? '~~~';
			$parameters = explode('~', $parameters);
			$style = $parameters[0] ?? '';
			$class = $parameters[1] ?? '';
			$type = $parameters[2] ?? empty($style) ? ';thumb' : '';

			// Not approved gets a bland pending image
			if (empty($attachment['is_approved']) && !$preview)
			{
				self::$typeTag = '
					<img src="' . getUrl('action', ['action' => 'dlattach', 'id' => 'ila']) . '" alt="X" class="bbc_img' . $class . '" />';
			}
			// An image will get the light box treatment
			elseif (!empty($attachment['is_image']) || $preview)
			{
				$type = !empty($modSettings['attachmentThumbnails']) ? $type : '';
				$alt = Util::htmlspecialchars($attachment['filename'] ?? 'X');
				self::$typeTag = '
					<a id="link_$1" data-lightboximage="$1" data-lightboxmessage="0" href="' . getUrl('action', ['action' => 'dlattach', 'attach' => '$1', 'image']) . '">
						<img src="' . getUrl('action', ['action' => 'dlattach', 'attach' => '$1']) . $type .'" style="' . $style . '" alt="' . $alt . '" class="bbc_img ' . $class . '" />
					</a>';
			}
			// Not an image, determine a mime thumbnail or use a default thumbnail
			else
			{
				$thumbUrl = returnMimeThumb(($attachment['fileext'] ?? ''), true);
				if ($attachment === false)
				{
					self::$typeTag = '
					<img src="' . $thumbUrl . '" alt="X" class="bbc_img' . $class . '" />';
				}
				else
				{
					self::$typeTag = '
					<a href="' . getUrl('action', ['action' => 'dlattach', 'attach' => $num]) . '">
						<img src="' . $thumbUrl . '" alt="' . $attachment['filename'] . '" class="bbc_img ' . $class . '" />
					</a>';
				}
			}

			self::trackIlaUsage($num);
		};
	}

	/**
	 * This is prevents a little repetition and provides a some control for url tags
	 *
	 * - Determines if the ILA is an image or not
	 * - Keeps track of attachment usage to prevent displaying below the post
	 *
	 * @return callable
	 */
	public static function validate_url()
	{
		global $txt;

		return static function (&$data) use ($txt) {
			$num = $data;
			$attachment = false;

			// Not a preview, then sanitize the attach id and determine the details
			$preview = self::isPreview($num);
			if (!$preview)
			{
				require_once(SUBSDIR . '/Attachments.subs.php');
				$attachment = isAttachmentImage($num);
			}

			// Not approved gets a bland message
			if (empty($attachment['is_approved']) && !$preview)
			{
				$data = '
				<a href="#">
					<i class="icon icon-small i-paperclip"></i>&nbsp;' . $txt['awaiting_approval'] . '
				</a>&nbsp;';
			}
			// If we got the details ...
			elseif ($attachment)
			{
				$data = '
				<a href="' . getUrl('action', ['action' => 'dlattach', 'attach' => $num]) . '">
					<i class="icon icon-small i-paperclip"></i>&nbsp;' . $attachment['filename'] . '
				</a>&nbsp;(' . $attachment['size'] . ($attachment['is_image'] ? ' ' . $attachment['width'] . 'x' . $attachment['height'] : '') . ')';
			}
			else
			{
				$data = '
				<a href="' . getUrl('action', ['action' => 'dlattach', 'attach' => $num]) . '">
					<i class="icon icon-small i-paperclip"></i>&nbsp;' . $num . '
				</a>';
			}

			self::trackIlaUsage($num);
		};
	}

	/**
	 * Checks if this is a request for a yet posted attachment preview
	 * Will intval the attachment number if not a preview
	 *
	 * @param string $data if ila will be (int)'ed otherwise left alone
	 * @return bool
	 */
	public static function isPreview(&$data)
	{
		if (strpos($data, 'post_tmp_' . User::$info->id . '_') === false)
		{
			$data = (int) $data;
			return false;
		}

		return true;
	}

	/**
	 * Keeps track of attachment usage to prevent displaying below the post
	 *
	 * @param int $data
	 */
	public static function trackIlaUsage($data)
	{
		global $context;

		$context['ila_dont_show_attach_below'][] = $data;
		$context['ila_dont_show_attach_below'] = array_unique($context['ila_dont_show_attach_below']);
	}

	/**
	 * Settings hook for the admin panel
	 *
	 * What it does:
	 *
	 * - Defines our settings array and uses our settings class to manage the data
	 *
	 * @param array $config_vars
	 */
	public static function integrate_modify_attachment_settings(&$config_vars)
	{
		$config_vars[] = array('title', 'attachment_inline_title');
		$config_vars[] = array('check', 'attachment_inline_enabled');
		$config_vars[] = array('check', 'attachment_inline_quotes');
	}
}
