<?php /** @noinspection PhpMultipleClassDeclarationsInspection */

$pathLib	= dirname( __DIR__, 2 ).'/';
require_once $pathLib.'vendor/autoload.php';

use CeusMedia\Common\UI\DevOutput;
use CeusMedia\Common\UI\HTML\Exception\Page as HtmlExceptionPage;
use CeusMedia\Common\UI\HTML\PageFrame as HtmlPage;
use CeusMedia\Common\UI\HTML\Tag as HtmlTag;
use CeusMedia\Common\UI\OutputBuffer;
use CeusMedia\Database\OSQL\Client;
use CeusMedia\Database\OSQL\Condition;
use CeusMedia\Database\OSQL\Condition\Group;
use CeusMedia\Database\OSQL\Query\Select;
use CeusMedia\Database\PDO\Connection;
use CeusMedia\Database\PDO\DataSourceName;

new DevOutput;

( file_exists( $pathLib.'demo/demo.ini' ) ) or die( 'Missing demo ini file (demo/demo.ini)'.PHP_EOL );

$config		= parse_ini_file( $pathLib.'demo/demo.ini', TRUE );
$dbConfig	= (object) ( $config['demo'] ?? [] );

$command	= "mysql -u%s -p%s %s < %sdemo/demo_galleries.sql";
$command	= sprintf( $command, $dbConfig->username, $dbConfig->password, $dbConfig->database, $pathLib );
passthru( $command );

/**
 *	Prints statement, parameters and result of a select query.
 */
function showQuery( string $title, Select $query, mixed $result ): void
{
	remark( '<h4>'.$title.'</h4>' );
	remark( 'Statement: '.$query->statement );
	remark( 'Parameters: '.json_encode( $query->parameters ) );
	print_m( $result );
}

try{
	$dsn	= DataSourceName::getInstance( $dbConfig->driver, $dbConfig->database )
		->setHost( $dbConfig->host )
		->setPort( (int) $dbConfig->port )
		->setUsername( $dbConfig->username )
		->setPassword( $dbConfig->password );
	$client	= new Client( new Connection( $dsn ) );

	$ob		= new OutputBuffer();

	//  images of galleries, joined
	$query	= $client->select( 'galleries AS g' )
		->fields( 'g.galleryId', 'g.title AS galleryTitle', 'gi.title AS imageTitle', 'gi.filename AS imageFilename' )
		->join( 'gallery_images AS gi', 'g.galleryId', 'gi.galleryId' )
		->where( 'g.rank', 0, '>' )
		->order( 'gi.rank' );
	showQuery( 'Images of galleries', $query, $query->all() );
	remark( 'Number of images: '.$query->count() );

	//  number of images per gallery, aggregated
	$query	= $client->select( 'galleries AS g' )
		->fields( 'g.title', 'COUNT(gi.galleryImageId) AS images' )
		->leftJoin( 'gallery_images AS gi', 'g.galleryId', 'gi.galleryId' )
		->groupBy( 'g.title' )
		->having( 'images', 0, '>' );
	showQuery( 'Number of images per gallery', $query, $query->all() );

	//  conditions combined by groups: images with title or of type PNG
	$query	= $client->select( 'gallery_images' )
		->fields( 'galleryImageId', 'filename', 'title' )
		->where( 'galleryId', [1, 2] )
		->and( Group::or(
			Condition::neq( 'title', '' ),
			Condition::like( 'filename', '%.png' )
		) );
	showQuery( 'Images with title or of type PNG', $query, $query->all() );

	//  single values
	$query	= $client->select( 'gallery_images' )->fields( 'filename' )->where( 'galleryId', 1 )->order( 'rank', 'DESC' );
	showQuery( 'Latest image filename', $query, $query->value() );

	$content	= $ob->get();
	$ob->close();
}
catch( Exception $e ){
	HtmlExceptionPage::display( $e );
	exit;
}

$body	= HtmlTag::create( 'div', [
	HtmlTag::create( 'div', [
		HtmlTag::create( 'h1', 'OSQL Demo' ),
		HtmlTag::create( 'p', 'Simple demo of the query builder of CeusMedia/Database' ),
	], ['class' => 'hero-unit'] ),
	HtmlTag::create( 'h3', 'Galleries' ),
	$content,
], ['class' => 'container'] );

$pathCdn	= 'https://cdn.ceusmedia.de/';
$page		= new HtmlPage();
$page->addStylesheet( $pathCdn.'css/bootstrap.min.css' );
$page->addStylesheet( $pathCdn.'css/bootstrap-responsive.min.css' );
$page->setBody( $body );
print( $page->build() );
