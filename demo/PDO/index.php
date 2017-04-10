<?php
require_once __DIR__.'/../../vendor/autoload.php';
require_once __DIR__.'/UserTable.php';


try{
	$dsn	= new \CeusMedia\Database\PDO\DataSourceName( 'mysql', 'projects_towers' );
	$dbc	= new \CeusMedia\Database\PDO\Connection( $dsn, 'kriss', 'k' );

	$model	= new UserTable( $dbc );
	ob_start();
	print_r( $model->getAll() );
	$body	= ob_get_clean();
}
catch( Exception $e ){
	$body	= UI_HTML_ExceptionView::render( $e );
}


$page		= new UI_HTML_PageFrame();
$page->setBody( $body );
print( $page->build() );


