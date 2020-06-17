<?php
$pathLib	= dirname( dirname( __DIR__ ) ).'/';
require_once $pathLib.'vendor/autoload.php';
require_once $pathLib.'test/PDO/TransactionTable.php';
new UI_DevOutput();
$cliColor	= new CLI_Color();

use CeusMedia\Database\PDO\Connection;
use CeusMedia\Database\PDO\DataSourceName;

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
	$model	= new CeusMedia_Database_Test_PDO_TransactionTable( $dbc );
	print_r( $model->getAll() );
}
catch( Exception $e ){
	CLI::out( $cliColor->asError( 'Error: '.$e->getMessage() ) );
	CLI::out( 'Location: '.$e->getFile().' at line '.$e->getLine() );
	CLI::out( 'Trace: ');
	CLI::out( $e->getTraceAsString() );
	CLI::out( '' );
}
