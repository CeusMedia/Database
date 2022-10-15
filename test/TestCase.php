<?php
declare(strict_types=1);

namespace CeusMedia\DatabaseTest;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
	public static array $config;

	public static string $pathLib;
}
