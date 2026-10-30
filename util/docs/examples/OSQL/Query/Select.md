```php
$query	= $client->select( 'orders AS o' )
	->fields( 'o.orderId', 'o.total', 'c.name' )
	->join( 'customers AS c', 'o.customerId', 'c.customerId' )
	->where( 'o.status', [1, 2] )
	->order( 'o.createdAt', 'DESC' );

$total	= $query->count();
$rows	= $query->page( 2, 20 )->all();
```

See guide: [Query builder](../../../guide/query-builder.md#reading)
