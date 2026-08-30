<?php

namespace CeusMedia\DatabaseTest\PDO;

/**
 *	Fixture for Table::checkTableSetup()'s "no table name set" guard.
 */
class TableWithNoName extends TransactionTable
{
	protected string $name	= '';
}
