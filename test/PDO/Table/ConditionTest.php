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

	public function testUnknownConditionKeyException(): void
	{
		$this->expectException( DomainException::class );
		$this->expectExceptionMessage( 'Condition key "not_a_column" is not a column of table "transactions"' );
		$this->getConditionQuery( ['not_a_column' => 1] );
	}

	public function testUnknownConditionKeyBesideValidColumnException(): void
	{
		//  a mistyped column must not remove a filter silently, so all entries would be found
		$this->expectException( DomainException::class );
		$this->reader->find( [], ['topic' => 'beta', 'labl' => 'banana'] );
	}

	public function testFunctionConditionExceptionIfNotAllowed(): void
	{
		$this->expectException( DomainException::class );
		$this->expectExceptionMessage( 'Function condition "COUNT(*)" is not allowed here' );
		$this->getConditionQuery( ['COUNT(*)' => 1] );
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

	public function testSecureField(): void
	{
		$method	= new ReflectionMethod( $this->reader, 'secureField' );
		self::assertSame( '*', $method->invoke( $this->reader, '*' ) );
		self::assertSame( '`label`', $method->invoke( $this->reader, ' label ' ) );
		self::assertSame( '`label` AS `title`', $method->invoke( $this->reader, 'label AS title' ) );
		self::assertSame( '`label` AS `title`', $method->invoke( $this->reader, 'label as  title' ), 'case insensitive, whitespace' );
		self::assertSame( 'COUNT(*) AS `number`', $method->invoke( $this->reader, 'COUNT(*) AS number' ) );
		self::assertSame( 'MAX(`id`) AS `last`', $method->invoke( $this->reader, 'max( id ) AS last' ) );
	}

	public function testFindWithAggregatedFieldsAndGrouping(): void
	{
		$result	= $this->reader->find( ['topic', 'COUNT(*) AS number'], [], ['topic' => 'ASC'], [], ['topic'] );
		self::assertSame( [
			['topic' => 'alpha', 'number' => 1],
			['topic' => 'beta', 'number' => 2],
			['topic' => 'gamma', 'number' => 1],
			['topic' => 'start', 'number' => 1],
		], $result );
	}

	/**
	 *	@return		array<string,array{string}>
	 */
	public static function provideInvalidFields(): array
	{
		return [
			'subquery as alias'			=> ['(SELECT USER()) AS who'],
			'expression as alias'		=> ['id + 1 AS next'],
			'not allowed function'		=> ['SLEEP(id) AS x'],
			'function of unknown column'	=> ['MAX(password) AS x'],
			'invalid alias'				=> ['label AS `x`'],
			'alias with space'			=> ['label AS a b'],
			'unknown column'			=> ['password'],
			'wildcard as alias'			=> ['* AS all'],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'provideInvalidFields' )]
	public function testFindExceptionOnInvalidField( string $field ): void
	{
		$this->expectException( DomainException::class );
		$this->reader->find( ['id', $field] );
	}

	public function testGetExceptionOnInvalidField(): void
	{
		$this->reader->focusPrimary( 1 );
		$this->expectException( DomainException::class );
		$this->reader->get( TRUE, [], [], ['id', '(SELECT USER()) AS who'] );
	}

	public function testGroupings(): void
	{
		$method	= new ReflectionMethod( $this->reader, 'getGroupingCondition' );
		self::assertSame( '', $method->invoke( $this->reader, [] ) );
		self::assertSame( ' GROUP BY `topic`, `label`', $method->invoke( $this->reader, ['topic', ' label '] ) );
		self::assertSame( ' GROUP BY `title`', $method->invoke( $this->reader, ['title'], ['title'] ), 'alias of selected field' );

		$result	= $this->reader->find( ['topic AS name', 'COUNT(*) AS number'], ['topic' => 'beta'], [], [], ['name'] );
		self::assertSame( [['name' => 'beta', 'number' => 2]], $result );
	}

	/**
	 *	@return		array<string,array{mixed}>
	 */
	public static function provideInvalidGroupings(): array
	{
		return [
			'injected statement'	=> ['topic, (SELECT SLEEP(1))'],
			'unknown column'		=> ['password'],
			'unselected alias'		=> ['number'],
			'not a string'			=> [1],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'provideInvalidGroupings' )]
	public function testGroupingExceptionOnInvalidGrouping( mixed $grouping ): void
	{
		$this->expectException( DomainException::class );
		$this->reader->find( ['topic'], [], [], [], [$grouping] );
	}

	public function testHavingAsMap(): void
	{
		$method	= new ReflectionMethod( $this->reader, 'getHavingCondition' );
		self::assertSame( '', $method->invoke( $this->reader, [] ) );
		self::assertSame( ' HAVING COUNT(*)  >  \'1\'', $method->invoke( $this->reader, ['COUNT(*)' => '> 1'] ) );
		self::assertSame( ' HAVING `number` = 2 AND `topic` = \'beta\'', $method->invoke( $this->reader, ['number' => 2, 'topic' => 'beta'], ['number'] ) );

		$fields	= ['topic', 'COUNT(*) AS number'];
		$expected	= [['topic' => 'beta', 'number' => 2]];
		self::assertSame( $expected, $this->reader->find( $fields, [], [], [], ['topic'], ['COUNT(*)' => '> 1'] ) );
		self::assertSame( $expected, $this->reader->find( $fields, [], [], [], ['topic'], ['number' => 2] ), 'alias as key' );
		self::assertSame( $expected, $this->reader->find( $fields, [], [], [], ['topic'], ['number' => ['> 1', '< 0']] ), 'list of values' );
	}

	public function testHavingAsString(): void
	{
		$method	= new ReflectionMethod( $this->reader, 'getHavingCondition' );
		self::assertSame( ' HAVING COUNT(*)  >  \'1\'', $method->invoke( $this->reader, ['COUNT(*) > 1'] ) );
		self::assertSame( ' HAVING COUNT(*) = 2', $method->invoke( $this->reader, ['count( * )=2'] ) );
		self::assertSame( ' HAVING SUM(`id`)  !=  \'1.5\'', $method->invoke( $this->reader, ['SUM(id) <> 1.5'] ) );
		self::assertSame( ' HAVING `topic` = \'beta\'', $method->invoke( $this->reader, ["topic = 'beta'"] ) );

		$fields	= ['topic', 'COUNT(*) AS number'];
		$expected	= [['topic' => 'beta', 'number' => 2]];
		self::assertSame( $expected, $this->reader->find( $fields, [], [], [], ['topic'], ['COUNT(*) > 1'] ) );
		self::assertSame( $expected, $this->reader->find( $fields, [], [], [], ['topic'], ['number >= 2', "topic = 'beta'"] ) );
	}

	/**
	 *	@return		array<string,array{array<int|string,mixed>, class-string<\Throwable>}>
	 */
	public static function provideInvalidHaving(): array
	{
		return [
			'injected OR'				=> [['COUNT(*) > 1 OR 1=1'], InvalidArgumentException::class],
			'subquery'					=> [['(SELECT 1) > 0'], InvalidArgumentException::class],
			'unquoted string'			=> [['topic = beta'], InvalidArgumentException::class],
			'quote in quoted string'	=> [["topic = 'a' OR 'b'"], InvalidArgumentException::class],
			'not a string'				=> [[1], InvalidArgumentException::class],
			'number as key'				=> [['1 = 1'], DomainException::class],
			'unknown column'			=> [['password' => 1], DomainException::class],
			'not allowed function'		=> [['SLEEP(id)' => 1], DomainException::class],
			'injected key'				=> [['1=1 OR id' => 1], DomainException::class],
			'unselected alias'			=> [['number' => 1], DomainException::class],
		];
	}

	/**
	 *	@param		array<int|string,mixed>			$having
	 *	@param		class-string<\Throwable>		$exception
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'provideInvalidHaving' )]
	public function testHavingExceptionOnInvalidCondition( array $having, string $exception ): void
	{
		$this->expectException( $exception );
		$this->reader->find( ['topic'], [], [], [], ['topic'], $having );
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
