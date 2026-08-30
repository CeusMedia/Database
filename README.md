# Database

![Branch](https://img.shields.io/badge/Branch-0.6.x-blue?style=flat-square)
![Release](https://img.shields.io/badge/Release-0.6.8-blue?style=flat-square)
![PHP version](https://img.shields.io/badge/PHP-%5E8.1-blue?style=flat-square&color=777BB4)
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
