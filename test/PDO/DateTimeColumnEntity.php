<?php

namespace CeusMedia\DatabaseTest\PDO;

use DateTime;

/**
 *	Minimal fetch target for PDO::FETCH_CLASS, used to verify that Reader
 *	decodes configured DateTime columns on entities, not just on plain rows.
 *	Members must accept the raw string PDO assigns before the constructor
 *	runs, as well as the decoded DateTime Reader assigns afterward.
 */
class DateTimeColumnEntity
{
	public string $id;

	public string $topic;

	public string|DateTime|null $preciseAt = NULL;

	public string|DateTime|null $plainAt = NULL;
}
