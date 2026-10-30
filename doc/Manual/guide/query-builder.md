# Query builder

The OSQL module builds SQL queries as objects and executes them as prepared statements.
It complements the [table abstraction](tables.md) where a table is not enough:
joins, `OR` combinations, aggregates over several tables, statements on tables without
a table class.

Built for MySQL and MariaDB: identifiers are quoted by backticks.

```php
use CeusMedia\Database\OSQL\Client;

$client	= new Client( $dbc );                       // a PDO\Connection (or any PDO)

$orders	= $client->select( 'orders' )
	->where( 'status', 1 )
	->order( 'createdAt', 'DESC' )
	->limit( 10 )
	->all();
```

## Reading

`$client->select( $table )` starts a query; the table may have an alias: `'orders AS o'` or `'orders o'`.

```php
$query	= $client->select( 'orders' )->where( 'customerId', 12 );

$query->all();              // all rows, as objects by default
$query->first();            // first row or NULL
$query->count();            // number of rows, ignoring order, limit and offset
$query->exists();           // TRUE, if at least one row is found

$client->select( 'orders' )->fields( 'orderId' )->where( 'customerId', 12 )->column();      // [3, 7, 9]
$client->select( 'orders' )->fields( 'SUM(total)' )->where( 'customerId', 12 )->value();    // '149.90'
```

`fields()` selects columns, by default all (`*`). A field can be a column (`'total'`), a qualified
column (`'o.total'`), a wildcard (`'o.*'`), an aggregate function (`'COUNT(*)'`, `'SUM(o.total)'`,
`'COUNT(DISTINCT customerId)'`) and have an alias (`'SUM(o.total) AS sum'`).
`distinct()` removes duplicate rows.

## Conditions

```php
->where( 'status', 1 )                              // `status` = 1
->where( 'total', 100, Condition::OP_GT )           // `total` > 100
->where( 'total', 100, '>' )                        // the same
->where( 'status', [1, 2] )                         // `status` IN (1, 2)
->where( 'status', [8, 9], '!=' )                   // `status` NOT IN (8, 9)
->where( 'deletedAt', NULL )                        // `deletedAt` IS NULL
->where( 'deletedAt', NULL, '!=' )                  // `deletedAt` IS NOT NULL
->where( 'createdAt', new DateTime( '-7 days' ), '>=' )
```

Values are always bound as parameters. Booleans are bound as `0` or `1`, date/time objects as
`Y-m-d H:i:s`, backed enums by their value. An empty list never matches (`IN`) or always matches (`NOT IN`).

Operators: `=`, `!=` (or `<>`), `>`, `>=`, `<`, `<=`, `LIKE`, `NOT LIKE`, `IN`, `NOT IN`,
`BETWEEN`, `NOT BETWEEN`, `IS NULL`, `IS NOT NULL` – see the constants `Condition::OP_*`.
Conditions can also be created by factory methods:

```php
use CeusMedia\Database\OSQL\Condition;

->where( Condition::between( 'total', 10, 50 ) )
->where( Condition::like( 'name', 'Mü%' ) )
->where( Condition::notIn( 'status', [8, 9] ) )
->where( Condition::isNotNull( 'paidAt' ) )
```

### AND, OR and groups

`where()` and `and()` combine by `AND`, `or()` by `OR`. The combination goes from left to right,
each step with everything before – there is no "`AND` before `OR`":

```php
->where( 'a', 1 )->and( 'b', 2 )->or( 'c', 3 )      // (a = 1 AND b = 2) OR c = 3
->where( 'a', 1 )->or( 'b', 2 )->and( 'c', 3 )      // (a = 1 OR b = 2) AND c = 3
```

For other combinations, use groups. Nested groups are always put in brackets:

```php
use CeusMedia\Database\OSQL\Condition\Group;

->where( 'customerId', 12 )
->and( Group::or( Condition::eq( 'status', 0 ), Condition::lt( 'createdAt', '2026-01-01' ) ) )
// `customerId` = 12 AND (`status` = 0 OR `createdAt` < '2026-01-01')
```

## Joins

```php
$rows	= $client->select( 'orders AS o' )
	->fields( 'o.orderId', 'o.total', 'c.name' )
	->join( 'customers AS c', 'o.customerId', 'c.customerId' )      // ON o.customerId = c.customerId
	->where( 'c.country', 'DE' )
	->all();

$client->select( 'orders' )->join( 'customers', 'customerId' );    // USING (customerId)
$client->select( 'customers' )->leftJoin( 'orders', 'customerId' )->where( 'orders.orderId', NULL );   // without orders
```

`join()` is an inner join, `leftJoin()` and `rightJoin()` are outer joins.

## Grouping and aggregates

```php
$rows	= $client->select( 'orders AS o' )
	->fields( 'c.name', 'COUNT(*) AS number', 'SUM(o.total) AS sum' )
	->join( 'customers AS c', 'o.customerId', 'c.customerId' )
	->groupBy( 'c.name' )
	->having( 'sum', 500, '>' )                     // alias of a selected field
	->having( 'COUNT(*)', 2, '>=' )                 // or aggregate function
	->order( 'sum', 'DESC' )
	->all();
```

Aggregate functions are `AVG`, `COUNT`, `MAX`, `MIN` and `SUM`.

## Order and pages

```php
$query	= $client->select( 'orders' )->where( 'status', 1 )->order( 'createdAt', 'DESC' );
$total	= $query->count();                          // for the pagination
$rows	= $query->page( 2, 20 )->all();             // rows 21 to 40, same as limit( 20 )->offset( 20 )
```

## Fetching

Rows are fetched as objects (`PDO::FETCH_OBJ`). The fetch mode can be changed for the client
or a single query, and rows can be fetched into a class:

```php
$client->setFetchMode( PDO::FETCH_ASSOC );                         // default of client
$client->select( 'orders' )->fetchMode( PDO::FETCH_NUM )->all();   // this query only
$client->select( 'orders' )->fetchAs( Order::class )->first();     // instance of Order
```

## Writing

```php
$orderId	= $client->insert( 'orders' )
	->setAll( ['customerId' => 12, 'total' => 49.9, 'createdAt' => new DateTime()] )
	->execute();                                    // ID of inserted row

$client->insert( 'stock' )                          // several rows at once
	->addRow( ['productId' => 1, 'quantity' => 5] )
	->addRow( ['productId' => 2, 'quantity' => 7] )
	->onDuplicateKeyUpdate( 'quantity' )            // update existing rows instead of failing
	->execute();

$changed	= $client->update( 'orders' )->set( 'status', 2 )->where( 'orderId', $orderId )->execute();
$removed	= $client->delete( 'orders' )->where( 'status', 9 )->execute();
```

`update()` and `delete()` return the number of changed rows.

!!! warning "Changing all rows needs `allRows()`"
    An update or delete without conditions fails – to prevent changing a whole table by a
    forgotten `where()`. To really change all rows, say so:
    `$client->delete( 'sessions' )->allRows()->execute();`

## Transactions

```php
$orderId	= $client->transaction( function( Client $client ) use ( $order, $items ){
	$orderId	= $client->insert( 'orders' )->setAll( $order )->execute();
	foreach( $items as $item )
		$client->insert( 'items' )->setAll( ['orderId' => $orderId] + $item )->execute();
	return $orderId;
} );
```

Commits, if the callback returns, rolls back and rethrows, if it throws. With a `PDO\Connection`,
transactions are [nestable](transactions.md#nested-transactions) – also together with table classes
on the same connection.

## Debugging

After execution, a query holds the statement and its parameters:

```php
$query	= $client->select( 'orders' )->where( 'status', [1, 2] );
$rows	= $query->all();
echo $query->statement;         // SELECT * FROM `orders` WHERE `status` IN (?, ?)
print_r( $query->parameters );  // [1, 2]
```

`render()` returns both without executing. With a `PDO\Connection`, statements are also
[logged](logging.md) and noted as `lastQuery`.

## Safety

- **Values** are always bound as parameters, never put into the statement.
- **Identifiers** (tables, columns, aliases) may only contain letters, digits and underscores,
  columns may be qualified by a table (`o.total`), functions must be on the list above.
  Everything else raises an `InvalidArgumentException`, already when the query is built.
  Identifiers are quoted by backticks.
- Still, take identifiers from user input (eg. a sort column) only from a list of allowed values:
  a valid column is not necessarily one the user may sort or filter by.
