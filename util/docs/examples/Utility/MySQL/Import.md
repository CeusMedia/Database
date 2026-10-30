```php
use CeusMedia\Database\Utility\MySQL\Import;

$result	= ( new Import( $dsn ) )->importFileWithPrefix( 'shop.sql', 'test_' );
echo $result->code;     // exit code of mysql
```

See guide: [Utilities](../../../guide/utilities.md#portable-dumps-with-table-prefix-placeholders)
