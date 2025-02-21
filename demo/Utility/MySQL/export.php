<?php
require_once dirname( __DIR__, 3 ).'/vendor/autoload.php';

use CeusMedia\Common\CLI;
use CeusMedia\Database\PDO\DataSourceName as PdoDataSourceName;
use CeusMedia\Database\Utility\MySQL\Export;

try{
	$export	= new Export( loadDsn() );
	$export->exportToFile( 'test.sql' );
}
catch( Throwable $e ){
	print( CeusMedia\Common\CLI\Exception\View::getInstance( $e )->render() );
}


function loadDsn(): PdoDataSourceName
{
	if( !file_exists( 'config.ini' ) )
		die( 'Missing database config file "config.ini".' );
	$dba	= (object) parse_ini_file( 'config.ini' );

	$dsn	= new PdoDataSourceName( 'mysql' );
	$dsn->setHost( $dba->host );
	$dsn->setPort( $dba->port );
	$dsn->setDatabase( $dba->database );
	$dsn->setUsername( $dba->username );
	$dsn->setPassword( $dba->password );
	return $dsn;
}
