<?php

/**
 * Handle credits, license, privacy policy?, cookie policy?, registration agreement?, contact
 * staff, COPPA contact
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\Controller;

use ArrayObject;
use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Exceptions\Exception;
use ElkArte\Helper\DataValidator;
use ElkArte\Http\Headers;
use ElkArte\Languages\Txt;

/**
 * About Controller
 */
class About extends AbstractController
{
	private const STATUS_AWAITING_COPPA = 5;

	/**
	 * Default action of this class.
	 * Accessed with ?action=about
	 */
	public function action_index(): void
	{
		// Add an subaction array to act accordingly
		$subActions = [
			'credits' => [$this, 'action_credits'],
			'contact' => [$this, 'action_contact'],
			'coppa' => [$this, 'action_coppa'],
		];

		// Setup the action handler
		$action = new Action();
		$subAction = $action->initialize($subActions, 'credits');

		// Call the action
		$action->dispatch($subAction);
	}

	/**
	 * Shows the contact form for the user to fill out
	 *
	 * - Functionality needs to be enabled in the ACP for this to be used
	 * - Triggers the verify_contact event
	 */
	public function action_contact(): void
	{
		global $context, $txt, $modSettings;

		// Users have no need to use this, just send a PM
		// Disabled, you cannot enter.
		if ($this->user->is_guest === false || empty($modSettings['enable_contactform']) || $modSettings['enable_contactform'] === 'disabled')
		{
			redirectexit();
		}

		Txt::load('Login');
		theme()->getTemplates()->load('Register');

		// Submitted the contact form?
		if (isset($this->_req->post->send))
		{
			checkSession();
			validateToken('contact');

			// Can't send a lot of these in a row, no sir!
			spamProtection('contact');

			// No errors, yet.
			$context['errors'] = [];
			Txt::load('Errors');

			// Could they get the right send topic verification code?
			require_once(SUBSDIR . '/Members.subs.php');

			// Form validation
			$validator = new DataValidator();
			$validator->sanitation_rules([
				'emailaddress' => 'trim',
				'contactmessage' => 'trim'
			]);
			$validator->validation_rules([
				'emailaddress' => 'required|valid_email',
				'contactmessage' => 'required'
			]);
			$validator->text_replacements([
				'emailaddress' => $txt['error_email'],
				'contactmessage' => $txt['error_message']
			]);

			// Any form errors
			if (!$validator->validate($this->_req->post))
			{
				$context['errors'] = $validator->validation_errors();
			}

			// Get the clean data
			$this->_req->post = new ArrayObject($validator->validation_data(), ArrayObject::ARRAY_AS_PROPS);

			// Trigger the verify contact event for captcha checks
			$this->_events->trigger('verify_contact');

			// No errors, then send the PM to the admins
			if (empty($context['errors']))
			{
				$admins = admins();
				if (!empty($admins))
				{
					require_once(SUBSDIR . '/PersonalMessage.subs.php');
					sendpm(['to' => array_keys($admins), 'bcc' => []], $txt['contact_subject'], $this->_req->post->contactmessage, false, ['id' => 0, 'name' => $this->_req->post->emailaddress, 'username' => $this->_req->post->emailaddress]);
				}

				// Send the PM
				redirectexit('action=about;sa=contact;done');
			}
			else
			{
				$context['emailaddress'] = $this->_req->post->emailaddress;
				$context['contactmessage'] = $this->_req->post->contactmessage;
			}
		}

		// Show the contact done form or the form itself
		if ($this->_req->hasQuery('done'))
		{
			$context['sub_template'] = 'contact_form_done';
		}
		else
		{
			loadJavascriptFile('ext/mailcheck.min.js');
			$context['sub_template'] = 'contact_form';
			$context['page_title'] = $txt['admin_contact_form'];

			// Setup any contract form events, like validation
			$this->_events->trigger('setup_contact');
		}

		createToken('contact');
	}

	/**
	 * This function will display the contact information for the forum, as well a form to fill in.
	 *
	 * - Accessed by action=about;sa=coppa
	 */
	public function action_coppa(): void
	{
		Txt::load('Login');
		theme()->getTemplates()->load('About');

		// No User ID??
		if (!$this->_req->hasQuery('member'))
		{
			throw new Exception('no_access', false);
		}

		// Get the user details...
		require_once(SUBSDIR . '/Members.subs.php');
		$member_id = $this->_req->getQuery('member', 'intval', 0);
		$member = getBasicMemberData($member_id, ['authentication' => true]);

		// If doesn't exist or not pending coppa
		if (empty($member) || (int) $member['is_activated'] !== self::STATUS_AWAITING_COPPA)
		{
			throw new Exception('no_access', false);
		}

		if ($this->_req->hasQuery('form'))
		{
			$this->handleContactForm($member);
		}
		else
		{
			$this->handleCoppa();
		}
	}

	/**
	 * Handle the contact form for member registration.
	 *
	 * This method sets the necessary variables in the global $context for displaying the contact form.
	 * If the query parameter `dl` is set, the method outputs a file for download, otherwise it shows the contact form template.
	 *
	 * @param array $member The member data from getBasicMemberData())
	 */
	private function handleContactForm($member): void
	{
		global $context, $modSettings, $txt;

		// Some simple contact stuff for the forum.
		$context['forum_contacts'] = (empty($modSettings['coppaPost']) ? '' : $modSettings['coppaPost'] . '<br /><br />') . (empty($modSettings['coppaFax']) ? '' : $modSettings['coppaFax'] . '<br />');
		$context['forum_contacts'] = empty($context['forum_contacts']) ? '' : $context['forum_name_html_safe'] . '<br />' . $context['forum_contacts'];

		// Showing template?
		if (!$this->_req->hasQuery('dl'))
		{
			// Shortcut for producing underlines.
			$context['ul'] = '<span class="underline">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>';
			theme()->getLayers()->removeAll();
			$context['sub_template'] = 'coppa_form';
			$context['page_title'] = replaceBasicActionUrl($txt['coppa_form_title']);
			$context['coppa_body'] = str_replace(['{PARENT_NAME}', '{CHILD_NAME}', '{USER_NAME}'], [$context['ul'], $context['ul'], $member['member_name']], replaceBasicActionUrl($txt['coppa_form_body']));
		}
		// Downloading.
		else
		{
			// Set up to output a file to the users browser
			while (ob_get_level() > 0)
			{
				@ob_end_clean();
			}

			// The data.
			$ul = '                ';
			$crlf = "\r\n";
			$data = $context['forum_contacts'] . $crlf . $txt['coppa_form_address'] . ':' . $crlf . $txt['coppa_form_date'] . ':' . $crlf . $crlf . $crlf . replaceBasicActionUrl($txt['coppa_form_body']);
			$data = str_replace(['{PARENT_NAME}', '{CHILD_NAME}', '{USER_NAME}', '<br />', '<br />'], [$ul, $ul, $member['member_name'], $crlf, $crlf], $data);

			// Send the headers.
			Headers::instance()
				->removeHeader('all')
				->header('Content-Encoding', 'none')
				->header('Pragma', 'no-cache')
				->header('Cache-Control', 'no-cache')
				->header('Connection', 'close')
				->header('Content-Disposition', 'attachment; filename="approval.txt"')
				->contentType('application/octet-stream', '')
				->sendHeaders();

			echo $data;

			obExit(false, false);
		}
	}

	/**
	 * Handle the Children's Online Privacy Protection Act (COPPA) for member registration.
	 *
	 * This method sets the necessary variables in the global $context for displaying the COPPA page.
	 *
	 * @return void
	 */
	private function handleCoppa(): void
	{
		global $context, $modSettings, $txt;

		$context += [
			'page_title' => $txt['coppa_title'],
			'sub_template' => 'coppa',
		];

		$context['coppa'] = [
			'body' => str_replace('{MINIMUM_AGE}', $modSettings['coppaAge'], replaceBasicActionUrl($txt['coppa_after_registration'])),
			'many_options' => !empty($modSettings['coppaPost']) && !empty($modSettings['coppaFax']),
			'post' => empty($modSettings['coppaPost']) ? '' : $modSettings['coppaPost'],
			'fax' => empty($modSettings['coppaFax']) ? '' : $modSettings['coppaFax'],
			'phone' => empty($modSettings['coppaPhone']) ? '' : str_replace('{PHONE_NUMBER}', $modSettings['coppaPhone'], $txt['coppa_send_by_phone']),
			'id' => $this->_req->getQuery('member', 'intval', 0),
		];
	}

	/**
	 * It prepares credit and copyright information for the credits page or the admin page.
	 *
	 * - Accessed by ?action=who;sa=credits
	 *
	 * @uses Who language file
	 * @uses template_credits() sub template in Who.template,
	 */
	public function action_credits(): void
	{
		global $context, $txt;

		require_once(SUBSDIR . '/About.subs.php');
		Txt::load('About');

		$context += prepareCreditsData();

		theme()->getTemplates()->load('About');
		$context['sub_template'] = 'credits';
		$context['robot_no_index'] = true;
		$context['page_title'] = $txt['credits'];
	}
}
