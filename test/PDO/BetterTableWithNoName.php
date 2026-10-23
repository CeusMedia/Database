<?php
declare(strict_types=1);

namespace CeusMedia\DatabaseTest\PDO;

/**
 *	Fixture for BetterTable::checkTableSetup()'s "no table name set" guard.
 */
class BetterTableWithNoName extends BetterTransactionTable
{
	protected string $name	= '';
}
