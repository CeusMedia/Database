<?php

namespace CeusMedia\DatabaseTest\PDO;

/**
 *	Fetch target for PDO::FETCH_INTO with a deliberately mistyped property:
 *	"topic" only accepts int, but the "transactions" table's topic values
 *	(eg. "test", "start") are non-numeric strings, so PDO's own assignment
 *	fails with a TypeError - to exercise
 *	Reader::applyFetchModeIntoOnResultSet()'s error handling.
 */
class NarrowIntoEntity
{
	public string $id;

	public int $topic;

	public string $label;

	public string $timestamp;
}
