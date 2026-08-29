<?php /** @noinspection PhpMultipleClassDeclarationsInspection */
/** @noinspection PhpUnused */

declare(strict_types=1);

/**
 *	Table with column definition and indices.
 *
 *	Copyright (c) 2007-2026 Christian Würker (ceusmedia.de)
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
 *	@copyright		2007-2026 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */

namespace CeusMedia\Database\PDO\Table;

use DateTime;
use DomainException;
use Error;
use Exception;
use PDO;
use PDOStatement;
use RangeException;
use RuntimeException;
use Throwable;
use TypeError;

/**
 *	Table with column definition and indices.
 *	@category		Library
 *	@package		CeusMedia_Database_PDO_Table
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2007-2026 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
class Reader extends Abstraction
{
	//	public $undoStorage;

	/**	@var	string[]	$jsonColumns	List of columns to be JSON-decoded on fetch */
	protected array $jsonColumns	= [];

	/**	@var	string[]	$dateTimeColumns	List of columns to be decoded into DateTime objects on fetch */
	protected array $dateTimeColumns	= [];

	/**
	 *	Returns count of all entries of this table covered by conditions.
	 *	@access		public
	 *	@param		array		$conditions		Map of columns and values to filter by
	 *	@return		integer
	 */
	public function count( array $conditions = [] ): int
	{
		//  render WHERE clause if needed, foreign cursored, allow functions
		$conditions	= $this->getConditionQuery( $conditions, FALSE, TRUE, TRUE );
		$conditions	= strlen( $conditions ) > 0 ? ' WHERE '.$conditions : '';
		/** @noinspection SqlNoDataSourceInspection */
		/** @noinspection SqlResolve */
		$query	= 'SELECT COUNT(`%s`) AS count FROM %s%s';
		$query	= sprintf( $query, $this->primaryKey, $this->getTableName(), $conditions );
		$result	= $this->dbc->query( $query );
		if( FALSE === $result )
			return 0;

		/** @var array|FALSE $array */
		$array	= $result->fetch( PDO::FETCH_NUM );
		if( FALSE === $array )
			return 0;
		return (int) $array[0];
	}

	/**
	 *	Returns count of all entries of this large table (containing many entries) covered by conditions.
	 *	Attention: The returned number may be inaccurate, but this is much faster.
	 *	@access		public
	 *	@param		array		$conditions		Map of columns and values to filter by
	 *	@return		integer
	 */
	public function countFast( array $conditions = [] ): int
	{
		//  render WHERE clause if needed, foreign cursored, allow functions
		$conditions	= $this->getConditionQuery( $conditions, FALSE, TRUE, TRUE );
		$conditions	= strlen( $conditions ) > 0 ? ' WHERE '.$conditions : '';
		$query		= 'EXPLAIN SELECT COUNT('.$this->primaryKey.') FROM '.$this->getTableName().$conditions;
		$result	= $this->dbc->query( $query );
		if( FALSE === $result )
			return 0;

		/** @var array|FALSE $array */
		$array	= $result->fetch( PDO::FETCH_ASSOC );
		if( FALSE === $array )
			return 0;
		return (int) $array['rows'];
	}

	/**
	 *	Returns all entries of this table in an array.
	 *	@access		public
	 *	@param		array|string|NULL	$columns		List of columns to deliver
	 *	@param		array		$conditions		Map of condition pairs additional to focuses indices
	 *	@param		array		$orders			Map of order relations
	 *	@param		array		$limits			Array of limit conditions
	 *	@param		array		$groupings		List of columns to group by
	 *	@param		array		$having			List of conditions to apply after grouping
	 *	@return		array		List of fetched table rows
	 *	@throws		RuntimeException			If executing fails
	 */
	public function find( array|string|null $columns = [], array $conditions = [], array $orders = [], array $limits = [], array $groupings = [], array $having = [] ): array
	{
		$columns	= $this->validateColumns( $columns );
		//  render WHERE clause if needed, uncursored, allow functions
		$conditions	= $this->getConditionQuery( $conditions, FALSE, FALSE, TRUE );
		$conditions = 0 !== strlen( $conditions ) ? ' WHERE '.$conditions : '';
		//  render ORDER BY clause if needed
		$orders		= $this->getOrderCondition( $orders );
		//  render LIMIT BY clause if needed
		$limits		= $this->getLimitCondition( $limits );
		//  render GROUP BY clause if needed
		$groupings	= 0 !== count( $groupings ) ? ' GROUP BY '.join( ', ', $groupings ) : '';
		//  render HAVING clause if needed
		$partHaving	= 0 !== count( $having ) ? ' HAVING '.join( ' AND ', $having ) : '';
		//  get enumeration of masked column names
		$columnList	= $this->getColumnEnumeration( $columns );
		//  render base query
		$query		= 'SELECT '.$columnList.' FROM '.$this->getTableName();

		//  append rendered conditions, orders, limits, groupings and having
		$query		= $query.$conditions.$groupings.$partHaving.$orders.$limits;
		$statement	= $this->dbc->prepare( $query );
		if( !$statement->execute() )
			throw new RuntimeException( 'Executing failed' );
		$this->applyFetchModeOnStatement( $statement );
		return $this->applyFetchModeOnResultSet( $statement );
	}

	/**
	 *	Returns all entries of this table in an array.
	 *	@access		public
	 *	@param		array|string|NULL	$columns		List of columns to deliver
	 *	@param		string				$column			Column to match with values
	 *	@param		array				$values			List of possible values of column
	 *	@param		array				$orders			Map of order relations
	 *	@param		array				$limits			Array of limit conditions
	 *	@throws		DomainException		if column is not an index
	 */
	public function findWhereIn( array|string|null $columns, string $column, array $values, array $orders = [], array $limits = [] ): array
	{
		//  columns attribute needs to of string or array
		if( !is_string( $columns ) && !is_array( $columns ) )
			//  otherwise use empty array
			$columns	= [];
		$columns	= $this->validateColumns( $columns );

		if( $column !== $this->getPrimaryKey() && !in_array( $column, $this->getIndices(), TRUE ) )
			throw new DomainException( 'Field of WHERE IN-statement must be an index' );

		$orders		= $this->getOrderCondition( $orders );
		$limits		= $this->getLimitCondition( $limits );
		for( $i=0; $i<count( $values ); $i++ )
			$values[$i]	= $this->secureValue( $values[$i] );

		//  get enumeration of masked column names
		$columns	= $this->getColumnEnumeration( $columns );
		$query		= 'SELECT '.$columns.' FROM '.$this->getTableName().' WHERE '.$column.' IN ('.implode( ', ', $values ).') '.$orders.$limits;
		$statement	= $this->dbc->prepare( $query );
		if( !$statement->execute() )
			throw new RuntimeException( 'Executing failed' );
		$this->applyFetchModeOnStatement( $statement );
		return $this->applyFetchModeOnResultSet( $statement );
	}

	/**
	 *	@access		public
	 *	@param		array|string|NULL	$columns		List of columns to deliver
	 *	@param		string				$column			Column to match with values
	 *	@param		array				$values			List of possible values of column
	 *	@param		array				$conditions		Additional AND-related conditions
	 *	@param		array				$orders			Map of order relations
	 *	@param		array				$limits			Array of limit conditions
	 *	@throws		RangeException		if column is not an index
	 */
	public function findWhereInAnd( array|string|null $columns, string $column, array $values, array $conditions = [], array $orders = [], array $limits = [] ): array
	{
		//  columns attribute needs to of string or array
		if( !is_string( $columns ) && !is_array( $columns ) )
			//  otherwise use empty array
			$columns	= [];
		$columns	= $this->validateColumns( $columns );

		if( $column !== $this->getPrimaryKey() && !in_array( $column, $this->getIndices(), TRUE ) )
			throw new RangeException( 'Field of WHERE IN-statement must be an index' );

		//  render WHERE clause if needed, uncursored, allow functions
		$conditions	= $this->getConditionQuery( $conditions, FALSE, FALSE, TRUE );
		$orders		= $this->getOrderCondition( $orders );
		$limits		= $this->getLimitCondition( $limits );
		for( $i=0; $i<count( $values ); $i++ )
			$values[$i]	= $this->secureValue( $values[$i] );

		if( 0 !== strlen( $conditions ) )
			$conditions	.= ' AND ';
		//  get enumeration of masked column names
		$columns	= $this->getColumnEnumeration( $columns );
		$query		= 'SELECT '.$columns.' FROM '.$this->getTableName().' WHERE '.$conditions.$column.' IN ('.implode( ', ', $values ).') '.$orders.$limits;
		$statement	= $this->dbc->prepare( $query );
		if( !$statement->execute() )
			throw new RuntimeException( 'Executing failed' );
		$this->applyFetchModeOnStatement( $statement );
		return $this->applyFetchModeOnResultSet( $statement );
	}

	/**
	 *	Returns data of focused keys.
	 *	@access		public
	 *	@param		bool	$first		Extract first entry of result
	 *	@param		array	$orders		Associative array of orders
	 *	@param		array	$limits		Array of offset and limit
	 *	@param		array	$fields		List of column, otherwise all
	 *	@return		array|object|NULL
	 *	@todo		implement using given fields
	 *	@throws		RuntimeException	If no index has been focused
	 */
	public function get( bool $first = TRUE, array $orders = [], array $limits = [], array $fields = [] ): object|array|NULL
	{
		$this->validateFocus();

		//  render WHERE clause if needed, cursored, without functions
		$conditions	= $this->getConditionQuery();
		$orders		= $this->getOrderCondition( $orders );
		$limits		= $this->getLimitCondition( $first ? [0, 1] : $limits );
		$allColumns	= array_unique( array_merge( $this->columns, $this->generated ) );
		//  get enumeration of masked column names
		$columns	= $this->getColumnEnumeration( 0 !== count( $fields ) ? $fields : $allColumns );
		$query		= 'SELECT '.$columns.' FROM '.$this->getTableName().' WHERE '.$conditions.$orders.$limits;
		$statement	= $this->dbc->prepare( $query );
		if( $statement->execute() ){
			$this->applyFetchModeOnStatement( $statement );
			$resultList = $this->applyFetchModeOnResultSet( $statement );
			if( $first )
				return count( $resultList ) !== 0 ? $resultList[0] : NULL;
			return $resultList;
		}
		return $first ? NULL : [];
	}

	/**
	 *	Returns a list of distinct column values.
	 *	@access		public
	 *	@param		string		$column			Column to get distinct values for
	 *	@param		array		$conditions		Map of condition pairs additional to focuses indices
	 *	@param		array		$orders			Map of order relations
	 *	@param		array		$limits			Array of limit conditions
	 *	@return		array		List of distinct column values
	 *	@throws		DomainException				If column is neither a defined column nor *
	 */
	public function getDistinctColumnValues( string $column, array $conditions = [], array $orders = [], array $limits = [] ): array
	{
		$columns	= $this->validateColumns( [$column] );
		$conditions	= $this->getConditionQuery( $conditions, FALSE, FALSE );
		$conditions	= 0 !== strlen( $conditions ) ? ' WHERE '.$conditions : '';
		$orders		= $this->getOrderCondition( $orders );
		$limits		= $this->getLimitCondition( $limits );
		$query		= 'SELECT DISTINCT('.reset( $columns ).') FROM '.$this->getTableName().$conditions.$orders.$limits;
		$list		= [];
		$resultSet	= $this->dbc->query( $query );
		//  this bypasses applyFetchModeOnResultSet(), so JSON/DateTime columns need decoding here, too
		$isJsonColumn		= in_array( $column, $this->jsonColumns, TRUE );
		$isDateTimeColumn	= in_array( $column, $this->dateTimeColumns, TRUE );
		if( $resultSet instanceof PDOStatement )
			foreach( $resultSet->fetchAll( PDO::FETCH_NUM ) as $row ){
				$value	= $row[0];
				if( $isJsonColumn && is_string( $value ) )
					$value	= $this->decodeJsonColumnValue( $value, TRUE );
				else if( $isDateTimeColumn && is_string( $value ) )
					$value	= $this->decodeDateTimeColumnValue( $value );
				$list[]	= $value;
			}
		return $list;
	}

	/**
	 *	Returns data of focused keys.
	 *	@access		public
	 *	@return		bool
	 */
	public function has(): bool
	{
		$this->validateFocus();
		$conditions	= $this->getConditionQuery();
//		$conditions	= $this->getConditionQuery( $conditions, FALSE, TRUE, TRUE );
		$query		= 'SELECT COUNT('.$this->primaryKey.') FROM '.$this->getTableName().' WHERE '.$conditions;
		$statement	= $this->dbc->prepare( $query );
		$statement->execute();
		/** @var array<int,int> $result */
		$result		= $statement->fetch( PDO::FETCH_NUM );
		return 0 !== $result[0];
	}

	/**
	 *	Returns list of columns to be JSON-decoded on fetch.
	 *	@access		public
	 *	@return		string[]
	 */
	public function getJsonColumns(): array
	{
		return $this->jsonColumns;
	}

	/**
	 *	Sets list of columns to be transparently JSON-decoded on fetch.
	 *	Applies to every fetch mode: plain array/object rows (eg. FETCH_ASSOC,
	 *	FETCH_OBJ) get the column decoded in place, and entities (FETCH_CLASS,
	 *	FETCH_INTO) get the matching member overwritten with the decoded value,
	 *	before any "onFetch" hook runs. Array rows are decoded into arrays, object
	 *	rows and entities are decoded into stdClass objects.
	 *	Values written via the table writer are JSON-encoded automatically for any
	 *	column given an array or object value, no matter this list.
	 *	@access		public
	 *	@param		string[]		$columns		List of column names
	 *	@return		static
	 *	@throws		DomainException					if a given column is not an existing column
	 */
	public function setJsonColumns( array $columns ): static
	{
		$allColumns	= array_unique( array_merge( $this->columns, $this->generated ) );
		foreach( $columns as $column )
			if( !in_array( $column, $allColumns, TRUE ) )
				throw new DomainException( 'Column "'.$column.'" is not existing in table "'.$this->tableName.'" and cannot be a JSON column' );
		$this->jsonColumns	= array_unique( $columns );
		return $this;
	}

	/**
	 *	Returns list of columns to be decoded into DateTime objects on fetch.
	 *	@access		public
	 *	@return		string[]
	 */
	public function getDateTimeColumns(): array
	{
		return $this->dateTimeColumns;
	}

	/**
	 *	Sets list of columns to be transparently decoded into DateTime objects on fetch.
	 *	Applies to every fetch mode, the same way as setJsonColumns() does. Fractional
	 *	seconds (microtime) are handled automatically in both directions: writing always
	 *	includes them, and the database silently truncates them if the column does not
	 *	support them (plain DATETIME/TIMESTAMP instead of DATETIME(6)/TIMESTAMP(6)); on
	 *	read, PHP's DateTime constructor detects whether the fetched string has a
	 *	fractional part or not, so no separate "has microtime" configuration is needed.
	 *	Values written via the table writer are encoded automatically for any column
	 *	given a DateTimeInterface value, no matter this list.
	 *	@access		public
	 *	@param		string[]		$columns		List of column names
	 *	@return		static
	 *	@throws		DomainException					if a given column is not an existing column
	 */
	public function setDateTimeColumns( array $columns ): static
	{
		$allColumns	= array_unique( array_merge( $this->columns, $this->generated ) );
		foreach( $columns as $column )
			if( !in_array( $column, $allColumns, TRUE ) )
				throw new DomainException( 'Column "'.$column.'" is not existing in table "'.$this->tableName.'" and cannot be a DateTime column' );
		$this->dateTimeColumns	= array_unique( $columns );
		return $this;
	}

	/**
	 *	Setting UNDO storage.
	 *	@access		public
	 *	@param		object		$storage		Object for UNDO storage
	 *	@return		self
	 */
/*	public function setUndoStorage( $storage ): self
	{
		$this->undoStorage = $storage;
		return $this;
	}*/


	//  --  PROTECTED  --  //


	/**
	 *	@param		PDOStatement	$resultSet
	 *	@param		bool			$manuallyOnFail		Fetch manually of PDO fetching failed, default: no
	 *	@return		array
	 *	@throws		RuntimeException	if fetching fails
	 */
	protected function applyFetchModeOnResultSet( PDOStatement $resultSet, bool $manuallyOnFail = FALSE ): array
	{
		if( 0 !== ( $this->fetchMode & PDO::FETCH_CLASS ) && NULL !== $this->fetchEntityClass )
			return $this->applyFetchModeClassOnResultSet( $resultSet, $manuallyOnFail );

		if( 0 !== ( $this->fetchMode & PDO::FETCH_INTO ) && NULL !== $this->fetchEntityObject )
			return $this->applyFetchModeIntoOnResultSet( $resultSet );

		$rows	= $resultSet->fetchAll( $this->fetchMode );
		if( [] === $this->jsonColumns && [] === $this->dateTimeColumns )
			return $rows;
		foreach( $rows as $nr => $row ){
			$row	= $this->decodeJsonColumns( $row );
			$row	= $this->decodeDateTimeColumns( $row );
			$rows[$nr]	= $row;
		}
		return $rows;
	}

	/**
	 *	Decodes configured JSON columns of a fetched row back into arrays or objects.
	 *	Row and value shape are kept consistent: array rows (eg. FETCH_ASSOC) get
	 *	array-decoded values, object rows (eg. FETCH_OBJ, entities of FETCH_CLASS or
	 *	FETCH_INTO) get object-decoded (stdClass) values; rows without string keys
	 *	(eg. FETCH_NUM) are returned unchanged. A column value that is not a
	 *	(decodable) JSON string is left untouched.
	 *	@access		protected
	 *	@param		mixed		$row		Fetched row or entity, of a shape depending on fetch mode
	 *	@return		mixed		Row with configured JSON columns decoded, same shape as given
	 *	@throws		RuntimeException	if an entity's property type rejects the decoded value
	 */
	protected function decodeJsonColumns( mixed $row ): mixed
	{
		foreach( $this->jsonColumns as $column ){
			if( is_array( $row ) ){
				if( isset( $row[$column] ) && is_string( $row[$column] ) )
					$row[$column]	= $this->decodeJsonColumnValue( $row[$column], TRUE );
			}
			else if( is_object( $row ) ){
				/** @phpstan-ignore-next-line */
				$value	= $row->$column ?? NULL;
				if( is_string( $value ) ){
					$decoded	= $this->decodeJsonColumnValue( $value, FALSE );
					try{
						/** @phpstan-ignore-next-line */
						$row->$column	= $decoded;
					}
					catch( TypeError $e ){
						throw new RuntimeException( vsprintf(
							'Cannot assign decoded JSON value to property "%s" of class %s: %s. '
							.'The property type must accept the decoded value (eg. "string|object|null"), not just "string".',
							[$column, get_class( $row ), $e->getMessage()]
						), 0, $e );
					}
				}
			}
		}
		return $row;
	}

	/**
	 *	Decodes a single JSON column value, unless it is not a (decodable) JSON string,
	 *	in which case it is returned unchanged.
	 *	@access		protected
	 *	@param		string		$value		Raw fetched column value
	 *	@param		bool		$asArray	Flag: decode JSON objects as arrays instead of stdClass objects
	 *	@return		mixed		Decoded value, or the original string if not JSON
	 */
	protected function decodeJsonColumnValue( string $value, bool $asArray ): mixed
	{
		$decoded	= json_decode( $value, $asArray );
		if( NULL !== $decoded || 'null' === strtolower( trim( $value ) ) )
			return $decoded;
		return $value;
	}

	/**
	 *	Decodes configured DateTime columns of a fetched row into DateTime objects.
	 *	Applies to array rows and object rows/entities alike, the same way as
	 *	decodeJsonColumns() does. A column value that is not a parseable date/time
	 *	string is left untouched.
	 *	@access		protected
	 *	@param		mixed		$row		Fetched row or entity, of a shape depending on fetch mode
	 *	@return		mixed		Row with configured DateTime columns decoded, same shape as given
	 *	@throws		RuntimeException	if an entity's property type rejects the decoded value
	 */
	protected function decodeDateTimeColumns( mixed $row ): mixed
	{
		foreach( $this->dateTimeColumns as $column ){
			if( is_array( $row ) ){
				if( isset( $row[$column] ) && is_string( $row[$column] ) )
					$row[$column]	= $this->decodeDateTimeColumnValue( $row[$column] );
			}
			else if( is_object( $row ) ){
				/** @phpstan-ignore-next-line */
				$value	= $row->$column ?? NULL;
				if( is_string( $value ) ){
					$decoded	= $this->decodeDateTimeColumnValue( $value );
					try{
						/** @phpstan-ignore-next-line */
						$row->$column	= $decoded;
					}
					catch( TypeError $e ){
						throw new RuntimeException( vsprintf(
							'Cannot assign decoded DateTime value to property "%s" of class %s: %s. '
							.'The property type must accept the decoded value (eg. "string|DateTime|null"), not just "string".',
							[$column, get_class( $row ), $e->getMessage()]
						), 0, $e );
					}
				}
			}
		}
		return $row;
	}

	/**
	 *	Decodes a single DateTime column value, unless it is not a parseable date/time
	 *	string, in which case it is returned unchanged. Fractional seconds (microtime)
	 *	are picked up automatically if present in the fetched string, no configuration
	 *	needed.
	 *	@access		protected
	 *	@param		string		$value		Raw fetched column value
	 *	@return		DateTime|string		Decoded value, or the original string if not parseable
	 */
	protected function decodeDateTimeColumnValue( string $value ): DateTime|string
	{
		try{
			return new DateTime( $value );
		}
		catch( Exception ){
			return $value;
		}
	}

	/**
	 *	@param		PDOStatement	$resultSet
	 *	@param		bool			$manuallyOnFail
	 *	@return		array
	 *	@throws		RuntimeException	if fetching fails
	 */
	protected function applyFetchModeClassOnResultSet( PDOStatement $resultSet, bool $manuallyOnFail ): array
	{
		if( NULL === $this->fetchEntityClass )
			throw new RuntimeException( 'No entity class set' );
		try{
			try{
				/** @var array<object> $fetched */
				$fetched	= $resultSet->fetchAll( PDO::FETCH_CLASS, $this->fetchEntityClass );
			}
			catch( \PDOException $e ){
				$fetched	= array_map( function( array $fetchedRow ){
					return new $this->fetchEntityClass( $fetchedRow );
				}, $resultSet->fetchAll( PDO::FETCH_ASSOC ) );
			}
		}
		/** @phpstan-ignore-next-line  */
		catch( Error|Exception|Throwable $e ){
			if( $manuallyOnFail )
				return $this->applyFetchModeClassOnResultSetManually( $resultSet );
			throw new RuntimeException( vsprintf( 'Could not create entity of class %s on fetch (%s)', [
				$this->fetchEntityClass,
				$e->getMessage()
			] ), 0, $e );
		}
		/** @var object $entity */
		foreach( $fetched as $entity ){
			//  objects are mutated in place, no reassignment needed
			$this->decodeJsonColumns( $entity );
			$this->decodeDateTimeColumns( $entity );
			if( method_exists( $entity, 'onFetch' ) )
				$entity->onFetch( $this, $entity );
		}
		return $fetched;
	}

	/**
	 *	Creates list of entities on fetched results.
	 *	If fetching using PDO failed, this method can try to fetch into a manually created entity object.
	 *	@param		PDOStatement	$resultSet
	 *	@return		array
	 *	@throws		RuntimeException	if fetching fails
	 */
	protected function applyFetchModeClassOnResultSetManually( PDOStatement $resultSet ): array
	{
		$fetched	= [];
		foreach( $resultSet->fetchAll( PDO::FETCH_ASSOC ) as $row ){
			/** @var object $entity */
			$entity	= new $this->fetchEntityClass();
			$data = [];
			foreach( $row as $key => $value )
				if( property_exists( $entity, $key ) )
					$data[$key]	= $value;

			/** @var object $entity */
			$entity	= new $this->fetchEntityClass( $data );
			$this->decodeJsonColumns( $entity );
			$this->decodeDateTimeColumns( $entity );
			if( method_exists( $entity, 'onFetch' ) )
				$entity->onFetch( $this, $entity );
			$fetched[] = $entity;
		}
		return $fetched;
	}

	/**
	 *	@param		PDOStatement		$resultSet
	 *	@return		object[]
	 *	@throws		RuntimeException	if fetching fails
	 */
	protected function applyFetchModeIntoOnResultSet( PDOStatement $resultSet ): array
	{
		try{
			/** @var array<object> $fetched */
			$fetched	= $resultSet->fetchAll( PDO::FETCH_INTO );
		}
			/** @phpstan-ignore-next-line */
		catch( Error|Exception|Throwable $e ){
			throw new RuntimeException( vsprintf( 'Could not extend entity object of class %s on fetch (%s)', [
				/** @phpstan-ignore-next-line */
				$this->fetchEntityObject::class,
				$e->getMessage()
			] ), 0, $e );
		}
		foreach( $fetched as $entity ){
			//  objects are mutated in place, no reassignment needed
			$this->decodeJsonColumns( $entity );
			$this->decodeDateTimeColumns( $entity );
			if( method_exists( $entity, 'onFetch' ) )
				$entity->onFetch( $this, $entity );
		}
		return $fetched;
	}
}
