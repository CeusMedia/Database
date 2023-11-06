<?php
/** @noinspection PhpMultipleClassDeclarationsInspection */
$pathLib	= dirname( dirname( __DIR__ ) ).'/';
require_once $pathLib.'vendor/autoload.php';
require_once $pathLib.'test/PDO/TransactionTable.php';

new CeusMedia\Common\UI\DevOutput;

use CeusMedia\Common\UI\HTML\Tag as HtmlTag;
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
	$model	= new \CeusMedia\DatabaseTest\PDO\TransactionTable( $dbc );
	$rows	= array();
	$heads	= array();
	foreach( $model->getAll() as $item ){
		$cells	= array();
		foreach( $item as $key => $value ){
			if( !count( $rows ) )
				$heads[]	= HtmlTag::create( 'th', $key );
			$cells[]	= HtmlTag::create( 'td', $value );
		}
		$rows[]		= HtmlTag::create( 'tr', $cells );
	}
	$thead	= HtmlTag::create( 'thead', HtmlTag::create( 'tr', $heads ) );
	$tbody	= HtmlTag::create( 'tbody', $rows );
	$table	= HtmlTag::create( 'table', array( $thead, $tbody ), array( 'class' => 'table table-striped' ) );

	$body	= HtmlTag::create( 'div', array(
		HtmlTag::create( 'div', array(
			HtmlTag::create( 'h1', 'PDO Demo' ),
			HtmlTag::create( 'p', 'Simple demo of CeusMedia/Database/PDO' ),
		), array( 'class' => 'hero-unit' ) ),
		HtmlTag::create( 'h3', 'Transactions' ),
		$table,
	), array( 'class' => 'container' ) );
}
catch( Exception $e ){
	\CeusMedia\Common\UI\HTML\Exception\Page::display( $e );
	exit;
}

$pathCdn	= 'https://cdn.ceusmedia.de/';
$page		= new \CeusMedia\Common\UI\HTML\PageFrame();
$page->addStylesheet( $pathCdn.'css/bootstrap.min.css' );
$page->addStylesheet( $pathCdn.'css/bootstrap-responsive.min.css' );
$page->setBody( $body );
print( $page->build() );
