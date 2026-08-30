<?php

namespace CeusMedia\DatabaseTest\PDO;

use CeusMedia\Database\PDO\Connection;
use PDO as Pdo;

/**
 *	Presets "fetchEntityObject" before construction (a property default cannot
 *	hold an object instance, so this is done in an overridden constructor), to
 *	prove Table::setDatabase() picks up such a preset when constructing.
 */
class TransactionTableWithPresetFetchEntityObject extends TransactionTable
{
	protected int $fetchMode	= Pdo::FETCH_INTO;

	public function __construct( Connection|Pdo $dbc, ?string $prefix = NULL, int|string $id = NULL )
	{
		$this->fetchEntityObject	= new AdvancedTransactionEntity();
		parent::__construct( $dbc, $prefix, $id );
	}
}
