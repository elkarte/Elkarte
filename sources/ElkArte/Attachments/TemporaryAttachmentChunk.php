<?php

/**
 * Handles the job of attachment chunked upload management.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Attachments;

use ElkArte\Helper\FileFunctions;
use ElkArte\Helper\HttpReq;
use ElkArte\Helper\TokenHash;

/**
 * Class TemporaryAttachmentChunk
 *
 * Handles the job of attachment chunked upload management.
 */
class TemporaryAttachmentChunk
{
	/** @var HttpReq */
	public $req;

	/** @var AttachmentsDirectory */
	public $attachmentDirectory;

	/** @var TemporaryAttachment */
	public $tempAttachment;

	/** @var string Active attachment directory */
	public $attach_current_dir;

	/** @var int Maximum chunk size allowed */
	public $chunkSize;

	/** @var string the combined file temporary path and name */
	private $combinedFilePath;

	/**
	 * Class constructor.
	 *
	 * Initializes the object and sets the necessary properties.
	 */
	public function __construct()
	{
		global $modSettings;

		$this->req = HttpReq::instance();
		$this->attachmentDirectory = new AttachmentsDirectory($modSettings, database());
		$this->tempAttachment = new TemporaryAttachment();

		$this->attachmentDirectory->automanageCheckDirectory();

		$this->attach_current_dir = $this->attachmentDirectory->getCurrent();

		$this->chunkSize = empty($modSettings['attachmentChunkSize']) ? 250000 : $modSettings['attachmentChunkSize'];

		require_once(SUBSDIR . '/Attachments.subs.php');
	}

	/**
	 * Handle asynchronous file upload by saving all fragments submitted
	 *
	 * @return array
	 */
	public function action_async()
	{
		if (checkSession('post', '', false) !== '')
		{
			return ['result' => false, 'data' => 'session_timeout'];
		}

		$result = $this->saveAsyncFile();

		return $this->returnResults($result);
	}

	/**
	 * Saves an asynchronously uploaded file.
	 *
	 * @return array An array containing the ID of the uploaded file and an error code.
	 */
	public function saveAsyncFile()
	{
		[$uuid, $chunkIndex, $totalChunkCount] = $this->extractPostData();
		$postValidationError = $this->validatePostData($uuid, $chunkIndex, $totalChunkCount);
		if (is_string($postValidationError))
		{
			return $this->errorAsyncFile($postValidationError, $uuid);
		}

		$validationError = $this->validateReceivedFile();
		if (is_string($validationError))
		{
			return $this->errorAsyncFile($validationError, $uuid);
		}

		$local_file = $this->generateLocalFileName($uuid, $totalChunkCount, $chunkIndex);
		$chunkWritingError = $this->writeChunkToFile($local_file);
		if ($chunkWritingError !== true)
		{
			return $this->errorAsyncFile($chunkWritingError, $uuid);
		}

		return ['id' => $uuid, 'code' => ''];
	}

	/**
	 * Extracts post data from the request
	 *
	 * @return array [int, int, int] An array containing the UUID, chunk index, and total chunk count
	 */
	private function extractPostData()
	{
		$chunkIndex = $this->req->getPost('elkchunkindex', 'intval');
		$totalChunkCount = $this->req->getPost('elktotalchunkcount', 'intval');
		$uuid = $this->req->getPost('elkuuid', 'intval');

		if (!isset($chunkIndex, $totalChunkCount))
		{
			$chunkIndex = 0;
			$totalChunkCount = 0;
		}

		return [$uuid, $chunkIndex, $totalChunkCount];
	}

	/**
	 * Validates the post data is complete and within the known bounds.
	 *
	 * @param string $uuid The UUID of the data.
	 * @param int $chunkIndex The index of the current chunk.
	 * @param int $totalChunkCount The total number of chunks.
	 *
	 * @return string|bool Returns 'invalid_chunk' if the chunk index is invalid or 'invalid_uuid' if the UUID is not set.
	 * If the chunk and UUID are valid, it delegates the validation to the validateInitialChunk method and returns its result.
	 */
	private function validatePostData($uuid, $chunkIndex, $totalChunkCount)
	{
		if ($chunkIndex < 0 || $totalChunkCount < 1 || $chunkIndex >= $totalChunkCount)
		{
			return 'invalid_chunk';
		}

		if (!isset($uuid))
		{
			return 'invalid_uuid';
		}

		return $this->validateInitialChunk($totalChunkCount, $chunkIndex);
	}

	/**
	 * Validates
	 * - the total number of chunks will fit within the maximum post size
	 * - that the output directory is writable
	 * - only does this on the first chuck
	 *
	 * @param int $totalChunkCount The total number of chunks.
	 * @param int $chunkIndex The index of the current chunk.
	 *
	 * @return string|bool Returns 'chunk_quota' if the chunk quota check fails, 'not_writable' if the attachment directory is not writable.
	 * If the checks passed, it returns true.
	 */
	private function validateInitialChunk($totalChunkCount, $chunkIndex)
	{
		// Make sure this (when completed) file size will not exceed what we are willing to accept
		if ($totalChunkCount === 1 || ($totalChunkCount > 1 && $chunkIndex === 0))
		{
			if ($this->checkTotalSize($totalChunkCount) !== true)
			{
				return 'chunk_quota';
			}

			if (!FileFunctions::instance()->isWritable($this->attach_current_dir))
			{
				return 'not_writable';
			}
		}

		return true;
	}

	/**
	 * Make sure total file size isn't going to be bigger than limit
	 *
	 * @param int $totalChunks
	 * @param int $chunkSize
	 * @return bool
	 */
	public function checkTotalSize($totalChunks, $chunkSize = 250000)
	{
		global $modSettings;

		$expectedSize = $totalChunks * $chunkSize;

		// What upload max sizes are defined
		$post_max_size = ini_get('post_max_size');
		$testPM = memoryReturnBytes($post_max_size);
		$acpPM = isset($modSettings['attachmentPostLimit']) ? $modSettings['attachmentPostLimit'] * 1024 : 0;

		// Limitless ?
		if ($testPM === 0 && $acpPM === 0)
		{
			return true;
		}

		// Which is creating the limit?
		$limit = $this->getSmallerNonZero($testPM, $acpPM);

		return ($expectedSize <= $limit);
	}

	/**
	 * Returns the smaller non-zero number between two given numbers.
	 *
	 * @param int|float $num1 The first number to compare.
	 * @param int|float $num2 The second number to compare.
	 *
	 * @return int|float Returns the smaller non-zero number between $num1 and $num2.
	 * If $num1 is equal to $num2 or if both numbers are zero, returns zero.
	 */
	public function getSmallerNonZero($num1, $num2)
	{
		if (empty($num1))
		{
			return $num2;
		}

		if (empty($num2))
		{
			return $num1;
		}

		if ($num1 < $num2)
		{
			return $num1;
		}

		return $num2;
	}

	/**
	 * Retrieves the error message for the given code and cleans up any related async files.
	 *
	 * @param string|int $code The error code.
	 * @param string $fileID The ID of the file. Default is an empty string.
	 * @return array An associative array containing the error message, code, and file ID.
	 */
	public function errorAsyncFile($code, $fileID = '')
	{
		global $txt;

		$error = $txt['attachment_' . $code] ?? $code;

		// Clean up
		$user_ident = $this->getUserIdentifier();
		$in = $this->attach_current_dir . '/post_tmp_async_' . $user_ident . '_' . $fileID . '*.dat';
		$iterator = new \GlobIterator($in, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::KEY_AS_FILENAME);
		foreach ($iterator as $file)
		{
			@unlink($file->getPathname());
		}

		return ['error' => $error, 'code' => $code, 'id' => $fileID];
	}

	/**
	 * Retrieves the user identifier for the current user.
	 *
	 * @return string The user identifier.
	 * @global array $user_info The user information array.
	 *
	 */
	protected function getUserIdentifier()
	{
		global $user_info;

		return empty($user_info['id']) ? preg_replace('~[^0-9a-z]~i', '', $_SESSION['session_value']) : $user_info['id'];
	}

	/**
	 * Validates that a file was properly received. Validates it is of the correct chunk size.
	 *
	 * @return string|bool Returns a string indicating the error type,
	 *                     or a boolean true if the file is valid.
	 */
	private function validateReceivedFile()
	{
		if (!$this->attachmentDirectory->hasFileTmpAttachments())
		{
			return 'no_files';
		}

		if ($_FILES['attachment']['size'][0] > $this->chunkSize)
		{
			return 'chunk_quota';
		}

		// If we have an initial PHP upload error, then we are baked
		// @todo this will not work as expected as tempAttachments is not tied to the fragment
		$errors = doPHPUploadChecks(0);
		if (!empty($errors))
		{
			$this->tempAttachment->setErrors($errors);
			$this->tempAttachment->remove(false);

			return 'upload_error';
		}

		return true;
	}

	/**
	 * Generate a local file name based on provided parameters.
	 *
	 * @param string $uuid The unique identifier.
	 * @param int $totalChunkCount The total count of chunks.
	 * @param int $chunkIndex The index of the current chunk.
	 *
	 * @return string The generated local file name.
	 */
	private function generateLocalFileName($uuid, $totalChunkCount, $chunkIndex): string
	{
		$salt = basename($_FILES['attachment']['tmp_name'][0]);
		$user_ident = $this->getUserIdentifier();

		return 'post_tmp_async_' . $user_ident . '_' . $uuid . ($totalChunkCount > 1 ? '_part_' . $chunkIndex : '') . '_' . $salt . '.dat';
	}

	/**
	 * Write a chunk of a file to the specified location.
	 *
	 * @param string $local_file The local file name.
	 *
	 * @return bool|string Returns true if the chunk was written successfully, 'not_found' if the destination file was not found.
	 */
	private function writeChunkToFile($local_file)
	{
		$out = $this->attach_current_dir . '/' . $local_file;
		$in = $_FILES['attachment']['tmp_name'][0];

		// Move the file to the attachment folder with a temp name for now.
		set_error_handler(static function () { /* ignore warnings */ });
		try
		{
			$result = move_uploaded_file($in, $out);
		}
		catch (\Throwable)
		{
			$result = false;
		}
		finally
		{
			restore_error_handler();
		}

		if (!$result || !FileFunctions::instance()->fileExists($out))
		{
			return 'not_found';
		}

		return true;
	}

	/**
	 * Return the results of a process.
	 *
	 * @param array $result The result of the process.
	 *
	 * @return array The result array
	 */
	public function returnResults($result)
	{
		// Some error?
		if (!empty($result['code']))
		{
			return [
				'result' => false,
				'error' => $result['error'],
				'code' => $result['code'],
				'fatal' => true,
				'async' => $result['id']
			];
		}

		return [
			'result' => true,
			'async' => $result['id']
		];
	}

	/**
	 * Combine the file chunks into a single file.
	 *
	 * @return array The path of the combined file.
	 */
	public function action_combineChunks()
	{
		[$uuid, , $totalChunkCount] = $this->extractPostData();
		$user_ident = $this->getUserIdentifier();
		$in = $this->getPathWithChunks($user_ident, $uuid);

		// Check that all chunks do exist
		if (!$this->verifyChunkExistence($in, $totalChunkCount))
		{
			$result = $this->errorAsyncFile('not_found', $uuid);
			return $this->returnResults($result);
		}

		// Combine the fragments in the correct order
		$success = $this->combineFileFragments($user_ident, $uuid, $in);

		if ($success)
		{
			$this->build_fileArray();
			$result = ['id' => $this->combinedFilePath, 'code' => ''];
		}
		else
		{
			$result = $this->errorAsyncFile('not_found', $uuid);
		}

		return $this->returnResults($result);
	}

	/**
	 * Build the fileArray parameter for now combined file.
	 *
	 * This will then be used in action_ulattach as though it was uploaded as a single file,
	 * and now be subject to all the same tests and manipulations.  This will also be done as strict=false
	 * as we have already verified these were php uploaded files.
	 *
	 * @return void
	 */
	public function build_fileArray()
	{
		unset($_FILES['attachment']);

		// What was sent should match what was claimed
		$sizeOnDisk = FileFunctions::instance()->fileSize($this->combinedFilePath);
		$sizeOnForm = $this->req->getPost('filesize', 'intval');
		$error = ($sizeOnDisk !== $sizeOnForm) ? UPLOAD_ERR_PARTIAL : UPLOAD_ERR_OK;

		$_FILES['attachment']['name'][] = $this->req->getPost('filename', 'trim');
		$_FILES['attachment']['type'][] = $this->req->getPost('filetype', 'trim');
		$_FILES['attachment']['size'][] = $this->req->getPost('filesize', 'intval');
		$_FILES['attachment']['tmp_name'][] = $this->combinedFilePath;
		$_FILES['attachment']['error'][] = $error;
	}

	/**
	 * Generate the path to a file with chunks based on provided parameters.
	 *
	 * @param string $user_ident The user identifier.
	 * @param string $uuid The unique identifier.
	 *
	 * @return string The generated path to the file with chunks.
	 */
	private function getPathWithChunks($user_ident, $uuid)
	{
		return $this->attach_current_dir . '/post_tmp_async_' . $user_ident . '_' . $uuid . '_part_*.dat';
	}

	/**
	 * Get the combined file path and name based on the user identifier, UUID and some salt
	 *
	 * @param string $user_ident The user identifier.
	 * @param string $uuid The unique identifier.
	 *
	 * @return string The combined file path.
	 */
	private function getCombinedFilePath(string $user_ident, string $uuid): string
	{
		$tokenizer = new TokenHash();

		return $this->attach_current_dir . '/post_tmp_async_combined_' . $user_ident . '_' . $uuid . '_' . $tokenizer->generate_hash(8) . '.dat';
	}

	/**
	 * Verify the existence of all chunks based on the provided file pattern and total chunk count.
	 *
	 * @param string $in The file pattern to search for chunks.
	 * @param int $totalChunkCount The total count of chunks.
	 *
	 * @return bool True if all chunks exist, false otherwise.
	 */
	private function verifyChunkExistence($in, $totalChunkCount)
	{
		$iterator = new \GlobIterator($in, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::KEY_AS_FILENAME);

		return $iterator->count() && $iterator->count() === $totalChunkCount;
	}

	/**
	 * Combine file fragments into a single file.
	 *
	 * @param string $user_ident The user identifier.
	 * @param string $uuid The unique identifier.
	 * @param string $in The input directory containing file fragments.
	 *
	 * @return bool Returns true if the file fragments were successfully combined into a single file, false otherwise.
	 */
	private function combineFileFragments($user_ident, $uuid, $in)
	{
		$files = iterator_to_array(new \GlobIterator($in, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::KEY_AS_FILENAME));
		ksort($files);
		$this->combinedFilePath = $this->getCombinedFilePath($user_ident, $uuid);
		$success = true;

		foreach ($files as $file)
		{
			$fileInputPath = $this->attach_current_dir . '/' . $file->getFilename();
			$writeResult = file_put_contents($this->combinedFilePath, file_get_contents($fileInputPath), LOCK_EX | FILE_APPEND);

			if ($writeResult === false)
			{
				$success = false;
			}

			@unlink($fileInputPath);
		}

		return $success;
	}
}
