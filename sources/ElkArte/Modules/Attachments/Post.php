<?php

/**
 * Integration system for attachments into Post controller
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Modules\Attachments;

use ElkArte\Errors\AttachmentErrorContext;
use ElkArte\Errors\ErrorContext;
use ElkArte\EventManager;
use ElkArte\FileFunctions;
use ElkArte\Modules\AbstractModule;
use ElkArte\TemporaryAttachmentsList;

/**
 * Class Attachments_Post_Module
 */
class Post extends AbstractModule
{
	/** @var int The mode of attachments (disabled/enabled/show only). */
	protected static $_attach_level = 0;

	/** @var AttachmentErrorContext The objects that keeps track of errors. */
	protected $_attach_errors = null;

	/** @var int[] List of attachments ID already saved. */
	protected $_saved_attach_id = array();

	/** @var bool If it is a new message or if it is an existing one edited. */
	protected $_is_new_message = false;

	/** @var bool If temporary attachments should be ignored or not */
	protected $ignore_temp = false;

	/**
	 * {@inheritdoc }
	 */
	public static function hooks(EventManager $eventsManager)
	{
		global $modSettings;

		if (!empty($modSettings['attachmentEnable']))
		{
			self::$_attach_level = (int) $modSettings['attachmentEnable'];

			return array(
				array('prepare_post', array('\\ElkArte\\Modules\\Attachments\\Post', 'prepare_post'), array()),
				array('prepare_context', array('\\ElkArte\\Modules\\Attachments\\Post', 'prepare_context'), array('post_errors')),
				array('finalize_post_form', array('\\ElkArte\\Modules\\Attachments\\Post', 'finalize_post_form'), array('show_additional_options', 'board', 'topic')),
				array('prepare_save_post', array('\\ElkArte\\Modules\\Attachments\\Post', 'prepare_save_post'), array('post_errors')),
				array('pre_save_post', array('\\ElkArte\\Modules\\Attachments\\Post', 'pre_save_post'), array('msgOptions')),
				array('after_save_post', array('\\ElkArte\\Modules\\Attachments\\Post', 'after_save_post'), array('msgOptions')),
			);
		}

		return array();
	}

	/**
	 * Get the error handler ready for post attachments
	 */
	public function prepare_post()
	{
		$this->_initErrors();
	}

	/**
	 * Set and activate the attachment error instance
	 */
	protected function _initErrors()
	{
		if ($this->_attach_errors === null)
		{
			$this->_attach_errors = AttachmentErrorContext::context();

			$this->_attach_errors->activate();
		}
	}

	/**
	 * Set up the errors for the template etc
	 *
	 * @param ErrorContext $post_errors
	 */
	public function prepare_context($post_errors)
	{
		global $context;

		// An array to hold all the attachments for this topic.
		$context['attachments']['current'] = array();

		if ($this->_attach_errors->hasErrors())
		{
			$post_errors->addError(array('attachments_errors' => $this->_attach_errors));
		}
	}

	/**
	 * This does lots of stuff, yes it does, in fact so much that trying to document a method like this
	 * would be insane.  What needs to be done, is fix this bowl of spaghetti.
	 *
	 * What it does:
	 *
	 * - Infuriates anyone trying to read the code or follow the execution path
	 * - Causes hallucinations and sleepless nights
	 * - Known to induce binge drinking
	 *
	 * @param bool $show_additional_options
	 * @param int $board
	 * @param int $topic
	 * @throws \ElkArte\Exceptions\Exception
	 */
	public function finalize_post_form(&$show_additional_options, $board, $topic)
	{
		global $txt, $context, $modSettings;

		$context['attachments']['can']['post'] = self::$_attach_level === 1 && (allowedTo('post_attachment') || ($modSettings['postmod_active'] && allowedTo('post_unapproved_attachments')));
		$context['attachments']['ila_enabled'] = !empty($modSettings['attachment_inline_enabled']);

		// If you can't use attachments, this is easy
		if ($context['attachments']['can']['post'] === false)
		{
			return;
		}

		// Calculate the total size and number of attachments.
		$attachments = $this->_getCurrentSize();

		// A bit of housekeeping first.
		$tmp_attachments = new TemporaryAttachmentsList();
		if ($tmp_attachments->count() === 1)
		{
			$tmp_attachments->unset();
		}

		// Adding/removing attachments to a new or existing post
		if ($tmp_attachments->hasAttachments())
		{
			// Is this a request to delete them?
			if (isset($_GET['delete_temp']))
			{
				$tmp_attachments->removeAll($this->user->id);
				$tmp_attachments->unset();
				$this->_attach_errors->addError('temp_attachments_gone');
			}
			// Hmm, coming in fresh and there are files in session.
			elseif ($context['current_action'] !== 'post2' || !empty($this->_req->getPost('from_qr')))
			{
				$this->_determineExistingFate($tmp_attachments, $board, $topic);
			}

			// Process new attachments, skipping over existing
			if (!isset($context['ignore_temp_attachments']) && $tmp_attachments->getPostParam('files') === null)
			{
				if ($tmp_attachments->hasSystemError())
				{
					if ($context['current_action'] !== 'post2')
					{
						$txt['error_attach_initial_error'] = $txt['attach_no_upload'] . '<div class="attachmenterrors">' . $tmp_attachments->getSystemError() . '</div>';
						$this->_attach_errors->addError('attach_initial_error');
					}

					$tmp_attachments->unset();
				}

				$prefix = $tmp_attachments->getTplName($this->user->id, '');

				/** @var \ElkArte\TemporaryAttachment $attachment */
				foreach ($tmp_attachments as $attachID => $attachment)
				{
					// Initial errors (such as missing directory), we can recover
					if ($attachID !== 'initial_error' && strpos($attachID, $prefix) === false)
					{
						continue;
					}

					// Show any errors which might have occurred.
					if ($attachment->hasErrors())
					{
						if ($context['current_action'] !== 'post2')
						{
							$txt['error_attach_errors'] = empty($txt['error_attach_errors']) ? '<br />' : '';
							$txt['error_attach_errors'] .= sprintf($txt['attach_warning'], $attachment->getName()) . '<div class="attachmenterrors">';
							foreach ($attachment->getErrors() as $error)
							{
								$txt['error_attach_errors'] .= (is_array($error) ? vsprintf($txt[$error[0]], $error[1]) : $txt[$error]) . '<br  />';
							}
							$txt['error_attach_errors'] .= '</div>';
							$this->_attach_errors->addError('attach_errors');
						}

						// Take out the trash.
						$tmp_attachments->removeById($attachID, false);

						continue;
					}

					// More housekeeping.
					if ($attachment->fileExists() === false)
					{
						$tmp_attachments->removeById($attachID, false);
						continue;
					}

					$attachments['quantity']++;
					$attachments['total_size'] += $attachment['size'];

					if (!isset($context['files_in_session_warning']))
					{
						$context['files_in_session_warning'] = $txt['attached_files_in_session'];
					}

					$context['attachments']['current'][] = array(
						'name' => '<span class="underline">' . htmlspecialchars($attachment['name'], ENT_COMPAT, 'UTF-8') . '</span>',
						'size' => $attachment['size'],
						'id' => $attachment['public_attachid'],
						'unchecked' => false,
						'approved' => 1,
					);
				}
			}
		}

		// If there are attachment errors. Let's show a list to the user.
		if ($this->_attach_errors->hasErrors())
		{
			theme()->getTemplates()->load('Errors');

			$errors = $this->_attach_errors->prepareErrors();

			foreach ($errors as $key => $error)
			{
				$context['attachment_error_keys'][] = $key . '_error';
				$context[$key . '_error'] = $error;
			}
		}

		// If they've unchecked an attachment, they may still want to attach that many
		// more files, but don't allow more than num_allowed_attachments.
		$context['attachments']['num_allowed'] = empty($modSettings['attachmentNumPerPostLimit']) ? 50 : min($modSettings['attachmentNumPerPostLimit'] - count($context['attachments']['current']), $modSettings['attachmentNumPerPostLimit']);
		$context['attachments']['can']['post_unapproved'] = allowedTo('post_attachment');
		$context['attachments']['total_size'] = $attachments['total_size'] ?? 0;
		$context['attachments']['quantity'] = $attachments['quantity'] ?? 0;
		$context['attachments']['restrictions'] = array();
		if (!empty($modSettings['attachmentCheckExtensions']))
		{
			$context['attachments']['allowed_extensions'] = strtr(strtolower($modSettings['attachmentExtensions']), array(',' => ', '));
		}
		else
		{
			$context['attachments']['allowed_extensions'] = '';
		}
		$context['attachments']['template'] = 'template_add_new_attachments';

		$attachmentRestrictionTypes = array('attachmentNumPerPostLimit', 'attachmentPostLimit', 'attachmentSizeLimit');
		foreach ($attachmentRestrictionTypes as $type)
		{
			if (!empty($modSettings[$type]))
			{
				$context['attachments']['restrictions'][] = $type === 'attachmentNumPerPostLimit'
					? sprintf($txt['attach_restrict_' . $type], comma_format($modSettings[$type], 0))
					: sprintf($txt['attach_restrict_' . $type], byte_format($modSettings[$type] * 1024));

				// Show some numbers.
				if ($type === 'attachmentNumPerPostLimit')
				{
					$context['attachments']['restrictions'][] = sprintf($txt['attach_remaining'], '<span id="' . $type . '">' . ($modSettings['attachmentNumPerPostLimit'] - $attachments['quantity']) . '</span>');
				}

				if ($type === 'attachmentPostLimit')
				{
					$context['attachments']['restrictions'][] = sprintf($txt['attach_available'], '<span id="' . $type . '">' . byte_format(max(($modSettings['attachmentPostLimit'] * 1024) - $attachments['total_size'], 0)) . '</span>');
				}
			}
		}

		$show_additional_options = $show_additional_options || $tmp_attachments->hasPostData();
	}

	/**
	 * Sets up the current number of attachment files and size.  Will account
	 * for any that are currently in session
	 *
	 * @return array
	 */
	private function _getCurrentSize()
	{
		global $context;

		$attachments = [];
		$attachments['total_size'] = 0;
		$attachments['quantity'] = 0;

		// If this isn't a new post, check the current attachments.
		if (isset($_REQUEST['msg']))
		{
			$attachments['quantity'] = count($context['attachments']['current']);
			foreach ($context['attachments']['current'] as $attachment)
			{
				$attachments['total_size'] += $attachment['size'];
			}
		}

		return $attachments;
	}

	/**
	 * If we have loose attachments, this will determine where they best belong and
	 * provides the proper action for the user to deal with them.
	 *
	 * @param TemporaryAttachmentsList $tmp_attachments
	 * @param int $board
	 * @param int $topic
	 */
	private function _determineExistingFate($tmp_attachments, $board, $topic)
	{
		global $context, $txt, $scripturl;

		$msg = $this->_req->getRequest('msg', 'intval', '');
		$last_msg = $this->_req->getRequest('last_msg', 'intval', '');

		// Let's be nice and see if they belong here first.
		if ((empty($msg) && $tmp_attachments->belongToBoard($board))
			|| (!empty($msg) && $tmp_attachments->belongToMsg($msg)))
		{
			// See if any files still exist before showing the warning message and the files attached.
			if ($tmp_attachments->filesExist($this->user->id))
			{
				$this->_attach_errors->addError('temp_attachments_new');
				$context['files_in_session_warning'] = $txt['attached_files_in_session'];
			}
		}
		else
		{
			// Since, they don't belong here. Let's inform the user that they exist..
			if (!empty($topic))
			{
				$delete_url = $scripturl . '?action=post' . (!empty($msg) ? (';msg=' . $msg) : '') . (!empty($last_msg) ? (';last_msg=' . $last_msg) : '') . ';topic=' . $topic . ';delete_temp';
			}
			else
			{
				$delete_url = $scripturl . '?action=post;board=' . $board . ';delete_temp';
			}

			// Compile a list of the files to show the user.
			$file_list = $tmp_attachments->getFileNames($this->user->id);
			$file_list = '<div class="attachments">' . implode('<br />', $file_list) . '</div>';

			$context['ignore_temp_attachments'] = true;
			if ($tmp_attachments->areLostAttachments())
			{
				$this->_attach_errors->addError(array('temp_attachments_lost', array($delete_url, $file_list)));
			}
			else
			{
				// We have a message id, so we can link back to the old topic they were trying to edit..
				$goback_url = $scripturl . '?action=post;msg=' . $tmp_attachments->getPostParam('msg') . ($tmp_attachments->getPostParam('last_msg') === null ? '' : ';last_msg=' . $tmp_attachments->getPostParam('last_msg')) . ';topic=' . $tmp_attachments->getPostParam('topic') . ';additionalOptions';
				$this->_attach_errors->addError(array('temp_attachments_found', array($delete_url, $goback_url, $file_list)));
			}
		}
	}

	/**
	 * Save attachments when the post is saved
	 *
	 * @param ErrorContext $post_errors
	 * @throws \ElkArte\Exceptions\Exception
	 */
	public function prepare_save_post($post_errors)
	{
		$this->_initErrors();

		$msg = isset($_REQUEST['msg']) ? (int) $_REQUEST['msg'] : 0;
		$this->saveAttachments($msg);

		if ($this->_attach_errors->hasErrors())
		{
			$post_errors->addError(array('attachments_errors' => $this->_attach_errors));
		}
	}

	/**
	 * Handles both the saving and removing of attachments on post save
	 *
	 * @param int $msg
	 * @throws \ElkArte\Exceptions\Exception
	 */
	protected function saveAttachments($msg)
	{
		global $context, $modSettings;

		// First check to see if they are trying to delete any current attachments.
		if (isset($_POST['attach_del']))
		{
			require_once(SUBSDIR . '/Attachments.subs.php');
			$keep_temp = array();
			$keep_ids = array();
			$tmp_attachments = new TemporaryAttachmentsList();
			$prefix = $tmp_attachments->getTplName($this->user->id, '');

			foreach ($_POST['attach_del'] as $public_id)
			{
				$attachID = $tmp_attachments->getIdFromPublic($public_id);

				if (strpos($attachID, $prefix) !== false)
				{
					$keep_temp[] = $attachID;
				}
				else
				{
					$keep_ids[] = (int) $attachID;
				}
			}

			$tmp_attachments->removeExcept($keep_temp, $this->user->id);

			// Editing a message, remove attachments they no longer wanted to keep
			if (!empty($msg))
			{
				require_once(SUBSDIR . '/ManageAttachments.subs.php');
				$attachmentQuery = array(
					'attachment_type' => 0,
					'id_msg' => (int) $msg,
					'not_id_attach' => $keep_ids,
				);
				removeAttachments($attachmentQuery);
			}
		}

		// Then try to upload any attachments.
		$context['attachments']['can']['post'] = self::$_attach_level === 1 && (allowedTo('post_attachment') || ($modSettings['postmod_active'] && allowedTo('post_unapproved_attachments')));
		if ($context['attachments']['can']['post'] && empty($_POST['from_qr']))
		{
			require_once(SUBSDIR . '/Attachments.subs.php');
			$this->ignore_temp = processAttachments((int) $msg);
		}
	}

	/**
	 * Saves all valid attachments that were uploaded
	 *
	 * @param array $msgOptions
	 */
	public function pre_save_post(&$msgOptions)
	{
		global $context, $modSettings;

		$this->_is_new_message = empty($msgOptions['id']);
		$tmp_attachments = new TemporaryAttachmentsList();
		$prefix = $tmp_attachments->getTplName($this->user->id, '');

		// ...or attach a new file...
		if (empty($this->ignore_temp)
			&& $context['attachments']['can']['post']
			&& $tmp_attachments->hasAttachments()
			&& empty($_POST['from_qr']))
		{
			$this->_saved_attach_id = array();

			foreach ($tmp_attachments->toArray() as $attachID => $attachment)
			{
				if ($attachID !== 'initial_error' && strpos($attachID, $prefix) === false)
				{
					continue;
				}

				// If there was an initial error just show that message.
				if ($attachID === 'initial_error')
				{
					$tmp_attachments->unset();
					break;
				}

				// No errors, then try to create the attachment
				if (empty($attachment['errors']))
				{
					// Load the attachmentOptions array with the data needed to create an attachment
					$attachmentOptions = array(
						'post' => $_REQUEST['msg'] ?? 0,
						'poster' => $this->user->id,
						'name' => $attachment['name'],
						'tmp_name' => $attachment['tmp_name'],
						'size' => $attachment['size'] ?? 0,
						'mime_type' => $attachment['type'] ?? '',
						'id_folder' => $attachment['id_folder'] ?? 0,
						'approved' => !$modSettings['postmod_active'] || allowedTo('post_attachment'),
						'errors' => array(),
					);

					if (createAttachment($attachmentOptions))
					{
						$this->_saved_attach_id[] = $attachmentOptions['id'];
						if (!empty($attachmentOptions['thumb']))
						{
							$this->_saved_attach_id[] = $attachmentOptions['thumb'];
						}

						$msgOptions['body'] = preg_replace('~\[attachurl(?:.*?)\]' . $attachment['public_attachid'] . '\[\/attachurl\]~', '[attachurl]' . $attachmentOptions['id'] . '[/attachurl]', $msgOptions['body']);
						$msgOptions['body'] = preg_replace('~\[attach(.*?)\]' . $attachment['public_attachid'] . '\[\/attach\]~', '[attach$1]' . $attachmentOptions['id'] . '[/attach]', $msgOptions['body']);
					}
				}
				// We have errors on this file, build out the issues for display to the user
				else
				{
					FileFunctions::instance()->delete($attachment['tmp_name']);
				}
			}

			// Clean up any post_tmp_.*?(_thumb)? items
			$tmp_attachments->removeAll($this->user->id);
			$tmp_attachments->unset();
		}

		if (!empty($this->_saved_attach_id) && $msgOptions['icon'] === 'xx')
		{
			$msgOptions['icon'] = 'clip';
		}
	}

	/**
	 * Assigns saved attachments to the message id they were saved with
	 *
	 * @param array $msgOptions
	 */
	public function after_save_post($msgOptions)
	{
		if ($this->_is_new_message && !empty($this->_saved_attach_id))
		{
			bindMessageAttachments($msgOptions['id'], $this->_saved_attach_id);
		}
	}
}
