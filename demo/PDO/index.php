<?php
/** @noinspection PhpMultipleClassDeclarationsInspection */
$pathLib	= dirname( __DIR__, 2 ).'/';
require_once $pathLib.'vendor/autoload.php';
require_once $pathLib.'test/PDO/TransactionTable.php';

new CeusMedia\Common\UI\DevOutput;

use CeusMedia\Common\UI\HTML\Exception\Page as HtmlExceptionPage;
use CeusMedia\Common\UI\HTML\PageFrame as HtmlPage;
use CeusMedia\Common\UI\HTML\Tag as HtmlTag;
use CeusMedia\Database\PDO\Connection;
use CeusMedia\Database\PDO\DataSourceName;
use CeusMedia\DatabaseTest\PDO\TransactionTable as TestTransactionTable;

( file_exists( $pathLib.'demo/demo.ini' ) ) or die( 'Missing demo ini file (demo/demo.ini)'.PHP_EOL );

$config		= parse_ini_file( $pathLib.'demo/demo.ini', TRUE );
$dbConfig	= (object) $config['demo'];

/*$command	= "mysql -u%s -p%s %s < %sdemo/demo_transactions.sql";
$command	= sprintf( $command, $dbConfig->username, $dbConfig->password, $dbConfig->database, $pathLib );
passthru( $command );
*/

$dsn		= DataSourceName::renderStatic(
	$dbConfig->driver,
	$dbConfig->database,
	$dbConfig->host,
	$dbConfig->port,
	$dbConfig->username,
	$dbConfig->password
);

$code	= <<<'EOT'
$dsn	= DataSourceName::renderStatic(
	'mysql',
	'myDatabaseName',
	'myHostName',
	myHostPort,
	'myUsername',
	'myPassword'
);

try{
	$dbc	= new Connection( $dsn, 'myUsername', 'myPassword' );
	$table	= new TestTransactionTable( $dbc );
	$table->getAll();
	$table->getAllByIndex( 'topic', 'start' );
	$table->getAllByIndices( ['topic' => 'start'], ['timestamp' => DESC], [0, 10] );
	$table->get( '1' );
	$table->getByIndex( 'topic', 'start' );
	$table->getByIndices( ['topic' => 'start'], ['timestamp' => DESC] );
	$table->edit( '1', ['timestamp' => date( 'Y-m-d H:i:s' )] );
	$table->editByIndices( ['topic' => 'start'], ['timestamp' => date( 'Y-m-d H:i:s' )] );
	$newId	= $table->add( ['topic' => 'start', 'timestamp' => date( 'Y-m-d H:i:s' )] );
	$table->remove( $newId );
	$table->removeByIndices( ['topic' => 'start'] );
} catch( \Throwable ) {
	...
}
EOT;

try{
	$dbc	= new Connection( $dsn, $dbConfig->username, $dbConfig->password );
	$model	= new TestTransactionTable( $dbc );
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
