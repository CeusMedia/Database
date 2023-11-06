<?php /** @noinspection PhpMultipleClassDeclarationsInspection */

namespace CeusMedia\DatabaseTest\PDO;

use CeusMedia\Common\ADT\Collection\Dictionary;
use CeusMedia\Database\PDO\Table as PdoTable;
use PDO as Pdo;

class TransactionTable extends PdoTable
{
	public static string $cacheClass	= Dictionary::class;

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
