<?php /** @noinspection ALL */
/** @noinspection SqlResolve */
/** @noinspection SqlNoDataSourceInspection */

/**
 *	TestUnit of PDO Table Reader.
 *	@package		Tests.database.pdo
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 */

namespace CeusMedia\DatabaseTest\PDO\Table;

use CeusMedia\Database\PDO\Connection as PdoConnection;
use CeusMedia\Database\PDO\Table\Reader as PdoTableReader;
use CeusMedia\DatabaseTest\PDO\TestCase;
use mysqli;


/**
 *	TestUnit of PDO Table Reader.
 *	@package		Tests.database.pdo
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 */
class ReaderTest extends TestCase
{
	protected array $columns;
	protected string $tableName;
	protected array $indices;
	protected string $primaryKey;
	protected PdoTableReader $reader;

	/**
	 *	Constructor.
	 *	@access		public
	 *	@return		void
	 */
	public function __construct( string $name = '' )
	{
		parent::__construct( $name );

		$this->tableName	= "transactions";
		$this->columns		= [
			'id',
			'topic',
			'label',
			'timestamp',
		];
		$this->primaryKey	= $this->columns[0];
		$this->indices		= [
			'topic',
			'label'
		];
	}

	/**
	 *	Tests Method '__construct'.
	 *	@access		public
	 *	@return		void
	 */
	public function testConstruct1()
	{
		$reader		= new PdoTableReader( $this->connection, "table", ['col1', 'col2'], 'col2', 1 );

		$expected	= 'table';
		$actual		= $reader->getTableName();
		self::assertEquals( $expected, $actual );

		$expected	= ['col1', 'col2'];
		$actual		= $reader->getColumns();
		self::assertEquals( $expected, $actual );

		$expected	= 'col2';
		$actual		= $reader->getPrimaryKey();
		self::assertEquals( $expected, $actual );

		$expected	= ['col2' => 1];
		$actual		= $reader->getFocus();
		self::assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method '__construct'.
	 *	@access		public
	 *	@return		void
	 */
	public function testConstruct2()
	{
		$reader		= new PdoTableReader( $this->connection, $this->tableName, $this->columns, $this->primaryKey, 1 );

		$expected	= ['id' => 1];
		$actual		= array_slice( $reader->get(), 0, 1 );
		self::assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'count'.
	 *	@access		public
	 *	@return		void
	 */
	public function testCount()
	{
		self::assertEquals( 1, $this->reader->count() );

		$this->connection->query( "INSERT INTO transactions (topic, label) VALUES ('test', 'countTest');" );

		self::assertEquals( 2, $this->reader->count() );

		$actual		= $this->reader->count( ['label' => 'countTest'] );
		self::assertEquals( 1, $actual );

		$actual		= $this->reader->count( ['label' => 'not_existing'] );
		self::assertEquals( 0, $actual );
	}

	/**
	 *	Tests Method 'defocus'.
	 *	@access		public
	 *	@return		void
	 */
	public function testDefocus()
	{
		$this->reader->focusPrimary( 2 );
		$this->reader->focusIndex( 'topic', 'test' );
		$this->reader->defocus( TRUE );

		$expected	= ['topic' => 'test'];
		$actual		= $this->reader->getFocus();
		self::assertEquals( $expected, $actual );

		$this->reader->defocus();

		$expected	= [];
		$actual		= $this->reader->getFocus();
		self::assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'find'.
	 *	@access		public
	 *	@return		void
	 */
	public function testFind1()
	{
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('test','findTest');" );

		$result		= $this->reader->find();
		self::assertCount( 2, $result );
		self::assertCount( 4, $result[0] );
	}

	/**
	 *	Tests Method 'find'.
	 *	@access		public
	 *	@return		void
	 */
	public function testFind2()
	{
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('test','findTest');" );

		$result		= $this->reader->find( ["*"] );
		self::assertCount( 2, $result );
		self::assertCount( 4, $result[0] );
	}

	/**
	 *	Tests Method 'find'.
	 *	@access		public->fetchMode
	 *	@return		void
	 */
	public function testFind3()
	{
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('test','findTest');" );

		$result		= $this->reader->find( "*" );
		self::assertCount( 2, $result );
		self::assertCount( 4, $result[0] );
	}

	/**
	 *	Tests Method 'find'.
	 *	@access		public
	 *	@return		void
	 */
	public function testFind4()
	{
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('test','findTest');" );

		$result		= $this->reader->find( ["id"] );
		self::assertCount( 2, $result );
		self::assertCount( 1, $result[0] );
		self::assertEquals( ['id'], array_keys( $result[0] ) );
	}

	/**
	 *	Tests Method 'find'.
	 *	@access		public
	 *	@return		void
	 */
	public function testFind5()
	{
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('test','findTest');" );

		$result		= $this->reader->find( "id" );
		self::assertCount( 2, $result );
		self::assertCount( 1, $result[0] );
		self::assertEquals( ['id'], array_keys( $result[0] ) );
	}

	/**
	 *	Tests Method 'find'.
	 *	@access		public
	 *	@return		void
	 */
	public function testFindWithOrder()
	{
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('test','findTest');" );

		$result		= $this->reader->find( ['id'], [], ['id' => 'ASC'] );

		self::assertCount( 2, $result );
		self::assertCount( 1, $result[0] );

		$expected	= [
			['id' => 1],
			['id' => 2],
		];
		$actual		= $result;
		self::assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'find'.
	 *	@access		public
	 *	@return		void
	 */
	public function testFindWithLimit()
	{
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('test','findTest');" );

		$result		= $this->reader->find( ['id'], [], ['id' => 'DESC'], [0, 1] );
		self::assertCount( 1, $result );
		self::assertCount( 1, $result[0] );
		self::assertEquals( [['id' => 2]], $result );
	}

	/**
	 *	Tests Method 'find'.
	 *	@access		public
	 *	@return		void
	 */
	public function testFindWithFocus1()
	{
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('test','findTest');" );
		//  will be ignored
		$this->reader->focusIndex( 'topic', 'start' );
		$result		= $this->reader->find( ['id'] );
		self::assertCount( 2, $result );
		self::assertCount( 1, $result[0] );
		self::assertEquals( 1, $result[0]['id'] );
	}

	/**
	 *	Tests Method 'find'.
	 *	@access		public
	 *	@return		void
	 */
	public function testFindWithFocus2()
	{
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('test','findTest');" );
		//  will be ignored
		$this->reader->focusPrimary( 1 );
		$result		= $this->reader->find( ['id'] );
		self::assertCount( 2, $result );
		self::assertCount( 1, $result[0] );
		self::assertEquals( 1, $result[0]['id'] );
	}

	/**
	 *	Tests Method 'find'.
	 *	@access		public
	 *	@return		void
	 */
	public function testFindWithFocus3()
	{
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('test','findTest');" );

		//  will be ignored
		$this->reader->focusIndex( 'topic', 'test' );
		//  will be ignored
		$this->reader->focusPrimary( 1, FALSE );
		$result		= $this->reader->find( ['id'] );
		self::assertCount( 2, $result );
	}

	/**
	 *	Tests Method 'findWhereIn'.
	 *	@access		public
	 *	@return		void
	 */
	public function testFindWhereIn()
	{
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('test','findWhereInTest');" );

		$result		= $this->reader->findWhereIn( ['id'], "topic", ['start', 'test'], ['id' => 'ASC'] );

		self::assertCount( 2, $result );
		self::assertCount( 1, $result[0] );
		self::assertEquals( 1, $result[0]['id'] );

		$result		= $this->reader->findWhereIn( ['id'], "topic", ['test'] );
		self::assertCount( 1, $result );
		self::assertCount( 1, $result[0] );
		self::assertEquals( 2, $result[0]['id'] );
	}

	/**
	 *	Tests Method 'findWhereIn'.
	 *	@access		public
	 *	@return		void
	 */
	public function testFindWhereInWithLimit()
	{
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('test','findWhereInTest');" );

		$result		= $this->reader->findWhereIn( ['id'], "topic", ['start', 'test'], ['id' => "DESC"], [0, 1] );

		self::assertCount( 1, $result );
		self::assertCount( 1, $result[0] );
		self::assertEquals( 2, $result[0]['id'] );
	}

	/**
	 *	Tests Exception of Method 'findWhereInAnd'.
	 *	@access		public
	 *	@return		void
	 */
	public function testFindWhereInException1()
	{
		$this->expectException( 'TypeError' );
		$this->reader->findWhereIn( ['not_valid'], "id", 1 );
	}

	/**
	 *	Tests Exception of Method 'findWhereInAnd'.
	 *	@access		public
	 *	@return		void
	 */
	public function testFindWhereInException2()
	{
		$this->expectException( 'TypeError' );
		/** @noinspection PhpParamsInspection */
		$this->reader->findWhereIn( "*", "not_valid", 1 );
	}

	/**
	 *	Tests Method 'findWhereInAnd'.
	 *	@access		public
	 *	@return		void
	 */
	public function testFindWhereInAnd()
	{
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('test','findWhereInAndTest');" );
		$result		= $this->reader->findWhereInAnd( ['id'], "topic", ['test'], ["label" => "findWhereInAndTest"] );

		self::assertCount( 1, $result );
		self::assertCount( 1, $result[0] );
		self::assertEquals( 2, $result[0]['id'] );

		$result		= $this->reader->findWhereInAnd( ['id'], "topic", ['start'], ["label" => "findWhereInAndTest"] );
		self::assertCount( 0, $result );
	}

	/**
	 *	Tests Method 'findWhereIn'.
	 *	@access		public
	 *	@return		void
	 */
	public function testFindWhereInAndWithFocus()
	{
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('test','findWhereInAndTest');" );

		//  will be ignored
		$this->reader->focusIndex( 'topic', 'test' );
		$result		= $this->reader->findWhereInAnd( ['id'], "topic", ['start', 'test'], ["label" => "findWhereInAndTest"], ['id' => 'ASC'] );
		self::assertCount( 1, $result );
		self::assertCount( 1, $result[0] );
		self::assertEquals( 2, $result[0]['id'] );

		$result		= $this->reader->findWhereInAnd( ['id'], "topic", ['start', 'test'] );
		self::assertCount( 2, $result );

		$result		= $this->reader->findWhereInAnd( ['id'], "topic", ['start', 'test'], ["label" => "findWhereInAndTest"], ['id' => 'ASC'] );
		self::assertCount( 1, $result );

		$result		= $this->reader->findWhereInAnd( ['id'], "topic", ['test'], ["label" => "findWhereInAndTest"], ['id' => 'ASC'] );
		self::assertCount( 1, $result );

		$result		= $this->reader->findWhereInAnd( ['id'], "topic", ['start'], ["label" => "findWhereInAndTest"], ['id' => 'ASC'] );
		self::assertCount( 0, $result );
	}

	/**
	 *	Tests Method 'focusIndex'.
	 *	@access		public
	 *	@return		void
	 */
	public function testFocusIndex()
	{
		$this->reader->focusIndex( 'topic', 'test' );
		$expected	= ['topic' => 'test'];
		self::assertEquals( $expected, $this->reader->getFocus() );

		$this->reader->focusIndex( 'label', 'text' );
		$expected	= ['topic' => 'test', 'label'	=> 'text'];
		self::assertEquals( $expected, $this->reader->getFocus() );

		$this->reader->focusIndex( 'id', 1 );
		$expected	= ['topic' => 'test', 'label'	=> 'text', 'id'	=> 1];
		self::assertEquals( $expected, $this->reader->getFocus() );
	}

	/**
	 *	Tests Exception of Method 'focusIndex'.
	 *	@access		public
	 *	@return		void
	 */
	public function testFocusIndexException()
	{
		$this->expectException( 'DomainException' );
		$this->reader->focusIndex( 'not_an_index', 'not_relevant' );
	}

	/**
	 *	Tests Method 'focusPrimary'.
	 *	@access		public
	 *	@return		void
	 */
	public function testFocusPrimary()
	{
		$this->reader->focusPrimary( 2 );
		$expected	= ['id' => 2];
		$actual		= $this->reader->getFocus();
		self::assertEquals( $expected, $actual );

		$this->reader->focusPrimary( 1 );
		$expected	= ['id' => 1];
		$actual		= $this->reader->getFocus();
		self::assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'get'.
	 *	@access		public
	 *	@return		void
	 */
	public function testGetWithPrimary1()
	{
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('test','findWhereInAndTest');" );
		$this->reader->focusPrimary( 1 );

		/** @var array $result */
		$result		= $this->reader->get( FALSE );
		self::assertCount( 1, $result );
		self::assertCount( 4, $result[0] );
		self::assertEquals( 1, $result[0]['id'] );

		$this->reader->focusPrimary( 2 );
		/** @var array $result */
		$result		= $this->reader->get();
		self::assertCount( 4, $result );
		self::assertEquals( 2, $result['id'] );
	}

	/**
	 *	Tests Method 'get'.
	 *	@access		public
	 *	@return		void
	 */
	public function testGetWithPrimary2()
	{
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('test','findWhereInAndTest');" );
		$this->reader->focusIndex( $this->primaryKey, 1 );

		/** @var array $result */
		$result		= $this->reader->get( FALSE );
		self::assertCount( 1, $result );
		self::assertCount( 4, $result[0] );
		self::assertEquals( 1, $result[0]['id'] );

		$this->reader->focusPrimary( 2 );
		$result		= $this->reader->get();
		self::assertCount( 4, $result );
		self::assertEquals( 2, $result['id'] );
	}

	/**
	 *	Tests Method 'get'.
	 *	@access		public
	 *	@return		void
	 */
	public function testGetWithIndex()
	{
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('start','getWithIndexTest');" );
		$this->reader->focusIndex( 'topic', 'start' );

		/** @var array $result */
		$result		= $this->reader->get();
		self::assertCount( 4, $result );

		/** @var array $result */
		$result		= $this->reader->get( FALSE );
		self::assertCount( 2, $result );
		self::assertCount( 4, $result[0] );

		$this->reader->focusIndex( 'label', 'getWithIndexTest' );
		/** @var array $result */
		$result		= $this->reader->get( FALSE );
		self::assertCount( 1, $result );
		self::assertCount( 4, $result[0] );
	}

	/**
	 *	Tests Method 'get'.
	 *	@access		public
	 *	@return		void
	 */
	public function testGetWithOrders()
	{
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('start','getWithOrderTest');" );
		$this->reader->focusIndex( 'topic', 'start' );

		/** @var array $result */
		$result		= $this->reader->get( FALSE, ['id' => "ASC"] );
		self::assertCount( 2, $result );
		self::assertCount( 4, $result[0] );
		self::assertEquals( 1, $result[0]['id'] );
		self::assertEquals( 2, $result[1]['id'] );

		/** @var array $result */
		$result		= $this->reader->get( FALSE, ['id' => "DESC"] );
		self::assertCount( 2, $result );
		self::assertCount( 4, $result[0] );
		self::assertEquals( 2, $result[0]['id'] );
		self::assertEquals( 1, $result[1]['id'] );
	}

	/**
	 *	Tests Method 'get'.
	 *	@access		public
	 *	@return		void
	 */
	public function testGetWithLimit()
	{
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('start','getWithLimitTest');" );
		$this->reader->focusIndex( 'topic', 'start' );

		/** @var array $result */
		$result		= $this->reader->get( FALSE, ['id' => "ASC"], [0, 1] );
		self::assertCount( 1, $result );
		self::assertEquals( 1, $result[0]['id'] );

		/** @var array $result */
		$result		= $this->reader->get( FALSE, ['id' => "ASC"], [1, 1] );
		self::assertCount( 1, $result );
		self::assertEquals( 2, $result[0]['id'] );
	}

	/**
	 *	Tests Exception of Method 'get'.
	 *	@access		public
	 *	@return		void
	 */
	public function testGetWithNoFocusException()
	{
		$this->expectException( 'RuntimeException' );
		$this->reader->get();
	}

	/**
	 *	Tests Method 'getColumns'.
	 *	@access		public
	 *	@return		void
	 */
	public function testGetColumns()
	{
		$expected	= $this->columns;
		$actual		= $this->reader->getColumns();
		self::assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'getDBConnection'.
	 *	@access		public
	 *	@return		void
	 */
	public function testGetDBConnection()
	{
		$expected	= $this->connection;
		$actual		= $this->reader->getDBConnection();
		self::assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'getFocus'.
	 *	@access		public
	 *	@return		void
	 */
	public function testGetFocus()
	{
		$this->reader->focusPrimary( 1 );
		$expected	= ['id' => 1];
		$actual		= $this->reader->getFocus();
		self::assertEquals( $expected, $actual );

		$this->reader->focusIndex( 'topic', 'start' );
		$expected	= [
			'id'	=> 1,
			'topic' => 'start'
		];
		$actual		= $this->reader->getFocus();
		self::assertEquals( $expected, $actual );

		$this->reader->focusPrimary( 2, FALSE );
		$expected	= [
			'topic' => 'start',
			'id' => 2
		];
		$actual		= $this->reader->getFocus();
		self::assertEquals( $expected, $actual );

		$this->reader->focusPrimary( 2, TRUE );
		$expected	= [
			'id' => 2
		];
		$actual		= $this->reader->getFocus();
		self::assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'getIndices'.
	 *	@access		public
	 *	@return		void
	 */
	public function testGetIndices()
	{
		$indices	= ['topic', 'timestamp'];
		$this->reader->setIndices( $indices );

		$expected	= $indices;
		$actual		= $this->reader->getIndices();
		self::assertEquals( $expected, $actual );

		$indices	= ['topic'];
		$this->reader->setIndices( $indices );

		$expected	= $indices;
		$actual		= $this->reader->getIndices();
		self::assertEquals( $expected, $actual );

		$indices	= [];
		$this->reader->setIndices( $indices );

		$expected	= $indices;
		$actual		= $this->reader->getIndices();
		self::assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'getPrimaryKey'.
	 *	@access		public
	 *	@return		void
	 */
	public function testGetPrimaryKey()
	{
		$expected	= 'id';
		$actual		= $this->reader->getPrimaryKey();
		self::assertEquals( $expected, $actual );

		$this->reader->setPrimaryKey( 'timestamp' );
		$expected	= 'timestamp';
		$actual		= $this->reader->getPrimaryKey();
		self::assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'getTableName'.
	 *	@access		public
	 *	@return		void
	 */
	public function testGetTableName()
	{
		$expected	= "transactions";
		$actual		= $this->reader->getTableName();
		self::assertEquals( $expected, $actual );

		$this->reader->setTableName( "other_table" );

		$expected	= "other_table";
		$actual		= $this->reader->getTableName();
		self::assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'isFocused'.
	 *	@access		public
	 *	@return		void
	 */
	public function testIsFocused()
	{
		self::assertFalse( $this->reader->isFocused() );

		$this->reader->focusPrimary( 2 );
		self::assertTrue( $this->reader->isFocused() );

		$this->reader->focusIndex( 'topic', 'start' );
		self::assertTrue( $this->reader->isFocused() );

		$this->reader->focusPrimary( 1, FALSE );
		self::assertTrue( $this->reader->isFocused() );

		$this->reader->focusPrimary( 1 );
		self::assertTrue( $this->reader->isFocused() );
	}

	/**
	 *	Tests Method 'setColumns'.
	 *	@access		public
	 *	@return		void
	 */
	public function testSetColumns()
	{
		$columns	= ['col1', 'col2', 'col3'];

		$this->reader->setColumns( $columns );

		$expected	= $columns;
		$actual		= $this->reader->getColumns();
		self::assertEquals( $expected, $actual );
	}

	/**
	 *	@return		void
	 */
	public function testGetSetFetchEntityClass(): void
	{
		$className	= 'TestA';
		$this->reader->setFetchEntityClass( $className );
		self::assertEquals( $className, $this->reader->getFetchEntityClass() );
	}

	/**
	 *	@return		void
	 */
	public function testGetSetFetchEntityObject(): void
	{
		$object	= new class(){ public $content	= 'testA'; };
		$this->reader->setFetchEntityObject( $object );
		self::assertEquals( $object, $this->reader->getFetchEntityObject() );
	}

	/**
	 *	Tests Exception of Method 'setColumns'.
	 *	@access		public
	 *	@return		void
	 */
	public function testSetColumnsException1()
	{
		$this->expectException( 'RangeException' );
		$this->reader->setColumns( [] );
	}

	/**
	 *	Tests Method 'setDbConnection'.
	 *	@access		public
	 *	@return		void
	 */
	public function testSetDbConnection()
	{
		$dbc		= new PdoConnection( $this->dsn, $this->username, $this->password );
		$this->reader->setDBConnection( $dbc );

		$expected	= $dbc;
		$actual		= $this->reader->getDBConnection();
		self::assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'setIndices'.
	 *	@access		public
	 *	@return		void
	 */
	public function testSetIndices()
	{
		$indices	= ['topic', 'timestamp'];
		$this->reader->setIndices( $indices );

		$expected	= $indices;
		$actual		= $this->reader->getIndices();
		self::assertEquals( $expected, $actual );

		$indices	= ['topic'];
		$this->reader->setIndices( $indices );

		$expected	= $indices;
		$actual		= $this->reader->getIndices();
		self::assertEquals( $expected, $actual );

		$indices	= [];
		$this->reader->setIndices( $indices );

		$expected	= $indices;
		$actual		= $this->reader->getIndices();
		self::assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Exception of Method 'setIndices'.
	 *	@access		public
	 *	@return		void
	 */
	public function testSetIndicesException1()
	{
		$this->expectException( 'DomainException' );
		$this->reader->setIndices( ['not_existing'] );
	}

	/**
	 *	Tests Exception of Method 'setIndices'.
	 *	@access		public
	 *	@return		void
	 */
	public function testSetIndicesException2()
	{
		$this->expectException( 'DomainException' );
		$this->reader->setIndices( ['id'] );
	}

	/**
	 *	Tests Method 'setPrimaryKey'.
	 *	@access		public
	 *	@return		void
	 */
	public function testSetPrimaryKey()
	{
		$this->reader->setPrimaryKey( 'topic' );

		$expected	= 'topic';
		$actual		= $this->reader->getPrimaryKey();
		self::assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Exception of Method 'setPrimaryKey'.
	 *	@access		public
	 *	@return		void
	 */
	public function testSetPrimaryKeyException()
	{
		$this->expectException( 'DomainException' );
		$this->reader->setPrimaryKey( 'not_existing' );
	}

	/**
	 *	Tests Method 'setTableName'.
	 *	@access		public
	 *	@return		void
	 */
	public function testSetTableName()
	{
		$tableName	= "other_table";
		$this->reader->setTableName( $tableName );

		$expected	= $tableName;
		$actual		= $this->reader->getTableName();
		self::assertEquals( $expected, $actual );
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

		$this->reader	= new PdoTableReader( $this->connection, $this->tableName, $this->columns, $this->primaryKey );
		$this->reader->setIndices( $this->indices );
	}

	/**
	 *	Cleanup after every Test.
	 *	@access		protected
	 *	@return		void
	 */
	protected function tearDown(): void
	{
		parent::tearDown();
	}
}
