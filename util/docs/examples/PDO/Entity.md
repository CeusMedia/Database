```php
use CeusMedia\Database\PDO\Entity;

class Order extends Entity
{
	public int|string $orderId;
	public ?string $note	= NULL;
}

$order	= Order::fromArray( ['orderId' => 1, 'note' => 'Hello'] );
$order->get( 'note' );          // 'Hello'
$order['note'];                 // 'Hello'
echo json_encode( $order );     // {"orderId":1,"note":"Hello"}
```

See guide: [Entities](../../guide/entities.md)
