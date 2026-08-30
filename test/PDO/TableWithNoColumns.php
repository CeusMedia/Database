<?php

namespace CeusMedia\DatabaseTest\PDO;

/**
 *	Fixture for Table::checkTableSetup()'s "no table columns set" guard.
 */
class TableWithNoColumns extends TransactionTable
{
	protected array $columns	= [];
}
