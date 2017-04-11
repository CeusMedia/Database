<?php
$pathLib	= dirname( dirname( dirname( __DIR__ ) ) ).'/';
require_once $pathLib.'vendor/autoload.php';
require_once $pathLib.'test/PDO/TransactionTable.php';

$config		= parse_ini_file( $pathLib.'test/test.ini', TRUE );
extract( $config['unitTest-Database'] );

$dsn		= new \CeusMedia\Database\PDO\DataSourceName( 'mysql', $database );
$dbc		= new \CeusMedia\Database\PDO\Connection( $dsn, $username, $password );

$command	= "mysql -u%s -p%s %s < %stest/PDO/createTable.sql";
$command	= sprintf( $command, $username, $password, $database, $pathLib );
passthru( $command );

$model		= new CeusMedia_Database_Test_PDO_TransactionTable( $dbc );
print_r( $model->getAll() );
