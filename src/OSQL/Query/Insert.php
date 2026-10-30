<?php
/** @noinspection PhpMultipleClassDeclarationsInspection */

/**
 *	OSQL query to insert data.
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
 *	OSQL query to insert data.
 *	@category		Library
 *	@package		CeusMedia_Database_OSQL_Query
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2026 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
class Insert extends AbstractQuery
{
	/** @var int $affectedRows Number of rows inserted (or changed on duplicate key) by latest execution */
	public int $affectedRows		= 0;

	protected ?Table $table			= NULL;

	/** @var array<int,array<string,string|int|float|NULL>> $rows */
	protected array $rows			= [];

	/** @var string[] $updateOnDuplicate */
	protected array $updateOnDuplicate	= [];

	/**
	 *	Adds another row to insert, for inserting several rows at once.
	 *	All rows must have the same columns.
	 *	@param		array<string,mixed>		$data		Map of columns and values
	 *	@return		static
	 */
	public function addRow( array $data ): static
	{
		$this->rows[]	= [];
		return $this->setAll( $data );
	}

	/**
	 *	Executes query and returns ID of (first) inserted row.
	 *	@return		int
	 */
	public function execute(): int
	{
		$statement			= $this->client->run( $this );
		$this->affectedRows	= $statement->rowCount();
		return (int) $this->client->getConnection()->lastInsertId();
	}

	/**
	 *	Sets table to insert into.
	 *	@param		Table|string	$table
	 *	@return		static
	 */
	public function into( Table|string $table ): static
	{
		$this->table	= Table::resolve( $table );
		return $this;
	}

	/**
	 *	Updates given columns, if a row with same primary or unique key already exists (ON DUPLICATE KEY UPDATE).
	 *	@param		string		...$fields		Columns to update by the values to insert
	 *	@return		static
	 */
	public function onDuplicateKeyUpdate( string ...$fields ): static
	{
		foreach( $fields as $field )
			Identifier::quoteName( $field );
		$this->updateOnDuplicate	= array_values( $fields );
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
		if( [] === $this->rows || [] === $this->rows[0] )
			throw new RuntimeException( 'No values set' );
		$columns	= array_keys( $this->rows[0] );
		$parameters	= [];
		$rows		= [];
		foreach( $this->rows as $nr => $row ){
			if( count( $row ) !== count( $columns ) || [] !== array_diff( $columns, array_keys( $row ) ) )
				throw new InvalidArgumentException( 'Row '.( $nr + 1 ).' has other columns than the first row' );
			foreach( $columns as $column )
				$parameters[]	= $row[$column];
			$rows[]	= '('.implode( ', ', array_fill( 0, count( $columns ), '?' ) ).')';
		}
		$query	= vsprintf( 'INSERT INTO %s (%s) VALUES %s', [
			$this->table->render(),
			implode( ', ', array_map( [Identifier::class, 'quoteName'], $columns ) ),
			implode( ', ', $rows ),
		] );
		if( [] !== $this->updateOnDuplicate ){
			$updates	= array_map( static function( string $field ): string {
				$quoted	= Identifier::quoteName( $field );
				return $quoted.' = VALUES('.$quoted.')';
			}, $this->updateOnDuplicate );
			$query	.= ' ON DUPLICATE KEY UPDATE '.implode( ', ', $updates );
		}
		return self::result( $query, $parameters );
	}

	/**
	 *	Sets value of column in (latest) row.
	 *	@param		string		$field		Column
	 *	@param		mixed		$value		Value: scalar, NULL, bool, date/time object or backed enum
	 *	@return		static
	 */
	public function set( string $field, mixed $value ): static
	{
		Identifier::quoteName( $field );
		if( [] === $this->rows )
			$this->rows[]	= [];
		$this->rows[count( $this->rows ) - 1][trim( $field )]	= Value::normalize( $value );
		return $this;
	}

	/**
	 *	Sets values of columns in (latest) row.
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
