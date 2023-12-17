<?php /** @noinspection ALL */
/** @noinspection SqlResolve */
/** @noinspection SqlNoDataSourceInspection */

/**
 *	TestUnit of DB_PDO_Connection.
 *	@package		Tests.database.pdo
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 */

namespace CeusMedia\DatabaseTest\PDO;

use CeusMedia\Common\Exception\SQL as SqlException;
use CeusMedia\Database\PDO\Connection\Factory;
use CeusMedia\DatabaseTest\TestCase;
use Exception;
use PDOStatement;

/**
 *	TestUnit of DB_PDO_Connection.
 *	@package		Tests.database.pdo
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 */
class FactoryTest extends TestCase
{
	protected array $columns;
	protected string $tableName;
	protected array $indices;
	protected string $primaryKey;

	/**
	 *	Tests method 'create'.
	 *	@access		public
	 *	@return		void
	 */
	public function testCreate()
	{
		$dsn		= "mysql:host=".$this->host.";dbname=".$this->database;
		$factory	= new Factory( $dsn, $this->username, $this->password, $this->options );
		$connection	= $factory->create();

		self::assertInstanceOf( \PDO::class, $connection );
	}

	/**
	 *	Tests static method 'createByPhpVersion'.
	 *	@access		public
	 *	@return		void
	 */
	public function testCreateByPhpVersion()
	{
		$dsn		= "mysql:host=".$this->host.";dbname=".$this->database;
		$connection	= Factory::createByPhpVersion( $dsn, $this->username, $this->password, $this->options );
		self::assertInstanceOf( \PDO::class, $connection );
	}

	/**
	 *	Tests static method 'createByPhpVersion'.
	 *	@access		public
	 *	@return		void
	 */
	public function testSetStrategyRangeException()
	{
		$this->expectException( \RangeException::class );
		$dsn		= "mysql:host=".$this->host.";dbname=".$this->database;
		$factory	= new Factory( $dsn, $this->username, $this->password, $this->options );
		$factory->setStrategy( 6 );
	}
}
