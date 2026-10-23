<?php /** @noinspection PhpMultipleClassDeclarationsInspection */

namespace CeusMedia\DatabaseTest\PDO;

use CeusMedia\Database\PDO\Table as PdoTable;
use PDO as Pdo;

class TransactionTable extends PdoTable
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

	protected string $prefix;

	protected int $fetchMode			= Pdo::FETCH_OBJ;
}
