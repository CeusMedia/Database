<?php /** @noinspection PhpMultipleClassDeclarationsInspection */

/**
 *	Write Access for Database Tables.
 *
 *	Copyright (c) 2007-2024 Christian Würker (ceusmedia.de)
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
 *	@package		CeusMedia_Database_PDO_Table
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2007-2024 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
namespace CeusMedia\Database\PDO\Table;

use CeusMedia\Common\ADT\Collection\Dictionary;
use InvalidArgumentException;
use PDO;
use ReflectionObject;
use RuntimeException;
use Traversable;

/**
 *	Write Access for Database Tables.
 *	@category		Library
 *	@package		CeusMedia_Database_PDO_Table
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2007-2024 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
class Writer extends Abstraction
{
	/**
	 *	Deletes focused Rows in this Table and returns number of affected Rows.
	 *	@access		public
	 *	@return		integer
	 */
	public function delete(): int
	{
		$this->validateFocus();
		$conditions	= $this->getConditionQuery();
		$query	= 'DELETE FROM '.$this->getTableName().' WHERE '.$conditions;
#		$has	= $this->get( FALSE );
#		if( !$has )
#			throw new \InvalidArgumentException( 'Focused Indices are not existing.' );
		return $this->dbc->exec( $query );
	}

	/**
	 *	Deletes data by given conditions.
	 *	@access		public
	 *	@param		array		$where		associative Array of Condition Strings
	 *	@return		integer
	 */
	public function deleteByConditions( array $where = [] ): int
	{
		//  render WHERE conditions, uncursored, without functions
		$conditions		= $this->getConditionQuery( $where, FALSE, FALSE );
		$query			= 'DELETE FROM '.$this->getTableName().' WHERE '.$conditions;
		$affectedRows	= $this->dbc->exec( $query );
		$this->defocus();
		return $affectedRows;
	}

	/**
	 *	Inserts data into this table and returns ID.
	 *	@access		public
	 *	@param		array|object	$data			Map of data (array | anonymous object | dictionary | traversable | iterator | entity object) to store
	 *	@param		boolean			$stripTags		Flag: strip HTML Tags from values
	 *	@return		integer			ID of inserted row
	 */
	public function insert( array|object $data = [], bool $stripTags = TRUE ): int
	{
		if( is_object( $data ) )
			$data	= $this->convertObjectToArray( $data );
		$columns	= [];
		$values		= [];
		//  iterate Columns
		foreach( $this->columns as $column ){
			//  no Data given for Column
			if( !isset( $data[$column] ) )
				continue;
			$value = $data[$column];
			if( $stripTags )
				$value = strip_tags( $value );
			$columns[$column]	= $column;
			$values[$column]	= $this->secureValue( $value );
		}
		//  add focused indices to data
		if( $this->isFocused() ){
			//  iterate focused indices
			foreach( $this->focusedIndices as $index => $value ){
				//  Column is already set
				if( isset( $columns[$index] ) )
					continue;
				//  skip primary key
				if( $index == $this->primaryKey )
					continue;
				//  add key
				$columns[$index]	= $index;
				//  add value
				$values[$index]		= $this->secureValue( $value );
			}
		}
		//  get enumeration of masked column names
		$columns	= $this->getColumnEnumeration( $columns );
		$values		= implode( ', ', array_values( $values ) );
		$this->dbc->exec( vsprintf( 'INSERT INTO %s (%s) VALUES (%s)', [
			$this->getTableName(),
			$columns,
			$values,
		] ) );
		return (int) $this->dbc->lastInsertId();
	}

	/**
	 *	Updating data of focused primary key in this table.
	 *	@access		public
	 *	@param		array|object	$data			Map of data (array | anonymous object | dictionary | traversable | iterator | entity object) to store
	 *	@param		boolean			$stripTags		Flag: strip HTML tags from values
	 *	@return		integer
	 *	@throw		RuntimeException				Not implemented: Entity classes with protected members
	 */
	public function update( array|object $data = [], bool $stripTags = TRUE ): int
	{
		if( is_object( $data ) )
			$data	= $this->convertObjectToArray( $data );
		if( 0 === count( $data ) )
			throw new InvalidArgumentException( 'Data for update cannot be empty' );
		$this->validateFocus();
		if( !$this->_currentFocusHits() )
			throw new InvalidArgumentException( 'No data sets focused for update' );
		$updates	= [];
		foreach( $this->columns as $column ){
			if( !array_key_exists($column, $data) )
				continue;
			$value	= $data[$column];
			if( $stripTags && $value !== NULL )
				$value	= strip_tags( $value );
			$value	= $this->secureValue( $value );
			$updates[] = '`'.$column.'`='.$value;
		}
		$affectedRows   = 0;
		if( 0 !== count( $updates ) ){
			$updates	= implode( ', ', $updates );
			$query		= 'UPDATE '.$this->getTableName().' SET '.$updates.' WHERE '.$this->getConditionQuery();
			$affectedRows	= $this->dbc->exec( $query );
		}
		return $affectedRows;
	}

	/**
	 *	Updates data in table where conditions are given for.
	 *	@access		public
	 *	@param		array|object	$data			Map of data (array | anonymous object | dictionary | traversable | iterator | entity object) to store
	 *	@param		array			$conditions		Array of condition pairs
	 *	@param		boolean			$stripTags		Flag: strip HTML tags from values
	 *	@return		integer
	 */
	public function updateByConditions( array|object $data = [], array $conditions = [], bool $stripTags = FALSE ): int
	{
		if( is_object( $data ) )
			$data	= $this->convertObjectToArray( $data );
		if( 0 === count( $data ) )
			throw new InvalidArgumentException( 'Data for update cannot be empty' );
		if( 0 === count( $conditions ) )
			throw new InvalidArgumentException( 'Conditions for update cannot be empty' );

		$updates	= [];
		//  render WHERE conditions, uncursored, without functions
		$conditions	= $this->getConditionQuery( $conditions, FALSE, FALSE );
		foreach( $this->columns as $column ){
			if( isset( $data[$column] ) ){
				if( $stripTags )
					$data[$column]	= strip_tags( $data[$column] );
				$data[$column]	= $this->secureValue( $data[$column] );
				$updates[] = '`'.$column.'`='.$data[$column];
			}
		}
		$affectedRows   = 0;
		if( 0 !== count( $updates ) ){
			$updates	= implode( ', ', $updates );
			$query		= 'UPDATE '.$this->getTableName().' SET '.$updates.' WHERE '.$conditions;
			$affectedRows		= $this->dbc->exec( $query );
		}
		return $affectedRows;
	}

	/**
	 *	Removes all data and resets incremental counter.
	 *	Note: This method does not return the number of removed rows.
	 *	@access		public
	 *	@return		self
	 *	@see		https://dev.mysql.com/doc/refman/4.1/en/truncate.html
	 */
	public function truncate(): self
	{
		$query	= 'TRUNCATE '.$this->getTableName();
		$this->dbc->exec( $query );
		return $this;
	}

	/**
	 *	Tries to convert several data object types to an array.
	 *	Supports anonymous object and dictionary.
	 *	Supports any traversable or iterator.
	 *	Supports entity object where class has public properties.
	 *	Does not support entity object with getters and setters, yet.
	 *
	 *	@param		object		$object
	 *	@return		array
	 */
	protected function convertObjectToArray( object $object ): array
	{
		if( 'stdClass' === get_class( $object ) )
			return (array) $object;
		if( $object instanceof Dictionary )
			/** @phpstan-ignore-next-line */
			return $object->getAll();
		if( is_iterable( $object ) || $object instanceof Traversable ){
			$map	= [];
			foreach( $object as $key => $value )
				$map[$key]	= $value;
			return $map;
		}

		$reflection	= new ReflectionObject( $object );
		if( $reflection->hasProperty( $this->primaryKey ) ){
			if( $reflection->getProperty( $this->primaryKey )->isPublic() )
				return (array) $object;
		}
		throw new RuntimeException( 'Not implemented, yet' );
	}

	/**
	 *	Returns data of focused keys.
	 *	@access		public
	 *	@return		bool
	 *	@todo		implement using given fields
	 */
	protected function _currentFocusHits(): bool
	{
		$this->validateFocus();

		/** @noinspection SqlNoDataSourceInspection */
		/** @noinspection SqlResolve */
		$query	= vsprintf( 'SELECT COUNT(`%s`) AS count FROM %s WHERE %s', [
			$this->primaryKey,
			$this->getTableName(),
			$this->getConditionQuery()
		] );
		$result	= $this->dbc->query( $query );
		if( FALSE !== $result ){
			/** @var array|FALSE $array */
			$array	= $result->fetch( PDO::FETCH_NUM );
			if( FALSE !== $array )
				return (bool) (int) $array[0];

		}
		return FALSE;
	}
}
