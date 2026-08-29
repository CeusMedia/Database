<?php

namespace CeusMedia\DatabaseTest\PDO;

use DateTime;
use PDO;

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

	public function testGetAndSetDateTimeColumns(): void
	{
		self::assertEquals( [], $this->table->getDateTimeColumns() );

		$this->table->setDateTimeColumns( ['preciseAt', 'plainAt'] );

		self::assertEquals( ['preciseAt', 'plainAt'], $this->table->getDateTimeColumns() );
	}

	public function testNonParseableValueIsReturnedUnchanged(): void
	{
		//	"topic" is a plain VARCHAR column, not really a date/time column -
		//	setDateTimeColumns() only checks that the column exists, so this is
		//	a valid (if pointless) configuration to exercise the fallback path
		$this->table->setDateTimeColumns( ['topic'] );

		$id	= $this->table->add( ['topic' => 'not-a-date'] );

		$entry	= $this->table->get( $id );

		self::assertSame( 'not-a-date', $entry->topic );
	}

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
