<?php

namespace CeusMedia\DatabaseTest\PDO;

/**
 *	Deliberately mistyped fetch target for PDO::FETCH_CLASS: "preciseAt" only
 *	accepts string, so assigning the decoded DateTime back onto it must fail
 *	with a clear, actionable error instead of a raw TypeError.
 */
class DateTimeColumnEntityNarrowType
{
	public string $id;

	public string $topic;

	public string $preciseAt;

	public ?string $plainAt = NULL;
}
