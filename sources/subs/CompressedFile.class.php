<?php

/**
 * This file contains a class for handling tar.gz and .zip files
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Release Candidate 2
 *
 */

if (!defined('ELK'))
	die('No access...');

class Compressed_File
{
	/**
	 * Name (full path or url) of the file to decompress
	 *
	 * @var string
	 */
	private $gzfilename = '';

	/**
	 * Destination path
	 *
	 * @var string
	 */
	private $destination = null;

	/**
	 * If extract a single file?
	 *
	 * @var bool
	 */
	private $single_file = false;

	/**
	 * If the files should be overwritten or not
	 *
	 * @var bool
	 */
	private $overwrite = false;

	/**
	 * List of files that you want to extract from the archive
	 *
	 * @var string[]
	 */
	private $files_to_extract = null;

	/**
	 * The content of the archive
	 *
	 * @var string
	 */
	private $data = null;

	/**
	 * Headers of the archive
	 *
	 * @var string[]
	 */
	private $header = array();

	/**
	 * Constructor of the class, assigns properties and do some checks
	 * if destination is null
	 * - returns a list of files in the archive.
	 *
	 * if single_file is true
	 * - returns the contents of the file specified by destination, if it exists, or false.
	 * - destination can start with * and / to signify that the file may come from any directory.
	 * - destination should not begin with a / if single_file is true.
	 *
	 * - existing files with newer modification times if and only if overwrite is true.
	 * - creates the destination directory if it doesn't exist, and is is specified.
	 * - requires zlib support be built into PHP.
	 * - returns an array of the files extracted on success
	 * - if files_to_extract is not equal to null only extracts the files within this array.
	 *
	 * @package Packages
	 * @param string $gzfilename
	 * @param string $destination
	 * @param bool $single_file = false
	 * @param bool $overwrite = false
	 * @param string[]|null $files_to_extract = null
	 * @return string|false
	 */
	public function __construct($gzfilename, $destination, $single_file = false, $overwrite = false, $files_to_extract = null)
	{
		$this->gzfilename = $gzfilename;
		$this->destination = $destination;
		$this->single_file = $single_file;
		$this->overwrite = $overwrite;
		$this->files_to_extract = $files_to_extract;
	}

	/**
	 * Reads the content of the file (either from the file system or from the web)
	 *
	 * @package Packages
	 * @return mixed[]|false
	 */
	public function read()
	{
		$this->loadData();

		if ($this->data === false || strlen($this->data) < 10)
			return false;

		return $this->determineType();
	}

	/**
	 * Finds out what type the file is.
	 *
	 * @package Packages
	 * @return mixed[]|false
	 */
	protected function determineType()
	{
		umask(0);
		if (!$this->single_file && $this->destination !== null && !file_exists($this->destination))
			mktree($this->destination, 0777);

		// Unpack the signature so we can see what we have
		$this->header = unpack('H2a/H2b/Ct/Cf/Vmtime/Cxtra/Cos', substr($this->data, 0, 10));
		$this->header['filename'] = '';
		$this->header['comment'] = '';

		// The IDentification number, gzip must be 1f8b
		if (strtolower($this->header['a'] . $this->header['b']) != '1f8b')
		{
			// Okay, this is not a tar.gz, but maybe it's a zip file.
			if (substr($this->data, 0, 2) === 'PK')
				return $this->read_zip_data();
			else
				return false;
		}
		else
			return $this->read_tgz_file();
	}

	/**
	 * Parses the passed data into an array
	 *
	 * @package Packages
	 * @return mixed[]|false
	 */
	public function read_data($data)
	{
		$this->data = $data;

		return $this->read_tgz_data();
	}

	/**
	 * Reads a .tar.gz file, filename, in and extracts file(s) from it.
	 * essentially just a shortcut for read_tgz_data().
	 *
	 * @package Packages
	 * @return mixed[]|false
	 */
	protected function loadData()
	{
		// From a web site
		if (substr($this->gzfilename, 0, 7) == 'http://' || substr($this->gzfilename, 0, 8) == 'https://')
		{
			$this->data = fetch_web_data($this->gzfilename, '', true);
		}
		// Or a file on the system
		else
		{
			$this->data = @file_get_contents($this->gzfilename);
		}
	}

	/**
	 * Extracts a file or files from the .tar.gz contained in data.
	 *
	 * - Detects if the file is really a .zip file, and if so returns the result of read_zip_data
	 *
	 * @package Packages
	 * @param string $data
	 * @return mixed[]|false
	 */
	protected function read_tgz_data()
	{
		// Compression method needs to be 8 = deflate!
		if ($this->header['t'] != 8)
			return false;

		// Each bit of this byte represents a processing flag as follows
		// 0 fTEXT, 1 fHCRC, 2 fEXTRA, 3 fNAME, 4 fCOMMENT, 5 fENCRYPT, 6-7 reserved
		$flags = $this->header['f'];

		// Start to read any data defined by the flags
		$offset = 10;

		// fEXTRA flag set we simply skip over its entry and the length of its data
		if ($flags & 4)
		{
			$xlen = unpack('vxlen', substr($this->data, $offset, 2));
			$offset += $xlen['xlen'] + 2;
		}

		// Read the filename, its zero terminated
		if ($flags & 8)
		{
			while ($this->data[$offset] != "\0")
				$this->header['filename'] .= $this->data[$offset++];
			$offset++;
		}

		// Read the comment, its also zero terminated
		if ($flags & 16)
		{
			while ($this->data[$offset] != "\0")
				$this->header['comment'] .= $this->data[$offset++];
			$offset++;
		}

		// "Read" the header CRC
		if ($flags & 2)
			$offset += 2; // $crc16 = unpack('vcrc16', substr($this->data, $offset, 2));

		// This function sorta needs gzinflate!
		if (!function_exists('gzinflate'))
			throw new Elk_Exception(array('Packages', 'package_no_zlib'), 'critical');

		// We have now arrived at the start of the compressed data,
		// Its terminated with 4 bytes of CRC and 4 bytes of the original input size
		$crc = unpack('Vcrc32/Visize', substr($this->data, strlen($this->data) - 8, 8));
		$this->data = @gzinflate(substr($this->data, $offset, strlen($this->data) - 8 - $offset));

		// crc32_compat and crc32 may not return the same results, so we accept either.
		if ($this->data === false || ($crc['crc32'] != crc32_compat($this->data) && $crc['crc32'] != crc32($this->data)))
			return false;

		$octdec = array('mode', 'uid', 'gid', 'size', 'mtime', 'checksum', 'type');
		$blocks = strlen($this->data) / 512 - 1;
		$offset = 0;
		$return = array();

		// We have Un-gziped the data, now lets extract the tar files
		while ($offset < $blocks)
		{
			$header = substr($this->data, $offset << 9, 512);
			$current = unpack('a100filename/a8mode/a8uid/a8gid/a12size/a12mtime/a8checksum/a1type/a100linkname/a6magic/a2version/a32uname/a32gname/a8devmajor/a8devminor/a155path', $header);

			// Clean the header fields, convert octal ones to decimal
			foreach ($current as $k => $v)
			{
				if (in_array($k, $octdec))
					$current[$k] = octdec(trim($v));
				else
					$current[$k] = trim($v);
			}

			// Blank record?  This is probably at the end of the file.
			if (empty($current['filename']))
			{
				$offset += 512;
				continue;
			}

			// If its a directory, lets make sure it ends in a /
			if ($current['type'] == 5 && substr($current['filename'], -1) != '/')
				$current['filename'] .= '/';

			// Build the checksum for this file and make sure it matches
			$checksum = 256;
			for ($i = 0; $i < 148; $i++)
				$checksum += ord($header[$i]);
			for ($i = 156; $i < 512; $i++)
				$checksum += ord($header[$i]);

			if ($current['checksum'] != $checksum)
				break;

			$size = ceil($current['size'] / 512);
			$current['data'] = substr($this->data, ++$offset << 9, $current['size']);
			$offset += $size;

			$write_this = $this->canWriteThis(&$current);

			if ($write_this && $this->destination !== null)
			{
				$dir = null;
				if (strpos($current['filename'], '/') !== false && !$this->single_file)
					$dir = $this->destination . '/' . dirname($current['filename']);

				if ($this->writeCurrent($current, $dir))
					return $current['data'];
			}

			// Not a directory, add it to our results
			if (substr($current['filename'], -1, 1) != '/')
				$return[] = array(
					'filename' => $current['filename'],
					'md5' => md5($current['data']),
					'preview' => substr($current['data'], 0, 100),
					'size' => $current['size'],
					'skipped' => false
				);
		}

		if ($this->destination !== null && !$this->single_file)
			package_flush_cache();

		if ($this->single_file)
			return false;
		else
			return $return;
	}

	/**
	 * Checks if the file or the directory can/should be written
	 *
	 * @package Packages
	 * @param string[] $current
	 * @return bool
	 */
	protected function canWriteThis(&$current)
	{
		// If this is a file, and it doesn't exist.... happy days!
		if (substr($current['filename'], -1, 1) != '/' && !file_exists($this->destination . '/' . $current['filename']))
			$write_this = true;
		// File exists... check if it is newer.
		elseif (substr($current['filename'], -1, 1) != '/')
			$write_this = $this->overwrite || filemtime($this->destination . '/' . $current['filename']) < $current['mtime'];
		// This is a directory, so we're gonna want to create it. (probably...)
		elseif ($this->destination !== null && !$this->single_file)
		{
			// Protect from accidental parent directory writing...
			$current['filename'] = strtr($current['filename'], array('../' => '', '/..' => ''));

			if (!file_exists($this->destination . '/' . $current['filename']))
				mktree($this->destination . '/' . $current['filename'], 0777);
			$write_this = false;
		}
		else
			$write_this = false;

		return $write_this;
	}

	/**
	 * Writes the file or skip if it is not needed
	 *
	 * @package Packages
	 * @param string[] $current
	 * @param string|null $make_dir directory path to create
	 * @return bool
	 */
	protected function writeCurrent($current, $make_dir = null)
	{
		if ($make_dir !== null)
			mktree($make_dir, 0777);

		// If we're looking for a specific file, and this is it... ka-bam, baby.
		if ($this->single_file && ($this->destination == $current['filename'] || $this->destination == '*/' . basename($current['filename'])))
			return true;
		// Oh?  Another file.  Fine.  You don't like this file, do you?  I know how it is.  Yeah... just go away.  No, don't apologize.  I know this file's just not *good enough* for you.
		elseif ($this->single_file)
			return false;
		// Don't really want this?
		elseif ($this->files_to_extract !== null && !in_array($current['filename'], $this->files_to_extract))
			return false;

		package_put_contents($this->destination . '/' . $current['filename'], $current['data']);

		return false;
	}

	/**
	 * Extract zip data.
	 *
	 * @package Packages
	 * @return mixed[]|false
	 */
	protected function read_zip_data()
	{
		// Look for the end of directory signature 0x06054b50
		$data_ecr = explode("\x50\x4b\x05\x06", $this->data);
		if (!isset($data_ecr[1]))
			return false;

		$return = array();

		// Get all the basic zip file info since we are here
		$zip_info = unpack('vdisknum/vdisks/vrecords/vfiles/Vsize/Voffset/vcomment_length', $data_ecr[1]);
		$zip_info['comment'] = substr($data_ecr[1], 18, $zip_info['comment_length']);

		// Cut file at the central directory file header signature -- 0x02014b50, use unpack if you want any of the data, we don't
		$file_sections = explode("\x50\x4b\x01\x02", $this->data);

		// Cut the result on each local file header -- 0x04034b50 so we have each file in the archive as an element.
		$file_sections = explode("\x50\x4b\x03\x04", $file_sections[0]);
		array_shift($file_sections);

		// Sections and count from the signature must match or the zip file is bad
		if (count($file_sections) != $zip_info['files'])
			return false;

		// Go though each file in the archive
		foreach ($file_sections as $this->data)
		{
			// Get all the important file information.
			$current = unpack('vversion/vgeneral_purpose/vcompress_method/vfile_time/vfile_date/Vcrc/Vcompressed_size/Vsize/vfilename_length/vextrafield_length', $this->data);
			$current['filename'] = substr($this->data, 26, $current['filename_length']);
			$current['dir'] = $this->destination . '/' . dirname($current['filename']);

			// If bit 3 (0x08) of the general-purpose flag is set, then the CRC and file size were not available when the header was written
			// In this case the CRC and size are instead appended in a 12-byte structure immediately after the compressed data
			if ($current['general_purpose'] & 0x0008)
			{
				$unzipped2 = unpack('Vcrc/Vcompressed_size/Vsize', substr($$data, -12));
				$current['crc'] = $unzipped2['crc'];
				$current['compressed_size'] = $unzipped2['compressed_size'];
				$current['size'] = $unzipped2['size'];
				unset($unzipped2);
			}

			$write_this = $this->canWriteThis(&$current);

			// Get the actual compressed data.
			$current['data'] = substr($data, 26 + $current['filename_length'] + $current['extrafield_length']);

			// Only inflate it if we need to ;)
			if (!empty($current['compress_method']) || ($current['compressed_size'] != $current['size']))
				$current['data'] = gzinflate($current['data']);

			// Okay!  We can write this file, looks good from here...
			if ($write_this && $this->destination !== null)
			{
				$dir = null;

				if ((strpos($current['filename'], '/') !== false && !$this->single_file) || (!$this->single_file && !is_dir($current['dir'])))
					$dir = $current['dir'];

				if ($this->writeCurrent($current, $dir))
					return $current['data'];
			}

			// Not a directory, add it to our results
			if (substr($current['filename'], -1, 1) != '/')
				$return[] = array(
					'filename' => $current['filename'],
					'md5' => md5($current['data']),
					'preview' => substr($current['data'], 0, 100),
					'size' => $current['size'],
					'skipped' => false
				);
		}

		if ($this->destination !== null && !$this->single_file)
			package_flush_cache();

		if ($this->single_file)
			return false;
		else
			return $return;
	}
}