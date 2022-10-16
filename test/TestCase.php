<?php
/** @noinspection PhpComposerExtensionStubsInspection */
declare(strict_types=1);

namespace CeusMedia\DatabaseTest;

use CeusMedia\Database\PDO\Connection as PdoConnection;
use PHPUnit\Framework\TestCase as BaseTestCase;
use mysqli;
use PDO;

use RuntimeException;
use function mysqli_connect;
use function mysqli_close;
use function mysqli_error;
use function mysqli_query;
use function mysqli_select_db;

abstract class TestCase extends BaseTestCase
{
	/**	@var	array			$config			Configuration, injected by initLoaders */
	public static array $config;

	/**	@var	string			$pathLib		Injected by initLoaders */
	public static string $pathLib;

	/**	@var	mysqli|NULL		$directDbc		Direct mysqli connection as base channel for imports and testing for made changes */
	protected ?mysqli $directDbc;

	protected string $path;

	//  for mysqli and PDO connection
	protected string $host;
	protected string $port;
	protected string $username;
	protected string $password;
	protected string $database;

	//  for PDO connection
	protected PdoConnection $connection;
	protected string $dsn;
	protected string $errorLog;
	protected string $queryLog;
	protected array $options;

	protected function createTransactionsTableFromFileOnDirectConnection(): bool
	{
		if( !$this->directDbc instanceof mysqli )
			throw new RuntimeException( 'Direct mysqli channel not connected' );

		$filePath	= __DIR__.'/config/createTransactionsTable.sql';
		if( !file_exists( $filePath ) )
			throw new RuntimeException( 'SQL import script not found: '.$filePath );

		/** @var string $sql */
		$sql	= file_get_contents( $filePath );

		/** @var array $parts */
		$parts	= explode( ";", $sql );
		foreach( $parts as $part )
			if( strlen( trim( $part ) ) !== 0 ){
				if( mysqli_query( $this->directDbc, $part ) === FALSE )
					die( mysqli_error( $this->directDbc ) );
			}
		return TRUE;
	}

	protected function dropTransactionsTable(): bool
	{
		return TRUE;
		if( extension_loaded( 'mysqli' ) && $this->directDbc instanceof mysqli ) {
			/** @noinspection SqlNoDataSourceInspection */
			/** @noinspection SqlResolve */
			/** @var bool $result */
			$result	= mysqli_query( $this->directDbc, "DROP TABLE transactions" );
			return $result;
		}
		return FALSE;
	}

	protected function setUp(): void
	{
		parent::setUp();
		$this->path		= __DIR__ . "/";

		$this->host		= self::$config['unitTest-Database']['host'];
		$this->port		= self::$config['unitTest-Database']['port'];
		$this->username	= self::$config['unitTest-Database']['username'];
		$this->password	= self::$config['unitTest-Database']['password'];
		$this->database	= self::$config['unitTest-Database']['database'];

		if( !$this->setUpDirectConnection() )
			self::markTestSkipped( 'Support for MySQL is missing' );
	}

	protected function setUpPdoConnection(): void
	{
		if( !extension_loaded( 'pdo_mysql' ) )
			self::markTestSkipped( 'PDO driver for MySQL not supported' );

		$this->errorLog	= $this->path."errors.log";
		$this->queryLog	= $this->path."queries.log";
		$this->dsn		= "mysql:host=".$this->host.";dbname=".$this->database;
		$this->options	= [];

		$this->connection	= new PdoConnection( $this->dsn, $this->username, $this->password, $this->options );
		$this->connection->setAttribute( PDO::ATTR_CASE, PDO::CASE_NATURAL );
		$this->connection->setAttribute( PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, TRUE );
		$this->connection->setErrorLogFile( $this->errorLog );
		$this->connection->setStatementLogFile( $this->queryLog );
	}

	/**
	 *	Cleanup after every Test.
	 *	@access		protected
	 *	@return		void
	 */
	protected function tearDown(): void
	{
		$this->tearDownDirectConnection();
		parent::tearDown();
	}

	protected function tearDownPdoConnection(): void
	{
		@unlink( $this->errorLog );
		@unlink( $this->queryLog );
//		$this->connection->...
	}

	//  --  PRIVAT  --  //

	private function setUpDirectConnection(): bool
	{
		if( !extension_loaded( 'mysqli' ) )
			return FALSE;
		$connection = mysqli_connect( $this->host, $this->username, $this->password );
		if( ( $connection ?? FALSE ) === FALSE )
			return FALSE;

		$this->directDbc = $connection;
		return mysqli_select_db( $this->directDbc, $this->database );
	}

	private function tearDownDirectConnection(): void
	{
		if( extension_loaded( 'mysqli' ) && $this->directDbc instanceof mysqli ){
			mysqli_close( $this->directDbc );
		}
	}
}
