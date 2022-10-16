<?php
namespace CeusMedia\Database\OSQL\Condition;

use CeusMedia\Database\OSQL\Condition;
use InvalidArgumentException;

class Group
{
	public const OPERATION_AND		= 'AND';
	public const OPERATION_OR		= 'OR';

	public const OPERATIONS			= [
		self::OPERATION_AND,
		self::OPERATION_OR,
	];

	protected string $operation;

	/** @var Condition[] $conditions */
	protected array $conditions		= [];

	public function __construct( string $operation, array $conditions = [] )
	{
		$this->setOperation( $operation );
		foreach( $conditions as $condition )
			$this->add( $condition );
	}

	public function add( Condition $condition ): self
	{
		$this->conditions[]	= $condition;
		return $this;
	}

	public function setOperation( string $operation ): self
	{
		$operation	= strtoupper( $operation );
		if( !in_array( $operation, self::OPERATIONS, TRUE ) )
			throw new InvalidArgumentException( 'Operation must be "AND" or "OR"' );
		$this->operation	= $operation;
		return $this;
	}

	public function render( array & $parameters ): string
	{
		$list	= [];
		foreach( $this->conditions as $condition ){
			$string	= $condition->render( $parameters );
			$list[]	= ( $condition instanceof Group ) ? '('.$string.')' : $string;
		}
		return implode( ' '.$this->operation.' ', $list );
	}
}
