<?php
declare(strict_types=1);

/**
 *	TestUnit of executing OSQL queries on MySQL.
 *	@package		Tests.database.osql
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 */

namespace CeusMedia\DatabaseTest\OSQL;

use CeusMedia\Database\OSQL\Client;
use CeusMedia\Database\OSQL\Condition;
use CeusMedia\Database\OSQL\Condition\Group;
use CeusMedia\DatabaseTest\PDO\TestCase;
use DateTimeImmutable;
use DomainException;
use PDO;

/**
 *	TestUnit of executing OSQL queries on MySQL.
 *	@package		Tests.database.osql
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 */
class ClientTest extends TestCase
{
	protected Client $client;

	public function testAllFirstValueColumn(): void
	{
		$query	= $this->client->select( 'osql_orders' )->where( 'customerId', 1 )->order( 'orderId' );
		self::assertCount( 2, $query->all() );
		self::assertEquals( (object) ['orderId' => 1, 'customerId' => 1, 'total' => '10.00', 'note' => 'first', 'createdAt' => '2026-01-01 10:00:00'], $query->first() );
		self::assertSame( [1, 2], $this->client->select( 'osql_orders' )->fields( 'orderId' )->where( 'customerId', 1 )->column() );
		self::assertSame( '30.00', $this->client->select( 'osql_orders' )->fields( 'SUM(total)' )->where( 'customerId', 1 )->value() );
		self::assertNull( $this->client->select( 'osql_orders' )->where( 'customerId', 99 )->first() );
		self::assertNull( $this->client->select( 'osql_orders' )->fields( 'orderId' )->where( 'customerId', 99 )->value() );
		self::assertEquals( $query->all(), $query->execute() );
	}

	public function testStatementAndParametersAreNoted(): void
	{
		$query	= $this->client->select( 'osql_orders' )->where( 'customerId', 1 );
		$query->first();
		self::assertSame( 'SELECT * FROM `osql_orders` WHERE `customerId` = ? LIMIT 1', $query->statement );
		self::assertSame( [1], $query->parameters );
		self::assertSame( $query->statement, $this->connection->lastQuery );
	}

	public function testConditions(): void
	{
		$ids	= fn( $query ) => $query->fields( 'orderId' )->order( 'orderId' )->column();
		$select	= fn() => $this->client->select( 'osql_orders' );

		self::assertSame( [1, 3], $ids( $select()->where( 'orderId', [1, 3] ) ) );
		self::assertSame( [2, 4], $ids( $select()->where( 'orderId', [1, 3], Condition::OP_NEQ ) ) );
		self::assertSame( [], $ids( $select()->where( 'orderId', [] ) ) );
		self::assertSame( [2, 3], $ids( $select()->where( Condition::between( 'total', 15, 30 ) ) ) );
		self::assertSame( [4], $ids( $select()->where( 'note', NULL ) ) );
		self::assertSame( [1, 2, 3], $ids( $select()->where( Condition::isNotNull( 'note' ) ) ) );
		self::assertSame( [1, 3], $ids( $select()->where( Condition::like( 'note', '%ir%' ) ) ) );
		self::assertSame( [3, 4], $ids( $select()->where( 'createdAt', new DateTimeImmutable( '2026-02-01' ), '>' ) ) );
	}

	/**
	 *	Before, a group was put into the query without brackets, so "id = 3 AND a OR b" found other rows.
	 */
	public function testGroupedConditionsKeepPrecedence(): void
	{
		$query	= $this->client->select( 'osql_orders' )
			->fields( 'orderId' )
			->where( 'customerId', 2 )
			->and( Group::or( Condition::eq( 'orderId', 1 ), Condition::eq( 'orderId', 2 ) ) );
		self::assertSame( [], $query->column() );

		$query	= $this->client->select( 'osql_orders' )->fields( 'orderId' )->where( 'customerId', 2 )->or( 'orderId', 1 )->order( 'orderId' );
		self::assertSame( [1, 3, 4], $query->column() );
	}

	public function testJoinGroupHaving(): void
	{
		$rows	= $this->client->select( 'osql_orders AS o' )
			->fields( 'c.name', 'COUNT(*) AS number', 'SUM(o.total) AS sum' )
			->join( 'osql_customers AS c', 'o.customerId', 'c.customerId' )
			->groupBy( 'c.name' )
			->having( 'sum', 50, '>' )
			->fetchMode( PDO::FETCH_ASSOC )
			->all();
		self::assertSame( [['name' => 'Bob', 'number' => 2, 'sum' => '70.00']], $rows );

		$names	= $this->client->select( 'osql_customers' )
			->fields( 'name' )
			->leftJoin( 'osql_orders', 'customerId' )
			->where( 'osql_orders.orderId', NULL )
			->column();
		self::assertSame( ['Cid'], $names );
	}

	public function testCountExistsAndPage(): void
	{
		$query	= $this->client->select( 'osql_orders' )->order( 'orderId' );
		self::assertSame( 4, $query->count() );
		self::assertTrue( $query->exists() );
		self::assertFalse( $this->client->select( 'osql_orders' )->where( 'customerId', 99 )->exists() );
		self::assertSame( [3, 4], $query->fields( 'orderId' )->page( 2, 2 )->column() );
		self::assertSame( 4, $query->count(), 'count ignores limit and offset' );

		$grouped	= $this->client->select( 'osql_orders' )->fields( 'customerId' )->groupBy( 'customerId' );
		self::assertSame( 2, $grouped->count() );
		self::assertSame( 1, $grouped->having( 'SUM(total)', 50, '>' )->count() );
		self::assertSame( 2, $this->client->select( 'osql_orders' )->distinct()->fields( 'customerId' )->count() );
	}

	public function testFetchAs(): void
	{
		$order	= $this->client->select( 'osql_orders' )->where( 'orderId', 2 )->fetchAs( ClientTestOrder::class )->first();
		self::assertInstanceOf( ClientTestOrder::class, $order );
		self::assertSame( 'second', $order->note );

		$this->client->setFetchMode( PDO::FETCH_NUM );
		self::assertSame( [2], $this->client->select( 'osql_orders' )->fields( 'orderId' )->where( 'orderId', 2 )->first() );
	}

	public function testInsert(): void
	{
		$id	= $this->client->insert( 'osql_orders' )->setAll( ['customerId' => 2, 'total' => 1.5, 'note' => NULL] )->execute();
		self::assertSame( 5, $id );
		self::assertSame( '1.50', $this->client->select( 'osql_orders' )->fields( 'total' )->where( 'orderId', 5 )->value() );

		$query	= $this->client->insert( 'osql_orders' )
			->addRow( ['customerId' => 1, 'total' => 1] )
			->addRow( ['customerId' => 2, 'total' => 2] );
		self::assertSame( 6, $query->execute(), 'ID of first inserted row' );
		self::assertSame( 2, $query->affectedRows );
		self::assertSame( 7, $this->client->select( 'osql_orders' )->count() );
	}

	public function testInsertOnDuplicateKeyUpdate(): void
	{
		$query	= $this->client->insert( 'osql_customers' )->setAll( ['customerId' => 1, 'name' => 'Anna'] )->onDuplicateKeyUpdate( 'name' );
		$query->execute();
		self::assertSame( 'Anna', $this->client->select( 'osql_customers' )->fields( 'name' )->where( 'customerId', 1 )->value() );
		self::assertSame( 3, $this->client->select( 'osql_customers' )->count() );
	}

	public function testUpdateAndDelete(): void
	{
		$query	= $this->client->update( 'osql_orders' )->set( 'note', 'changed' )->where( 'customerId', 1 );
		self::assertSame( 2, $query->execute() );
		self::assertSame( 2, $query->affectedRows );

		self::assertSame( 1, $this->client->update( 'osql_orders AS o' )->set( 'o.note', NULL )->where( 'o.orderId', 1 )->execute() );
		self::assertNull( $this->client->select( 'osql_orders' )->fields( 'note' )->where( 'orderId', 1 )->value() );

		self::assertSame( 1, $this->client->delete( 'osql_orders' )->where( 'customerId', 1 )->limit( 1 )->execute() );
		self::assertSame( 3, $this->client->delete( 'osql_orders' )->allRows()->execute() );
		self::assertSame( 0, $this->client->select( 'osql_orders' )->count() );
	}

	public function testTransaction(): void
	{
		$id	= $this->client->transaction( fn( Client $client ) => $client->insert( 'osql_customers' )->setAll( ['customerId' => 4, 'name' => 'Dan'] )->execute() );
		self::assertSame( 0, $id, 'no auto increment column' );
		self::assertTrue( $this->client->select( 'osql_customers' )->where( 'customerId', 4 )->exists() );

		try{
			$this->client->transaction( function( Client $client ){
				$client->delete( 'osql_orders' )->allRows()->execute();
				throw new DomainException( 'failed' );
			} );
			self::fail( 'Expected DomainException' );
		}
		catch( DomainException ){}
		self::assertSame( 4, $this->client->select( 'osql_orders' )->count(), 'rolled back' );
	}

	public function testTransactionWithPlainPdo(): void
	{
		$pdo	= new PDO( $this->dsn, $this->username, $this->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION] );
		$client	= new Client( $pdo );
		$pdo->exec( 'CREATE TEMPORARY TABLE osql_plain (id INT)' );
		try{
			$client->transaction( function( Client $client ){
				$client->insert( 'osql_plain' )->set( 'id', 1 )->execute();
				throw new DomainException( 'failed' );
			} );
		}
		catch( DomainException ){}
		self::assertSame( 0, $client->select( 'osql_plain' )->count() );
		self::assertSame( 'ok', $client->transaction( fn() => 'ok' ) );
	}

	protected function setUp(): void
	{
		parent::setUp();
		$this->connection->exec( "CREATE TEMPORARY TABLE osql_customers (customerId INT PRIMARY KEY, name VARCHAR(20))" );
		$this->connection->exec( "CREATE TEMPORARY TABLE osql_orders (orderId INT AUTO_INCREMENT PRIMARY KEY, customerId INT, total DECIMAL(6,2), note VARCHAR(20) NULL, createdAt DATETIME)" );
		$this->connection->exec( "INSERT INTO osql_customers VALUES (1, 'Ann'), (2, 'Bob'), (3, 'Cid')" );
		$this->connection->exec( "INSERT INTO osql_orders (customerId, total, note, createdAt) VALUES
			(1, 10, 'first', '2026-01-01 10:00:00'),
			(1, 20, 'second', '2026-01-15 10:00:00'),
			(2, 30, 'third', '2026-02-10 10:00:00'),
			(2, 40, NULL, '2026-03-01 10:00:00')" );
		$this->client	= new Client( $this->connection );
	}
}

class ClientTestOrder
{
	public int $orderId;
	public int $customerId;
	public string $total;
	public ?string $note;
	public string $createdAt;
}
