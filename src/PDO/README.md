# PDO

[← Database](../../README.md)

Enhanced PDO wrapper: connections with transactions, logging and pooling, plus a
table abstraction (CRUD, entities, caching, a small condition-value query language).

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

### Logging

```php
$dbc->setErrorLogFile( 'logs/db/error.log' );
$dbc->setStatementLogFile( 'logs/db/statement.log' );
$dbc->setLogLevel( \CeusMedia\Database\PDO\Connection\Base::LOG_LEVEL_ERROR );
```
Available log levels (constants on `Connection\Base`, combinable as a bitmask):

- `LOG_LEVEL_NONE`
- `LOG_LEVEL_ERROR` (default)
- `LOG_LEVEL_STATEMENT`

Use `setLogLevelForNextStatement()` to temporarily override the log level for a single upcoming statement or query; it resets itself automatically afterward.

### Using a connection pool

To manage several named connections (eg. read/write splitting, or several databases):

```php
$pool	= new \CeusMedia\Database\PDO\Pool();
$pool->add( 'main', $dbc, TRUE );
$pool->add( 'reporting', $otherDbc );

$dbc	= $pool->get();               // the connection flagged as default ('main')
$dbc	= $pool->get( 'reporting' );  // a specific connection by name
```

### Using the connection factory

Instead of instantiating `Connection` directly, a factory can pick the implementation matching the running PHP version:

```php
$dbc	= \CeusMedia\Database\PDO\Connection\Factory::createByPhpVersion(
	(string) new \CeusMedia\Database\PDO\DataSourceName( $dbDriver, $dbName ),
	$dbUsername, $dbPassword
);
```

### Data source names for other drivers

`DataSourceName` supports more than MySQL - `pgsql`, `sqlite`, `firebird`, `informix` and `oci` are built in, each rendering the DSN format that driver expects:

```php
$dsn	= new \CeusMedia\Database\PDO\DataSourceName( 'pgsql', 'myDatabase' );
$dsn->setHost( 'myHost' )->setPort( 5432 )->setUsername( 'myUser' )->setPassword( 'myPassword' );
$dbc	= new \CeusMedia\Database\PDO\Connection( $dsn, 'myUser', 'myPassword' );
```
or in one call:
```php
$dsnString	= \CeusMedia\Database\PDO\DataSourceName::renderStatic(
	'pgsql', 'myDatabase', 'myHost', 5432, 'myUser', 'myPassword'
);
```

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
or share one already-built cache instance across all tables:
```php
\CeusMedia\Database\PDO\Table::$cacheInstance	= $myCache;
```

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
Conditions can be indices or any other column. A condition value is compared by equality by default, but a prefix switches to a different SQL operator:

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

- fields: restricting columns in result set
- grouping: apply GROUP BY
- having: apply HAVING

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

### Adding an entry

```php
$data = [
    'maybeSomeForeignId' => 123,
    'content'            => 'Second entry.',
];
$entryId	= $table->add( $data );
```
**Attention:** For security reasons, all HTML tags will be striped. Set second parameter to FALSE to avoid that, if needed. Make sure to strip HTML tags of none-HTML columns manually!


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

**Attention:** For security reasons, all HTML tags will be striped. Set third parameter to FALSE to avoid that, if needed. Make sure to strip HTML tags of none-HTML columns manually!

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

**Attention:** For security reasons, all HTML tags will be striped. Set third parameter to FALSE to avoid that, if needed. Make sure to strip HTML tags of none-HTML columns manually!

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

### Removing several entry

```php
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
