<?php
declare(strict_types=1);

namespace CeusMedia\DatabaseTest\PDO;

/**
 *	Fixture for Table\Abstraction::validateColumns(), which adds mandatory fields of
 *	an entity class to a selected column list, so fetched entities are always valid.
 */
class MandatoryFieldsEntity
{
	public static array $mandatoryFields	= ['id', 'topic'];

	public ?int $id			= NULL;

	public ?string $topic	= NULL;

	public ?string $label	= NULL;
}
