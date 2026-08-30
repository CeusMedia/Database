<?php

namespace CeusMedia\DatabaseTest\PDO;

/**
 *	Fetch target for PDO::FETCH_CLASS whose constructor always fails, to
 *	exercise Reader::applyFetchModeClassOnResultSet()'s error handling.
 */
class ThrowingConstructorEntity
{
	public string $id;

	public string $topic;

	public string $label;

	public string $timestamp;

	public function __construct()
	{
		throw new \RuntimeException( 'constructor always fails' );
	}
}
