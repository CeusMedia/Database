<?php
/** @noinspection PhpMultipleClassDeclarationsInspection */

/**
 *	Validates and quotes identifiers (tables, columns, aliases, aggregate functions) for OSQL queries.
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
 *	@package		CeusMedia_Database_OSQL
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2026-2026 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */

namespace CeusMedia\Database\OSQL;

use InvalidArgumentException;

/**
 *	Validates and quotes identifiers for OSQL queries.
 *	Identifiers are names of tables, columns and aliases (letters, digits, underscore),
 *	columns can be qualified by table (alias) and aggregate functions of the whitelist are allowed.
 *	@category		Library
 *	@package		CeusMedia_Database_OSQL
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2026-2026 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
final class Identifier
{
	/** @var string[] Aggregate functions allowed in fields, conditions and orders */
	public const FUNCTIONS			= ['AVG', 'COUNT', 'MAX', 'MIN', 'SUM'];

	protected const PATTERN_NAME		= '/^[a-z_][a-z0-9_]*$/i';
	protected const PATTERN_COLUMN		= '/^(?:([a-z_][a-z0-9_]*)\.)?([a-z_][a-z0-9_]*|\*)$/i';
	protected const PATTERN_FUNCTION	= '/^([a-z_]+)\(\s*(DISTINCT\s+)?(.+?)\s*\)$/i';
	protected const PATTERN_ALIAS		= '/^(.+?)\s+AS\s+(\S+)$/i';

	/**
	 *	Returns quoted column, optionally qualified by table (alias), eg. `orders`.`total`.
	 *	@param		string		$column			Column name, eg. "total" or "o.total"
	 *	@param		boolean		$allowWildcard	Flag: allow "*" and "table.*"
	 *	@return		string
	 *	@throws		InvalidArgumentException	if column is not valid
	 */
	public static function quoteColumn( string $column, bool $allowWildcard = FALSE ): string
	{
		$column	= trim( $column );
		if( 1 !== preg_match( self::PATTERN_COLUMN, $column, $matches ) )
			throw new InvalidArgumentException( 'Invalid column "'.$column.'"' );
		if( '*' === $matches[2] && !$allowWildcard )
			throw new InvalidArgumentException( 'Wildcard is not allowed here: "'.$column.'"' );
		$name	= '*' === $matches[2] ? '*' : '`'.$matches[2].'`';
		return '' !== $matches[1] ? '`'.$matches[1].'`.'.$name : $name;
	}

	/**
	 *	Returns quoted column or aggregate function of a column, eg. COUNT(*) or SUM(`o`.`total`).
	 *	@param		string		$expression		Column or function, eg. "o.total" or "COUNT(DISTINCT customerId)"
	 *	@return		string
	 *	@throws		InvalidArgumentException	if expression is not valid or function is not allowed
	 */
	public static function quoteExpression( string $expression ): string
	{
		$expression	= trim( $expression );
		if( 1 !== preg_match( self::PATTERN_FUNCTION, $expression, $matches ) )
			return self::quoteColumn( $expression );
		$function	= strtoupper( $matches[1] );
		if( !in_array( $function, self::FUNCTIONS, TRUE ) )
			throw new InvalidArgumentException( 'Function "'.$matches[1].'" is not allowed, only: '.implode( ', ', self::FUNCTIONS ) );
		$distinct	= '' !== $matches[2] ? 'DISTINCT ' : '';
		$argument	= ( 'COUNT' === $function && '' === $distinct && '*' === $matches[3] ) ? '*' : self::quoteColumn( $matches[3] );
		return $function.'('.$distinct.$argument.')';
	}

	/**
	 *	Returns quoted field for a select list: column, wildcard or function, optionally aliased.
	 *	@param		string		$field			Field, eg. "*", "o.*", "total", "o.total AS sum" or "COUNT(*) AS number"
	 *	@return		string
	 *	@throws		InvalidArgumentException	if field or alias is not valid
	 */
	public static function quoteField( string $field ): string
	{
		$field	= trim( $field );
		if( 1 === preg_match( self::PATTERN_ALIAS, $field, $matches ) )
			return self::quoteExpression( $matches[1] ).' AS '.self::quoteName( $matches[2] );
		if( 1 === preg_match( self::PATTERN_FUNCTION, $field ) )
			return self::quoteExpression( $field );
		return self::quoteColumn( $field, TRUE );
	}

	/**
	 *	Returns quoted name of table, alias or column, without qualification.
	 *	@param		string		$name			Name, eg. "orders"
	 *	@return		string
	 *	@throws		InvalidArgumentException	if name is not valid
	 */
	public static function quoteName( string $name ): string
	{
		$name	= trim( $name );
		if( 1 !== preg_match( self::PATTERN_NAME, $name ) )
			throw new InvalidArgumentException( 'Invalid name "'.$name.'"' );
		return '`'.$name.'`';
	}
}
