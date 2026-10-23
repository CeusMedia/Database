<?php
declare(strict_types=1);

namespace CeusMedia\DatabaseTest\PDO;

/**
 *	TestUnit of PDO Table with generated (virtual) columns.
 *	@package		Tests.database.pdo
 */
class GeneratedColumnTableTest extends TestCase
{
	protected GeneratedColumnTable $table;

	public function testGeneratedColumnIsReadable(): void
	{
		$id	= $this->table->add( ['content' => 'abc'] );
		self::assertEquals( 3, $this->table->get( $id, 'contentLength' ) );
		self::assertSame( 1, $this->table->count( ['contentLength' => 3] ) );
	}

	/**
	 *	Generated columns are never written, even if given in data, like from a fetched entry.
	 *	@access		public
	 *	@return		void
	 */
	public function testGeneratedColumnIsNotWritten(): void
	{
		$id	= $this->table->add( ['content' => 'abc', 'contentLength' => 99] );
		self::assertEquals( 3, $this->table->get( $id, 'contentLength' ) );

		$entry	= $this->table->get( $id );
		$entry->content	= 'abcdef';
		self::assertSame( 1, $this->table->edit( $id, $entry ) );
		self::assertEquals( 6, $this->table->get( $id, 'contentLength' ) );

		self::assertSame( 1, $this->table->editByIndices( ['id' => $id], ['content' => 'a', 'contentLength' => 99] ) );
		self::assertEquals( 1, $this->table->get( $id, 'contentLength' ) );
	}

	protected function setUp(): void
	{
		parent::setUp();
		$this->connection->exec( 'DROP TABLE IF EXISTS generated_columns_test' );
		$this->connection->exec(
			'CREATE TABLE generated_columns_test ('
			.'id INT PRIMARY KEY AUTO_INCREMENT, '
			.'content VARCHAR(20) NOT NULL, '
			.'contentLength INT AS (CHAR_LENGTH(content)) VIRTUAL'
			.')'
		);
		$this->table	= new GeneratedColumnTable( $this->connection );
	}

	protected function tearDown(): void
	{
		$this->connection->exec( 'DROP TABLE IF EXISTS generated_columns_test' );
		parent::tearDown();
	}
}
