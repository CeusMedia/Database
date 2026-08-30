<?php

namespace CeusMedia\DatabaseTest\PDO;

/**
 *	Minimal fetch target for PDO::FETCH_CLASS, used to verify that Reader
 *	decodes configured JSON columns on entities, not just on plain rows.
 *	The "label" member must accept the raw string PDO assigns before the
 *	constructor runs, as well as the decoded object Reader assigns afterwards.
 */
class JsonLabelEntity
{
	public string $id;

	public string $topic;

	public string|object|null $label = NULL;

	public string $timestamp;
}
