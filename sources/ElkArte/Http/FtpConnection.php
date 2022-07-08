<?php

/**
 * The FtpConnection class is a Simple FTP protocol implementation.
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

namespace ElkArte\Http;

use ElkArte\FileFunctions;

/**
 * Simple FTP protocol implementation.
 *
 * http://www.faqs.org/rfcs/rfc959.html
 */
class FtpConnection
{
	/** @var resource|string Holds the connection response */
	public $connection;

	/** @var string|bool Holds any errors */
	public $error;

	/** @var string Holds last message from the server */
	public $last_message;

	/** @var array Passive connection */
	public $pasv;

	/** @var string Holds last response message from the server */
	public $last_response;

	/**
	 * Create a new FTP connection...
	 *
	 * @param string $ftp_server The server to connect to
	 * @param int $ftp_port The port to connect to
	 * @param string $ftp_user The username
	 * @param string $ftp_pass The password
	 */
	public function __construct($ftp_server, $ftp_port = 21, $ftp_user = 'anonymous', $ftp_pass = 'ftpclient@yourdomain.org')
	{
		// Initialize variables.
		$this->connection = 'no_connection';
		$this->error = false;
		$this->pasv = array();

		if ($ftp_server !== null)
		{
			$this->connect($ftp_server, $ftp_port, $ftp_user, $ftp_pass);
		}
	}

	/**
	 * Connects to a server
	 *
	 * @param string $ftp_server The server to connect to
	 * @param int $ftp_port The port to connect to
	 * @param string $ftp_user The username
	 * @param string $ftp_pass The password
	 */
	public function connect($ftp_server, $ftp_port = 21, $ftp_user = 'anonymous', $ftp_pass = 'ftpclient@yourdomain.org')
	{
		// Connect to the FTP server.
		set_error_handler(function () { /* ignore errors */ });
		$ftp_server = $this->getServer($ftp_server);
		$this->connection = stream_socket_client($ftp_server . ':' . $ftp_port, $err_code, $err, 5);
		restore_error_handler();
		if (!$this->connection || $err_code !== 0)
		{
			return $this->error = !empty($err) ? $err : 'bad_server';
		}

		// Get the welcome message...
		if (!$this->check_response(220))
		{
			$this->close();

			return $this->error = 'bad_response';
		}

		// Send the username, it should ask for a password.
		fwrite($this->connection, 'USER ' . $ftp_user . "\r\n");
		if (!$this->check_response(331))
		{
			return $this->error = 'bad_username';
		}

		// Now send the password... and hope it goes okay.
		fwrite($this->connection, 'PASS ' . $ftp_pass . "\r\n");
		if (!$this->check_response(230))
		{
			return $this->error = 'bad_password';
		}

		return true;
	}

	/**
	 * Sanitize the supplied server string
	 *
	 * @param $ftp_server
	 * @return string
	 */
	public function getServer($ftp_server)
	{
		$location = parse_url($ftp_server);
		$location['host']= $location['host'] ?? $ftp_server;
		$location['scheme']= $location['scheme'] ?? '';

		$ftp_scheme = '';
		if ($location['scheme'] === 'ftps' || $location['scheme'] === 'https')
		{
			$ftp_scheme = 'ssl://';
		}

		return $ftp_scheme . strtr($location['host'], array('/' => '', ':' => '', '@' => ''));
	}

	/**
	 * Reads the response to the command from the server
	 *
	 * @param string[]|string $desired string or array of acceptable return values
	 *
	 * @return bool
	 */
	public function check_response($desired)
	{
		$return_code = false;
		$time = time();
		while (!$return_code && time() - $time < 4)
		{
			$this->last_message = fgets($this->connection, 1024);

			// A reply will start with a 3-digit code, followed by space " ", followed by one line of text
			if (preg_match('~^(\d\d\d)\s(.+)$~m', $this->last_message, $matches) === 1)
			{
				$return_code = (int) $matches[1];
				$this->last_response = $return_code . ' :: ' . $matches[2];
			}
		}

		// Was the desired response returned?
		return is_array($desired) ? in_array($return_code, $desired) : $return_code === $desired;
	}

	/**
	 * Changes to a directory (chdir) via the ftp connection
	 *
	 * @param string $ftp_path The path to the directory
	 * @return bool
	 */
	public function chdir($ftp_path)
	{
		if (!$this->hasConnection())
		{
			return false;
		}

		// No slash on the end, please...
		if ($ftp_path !== '/' && substr($ftp_path, -1) === '/')
		{
			$ftp_path = substr($ftp_path, 0, -1);
		}

		fwrite($this->connection, 'CWD ' . $ftp_path . "\r\n");
		if (!$this->check_response(250))
		{
			$this->error = 'bad_path';

			return false;
		}

		return true;
	}

	/**
	 * Changes a files attributes (chmod)
	 *
	 * @param string $ftp_file The file to CHMOD
	 * @param int $chmod The value for the CHMOD operation
	 * @return bool If the chmod was successful or not
	 */
	public function chmod($ftp_file, $chmod)
	{
		if (!$this->hasConnection())
		{
			return false;
		}

		if (trim($ftp_file) === '')
		{
			$ftp_file = '.';
		}

		// Convert the chmod value from octal (0777) to text ("777").
		fwrite($this->connection, 'SITE CHMOD ' . decoct($chmod) . ' ' . $ftp_file . "\r\n");
		if (!$this->check_response(200))
		{
			$this->error = $this->last_response;

			return false;
		}

		return true;
	}

	/**
	 * Uses a supplied list of modes to make a file or directory writable
	 * assumes supplied name is relative from boarddir, which it should be
	 *
	 * @param string $ftp_file
	 * @param array|int $chmod
	 * @return bool
	 */
	public function ftp_chmod($ftp_file, $chmod)
	{
		$chmod = is_array($chmod) ? $chmod : (array) $chmod;

		foreach ($chmod as $permission)
		{
			if (!$this->chmod($ftp_file, $permission))
			{
				continue;
			}

			if (FileFunctions::instance()->isWritable($_SESSION['ftp_connection']['root'] . '/' . ltrim($ftp_file, '\/')))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Deletes a file
	 *
	 * @param string $ftp_file The file to delete
	 * @return bool If delete was successful or not
	 */
	public function unlink($ftp_file)
	{
		// We are actually connected, right?
		if (!$this->hasConnection())
		{
			return false;
		}

		// Delete file X.
		fwrite($this->connection, 'DELE ' . $ftp_file . "\r\n");
		if (!$this->check_response(250))
		{
			fwrite($this->connection, 'RMD ' . $ftp_file . "\r\n");

			// Still no love?
			if (!$this->check_response(250))
			{
				$this->error = 'bad_file';

				return false;
			}
		}

		return true;
	}

	/**
	 * Creates a new file on the server
	 *
	 * @param string $ftp_file The file to create
	 * @return bool If we were able to create the file
	 */
	public function create_file($ftp_file)
	{
		// First, we have to be connected... very important.
		if (!$this->hasConnection())
		{
			return false;
		}

		// I'd like one passive mode, please!
		if (!$this->passive())
		{
			return false;
		}

		// Seems logical enough, so far...
		fwrite($this->connection, 'STOR ' . $ftp_file . "\r\n");

		// Okay, now we connect to the data port.  If it doesn't work out, it's probably "file already exists", etc.
		set_error_handler(function () { /* ignore errors */ });
		$fp = stream_socket_client($this->pasv['ip'] . ':' . $this->pasv['port'], $err_code, $err, 5);
		restore_error_handler();
		if ($fp === false || $err_code !== 0 || !$this->check_response(150))
		{
			$this->error = 'bad_file';
			fclose($fp);

			return false;
		}

		// This may look strange, but we're just closing it to indicate a zero-byte upload.
		fclose($fp);
		if (!$this->check_response(226))
		{
			$this->error = 'bad_response';

			return false;
		}

		return true;
	}

	/**
	 * Used to create a passive connection
	 *
	 * @return bool If the connection was made or not
	 */
	public function passive()
	{
		// We can't create a passive data connection without a primary one first being there.
		if (!$this->hasConnection())
		{
			return false;
		}

		// Request a IPV4 passive connection - this means, we'll talk to you, you don't talk to us.
		fwrite($this->connection, 'PASV' . "\r\n");

		// If it's not 227, we weren't given an IP and port, which means it failed.
		// If it's 425, that may indicate a response to use EPSV (ipv6) which we don't support
		if (!$this->check_response(227))
		{
			$this->error = $this->last_response;

			return false;
		}

		// Snatch the IP and port information, or die horribly trying...
		if (preg_match('~\((\d+),\s*(\d+),\s*(\d+),\s*(\d+),\s*(\d+)(?:,\s*(\d+))\)~', $this->last_response, $match) == 0)
		{
			$this->error = 'bad_response';

			return false;
		}

		// This is pretty simple - store it for later use ;).
		$this->pasv = [
			'ip' => $match[1] . '.' . $match[2] . '.' . $match[3] . '.' . $match[4],
			'port' => $match[5] * 256 + $match[6]
		];

		return true;
	}

	/**
	 * Creates a new directory on the server
	 *
	 * @param string $ftp_dir The name of the directory to create
	 * @return bool If the operation was successful
	 */
	public function create_dir($ftp_dir)
	{
		// We must be connected to the server to do something.
		if (!$this->hasConnection())
		{
			return false;
		}

		// Make this new beautiful directory!
		fwrite($this->connection, 'MKD ' . $ftp_dir . "\r\n");
		if (!$this->check_response(257))
		{
			$this->error = 'bad_file';

			return false;
		}

		return true;
	}

	/**
	 * Detects the current path
	 *
	 * @param string $filesystem_path The full path from the filesystem
	 * @param string|null $lookup_file The name of a file in the specified path
	 * @return array string $username, string $path, bool found_path
	 */
	public function detect_path($filesystem_path, $lookup_file = null)
	{
		$username = '';

		if (isset($_SERVER['DOCUMENT_ROOT']))
		{
			if (preg_match('~^/home[2]?/([^/]+)/public_html~', $_SERVER['DOCUMENT_ROOT'], $match))
			{
				$username = $match[1];

				$path = strtr($_SERVER['DOCUMENT_ROOT'], array('/home/' . $match[1] . '/' => '', '/home2/' . $match[1] . '/' => ''));

				if (substr($path, -1) === '/')
				{
					$path = substr($path, 0, -1);
				}

				if (strlen(dirname($_SERVER['PHP_SELF'])) > 1)
				{
					$path .= dirname($_SERVER['PHP_SELF']);
				}
			}
			elseif (strpos($filesystem_path, '/var/www/') === 0)
			{
				$path = substr($filesystem_path, 8);
			}
			else
			{
				$path = strtr(strtr($filesystem_path, array('\\' => '/')), array($_SERVER['DOCUMENT_ROOT'] => ''));
			}
		}
		else
		{
			$path = '';
		}

		if ($this->hasConnection() && $this->list_dir($path) === '')
		{
			$data = $this->list_dir('', true);

			if ($lookup_file === null)
			{
				$lookup_file = $_SERVER['PHP_SELF'];
			}

			$found_path = dirname($this->locate('*' . basename(dirname($lookup_file)) . '/' . basename($lookup_file), $data));
			if ($found_path === '.')
			{
				$found_path = dirname($this->locate(basename($lookup_file)));
			}

			$path = $found_path;
		}
		elseif ($this->hasConnection())
		{
			$found_path = true;
		}

		return array($username, $path, isset($found_path));
	}

	/**
	 * Generates a directory listing for the current directory
	 *
	 * @param string $ftp_path The path to the directory
	 * @param string|bool $search Whether or not to get a recursive directory listing
	 * @return false|string The results of the command or false if unsuccessful
	 */
	public function list_dir($ftp_path = '', $search = false)
	{
		// Are we even connected...?
		if (!$this->hasConnection())
		{
			return false;
		}

		// Passive... non-aggressive...
		if (!$this->passive())
		{
			return false;
		}

		// Get the listing!
		fwrite($this->connection, 'LIST -1' . ($search ? 'R' : '') . ($ftp_path === '' ? '' : ' ' . $ftp_path) . "\r\n");

		// Connect, assuming we've got a connection.
		$fp = @fsockopen($this->pasv['ip'], $this->pasv['port'], $err, $err, 5);
		if (!$fp || !$this->check_response(array(150, 125)))
		{
			$this->error = 'bad_response';
			@fclose($fp);

			return false;
		}

		// Read in the file listing.
		$data = '';
		while (!feof($fp))
		{
			$data .= fread($fp, 4096);
		}
		fclose($fp);

		// Everything go okay?
		if (!$this->check_response(226))
		{
			$this->error = 'bad_response';

			return false;
		}

		return $data;
	}

	/**
	 * Determines the current directory we are in
	 *
	 * @param string $file The name of a file
	 * @param string|null $listing A directory listing or null to generate one
	 * @return string|false The name of the file or false if it wasn't found
	 */
	public function locate($file, $listing = null)
	{
		if ($listing === null)
		{
			$listing = $this->list_dir('', true);
		}
		$listing = explode("\n", $listing);

		$current_dir = '';
		fwrite($this->connection, 'PWD' . "\r\n");
		if ($this->check_response(257))
		{
			$current_dir = strtr($this->last_response, array('""' => '"'));
		}

		for ($i = 0, $n = count($listing); $i < $n; $i++)
		{
			if (trim($listing[$i]) === '' && isset($listing[$i + 1]))
			{
				$current_dir = substr(trim($listing[++$i]), 0, -1);
				$i++;
			}

			// Okay, this file's name is:
			$listing[$i] = $current_dir . '/' . trim(strlen($listing[$i]) > 30 ? strrchr($listing[$i], ' ') : $listing[$i]);

			if ($file[0] === '*' && substr($listing[$i], -(strlen($file) - 1)) === substr($file, 1))
			{
				return $listing[$i];
			}

			if (substr($file, -1) === '*' && substr($listing[$i], 0, strlen($file) - 1) === substr($file, 0, -1))
			{
				return $listing[$i];
			}

			if (basename($listing[$i]) === $file || $listing[$i] === $file)
			{
				return $listing[$i];
			}
		}

		return false;
	}

	/**
	 * Close the ftp connection
	 *
	 * @return bool
	 */
	public function close()
	{
		// Goodbye!
		if ($this->hasConnection())
		{
			fwrite($this->connection, 'QUIT' . "\r\n");
			fclose($this->connection);
		}

		return true;
	}

	/**
	 * If we are connected
	 *
	 * @return bool
	 */
	public function hasConnection()
	{
		return is_resource($this->connection);
	}
}
