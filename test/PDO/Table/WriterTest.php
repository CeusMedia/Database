<?php
/**
 *	TestUnit of DB_PDO_TableWriter.
 *	@package		Tests.{classPackage}
 *	@author			Christian WÃ¼rker <christian.wuerker@ceusmedia.de>
 *	@since			02.05.2008
 *	@version		0.1
 */
require_once 'test/initLoaders.php';
/**
 *	TestUnit of DB_PDO_TableWriter.
 *	@package		Tests.{classPackage}
 *	@extends		Test_Case
 *	@uses			DB_PDO_Connection
 *	@uses			DB_PDO_TableWriter
 *	@author			Christian WÃ¼rker <christian.wuerker@ceusmedia.de>
 *	@since			02.05.2008
 *	@version		0.1
 */
class CeusMedia_Database_Test_PDO_Table_WriterTest extends CeusMedia_Database_Test_Case{

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
		$this->path		= dirname( dirname( __FILE__ ) )."/";
		$this->errorLog	= $this->path."errors.log";
		$this->queryLog	= $this->path."queries.log";

		$this->dsn		= "mysql:host=".$this->host.";dbname=".$this->database;
		$this->options	= array();

		$this->tableName	= "transactions";
		$this->columns		= array(
			'id',
			'topic',
			'label',
			'timestamp',
		);
		$this->primaryKey	= $this->columns[0];
		$this->indices	= array(
			'topic',
			'label'
		);
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

		$this->connection	= new \CeusMedia\Database\PDO\Connection( $this->dsn, $this->username, $this->password, $this->options );
		$this->connection->setAttribute( \PDO::ATTR_CASE, \PDO::CASE_NATURAL );
		$this->connection->setAttribute( \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, TRUE );
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
			$this->markTestSkipped( "Support for MySQL is missing" );
		}

		$this->writer	= new \CeusMedia\Database\PDO\Table\Writer( $this->connection, $this->tableName, $this->columns, $this->primaryKey );
		$this->writer->setIndices( $this->indices );
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
			mysql_close();
		}
		else if( extension_loaded( 'mysqli' ) ){
			mysqli_query( $this->directDbc, "DROP TABLE transactions" );
			mysqli_close( $this->directDbc );
		}
	}

	/**
	 *	Tests Method 'delete'.
	 *	@access		public
	 *	@return		void
	 */
	public function testDelete(){
		$this->connection->query( "INSERT INTO transactions (topic, label) VALUES ('test', 'deleteTest');" );
		$this->connection->query( "INSERT INTO transactions (topic, label) VALUES ('test', 'deleteTest');" );
		$this->connection->query( "INSERT INTO transactions (topic, label) VALUES ('test', 'deleteTest');" );

		$assertion	= 4;
		$creation	= $this->writer->count();
		$this->assertEquals( $assertion, $creation );

		$this->writer->focusPrimary( 4 );
		$assertion	= 1;
		$creation	= $this->writer->delete();
		$this->assertEquals( $assertion, $creation );

		$this->writer->defocus();
		$assertion	= 3;
		$creation	= $this->writer->count();
		$this->assertEquals( $assertion, $creation );

		$assertion	= 2;
		$creation	= count( $this->writer->find( array(), array( 'label' => 'deleteTest' ) ) );
		$this->assertEquals( $assertion, $creation );

		$this->writer->focusIndex( 'label', 'deleteTest' );
		$assertion	= 2;
		$creation	= $this->writer->delete();
		$this->assertEquals( $assertion, $creation );

		$this->writer->defocus();
		$assertion	= 1;
		$creation	= $this->writer->count();
		$this->assertEquals( $assertion, $creation );

		$this->writer->defocus();
		$this->writer->focusPrimary( 999999 );
		$assertion	= 0;
		$creation	= $this->writer->delete();
		$this->assertEquals( $assertion, $creation );
	}

	/**
	 *	Tests Exception of Method 'delete'.
	 *	@access		public
	 *	@return		void
	 */
	public function testDeleteException1(){
		$this->expectException( 'RuntimeException' );
		$this->writer->delete();
	}

	/**
	 *	Tests Method 'deleteByConditions'.
	 *	@access		public
	 *	@return		void
	 */
	public function testDeleteByConditions(){
		$this->connection->query( "INSERT INTO transactions (topic, label) VALUES ('test', 'deleteTest');" );
		$this->connection->query( "INSERT INTO transactions (topic, label) VALUES ('test', 'deleteTest');" );
		$this->connection->query( "INSERT INTO transactions (topic, label) VALUES ('test', 'deleteTest');" );

		$assertion	= 4;
		$creation	= $this->writer->count();
		$this->assertEquals( $assertion, $creation );

		$assertion	= 3;
		$creation	= $this->writer->deleteByConditions( array( 'label' => 'deleteTest' ) );
		$this->assertEquals( $assertion, $creation );

		$assertion	= 1;
		$creation	= $this->writer->count();
		$this->assertEquals( $assertion, $creation );
	}

	/**
	 *	Tests Method 'insert'.
	 *	@access		public
	 *	@return		void
	 */
	public function testInsert(){
		$data	= array(
			'topic'	=> 'insert',
			'label'	=> 'insertTest',
		);

		$assertion	= 2;
		$creation	= $this->writer->insert( $data );
		$this->assertEquals( $assertion, $creation );

		$assertion	= 2;
		$creation	= $this->writer->count();
		$this->assertEquals( $assertion, $creation );

		$this->writer->focusPrimary( 2 );
		$assertion	= $data;
		$creation	= array_slice( $this->writer->get( TRUE ), 1, 2 );
		$this->assertEquals( $assertion, $creation );

		$this->writer->focusIndex( 'topic', 'insert' );
		$assertion	= 3;
		$creation	= $this->writer->insert( array( 'label' => 'insertTest2' ) );
		$this->assertEquals( $assertion, $creation );

		$this->writer->defocus();
		$assertion	= 3;
		$creation	= $this->writer->count();
		$this->assertEquals( $assertion, $creation );

		$results	= $this->writer->find( array( 'label' ) );
		$assertion	= array( 'label' => 'insertTest2' );
		$creation	= array_pop( $results );
		$this->assertEquals( $assertion, $creation );
	}

	/**
	 *	Tests Method 'update'.
	 *	@access		public
	 *	@return		void
	 */
	public function testUpdatePrimary(){
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('update','updateTest1');" );
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('update','updateTest2');" );
		$this->writer->focusPrimary( 2 );

		$data		= array(
			'label'	=> "updateTest1-changed"
		);
		$assertion	= 1;
		$creation	= $this->writer->update( $data );
		$this->assertEquals( $assertion, $creation );

		$assertion	= array( 'label' => "updateTest1-changed" );
		$creation	= $this->writer->find( array( 'label' ), array( 'id' => 2 ) );
		$this->assertEquals( $assertion, array_pop( $creation ) );
	}

	/**
	 *	Tests Method 'update'.
	 *	@access		public
	 *	@return		void
	 */
	public function testUpdateIndex(){
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('update','updateTest1');" );
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('update','updateTest2');" );
		$this->writer->focusIndex( 'topic', 'update' );

		$data		= array(
			'label'	=> "changed"
		);
		$assertion	= 2;
		$creation	= $this->writer->update( $data );
		$this->assertEquals( $assertion, $creation );

		$this->writer->focusIndex( 'label', 'changed' );
		$assertion	= 2;
		$creation	= count( $this->writer->get( FALSE ) );
		$this->assertEquals( $assertion, $creation );
	}

	/**
	 *	Tests Exception of Method 'update'.
	 *	@access		public
	 *	@return		void
	 */
	public function testUpdateException1(){
		$this->expectException( 'InvalidArgumentException' );
		$this->writer->updateByConditions( array() );
	}

	/**
	 *	Tests Exception of Method 'update'.
	 *	@access		public
	 *	@return		void
	 */
	public function testUpdateException2(){
		$this->expectException( 'InvalidArgumentException' );
		$this->writer->focusPrimary( 9999 );
		$this->writer->update( array( 'label' => 'not_relevant' ));
	}

	/**
	 *	Tests Method 'updateByConditions'.
	 *	@access		public
	 *	@return		void
	 */
	public function testUpdateByConditions(){
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('update','updateTest1');" );
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('update','updateTest2');" );

		$conditions	= array(
			'label' => "updateTest1"
		);
		$data		= array(
			'label'	=> "updateTest1-changed"
		);

		$assertion	= 0;
		$wrongData	= array( 'invalid_column' => 'not_important' );
		$creation	= $this->writer->updateByConditions( $wrongData, $conditions );
		$this->assertEquals( $assertion, $creation );

		$assertion	= 1;
		$creation	= $this->writer->updateByConditions( $data, $conditions );
		$this->assertEquals( $assertion, $creation );

		$assertion	= array( 'label' => "updateTest1-changed" );
		$creation	= $this->writer->find( array( 'label' ), array( 'id' => 2 ) );
		$this->assertEquals( $assertion, array_pop( $creation ) );

		$conditions	= array(
			'topic' => "update"
		);
		$data		= array(
			'label'	=> "changed"
		);

		$assertion	= 2;
		$creation	= $this->writer->updateByConditions( $data, $conditions );
		$this->assertEquals( $assertion, $creation );

		$this->writer->focusIndex( 'label', 'changed' );
		$assertion	= 2;
		$creation	= count( $this->writer->get( FALSE ) );
		$this->assertEquals( $assertion, $creation );
	}

	/**
	 *	Tests Exception of Method 'updateByConditions'.
	 *	@access		public
	 *	@return		void
	 */
	public function testUpdateByConditionsException1(){
		$this->expectException( 'InvalidArgumentException' );
		$this->writer->updateByConditions( array(), array( 'label' => 'not_relevant' ) );
	}

	/**
	 *	Tests Exception of Method 'updateByConditions'.
	 *	@access		public
	 *	@return		void
	 */
	public function testUpdateByConditionsException2(){
		$this->expectException( 'InvalidArgumentException' );
		$this->writer->updateByConditions( array( 'label' => 'not_relevant' ), array() );
	}

	/**
	 *	Tests Method 'truncate'.
	 *	@access		public
	 *	@return		void
	 */
	public function testTruncate(){
		$this->connection->query( "INSERT INTO transactions (topic, label) VALUES ('test', 'truncateTest');" );

		$assertion	= 2;
		$creation	= $this->writer->count();
		$this->assertEquals( $assertion, $creation );

		$assertion	= $this->writer;
		$creation	= $this->writer->truncate();
		$this->assertEquals( $assertion, $creation );

		$assertion	= 0;
		$creation	= $this->writer->count();
		$this->assertEquals( $assertion, $creation );
	}
}
?>
