```php
use CeusMedia\Database\PDO\DataSourceName;
use CeusMedia\Database\Utility\MySQL\Export;

$dsn	= DataSourceName::getInstance( 'mysql', 'shop' )
	->setHost( 'localhost' )->setUsername( 'myUser' )->setPassword( 'myPassword' );

$result	= ( new Export( $dsn ) )->exportToFile( 'backup.sql' );
echo $result->code;     // exit code of mysqldump
```

See guide: [Utilities](../../../guide/utilities.md#mysql-export-and-import)
