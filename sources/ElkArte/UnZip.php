<?php

/**
 * Class to unZip a file
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

/**
 * Utility class to unzip package files
 *
 * If destination is null
 * - Returns a list of files in the archive.
 *
 * If single_file is true
 * - Returns the contents of the file specified by destination, if it exists, or false.
 * - Destination can start with * and / to signify that the file may come from any directory.
 * - Destination should not begin with a / if single_file is true.
 * - Overwrites existing files with newer modification times if and only if overwrite is true.
 * - Creates the destination directory if it doesn't exist, and is is specified.
 * - Requires zlib support be built into PHP.
 * - Returns an array of the files extracted on success
 */
class UnZip
{
	/**
	 * Holds the return array of files processed
	 * @var mixed[]
	 */
	protected $return = array();

	/**
	 * Holds the data found in the end of central directory record
	 * @var mixed[]
	 */
	protected $_zip_info = array();

	/**
	 * Holds the information from the central directory for each file in the archive
	 * @var mixed[]
	 */
	protected $_files_info = array();

	/**
	 * Hold the current file we are processing
	 * @var mixed[]
	 */
	protected $_file_info = array();

	/**
	 * Holds the current filename of the above
	 * @var string
	 */
	protected $_filename = '';

	/**
	 * Contains the central record string
	 * @var string
	 */
	protected $_data_cdr = '';

	/**
	 * If the file passes or fails crc check
	 * @var boolean
	 */
	protected $_crc_check = false;

	/**
	 * If we are going to write out the files processed
	 * @var boolean
	 */
	protected $_write_this = false;

	/**
	 * If we will skip a file we found
	 * @var boolean
	 */
	protected $_skip = false;

	/**
	 * If we found a file that was requested ($files_to_extract)
	 * @var boolean
	 */
	protected $_found = false;

	/**
	 * Array of file names we want to extract from the archive
	 * @var null|string[]
	 */
	protected $files_to_extract;

	/**
	 * Holds the data string passed to the function
	 * @var string
	 */
	protected $data;

	/**
	 * Location to write the files.
	 * @var string
	 */
	protected $destination;

	/**
	 * If we are looking for a single specific file
	 * @var boolean|string
	 */
	protected $single_file;

	/**
	 * If we can overwrite a file with the same name in the destination
	 * @var boolean
	 */
	protected $overwrite;

	/**
	 * Class initialization, passes variables, loads dependencies
	 *
	 * @param string        $data
	 * @param string        $destination
	 * @param bool|string   $single_file
	 * @param bool          $overwrite
	 * @param null|string[] $files_to_extract
	 *
	 * @throws \ElkArte\Exceptions\Exception package_no_zlib
	 */
	public function __construct($data, $destination, $single_file = false, $overwrite = false, $files_to_extract = null)
	{
		// Load the passed commands in to the class
		$this->data = $data;
		$this->destination = $destination;
		$this->single_file = $single_file;
		$this->overwrite = $overwrite;
		$this->files_to_extract = $files_to_extract;

		// This function sorta needs gzinflate!
		if (!function_exists('gzinflate'))
			throw new Exceptions\Exception('package_no_zlib', 'critical');

		// Make sure we have this loaded.
		theme()->getTemplates()->loadLanguageFile('Packages');

		// Likely to need this
		require_once(SUBSDIR . '/Package.subs.php');

		// The destination needs exist and be writable or we are doomed
		umask(0);
		if ($this->destination !== null && !file_exists($this->destination) && !$this->single_file)
			mktree($this->destination, 0777);
	}

	/**
	 * Class controller, calls the functions in required order
	 *
	 * @return boolean|mixed[]
	 */
	public function read_zip_data()
	{
		// Make sure we have a zip file
		if (!$this->check_valid_zip())
			return false;

		// The overall zip information for this archive
		$this->_read_endof_cdr();

		// Load the actual CDR as defined by offset in the ecdr record
		$this->_data_cdr = substr($this->data, $this->_zip_info['cdr_offset'], $this->_zip_info['cdr_size']);

		// Load the file list from the central directory record
		if ($this->_load_file_headers() === false)
			return false;

		// The file records in the CDR point to the files location in the archive
		$this->_process_files();

		// Looking for a single file and this is it
		if ($this->_found && $this->single_file)
			return $this->_crc_check ? $this->_found : false;

		// Wanted many files then we need to clean up
		if ($this->destination !== null && !$this->single_file)
			package_flush_cache();

		if ($this->single_file)
			return false;
		else
			return $this->return;
	}

	/**
	 * Does a quick check to see if this is a valid looking zip file
	 *
	 * @return boolean
	 */
	public function check_valid_zip()
	{
		// No signature?
		if (strlen($this->data) < 10)
			return false;

		// Look for an end of central directory signature 0x06054b50
		$check = explode("\x50\x4b\x05\x06", $this->data);

		return isset($check[1]);
	}

	/**
	 * Finds and reads the end of central directory record.
	 *
	 * What it does:
	 *  - Read so we can find the actual central directory record for processing.
	 *
	 * Signature Definition:
	 * - End of central dir signature: 4 bytes, always(0x06054b50)
	 * - Number of this disk: 2 bytes
	 * - Number of the disk with the start of the central directory: 2 bytes
	 * - Total number of entries in the central dir on this disk: 2 bytes
	 * - Total number of entries in the central dir: 2 bytes
	 * - Size of the central directory: 4 bytes
	 * - Offset of start of central directory with respect to the starting disk number: 4 bytes
	 * - Zipfile comment length: 2 bytes
	 * - Zipfile comment (variable size)
	 */
	private function _read_endof_cdr()
	{
		// Look for the end of central directory signature 0x06054b50
		$data_ecdr = explode("\x50\x4b\x05\x06", $this->data);

		// Handle edge cases for files with duplicate ecdr strings
		$data_ecdr = array_pop($data_ecdr);

		// Unpack the general zip data for this archive from the ecdr
		$this->_zip_info = unpack('vdisknum/vdisks/vrecords/vfiles/Vcdr_size/Vcdr_offset/vcomment_length', $data_ecdr);
		$this->_zip_info['comment'] = substr($data_ecdr, 18, $this->_zip_info['comment_length']);
	}

	/**
	 * Used to process the actual CDR record
	 *
	 * What it does:
	 *
	 * - Is a repeated sequence of [file header] . . .  until the end of central dir record.
	 * - Relative offset, used so we can find the actual data entry for each file in the archive
	 * - Validates the number of found files in the CDR matches what the ECDR record claims
	 *
	 * Signature Definition:
	 * - Central file header signature: 4 bytes always(0x02014b50)
	 * - Version made by: 2 bytes
	 * - Version needed to extract: 2 bytes
	 * - General purpose bit flag: 2 bytes
	 * - Compression method: 2 bytes
	 * - Last mod file time: 2 bytes
	 * - Last mod file date: 2 bytes
	 * - CRC-32: 4 bytes
	 * - Compressed size: 4 bytes
	 * - Uncompressed size: 4 bytes
	 * - Filename length: 2 bytes
	 * - Extra field length: 2 bytes
	 * - File comment length: 2 bytes
	 * - Disk number start: 2 bytes
	 * - Internal file attributes: 2 bytes
	 * - External file attributes: 4 bytes
	 * - Relative offset of local header: 4 bytes
	 */
	private function _load_file_headers()
	{
		$pointer = 0;
		$i = 0;

		// Each header will be proceeded by the central directory file header signature which is always \x50\x4b\x01\x02
		while (substr($this->_data_cdr, $pointer, 4) === "\x50\x4b\x01\x02")
		{
			$i++;

			// Extract all file standard length information for this record, its the 42 bytes following the signature
			$temp = unpack('vversion/vversion_needed/vgeneral_purpose/vcompress_method/vfile_time/vfile_date/Vcrc/Vcompressed_size/Vsize/vfilename_length/vextra_field_length/vcomment_length/vdisk_number_start/vinternal_attributes/vexternal_attributes1/vexternal_attributes2/Vrelative_offset', substr($this->_data_cdr, $pointer + 4, 42));

			// Extract the variable length data, filename, etc
			$pointer += 46;
			$temp['filename'] = substr($this->_data_cdr, $pointer, $temp['filename_length']);
			$temp['extra_field'] = $temp['extra_field_length'] ? substr($this->_data_cdr, $pointer + $temp['filename_length'], $temp['extra_field_length']) : '';
			$temp['file_comment'] = $temp['comment_length'] ? substr($this->_data_cdr, $pointer + $temp['filename_length'] + $temp['extra_field_length'], $temp['comment_length']) : '';
			$temp['dir'] = $this->destination . '/' . dirname($temp['filename']);

			// Save this file details
			$this->_files_info[$temp['filename']] = $temp;

			// Move to the next record
			$pointer = $pointer + $temp['filename_length'] + $temp['extra_field_length'];
		}

		// Sections and count from the signature must match or the zip file is bad
		if ($i !== $this->_zip_info['files'])
			return false;
	}

	/**
	 * Does the actual uncompressing of the files if they achieve necessary conditions
	 *
	 * What it does
	 * - Moves to the start of each file as defined in the CDR records
	 * - Validates the move takes us to a valid signature
	 * - Process the local file headers
	 * - Checks on the general purpose bit
	 * - Uncompressed and saves if needed
	 * - Returns processing array
	 */
	private function _process_files()
	{
		foreach ($this->_files_info as $this->_filename => $this->_file_info)
		{
			$this->_determine_write_this();

			// Navigate to the actual start of the data entry in the archive
			$this->_file_info['data'] = substr($this->data, $this->_file_info['relative_offset']);

			// Validate we are at a local file header '\x50\x4b\x03\x04'
			if (substr($this->_file_info['data'], 0, 4) === "\x50\x4b\x03\x04")
				$this->_read_local_header();
			// Something is probably wrong with the archive, like the 70's
			else
				continue;

			// Check if the gp flag requires us to make any adjustments
			$this->_check_general_purpose_flag();

			// Only inflate if we need to ;)
			if (!empty($this->_file_info['compress_method']) || ($this->_file_info['compressed_size'] !== $this->_file_info['size']))
			{
				if ($this->_file_info['compress_method'] == 8)
					$this->_file_info['data'] = @gzinflate($this->_file_info['compress_data'], $this->_file_info['size']);
				elseif ($this->_file_info['compress_method'] == 12 && function_exists('bzdecompress'))
					$this->_file_info['data'] = bzdecompress($this->_file_info['compress_data']);
			}
			else
				$this->_file_info['data'] = $this->_file_info['compress_data'];

			// Okay!  We can write this file, looks good from here...
			if ($this->_write_this && $this->destination !== null)
			{
				$this->_write_this_file();

				if ($this->_skip)
					continue;

				if ($this->_found)
					return;
			}

			// Not a directory, add it to our results
			if (substr($this->_filename, -1) !== '/')
			{
				$this->return[] = array(
					'filename' => $this->_filename,
					'md5' => md5($this->_file_info['data']),
					'preview' => substr($this->_file_info['data'], 0, 100),
					'size' => $this->_file_info['size'],
					'formatted_size' => byte_format($this->_file_info['size']),
					'skipped' => false,
					'crc' => $this->_crc_check,
				);
			}
		}
	}

	/**
	 * Reads the local header, [local file header + file data + data_descriptor]
	 *
	 * What it does:
	 *
	 * - Unpacks the local file header, 26 bytes after the signature
	 * - Updates certain CDR fields based on the variable length data found
	 * - Sets the compressed data in to the array for processing
	 *
	 * Signature Definition:
	 * - Local file header signature: 4 bytes, always (0x04034b50)
	 * - Version needed to extract: 2 bytes
	 * - General purpose bit flag: 2 bytes
	 * - Compression method: 2 bytes
	 * - Last mod file time: 2 bytes
	 * - Last mod file date: 2 bytes
	 * - CRC-32: 4 bytes
	 * - Compressed size: 4 bytes
	 * - Uncompressed size: 4 bytes
	 * - Filename length: 2 bytes
	 * - Extra field length: 2 bytes
	 * - Filename (variable size)
	 * - Extra field (variable size)
	 */
	private function _read_local_header()
	{
		// The local header data is always the 26 bytes after the 4 byte signature
		$local_file_data = unpack('vversion_needed/vgeneral_purpose/vcompress_method/vfile_time/vfile_date/Vcrc/Vcompressed_size/Vsize/vfilename_length/vextra_field_length', substr($this->_file_info['data'], 4, 26));

		$this->_file_info['filename_length'] = $local_file_data['filename_length'];
		$this->_file_info['extra_field_length'] = $local_file_data['extra_field_length'];
		$this->_file_info['compress_data'] = substr($this->_file_info['data'], 30 + $this->_file_info['filename_length'] + $this->_file_info['extra_field_length'], $this->_file_info['compressed_size']);
		$this->_file_info['crc'] = empty($this->_file_info['crc']) ? $local_file_data['crc'] : $this->_file_info['crc'];
	}

	/**
	 * Does what it says, determines if we are writing this file or not
	 */
	private function _determine_write_this()
	{
		// If this is a file, and it doesn't exist.... happy days!
		if (substr($this->_filename, -1) !== '/' && !file_exists($this->destination . '/' . $this->_filename))
			$this->_write_this = true;
		// If the file exists, we may not want to overwrite it.
		elseif (substr($this->_filename, -1) !== '/')
			$this->_write_this = $this->overwrite;
		// This is a directory, so we're gonna want to create it. (probably...)
		elseif ($this->destination !== null && !$this->single_file)
		{
			// Just a little accident prevention, don't mind me.
			$this->_filename = strtr($this->_filename, array('../' => '', '/..' => ''));

			if (!file_exists($this->destination . '/' . $this->_filename))
				mktree($this->destination . '/' . $this->_filename, 0777);
			$this->_write_this = false;
		}
		else
			$this->_write_this = false;
	}

	/**
	 * Does the actual writing of the file
	 *
	 * - Writes the extracted file to disk or if we are extracting a single file
	 * - It returns the extracted data
	 */
	private function _write_this_file()
	{
		$this->_skip = false;
		$this->_found = false;

		// A directory may need to be created
		if ((strpos($this->_filename, '/') !== false && !$this->single_file) || (!$this->single_file && !is_dir($this->_file_info['dir'])))
			mktree($this->_file_info['dir'], 0777);

		// If we're looking for a **specific file**, and this is it... ka-bam, baby.
		if ($this->single_file && ($this->destination === $this->_filename || $this->destination === '*/' . basename($this->_filename)))
			$this->_found = $this->_file_info['data'];
		// Oh?  Another file.  Fine.  You don't like this file, do you?  I know how it is.
		// Yeah... just go away.  No, don't apologize.  I know this file's just not *good enough* for you.
		elseif ($this->single_file)
			$this->_skip = true;
		// Don't really want this file?
		elseif ($this->files_to_extract !== null && !in_array($this->_filename, $this->files_to_extract))
			$this->_skip = true;

		// Write it out then
		if ($this->_skip)
			return;
		elseif (!empty($this->_found))
			$this->_check_crc();
		elseif (!$this->_skip && $this->_found === false && $this->_check_crc())
			package_put_contents($this->destination . '/' . $this->_filename, $this->_file_info['data']);
	}

	/**
	 * Alters processing based on the general purpose flag bits
	 *
	 * What it does:
	 *
	 * - If bit 1 is set the file is protected, so it returns an empty one
	 * - If bit 3 is set then the data descriptor is read and processed
	 *
	 * The data descriptor, if it exists, is structured as
	 * - Local header signature: 4 bytes, optional (0x08074b50)
	 * - CRC-32: 4 bytes
	 * - Compressed size: 4 bytes
	 * - Uncompressed size: 4 bytes
	 *
	 * This descriptor exists only if bit 3 of the general purpose bit flag is set.
	 * It is byte aligned and immediately follows the last byte of compressed data.
	 * This descriptor is used only when it was not possible to seek in the output zip
	 * file, e.g., when the output zip file was standard output or a non seekable device.
	 */
	private function _check_general_purpose_flag()
	{
		// If bit 1 is set the file is encrypted so empty it instead of writing out gibberish
		if (($this->_file_info['general_purpose'] & 0x0001) !== 0)
			$this->_file_info['data'] = '';

		// See if bit 3 is set
		if (($this->_file_info['general_purpose'] & 0x0008) !== 0)
		{
			// Grab the 16 bytes after the compressed data
			$general_purpose = substr($this->_file_info['data'], 30 + $this->_file_info['filename_length'] + $this->_file_info['extra_field_length'] + $this->_file_info['compressed_size'], 16);

			// The spec allows for an optional header in the general purpose record
			if (substr($general_purpose, 0, 4) === "\x50\x4b\x07\x08")
				$general_purpose = substr($general_purpose, 4);

			// These values should be what's in the CDR record per spec
			$general_purpose_data = unpack('Vcrc/Vcompressed_size/Vsize', $general_purpose);
			$this->_file_info['crc'] = empty($this->_file_info['crc']) ? $general_purpose_data['crc'] : $this->_file_info['crc'];
			$this->_file_info['compressed_size'] = $general_purpose_data['compressed_size'];
			$this->_file_info['size'] = $general_purpose_data['size'];
		}
	}

	/**
	 * Checks the saved vs calculated crc values
	 */
	private function _check_crc()
	{
		// Convert everything to a hex value (unsigned)
		$crc_uncompressed = hash('crc32b', $this->_file_info['data']);
		$crc_compressed = hash('crc32b', $this->_file_info['compress_data']);
		$crc_this = str_pad(dechex($this->_file_info['crc']), 8, '0', STR_PAD_LEFT);

		$this->_crc_check = ($crc_this === $crc_uncompressed || $crc_this === $crc_compressed);

		return $this->_crc_check;
	}
}
