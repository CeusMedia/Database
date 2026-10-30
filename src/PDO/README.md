# PDO

[← Database](../../README.md)

Enhanced PDO wrapper: connections with transactions, logging and pooling, plus a
table abstraction (CRUD, entities, caching, a small condition-value query language).

For practical examples of everyday and special tasks, see [Use cases and recipes](#use-cases-and-recipes).

## Database Connection

```php
$dbDriver	= 'mysql';
$dbName		= 'myDatabase';
$dbUsername	= 'myDatabaseUser';
$dbPassword	= 'myDatabasePassword';

$dbc	= new \CeusMedia\Database\PDO\Connection(
	new \CeusMedia\Database\PDO\DataSourceName( $dbDriver, $dbName ),
	$dbUsername, $dbPassword
);
```

### Transactions

```php
$dbc->beginTransaction();
try{
	$table->add( $data );
	$table->edit( $primaryKey, $otherData );
	$dbc->commit();
}
catch( \Throwable $e ){
	$dbc->rollBack();
	throw $e;
}
```
Transactions can be nested safely: only the outermost `beginTransaction()` call actually starts a database transaction, and only the outermost `commit()` actually commits. If an inner `rollBack()` happened, the outermost `commit()` will roll back instead of committing, and throws a `RuntimeException` to make that failure visible instead of silently pretending success.

`getOpenTransactions()` returns the current nesting depth.

Shorter, with the same effect: `transaction()` commits, if the callback returns, and rolls back and rethrows, if it throws. The callback's result is returned.

```php
$id	= $dbc->transaction( function() use ( $table, $data, $primaryKey, $otherData ){
	$id	= $table->add( $data );
	$table->edit( $primaryKey, $otherData );
	return $id;
} );
```
A transaction left open (neither committed nor rolled back) is rolled back by PDO when the connection is closed. The connection notes that in the error log file on destruction, if set and log level contains `LOG_LEVEL_ERROR`.

### Logging

```php
$dbc->setErrorLogFile( 'logs/db/error.log' );
$dbc->setStatementLogFile( 'logs/db/statement.log' );
$dbc->setLogLevel( \CeusMedia\Database\PDO\Connection::LOG_LEVEL_ERROR );
```
Available log levels (constants on `Connection`, combinable as a bitmask):

- `LOG_LEVEL_NONE`
- `LOG_LEVEL_ERROR` (default)
- `LOG_LEVEL_STATEMENT`

Use `setLogLevelForNextStatement()` to temporarily override the log level for a single upcoming statement or query; it resets itself automatically afterward.

**Noteworthy:** `$dbc->lastQuery` (and `$table->getLastQuery()`, which reads it from the table's reader) holds the latest statement given to `query()`, `prepare()` or `exec()` - so it reflects every read and write method of a table.

### Using a connection pool

To manage several named connections (eg. read/write splitting, or several databases):

```php
$pool	= new \CeusMedia\Database\PDO\Pool();
$pool->add( 'main', $dbc, TRUE );
$pool->add( 'reporting', $otherDbc );

$dbc	= $pool->get();               // the connection flagged as default ('main')
$dbc	= $pool->get( 'reporting' );  // a specific connection by name
```

### Data source names for other drivers

`DataSourceName` supports more than MySQL - `pgsql`, `sqlite`, `firebird`, `informix` and `oci` are built in, each rendering the DSN format that driver expects:

```php
$dsn	= new \CeusMedia\Database\PDO\DataSourceName( 'pgsql', 'myDatabase' );
$dsn->setHost( 'myHost' )->setPort( 5432 )->setUsername( 'myUser' )->setPassword( 'myPassword' );
$dbc	= new \CeusMedia\Database\PDO\Connection( $dsn );
```
Username and password are taken from the DSN object, if not given as arguments.
or in one call:
```php
$dsnString	= \CeusMedia\Database\PDO\DataSourceName::renderStatic(
	'pgsql', 'myDatabase', 'myHost', 5432, 'myUser', 'myPassword'
);
```
Every part (host, database, username, password) is checked for NUL bytes and, depending on the DSN's delimiter style, either safely quoted (`pgsql`'s space-delimited style) or rejected if it contains a character that would otherwise silently corrupt or truncate the resulting DSN (the semicolon-delimited style used by `mysql`, `firebird`, `informix`, ... has no quoting mechanism at all).

## Tables

Existing database tables can be declared as tables:

### Table class

```php
class MyFirstTable extends \CeusMedia\Database\PDO\Table
{
	protected string $name			= "my_first_table";
	protected array $columns		= [
		'id',
		'maybeSomeForeignId',
		'content',
	];
	protected string $primaryKey	= 'id';
	protected array $indices		= [
		'maybeSomeForeignId',
	];
	protected int $fetchMode		= \PDO::FETCH_OBJ;
}
```
Generated columns (eg. MySQL `GENERATED ALWAYS AS`) can be declared separately - they are readable like any other column, but the table writer never writes to them:

```php
	protected array $generated		= [
		'contentLength',
	];
```
If the primary key is not auto-incremented by the database, disable that assumption so its value can be set explicitly on insert:

```php
	protected bool $autoIncrementPrimaryKey	= FALSE;
```

### Table instance

Having this defined structure, you can use a table instance for reading from and writing into the database table. Hence that you need to create a database connection beforehand.

```php
$table	= new MyFirstTable( $dbc );
```

### Caching

Every `get()`, `add()` and `edit()` call transparently caches read entries by primary key, through a PSR-16 (`Psr\SimpleCache\CacheInterface`) adapter. By default, nothing is actually cached (a no-op adapter). To enable caching, set the static configuration before creating table instances:

```php
\CeusMedia\Database\PDO\Table::$cacheClass		= \CeusMedia\Cache\Adapter\Memory::class;
\CeusMedia\Database\PDO\Table::$cacheResource	= NULL;    // passed to the adapter's constructor
```
or redeclare these static properties in a table class, to set defaults for this table only:
```php
class MyFirstTable extends \CeusMedia\Database\PDO\Table
{
	public static string $cacheClass	= \CeusMedia\Cache\Adapter\Memory::class;
	...
}
```
or share one already-built cache instance across all tables:
```php
\CeusMedia\Database\PDO\Table::$cacheInstance	= $myCache;
```
or set one on a single table instance only, overriding the static configuration:
```php
$table->setCache( $myCache );
```
Only found entries are cached, so a missing entry is looked up again next time. A misbehaving cache (eg. one that rejects a particular key as invalid per PSR-16) never breaks reading or writing - every cache call is guarded and treated as a cache miss/no-op on failure, not surfaced to the caller.

### Reading an entry

Example for getting an entry by its primary key:

```php
$entry	= $table->get( 1 );
```
The result will be an object of table columns and their values, since the fetch mode is set to object-wise by table structure:

```php
object stdObject(
    'id'                 => 1,
    'maybeSomeForeignId' => 123,
    'content'            => 'Content of first entry.'
)
```
Not having the fetch mode set would result in an associated array, which is set as default fetch mode in underlaying table reader. To change the fetch see below.

**Hint:** There are more methods to read a single entry:

- getByIndex
- getByIndices

which allow to focus on foreign indices instead of the primary key.

### Finding entries

A group of entries, filtered by a foreign key:

```php
$someEntries	= $table->getAllByIndex( 'maybeSomeForeignId', 123 );
```

A group of entries, filtered by several foreign keys:

```php
$indices = [
    'maybeSomeForeignId' => 123,
    'notExistingKey'     => 'will result in an exception',
];
$someEntries	= $table->getAllByIndices( $indices );
```
To get **all entries**, call:

```php
$allEntries	= $table->getAll();
```
which may be bad in scaling, so reduce the result set by defining limits and conditions:

```php
$conditions = ['content' => '%test%'];
$orders     = [];
$limits     = [$offset = 0, $limit = 10];

$allEntries = $table->getAll( $conditions, $orders, $limits );
```
Conditions can be indices or any other declared column - unknown columns raise a `DomainException`, so a typo never removes a filter silently. A condition value is compared by equality by default, but a prefix switches to a different SQL operator:

| Condition value | Resulting SQL |
| --- | --- |
| `'%foo%'` | `LIKE '%foo%'` |
| `'% foo'` / `'!% foo'` | `LIKE 'foo'` / `NOT LIKE 'foo'` |
| `'> 10'`, `'>= 10'`, `'< 10'`, `'<= 10'`, `'!= 10'` | comparison operators |
| `'>< 1 & 10'` / `'!>< 1 & 10'` | `BETWEEN 1 AND 10` / `NOT BETWEEN 1 AND 10` |
| `'\| 4'`, `'& 4'`, `'^ 4'`, `'<< 4'`, `'>> 4'`, `'&~ 4'` | bitwise operators |
| `'is null'` / `'is not null'` | `IS NULL` / `IS NOT NULL` |
| `NULL` | `IS NULL` |

Note the required space between an operator and its value - except for `%`/`!%`, which also works without a leading operator by simply including the `%` wildcard directly in the value, as in the first row above.

Orders are pairs of columns and directions, like:

```php
$orders	= [
    'maybeSomeForeignId' => 'DESC',
    'content'            => 'ASC',
];
```
There are more parameters possible for each of this indexing methods, which allow:

- fields: restricting columns in result set, also aliased columns and functions, like `'COUNT(*) AS number'`
- grouping: apply GROUP BY, on columns or aliases of selected fields
- having: apply HAVING, as map like conditions, eg. `['COUNT(*)' => '> 5']` or `['number' => '> 5']`

### Reading single columns

Instead of restricting columns using the `$fields` parameter (whose return type depends on the number of given fields), there are dedicated methods with a fixed return type:

| Method | Returns |
| --- | --- |
| `getField( $id, 'content' )` | value of one column of an entry by primary key (cached like `get()`) |
| `getFirstFieldByIndex( 'content', 'maybeSomeForeignId', 123 )` | value of one column of the first entry by index |
| `getFirstFieldByIndices( 'content', $indices )` | value of one column of the first entry by indices |
| `getFirstFieldsByIndex( ['id', 'content'], 'maybeSomeForeignId', 123 )` | first entry by index, reduced to some columns |
| `getFirstFieldsByIndices( ['id', 'content'], $indices )` | first entry by indices, reduced to some columns |
| `getColumnOfAllByConditions( 'content', $conditions )` | list of values of one column of all found entries |
| `getColumnsOfAllByConditions( ['id', 'content'], $conditions )` | list of all found entries, reduced to some columns |

Single values are returned as is, or decoded, for JSON and DateTime columns. Reduced entries are arrays or anonymous objects, depending on fetch mode, even if only one column is requested. If nothing is found, `NULL` is returned, or a `RangeException` is thrown, if `$strict` is enabled. Only real table columns (or `*` for all) are accepted.

```php
$content	= $table->getField( 1, 'content' );
$ids		= $table->getColumnOfAllByConditions( 'id', ['maybeSomeForeignId' => 123], ['id' => 'ASC'] );
```

### Distinct column values

```php
$topics	= $table->getDistinct( 'content', ['maybeSomeForeignId' => 123] );
```

### Counting

To count entries by a foreign key:

```php
$number	= $table->countByIndex( 'maybeSomeForeignId', 123 );
```

To count entries, filtered by several foreign keys:

```php
$number = $table->countByIndices( [
    'maybeSomeForeignId' => 123,
    'notExistingKey'     => 'will result in an exception',
] );
```
To get **all entries**, call:

```php
$number	= $table->count();
```
which may be bad in scaling, so reduce the result set by defining conditions:

```php
$Conditions	= [
    'maybeSomeForeignId' => 123,
    'content'            => '%test%',
];
$number	= $table->count( $conditions );
```
**Hint:** Counting having really large MySQL tables may be slow. `countFast()` uses `EXPLAIN` instead of `COUNT`, trading exactness for speed - the returned number is an estimate:

```php
$approximateNumber	= $table->countFast( $conditions );
```

### Checking existence

Without fetching the entry itself:

```php
$exists	= $table->has( $primaryKey );
$exists	= $table->hasByIndex( 'maybeSomeForeignId', 123 );
$exists	= $table->hasByIndices( ['maybeSomeForeignId' => 123] );
```
`has()` consults the cache first (if a fetched entry for that primary key is already cached, no query is made at all).

### Adding an entry

```php
$data = [
    'maybeSomeForeignId' => 123,
    'content'            => 'Second entry.',
];
$entryId	= $table->add( $data );
```
**Attention:** For security reasons, all HTML tags will be stripped. Set second parameter to FALSE to avoid that, if needed. Make sure to strip HTML tags of none-HTML columns manually! Since `strip_tags()` also cuts text looking like a tag (eg. `'a<b'` becomes `'a'`), disable it for plain text that may contain `<`.

Values of `NULL` are left out, so the column gets its default value. On updates, `NULL` is written.


### Updating an entry

```php
$primaryKey = 2;
$data       = [
    'maybeSomeForeignId' => 124,
    'content'            => 'Second entry - changed.',
];
$result	= $table->edit( $primaryKey, $data );
```
where the result will be the number of changed entries.

**Attention:** For security reasons, all HTML tags will be stripped. Set third parameter to FALSE to avoid that, if needed. Make sure to strip HTML tags of none-HTML columns manually!

### Updating several entries

```php
$indices = [
    'maybeSomeForeignId' => 123,
];
$data    = [
    'maybeSomeForeignId' => 124,
];
$result  = $table->editByIndices( $indices, $data );
```
where the result will be the number of changed entries.

**Attention:** For security reasons, all HTML tags will be stripped. Set third parameter to FALSE to avoid that, if needed. Make sure to strip HTML tags of none-HTML columns manually!

### Saving an entity

If you already have a fetched (and modified) entity object at hand, it can be written back directly instead of building a data array by hand:

```php
$entry->content = 'Second entry - changed again.';
$table->save( $entry );
```
This reads the primary key from the entity object itself and delegates to `edit()`. It requires the entity's class to match the table's configured `fetchEntityClass` (or `fetchEntityObject`'s class), if either is set.

### Removing an entry

```php
$primaryKey = 2;
$result     = $table->remove( $primaryKey );
```
where the result will be the number of removed entries.

### Removing several entries

```php
$result  = $table->removeByIndex( 'maybeSomeForeignId', 123 );

$indices = [
    'maybeSomeForeignId' => 123,
];
$result  = $table->removeByIndices( $indices );
```
where the result will be the number of removed entries.

### Truncating a table

```php
$table->truncate();
```
Removes all rows and resets the auto-increment counter. Unlike the other write methods, this does not return the number of removed rows.

### Change fetch mode

In your table structure class, set:

```php
    protected int $fetchMode = \PDO::[YOUR_FETCH_MODE];
```
where YOUR_FETCH_MODE is one of these standard PDO fetch modes:

- FETCH_ASSOC
- FETCH_NAMED
- FETCH_NUM
- FETCH_BOTH
- FETCH_OBJ

(`FETCH_CLASS` and `FETCH_INTO` are covered under "Entities" below.)

The currently set mode can be read back with `$table->getFetchMode()`.

## Entities

Reading from tables can return lists of arrays or anonymous objects, easily.  
To use entity classes to receive data objects, PDO's fetch mode can be set to <code>FETCH_CLASS</code>.
A table implementation needs to set <code>::fetchEntityClass</code> to a class name.

This could be an entity class, extending the library's own base entity class:
```
use CeusMedia\Database\PDO\Entity;

class MyFirstTableEntity extends Entity
{
    public string $id;
    public string $maybeSomeForeignId;
    public string $content;
}
```
Extending `Entity` is optional - any plain class with public properties works as a fetch
target for `FETCH_CLASS`, since PDO itself does not require a specific base class.
`Entity` is provided for convenience: it implements `ArrayAccess`, `Countable`,
`Iterator` and `JsonSerializable` on top of the plain properties, so an entity can
also be used like an array or iterated over, if needed.
This entity class can be linked within the table as class to use on fetch:
```
class MyFirstTable extends Table
{
    ...
    public ?string $fetchEntityClass = '\\MyProject\\MyFirstTableEntity';
}
```
Now, all indexing methods will return lists of filled entity classes. 

### The "onFetch" hook

If an entity class (used with `FETCH_CLASS` or `FETCH_INTO`) defines a public `onFetch()` method, it is called right after fetching (and after JSON/DateTime column decoding), once per entity:

```php
class MyFirstTableEntity extends Entity
{
    public string $id;
    public string $content;

    public function onFetch( \CeusMedia\Database\PDO\Table\Reader $reader, self $entity ): void
    {
        //  eg. derive a computed property, or lazy-load a related entity
    }
}
```
This is entirely optional - entities without an `onFetch()` method are unaffected.

## JSON columns

Array and object values are written transparently as JSON, for **any** column, without any configuration:

```php
$table->add( [
    'maybeSomeForeignId' => 123,
    'content'            => 'Second entry.',
    'meta'               => ['tags' => ['a', 'b'], 'archived' => FALSE],
] );
```
Reading it back, however, is opt-in per column - without configuration, you would just get the raw JSON string back:

```php
$table->setJsonColumns( ['meta'] );

$entry = $table->get( $entryId );
$entry->meta;    // (object) ['tags' => ['a', 'b'], 'archived' => FALSE]
```
`getJsonColumns()` returns the currently configured list. Configuring an unknown column throws a `DomainException`.

Decoding matches the shape of the surrounding row: array rows (eg. `FETCH_ASSOC`) decode a JSON column into an array, object rows and entities (`FETCH_OBJ`, `FETCH_CLASS`, `FETCH_INTO`) decode it into a `stdClass` object. This also applies to `getDistinct()`.

**Attention when using JSON columns together with `FETCH_CLASS`:** the entity's property for that column is first assigned the *raw* JSON string by PDO, and only afterward overwritten with the decoded value - so the property's type must accept both, eg. `string|object|null`, not just `string`. A too-narrow property type raises a clear `RuntimeException` naming the offending class and property, instead of a confusing raw `TypeError`.

## DateTime columns

`DateTime` (and `DateTimeImmutable`) values are written transparently, for **any** column, without any configuration - fractional seconds (microtime) are always included:

```php
$table->add( [
    'maybeSomeForeignId' => 123,
    'content'            => 'Second entry.',
    'occurredAt'         => new \DateTime( '2026-08-29 14:30:00.123456' ),
] );
```
Whether the microseconds actually get stored depends only on the database column, not on anything configured here: a plain `DATETIME`/`TIMESTAMP` column silently truncates to whole seconds, a `DATETIME(6)`/`TIMESTAMP(6)` column keeps them - no error either way.

Reading it back, as with JSON, is opt-in per column:

```php
$table->setDateTimeColumns( ['occurredAt'] );

$entry = $table->get( $entryId );
$entry->occurredAt;    // a DateTime instance, with or without microseconds, matching what the column stored
```
`getDateTimeColumns()` returns the currently configured list. Configuring an unknown column throws a `DomainException`. There is no separate "has microtime" setting to configure: PHP's `DateTime` constructor detects a fractional part in the fetched string automatically, whether it is there or not. This also applies to `getDistinct()`. A column value that cannot be parsed as a date/time is returned unchanged as a string, rather than throwing.

The same `FETCH_CLASS` caveat as for JSON columns applies here: the entity's property must accept both the initial raw string and the decoded `DateTime`, eg. `string|DateTime|null`, not just `string` - otherwise a clear `RuntimeException` is raised instead of a raw `TypeError`.

`DateTime` (mutable) is used, not `DateTimeImmutable`, so an entity's date can be changed in place and saved back directly:
```php
$entry->occurredAt->modify( '+1 day' );
$table->save( $entry );
```

## Use cases and recipes

The sections above describe every feature on its own. This section shows how they play
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

### Everyday tasks

#### Store form input without picking fields by hand

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

**Attention:** "Only declared columns" does not mean "only columns the user may change".
If a table has sensitive columns (eg. `status`, `role`, `price`), pick the allowed keys
before writing:

```php
$data	= array_intersect_key( $_POST, array_flip( ['customerId', 'note'] ) );
$orders->add( $data );
```

#### Filters of a search form

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

#### Pagination

`count()` and `getAll()` share the same conditions, so a paged list needs two calls:

```php
$limit	= 20;
$page	= max( 0, (int) ( $_GET['page'] ?? 0 ) );
$total	= $orders->count( $conditions );
$list	= $orders->getAll( $conditions, ['createdAt' => 'DESC'], [$page * $limit, $limit] );
$pages	= (int) ceil( $total / $limit );
```

#### Fetching a list of entries by IDs

A list of values given for a column is rendered as `(… = 1 OR … = 2 OR …)`, which works
like `IN (…)`:

```php
$selected	= $orders->getAll( ['orderId' => [3, 5, 8]] );
```

#### Options of a select box

```php
$rows		= $orders->getColumnsOfAllByConditions( ['orderId', 'note'], ['customerId' => 12], ['orderId' => 'ASC'] );
$options	= array_column( $rows, 'note', 'orderId' );    // [3 => 'Ring twice', 5 => '...']

$statuses	= $orders->getDistinct( 'status' );             // all statuses in use, eg. for a filter
```
`array_column()` works on lists of objects, too, so this needs no specific fetch mode.

#### A single value

No need to fetch a whole entry and read one property, if only one value is of interest:

```php
$note		= $orders->getField( $orderId, 'note' );
$lastId		= $orders->getFirstFieldByIndex( 'orderId', 'customerId', 12, ['createdAt' => 'DESC'] );
$customerIds	= $orders->getColumnOfAllByConditions( 'customerId', ['status' => 0] );
```

#### Insert or update

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

#### Changing many entries at once

Eg. closing all open orders of a customer, or a soft delete by status:

```php
$changed	= $orders->editByIndices( ['customerId' => 12, 'status' => 0], ['status' => 9] );
$removed	= $orders->removeByIndices( ['customerId' => 12, 'status' => 9] );
```
Both only accept declared indices (and the primary key) as filter, so a typo cannot turn
into an unfiltered update or delete of the whole table.

### Special tasks

#### Typed entities with JSON and date columns

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

#### Several tables in one transaction - even across services

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

#### Flags in a bitmask column

Store several yes/no options in one integer column and filter with bitwise operators:

```php
const FLAG_GIFT		= 1;
const FLAG_EXPRESS	= 2;
const FLAG_PAID		= 4;

$orders->add( ['customerId' => 12, 'flags' => FLAG_GIFT | FLAG_PAID] );

$giftOrders		= $orders->getAll( ['flags' => '& '.FLAG_GIFT] );   // `flags` & 1, ie. flag is set
```

#### Reports with grouping

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

#### Fast numbers for dashboards

Exact counts of large InnoDB tables are expensive. For a dashboard, an estimate is often
good enough:

```php
$approximately	= $orders->countFast( ['status' => 0] );
```

#### Table prefixes and several installations in one database

The table prefix is given on construction, so the same table class serves several
installations (or tenants, or test runs) in one database:

```php
$shopOrders		= new OrderTable( $dbc, 'shop_' );    // table "shop_orders"
$testOrders		= new OrderTable( $dbc, 'test_' );    // table "test_orders"

$tables			= $dbc->getTables( 'shop_' );          // all tables of this installation
```
Cache keys contain the prefix, too, so a shared cache does not mix up entries of
different installations.

#### Caching lookup tables

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

#### Reading from a replica, writing to the primary

```php
$pool	= new \CeusMedia\Database\PDO\Pool();
$pool->add( 'primary', $primaryDbc, TRUE );
$pool->add( 'replica', $replicaDbc );

$reportOrders	= new OrderTable( $pool->get( 'replica' ) );     // for heavy reports
$orders			= new OrderTable( $pool->get() );                // for everything that writes
```

#### Finding a slow or wrong query

Log all statements of a single request, or only the next one:

```php
$dbc->setStatementLogFile( 'logs/db/statement.log' );

$dbc->setLogLevelForNextStatement( \CeusMedia\Database\PDO\Connection::LOG_LEVEL_STATEMENT );
$number	= $orders->count( ['status' => 0] );      // only this statement is logged
echo $orders->getLastQuery();                      // SELECT COUNT(`orderId`) AS count FROM orders WHERE ...
```
The connection also counts statements in `$dbc->numberStatements` and
`$dbc->numberExecutes`, which helps to spot N+1 query problems in a request.

#### Raw SQL, when needed

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
