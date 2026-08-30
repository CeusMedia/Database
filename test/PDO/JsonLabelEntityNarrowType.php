<?php

namespace CeusMedia\DatabaseTest\PDO;

/**
 *	Deliberately mistyped fetch target for PDO::FETCH_CLASS: "label" only accepts
 *	string, so assigning the JSON-decoded object back onto it must fail with a
 *	clear, actionable error instead of a raw TypeError.
 */
class JsonLabelEntityNarrowType
{
	public string $id;

	public string $topic;

	public string $label;

	public string $timestamp;
}
