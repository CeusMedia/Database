<?php
/**
 *	TestUnit of PDO Table Reader.
 *	@package		Tests.database.pdo
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@since			02.07.2008
 *	@version		0.1
 */
require_once 'test/initLoaders.php';
/**
 *	TestUnit of PDO Table Reader.
 *	@package		Tests.database.pdo
 *	@extends		Test_Case
 *	@uses			DB_PDO_Connection
 *	@uses			\CeusMedia\Database\PDO\Table\Reader
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@since			02.07.2008
 *	@version		0.1
 */
class CeusMedia_Database_Test_PDO_Table_ReaderTest extends CeusMedia_Database_Test_Case{

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

		$options	= array();
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

		$this->reader	= new \CeusMedia\Database\PDO\Table\Reader( $this->connection, $this->tableName, $this->columns, $this->primaryKey );
		$this->reader->setIndices( $this->indices );
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
	 *	Tests Method '__construct'.
	 *	@access		public
	 *	@return		void
	 */
	public function testConstruct1(){
		$reader		= new \CeusMedia\Database\PDO\Table\Reader( $this->connection, "table", array( 'col1', 'col2' ), 'col2', 1 );

		$expected	= 'table';
		$actual		= $reader->getTableName();
		$this->assertEquals( $expected, $actual );

		$expected	= array( 'col1', 'col2' );
		$actual		= $reader->getColumns();
		$this->assertEquals( $expected, $actual );

		$expected	= 'col2';
		$actual		= $reader->getPrimaryKey();
		$this->assertEquals( $expected, $actual );

		$expected	= array( 'col2' => 1 );
		$actual		= $reader->getFocus();
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method '__construct'.
	 *	@access		public
	 *	@return		void
	 */
	public function testConstruct2(){
		$reader		= new \CeusMedia\Database\PDO\Table\Reader( $this->connection, $this->tableName, $this->columns, $this->primaryKey, 1 );

		$expected	= array( 'id' => 1 );
		$actual		= array_slice( $reader->get(), 0, 1 );
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'count'.
	 *	@access		public
	 *	@return		void
	 */
	public function testCount(){
		$expected	= 1;
		$actual		= $this->reader->count();
		$this->assertEquals( $expected, $actual );

		$this->connection->query( "INSERT INTO transactions (topic, label) VALUES ('test', 'countTest');" );

		$expected	= 2;
		$actual		= $this->reader->count();
		$this->assertEquals( $expected, $actual );

		$expected	= 1;
		$actual		= $this->reader->count( array( 'label' => 'countTest' ) );
		$this->assertEquals( $expected, $actual );

		$expected	= 0;
		$actual		= $this->reader->count( array( 'label' => 'not_existing' ) );
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'defocus'.
	 *	@access		public
	 *	@return		void
	 */
	public function testDefocus(){
		$this->reader->focusPrimary( 2 );
		$this->reader->focusIndex( 'topic', 'test' );
		$this->reader->defocus( TRUE );

		$expected	= array( 'topic' => 'test' );
		$actual		= $this->reader->getFocus();
		$this->assertEquals( $expected, $actual );

		$this->reader->defocus();

		$expected	= array();
		$actual		= $this->reader->getFocus();
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'find'.
	 *	@access		public
	 *	@return		void
	 */
	public function testFind1(){
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('test','findTest');" );

		$result		= $this->reader->find();

		$expected	= 2;
		$actual		= count( $result );
		$this->assertEquals( $expected, $actual );

		$expected	= 4;
		$actual		= count( $result[0] );
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'find'.
	 *	@access		public
	 *	@return		void
	 */
	public function testFind2(){
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('test','findTest');" );

		$result		= $this->reader->find( array( "*" ) );

		$expected	= 2;
		$actual		= count( $result );
		$this->assertEquals( $expected, $actual );

		$expected	= 4;
		$actual		= count( $result[0] );
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'find'.
	 *	@access		public
	 *	@return		void
	 */
	public function testFind3(){
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('test','findTest');" );

		$result		= $this->reader->find( "*" );

		$expected	= 2;
		$actual		= count( $result );
		$this->assertEquals( $expected, $actual );

		$expected	= 4;
		$actual		= count( $result[0] );
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'find'.
	 *	@access		public
	 *	@return		void
	 */
	public function testFind4(){
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('test','findTest');" );

		$result		= $this->reader->find( array( "id" ) );

		$expected	= 2;
		$actual		= count( $result );
		$this->assertEquals( $expected, $actual );

		$expected	= 1;
		$actual		= count( $result[0] );
		$this->assertEquals( $expected, $actual );

		$expected	= array( 'id' );
		$actual		= array_keys( $result[0] );
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'find'.
	 *	@access		public
	 *	@return		void
	 */
	public function testFind5(){
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('test','findTest');" );

		$result		= $this->reader->find( "id" );

		$expected	= 2;
		$actual		= count( $result );
		$this->assertEquals( $expected, $actual );

		$expected	= 1;
		$actual		= count( $result[0] );
		$this->assertEquals( $expected, $actual );

		$expected	= array( 'id' );
		$actual		= array_keys( $result[0] );
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'find'.
	 *	@access		public
	 *	@return		void
	 */
	public function testFindWithOrder(){
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('test','findTest');" );

		$result		= $this->reader->find( array( 'id' ), array(), array( 'id' => 'ASC' ) );

		$expected	= 2;
		$actual		= count( $result );
		$this->assertEquals( $expected, $actual );

		$expected	= 1;
		$actual		= count( $result[0] );
		$this->assertEquals( $expected, $actual );

		$expected	= array(
			array( 'id' => 1 ),
			array( 'id' => 2 ),
		);
		$actual		= $result;
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'find'.
	 *	@access		public
	 *	@return		void
	 */
	public function testFindWithLimit(){
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('test','findTest');" );

		$result		= $this->reader->find( array( 'id' ), array(), array( 'id' => 'DESC' ), array( 0, 1 ) );

		$expected	= 1;
		$actual		= count( $result );
		$this->assertEquals( $expected, $actual );

		$expected	= 1;
		$actual		= count( $result[0] );
		$this->assertEquals( $expected, $actual );

		$expected	= array( array( 'id' => 2 ) );
		$actual		= $result;
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'find'.
	 *	@access		public
	 *	@return		void
	 */
	public function testFindWithFocus1(){
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('test','findTest');" );
		$this->reader->focusIndex( 'topic', 'start' );							//  will be ignored
		$result		= $this->reader->find( array( 'id' ) );

		$expected	= 2;
		$actual		= count( $result );
		$this->assertEquals( $expected, $actual );

		$expected	= 1;
		$actual		= count( $result[0] );
		$this->assertEquals( $expected, $actual );

		$expected	= 1;
		$actual		= $result[0]['id'];
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'find'.
	 *	@access		public
	 *	@return		void
	 */
	public function testFindWithFocus2(){
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('test','findTest');" );
		$this->reader->focusPrimary( 1 );										//  will be ignored
		$result		= $this->reader->find( array( 'id' ) );

		$expected	= 2;
		$actual		= count( $result );
		$this->assertEquals( $expected, $actual );

		$expected	= 1;
		$actual		= count( $result[0] );
		$this->assertEquals( $expected, $actual );

		$expected	= 1;
		$actual		= $result[0]['id'];
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'find'.
	 *	@access		public
	 *	@return		void
	 */
	public function testFindWithFocus3(){
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('test','findTest');" );

		$this->reader->focusIndex( 'topic', 'test' );							//  will be ignored
		$this->reader->focusPrimary( 1, FALSE );								//  will be ignored
		$result		= $this->reader->find( array( 'id' ) );

		$expected	= 2;
		$actual		= count( $result );
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'findWhereIn'.
	 *	@access		public
	 *	@return		void
	 */
	public function testFindWhereIn(){
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('test','findWhereInTest');" );

		$result		= $this->reader->findWhereIn( array( 'id' ), "topic", array( 'start', 'test' ), array( 'id' => 'ASC' ) );

		$expected	= 2;
		$actual		= count( $result );
		$this->assertEquals( $expected, $actual );

		$expected	= 1;
		$actual		= count( $result[0] );
		$this->assertEquals( $expected, $actual );

		$expected	= 1;
		$actual		= $result[0]['id'];
		$this->assertEquals( $expected, $actual );

		$result		= $this->reader->findWhereIn( array( 'id' ), "topic", array( 'test' ) );

		$expected	= 1;
		$actual		= count( $result );
		$this->assertEquals( $expected, $actual );

		$expected	= 1;
		$actual		= count( $result[0] );
		$this->assertEquals( $expected, $actual );

		$expected	= 2;
		$actual		= $result[0]['id'];
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'findWhereIn'.
	 *	@access		public
	 *	@return		void
	 */
	public function testFindWhereInWithLimit(){
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('test','findWhereInTest');" );

		$result		= $this->reader->findWhereIn( array( 'id' ), "topic", array( 'start', 'test' ), array( 'id' => "DESC" ), array( 0, 1 ) );

		$expected	= 1;
		$actual		= count( $result );
		$this->assertEquals( $expected, $actual );

		$expected	= 1;
		$actual		= count( $result[0] );
		$this->assertEquals( $expected, $actual );

		$expected	= 2;
		$actual		= $result[0]['id'];
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Exception of Method 'findWhereInAnd'.
	 *	@access		public
	 *	@return		void
	 */
	public function testFindWhereInException1(){
		$this->expectException( 'DomainException' );
		$this->reader->findWhereIn( array( 'not_valid' ), "id", 1 );
	}

	/**
	 *	Tests Exception of Method 'findWhereInAnd'.
	 *	@access		public
	 *	@return		void
	 */
	public function testFindWhereInException2(){
		$this->expectException( 'DomainException' );
		$this->reader->findWhereIn( "*", "not_valid", 1 );
	}

	/**
	 *	Tests Method 'findWhereInAnd'.
	 *	@access		public
	 *	@return		void
	 */
	public function testFindWhereInAnd(){
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('test','findWhereInAndTest');" );
		$result		= $this->reader->findWhereInAnd( array( 'id' ), "topic", array( 'test' ), array( "label" => "findWhereInAndTest" ) );

		$expected	= 1;
		$actual		= count( $result );
		$this->assertEquals( $expected, $actual );

		$expected	= 1;
		$actual		= count( $result[0] );
		$this->assertEquals( $expected, $actual );

		$expected	= 2;
		$actual		= $result[0]['id'];
		$this->assertEquals( $expected, $actual );

		$result		= $this->reader->findWhereInAnd( array( 'id' ), "topic", array( 'start' ), array( "label" => "findWhereInAndTest" ) );

		$expected	= 0;
		$actual		= count( $result );
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'findWhereIn'.
	 *	@access		public
	 *	@return		void
	 */
	public function testFindWhereInAndWithFocus(){
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('test','findWhereInAndTest');" );

		$this->reader->focusIndex( 'topic', 'test' );								//  will be ignored
		$result		= $this->reader->findWhereInAnd( array( 'id' ), "topic", array( 'start', 'test' ), array( "label" => "findWhereInAndTest" ), array( 'id' => 'ASC' ) );

		$expected	= 1;
		$actual		= count( $result );
		$this->assertEquals( $expected, $actual );

		$expected	= 1;
		$actual		= count( $result[0] );
		$this->assertEquals( $expected, $actual );

		$expected	= 2;
		$actual		= $result[0]['id'];
		$this->assertEquals( $expected, $actual );

		$result		= $this->reader->findWhereInAnd( array( 'id' ), "topic", array( 'start', 'test' ) );

		$expected	= 2;
		$actual		= count( $result );
		$this->assertEquals( $expected, $actual );

		$result		= $this->reader->findWhereInAnd( array( 'id' ), "topic", array( 'start', 'test' ), array( "label" => "findWhereInAndTest" ), array( 'id' => 'ASC' ) );

		$expected	= 1;
		$actual		= count( $result );
		$this->assertEquals( $expected, $actual );

		$result		= $this->reader->findWhereInAnd( array( 'id' ), "topic", array( 'test' ), array( "label" => "findWhereInAndTest" ), array( 'id' => 'ASC' ) );

		$expected	= 1;
		$actual		= count( $result );
		$this->assertEquals( $expected, $actual );

		$result		= $this->reader->findWhereInAnd( array( 'id' ), "topic", array( 'start' ), array( "label" => "findWhereInAndTest" ), array( 'id' => 'ASC' ) );

		$expected	= 0;
		$actual		= count( $result );
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'focusIndex'.
	 *	@access		public
	 *	@return		void
	 */
	public function testFocusIndex(){
		$this->reader->focusIndex( 'topic', 'test' );
		$expected	= array(
			'topic' => 'test'
			);
		$actual		= $this->reader->getFocus();
		$this->assertEquals( $expected, $actual );

		$this->reader->focusIndex( 'label', 'text' );
		$expected	= array(
			'topic' => 'test',
			'label'	=> 'text'
		);
		$actual		= $this->reader->getFocus();
		$this->assertEquals( $expected, $actual );

		$this->reader->focusIndex( 'id', 1 );
		$expected	= array(
			'topic' => 'test',
			'label'	=> 'text',
			'id'	=> 1
		);
		$actual		= $this->reader->getFocus();
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Exception of Method 'focusIndex'.
	 *	@access		public
	 *	@return		void
	 */
	public function testFocusIndexException(){
		$this->expectException( 'DomainException' );
		$this->reader->focusIndex( 'not_an_index', 'not_relevant' );
	}

	/**
	 *	Tests Method 'focusPrimary'.
	 *	@access		public
	 *	@return		void
	 */
	public function testFocusPrimary(){
		$this->reader->focusPrimary( 2 );
		$expected	= array( 'id' => 2 );
		$actual		= $this->reader->getFocus();
		$this->assertEquals( $expected, $actual );

		$this->reader->focusPrimary( 1 );
		$expected	= array( 'id' => 1 );
		$actual		= $this->reader->getFocus();
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'get'.
	 *	@access		public
	 *	@return		void
	 */
	public function testGetWithPrimary1(){
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('test','findWhereInAndTest');" );
		$this->reader->focusPrimary( 1 );
		$result		= $this->reader->get( FALSE );

		$expected	= 1;
		$actual		= count( $result );
		$this->assertEquals( $expected, $actual );

		$expected	= 4;
		$actual		= count( $result[0] );
		$this->assertEquals( $expected, $actual );

		$expected	= 1;
		$actual		= $result[0]['id'];
		$this->assertEquals( $expected, $actual );

		$this->reader->focusPrimary( 2 );
		$result		= $this->reader->get();

		$expected	= 4;
		$actual		= count( $result );
		$this->assertEquals( $expected, $actual );

		$expected	= 2;
		$actual		= $result['id'];
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'get'.
	 *	@access		public
	 *	@return		void
	 */
	public function testGetWithPrimary2(){
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('test','findWhereInAndTest');" );
		$this->reader->focusIndex( $this->primaryKey, 1 );
		$result		= $this->reader->get( FALSE );

		$expected	= 1;
		$actual		= count( $result );
		$this->assertEquals( $expected, $actual );

		$expected	= 4;
		$actual		= count( $result[0] );
		$this->assertEquals( $expected, $actual );

		$expected	= 1;
		$actual		= $result[0]['id'];
		$this->assertEquals( $expected, $actual );

		$this->reader->focusPrimary( 2 );
		$result		= $this->reader->get();

		$expected	= 4;
		$actual		= count( $result );
		$this->assertEquals( $expected, $actual );

		$expected	= 2;
		$actual		= $result['id'];
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'get'.
	 *	@access		public
	 *	@return		void
	 */
	public function testGetWithIndex(){
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('start','getWithIndexTest');" );
		$this->reader->focusIndex( 'topic', 'start' );
		$result		= $this->reader->get();

		$expected	= 4;
		$actual		= count( $result );
		$this->assertEquals( $expected, $actual );

		$result		= $this->reader->get( FALSE );

		$expected	= 2;
		$actual		= count( $result );
		$this->assertEquals( $expected, $actual );

		$expected	= 4;
		$actual		= count( $result[0] );
		$this->assertEquals( $expected, $actual );

		$this->reader->focusIndex( 'label', 'getWithIndexTest' );
		$result		= $this->reader->get( FALSE );

		$expected	= 1;
		$actual		= count( $result );
		$this->assertEquals( $expected, $actual );

		$expected	= 4;
		$actual		= count( $result[0] );
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'get'.
	 *	@access		public
	 *	@return		void
	 */
	public function testGetWithOrders(){
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('start','getWithOrderTest');" );
		$this->reader->focusIndex( 'topic', 'start' );
		$result		= $this->reader->get( FALSE, array( 'id' => "ASC" ) );

		$expected	= 2;
		$actual		= count( $result );
		$this->assertEquals( $expected, $actual );

		$expected	= 4;
		$actual		= count( $result[0] );
		$this->assertEquals( $expected, $actual );

		$expected	= 1;
		$actual		= $result[0]['id'];
		$this->assertEquals( $expected, $actual );

		$expected	= 2;
		$actual		= $result[1]['id'];
		$this->assertEquals( $expected, $actual );

		$result		= $this->reader->get( FALSE, array( 'id' => "DESC" ) );

		$expected	= 2;
		$actual		= count( $result );
		$this->assertEquals( $expected, $actual );

		$expected	= 4;
		$actual		= count( $result[0] );
		$this->assertEquals( $expected, $actual );

		$expected	= 2;
		$actual		= $result[0]['id'];
		$this->assertEquals( $expected, $actual );

		$expected	= 1;
		$actual		= $result[1]['id'];
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'get'.
	 *	@access		public
	 *	@return		void
	 */
	public function testGetWithLimit(){
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('start','getWithLimitTest');" );
		$this->reader->focusIndex( 'topic', 'start' );
		$result		= $this->reader->get( FALSE, array( 'id' => "ASC" ), array( 0, 1 ) );

		$expected	= 1;
		$actual		= count( $result );
		$this->assertEquals( $expected, $actual );

		$expected	= 1;
		$actual		= $result[0]['id'];
		$this->assertEquals( $expected, $actual );

		$result		= $this->reader->get( FALSE, array( 'id' => "ASC" ), array( 1, 1 ) );

		$expected	= 1;
		$actual		= count( $result );
		$this->assertEquals( $expected, $actual );

		$expected	= 2;
		$actual		= $result[0]['id'];
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Exception of Method 'get'.
	 *	@access		public
	 *	@return		void
	 */
	public function testGetWithNoFocusException(){
		$this->expectException( 'RuntimeException' );
		$this->reader->get();
	}

	/**
	 *	Tests Method 'getColumns'.
	 *	@access		public
	 *	@return		void
	 */
	public function testGetColumns(){
		$expected	= $this->columns;
		$actual		= $this->reader->getColumns();
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'getDBConnection'.
	 *	@access		public
	 *	@return		void
	 */
	public function testGetDBConnection(){
		$expected	= $this->connection;
		$actual		= $this->reader->getDBConnection();
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'getFocus'.
	 *	@access		public
	 *	@return		void
	 */
	public function testGetFocus(){
		$this->reader->focusPrimary( 1 );
		$expected	= array(
			'id' => 1
		);
		$actual		= $this->reader->getFocus();
		$this->assertEquals( $expected, $actual );

		$this->reader->focusIndex( 'topic', 'start' );
		$expected	= array(
			'id'	=> 1,
			'topic' => 'start'
		);
		$actual		= $this->reader->getFocus();
		$this->assertEquals( $expected, $actual );

		$this->reader->focusPrimary( 2, FALSE );
		$expected	= array(
			'topic' => 'start',
			'id' => 2
		);
		$actual		= $this->reader->getFocus();
		$this->assertEquals( $expected, $actual );

		$this->reader->focusPrimary( 2, TRUE );
		$expected	= array(
			'id' => 2
		);
		$actual		= $this->reader->getFocus();
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'getIndices'.
	 *	@access		public
	 *	@return		void
	 */
	public function testGetIndices(){
		$indices	= array( 'topic', 'timestamp' );
		$this->reader->setIndices( $indices );

		$expected	= $indices;
		$actual		= $this->reader->getIndices();
		$this->assertEquals( $expected, $actual );

		$indices	= array( 'topic' );
		$this->reader->setIndices( $indices );

		$expected	= $indices;
		$actual		= $this->reader->getIndices();
		$this->assertEquals( $expected, $actual );

		$indices	= array();
		$this->reader->setIndices( $indices );

		$expected	= $indices;
		$actual		= $this->reader->getIndices();
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'getPrimaryKey'.
	 *	@access		public
	 *	@return		void
	 */
	public function testGetPrimaryKey(){
		$expected	= 'id';
		$actual		= $this->reader->getPrimaryKey();
		$this->assertEquals( $expected, $actual );

		$this->reader->setPrimaryKey( 'timestamp' );
		$expected	= 'timestamp';
		$actual		= $this->reader->getPrimaryKey();
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'getTableName'.
	 *	@access		public
	 *	@return		void
	 */
	public function testGetTableName(){
		$expected	= "transactions";
		$actual		= $this->reader->getTableName();
		$this->assertEquals( $expected, $actual );

		$this->reader->setTableName( "other_table" );

		$expected	= "other_table";
		$actual		= $this->reader->getTableName();
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'isFocused'.
	 *	@access		public
	 *	@return		void
	 */
	public function testIsFocused(){
		$expected	= FALSE;
		$actual		= $this->reader->isFocused();
		$this->assertEquals( $expected, $actual );

		$this->reader->focusPrimary( 2 );
		$expected	= TRUE;
		$actual		= $this->reader->isFocused();
		$this->assertEquals( $expected, $actual );

		$this->reader->focusIndex( 'topic', 'start' );
		$expected	= TRUE;
		$actual		= $this->reader->isFocused();
		$this->assertEquals( $expected, $actual );

		$this->reader->focusPrimary( 1, FALSE );
		$expected	= TRUE;
		$actual		= $this->reader->isFocused();
		$this->assertEquals( $expected, $actual );

		$this->reader->focusPrimary( 1 );
		$expected	= TRUE;
		$actual		= $this->reader->isFocused();
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'setColumns'.
	 *	@access		public
	 *	@return		void
	 */
	public function testSetColumns(){
		$columns	= array( 'col1', 'col2', 'col3' );

		$this->reader->setColumns( $columns );

		$expected	= $columns;
		$actual		= $this->reader->getColumns();
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Exception of Method 'setColumns'.
	 *	@access		public
	 *	@return		void
	 */
	public function testSetColumnsException1(){
		$this->expectException( 'InvalidArgumentException' );
		$this->reader->setColumns( "string" );
	}

	/**
	 *	Tests Exception of Method 'setColumns'.
	 *	@access		public
	 *	@return		void
	 */
	public function testSetColumnsException2(){
		$this->expectException( 'RangeException' );
		$this->reader->setColumns( array() );
	}

	/**
	 *	Tests Method 'setDBConnection'.
	 *	@access		public
	 *	@return		void
	 */
	public function testSetDBConnection(){
		$dbc		= new PDO( $this->dsn, $this->username, $this->password );
		$this->reader->setDBConnection( $dbc );

		$expected	= $dbc;
		$actual		= $this->reader->getDBConnection();
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Exception of Method 'setDBConnection'.
	 *	@access		public
	 *	@return		void
	 */
	public function testSetDBConnection1(){
		$this->expectException( 'InvalidArgumentException' );
		$this->reader->setDBConnection( "string" );
	}

	/**
	 *	Tests Exception of Method 'setDBConnection'.
	 *	@access		public
	 *	@return		void
	 */
	public function testSetDBConnection2(){
		$this->expectException( 'RuntimeException' );
		$this->reader->setDBConnection( new stdClass() );
	}

	/**
	 *	Tests Method 'setIndices'.
	 *	@access		public
	 *	@return		void
	 */
	public function testSetIndices(){
		$indices	= array( 'topic', 'timestamp' );
		$this->reader->setIndices( $indices );

		$expected	= $indices;
		$actual		= $this->reader->getIndices();
		$this->assertEquals( $expected, $actual );

		$indices	= array( 'topic' );
		$this->reader->setIndices( $indices );

		$expected	= $indices;
		$actual		= $this->reader->getIndices();
		$this->assertEquals( $expected, $actual );

		$indices	= array();
		$this->reader->setIndices( $indices );

		$expected	= $indices;
		$actual		= $this->reader->getIndices();
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Exception of Method 'setIndices'.
	 *	@access		public
	 *	@return		void
	 */
	public function testSetIndicesException1(){
		$this->expectException( 'DomainException' );
		$this->reader->setIndices( array( 'not_existing' ) );
	}

	/**
	 *	Tests Exception of Method 'setIndices'.
	 *	@access		public
	 *	@return		void
	 */
	public function testSetIndicesException2(){
		$this->expectException( 'DomainException' );
		$this->reader->setIndices( array( 'id' ) );
	}

	/**
	 *	Tests Method 'setPrimaryKey'.
	 *	@access		public
	 *	@return		void
	 */
	public function testSetPrimaryKey(){
		$this->reader->setPrimaryKey( 'topic' );

		$expected	= 'topic';
		$actual		= $this->reader->getPrimaryKey();
		$this->assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Exception of Method 'setPrimaryKey'.
	 *	@access		public
	 *	@return		void
	 */
	public function testSetPrimaryKeyException(){
		$this->expectException( 'DomainException' );
		$this->reader->setPrimaryKey( 'not_existing' );
	}

	/**
	 *	Tests Method 'setTableName'.
	 *	@access		public
	 *	@return		void
	 */
	public function testSetTableName(){
		$tableName	= "other_table";
		$this->reader->setTableName( $tableName );

		$expected	= $tableName;
		$actual		= $this->reader->getTableName();
		$this->assertEquals( $expected, $actual );
	}
}
?>
