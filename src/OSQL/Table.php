<?php
/** @noinspection PhpMultipleClassDeclarationsInspection */

/**
 *	Table, optionally with alias, as part of OSQL queries.
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
 *	Table, optionally with alias, as part of OSQL queries.
 *	@category		Library
 *	@package		CeusMedia_Database_OSQL
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2026 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
class Table
{
	protected string $name;

	protected ?string $alias	= NULL;

	/**
	 *	Constructor.
	 *	@param		string			$name		Table name
	 *	@param		string|NULL		$alias		Alias, optional
	 *	@throws		InvalidArgumentException	if name or alias is not valid
	 */
	public function __construct( string $name, ?string $alias = NULL )
	{
		Identifier::quoteName( $name );
		if( NULL !== $alias )
			Identifier::quoteName( $alias );
		$this->name		= trim( $name );
		$this->alias	= NULL !== $alias ? trim( $alias ) : NULL;
	}

	/**
	 *	Returns given table object or creates one from given name, which may contain an alias, eg. "orders AS o".
	 *	@param		Table|string	$table		Table object or name
	 *	@return		self
	 *	@throws		InvalidArgumentException	if name or alias is not valid
	 */
	public static function resolve( Table|string $table ): self
	{
		if( $table instanceof self )
			return $table;
		if( 1 === preg_match( '/^(\S+)\s+(?:AS\s+)?(\S+)$/i', trim( $table ), $matches ) )
			return new self( $matches[1], $matches[2] );
		return new self( $table );
	}

	public function getAlias(): ?string
	{
		return $this->alias;
	}

	public function getName(): string
	{
		return $this->name;
	}

	/**
	 *	Returns quoted table name, with alias if set.
	 *	@return		string
	 */
	public function render(): string
	{
		$name	= Identifier::quoteName( $this->name );
		if( NULL !== $this->alias && $this->alias !== $this->name )
			return $name.' AS '.Identifier::quoteName( $this->alias );
		return $name;
	}
}
