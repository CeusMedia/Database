# OSQL

[← Database](../../README.md)

Object-oriented SQL query builder for MySQL and MariaDB: build queries as objects, execute them as
prepared statements. It complements the [PDO table abstraction](../PDO/README.md) where a table class
is not enough - joins, `OR` combinations, aggregates, tables without a table class.

The full guide is part of the [manual](../../doc/Manual/guide/query-builder.md).

## Connect

```php
use CeusMedia\Database\OSQL\Client;
use CeusMedia\Database\PDO\Connection;
use CeusMedia\Database\PDO\DataSourceName;

$dsn	= DataSourceName::getInstance( 'mysql', 'shop' )
	->setHost( 'localhost' )->setUsername( 'myUser' )->setPassword( 'myPassword' );
$client	= new Client( new Connection( $dsn ) );
```

## Read

```php
use CeusMedia\Database\OSQL\Condition;
use CeusMedia\Database\OSQL\Condition\Group;

$orders	= $client->select( 'orders' )
	->where( 'status', [1, 2] )                         // IN
	->and( 'total', 100, '>' )
	->and( Group::or( Condition::isNull( 'deletedAt' ), Condition::gt( 'deletedAt', '2026-01-01' ) ) )
	->order( 'createdAt', 'DESC' )
	->page( 1, 20 )
	->all();

$order	= $client->select( 'orders' )->where( 'orderId', 5 )->first();
$ids	= $client->select( 'orders' )->fields( 'orderId' )->where( 'customerId', 12 )->column();
$sum	= $client->select( 'orders' )->fields( 'SUM(total)' )->where( 'customerId', 12 )->value();
$number	= $client->select( 'orders' )->where( 'status', 1 )->count();

$rows	= $client->select( 'orders AS o' )
	->fields( 'c.name', 'COUNT(*) AS number', 'SUM(o.total) AS sum' )
	->join( 'customers AS c', 'o.customerId', 'c.customerId' )
	->groupBy( 'c.name' )
	->having( 'sum', 500, '>' )
	->all();
```

## Write

```php
$orderId	= $client->insert( 'orders' )->setAll( ['customerId' => 12, 'total' => 49.9] )->execute();
$changed	= $client->update( 'orders' )->set( 'status', 2 )->where( 'orderId', $orderId )->execute();
$removed	= $client->delete( 'orders' )->where( 'status', 9 )->execute();
$client->delete( 'sessions' )->allRows()->execute();   // without conditions only by allRows()
```

## Safety

Values are always bound as parameters. Identifiers (tables, columns, aliases, functions) are validated
and quoted - anything else than names, qualified names, `*` and the aggregate functions `AVG`, `COUNT`,
`MAX`, `MIN`, `SUM` raises an `InvalidArgumentException`.
