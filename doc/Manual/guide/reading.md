# Reading

All examples use the `OrderTable` from [Tables](tables.md), fetching anonymous objects.

## One entry

```php
$order	= $orders->get( 1 );
```

```php
object(stdClass) {
	'orderId'    => 1,
	'customerId' => 12,
	'status'     => 0,
	'total'      => '49.90',
	'note'       => 'Ring twice',
	'createdAt'  => '2026-10-28 12:00:00',
}
```

`get()` returns `NULL`, if there is no entry. Entries read by primary key are [cached](caching.md).

By index instead of primary key – the first entry found, optionally ordered:

```php
$latest	= $orders->getByIndex( 'customerId', 12, ['createdAt' => 'DESC'] );
$latest	= $orders->getByIndices( ['customerId' => 12, 'status' => 0], ['createdAt' => 'DESC'] );
```

## Many entries

```php
$list	= $orders->getAllByIndex( 'customerId', 12 );
$list	= $orders->getAllByIndices( ['customerId' => 12, 'status' => 0] );
$list	= $orders->getAll();
```

Using an index not declared in `$indices` raises a `DomainException`.

Reading all entries may scale badly – reduce the result by conditions, orders and limits:

```php
$conditions	= ['note' => '%gift%'];
$orderBy	= ['createdAt' => 'DESC', 'orderId' => 'ASC'];
$limits		= [0, 10];          // offset, limit

$list	= $orders->getAll( $conditions, $orderBy, $limits );
```

Conditions may use any declared column and have a [compact syntax for operators](conditions.md).
`getAllByIndex()` and `getAllByIndices()` accept orders and limits as well.

## Single values and columns

Instead of fetching whole entries, dedicated methods read single values or columns,
with a fixed return type:

| Method | Returns |
|--------|---------|
| `getField( $id, 'note' )` | value of one column of an entry by primary key (cached like `get()`) |
| `getFirstFieldByIndex( 'note', 'customerId', 12 )` | value of one column of the first entry by index |
| `getFirstFieldByIndices( 'note', $indices )` | value of one column of the first entry by indices |
| `getFirstFieldsByIndex( ['orderId', 'note'], 'customerId', 12 )` | first entry by index, reduced to some columns |
| `getFirstFieldsByIndices( ['orderId', 'note'], $indices )` | first entry by indices, reduced to some columns |
| `getColumnOfAllByConditions( 'note', $conditions )` | list of values of one column of all found entries |
| `getColumnsOfAllByConditions( ['orderId', 'note'], $conditions )` | list of found entries, reduced to some columns |

```php
$note		= $orders->getField( 1, 'note' );
$ids		= $orders->getColumnOfAllByConditions( 'orderId', ['customerId' => 12], ['orderId' => 'ASC'] );
$options	= array_column( $orders->getColumnsOfAllByConditions( ['orderId', 'note'] ), 'note', 'orderId' );
```

- Single values are returned as they are, or decoded for [JSON and DateTime columns](json-datetime.md).
- Reduced entries stay arrays or objects (depending on fetch mode), even for one column.
- If nothing is found, `NULL` is returned – or a `RangeException` is thrown, if `$strict` is enabled.
- Only declared columns (or `*` for all) are accepted.

## Distinct values

```php
$statuses	= $orders->getDistinct( 'status' );
$statuses	= $orders->getDistinct( 'status', ['customerId' => 12], ['status' => 'ASC'] );
```

## Counting

```php
$number	= $orders->count();
$number	= $orders->count( ['customerId' => 12, 'note' => '%gift%'] );
$number	= $orders->countByIndex( 'customerId', 12 );
$number	= $orders->countByIndices( ['customerId' => 12, 'status' => 0] );
```

Counting really large tables can be slow. `countFast()` uses `EXPLAIN` instead of `COUNT`,
trading exactness for speed – the result is an **estimate**:

```php
$approximately	= $orders->countFast( ['status' => 0] );
```

## Checking existence

Without fetching the entry itself:

```php
$exists	= $orders->has( $orderId );
$exists	= $orders->hasByIndex( 'customerId', 12 );
$exists	= $orders->hasByIndices( ['customerId' => 12, 'status' => 0] );
```

`has()` asks the [cache](caching.md) first – an entry already cached needs no query at all.
