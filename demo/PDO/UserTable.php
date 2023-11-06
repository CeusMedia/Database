<?php
class UserTable extends \CeusMedia\Database\PDO\Table{

	protected $name			= 'users';
	protected $columns		= [
		'userId',
		'username',
	];
	protected $primaryKey	= 'userId';
	protected $indices		= array();
	protected $fetchMode	= \PDO::FETCH_OBJ;
}
