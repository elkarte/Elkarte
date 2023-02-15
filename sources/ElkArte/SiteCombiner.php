<?php

/**
 * Used to combine css and js files in to a single compressed file
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 */

namespace ElkArte;

use Patchwork\JSqueeze;
use tubalmartin\CssMin\Minifier as CSSmin;

/**
 * Used to combine css or js files in to a single file
 *
 * What it does:
 *
 * - Checks if the files have changed, and if so rebuilds the amalgamation
 * - Calls minification classes to reduce size of css and js file saving bandwidth
 * - Can creates a .gz file, be would require .htaccess or the like to use
 */
class SiteCombiner
{
	/** @var array Holds all the files contents that we have joined in to one */
	private $_combine_files = [];

	/** @var string Holds the file name of our newly created file */
	private $_archive_name;

	/** @var string Holds the file names of the files in the compilation */
	private $_archive_filenames;

	/** @var string Holds the comment line to add at the start of the compressed compilation */
	private $_archive_header;

	/** @var string Holds the file data of the combined files */
	private $_cache = [];

	/** @var string Holds the file data of pre minimized files */
	private $_min_cache = [];

	/** @var string Holds the minified data of the combined files */
	private $_minified_cache;

	/** @var string The directory where we will save the combined and packed files */
	private $_archive_dir;

	/** @var string The url where we will save the combined and packed files */
	private $_archive_url;

	/** @var string The stale parameter added to the url */
	private $_archive_stale = CACHE_STALE;

	/** @var bool The parameter to indicate if minification should be run */
	private $_minify;

	/** @var string[] All files that was not possible to combine */
	private $_spares = [];

	/** @var \ElkArte\FileFunctions */
	private $fileFunc;

	/**
	 * Nothing much to do but start
	 *
	 * @param string $cachedir
	 * @param string $cacheurl
	 * @param bool $minimize
	 */
	public function __construct($cachedir, $cacheurl, $minimize = true)
	{
		$this->_archive_dir = $cachedir;
		$this->_archive_url = $cacheurl;
		$this->_minify = $minimize ?? true;
		$this->fileFunc = FileFunctions::instance();
	}

	/**
	 * Combine javascript files in to a single file to save requests
	 *
	 * @param array $files array created by loadJavascriptFile() function
	 * @param bool $do_deferred true combines files with deferred tag, false combine other
	 *
	 * @return bool|string
	 */
	public function site_js_combine($files, $do_deferred)
	{
		if (!$this->_validRequest($files))
		{
			// Anything is spare
			$this->_addSpare($files);

			return false;
		}

		// Get the filenames and last modified time for this batch
		foreach ($files as $id => $file)
		{
			$load = (!$do_deferred && empty($file['options']['defer'])) || ($do_deferred && !empty($file['options']['defer']));

			// Get the ones that we would load locally, so we can merge them
			if ($load && (empty($file['options']['local']) || !$this->_addFile($file['options'])))
			{
				$this->_addSpare(array($id => $file));
			}
		}

		// Nothing to combine
		if (count($this->_combine_files) === 0)
		{
			return true;
		}

		// Create an archive name
		$this->_buildName('.js');

		// No archive file, or a stale one, creates a new compilation
		if ($this->_isStale())
		{
			// Our buddies will be needed for this to work.
			require_once(SUBSDIR . '/Package.subs.php');

			$this->_archive_header = "/*!\n * " . $this->_archive_filenames . "\n */\n";
			$this->_combineFiles('js');

			// Minify, or not, these files
			$this->_minified_cache = $this->_minify ? $this->_jsCompiler() : trim($this->_cache);

			// Combined any pre minimized + our string
			$this->_minified_cache = $this->_min_cache . "\n" . $this->_minified_cache;

			// And save them for future users
			$this->_saveFiles();
		}

		// Return the name for inclusion in the output
		return $this->_archive_url . '/' . $this->_archive_name . $this->_archive_stale;
	}

	/**
	 * Checks if directory exists/writable and we have files
	 *
	 * @param $files
	 * @return bool
	 */
	private function _validRequest($files)
	{
		// No files or missing we are done
		if (empty($files))
		{
			return false;
		}

		// Directory not writable then we are done
		if (!$this->_validDestination())
		{
			return false;
		}

		return true;
	}

	/**
	 * Tests if the destination directory exists and is writable
	 *
	 * @return bool
	 */
	protected function _validDestination()
	{
		return $this->fileFunc->isDir($this->_archive_dir) && $this->fileFunc->isWritable($this->_archive_dir);
	}

	/**
	 * Adds files to the spare list
	 *
	 * @param array
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
	 *
	 * @return bool
	 */
	private function _addFile($options)
	{
		if (!isset($options['dir']))
		{
			return false;
		}

		$filename = $options['dir'] . $options['basename'];
		if (!$this->fileFunc->fileExists($filename))
		{
			return false;
		}

		$this->_combine_files[$options['basename']] = array(
			'file' => $filename,
			'basename' => $options['basename'],
			'url' => $options['url'],
			'filemtime' => filemtime($filename),
			'minimized' => strpos($options['basename'], '.min.js') || strpos($options['basename'], '.min.css') !== false,
		);

		return true;
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
		$this->_archive_filenames = '';
		foreach ($this->_combine_files as $file)
		{
			$this->_archive_filenames .= $file['basename'] . ' ';
		}

		// Add in the actual theme url to make the sha1 unique to this hive
		$this->_archive_filenames = $settings['actual_theme_url'] . '/' . trim($this->_archive_filenames);
		$this->_archive_name = 'hive-' . sha1($this->_archive_filenames) . $type;

		// Create a cache stale for this hive ?x12345
		if (!empty($this->_combine_files))
		{
			$stale = max(array_column($this->_combine_files, 'filemtime'));
			$this->_archive_stale = '?x' . $stale;
		}
	}

	/**
	 * Determines if the existing combined file is stale
	 *
	 * - If any date of the files that make up the archive are newer than the archive, its considered stale
	 */
	private function _isStale()
	{
		// If any files in the archive are newer than the archive file itself, then the archive is stale
		$filemtime = $this->fileFunc->fileExists($this->_archive_dir . '/' . $this->_archive_name) ? filemtime($this->_archive_dir . '/' . $this->_archive_name) : 0;

		foreach ($this->_combine_files as $file)
		{
			if ($file['filemtime'] > $filemtime)
			{
				return true;
			}
		}

		return false;
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
		$this->fileFunc->delete($this->_archive_dir . '/' . $this->_archive_name);
		$this->fileFunc->delete($this->_archive_dir . '/' . $this->_archive_name . '.gz');

		$_cache = [];
		$_min_cache = [];

		// Read in all the data so we can process
		foreach ($this->_combine_files as $key => $file)
		{
			if (!$this->fileFunc->fileExists($file['file']))
			{
				continue;
			}

			$tempfile = trim(file_get_contents($file['file']));
			$tempfile = (substr($tempfile, -3) === '}()') ? $tempfile . ';' : $tempfile;
			$this->_combine_files[$key]['content'] = $tempfile;

			// CSS needs relative locations converted for the moved hive to work
			// @todo needs to be smarter, based on "new" cache location
			if ($type === 'css')
			{
				$tempfile = str_replace(array('../../images', '../../webfonts', '../../scripts'), array($file['url'] . '/images', $file['url'] . '/webfonts', $file['url'] . '/scripts'), $tempfile);
			}

			// Add the file to the correct array for processing
			if ($file['minimized'] === false)
			{
				$_cache[] = $tempfile;
			}
			else
			{
				$_min_cache[] = $tempfile;
			}
		}

		// Build out our combined file strings
		$this->_cache = implode("\n", $_cache);
		$this->_min_cache = implode("\n", $_min_cache);
		unset($_cache, $_min_cache);
	}

	/**
	 * Takes a js file and compresses it to save space
	 *
	 * What it does:
	 *
	 * - Attempt to use JSqueeze
	 * - Failing that will return original uncompressed file
	 */
	private function _jsCompiler()
	{
		// To prevent a stack overflow segmentation fault, which silently kills Apache, we need to limit
		// recursion on windows.  This may cause JSqueeze to fail, but at least its then catchable.
		if (detectServer()->is('windows'))
		{
			@ini_set('pcre.recursion_limit', '524');
		}

		require_once(EXTDIR . '/JSqueeze.php');
		$jsqueeze = new JSqueeze();
		$fetch_data = $jsqueeze->squeeze($this->_cache);

		// If we have nothing to return, use the original data
		return ($fetch_data === false || trim($fetch_data) === '') ? $this->_cache : $fetch_data;
	}

	/**
	 * Save a compilation file
	 */
	private function _saveFiles()
	{
		// Add in the file header if available
		if (!empty($this->_archive_header))
		{
			$this->_minified_cache = $this->_archive_header . $this->_minified_cache;
		}

		// Save the hive, or a nest, or a conglomeration. Like it was grown
		file_put_contents($this->_archive_dir . '/' . $this->_archive_name, $this->_minified_cache, LOCK_EX);
	}

	/**
	 * Minify individual javascript files
	 *
	 * @param array $files array created by loadJavascriptFile() function
	 *
	 * @return array
	 */
	public function site_js_minify($files)
	{
		if (!$this->_validRequest($files))
		{
			return $files;
		}

		// Build the cache filename's, check for changes, minify when requested
		require_once(SUBSDIR . '/Package.subs.php');
		foreach ($files as $id => $file)
		{
			// Clean start
			$this->_combine_files = [];
			$file['options']['minurl'] = $file['filename'];

			// Skip the ones that we would not load locally
			if (empty($file['options']['local']) || !$this->_addFile($file['options']))
			{
				continue;
			}

			// Get a file cache name and modification data
			$this->_buildName('.js');

			// Fresh version required?
			if ($this->_isStale())
			{
				$this->_combineFiles('js');

				if (!empty($this->_min_cache))
				{
					$this->_minified_cache = $this->_min_cache;
				}
				else
				{
					$this->_minified_cache = trim($this->_jsCompiler());
				}

				$this->_saveFiles();
			}

			$file['options']['minurl'] = $this->_archive_url . '/' . $this->_archive_name . $this->_archive_stale;
			$files[$id]['options'] = $file['options'];
			$files[$id]['filename'] = $file['options']['minurl'];
		}

		// Return the array for inclusion in the output
		return $files;
	}

	/**
	 * Combine css files in to a single file
	 *
	 * @param string[] $files
	 *
	 * @return bool|string
	 */
	public function site_css_combine($files)
	{
		if (!$this->_validRequest($files))
		{
			// Everything is spare
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
		{
			return true;
		}

		// Create the css archive name
		$this->_buildName('.css');

		// No file, or a stale one, so we create a new css compilation
		if ($this->_isStale())
		{
			$this->_archive_header = "/*\n *" . $this->_archive_filenames . "\n */\n";
			$this->_combineFiles('css');

			// Compress with CssMin
			$this->_minified_cache = $this->_minify ? $this->_cssCompiler() : trim($this->_cache);

			// Combine in any pre minimized css files to our string
			$this->_minified_cache .= "\n" . $this->_min_cache;

			$this->_saveFiles();
		}

		// Return the name
		return $this->_archive_url . '/' . $this->_archive_name . $this->_archive_stale;
	}

	/**
	 * Takes a css file and compresses it to save space
	 *
	 * What it does:
	 *
	 * - Attempt to use CssMin
	 * - Failing that will return original uncompressed file
	 */
	private function _cssCompiler()
	{
		// Temporary manual loading of css min files
		require_once(EXTDIR . '/CssMin/Minifier.php');
		require_once(EXTDIR . '/CssMin/Colors.php');
		require_once(EXTDIR . '/CssMin/Utils.php');
		require_once(EXTDIR . '/CssMin/Command.php');

		// CSSmin it to save some space
		return (new CSSmin())->run($this->_cache);
	}

	/**
	 * Minify individual javascript files
	 *
	 * @param array $files array created by loadJavascriptFile() function
	 *
	 * @return array
	 */
	public function site_css_minify($files)
	{
		if (!$this->_validRequest($files))
		{
			return $files;
		}

		// Build the cache filename's, check for changes, minify when needed
		foreach ($files as $id => $file)
		{
			// Clean start
			$this->_combine_files = [];
			$file['options']['minurl'] = $file['filename'];

			// Skip the ones that we would not load locally
			if (empty($file['options']['local']) || !$this->_addFile($file['options']))
			{
				continue;
			}

			// Get a file cache name and modification data
			$this->_buildName('.css');

			// Fresh version required?
			if ($this->_isStale())
			{
				$this->_combineFiles('css');
				$this->_minified_cache = trim( $this->_cssCompiler());
				$this->_saveFiles();
			}

			$file['options']['minurl'] = $this->_archive_url . '/' . $this->_archive_name . $this->_archive_stale;
			$files[$id]['options'] = $file['options'];
			$files[$id]['filename'] = $file['options']['minurl'];
		}

		// Return the array for inclusion in the output
		return $files;
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
	 * Deletes hives from the cache based on extension.
	 *
	 * @param string $ext
	 *
	 * @return bool
	 */
	protected function _removeHives($ext)
	{
		$path = $this->_archive_dir . '/hive-*.' . $ext;

		$glob = new \GlobIterator($path, \FilesystemIterator::SKIP_DOTS);
		$fileFunc = FileFunctions::instance();
		$return = true;

		foreach ($glob as $file)
		{
			$return = $return && $fileFunc->delete($file->getPathname());
		}

		return $return;
	}

	/**
	 * Deletes the JS hives from the cache.
	 */
	public function removeJsHives()
	{
		return $this->_removeHives('js');
	}
}
