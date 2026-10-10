<?php

/**
 * This file has all the main functions in it that set up the database connection
 * and initializes the appropriate adapters.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\Database;

use ElkArte\Database\Mysqli\Connection;
use ElkArte\Errors\Errors;
use Exception;
use Throwable;

/**
 * Factory class to manage database instances and adapter utilities.
 *
 * @package ElkArte\Database
 */
class DatabaseConnectionFactory
{
	/** @var QueryInterface|null Holds the current instance of the db class */
	private ?QueryInterface $dbInstance = null;

	/** @var AbstractTable|null Holds the current instance of the table class */
	private ?AbstractTable $tableInstance = null;

	/** @var AbstractSearch|null Holds the current instance of the search class */
	private ?AbstractSearch $searchInstance = null;

	/** @var AbstractDump|null Holds the current instance of the dump class */
	private ?AbstractDump $dumpInstance = null;

	/** @var array Holds the database connection options */
	private array $dbOptions;

	/** @var string Holds the database type */
	private string $dbType;

	/**
	 * DatabaseConnectionFactory constructor.
	 *
	 * @param array $dbOptions The database connection options
	 * @param string $dbType The database type (e.g., 'mysqli', 'postgresql', etc.)
	 */
	public function __construct(array $dbOptions = [], string $dbType = 'mysqli')
	{
		$this->dbOptions = $dbOptions;
		$this->dbType = $dbType;
	}

	/**
	 * Resolve the database type to the appropriate adapter name.
	 *
	 * @param string $type
	 *
	 * @return string
	 */
	public static function resolveType(string $type): string
	{
		$type = strtolower($type);

		return $type === 'mysql' ? 'mysqli' : $type;
	}

	/**
	 * Resolve the class name for a database adapter (e.g., Connection, Table, Search, Dump).
	 *
	 * @param string $adapter
	 *
	 * @return string
	 */
	public function resolveClass(string $adapter): string
	{
		$type = self::resolveType($this->dbType);

		return '\\ElkArte\\Database\\' . ucfirst($type) . '\\' . ucfirst($adapter);
	}

	/**
	 * Retrieve a specific option with fallback key support.
	 *
	 * @param string|array $keys
	 * @param mixed $default
	 *
	 * @return mixed
	 */
	private function getOption(string|array $keys, mixed $default = null): mixed
	{
		if (is_string($keys))
		{
			$keys = [$keys];
		}

		foreach ($keys as $key)
		{
			if (isset($this->dbOptions[$key]))
			{
				return $this->dbOptions[$key];
			}
		}

		return $default;
	}

	/**
	 * Get the current database instance, creating it if it doesn't exist.
	 *
	 * @param bool $fatal Stop the execution or throw an \Exception
	 *
	 * @return QueryInterface
	 * @throws Exception if fatal is false and connection fails
	 */
	public function getDatabase(bool $fatal = true): QueryInterface
	{
		if ($this->dbInstance === null)
		{
			$this->dbInstance = $this->createDatabaseInstance($fatal);
		}

		return $this->dbInstance;
	}

	/**
	 * Create a new database instance based on the provided options and type.
	 *
	 * @param bool $fatal Stop the execution or throw an \Exception
	 *
	 * @return QueryInterface
	 * @throws Exception if fatal is false and connection fails
	 */
	public function createDatabaseInstance(bool $fatal = true): QueryInterface
	{
		/** @var Connection $class */
		$class = $this->resolveClass('Connection');

		$server = (string) $this->getOption(['server', 'db_server'], '');
		$name = (string) $this->getOption(['name', 'db_name'], '');
		$user = (string) $this->getOption(['user', 'db_user'], '');
		$password = (string) $this->getOption(['password', 'passwd', 'db_passwd', 'pass'], '');
		$prefix = (string) $this->getOption(['prefix', 'db_prefix'], '');

		$options = $this->dbOptions;
		if (!isset($options['select_db']))
		{
			$options['select_db'] = true;
		}

		try
		{
			return $class::initiate($server, $name, $user, $password, $prefix, $options);
		}
		catch (Throwable $e)
		{
			if ($fatal === true)
			{
				Errors::instance()->display_db_error($e->getMessage());
			}

			throw $e;
		}
	}

	/**
	 * Get an AbstractTable instance.
	 *
	 * @param QueryInterface|null $db Database object
	 * @param string|null $prefix Database prefix
	 * @param bool $fatal Stop execution or throw an \Exception
	 *
	 * @return AbstractTable
	 * @throws Exception if fatal is false and instantiation fails
	 */
	public function getTable(?QueryInterface $db = null, ?string $prefix = null, bool $fatal = false): AbstractTable
	{
		if ($this->tableInstance === null || $db !== null || $prefix !== null)
		{
			$dbInstance = $db ?? $this->getDatabase($fatal);
			$prefixStr = $prefix ?? (string) $this->getOption(['prefix', 'db_prefix'], '');
			$instance = $this->createTable($dbInstance, $prefixStr, $fatal);

			if ($db === null && $prefix === null)
			{
				$this->tableInstance = $instance;
			}

			return $instance;
		}

		return $this->tableInstance;
	}

	/**
	 * Create an AbstractTable instance.
	 *
	 * @param QueryInterface $db Database object
	 * @param string $prefix Database prefix
	 * @param bool $fatal Stop execution or throw an \Exception
	 *
	 * @return AbstractTable
	 * @throws Exception if fatal is false and instantiation fails
	 */
	public function createTable(QueryInterface $db, string $prefix, bool $fatal = false): AbstractTable
	{
		$class = $this->resolveClass('Table');

		try
		{
			return new $class($db, $prefix);
		}
		catch (Throwable $e)
		{
			if ($fatal === true)
			{
				Errors::instance()->display_db_error($e->getMessage());
			}

			throw $e;
		}
	}

	/**
	 * Get an AbstractSearch instance.
	 *
	 * @param QueryInterface|null $db Database object
	 * @param bool $fatal Stop execution or throw an \Exception
	 *
	 * @return AbstractSearch
	 * @throws Throwable if fatal is false and instantiation fails
	 */
	public function getSearch(?QueryInterface $db = null, bool $fatal = true): AbstractSearch
	{
		if ($this->searchInstance === null || $db !== null)
		{
			$dbInstance = $db ?? $this->getDatabase($fatal);
			$instance = $this->createSearch($dbInstance, $fatal);

			if ($db === null)
			{
				$this->searchInstance = $instance;
			}

			return $instance;
		}

		return $this->searchInstance;
	}

	/**
	 * Create an AbstractSearch instance.
	 *
	 * @param QueryInterface $db Database object
	 * @param bool $fatal Stop execution or throw an \Exception
	 *
	 * @return AbstractSearch
	 * @throws Throwable if fatal is false and instantiation fails
	 */
	public function createSearch(QueryInterface $db, bool $fatal = true): AbstractSearch
	{
		$class = $this->resolveClass('Search');

		try
		{
			return new $class($db);
		}
		catch (Throwable $e)
		{
			if ($fatal === true)
			{
				Errors::instance()->display_db_error($e->getMessage());
			}

			throw $e;
		}
	}

	/**
	 * Get an AbstractDump instance.
	 *
	 * @param QueryInterface|null $db Database object
	 * @param AbstractTable|null $dbTable Table object
	 * @param bool $fatal Stop execution or throw an \Exception
	 *
	 * @return AbstractDump
	 * @throws Throwable if fatal is false and instantiation fails
	 */
	public function getDump(?QueryInterface $db = null, ?AbstractTable $dbTable = null, bool $fatal = true): AbstractDump
	{
		if ($this->dumpInstance === null || $db !== null || $dbTable !== null)
		{
			$dbInstance = $db ?? $this->getDatabase($fatal);
			$tableInstance = $dbTable ?? $this->getTable($dbInstance, null, $fatal);
			$instance = $this->createDump($dbInstance, $tableInstance, $fatal);

			if ($db === null && $dbTable === null)
			{
				$this->dumpInstance = $instance;
			}

			return $instance;
		}

		return $this->dumpInstance;
	}

	/**
	 * Create an AbstractDump instance.
	 *
	 * @param QueryInterface $db Database object
	 * @param AbstractTable|null $dbTable Table object
	 * @param bool $fatal Stop execution or throw an \Exception
	 *
	 * @return AbstractDump
	 * @throws Throwable if fatal is false and instantiation fails
	 */
	public function createDump(QueryInterface $db, ?AbstractTable $dbTable = null, bool $fatal = true): AbstractDump
	{
		$class = $this->resolveClass('Dump');

		try
		{
			return new $class($db, $dbTable);
		}
		catch (Throwable $e)
		{
			if ($fatal === true)
			{
				Errors::instance()->display_db_error($e->getMessage());
			}

			throw $e;
		}
	}
}
