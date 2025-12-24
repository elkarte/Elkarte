<?php

/**
 * Handles XML preview request in their various forms
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 */

namespace ElkArte\Controller;

use BBC\ParserWrapper;
use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Helper\Util;
use ElkArte\Languages\Txt;

/**
 * Handles requests for previews of an item, in an ajax enabled template.
 */
class XmlPreview extends AbstractController
{
	/**
	 * {@inheritDoc}
	 */
	public function trackStats($action = '')
	{
		return false;
	}

	/**
	 * Calls the correct function for the action.
	 *
	 * @see AbstractController::action_index
	 */
	public function action_index()
	{
		global $context;

		$subActions = [
			'newspreview' => [$this, 'action_newspreview'],
			'newsletterpreview' => [$this, 'action_newsletterpreview'],
			'sig_preview' => [$this, 'action_sig_preview'],
			'warning_preview' => [$this, 'action_warning_preview'],
			'bounce_preview' => [$this, 'action_bounce_preview'],
			'invalid' => [],
		];

		// Valid action?
		$action = new Action('xml_preview');
		$subAction = $action->initialize($subActions, 'invalid', 'item');

		theme()->getTemplates()->load('Xml');
		if ($subAction === 'invalid')
		{
			$context['sub_template'] = 'empty_xml';

			return;
		}

		// Set up the template and default sub-template.
		$context['sub_template'] = 'generic_xml';

		// A preview it is then
		$action->dispatch($subAction);
	}

	/**
	 * Get a preview of the important forum news for review before use
	 *
	 *  - Calls parse bbc to render bbc tags for the preview
	 */
	public function action_newspreview(): void
	{
		global $context;

		// Needed to parse bbc
		require_once(SUBSDIR . '/Post.subs.php');

		$errors = [];
		$news = isset($this->_req->post->news) ? Util::htmlspecialchars($this->_req->post->news, ENT_QUOTES) : '';
		if (empty($news))
		{
			$errors[] = ['value' => 'no_news'];
		}
		else
		{
			preparsecode($news);
		}

		$bbc_parser = ParserWrapper::instance();

		// Return the XML response to the template
		$context['xml_data'] = [
			'news' => [
				'identifier' => 'parsedNews',
				'children' => [
					[
						'value' => $bbc_parser->parseNews($news),
					],
				],
			],
			'errors' => [
				'identifier' => 'error',
				'children' => $errors
			],
		];
	}

	/**
	 * Get a preview of a newsletter before it's sent on to the masses
	 *
	 *  - Uses prepareMailingForPreview to create the actual preview
	 */
	public function action_newsletterpreview(): void
	{
		global $context, $txt;

		// Needed to create the preview
		require_once(SUBSDIR . '/Mail.subs.php');
		Txt::load('Errors');

		$context['post_error']['errors'] = [];
		$context['send_pm'] = empty($this->_req->post->send_pm) ? 0 : 1;
		$context['send_html'] = empty($this->_req->post->send_html) ? 0 : 1;

		// Let them know about any mistakes
		if (empty($this->_req->post->subject))
		{
			$context['post_error']['errors'][] = $txt['error_no_subject'];
		}

		if (empty($this->_req->post->message))
		{
			$context['post_error']['errors'][] = $txt['error_no_message'];
		}

		prepareMailingForPreview();

		$context['sub_template'] = 'generic_preview';
	}

	/**
	 * Let them see what their signature looks like before they use it like spam
	 */
	public function action_sig_preview(): void
	{
		global $context, $txt;

		require_once(SUBSDIR . '/Profile.subs.php');
		Txt::load('Profile+Errors');

		$user = isset($this->_req->post->user) ? (int) $this->_req->post->user : 0;
		$is_owner = $user === (int) $this->user->id;

		// @todo Temporary
		// Borrowed from loadAttachmentContext in Display.controller.php
		$can_change = $is_owner ? allowedTo(['profile_extra_any', 'profile_extra_own']) : allowedTo('profile_extra_any');

		$errors = [];
		if (!empty($user) && $can_change)
		{
			require_once(SUBSDIR . '/Members.subs.php');

			// Get the current signature
			$member = getBasicMemberData($user, ['preferences' => true]);

			$member['signature'] = censor($member['signature']);
			$bbc_parser = ParserWrapper::instance();
			$member['signature'] = $bbc_parser->parseSignature($member['signature'], true);

			// And now what they want it to be
			$preview_signature = empty($this->_req->post->signature) ? '' : Util::htmlspecialchars($this->_req->post->signature);
			$validation = profileValidateSignature($preview_signature);

			// An odd check for errors to be sure
			if ($validation !== true && $validation !== false)
			{
				$errors[] = ['value' => $txt['profile_error_' . $validation], 'attributes' => ['type' => 'error']];
			}

			preparsecode($preview_signature);
			$preview_signature = censor($preview_signature);
			$preview_signature = $bbc_parser->parseSignature($preview_signature, true);
		}
		// Sorry, but you can't change the signature
		elseif (!$can_change)
		{
			if ($is_owner)
			{
				$errors[] = ['value' => $txt['cannot_profile_extra_own'], 'attributes' => ['type' => 'error']];
			}
			else
			{
				$errors[] = ['value' => $txt['cannot_profile_extra_any'], 'attributes' => ['type' => 'error']];
			}
		}
		else
		{
			$errors[] = ['value' => $txt['no_user_selected'], 'attributes' => ['type' => 'error']];
		}

		// Return the response for the template
		$context['xml_data']['signatures'] = [
			'identifier' => 'signature',
			'children' => []
		];

		if (isset($member['signature']))
		{
			$context['xml_data']['signatures']['children'][] = [
				'value' => $member['signature'],
				'attributes' => ['type' => 'current'],
			];
		}

		if (isset($preview_signature))
		{
			$context['xml_data']['signatures']['children'][] = [
				'value' => $preview_signature,
				'attributes' => ['type' => 'preview'],
			];
		}

		if (!empty($errors))
		{
			$context['xml_data']['errors'] = [
				'identifier' => 'error',
				'children' => array_merge(
					[
						[
							'value' => $txt['profile_errors_occurred'],
							'attributes' => ['type' => 'errors_occurred'],
						],
					], $errors
				),
			];
		}
	}

	/**
	 * Used to preview custom warning templates before they are saved and submitted to the user
	 */
	public function action_warning_preview(): void
	{
		global $context, $txt, $scripturl, $mbname;

		require_once(SUBSDIR . '/Post.subs.php');
		Txt::load('Errors+ModerationCenter');

		$context['post_error']['errors'] = [];

		// If you can't issue the warning, what are you doing here?
		if (allowedTo('issue_warning'))
		{
			$warning_body = empty($this->_req->post->body) ? '' : trim(censor($this->_req->post->body));
			$context['preview_subject'] = empty($this->_req->post->title) ? '' : trim(Util::htmlspecialchars($this->_req->post->title));
			if (isset($this->_req->post->issuing))
			{
				if (empty($this->_req->post->title) || empty($this->_req->post->body))
				{
					$context['post_error']['errors'][] = $txt['warning_notify_blank'];
				}
			}
			else
			{
				if (empty($this->_req->post->title))
				{
					$context['post_error']['errors'][] = $txt['mc_warning_template_error_no_title'];
				}

				if (empty($this->_req->post->body))
				{
					$context['post_error']['errors'][] = $txt['mc_warning_template_error_no_body'];
				}

				// Add in a few replacements.
				/**
				 * These are the defaults:
				 * - {MEMBER} - Member Name. => current user for review
				 * - {MESSAGE} - Link to Offending Post. (If Applicable) => not applicable here, so not replaced
				 * - {FORUMNAME} - Forum Name.
				 * - {SCRIPTURL} - Web address of forum.
				 * - {REGARDS} - Standard email sign-off.
				 */
				$find = [
					'{MEMBER}',
					'{FORUMNAME}',
					'{SCRIPTURL}',
					'{REGARDS}',
				];
				$replace = [
					$this->user->name,
					$mbname,
					$scripturl,
					replaceBasicActionUrl($txt['regards_team']),
				];
				$warning_body = str_replace($find, $replace, $warning_body);
			}

			// Deal with any BBC so it looks good for the preview
			if (!empty($this->_req->post->body))
			{
				preparsecode($warning_body);
				$bbc_parser = ParserWrapper::instance();
				$warning_body = $bbc_parser->parseNotice($warning_body);
			}

			$context['preview_message'] = $warning_body;
		}
		else
		{
			$context['post_error']['errors'][] = ['value' => $txt['cannot_issue_warning'], 'attributes' => ['type' => 'error']];
		}

		$context['sub_template'] = 'generic_preview';
	}

	/**
	 * Used to preview custom email bounce templates before they are saved for use
	 */
	public function action_bounce_preview(): void
	{
		global $context, $txt, $scripturl, $mbname, $modSettings;

		require_once(SUBSDIR . '/Post.subs.php');
		Txt::load('Errors+ModerationCenter');

		$context['post_error']['errors'] = [];

		// If you can't approve emails, what are you doing here?
		if (allowedTo('approve_emails'))
		{
			$body = empty($this->_req->post->body) ? '' : trim(censor($this->_req->post->body));
			$context['preview_subject'] = empty($this->_req->post->title) ? '' : trim(Util::htmlspecialchars($this->_req->post->title));

			if (isset($this->_req->post->issuing))
			{
				if (empty($this->_req->post->title) || empty($this->_req->post->body))
				{
					$context['post_error']['errors'][] = $txt['warning_notify_blank'];
				}
			}
			else
			{
				if (empty($this->_req->post->title))
				{
					$context['post_error']['errors'][] = $txt['mc_warning_template_error_no_title'];
				}

				if (empty($this->_req->post->body))
				{
					$context['post_error']['errors'][] = $txt['mc_warning_template_error_no_body'];
				}

				// Add in a few replacements.
				/**
				 * These are the defaults:
				 * - {FORUMNAME} - Forum Name, the full name with all the bells
				 * - {FORUMNAMESHORT} - Short and simple name
				 * - {SCRIPTURL} - Web address of forum.
				 * - {ERROR} - The error that was generated by the post, it's unique to the post so can't render it here
				 * - {SUBJECT} - The subject of the email that's being discussed, unique to the post so can't render it here
				 * - {REGARDS} - Standard email sign-off.
				 * - {EMAILREGARDS} - Maybe a bit more friendly sign-off.
				 */
				$find = [
					'{FORUMNAME}',
					'{FORUMNAMESHORT}',
					'{SCRIPTURL}',
					'{REGARDS}',
					'{EMAILREGARDS}',
				];
				$replace = [
					$mbname,
					(empty($modSettings['maillist_sitename']) ? $mbname : $modSettings['maillist_sitename']),
					$scripturl,
					replaceBasicActionUrl($txt['regards_team']),
					(empty($modSettings['maillist_sitename_regards']) ? '' : $modSettings['maillist_sitename_regards'])
				];
				$body = str_replace($find, $replace, $body);
			}

			// Deal with any BBC so it looks good for the preview
			if (!empty($this->_req->post->body))
			{
				preparsecode($body);
				$bbc_parser = ParserWrapper::instance();
				$body = $bbc_parser->parseEmail($body);
			}

			$context['preview_message'] = $body;
		}

		$context['sub_template'] = 'generic_preview';
	}
}
