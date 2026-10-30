```php
use CeusMedia\Database\OSQL\Condition;
use CeusMedia\Database\OSQL\Condition\Group;

$query->where( 'status', [1, 2] )                           // IN
	->and( Condition::between( 'total', 10, 50 ) )
	->and( Group::or( Condition::isNull( 'deletedAt' ), Condition::gt( 'deletedAt', '2026-01-01' ) ) );
```

See guide: [Query builder](../../guide/query-builder.md#conditions)
