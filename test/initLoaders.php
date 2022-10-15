<?php

use CeusMedia\DatabaseTest\TestCase;

if( !file_exists( dirname( __DIR__ ).'/vendor/autoload.php' ) )
	die( 'Please install libraries with composer, first!' );

$pathTest	= __DIR__.'/';

//  get new Loader Instance
$loaderTest	= new \CeusMedia\Common\Loader();
//  set allowed Extension
$loaderTest->setExtensions( 'php' );
//  set fixed Library Path
$loaderTest->setPath( $pathTest );
//  set prefix class prefix
$loaderTest->setPrefix( 'CeusMedia_Database_Test_' );
//  apply this autoloader
$loaderTest->registerAutoloader();

/** @var array $__config */
$__config	= parse_ini_file( $pathTest.'/test.ini', TRUE );
//new UI_DevOutput;
//print_m( $__config );die;

TestCase::$pathLib	= dirname( __DIR__  ).'/';
TestCase::$config	= $__config;
error_reporting( E_ALL );
//error_reporting( error_reporting() || ~E_USER_DEPRECATED );
