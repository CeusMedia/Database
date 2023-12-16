<?php
namespace CeusMedia\DatabaseTest;

use CeusMedia\Database\SQLSTATE;

class SQLSTATETest extends \PHPUnit\Framework\TestCase
{
	public function testGetMeaning(): void
	{
		self::assertNull( SQLSTATE::getMeaning( 'invalid' ) );
		self::assertNotNull( SQLSTATE::getMeaning( 'S1098' ) );
		self::assertStringContainsString( 'scope type out of range', SQLSTATE::getMeaning( 'S1098' ) );
	}
}