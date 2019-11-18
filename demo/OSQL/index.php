<?php
require_once __DIR__.'/../../vendor/autoload.php';
new UI_DevOutput;

use CeusMedia\Database\OSQL\Condition;
use CeusMedia\Database\OSQL\Condition\Group as ConditionGroup;
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
	->join( $tableImage, 'g.galleryId', 'gi.galleryId' );
;

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

/*
$states = [FALSE, TRUE];
foreach( $states as $a ){
	foreach( $states as $b ){
		foreach( $states as $c ){
			foreach( $states as $d ){
				$f = $a && $b || $c && $d;
				print_m( [
					'a' => $a,
					'b' => $b,
					'c' => $c,
					'd' => $d,
					'f' => $f,
				] );
			}
		}
	}
}
*/
