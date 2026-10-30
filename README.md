# Database

![Branch](https://img.shields.io/badge/Branch-0.7.x-blue?style=flat-square)
![Release](https://img.shields.io/badge/Release-0.7.3-blue?style=flat-square)
![PHP version](https://img.shields.io/badge/PHP-%5E8.3-blue?style=flat-square&color=777BB4)
![PHPStan level](https://img.shields.io/badge/PHPStan_level-9+strict-darkgreen?style=flat-square)
[![Monthly downloads](https://img.shields.io/packagist/dt/ceus-media/database.svg?style=flat-square)](https://packagist.org/packages/ceus-media/database)
[![Package version](https://img.shields.io/packagist/v/ceus-media/database.svg?style=flat-square)](https://packagist.org/packages/ceus-media/database)
[![License](https://img.shields.io/packagist/l/ceus-media/database.svg?style=flat-square)](https://packagist.org/packages/ceus-media/database)

PHP database access for MySQL and MariaDB (and other PDO drivers): an enhanced PDO connection, a table abstraction with entities, caching and a compact condition syntax - and a query builder for everything beyond.

```php
use CeusMedia\Database\PDO\Connection;
use CeusMedia\Database\PDO\DataSourceName;
use CeusMedia\Database\PDO\Table;
use CeusMedia\Database\OSQL\Client;

class OrderTable extends Table
{
	protected string $name			= 'orders';
	protected array $columns		= ['orderId', 'customerId', 'status', 'total', 'createdAt'];
	protected string $primaryKey	= 'orderId';
	protected array $indices		= ['customerId', 'status'];
}

$dsn	= DataSourceName::getInstance( 'mysql', 'shop' )->setUsername( 'user' )->setPassword( 'password' );
$dbc	= new Connection( $dsn );
$orders	= new OrderTable( $dbc );

$orderId	= $orders->add( ['customerId' => 12, 'status' => 0, 'total' => 49.9] );
$open		= $orders->getAll( ['status' => 0, 'total' => '>= 20'], ['createdAt' => 'DESC'], [0, 10] );

$dbc->transaction( fn() => $orders->edit( $orderId, ['status' => 1] ) );

$topCustomers	= ( new Client( $dbc ) )->select( 'orders AS o' )
	->fields( 'c.name', 'SUM(o.total) AS sum' )
	->join( 'customers AS c', 'o.customerId', 'c.customerId' )
	->groupBy( 'c.name' )
	->having( 'sum', 1000, '>' )
	->all();
```

## Installation

Requirements: PHP 8.3 or newer, extension `pdo` and the PDO driver of your database, eg. `pdo_mysql`.

```sh
composer require ceus-media/database
```

## Modules

- **[PDO](src/PDO/README.md)** - an enhanced PDO connection with nestable transactions (`transaction()`), error and statement logging, connection pools and DSNs for many drivers, plus a table abstraction: CRUD without SQL, entities, JSON and DateTime columns, PSR-16 caching and a compact condition syntax (`'>= 10'`, `'%foo%'`, lists of values). See its [use cases and recipes](src/PDO/README.md#use-cases-and-recipes).
- **[OSQL](src/OSQL/README.md)** - a query builder for MySQL and MariaDB: queries as objects, executed as prepared statements - for joins, `OR` groups and aggregates beyond the table abstraction.

In both modules, values are always quoted or bound, and column names, functions and aliases are validated against whitelists.

## Documentation

A manual with guides, recipes and an API reference is built by MkDocs from `doc/Manual/`: run `composer dev:docs`, then open `doc/Site/index.html` - or `composer dev:docs-serve` for a live preview. Changes per version are listed in the [changelog](CHANGELOG.md).

## Upgrading to 0.7.3

Version 0.7.3 fixes SQL injections and silent errors, which needs some changes:

- `PDO\BetterTable` is removed - use `PDO\Table`, which has all its methods.
- `PDO\Connection\Base`, `Connection\Php81` and `Connection\Factory` are merged into `PDO\Connection` - use `new Connection()` and `Connection::LOG_LEVEL_*`.
- Conditions with unknown columns raise a `DomainException` instead of being ignored.
- Fields, groupings and having of `Table::getAll()` and related methods only accept columns, aliases and the aggregate functions `AVG`, `COUNT`, `MAX`, `MIN`, `SUM`. Having is given as map, eg. `['COUNT(*)' => '> 5']`.
- Fetch mode `FETCH_INTO` fills a clone of the entity object per entry.
- OSQL is rebuilt - see its [README](src/OSQL/README.md).

See the [changelog](CHANGELOG.md) for all changes.

## Deprecations

These parts are deprecated and will be removed in version 0.8:

- `CeusMedia\Database\DAO` - unmaintained and partly broken, use the PDO table abstraction instead.

## Development

Tests need a MySQL/MariaDB database: copy `test/config/test.ini.dist` to `test/config/test.ini` and set the connection. Since all tests share this database, do not run several test runs at once.

| Composer script | Purpose |
| --- | --- |
| `composer dev:all` | syntax check, PHPStan and unit tests |
| `composer dev:phpunit` | unit tests, writing coverage reports to `doc/Test/` |
| `composer dev:phpunit-coverage` | unit tests, showing code coverage on the console, too |
| `composer dev:phpstan` | static analysis (level 9 + strict rules) |
| `composer dev:test-syntax` | PHP syntax check |
| `composer dev:docs` | generate API reference and build documentation (MkDocs) into `doc/Site/` |
| `composer dev:docs-serve` | live preview of documentation on http://localhost:8000 |
