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
		self::assertFileExists( $this->queryLog );
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
		try{
			$this->connection->query( "SELECT none FROM nowhere" );
		}
		catch( SqlException $e ){
		}

		self::assertFileExists( $logFile );
		@unlink( $logFile );
	}

	//  --  PROTECTED  --  //

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
