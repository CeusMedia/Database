---
title: Home
hide:
  - navigation
  - toc
---

<div class="hero" markdown>

# CeusMedia Database

PHP database access in two layers on one connection: a **database abstraction layer** on top of PDO –
table classes, entities, caching and a compact condition syntax – and a **query builder** for joins,
`OR` groups and aggregates.

[Get started](guide/quickstart.md){ .md-button .md-button--primary }
[Query builder](guide/query-builder.md){ .md-button }
[API Reference](api/index.md){ .md-button }
[GitHub](https://github.com/CeusMedia/Database){ .md-button }

</div>

=== "DBAL: table classes"

    ```php
    use CeusMedia\Database\PDO\Connection;
    use CeusMedia\Database\PDO\DataSourceName;
    use CeusMedia\Database\PDO\Table;

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
    $orders->edit( $orderId, ['status' => 1] );
    ```

=== "Query builder"

    ```php
    use CeusMedia\Database\OSQL\Client;
    use CeusMedia\Database\OSQL\Condition;
    use CeusMedia\Database\OSQL\Condition\Group;

    $client	= new Client( $dbc );

    $customers	= $client->select( 'orders AS o' )
    	->fields( 'c.name', 'COUNT(*) AS number', 'SUM(o.total) AS sum' )
    	->join( 'customers AS c', 'o.customerId', 'c.customerId' )
    	->where( 'o.status', [1, 2] )
    	->and( Group::or( Condition::gt( 'o.total', 100 ), Condition::isNull( 'c.country' ) ) )
    	->groupBy( 'c.name' )
    	->having( 'sum', 1000, '>' )
    	->order( 'sum', 'DESC' )
    	->all();
    ```

=== "Both in one transaction"

    ```php
    $dbc->transaction( function() use ( $orders, $client, $orderId ){
    	$orders->edit( $orderId, ['status' => 2] );
    	$client->update( 'stock' )->set( 'reserved', 0 )->where( 'orderId', $orderId )->execute();
    } );                                            // committed – or rolled back on any exception
    ```

## Two layers, one connection

| | **DBAL** – `PDO` module | **Query builder** – `OSQL` module |
|---|---|---|
| **Use for** | everyday work on single tables: CRUD, lists, filters, entities | joins, `OR` groups, aggregates, tables without a class |
| **Style** | declare a table class once, call methods with arrays | build a query as object, then fetch or execute |
| **Conditions** | compact maps: `['total' => '>= 20', 'status' => [0, 1]]` | `where( 'total', 20, '>=' )`, `Condition::in()`, nested groups |
| **Results** | entities, JSON and DateTime columns, cache by primary key | `all()`, `first()`, `value()`, `column()`, `count()`, `page()` |
| **Start** | [Tables](guide/tables.md) | [Query builder](guide/query-builder.md) |

Both run on the same [`PDO\Connection`](guide/connection.md) – with nestable transactions, logging and
connection pools – and can be mixed freely, also within one transaction.

## Database abstraction layer

<div class="grid cards" markdown>

-   :material-connection:{ .lg .middle } __Better connections__

    ---

    A `PDO` with `transaction()` callbacks, safely nested transactions, error and statement logging, connection pools and DSNs for many drivers.

    [:octicons-arrow-right-24: Connection](guide/connection.md)

-   :material-table-edit:{ .lg .middle } __Tables, not SQL strings__

    ---

    Declare a table once, then read, count, add, edit and remove entries – without writing a single query.

    [:octicons-arrow-right-24: Tables](guide/tables.md)

-   :material-filter-variant:{ .lg .middle } __Compact conditions__

    ---

    `'>= 10'`, `'%foo%'`, `'>< 1 & 10'`, `'& 4'`, lists of values – operators live in the value, all values are quoted.

    [:octicons-arrow-right-24: Conditions](guide/conditions.md)

-   :material-code-braces-box:{ .lg .middle } __Entities and types__

    ---

    Fetch into entity classes, decode JSON and DateTime columns automatically and save changed entities back.

    [:octicons-arrow-right-24: Entities](guide/entities.md)

-   :material-lightning-bolt-outline:{ .lg .middle } __Caching__

    ---

    Entries are cached by primary key through any PSR-16 cache – per table, per instance or globally.

    [:octicons-arrow-right-24: Caching](guide/caching.md)

-   :material-console-line:{ .lg .middle } __Logging and debugging__

    ---

    Log errors and statements to files, for all statements or just the next one, and see the last query.

    [:octicons-arrow-right-24: Logging](guide/logging.md)

</div>

## Query builder

<div class="grid cards" markdown>

-   :material-source-merge:{ .lg .middle } __Queries as objects__

    ---

    Select with joins, grouping, having, order and pages – then fetch all rows, the first, a value, a column or the count.

    [:octicons-arrow-right-24: Reading](guide/query-builder.md#reading)

-   :material-sitemap-outline:{ .lg .middle } __Conditions and groups__

    ---

    `IN`, `BETWEEN`, `LIKE`, `IS NULL` and nested `AND`/`OR` groups – always in brackets, no precedence surprises.

    [:octicons-arrow-right-24: Conditions](guide/query-builder.md#conditions)

-   :material-database-edit-outline:{ .lg .middle } __Writing__

    ---

    Insert one or many rows, upsert on duplicate keys, update and delete – in transactions with your table classes.

    [:octicons-arrow-right-24: Writing](guide/query-builder.md#writing)

</div>

## Safe by default

- **Values** are always quoted or bound as parameters – in table classes and in the query builder.
- **Identifiers** – columns, aliases, functions, orders, groupings – are validated against the declared
  columns or a strict syntax and a whitelist of aggregate functions; anything else raises an exception.
- **No silent mistakes:** unknown condition columns raise an exception instead of being ignored, and the
  query builder refuses to update or delete without conditions, unless asked for `allRows()`.

## More

<div class="grid cards" markdown>

-   :material-book-open-variant:{ .lg .middle } __Recipes__

    ---

    Search forms, pagination, upserts, reports, bitmask flags, read replicas and more – ready to copy.

    [:octicons-arrow-right-24: Use cases and recipes](guide/recipes.md)

-   :material-database-export-outline:{ .lg .middle } __Utilities__

    ---

    Export and import MySQL databases, with portable table prefixes, and look up SQLSTATE error codes.

    [:octicons-arrow-right-24: Utilities](guide/utilities.md)

-   :material-api:{ .lg .middle } __API reference__

    ---

    Every class and method of both layers, generated from the code.

    [:octicons-arrow-right-24: API reference](api/index.md)

</div>

!!! info "New in version 0.7.3"
    The query builder is rebuilt, the connection got `transaction()`, and SQL injections by fields,
    groupings and having are fixed. Some changes affect existing code – see
    [Installation](guide/installation.md) and the [changelog](changelog.md).

## Requirements

- PHP 8.3 or newer
- Extension `pdo` and the PDO driver of your database, eg. `pdo_mysql`
- The query builder supports MySQL and MariaDB.

```sh
composer require ceus-media/database
```

License: [GPL-3.0-or-later](https://www.gnu.org/licenses/gpl-3.0.txt)
