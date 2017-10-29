<?php

/**
 * Used to combine css and js files in to a single compressed file
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1.1
 */

/**
 * Used to combine css or js files in to a single file
 *
 * What it does:
 *
 * - Checks if the files have changed, and if so rebuilds the amalgamation
 * - Calls minification classes to reduce size of css and js file saving bandwidth
 * - Can creates a .gz file, be would require .htaccess or the like to use
 */
class Site_Combiner
{
	/**
	 * Holds all the files contents that we have joined in to one
	 *
	 * @var array
	 */
	private $_combine_files = array();

	/**
	 * Holds the file name of our newly created file
	 *
	 * @var string
	 */
	private $_archive_name = null;

	/**
	 * Holds the file names of the files in the compilation
	 *
	 * @var string
	 */
	private $_archive_filenames = null;

	/**
	 * Holds the comment line to add at the start of the compressed compilation
	 *
	 * @var string
	 */
	private $_archive_header = null;

	/**
	 * Holds the file data of the combined files
	 *
	 * @var string
	 */
	private $_cache = array();

	/**
	 * Holds the file data of pre minimized files
	 *
	 * @var string
	 */
	private $_min_cache = array();

	/**
	 * Holds the minified data of the combined files
	 *
	 * @var string
	 */
	private $_minified_cache = null;

	/**
	 * The directory where we will save the combined and packed files
	 *
	 * @var string
	 */
	private $_archive_dir = null;

	/**
	 * The url where we will save the combined and packed files
	 *
	 * @var string
	 */
	private $_archive_url = null;

	/**
	 * The stale parameter added to the url
	 *
	 * @var string
	 */
	private $_archive_stale = '';

	/**
	 * All the cache-stale params added to the file urls
	 *
	 * @var string[]
	 */
	private $_stales = array();

	/**
	 * All files that was not possible to combine
	 *
	 * @var string[]
	 */
	private $_spares = array();

	/**
	 * Location of the closure compiler
	 * @var string
	 */
	private $_url = 'http://closure-compiler.appspot.com/compile';

	/**
	 * Base post header to send to the closure compiler
	 * @var string
	 */
	private $_post_header = 'output_info=compiled_code&output_format=text&compilation_level=SIMPLE_OPTIMIZATIONS';

	/**
	 * Nothing much to do but start
	 *
	 * @param string $cachedir
	 * @param string $cacheurl
	 */
	public function __construct($cachedir, $cacheurl)
	{
		// Init
		$this->_archive_dir = $cachedir;
		$this->_archive_url = $cacheurl;

		$this->_spares = array();
	}

	/**
	 * Combine javascript files in to a single file to save requests
	 *
	 * @param mixed[] $files array created by loadjavascriptfile function
	 * @param bool $do_defered true when coming from footer area, false for header
	 */
	public function site_js_combine($files, $do_defered)
	{
		// No files or missing we are done
		if (empty($files))
		{
			return false;
		}

		// Directory not writable then we are done
		if ($this->_validDestination() === false)
		{
			// Anything is spare
			$this->_addSpare($files);
			return false;
		}

		// Get the filename's and last modified time for this batch
		foreach ($files as $id => $file)
		{
			$load = (!$do_defered && empty($file['options']['defer'])) || ($do_defered && !empty($file['options']['defer']));

			// Get the ones that we would load locally so we can merge them
			if ($load && (empty($file['options']['local']) || !$this->_addFile($file['options'])))
			{
				$this->_addSpare(array($id => $file));
			}
		}

		// Nothing to do, then we are done
		if (count($this->_combine_files) === 0)
			return true;

		// Create the archive name
		$this->_buildName('.js');

		// No file, or a stale one, create a new compilation
		if ($this->_isStale())
		{
			// Our buddies will be needed for this to work.
			require_once(SUBSDIR . '/Package.subs.php');

			$this->_archive_header = '// ' . $this->_archive_filenames . "\n";
			$this->_combineFiles('js');

			// Minify these files to save space,
			$this->_minified_cache = $this->_jsCompiler();

			// And save them for future users
			$this->_saveFiles();
		}

		// Return the name for inclusion in the output
		return $this->_archive_url . '/' . $this->_archive_name . $this->_archive_stale;
	}

	/**
	 * Combine css files in to a single file
	 *
	 * @param string[] $files
	 */
	public function site_css_combine($files)
	{
		// No files or missing we are done
		if (empty($files))
		{
			return false;
		}

		// Directory not writable then we are done
		if ($this->_validDestination() === false)
		{
			// Anything is spare
			$this->_addSpare($files);
			return false;
		}

		// Get the filenames and last modified time for this batch
		foreach ($files as $id => $file)
		{
			// Get the ones that we would load locally so we can merge them
			if (empty($file['options']['local']) || !$this->_addFile($file['options']))
			{
				$this->_addSpare(array($id => $file));
			}
		}

		// Nothing to do so return
		if (count($this->_combine_files) === 0)
			return true;

		// Create the css archive name
		$this->_buildName('.css');

		// No file, or a stale one, so we create a new css compilation
		if ($this->_isStale())
		{
			$this->_archive_header = '/* ' . $this->_archive_filenames . " */\n";
			$this->_combineFiles('css');

			// CSSmin it to save some space
			require_once(EXTDIR . '/cssmin.php');
			$compressor = new CSSmin();
			$this->_minified_cache = $compressor->run($this->_cache);

			// Combine in any pre minimized css files to our new minimized string
			$this->_minified_cache .= "\n" . $this->_min_cache;

			$this->_saveFiles();
		}

		// Return the name
		return $this->_archive_url . '/' . $this->_archive_name . $this->_archive_stale;
	}

	/**
	 * Returns the info of the files that were not combined
	 *
	 * @return string[]
	 */
	public function getSpares()
	{
		return $this->_spares;
	}

	/**
	 * Deletes the CSS hives from the cache.
	 */
	public function removeCssHives()
	{
		return $this->_removeHives('css');
	}

	/**
	 * Deletes the JS hives from the cache.
	 */
	public function removeJsHives()
	{
		return $this->_removeHives('js');
	}

	/**
	 * Deletes hives from the cache based on extension.
	 *
	 * @param string $ext
	 */
	protected function _removeHives($ext)
	{
		$path = $this->_archive_dir . '/hive-*.' . $ext;

		$glob = new GlobIterator($path, FilesystemIterator::SKIP_DOTS);
		$return = true;

		foreach ($glob as $file)
		{
			$return &= @unlink($file->getPathname());
		}
		return $return;
	}

	/**
	 * Tests if the destination directory exists and is writable
	 *
	 * @return bool
	 */
	protected function _validDestination()
	{
		return file_exists($this->_archive_dir) && is_writable($this->_archive_dir);
	}

	/**
	 * Adds files to the spare list
	 *
	 * @param mixed[]
	 */
	protected function _addSpare($files)
	{
		foreach ($files as $id => $file)
		{
			$this->_spares[$id] = $file;
		}
	}

	/**
	 * Add all the file parameters to the $_combine_files array
	 *
	 * What it does:
	 *
	 * - If the file has a 'stale' option defined it will be added to the
	 *   $_stales array as well to be used later
	 * - Tags any files that are pre-minimized by filename matching .min.js
	 *
	 * @param string[] $options An array with all the passed file options:
	 * - dir
	 * - basename
	 * - file
	 * - url
	 * - stale (optional)
	 */
	private function _addFile($options)
	{
		if (isset($options['dir']))
		{
			$filename = $options['dir'] . $options['basename'];
			$this->_combine_files[$options['basename']] = array(
				'file' => $filename,
				'basename' => $options['basename'],
				'url' => $options['url'],
				'filemtime' => filemtime($filename),
				'minimized' => (bool) strpos($options['basename'], '.min.js') !== false || strpos($options['basename'], '.min.css') !== false,
			);

			$this->_stales[] = $this->_combine_files[$options['basename']]['filemtime'];

			return true;
		}
		return false;
	}

	/**
	 * Determines if the existing combined file is stale
	 *
	 * - If any date of the files that make up the archive are newer than the archive, its considered stale
	 */
	private function _isStale()
	{
		// If any files in the archive are newer than the archive file itself, then the archive is stale
		$filemtime = file_exists($this->_archive_dir . '/' . $this->_archive_name) ? filemtime($this->_archive_dir . '/' . $this->_archive_name) : 0;

		foreach ($this->_combine_files as $file)
		{
			if ($file['filemtime'] > $filemtime)
				return true;
		}

		return false;
	}

	/**
	 * Creates a new archive name
	 *
	 * @param string $type - should be one of '.js' or '.css'
	 */
	private function _buildName($type)
	{
		global $settings;

		// Create this groups archive name
		foreach ($this->_combine_files as $file)
			$this->_archive_filenames .= $file['basename'] . ' ';

		// Add in the actual theme url to make the sha1 unique to this hive
		$this->_archive_filenames = $settings['actual_theme_url'] . '/' . trim($this->_archive_filenames);

		// Save the hive, or a nest, or a conglomeration. Like it was grown
		$this->_archive_name = 'hive-' . sha1($this->_archive_filenames) . $type;

		// Create a unique cache stale for this hive ?x12345
		if (!empty($this->_stales))
			$this->_archive_stale = '?x' . hash('crc32b', implode(' ', $this->_stales));
	}

	/**
	 * Reads each files contents in to the _combine_files array
	 *
	 * What it does:
	 *
	 * - For each file, loads its contents in to the content key
	 * - If the file is CSS will convert some common relative links to the
	 * location of the hive
	 *
	 * @param string $type one of css or js
	 */
	private function _combineFiles($type)
	{
		// Remove any old cache file(s)
		@unlink($this->_archive_dir . '/' . $this->_archive_name);
		@unlink($this->_archive_dir . '/' . $this->_archive_name . '.gz');

		$_cache = array();
		$_min_cache = array();

		// Read in all the data so we can process
		foreach ($this->_combine_files as $key => $file)
		{
			if (!file_exists($file['file']))
			{
				continue;
			}

			$tempfile = trim(file_get_contents($file['file']));
			$tempfile = (substr($tempfile, -3) === '}()') ? $tempfile . ';' : $tempfile;
			$this->_combine_files[$key]['content'] = $tempfile;

			// CSS needs relative locations converted for the moved hive to work
			if ($type === 'css')
			{
				$tempfile = str_replace(array('../../images', '../images'), $file['url'] . '/images', $tempfile);
				$tempfile = str_replace(array('../../webfonts', '../webfonts'), $file['url'] . '/webfonts', $tempfile);
				$tempfile = str_replace(array('../../scripts', '../scripts'), $file['url'] . '/scripts', $tempfile);
			}

			// Add the file to the correct array for processing
			if ($file['minimized'] === false)
				$_cache[] = $tempfile;
			else
				$_min_cache[] = $tempfile;
		}

		// Build out our combined file strings
		$this->_cache = implode("\n", $_cache);
		$this->_min_cache = implode("\n", $_min_cache);
		unset($_cache, $_min_cache);
	}

	/**
	 * Save a compilation as text and optionally a compressed .gz file
	 */
	private function _saveFiles()
	{
		// Add in the file header if available
		if (!empty($this->_archive_header))
			$this->_minified_cache = $this->_archive_header . $this->_minified_cache;

		// First the plain text version
		file_put_contents($this->_archive_dir . '/' . $this->_archive_name, $this->_minified_cache, LOCK_EX);

		// And now the compressed version, just uncomment the below
		/*
		$fp = gzopen($this->_archive_dir . '/' . $this->_archive_name . '.gz', 'w9');
		gzwrite ($fp, $this->_minified_cache);
		gzclose($fp);
		*/
	}

	/**
	 * Takes a js file and compresses it to save space, will try several methods
	 * to minimize the code
	 *
	 * What it does:
	 *
	 * - Attempt to use the closure-compiler API using code_url
	 * - Failing that will use JSqueeze
	 * - Failing that it will use the closure-compiler API using js_code
	 *    a) single block if it can or
	 *    b) as multiple calls
	 * - Failing that will return original uncompressed file
	 */
	private function _jsCompiler()
	{
		// First try the closure request using code_url param
		$fetch_data = $this->_closure_code_url();

		// Nothing returned or an error, try our internal JSqueeze minimizer
		if ($fetch_data === false || trim($fetch_data) == '' || preg_match('/^Error\(\d{1,2}\):\s/m', $fetch_data))
		{
			// To prevent a stack overflow segmentation fault, which silently kills Apache, we need to limit
			// recursion on windows.  This may cause JSqueeze to fail, but at least its then catchable.
			if (detectServer()->is('windows'))
				@ini_set('pcre.recursion_limit', '524');

			require_once(EXTDIR . '/JSqueeze.php');
			$jsqueeze = new Patchwork\JSqueeze;
			$fetch_data = $jsqueeze->squeeze($this->_cache);
		}

		// If we still have no data, then try the post js_code method to the closure compiler
		if ($fetch_data === false || trim($fetch_data) == '')
			$fetch_data = $this->_closure_js_code();

		// If we have nothing to return, use the original data
		$fetch_data = ($fetch_data === false || trim($fetch_data) == '') ? $this->_cache : $fetch_data;

		// Return a combined pre minimized + our minimized string
		return $this->_min_cache . "\n" . $fetch_data;
	}

	/**
	 * Makes a request to the closure compiler using the code_url syntax
	 *
	 * What it does:
	 *
	 * - Allows us to make a single request and let the compiler fetch the files from us
	 * - Best option if its available (closure can see the files)
	 */
	private function _closure_code_url()
	{
		$post_data = '';

		// Build the closure request using code_url param, this allows us to do a single request
		foreach ($this->_combine_files as $file)
		{
			if ($file['minimized'] === false)
				$post_data .= '&code_url=' . urlencode($file['url'] . '/scripts/' . $file['basename'] . $this->_archive_stale);
		}

		return fetch_web_data($this->_url, $this->_post_header . $post_data);
	}

	/**
	 * Makes a request to the closure compiler using the js_code syntax
	 *
	 * What it does:
	 *
	 * - If our combined file size allows, this is done as a single post to the compiler
	 * - If the combined string is to large, then it is processed as chunks done
	 * to minimize the number of posts required
	 */
	private function _closure_js_code()
	{
		// As long as we are below 200000 in post data size we can do this in one request
		if (Util::strlen(urlencode($this->_post_header . $this->_cache)) <= 200000)
		{
			$post_data = '&js_code=' . urlencode($this->_cache);
			$fetch_data = fetch_web_data($this->_url, $this->_post_header . $post_data);
		}
		// Simply to much data for a single post so break it down in to as few as possible
		else
			$fetch_data = $this->_closure_js_code_chunks();

		return $fetch_data;
	}

	/**
	 * Combine files in to <200k chunks and make closure compiler requests
	 *
	 * What it does:
	 *
	 * - Loads as many files as it can in to a single post request while
	 * keeping the post size within the limits accepted by the service
	 * - Will do multiple requests until done, combining the results
	 * - Returns the compressed string or the original if an error occurs
	 */
	private function _closure_js_code_chunks()
	{
		$fetch_data = '';
		$combine_files = array_values($this->_combine_files);

		for ($i = 0, $filecount = count($combine_files); $i < $filecount; $i++)
		{
			// New post request, start off empty
			$post_len = 0;
			$post_data = '';
			$post_data_raw = '';

			// Combine data in to chunks of < 200k to minimize http posts
			while ($i < $filecount)
			{
				// Get the details for this file
				$file = $combine_files[$i];

				// Skip over minimized ones
				if ($file['minimized'] === true)
				{
					$i++;
					continue;
				}

				// Prepare the data for posting
				$data = urlencode($file['content']);
				$data_len = Util::strlen($data);

				// While we can add data to the post and not exceed the post size allowed by the service
				if ($data_len + $post_len < 200000)
				{
					$post_data .= $data;
					$post_data_raw .= $file['content'];
					$post_len = $data_len + $post_len;
					$i++;
				}
				// No more room in this request, so back up and make the request
				else
				{
					$i--;
					break;
				}
			}

			// Send it off and get the results
			$post_data = '&js_code=' . $post_data;
			$data = fetch_web_data($this->_url, $this->_post_header . $post_data);

			// Use the results or the raw data if an error is detected
			$fetch_data .= ($data === false || trim($data) == '' || preg_match('/^Error\(\d{1,2}\):\s/m', $data)) ? $post_data_raw : $data;
		}

		return $fetch_data;
	}
}
