<?php
/** @noinspection PhpMultipleClassDeclarationsInspection */

/**
 *	OSQL query to update data.
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
 *	@package		CeusMedia_Database_OSQL_Query
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2026 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */

namespace CeusMedia\Database\OSQL\Query;

use CeusMedia\Database\OSQL\Identifier;
use CeusMedia\Database\OSQL\Table;
use CeusMedia\Database\OSQL\Value;
use InvalidArgumentException;
use RuntimeException;

/**
 *	OSQL query to update data.
 *	@category		Library
 *	@package		CeusMedia_Database_OSQL_Query
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2026 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
class Update extends AbstractQuery
{
	use ConditionsTrait;

	/** @var int $affectedRows Number of rows changed by latest execution */
	public int $affectedRows		= 0;

	protected ?Table $table			= NULL;

	/** @var array<string,string|int|float|NULL> $fields Map of quoted columns and values */
	protected array $fields			= [];

	protected bool $allRows			= FALSE;

	protected ?int $limit			= NULL;

	public function __clone()
	{
		$this->cloneConditions();
	}

	/**
	 *	Allows to update all rows, without conditions.
	 *	Without conditions and this call, rendering fails, to prevent changing a whole table by mistake.
	 *	@param		bool		$allRows
	 *	@return		static
	 */
	public function allRows( bool $allRows = TRUE ): static
	{
		$this->allRows	= $allRows;
		return $this;
	}

	/**
	 *	Executes query and returns number of changed rows.
	 *	@return		int
	 */
	public function execute(): int
	{
		$this->affectedRows	= $this->client->run( $this )->rowCount();
		return $this->affectedRows;
	}

	/**
	 *	Sets table to update.
	 *	@param		Table|string	$table
	 *	@return		static
	 */
	public function table( Table|string $table ): static
	{
		$this->table	= Table::resolve( $table );
		return $this;
	}

	/**
	 *	Sets maximum number of rows to update, NULL for no limit.
	 *	@param		int|NULL	$limit
	 *	@return		static
	 */
	public function limit( ?int $limit ): static
	{
		if( NULL !== $limit && $limit < 1 )
			throw new InvalidArgumentException( 'Limit must be at least 1' );
		$this->limit	= $limit;
		return $this;
	}

	/**
	 *	Returns statement with placeholders and list of parameters.
	 *	@return		object{query: string, parameters: array<int,string|int|float|NULL>}
	 */
	public function render(): object
	{
		if( NULL === $this->table )
			throw new RuntimeException( 'No table set' );
		if( [] === $this->fields )
			throw new RuntimeException( 'No values set' );
		if( NULL === $this->conditions && !$this->allRows )
			throw new RuntimeException( 'No conditions set - call allRows() to update all rows' );
		$parameters	= [];
		$sets		= [];
		foreach( $this->fields as $field => $value ){
			$sets[]			= $field.' = ?';
			$parameters[]	= $value;
		}
		$query	= 'UPDATE '.$this->table->render().' SET '.implode( ', ', $sets );
		$query	.= self::renderConditionGroup( $this->conditions, ' WHERE ', $parameters );
		$query	.= NULL !== $this->limit ? ' LIMIT '.$this->limit : '';
		return self::result( $query, $parameters );
	}

	/**
	 *	Sets value of column.
	 *	@param		string		$field		Column, may be qualified by table, eg. "o.total"
	 *	@param		mixed		$value		Value: scalar, NULL, bool, date/time object or backed enum
	 *	@return		static
	 */
	public function set( string $field, mixed $value ): static
	{
		$this->fields[Identifier::quoteColumn( $field )]	= Value::normalize( $value );
		return $this;
	}

	/**
	 *	Sets values of columns.
	 *	@param		array<string,mixed>		$data		Map of columns and values
	 *	@return		static
	 */
	public function setAll( array $data ): static
	{
		foreach( $data as $field => $value )
			$this->set( $field, $value );
		return $this;
	}
}
