<?php
require_once __DIR__.'/../../vendor/autoload.php';
new UI_DevOutput;

use CeusMedia\Database\OSQL\Condition;
use CeusMedia\Database\OSQL\Connection;
use CeusMedia\Database\OSQL\Client;
use CeusMedia\Database\OSQL\Table;
use CeusMedia\Database\OSQL\Query\Select;

$tableGallery	= new Table( 'galleries', 'g' );
$tableImage		= new Table( 'gallery_images', 'gi' );

$dbName		= 'test';
$dbUsername	= 'kriss';
$dbPassword	= 'k';

$dbc	= new Connection( 'mysql:host=localhost;dbname=test', $dbUsername, $dbPassword );
$client	= new Client( $dbc );
$select	= Select::create( $client )
	->get( array(
		'g.galleryId',
		'g.title AS galleryTitle',
		'gi.title AS imageTitle',
		'gi.filename AS imageFilename',
	) )
	->from( $tableGallery )
	->join( $tableImage, 'g.galleryId', 'gi.galleryId' )
	->where( new Condition( 'g.galleryId', 1 ) )
//	->groupBy( 'g.galleryId' )
;

$query	= $select->render();
remark( 'Query:' );
print_m( $query[0] );
remark( 'Parameters:' );
print_m( $query[1] );
remark( 'Result:' );
print_m( $select->execute() );
