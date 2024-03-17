<?php

/**
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Attachments;

use ElkArte\Errors\AttachmentErrorContext;
use ElkArte\Errors\Errors;
use ElkArte\Helper\FileFunctions;
use ElkArte\Helper\TokenHash;
use ElkArte\User;
use ElkArte\Helper\HttpReq;

/**
 * Handle the actual saving of attachments to the active attachment directory.
 */
class TemporaryAttachmentProcess
{
	/** @var AttachmentErrorContext */
	public $attach_errors;

	/** @var FileFunctions */
	public $file_functions;

	/** @var TemporaryAttachmentsList */
	public $tmp_attachments;

	/** @var AttachmentsDirectory */
	public $attachmentDirectory;

	/** @var HttpReq */
	public $req;

	/** @var bool if to use rename or move_uploaded_file (strict) */
	public $strict = true;

	/**
	 * Class constructor.
	 *
	 * Initializes the object and sets the necessary properties.
	 */
	public function __construct()
	{
		global $modSettings;

		$this->attach_errors = AttachmentErrorContext::context();
		$this->file_functions = FileFunctions::instance();
		$this->tmp_attachments = new TemporaryAttachmentsList();
		$this->attachmentDirectory = new AttachmentsDirectory($modSettings, database());
		$this->req = HttpReq::instance();

		require_once(SUBSDIR . '/Attachments.subs.php');
	}

	/**
	 * Handles the actual saving of attachments to a directory.
	 *
	 * What it does:
	 *
	 * - Loops through $_FILES['attachment'] array and saves each file to the current attachments' folder.
	 * - Validates the save location actually exists.
	 *
	 * @param int $id_msg 0 or id of the message with attachments, if any.
	 *                    If 0, this is an upload in progress for a new post.
	 * @return bool
	 */
	public function processAttachments($id_msg = 0)
	{
		global $topic, $board;

		// Validate we need to do processing, nothing new, nothing previously sent
		if (!$this->hasAttachmentsToProcess())
		{
			return false;
		}

		// Make sure we're uploading to the right place and that it is available for writing
		$this->setupAttachmentDirectory();
		$attach_current_dir = $this->attachmentDirectory->getCurrent();
		$this->ensureDirectoryExists($attach_current_dir);

		// Check for any current attachments associated with the message (number and size)
		$this->currentValuesForAttachments($id_msg);

		// Are there are files already in session?
		$ignore_temp = $this->hasPendingSessionAttachments();

		// Validate we have new files to process
		$this->ensureValidAttachmentFile();

		if (!$ignore_temp)
		{
			$this->postTemporaryAttachments($id_msg, $topic, $board);
		}

		// If we have a system error, like no attachment directory, show the error and clear any attachments sent
		if ($this->tmp_attachments->hasSystemError())
		{
			$this->processInitialErrors();
		}

		// Do the actual moving
		$this->moveAttachmentsToCurrentDirectory($attach_current_dir);

		// Mod authors, finally a hook to hang an alternate attachment upload system upon
		// Upload to the current attachment folder with the file name $attachID or 'post_tmp_' . User::$info->id . '_' . md5(mt_rand())
		// Populate TemporaryAttachmentsList[$attachID] with the following:
		//   name => The file name
		//   tmp_name => Path to the temp file (AttachmentsDirectory->getCurrent() . '/' . $attachID).
		//   size => File size (required).
		//   type => MIME type (optional if not available on upload).
		//   id_folder => AttachmentsDirectory->currentDirectoryId
		//   errors => An array of errors (use the index of the $txt variable for that error).
		// Template changes can be done using "integrate_upload_template".
		call_integration_hook('integrate_attachment_upload');

		return $ignore_temp;
	}

	/**
	 * Check if processing is needed.
	 *
	 * This method checks if processing is needed based on the following conditions:
	 *
	 * - If the 'files' post parameter is null in the temporary attachments list (a session check)
	 * - If there are no attachments in the temporary attachments list.
	 * - If there are no temporary attachments in the attachments directory.
	 *
	 * @return bool Returns `true` if processing is needed, otherwise returns `false`.
	 */
	private function hasAttachmentsToProcess()
	{
		if ($this->tmp_attachments->getPostParam('files') !== null)
		{
			return true;
		}

		if ($this->tmp_attachments->hasAttachments())
		{
			return true;
		}

		return $this->attachmentDirectory->hasFileTmpAttachments($this->strict);
	}

	/**
	 * Prepare the attachment directory.
	 *
	 * This method checks and creates the attachment directory automatically when needed.
	 *
	 * @return void
	 */
	private function setupAttachmentDirectory()
	{
		$action = $this->req->getRequest('action', 'trim', '');

		// Check / create an attachment directory automatically and when needed.
		$this->attachmentDirectory->automanageCheckDirectory($action === 'admin');
	}

	/**
	 * Check if the given attachment directory exists and is writable
	 *
	 * @param string $attach_current_dir The path to the attachment directory.
	 */
	private function ensureDirectoryExists($attach_current_dir)
	{
		global $txt;

		if (!$this->file_functions->isDir($attach_current_dir) || !$this->file_functions->isWritable($attach_current_dir))
		{
			$this->tmp_attachments->setSystemError('attach_folder_warning');
			Errors::instance()->log_error(
				sprintf($txt['attach_folder_admin_warning'], $attach_current_dir),
				'critical'
			);
		}
	}

	/**
	 * Get the current values for attachments.
	 *
	 * This method retrieves the current values for attachments, such as the quantity and total size, and stores
	 * them in the $context variable.
	 *
	 * @param int $id_msg The message id
	 * @return void
	 */
	private function currentValuesForAttachments($id_msg)
	{
		global $context;

		// No system errors, like missing attachment directory, then prepare our values
		if (!isset($context['attachments']['quantity']) && $this->tmp_attachments->hasSystemError() === false)
		{
			$context['attachments']['quantity'] = 0;
			$context['attachments']['total_size'] = 0;

			// If this isn't a new post, check for any current attachments (number and size)
			if ($id_msg !== 0)
			{
				[$context['attachments']['quantity'], $context['attachments']['total_size']] = attachmentsSizeForMessage($id_msg);
			}
		}
	}

	/**
	 * Checks if there are temporary attachments and determines whether to ignore (keep) them or not.
	 *
	 * @return bool Returns true if temporary attachments should be ignored, false otherwise.
	 */
	private function hasPendingSessionAttachments()
	{
		$ignore_temp = false;
		if ($this->tmp_attachments->getPostParam('files') !== null && $this->tmp_attachments->hasAttachments())
		{
			// Let's try to keep them. But...
			$ignore_temp = true;

			// If new files are being added. We can't ignore those
			if (!empty($_FILES['attachment']['tmp_name']) && array_filter($_FILES['attachment']['tmp_name']) !== [])
			{
				$ignore_temp = false;
			}

			// Need to make space for the new files. So, bye bye.
			if (!$ignore_temp)
			{
				$this->tmp_attachments->removeAll(User::$info->id);
				$this->tmp_attachments->unset();
				$this->attach_errors->activate()->addError('temp_attachments_flushed');
			}
		}

		return $ignore_temp;
	}

	/**
	 * Ensure that the attachment files are available.
	 *
	 * This method removes any empty attachment files from the $_FILES array if no files are being uploaded.
	 *
	 * @return void
	 */
	private function ensureValidAttachmentFile()
	{
		// If we have an array of already attached files, but no longer any files being uploaded.
		if (empty($_FILES['attachment']['tmp_name']))
		{
			return;
		}

		if (array_filter($_FILES['attachment']['tmp_name']) !== [])
		{
			return;
		}

		foreach (array_keys($_FILES['attachment']['tmp_name']) as $index)
		{
			if ($_FILES['attachment']['name'][$index] === '')
			{
				unset($_FILES['attachment']['tmp_name'][$index]);
			}
		}
	}

	/**
	 * Post values for any temporary attachments for a message.
	 *
	 * This method sets the post parameters for temporary attachments, including the message ID,
	 * last message ID, topic ID, and board ID.
	 *
	 * @param int $id_msg The ID of the message.
	 * @param int|null $topic The ID of the topic (optional).
	 * @param int|null $board The ID of the board (optional).
	 * @return void
	 */
	private function postTemporaryAttachments($id_msg, $topic, $board)
	{
		// Remember where we are at. If it's anywhere at all.
		$this->tmp_attachments->setPostParam([
			'msg' => $id_msg,
			'last_msg' => (int) ($_REQUEST['last_msg'] ?? 0),
			'topic' => (int) ($topic ?? 0),
			'board' => (int) ($board ?? 0),
		]);
	}

	/**
	 * Process initial errors.
	 *
	 * This method is used to display a generic error message, delete temporary files, and reset the attachment array.
	 */
	private function processInitialErrors()
	{
		$this->attach_errors->activate();
		$this->attach_errors->addError('attach_no_upload');

		// And delete the files 'cos they ain't going nowhere.
		foreach ($_FILES['attachment']['tmp_name'] as $n => $dummy)
		{
			if (is_writable($_FILES['attachment']['tmp_name'][$n]))
			{
				unlink($_FILES['attachment']['tmp_name'][$n]);
			}
		}

		$_FILES['attachment']['tmp_name'] = [];
	}

	/**
	 * Move uploaded attachments to the current active attachment directory.
	 *
	 * This method loops through the $_FILES['attachment'] array and moves each file to the current attachments' folder.
	 *
	 * @param string $attach_current_dir The directory to which the attachments should be moved.
	 * @return void
	 */
	private function moveAttachmentsToCurrentDirectory($attach_current_dir)
	{
		// Loop through $_FILES['attachment'] array and move each file to the current attachments' folder.
		foreach ($_FILES['attachment']['tmp_name'] as $index => $dummy)
		{
			if ($_FILES['attachment']['name'][$index] === '')
			{
				continue;
			}

			// First, let's check for standard PHP upload errors.
			$errors = doPHPUploadChecks($index);

			$tempAttachment = $this->prepareTemporaryAttachmentData($index);

			// If we are error free, Try to move and rename the file before doing more checks on it.
			if (empty($errors))
			{
				$tempAttachment->moveUploaded($attach_current_dir, $this->strict);
			}
			// Upload error(s) were detected, flag the error, remove the file
			else
			{
				$tempAttachment->setErrors($errors);
				$tempAttachment->remove(false);
			}

			// The file made it to the server,
			// - Do our checks such as injection, size, extension
			// - Do any adjustments such as rotation, webp, resizing
			$tempAttachment->doElkarteUploadChecks($this->attachmentDirectory);

			// Sort out the errors for display and delete any associated files.
			if ($tempAttachment->hasErrors())
			{
				$this->handleAttachmentErrors($tempAttachment);
			}

			$this->tmp_attachments->addAttachment($tempAttachment);
		}
	}

	/**
	 * Prepare temporary attachment data.
	 *
	 * This method creates and returns an instance of the TemporaryAttachment class with the provided data.
	 *
	 * @param int $index The index of the attachment in the $_FILES array.
	 * @return TemporaryAttachment An instance of the TemporaryAttachment class with the following properties:
	 * - name: The name of the attachment file.
	 * - tmp_name: The temporary name of the attachment file.
	 * - attachid: The attachment ID generated by the tmp_attachments->getTplName method.
	 * - public_attachid: The public attachment ID generated by the tmp_attachments->getTplName method.
	 * - user_id: The ID of the user.
	 * - size: The size of the attachment file.
	 * - type: The type of the attachment file.
	 * - id_folder: The ID of the current attachment directory.
	 * - mime: The MIME type of the attachment file.
	 */
	public function prepareTemporaryAttachmentData($index)
	{
		$tokenizer = new TokenHash();

		return new TemporaryAttachment([
			'name' => basename($_FILES['attachment']['name'][$index]),
			'tmp_name' => $_FILES['attachment']['tmp_name'][$index],
			'attachid' => $this->tmp_attachments->getTplName(User::$info->id, $tokenizer->generate_hash(16)),
			'public_attachid' => $this->tmp_attachments->getTplName(User::$info->id, $tokenizer->generate_hash(16)),
			'user_id' => User::$info->id,
			'size' => $_FILES['attachment']['size'][$index],
			'type' => $_FILES['attachment']['type'][$index],
			'id_folder' => $this->attachmentDirectory->currentDirectoryId(),
			'mime' => getMimeType($_FILES['attachment']['tmp_name'][$index]),
		]);
	}

	/**
	 * Handle attachment errors.
	 *
	 * This method handles any errors that occurred during attachment processing.
	 *
	 * @param object $tempAttachment The temporary attachment object.
	 * @return void
	 */
	private function handleAttachmentErrors($tempAttachment)
	{
		global $txt;

		$this->attach_errors->addAttach($tempAttachment['attachid'], $tempAttachment->getName());
		$log_these = ['attachments_no_create', 'attachments_no_write', 'attach_timeout',
			'ran_out_of_space', 'cant_access_upload_path', 'attach_0_byte_file', 'bad_attachment'];

		foreach ($tempAttachment->getErrors() as $error)
		{
			$error = array_filter($error);
			$this->attach_errors->addError(isset($error[1]) ? $error : $error[0]);
			if (in_array($error[0], $log_these, true))
			{
				Errors::instance()->log_error($tempAttachment->getName() . ': ' . $txt[$error[0]], 'critical');

				// For critical errors, we don't want the file or session data to persist
				$tempAttachment->remove(false);
			}
		}
	}
}
