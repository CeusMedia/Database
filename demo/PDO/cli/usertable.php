<?php
require_once __DIR__.'/../../../vendor/autoload.php';
require_once __DIR__.'/../UserTable.php';

$dsn	= new \CeusMedia\Database\PDO\DataSourceName( 'mysql', 'projects_towers' );
$dbc	= new \CeusMedia\Database\PDO\Connection( $dsn, 'kriss', 'k' );

$model	= new UserTable( $dbc );
print_r( $model->getAll() );
