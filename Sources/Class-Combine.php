<?php

/**
 * @name      Dialogo Forum
 * @copyright Dialogo Forum contributors
 *
 * @version 1.0 Alpha
 *
 */

if (!defined('DIALOGO'))
	die('Hacking attempt...');

/**
 * Used to combine css or js files in to a single file
 * Checks if the files have changed, and if so rebuilds the amalgamation
 * Calls minification classes to reduce size of css and js file saving bandwidth
 * Can creates a .gz file, be would require .htaccess or the like to use
 */
class smf_Combiner
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
	private $_cache = null;

	/**
	 * Holds the minified data of the combined files
	 *
	 * @var string
	 */
	private $_minified_cache = null;

	/**
	 * The directory where we will save the combined and packed files
	 * @todo .. yeah remove the hard-code
	 *
	 * @var string
	 */
	private $_archivedir = null;

	/**
	 * Combine javascript files in to a single file to save requests
	 *
	 * @param array $files -- array created by loadjavascriptfile function
	 * @param bool $do_defered
	 * @param bool $loadjquery
	 */
	public function smf_js_combine($files, $do_defered, &$loadjquery)
	{
		global $modSettings, $settings, $sourcedir;

		// init
		$this->_loadjquery = false;
		$this->_archivedir = $settings['actual_theme_dir'] . '/scripts/';

		// No files then we are done
		if (empty($files))
			return false;

		// get the filenames and last modified time for this batch
		foreach ($files as $id => $file)
		{
			// Get the ones that we would load locally so we can merge them
			if (!empty($file['options']['local']) && (!$do_defered && empty($file['options']['defer'])) || ($do_defered && !empty($file['options']['defer'])))
			{
				$filename = $file['options']['dir'] . $file['options']['basename'];
				$this->_combine_files[$file['options']['basename']]['file'] = $filename;
				$this->_combine_files[$file['options']['basename']]['time'] = filemtime($filename);
				$this->_combine_files[$file['options']['basename']]['basename'] = $file['options']['basename'];
			}
			// one off's get output now
			elseif ((!$do_defered && empty($file['options']['defer'])) || ($do_defered && !empty($file['options']['defer'])))
				echo '
	<script type="text/javascript" src="', $file['filename'], '" id="', $id,'"' , !empty($file['options']['async']) ? ' async="async"' : '' ,'></script>';

			// If we are loading JQuery and we are set to 'auto' load, put in our remote success or load local check
			if ($id === 'jquery' && (!isset($modSettings['jquery_source']) || $modSettings['jquery_source'] === 'auto'))
				$loadjquery = true;
		}

		// nothing to do, then we are done
		if (count($this->_combine_files) === 0)
			return;

		// create the archive name
		$this->_buildName('.js');

		// no file, or a stale one, so we create a new compilation
		if ($this->_isStale())
		{
			$this->_archive_header = '// ' . $this->_archive_filenames . "\n";
			$this->_combineFiles();

			// minify it to save some space
			require_once($sourcedir . '/lib/jsminplus.php');
			$this->_minified_cache = JSMinPlus::minify($this->_cache);

			// and save them for future users
			$this->_saveFiles();
		}

		// return the name for inclusion in the output
		return $settings['actual_theme_url'] . '/scripts/' . $this->_archive_name;
	}

	/**
	 * Combine css files in to a single file
	 *
	 * @param array $files
	 */
	public function smf_css_combine($files)
	{
		global $settings, $sourcedir;

		// init
		$this->_archivedir = $settings['actual_theme_dir'] . '/css/';

		// No files then we are done
		if (empty($files))
			return false;

		// get the filenames and last modified time for this batch
		foreach ($files as $id => $file)
		{
			// Get the ones that we would load locally so we can merge them
			if (!empty($file['options']['local']))
			{
				$filename = $file['options']['dir'] . $file['options']['basename'];
				$this->_combine_files[$file['options']['basename']]['file'] = $filename;
				$this->_combine_files[$file['options']['basename']]['time'] = filemtime($filename);
				$this->_combine_files[$file['options']['basename']]['basename'] = $file['options']['basename'];
			}
		}

		// nothing to do so return
		if (count($this->_combine_files) === 0)
			return;

		// create the css archive name
		$this->_buildName('.css');

		// no file, or a stale one, so we create a new css compilation
		if ($this->_isStale())
		{
			$this->_archive_header = '/* ' . $this->_archive_filenames . " */\n";
			$this->_combineFiles();

			// CSSmin it to save some space
			require_once($sourcedir . '/lib/cssmin.php');
			$compressor = new CSSmin($this->_cache);
			$this->_minified_cache = $compressor->run($this->_cache);

			$this->_saveFiles();
		}

		// return the name
		return $settings['actual_theme_url'] . '/css/' . $this->_archive_name;
	}

	/**
	 * Determines if the existing combined file is stale
	 * If any date of the files that make up the archive are newer than the archive, its considered stale
	 */
	private function _isStale()
	{
		// if any files in the archive are newer than the archive file itself, then the archive is stale
		$filemtime = file_exists($this->_archivedir . '/' . $this->_archive_name) ? filemtime($this->_archivedir . '/' . $this->_archive_name) : 0;

		foreach ($this->_combine_files as $file)
		{
			if (filemtime($file['file']) > $filemtime)
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
		// create this groups archive name
		foreach ($this->_combine_files as $file)
			$this->_archive_filenames .= $file['basename'] . ' ';
		$this->_archive_filenames  = trim($this->_archive_filenames);

		$this->_archive_name = 'Amalgamation-' . sha1($this->_archive_filenames) . $type;
	}

	/**
	 * Combines files into a single compliation
	 */
	private function _combineFiles()
	{
		$i = 0;

		// remove any old cache file(s)
		@unlink($this->_archivedir . '/' . $this->_archive_name);
		@unlink($this->_archivedir . '/' . $this->_archive_name . '.gz');

		// now build the new compilation
		foreach ($this->_combine_files as $file)
		{
			$this->_cache .= (($i !== 0) ? "\n" : '') . file_get_contents($file['file']);
			$i++;
		}
	}

	/**
	 * Save a compilation as both a text and optionally a compressed .gz file
	 */
	private function _saveFiles()
	{
		// Add in the file header if available
		if (!empty($this->_archive_header))
			$this->_minified_cache = $this->_archive_header . $this->_minified_cache;

		// First the plain text version
		file_put_contents($this->_archivedir . $this->_archive_name, $this->_minified_cache, LOCK_EX);

		// And now the compressed version, just uncomment the below
		/*
		$fp = gzopen($this->_archivedir . $this->_archive_name . '.gz', 'w9');
		gzwrite ($fp, $this->_minified_cache);
		gzclose($fp);
		*/
	}
}

?>