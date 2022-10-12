<?php
namespace CeusMedia\Database\OSQL\Condition;

use CeusMedia\Database\OSQL\Condition;

class Group
{
	const OPERATION_AND		= 'AND';
	const OPERATION_OR		= 'OR';

	protected $operation;
	protected $conditions	= [];

	public function __construct( string $operation, array $conditions = [] ){
		$this->setOperation( $operation );
		foreach( $conditions as $condition )
			$this->add( $condition );
	}

	public function add( $condition ): self
	{
		$this->conditions[]	= $condition;
		return $this;
	}

	public function setOperation( string $operation ): self
	{
		if( !in_array( strtoupper( $operation ), ['AND', 'OR'] ) )
			throw new \InvalidArgumentException( 'Operation must be "AND" or "OR"' );
		$this->operation	= strtoupper( $operation );
		return $this;
	}

	public function render( & $parameters )
	{
		$list	= [];
		foreach( $this->conditions as $condition ){
			$string	= $condition->render( $parameters );
			$list[]	= ( $condition instanceof Group ) ? '('.$string.')' : $string;
		}
		return implode( ' '.$this->operation.' ', $list );
	}
}
