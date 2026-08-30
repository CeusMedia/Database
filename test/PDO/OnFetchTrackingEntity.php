<?php

namespace CeusMedia\DatabaseTest\PDO;

/**
 *	Fetch target implementing the optional "onFetch" hook, to prove Reader
 *	calls it after both FETCH_CLASS and FETCH_INTO fetches.
 */
class OnFetchTrackingEntity
{
	public string $id;

	public string $topic;

	public string $label;

	public string $timestamp;

	public bool $onFetchCalled = FALSE;

	public function onFetch( mixed $reader, mixed $entity ): void
	{
		$this->onFetchCalled = TRUE;
	}
}
