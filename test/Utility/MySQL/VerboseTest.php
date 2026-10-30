<?php
declare(strict_types=1);

/**
 *	TestUnit of switching progress messages of Utility\MySQL\Export and Import.
 *	@package		Tests.database.utility.mysql
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 */

namespace CeusMedia\DatabaseTest\Utility\MySQL;

use CeusMedia\DatabaseTest\TestCase;

/**
 *	TestUnit of switching progress messages of Utility\MySQL\Export and Import.
 *	Messages are written directly to STDOUT on CLI, so each case runs in a separate PHP process.
 *	@package		Tests.database.utility.mysql
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 */
class VerboseTest extends TestCase
{
	protected string $fileName;

	public function testExportIsVerboseByDefault(): void
	{
		$this->skipIfCommandIsMissing( 'mysqldump' );
		$output	= $this->runInProcess( '( new Export( $dsn ) )->exportToFile( $file, "transactions" );' );
		self::assertStringContainsString( 'table(s):', $output );
		self::assertStringContainsString( 'Wrote '.$this->fileName, $output );
	}

	public function testExportWithoutVerbose(): void
	{
		$this->skipIfCommandIsMissing( 'mysqldump' );
		$output	= $this->runInProcess( '( new Export( $dsn ) )->setVerbose( FALSE )->exportToFile( $file, "transactions" );' );
		self::assertSame( '', $output );
		self::assertFileExists( $this->fileName, 'export has been done' );
	}

	public function testImportIsVerboseByDefault(): void
	{
		$this->skipIfCommandIsMissing( 'mysql' );
		file_put_contents( $this->fileName, 'SELECT 1;' );
		$output	= $this->runInProcess( '( new Import( $dsn ) )->importFileWithPrefix( $file, "test_" );' );
		self::assertStringContainsString( 'Applying table prefix', $output );
		self::assertStringContainsString( 'Loading ', $output );
	}

	public function testImportWithoutVerbose(): void
	{
		$this->skipIfCommandIsMissing( 'mysql' );
		file_put_contents( $this->fileName, 'SELECT 1;' );
		$output	= $this->runInProcess( '$result = ( new Import( $dsn ) )->setVerbose( FALSE )->importFileWithPrefix( $file, "test_" ); exit( $result->code );' );
		self::assertSame( '', $output );
	}

	protected function setUp(): void
	{
		parent::setUp();
		$this->fileName	= sys_get_temp_dir().'/ceusmedia-database-test-verbose-'.getmypid().'.sql';
	}

	protected function tearDown(): void
	{
		@unlink( $this->fileName );
		@unlink( $this->fileName.'.tmp' );
		parent::tearDown();
	}

	/**
	 *	Runs given code in a separate PHP process, with $dsn and $file prepared, and returns its output.
	 *	@param		string		$code
	 *	@return		string
	 */
	protected function runInProcess( string $code ): string
	{
		$script	= vsprintf( '
			require %s;
			use CeusMedia\Database\PDO\DataSourceName;
			use CeusMedia\Database\Utility\MySQL\Export;
			use CeusMedia\Database\Utility\MySQL\Import;
			$dsn	= DataSourceName::getInstance( "mysql", %s )->setHost( %s )->setPort( %d )->setUsername( %s )->setPassword( %s );
			$file	= %s;
			%s', [
			var_export( dirname( __DIR__, 3 ).'/vendor/autoload.php', TRUE ),
			var_export( $this->database, TRUE ),
			var_export( $this->host, TRUE ),
			(int) $this->port,
			var_export( $this->username, TRUE ),
			var_export( $this->password, TRUE ),
			var_export( $this->fileName, TRUE ),
			$code,
		] );
		exec( escapeshellarg( PHP_BINARY ).' -r '.escapeshellarg( $script ).' 2>&1', $lines, $code );
		$output	= implode( PHP_EOL, $lines );
		self::assertSame( 0, $code, 'process failed: '.$output );
		return $output;
	}

	protected function skipIfCommandIsMissing( string $command ): void
	{
		exec( 'command -v '.escapeshellarg( $command ), $lines, $code );
		if( 0 !== $code )
			self::markTestSkipped( 'Command "'.$command.'" is not available' );
	}
}
