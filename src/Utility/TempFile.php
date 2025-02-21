<?php
declare(strict_types=1);

namespace CeusMedia\Database\Utility;

use RuntimeException;

class TempFile
{
	protected string $prefix;

	protected ?string $filePath		= NULL;

	public static function getInstance( string $prefix = '' ): self
	{
		$object	= new self( $prefix );
		return $object->create();
	}

	public function __construct( string $prefix = '' )
	{
		$this->prefix	= $prefix;
	}

	public function __destruct()
	{
		$this->destroy();
	}

	public function create(): self
	{
		$filePath	= tempnam( sys_get_temp_dir(), $this->prefix );
		if( FALSE === $filePath )
			throw new RuntimeException( 'Create a temporary file failed' );
		$this->filePath = $filePath;
		return $this;
	}

	public function destroy(): bool
	{
		if( NULL !== $this->filePath ){
			unlink( $this->getFilePath() );
			$this->filePath	= NULL;
			return TRUE;
		}
		return FALSE;
	}

	public function getFilePath(): string
	{
		if( NULL === $this->filePath )
			throw new RuntimeException( 'No temp file created, yet' );
		return $this->filePath;
	}

	public function setPrefix( string $prefix ): self
	{
		$this->prefix	= $prefix;
		return $this;
	}
}
