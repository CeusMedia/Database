<?php
class CeusMedia_Database_Test_PDO_TransactionTable extends \CeusMedia\Database\PDO\Table{

	protected $name					= "transactions";
	protected $columns				= array(
		'id',
		'topic',
		'label',
		'timestamp'
	);
	protected $primaryKey			= 'id';
	protected $indices				= array(
		'topic'
	);

	protected $prefix;
	protected $fetchMode			= \PDO::FETCH_OBJ;
	public static $cacheClass		= 'ADT_List_Dictionary';
}
