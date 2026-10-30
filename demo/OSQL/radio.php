<?php /** @noinspection PhpMultipleClassDeclarationsInspection */

/**
 *	Demo of joins over several tables.
 *	Needs the tables "library_*" of a radio show library, which are not part of the demo SQL files.
 */

$pathLib	= dirname( __DIR__, 2 ).'/';
require_once $pathLib.'vendor/autoload.php';

use CeusMedia\Common\UI\HTML\Exception\Page as HtmlExceptionPage;
use CeusMedia\Common\UI\DevOutput;
use CeusMedia\Database\OSQL\Client;
use CeusMedia\Database\PDO\Connection;
use CeusMedia\Database\PDO\DataSourceName;

new DevOutput;

( file_exists( $pathLib.'demo/demo.ini' ) ) or die( 'Missing demo ini file (demo/demo.ini)'.PHP_EOL );

$config		= parse_ini_file( $pathLib.'demo/demo.ini', TRUE );
$dbConfig	= (object) ( $config['demo'] ?? [] );

try{
	$dsn	= DataSourceName::getInstance( $dbConfig->driver, $dbConfig->database )
		->setHost( $dbConfig->host )
		->setPort( (int) $dbConfig->port )
		->setUsername( $dbConfig->username )
		->setPassword( $dbConfig->password );
	$client	= new Client( new Connection( $dsn ) );

	$select	= $client->select( 'library_tracks AS t' )
		->fields(
			't.title AS trackTitle',
			't.youtube AS trackYoutube',
			't.bandcamp AS trackBandcamp',
			'a.title AS artistTitle',
			'r.title AS releaseTitle',
			'e.title AS episodeTitle'
		)
		->leftJoin( 'library_artists AS a', 't.artistId', 'a.artistId' )
		->leftJoin( 'library_albums AS r', 't.albumId', 'r.albumId' )
		->leftJoin( 'library_track_episodes AS te', 't.trackId', 'te.trackId' )
		->leftJoin( 'library_show_episodes AS e', 'te.episodeId', 'e.episodeId' )
		->where( 'e.episodeId', 42 )
		->order( 't.trackId', 'DESC' )
		->limit( 3 )
		->offset( 6 );

	$query	= $select->render();
	remark( 'Query:' );
	remark( $query->query );
	remark( 'Parameters:' );
	print_m( $query->parameters );
	remark( 'Result:' );
	print_m( $select->all() );
	remark( 'Rows:' );
	print_m( $select->count() );
}
catch( Exception $e ){
	HtmlExceptionPage::display( $e );
	exit;
}
