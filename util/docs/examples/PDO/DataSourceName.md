```php
use CeusMedia\Database\PDO\DataSourceName;

$dsn	= DataSourceName::getInstance( 'mysql', 'shop' )
	->setHost( 'db.example.com' )
	->setPort( 3307 );
echo $dsn->render();            // mysql:host=db.example.com; port=3307; dbname=shop

echo DataSourceName::renderStatic( 'mysql', 'shop', 'localhost', 3306 );
```

The PDO driver must be loaded, otherwise a `RuntimeException` is thrown on construction.

See guide: [Connection](../../guide/connection.md#data-source-names)
