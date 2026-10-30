```php
use CeusMedia\Database\PDO\Pool;

$pool	= new Pool();
$pool->add( 'primary', $primaryDbc, TRUE );
$pool->add( 'replica', $replicaDbc );

$dbc	= $pool->get();             // default connection
$dbc	= $pool->get( 'replica' );
```

See guide: [Connection pool](../../guide/connection.md#connection-pool)
