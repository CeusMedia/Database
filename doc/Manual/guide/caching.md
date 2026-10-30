# Caching

`get()`, `add()` and `edit()` cache entries by primary key transparently, through a
[PSR-16](https://www.php-fig.org/psr/psr-16/) cache (`Psr\SimpleCache\CacheInterface`).
By default nothing is cached (a no-op adapter).

## Enabling the cache

For all tables, by static configuration before creating table instances – an adapter class of
[ceus-media/cache](https://github.com/CeusMedia/Cache) and the resource given to its constructor:

```php
use CeusMedia\Cache\Adapter\Memory as MemoryCache;
use CeusMedia\Database\PDO\Table;

Table::$cacheClass		= MemoryCache::class;
Table::$cacheResource	= NULL;
```

For one table class only, by redeclaring the static properties:

```php
class CountryTable extends Table
{
	public static string $cacheClass	= MemoryCache::class;
	…
}
```

Sharing one cache instance (of any PSR-16 implementation) across all tables:

```php
Table::$cacheInstance	= $myCache;
```

For a single table instance only, overriding the static configuration:

```php
$countries->setCache( $myCache );
```

## What is cached

| Method | Cache |
|--------|-------|
| `get()`, `getField()` | read from cache, or fetched and stored |
| `has()` | asks the cache first |
| `add()` | stores the new entry |
| `edit()`, `save()` | removes the entry, it is fetched again on next read |
| `editByIndices()` | removes the changed entries |
| `remove()`, `removeByIndex()`, `removeByIndices()` | removes the entries |
| `truncate()` | removes all entries of the table |

To know which entries to remove, `editByIndices()` and `truncate()` read the IDs of affected entries
first – but only if caching is enabled.

Only found entries are cached – a missing entry is looked up again next time.
Cache keys contain the table prefix and name, so a shared cache does not mix up tables or installations.

!!! warning "Changes made elsewhere"
    Changes by raw SQL or other applications are not noticed by the cache.
    Use a cache with a lifetime for such tables, or do not cache them.

!!! info "A broken cache never breaks the database access"
    Every cache call is guarded: a misbehaving cache (eg. one rejecting a key as invalid per PSR-16)
    is treated as cache miss, not surfaced to the caller.
