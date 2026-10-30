# Use cases and recipes

The other pages of this guide describe every feature on its own. This section shows how they play
together in typical tasks - first everyday work, then some more special ones. All examples
use this table:

```php
class OrderTable extends \CeusMedia\Database\PDO\Table
{
	protected string $name			= 'orders';
	protected array $columns		= [
		'orderId',
		'customerId',
		'status',
		'flags',
		'total',
		'note',
		'meta',
		'createdAt',
	];
	protected string $primaryKey	= 'orderId';
	protected array $indices		= [
		'customerId',
		'status',
	];
	protected int $fetchMode		= \PDO::FETCH_OBJ;
}

$orders	= new OrderTable( $dbc );
```

## Everyday tasks

### Store form input without picking fields by hand

`add()` and `edit()` only write keys that are declared columns - anything else in the
given data is ignored. So a submitted form can be passed in as a whole, without an
`Unknown column` error for submit buttons, CSRF tokens and the like:

```php
$orderId	= $orders->add( $_POST );          // eg. ['customerId' => 12, 'note' => 'Ring twice', 'save' => '1', 'csrf' => '...']
$orders->edit( $orderId, $_POST );
```
HTML tags are stripped from string values by default, an auto-incremented primary key
inside the data is ignored. Values are quoted - numeric strings stay strings, so a zip
code like `'01067'` keeps its leading zero.

!!! warning "Declared columns are not the same as allowed columns"
    "Only declared columns" does not mean "only columns the user may change". If a table has sensitive columns (eg. `status`, `role`, `price`), pick the allowed keys
    before writing:

    ```php
    $data	= array_intersect_key( $_POST, array_flip( ['customerId', 'note'] ) );
    $orders->add( $data );
    ```

### Filters of a search form

The condition values of `getAll()` and `count()` already contain the operator, so filter
input can be mapped to conditions directly, and empty filters are simply left out:

```php
$conditions	= [];
if( '' !== ( $filter['note'] ?? '' ) )
	$conditions['note']			= '%'.$filter['note'].'%';            // LIKE
if( '' !== ( $filter['minTotal'] ?? '' ) )
	$conditions['total']		= '>= '.(float) $filter['minTotal'];  // comparison
if( [] !== ( $filter['status'] ?? [] ) )
	$conditions['status']		= $filter['status'];                  // list of values: combined by OR
if( '' !== ( $filter['from'] ?? '' ) && '' !== ( $filter['to'] ?? '' ) )
	$conditions['createdAt']	= '>< '.$filter['from'].' & '.$filter['to'];  // BETWEEN

$list	= $orders->getAll( $conditions, ['createdAt' => 'DESC'] );
```
All values are quoted, so user input in conditions cannot inject SQL. Condition and order
columns as well as order directions are checked against the table declaration and rejected
with an exception, if unknown - so map filter names to columns yourself, as above, instead
of passing request data through.

### Pagination

`count()` and `getAll()` share the same conditions, so a paged list needs two calls:

```php
$limit	= 20;
$page	= max( 0, (int) ( $_GET['page'] ?? 0 ) );
$total	= $orders->count( $conditions );
$list	= $orders->getAll( $conditions, ['createdAt' => 'DESC'], [$page * $limit, $limit] );
$pages	= (int) ceil( $total / $limit );
```

### Fetching a list of entries by IDs

A list of values given for a column is rendered as `(… = 1 OR … = 2 OR …)`, which works
like `IN (…)`:

```php
$selected	= $orders->getAll( ['orderId' => [3, 5, 8]] );
```

### Options of a select box

```php
$rows		= $orders->getColumnsOfAllByConditions( ['orderId', 'note'], ['customerId' => 12], ['orderId' => 'ASC'] );
$options	= array_column( $rows, 'note', 'orderId' );    // [3 => 'Ring twice', 5 => '...']

$statuses	= $orders->getDistinct( 'status', [] );         // all statuses in use, eg. for a filter
```
`array_column()` works on lists of objects, too, so this needs no specific fetch mode.

### A single value

No need to fetch a whole entry and read one property, if only one value is of interest:

```php
$note		= $orders->getField( $orderId, 'note' );
$lastId		= $orders->getFirstFieldByIndex( 'orderId', 'customerId', 12, ['createdAt' => 'DESC'] );
$customerIds	= $orders->getColumnOfAllByConditions( 'customerId', ['status' => 0] );
```

### Insert or update

```php
if( $orders->has( $orderId ) )
	$orders->edit( $orderId, $data );
else
	$orderId	= $orders->add( $data );
```
or based on an index, eg. to keep only one open order per customer:

```php
$openOrderId	= $orders->getFirstFieldByIndices( 'orderId', ['customerId' => 12, 'status' => 0] );
if( NULL === $openOrderId )
	$openOrderId	= $orders->add( ['customerId' => 12, 'status' => 0] );
```

### Changing many entries at once

Eg. closing all open orders of a customer, or a soft delete by status:

```php
$changed	= $orders->editByIndices( ['customerId' => 12, 'status' => 0], ['status' => 9] );
$removed	= $orders->removeByIndices( ['customerId' => 12, 'status' => 9] );
```
Both only accept declared indices (and the primary key) as filter, so a typo cannot turn
into an unfiltered update or delete of the whole table.

## Special tasks

### Typed entities with JSON and date columns

Combine an entity class, JSON and DateTime columns and `save()`, to work with real types
instead of strings:

```php
class Order extends \CeusMedia\Database\PDO\Entity
{
	public int|string $orderId;
	public int|string $customerId;
	public int|string $status;
	public int|string $flags;
	public float|string $total;
	public ?string $note				= NULL;
	public string|object|null $meta		= NULL;
	public string|\DateTime|null $createdAt	= NULL;
}

class OrderTable extends \CeusMedia\Database\PDO\Table
{
	...
	protected int $fetchMode				= \PDO::FETCH_CLASS;
	protected ?string $fetchEntityClass		= Order::class;
	protected array $jsonColumns			= ['meta'];
	protected array $dateTimeColumns		= ['createdAt'];
}

$order	= $orders->get( $orderId );               // an Order instance
$order->meta->trackingCode	= 'ABC123';          // decoded JSON, written back as JSON
$order->createdAt->modify( '+1 day' );           // a DateTime, written back as datetime string
$orders->save( $order );

echo json_encode( $order );                      // Entity is JsonSerializable, eg. for an API response
```

### Several tables in one transaction - even across services

Since transactions can be nested, each service method may open its own transaction.
Called alone, it commits by itself; called inside another transaction, it becomes part
of it, and the outermost commit decides:

```php
class OrderService
{
	public function placeOrder( array $order, array $items ): int
	{
		return $this->dbc->transaction( function() use ( $order, $items ){
			$orderId	= $this->orders->add( $order );
			foreach( $items as $item )
				$this->items->add( ['orderId' => $orderId] + $item );
			$this->stock->reserve( $items );            // may open a transaction of its own
			return $orderId;
		} );
	}
}
```
`transaction()` commits, if the callback returns, and rolls back and rethrows, if it throws.
If any inner part rolls back, the whole order is rolled back - even if the inner exception
has been caught: then the outermost commit throws a `RuntimeException`, instead of silently
reporting success.

### Flags in a bitmask column

Store several yes/no options in one integer column and filter with bitwise operators:

```php
const FLAG_GIFT		= 1;
const FLAG_EXPRESS	= 2;
const FLAG_PAID		= 4;

$orders->add( ['customerId' => 12, 'flags' => FLAG_GIFT | FLAG_PAID] );

$giftOrders		= $orders->getAll( ['flags' => '& '.FLAG_GIFT] );   // `flags` & 1, ie. flag is set
```

### Reports with grouping

Selected fields may contain aggregate functions with an alias, combined with groupings
and having:

```php
$report	= $orders->getAll(
	['status' => [1, 2]],                                   // conditions
	[],                                                     // orders
	[],                                                     // limits
	['customerId', 'COUNT(*) AS number', 'SUM(total) AS revenue'],
	['customerId'],                                         // GROUP BY
	['COUNT(*)' => '> 5'],                                  // HAVING
);
//  [{customerId: 12, number: 8, revenue: '1234.50'}, ...]
```
Like conditions, fields, groupings and having are validated and masked: fields may only be
columns or `COUNT`, `SUM`, `AVG`, `MIN` and `MAX` of a column, with a simple alias; groupings and
having keys may only be columns, those functions or aliases of selected fields. Having values use
the condition syntax, so `['revenue' => '>= 1000']` works, too.

### Fast numbers for dashboards

Exact counts of large InnoDB tables are expensive. For a dashboard, an estimate is often
good enough:

```php
$approximately	= $orders->countFast( ['status' => 0] );
```

### Table prefixes and several installations in one database

The table prefix is given on construction, so the same table class serves several
installations (or tenants, or test runs) in one database:

```php
$shopOrders		= new OrderTable( $dbc, 'shop_' );    // table "shop_orders"
$testOrders		= new OrderTable( $dbc, 'test_' );    // table "test_orders"

$tables			= $dbc->getTables( 'shop_' );          // all tables of this installation
```
Cache keys contain the prefix, too, so a shared cache does not mix up entries of
different installations.

### Caching lookup tables

Entries which are read often but rarely change (countries, settings, users of the
current session, ...) can be cached by primary key, for one table only:

```php
class CountryTable extends \CeusMedia\Database\PDO\Table
{
	public static string $cacheClass	= \CeusMedia\Cache\Adapter\Memory::class;
	...
}

$country	= $countries->get( 'DE' );        // query
$country	= $countries->get( 'DE' );        // from cache
```
All writing methods of the table keep the cache up to date. Changes made outside of the
table - by raw SQL or another application - are not noticed, though. Use a cache with a
lifetime in that case, or avoid caching for tables written that way.

### Reading from a replica, writing to the primary

```php
$pool	= new \CeusMedia\Database\PDO\Pool();
$pool->add( 'primary', $primaryDbc, TRUE );
$pool->add( 'replica', $replicaDbc );

$reportOrders	= new OrderTable( $pool->get( 'replica' ) );     // for heavy reports
$orders			= new OrderTable( $pool->get() );                // for everything that writes
```

### Finding a slow or wrong query

Log all statements of a single request, or only the next one:

```php
$dbc->setStatementLogFile( 'logs/db/statement.log' );

$dbc->setLogLevelForNextStatement( \CeusMedia\Database\PDO\Connection::LOG_LEVEL_STATEMENT );
$number	= $orders->count( ['status' => 0] );      // only this statement is logged
echo $orders->getLastQuery();                      // SELECT COUNT(`orderId`) AS count FROM orders WHERE ...
```
The connection also counts statements in `$dbc->numberStatements` and
`$dbc->numberExecutes`, which helps to spot N+1 query problems in a request.

### Raw SQL, when needed

The connection is a `PDO` object, so for joins, subqueries or vendor-specific SQL, plain
PDO is always at hand - including logging and the nested transactions described above:

```php
$statement	= $dbc->prepare( '
	SELECT o.orderId, o.total, c.name
	FROM orders AS o
	JOIN customers AS c ON c.customerId = o.customerId
	WHERE o.status = :status' );
$statement->execute( ['status' => 0] );
$rows	= $statement->fetchAll( \PDO::FETCH_OBJ );
```
