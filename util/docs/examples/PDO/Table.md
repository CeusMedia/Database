```php
use CeusMedia\Database\PDO\Table;

class OrderTable extends Table
{
	protected string $name			= 'orders';
	protected array $columns		= ['orderId', 'customerId', 'status', 'total', 'note'];
	protected string $primaryKey	= 'orderId';
	protected array $indices		= ['customerId', 'status'];
	protected int $fetchMode		= \PDO::FETCH_OBJ;
}

$orders		= new OrderTable( $dbc );
$orderId	= $orders->add( ['customerId' => 12, 'total' => 49.9] );
$order		= $orders->get( $orderId );
$list		= $orders->getAll( ['status' => [0, 1], 'total' => '>= 20'], ['total' => 'DESC'], [0, 10] );
$orders->edit( $orderId, ['status' => 1] );
$orders->remove( $orderId );
```

See guide: [Tables](../../guide/tables.md), [Reading](../../guide/reading.md), [Conditions](../../guide/conditions.md), [Writing](../../guide/writing.md)
