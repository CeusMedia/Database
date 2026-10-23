<?php
/** @noinspection SqlResolve */
/** @noinspection SqlNoDataSourceInspection */
declare(strict_types=1);

/**
 *	TestUnit of PDO table condition and order rendering (Table\Abstraction), tested on a table reader.
 *	@package		Tests.database.pdo
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 */

namespace CeusMedia\DatabaseTest\PDO\Table;

use CeusMedia\Database\PDO\Table\Reader as PdoTableReader;
use CeusMedia\DatabaseTest\PDO\TestCase;
use DomainException;
use InvalidArgumentException;
use ReflectionMethod;

/**
 *	TestUnit of PDO table condition and order rendering (Table\Abstraction), tested on a table reader.
 *	Fixture rows: 1 start/1210847252 (from SQL file), 2 alpha/apple, 3 beta/banana, 4 beta/cherry, 5 gamma/date
 *	@package		Tests.database.pdo
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 */
class ConditionTest extends TestCase
{
	protected PdoTableReader $reader;

	public function testEquals(): void
	{
		self::assertSame( 2, $this->reader->count( ['topic' => 'beta'] ) );
		self::assertSame( 1, $this->reader->count( ['id' => 3] ) );
		self::assertSame( 1, $this->reader->count( ['id' => '3'] ), 'numeric string' );
		self::assertSame( 1, $this->reader->count( ['id' => 3.0] ), 'float' );
	}

	public function testComparisonOperators(): void
	{
		self::assertSame( 2, $this->reader->count( ['id' => '> 3'] ) );
		self::assertSame( 3, $this->reader->count( ['id' => '>= 3'] ) );
		self::assertSame( 2, $this->reader->count( ['id' => '< 3'] ) );
		self::assertSame( 3, $this->reader->count( ['id' => '<= 3'] ) );
		self::assertSame( 3, $this->reader->count( ['topic' => '!= beta'] ) );
	}

	public function testComparisonOperatorExceptionOnMissingWhitespace(): void
	{
		$this->expectException( InvalidArgumentException::class );
		$this->reader->count( ['id' => '>3'] );
	}

	public function testBetween(): void
	{
		self::assertSame( 3, $this->reader->count( ['id' => '>< 2 & 4'] ) );
		self::assertSame( 2, $this->reader->count( ['id' => '!>< 2 & 4'] ) );
	}

	public function testBetweenExceptionOnMissingWhitespace(): void
	{
		$this->expectException( InvalidArgumentException::class );
		$this->reader->count( ['id' => '><2 & 4'] );
	}

	public function testBitwise(): void
	{
		//  ids having bit 1 (value 2) set: 2, 3
		$query	= $this->getConditionQuery( ['id' => '& 2'] );
		self::assertSame( "`id`  &  '2'", $query );
		self::assertSame( 2, $this->reader->count( ['id' => '& 2'] ) );
	}

	public function testBitwiseExceptionOnMissingWhitespace(): void
	{
		$this->expectException( InvalidArgumentException::class );
		$this->reader->count( ['id' => '&2'] );
	}

	public function testLike(): void
	{
		self::assertSame( 3, $this->reader->count( ['label' => '% %a%'] ), 'explicit LIKE' );
		self::assertSame( 2, $this->reader->count( ['label' => '!% %a%'] ), 'explicit NOT LIKE' );
		self::assertSame( 1, $this->reader->count( ['label' => 'ch%'] ), 'implicit LIKE by trailing wildcard' );
		self::assertSame( 2, $this->reader->count( ['label' => '%e'] ), 'implicit LIKE by leading wildcard' );
	}

	public function testIsNullAndIsNotNull(): void
	{
		self::assertSame( 0, $this->reader->count( ['label' => NULL] ) );
		self::assertSame( 0, $this->reader->count( ['label' => 'IS NULL'] ) );
		self::assertSame( 5, $this->reader->count( ['label' => 'is not null'] ), 'case insensitive' );
	}

	public function testListOfValuesIsCombinedByOr(): void
	{
		self::assertSame( 3, $this->reader->count( ['topic' => ['alpha', 'beta']] ) );
		self::assertSame( 2, $this->reader->count( ['id' => ['< 2', '> 4']] ), 'with operators' );
	}

	public function testMultipleConditionsAreCombinedByAnd(): void
	{
		self::assertSame( 1, $this->reader->count( ['topic' => 'beta', 'label' => 'cherry'] ) );
		self::assertSame( 0, $this->reader->count( ['topic' => 'alpha', 'label' => 'cherry'] ) );
	}

	public function testValuesAreEscaped(): void
	{
		self::assertSame( 0, $this->reader->count( ['label' => "x' OR '1'='1"] ) );
	}

	public function testUnknownConditionKeysAreIgnored(): void
	{
		self::assertSame( '', $this->getConditionQuery( ['not_a_column' => 1] ) );
		self::assertSame( '', $this->getConditionQuery( ['COUNT(*)' => 1] ), 'function conditions only if allowed' );
	}

	public function testFunctionConditions(): void
	{
		self::assertSame( 'COUNT(*) = 1', $this->getConditionQuery( ['COUNT(*)' => 1], TRUE ) );
		self::assertSame( 'MAX(`id`) = 1', $this->getConditionQuery( ['max(id)' => 1], TRUE ), 'function name normalized' );
		self::assertSame( 'SUM(`id`)  >  \'3\'', $this->getConditionQuery( ['SUM( id )' => '> 3'], TRUE ) );
	}

	public function testFunctionConditionExceptionOnNotAllowedFunction(): void
	{
		$this->expectException( DomainException::class );
		$this->expectExceptionMessage( 'not allowed' );
		$this->getConditionQuery( ['SLEEP(id)' => 1], TRUE );
	}

	public function testFunctionConditionExceptionOnInvalidColumn(): void
	{
		$this->expectException( DomainException::class );
		$this->expectExceptionMessage( 'not an existing column' );
		$this->getConditionQuery( ['MAX(password)' => 1], TRUE );
	}

	public function testSecureFunctionConditionExceptionOnInvalidShape(): void
	{
		$method	= new ReflectionMethod( $this->reader, 'secureFunctionCondition' );
		$this->expectException( DomainException::class );
		$this->expectExceptionMessage( 'Invalid function condition' );
		$method->invoke( $this->reader, 'MAX(id); DROP TABLE x', ['id'] );
	}

	public function testFocusedPrimaryKeyAndIndicesAreUsed(): void
	{
		$this->reader->focusPrimary( 3 );
		self::assertSame( '`id` = 3', $this->getConditionQuery( [], FALSE, TRUE, FALSE ) );
		self::assertSame( '`id` = 4', $this->getConditionQuery( ['id' => 4], FALSE, TRUE, FALSE ), 'given condition wins' );
		self::assertSame( '', $this->getConditionQuery( [], FALSE, FALSE, FALSE ) );

		$this->reader->defocus();
		$this->reader->focusIndex( 'topic', 'beta' );
		self::assertSame( "`topic` = 'beta'", $this->getConditionQuery( [], FALSE, FALSE, TRUE ) );
		self::assertSame( "`topic` = 'gamma'", $this->getConditionQuery( ['topic' => 'gamma'], FALSE, FALSE, TRUE ) );
	}

	public function testOrders(): void
	{
		$method	= new ReflectionMethod( $this->reader, 'getOrderCondition' );
		self::assertSame( '', $method->invoke( $this->reader, [] ) );
		self::assertSame( ' ORDER BY `topic` ASC, `id` DESC', $method->invoke( $this->reader, ['topic' => 'asc', 'id' => 'DESC'] ) );

		$result	= $this->reader->find( ['id'], [], ['topic' => 'DESC', 'id' => 'ASC'] );
		self::assertSame( [1, 5, 3, 4, 2], array_map( fn( $row ) => $row['id'], $result ) );
	}

	public function testOrderExceptionOnInvalidColumn(): void
	{
		$this->expectException( DomainException::class );
		$this->reader->find( [], [], ['id; DROP TABLE transactions' => 'ASC'] );
	}

	public function testOrderExceptionOnInvalidDirection(): void
	{
		$this->expectException( InvalidArgumentException::class );
		$this->reader->find( [], [], ['id' => 'ASC; DROP TABLE transactions'] );
	}

	public function testLimits(): void
	{
		$method	= new ReflectionMethod( $this->reader, 'getLimitCondition' );
		self::assertSame( '', $method->invoke( $this->reader, [] ) );
		self::assertSame( '', $method->invoke( $this->reader, [10] ), 'offset without limit' );
		self::assertSame( ' LIMIT 2 OFFSET 1', $method->invoke( $this->reader, [1, 2] ) );
		self::assertSame( ' LIMIT 2 OFFSET 1', $method->invoke( $this->reader, [-1, -2] ), 'negative values' );

		$result	= $this->reader->find( ['id'], [], ['id' => 'ASC'], [1, 2] );
		self::assertSame( [2, 3], array_map( fn( $row ) => $row['id'], $result ) );
	}

	public function testSecureValue(): void
	{
		$method	= new ReflectionMethod( $this->reader, 'secureValue' );
		self::assertSame( 'NULL', $method->invoke( $this->reader, NULL ) );
		self::assertSame( '12', $method->invoke( $this->reader, 12 ) );
		self::assertSame( '1.5', $method->invoke( $this->reader, 1.5 ) );
		self::assertSame( "'012'", $method->invoke( $this->reader, '012' ) );
		self::assertSame( $this->connection->quote( '{"a":1}' ), $method->invoke( $this->reader, ['a' => 1] ) );
	}

	public function testSecureValueExceptionOnUnencodableJson(): void
	{
		$method	= new ReflectionMethod( $this->reader, 'secureValue' );
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Encoding value as JSON failed' );
		$method->invoke( $this->reader, ['invalid' => "\xB1\x31"] );
	}

	//  --  PROTECTED  --  //

	protected function getConditionQuery( array $conditions, bool $allowFunctions = FALSE, bool $usePrimary = FALSE, bool $useIndices = FALSE ): string
	{
		$method	= new ReflectionMethod( $this->reader, 'getConditionQuery' );
		/** @var string $result */
		$result	= $method->invoke( $this->reader, $conditions, $usePrimary, $useIndices, $allowFunctions );
		return $result;
	}

	protected function setUp(): void
	{
		parent::setUp();
		$this->createTransactionsTableFromFileOnDirectConnection();
		$this->connection->exec( "INSERT INTO transactions (topic, label) VALUES ('alpha', 'apple'), ('beta', 'banana'), ('beta', 'cherry'), ('gamma', 'date')" );

		$this->reader	= new PdoTableReader( $this->connection, 'transactions', ['id', 'topic', 'label', 'timestamp'], 'id' );
		$this->reader->setIndices( ['topic', 'label'] );
	}

	protected function tearDown(): void
	{
		$this->dropTransactionsTable();
		parent::tearDown();
	}
}
