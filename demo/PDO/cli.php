<?php
/** @noinspection PhpMultipleClassDeclarationsInspection */

$pathLib	= dirname( __DIR__, 2 ).'/';
require_once $pathLib.'vendor/autoload.php';

use CeusMedia\Common\CLI;
use CeusMedia\Common\CLI\Color as CliColor;
use CeusMedia\Common\UI\DevOutput;
use CeusMedia\Database\PDO\Connection;
use CeusMedia\Database\PDO\DataSourceName;
use CeusMedia\DatabaseDemo\PDO\TransactionEntity;
use CeusMedia\DatabaseDemo\PDO\TransactionTable;

new DevOutput();

$cliColor	= new CliColor();

( file_exists( $pathLib.'demo/demo.ini' ) ) or die( 'Missing demo ini file (demo/demo.ini)'.PHP_EOL );

$config		= parse_ini_file( $pathLib.'demo/demo.ini', TRUE );
$dbConfig	= (object) ( $config['demo'] ?? [] );

//  reset demo data
$command	= "mysql -u%s -p%s %s < %sdemo/demo_transactions.sql";
$command	= sprintf( $command, $dbConfig->username, $dbConfig->password, $dbConfig->database, $pathLib );
passthru( $command );

function title( string $title ): void
{
	global $cliColor;
	CLI::out( PHP_EOL.$cliColor->bold( $title ) );
}

try{
	$dsn	= DataSourceName::getInstance( $dbConfig->driver, $dbConfig->database )
		->setHost( $dbConfig->host )
		->setPort( (int) $dbConfig->port )
		->setUsername( $dbConfig->username )
		->setPassword( $dbConfig->password );
	$dbc	= new Connection( $dsn );                         //  credentials are taken from DSN object

	$pathLogs	= sys_get_temp_dir().'/ceusmedia-database-demo/';
	$dbc->setErrorLogFile( $pathLogs.'error.log' );
	$dbc->setStatementLogFile( $pathLogs.'statement.log' );
	$dbc->setLogLevel( Connection::LOG_LEVEL_ERROR | Connection::LOG_LEVEL_STATEMENT );

	$table	= new TransactionTable( $dbc );
	$table->add( ['topic' => 'stop', 'label' => 'first stop'] );
	$table->add( ['topic' => 'stop', 'label' => 'second stop'] );

	title( 'All entries, as anonymous objects:' );
	print_m( $table->getAll() );

	title( 'Conditions with operators and lists, orders and limits:' );
	print_m( $table->getAll( ['topic' => ['start', 'stop'], 'id' => '>= 2'], ['id' => 'DESC'], [0, 10] ) );

	title( 'Single values:' );
	CLI::out( ' - number of stops: '.$table->count( ['topic' => 'stop'] ) );
	CLI::out( ' - label of entry 2: '.$table->getField( 2, 'label' ) );
	CLI::out( ' - IDs of stops: '.implode( ', ', $table->getColumnOfAllByConditions( 'id', ['topic' => 'stop'] ) ) );
	CLI::out( ' - last query: '.$table->getLastQuery() );

	title( 'Entries as entity objects:' );
	$table->setFetchMode( PDO::FETCH_CLASS );
	$table->setFetchEntityClass( TransactionEntity::class );
	/** @var TransactionEntity $entity */
	$entity	= $table->getByIndex( 'topic', 'start', ['timestamp' => 'DESC'] );
	print_m( $entity );
	CLI::out( ' - age (by entity method): '.$entity->getAge() );

	title( 'Change entity and save:' );
	$entity->label	= 'changed by save()';
	CLI::out( ' - saved: '.( $table->save( $entity ) ? 'yes' : 'no' ) );
	CLI::out( ' - label now: '.$table->get( $entity->id )->label );

	title( 'Transactions:' );
	$id	= $dbc->transaction( fn() => $table->add( ['topic' => 'start', 'label' => 'committed'] ) );
	CLI::out( ' - added entry '.$id.' in committed transaction' );
	try{
		$dbc->transaction( function() use ( $table ){
			$table->add( ['topic' => 'start', 'label' => 'rolled back'] );
			throw new RuntimeException( 'Something failed' );
		} );
	}
	catch( RuntimeException $e ){
		CLI::out( ' - rolled back on exception: '.$e->getMessage() );
	}
	CLI::out( ' - entries with label "rolled back": '.$table->count( ['label' => 'rolled back'] ) );

	title( 'Mistyped column:' );
	try{
		$table->getAll( ['topc' => 'start'] );
	}
	catch( DomainException $e ){
		CLI::out( ' - '.$e->getMessage() );
	}

	title( 'Log files:' );
	CLI::out( ' - errors: '.$pathLogs.'error.log' );
	CLI::out( ' - statements: '.$pathLogs.'statement.log' );
	CLI::out();
}
catch( Exception $e ){
	CLI::out( $cliColor->asError( 'Error: '.$e->getMessage() ) );
	CLI::out( 'Location: '.$e->getFile().' at line '.$e->getLine() );
	CLI::out( 'Trace: ');
	CLI::out( $e->getTraceAsString() );
	CLI::out( '' );
}
