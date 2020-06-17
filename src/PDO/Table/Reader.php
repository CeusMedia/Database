<?php
/**
 *	Table with column definition and indices.
 *
 *	Copyright (c) 2007-2020 Christian Würker (ceusmedia.de)
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
 *	along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *	@category		Library
 *	@package		CeusMedia_Database_PDO_Table
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2007-2020 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
namespace CeusMedia\Database\PDO\Table;
/**
 *	Table with column definition and indices.
 *	@category		Library
 *	@package		CeusMedia_Database_PDO_Table
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2007-2020 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
class Reader
{
	/**	@var	BaseConnection	$dbc				Database connection resource object */
	protected $dbc;
	/**	@var	array			$columns			List of table columns */
	protected $columns			= array();
	/**	@var	array			$indices			List of indices of table */
	protected $indices			= array();
	/**	@var	string			$focusedIndices		List of focused indices */
	protected $focusedIndices	= array();
	/**	@var	string			$primaryKey			Primary key of this table */
	protected $primaryKey;
	/**	@var	string			$tableName			Name of this table */
	protected $tableName;
	/**	@var	int				$fetchMode			Name of this table */
	protected $fetchMode;
	/**	@var	int				$defaultFetchMode	Default fetch mode, can be set statically */
	public static $defaultFetchMode	= \PDO::FETCH_ASSOC;

	public $undoStorage;

	/**
	 *	Constructor.
	 *
	 *	@access		public
	 *	@param		PDO			$dbc			Database connection resource object
	 *	@param		string		$tableName		Table name
	 *	@param		array		$columns		List of table columns
	 *	@param		string		$primaryKey		Name of the primary key of this table
	 *	@param		int			$focus			Focused primary key on start up
	 *	@return		void
	 */
	public function __construct( $dbc, $tableName, $columns, $primaryKey, $focus = NULL )
	{
		$this->setDbConnection( $dbc );
		$this->setTableName( $tableName );
		$this->setColumns( $columns );
		$this->setPrimaryKey( $primaryKey );
		$this->fetchMode	= self::$defaultFetchMode;
		$this->defocus();
		if( $focus )
			$this->focusPrimary( $focus );
	}

	/**
	 *	Returns count of all entries of this table covered by conditions.
	 *	@access		public
	 *	@param		array		$conditions		Map of columns and values to filter by
	 *	@return		integer
	 */
	public function count( $conditions = array() ): int
	{
		//  render WHERE clause if needed, foreign cursored, allow functions
		$conditions	= $this->getConditionQuery( $conditions, FALSE, TRUE, TRUE );
		$conditions	= $conditions ? ' WHERE '.$conditions : '';
		$query	= 'SELECT COUNT(`%s`) as count FROM %s%s';
		$query	= sprintf( $query, $this->primaryKey, $this->getTableName(), $conditions );
		return (int) $this->dbc->query( $query )->fetch( \PDO::FETCH_OBJ )->count;
	}

	/**
	 *	Returns count of all entries of this large table (containing many entries) covered by conditions.
	 *	Attention: The returned number may be inaccurat, but this is much faster.
	 *	@access		public
	 *	@param		array		$conditions		Map of columns and values to filter by
	 *	@return		integer
	 */
	public function countFast( $conditions = array() ): int
	{
		//  render WHERE clause if needed, foreign cursored, allow functions
		$conditions	= $this->getConditionQuery( $conditions, FALSE, TRUE, TRUE );
		$conditions	= $conditions ? ' WHERE '.$conditions : '';
		$query		= 'EXPLAIN SELECT COUNT(*) FROM '.$this->getTableName().$conditions;
		return (int) $this->dbc->query( $query )->fetch( \PDO::FETCH_OBJ )->rows;
	}

	/**
	 *	Deleting current focus on indices (including primary key).
	 *	@access		public
	 *	@param		bool		$primaryOnly		Flag: delete focus on primary key only
	 *	@return		bool
	 */
	public function defocus( $primaryOnly = FALSE )
	{
		if( !$this->focusedIndices )
			return FALSE;
		if( $primaryOnly ){
			if( !array_key_exists( $this->primaryKey, $this->focusedIndices ) )
				return FALSE;
			unset( $this->focusedIndices[$this->primaryKey] );
			return TRUE;
		}
		$this->focusedIndices = array();
		return TRUE;
	}

	/**
	 *	Returns all entries of this table in an array.
	 *	@access		public
	 *	@param		array		$columns		List of columns to deliver
	 *	@param		array		$conditions		Map of condition pairs additional to focuses indices
	 *	@param		array		$orders			Map of order relations
	 *	@param		array		$limits			Array of limit conditions
	 *	@param		array		$groupings		List of columns to group by
	 *	@param		array		$havings		List of conditions to apply after grouping
	 *	@return		array		List of fetched table rows
	 */
	public function find( $columns = array(), $conditions = array(), $orders = array(), $limits = array(), $groupings = array(), $havings = array() ): array
	{
		$this->validateColumns( $columns );
		//  render WHERE clause if needed, uncursored, allow functions
		$conditions	= $this->getConditionQuery( $conditions, FALSE, FALSE, TRUE );
		$conditions = $conditions ? ' WHERE '.$conditions : '';
		//  render ORDER BY clause if needed
		$orders		= $this->getOrderCondition( $orders );
		//  render LIMIT BY clause if needed
		$limits		= $this->getLimitCondition( $limits );
		//  render GROUP BY clause if needed
		$groupings	= !empty( $groupings ) ? ' GROUP BY '.join( ', ', $groupings ) : '';
		//  render HAVING clause if needed
		$havings 	= !empty( $havings ) ? ' HAVING '.join( ' AND ', $havings ) : '';
		//  get enumeration of masked column names
		$columns	= $this->getColumnEnumeration( $columns );
		//  render base query
		$query		= 'SELECT '.$columns.' FROM '.$this->getTableName();

		//  append rendered conditions, orders, limits, groupings and havings
		$query		= $query.$conditions.$groupings.$havings.$orders.$limits;
		$resultSet	= $this->dbc->query( $query );
		if( $resultSet )
			return $resultSet->fetchAll( $this->getFetchMode() );
		return array();
	}

	/**
	 *	@throws		\DomainException			if column is not an index
	 */
	public function findWhereIn( $columns, $column, $values, $orders = array(), $limits = array() ): array
	{
		//  columns attribute needs to of string or array
		if( !is_string( $columns ) && !is_array( $columns ) )
			//  otherwise use empty array
			$columns	= array();
		$this->validateColumns( $columns );

		if( $column != $this->getPrimaryKey() && !in_array( $column, $this->getIndices() ) )
			throw new \DomainException( 'Field of WHERE IN-statement must be an index' );

		$orders		= $this->getOrderCondition( $orders );
		$limits		= $this->getLimitCondition( $limits );
		for( $i=0; $i<count( $values ); $i++ )
			$values[$i]	= $this->secureValue( $values[$i] );

		//  get enumeration of masked column names
		$columns	= $this->getColumnEnumeration( $columns );
		$query		= 'SELECT '.$columns.' FROM '.$this->getTableName().' WHERE '.$column.' IN ('.implode( ', ', $values ).') '.$orders.$limits;
		$resultSet	= $this->dbc->query( $query );
		if( $resultSet )
			return $resultSet->fetchAll( $this->getFetchMode() );
		return array();
	}

	/**
	 *	@throws		\RangeException			if column is not an index
	 */
	public function findWhereInAnd( $columns, $column, $values, $conditions = array(), $orders = array(), $limits = array() ): array
	{
		//  columns attribute needs to of string or array
		if( !is_string( $columns ) && !is_array( $columns ) )
			//  otherwise use empty array
			$columns	= array();
		$this->validateColumns( $columns );

		if( $column != $this->getPrimaryKey() && !in_array( $column, $this->getIndices() ) )
			throw new \RangeException( 'Field of WHERE IN-statement must be an index' );

		//  render WHERE clause if needed, uncursored, allow functions
		$conditions	= $this->getConditionQuery( $conditions, FALSE, FALSE, TRUE );
		$orders		= $this->getOrderCondition( $orders );
		$limits		= $this->getLimitCondition( $limits );
		for( $i=0; $i<count( $values ); $i++ )
			$values[$i]	= $this->secureValue( $values[$i] );

		if( $conditions )
			$conditions	.= ' AND ';
		//  get enumeration of masked column names
		$columns	= $this->getColumnEnumeration( $columns );
		$query		= 'SELECT '.$columns.' FROM '.$this->getTableName().' WHERE '.$conditions.$column.' IN ('.implode( ', ', $values ).') '.$orders.$limits;
		$resultSet	= $this->dbc->query( $query );
		if( $resultSet )
			return $resultSet->fetchAll( $this->getFetchMode() );
		return array();
	}

	/**
	 *	Setting focus on an index.
	 *	@access		public
	 *	@param		string		$column			Index column name
	 *	@param		int			$value			Index to focus on
	 *	@return		SessionHandlerInterface
	 *	@throws		\DomainException			if given column is not a defined column
	 */
	public function focusIndex( $column, $value ): self
	{
		//  check column name
		if( !in_array( $column, $this->indices ) && $column != $this->primaryKey )
			throw new \DomainException( 'Column "'.$column.'" is neither an index nor primary key and cannot be focused' );
		//  set Focus
		$this->focusedIndices[$column] = $value;
		return $this;
	}

	/**
	 *	Setting focus on a primary key ID.
	 *	@access		public
	 *	@param		int			$id				Primary key ID to focus on
	 *	@param		bool		$clearIndices	Flag: clear all previously focuses indices
	 *	@return		self
	 */
	public function focusPrimary( $id, $clearIndices = TRUE ): self
	{
		if( $clearIndices )
			$this->focusedIndices	= array();
		$this->focusedIndices[$this->primaryKey] = $id;
		return $this;
	}

	/**
	 *	Returns data of focused keys.
	 *	@access		public
	 *	@param		bool	$first		Extract first entry of result
	 *	@param		array	$orders		Associative array of orders
	 *	@param		array	$limits		Array of offset and limit
	 *	@param		array	$fields		List of colummn, otherwise all
	 *	@return		array
	 *	@todo		implement using given fields
	 */
	public function get( $first = TRUE, $orders = array(), $limits = array(), $fields = array() )
	{
		$this->validateFocus();
		$data = array();
		//  render WHERE clause if needed, cursored, without functions
		$conditions	= $this->getConditionQuery( array(), TRUE, TRUE, FALSE );
		$orders		= $this->getOrderCondition( $orders );
		$limits		= $this->getLimitCondition( $limits );
		//  get enumeration of masked column names
		$columns	= $this->getColumnEnumeration( $this->columns );
		$query		= 'SELECT '.$columns.' FROM '.$this->getTableName().' WHERE '.$conditions.$orders.$limits;

		$resultSet	= $this->dbc->query( $query );
		if( !$resultSet )
			return $first ? NULL : array();
		$resultList	= $resultSet->fetchAll( $this->getFetchMode() );
		if( $first )
			return $resultList ? $resultList[0] : NULL;
		return $resultList;
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
	 *	@return		object
	 */
	public function getDBConnection()
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
	public function getDistinctColumnValues( $column, $conditions = array(), $orders = array(), $limits = array() )
	{
		$this->validateColumns( $columns );
		$conditions	= $this->getConditionQuery( $conditions, FALSE, FALSE, FALSE );
		$conditions	= $conditions ? ' WHERE '.$conditions : '';
		$orders		= $this->getOrderCondition( $orders );
		$limits		= $this->getLimitCondition( $limits );
		$query		= 'SELECT DISTINCT('.$column.') FROM '.$this->getTableName().$conditions.$orders.$limits;
		$list		= array();
		$resultSet	= $this->dbc->query( $query );
		if( $resultSet ){
			foreach( $resultSet->fetchAll( \PDO::FETCH_NUM ) as $row )
				$list[]	= $row[0];
		}
		return $list;
	}

	/**
	 *	Returns set fetch mode.
	 *	@access		public
	 *	@return		integer		$fetchMode		Currently set fetch mode
	 */
	protected function getFetchMode(): int
	{
		return $this->fetchMode;
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
	 *	By default only indices meant to be foreign keys are returned.
	 *	Setting paramter "withPrimaryKey" to TRUE will include primary key as well.
	 *	@access		public
	 *	@param		boolean		$withPrimaryKey			Flag: include primary key (default: FALSE)
	 *	@return		array								List of table indices
	 */
	public function getIndices( $withPrimaryKey = FALSE ): array
	{
		$indices	= $this->indices;
		if( $this->primaryKey && $withPrimaryKey )
			array_shift( $indices, $this->primaryKey );
		return $indices;
	}

	/**
	 *	...
	 *	@access		public
	 *	@return		array
	 */
	public function getLastQuery(): array
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
	 *	Indicates wheter the focus on a index (including primary key) is set.
	 *	@access		public
	 *	@return		boolean
	 */
	public function isFocused( $index = NULL ): bool
	{
		if( !count( $this->focusedIndices ) )
			return FALSE;
		if( $index && !array_key_exists( $index, $this->focusedIndices ) )
			return FALSE;
		return TRUE;
	}

	/**
	 *	Setting all columns of the table.
	 *	@access		public
	 *	@param		array		$columns	List of table columns
	 *	@return		self
	 *	@throws		\InvalidArgumentException		If given fields list is not a list
	 *	@throws		\RangeException					If given fields list is empty
	 */
	public function setColumns( $columns ): self
	{
		if( !is_array( $columns ) )
			throw new \InvalidArgumentException( 'Columns must be an array' );
		if( !( is_array( $columns ) && count( $columns ) ) )
			throw new \RangeException( 'Column array must not be empty' );
		$this->columns = $columns;
		return $this;
	}

	/**
	 *	Setting a reference to a database connection.
	 *	@access		public
	 *	@param		\PDO		$dbc		Database connection resource object
	 *	@return		self
	 *	@throws		\InvalidArgumentException	if given resource is not an object
	 *	@throws		\RuntimeException			if given resource object is not instance of PDO
	 */
	public function setDbConnection( $dbc ): self
	{
		if( !is_object( $dbc ) )
			throw new \InvalidArgumentException( 'Database connection resource must be an object' );
		if( !is_a( $dbc, 'PDO' ) )
			throw new \RuntimeException( 'Database connection resource must be a direct or inherited PDO object' );
		$this->dbc = $dbc;
		return $this;
	}

	/**
	 *	Sets fetch mode.
	 *	Mode is a mandatory integer representing a PDO fetch mode.
	 *	@access		public
	 *	@param		int			$mode			PDO fetch mode
	 *	@see		http://www.php.net/manual/en/pdo.constants.php
	 *	@return		self
	 */
	public function setFetchMode( $mode ): self
	{
		$this->fetchMode	= $mode;
		return $this;
	}

	/**
	 *	Setting all indices of this table.
	 *	@access		public
	 *	@param		array		$indices		List of table indices
	 *	@return		self
	 *	@throws		\DomainException			if column in index list is not a column
	 *	@throws		\DomainException			if column in index list is already known as primary key
	 */
	public function setIndices( $indices ): self
	{
		foreach( $indices as $index )
		{
			if( !in_array( $index, $this->columns ) )
				throw new \DomainException( 'Column "'.$index.'" is not existing in table "'.$this->tableName.'" and cannot be an index' );
			if( $index === $this->primaryKey )
				throw new \DomainException( 'Column "'.$index.'" is already primary key and cannot be an index' );
		}
		$this->indices	= $indices;
		array_unique( $this->indices );
		return $this;
	}

	/**
	 *	Setting the name of the primary key.
	 *	@access		public
	 *	@param		string		$column		Pimary key column of this table
	 *	@throws		\RangeException			If given column is empty
	 *	@throws		\DomainException		If given column is not a defined column
	 *	@return		self
	 */
	public function setPrimaryKey( $column ): self
	{
		if( !strlen( trim( $column ) ) )
			throw new \RangeException( 'Primary key column cannot be empty' );
		if( !in_array( $column, $this->columns ) )
			throw new \DomainException( 'Column "'.$column.'" is not existing and can not be primary key' );
		$this->primaryKey = $column;
		return $this;
	}

	/**
	 *	Setting the name of the table.
	 *	@access		public
	 *	@param		string		$tableName		Name of this table
	 *	@return		void
	 *	@throws		\RangeException				If given table name is empty
	 *	@return		self
	 */
	public function setTableName( $tableName ): self
	{
		if( !strlen( trim( $tableName ) ) )
			throw new \RangeException( 'Table name cannot be empty' );
		$this->tableName = $tableName;
		return $this;
	}

	/**
	 *	Setting the name of the table.
	 *	@access		public
	 *	@param		string		$tableName		Name of this table
	 *	@return		self
	 */
	public function setUndoStorage( $storage ): self
	{
		$this->undoStorage = $storage;
		return $this;
	}

	//  --  PROTECTED  --  //

	/**
	 *	Returns a list of comma separated and masked columns.
	 *	@access		protected
	 *	@param		array		$columns		List of columns to mask and enumerate
	 *	@return		string
	 */
	protected function getColumnEnumeration( $columns ): string
	{
		$list	= array();
		foreach( $columns as $column )
			$list[]	= in_array( $column, $this->columns ) ? '`'.$column.'`' : $column;
		return implode( ', ', $list );
	}

	/**
	 *	Builds and returns WHERE statement component.
	 *	@access		protected
	 *	@param		array		$conditions		Array of conditions
	 *	@param		bool		$usePrimary		Flag: use focused primary key
	 *	@param		bool		$useIndices		Flag: use focused indices
	 *	@return		string
	 */
	protected function getConditionQuery( $conditions, $usePrimary = TRUE, $useIndices = TRUE, $allowFunctions = FALSE ): string
	{
		$columnConditions = array();
		//  iterate all columns
		foreach( $this->columns as $column ){
			//  if condition given
			if( isset( $conditions[$column] ) ){
				//  note condition pair
				$columnConditions[$column] = $conditions[$column];
				unset( $conditions[$column] );
			}
		}
		$functionConditions = array();
		//  iterate remaining conditions
		foreach( $conditions as $key => $value )
			//  column key is a aggregate function
			if( preg_match( "/^[a-z]+\(.+\)$/i", $key ) )
				$functionConditions[$key]	= $value;

		//  if using primary key & is focused primary
		if( $usePrimary && $this->isFocused( $this->primaryKey ) ){
			//  if primary key is not already in conditions
			if( !array_key_exists( $this->primaryKey, $columnConditions ) )
				//  note primary key pair
				$columnConditions = $this->getFocus();
		}
		//  if using indices
		if( $useIndices && count( $this->focusedIndices ) ){
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
		$conditions = array();

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
	protected function getLimitCondition( $limits = array() ): string
	{
		if( !is_array( $limits ) )
			throw new \InvalidArgumentException( 'Given limits must be list of offset and limit' );
		$limit		= !isset( $limits[1] ) ? 0 : abs( $limits[1] );
		$offset		= !isset( $limits[0] ) ? 0 : abs( $limits[0] );
		if( $limit )
			return ' LIMIT '.$limit.' OFFSET '.$offset;
		return '';
	}

	/**
	 *	Builds and returns ORDER BY Statement Component.
	 *	@access		protected
	 *	@param		array		$orders			Associative Array with Orders
	 *	@return		string
	 */
	protected function getOrderCondition( $orders = array() ): string
	{
		$order	= '';
		if( is_array( $orders ) && count( $orders ) )
		{
			$list	= array();
			foreach( $orders as $column => $direction )
				$list[] = '`'.$column.'` '.strtoupper( $direction );
			$order	= ' ORDER BY '.implode( ', ', $list );
		}
		return $order;
	}

	protected function realizeConditionQueryPart( $column, $value, $maskColumn = TRUE )
	{
		$patternBetween		= '/^(><|!><)( ?)([0-9]+)( ?)&( ?)([0-9]+)$/';
		$patternBitwise		= '/^(\||&|\^|<<|>>|&~)( ?)([0-9]+)$/';
		$patternOperators	= '/^(<=|>=|<|>|!=)( ?)(.+)$/';
		if( preg_match( '/^%/', $value ) || preg_match( '/%$/', $value ) ){
			$operation	= ' LIKE ';
			$value		= $this->secureValue( $value );
		}
		else if( preg_match( $patternBetween, trim( $value ), $result ) ){
			$matches	= array();
			preg_match_all( $patternBetween, $value, $matches );
			$operation	= $matches[1][0] == '!><' ? ' NOT BETWEEN ' : ' BETWEEN ';
			$value		= $this->secureValue( $matches[3][0] ).' AND '.$this->secureValue( $matches[6][0] );
			if( !strlen( $matches[2][0] ) || !strlen( $matches[4][0] ) || !strlen( $matches[5][0] ) )
				throw new \Exception( 'Missing whitespace between operator and value' );
//				trigger_error( 'Missing whitespace between operators and values', E_USER_DEPRECATED );
		}
		else if( preg_match( $patternBitwise, $value, $result ) ){
			$matches	= array();
			preg_match_all( $patternOperators, $value, $matches );
			$operation	= ' '.$matches[1][0].' ';
			$value		= $this->secureValue( $matches[3][0] );
			if( !strlen( $matches[2][0] ) )
				throw new \Exception( 'Missing whitespace between operator and value' );
//				trigger_error( 'Missing whitespace between operator and value', E_USER_DEPRECATED );
		}
		else if( preg_match( $patternOperators, $value, $result ) ){
			$matches	= array();
			preg_match_all( $patternOperators, $value, $matches );
			$operation	= ' '.$matches[1][0].' ';
			$value		= $this->secureValue( $matches[3][0] );
			if( !strlen( $matches[2][0] ) )
				throw new \Exception( 'Missing whitespace between operator and value' );
//				trigger_error( 'Missing whitespace between operator and value', E_USER_DEPRECATED );
		}
		else{
			if( strtolower( $value ) == 'is null' || strtolower( $value ) == 'is not null'){
				$operation	= '';
				$value		= strtoupper( $value );
			}
			else if( $value === NULL ){
				$operation	= 'IS';
				$value		= 'NULL';
			}
			else{
				$operation	= '=';
				$value		= $this->secureValue( $value );
			}
		}
		$column	= $maskColumn ? '`'.$column.'`' : $column;
		return $column.' '.$operation.' '.$value;
	}

	/**
	 *	Secures Conditions Value by adding slashes or quoting.
	 *	@access		protected
	 *	@param		string		$value		String to be secured
	 *	@return		string
	 */
	protected function secureValue( $value )
	{
#		if( !ini_get( 'magic_quotes_gpc' ) )
#			$value = addslashes( $value );
#		$value	= htmlentities( $value );
		if ( $value === NULL )
			return "NULL";
		$value	= $this->dbc->quote( $value );
		return $value;
	}

	/**
	 *	Checks columns names for querying methods (find,get), sets wildcard if empty or throws an exception if inacceptable.
	 *	@access		protected
	 *	@param		mixed		$columns			String or array of column names to validate
	 *	@return		void
	 *	@throws		\InvalidArgumentException		if columns is neither a list of columns nor *
	 *	@throws		\DomainException				if column is neither a defined column nor *
	 */
	protected function validateColumns( &$columns ): void
	{
		if( is_string( $columns ) && $columns )
			$columns	= array( $columns );
		else if( is_array( $columns ) && !count( $columns ) )
			$columns	= array( '*' );
		else if( $columns === NULL || $columns == FALSE )
			$columns	= array( '*' );

		if( !is_array( $columns ) )
			throw new \InvalidArgumentException( 'Column keys must be an array of column names, a column name string or "*"' );
		foreach( $columns as $column ){
			if( $column === '*' || in_array( $column, $this->columns ) )
				continue;
			if( preg_match( '/ AS /i', $column ) )
				continue;
			throw new \DomainException( 'Column key "'.$column.'" is not a valid column of table "'.$this->tableName.'"' );
		}
	}

	/**
	 *	Checks if a focus is set for following operation and throws an exception if not.
	 *	@access		protected
	 *	@throws		\RuntimeException
	 *	@return		void
	 */
	protected function validateFocus(): void
	{
		if( !$this->isFocused() )
			throw new \RuntimeException( 'No Primary Key or Index focused for Table "'.$this->tableName.'"' );
	}
}
