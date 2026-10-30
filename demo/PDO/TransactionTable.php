<?php

namespace CeusMedia\DatabaseDemo\PDO;

use CeusMedia\Database\PDO\Table;
use PDO;

/**
 *	Demo table class for table "transactions" of demo/demo_transactions.sql.
 */
class TransactionTable extends Table
{
	protected string $name			= 'transactions';

	protected array $columns		= [
		'id',
		'topic',
		'label',
		'timestamp',
	];

	protected string $primaryKey	= 'id';

	protected array $indices		= [
		'topic',
	];

	protected int $fetchMode		= PDO::FETCH_OBJ;
}
