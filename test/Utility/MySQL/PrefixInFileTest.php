<?php
declare(strict_types=1);

/**
 *	TestUnit of replacing table prefixes by a placeholder in SQL dump files (Utility\MySQL\Export and Import).
 *	@package		Tests.database.utility.mysql
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 */

namespace CeusMedia\DatabaseTest\Utility\MySQL;

use CeusMedia\Database\PDO\DataSourceName;
use CeusMedia\Database\Utility\MySQL\Export;
use CeusMedia\Database\Utility\MySQL\Import;
use CeusMedia\DatabaseTest\TestCase;

/**
 *	TestUnit of replacing table prefixes by a placeholder in SQL dump files (Utility\MySQL\Export and Import).
 *	@package		Tests.database.utility.mysql
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 */
class PrefixInFileTest extends TestCase
{
	protected const DUMP	= <<<'SQL'
-- Table structure for table `shop_orders`
DROP TABLE IF EXISTS `shop_orders`;
CREATE TABLE `shop_orders` (
  `orderId` int NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`orderId`)
);
-- Dumping data for table `shop_orders`
LOCK TABLES `shop_orders` WRITE;
INSERT INTO `shop_orders` VALUES (1);
UNLOCK TABLES;
SELECT * FROM `shop_orders`;
SQL;

	protected const EXPECTED	= <<<'SQL'
-- Table structure for table `orders`
DROP TABLE IF EXISTS `<%?prefix%>orders`;
CREATE TABLE `<%?prefix%>orders` (
  `orderId` int NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`orderId`)
);
-- Dumping data for table `orders`
LOCK TABLES `<%?prefix%>orders` WRITE;
INSERT INTO `<%?prefix%>orders` VALUES (1);
UNLOCK TABLES;
SELECT * FROM `<%?prefix%>orders`;
SQL;

	protected DataSourceName $dataSourceName;

	protected string $fileName;

	public function testExportInsertPrefixInFile(): void
	{
		file_put_contents( $this->fileName, self::DUMP );
		( new Export( $this->dataSourceName ) )->insertPrefixInFile( $this->fileName, 'shop_' );
		self::assertSame( self::EXPECTED, file_get_contents( $this->fileName ), 'last line without line break is kept' );
		self::assertFileDoesNotExist( $this->fileName.'_', 'temporary source file is removed' );
	}

	public function testExportInsertPrefixInFileWithTrailingLineBreak(): void
	{
		file_put_contents( $this->fileName, self::DUMP.PHP_EOL );
		( new Export( $this->dataSourceName ) )->insertPrefixInFile( $this->fileName, 'shop_' );
		self::assertSame( self::EXPECTED.PHP_EOL, file_get_contents( $this->fileName ) );
	}

	public function testExportInsertPrefixInFileWithOwnPlaceholder(): void
	{
		file_put_contents( $this->fileName, "INSERT INTO `shop_orders` VALUES (1);" );
		$export	= new Export( $this->dataSourceName );
		$export->setPrefixPlaceholder( '{{prefix}}' );
		$export->insertPrefixInFile( $this->fileName, 'shop_' );
		self::assertSame( "INSERT INTO `{{prefix}}orders` VALUES (1);", file_get_contents( $this->fileName ) );
	}

	public function testExportInsertPrefixInEmptyFile(): void
	{
		file_put_contents( $this->fileName, '' );
		( new Export( $this->dataSourceName ) )->insertPrefixInFile( $this->fileName, 'shop_' );
		self::assertSame( '', file_get_contents( $this->fileName ) );
	}

	public function testImportInsertPrefixInFile(): void
	{
		file_put_contents( $this->fileName, self::DUMP );
		( new Import( $this->dataSourceName ) )->insertPrefixInFile( $this->fileName, 'shop_' );
		self::assertSame( self::EXPECTED, file_get_contents( $this->fileName ) );
	}

	protected function setUp(): void
	{
		parent::setUp();
		if( !extension_loaded( 'pdo_mysql' ) )
			self::markTestSkipped( 'PDO driver for MySQL not supported' );
		$this->dataSourceName	= DataSourceName::getInstance( 'mysql', $this->database )
			->setHost( $this->host )
			->setPort( (int) $this->port )
			->setUsername( $this->username )
			->setPassword( $this->password );
		$this->fileName	= sys_get_temp_dir().'/ceusmedia-database-test-'.getmypid().'.sql';
	}

	protected function tearDown(): void
	{
		@unlink( $this->fileName );
		@unlink( $this->fileName.'_' );
		parent::tearDown();
	}
}
