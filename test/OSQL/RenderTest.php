<?php
declare(strict_types=1);

/**
 *	TestUnit of rendering OSQL queries, conditions and identifiers - without database.
 *	@package		Tests.database.osql
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 */

namespace CeusMedia\DatabaseTest\OSQL;

use CeusMedia\Database\OSQL\Client;
use CeusMedia\Database\OSQL\Condition;
use CeusMedia\Database\OSQL\Condition\Group;
use CeusMedia\Database\OSQL\Identifier;
use CeusMedia\Database\OSQL\Table;
use CeusMedia\Database\OSQL\Value;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 *	TestUnit of rendering OSQL queries, conditions and identifiers - without database.
 *	@package		Tests.database.osql
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 */
class RenderTest extends TestCase
{
	protected Client $client;

	//  --  IDENTIFIERS AND VALUES  --  //

	public function testIdentifierQuotesValidIdentifiers(): void
	{
		self::assertSame( '`orders`', Identifier::quoteName( ' orders ' ) );
		self::assertSame( '`total`', Identifier::quoteColumn( 'total' ) );
		self::assertSame( '`o`.`total`', Identifier::quoteColumn( 'o.total' ) );
		self::assertSame( '`o`.*', Identifier::quoteColumn( 'o.*', TRUE ) );
		self::assertSame( 'COUNT(*)', Identifier::quoteExpression( 'count( * )' ) );
		self::assertSame( 'COUNT(DISTINCT `o`.`customerId`)', Identifier::quoteExpression( 'COUNT(DISTINCT o.customerId)' ) );
		self::assertSame( 'SUM(`total`) AS `sum`', Identifier::quoteField( 'SUM(total) as sum' ) );
		self::assertSame( '`o`.`total` AS `amount`', Identifier::quoteField( 'o.total AS amount' ) );
		self::assertSame( '*', Identifier::quoteField( '*' ) );
	}

	/**
	 *	@return		array<string,array{string}>
	 */
	public static function provideInvalidFields(): array
	{
		return [
			'subquery'				=> ['(SELECT USER()) AS who'],
			'function not allowed'	=> ['SLEEP(1)'],
			'expression'			=> ['total + 1'],
			'quote'					=> ['to`tal'],
			'comment'				=> ['total -- x'],
			'invalid alias'			=> ['total AS `x`'],
			'alias with space'		=> ['total AS a b'],
			'sum of all'			=> ['SUM(*)'],
			'three parts'			=> ['a.b.c'],
			'empty'					=> [''],
		];
	}

	#[DataProvider( 'provideInvalidFields' )]
	public function testIdentifierRejectsInvalidField( string $field ): void
	{
		$this->expectException( InvalidArgumentException::class );
		Identifier::quoteField( $field );
	}

	public function testIdentifierRejectsWildcardIfNotAllowed(): void
	{
		$this->expectException( InvalidArgumentException::class );
		Identifier::quoteColumn( '*' );
	}

	public function testValueNormalize(): void
	{
		self::assertSame( 1, Value::normalize( TRUE ) );
		self::assertSame( 0, Value::normalize( FALSE ) );
		self::assertSame( '2026-10-30 12:34:56', Value::normalize( new DateTimeImmutable( '2026-10-30 12:34:56' ) ) );
		self::assertSame( 'paid', Value::normalize( RenderTestStatus::Paid ) );
		self::assertSame( 1.5, Value::normalize( 1.5 ) );
		self::assertNull( Value::normalize( NULL ) );
		$this->expectException( InvalidArgumentException::class );
		Value::normalize( [1] );
	}

	//  --  CONDITIONS  --  //

	public function testConditionOperators(): void
	{
		self::assertSame( ['`a` = ?', [1]], self::renderCondition( Condition::eq( 'a', 1 ) ) );
		self::assertSame( ['`a` != ?', [1]], self::renderCondition( new Condition( 'a', 1, '<>' ) ) );
		self::assertSame( ['`a` >= ?', [1]], self::renderCondition( Condition::gte( 'a', 1 ) ) );
		self::assertSame( ['`a` NOT LIKE ?', ['x%']], self::renderCondition( new Condition( 'a', 'x%', 'not  like' ) ) );
		self::assertSame( ['`a` IN (?, ?)', [1, 2]], self::renderCondition( Condition::in( 'a', [1, 2] ) ) );
		self::assertSame( ['`a` NOT IN (?)', ['x']], self::renderCondition( Condition::notIn( 'a', ['x'] ) ) );
		self::assertSame( ['`a` BETWEEN ? AND ?', [1, 9]], self::renderCondition( Condition::between( 'a', 1, 9 ) ) );
		self::assertSame( ['`a` IS NULL', []], self::renderCondition( Condition::isNull( 'a' ) ) );
		self::assertSame( ['COUNT(*) > ?', [5]], self::renderCondition( Condition::gt( 'COUNT(*)', 5 ) ) );
	}

	public function testConditionFactoriesAndGetters(): void
	{
		self::assertSame( ['`a` < ?', [1]], self::renderCondition( Condition::lt( 'a', 1 ) ) );
		self::assertSame( ['`a` <= ?', [1]], self::renderCondition( Condition::lte( 'a', 1 ) ) );
		self::assertSame( ['`a` > ?', [1]], self::renderCondition( Condition::gt( 'a', 1 ) ) );
		self::assertSame( ['`a` NOT LIKE ?', ['x%']], self::renderCondition( Condition::notLike( 'a', 'x%' ) ) );
		self::assertSame( ['`a` NOT BETWEEN ? AND ?', [1, 2]], self::renderCondition( Condition::notBetween( 'a', 1, 2 ) ) );
		self::assertSame( ['`a` IS NOT NULL', []], self::renderCondition( Condition::isNotNull( 'a' ) ) );

		$condition	= Condition::in( ' o.id ', [1, TRUE] );
		self::assertSame( 'o.id', $condition->getField() );
		self::assertSame( Condition::OP_IN, $condition->getOperator() );
		self::assertSame( [1, 1], $condition->getValue() );

		$group	= Group::or( $condition );
		self::assertSame( Group::OPERATION_OR, $group->getOperation() );
		self::assertSame( [$condition], $group->getConditions() );
	}

	public function testTable(): void
	{
		$table	= Table::resolve( 'orders o' );
		self::assertSame( 'orders', $table->getName() );
		self::assertSame( 'o', $table->getAlias() );
		self::assertSame( $table, Table::resolve( $table ) );
		self::assertSame( '`orders`', ( new Table( 'orders', 'orders' ) )->render() );
		$this->expectException( InvalidArgumentException::class );
		new Table( 'orders', 'o-1' );
	}

	public function testClonedQueryIsIndependent(): void
	{
		$query	= $this->client->select( 't' )->where( 'a', 1 )->having( 'COUNT(*)', 1, '>' );
		$clone	= ( clone $query )->and( 'b', 2 )->having( 'SUM(c)', 3, '>' );
		self::assertSame( 'SELECT * FROM `t` WHERE `a` = ? HAVING COUNT(*) > ?', $query->render()->query );
		self::assertSame( 'SELECT * FROM `t` WHERE `a` = ? AND `b` = ? HAVING COUNT(*) > ? AND SUM(`c`) > ?', $clone->render()->query );

		$update	= $this->client->update( 't' )->set( 'a', 1 )->where( 'a', 0 );
		$clone	= ( clone $update )->or( 'b', 0 );
		self::assertSame( 'UPDATE `t` SET `a` = ? WHERE `a` = ?', $update->render()->query );
		self::assertSame( 'UPDATE `t` SET `a` = ? WHERE `a` = ? OR `b` = ?', $clone->render()->query );

		$delete	= $this->client->delete( 't' )->where( 'a', 0 );
		( clone $delete )->or( 'b', 0 );
		self::assertSame( 'DELETE FROM `t` WHERE `a` = ?', $delete->render()->query );
	}

	public function testUpdateAndDeleteRejectInvalidLimit(): void
	{
		foreach( [$this->client->update( 't' ), $this->client->delete( 't' )] as $query ){
			try{
				$query->limit( 0 );
				self::fail( 'Expected InvalidArgumentException for '.$query::class );
			}
			catch( InvalidArgumentException ){}
			self::assertSame( $query, $query->limit( NULL ) );
		}
	}

	public function testConditionTurnsListIntoInAndNullIntoIsNull(): void
	{
		self::assertSame( ['`a` IN (?, ?)', [1, 2]], self::renderCondition( new Condition( 'a', [1, 2] ) ) );
		self::assertSame( ['`a` NOT IN (?, ?)', [1, 2]], self::renderCondition( Condition::neq( 'a', [1, 2] ) ) );
		self::assertSame( ['`a` IS NULL', []], self::renderCondition( Condition::eq( 'a', NULL ) ) );
		self::assertSame( ['`a` IS NOT NULL', []], self::renderCondition( Condition::neq( 'a', NULL ) ) );
	}

	public function testConditionWithEmptyListIsAlwaysFalseOrTrue(): void
	{
		self::assertSame( ['1 = 0', []], self::renderCondition( Condition::in( 'a', [] ) ) );
		self::assertSame( ['1 = 1', []], self::renderCondition( Condition::notIn( 'a', [] ) ) );
	}

	/**
	 *	@return		array<string,array{string,mixed,string}>
	 */
	public static function provideInvalidConditions(): array
	{
		return [
			'invalid operator'		=> ['a', 1, '=='],
			'injected field'		=> ['a = 1 OR 1', 1, '='],
			'list for >'			=> ['a', [1], '>'],
			'NULL for >'			=> ['a', NULL, '>'],
			'scalar for IN'			=> ['a', 1, 'IN'],
			'one value BETWEEN'		=> ['a', [1], 'BETWEEN'],
			'value for IS NULL'		=> ['a', 1, 'IS NULL'],
			'object value'			=> ['a', new \stdClass(), '='],
		];
	}

	#[DataProvider( 'provideInvalidConditions' )]
	public function testConditionRejectsInvalidInput( string $field, mixed $value, string $operator ): void
	{
		$this->expectException( InvalidArgumentException::class );
		new Condition( $field, $value, $operator );
	}

	public function testGroupNestingIsBracketed(): void
	{
		$group	= Group::and(
			Condition::eq( 'a', 1 ),
			Group::or( Condition::eq( 'b', 2 ), Group::and( Condition::eq( 'c', 3 ), Condition::eq( 'd', 4 ) ) )
		);
		$parameters	= [];
		self::assertSame( '`a` = ? AND (`b` = ? OR (`c` = ? AND `d` = ?))', $group->render( $parameters ) );
		self::assertSame( [1, 2, 3, 4], $parameters );

		$parameters	= [];
		self::assertSame( '1 = 1', ( new Group() )->render( $parameters ) );
		self::assertSame( '1 = 0', Group::or()->render( $parameters ) );
	}

	public function testGroupRejectsInvalidOperation(): void
	{
		$this->expectException( InvalidArgumentException::class );
		new Group( 'XOR' );
	}

	//  --  SELECT  --  //

	public function testSelectSimple(): void
	{
		$query	= $this->client->select( 'orders' )->where( 'status', 1 );
		self::assertSame( 'SELECT * FROM `orders` WHERE `status` = ?', $query->render()->query );
		self::assertSame( [1], $query->render()->parameters );
	}

	public function testSelectWithAllParts(): void
	{
		$query	= $this->client->select( 'orders AS o' )
			->distinct()
			->fields( 'o.customerId', 'c.name', 'COUNT(*) AS number', 'SUM(o.total) AS sum' )
			->leftJoin( new Table( 'customers', 'c' ), 'o.customerId', 'c.customerId' )
			->join( 'addresses', 'addressId' )
			->rightJoin( 'regions r', 'c.regionId', 'r.regionId' )
			->where( 'o.status', [1, 2] )
			->and( 'o.createdAt', NULL )
			->groupBy( 'o.customerId', 'c.name' )
			->having( 'COUNT(*)', 2, Condition::OP_GT )
			->having( 'sum', 100, '>=' )
			->order( 'sum', 'desc' )
			->order( 'c.name' )
			->page( 3, 10 );
		$expected	= 'SELECT DISTINCT `o`.`customerId`, `c`.`name`, COUNT(*) AS `number`, SUM(`o`.`total`) AS `sum`'
			.' FROM `orders` AS `o`'
			.' LEFT JOIN `customers` AS `c` ON `o`.`customerId` = `c`.`customerId`'
			.' JOIN `addresses` USING (`addressId`)'
			.' RIGHT JOIN `regions` AS `r` ON `c`.`regionId` = `r`.`regionId`'
			.' WHERE `o`.`status` IN (?, ?) AND `o`.`createdAt` IS NULL'
			.' GROUP BY `o`.`customerId`, `c`.`name`'
			.' HAVING COUNT(*) > ? AND `sum` >= ?'
			.' ORDER BY `sum` DESC, `c`.`name` ASC'
			.' LIMIT 10 OFFSET 20';
		self::assertSame( $expected, $query->render()->query );
		self::assertSame( [1, 2, 2, 100], $query->render()->parameters );
	}

	/**
	 *	Conditions are combined from left to right, each step in brackets - "AND" does not bind stronger than "OR".
	 */
	public function testSelectConditionsAreCombinedFromLeftToRight(): void
	{
		$query	= $this->client->select( 't' )->where( 'a', 1 )->and( 'b', 2 )->or( 'c', 3 )->and( 'd', 4 );
		self::assertSame( 'SELECT * FROM `t` WHERE ((`a` = ? AND `b` = ?) OR `c` = ?) AND `d` = ?', $query->render()->query );

		$query	= $this->client->select( 't' )->where( 'a', 1 )->or( 'b', 2 )->or( 'c', 3 );
		self::assertSame( 'SELECT * FROM `t` WHERE `a` = ? OR `b` = ? OR `c` = ?', $query->render()->query );

		$query	= $this->client->select( 't' )->where( 'id', 3 )->and( Group::or( Condition::eq( 'a', 1 ), Condition::eq( 'b', 2 ) ) );
		self::assertSame( 'SELECT * FROM `t` WHERE `id` = ? AND (`a` = ? OR `b` = ?)', $query->render()->query );
	}

	public function testSelectLimitAndOffset(): void
	{
		$query	= $this->client->select( 't' );
		self::assertSame( 'SELECT * FROM `t`', $query->offset( 0 )->render()->query );
		self::assertSame( 'SELECT * FROM `t` LIMIT 18446744073709551615 OFFSET 5', $query->offset( 5 )->render()->query );
		self::assertSame( 'SELECT * FROM `t` LIMIT 2 OFFSET 5', $query->limit( 2 )->render()->query );
		self::assertSame( 'SELECT * FROM `t` LIMIT 2', $query->page( 1, 2 )->render()->query );
	}

	public function testSelectFieldsAreReplaced(): void
	{
		$query	= $this->client->select( 't' )->fields( 'a' )->fields( 'b', 'c' );
		self::assertSame( 'SELECT `b`, `c` FROM `t`', $query->render()->query );
	}

	/**
	 *	@return		array<string,array{callable(Client): mixed}>
	 */
	public static function provideInvalidSelects(): array
	{
		return [
			'no table'				=> [fn( Client $c ) => $c->select()->render()],
			'injected table'		=> [fn( Client $c ) => $c->select( 't; DROP TABLE t' )],
			'injected field'		=> [fn( Client $c ) => $c->select( 't' )->fields( '(SELECT USER()) AS who' )],
			'injected order'		=> [fn( Client $c ) => $c->select( 't' )->order( '(SELECT SLEEP(1))' )],
			'invalid direction'		=> [fn( Client $c ) => $c->select( 't' )->order( 'a', 'DESC; DROP' )],
			'injected grouping'		=> [fn( Client $c ) => $c->select( 't' )->groupBy( 'a, (SELECT 1)' )],
			'injected join key'		=> [fn( Client $c ) => $c->select( 't' )->join( 'u', 't.id OR 1', 'u.id' )],
			'qualified using'		=> [fn( Client $c ) => $c->select( 't' )->join( 'u', 't.id' )],
			'limit 0'				=> [fn( Client $c ) => $c->select( 't' )->limit( 0 )],
			'negative offset'		=> [fn( Client $c ) => $c->select( 't' )->offset( -1 )],
			'page 0'				=> [fn( Client $c ) => $c->select( 't' )->page( 0, 10 )],
			'missing value'			=> [fn( Client $c ) => $c->select( 't' )->where( 'a' )],
			'value beside object'	=> [fn( Client $c ) => $c->select( 't' )->where( Condition::eq( 'a', 1 ), 2 )],
			'unknown fetch class'	=> [fn( Client $c ) => $c->select( 't' )->fetchAs( 'NotExistingClass' )],
		];
	}

	/**
	 *	@param		callable(Client): mixed		$callback
	 */
	#[DataProvider( 'provideInvalidSelects' )]
	public function testSelectRejectsInvalidInput( callable $callback ): void
	{
		try{
			$callback( $this->client );
			self::fail( 'Expected InvalidArgumentException or RuntimeException' );
		}
		catch( InvalidArgumentException|RuntimeException $e ){
			self::assertNotSame( '', $e->getMessage() );
		}
	}

	/**
	 *	count() renders a simple COUNT(*) query, or counts the rows of a grouped query by a subquery.
	 */
	public function testSelectCountQuery(): void
	{
		$query	= $this->client->select( 'orders' )->where( 'status', 1 )->order( 'id' )->limit( 10 );
		( fn() => $this->counting = TRUE )->call( $query );
		self::assertSame( 'SELECT COUNT(*) FROM `orders` WHERE `status` = ?', $query->render()->query );

		$query	= $this->client->select( 'orders' )->fields( 'customerId' )->groupBy( 'customerId' )->order( 'customerId' );
		( fn() => $this->counting = TRUE )->call( $query );
		self::assertSame( 'SELECT COUNT(*) FROM (SELECT `customerId` FROM `orders` GROUP BY `customerId`) AS `counted`', $query->render()->query );
	}

	//  --  INSERT  --  //

	public function testInsert(): void
	{
		$query	= $this->client->insert( 'orders' )->set( 'customerId', 12 )->setAll( ['total' => 9.5, 'paid' => TRUE] );
		self::assertSame( 'INSERT INTO `orders` (`customerId`, `total`, `paid`) VALUES (?, ?, ?)', $query->render()->query );
		self::assertSame( [12, 9.5, 1], $query->render()->parameters );
	}

	public function testInsertSeveralRowsWithUpdateOnDuplicateKey(): void
	{
		$query	= $this->client->insert( 'stock' )
			->addRow( ['productId' => 1, 'quantity' => 5] )
			->addRow( ['quantity' => 7, 'productId' => 2] )
			->onDuplicateKeyUpdate( 'quantity' );
		$expected	= 'INSERT INTO `stock` (`productId`, `quantity`) VALUES (?, ?), (?, ?)'
			.' ON DUPLICATE KEY UPDATE `quantity` = VALUES(`quantity`)';
		self::assertSame( $expected, $query->render()->query );
		self::assertSame( [1, 5, 2, 7], $query->render()->parameters );
	}

	public function testInsertRejectsRowsWithOtherColumns(): void
	{
		$query	= $this->client->insert( 'stock' )->addRow( ['productId' => 1, 'quantity' => 5] )->addRow( ['productId' => 2, 'amount' => 7] );
		$this->expectException( InvalidArgumentException::class );
		$query->render();
	}

	public function testInsertRejectsMissingValues(): void
	{
		$this->expectException( RuntimeException::class );
		$this->client->insert( 'stock' )->render();
	}

	public function testInsertRejectsQualifiedColumn(): void
	{
		$this->expectException( InvalidArgumentException::class );
		$this->client->insert( 'stock' )->set( 's.quantity', 1 );
	}

	//  --  UPDATE AND DELETE  --  //

	public function testUpdate(): void
	{
		$query	= $this->client->update( 'orders AS o' )
			->setAll( ['o.status' => 2] )
			->set( 'o.note', NULL )
			->where( 'o.id', 5 )
			->limit( 1 );
		self::assertSame( 'UPDATE `orders` AS `o` SET `o`.`status` = ?, `o`.`note` = ? WHERE `o`.`id` = ? LIMIT 1', $query->render()->query );
		self::assertSame( [2, NULL, 5], $query->render()->parameters );
	}

	public function testDelete(): void
	{
		$query	= $this->client->delete( 'orders' )->where( 'status', 9 )->or( 'createdAt', '2020-01-01', '<' );
		self::assertSame( 'DELETE FROM `orders` WHERE `status` = ? OR `createdAt` < ?', $query->render()->query );
		self::assertSame( [9, '2020-01-01'], $query->render()->parameters );
	}

	public function testUpdateAndDeleteNeedConditionsOrAllRows(): void
	{
		self::assertSame( 'UPDATE `t` SET `a` = ?', $this->client->update( 't' )->set( 'a', 1 )->allRows()->render()->query );
		self::assertSame( 'DELETE FROM `t`', $this->client->delete( 't' )->allRows()->render()->query );

		foreach( [$this->client->update( 't' )->set( 'a', 1 ), $this->client->delete( 't' )] as $query ){
			try{
				$query->render();
				self::fail( 'Expected RuntimeException for '.$query::class );
			}
			catch( RuntimeException $e ){
				self::assertStringContainsString( 'allRows()', $e->getMessage() );
			}
		}
	}

	public function testUpdateRejectsMissingTableOrValues(): void
	{
		try{
			$this->client->update()->set( 'a', 1 )->allRows()->render();
			self::fail( 'Expected RuntimeException on missing table' );
		}
		catch( RuntimeException ){}
		$this->expectException( RuntimeException::class );
		$this->client->update( 't' )->allRows()->render();
	}

	protected function setUp(): void
	{
		$this->client	= new Client( new PDO( 'sqlite::memory:' ) );
	}

	/**
	 *	@param		Condition		$condition
	 *	@return		array{string,array<int,string|int|float|NULL>}
	 */
	protected static function renderCondition( Condition $condition ): array
	{
		$parameters	= [];
		$string		= $condition->render( $parameters );
		return [$string, $parameters];
	}
}

enum RenderTestStatus: string
{
	case Paid	= 'paid';
}
