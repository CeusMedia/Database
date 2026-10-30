```php
use CeusMedia\Database\PDO\Connection;
use CeusMedia\Database\PDO\DataSourceName;

$dsn	= DataSourceName::getInstance( 'mysql', 'shop' )->setHost( 'localhost' );
$dbc	= new Connection( $dsn, 'myUser', 'myPassword' );

$dbc->setErrorLogFile( 'logs/db/error.log' );

$dbc->beginTransaction();       // nestable
// …
$dbc->commit();
```

See guide: [Connection](../../guide/connection.md), [Transactions](../../guide/transactions.md), [Logging](../../guide/logging.md)
