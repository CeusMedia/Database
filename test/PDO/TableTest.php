<?php
/**
 *	TestUnit of DB_PDO_TableReader.
 *	@package		Tests.database.pdo
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 */

namespace CeusMedia\DatabaseTest\PDO;

use CeusMedia\Database\PDO\Table as PdoTable;

/**
 *	TestUnit of DB_PDO_TableReader.
 *	@package		Tests.database.pdo
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 */
class TableTest extends TestCase
{
	protected array $columns;
	protected string $tableName;
	protected array $indices;
	protected string $primaryKey;
	protected PdoTable $table;

	public function testAdd(): void
	{
		$this->table->add( ['topic' => 'stop', 'label' => microtime( TRUE )] );
		self::assertEquals( 2, $this->table->count() );
	}

	public function testCount(): void
	{
		self::assertEquals( 1, $this->table->count() );
		$this->table->add( ['topic' => 'stop', 'label' => time()] );
		self::assertEquals( 2, $this->table->count() );
		$this->table->add( ['topic' => 'stop', 'label' => time()] );
		self::assertEquals( 3, $this->table->count() );
	}

	public function testCountByIndex(): void
	{
		$this->table->add( ['topic' => 'stop', 'label' => microtime( TRUE )] );
		$this->table->add( ['topic' => 'stop', 'label' => microtime( TRUE )] );

		self::assertEquals( 0, $this->table->countByIndex( 'topic', 'invalid' ) );
		self::assertEquals( 1, $this->table->countByIndex( 'topic', 'start' ) );
		self::assertEquals( 2, $this->table->countByIndex( 'topic', 'stop' ) );
	}

	public function testCountByIndices(): void
	{
		$this->table->add( ['topic' => 'stop', 'label' => 'label1'] );
		$this->table->add( ['topic' => 'stop', 'label' => 'label2'] );

		$indices	= ['topic' => 'invalid'];
		self::assertEquals( 0, $this->table->countByIndices( $indices ) );

		$indices	= ['topic' => 'stop'];
		self::assertEquals( 2, $this->table->countByIndices( $indices ) );

		$indices	= ['topic' => 'stop', 'label'	=> 'label1'];
		self::assertEquals( 1, $this->table->countByIndices( $indices ) );
	}

	public function testEdit(): void
	{
		$this->table->add( ['topic' => 'stop', 'label' => 'label1'] );

		$indices	= ['topic' => 'stop'];
		self::assertEquals( 1, $this->table->countByIndices( $indices ) );
		$indices	= ['topic' => 'stop', 'label'	=> 'label1'];
		self::assertEquals( 1, $this->table->countByIndices( $indices ) );

		self::assertEquals( 1, $this->table->edit( 2, ['label' => 'label3'] ) );

		$indices	= ['topic' => 'stop'];
		self::assertEquals( 1, $this->table->countByIndices( $indices ) );
		$indices	= ['topic' => 'stop', 'label'	=> 'label1'];
		self::assertEquals( 0, $this->table->countByIndices( $indices ) );
		$indices	= ['topic' => 'stop', 'label'	=> 'label3'];
		self::assertEquals( 1, $this->table->countByIndices( $indices ) );
	}

	public function testEditByIndices(): void
	{
		$this->table->add( ['topic' => 'start', 'label' => 'label1'] );

		$indices	= ['topic' => 'start'];
		self::assertEquals( 2, $this->table->countByIndices( $indices ) );
		$indices	= ['topic' => 'start', 'label' => 'label1'];
		self::assertEquals( 1, $this->table->countByIndices( $indices ) );

		$indices	= ['topic' => 'start'];
		self::assertEquals( 2, $this->table->editByIndices( $indices, ['label' => 'label3'] ) );
		self::assertEquals( 2, $this->table->countByIndices( $indices ) );
		$indices	= ['topic' => 'start', 'label' => 'label1'];
		self::assertEquals( 0, $this->table->countByIndices( $indices ) );
	}

	public function testGet(): void
	{
		$this->table->add( ['topic' => 'start', 'label' => 'label1'] );

		/** @var object|NULL $data */
		$data	= $this->table->get( 2 );
		unset( $data->timestamp );
		self::assertEquals( $data, (object) array(
			'id'	=> '2',
			'topic'	=> 'start',
			'label'	=> 'label1'
		) );

		$data	= $this->table->get( 2, 'label' );
		self::assertEquals( 'label1', $data );
	}

	public function testGetAll(): void
	{
		$this->table->add( ['topic' => 'start', 'label' => 'label1'] );
		$this->table->add( ['topic' => 'start', 'label' => 'label2'] );

		$results	= $this->table->getAll();
		self::assertCount( 3, $results );

		$conditions	= ['topic' => 'start'];
		self::assertCount( 3, $this->table->getAll( $conditions ) );

		$conditions	= ['topic' => 'start', 'label' => 'label1'];
		$results	= $this->table->getAll( $conditions );
		self::assertCount( 1, $results );

		$conditions	= ['topic' => 'start'];
		$orders		= ['label' => 'ASC'];
		$results	= $this->table->getAll( $conditions, $orders );
		self::assertCount( 3, $results );
		self::assertEquals( 'label1', $results[1]->label );

		$orders		= ['label' => 'DESC'];
		$results	= $this->table->getAll( $conditions, $orders );
		self::assertCount( 3, $results );
		self::assertEquals( 'label2', $results[0]->label );

		$limits		= [0, 1];
		$results	= $this->table->getAll( $conditions, $orders, $limits );
		self::assertCount( 1, $results );
		self::assertEquals( 'label2', $results[0]->label );

		$limits		= [1, 1];
		$results	= $this->table->getAll( $conditions, $orders, $limits );
		self::assertCount( 1, $results );
		self::assertEquals( 'label1', $results[0]->label );
	}

	public function testGetAllByIndex(): void
	{
		$this->table->add( ['topic' => 'start', 'label' => 'label1'] );
		$this->table->add( ['topic' => 'start', 'label' => 'label2'] );

		$results	= $this->table->getAllByIndex( 'topic', 'start' );
		self::assertCount( 3, $results );

		$orders		= ['label' => 'ASC'];
		$results	= $this->table->getAllByIndex( 'topic', 'start', $orders );
		self::assertCount( 3, $results );
		self::assertEquals( 'label1', $results[1]->label );

		$orders		= ['label' => 'DESC'];
		$results	= $this->table->getAllByIndex( 'topic', 'start', $orders );
		self::assertCount( 3, $results );
		self::assertEquals( 'label2', $results[0]->label );

		$limits		= [0, 1];
		$results	= $this->table->getAllByIndex( 'topic', 'start', $orders, $limits );
		self::assertCount( 1, $results );
		self::assertEquals( 'label2', $results[0]->label );

		$limits		= [1, 1];
		$results	= $this->table->getAllByIndex( 'topic', 'start', $orders, $limits );
		self::assertCount( 1, $results );
		self::assertEquals( 'label1', $results[0]->label );
	}

	public function testGetAllByIndices(): void
	{
		$this->table->add( ['topic' => 'start', 'label' => 'label1'] );
		$this->table->add( ['topic' => 'start', 'label' => 'label2'] );

		$indices	= ['topic' => 'start'];
		$results	= $this->table->getAllByIndices( $indices );
		self::assertCount( 3, $results );

		$orders		= ['label' => 'ASC'];
		$results	= $this->table->getAllByIndices( $indices, $orders );
		self::assertCount( 3, $results );
		self::assertEquals( 'label1', $results[1]->label );

		$orders		= ['label' => 'DESC'];
		$results	= $this->table->getAllByIndices( $indices, $orders );
		self::assertCount( 3, $results );
		self::assertEquals( 'label2', $results[0]->label );

		$limits		= [0, 1];
		$results	= $this->table->getAllByIndices( $indices, $orders, $limits );
		self::assertCount( 1, $results );
		self::assertEquals( 'label2', $results[0]->label );

		$limits		= [1, 1];
		$results	= $this->table->getAllByIndices( $indices, $orders, $limits );
		self::assertCount( 1, $results );
		self::assertEquals( 'label1', $results[0]->label );
	}

	public function testGetByIndex(): void
	{
		$this->table->remove( 1 );
		$this->table->add( ['topic' => 'start', 'label' => 'label1'] );
		$this->table->add( ['topic' => 'start', 'label' => 'label2'] );

		/** @var object|NULL $result */
		$result		= $this->table->getByIndex( 'topic', 'start' );
		unset( $result->timestamp );
		self::assertEquals( $result, (object) ['id' => 2, 'topic' => 'start', 'label' => 'label1'] );

		$orders		= ['label' => 'DESC'];
		/** @var object|NULL $result */
		$result		= $this->table->getByIndex( 'topic', 'start', $orders );
		unset( $result->timestamp );
		self::assertEquals( (object) ['id' => 3, 'topic' => 'start', 'label' => 'label2'], $result );

		$fields		= ['label'];
		$result		= $this->table->getByIndex( 'topic', 'start', $orders, $fields );
		self::assertEquals( 'label2', $result );

		$fields		= ['label', 'topic'];
		$result		= $this->table->getByIndex( 'topic', 'start', $orders, $fields );
		self::assertEquals( (object) ['label' => 'label2', 'topic' => 'start'], $result );
	}

	public function testGetByIndexException1(): void
	{
		$this->expectException( 'DomainException' );
		/** @var array $result */
		$result		= $this->table->getByIndex( 'label', 'label2' );
		unset( $result['timestamp'] );
		self::assertEquals( (object) ['id' => 3, 'topic' => 'start', 'label' => 'label2'], $result );
	}

	public function testGetByIndices(): void
	{
		$this->table->remove( 1 );
		$this->table->add( ['topic' => 'start', 'label' => 'label1'] );
		$this->table->add( ['topic' => 'start', 'label' => 'label2'] );

		$indices	= ['topic' => 'start'];
		/** @var object|NULL $result */
		$result		= $this->table->getByIndices( $indices );
		unset( $result->timestamp );
		$expected	= (object) ['id' => 2, 'topic' => 'start', 'label' => 'label1'];
		self::assertEquals( $expected, $result );

		$orders		= ['label' => 'DESC'];
		/** @var object|NULL $result */
		$result		= $this->table->getByIndices( $indices, $orders );
		unset( $result->timestamp );
		$expected	= (object) ['id' => 3, 'topic' => 'start', 'label' => 'label2'];
		self::assertEquals( $expected, $result );

		$fields		= ['label'];
		$result		= $this->table->getByIndices( $indices, $orders, $fields );
		self::assertEquals( 'label2', $result );

		$fields		= ['label', 'topic'];
		$result		= $this->table->getByIndices( $indices, $orders, $fields );
		$expected	= (object) ['label' => 'label2', 'topic' => 'start'];
		self::assertEquals( $expected, $result );
	}

	public function testGetByIndicesException1(): void
	{
		$this->expectException( 'DomainException' );

		$indices	= ['label' => 'label2'];
		/** @var object|NULL $result */
		$result		= $this->table->getByIndices( $indices );
		unset( $result->timestamp );
		$expected	= (object) ['id' => 3, 'topic' => 'start', 'label' => 'label2'];
		self::assertEquals( $expected, $result );
	}

	public function testGetColumns(): void
	{
		$expected	= ['id', 'topic', 'label', 'timestamp'];
		self::assertEquals( $expected, $this->table->getColumns() );
	}

	public function testGetIndices(): void
	{
		self::assertEquals( ['topic'], $this->table->getIndices() );
	}

	public function testGetName(): void
	{
		self::assertEquals( 'transactions', $this->table->getName() );
		/** @noinspection PhpRedundantOptionalArgumentInspection */
		self::assertEquals( 'transactions', $this->table->getName( TRUE ) );
	}

	public function testGetPrimaryKey(): void
	{
		self::assertEquals( 'id', $this->table->getPrimaryKey() );
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

		$this->table	= new TransactionTable( $this->connection );
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
