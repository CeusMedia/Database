```php
use CeusMedia\Database\OSQL\Client;

$client	= new Client( $dbc );       // PDO\Connection or any PDO

$orders	= $client->select( 'orders' )->where( 'customerId', 12 )->order( 'createdAt', 'DESC' )->all();
$id		= $client->insert( 'orders' )->setAll( ['customerId' => 12, 'total' => 49.9] )->execute();
$client->update( 'orders' )->set( 'status', 2 )->where( 'orderId', $id )->execute();
$client->delete( 'orders' )->where( 'orderId', $id )->execute();
```

See guide: [Query builder](../../guide/query-builder.md)
