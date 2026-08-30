<?php

namespace CeusMedia\DatabaseTest\PDO;

use PDO as Pdo;

/**
 *	Presets "fetchEntityClass" as a class default, not via setFetchEntityClass(),
 *	to prove Table::setDatabase() picks up such a preset when constructing.
 */
class TransactionTableWithPresetFetchEntityClass extends TransactionTable
{
	protected int $fetchMode			= Pdo::FETCH_CLASS;

	protected ?string $fetchEntityClass	= AdvancedTransactionEntity::class;
}
