<?php
class UserTable extends \CeusMedia\Database\PDO\Table{

	protected $name			= 'users';
	protected $columns		= array(
		'userId',
		'username',
	);
	protected $primaryKey	= 'userId';
	protected $indices		= array();
	protected $fetchMode	= \PDO::FETCH_OBJ;
}
