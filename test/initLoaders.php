<?php
if( !file_exists( dirname( __DIR__ ).'/vendor/autoload.php' ) )
	die( 'Please install libraries with composer, first!' );

$pathTest	= __DIR__.'/';

$loaderTest	= new \Loader();										//  get new Loader Instance
$loaderTest->setExtensions( 'php' );								//  set allowed Extension
$loaderTest->setPath( $pathTest );									//  set fixed Library Path
$loaderTest->setPrefix( 'CeusMedia_Database_Test_' );				//  set prefix class prefix
$loaderTest->registerAutoloader();									//  apply this autoloader

$__config	= parse_ini_file( $pathTest.'/test.ini', TRUE );
//new UI_DevOutput;
//print_m( $__config );die;

CeusMedia_Database_Test_Case::$pathLib	= dirname( __DIR__  ).'/';
CeusMedia_Database_Test_Case::$config	= $__config;
error_reporting( E_ALL );
//error_reporting( error_reporting() || ~E_USER_DEPRECATED );

?>
