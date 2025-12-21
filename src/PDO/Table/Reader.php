<?php /** @noinspection PhpMultipleClassDeclarationsInspection */
/** @noinspection PhpUnused */

declare(strict_types=1);

/**
 *	Table with column definition and indices.
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

use DomainException;
use Error;
use Exception;
use PDO;
use PDOStatement;
use RangeException;
use RuntimeException;
use Throwable;

/**
 *	Table with column definition and indices.
 *	@category		Library
 *	@package		CeusMedia_Database_PDO_Table
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2007-2024 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
class Reader extends Abstraction
{
	//	public $undoStorage;

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

		if( $this->getPrimaryKey() !== $column && !in_array( $column, $this->getIndices(), TRUE ) )
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

		if( $this->getPrimaryKey() !== $column && !in_array( $column, $this->getIndices(), TRUE ) )
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
		//  get enumeration of masked column names
		$columns	= $this->getColumnEnumeration( 0 !== count( $fields ) ? $fields : $this->columns );
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
		if( $resultSet instanceof PDOStatement )
			foreach( $resultSet->fetchAll( PDO::FETCH_NUM ) as $row )
				$list[]	= $row[0];
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
		if( PDO::FETCH_CLASS === $this->fetchMode && NULL !== $this->fetchEntityClass )
			return $this->applyFetchModeClassOnResultSet( $resultSet, $manuallyOnFail );

		if( PDO::FETCH_INTO === $this->fetchMode && NULL !== $this->fetchEntityObject )
			return $this->applyFetchModeIntoOnResultSet( $resultSet );

		return $resultSet->fetchAll( $this->fetchMode );
	}

	/**
	 *	@param		PDOStatement	$resultSet
	 *	@param		bool			$manuallyOnFail
	 *	@return		array
	 *	@throws		RuntimeException	if fetching fails
	 */
	protected function applyFetchModeClassOnResultSet( PDOStatement $resultSet, bool $manuallyOnFail ): array
	{
		try{
			if( NULL === $this->fetchEntityClass )
				throw new RuntimeException( 'No entity class set' );
			/** @var array<object> $fetched */
			$fetched	= $resultSet->fetchAll( PDO::FETCH_CLASS, $this->fetchEntityClass );
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
		foreach( $fetched as $entity )
			if( method_exists( $entity, 'onFetch' ) )
				$entity->onFetch( $this, $entity );
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
			foreach( $row as $key => $value )
				if( property_exists( $entity, $key ) )
					/** @phpstan-ignore-next-line  */
					$entity->{$key} = $value;
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
		foreach( $fetched as $entity )
			if( method_exists( $entity, 'onFetch' ) )
				$entity->onFetch( $this, $entity );
		return $fetched;
	}
}
