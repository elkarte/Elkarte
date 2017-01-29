<?php

/**
 * Class to unTgz a file (tar -xvf)
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 Release Candidate 1
 *
 */

/**
 * Utility class to un gzip + un tar package files
 *
 * if destination is null
 * - returns a list of files in the archive.
 *
 * if single_file is true
 * - returns the contents of the file specified by destination, if it exists, or false.
 * - destination can start with * and / to signify that the file may come from any directory.
 * - destination should not begin with a / if single_file is true.
 * - overwrites existing files with newer modification times if and only if overwrite is true.
 * - creates the destination directory if it doesn't exist, and is is specified.
 * - requires zlib support be built into PHP.
 * - returns an array of the files extracted on success
 */
class UnTgz
{
	/**
	 * Holds the return array of files processed
	 * @var mixed[]
	 */
	protected $return = array();

	/**
	 * Holds the data found in each tar file header block
	 * @var mixed[]
	 */
	protected $_current = array();

	/**
	 * Holds the file pointer, generally to the 512 block we are working on
	 * @var int
	 */
	protected $_offset = 0;

	/**
	 * If the file passes or fails crc check
	 * @var boolean
	 */
	protected $_crc_check = false;

	/**
	 * The current crc value of the data
	 * @var string|int
	 */
	protected $_crc;

	/**
	 * The claimed size of the data in the tarball
	 * @var int
	 */
	protected $_size;

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
	 * Current file header we are working on
	 * @var mixed[]|string
	 */
	protected $_header = array();

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
	 * @throws Elk_Exception package_no_zlib
	 */
	public function __construct($data, $destination, $single_file = false, $overwrite = false, $files_to_extract = null)
	{
		// Load the passed commands in to the class
		$this->data = $data;
		$this->destination = $destination;
		$this->single_file = $single_file;
		$this->overwrite = $overwrite;
		$this->files_to_extract = $files_to_extract;

		// This class sorta needs gzinflate!
		if (!function_exists('gzinflate'))
			throw new Elk_Exception('package_no_zlib', 'critical');

		// Make sure we have this loaded.
		loadLanguage('Packages');

		// Likely to need this
		require_once(SUBSDIR . '/Package.subs.php');

		// The destination needs exist and be writable or we are doomed
		umask(0);
		if ($this->destination !== null && !file_exists($this->destination) && !$this->single_file)
			mktree($this->destination, 0777);
	}

	/**
	 * Class controller, calls the ungzip / untar functions in required order
	 *
	 * @return boolean|array
	 */
	public function read_tgz_data()
	{
		// Snif test that this is a .tgz tar.gz file
		if (empty($this->_header) && $this->check_valid_tgz() === false)
			return false;

		// The tgz information for this archive
		if ($this->_read_header_tgz() === false)
			return false;

		// With the offset found, read and deflate the archive data
		if ($this->_ungzip_data() === false)
			return false;

		// With the archive data in hand, we need to un tarball it
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
	 * Loads the 10 byte header and validates its a tgz file
	 *
	 * @return boolean
	 */
	public function check_valid_tgz()
	{
		// No signature?
		if (strlen($this->data) < 10)
			return false;

		// Unpack the 10 byte signature so we can see what we have
		$this->_header = unpack('H2a/H2b/Ct/Cf/Vmtime/Cxtra/Cos', substr($this->data, 0, 10));

		// The IDentification number, gzip must be 1f8b
		return strtolower($this->_header['a'] . $this->_header['b']) === '1f8b';
	}

	/**
	 * Reads the archive file header
	 *
	 * What it does:
	 *
	 * - validates that the file is a tar.gz
	 * - validates that its compressed with deflate
	 * - processes header information so we can set the start of archive data
	 *    - archive comment
	 *    - archive filename
	 *    - header CRC
	 *
	 * Signature Definition:
	 * - identification byte 1 and 2: 2 bytes, 0x1f 0x8b
	 * - Compression Method: 1 byte
	 * - Flags: 1 byte
	 * - Last modification time Contains a POSIX timestamp, 4 bytes
	 * - Compression flags (or extra flags): 1 byte
	 * - Operating system, Value that indicates on which operating system file was created, 1 byte
	 */
	private function _read_header_tgz()
	{
		// Compression method needs to be 8 = deflate!
		if ($this->_header['t'] !== 8)
			return false;

		// Each bit of this byte represents a processing flag as follows
		// 0 fTEXT, 1 fHCRC, 2 fEXTRA, 3 fNAME, 4 fCOMMENT, 5 fENCRYPT, 6-7 reserved
		$flags = $this->_header['f'];

		// Start to read any data defined by the flags, its the data after the 10 byte header
		$this->_offset = 10;

		// fEXTRA flag set we simply skip over its entry and the length of its data
		if ($flags & 4)
		{
			$xlen = unpack('vxlen', substr($this->data, $this->_offset, 2));
			$this->_offset += $xlen['xlen'] + 2;
		}

		// Read the filename, its zero terminated
		if ($flags & 8)
		{
			$this->_header['filename'] = '';
			while ($this->data[$this->_offset] !== "\0")
				$this->_header['filename'] .= $this->data[$this->_offset++];
			$this->_offset++;
		}

		// Read the comment, its also zero terminated
		if ($flags & 16)
		{
			$this->_header['comment'] = '';
			while ($this->data[$this->_offset] !== "\0")
				$this->_header['comment'] .= $this->data[$this->_offset++];
			$this->_offset++;
		}

		// "Read" the header CRC $crc16 = unpack('vcrc16', substr($data, $this->_offset, 2));
		if ($flags & 2)
			$this->_offset += 2;
	}

	/**
	 * We now know where the start of the compressed data is in the archive
	 * The data is terminated with 4 bytes of CRC and 4 bytes of the original input size
	 */
	public function _ungzip_data()
	{
		// Unpack the crc and original size, its the trailing 8 bytes
		$check = unpack('Vcrc32/Visize', substr($this->data, strlen($this->data) - 8));
		$this->_crc = $check['crc32'];
		$this->_size = $check['isize'];

		// Extract the data, in this case its the tarball
		$this->data = @gzinflate(substr($this->data, $this->_offset, strlen($this->data) - 8 - $this->_offset));

		// Check the crc and the data size
		if (!$this->_check_crc() || (strlen($this->data) !== $check['isize']))
			return false;
	}

	/**
	 * Does the work of un tarballing the now ungzip'ed tar file
	 *
	 * What it does
	 * - Assumes its Ustar format
	 */
	private function _process_files()
	{
		// Tar files are written in 512 byte chunks
		$blocks = strlen($this->data) / 512 - 1;
		$this->_offset = 0;

		// While we have blocks to process
		while ($this->_offset < $blocks)
		{
			$this->_read_current_header();

			// Blank record?  This is probably at the end of the file.
			if (empty($this->_current['filename']))
			{
				$this->_offset += 512;
				continue;
			}

			// If its a directory, lets make sure it ends in a /
			if ($this->_current['type'] == 5 && substr($this->_current['filename'], -1) !== '/')
				$this->_current['filename'] .= '/';

			// Figure out what we will do with the data once we have it
			$this->_determine_write_this();

			// Read the files data, move the offset to the start of the following 512 block
			$size = ceil($this->_current['size'] / 512);
			$this->_current['data'] = substr($this->data, ++$this->_offset << 9, $this->_current['size']);
			$this->_offset += $size;

			// We can write this file or return its data or ...
			if ($this->_write_this && $this->destination !== null)
			{
				$this->_write_this_file();

				if ($this->_skip)
					continue;

				if ($this->_found)
					return;
			}

			if (substr($this->_current['filename'], -1) !== '/')
			{
				$this->return[] = array(
					'filename' => $this->_current['filename'],
					'md5' => md5($this->_current['data']),
					'preview' => substr($this->_current['data'], 0, 100),
					'size' => $this->_current['size'],
					'skipped' => false,
					'crc' => $this->_crc_check,
				);
			}
		}
	}

	/**
	 * Reads the tar file header block, its a 512 block and contains the following:
	 *
	 * Signature Definition:
	 * - char filename[100]; File name
	 * - char mode[8]; File mode
	 * - char uid[8]; Owner's numeric user ID
	 * - char gid[8]; Group's numeric user ID
	 * - char size[12]; File size in bytes (octal base)
	 * - char mtime[12]; Last modification time in numeric Unix time format (octal)
	 * - char checksum[8]; Checksum for header record
	 * - char type[1]; Link indicator (file type 0=normal, 1=hard, 2=symlink ... 5=directory ...
	 * - char linkname[100]; Name of linked file
	 * - char magic[6]; UStar indicator "ustar"
	 * - char version[2]; UStar version "00"
	 * - char uname[32]; Owner user name
	 * - char gname[32]; Owner group name
	 * - char devmajor[8]; Device major number
	 * - char devminor[8]; Device minor number
	 * - char path[155]; Filename prefix
	 */
	private function _read_current_header()
	{
		$octdec = array('mode', 'uid', 'gid', 'size', 'mtime', 'checksum', 'type');

		// Each file object is preceded by a 512-byte header record on 512 boundaries
		$this->_header = substr($this->data, $this->_offset << 9, 512);

		// Unpack
		$this->_current = unpack('a100filename/a8mode/a8uid/a8gid/a12size/a12mtime/a8checksum/a1type/a100linkname/a6magic/a2version/a32uname/a32gname/a8devmajor/a8devminor/a155path', $this->_header);

		// Clean the header fields, convert octal to decimal as needed
		foreach ($this->_current as $key => $value)
		{
			if (in_array($key, $octdec))
				$this->_current[$key] = octdec(trim($value));
			else
				$this->_current[$key] = trim($value);
		}
	}

	/**
	 * Does what it says, determines if we are writing this file or not
	 */
	private function _determine_write_this()
	{
		// Not a directory and doesn't exist already...
		if (substr($this->_current['filename'], -1) !== '/' && !file_exists($this->destination . '/' . $this->_current['filename']))
			$this->_write_this = true;
		// File exists... check if it is newer.
		elseif (substr($this->_current['filename'], -1) !== '/')
			$this->_write_this = $this->overwrite || filemtime($this->destination . '/' . $this->_current['filename']) < $this->_current['mtime'];
		// Folder... create.
		elseif ($this->destination !== null && !$this->single_file)
		{
			// Protect from accidental parent directory writing...
			$this->_current['filename'] = strtr($this->_current['filename'], array('../' => '', '/..' => ''));

			if (!file_exists($this->destination . '/' . $this->_current['filename']))
				mktree($this->destination . '/' . $this->_current['filename'], 0777);
			$this->_write_this = false;
		}
		else
			$this->_write_this = false;
	}

	/**
	 * Does the actual writing of the file
	 *
	 * - Writes the extracted file to disk or if we are extracting a single file
	 * - it returns the extracted data
	 */
	private function _write_this_file()
	{
		$this->_skip = false;
		$this->_found = false;

		// A directory may need to be created
		if (strpos($this->_current['filename'], '/') !== false && !$this->single_file)
			mktree($this->destination . '/' . dirname($this->_current['filename']), 0777);

		// Is this the file we're looking for?
		if ($this->single_file && ($this->destination === $this->_current['filename'] || $this->destination === '*/' . basename($this->_current['filename'])))
			$this->_found = $this->_current['data'];
		// If we're looking for another file, keep going.
		elseif ($this->single_file)
			$this->_skip = true;
		// Looking for restricted files?
		elseif ($this->files_to_extract !== null && !in_array($this->_current['filename'], $this->files_to_extract))
			$this->_skip = true;

		// Write it out then
		if ($this->_check_header_crc() && $this->_skip === false && $this->_found === false)
			package_put_contents($this->destination . '/' . $this->_current['filename'], $this->_current['data']);
	}

	/**
	 * Checks the saved vs calculated crc values
	 */
	private function _check_crc()
	{
		// Make sure we have unsigned crc padded hex.
		$crc_uncompressed = hash('crc32b', $this->data);
		$this->_crc = str_pad(dechex($this->_crc), 8, '0', STR_PAD_LEFT);

		return !($this->data === false || ($this->_crc !== $crc_uncompressed));
	}

	/**
	 * Checks the saved vs calculated crc values
	 */
	private function _check_header_crc()
	{
		$this->_crc = 256;

		// Build the checksum for this header and make sure it matches what it claims
		for ($i = 0; $i < 148; $i++)
			$this->_crc += ord($this->_header[$i]);
		for ($i = 156; $i < 512; $i++)
			$this->_crc += ord($this->_header[$i]);

		$this->_crc_check = $this->_current['checksum'] === $this->_crc;

		return $this->_crc_check;
	}
}
