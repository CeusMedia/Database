<?php
/** @noinspection PhpMultipleClassDeclarationsInspection */

/**
 *	Conditions (WHERE) for OSQL queries.
 *
 *	Copyright (c) 2026-2026 Christian Würker (ceusmedia.de)
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
 *	@package		CeusMedia_Database_OSQL_Query
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2026-2026 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */

namespace CeusMedia\Database\OSQL\Query;

use CeusMedia\Database\OSQL\Condition;
use CeusMedia\Database\OSQL\Condition\Group;
use InvalidArgumentException;

/**
 *	Conditions (WHERE) for OSQL queries.
 *	@category		Library
 *	@package		CeusMedia_Database_OSQL_Query
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2026-2026 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
trait ConditionsTrait
{
	protected ?Group $conditions		= NULL;

	/**
	 *	Adds condition, combined by AND with all conditions before.
	 *	Same as where().
	 *	@param		Condition|Group|string	$field		Condition, group of conditions or field to create condition for
	 *	@param		mixed					$value		Value, if field is given
	 *	@param		string					$operator	Operator, if field is given, default: "="
	 *	@return		static
	 */
	public function and( Condition|Group|string $field, mixed $value = NULL, string $operator = Condition::OP_EQ ): static
	{
		$condition			= self::resolveCondition( func_get_args() );
		$this->conditions	= self::combineConditions( $this->conditions, Group::OPERATION_AND, $condition );
		return $this;
	}

	/**
	 *	Adds condition, combined by OR with all conditions before.
	 *	Conditions are combined from left to right: where( a )->and( b )->or( c ) is (a AND b) OR c.
	 *	@param		Condition|Group|string	$field		Condition, group of conditions or field to create condition for
	 *	@param		mixed					$value		Value, if field is given
	 *	@param		string					$operator	Operator, if field is given, default: "="
	 *	@return		static
	 */
	public function or( Condition|Group|string $field, mixed $value = NULL, string $operator = Condition::OP_EQ ): static
	{
		$condition			= self::resolveCondition( func_get_args() );
		$this->conditions	= self::combineConditions( $this->conditions, Group::OPERATION_OR, $condition );
		return $this;
	}

	/**
	 *	Adds condition, combined by AND with all conditions before.
	 *	Examples: where( 'status', 1 ), where( 'total', 100, Condition::OP_GT ), where( 'id', [1, 2] ) for IN,
	 *	where( 'deletedAt', NULL ) for IS NULL, where( Condition::between( 'total', 10, 20 ) ).
	 *	@param		Condition|Group|string	$field		Condition, group of conditions or field to create condition for
	 *	@param		mixed					$value		Value, if field is given
	 *	@param		string					$operator	Operator, if field is given, default: "="
	 *	@return		static
	 */
	public function where( Condition|Group|string $field, mixed $value = NULL, string $operator = Condition::OP_EQ ): static
	{
		$condition			= self::resolveCondition( func_get_args() );
		$this->conditions	= self::combineConditions( $this->conditions, Group::OPERATION_AND, $condition );
		return $this;
	}

	protected function cloneConditions(): void
	{
		if( NULL !== $this->conditions )
			$this->conditions	= clone $this->conditions;
	}

	/**
	 *	@param		Group|NULL			$current
	 *	@param		string				$operation
	 *	@param		Condition|Group		$condition
	 *	@return		Group
	 */
	protected static function combineConditions( ?Group $current, string $operation, Condition|Group $condition ): Group
	{
		if( NULL === $current )
			return new Group( $operation, [$condition] );
		if( $current->getOperation() === $operation || 1 === $current->count() )
			return ( new Group( $operation, $current->getConditions() ) )->add( $condition );
		return new Group( $operation, [$current, $condition] );
	}

	/**
	 *	@param		Group|NULL							$conditions
	 *	@param		string								$keyword		Keyword to start with, eg. " WHERE "
	 *	@param		array<int,string|int|float|NULL>	$parameters
	 *	@return		string
	 */
	protected static function renderConditionGroup( ?Group $conditions, string $keyword, array &$parameters ): string
	{
		if( NULL === $conditions )
			return '';
		return $keyword.$conditions->render( $parameters );
	}

	/**
	 *	@param		array<int,mixed>	$arguments		Arguments given to where(), and(), or() or having()
	 *	@return		Condition|Group
	 */
	protected static function resolveCondition( array $arguments ): Condition|Group
	{
		$field	= array_shift( $arguments );
		if( $field instanceof Condition || $field instanceof Group ){
			if( [] !== $arguments )
				throw new InvalidArgumentException( 'Value and operator are not allowed beside a condition object' );
			return $field;
		}
		if( !is_string( $field ) )
			throw new InvalidArgumentException( 'Field must be a condition, a group or a string' );
		if( [] === $arguments )
			throw new InvalidArgumentException( 'Value missing for condition on "'.$field.'"' );
		$operator	= $arguments[1] ?? Condition::OP_EQ;
		if( !is_string( $operator ) )
			throw new InvalidArgumentException( 'Operator must be a string' );
		return new Condition( $field, $arguments[0], $operator );
	}
}
