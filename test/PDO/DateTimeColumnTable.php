<?php

namespace CeusMedia\DatabaseTest\PDO;

use CeusMedia\Database\PDO\Table as PdoTable;
use PDO;

class DateTimeColumnTable extends PdoTable
{
	protected string $name			= "datetime_columns_test";

	protected array $columns		= [
		'id',
		'topic',
		'preciseAt',
		'plainAt',
	];

	protected string $primaryKey	= 'id';

	protected string $prefix;

	protected int $fetchMode		= PDO::FETCH_OBJ;
}
