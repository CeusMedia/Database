<?php /** @noinspection ALL */
/** @noinspection SqlResolve */
/** @noinspection SqlNoDataSourceInspection */

/**
 *	TestUnit of DB_PDO_Connection.
 *	@package		Tests.database.pdo
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 */

namespace CeusMedia\DatabaseTest\PDO;

use CeusMedia\Common\Exception\SQL as SqlException;
use CeusMedia\Database\PDO\Connection\Base;
use Exception;
use PDOStatement;

/**
 *	TestUnit of DB_PDO_Connection.
 *	@package		Tests.database.pdo
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 */
class ConnectionTest extends TestCase
{
	protected array $columns;
	protected string $tableName;
	protected array $indices;
	protected string $primaryKey;

	/**
	 *	Tests Method 'beginTransaction'.
	 *	@access		public
	 *	@return		void
	 */
	public function testBeginTransaction()
	{
		self::assertTrue( $this->connection->beginTransaction() );

		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('begin','beginTransactionTest');" );
		$this->connection->rollBack();

		$result		= $this->connection->query( "SELECT * FROM transactions" );
		self::assertInstanceOf( 'PDOStatement', $result );
		/** @var PDOStatement $result */
		self::assertEquals( 1, $result->rowCount() );
	}

	/**
	 *	Tests Method 'commit'.
	 *	@access		public
	 *	@return		void
	 */
	public function testCommit()
	{
		$this->connection->beginTransaction();

		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('begin','beginTransactionTest');" );
		self::assertTrue( $this->connection->commit() );

		$result		= $this->connection->query( "SELECT * FROM transactions" );
		self::assertInstanceOf( 'PDOStatement', $result );
		/** @var PDOStatement $result */
		$actual		= $result->rowCount();
		self::assertEquals( 2, $actual );
	}

	/**
	 *	Tests Method 'exec'.
	 *	@access		public
	 *	@return		void
	 */
	public function testExec()
	{
		for( $i=0; $i<10; $i++ )
			$this->connection->query( "INSERT INTO transactions (topic, label) VALUES ('test', '".microtime()."');" );

		$expected	= 11;
		$actual		= $this->connection->exec( "UPDATE transactions SET topic='exec' WHERE topic!='exec'" );
		self::assertEquals( $expected, $actual );

		$expected	= 0;
		$actual		= $this->connection->exec( "UPDATE transactions SET topic='exec' WHERE topic!='exec'" );
		self::assertEquals( $expected, $actual );

		$expected	= 11;
		$actual		= $this->connection->exec( "DELETE FROM transactions WHERE topic='exec'" );
		self::assertEquals( $expected, $actual );

		$expected	= 0;
		$actual		= $this->connection->exec( "DELETE FROM transactions WHERE topic='exec'" );
		self::assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'prepare'.
	 *	@access		public
	 *	@return		void
	 */
	public function testPrepare()
	{
		$statement	= $this->connection->prepare( "SELECT * FROM transactions" );
		self::assertIsObject( $statement );
		self::assertInstanceOf( 'PDOStatement', $statement );
		self::assertFileDoesNotExist( $this->queryLog );
		self::assertEquals( 1, $this->connection->numberStatements );

		$this->connection->prepare( "SELECT * FROM transactions" );
		self::assertEquals( 2, $this->connection->numberStatements );
	}

	/**
	 *	Tests Method 'query'.
	 *	@access		public
	 *	@return		void
	 */
	public function testQuery()
	{
		try{
			$actual		= $this->connection->query( "SELECT none FROM nowhere" );
		}
		catch( Exception $e ){
			$actual		= FALSE;
		}
		self::assertFalse( $actual );

		$result		= $this->connection->query( "SELECT * FROM transactions" );
		self::assertIsObject( $result );
		self::assertInstanceOf( 'PDOStatement', $result );
		/** @var PDOStatement $result */
		self::assertEquals( 1, $result->rowCount() );
		self::assertEquals( 4, $result->columnCount() );
		self::assertEquals( 2, $this->connection->numberStatements );
	}

	/**
	 *	Tests Method 'rollBack'.
	 *	@access		public
	 *	@return		void
	 */
	public function testRollBack()
	{
		$this->connection->beginTransaction();
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('begin','beginTransactionTest');" );

		$actual		= $this->connection->rollBack();
		self::assertTrue( $actual );

		$result		= $this->connection->query( "SELECT * FROM transactions" );
		self::assertInstanceOf( 'PDOStatement', $result );
		/** @var PDOStatement $result */
		self::assertEquals( 1, $result->rowCount() );
	}


	/**
	 *	Tests Method 'setErrorLogFile'.
	 *	@access		public
	 *	@return		void
	 */
	public function testSetErrorLogFile()
	{
		$logFile	= $this->path."error_log";
		$this->connection->setErrorLogFile( $logFile );
		try{
			$this->connection->query( "SELECT none FROM nowhere" );
		}catch( SqlException $e ){}

		self::assertFileExists( $logFile );
		@unlink( $logFile );
	}

	/**
	 *	Tests Method 'setStatementLogFile'.
	 *	@access		public
	 *	@return		void
	 */
	public function testSetStatementLogFile()
	{
		$logFile	= $this->path."statement_log";
		$this->connection->setStatementLogFile( $logFile );
		$this->connection->setLogLevel( Base::LOG_LEVEL_ERROR | Base::LOG_LEVEL_STATEMENT );
		try{
			$this->connection->query( "SELECT none FROM nowhere" );
		}
		catch( SqlException $e ){
		}

		self::assertFileExists( $logFile );
		@unlink( $logFile );
	}

	//  --  PROTECTED  --  //

	public function testCommitAndRollBackWithoutOpenTransaction(): void
	{
		self::assertFalse( $this->connection->commit() );
		self::assertFalse( $this->connection->rollBack() );
	}

	/**
	 *	Nested transactions: only the outer one really commits; a failed inner one rolls back
	 *	everything on outer commit and makes the failure visible.
	 *	@access		public
	 *	@return		void
	 */
	public function testNestedTransactions(): void
	{
		$this->connection->beginTransaction();
		$this->connection->beginTransaction();
		self::assertSame( 2, $this->connection->getOpenTransactions() );
		$this->connection->exec( "INSERT INTO transactions (topic,label) VALUES ('nested','committed')" );
		self::assertTrue( $this->connection->commit() );
		self::assertSame( 1, $this->connection->getOpenTransactions() );
		self::assertTrue( $this->connection->commit() );
		self::assertSame( 0, $this->connection->getOpenTransactions() );
		self::assertSame( 2, $this->countTransactions() );
	}

	public function testNestedTransactionsWithFailedInnerTransaction(): void
	{
		$this->connection->beginTransaction();
		$this->connection->exec( "INSERT INTO transactions (topic,label) VALUES ('outer','rolled back')" );
		$this->connection->beginTransaction();
		self::assertTrue( $this->connection->rollBack(), 'inner roll back is noted' );
		self::assertSame( 1, $this->connection->getOpenTransactions() );
		try{
			$this->connection->commit();
			self::fail( 'Expected RuntimeException on outer commit' );
		}
		catch( \RuntimeException $e ){
			self::assertStringContainsString( 'nested transaction', $e->getMessage() );
		}
		self::assertSame( 0, $this->connection->getOpenTransactions() );
		self::assertSame( 1, $this->countTransactions(), 'outer transaction rolled back' );

		//  failure is forgotten afterward
		$this->connection->beginTransaction();
		self::assertTrue( $this->connection->commit() );
	}

	public function testExecThrowsSqlExceptionAndLogsError(): void
	{
		$logFile	= $this->path.'error_exec_log';
		@unlink( $logFile );
		$this->connection->setErrorLogFile( $logFile );
		try{
			$this->connection->exec( "DELETE FROM nowhere" );
			self::fail( 'Expected SqlException' );
		}
		catch( SqlException ){}
		self::assertFileExists( $logFile );
		self::assertStringContainsString( 'DELETE FROM nowhere', (string) file_get_contents( $logFile ) );
		@unlink( $logFile );
	}

	/**
	 *	Errors are still thrown with log level NONE, but not logged.
	 *	@access		public
	 *	@return		void
	 */
	public function testLogLevelNoneDisablesErrorLog(): void
	{
		$logFile	= $this->path.'error_none_log';
		@unlink( $logFile );
		$this->connection->setErrorLogFile( $logFile );
		self::assertSame( $this->connection, $this->connection->setLogLevel( Base::LOG_LEVEL_NONE ) );
		try{
			$this->connection->query( "SELECT none FROM nowhere" );
			self::fail( 'Expected SqlException' );
		}
		catch( SqlException ){}
		self::assertFileDoesNotExist( $logFile );
	}

	/**
	 *	A log level for the next statement applies once, for query() and exec().
	 *	@access		public
	 *	@return		void
	 */
	public function testLogLevelForNextStatement(): void
	{
		$logFile	= $this->path.'statement_next_log';
		@unlink( $logFile );
		$this->connection->setStatementLogFile( $logFile );

		$this->connection->query( "SELECT 'not logged'" );
		self::assertFileDoesNotExist( $logFile, 'statements are not logged by default' );

		self::assertSame( $this->connection, $this->connection->setLogLevelForNextStatement( Base::LOG_LEVEL_STATEMENT ) );
		$this->connection->query( "SELECT 'logged by query'" );
		$this->connection->query( "SELECT 'not logged again'" );

		$this->connection->setLogLevelForNextStatement( Base::LOG_LEVEL_STATEMENT );
		$this->connection->exec( "SET @x = 'logged by exec'" );
		$this->connection->exec( "SET @x = 'not logged again'" );

		$log	= (string) file_get_contents( $logFile );
		self::assertStringContainsString( 'logged by query', $log );
		self::assertStringContainsString( 'logged by exec', $log );
		self::assertStringNotContainsString( 'not logged', $log );
		@unlink( $logFile );
	}

	/**
	 *	A one-time log level is reset even if the statement fails.
	 *	@access		public
	 *	@return		void
	 */
	public function testLogLevelForNextStatementIsResetOnFailure(): void
	{
		$logFile	= $this->path.'error_next_log';
		@unlink( $logFile );
		$this->connection->setErrorLogFile( $logFile );

		$this->connection->setLogLevelForNextStatement( Base::LOG_LEVEL_NONE );
		try{
			$this->connection->exec( "DELETE FROM nowhere" );
		}
		catch( SqlException ){}
		self::assertFileDoesNotExist( $logFile );

		try{
			$this->connection->exec( "DELETE FROM nowhere" );
		}
		catch( SqlException ){}
		self::assertFileExists( $logFile, 'default log level applies again' );
		@unlink( $logFile );
	}

	public function testLogFilesCreateMissingFolder(): void
	{
		$folder	= $this->path.'logs_'.uniqid();
		$this->connection->setErrorLogFile( $folder.'/errors/error.log' );
		$this->connection->setStatementLogFile( $folder.'/statements/statement.log' );
		self::assertDirectoryExists( $folder.'/errors' );
		self::assertDirectoryExists( $folder.'/statements' );
		rmdir( $folder.'/errors' );
		rmdir( $folder.'/statements' );
		rmdir( $folder );
	}

	public function testQueryWithFetchMode(): void
	{
		$result	= $this->connection->query( "SELECT topic FROM transactions", \PDO::FETCH_COLUMN, 0 );
		self::assertInstanceOf( PDOStatement::class, $result );
		self::assertSame( ['start'], $result->fetchAll() );
		self::assertSame( "SELECT topic FROM transactions", $this->connection->lastQuery );
	}

	public function testGetDriver(): void
	{
		self::assertSame( 'mysql', $this->connection->getDriver() );
	}

	public function testGetTables(): void
	{
		$tables	= $this->connection->getTables();
		self::assertContains( 'transactions', $tables );
		self::assertContains( 'transactions2', $tables );

		self::assertSame( $tables, $this->connection->getTables( '' ), 'empty prefix means no prefix' );

		$tables	= $this->connection->getTables( 'transactions2' );
		self::assertSame( ['transactions2'], $tables );
		self::assertSame( [], $this->connection->getTables( 'not_existing_prefix_' ) );
	}

	public function testGetTablesEscapesPrefix(): void
	{
		self::assertSame( [], $this->connection->getTables( "x' OR '1'='1" ) );
	}

	protected function countTransactions(): int
	{
		/** @var PDOStatement $result */
		$result	= $this->connection->query( "SELECT COUNT(*) FROM transactions" );
		return (int) $result->fetchColumn();
	}

	/**
	 *	Without exception error mode, failing queries return FALSE (or an empty list).
	 *	@access		public
	 *	@return		void
	 */
	public function testQueryAndGetTablesWithSilentErrorMode(): void
	{
		$connection	= new \CeusMedia\Database\PDO\Connection( $this->dsn, $this->username, $this->password, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_SILENT] );
		self::assertFalse( $connection->query( "SELECT none FROM nowhere" ) );
		self::assertSame( 0, $connection->exec( "DELETE FROM nowhere" ) );
	}

	/**
	 *	Setup for every Test.
	 *	@access		protected
	 *	@return		void
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->createTransactionsTableFromFileOnDirectConnection();
	}

	/**
	 *	Cleanup after every Test.
	 *	@access		protected
	 *	@return		void
	 */
	protected function tearDown(): void
	{
		$this->dropTransactionsTable();
		parent::tearDown();
	}
}
