<?php /** @noinspection ALL */

/**
 *	TestUnit of PDO Connection Pool.
 *	@package		Tests.database.pdo
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 */

namespace CeusMedia\DatabaseTest\PDO;

use CeusMedia\Database\PDO\Connection as PdoConnection;
use CeusMedia\Database\PDO\Pool;
use DomainException;
use RuntimeException;

/**
 *	TestUnit of PDO Connection Pool.
 *	@package		Tests.database.pdo
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 */
class PoolTest extends TestCase
{
	protected Pool $pool;

	protected PdoConnection $connectionTwo;

	public function testAddSetsFirstConnectionAsDefault(): void
	{
		self::assertSame( $this->pool, $this->pool->add( 'first', $this->connection ) );
		self::assertEquals( 'first', $this->pool->getDefault() );
		self::assertSame( $this->connection, $this->pool->get() );
		self::assertSame( $this->connection, $this->pool->get( 'first' ) );
	}

	public function testAddDoesNotChangeDefaultUnlessRequested(): void
	{
		$this->pool->add( 'first', $this->connection );
		$this->pool->add( 'second', $this->connectionTwo );
		self::assertEquals( 'first', $this->pool->getDefault() );

		$this->pool->add( 'third', $this->connectionTwo, TRUE );
		self::assertEquals( 'third', $this->pool->getDefault() );
		self::assertSame( $this->connectionTwo, $this->pool->get() );
	}

	public function testAddThrowsExceptionOnDuplicateName(): void
	{
		$this->pool->add( 'first', $this->connection );
		$this->expectException( DomainException::class );
		$this->pool->add( 'first', $this->connectionTwo );
	}

	public function testGetThrowsExceptionWhenNoDefaultSet(): void
	{
		$this->expectException( RuntimeException::class );
		$this->pool->get();
	}

	public function testGetThrowsExceptionOnUnknownName(): void
	{
		$this->pool->add( 'first', $this->connection );
		$this->expectException( DomainException::class );
		$this->pool->get( 'not_existing' );
	}

	public function testGetDefaultThrowsExceptionWhenNoneSet(): void
	{
		$this->expectException( RuntimeException::class );
		$this->pool->getDefault();
	}

	public function testRemove(): void
	{
		$this->pool->add( 'first', $this->connection );
		self::assertSame( $this->pool, $this->pool->remove( 'first' ) );

		$this->expectException( DomainException::class );
		$this->pool->get( 'first' );
	}

	public function testRemoveThrowsExceptionOnUnknownName(): void
	{
		$this->expectException( DomainException::class );
		$this->pool->remove( 'not_existing' );
	}

	public function testSetDefault(): void
	{
		$this->pool->add( 'first', $this->connection );
		$this->pool->add( 'second', $this->connectionTwo );

		self::assertSame( $this->pool, $this->pool->setDefault( 'second' ) );
		self::assertEquals( 'second', $this->pool->getDefault() );
		self::assertSame( $this->connectionTwo, $this->pool->get() );
	}

	public function testSetDefaultThrowsExceptionOnUnknownName(): void
	{
		$this->expectException( DomainException::class );
		$this->pool->setDefault( 'not_existing' );
	}

	//  --  PROTECTED  --  //

	protected function setUp(): void
	{
		parent::setUp();
		$this->pool				= new Pool();
		$this->connectionTwo	= new PdoConnection( $this->dsn, $this->username, $this->password );
	}
}
