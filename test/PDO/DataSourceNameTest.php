<?php /** @noinspection ALL */

/**
 *	TestUnit of PDO Data Source Name builder.
 *	@package		Tests.database.pdo
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 */

namespace CeusMedia\DatabaseTest\PDO;

use CeusMedia\Database\PDO\DataSourceName;
use PHPUnit\Framework\TestCase as BaseTestCase;
use ReflectionClass;
use RuntimeException;

/**
 *	TestUnit of PDO Data Source Name builder.
 *
 *	Only the "mysql" driver is actually loaded in the test environment (see
 *	PDO::getAvailableDrivers()), so the string-building logic of the other
 *	drivers (pgsql/sqlite/firebird/informix/oci) - which is otherwise gated
 *	behind DataSourceName::checkDriverSupport()'s "is this PDO driver loaded"
 *	check, unrelated to the correctness of that logic - is exercised via a
 *	reflection helper that bypasses the constructor instead.
 *	@package		Tests.database.pdo
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 */
class DataSourceNameTest extends BaseTestCase
{
	public function testConstructWithSupportedAndLoadedDriver(): void
	{
		$dsn	= new DataSourceName( 'mysql', 'mydb' );
		self::assertEquals( 'mysql', $dsn->getDriver() );
	}

	public function testConstructLowercasesDriverName(): void
	{
		$dsn	= new DataSourceName( 'MySQL' );
		self::assertEquals( 'mysql', $dsn->getDriver() );
	}

	public function testConstructWithoutDatabaseDoesNotSetIt(): void
	{
		$dsn	= new DataSourceName( 'mysql' );
		self::assertEquals( 'mysql:', $dsn->render() );
	}

	public function testConstructThrowsExceptionOnUnsupportedDriver(): void
	{
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'PDO driver "not_a_real_driver" is not supported' );
		new DataSourceName( 'not_a_real_driver' );
	}

	public function testConstructThrowsExceptionOnNotLoadedDriver(): void
	{
		//	"sqlite" is supported (whitelisted) but not compiled into this PHP build
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'PDO driver "sqlite" is not loaded' );
		new DataSourceName( 'sqlite' );
	}

	public function testGetInstance(): void
	{
		$dsn	= DataSourceName::getInstance( 'mysql', 'mydb' );
		self::assertInstanceOf( DataSourceName::class, $dsn );
		self::assertEquals( 'mysql', $dsn->getDriver() );
	}

	public function testToString(): void
	{
		$dsn	= new DataSourceName( 'mysql', 'mydb' );
		$dsn->setHost( 'localhost' );
		self::assertEquals( (string) $dsn, $dsn->render() );
	}

	public function testRenderStatic(): void
	{
		$actual	= DataSourceName::renderStatic( 'mysql', 'mydb', 'localhost', 3306, 'user', 'pass' );
		self::assertEquals( 'mysql:host=localhost; port=3306; dbname=mydb', $actual );
	}

	public function testRenderForDefaultDriver(): void
	{
		$dsn	= new DataSourceName( 'mysql', 'mydb' );
		$dsn->setHost( 'localhost' )->setPort( 3306 );
		self::assertEquals( 'mysql:host=localhost; port=3306; dbname=mydb', $dsn->render() );
	}

	public function testRenderForDefaultDriverOmitsZeroPort(): void
	{
		$dsn	= new DataSourceName( 'mysql', 'mydb' );
		$dsn->setHost( 'localhost' )->setPort( 0 );
		self::assertEquals( 'mysql:host=localhost; dbname=mydb', $dsn->render() );
	}

	public function testRenderForDefaultDriverThrowsExceptionOnSemicolonInValue(): void
	{
		$dsn	= $this->createDsn( 'mysql', ['host' => 'local;host', 'database' => 'mydb'] );
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'DSN value "local;host" must not contain ";" or "="' );
		$dsn->render();
	}

	public function testRenderForDefaultDriverThrowsExceptionOnEqualsInValue(): void
	{
		$dsn	= $this->createDsn( 'mysql', ['host' => 'local=host', 'database' => 'mydb'] );
		$this->expectException( RuntimeException::class );
		$dsn->render();
	}

	public function testRenderForPgsql(): void
	{
		$dsn	= $this->createDsn( 'pgsql', [
			'host'		=> 'localhost',
			'port'		=> 5432,
			'database'	=> 'mydb',
			'username'	=> 'user',
			'password'	=> 'my pass',
		] );
		self::assertEquals( "pgsql:host=localhost port=5432 dbname=mydb user=user password='my pass'", $dsn->render() );
	}

	public function testRenderForPgsqlOmitsZeroPort(): void
	{
		$dsn	= $this->createDsn( 'pgsql', ['host' => 'localhost', 'port' => 0, 'database' => 'mydb'] );
		self::assertEquals( 'pgsql:host=localhost dbname=mydb', $dsn->render() );
	}

	public function testRenderForPgsqlQuotesEmptyValue(): void
	{
		$dsn	= $this->createDsn( 'pgsql', ['database' => 'mydb', 'password' => ''] );
		self::assertEquals( "pgsql:dbname=mydb password=''", $dsn->render() );
	}

	public function testRenderForPgsqlEscapesBackslashesAndQuotes(): void
	{
		$dsn	= $this->createDsn( 'pgsql', ['database' => 'mydb', 'password' => "back\\slash'quote"] );
		self::assertEquals( "pgsql:dbname=mydb password='back\\\\slash\\'quote'", $dsn->render() );
	}

	public function testRenderForFirebird(): void
	{
		$dsn	= $this->createDsn( 'firebird', [
			'host'		=> 'localhost',
			'port'		=> 3050,
			'database'	=> 'mydb',
			'username'	=> 'user',
			'password'	=> 'pass',
		] );
		self::assertEquals( 'firebird:DataSource=localhost; Port=3050; Database=mydb; User=user; Password=pass', $dsn->render() );
	}

	public function testRenderForInformix(): void
	{
		$dsn	= $this->createDsn( 'informix', ['host' => 'localhost', 'port' => 1526, 'database' => 'mydb'] );
		self::assertEquals( 'informix:host=localhost; service=1526; database=mydb', $dsn->render() );
	}

	public function testRenderForOciWithHost(): void
	{
		$dsn	= $this->createDsn( 'oci', ['host' => 'localhost', 'port' => 1521, 'database' => 'mydb'] );
		self::assertEquals( 'oci:dbname=//localhost:1521/mydb', $dsn->render() );
	}

	public function testRenderForOciWithoutHost(): void
	{
		$dsn	= $this->createDsn( 'oci', ['database' => 'mydb'] );
		self::assertEquals( 'oci:dbname=mydb', $dsn->render() );
	}

	public function testRenderForSqlite(): void
	{
		$dsn	= $this->createDsn( 'sqlite', ['database' => '/tmp/foo.db'] );
		self::assertEquals( 'sqlite:/tmp/foo.db', $dsn->render() );
	}

	public function testRenderForSqliteThrowsExceptionWithoutDatabase(): void
	{
		$dsn	= $this->createDsn( 'sqlite' );
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'No sqlite file set (using $database parameter or ::setDatabase)' );
		$dsn->render();
	}

	/**
	 *	The 'odbc' arm of render()'s match statement is commented out in the
	 *	source, so 'odbc' actually falls through to the default (generic)
	 *	renderer instead of the dedicated (not-yet-implemented) one below.
	 *	@access		public
	 *	@return		void
	 */
	public function testRenderForOdbcFallsThroughToDefaultRenderer(): void
	{
		$dsn	= $this->createDsn( 'odbc', ['host' => 'localhost', 'database' => 'mydb'] );
		self::assertEquals( 'odbc:host=localhost; dbname=mydb', $dsn->render() );
	}

	public function testRenderDsnForOdbcIsNotYetImplemented(): void
	{
		$dsn		= $this->createDsn( 'odbc' );
		$reflection	= new \ReflectionMethod( $dsn, 'renderDsnForOdbc' );
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Not yet implemented' );
		$reflection->invoke( $dsn );
	}

	public function testSetDatabaseThrowsExceptionOnNulByte(): void
	{
		$dsn	= new DataSourceName( 'mysql' );
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'DSN value "database" must not contain a NUL byte' );
		$dsn->setDatabase( "bad\0name" );
	}

	public function testSetHostThrowsExceptionOnNulByte(): void
	{
		$dsn	= new DataSourceName( 'mysql' );
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'DSN value "host" must not contain a NUL byte' );
		$dsn->setHost( "bad\0host" );
	}

	public function testSetUsernameThrowsExceptionOnNulByte(): void
	{
		$dsn	= new DataSourceName( 'mysql' );
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'DSN value "username" must not contain a NUL byte' );
		$dsn->setUsername( "bad\0user" );
	}

	public function testSetPasswordThrowsExceptionOnNulByte(): void
	{
		$dsn	= new DataSourceName( 'mysql' );
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'DSN value "password" must not contain a NUL byte' );
		$dsn->setPassword( "bad\0pass" );
	}

	//  --  PROTECTED  --  //

	/**
	 *	Creates a DataSourceName instance bypassing the constructor's "is this
	 *	PDO driver loaded" check, so drivers not compiled into this PHP build
	 *	can still be used to exercise their (environment-independent) string-
	 *	building logic.
	 *	@access		protected
	 *	@param		string		$driver
	 *	@param		array		$properties		Map of protected property name to value (host, port, database, username, password)
	 *	@return		DataSourceName
	 */
	protected function createDsn( string $driver, array $properties = [] ): DataSourceName
	{
		$reflection	= new ReflectionClass( DataSourceName::class );
		/** @var DataSourceName $dsn */
		$dsn		= $reflection->newInstanceWithoutConstructor();
		$reflection->getProperty( 'driver' )->setValue( $dsn, $driver );
		foreach( $properties as $name => $value )
			$reflection->getProperty( $name )->setValue( $dsn, $value );
		return $dsn;
	}
}
