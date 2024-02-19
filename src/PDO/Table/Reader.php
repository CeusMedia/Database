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

use CeusMedia\Database\PDO\Connection;
use DomainException;
use InvalidArgumentException;
use PDO;
use PDOStatement;
use RangeException;
use RuntimeException;

/**
 *	Table with column definition and indices.
 *	@category		Library
 *	@package		CeusMedia_Database_PDO_Table
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2007-2024 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
class Reader
{
	/**	@var	int							$defaultFetchMode	Default fetch mode, can be set statically */
	public static int $defaultFetchMode		= PDO::FETCH_ASSOC;

	/**	@var	Connection					$dbc				Database connection resource object */
	protected Connection $dbc;

	/**	@var	array						$columns			List of table columns */
	protected array $columns;

	/**	@var	array						$indices			List of indices of table */
	protected array $indices				= [];

	/**	@var	array						$focusedIndices		List of focused indices */
	protected array $focusedIndices			= [];

	/**	@var	string						$primaryKey			Primary key of this table */
	protected string $primaryKey;

	/**	@var	string						$tableName			Name of this table */
	protected string $tableName;

	/**	@var	int							$fetchMode			Name of this table */
	protected int $fetchMode;

	/**	@var	string|NULL					$fetchEntityClass	Entity class name for PDO fetch mode FETCH_CLASS */
	protected ?string $fetchEntityClass		= NULL;

	/**	@var	object|NULL					$fetchEntityObject	Entity object for PDO fetch mode FETCH_INTO */
	protected ?object $fetchEntityObject	= NULL;

	//	public $undoStorage;

	/**
	 *	Constructor.
	 *
	 *	@access		public
	 *	@param		Connection	$dbc			Database connection resource object
	 *	@param		string		$tableName		Table name
	 *	@param		array		$columns		List of table columns
	 *	@param		string		$primaryKey		Name of the primary key of this table
	 *	@param		?string		$focus			Focused primary key on start up
	 *	@return		void
	 */
	public function __construct( Connection $dbc, string $tableName, array $columns, string $primaryKey, ?string $focus = NULL )
	{
		$this->setDbConnection( $dbc );
		$this->setTableName( $tableName );
		$this->setColumns( $columns );
		$this->setPrimaryKey( $primaryKey );
		$this->setFetchMode( static::$defaultFetchMode );
		$this->defocus();
		if( NULL !== $focus )
			$this->focusPrimary( $focus );
	}

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
	 *	Deleting current focus on indices (including primary key).
	 *	@access		public
	 *	@param		boolean		$primaryOnly		Flag: delete focus on primary key only
	 *	@return		boolean
	 */
	public function defocus( bool $primaryOnly = FALSE ): bool
	{
		if( 0 === count( $this->focusedIndices ) )
			return FALSE;
		if( $primaryOnly ){
			if( !array_key_exists( $this->primaryKey, $this->focusedIndices ) )
				return FALSE;
			unset( $this->focusedIndices[$this->primaryKey] );
			return TRUE;
		}
		$this->focusedIndices = [];
		return TRUE;
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
		$this->validateColumns( $columns );
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
		$columns	= $this->getColumnEnumeration( $columns );
		//  render base query
		$query		= 'SELECT '.$columns.' FROM '.$this->getTableName();

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
		$this->validateColumns( $columns );

		if( $column != $this->getPrimaryKey() && !in_array( $column, $this->getIndices(), TRUE ) )
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
		$this->validateColumns( $columns );

		if( $column != $this->getPrimaryKey() && !in_array( $column, $this->getIndices(), TRUE ) )
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
	 *	Setting focus on an index.
	 *	@access		public
	 *	@param		string					$column			Index column name
	 *	@param		string|int|float|array	$value			Index to focus on
	 *	@return		self
	 *	@throws		DomainException				if given column is not a defined column
	 */
	public function focusIndex( string $column, string|int|float|array $value ): self
	{
		//  check column name
		if( !in_array( $column, $this->indices, TRUE ) && $column != $this->primaryKey )
			throw new DomainException( 'Column "'.$column.'" is neither an index nor primary key and cannot be focused' );
		//  set Focus
		$this->focusedIndices[$column] = $value;
		return $this;
	}

	/**
	 *	Setting focus on a primary key ID.
	 *	@access		public
	 *	@param		int|string	$id				Primary key ID to focus on
	 *	@param		bool		$clearIndices	Flag: clear all previously focuses indices
	 *	@return		self
	 */
	public function focusPrimary( int|string $id, bool $clearIndices = TRUE ): self
	{
		if( $clearIndices )
			$this->focusedIndices	= [];
		$this->focusedIndices[$this->primaryKey] = $id;
		return $this;
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
	 */
	public function get( bool $first = TRUE, array $orders = [], array $limits = [], array $fields = [] ): object|array|NULL
	{
		$this->validateFocus();

		//  render WHERE clause if needed, cursored, without functions
		$conditions	= $this->getConditionQuery();
		$orders		= $this->getOrderCondition( $orders );
		$limits		= $this->getLimitCondition( $limits );
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
	 *	Returns a list of all table columns.
	 *	@access		public
	 *	@return		array
	 */
	public function getColumns(): array
	{
		return $this->columns;
	}

	/**
	 *	Returns reference the database connection.
	 *	@access		public
	 *	@return		Connection
	 */
	public function getDBConnection(): Connection
	{
		return $this->dbc;
	}

	/**
	 *	Returns a list of distinct column values.
	 *	@access		public
	 *	@param		string		$column			Column to get distinct values for
	 *	@param		array		$conditions		Map of condition pairs additional to focuses indices
	 *	@param		array		$orders			Map of order relations
	 *	@param		array		$limits			Array of limit conditions
	 *	@return		array		List of distinct column values
	 */
	public function getDistinctColumnValues( string $column, array $conditions = [], array $orders = [], array $limits = [] ): array
	{
		$columns	= [$column];
		$this->validateColumns( $columns );
		$conditions	= $this->getConditionQuery( $conditions, FALSE, FALSE );
		$conditions	= 0 !== strlen( $conditions ) ? ' WHERE '.$conditions : '';
		$orders		= $this->getOrderCondition( $orders );
		$limits		= $this->getLimitCondition( $limits );
		$query		= 'SELECT DISTINCT('.reset( $columns ).') FROM '.$this->getTableName().$conditions.$orders.$limits;
		$list		= [];
		$resultSet	= $this->dbc->query( $query );
		if( $resultSet instanceof PDOStatement ){
			$resultRows	= $resultSet->fetchAll( PDO::FETCH_NUM );
			if( FALSE !== $resultRows )
				foreach( $resultRows as $row )
					$list[]	= $row[0];
		}
		return $list;
	}

	/**
	 *	Returns set fetch mode.
	 *	@access		public
	 *	@return		integer		$fetchMode		Currently set fetch mode
	 */
	public function getFetchMode(): int
	{
		return $this->fetchMode;
	}

	/**
	 *	@return		string|NULL
	 */
	public function getFetchEntityClass(): ?string
	{
		return $this->fetchEntityClass;
	}

	/**
	 *	@return		object|NULL
	 */
	public function getFetchEntityObject(): ?object
	{
		return $this->fetchEntityObject;
	}

	/**
	 *	Returns current primary focus or index focuses.
	 *	@access		public
	 *	@return		array
	 */
	public function getFocus(): array
	{
		return $this->focusedIndices;
	}

	/**
	 *	Returns all Indices of this table.
	 *	By default, only indices meant to be foreign keys are returned.
	 *	Setting parameter "withPrimaryKey" to TRUE will include primary key as well.
	 *	@access		public
	 *	@param		boolean		$withPrimaryKey			Flag: include primary key (default: FALSE)
	 *	@return		array								List of table indices
	 */
	public function getIndices( bool $withPrimaryKey = FALSE ): array
	{
		$indices	= $this->indices;
		if( 0 !== strlen( trim( $this->primaryKey ) ) && $withPrimaryKey )
			array_unshift( $indices, $this->primaryKey );
		return $indices;
	}

	/**
	 *	...
	 *	@access		public
	 *	@return		?string
	 */
	public function getLastQuery(): ?string
	{
		return $this->dbc->lastQuery;
	}


	/**
	 *	Returns the name of the primary key column.
	 *	@access		public
	 *	@return		string
	 */
	public function getPrimaryKey(): string
	{
		return $this->primaryKey;
	}

	/**
	 *	Returns the name of the table.
	 *	@access		public
	 *	@return		string
	 */
	public function getTableName(): string
	{
		return $this->tableName;
	}

	/**
	 *	Returns data of focused keys.
	 *	@access		public
	 *	@return		bool
	 *	@todo		implement using given fields
	 */
	public function has(): bool
	{
		$this->validateFocus();
		$conditions	= $this->getConditionQuery();
//		$conditions	= $this->getConditionQuery( $conditions, FALSE, TRUE, TRUE );
		$query		= 'SELECT COUNT(*) FROM '.$this->getTableName().' WHERE '.$conditions;
		$statement	= $this->dbc->prepare( $query );
		$statement->execute();
		/** @var array<int,int> $result */
		$result		= $statement->fetch( PDO::FETCH_NUM );
		return 0 !== $result[0];
	}

	/**
	 *	Indicates whether the focus on an index (including primary key) is set.
	 *	@access		public
	 *	@param		?string			$index			...
	 *	@return		boolean
	 */
	public function isFocused( ?string $index = NULL ): bool
	{
		if( 0 === count( $this->focusedIndices ) )
			return FALSE;
		if( !is_null( $index ) && 0 !== strlen( trim( $index ) ) && !array_key_exists( $index, $this->focusedIndices ) )
			return FALSE;
		return TRUE;
	}

	/**
	 *	Setting all columns of the table.
	 *	@access		public
	 *	@param		array		$columns		List of table columns
	 *	@return		self
	 *	@throws		RangeException				If given fields list is empty
	 */
	public function setColumns( array $columns ): self
	{
		if( 0 === count( $columns ) )
			throw new RangeException( 'Column array must not be empty' );
		$this->columns = $columns;
		return $this;
	}

	/**
	 *	Setting a reference to a database connection.
	 *	@access		public
	 *	@param		Connection			$dbc			Database connection resource object
	 *	@return		self
	 */
	public function setDbConnection( Connection $dbc ): self
	{
		$this->dbc = $dbc;
		return $this;
	}

	/**
	 *	Sets fetch mode.
	 *	Mode is a mandatory integer representing a PDO fetch mode.
	 *	@access		public
	 *	@param		integer		$mode			PDO fetch mode
	 *	@see		https://php.net/manual/en/pdo.constants.php
	 *	@return		self
	 */
	public function setFetchMode( int $mode ): self
	{
		$this->fetchMode	= $mode;
		return $this;
	}

	/**
	 *	@access		public
	 * 	@param		string|NULL		$className
	 *	@return		self
	 */
	public function setFetchEntityClass( ?string $className ): self
	{
		$this->fetchEntityClass	= $className;
		return $this;
	}

	/**
	 *	@access		public
	 *	@param		object|NULL		$object
	 *	@return		self
	 */
	public function setFetchEntityObject( ?object $object ): self
	{
		$this->fetchEntityObject	= $object;
		return $this;
	}

	/**
	 *	Setting all indices of this table.
	 *	@access		public
	 *	@param		array		$indices		List of table indices
	 *	@return		self
	 *	@throws		DomainException				if column in index list is not a column
	 *	@throws		DomainException				if column in index list is already known as primary key
	 */
	public function setIndices( array $indices ): self
	{
		foreach( $indices as $index ){
			if( !in_array( $index, $this->columns, TRUE ) )
				throw new DomainException( 'Column "'.$index.'" is not existing in table "'.$this->tableName.'" and cannot be an index' );
			if( $index === $this->primaryKey )
				throw new DomainException( 'Column "'.$index.'" is already primary key and cannot be an index' );
		}
		$this->indices	= array_unique( $indices );
		return $this;
	}

	/**
	 *	Setting the name of the primary key.
	 *	@access		public
	 *	@param		string		$column		Primary key column of this table
	 *	@throws		RangeException			If given column is empty
	 *	@throws		DomainException			If given column is not a defined column
	 *	@return		self
	 */
	public function setPrimaryKey( string $column ): self
	{
		if( 0 === strlen( trim( $column ) ) )
			throw new RangeException( 'Primary key column cannot be empty' );
		if( !in_array( $column, $this->columns, TRUE ) )
			throw new DomainException( 'Column "'.$column.'" is not existing and can not be primary key' );
		$this->primaryKey = $column;
		return $this;
	}

	/**
	 *	Setting the name of the table.
	 *	@access		public
	 *	@param		string		$tableName		Name of this table
	 *	@throws		RangeException				If given table name is empty
	 *	@return		self
	 */
	public function setTableName( string $tableName ): self
	{
		if( 0 === strlen( trim( $tableName ) ) )
			throw new InvalidArgumentException( 'Table name cannot be empty' );
		$this->tableName = $tableName;
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
	 *	@return		array
	 *	@throws		RuntimeException	if fetching fails
	 */
	protected function applyFetchModeOnResultSet( PDOStatement $resultSet ): array
	{
		if( PDO::FETCH_CLASS === $this->fetchMode && NULL !== $this->fetchEntityClass )
			$fetched	= $resultSet->fetchAll( $this->fetchMode, $this->fetchEntityClass );
		else if( PDO::FETCH_INTO === $this->fetchMode && NULL !== $this->fetchEntityObject )
			$fetched	= $resultSet->fetchAll( $this->fetchMode );
		else
			$fetched	= $resultSet->fetchAll( $this->fetchMode );
		if( FALSE === $fetched )
			throw new RuntimeException( 'Fetching failed' );
		return $fetched;
	}

	/**
	 *	@param		PDOStatement		$statement
	 *	@return		bool
	 */
	protected function applyFetchModeOnStatement( PDOStatement $statement ): bool
	{
		if( PDO::FETCH_INTO === $this->fetchMode && NULL !== $this->fetchEntityObject )
			return $statement->setFetchMode( $this->fetchMode, $this->fetchEntityObject );
		if( PDO::FETCH_CLASS === $this->fetchMode && NULL !== $this->fetchEntityClass )
			return $statement->setFetchMode( $this->fetchMode, $this->fetchEntityClass );
		return $statement->setFetchMode( $this->fetchMode );
	}


	/**
	 *	Returns a list of comma separated and masked columns.
	 *	@access		protected
	 *	@param		array		$columns		List of columns to mask and enumerate
	 *	@return		string
	 */
	protected function getColumnEnumeration( array $columns ): string
	{
		$list	= [];
		foreach( $columns as $column )
			$list[]	= in_array( $column, $this->columns, TRUE ) ? '`'.$column.'`' : $column;
		return implode( ', ', $list );
	}

	/**
	 *	Builds and returns WHERE statement component.
	 *	@access		protected
	 *	@param		array		$conditions			Array of conditions
	 *	@param		bool		$usePrimary			Flag: use focused primary key
	 *	@param		bool		$useIndices			Flag: use focused indices
	 *	@param		bool		$allowFunctions		Flag: use focused indices
	 *	@return		string
	 */
	protected function getConditionQuery( array $conditions = [], bool $usePrimary = TRUE, bool $useIndices = TRUE, bool $allowFunctions = FALSE ): string
	{
		$columnConditions = [];
		//  iterate all columns
		foreach( $this->columns as $column ){
			//  if condition given
			if( isset( $conditions[$column] ) ){
				//  note condition pair
				$columnConditions[$column] = $conditions[$column];
				unset( $conditions[$column] );
			}
		}
		$functionConditions = [];
		//  iterate remaining conditions
		foreach( $conditions as $key => $value )
			//  column key is an aggregate function
			if( 1 === preg_match( "/^[a-z]+\(.+\)$/i", $key ) )
				$functionConditions[$key]	= $value;

		//  if using primary key & is focused primary
		if( $usePrimary && $this->isFocused( $this->primaryKey ) ){
			//  if primary key is not already in conditions
			if( !array_key_exists( $this->primaryKey, $columnConditions ) )
				//  note primary key pair
				$columnConditions = $this->getFocus();
		}
		//  if using indices
		if( $useIndices && 0 !== count( $this->focusedIndices ) ){
			//  iterate focused indices
			foreach( $this->focusedIndices as $index => $value )
				//  skip primary key
				if( $index != $this->primaryKey )
					//  if index column is not already in conditions
					if( !array_key_exists( $index, $columnConditions ) )
						//  note index pair
						$columnConditions[$index] = $value;
		}

		//  restart with fresh conditions array
		$conditions = [];

		//  iterate noted column conditions
		foreach( $columnConditions as $column => $value ){
			if( is_array( $value ) ){
				foreach( $value as $nr => $part )
					$value[$nr]	= $this->realizeConditionQueryPart( $column, $part );
				$part	= '('.implode( ' OR ', $value ).')';
			}
			else
				$part	= $this->realizeConditionQueryPart( $column, $value );
			$conditions[]	= $part;

		}

		/*  --  THIS IS NEW, UNDER DEVELOPMENT, UNSECURE AND UNSTABLE  --  */
		//  function are allowed
		if( $allowFunctions )
			//  iterate noted functions
			foreach( $functionConditions as $function => $value ){
				//  extend conditions
				$conditions[]	= $this->realizeConditionQueryPart( $function, $value, FALSE );
			}

		//  return AND combined conditions
		return implode( ' AND ', $conditions );
	}

	/**
	 *	Builds and returns ORDER BY Statement Component.
	 *	@access		protected
	 *	@param		array		$limits			List of Offset and Limit
	 *	@return		string
	 */
	protected function getLimitCondition( array $limits = [] ): string
	{
		$limit		= !isset( $limits[1] ) ? 0 : abs( $limits[1] );
		$offset		= !isset( $limits[0] ) ? 0 : abs( $limits[0] );
		if( 0 !== $limit )
			return ' LIMIT '.$limit.' OFFSET '.$offset;
		return '';
	}

	/**
	 *	Builds and returns ORDER BY Statement Component.
	 *	@access		protected
	 *	@param		array		$orders			Associative Array with Orders
	 *	@return		string
	 */
	protected function getOrderCondition( array $orders = [] ): string
	{
		$order	= '';
		if( 0 !== count( $orders ) ){
			$list	= [];
			foreach( $orders as $column => $direction )
				$list[] = '`'.$column.'` '.strtoupper( $direction );
			$order	= ' ORDER BY '.implode( ', ', $list );
		}
		return $order;
	}

	/**
	 *	...
	 *	@access		protected
	 *	@param		string					$column			...
	 *	@param		string|int|float|NULL	$value			...
	 *	@param		boolean					$maskColumn		...
	 *	@return		string					...
	 *	@throws		InvalidArgumentException	if whitespace is missing after an operator
	 */
	protected function realizeConditionQueryPart( string $column, string|int|float|null $value, bool $maskColumn = TRUE ): string
	{
		$patternBetween		= '/^(><|!><)( ?)(\d+)( ?)&( ?)(\d+)$/';
		$patternBitwise		= '/^(\||&|\^|<<|>>|&~)( ?)(\d+)$/';
		$patternOperators	= '/^(<=|>=|<|>|!=)( ?)(.+)$/';
		$patternLike		= '/^(%|!%) (.+)$/';

		$valueString	= (string) $value;
		if( 1 === preg_match( $patternBetween, trim( $valueString ), $result ) ){
			$matches	= [];
			preg_match_all( $patternBetween, $valueString, $matches );
			$operation		= $matches[1][0] == '!><' ? ' NOT BETWEEN ' : ' BETWEEN ';
			$valueString	= $this->secureValue( $matches[3][0] ).' AND '.$this->secureValue( $matches[6][0] );
			if( 0 === strlen( $matches[2][0] ) || 0 === strlen( $matches[4][0] ) || 0 === strlen( $matches[5][0] ) )
				throw new InvalidArgumentException( 'Missing whitespace between operator and value' );
//				trigger_error( 'Missing whitespace between operators and values', E_USER_DEPRECATED );
		}
		else if( 1 === preg_match( $patternBitwise, $valueString, $result ) ){
			$matches	= [];
			preg_match_all( $patternBitwise, $valueString, $matches );
			$operation		= ' '.$matches[1][0].' ';
			$valueString	= $this->secureValue( $matches[3][0] );
			if( 0 === strlen( $matches[2][0] ) )
				throw new InvalidArgumentException( 'Missing whitespace between operator and value' );
//				trigger_error( 'Missing whitespace between operator and value', E_USER_DEPRECATED );
		}
		else if( 1 === preg_match( $patternOperators, $valueString, $result ) ){
			$matches	= [];
			preg_match_all( $patternOperators, $valueString, $matches );
			$operation		= ' '.$matches[1][0].' ';
			$valueString	= $this->secureValue( $matches[3][0] );
			if( 0 === strlen( $matches[2][0] ) )
				throw new InvalidArgumentException( 'Missing whitespace between operator and value' );
//				trigger_error( 'Missing whitespace between operator and value', E_USER_DEPRECATED );
		}
		else if( 1 === preg_match( $patternLike, $valueString, $result ) ){
			$matches	= [];
			preg_match_all( $patternLike, $valueString, $matches );
			$operation		= ( $matches[1][0] === '!%' ? 'NOT ' : '' ).'LIKE';
			$valueString	= $this->secureValue( $matches[2][0] );
		}
		else if( 1 === preg_match( '/^%/', $valueString ) || 1 === preg_match( '/%$/', $valueString ) ){
			$operation		= ' LIKE ';
			$valueString	= $this->secureValue( $valueString );
		}
		else{
			if( 'is null' === strtolower( $valueString ) || 'is not null' === strtolower( $valueString ) ){
				$operation		= '';
				$valueString	= strtoupper( $valueString );
			}
			else if( NULL === $value ){
				$operation		= 'IS';
				$valueString	= 'NULL';
			}
			else{
				$operation		= '=';
				$valueString	= $this->secureValue( $value );
			}
		}
		$column	= $maskColumn ? '`'.$column.'`' : $column;
		return $column.' '.$operation.' '.$valueString;
	}

	/**
	 *	Secures Conditions Value by adding slashes or quoting.
	 *	@access		protected
	 *	@param		string|int|float|NULL	$value		String, integer, float or NULL to be secured
	 *	@return		string
	 */
	protected function secureValue( string|int|float|null $value ): string
	{
		if( NULL === $value )
			return "NULL";
		if( is_numeric( $value ) )
			return (string) $value;
		$result	= $this->dbc->quote( $value );
        if( FALSE === $result )
			throw new RuntimeException( 'Securing value failed' );
		return $result;
	}

	/**
	 *	Checks columns names for querying methods (find,get), sets wildcard if empty or throws an exception if unacceptable.
	 *	@access		protected
	 *	@param		array|string|NULL		$columns		String or array of column names to validate
	 *	@return		void
	 *	@throws		InvalidArgumentException	if columns is neither a list of columns nor *
	 *	@throws		DomainException				if column is neither a defined column nor *
	 */
	protected function validateColumns( array|string|null &$columns ): void
	{
		if( is_string( $columns ) && 0 !== strlen( trim( $columns ) ) )
			$columns	= [$columns];
		else if( is_array( $columns ) && 0 === count( $columns ) )
			$columns	= ['*'];
		else if( NULL === $columns )
			$columns	= ['*'];

		if( !is_array( $columns ) )
			throw new InvalidArgumentException( 'Column keys must be an array of column names, a column name string or "*"' );
		foreach( $columns as $column ){
			if( '*' === $column || in_array( $column, $this->columns, TRUE ) )
				continue;
			if( 1 === preg_match( '/ AS /i', $column ) )
				continue;
			throw new DomainException( 'Column key "'.$column.'" is not a valid column of table "'.$this->tableName.'"' );
		}
	}

	/**
	 *	Checks if a focus is set for following operation and throws an exception if not.
	 *	@access		protected
	 *	@throws		RuntimeException
	 *	@return		void
	 */
	protected function validateFocus(): void
	{
		if( !$this->isFocused() )
			throw new RuntimeException( 'No Primary Key or Index focused for Table "'.$this->tableName.'"' );
	}
}
