<?php
/** @noinspection PhpMultipleClassDeclarationsInspection */

$pathLib	= dirname( __DIR__, 2 ).'/';
require_once $pathLib.'vendor/autoload.php';

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
$dbConfig	= (object) ( $config['demo'] ?? [] );

/*
$command	= "mysql -u%s -p%s %s < %sdemo/demo_transactions.sql";
$command	= sprintf( $command, $dbConfig->username, $dbConfig->password, $dbConfig->database, $pathLib );
passthru( $command );
*/


try{
	$dsn		= DataSourceName::renderStatic(
		$dbConfig->driver,
		$dbConfig->database,
		$dbConfig->host,
		$dbConfig->port,
		$dbConfig->username,
		$dbConfig->password
	);

	$dbc	= new Connection( $dsn, $dbConfig->username, $dbConfig->password );
	$dbc->setErrorLogFile( 'db.error.log' );
	$dbc->setStatementLogFile( 'db.stmt.log' );
	$table	= new TestTransactionTable( $dbc );
	remark( 'Fetch Transactions as list of anonymous objects:' );
	print_m( $table->getAll() );

	$table->setFetchMode( PDO::FETCH_CLASS );
	$table->setFetchEntityClass( TestTransactionEntity::class );
	remark( 'Fetch Transactions as list of entity objects:' );
	print_m( $table->getAll() );

	/** @var TestTransactionEntity $latestEntity */
	$latestEntity	= $table->getByIndex( 'topic', 'start', ['timestamp' => 'DESC'] );
	remark( 'Latest entry entity objects:' );
	print_m( $latestEntity );

//	$latestEntity->id = 1;
	$result	= $table->has( $latestEntity->id );
	remark( 'Has row with ID '.$latestEntity->id.': '.( $result ? 'yes' : 'no' ) );
	print_m( $result );
	$result	= $table->edit( $latestEntity->id, ['label' => (int) $latestEntity->label + 1] );
	remark( 'Increase object property "label" and save (using ::edit): '.$result );
	print_m( $table->get( $latestEntity->id ) );

	$latestEntity	= $table->getByIndex( 'topic', 'start', ['timestamp' => 'DESC'] );
	$latestEntity->label += 1;
	$result	= $table->save( $latestEntity ) ;
	remark( 'Increase entity object property "label" and save (using ::save): '.( $result ? 'yes' : 'no' ) );
	print_m( $table->get( $latestEntity->id ) );
}
catch( Exception $e ){
	CLI::out( $cliColor->asError( 'Error: '.$e->getMessage() ) );
	CLI::out( 'Location: '.$e->getFile().' at line '.$e->getLine() );
	CLI::out( 'Trace: ');
	CLI::out( $e->getTraceAsString() );
	CLI::out( '' );
}
