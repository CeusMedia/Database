# Database

![Branch](https://img.shields.io/badge/Branch-0.7.x-blue?style=flat-square)
![Release](https://img.shields.io/badge/Release-0.7.2-blue?style=flat-square)
![PHP version](https://img.shields.io/badge/PHP-%5E8.3-blue?style=flat-square&color=777BB4)
![PHPStan level](https://img.shields.io/badge/PHPStan_level-9+strict-darkgreen?style=flat-square)
[![Monthly downloads](https://img.shields.io/packagist/dt/ceus-media/database.svg?style=flat-square)](https://packagist.org/packages/ceus-media/database)
[![Package version](https://img.shields.io/packagist/v/ceus-media/database.svg?style=flat-square)](https://packagist.org/packages/ceus-media/database)
[![License](https://img.shields.io/packagist/l/ceus-media/database.svg?style=flat-square)](https://packagist.org/packages/ceus-media/database)

PHP database access

## Installation

### Composer

Install this library using composer:

```
composer require ceus-media/Database
```

Within your code, load library:

```php
require_once 'vendor/autoload.php';
```

## Modules

This library offers two independent ways to work with a database:

- **[PDO](src/PDO/README.md)** - an enhanced PDO wrapper: connections with transactions, logging and pooling, plus a table abstraction (CRUD, entities, caching, a small condition-value query language).
- **[OSQL](src/OSQL/README.md)** - an object-oriented SQL query builder (`Select`, `Condition`, `Table`, `Client`) for building and executing queries as objects.

See each module's own README for code examples.

## Deprecations

These parts are deprecated and will be removed in version 0.8:

- `CeusMedia\Database\DAO` - unmaintained and partly broken, use the PDO table abstraction instead.
- `CeusMedia\Database\PDO\BetterTable` - use `CeusMedia\Database\PDO\Table`, which has all its methods.

## Development

Tests need a MySQL/MariaDB database: copy `test/config/test.ini.dist` to `test/config/test.ini` and set the connection. Since all tests share this database, do not run several test runs at once.

| Composer script | Purpose |
| --- | --- |
| `composer dev:all` | syntax check, PHPStan and unit tests |
| `composer dev:phpunit` | unit tests, writing coverage reports to `doc/Test/` |
| `composer dev:phpunit-coverage` | unit tests, showing code coverage on the console, too |
| `composer dev:phpstan` | static analysis (level 9 + strict rules) |
| `composer dev:test-syntax` | PHP syntax check |
