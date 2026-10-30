# Installation

## Requirements

| Requirement | Purpose |
|-------------|---------|
| PHP **8.3** or newer | |
| `ext-pdo` and a PDO driver, eg. `ext-pdo_mysql` | database access |
| `ceus-media/common`, `ceus-media/cache` | installed by Composer automatically |
| `mysqldump` and `mysql` command line clients | only for the [MySQL export and import utilities](utilities.md) |

## Composer

```sh
composer require ceus-media/database
```

The library follows PSR-4, all classes live in namespace `CeusMedia\Database`.
Include Composer's autoloader and you are ready:

```php
require_once 'vendor/autoload.php';

use CeusMedia\Database\PDO\Connection;
use CeusMedia\Database\PDO\DataSourceName;

$dbc	= new Connection( DataSourceName::getInstance( 'mysql', 'myDatabase' ), 'myUser', 'myPassword' );
```

## Modules

The library works in two layers on the same connection, which can be mixed freely:

| Module | Content |
|--------|---------|
| **PDO** – database abstraction layer | an enhanced PDO connection plus table classes (CRUD, entities, caching, a compact condition syntax) – see [Connection](connection.md) and [Tables](tables.md) |
| **OSQL** – query builder | queries as objects for joins, `OR` groups and aggregates – see [Query builder](query-builder.md) |

## Upgrading to 0.7.3

Version 0.7.3 fixes SQL injections and silent errors, which needs some changes:

- `PDO\BetterTable` is removed – use `PDO\Table`, which has all its methods.
- `PDO\Connection\Base`, `Connection\Php81` and `Connection\Factory` are merged into `PDO\Connection` –
  use `new Connection()` and `Connection::LOG_LEVEL_*`.
- Conditions with unknown columns raise a `DomainException` instead of being ignored.
- Fields, groupings and having of `Table::getAll()` and related methods only accept columns, aliases and
  the aggregate functions `AVG`, `COUNT`, `MAX`, `MIN`, `SUM` – see [Conditions](conditions.md).
- Fetch mode `FETCH_INTO` fills a clone of the entity object per entry – see [Entities](entities.md).
- OSQL is rebuilt – see [Query builder](query-builder.md).

See the [changelog](../changelog.md) for all changes.

!!! warning "Deprecated in version 0.7, removed in version 0.8"
    - `CeusMedia\Database\DAO` – unmaintained and partly broken, use the PDO table abstraction instead.
