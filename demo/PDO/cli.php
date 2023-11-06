<?php
/** @noinspection PhpMultipleClassDeclarationsInspection */

$pathLib	= dirname( __DIR__, 2 ).'/';
require_once $pathLib.'vendor/autoload.php';
require_once $pathLib.'test/PDO/TransactionTable.php';

use CeusMedia\Common\CLI;
use CeusMedia\Common\CLI\Color as CliColor;
use CeusMedia\Common\UI\DevOutput;
use CeusMedia\Database\PDO\Connection;
use CeusMedia\Database\PDO\DataSourceName;
use CeusMedia\DatabaseTest\PDO\TransactionEntity as TestTransactionEntity;
use CeusMedia\DatabaseTest\PDO\TransactionTable as TestTransactionTable;

new DevOutput();

$cliColor	= new CliColor();

( file_exists( $pathLib.'demo/demo.ini' ) ) or die( 'Missing demo ini file (demo/demo.ini)'.PHP_EOL );

$config		= parse_ini_file( $pathLib.'demo/demo.ini', TRUE );
$dbConfig	= (object) $config['demo'];

$command	= "mysql -u%s -p%s %s < %sdemo/demo_transactions.sql";
$command	= sprintf( $command, $dbConfig->username, $dbConfig->password, $dbConfig->database, $pathLib );
passthru( $command );

$dsn		= DataSourceName::renderStatic(
	$dbConfig->driver,
	$dbConfig->database,
	$dbConfig->host,
	$dbConfig->port,
	$dbConfig->username,
	$dbConfig->password
);

try{
	$dbc	= new Connection( $dsn, $dbConfig->username, $dbConfig->password );
	$model	= new TestTransactionTable( $dbc );
	remark( 'Fetch Transactions as list of anonymous objects:' );
	print_m( $model->getAll() );

	$model->setFetchMode( PDO::FETCH_CLASS );
	$model->setFetchEntityClass( TestTransactionEntity::class );
	remark( 'Fetch Transactions as list of entity objects:' );
	print_m( $model->getAll() );

}
catch( Exception $e ){
	CLI::out( $cliColor->asError( 'Error: '.$e->getMessage() ) );
	CLI::out( 'Location: '.$e->getFile().' at line '.$e->getLine() );
	CLI::out( 'Trace: ');
	CLI::out( $e->getTraceAsString() );
	CLI::out( '' );
}
