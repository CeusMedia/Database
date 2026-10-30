<?php
/** @noinspection PhpMultipleClassDeclarationsInspection */

/**
 *	Group of conditions combined by AND or OR, as part of OSQL queries.
 *
 *	Copyright (c) 2010-2026 Christian Würker (ceusmedia.de)
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
 *	along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 *	@category		Library
 *	@package		CeusMedia_Database_OSQL_Condition
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2026 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */

namespace CeusMedia\Database\OSQL\Condition;

use CeusMedia\Database\OSQL\Condition;
use InvalidArgumentException;

/**
 *	Group of conditions combined by AND or OR, as part of OSQL queries.
 *	@category		Library
 *	@package		CeusMedia_Database_OSQL_Condition
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2026 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
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

	/** @var array<Condition|Group> $conditions */
	protected array $conditions		= [];

	/**
	 *	Constructor.
	 *	@param		string					$operation		Operation to combine conditions by: AND or OR
	 *	@param		array<Condition|Group>	$conditions		Conditions or nested groups
	 *	@throws		InvalidArgumentException	if operation is not AND or OR
	 */
	public function __construct( string $operation = self::OPERATION_AND, array $conditions = [] )
	{
		$operation	= strtoupper( trim( $operation ) );
		if( !in_array( $operation, self::OPERATIONS, TRUE ) )
			throw new InvalidArgumentException( 'Operation must be "AND" or "OR"' );
		$this->operation	= $operation;
		foreach( $conditions as $condition )
			$this->add( $condition );
	}

	public function __clone()
	{
		foreach( $this->conditions as $nr => $condition )
			if( $condition instanceof self )
				$this->conditions[$nr]	= clone $condition;
	}

	/**
	 *	Returns new group of conditions, which all must match.
	 *	@param		Condition|Group		...$conditions
	 *	@return		self
	 */
	public static function and( Condition|Group ...$conditions ): self
	{
		return new self( self::OPERATION_AND, $conditions );
	}

	/**
	 *	Returns new group of conditions, of which one must match.
	 *	@param		Condition|Group		...$conditions
	 *	@return		self
	 */
	public static function or( Condition|Group ...$conditions ): self
	{
		return new self( self::OPERATION_OR, $conditions );
	}

	public function add( Condition|Group $condition ): self
	{
		$this->conditions[]	= $condition;
		return $this;
	}

	public function count(): int
	{
		return count( $this->conditions );
	}

	/**
	 *	@return		array<Condition|Group>
	 */
	public function getConditions(): array
	{
		return $this->conditions;
	}

	public function getOperation(): string
	{
		return $this->operation;
	}

	/**
	 *	Renders conditions with placeholders, nested groups in brackets, and appends values to given parameter list.
	 *	An empty group renders as always true (AND) or always false (OR).
	 *	@param		array<int,string|int|float|NULL>	$parameters		List of parameters to append values to
	 *	@return		string
	 */
	public function render( array &$parameters ): string
	{
		if( [] === $this->conditions )
			return self::OPERATION_AND === $this->operation ? '1 = 1' : '1 = 0';
		$list	= [];
		foreach( $this->conditions as $condition ){
			$string	= $condition->render( $parameters );
			$list[]	= $condition instanceof self ? '('.$string.')' : $string;
		}
		return implode( ' '.$this->operation.' ', $list );
	}
}
