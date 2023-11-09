<?php /** @noinspection PhpMultipleClassDeclarationsInspection */

$pathLib	= dirname( __DIR__, 2 ).'/';
require_once $pathLib.'vendor/autoload.php';
require_once $pathLib.'test/PDO/TransactionTable.php';

use CeusMedia\Common\UI\DevOutput;
use CeusMedia\Common\UI\HTML\Exception\Page as HtmlExceptionPage;
use CeusMedia\Common\UI\HTML\PageFrame as HtmlPage;
use CeusMedia\Common\UI\HTML\Tag as HtmlTag;
use CeusMedia\Common\UI\OutputBuffer;
use CeusMedia\Database\PDO\DataSourceName;
use CeusMedia\Database\OSQL\Condition;
use CeusMedia\Database\OSQL\Condition\Group as ConditionGroup;
use CeusMedia\Database\OSQL\Connection;
use CeusMedia\Database\OSQL\Client;
use CeusMedia\Database\OSQL\Table;
use CeusMedia\Database\OSQL\Query\Select;

new DevOutput;

( file_exists( $pathLib.'demo/demo.ini' ) ) or die( 'Missing demo ini file (demo/demo.ini)'.PHP_EOL );

$config		= parse_ini_file( $pathLib.'demo/demo.ini', TRUE );
$dbConfig	= (object) ( $config['demo'] ?? '' );

$command	= "mysql -u%s -p%s %s < %sdemo/demo_galleries.sql";
$command	= sprintf( $command, $dbConfig->username, $dbConfig->password, $dbConfig->database, $pathLib );
passthru( $command );


try{
	$dsn		= DataSourceName::renderStatic(
		$dbConfig->driver,
		$dbConfig->database,
		$dbConfig->host,
		$dbConfig->port,
		$dbConfig->username,
		$dbConfig->password
	);

	$tableGallery	= new Table( 'galleries', 'g' );
	$tableImage		= new Table( 'gallery_images', 'gi' );

	$dbc	= new Connection( $dsn, $dbConfig->username, $dbConfig->password );
	$client	= new Client( $dbc );
	$select	= Select::create( $client )
		->get( [
			'g.galleryId',
			'g.title AS galleryTitle',
			'gi.title AS imageTitle',
			'gi.filename AS imageFilename',
		] )
		->from( $tableGallery )
		->join( $tableImage, 'g.galleryId', 'gi.galleryId' )
		->countRows( TRUE );

	if( 0 ){
		$select->where( new ConditionGroup( 'AND', [
			new ConditionGroup( 'OR', [
				new Condition( 1, 1 ),
				new Condition( -1, -1 ),
			] ),
			new ConditionGroup( 'OR', [
				new Condition( 3, 3 ),
				new Condition( -3, -3 ),
			] ),
		] ) )
		;
	}
	if( 0 ){
		$select->where( new Condition( 1, 1 ) )
			->or( new Condition( 2, 2 ) )
			->or( new Condition( -2, -2 ) )
		;
	}
	if( 0 ){
		$select->where( new Condition( 1, 1 ) )
			->or( new Condition( 2, 2 ) )
			->or( new Condition( -2, -2 ) )
			->and( new Condition( 3, 3 ) )
			->and( new Condition( -3, -3 ) )
			->or( new Condition( 4, 4 ) )
			->or( new Condition( -4, -4 ) )
			->or( new Condition( 5, 5 ) )
		;
	}
	if( 1 ){
	//	$select->where( new Condition( 1, [1, 2, 3], 'IN' ) )
		$select->where( new Condition( 'g.rank', 0, '>' ) )
		;
	}

	//	->and( new Condition( 'g.galleryId', 1 ) )
	//	->groupBy( 'g.galleryId' )
	//;

	$query	= $select->render();

	$ob		= new OutputBuffer();
	remark( 'Query:' );
	remark( $query->query );
	remark( 'Parameters:' );
	print_m( $query->parameters );
	remark( 'Result:' );
	print_m( $select->execute() );
	remark( 'Rows:' );
	print_m( $select->foundRows );
	remark( 'Timing:' );
	print_m( $select->timing );

	$content	= $ob->get();
	$ob->close();
}
catch( Exception $e ){
	HtmlExceptionPage::display( $e );
	exit;
}

$body	= HtmlTag::create( 'div', array(
	HtmlTag::create( 'div', array(
		HtmlTag::create( 'h1', 'OSQL Demo' ),
		HtmlTag::create( 'p', 'Simple demo of CeusMedia/Database/OSQL' ),
	), array( 'class' => 'hero-unit' ) ),
	HtmlTag::create( 'h3', 'Galleries' ),
	$content,
), array( 'class' => 'container' ) );

$pathCdn	= 'https://cdn.ceusmedia.de/';
$page		= new HtmlPage();
$page->addStylesheet( $pathCdn.'css/bootstrap.min.css' );
$page->addStylesheet( $pathCdn.'css/bootstrap-responsive.min.css' );
$page->setBody( $body );
print( $page->build() );
