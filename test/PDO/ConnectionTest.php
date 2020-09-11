<?php
/**
 *	TestUnit of DB_PDO_Connection.
 *	@package		Tests.database.pdo
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@since			02.07.2008
 *	@version		0.1
 */
require_once 'test/initLoaders.php';
/**
 *	TestUnit of DB_PDO_Connection.
 *	@package		Tests.database.pdo
 *	@extends		Test_Case
 *	@uses			DB_PDO_Connection
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@since			02.07.2008
 *	@version		0.1
 */
class CeusMedia_Database_Test_PDO_ConnectionTest extends CeusMedia_Database_Test_Case{

	protected $directDbc;

	/**
	 *	Constructor.
	 *	@access		public
	 *	@return		void
	 */
	public function __construct(){
		parent::__construct();
		$this->host		= self::$config['unitTest-Database']['host'];
		$this->port		= self::$config['unitTest-Database']['port'];
		$this->username	= self::$config['unitTest-Database']['username'];
		$this->password	= self::$config['unitTest-Database']['password'];
		$this->database	= self::$config['unitTest-Database']['database'];
		$this->path		= dirname( __FILE__ )."/";
		$this->errorLog	= $this->path."errors.log";
		$this->queryLog	= $this->path."queries.log";
	}

	/**
	 *	Setup for every Test.
	 *	@access		public
	 *	@return		void
	 */
	public function setUp(): void
	{
		if( !extension_loaded( 'pdo_mysql' ) )
			$this->markTestSkipped( "PDO driver for MySQL not supported" );
		$dsn 		= "mysql:host=".$this->host.";dbname=".$this->database;
		$options	= array();
		$this->connection	= new \CeusMedia\Database\PDO\Connection( $dsn, $this->username, $this->password, $options );
		$this->connection->setAttribute( PDO::ATTR_CASE, PDO::CASE_NATURAL );
		$this->connection->setAttribute( PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, TRUE );
		$this->connection->setErrorLogFile( $this->errorLog );
		$this->connection->setStatementLogFile( $this->queryLog );
		if( extension_loaded( 'mysql' ) ){
			$this->directDbc	= mysql_connect( $this->host, $this->username, $this->password ) or die( mysql_error() );
			mysql_select_db( $this->database );
			$sql	= file_get_contents( $this->path."createTable.sql" );
			foreach( explode( ";", $sql ) as $part )
				if( trim( $part ) )
					mysql_query( $part ) or die( mysql_error() );
		}
		else if( extension_loaded( 'mysqli' ) ){
			$this->directDbc	= new mysqli( $this->host, $this->username, $this->password ) or die( mysqli_error() );
			mysqli_select_db( $this->directDbc, $this->database );
			$sql	= file_get_contents( $this->path."createTable.sql" );
			foreach( explode( ";", $sql ) as $part )
				if( trim( $part ) )
					mysqli_query( $this->directDbc, $part ) or die( mysqli_error() );
		}
		else{
			throw new \RuntimeException( 'No suitable MySQL connector found' );
		}
	}

	/**
	 *	Cleanup after every Test.
	 *	@access		public
	 *	@return		void
	 */
	public function tearDown(): void
	{
		@unlink( $this->errorLog );
		@unlink( $this->queryLog );
		if( extension_loaded( 'mysql' ) ){
			mysql_query( "DROP TABLE transactions" );
			mysql_close( $this->directDbc );
		}
		else if( extension_loaded( 'mysqli' ) ){
			mysqli_query( $this->directDbc, "DROP TABLE transactions" );
			mysqli_close( $this->directDbc );
		}
	}

	/**
	 *	Tests Method 'beginTransaction'.
	 *	@access		public
	 *	@return		void
	 */
	public function testBeginTransaction(){
//		$expected	= $this->connection;
		$actual		= $this->connection->beginTransaction();
		$this->assertEquals( TRUE, $actual );

		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('begin','beginTransactionTest');" );
		$this->connection->rollBack();

		$result		= $this->connection->query( "SELECT * FROM transactions" );

		$expected	= 1;
		$actual		= $result->rowCount();
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'commit'.
	 *	@access		public
	 *	@return		void
	 */
	public function testCommit(){
		$this->connection->beginTransaction();

		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('begin','beginTransactionTest');" );
		$expected	= TRUE;
		$actual		= $this->connection->commit();
		$this->assertEquals( $expected, $actual );

		$result		= $this->connection->query( "SELECT * FROM transactions" );

		$expected	= 2;
		$actual		= $result->rowCount();
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'exec'.
	 *	@access		public
	 *	@return		void
	 */
	public function testExec(){
		for( $i=0; $i<10; $i++ )
			$this->connection->query( "INSERT INTO transactions (topic, label) VALUES ('test', '".microtime()."');" );

		$expected	= 11;
		$actual		= $this->connection->exec( "UPDATE transactions SET topic='exec' WHERE topic!='exec'" );
		$this->assertEquals( $expected, $actual );

		$expected	= 0;
		$actual		= $this->connection->exec( "UPDATE transactions SET topic='exec' WHERE topic!='exec'" );
		$this->assertEquals( $expected, $actual );

		$expected	= 11;
		$actual		= $this->connection->exec( "DELETE FROM transactions WHERE topic='exec'" );
		$this->assertEquals( $expected, $actual );

		$expected	= 0;
		$actual		= $this->connection->exec( "DELETE FROM transactions WHERE topic='exec'" );
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'prepare'.
	 *	@access		public
	 *	@return		void
	 */
	public function testPrepare(){
		$statement	= $this->connection->prepare( "SELECT * FROM transactions" );

		$expected	= TRUE;
		$actual		= is_object( $statement );
		$this->assertEquals( $expected, $actual );

		$expected	= TRUE;
		$actual		= is_a( $statement, 'PDOStatement' );
		$this->assertEquals( $expected, $actual );

		$expected	= TRUE;
		$actual		= file_exists( $this->queryLog );
		$this->assertEquals( $expected, $actual );

		$expected	= 1;
		$actual		= $this->connection->numberStatements;
		$this->assertEquals( $expected, $actual );

		$statement	= $this->connection->prepare( "SELECT * FROM transactions" );

		$expected	= 2;
		$actual		= $this->connection->numberStatements;
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'query'.
	 *	@access		public
	 *	@return		void
	 */
	public function testQuery(){
		$expected	= FALSE;
		$actual		= NULL;
		try
		{
			$actual		= $this->connection->query( "SELECT none FROM nowhere" );
		}
		catch( Exception $e ){}
		$this->assertEquals( $expected, $actual );

		$result		= $this->connection->query( "SELECT * FROM transactions" );

		$expected	= TRUE;
		$actual		= is_object( $result );
		$this->assertEquals( $expected, $actual );

		$expected	= 1;
		$actual		= $result->rowCount();
		$this->assertEquals( $expected, $actual );

		$expected	= 4;
		$actual		= $result->columnCount();
		$this->assertEquals( $expected, $actual );

		$expected	= 2;
		$actual		= $this->connection->numberStatements;
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'rollBack'.
	 *	@access		public
	 *	@return		void
	 */
	public function testRollBack(){
		$this->connection->beginTransaction();
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('begin','beginTransactionTest');" );

		$expected	= TRUE;
		$actual		= $this->connection->rollBack();
		$this->assertEquals( $expected, $actual );

		$result		= $this->connection->query( "SELECT * FROM transactions" );

		$expected	= 1;
		$actual		= $result->rowCount();
		$this->assertEquals( $expected, $actual );
	}


	/**
	 *	Tests Method 'setErrorLogFile'.
	 *	@access		public
	 *	@return		void
	 */
	public function testSetErrorLogFile(){
		$logFile	= $this->path."error_log";
		$this->connection->setErrorLogFile( $logFile );
		try{
			$this->connection->query( "SELECT none FROM nowhere" );
		}catch( Exception_SQL $e ){}

		$expected	= TRUE;
		$actual		= file_exists( $logFile );
		$this->assertEquals( $expected, $actual );
		@unlink( $logFile );
	}

	/**
	 *	Tests Method 'setStatementLogFile'.
	 *	@access		public
	 *	@return		void
	 */
	public function testSetStatementLogFile(){
		$logFile	= $this->path."statement_log";
		$this->connection->setStatementLogFile( $logFile );
		try{
			$this->connection->query( "SELECT none FROM nowhere" );
		}catch( Exception_SQL $e ){}

		$expected	= TRUE;
		$actual		= file_exists( $logFile );
		$this->assertEquals( $expected, $actual );
		@unlink( $logFile );
	}
}
