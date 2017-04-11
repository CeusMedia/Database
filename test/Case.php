<?php
//require_once 'PHPUnit/Framework/TestCase.php';
if( !class_exists( 'PHPUnit_Framework_TestCase' ) )
	require_once 'PHPUnit/Framework/TestCase.php';
class CeusMedia_Database_Test_Case extends PHPUnit_Framework_TestCase{
	static public $config;
	static public $pathLib;
}
CeusMedia_Database_Test_Case::$pathLib	= dirname( __DIR__  ).'/';
error_reporting( error_reporting() || ~E_USER_DEPRECATED );
?>
