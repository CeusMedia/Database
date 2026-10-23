<?php
declare(strict_types=1);

namespace CeusMedia\DatabaseTest\PDO;

use CeusMedia\Database\PDO\Table as PdoTable;
use PDO as Pdo;

/**
 *	Fixture of a table with a generated column, which is listed in columns, too.
 */
class GeneratedColumnTable extends PdoTable
{
	protected string $name			= "generated_columns_test";

	protected array $columns		= [
		'id',
		'content',
		'contentLength',
	];

	protected array $generated		= [
		'contentLength',
	];

	protected string $primaryKey	= 'id';

	protected int $fetchMode		= Pdo::FETCH_OBJ;
}
