<?php
/** @noinspection PhpComposerExtensionStubsInspection */
declare(strict_types=1);

namespace CeusMedia\DatabaseTest\PDO;

use CeusMedia\DatabaseTest\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
	/**
	 *	Setup for every Test.
	 *	@access		protected
	 *	@return		void
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->setUpPdoConnection();
	}

	/**
	 *	Cleanup after every Test.
	 *	@access		protected
	 *	@return		void
	 */
	protected function tearDown(): void
	{
		$this->tearDownPdoConnection();
		parent::tearDown();
	}
}
