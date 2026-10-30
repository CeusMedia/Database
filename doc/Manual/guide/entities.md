# Entities

Instead of arrays or anonymous objects, tables can return instances of own entity classes.

## Fetching into entity classes

Set fetch mode `FETCH_CLASS` and the entity class in the table:

```php
use CeusMedia\Database\PDO\Entity;
use CeusMedia\Database\PDO\Table;

class Order extends Entity
{
	public int|string $orderId;
	public int|string $customerId;
	public int|string $status;
	public float|string $total;
	public ?string $note		= NULL;
}

class OrderTable extends Table
{
	…
	protected int $fetchMode			= \PDO::FETCH_CLASS;
	protected ?string $fetchEntityClass	= Order::class;
}

$order	= $orders->get( $orderId );                  // an Order
$list	= $orders->getAllByIndex( 'customerId', 12 );  // a list of Orders
```

The class can also be set on an instance, by `setFetchEntityClass()`.

!!! tip "Property types"
    Depending on driver, settings and column type, PDO delivers numbers as strings
    (MySQL `DECIMAL` columns always). Declare property types accepting both, eg. `int|string`.

## The Entity base class

Extending [`PDO\Entity`](../api/PDO/Entity.md) is optional – any class with public properties works
as target of `FETCH_CLASS`. `Entity` adds some convenience on top:

```php
$order	= Order::fromArray( ['orderId' => 1, 'note' => 'Hello'] );   // unknown keys are ignored

$order->get( 'note' );              // 'Hello'
$order->set( 'note', 'Changed' );   // chainable, unknown keys are ignored
$order->has( 'note' );              // TRUE, if set and not NULL
$order['note'];                     // ArrayAccess
count( $order );                    // number of properties
foreach( $order as $key => $value ) // Iterator
	…
$order->toArray();
echo json_encode( $order );         // JsonSerializable: {"orderId":1,"note":"Changed",…}
```

## Saving entities

A fetched and changed entity can be written back directly:

```php
$order			= $orders->get( $orderId );
$order->status	= 2;
$orders->save( $order );
```

`save()` reads the primary key from the entity and calls `edit()`. The entity must be of the
table's entity class, if one is set.

## Fetching into an existing object

With fetch mode `FETCH_INTO`, entries are fetched into clones of a given object instead.
The object serves as template: preset values stay, unless overwritten by a column, and the object itself stays untouched.

```php
class OrderTable extends Table
{
	…
	protected int $fetchMode = \PDO::FETCH_INTO;
}

$orders->setFetchEntityObject( new Order() );
$order	= $orders->get( $orderId );                // a filled clone of the template
$list	= $orders->getAllByIndex( 'customerId', 12 ); // one clone per entry
```

## Mandatory fields

When the [table reader](../api/PDO/Table/Reader.md) selects a restricted list of columns into entities
(`FETCH_CLASS`), required properties might stay uninitialized. An entity class can name fields which
the reader always selects:

```php
class Order extends Entity
{
	public static array $mandatoryFields	= ['orderId', 'customerId'];
	…
}
```

Methods of `Table` with a list of fields return reduced rows instead of entities anyway.

## The "onFetch" hook

If an entity class defines a public `onFetch()` method, it is called right after fetching
(and after decoding JSON and DateTime columns), once per entity:

```php
use CeusMedia\Database\PDO\Table\Reader;

class Order extends Entity
{
	…
	public ?string $label	= NULL;

	public function onFetch( Reader $reader, self $entity ): void
	{
		//  eg. derive a computed property, or lazy-load a related entity
		$this->label	= sprintf( '#%d (%s)', $this->orderId, $this->status );
	}
}
```

This is entirely optional – entities without `onFetch()` are not affected.
