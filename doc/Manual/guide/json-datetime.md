# JSON and DateTime columns

## JSON columns

Arrays and objects are written as JSON, into **any** column, without configuration:

```php
$orders->add( [
	'customerId'	=> 12,
	'meta'			=> ['tags' => ['gift', 'express'], 'archived' => FALSE],
] );
```

Decoding on read is enabled per column – otherwise you get the raw JSON string back:

```php
class OrderTable extends Table
{
	…
	protected array $jsonColumns	= ['meta'];
}

$order	= $orders->get( $orderId );
$order->meta;      // (object) ['tags' => ['gift', 'express'], 'archived' => FALSE]
```

or on an instance by `setJsonColumns( ['meta'] )`. `getJsonColumns()` returns the current list,
an unknown column raises a `DomainException`.

Decoding follows the shape of the row: array rows (eg. `FETCH_ASSOC`) decode a JSON column into an
array, object rows and entities (`FETCH_OBJ`, `FETCH_CLASS`, `FETCH_INTO`) into a `stdClass` object.
This also applies to `getDistinct()` and the single value methods like `getField()`.

## DateTime columns

`DateTime` and `DateTimeImmutable` values are written into **any** column, without configuration –
always including fractional seconds:

```php
$orders->add( [
	'customerId'	=> 12,
	'createdAt'		=> new \DateTime( '2026-08-29 14:30:00.123456' ),
] );
```

Whether the microseconds are stored depends only on the column: plain `DATETIME` / `TIMESTAMP`
truncates them silently, `DATETIME(6)` / `TIMESTAMP(6)` keeps them.

Decoding on read is enabled per column:

```php
class OrderTable extends Table
{
	…
	protected array $dateTimeColumns	= ['createdAt'];
}

$order	= $orders->get( $orderId );
$order->createdAt;     // a DateTime instance, with microseconds if the column stores them
```

or on an instance by `setDateTimeColumns( ['createdAt'] )`. A value which can not be parsed is
returned unchanged as string.

Mutable `DateTime` objects are returned, so a date can be changed in place and saved back:

```php
$order->createdAt->modify( '+1 day' );
$orders->save( $order );
```

## Entities with JSON and DateTime columns

!!! warning "Property types must accept the raw string"
    With `FETCH_CLASS`, PDO first assigns the *raw* string to the property, which is replaced by the
    decoded value afterward. So the property type must accept both:

    ```php
    class Order extends Entity
    {
    	public string|object|null $meta				= NULL;
    	public string|\DateTime|null $createdAt	= NULL;
    }
    ```

    A too narrow type (eg. only `object`) raises a `RuntimeException` naming class and property,
    instead of a confusing `TypeError`.
