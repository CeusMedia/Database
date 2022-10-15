<?php

namespace CeusMedia\DatabaseTest\PDO;

use CeusMedia\Common\ADT\Collection\Dictionary;
use CeusMedia\Database\PDO\Table as PdoTable;
use PDO as Pdo;

class TransactionTable extends PdoTable
{
	protected string $name					= "transactions";
	protected array $columns				= array(
		'id',
		'topic',
		'label',
		'timestamp'
	);
	protected string $primaryKey			= 'id';
	protected array $indices				= array(
		'topic'
	);

	protected string $prefix;
	protected int $fetchMode			= Pdo::FETCH_OBJ;
	public static string $cacheClass		= Dictionary::class;
}
