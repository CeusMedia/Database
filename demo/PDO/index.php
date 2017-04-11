<?php
$pathLib	= dirname( dirname( __DIR__ ) ).'/';
require_once $pathLib.'vendor/autoload.php';
require_once $pathLib.'test/PDO/TransactionTable.php';
new UI_DevOutput;

$config	= parse_ini_file( $pathLib.'test/test.ini', TRUE );
extract( $config['unitTest-Database'] );

try{
	$dsn	= new \CeusMedia\Database\PDO\DataSourceName( 'mysql', $database );
	$dbc	= new \CeusMedia\Database\PDO\Connection( $dsn, $username, $password );

	$command	= "mysql -u%s -p%s %s < %stest/PDO/createTable.sql";
	$command	= sprintf( $command, $username, $password, $database, $pathLib );
	passthru( $command );

	$model	= new CeusMedia_Database_Test_PDO_TransactionTable( $dbc );
	$rows	= array();
	$heads	= array();
	foreach( $model->getAll() as $item ){
		$cells	= array();
		foreach( $item as $key => $value ){
			if( !count( $rows ) )
				$heads[]	= UI_HTML_Tag::create( 'th', $key );
			$cells[]	= UI_HTML_Tag::create( 'td', $value );
		}
		$rows[]		= UI_HTML_Tag::create( 'tr', $cells );
	}
	$thead	= UI_HTML_Tag::create( 'thead', UI_HTML_Tag::create( 'tr', $heads ) );
	$tbody	= UI_HTML_Tag::create( 'tbody', $rows );
	$table	= UI_HTML_Tag::create( 'table', array( $thead, $tbody ), array( 'class' => 'table table-striped' ) );

	$body	= UI_HTML_Tag::create( 'div', array(
		UI_HTML_Tag::create( 'div', array(
			UI_HTML_Tag::create( 'h1', 'PDO Demo' ),
			UI_HTML_Tag::create( 'p', 'Simple demo of CeusMedia/Database/PDO' ),
		), array( 'class' => 'hero-unit' ) ),
		UI_HTML_Tag::create( 'h3', 'Transactions' ),
		$table,
	), array( 'class' => 'container' ) );
}
catch( Exception $e ){
	UI_HTML_Exception_Page::display( $e );
	exit;
}

$pathCdn	= 'https://cdn.ceusmedia.de/';
$page		= new UI_HTML_PageFrame();
$page->addStylesheet( $pathCdn.'css/bootstrap.min.css' );
$page->addStylesheet( $pathCdn.'css/bootstrap-responsive.min.css' );
$page->setBody( $body );
print( $page->build() );
