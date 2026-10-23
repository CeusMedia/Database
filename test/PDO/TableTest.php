<?php
/**
 *	TestUnit of DB_PDO_TableReader.
 *	@package		Tests.database.pdo
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 */

namespace CeusMedia\DatabaseTest\PDO;

use CeusMedia\Cache\Adapter\Memory as MemoryCache;
use CeusMedia\Database\PDO\Table as PdoTable;
use PDO;

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

	public function testCountFast(): void
	{
		self::assertEquals( 1, $this->table->countFast( [] ) );
		$this->table->add( ['topic' => 'stop', 'label' => 'countFastTest'] );
		self::assertEquals( 2, $this->table->countFast( [] ) );
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

	public function testEditSwallowsCacheInvalidArgumentException(): void
	{
		$this->table->setCache( new MemoryCache( NULL ) );
		self::assertEquals( 0, $this->table->edit( 'invalid key!', ['label' => 'x'] ) );
	}

	public function testEditByIndicesExceptionOnEmptyIndices(): void
	{
		$this->expectException( \RangeException::class );
		$this->table->editByIndices( [], ['label' => 'x'] );
	}

	public function testGet(): void
	{
		$this->table->add( ['topic' => 'start', 'label' => 'label1'] );

		/** @var object|NULL $data */
		$data	= $this->table->get( 2 );
		unset( $data->timestamp );
		self::assertEquals( $data, (object) [
			'id'	=> '2',
			'topic'	=> 'start',
			'label'	=> 'label1'
		] );

		$data	= $this->table->get( 2, 'label' );
		self::assertEquals( 'label1', $data );
	}

	public function testGetThrowsExceptionOnInvalidField(): void
	{
		$this->expectException( \DomainException::class );
		$this->table->get( 1, 'not_a_column' );
	}

	public function testGetThrowsExceptionOnEmptyResultWithField(): void
	{
		$this->expectException( \RangeException::class );
		$this->table->get( 999, 'topic' );
	}

	/**
	 *	Table::get() has a cache read and a cache write-back, both guarded
	 *	against SimpleCacheInvalidArgumentException. Using a real cache
	 *	(instead of the default no-op one) here proves both actually work,
	 *	and that a fetch is only served from the database once - the second
	 *	get() must return the cached (by now stale) value.
	 *	@access		public
	 *	@return		void
	 */
	public function testGetServesCachedValueOnSecondCall(): void
	{
		$this->table->setCache( new MemoryCache( NULL ) );

		$id		= $this->table->add( ['topic' => 'start', 'label' => 'cachedLabel'] );
		$first	= $this->table->get( $id );

		$this->connection->query( "UPDATE transactions SET label = 'changed-directly' WHERE id = $id" );

		$second	= $this->table->get( $id );
		self::assertEquals( $first->label, $second->label );
	}

	/**
	 *	Regression test: the cache read in get() must be guarded the same way
	 *	as the cache write-back and every other cache call in this class - an
	 *	invalid cache key must not bubble up as an uncaught exception, it must
	 *	just be treated as a cache miss.
	 *	@access		public
	 *	@return		void
	 */
	public function testGetSwallowsCacheInvalidArgumentExceptionOnRead(): void
	{
		$this->table->setCache( new MemoryCache( NULL ) );
		self::assertNull( $this->table->get( 'invalid key!' ) );
	}

	public function testSetCache(): void
	{
		self::assertSame( $this->table, $this->table->setCache( new MemoryCache( NULL ) ) );
	}

	public function testSetupCacheReusesPresetCacheInstance(): void
	{
		PdoTable::$cacheInstance	= new MemoryCache( NULL );
		try{
			$table	= new TransactionTable( $this->connection );
			$id		= $table->add( ['topic' => 'start', 'label' => 'presetInstanceLabel'] );
			$first	= $table->get( $id );

			$this->connection->query( "UPDATE transactions SET label = 'changed-directly' WHERE id = $id" );

			$second	= $table->get( $id );
			self::assertEquals( $first->label, $second->label, 'preset cacheInstance must be reused' );
		}
		finally{
			PdoTable::$cacheInstance	= NULL;
		}
	}

	public function testJsonColumns(): void
	{
		self::assertEquals( [], $this->table->getJsonColumns() );
		$this->table->setJsonColumns( ['label'] );
		self::assertEquals( ['label'], $this->table->getJsonColumns() );

		$data	= ['a' => 1, 'b' => ['c', 'd'], 'e' => NULL];
		$id		= $this->table->add( ['topic' => 'start', 'label' => $data] );

		//	fetch mode is FETCH_OBJ here, so the decoded value is an object, too
		/** @var object $result */
		$result	= $this->table->get( $id );
		self::assertEquals( (object) $data, $result->label );

		self::assertEquals( 1, $this->table->edit( $id, ['label' => (object) ['x' => 'y']] ) );
		/** @var object $result */
		$result	= $this->table->get( $id );
		self::assertEquals( (object) ['x' => 'y'], $result->label );
	}

	public function testJsonColumnsWithFetchClass(): void
	{
		$this->table->setJsonColumns( ['label'] );
		$this->table->setFetchEntityClass( JsonLabelEntity::class );
		$this->table->setFetchMode( \PDO::FETCH_CLASS );

		$data	= ['a' => 1, 'b' => ['c', 'd']];
		$id		= $this->table->add( ['topic' => 'start', 'label' => $data] );

		/** @var JsonLabelEntity $result */
		$result	= $this->table->get( $id );
		self::assertInstanceOf( JsonLabelEntity::class, $result );
		self::assertEquals( (object) $data, $result->label );
	}

	public function testJsonColumnsWithFetchClassViaFindAndSave(): void
	{
		$this->table->setJsonColumns( ['label'] );
		$this->table->setFetchEntityClass( JsonLabelEntity::class );
		$this->table->setFetchMode( \PDO::FETCH_CLASS );

		$data	= ['k' => 'v', 'n' => 2];
		$this->table->add( ['topic' => 'find-json', 'label' => $data] );

		//	find(): same decode path as get(), verified here explicitly
		$results	= $this->table->getAll( ['topic' => 'find-json'] );
		self::assertCount( 1, $results );
		/** @var JsonLabelEntity $result */
		$result	= $results[0];
		self::assertInstanceOf( JsonLabelEntity::class, $result );
		self::assertEquals( (object) $data, $result->label );

		//	set a (changed) object on the entity and persist it via save()
		$result->label	= (object) ['k' => 'changed'];
		self::assertTrue( $this->table->save( $result ) );

		$resultsAfter	= $this->table->getAll( ['topic' => 'find-json'] );
		self::assertEquals( (object) ['k' => 'changed'], $resultsAfter[0]->label );
	}

	public function testJsonColumnsWithMistypedEntityProperty(): void
	{
		$this->table->setJsonColumns( ['label'] );
		$this->table->setFetchEntityClass( JsonLabelEntityNarrowType::class );
		$this->table->setFetchMode( \PDO::FETCH_CLASS );

		//	Table::add() primes the cache via an internal get(), so the mistyped
		//	property already surfaces here, not only on an explicit later fetch
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/property "label" of class .*JsonLabelEntityNarrowType/' );
		$this->table->add( ['topic' => 'start', 'label' => ['a' => 1]] );
	}

	public function testGetDistinctWithJsonColumn(): void
	{
		$this->table->setJsonColumns( ['label'] );

		$this->table->add( ['topic' => 'distinct', 'label' => ['a' => 1]] );
		$this->table->add( ['topic' => 'distinct', 'label' => ['a' => 1]] );
		$this->table->add( ['topic' => 'distinct', 'label' => ['b' => 2]] );

		$values	= $this->table->getDistinct( 'label', ['topic' => 'distinct'] );

		self::assertCount( 2, $values );
		self::assertContainsEquals( ['a' => 1], $values );
		self::assertContainsEquals( ['b' => 2], $values );
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

	public function testGetAllWithFields(): void
	{
		$this->table->add( ['topic' => 'start', 'label' => 'label1'] );

		$results	= $this->table->getAll( ['topic' => 'start'], [], [], ['label'] );
		self::assertCount( 2, $results );
		self::assertContains( 'label1', $results );

		$results	= $this->table->getAll( ['topic' => 'start'], [], [], ['label', 'topic'] );
		self::assertCount( 2, $results );
		self::assertEquals( 'label1', $results[1]->label );
		self::assertEquals( 'start', $results[1]->topic );
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

	public function testGetAllByIndexExceptionOnInvalidIndex(): void
	{
		$this->expectException( \DomainException::class );
		$this->table->getAllByIndex( 'label', 'label1' );
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

	public function testGetAllByIndicesWithSingleField(): void
	{
		$results	= $this->table->getAllByIndices( ['topic' => 'start'], [], [], ['label'] );
		self::assertCount( 1, $results );
		self::assertEquals( '1210847252', $results[0] );
	}

	public function testGetAllByIndicesWithMultipleFields(): void
	{
		$results	= $this->table->getAllByIndices( ['topic' => 'start'], [], [], ['label', 'topic'] );
		self::assertCount( 1, $results );
		self::assertEquals( '1210847252', $results[0]->label );
		self::assertEquals( 'start', $results[0]->topic );
	}

	/**
	 *	Unlike getAll(), getAllByIndices() always fetches full rows and applies
	 *	field-filtering in PHP afterward, so an unselected/invalid field name
	 *	only surfaces here, not at the SQL level.
	 *	@access		public
	 *	@return		void
	 */
	public function testGetAllByIndicesExceptionOnInvalidField(): void
	{
		$this->expectException( \DomainException::class );
		$this->table->getAllByIndices( ['topic' => 'start'], [], [], ['not_a_column'] );
	}

	public function testGetAllByIndicesExceptionOnMultiFieldInvalidField(): void
	{
		$this->expectException( \DomainException::class );
		$this->table->getAllByIndices( ['topic' => 'start'], [], [], ['label', 'not_a_column'] );
	}

	/**
	 *	getFieldFromResult() (used here since a single field is requested) now
	 *	parses "X AS Y" aliases before validating against the column whitelist,
	 *	same as getFieldsFromResult() (used for 2+ fields) already did. Since
	 *	getAllByIndices() always fetches full rows (never passes fields to the
	 *	SQL query), an aliased target can never actually exist as a result
	 *	property here - so a single-field alias always fails, just at the
	 *	(correct) "not a column of result set" stage instead of failing on the
	 *	raw "field AS alias" string not being a whitelisted column.
	 *	@access		public
	 *	@return		void
	 */
	public function testGetAllByIndicesWithSingleAliasedFieldMissingFromResult(): void
	{
		$this->expectException( \RangeException::class );
		$this->expectExceptionMessage( 'Field "not_a_real_column" is not an column of result set' );
		$this->table->getAllByIndices( ['topic' => 'start'], [], [], ['topic AS not_a_real_column'] );
	}

	public function testGetAllByIndicesExceptionOnSingleFieldAliasCollidingWithColumn(): void
	{
		$this->expectException( \DomainException::class );
		$this->expectExceptionMessage( 'Field "topic AS label" is not possible since label is a column' );
		$this->table->getAllByIndices( ['topic' => 'start'], [], [], ['topic AS label'] );
	}

	/**
	 *	getAllByIndices() (unlike getByIndex()/getByIndices()) does not run
	 *	fields through checkField() first, so this is the only place a "*"
	 *	wildcard field (expanded to all columns) or a working "X AS Y" alias
	 *	(for 2+ fields, via getFieldsFromResult()) can actually be reached.
	 *	@access		public
	 *	@return		void
	 */
	public function testGetAllByIndicesWithWildcardFieldExpandsToAllColumns(): void
	{
		$results	= $this->table->getAllByIndices( ['topic' => 'start'], [], [], ['*', 'topic'] );
		self::assertCount( 1, $results );
		self::assertObjectHasProperty( 'id', $results[0] );
		self::assertObjectHasProperty( 'label', $results[0] );
		self::assertObjectHasProperty( 'topic', $results[0] );
	}

	public function testGetAllByIndicesExceptionOnMultiFieldAliasCollidingWithColumn(): void
	{
		$this->expectException( \DomainException::class );
		$this->table->getAllByIndices( ['topic' => 'start'], [], [], ['label', 'topic AS label'] );
	}

	public function testGetAllByIndicesExceptionOnMultiFieldAliasedFieldMissingFromResult(): void
	{
		$this->expectException( \RangeException::class );
		$this->table->getAllByIndices( ['topic' => 'start'], [], [], ['label', 'topic AS made_up_alias'] );
	}

	public function testGetAllByIndicesWithArrayFetchModeAndMultipleFields(): void
	{
		$this->table->setFetchMode( PDO::FETCH_ASSOC );
		$results	= $this->table->getAllByIndices( ['topic' => 'start'], [], [], ['label', 'topic'] );
		self::assertEquals( [['label' => '1210847252', 'topic' => 'start']], $results );
	}

	public function testGetAllByIndicesWithArrayFetchModeExceptionOnMultiFieldAliasedFieldMissingFromResult(): void
	{
		$this->table->setFetchMode( PDO::FETCH_ASSOC );
		$this->expectException( \RangeException::class );
		$this->table->getAllByIndices( ['topic' => 'start'], [], [], ['label', 'topic AS made_up_alias'] );
	}

	public function testGetAllByIndicesWithArrayFetchModeAndSingleField(): void
	{
		$this->table->setFetchMode( PDO::FETCH_ASSOC );
		$results	= $this->table->getAllByIndices( ['topic' => 'start'], [], [], ['label'] );
		self::assertEquals( ['1210847252'], $results );
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

		//	fields as a single string, not an array, is also allowed
		$result		= $this->table->getByIndex( 'topic', 'start', $orders, 'label' );
		self::assertEquals( 'label2', $result );
	}

	public function testGetByIndexException1(): void
	{
		$this->expectException( 'DomainException' );
		/** @var array $result */
		$result		= $this->table->getByIndex( 'label', 'label2' );
		unset( $result['timestamp'] );
		self::assertEquals( (object) ['id' => 3, 'topic' => 'start', 'label' => 'label2'], $result );
	}

	public function testGetByIndexWithSingleFieldReturnsNullOnNoMatch(): void
	{
		$result	= $this->table->getByIndex( 'topic', 'no_such_topic', [], ['label'] );
		self::assertNull( $result );
	}

	public function testGetByIndexWithMultipleFieldsReturnsEmptyArrayOnNoMatch(): void
	{
		$result	= $this->table->getByIndex( 'topic', 'no_such_topic', [], ['label', 'topic'] );
		self::assertEquals( [], $result );
	}

	public function testGetByIndexWithMultipleFieldsThrowsExceptionOnNoMatchWhenStrict(): void
	{
		$this->expectException( \RangeException::class );
		$this->table->getByIndex( 'topic', 'no_such_topic', [], ['label', 'topic'], TRUE );
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

		//	fields as a single string, not an array, is also allowed
		$result		= $this->table->getByIndices( $indices, $orders, 'label' );
		self::assertEquals( 'label2', $result );
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
		self::assertEquals( 'transactions', $this->table->getName( FALSE ) );
	}

	public function testGetPrimaryKey(): void
	{
		self::assertEquals( 'id', $this->table->getPrimaryKey() );
	}

	public function testGetFetchMode(): void
	{
		self::assertEquals( PDO::FETCH_OBJ, $this->table->getFetchMode() );
		$this->table->setFetchMode( PDO::FETCH_ASSOC );
		self::assertEquals( PDO::FETCH_ASSOC, $this->table->getFetchMode() );
	}

	/**
	 *	"lastQuery" is only tracked by Connection::query(), not by prepare()/
	 *	execute() - so it only reflects queries run through Reader methods
	 *	using query() directly (eg. count()), not eg. get()/find().
	 *	@access		public
	 *	@return		void
	 */
	public function testGetLastQuery(): void
	{
		self::assertNull( $this->table->getLastQuery() );
		$this->table->count();
		self::assertIsString( $this->table->getLastQuery() );
		self::assertStringContainsStringIgnoringCase( 'select', $this->table->getLastQuery() );
	}

	public function testHas(): void
	{
		self::assertTrue( $this->table->has( 1 ) );
		self::assertFalse( $this->table->has( 999 ) );
	}

	public function testHasSwallowsCacheInvalidArgumentException(): void
	{
		$this->table->setCache( new MemoryCache( NULL ) );
		self::assertTrue( $this->table->has( 1 ) );
		self::assertFalse( $this->table->has( 'invalid key!' ) );
	}

	public function testHasByIndex(): void
	{
		self::assertTrue( $this->table->hasByIndex( 'topic', 'start' ) );
		self::assertFalse( $this->table->hasByIndex( 'topic', 'not_existing' ) );
	}

	public function testHasByIndices(): void
	{
		self::assertTrue( $this->table->hasByIndices( ['topic' => 'start'] ) );
		self::assertFalse( $this->table->hasByIndices( ['topic' => 'not_existing'] ) );
	}

	public function testRemoveSwallowsCacheInvalidArgumentException(): void
	{
		$this->table->setCache( new MemoryCache( NULL ) );
		self::assertFalse( $this->table->remove( 'invalid key!' ) );
	}

	public function testRemoveByIndex(): void
	{
		$this->table->add( ['topic' => 'stop', 'label' => 'x'] );
		self::assertEquals( 1, $this->table->removeByIndex( 'topic', 'stop' ) );
		self::assertEquals( 0, $this->table->countByIndex( 'topic', 'stop' ) );
	}

	/**
	 *	removeBySetFocus() reads the primary key differently depending on
	 *	fetch mode - object-shaped rows (FETCH_CLASS/FETCH_OBJ) vs array-shaped
	 *	ones (everything else, eg. FETCH_ASSOC).
	 *	@access		public
	 *	@return		void
	 */
	public function testRemoveByIndexWithArrayFetchMode(): void
	{
		$this->table->setFetchMode( PDO::FETCH_ASSOC );
		$this->table->add( ['topic' => 'stop', 'label' => 'x'] );
		self::assertEquals( 1, $this->table->removeByIndex( 'topic', 'stop' ) );
	}

	public function testRemoveByIndexNoMatch(): void
	{
		self::assertEquals( 0, $this->table->removeByIndex( 'topic', 'not_existing' ) );
	}

	public function testRemoveByIndices(): void
	{
		$this->table->add( ['topic' => 'stop', 'label' => 'y'] );
		$indices	= ['topic' => 'stop'];
		self::assertEquals( 1, $this->table->removeByIndices( $indices ) );
		self::assertEquals( 0, $this->table->countByIndices( $indices ) );
	}

	public function testRemoveByIndicesNoMatch(): void
	{
		self::assertEquals( 0, $this->table->removeByIndices( ['topic' => 'not_existing'] ) );
	}

	public function testSaveExceptionOnMismatchedFetchEntityClass(): void
	{
		$this->table->setFetchEntityClass( TransactionEntity::class );
		$this->expectException( \InvalidArgumentException::class );
		$this->table->save( new AdvancedTransactionEntity() );
	}

	public function testSaveExceptionOnMismatchedFetchEntityObject(): void
	{
		$this->table->setFetchEntityObject( new AdvancedTransactionEntity() );
		$this->expectException( \InvalidArgumentException::class );
		$this->table->save( new TransactionEntity() );
	}

	public function testSetFetchEntityObject(): void
	{
		$this->table->setFetchMode( PDO::FETCH_INTO );
		$entity	= new AdvancedTransactionEntity();
		self::assertSame( $this->table, $this->table->setFetchEntityObject( $entity ) );

		$result	= $this->table->get( 1 );
		self::assertSame( $entity, $result );
	}

	public function testTruncate(): void
	{
		self::assertSame( $this->table, $this->table->truncate() );
		self::assertEquals( 0, $this->table->count() );
	}

	public function testConstructThrowsExceptionOnMissingTableName(): void
	{
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'No table name set' );
		new TableWithNoName( $this->connection );
	}

	public function testConstructThrowsExceptionOnMissingColumns(): void
	{
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'No table columns set' );
		new TableWithNoColumns( $this->connection );
	}

	/**
	 *	Table::setDatabase() must apply a preset "fetchEntityClass" (set as a
	 *	class default, not via setFetchEntityClass()) when constructing.
	 *	@access		public
	 *	@return		void
	 */
	public function testConstructAppliesPresetFetchEntityClass(): void
	{
		$table	= new TransactionTableWithPresetFetchEntityClass( $this->connection );
		$result	= $table->get( 1 );
		self::assertInstanceOf( AdvancedTransactionEntity::class, $result );
	}

	/**
	 *	Table::setDatabase() must apply a preset "fetchEntityObject" (set
	 *	before construction, since a property default cannot hold an object)
	 *	when constructing.
	 *	@access		public
	 *	@return		void
	 */
	public function testConstructAppliesPresetFetchEntityObject(): void
	{
		$table	= new TransactionTableWithPresetFetchEntityObject( $this->connection );
		$result	= $table->get( 1 );
		self::assertInstanceOf( AdvancedTransactionEntity::class, $result );
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
	}

	public function testGetFieldWithArrayFetchModeExceptionOnFieldMissingInResult(): void
	{
		$getField	= new \ReflectionMethod( $this->table, 'getFieldFromResult' );
		$this->expectException( \RangeException::class );
		$getField->invoke( $this->table, ['id' => 1], 'topic' );
	}

	/**
	 *	Deprecated, but still available for subclasses: removes a zero primary key value before insert.
	 *	@access		public
	 *	@return		void
	 */
	public function testRemovePrimaryKeyValueOnInsertIfAutoIncrement(): void
	{
		$method	= new \ReflectionMethod( $this->table, 'removePrimaryKeyValueOnInsertIfAutoIncrement' );

		$array	= ['id' => 0, 'topic' => 'x'];
		$method->invokeArgs( $this->table, [&$array] );
		self::assertSame( ['topic' => 'x'], $array );

		$array	= ['id' => 5, 'topic' => 'x'];
		$method->invokeArgs( $this->table, [&$array] );
		self::assertSame( ['id' => 5, 'topic' => 'x'], $array, 'sane value is kept' );

		$object	= (object) ['id' => '0', 'topic' => 'x'];
		$method->invokeArgs( $this->table, [&$object] );
		self::assertFalse( property_exists( $object, 'id' ) );

		$property	= new \ReflectionProperty( $this->table, 'autoIncrementPrimaryKey' );
		$property->setValue( $this->table, FALSE );
		$array	= ['id' => 0, 'topic' => 'x'];
		$method->invokeArgs( $this->table, [&$array] );
		self::assertSame( ['id' => 0, 'topic' => 'x'], $array, 'kept without auto increment' );
	}

	//  --  FIELD AND COLUMN METHODS (ported from BetterTable)  --  //

	public function testGetField(): void
	{
		self::assertSame( 'start', $this->table->getField( 1, 'topic' ) );
		self::assertSame( 'start', $this->table->getField( 1, ' topic ' ), 'trimmed' );
		self::assertNull( $this->table->getField( 999, 'topic' ), 'not existing' );

		$this->table->setFetchMode( PDO::FETCH_ASSOC );
		self::assertSame( 'start', $this->table->getField( 1, 'topic' ) );
	}

	public function testGetFieldUsesCache(): void
	{
		$this->table->setCache( new MemoryCache( NULL ) );
		self::assertSame( 'start', $this->table->getField( 1, 'topic' ) );
		$this->connection->exec( "UPDATE transactions SET topic='changed' WHERE id=1" );
		self::assertSame( 'start', $this->table->getField( 1, 'topic' ) );
	}

	public function testGetFieldExceptionOnEmptyField(): void
	{
		$this->expectException( \RangeException::class );
		$this->table->getField( 1, ' ' );
	}

	public function testGetFieldExceptionOnInvalidField(): void
	{
		$this->expectException( \DomainException::class );
		$this->table->getField( 1, 'invalid' );
	}

	/**
	 *	Decoded JSON values are returned as single field, too (did fail with TypeError before).
	 *	@access		public
	 *	@return		void
	 */
	public function testGetFieldWithJsonColumn(): void
	{
		$this->table->setJsonColumns( ['label'] );
		$id	= $this->table->add( ['topic' => 'json', 'label' => ['a' => 1]] );
		self::assertEquals( (object) ['a' => 1], $this->table->getField( $id, 'label' ) );
		self::assertEquals( (object) ['a' => 1], $this->table->get( $id, 'label' ), 'old API, too' );
		self::assertEquals( (object) ['a' => 1], $this->table->getFirstFieldByIndex( 'label', 'topic', 'json' ) );
		self::assertEquals( [(object) ['a' => 1]], $this->table->getColumnOfAllByConditions( 'label', ['topic' => 'json'] ) );
	}

	public function testGetFirstFieldByIndex(): void
	{
		$this->table->add( ['topic' => 'stop', 'label' => 'label1'] );
		$this->table->add( ['topic' => 'stop', 'label' => 'label2'] );

		self::assertSame( 'label1', $this->table->getFirstFieldByIndex( 'label', 'topic', 'stop' ) );
		self::assertSame( 'label2', $this->table->getFirstFieldByIndex( 'label', 'topic', 'stop', ['id' => 'DESC'] ) );
		self::assertSame( 'label2', $this->table->getFirstFieldByIndex( 'label', 'id', 3 ), 'primary key counts as index' );
		self::assertSame( 'label2', $this->table->getFirstFieldByIndex( 'label', 'id', [3, 4] ), 'list of values' );
		self::assertNull( $this->table->getFirstFieldByIndex( 'label', 'topic', 'invalid' ) );
	}

	/**
	 *	The reader focus is removed after reading, even if reading failed.
	 *	@access		public
	 *	@return		void
	 */
	public function testGetFirstFieldsByIndicesRemovesFocusEvenOnFailure(): void
	{
		/** @var \CeusMedia\Database\PDO\Table\Reader $reader */
		$reader	= ( new \ReflectionProperty( $this->table, 'reader' ) )->getValue( $this->table );

		$this->table->getFirstFieldsByIndices( ['id', 'topic'], ['topic' => 'start'] );
		self::assertSame( [], $reader->getFocus() );

		try{
			$this->table->getFirstFieldsByIndices( ['id', 'topic'], ['topic' => 'start'], ['invalid' => 'ASC'] );
			self::fail( 'Expected DomainException on invalid order column' );
		}
		catch( \DomainException ){}
		self::assertSame( [], $reader->getFocus() );
	}

	public function testGetFirstFieldByIndexExceptionOnEmptyResultWhenStrict(): void
	{
		$this->expectException( \RangeException::class );
		$this->table->getFirstFieldByIndex( 'label', 'topic', 'invalid', [], TRUE );
	}

	public function testGetFirstFieldByIndexExceptionOnInvalidIndex(): void
	{
		$this->expectException( \DomainException::class );
		$this->table->getFirstFieldByIndex( 'topic', 'label', 'x' );
	}

	public function testGetFirstFieldByIndexExceptionOnInjectedField(): void
	{
		$this->expectException( \DomainException::class );
		$this->table->getFirstFieldByIndex( 'id FROM transactions; --', 'topic', 'start' );
	}

	public function testGetFirstFieldByIndices(): void
	{
		$this->table->add( ['topic' => 'stop', 'label' => 'label1'] );
		$this->table->add( ['topic' => 'stop', 'label' => 'label2'] );

		self::assertSame( 'label2', $this->table->getFirstFieldByIndices( 'label', ['topic' => 'stop', 'id' => 3] ) );
		self::assertSame( 'label2', $this->table->getFirstFieldByIndices( 'label', ['topic' => 'stop'], ['id' => 'DESC'] ) );
		self::assertNull( $this->table->getFirstFieldByIndices( 'label', ['topic' => 'stop', 'id' => 1] ) );
	}

	public function testGetFirstFieldByIndicesExceptionOnEmptyIndices(): void
	{
		$this->expectException( \RangeException::class );
		$this->table->getFirstFieldByIndices( 'label', [] );
	}

	public function testGetFirstFieldsByIndex(): void
	{
		$this->table->add( ['topic' => 'stop', 'label' => 'label1'] );
		$this->table->add( ['topic' => 'stop', 'label' => 'label2'] );

		$result	= $this->table->getFirstFieldsByIndex( ['id', 'label'], 'topic', 'stop', ['id' => 'DESC'] );
		self::assertEquals( (object) ['id' => 3, 'label' => 'label2'], $result );

		$result	= $this->table->getFirstFieldsByIndex( ['label'], 'topic', 'stop' );
		self::assertEquals( (object) ['label' => 'label1'], $result, 'a single field still is a row' );

		self::assertNull( $this->table->getFirstFieldsByIndex( ['id', 'label'], 'topic', 'invalid' ) );

		$this->table->setFetchMode( PDO::FETCH_ASSOC );
		$result	= $this->table->getFirstFieldsByIndex( ['id', 'label'], 'topic', 'stop' );
		self::assertSame( ['id' => 2, 'label' => 'label1'], $result );

		$result	= $this->table->getFirstFieldsByIndex( ['*'], 'topic', 'stop' );
		self::assertSame( ['id', 'topic', 'label', 'timestamp'], array_keys( $result ) );
	}

	public function testGetFirstFieldsByIndexExceptionOnNoFields(): void
	{
		$this->expectException( \RangeException::class );
		$this->table->getFirstFieldsByIndex( [], 'topic', 'start' );
	}

	public function testGetFirstFieldsByIndexExceptionOnInvalidField(): void
	{
		$this->expectException( \DomainException::class );
		$this->table->getFirstFieldsByIndex( ['id', 'invalid'], 'topic', 'start' );
	}

	public function testGetFirstFieldsByIndexExceptionOnEmptyResultWhenStrict(): void
	{
		$this->expectException( \RangeException::class );
		$this->table->getFirstFieldsByIndex( ['id', 'label'], 'topic', 'invalid', [], TRUE );
	}

	public function testGetFirstFieldsByIndices(): void
	{
		$this->table->add( ['topic' => 'stop', 'label' => 'label1'] );

		$result	= $this->table->getFirstFieldsByIndices( ['id', 'label'], ['topic' => 'stop', 'id' => 2] );
		self::assertEquals( (object) ['id' => 2, 'label' => 'label1'], $result );
		self::assertNull( $this->table->getFirstFieldsByIndices( ['id', 'label'], ['topic' => 'stop', 'id' => 1] ) );
	}

	public function testGetFirstFieldsByIndicesExceptionOnInvalidIndex(): void
	{
		$this->expectException( \DomainException::class );
		$this->table->getFirstFieldsByIndices( ['id', 'topic'], ['label' => 'x'] );
	}

	/**
	 *	Entity rows are reduced to anonymous objects, containing the requested fields only.
	 *	@access		public
	 *	@return		void
	 */
	public function testGetFirstFieldsByIndexWithEntityFetchMode(): void
	{
		$this->table->setFetchMode( PDO::FETCH_CLASS );
		$this->table->setFetchEntityClass( TransactionEntity::class );
		$result	= $this->table->getFirstFieldsByIndex( ['id', 'topic'], 'topic', 'start' );
		self::assertEquals( (object) ['id' => '1', 'topic' => 'start'], $result );
	}

	public function testGetColumnOfAllByConditions(): void
	{
		$this->table->add( ['topic' => 'stop', 'label' => 'label1'] );
		$this->table->add( ['topic' => 'stop', 'label' => 'label2'] );

		self::assertSame( ['label2', 'label1'], $this->table->getColumnOfAllByConditions( 'label', ['topic' => 'stop'], ['id' => 'DESC'] ) );
		self::assertSame( ['label1'], $this->table->getColumnOfAllByConditions( 'label', ['topic' => 'stop'], ['id' => 'ASC'], [0, 1] ) );
		self::assertSame( [1, 2, 3], $this->table->getColumnOfAllByConditions( 'id' ) );
		self::assertSame( [], $this->table->getColumnOfAllByConditions( 'label', ['topic' => 'invalid'] ) );

		$this->table->setFetchMode( PDO::FETCH_ASSOC );
		self::assertSame( ['start', 'stop'], $this->table->getColumnOfAllByConditions( 'topic', [], ['topic' => 'ASC'], [], ['topic'] ), 'grouped' );
	}

	public function testGetColumnOfAllByConditionsExceptionOnInvalidColumn(): void
	{
		$this->expectException( \DomainException::class );
		$this->table->getColumnOfAllByConditions( 'invalid' );
	}

	public function testGetColumnsOfAllByConditions(): void
	{
		$this->table->add( ['topic' => 'stop', 'label' => 'label1'] );
		$this->table->add( ['topic' => 'stop', 'label' => 'label2'] );

		$result	= $this->table->getColumnsOfAllByConditions( ['id', 'label'], ['topic' => 'stop'], ['id' => 'DESC'] );
		self::assertEquals( [
			(object) ['id' => 3, 'label' => 'label2'],
			(object) ['id' => 2, 'label' => 'label1'],
		], $result );

		$this->table->setFetchMode( PDO::FETCH_ASSOC );
		$result	= $this->table->getColumnsOfAllByConditions( ['label'], ['topic' => 'stop'], [], [1, 1] );
		self::assertSame( [['label' => 'label2']], $result, 'a single column still are rows' );
		self::assertSame( [], $this->table->getColumnsOfAllByConditions( ['id'], ['topic' => 'invalid'] ) );
	}

	public function testGetColumnsOfAllByConditionsExceptionOnNoColumns(): void
	{
		$this->expectException( \RangeException::class );
		$this->table->getColumnsOfAllByConditions( [] );
	}

	public function testGetColumnsOfAllByConditionsExceptionOnAliasedColumn(): void
	{
		//  only real columns, since fields are put into the query
		$this->expectException( \DomainException::class );
		$this->table->getColumnsOfAllByConditions( ['id', 'label AS title'] );
	}

	/**
	 *	NULL values of array rows are values, not missing fields (did throw RangeException before).
	 *	@access		public
	 *	@return		void
	 */
	public function testFieldsWithNullValueInArrayFetchMode(): void
	{
		$getField	= new \ReflectionMethod( $this->table, 'getFieldFromResult' );
		self::assertNull( $getField->invoke( $this->table, ['id' => 1, 'label' => NULL], 'label' ) );

		$getFields	= new \ReflectionMethod( $this->table, 'getFieldsFromResult' );
		self::assertSame( ['id' => 1, 'label' => NULL], $getFields->invoke( $this->table, ['id' => 1, 'label' => NULL], ['id', 'label'] ) );
	}

	/**
	 *	Missing entries are not cached, so has() does not report them as existing afterward.
	 *	@access		public
	 *	@return		void
	 */
	public function testGetDoesNotCacheMissingEntry(): void
	{
		$this->table->setCache( new MemoryCache( NULL ) );
		self::assertNull( $this->table->get( 999 ) );
		self::assertFalse( $this->table->has( 999 ) );

		//  inserted bypassing the table: must be found, not served as cached NULL
		$this->connection->exec( "INSERT INTO transactions (id, topic, label) VALUES (999, 'late', 'inserted later')" );
		self::assertTrue( $this->table->has( 999 ) );
		self::assertSame( 'late', $this->table->get( 999 )->topic );
	}

	/**
	 *	A subclass can set its own default cache class.
	 *	@access		public
	 *	@return		void
	 */
	public function testSetupCacheUsesCacheClassOfSubclass(): void
	{
		$table	= new TransactionTableWithOwnCacheClass( $this->connection );
		$cache	= ( new \ReflectionProperty( $table, 'cache' ) )->getValue( $table );
		self::assertInstanceOf( MemoryCache::class, $cache );

		$cache	= ( new \ReflectionProperty( $this->table, 'cache' ) )->getValue( $this->table );
		self::assertInstanceOf( \CeusMedia\Cache\Adapter\Noop::class, $cache, 'default stays untouched' );
	}

	/**
	 *	Removing by index works for every fetch mode and invalidates the cache of every removed entry.
	 *	@access		public
	 *	@return		void
	 */
	public function testRemoveByIndexWithEveryFetchModeInvalidatesCache(): void
	{
		$modes	= [
			'FETCH_OBJ'		=> fn() => $this->table->setFetchMode( PDO::FETCH_OBJ ),
			'FETCH_ASSOC'	=> fn() => $this->table->setFetchMode( PDO::FETCH_ASSOC ),
			'FETCH_CLASS'	=> fn() => $this->table->setFetchMode( PDO::FETCH_CLASS )->setFetchEntityClass( TransactionEntity::class ),
			'FETCH_CLASS|FETCH_PROPS_LATE'	=> fn() => $this->table->setFetchMode( PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE )->setFetchEntityClass( TransactionEntity::class ),
			'FETCH_INTO'	=> fn() => $this->table->setFetchMode( PDO::FETCH_INTO )->setFetchEntityObject( new TransactionEntity() ),
		];
		foreach( $modes as $label => $setMode ){
			$cache	= new MemoryCache( NULL );
			$this->table->setCache( $cache );
			$this->table->setFetchEntityClass( NULL )->setFetchEntityObject( NULL );
			$setMode();

			$id1	= $this->table->add( ['topic' => 'remove', 'label' => 'label1'] );
			$id2	= $this->table->add( ['topic' => 'remove', 'label' => 'label2'] );
			self::assertTrue( $cache->has( 'db.transactions.'.$id1 ), $label );

			self::assertSame( 2, $this->table->removeByIndex( 'topic', 'remove' ), $label );
			self::assertFalse( $cache->has( 'db.transactions.'.$id1 ), $label );
			self::assertFalse( $cache->has( 'db.transactions.'.$id2 ), $label );
			self::assertFalse( $this->table->has( $id1 ), $label );
		}
	}

	public function testRemoveByIndicesWithEntityObjectFetchMode(): void
	{
		$this->table->setFetchMode( PDO::FETCH_INTO )->setFetchEntityObject( new TransactionEntity() );
		$id	= $this->table->add( ['topic' => 'remove', 'label' => 'label1'] );
		self::assertSame( 1, $this->table->removeByIndices( ['topic' => 'remove', 'id' => $id] ) );
		self::assertSame( 0, $this->table->removeByIndices( ['topic' => 'remove'] ) );
	}

	public function testRemoveByIndexExceptionOnInvalidIndex(): void
	{
		$this->expectException( \DomainException::class );
		$this->table->removeByIndex( 'label', 'x' );
	}

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
