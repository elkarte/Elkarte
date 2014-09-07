<?php

/**
 * Handles xml preview request in their various forms
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0
 */

if (!defined('ELK'))
	die('No access...');

/**
 * This class handles requests for previews of an item, in an ajax enabled template.
 */
class XmlPreview_Controller extends Action_Controller
{
	/**
	 * Calls the correct function for the action.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		global $context;

		$subActions = array(
			'newspreview' => array('action_newspreview'),
			'newsletterpreview' => array('action_newsletterpreview'),
			'sig_preview' => array('action_sig_preview'),
			'warning_preview' => array('action_warning_preview'),
			'bounce_preview' => array('action_bounce_preview'),
		);

		// Valid action?
		$subAction = !isset($_REQUEST['item']) || !isset($subActions[$_REQUEST['item']]) ? null : $_REQUEST['item'];

		if (empty($subAction))
			return;

		// Set up the template and default sub-template.
		loadTemplate('Xml');
		$context['sub_template'] = 'generic_xml';

		// A preview it is then
		$this->{$subActions[$subAction][0]}();
	}

	/**
	 * Get a preview of the important forum news for review before use
	 *  - Calls parse bbc to render bbc tags for the preview
	 */
	public function action_newspreview()
	{
		global $context;

		// Needed for parse bbc
		require_once(SUBSDIR . '/Post.subs.php');

		$errors = array();
		$news = !isset($_POST['news']) ? '' : Util::htmlspecialchars($_POST['news'], ENT_QUOTES);
		if (empty($news))
			$errors[] = array('value' => 'no_news');
		else
			preparsecode($news);

		// Return the xml response to the template
		$context['xml_data'] = array(
			'news' => array(
				'identifier' => 'parsedNews',
				'children' => array(
					array(
						'value' => parse_bbc($news),
					),
				),
			),
			'errors' => array(
				'identifier' => 'error',
				'children' => $errors
			),
		);
	}

	/**
	 * Get a preview of a news letter before its sent on to the masses
	 *  - Uses prepareMailingForPreview to create the actual preview
	 */
	public function action_newsletterpreview()
	{
		global $context, $txt;

		// needed to create the preview
		require_once(SUBSDIR . '/Mail.subs.php');
		loadLanguage('Errors');

		$context['post_error']['errors'] = array();
		$context['send_pm'] = !empty($_POST['send_pm']) ? 1 : 0;
		$context['send_html'] = !empty($_POST['send_html']) ? 1 : 0;

		// Let them know about any mistakes
		if (empty($_POST['subject']))
			$context['post_error']['errors'][] = $txt['error_no_subject'];
		if (empty($_POST['message']))
			$context['post_error']['errors'][] = $txt['error_no_message'];

		prepareMailingForPreview();

		$context['sub_template'] = 'generic_preview';
	}

	/**
	 * Let them see what their signature looks like before they use it like spam
	 */
	public function action_sig_preview()
	{
		global $context, $txt, $user_info;

		require_once(SUBSDIR . '/Profile.subs.php');
		loadLanguage('Profile');
		loadLanguage('Errors');

		$user = isset($_POST['user']) ? (int) $_POST['user'] : 0;
		$is_owner = $user == $user_info['id'];

		// @todo Temporary
		// Borrowed from loadAttachmentContext in Display.controller.php
		$can_change = $is_owner ? allowedTo(array('profile_extra_any', 'profile_extra_own')) : allowedTo('profile_extra_any');

		$errors = array();
		if (!empty($user) && $can_change)
		{
			require_once(SUBSDIR . '/Members.subs.php');

			// Get the current signature
			$member = getBasicMemberData($user, array('preferences' => true));

			censorText($member['signature']);
			$member['signature'] = parse_bbc($member['signature'], true, 'sig' . $user);

			// And now what they want it to be
			$preview_signature = !empty($_POST['signature']) ? $_POST['signature'] : '';
			$validation = profileValidateSignature($preview_signature);

			// An odd check for errors to be sure
			if ($validation !== true && $validation !== false)
				$errors[] = array('value' => $txt['profile_error_' . $validation], 'attributes' => array('type' => 'error'));

			censorText($preview_signature);
			$preview_signature = parse_bbc($preview_signature, true, 'sig' . $user);
		}
		// Sorry but you can't change the signature
		elseif (!$can_change)
		{
			if ($is_owner)
				$errors[] = array('value' => $txt['cannot_profile_extra_own'], 'attributes' => array('type' => 'error'));
			else
				$errors[] = array('value' => $txt['cannot_profile_extra_any'], 'attributes' => array('type' => 'error'));
		}
		else
			$errors[] = array('value' => $txt['no_user_selected'], 'attributes' => array('type' => 'error'));

		// Return the response for the template
		$context['xml_data']['signatures'] = array(
			'identifier' => 'signature',
			'children' => array()
		);

		if (isset($member['signature']))
			$context['xml_data']['signatures']['children'][] = array(
				'value' => $member['signature'],
				'attributes' => array('type' => 'current'),
			);

		if (isset($preview_signature))
			$context['xml_data']['signatures']['children'][] = array(
				'value' => $preview_signature,
				'attributes' => array('type' => 'preview'),
			);

		if (!empty($errors))
			$context['xml_data']['errors'] = array(
				'identifier' => 'error',
				'children' => array_merge(
						array(
					array(
						'value' => $txt['profile_errors_occurred'],
						'attributes' => array('type' => 'errors_occurred'),
					),
						), $errors
				),
			);
	}

	/**
	 * Used to preview custom warning templates before they are saved to submitted to the user
	 */
	public function action_warning_preview()
	{
		global $context, $txt, $user_info, $scripturl, $mbname;

		require_once(SUBSDIR . '/Post.subs.php');
		loadLanguage('Errors');
		loadLanguage('ModerationCenter');

		$context['post_error']['errors'] = array();

		// If you can't issue the warning, what are you doing here?
		if (allowedTo('issue_warning'))
		{
			$warning_body = !empty($_POST['body']) ? trim(censorText($_POST['body'])) : '';
			$context['preview_subject'] = !empty($_POST['title']) ? trim(Util::htmlspecialchars($_POST['title'])) : '';
			if (isset($_POST['issuing']))
			{
				if (empty($_POST['title']) || empty($_POST['body']))
					$context['post_error']['errors'][] = $txt['warning_notify_blank'];
			}
			else
			{
				if (empty($_POST['title']))
					$context['post_error']['errors'][] = $txt['mc_warning_template_error_no_title'];
				if (empty($_POST['body']))
					$context['post_error']['errors'][] = $txt['mc_warning_template_error_no_body'];
				// Add in few replacements.
				/**
				 * These are the defaults:
				 * - {MEMBER} - Member Name. => current user for review
				 * - {MESSAGE} - Link to Offending Post. (If Applicable) => not applicable here, so not replaced
				 * - {FORUMNAME} - Forum Name.
				 * - {SCRIPTURL} - Web address of forum.
				 * - {REGARDS} - Standard email sign-off.
				 */
				$find = array(
					'{MEMBER}',
					'{FORUMNAME}',
					'{SCRIPTURL}',
					'{REGARDS}',
				);
				$replace = array(
					$user_info['name'],
					$mbname,
					$scripturl,
					replaceBasicActionUrl($txt['regards_team']),
				);
				$warning_body = str_replace($find, $replace, $warning_body);
			}

			// Deal with any BBC so it looks good for the preview
			if (!empty($_POST['body']))
			{
				preparsecode($warning_body);
				$warning_body = parse_bbc($warning_body, true);
			}
			$context['preview_message'] = $warning_body;
		}
		else
			$context['post_error']['errors'][] = array('value' => $txt['cannot_issue_warning'], 'attributes' => array('type' => 'error'));

		$context['sub_template'] = 'generic_preview';
	}

	/**
	 * Used to preview custom email bounce templates before they are saved for use
	 */
	public function action_bounce_preview()
	{
		global $context, $txt, $scripturl, $mbname, $modSettings;

		require_once(SUBSDIR . '/Post.subs.php');
		loadLanguage('Errors');
		loadLanguage('ModerationCenter');

		$context['post_error']['errors'] = array();

		// If you can't approve emails, what are you doing here?
		if (allowedTo('approve_emails'))
		{
			$body = !empty($_POST['body']) ? trim(censorText($_POST['body'])) : '';
			$context['preview_subject'] = !empty($_POST['title']) ? trim(Util::htmlspecialchars($_POST['title'])) : '';

			if (isset($_POST['issuing']))
			{
				if (empty($_POST['title']) || empty($_POST['body']))
					$context['post_error']['errors'][] = $txt['warning_notify_blank'];
			}
			else
			{
				if (empty($_POST['title']))
					$context['post_error']['errors'][] = $txt['mc_warning_template_error_no_title'];

				if (empty($_POST['body']))
					$context['post_error']['errors'][] = $txt['mc_warning_template_error_no_body'];
				// Add in few replacements.
				/**
				 * These are the defaults:
				 * - {FORUMNAME} - Forum Name, the full name with all the bells
				 * - {FORUMNAMESHORT} - Short and simple name
				 * - {SCRIPTURL} - Web address of forum.
				 * - {ERROR} - The error that was generated by the post, its unique to the post so can't render it here
				 * - {SUBJECT} - The subject of the email thats being discussed, unique to the post so can't render it here
				 * - {REGARDS} - Standard email sign-off.
				 * - {EMAILREGARDS} - Maybe a bit more friendly sign-off.
				 */
				$find = array(
					'{FORUMNAME}',
					'{FORUMNAMESHORT}',
					'{SCRIPTURL}',
					'{REGARDS}',
					'{EMAILREGARDS}',
				);
				$replace = array(
					$mbname,
					(!empty($modSettings['maillist_sitename']) ? $modSettings['maillist_sitename'] : $mbname),
					$scripturl,
					replaceBasicActionUrl($txt['regards_team']),
					(!empty($modSettings['maillist_sitename_regards']) ? $modSettings['maillist_sitename_regards'] : '')
				);
				$body = str_replace($find, $replace, $body);
			}

			// Deal with any BBC so it looks good for the preview
			if (!empty($_POST['body']))
			{
				preparsecode($body);
				$body = parse_bbc($body, true);
			}
			$context['preview_message'] = $body;
		}

		$context['sub_template'] = 'generic_preview';
	}
}