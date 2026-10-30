# Tables

An existing database table is declared once, as a class extending [`PDO\Table`](../api/PDO/Table.md).
From then on, a table instance reads and writes this table – without writing SQL.

## Declaring a table

```php
use CeusMedia\Database\PDO\Table;

class OrderTable extends Table
{
	protected string $name			= 'orders';
	protected array $columns		= [
		'orderId',
		'customerId',
		'status',
		'total',
		'note',
		'createdAt',
	];
	protected string $primaryKey	= 'orderId';
	protected array $indices		= [
		'customerId',
		'status',
	];
	protected int $fetchMode		= \PDO::FETCH_OBJ;
}
```

| Property | Meaning |
|----------|---------|
| `$name` | table name, without prefix – **required** |
| `$columns` | all columns to read and write – **required** |
| `$primaryKey` | column of the primary key |
| `$indices` | columns usable as index in `…ByIndex()` / `…ByIndices()` methods |
| `$generated` | generated columns: readable, but never written |
| `$autoIncrementPrimaryKey` | whether the database creates primary keys (default: `TRUE`) |
| `$fetchMode` | PDO fetch mode of read entries (default: `PDO::FETCH_OBJ`) |
| `$fetchEntityClass`, `$fetchEntityObject` | targets of fetch modes `FETCH_CLASS` / `FETCH_INTO`, see [Entities](entities.md) |
| `$jsonColumns`, `$dateTimeColumns` | columns to decode on read, see [JSON and DateTime columns](json-datetime.md) |

!!! info "Declared columns are a whitelist"
    Only declared columns are ever written, selected, ordered by or used in conditions – so user input
    can never sneak in a column name. Unknown columns in conditions, orders, field lists and index maps
    raise a `DomainException`, see [Conditions](conditions.md#conditions). Unknown keys in written data
    are ignored, so form data can be written as a whole, see [Writing](writing.md).

### Generated columns

Generated columns (eg. MySQL `GENERATED ALWAYS AS`) are readable like any other column,
but the table never writes to them:

```php
	protected array $generated		= [
		'totalWithTax',
	];
```

### Primary keys not created by the database

If the primary key is not auto-incremented by the database (eg. a UUID or a country code),
disable that assumption, so its value is written on insert:

```php
	protected bool $autoIncrementPrimaryKey	= FALSE;
```

## Table instances

A table instance needs a connection – and optionally a table name prefix:

```php
$orders		= new OrderTable( $dbc );
$shopOrders	= new OrderTable( $dbc, 'shop_' );   // table "shop_orders"
```

Prefixes allow several installations (or tenants, or test runs) in one database, using the same
table classes. `getName()` returns the table name with prefix, `getName( FALSE )` without.

## Fetch modes

Read entries are anonymous objects by default. Set another standard PDO fetch mode in the class:

```php
	protected int $fetchMode = \PDO::FETCH_ASSOC;
```

or on an instance by `setFetchMode()`. Supported are `FETCH_ASSOC`, `FETCH_NAMED`, `FETCH_NUM`,
`FETCH_BOTH` and `FETCH_OBJ`, plus `FETCH_CLASS` and `FETCH_INTO` for [entities](entities.md).
`getFetchMode()` returns the current mode.

## Introspection

| Method | Returns |
|--------|---------|
| `getName( $prefixed = TRUE )` | table name, with or without prefix |
| `getColumns()` | declared columns |
| `getPrimaryKey()` | primary key column |
| `getIndices()` | declared indices |
| `getJsonColumns()`, `getDateTimeColumns()` | columns decoded on read |
| `getLastQuery()` | last query run by `query()`, see [Logging](logging.md#last-query) |
