<?php

namespace CeusMedia\DatabaseTest\PDO;

use CeusMedia\Database\PDO\Entity;

class AdvancedTransactionEntity extends Entity
{
	public string $id;

	public string $topic;

	public string $label;

	public string $timestamp;
}