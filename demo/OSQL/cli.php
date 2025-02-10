<?php
/** @noinspection PhpMultipleClassDeclarationsInspection */

$pathLib	= dirname( __DIR__, 2 ).'/';
require_once $pathLib.'vendor/autoload.php';

use CeusMedia\Common\Alg\UnitFormater;
use CeusMedia\Common\CLI;
use CeusMedia\Common\CLI\Color as CliColor;
use CeusMedia\Common\UI\DevOutput;
use CeusMedia\Database\PDO\DataSourceName;
use CeusMedia\Database\OSQL\Client;
use CeusMedia\Database\OSQL\Connection;
use CeusMedia\Database\OSQL\Condition;
use CeusMedia\Database\OSQL\Table;
use CeusMedia\Database\OSQL\Query\Select;

new DevOutput();

$cliColor	= new CliColor();

( file_exists( $pathLib.'demo/demo.ini' ) ) or die( 'Missing demo ini file (demo/demo.ini)'.PHP_EOL );

$config		= parse_ini_file( $pathLib.'demo/demo.ini', TRUE );
$dbConfig	= (object) ( $config['demo'] ?? [] );

$command	= "mysql -u%s -p%s %s < %sdemo/demo_galleries.sql";
$command	= sprintf( $command, $dbConfig->username, $dbConfig->password, $dbConfig->database, $pathLib );
//passthru( $command );

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
	$client	= new Client( $dbc );

	$query	= Select::create( $client )
		->get( ['*', 'SUM(galleryId) as summedGalleryIds'] )
//		->groupBy( 'galleryId' )
		->from( new Table( 'galleries', 'g' ) )
		->where( new Condition( 'galleryId', 1, Condition::OP_EQ ) );
//		->countRows();

	$result	= $query->execute();

	CLI::out( PHP_EOL.$cliColor->bold( 'Final Statement:' ) );
	CLI::out( str_replace( PHP_EOL, ' ', $query->statement ) );

	CLI::out( PHP_EOL.$cliColor->bold( 'Bound Parameters:' ) );
	foreach( $query->parameters as $key => $data )
		CLI::out( ' - '.$key.': ('.$data['type'].') '.$data['value'] );

	CLI::out( PHP_EOL.$cliColor->bold( 'Result:' ) );
	foreach( $result as $nr => $row ){
		CLI::out( ' - Row #'.$nr );
		foreach( $row as $key => $data )
			CLI::out( '   - '.$key.': '.$data );
	}
	CLI::out( PHP_EOL.$cliColor->bold( 'Query timings:' ) );
	foreach( $query->timing as $key => $value )
		CLI::out( ' - '.$key.': '.UnitFormater::formatSeconds( $value ) );
	CLI::out();
}
catch( Exception $e ){
	CLI::out( $cliColor->asError( 'Error: '.$e->getMessage() ) );
	CLI::out( 'Trace: ');
	CLI::out( $e->getTraceAsString().PHP_EOL );
}
