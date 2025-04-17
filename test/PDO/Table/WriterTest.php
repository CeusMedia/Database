<?php /** @noinspection PhpMultipleClassDeclarationsInspection */
/** @noinspection SqlResolve */
/** @noinspection SqlNoDataSourceInspection */

/**
 *	TestUnit of DB_PDO_TableWriter.
 *	@package		Tests.{classPackage}
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 */

namespace CeusMedia\DatabaseTest\PDO\Table;

use CeusMedia\Common\ADT\Collection\Dictionary;
use CeusMedia\Database\PDO\Table\Reader as PdoTableReader;
use CeusMedia\Database\PDO\Table\Writer as PdoTableWriter;
use CeusMedia\DatabaseTest\PDO\TestCase;
use CeusMedia\DatabaseTest\PDO\AdvancedTransactionEntity;
use CeusMedia\DatabaseTest\PDO\TransactionEntity;

/**
 *	TestUnit of DB_PDO_TableWriter.
 *	@package		Tests.{classPackage}
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 */
class WriterTest extends TestCase
{
	protected array $columns;
	protected string $tableName;
	protected array $indices;
	protected string $primaryKey;
	protected PdoTableReader $reader;
	protected PdoTableWriter $writer;

	/**
	 *	Tests Method 'delete'.
	 *	@access		public
	 *	@return		void
	 */
	public function testDelete()
	{
		$this->connection->query( "INSERT INTO transactions (topic, label) VALUES ('test', 'deleteTest');" );
		$this->connection->query( "INSERT INTO transactions (topic, label) VALUES ('test', 'deleteTest');" );
		$this->connection->query( "INSERT INTO transactions (topic, label) VALUES ('test', 'deleteTest');" );

		self::assertEquals( 4, $this->reader->count() );

		$this->writer->focusPrimary( 4 );
		self::assertEquals( 1, $this->writer->delete() );

		$this->writer->defocus();
		self::assertEquals( 3, $this->reader->count() );

		$actual		= $this->reader->find( [], ['label' => 'deleteTest'] );
		self::assertCount( 2, $actual );

		$this->writer->focusIndex( 'label', 'deleteTest' );
		self::assertEquals( 2, $this->writer->delete() );

		$this->writer->defocus();
		self::assertEquals( 1, $this->reader->count() );

		$this->writer->defocus();
		$this->writer->focusPrimary( 999999 );
		self::assertEquals( 0, $this->writer->delete() );
	}

	/**
	 *	Tests Exception of Method 'delete'.
	 *	@access		public
	 *	@return		void
	 */
	public function testDeleteException1()
	{
		$this->expectException( 'RuntimeException' );
		$this->writer->delete();
	}

	/**
	 *	Tests Method 'deleteByConditions'.
	 *	@access		public
	 *	@return		void
	 */
	public function testDeleteByConditions()
	{
		$this->connection->query( "INSERT INTO transactions (topic, label) VALUES ('test', 'deleteTest');" );
		$this->connection->query( "INSERT INTO transactions (topic, label) VALUES ('test', 'deleteTest');" );
		$this->connection->query( "INSERT INTO transactions (topic, label) VALUES ('test', 'deleteTest');" );

		self::assertEquals( 4, $this->reader->count() );

		$actual		= $this->writer->deleteByConditions( ['label' => 'deleteTest'] );
		self::assertEquals( 3, $actual );

		self::assertEquals( 1, $this->reader->count() );
	}

	/**
	 *	Tests Method 'insert'.
	 *	@access		public
	 *	@return		void
	 */
	public function testInsert_withArray()
	{
		$data	= ['topic' => 'insert', 'label' => 'insertTest'];

		self::assertEquals( 2, $this->writer->insert( $data ) );
		self::assertEquals( 2, $this->reader->count() );

		$this->writer->focusPrimary( 2 );

		/** @var array|NULL $result */
		$result		= $this->reader->focusPrimary( 2 )->get();
		self::assertNotNull( $result );

		/** @var array $result */
		$actual		= array_slice( $result, 1, 2 );
		self::assertEquals( $data, $actual );

		$this->writer->focusIndex( 'topic', 'insert' );
		$actual		= $this->writer->insert( ['label' => 'insertTest2'] );
		self::assertEquals( 3, $actual );

		$this->writer->defocus();
		self::assertEquals( 3, $this->reader->count() );

		$results	= $this->reader->find( ['label'] );
		$expected	= ['label' => 'insertTest2'];
		$actual		= array_pop( $results );
		self::assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'insert'.
	 *	@access		public
	 *	@return		void
	 */
	public function testInsert_withDictionary()
	{
		$data	= ['topic' => 'insert', 'label' => 'insertTest'];

		self::assertEquals( 2, $this->writer->insert( new Dictionary( $data ) ) );
		self::assertEquals( 2, $this->reader->count() );

		$this->writer->focusPrimary( 2 );
		/** @var array|NULL $result */
		$result		= $this->reader->focusPrimary( 2 )->get();
		self::assertNotNull( $result );

		/** @var array $result */
		$actual		= array_slice( $result, 1, 2 );
		self::assertEquals( $data, $actual );

		$this->writer->focusIndex( 'topic', 'insert' );
		$actual		= $this->writer->insert( ['label' => 'insertTest2'] );
		self::assertEquals( 3, $actual );

		$this->writer->defocus();
		self::assertEquals( 3, $this->reader->count() );

		$results	= $this->reader->find( ['label'] );
		$expected	= ['label' => 'insertTest2'];
		$actual		= array_pop( $results );
		self::assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'insert'.
	 *	@access		public
	 *	@return		void
	 */
	public function testInsert_withAdvancedEntity()
	{
		$data	= ['topic' => 'insert', 'label' => 'insertTest'];
		$entity	= new AdvancedTransactionEntity( $data );

		self::assertEquals( 2, $this->writer->insert( $entity ) );
		self::assertEquals( 2, $this->reader->count() );

		$this->writer->focusPrimary( 2 );
		/** @var array|NULL $result */
		$result		= $this->reader->focusPrimary( 2 )->get();
		self::assertNotNull( $result );

		/** @var array $result */
		$actual		= array_slice( $result, 1, 2 );
		self::assertEquals( $data, $actual );

		$this->writer->focusIndex( 'topic', 'insert' );
		$actual		= $this->writer->insert( ['label' => 'insertTest2'] );
		self::assertEquals( 3, $actual );

		$this->writer->defocus();
		self::assertEquals( 3, $this->reader->count() );

		$results	= $this->reader->find( ['label'] );
		$expected	= ['label' => 'insertTest2'];
		$actual		= array_pop( $results );
		self::assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'insert'.
	 *	@access		public
	 *	@return		void
	 */
	public function testInsert_withEntity()
	{
		$data	= ['topic' => 'insert', 'label' => 'insertTest'];
		$entity	= new TransactionEntity();
		foreach( $data as $key => $value )
			$entity->$key = $value;

		self::assertEquals( 2, $this->writer->insert( $entity ) );
		self::assertEquals( 2, $this->reader->count() );

		$this->writer->focusPrimary( 2 );
		/** @var array|NULL $result */
		$result		= $this->reader->focusPrimary( 2 )->get();
		self::assertNotNull( $result );

		/** @var array $result */
		$actual		= array_slice( $result, 1, 2 );
		self::assertEquals( $data, $actual );

		$this->writer->focusIndex( 'topic', 'insert' );
		$actual		= $this->writer->insert( ['label' => 'insertTest2'] );
		self::assertEquals( 3, $actual );

		$this->writer->defocus();
		self::assertEquals( 3, $this->reader->count() );

		$results	= $this->reader->find( ['label'] );
		$expected	= ['label' => 'insertTest2'];
		$actual		= array_pop( $results );
		self::assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'insert'.
	 *	@access		public
	 *	@return		void
	 */
	public function testInsert_withStdClassObject()
	{
		$data	= ['topic' => 'insert', 'label' => 'insertTest'];

		self::assertEquals( 2, $this->writer->insert( (object) $data ) );
		self::assertEquals( 2, $this->reader->count() );

		$this->writer->focusPrimary( 2 );
		$this->reader->focusPrimary( 2 );
		/** @var array|NULL $result */
		$result		= $this->reader->get();
		self::assertNotNull( $result );

		/** @var array $result */
		$actual		= array_slice( $result, 1, 2 );
		self::assertEquals( $data, $actual );

		$this->writer->focusIndex( 'topic', 'insert' );
		$actual		= $this->writer->insert( ['label' => 'insertTest2'] );
		self::assertEquals( 3, $actual );

		$this->writer->defocus();
		self::assertEquals( 3, $this->reader->count() );

		$results	= $this->reader->find( ['label'] );
		$expected	= ['label' => 'insertTest2'];
		$actual		= array_pop( $results );
		self::assertEquals( $expected, $actual );
	}

	/**
	 *	Tests Method 'update'.
	 *	@access		public
	 *	@return		void
	 */
	public function testUpdatePrimary()
	{
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('update','updateTest1');" );
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('update','updateTest2');" );
		$this->writer->focusPrimary( 2 );
		$this->reader->focusPrimary( 2 );

		$data		= ['label' => "updateTest1-changed"];

		self::assertEquals( 1, $this->writer->update( $data ) );

		$expected	= ['label' => "updateTest1-changed"];
		$actual		= $this->reader->find( ['label'], ['id' => 2] );
		self::assertEquals( $expected, end( $actual ) );
	}

	/**
	 *	Tests Method 'update'.
	 *	@access		public
	 *	@return		void
	 */
	public function testUpdateIndex()
	{
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('update','updateTest1');" );
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('update','updateTest2');" );
		$this->writer->focusIndex( 'topic', 'update' );
		$this->reader->focusIndex( 'topic', 'update' );

		$data		= ['label' => "changed"];

		self::assertEquals( 2, $this->writer->update( $data ) );

		$this->writer->focusIndex( 'label', 'changed' );
		$this->reader->focusIndex( 'label', 'changed' );
		/** @var array $result */
		$result		= $this->reader->get( FALSE );
		self::assertCount( 2, $result );
	}

	/**
	 *	Tests Exception of Method 'update'.
	 *	@access		public
	 *	@return		void
	 */
	public function testUpdateException1()
	{
		$this->expectException( 'InvalidArgumentException' );
		$this->writer->update( [] );
	}

	/**
	 *	Tests Exception of Method 'update'.
	 *	@access		public
	 *	@return		void
	 */
	public function testUpdateException2()
	{
		$this->expectException( 'InvalidArgumentException' );
		$this->writer->focusPrimary( 9999 );
		$this->writer->update( ['label' => 'not_relevant'] );
	}

	/**
	 *	Tests Method 'updateByConditions'.
	 *	@access		public
	 *	@return		void
	 */
	public function testUpdateByConditions()
	{
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('update','updateTest1');" );
		$this->connection->query( "INSERT INTO transactions (topic,label) VALUES ('update','updateTest2');" );

		$conditions	= ['label' => "updateTest1"];
		$data		= ['label' => "updateTest1-changed"];

		$wrongData	= ['invalid_column' => 'not_important'];
		$actual		= $this->writer->updateByConditions( $wrongData, $conditions );
		self::assertEquals( 0, $actual );

		$actual		= $this->writer->updateByConditions( $data, $conditions );
		self::assertEquals( 1, $actual );

		$expected	= ['label' => "updateTest1-changed"];
		$actual		= $this->reader->find( ['label'], ['id' => 2] );
		self::assertEquals( $expected, end( $actual ) );

		$conditions	= ['topic' => "update"];
		$data		= ['label' => "changed"];

		$actual		= $this->writer->updateByConditions( $data, $conditions );
		self::assertEquals( 2, $actual );

		$this->reader->focusIndex( 'label', 'changed' );
		/** @var array $result */
		$result		= $this->reader->get( FALSE );
		self::assertCount( 2, $result );
	}

	/**
	 *	Tests Exception on Method 'updateByConditions'.
	 *	@access		public
	 *	@return		void
	 */
	public function testUpdateByConditionsException1()
	{
		$this->expectException( 'InvalidArgumentException' );
		$this->writer->updateByConditions( [], ['label' => 'not_relevant'] );
	}

	/**
	 *	Tests Exception on Method 'updateByConditions'.
	 *	@access		public
	 *	@return		void
	 */
	public function testUpdateByConditionsException2()
	{
		$this->expectException( 'InvalidArgumentException' );
		$this->writer->updateByConditions( ['label' => 'not_relevant'], [] );
	}

	/**
	 *	Tests Method 'truncate'.
	 *	@access		public
	 *	@return		void
	 */
	public function testTruncate()
	{
		$this->connection->query( "INSERT INTO transactions (topic, label) VALUES ('test', 'truncateTest');" );
		self::assertEquals( 2, $this->reader->count() );
		self::assertEquals( $this->writer, $this->writer->truncate() );
		self::assertEquals( 0, $this->reader->count() );
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

		$this->createTransactionsTableFromFileOnDirectConnection();

		$this->reader	= new PdoTableReader( $this->connection, $this->tableName, $this->columns, $this->primaryKey );
		$this->reader->setIndices( $this->indices );

		$this->writer	= new PdoTableWriter( $this->connection, $this->tableName, $this->columns, $this->primaryKey );
		$this->writer->setIndices( $this->indices );
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
