<?php
/** @noinspection PhpMultipleClassDeclarationsInspection */

$pathLib	= dirname( __DIR__, 2 ).'/';
require_once $pathLib.'vendor/autoload.php';

use CeusMedia\Common\CLI;
use CeusMedia\Common\CLI\Color as CliColor;
use CeusMedia\Database\OSQL\Client;
use CeusMedia\Database\PDO\Connection;
use CeusMedia\Database\PDO\DataSourceName;

$cliColor	= new CliColor();

( file_exists( $pathLib.'demo/demo.ini' ) ) or die( 'Missing demo ini file (demo/demo.ini)'.PHP_EOL );

$config		= parse_ini_file( $pathLib.'demo/demo.ini', TRUE );
$dbConfig	= (object) ( $config['demo'] ?? [] );

$command	= "mysql -u%s -p%s %s < %sdemo/demo_galleries.sql";
$command	= sprintf( $command, $dbConfig->username, $dbConfig->password, $dbConfig->database, $pathLib );
//passthru( $command );

try{
	$dsn	= DataSourceName::getInstance( $dbConfig->driver, $dbConfig->database )
		->setHost( $dbConfig->host )
		->setPort( (int) $dbConfig->port )
		->setUsername( $dbConfig->username )
		->setPassword( $dbConfig->password );
	$client	= new Client( new Connection( $dsn ) );

	$query	= $client->select( 'galleries AS g' )
		->fields( 'g.galleryId', 'g.title', 'COUNT(gi.galleryImageId) AS images', 'MAX(gi.timestamp) AS lastUpload' )
		->leftJoin( 'gallery_images AS gi', 'g.galleryId', 'gi.galleryId' )
		->where( 'g.galleryId', 1 )
		->groupBy( 'g.galleryId', 'g.title' );

	$result	= $query->all();

	CLI::out( PHP_EOL.$cliColor->bold( 'Final Statement:' ) );
	CLI::out( $query->statement );

	CLI::out( PHP_EOL.$cliColor->bold( 'Bound Parameters:' ) );
	foreach( $query->parameters as $nr => $value )
		CLI::out( ' - #'.( $nr + 1 ).': ('.get_debug_type( $value ).') '.$value );

	CLI::out( PHP_EOL.$cliColor->bold( 'Result:' ) );
	foreach( $result as $nr => $row ){
		CLI::out( ' - Row #'.$nr );
		foreach( (array) $row as $key => $data )
			CLI::out( '   - '.$key.': '.$data );
	}
	CLI::out( PHP_EOL.$cliColor->bold( 'Number of galleries:' ).' '.$client->select( 'galleries' )->count() );
	CLI::out();
}
catch( Exception $e ){
	CLI::out( $cliColor->asError( 'Error: '.$e->getMessage() ) );
	CLI::out( 'Trace: ');
	CLI::out( $e->getTraceAsString().PHP_EOL );
}
