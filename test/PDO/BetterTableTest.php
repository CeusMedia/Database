<?php
/** @noinspection SqlResolve */
/** @noinspection SqlNoDataSourceInspection */
declare(strict_types=1);

/**
 *	TestUnit of PDO BetterTable.
 *	@package		Tests.database.pdo
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 */

namespace CeusMedia\DatabaseTest\PDO;

use CeusMedia\Cache\Adapter\Memory as MemoryCache;
use CeusMedia\Common\Exception\Deprecation;
use CeusMedia\Database\PDO\BetterTable as PdoBetterTable;
use DomainException;
use InvalidArgumentException;
use PDO;
use RangeException;
use RuntimeException;

/**
 *	TestUnit of PDO BetterTable.
 *	@package		Tests.database.pdo
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 */
class BetterTableTest extends TestCase
{
	protected BetterTransactionTable $table;

	//  --  CONSTRUCTION & SETUP  --  //

	public function testConstructThrowsExceptionOnMissingTableName(): void
	{
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'No table name set' );
		new BetterTableWithNoName( $this->connection );
	}

	public function testConstructThrowsExceptionOnMissingColumns(): void
	{
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'No table columns set' );
		new BetterTableWithNoColumns( $this->connection );
	}

	public function testConstructWithPrefixAndFocusedId(): void
	{
		$table	= new BetterTransactionTable( $this->connection, 'pre_', 1 );
		self::assertEquals( 'pre_transactions', $table->getName() );
		self::assertEquals( 'transactions', $table->getName( FALSE ) );
	}

	public function testConstructAppliesPresetFetchEntityClass(): void
	{
		$table	= new BetterTransactionTableWithPresetFetchEntityClass( $this->connection );
		$result	= $table->get( 1 );
		self::assertInstanceOf( AdvancedTransactionEntity::class, $result );
		self::assertEquals( 'start', $result->topic );
	}

	public function testConstructAppliesPresetFetchEntityObject(): void
	{
		$table	= new BetterTransactionTableWithPresetFetchEntityObject( $this->connection );
		$result	= $table->get( 1 );
		self::assertInstanceOf( AdvancedTransactionEntity::class, $result );
		self::assertEquals( 'start', $result->topic );
	}

	public function testSetupCacheReusesPresetCacheInstance(): void
	{
		$cache	= new MemoryCache( NULL );
		PdoBetterTable::$cacheInstance	= $cache;
		try{
			$table	= new BetterTransactionTable( $this->connection );
			$table->get( 1 );
			self::assertTrue( $cache->has( 'db.transactions.1' ) );
		}
		finally{
			PdoBetterTable::$cacheInstance	= NULL;
		}
	}

	public function testSetCache(): void
	{
		self::assertSame( $this->table, $this->table->setCache( new MemoryCache( NULL ) ) );
	}

	//  --  COUNTING  --  //

	public function testCount(): void
	{
		self::assertEquals( 1, $this->table->count() );
		$this->table->add( ['topic' => 'stop', 'label' => 'label1'] );
		self::assertEquals( 2, $this->table->count() );
		self::assertEquals( 1, $this->table->count( ['topic' => 'stop'] ) );
		self::assertEquals( 0, $this->table->count( ['topic' => 'invalid'] ) );
	}

	public function testCountByIndex(): void
	{
		$this->table->add( ['topic' => 'stop', 'label' => 'label1'] );
		$this->table->add( ['topic' => 'stop', 'label' => 'label2'] );
		self::assertEquals( 0, $this->table->countByIndex( 'topic', 'invalid' ) );
		self::assertEquals( 1, $this->table->countByIndex( 'topic', 'start' ) );
		self::assertEquals( 2, $this->table->countByIndex( 'topic', 'stop' ) );
		self::assertEquals( 3, $this->table->countByIndex( 'topic', ['start', 'stop'] ) );
	}

	public function testCountByIndices(): void
	{
		$this->table->add( ['topic' => 'stop', 'label' => 'label1'] );
		$this->table->add( ['topic' => 'stop', 'label' => 'label2'] );
		self::assertEquals( 2, $this->table->countByIndices( ['topic' => 'stop'] ) );
		self::assertEquals( 1, $this->table->countByIndices( ['topic' => 'stop', 'label' => 'label1'] ) );
	}

	public function testCountFast(): void
	{
		self::assertEquals( 1, $this->table->countFast( [] ) );
		$this->table->add( ['topic' => 'stop', 'label' => 'label1'] );
		self::assertEquals( 2, $this->table->countFast( [] ) );
	}

	//  --  WRITING  --  //

	public function testAdd(): void
	{
		$id	= $this->table->add( ['topic' => 'stop', 'label' => '<b>bold</b>'] );
		self::assertEquals( 2, $id );
		self::assertEquals( 'bold', $this->table->get( $id )->label );

		$id	= $this->table->add( ['topic' => 'stop', 'label' => '<b>bold</b>'], FALSE );
		self::assertEquals( '<b>bold</b>', $this->table->get( $id )->label );
	}

	public function testAddStoresNewEntryInCache(): void
	{
		$cache	= new MemoryCache( NULL );
		$this->table->setCache( $cache );
		$id	= $this->table->add( ['topic' => 'stop', 'label' => 'cached'] );
		self::assertTrue( $cache->has( 'db.transactions.'.$id ) );
	}

	public function testEdit(): void
	{
		$id	= $this->table->add( ['topic' => 'stop', 'label' => 'label1'] );
		self::assertEquals( 1, $this->table->edit( $id, ['label' => 'label2'] ) );
		self::assertEquals( 'label2', $this->table->get( $id )->label );
		self::assertEquals( 0, $this->table->edit( 999, ['label' => 'label3'] ), 'not existing' );
	}

	public function testEditInvalidatesCache(): void
	{
		$cache	= new MemoryCache( NULL );
		$this->table->setCache( $cache );
		self::assertEquals( 'start', $this->table->get( 1 )->topic );
		self::assertTrue( $cache->has( 'db.transactions.1' ) );

		$this->table->edit( 1, ['topic' => 'changed'] );
		self::assertFalse( $cache->has( 'db.transactions.1' ) );
		self::assertEquals( 'changed', $this->table->get( 1 )->topic );
	}

	public function testEditByIndices(): void
	{
		$this->table->add( ['topic' => 'start', 'label' => 'label1'] );
		self::assertEquals( 2, $this->table->editByIndices( ['topic' => 'start'], ['label' => 'label3'] ) );
		self::assertEquals( 2, $this->table->countByIndices( ['topic' => 'start', 'label' => 'label3'] ) );
	}

	public function testEditByIndicesExceptionOnEmptyIndices(): void
	{
		$this->expectException( RangeException::class );
		$this->table->editByIndices( [], ['label' => 'x'] );
	}

	public function testEditByIndicesExceptionOnInvalidIndex(): void
	{
		$this->expectException( DomainException::class );
		$this->table->editByIndices( ['label' => 'x'], ['label' => 'y'] );
	}

	public function testRemove(): void
	{
		$id	= $this->table->add( ['topic' => 'stop', 'label' => 'label1'] );
		self::assertTrue( $this->table->remove( $id ) );
		self::assertFalse( $this->table->remove( $id ), 'already removed' );
		self::assertEquals( 1, $this->table->count() );
	}

	public function testRemoveByIndex(): void
	{
		$this->table->add( ['topic' => 'stop', 'label' => 'label1'] );
		$this->table->add( ['topic' => 'stop', 'label' => 'label2'] );
		self::assertEquals( 0, $this->table->removeByIndex( 'topic', 'invalid' ) );
		self::assertEquals( 2, $this->table->removeByIndex( 'topic', 'stop' ) );
		self::assertEquals( 1, $this->table->count() );
	}

	public function testRemoveByIndexInvalidatesCache(): void
	{
		$cache	= new MemoryCache( NULL );
		$this->table->setCache( $cache );
		$id	= $this->table->add( ['topic' => 'stop', 'label' => 'label1'] );
		self::assertTrue( $cache->has( 'db.transactions.'.$id ) );
		$this->table->removeByIndex( 'topic', 'stop' );
		self::assertFalse( $cache->has( 'db.transactions.'.$id ) );
	}

	public function testRemoveByIndexWithArrayFetchMode(): void
	{
		$this->table->setFetchMode( PDO::FETCH_ASSOC );
		$this->table->add( ['topic' => 'stop', 'label' => 'label1'] );
		self::assertEquals( 1, $this->table->removeByIndex( 'topic', 'stop' ) );
	}

	public function testRemoveByIndices(): void
	{
		$this->table->add( ['topic' => 'stop', 'label' => 'label1'] );
		$this->table->add( ['topic' => 'stop', 'label' => 'label2'] );
		self::assertEquals( 0, $this->table->removeByIndices( ['topic' => 'invalid'] ) );
		self::assertEquals( 2, $this->table->removeByIndices( ['topic' => 'stop'] ) );
		self::assertEquals( 1, $this->table->count() );
	}

	public function testRemoveByIndicesExceptionOnEmptyIndices(): void
	{
		$this->expectException( RangeException::class );
		$this->table->removeByIndices( [] );
	}

	public function testSave(): void
	{
		$this->table->setFetchMode( PDO::FETCH_CLASS );
		$this->table->setFetchEntityClass( TransactionEntity::class );
		/** @var TransactionEntity $entity */
		$entity	= $this->table->get( 1 );
		$entity->label	= 'saved';
		self::assertTrue( $this->table->save( $entity ) );
		self::assertEquals( 'saved', $this->table->get( 1 )->label );
	}

	public function testSaveExceptionOnMismatchedFetchEntityClass(): void
	{
		$this->table->setFetchEntityClass( TransactionEntity::class );
		$this->expectException( InvalidArgumentException::class );
		$this->table->save( new AdvancedTransactionEntity() );
	}

	public function testSaveExceptionOnMismatchedFetchEntityObject(): void
	{
		$this->table->setFetchEntityObject( new AdvancedTransactionEntity() );
		$this->expectException( InvalidArgumentException::class );
		$this->table->save( new TransactionEntity() );
	}

	public function testTruncate(): void
	{
		$this->table->add( ['topic' => 'stop', 'label' => 'label1'] );
		self::assertSame( $this->table, $this->table->truncate() );
		self::assertEquals( 0, $this->table->count() );
	}

	//  --  READING  --  //

	public function testGet(): void
	{
		$result	= $this->table->get( 1 );
		self::assertIsObject( $result );
		self::assertEquals( 'start', $result->topic );
		self::assertNull( $this->table->get( 999 ) );
	}

	public function testGetServesCachedValueOnSecondCall(): void
	{
		$this->table->setCache( new MemoryCache( NULL ) );
		self::assertEquals( 'start', $this->table->get( 1 )->topic );

		//  change bypassing the table, so only the cache knows the old value
		$this->connection->query( "UPDATE transactions SET topic='changed' WHERE id=1" );
		self::assertEquals( 'start', $this->table->get( 1 )->topic );
	}

	public function testGetAll(): void
	{
		$this->table->add( ['topic' => 'stop', 'label' => 'label1'] );
		$this->table->add( ['topic' => 'stop', 'label' => 'label2'] );

		self::assertCount( 3, $this->table->getAll() );
		self::assertCount( 2, $this->table->getAll( ['topic' => 'stop'] ) );

		$result	= $this->table->getAll( [], ['id' => 'DESC'], [0, 1] );
		self::assertCount( 1, $result );
		self::assertEquals( 3, $result[0]->id );
	}

	public function testGetAllExceptionOnFields(): void
	{
		$this->expectException( Deprecation::class );
		$this->table->getAll( [], [], [], ['id'] );
	}

	public function testGetAllByIndex(): void
	{
		$this->table->add( ['topic' => 'stop', 'label' => 'label1'] );
		$this->table->add( ['topic' => 'stop', 'label' => 'label2'] );

		self::assertCount( 2, $this->table->getAllByIndex( 'topic', 'stop' ) );
		self::assertCount( 0, $this->table->getAllByIndex( 'topic', 'invalid' ) );
		self::assertCount( 1, $this->table->getAllByIndex( 'id', 1 ), 'primary key counts as index' );

		$result	= $this->table->getAllByIndex( 'topic', 'stop', ['id' => 'DESC'], [0, 1] );
		self::assertCount( 1, $result );
		self::assertEquals( 'label2', $result[0]->label );
	}

	public function testGetAllByIndexExceptionOnFields(): void
	{
		$this->expectException( Deprecation::class );
		$this->table->getAllByIndex( 'topic', 'stop', [], [], ['id'] );
	}

	public function testGetAllByIndexExceptionOnInvalidIndex(): void
	{
		$this->expectException( DomainException::class );
		$this->table->getAllByIndex( 'label', 'x' );
	}

	public function testGetAllByIndices(): void
	{
		$this->table->add( ['topic' => 'stop', 'label' => 'label1'] );
		$this->table->add( ['topic' => 'stop', 'label' => 'label2'] );

		self::assertCount( 2, $this->table->getAllByIndices( ['topic' => 'stop'] ) );
		self::assertCount( 1, $this->table->getAllByIndices( ['topic' => 'stop', 'id' => 2] ) );
	}

	public function testGetAllByIndicesExceptionOnFields(): void
	{
		$this->expectException( Deprecation::class );
		$this->table->getAllByIndices( ['topic' => 'stop'], [], [], ['id'] );
	}

	public function testGetAllByIndicesExceptionOnEmptyIndices(): void
	{
		$this->expectException( RangeException::class );
		$this->table->getAllByIndices();
	}

	public function testGetByIndex(): void
	{
		$this->table->add( ['topic' => 'stop', 'label' => 'label1'] );
		$this->table->add( ['topic' => 'stop', 'label' => 'label2'] );

		self::assertEquals( 'label1', $this->table->getByIndex( 'topic', 'stop' )->label );
		self::assertEquals( 'label2', $this->table->getByIndex( 'topic', 'stop', ['id' => 'DESC'] )->label );
		self::assertNull( $this->table->getByIndex( 'topic', 'invalid' ) );
	}

	public function testGetByIndexExceptionOnInvalidIndex(): void
	{
		$this->expectException( DomainException::class );
		$this->table->getByIndex( 'label', 'x' );
	}

	public function testGetByIndices(): void
	{
		$this->table->add( ['topic' => 'stop', 'label' => 'label1'] );
		$this->table->add( ['topic' => 'stop', 'label' => 'label2'] );

		self::assertEquals( 'label2', $this->table->getByIndices( ['topic' => 'stop', 'id' => 3] )->label );
		self::assertEquals( 'label2', $this->table->getByIndices( ['topic' => 'stop'], ['id' => 'DESC'] )->label );
		self::assertNull( $this->table->getByIndices( ['topic' => 'invalid'] ) );
	}

	public function testGetByIndicesExceptionOnEmptyIndices(): void
	{
		$this->expectException( RangeException::class );
		$this->table->getByIndices( [] );
	}

	public function testGetColumnOfAllByConditions(): void
	{
		$this->table->add( ['topic' => 'stop', 'label' => 'label1'] );
		$this->table->add( ['topic' => 'stop', 'label' => 'label2'] );

		$result	= $this->table->getColumnOfAllByConditions( 'label', ['topic' => 'stop'], ['id' => 'DESC'] );
		self::assertSame( ['label2', 'label1'], $result );
		self::assertSame( [], $this->table->getColumnOfAllByConditions( 'label', ['topic' => 'invalid'] ) );
	}

	public function testGetColumnOfAllByConditionsExceptionOnInvalidColumn(): void
	{
		$this->expectException( DomainException::class );
		$this->table->getColumnOfAllByConditions( 'invalid' );
	}

	public function testGetColumnsOfAllByConditions(): void
	{
		$this->table->add( ['topic' => 'stop', 'label' => 'label1'] );

		$result	= $this->table->getColumnsOfAllByConditions( ['id', 'label'], ['topic' => 'stop'] );
		self::assertCount( 1, $result );
		self::assertEquals( (object) ['id' => 2, 'label' => 'label1'], $result[0] );
	}

	public function testGetColumnsOfAllByConditionsWithAlias(): void
	{
		$result	= $this->table->getColumnsOfAllByConditions( ['id', 'label AS title'], ['id' => 1] );
		self::assertEquals( (object) ['id' => 1, 'title' => '1210847252'], $result[0] );
	}

	public function testGetColumnsOfAllByConditionsWithArrayFetchMode(): void
	{
		$this->table->setFetchMode( PDO::FETCH_ASSOC );
		$result	= $this->table->getColumnsOfAllByConditions( ['id', 'topic'], ['id' => 1] );
		self::assertSame( [['id' => 1, 'topic' => 'start']], $result );
	}

	public function testGetColumnsOfAllByConditionsWithWildcardExpandsToAllColumns(): void
	{
		$this->table->setFetchMode( PDO::FETCH_ASSOC );
		$result	= $this->table->getColumnsOfAllByConditions( ['*'], ['id' => 1] );
		self::assertSame( ['id', 'topic', 'label', 'timestamp'], array_keys( $result[0] ) );
	}

	public function testGetColumnsOfAllByConditionsExceptionOnAliasCollidingWithColumn(): void
	{
		$this->expectException( DomainException::class );
		$this->table->getColumnsOfAllByConditions( ['id', 'label AS topic'], ['id' => 1] );
	}

	public function testGetDistinct(): void
	{
		$this->table->add( ['topic' => 'stop', 'label' => 'label1'] );
		$this->table->add( ['topic' => 'stop', 'label' => 'label2'] );

		$result	= $this->table->getDistinct( 'topic', [], ['topic' => 'ASC'] );
		self::assertSame( ['start', 'stop'], $result );
		self::assertSame( ['stop'], $this->table->getDistinct( 'topic', ['id' => '> 1'] ) );
	}

	public function testGetField(): void
	{
		self::assertEquals( 'start', $this->table->getField( 1, 'topic' ) );
		self::assertNull( $this->table->getField( 999, 'topic' ), 'not existing, not strict' );
	}

	public function testGetFieldExceptionOnInvalidField(): void
	{
		$this->expectException( DomainException::class );
		$this->table->getField( 1, 'invalid' );
	}

	public function testGetFieldWithArrayFetchMode(): void
	{
		$this->table->setFetchMode( PDO::FETCH_ASSOC );
		self::assertEquals( 'start', $this->table->getField( 1, 'topic' ) );
	}

	public function testGetFirstFieldByIndex(): void
	{
		$this->table->add( ['topic' => 'stop', 'label' => 'label1'] );
		$this->table->add( ['topic' => 'stop', 'label' => 'label2'] );

		self::assertEquals( 'label1', $this->table->getFirstFieldByIndex( 'label', 'topic', 'stop' ) );
		self::assertEquals( 'label2', $this->table->getFirstFieldByIndex( 'label', 'topic', 'stop', ['id' => 'DESC'] ) );
		self::assertNull( $this->table->getFirstFieldByIndex( 'label', 'topic', 'invalid' ) );
	}

	public function testGetFirstFieldByIndexExceptionOnEmptyResultWhenStrict(): void
	{
		$this->expectException( RangeException::class );
		$this->table->getFirstFieldByIndex( 'label', 'topic', 'invalid', [], TRUE );
	}

	public function testGetFirstFieldByIndexExceptionOnEmptyField(): void
	{
		$this->expectException( RangeException::class );
		$this->table->getFirstFieldByIndex( ' ', 'topic', 'stop' );
	}

	public function testGetFirstFieldsByIndex(): void
	{
		$this->table->add( ['topic' => 'stop', 'label' => 'label1'] );

		$result	= $this->table->getFirstFieldsByIndex( ['id', 'label'], 'topic', 'stop' );
		self::assertEquals( (object) ['id' => 2, 'label' => 'label1'], $result );
		self::assertSame( [], $this->table->getFirstFieldsByIndex( ['id', 'label'], 'topic', 'invalid' ) );
	}

	public function testGetFirstFieldsByIndexExceptionOnTooFewFields(): void
	{
		$this->expectException( RangeException::class );
		$this->table->getFirstFieldsByIndex( ['id'], 'topic', 'stop' );
	}

	public function testGetFirstFieldsByIndexExceptionOnEmptyResultWhenStrict(): void
	{
		$this->expectException( RangeException::class );
		$this->table->getFirstFieldsByIndex( ['id', 'label'], 'topic', 'invalid', [], TRUE );
	}

	public function testGetFirstFieldByIndices(): void
	{
		$this->table->add( ['topic' => 'stop', 'label' => 'label1'] );
		$this->table->add( ['topic' => 'stop', 'label' => 'label2'] );

		self::assertEquals( 'label2', $this->table->getFirstFieldByIndices( 'label', ['topic' => 'stop', 'id' => 3] ) );
		self::assertEquals( 'label2', $this->table->getFirstFieldByIndices( 'label', ['topic' => 'stop'], ['id' => 'DESC'] ) );
		self::assertNull( $this->table->getFirstFieldByIndices( 'label', ['topic' => 'invalid'] ) );
	}

	public function testGetFirstFieldByIndicesExceptionOnInvalidField(): void
	{
		$this->expectException( DomainException::class );
		$this->table->getFirstFieldByIndices( 'invalid', ['topic' => 'stop'] );
	}

	public function testGetFirstFieldsByIndices(): void
	{
		$this->table->add( ['topic' => 'stop', 'label' => 'label1'] );

		$result	= $this->table->getFirstFieldsByIndices( ['id', 'label'], ['topic' => 'stop'] );
		self::assertEquals( (object) ['id' => 2, 'label' => 'label1'], $result );
		self::assertSame( [], $this->table->getFirstFieldsByIndices( ['id', 'label'], ['topic' => 'invalid'] ) );
	}

	public function testGetFirstFieldsByIndicesExceptionOnTooFewFields(): void
	{
		$this->expectException( RangeException::class );
		$this->table->getFirstFieldsByIndices( ['id'], ['topic' => 'stop'] );
	}

	public function testGetFirstFieldsByIndicesExceptionOnInvalidIndex(): void
	{
		$this->expectException( DomainException::class );
		$this->table->getFirstFieldsByIndices( ['id', 'label'], ['label' => 'x'] );
	}

	public function testHas(): void
	{
		self::assertTrue( $this->table->has( 1 ) );
		self::assertFalse( $this->table->has( 999 ) );
	}

	public function testHasUsesCache(): void
	{
		$this->table->setCache( new MemoryCache( NULL ) );
		$this->table->get( 1 );

		//  remove bypassing the table, so only the cache knows the entry
		$this->connection->query( "DELETE FROM transactions WHERE id=1" );
		self::assertTrue( $this->table->has( 1 ) );
	}

	public function testHasByIndex(): void
	{
		self::assertTrue( $this->table->hasByIndex( 'topic', 'start' ) );
		self::assertFalse( $this->table->hasByIndex( 'topic', 'invalid' ) );
	}

	public function testHasByIndices(): void
	{
		self::assertTrue( $this->table->hasByIndices( ['topic' => 'start', 'id' => 1] ) );
		self::assertFalse( $this->table->hasByIndices( ['topic' => 'start', 'id' => 2] ) );
	}

	//  --  META  --  //

	public function testGetColumns(): void
	{
		self::assertSame( ['id', 'topic', 'label', 'timestamp'], $this->table->getColumns() );
	}

	public function testGetIndices(): void
	{
		self::assertSame( ['topic'], $this->table->getIndices() );
	}

	public function testGetPrimaryKey(): void
	{
		self::assertSame( 'id', $this->table->getPrimaryKey() );
	}

	public function testGetLastQuery(): void
	{
		$this->table->count( ['topic' => 'start'] );
		self::assertStringContainsString( 'SELECT COUNT', (string) $this->table->getLastQuery() );
	}

	public function testGetSetFetchMode(): void
	{
		self::assertSame( PDO::FETCH_OBJ, $this->table->getFetchMode() );
		self::assertSame( $this->table, $this->table->setFetchMode( PDO::FETCH_ASSOC ) );
		self::assertSame( PDO::FETCH_ASSOC, $this->table->getFetchMode() );
		self::assertIsArray( $this->table->get( 1 ) );
	}

	public function testSetFetchEntityClass(): void
	{
		$this->table->setFetchMode( PDO::FETCH_CLASS );
		self::assertSame( $this->table, $this->table->setFetchEntityClass( TransactionEntity::class ) );
		self::assertInstanceOf( TransactionEntity::class, $this->table->get( 1 ) );
	}

	public function testSetFetchEntityObject(): void
	{
		$this->table->setFetchMode( PDO::FETCH_INTO );
		$entity	= new AdvancedTransactionEntity();
		self::assertSame( $this->table, $this->table->setFetchEntityObject( $entity ) );
		self::assertSame( $entity, $this->table->get( 1 ) );
		self::assertEquals( 'start', $entity->topic );
	}

	//  --  PROTECTED  --  //

	/**
	 *	checkField() and checkIndices() return FALSE or NULL instead of throwing, if not strict.
	 *	@access		public
	 *	@return		void
	 */
	public function testCheckFieldAndCheckIndicesInNonStrictMode(): void
	{
		$checkField		= new \ReflectionMethod( $this->table, 'checkField' );
		self::assertNull( $checkField->invoke( $this->table, ' ' ) );
		self::assertFalse( $checkField->invoke( $this->table, ' ', TRUE, FALSE ) );
		self::assertFalse( $checkField->invoke( $this->table, 'invalid', FALSE, FALSE ) );
		self::assertSame( 'topic', $checkField->invoke( $this->table, ' topic ' ) );

		$checkIndices	= new \ReflectionMethod( $this->table, 'checkIndices' );
		self::assertSame( [], $checkIndices->invoke( $this->table, [] ) );
		self::assertFalse( $checkIndices->invoke( $this->table, [], TRUE, FALSE ) );
		self::assertFalse( $checkIndices->invoke( $this->table, ['label' => 'x'], FALSE, FALSE ) );
		self::assertSame( ['topic' => 'x'], $checkIndices->invoke( $this->table, ['topic' => 'x'] ) );
	}

	/**
	 *	getFieldFromResult() and getFieldsFromResult() reject fields missing in the result set.
	 *	@access		public
	 *	@return		void
	 */
	public function testGetFieldsFromResultExceptionOnFieldMissingInResult(): void
	{
		$getField	= new \ReflectionMethod( $this->table, 'getFieldFromResult' );
		try{
			$getField->invoke( $this->table, (object) ['id' => 1], 'topic' );
			self::fail( 'Expected RangeException for object result' );
		}
		catch( RangeException ){}
		try{
			$getField->invoke( $this->table, ['id' => 1], 'topic' );
			self::fail( 'Expected RangeException for array result' );
		}
		catch( RangeException ){}

		$getFields	= new \ReflectionMethod( $this->table, 'getFieldsFromResult' );
		try{
			$getFields->invoke( $this->table, (object) ['id' => 1], ['id', 'topic'] );
			self::fail( 'Expected RangeException for object result' );
		}
		catch( RangeException ){}
		try{
			$getFields->invoke( $this->table, ['id' => 1], ['id', 'topic'] );
			self::fail( 'Expected RangeException for array result' );
		}
		catch( RangeException ){}
		try{
			$getFields->invoke( $this->table, ['id' => 1], ['id', 'invalid'] );
			self::fail( 'Expected DomainException for invalid column' );
		}
		catch( DomainException ){}
		self::assertSame( [], $getFields->invoke( $this->table, NULL, ['id', 'topic'], FALSE ) );
	}

	//  --  SETUP  --  //

	protected function setUp(): void
	{
		parent::setUp();
		$this->createTransactionsTableFromFileOnDirectConnection();
		$this->table	= new BetterTransactionTable( $this->connection );
	}

	protected function tearDown(): void
	{
		$this->dropTransactionsTable();
		parent::tearDown();
	}
}
