<?php
declare(strict_types=1);

/**
 *	TestUnit of PDO Entity.
 *	@package		Tests.database.pdo
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 */

namespace CeusMedia\DatabaseTest\PDO;

use CeusMedia\Common\ADT\Collection\Dictionary;
use PHPUnit\Framework\TestCase;

/**
 *	TestUnit of PDO Entity, which needs no database connection.
 *	@package		Tests.database.pdo
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 */
class EntityTest extends TestCase
{
	public function testConstructSetsOnlyDeclaredProperties(): void
	{
		$entity	= new AdvancedTransactionEntity( ['id' => '1', 'topic' => 'start', 'unknown' => 'x'] );
		self::assertSame( '1', $entity->id );
		self::assertSame( 'start', $entity->topic );
		self::assertFalse( $entity->has( 'unknown' ) );
		self::assertNull( $entity->get( 'unknown' ) );
	}

	public function testFromArray(): void
	{
		$entity	= AdvancedTransactionEntity::fromArray( ['topic' => 'start'] );
		self::assertInstanceOf( AdvancedTransactionEntity::class, $entity );
		self::assertSame( 'start', $entity->topic );
	}

	public function testFromDictionary(): void
	{
		$entity	= AdvancedTransactionEntity::fromDictionary( new Dictionary( ['topic' => 'start'] ) );
		self::assertInstanceOf( AdvancedTransactionEntity::class, $entity );
		self::assertSame( 'start', $entity->topic );

		$entity	= new AdvancedTransactionEntity( new Dictionary( ['label' => 'from dictionary'] ) );
		self::assertSame( 'from dictionary', $entity->label );
	}

	public function testGetAndHasWithUninitializedTypedProperty(): void
	{
		$entity	= new AdvancedTransactionEntity();
		self::assertFalse( $entity->has( 'topic' ) );
		self::assertNull( $entity->get( 'topic' ) );
	}

	public function testSetIsFluentAndIgnoresUndeclaredProperties(): void
	{
		$entity	= new AdvancedTransactionEntity();
		self::assertSame( $entity, $entity->set( 'topic', 'set' ) );
		self::assertSame( 'set', $entity->get( 'topic' ) );

		$entity->set( 'unknown', 'x' );
		self::assertFalse( property_exists( $entity, 'unknown' ) );
	}

	public function testCountGetKeysAndToArrayIgnoreUninitializedAndInternalProperties(): void
	{
		$entity	= new AdvancedTransactionEntity( ['id' => '1', 'topic' => 'start'] );
		self::assertSame( 2, $entity->count() );
		self::assertCount( 2, $entity );
		self::assertSame( ['id', 'topic'], $entity->getKeys() );
		self::assertSame( ['id' => '1', 'topic' => 'start'], $entity->toArray() );
	}

	public function testToDictionary(): void
	{
		$entity		= new AdvancedTransactionEntity( ['id' => '1', 'topic' => 'start'] );
		$dictionary	= $entity->toDictionary();
		self::assertInstanceOf( Dictionary::class, $dictionary );
		self::assertSame( ['id' => '1', 'topic' => 'start'], $dictionary->getAll() );
	}

	public function testIterator(): void
	{
		$entity	= new AdvancedTransactionEntity( ['id' => '1', 'topic' => 'start'] );
		self::assertSame( ['id' => '1', 'topic' => 'start'], iterator_to_array( $entity ) );
		self::assertSame( ['id' => '1', 'topic' => 'start'], iterator_to_array( $entity ), 'rewindable' );

		//  beyond the end
		self::assertFalse( $entity->valid() );
		self::assertNull( $entity->key() );
		self::assertNull( $entity->current() );
	}

	public function testArrayAccess(): void
	{
		$entity	= new AdvancedTransactionEntity( ['topic' => 'start'] );
		self::assertTrue( isset( $entity['topic'] ) );
		self::assertFalse( isset( $entity['label'] ), 'not initialized' );
		self::assertFalse( isset( $entity['unknown'] ) );
		self::assertSame( 'start', $entity['topic'] );

		$entity['label']	= 'via array access';
		self::assertSame( 'via array access', $entity->label );

		unset( $entity['label'] );
		self::assertFalse( isset( $entity['label'] ) );
		self::assertNull( $entity['label'] );

		unset( $entity['unknown'] );
		self::assertSame( ['topic' => 'start'], $entity->toArray() );
	}

	/**
	 *	json_encode() must render the entity as JSON object, not as double encoded JSON string.
	 *	@access		public
	 *	@return		void
	 */
	public function testJsonSerialize(): void
	{
		$entity	= new AdvancedTransactionEntity( ['id' => '1', 'topic' => 'start'] );
		self::assertSame( ['id' => '1', 'topic' => 'start'], $entity->jsonSerialize() );
		self::assertSame( '{"id":"1","topic":"start"}', json_encode( $entity ) );
	}
}
