<?php
declare(strict_types=1);

namespace CeusMedia\DatabaseTest\PDO;

/**
 *	Fixture for BetterTable::checkTableSetup()'s "no table columns set" guard.
 */
class BetterTableWithNoColumns extends BetterTransactionTable
{
	protected array $columns	= [];
}
