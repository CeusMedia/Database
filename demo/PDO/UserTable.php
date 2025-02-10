<?php

namespace CeusMedia\DatabaseDemo\PDO;

use CeusMedia\Database\PDO\Table as PdoTable;
use PDO;

class UserTable extends PdoTable
{
	protected string $name			= 'users';

	protected array $columns		= [
		'userId',
		'username',
	];

	protected string $primaryKey	= 'userId';

	protected array $indices		= [];

	protected int $fetchMode		= PDO::FETCH_OBJ;
}
