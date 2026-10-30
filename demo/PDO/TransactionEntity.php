<?php

namespace CeusMedia\DatabaseDemo\PDO;

/**
 *	Demo entity class for rows of table "transactions".
 */
class TransactionEntity
{
	public int $id;

	public string $topic;

	public string $label;

	public string $timestamp;

	public function getAge(): string
	{
		return ( new \DateTimeImmutable( $this->timestamp ) )->diff( new \DateTimeImmutable() )->format( '%y years, %m months' );
	}
}
