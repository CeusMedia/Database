<?php
/**
 *	TestUnit of DB_PDO_TableReader.
 *	@package		Tests.database.pdo
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@since			02.07.2008
 *	@version		0.1
 */
require_once 'test/initLoaders.php';
/**
 *	TestUnit of DB_PDO_TableReader.
 *	@package		Tests.database.pdo
 *	@extends		Test_Case
 *	@uses			DB_PDO_Connection
 *	@uses			DB_PDO_TableReader
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@since			02.07.2008
 *	@version		0.1
 */
class CeusMedia_Database_Test_PDO_TableTest extends CeusMedia_Database_Test_Case{

	protected $directDbc;

	/**
	 *	Constructor.
	 *	@access		public
	 *	@return		void
	 */
	public function __construct(){
		$this->host		= self::$config['unitTest-Database']['host'];
		$this->port		= self::$config['unitTest-Database']['port'];
		$this->username	= self::$config['unitTest-Database']['username'];
		$this->password	= self::$config['unitTest-Database']['password'];
		$this->database	= self::$config['unitTest-Database']['database'];
		$this->path		= dirname( __FILE__ )."/";
		$this->errorLog	= $this->path."errors.log";
		$this->queryLog	= $this->path."queries.log";

		$this->dsn		= "mysql:host=".$this->host.";dbname=".$this->database;
		$this->options	= array();
	}

	/**
	 *	Setup for every Test.
	 *	@access		public
	 *	@return		void
	 */
	public function setUp(){
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

		$this->table	= new \CeusMedia_Database_Test_PDO_TransactionTable( $this->connection );
	}

	/**
	 *	Cleanup after every Test.
	 *	@access		public
	 *	@return		void
	 */
	public function tearDown(){
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

	public function testAdd(){
		$this->table->add( array( 'topic' => 'stop', 'label' => microtime( TRUE ) ) );
		$this->assertEquals( $this->table->count(), 2 );
	}

	public function testCount(){
		$this->assertEquals( $this->table->count(), 1 );
		$this->table->add( array( 'topic' => 'stop', 'label' => time() ) );
		$this->assertEquals( $this->table->count(), 2 );
		$this->table->add( array( 'topic' => 'stop', 'label' => time() ) );
		$this->assertEquals( $this->table->count(), 3 );
	}

	public function testCountByIndex(){
		$this->table->add( array( 'topic' => 'stop', 'label' => microtime( TRUE ) ) );
		$this->table->add( array( 'topic' => 'stop', 'label' => microtime( TRUE ) ) );

		$this->assertEquals( $this->table->countByIndex( 'topic', 'invalid' ), 0 );
		$this->assertEquals( $this->table->countByIndex( 'topic', 'start' ), 1 );
		$this->assertEquals( $this->table->countByIndex( 'topic', 'stop' ), 2 );
	}

	public function testCountByIndices(){
		$this->table->add( array( 'topic' => 'stop', 'label' => 'label1' ) );
		$this->table->add( array( 'topic' => 'stop', 'label' => 'label2' ) );

		$indices	= array( 'topic' => 'invalid' );
		$this->assertEquals( $this->table->countByIndices( $indices ), 0 );

		$indices	= array( 'topic' => 'stop' );
		$this->assertEquals( $this->table->countByIndices( $indices ), 2 );

		$indices	= array( 'topic' => 'stop', 'label'	=> 'label1' );
		$this->assertEquals( $this->table->countByIndices( $indices ), 1 );
	}

	public function testEdit(){
		$this->table->add( array( 'topic' => 'stop', 'label' => 'label1' ) );

		$indices	= array( 'topic' => 'stop' );
		$this->assertEquals( $this->table->countByIndices( $indices ), 1 );
		$indices	= array( 'topic' => 'stop', 'label'	=> 'label1' );
		$this->assertEquals( $this->table->countByIndices( $indices ), 1 );

		$this->assertEquals( $this->table->edit( 2, array( 'label' => 'label3' ) ), 1 );

		$indices	= array( 'topic' => 'stop' );
		$this->assertEquals( $this->table->countByIndices( $indices ), 1 );
		$indices	= array( 'topic' => 'stop', 'label'	=> 'label1' );
		$this->assertEquals( $this->table->countByIndices( $indices ), 0 );
		$indices	= array( 'topic' => 'stop', 'label'	=> 'label3' );
		$this->assertEquals( $this->table->countByIndices( $indices ), 1 );
	}

	public function testEditByIndices(){
		$this->table->add( array( 'topic' => 'start', 'label' => 'label1' ) );

		$indices	= array( 'topic' => 'start' );
		$this->assertEquals( $this->table->countByIndices( $indices ), 2 );
		$indices	= array( 'topic' => 'start', 'label'	=> 'label1' );
		$this->assertEquals( $this->table->countByIndices( $indices ), 1 );

		$indices	= array( 'topic' => 'start' );
		$this->assertEquals( $this->table->editByIndices( $indices, array( 'label' => 'label3' ) ), 2 );
		$indices	= array( 'topic' => 'start' );
		$this->assertEquals( $this->table->countByIndices( $indices ), 2 );
		$indices	= array( 'topic' => 'start', 'label'	=> 'label1' );
		$this->assertEquals( $this->table->countByIndices( $indices ), 0 );
	}

	public function testGet(){
		$this->table->add( array( 'topic' => 'start', 'label' => 'label1' ) );

		$data	= $this->table->get( 2 );
		unset( $data->timestamp );
		$this->assertEquals( $data, (object) array(
			'id'	=> '2',
			'topic'	=> 'start',
			'label'	=> 'label1'
		) );

		$data	= $this->table->get( 2, 'label' );
		unset( $data->timestamp );
		$this->assertEquals( $data, 'label1' );
	}

	public function testGetAll(){
		$this->table->add( array( 'topic' => 'start', 'label' => 'label1' ) );
		$this->table->add( array( 'topic' => 'start', 'label' => 'label2' ) );

		$results	= $this->table->getAll();
		$this->assertEquals( count( $results ), 3 );

		$conditions	= array( 'topic' => 'start' );
		$results	= $this->table->getAll( $conditions );
		$this->assertEquals( count( $results ), 3 );

		$conditions	= array( 'topic' => 'start', 'label' => 'label1' );
		$results	= $this->table->getAll( $conditions );
		$this->assertEquals( count( $results ), 1 );

		$conditions	= array( 'topic' => 'start' );
		$orders		= array( 'label' => 'ASC' );
		$results	= $this->table->getAll( $conditions, $orders );
		$this->assertEquals( count( $results ), 3 );
		$this->assertEquals( $results[1]->label, 'label1' );

		$orders		= array( 'label' => 'DESC' );
		$results	= $this->table->getAll( $conditions, $orders );
		$this->assertEquals( count( $results ), 3 );
		$this->assertEquals( $results[0]->label, 'label2' );

		$limits		= array( 0, 1 );
		$results	= $this->table->getAll( $conditions, $orders, $limits );
		$this->assertEquals( count( $results ), 1 );
		$this->assertEquals( $results[0]->label, 'label2' );

		$limits		= array( 1, 1 );
		$results	= $this->table->getAll( $conditions, $orders, $limits );
		$this->assertEquals( count( $results ), 1 );
		$this->assertEquals( $results[0]->label, 'label1' );
	}

	public function testGetAllByIndex(){
		$this->table->add( array( 'topic' => 'start', 'label' => 'label1' ) );
		$this->table->add( array( 'topic' => 'start', 'label' => 'label2' ) );

		$results	= $this->table->getAllByIndex( 'topic', 'start' );
		$this->assertEquals( count( $results ), 3 );

		$index		= array( 'topic' => 'start' );
		$orders		= array( 'label' => 'ASC' );
		$results	= $this->table->getAllByIndex( 'topic', 'start', $orders );
		$this->assertEquals( count( $results ), 3 );
		$this->assertEquals( $results[1]->label, 'label1' );

		$orders		= array( 'label' => 'DESC' );
		$results	= $this->table->getAllByIndex( 'topic', 'start', $orders );
		$this->assertEquals( count( $results ), 3 );
		$this->assertEquals( $results[0]->label, 'label2' );

		$limits		= array( 0, 1 );
		$results	= $this->table->getAllByIndex( 'topic', 'start', $orders, $limits );
		$this->assertEquals( count( $results ), 1 );
		$this->assertEquals( $results[0]->label, 'label2' );

		$limits		= array( 1, 1 );
		$results	= $this->table->getAllByIndex( 'topic', 'start', $orders, $limits );
		$this->assertEquals( count( $results ), 1 );
		$this->assertEquals( $results[0]->label, 'label1' );
	}

	public function testGetAllByIndices(){
		$this->table->add( array( 'topic' => 'start', 'label' => 'label1' ) );
		$this->table->add( array( 'topic' => 'start', 'label' => 'label2' ) );

		$indices	= array( 'topic' => 'start' );
		$results	= $this->table->getAllByIndices( $indices );
		$this->assertEquals( count( $results ), 3 );

		$indices	= array( 'topic' => 'start', 'label' => 'label1' );
		$results	= $this->table->getAllByIndices( $indices );
		$this->assertEquals( count( $results ), 1 );

		$indices	= array( 'topic' => 'start' );
		$orders		= array( 'label' => 'ASC' );
		$results	= $this->table->getAllByIndices( $indices, $orders );
		$this->assertEquals( count( $results ), 3 );
		$this->assertEquals( $results[1]->label, 'label1' );

		$orders		= array( 'label' => 'DESC' );
		$results	= $this->table->getAllByIndices( $indices, $orders );
		$this->assertEquals( count( $results ), 3 );
		$this->assertEquals( $results[0]->label, 'label2' );

		$limits		= array( 0, 1 );
		$results	= $this->table->getAllByIndices( $indices, $orders, $limits );
		$this->assertEquals( count( $results ), 1 );
		$this->assertEquals( $results[0]->label, 'label2' );

		$limits		= array( 1, 1 );
		$results	= $this->table->getAllByIndices( $indices, $orders, $limits );
		$this->assertEquals( count( $results ), 1 );
		$this->assertEquals( $results[0]->label, 'label1' );
	}

	public function testGetByIndex(){
		$this->table->remove( 1 );
		$this->table->add( array( 'topic' => 'start', 'label' => 'label1' ) );
		$this->table->add( array( 'topic' => 'start', 'label' => 'label2' ) );

		$result		= $this->table->getByIndex( 'topic', 'start' );
		unset( $result->timestamp );
		$this->assertEquals( $result, (object) array( 'id' => 2, 'topic' => 'start', 'label' => 'label1' ) );

		$orders		= array( 'label' => 'DESC' );
		$result		= $this->table->getByIndex( 'topic', 'start', $orders );
		unset( $result->timestamp );
		$this->assertEquals( $result, (object) array( 'id' => 3, 'topic' => 'start', 'label' => 'label2' ) );

		$orders		= array( 'label' => 'DESC' );
		$fields		= array( 'label' );
		$result		= $this->table->getByIndex( 'topic', 'start', $orders, $fields );
		$this->assertEquals( $result, 'label2' );

		$orders		= array( 'label' => 'DESC' );
		$fields		= array( 'label', 'topic' );
		$result		= $this->table->getByIndex( 'topic', 'start', $orders, $fields );
		$this->assertEquals( $result, (object) array( 'label' => 'label2', 'topic' => 'start' ) );

		$result		= $this->table->getByIndex( 'label', 'label2' );
		unset( $result->timestamp );
		$this->assertEquals( $result, (object) array( 'id' => 3, 'topic' => 'start', 'label' => 'label2' ) );
	}

	public function testGetByIndices(){
		$this->table->remove( 1 );
		$this->table->add( array( 'topic' => 'start', 'label' => 'label1' ) );
		$this->table->add( array( 'topic' => 'start', 'label' => 'label2' ) );

		$indices	= array( 'topic' => 'start' );
		$result		= $this->table->getByIndices( $indices );
		unset( $result->timestamp );
		$this->assertEquals( $result, (object) array( 'id' => 2, 'topic' => 'start', 'label' => 'label1' ) );

		$indices	= array( 'topic' => 'start' );
		$orders		= array( 'label' => 'DESC' );
		$result		= $this->table->getByIndices( $indices, $orders );
		unset( $result->timestamp );
		$this->assertEquals( $result, (object) array( 'id' => 3, 'topic' => 'start', 'label' => 'label2' ) );

		$orders		= array( 'label' => 'DESC' );
		$fields		= array( 'label' );
		$result		= $this->table->getByIndices( $indices, $orders, $fields );
		$this->assertEquals( $result, 'label2' );

		$orders		= array( 'label' => 'DESC' );
		$fields		= array( 'label', 'topic' );
		$result		= $this->table->getByIndices( $indices, $orders, $fields );
		$this->assertEquals( $result, (object) array( 'label' => 'label2', 'topic' => 'start' ) );

		$indices	= array( 'label' => 'label2' );
		$result		= $this->table->getByIndices( $indices );
		unset( $result->timestamp );
		$this->assertEquals( $result, (object) array( 'id' => 3, 'topic' => 'start', 'label' => 'label2' ) );
	}

	public function testGetColumns(){
		$this->assertEquals( $this->table->getColumns(), array( 'id', 'topic', 'label', 'timestamp' ) );
	}

	public function testGetIndices(){
		$this->assertEquals( $this->table->getIndices(), array( 'topic', 'label' ) );
	}

	public function testGetName(){
		$this->assertEquals( $this->table->getName(), 'transactions' );
		$this->assertEquals( $this->table->getName( TRUE ), 'transactions' );
	}

	public function testGetPrimaryKey(){
		$this->assertEquals( $this->table->getPrimaryKey(), 'id' );
	}
}
