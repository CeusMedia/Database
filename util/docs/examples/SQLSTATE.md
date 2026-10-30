```php
use CeusMedia\Database\SQLSTATE;

echo SQLSTATE::getMeaning( '42S02' );   // "Syntax error or access rule violation - base table or view not found"
```

See guide: [Error handling](../guide/connection.md#error-handling)
