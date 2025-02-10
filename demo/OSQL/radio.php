<?php /** @noinspection PhpMultipleClassDeclarationsInspection */

$pathLib	= dirname( __DIR__, 2 ).'/';
require_once $pathLib.'vendor/autoload.php';
require_once $pathLib.'test/PDO/TransactionTable.php';

use CeusMedia\Common\UI\HTML\Exception\Page as HtmlExceptionPage;
use CeusMedia\Common\UI\DevOutput;
use CeusMedia\Database\PDO\DataSourceName;
use CeusMedia\Database\OSQL\Condition;
use CeusMedia\Database\OSQL\Connection;
use CeusMedia\Database\OSQL\Client;
use CeusMedia\Database\OSQL\Table;
use CeusMedia\Database\OSQL\Query\Select;
new DevOutput;

( file_exists( $pathLib.'demo/demo.ini' ) ) or die( 'Missing demo ini file (demo/demo.ini)'.PHP_EOL );

$config		= parse_ini_file( $pathLib.'demo/demo.ini', TRUE );
$dbConfig	= (object) ( $config['demo'] ?? [] );

$tableGallery	= new Table( 'galleries', 'g' );
$tableImage		= new Table( 'gallery_images', 'gi' );

try{

	$dsn		= DataSourceName::renderStatic(
		$dbConfig->driver,
		$dbConfig->database,
		$dbConfig->host,
		$dbConfig->port,
		$dbConfig->username,
		$dbConfig->password
	);

	$dbc	= new Connection( $dsn, $dbConfig->username, $dbConfig->password );
	$client	= new Client( $dbc );

	$select	= Select::create( $client )
		->get( [
			't.title as trackTitle',
			't.youtube as trackYoutube',
			't.bandcamp as trackBandcamp',
			'a.title AS artistTitle',
			'r.title AS releaseTitle',
			'e.title as episodeTitle',
	//		'e.episodeId',
		] )
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
}
catch( Exception $e ){
	HtmlExceptionPage::display( $e );
	exit;
}
