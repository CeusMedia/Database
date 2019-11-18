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

$dbName		= 'radio';
$dbUsername	= 'kriss';
$dbPassword	= 'k';

$dbc	= new Connection( 'mysql:host=localhost;dbname='.$dbName, $dbUsername, $dbPassword );
$client	= new Client( $dbc );

$select	= Select::create( $client )
	->get( array(
		't.title as trackTitle',
		't.youtube as trackYoutube',
		't.bandcamp as trackBandcamp',
		'a.title AS artistTitle',
		'r.title AS releaseTitle',
		'e.title as episodeTitle',
//		'e.episodeId',
	) )
	->from( new Table( 'library_tracks', 't' ) )
	->where( new Condition( 'e.episodeId', 42 ) )
//	->leftJoin( new Table( 'library_artists', 'a' ), 't.artistId', 'a.artistId' )
//	->leftJoin( new Table( 'library_albums', 'r' ), 'r.albumId', 't.albumId' )
//	->leftJoin( new Table( 'library_track_episodes', 'te' ), 'te.trackId', 't.trackId' )
//	->leftJoin( new Table( 'library_show_episodes', 'e' ), 'e.episodeId', 'te.episodeId' )
	->leftJoin( new Table( 'library_artists', 'a' ), 'artistId' )
	->leftJoin( new Table( 'library_albums', 'r' ), 'albumId' )
	->leftJoin( new Table( 'library_track_episodes', 'te' ), 'trackId' )
	->leftJoin( new Table( 'library_show_episodes', 'e' ), 'episodeId' )
	->limit( 3 )
	->offset( 6 )
	->order( 't.trackId', 'DESC' )
	->countRows( TRUE )
;


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
