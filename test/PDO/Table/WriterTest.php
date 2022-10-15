<?php /** @noinspection SqlResolve */
/** @noinspection SqlNoDataSourceInspection */

/**
 *	TestUnit of DB_PDO_TableWriter.
 *	@package		Tests.{classPackage}
 *	@author			Christian WÃ¼rker <christian.wuerker@ceusmedia.de>
 *	@since			02.05.2008
 *	@version		0.1
 */

namespace CeusMedia\DatabaseTest\PDO\Table;

use CeusMedia\Database\PDO\Connection as PdoConnection;
use CeusMedia\Database\PDO\Table\Writer as PdoTableWriter;
use CeusMedia\DatabaseTest\PDO\TestCase;

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
	protected PdoTableWriter $writer;

	/**
	 *	Constructor.
	 *	@access		public
	 *	@return		void
	 */
	public function __construct()
	{
		parent::__construct();

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
		parent::setUp();

		$this->writer	= new PdoTableWriter( $this->connection, $this->tableName, $this->columns, $this->primaryKey );
		$this->writer->setIndices( $this->indices );
	}

	/**
	 *	Cleanup after every Test.
	 *	@access		public
	 *	@return		void
	 */
	public function tearDown(): void
	{
		parent::tearDown();
	}

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

		self::assertEquals( 4, $this->writer->count() );

		$this->writer->focusPrimary( 4 );
		self::assertEquals( 1, $this->writer->delete() );

		$this->writer->defocus();
		self::assertEquals( 3, $this->writer->count() );

		$actual		= count( $this->writer->find( [], ['label' => 'deleteTest'] ) );
		self::assertEquals( 2, $actual );

		$this->writer->focusIndex( 'label', 'deleteTest' );
		self::assertEquals( 2, $this->writer->delete() );

		$this->writer->defocus();
		self::assertEquals( 1, $this->writer->count() );

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

		self::assertEquals( 4, $this->writer->count() );

		$actual		= $this->writer->deleteByConditions( array( 'label' => 'deleteTest' ) );
		self::assertEquals( 3, $actual );

		self::assertEquals( 1, $this->writer->count() );
	}

	/**
	 *	Tests Method 'insert'.
	 *	@access		public
	 *	@return		void
	 */
	public function testInsert()
	{
		$data	= array(
			'topic'	=> 'insert',
			'label'	=> 'insertTest',
		);

		self::assertEquals( 2, $this->writer->insert( $data ) );
		self::assertEquals( 2, $this->writer->count() );

		$this->writer->focusPrimary( 2 );
		$actual		= array_slice( $this->writer->get( TRUE ), 1, 2 );
		self::assertEquals( $data, $actual );

		$this->writer->focusIndex( 'topic', 'insert' );
		$actual		= $this->writer->insert( array( 'label' => 'insertTest2' ) );
		self::assertEquals( 3, $actual );

		$this->writer->defocus();
		self::assertEquals( 3, $this->writer->count() );

		$results	= $this->writer->find( array( 'label' ) );
		$expected	= array( 'label' => 'insertTest2' );
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

		$data		= array(
			'label'	=> "updateTest1-changed"
		);

		self::assertEquals( 1, $this->writer->update( $data ) );

		$expected	= array( 'label' => "updateTest1-changed" );
		$actual		= $this->writer->find( array( 'label' ), array( 'id' => 2 ) );
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

		$data		= array(
			'label'	=> "changed"
		);

		self::assertEquals( 2, $this->writer->update( $data ) );

		$this->writer->focusIndex( 'label', 'changed' );
		/** @var array $result */
		$result		= $this->writer->get( FALSE );
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
		$this->writer->updateByConditions( [] );
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
		$this->writer->update( array( 'label' => 'not_relevant' ));
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

		$conditions	= array(
			'label' => "updateTest1"
		);
		$data		= array(
			'label'	=> "updateTest1-changed"
		);

		$expected	= 0;
		$wrongData	= array( 'invalid_column' => 'not_important' );
		$actual		= $this->writer->updateByConditions( $wrongData, $conditions );
		self::assertEquals( $expected, $actual );

		$expected	= 1;
		$actual		= $this->writer->updateByConditions( $data, $conditions );
		self::assertEquals( $expected, $actual );

		$expected	= array( 'label' => "updateTest1-changed" );
		$actual		= $this->writer->find( array( 'label' ), array( 'id' => 2 ) );
		self::assertEquals( $expected, end( $actual ) );

		$conditions	= array(
			'topic' => "update"
		);
		$data		= array(
			'label'	=> "changed"
		);

		$expected	= 2;
		$actual		= $this->writer->updateByConditions( $data, $conditions );
		self::assertEquals( $expected, $actual );

		$this->writer->focusIndex( 'label', 'changed' );
		/** @var array $result */
		$result		= $this->writer->get( FALSE );
		self::assertCount( 2, $result );
	}

	/**
	 *	Tests Exception of Method 'updateByConditions'.
	 *	@access		public
	 *	@return		void
	 */
	public function testUpdateByConditionsException1()
	{
		$this->expectException( 'InvalidArgumentException' );
		$this->writer->updateByConditions( [], array( 'label' => 'not_relevant' ) );
	}

	/**
	 *	Tests Exception of Method 'updateByConditions'.
	 *	@access		public
	 *	@return		void
	 */
	public function testUpdateByConditionsException2()
	{
		$this->expectException( 'InvalidArgumentException' );
		$this->writer->updateByConditions( array( 'label' => 'not_relevant' ), [] );
	}

	/**
	 *	Tests Method 'truncate'.
	 *	@access		public
	 *	@return		void
	 */
	public function testTruncate()
	{
		$this->connection->query( "INSERT INTO transactions (topic, label) VALUES ('test', 'truncateTest');" );

		$expected	= 2;
		$actual		= $this->writer->count();
		self::assertEquals( $expected, $actual );

		$expected	= $this->writer;
		$actual		= $this->writer->truncate();
		self::assertEquals( $expected, $actual );

		$expected	= 0;
		$actual		= $this->writer->count();
		self::assertEquals( $expected, $actual );
	}
}
