<?php
declare(strict_types=1);

namespace CeusMedia\DatabaseTest\PDO;

use CeusMedia\Database\PDO\BetterTable as PdoBetterTable;
use PDO as Pdo;

/**
 *	Fixture of a better table on the transactions test table.
 *	@extends	PdoBetterTable<object>
 */
class BetterTransactionTable extends PdoBetterTable
{
	protected string $name				= "transactions";

	protected array $columns			= [
		'id',
		'topic',
		'label',
		'timestamp'
	];

	protected array $indices			= [
		'topic'
	];

	protected string $primaryKey		= 'id';

	protected int $fetchMode			= Pdo::FETCH_OBJ;
}
