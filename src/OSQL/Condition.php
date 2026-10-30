<?php
/** @noinspection PhpMultipleClassDeclarationsInspection */

/**
 *	Condition of a column (or aggregate function) and a value, as part of OSQL queries.
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
 *	@package		CeusMedia_Database_OSQL
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2026 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */

namespace CeusMedia\Database\OSQL;

use InvalidArgumentException;

/**
 *	Condition of a column (or aggregate function) and a value, as part of OSQL queries.
 *	Create by constructor or by the static factory methods, eg. Condition::gt( 'total', 100 ).
 *	@category		Library
 *	@package		CeusMedia_Database_OSQL
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2026 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
class Condition
{
	public const OP_EQ				= '=';
	public const OP_NEQ				= '!=';
	public const OP_GT				= '>';
	public const OP_GTE				= '>=';
	public const OP_LT				= '<';
	public const OP_LTE				= '<=';
	public const OP_LIKE			= 'LIKE';
	public const OP_NOT_LIKE		= 'NOT LIKE';
	public const OP_IN				= 'IN';
	public const OP_NOT_IN			= 'NOT IN';
	public const OP_BETWEEN			= 'BETWEEN';
	public const OP_NOT_BETWEEN		= 'NOT BETWEEN';
	public const OP_IS_NULL			= 'IS NULL';
	public const OP_IS_NOT_NULL		= 'IS NOT NULL';

	public const OPERATORS			= [
		self::OP_EQ,
		self::OP_NEQ,
		self::OP_GT,
		self::OP_GTE,
		self::OP_LT,
		self::OP_LTE,
		self::OP_LIKE,
		self::OP_NOT_LIKE,
		self::OP_IN,
		self::OP_NOT_IN,
		self::OP_BETWEEN,
		self::OP_NOT_BETWEEN,
		self::OP_IS_NULL,
		self::OP_IS_NOT_NULL,
	];

	protected string $field;

	protected string $operator;

	/** @var string|int|float|array<int,string|int|float|NULL>|NULL */
	protected string|int|float|array|NULL $value;

	/**
	 *	Constructor.
	 *	A list of values turns "=" into "IN" and "!=" into "NOT IN".
	 *	NULL turns "=" into "IS NULL" and "!=" into "IS NOT NULL".
	 *	@param		string		$field		Column, qualified column (eg. "o.total") or aggregate function (eg. "COUNT(*)")
	 *	@param		mixed		$value		Value, list of values for IN and BETWEEN
	 *	@param		string		$operator	Operator, see constants OP_*, default: "="
	 *	@throws		InvalidArgumentException	if field, operator or value is not valid
	 */
	public function __construct( string $field, mixed $value = NULL, string $operator = self::OP_EQ )
	{
		Identifier::quoteExpression( $field );
		$operator	= strtoupper( (string) preg_replace( '/\s+/', ' ', trim( $operator ) ) );
		if( '<>' === $operator )
			$operator	= self::OP_NEQ;
		if( !in_array( $operator, self::OPERATORS, TRUE ) )
			throw new InvalidArgumentException( 'Invalid operator "'.$operator.'"' );

		if( is_array( $value ) && in_array( $operator, [self::OP_EQ, self::OP_NEQ], TRUE ) )
			$operator	= self::OP_EQ === $operator ? self::OP_IN : self::OP_NOT_IN;
		else if( NULL === $value && in_array( $operator, [self::OP_EQ, self::OP_NEQ], TRUE ) )
			$operator	= self::OP_EQ === $operator ? self::OP_IS_NULL : self::OP_IS_NOT_NULL;

		$this->field	= trim( $field );
		$this->operator	= $operator;
		$this->value	= self::validateValue( $operator, $value );
	}

	public static function between( string $field, mixed $from, mixed $to ): self
	{
		return new self( $field, [$from, $to], self::OP_BETWEEN );
	}

	public static function eq( string $field, mixed $value ): self
	{
		return new self( $field, $value, self::OP_EQ );
	}

	public static function gt( string $field, mixed $value ): self
	{
		return new self( $field, $value, self::OP_GT );
	}

	public static function gte( string $field, mixed $value ): self
	{
		return new self( $field, $value, self::OP_GTE );
	}

	/**
	 *	@param		string			$field
	 *	@param		array<mixed>	$values
	 *	@return		self
	 */
	public static function in( string $field, array $values ): self
	{
		return new self( $field, $values, self::OP_IN );
	}

	public static function isNotNull( string $field ): self
	{
		return new self( $field, NULL, self::OP_IS_NOT_NULL );
	}

	public static function isNull( string $field ): self
	{
		return new self( $field, NULL, self::OP_IS_NULL );
	}

	public static function like( string $field, string $pattern ): self
	{
		return new self( $field, $pattern, self::OP_LIKE );
	}

	public static function lt( string $field, mixed $value ): self
	{
		return new self( $field, $value, self::OP_LT );
	}

	public static function lte( string $field, mixed $value ): self
	{
		return new self( $field, $value, self::OP_LTE );
	}

	public static function neq( string $field, mixed $value ): self
	{
		return new self( $field, $value, self::OP_NEQ );
	}

	public static function notBetween( string $field, mixed $from, mixed $to ): self
	{
		return new self( $field, [$from, $to], self::OP_NOT_BETWEEN );
	}

	/**
	 *	@param		string			$field
	 *	@param		array<mixed>	$values
	 *	@return		self
	 */
	public static function notIn( string $field, array $values ): self
	{
		return new self( $field, $values, self::OP_NOT_IN );
	}

	public static function notLike( string $field, string $pattern ): self
	{
		return new self( $field, $pattern, self::OP_NOT_LIKE );
	}

	public function getField(): string
	{
		return $this->field;
	}

	public function getOperator(): string
	{
		return $this->operator;
	}

	/**
	 *	@return		string|int|float|array<int,string|int|float|NULL>|NULL
	 */
	public function getValue(): string|int|float|array|NULL
	{
		return $this->value;
	}

	/**
	 *	Renders condition with placeholders and appends values to given parameter list.
	 *	@param		array<int,string|int|float|NULL>	$parameters		List of parameters to append values to
	 *	@return		string
	 */
	public function render( array &$parameters ): string
	{
		$field	= Identifier::quoteExpression( $this->field );
		switch( $this->operator ){
			case self::OP_IS_NULL:
			case self::OP_IS_NOT_NULL:
				return $field.' '.$this->operator;
			case self::OP_IN:
			case self::OP_NOT_IN:
				/** @var array<int,string|int|float|NULL> $values */
				$values	= $this->value;
				if( [] === $values )
					return self::OP_IN === $this->operator ? '1 = 0' : '1 = 1';
				foreach( $values as $value )
					$parameters[]	= $value;
				return $field.' '.$this->operator.' ('.implode( ', ', array_fill( 0, count( $values ), '?' ) ).')';
			case self::OP_BETWEEN:
			case self::OP_NOT_BETWEEN:
				/** @var array<int,string|int|float|NULL> $values */
				$values	= $this->value;
				array_push( $parameters, $values[0], $values[1] );
				return $field.' '.$this->operator.' ? AND ?';
			default:
				/** @var string|int|float $value */
				$value	= $this->value;
				$parameters[]	= $value;
				return $field.' '.$this->operator.' ?';
		}
	}

	/**
	 *	@param		string		$operator
	 *	@param		mixed		$value
	 *	@return		string|int|float|array<int,string|int|float|NULL>|NULL
	 */
	protected static function validateValue( string $operator, mixed $value ): string|int|float|array|NULL
	{
		switch( $operator ){
			case self::OP_IS_NULL:
			case self::OP_IS_NOT_NULL:
				if( NULL !== $value )
					throw new InvalidArgumentException( 'Operator "'.$operator.'" does not take a value' );
				return NULL;
			case self::OP_IN:
			case self::OP_NOT_IN:
				if( !is_array( $value ) )
					throw new InvalidArgumentException( 'Operator "'.$operator.'" needs a list of values' );
				return array_values( array_map( [Value::class, 'normalize'], $value ) );
			case self::OP_BETWEEN:
			case self::OP_NOT_BETWEEN:
				if( !is_array( $value ) || 2 !== count( $value ) )
					throw new InvalidArgumentException( 'Operator "'.$operator.'" needs a list of two values' );
				return array_values( array_map( [Value::class, 'normalize'], $value ) );
			default:
				if( is_array( $value ) || NULL === $value )
					throw new InvalidArgumentException( 'Operator "'.$operator.'" needs a single value, not '.get_debug_type( $value ) );
				return Value::normalize( $value );
		}
	}
}
