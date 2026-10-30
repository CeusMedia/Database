# Quickstart

Four steps from an existing database table to reading and writing it.

## 1. Connect

```php
use CeusMedia\Database\PDO\Connection;
use CeusMedia\Database\PDO\DataSourceName;

$dsn	= DataSourceName::getInstance( 'mysql', 'shop' )
	->setHost( 'localhost' )
	->setPort( 3306 );
$dbc	= new Connection( $dsn, 'myUser', 'myPassword' );
```

The connection *is* a `PDO` object – with errors raised as exceptions by default, nested
transactions and logging on top. See [Connection](connection.md).

## 2. Declare a table

Describe an existing table once: name, columns, primary key and indices.

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

$orders	= new OrderTable( $dbc );
```

Only declared columns are ever read, written or accepted in conditions and orders.
See [Tables](tables.md).

## 3. Write

```php
$orderId	= $orders->add( ['customerId' => 12, 'total' => 49.9, 'note' => 'Ring twice'] );

$orders->edit( $orderId, ['status' => 1] );
$orders->editByIndices( ['customerId' => 12, 'status' => 0], ['status' => 9] );
$orders->remove( $orderId );
```

See [Writing](writing.md).

## 4. Read

```php
$order		= $orders->get( $orderId );                                  // one entry by primary key
$list		= $orders->getAllByIndex( 'customerId', 12 );                // all entries of a customer
$expensive	= $orders->getAll( ['total' => '> 100'], ['total' => 'DESC'], [0, 10] );
$number		= $orders->count( ['status' => [0, 1]] );                    // status 0 OR 1
$note		= $orders->getField( $orderId, 'note' );                     // a single value
```

See [Reading](reading.md) and [Conditions](conditions.md).

## Next steps

<div class="grid cards" markdown>

-   __Typed entities__ – fetch into own classes, with JSON and DateTime columns decoded.

    [:octicons-arrow-right-24: Entities](entities.md)

-   __Use cases and recipes__ – search forms, pagination, reports, transactions across services.

    [:octicons-arrow-right-24: Recipes](recipes.md)

</div>
