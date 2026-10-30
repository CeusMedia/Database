# Connection

[`PDO\Connection`](../api/PDO/Connection.md) is an enhanced `PDO`. Everything you know from
PDO still works – plus:

- errors are raised as exceptions by default (`PDO::ERRMODE_EXCEPTION`),
- transactions can be [nested safely](transactions.md),
- errors and statements can be [logged to files](logging.md),
- statements are counted, and `getTables()` lists the tables of the database.

## Connecting

```php
use CeusMedia\Database\PDO\Connection;
use CeusMedia\Database\PDO\DataSourceName;

$dsn	= DataSourceName::getInstance( 'mysql', 'myDatabase' )
	->setHost( 'localhost' )
	->setPort( 3306 );

$dbc	= new Connection( $dsn, 'myUser', 'myPassword' );
```

Driver options can be given as fourth argument. They are merged with the default options in
`Connection::$defaultOptions` – given options win:

```php
$dbc	= new Connection( $dsn, 'myUser', 'myPassword', [
	\PDO::ATTR_TIMEOUT	=> 5,
] );
```

!!! tip "DSN object or string"
    The constructor accepts a `DataSourceName` object or a DSN string. Username and password
    are taken from the object, if they are not given as arguments – so `new Connection( $dsn )`
    is enough, if the object holds the credentials.

## Data source names

[`PDO\DataSourceName`](../api/PDO/DataSourceName.md) renders the DSN format each driver expects.
Built in are `mysql`, `pgsql`, `sqlite`, `firebird`, `informix` and `oci`:

```php
$dsn	= new DataSourceName( 'pgsql', 'myDatabase' );
$dsn->setHost( 'myHost' )->setPort( 5432 )->setUsername( 'myUser' )->setPassword( 'myPassword' );
$dbc	= new Connection( $dsn );                     // credentials taken from DSN object
```

or in one call:

```php
$dsnString	= DataSourceName::renderStatic( 'pgsql', 'myDatabase', 'myHost', 5432, 'myUser', 'myPassword' );
```

The driver must be supported *and* loaded in PHP, otherwise a `RuntimeException` is thrown right away.

!!! info "Safe against malformed parts"
    Every part (host, database, username, password) is checked for NUL bytes and, depending on the
    delimiter style of the DSN, either quoted safely (the space-delimited style of `pgsql`) or rejected,
    if it contains a character which would silently corrupt or truncate the DSN (the semicolon-delimited
    style of `mysql`, `firebird`, `informix`, … has no quoting at all).

## Connection pool

[`PDO\Pool`](../api/PDO/Pool.md) manages several named connections, eg. for read/write splitting
or several databases:

```php
use CeusMedia\Database\PDO\Pool;

$pool	= new Pool();
$pool->add( 'main', $dbc, TRUE );        // TRUE: make it the default connection
$pool->add( 'reporting', $otherDbc );

$dbc	= $pool->get();                  // the default connection ('main')
$dbc	= $pool->get( 'reporting' );     // a connection by name
```

The first connection added becomes the default automatically. `setDefault()`, `getDefault()`
and `remove()` manage the pool later on.

## Error handling

Statements run by `query()` and `exec()` throw a `CeusMedia\Common\Exception\SQL` on failure
(after [logging it](logging.md), if enabled). It carries the driver's error code and the SQLSTATE:

```php
use CeusMedia\Common\Exception\SQL as SqlException;
use CeusMedia\Database\SQLSTATE;

try{
	$dbc->exec( 'DELETE FROM not_existing' );
}
catch( SqlException $e ){
	echo $e->getCode();                            // eg. 1146 (MySQL error code)
	echo $e->getSQLSTATE();                        // eg. 42S02
	echo SQLSTATE::getMeaning( $e->getSQLSTATE() ); // "Syntax error or access rule violation - base table or view not found"
}
```

Prepared statements (`prepare()` / `execute()`, used by most table read methods) throw
the usual `PDOException`.

## Listing tables

```php
$tables		= $dbc->getTables();           // all tables of the database (MySQL: SHOW TABLES)
$shopTables	= $dbc->getTables( 'shop_' );  // only tables with this prefix
```
