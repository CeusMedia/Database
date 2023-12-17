<?php

/**
 * 	A condition group will allow nested conditions, like <code>OR</code> and <code>AND</code>, grouped logically.
 *
 *	Copyright (c) 2010-2023 Christian Würker (ceusmedia.de)
 *
 *	This program is free software: you can redistribute it and/or modify
 *	it under the terms of the GNU General Public License as published by
 *	the Free Software Foundation, either version 3 of the License, or
 *	(at your option) any later version.
 *
 *	This program is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU General Public License for more details.
 *
 *	You should have received a copy of the GNU General Public License
 *	along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *	@category		Library
 *	@package		CeusMedia_Database_OSQL_Condition
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2023 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */

namespace CeusMedia\Database\OSQL\Condition;

use CeusMedia\Database\OSQL\Condition;
use InvalidArgumentException;

/**
 *	A condition group will allow nested conditions, like <code>OR</code> and <code>AND</code>, grouped logically.
 *
 *	@category		Library
 *	@package		CeusMedia_Database_OSQL_Condition
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2023 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
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
