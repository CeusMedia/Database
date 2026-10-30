<?php
/** @noinspection PhpMultipleClassDeclarationsInspection */
$pathLib	= dirname( __DIR__, 2 ).'/';
require_once $pathLib.'vendor/autoload.php';

new CeusMedia\Common\UI\DevOutput;

use CeusMedia\Common\UI\HTML\Exception\Page as HtmlExceptionPage;
use CeusMedia\Common\UI\HTML\PageFrame as HtmlPage;
use CeusMedia\Common\UI\HTML\Tag as HtmlTag;
use CeusMedia\Database\PDO\Connection;
use CeusMedia\Database\PDO\DataSourceName;
use CeusMedia\DatabaseDemo\PDO\TransactionTable;

( file_exists( $pathLib.'demo/demo.ini' ) ) or die( 'Missing demo ini file (demo/demo.ini)'.PHP_EOL );

$config		= parse_ini_file( $pathLib.'demo/demo.ini', TRUE );
$dbConfig	= (object) $config['demo'];

//  reset demo data
$command	= "mysql -u%s -p%s %s < %sdemo/demo_transactions.sql";
$command	= sprintf( $command, $dbConfig->username, $dbConfig->password, $dbConfig->database, $pathLib );
passthru( $command );

$dsn	= DataSourceName::getInstance( $dbConfig->driver, $dbConfig->database )
	->setHost( $dbConfig->host )
	->setPort( (int) $dbConfig->port )
	->setUsername( $dbConfig->username )
	->setPassword( $dbConfig->password );

$code	= <<<'EOT'
$dsn	= DataSourceName::getInstance( 'mysql', 'myDatabase' )
	->setHost( 'myHost' )
	->setUsername( 'myUsername' )
	->setPassword( 'myPassword' );

$dbc	= new Connection( $dsn );
$table	= new TransactionTable( $dbc );

$table->getAll();
$table->getAll( ['topic' => ['start', 'stop'], 'id' => '>= 2'], ['timestamp' => 'DESC'], [0, 10] );
$table->getAllByIndex( 'topic', 'start' );
$table->get( 1 );
$table->getByIndex( 'topic', 'start', ['timestamp' => 'DESC'] );
$table->count( ['topic' => 'start'] );
$table->getField( 1, 'label' );

$dbc->transaction( function() use ( $table ){
	$newId	= $table->add( ['topic' => 'start', 'label' => 'new'] );
	$table->edit( $newId, ['label' => 'changed'] );
	$table->editByIndices( ['topic' => 'start'], ['label' => 'all changed'] );
} );                                    //  committed - or rolled back on any exception

$table->removeByIndices( ['topic' => 'stop'] );
EOT;

try{
	$dbc	= new Connection( $dsn );
	$model	= new TransactionTable( $dbc );
	$model->add( ['topic' => 'stop', 'label' => 'added by demo'] );
	$rows	= [];
	$heads	= [];
	foreach( $model->getAll() as $item ){
		$cells	= [];
		foreach( $item as $key => $value ){
			if( 0 === count( $rows ) )
				$heads[]	= HtmlTag::create( 'th', $key );
			$cells[]	= HtmlTag::create( 'td', $value );
		}
		$rows[]		= HtmlTag::create( 'tr', $cells );
	}
	$thead	= HtmlTag::create( 'thead', HtmlTag::create( 'tr', $heads ) );
	$tbody	= HtmlTag::create( 'tbody', $rows );
	$table	= HtmlTag::create( 'table', [$thead, $tbody], ['class' => 'table table-striped'] );

	$body	= HtmlTag::create( 'div', [
		HtmlTag::create( 'div', [
			HtmlTag::create( 'h1', 'PDO Demo' ),
			HtmlTag::create( 'p', 'Simple demo of CeusMedia/Database/PDO' ),
		], ['class' => 'hero-unit'] ),
		HtmlTag::create( 'h3', 'Transactions' ),
		$table,
		HtmlTag::create( 'p', 'Entries: '.$model->count().', starts: '.$model->count( ['topic' => 'start'] ) ),
		HtmlTag::create( 'div', '<xmp>'.$code.'</xmp>', ['class' => 'well'] ),
	], ['class' => 'container'] );
}
catch( Exception $e ){
	HtmlExceptionPage::display( $e );
	exit;
}

$pathCdn	= 'https://cdn.ceusmedia.de/';
$page		= new HtmlPage();
$page->addStylesheet( $pathCdn.'css/bootstrap.min.css' );
$page->addStylesheet( $pathCdn.'css/bootstrap-responsive.min.css' );
$page->setBody( $body );
print( $page->build() );
