<?php
$pathLib	= dirname( dirname( __DIR__ ) ).'/';
require_once $pathLib.'vendor/autoload.php';
new UI_DevOutput();
$cliColor	= new CLI_Color();

use CeusMedia\Database\PDO\DataSourceName;
use CeusMedia\Database\OSQL\Client;
use CeusMedia\Database\OSQL\Connection;
use CeusMedia\Database\OSQL\Condition;
use CeusMedia\Database\OSQL\Table;
use CeusMedia\Database\OSQL\Query\Select;

( file_exists( $pathLib.'demo/demo.ini' ) ) or die( 'Missing demo ini file (demo/demo.ini)'.PHP_EOL );

$config		= parse_ini_file( $pathLib.'demo/demo.ini', TRUE );
$dbConfig	= (object) $config['demo'];

$command	= "mysql -u%s -p%s %s < %sdemo/demo_galleries.sql";
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
	$client	= new Client( $dbc );

	$query	= Select::create( $client )
		->from( new Table( 'galleries', 'g' ) )
		->where( new Condition( 'galleryId', 1, Condition::OP_EQ ) )
		->countRows();

	$result	= $query->execute();

	CLI::OUT( PHP_EOL.$cliColor->bold( 'Final Statement:' ) );
	CLI::OUT( str_replace( PHP_EOL, ' ', $query->statement ) );

	CLI::OUT( PHP_EOL.$cliColor->bold( 'Bound Parameters:' ) );
	foreach( $query->parameters as $key => $data )
		CLI::OUT( ' - '.$key.': ('.$data['type'].') '.$data['value'] );

	CLI::OUT( PHP_EOL.$cliColor->bold( 'Result:' ) );
	foreach( $result as $nr => $row ){
		CLI::OUT( ' - Row #'.$nr );
		foreach( $row as $key => $data )
			CLI::OUT( '   - '.$key.': '.$data );
	}
	CLI::OUT( PHP_EOL.$cliColor->bold( 'Query timings:' ) );
	foreach( $query->timing as $key => $value )
		CLI::OUT( ' - '.$key.': '.Alg_UnitFormater::formatSeconds( $value ) );
	CLI::OUT();
}
catch( Exception $e ){
	CLI::out( $cliColor->asError( 'Error: '.$e->getMessage() ) );
	CLI::out( 'Trace: ');
	CLI::out( $e->getTraceAsString().PHP_EOL );
}
