<?php
declare(strict_types=1);

namespace CeusMedia\DatabaseTest\PDO;

use PDO as Pdo;

/**
 *	Presets "fetchEntityClass" as a class default, not via setFetchEntityClass(),
 *	to prove BetterTable::setDatabase() picks up such a preset when constructing.
 */
class BetterTransactionTableWithPresetFetchEntityClass extends BetterTransactionTable
{
	protected int $fetchMode			= Pdo::FETCH_CLASS;

	protected ?string $fetchEntityClass	= AdvancedTransactionEntity::class;
}
