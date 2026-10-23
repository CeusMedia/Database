<?php
declare(strict_types=1);

namespace CeusMedia\DatabaseTest\PDO;

use PDO as Pdo;

/**
 *	Presets "fetchEntityObject" as a class default, not via setFetchEntityObject(),
 *	to prove BetterTable::setDatabase() picks up such a preset when constructing.
 */
class BetterTransactionTableWithPresetFetchEntityObject extends BetterTransactionTable
{
	protected int $fetchMode			= Pdo::FETCH_INTO;

	public function __construct( \CeusMedia\Database\PDO\Connection $dbc, ?string $prefix = NULL, int|string|null $id = NULL )
	{
		$this->fetchEntityObject	= new AdvancedTransactionEntity();
		parent::__construct( $dbc, $prefix, $id );
	}
}
