<?php

namespace CeusMedia\DatabaseTest\PDO;

use DateTime;
use PDO;
use ReflectionObject;

/**
 *	Verifies DateTime column support end-to-end against a real table with
 *	both a fractional-seconds-capable column ("preciseAt", DATETIME(6)) and
 *	a plain one ("plainAt", DATETIME) - both configured identically via
 *	setDateTimeColumns(), proving that no separate "has microtime"
 *	configuration is needed: the database decides what is kept, and PHP's
 *	DateTime constructor detects what is present on read.
 */
class DateTimeColumnTableTest extends TestCase
{
	protected DateTimeColumnTable $table;

	public function testMicrosecondsArePreservedWhenColumnSupportsThem(): void
	{
		$this->table->setDateTimeColumns( ['preciseAt', 'plainAt'] );

		$moment	= new DateTime( '2026-08-29 14:30:00.123456' );
		$id		= $this->table->add( ['topic' => 'start', 'preciseAt' => $moment] );

		$entry	= $this->table->get( $id );

		self::assertInstanceOf( DateTime::class, $entry->preciseAt );
		self::assertEquals( '2026-08-29 14:30:00.123456', $entry->preciseAt->format( 'Y-m-d H:i:s.u' ) );
	}

	public function testMicrosecondsAreSilentlyTruncatedWithoutFractionalColumn(): void
	{
		$this->table->setDateTimeColumns( ['preciseAt', 'plainAt'] );

		$moment	= new DateTime( '2026-08-29 14:30:00.123456' );
		$id		= $this->table->add( ['topic' => 'start', 'plainAt' => $moment] );

		$entry	= $this->table->get( $id );

		self::assertInstanceOf( DateTime::class, $entry->plainAt );
		self::assertEquals( '2026-08-29 14:30:00.000000', $entry->plainAt->format( 'Y-m-d H:i:s.u' ) );
	}

	public function testFetchClassDecodesDateTimeColumns(): void
	{
		$this->table->setDateTimeColumns( ['preciseAt'] );
		$this->table->setFetchEntityClass( DateTimeColumnEntity::class );
		$this->table->setFetchMode( PDO::FETCH_CLASS );

		$moment	= new DateTime( '2026-08-29 14:30:00.123456' );
		$id		= $this->table->add( ['topic' => 'start', 'preciseAt' => $moment] );

		/** @var DateTimeColumnEntity $entry */
		$entry	= $this->table->get( $id );

		self::assertInstanceOf( DateTimeColumnEntity::class, $entry );
		self::assertInstanceOf( DateTime::class, $entry->preciseAt );
		self::assertEquals( '2026-08-29 14:30:00.123456', $entry->preciseAt->format( 'Y-m-d H:i:s.u' ) );
	}

	public function testMistypedEntityPropertyThrowsClearError(): void
	{
		$this->table->setDateTimeColumns( ['preciseAt'] );
		$this->table->setFetchEntityClass( DateTimeColumnEntityNarrowType::class );
		$this->table->setFetchMode( PDO::FETCH_CLASS );

		//	Table::add() primes the cache via an internal get(), so the mistyped
		//	property already surfaces here, not only on an explicit later fetch
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/property "preciseAt" of class .*DateTimeColumnEntityNarrowType/' );
		$this->table->add( ['topic' => 'start', 'preciseAt' => new DateTime( '2026-08-29 14:30:00' )] );
	}

	public function testGetDistinctDecodesDateTimeColumn(): void
	{
		$this->table->setDateTimeColumns( ['preciseAt'] );

		$this->table->add( ['topic' => 'distinct', 'preciseAt' => new DateTime( '2026-08-29 14:30:00.100000' )] );
		$this->table->add( ['topic' => 'distinct', 'preciseAt' => new DateTime( '2026-08-29 14:30:00.100000' )] );
		$this->table->add( ['topic' => 'distinct', 'preciseAt' => new DateTime( '2026-08-29 15:00:00.200000' )] );

		$values	= $this->table->getDistinct( 'preciseAt', ['topic' => 'distinct'] );

		self::assertCount( 2, $values );
		foreach( $values as $value )
			self::assertInstanceOf( DateTime::class, $value );
	}

	public function testFetchStyleDispatchIsCorrectEvenWithStaleSiblingState(): void
	{
		$id	= $this->table->add( ['topic' => 'start', 'preciseAt' => new DateTime( '2026-08-29 14:30:00.123456' )] );

		//	reach into the Reader directly and set BOTH sibling properties at
		//	once - this isolates and proves the dispatch logic in
		//	Abstraction::applyFetchModeOnStatement() and
		//	Reader::applyFetchModeOnResultSet() picks the right style from
		//	fetchMode alone, not just because one property happens to be NULL.
		//	Ported from 0.6.x-generics, where this exact bug was found: PDO::FETCH_INTO
		//	(9) has all the bits of PDO::FETCH_CLASS (8) plus one, so a naive
		//	"fetchMode & FETCH_CLASS" check is also true when the mode is FETCH_INTO.
		$reflection	= new ReflectionObject( $this->table );
		$readerProp	= $reflection->getProperty( 'reader' );
		$readerProp->setAccessible( TRUE );
		/** @var \CeusMedia\Database\PDO\Table\Reader $reader */
		$reader	= $readerProp->getValue( $this->table );

		$entityObject	= new DateTimeColumnEntity();
		$reader->setFetchEntityClass( DateTimeColumnEntity::class );
		$reader->setFetchEntityObject( $entityObject );

		$reader->setFetchMode( PDO::FETCH_INTO );
		$reader->focusPrimary( $id );
		$intoResult	= $reader->get();
		$reader->defocus();
		self::assertSame( $entityObject, $intoResult, 'FETCH_INTO must fetch into the bound object, not create a class instance' );

		$reader->setFetchMode( PDO::FETCH_CLASS );
		$reader->focusPrimary( $id );
		$classResult	= $reader->get();
		$reader->defocus();
		self::assertNotSame( $entityObject, $classResult, 'FETCH_CLASS must create a fresh instance, not reuse the bound object' );
		self::assertInstanceOf( DateTimeColumnEntity::class, $classResult );
	}

	protected function setUp(): void
	{
		parent::setUp();
		$this->connection->exec( 'DROP TABLE IF EXISTS datetime_columns_test' );
		$this->connection->exec(
			'CREATE TABLE datetime_columns_test ('
			.'id INT PRIMARY KEY AUTO_INCREMENT, '
			.'topic VARCHAR(20), '
			.'preciseAt DATETIME(6) NULL, '
			.'plainAt DATETIME NULL'
			.')'
		);

		$this->table	= new DateTimeColumnTable( $this->connection );
	}

	protected function tearDown(): void
	{
		$this->connection->exec( 'DROP TABLE IF EXISTS datetime_columns_test' );
		parent::tearDown();
	}
}
