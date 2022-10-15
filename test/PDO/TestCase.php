<?php
/** @noinspection PhpComposerExtensionStubsInspection */
declare(strict_types=1);

namespace CeusMedia\DatabaseTest\PDO;

use CeusMedia\Database\PDO\Connection as PdoConnection;
use CeusMedia\Database\PDO\Table\Reader as PdoTableReader;
use CeusMedia\DatabaseTest\TestCase as BaseTestCase;
use mysqli;

use function mysqli_connect;
use function mysqli_close;
use function mysqli_error;
use function mysqli_query;
use function mysqli_select_db;

abstract class TestCase extends BaseTestCase
{
	/** @var mysqli $directDbc */
	protected $directDbc;

	protected string $host;
	protected string $port;
	protected string $username;
	protected string $password;
	protected string $database;
	protected string $path;
	protected string $errorLog;
	protected string $queryLog;
	protected string $dsn;
	protected array $options;
	protected PdoConnection $connection;

	protected function setUp(): void
	{
		parent::setUp();
		$this->setUpMembers();
		$this->setUpPdoConnection();
		if( !$this->setUpDirectConnection() )
			self::markTestSkipped( "Support for MySQL is missing" );
	}

	/**
	 *	Cleanup after every Test.
	 *	@access		protected
	 *	@return		void
	 */
	protected function tearDown(): void
	{
		$this->tearDownPdoConnection();
		$this->tearDownDirectConnection();
	}

	private function setUpMembers(): void
	{
		$this->host		= self::$config['unitTest-Database']['host'];
		$this->port		= self::$config['unitTest-Database']['port'];
		$this->username	= self::$config['unitTest-Database']['username'];
		$this->password	= self::$config['unitTest-Database']['password'];
		$this->database	= self::$config['unitTest-Database']['database'];
		$this->path		= __DIR__ . "/";
		$this->errorLog	= $this->path."errors.log";
		$this->queryLog	= $this->path."queries.log";
		$this->dsn		= "mysql:host=".$this->host.";dbname=".$this->database;
		$this->options	= array();
	}

	private function setUpPdoConnection()
	{
		if( !extension_loaded( 'pdo_mysql' ) )
			self::markTestSkipped( "PDO driver for MySQL not supported" );
		$this->connection	= new PdoConnection( $this->dsn, $this->username, $this->password, $this->options );
		$this->connection->setAttribute( \PDO::ATTR_CASE, \PDO::CASE_NATURAL );
		$this->connection->setAttribute( \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, TRUE );
		$this->connection->setErrorLogFile( $this->errorLog );
		$this->connection->setStatementLogFile( $this->queryLog );
	}

	private function setUpDirectConnection(): bool
	{
		if( !extension_loaded( 'mysqli' ) )
			return FALSE;
		$connection	= mysqli_connect( $this->host, $this->username, $this->password );
		if( ( $connection ?? FALSE ) === FALSE )
			return FALSE;

		$this->directDbc	= $connection;
		mysqli_select_db( $this->directDbc, $this->database );

		/** @var string $sql */
		$sql	= file_get_contents( $this->path."createTable.sql" );

		/** @var array $parts */
		$parts	= explode( ";", $sql );
		foreach( $parts as $part )
			if( strlen( trim( $part ) ) !== 0 ){
				if( mysqli_query( $this->directDbc, $part ) === FALSE )
					die( mysqli_error( $this->directDbc ) );
			}
		return TRUE;
	}

	private function tearDownPdoConnection(): void
	{
		@unlink( $this->errorLog );
		@unlink( $this->queryLog );
//		$this->connection->...
	}

	private function tearDownDirectConnection(): void
	{
		if( extension_loaded( 'mysqli' ) ){
			mysqli_query( $this->directDbc, "DROP TABLE transactions" );
			mysqli_close( $this->directDbc );
		}
	}
}
