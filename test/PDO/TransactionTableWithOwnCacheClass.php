<?php
declare(strict_types=1);

namespace CeusMedia\DatabaseTest\PDO;

use CeusMedia\Cache\Adapter\Memory as MemoryCache;

/**
 *	Sets an own default cache class, to prove Table::setupCache() respects it (late static binding).
 */
class TransactionTableWithOwnCacheClass extends TransactionTable
{
	public static string $cacheClass	= MemoryCache::class;
}
