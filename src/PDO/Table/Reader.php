<?php
/**
 *	Table with column definition and indices.
 *
 *	Copyright (c) 2007-2015 Christian Würker (ceusmedia.de)
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
 *	@copyright		2007-2015 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
namespace CeusMedia\Database\PDO\Table;
/**
 *	Table with column definition and indices.
 *	@category		Library
 *	@package		CeusMedia_Database_PDO_Table
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2007-2015 Christian Würker
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
	public function count( $conditions = array() )
	{
		$conditions	= $this->getConditionQuery( $conditions, FALSE, TRUE, TRUE );					//  render WHERE clause if needed, foreign cursored, allow functions
		$conditions	= $conditions ? ' WHERE '.$conditions : '';
		$query	= 'SELECT COUNT(`%s`) as count FROM %s%s';
		$query	= sprintf( $query, $this->primaryKey, $this->getTableName(), $conditions );
		return $this->dbc->query( $query )->fetch( \PDO::FETCH_OBJ )->count;
	}

	/**
	 *	Returns count of all entries of this large table (containing many entries) covered by conditions.
	 *	Attention: The returned number may be inaccurat, but this is much faster.
	 *	@access		public
	 *	@param		array		$conditions		Map of columns and values to filter by
	 *	@return		integer
	 */
	public function countFast( $conditions = array() )
	{
		$conditions	= $this->getConditionQuery( $conditions, FALSE, TRUE, TRUE );					//  render WHERE clause if needed, foreign cursored, allow functions
		$conditions	= $conditions ? ' WHERE '.$conditions : '';
		$query		= 'EXPLAIN SELECT COUNT(*) FROM '.$this->getTableName().$conditions;
		return $this->dbc->query( $query )->fetch( \PDO::FETCH_OBJ )->rows;
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
		if( $primaryOnly )
		{
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
	public function find( $columns = array(), $conditions = array(), $orders = array(), $limits = array(), $groupings = array(), $havings = array() )
	{
		$this->validateColumns( $columns );
		$conditions	= $this->getConditionQuery( $conditions, FALSE, FALSE, TRUE );					//  render WHERE clause if needed, uncursored, allow functions
		$conditions = $conditions ? ' WHERE '.$conditions : '';
		$orders		= $this->getOrderCondition( $orders );											//  render ORDER BY clause if needed
		$limits		= $this->getLimitCondition( $limits );											//  render LIMIT BY clause if needed
		$groupings	= !empty( $groupings ) ? ' GROUP BY '.join( ', ', $groupings ) : '';			//  render GROUP BY clause if needed
		$havings 	= !empty( $havings ) ? ' HAVING '.join( ' AND ', $havings ) : '';				//  render HAVING clause if needed
		$query		= 'SELECT '.implode( ', ', $columns ).' FROM '.$this->getTableName();			//  render base query

		$query		= $query.$conditions.$groupings.$havings.$orders.$limits;						//  append rendered conditions, orders, limits, groupings and havings
		$resultSet	= $this->dbc->query( $query );
		if( $resultSet )
			return $resultSet->fetchAll( $this->getFetchMode() );
		return array();
	}

	public function findWhereIn( $columns, $column, $values, $orders = array(), $limits = array() )
	{
		if( !is_string( $columns ) && !is_array( $columns ) )										//  columns attribute needs to of string or array
			$columns	= array();																	//  otherwise use empty array
		$this->validateColumns( $columns );

		if( $column != $this->getPrimaryKey() && !in_array( $column, $this->getIndices() ) )
			throw new \InvalidArgumentException( 'Field of WHERE IN-statement must be an index' );

		$orders		= $this->getOrderCondition( $orders );
		$limits		= $this->getLimitCondition( $limits );
		for( $i=0; $i<count( $values ); $i++ )
			$values[$i]	= $this->secureValue( $values[$i] );

		$query		= 'SELECT '.implode( ', ', $columns ).' FROM '.$this->getTableName().' WHERE '.$column.' IN ('.implode( ', ', $values ).') '.$orders.$limits;
		$resultSet	= $this->dbc->query( $query );
		if( $resultSet )
			return $resultSet->fetchAll( $this->getFetchMode() );
		return array();
	}

	public function findWhereInAnd( $columns, $column, $values, $conditions = array(), $orders = array(), $limits = array() )
	{
		if( !is_string( $columns ) && !is_array( $columns ) )										//  columns attribute needs to of string or array
			$columns	= array();																	//  otherwise use empty array
		$this->validateColumns( $columns );

		if( $column != $this->getPrimaryKey() && !in_array( $column, $this->getIndices() ) )
			throw new \InvalidArgumentException( 'Field of WHERE IN-statement must be an index' );

		$conditions	= $this->getConditionQuery( $conditions, FALSE, FALSE, TRUE );					//  render WHERE clause if needed, uncursored, allow functions
		$orders		= $this->getOrderCondition( $orders );
		$limits		= $this->getLimitCondition( $limits );
		for( $i=0; $i<count( $values ); $i++ )
			$values[$i]	= $this->secureValue( $values[$i] );

		if( $conditions )
			$conditions	.= ' AND ';
		$query		= 'SELECT '.implode( ', ', $columns ).' FROM '.$this->getTableName().' WHERE '.$conditions.$column.' IN ('.implode( ', ', $values ).') '.$orders.$limits;
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
	 *	@return		void
	 */
	public function focusIndex( $column, $value )
	{
		if( !in_array( $column, $this->indices ) && $column != $this->primaryKey )				//  check column name
			throw new \InvalidArgumentException( 'Column "'.$column.'" is neither an index nor primary key and cannot be focused' );
		$this->focusedIndices[$column] = $value;													//  set Focus
	}

	/**
	 *	Setting focus on a primary key ID.
	 *	@access		public
	 *	@param		int			$id				Primary key ID to focus on
	 *	@param		bool		$clearIndices	Flag: clear all previously focuses indices
	 *	@return		void
	 */
	public function focusPrimary( $id, $clearIndices = TRUE )
	{
		if( $clearIndices )
			$this->focusedIndices	= array();
		$this->focusedIndices[$this->primaryKey] = $id;
	}

	/**
	 *	Returns data of focused keys.
	 *	@access		public
	 *	@param		bool	$first		Extract first entry of result
	 *	@param		array	$orders		Associative array of orders
	 *	@param		array	$limits		Array of offset and limit
	 *	@return		array
	 */
	public function get( $first = TRUE, $orders = array(), $limits = array() )
	{
		$this->validateFocus();
		$data = array();
		$conditions	= $this->getConditionQuery( array(), TRUE, TRUE, FALSE );						//  render WHERE clause if needed, cursored, without functions
		$orders		= $this->getOrderCondition( $orders );
		$limits		= $this->getLimitCondition( $limits );
		$columns	= "'". join( "', '", $this->columns )."'";
		$query = 'SELECT '.$columns.' FROM '.$this->getTableName().' WHERE '.$conditions.$orders.$limits;

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
	public function getColumns()
	{
		return $this->columns;
	}

	/**
	 *	Builds and returns WHERE statement component.
	 *	@access		protected
	 *	@param		array		$conditions		Array of conditions
	 *	@param		bool		$usePrimary		Flag: use focused primary key
	 *	@param		bool		$useIndices		Flag: use focused indices
	 *	@return		string
	 */
	protected function getConditionQuery( $conditions, $usePrimary = TRUE, $useIndices = TRUE, $allowFunctions = FALSE )
	{
		$columnConditions = array();
		foreach( $this->columns as $column )														//  iterate all columns
		{
			if( isset( $conditions[$column] ) )														//  if condition given
			{
				$columnConditions[$column] = $conditions[$column];									//  note condition pair
				unset( $conditions[$column] );
			}
		}
		$functionConditions = array();
		foreach( $conditions as $key => $value )													//  iterate remaining conditions
			if( preg_match( "/^[a-z]+\(.+\)$/i", $key ) )											//  column key is a aggregate function
				$functionConditions[$key]	= $value;

		if( $usePrimary && $this->isFocused( $this->primaryKey ) )									//  if using primary key & is focused primary
		{
			if( !array_key_exists( $this->primaryKey, $columnConditions ) )							//  if primary key is not already in conditions
				$columnConditions = $this->getFocus();												//  note primary key pair
		}
		if( $useIndices && count( $this->focusedIndices ) )											//  if using indices
		{
			foreach( $this->focusedIndices as $index => $value )									//  iterate focused indices
				if( $index != $this->primaryKey )													//  skip primary key
					if( !array_key_exists( $index, $columnConditions ) )							//  if index column is not already in conditions
						$columnConditions[$index] = $value;											//  note index pair
		}

		$conditions = array();																		//  restart with fresh conditions array

		foreach( $columnConditions as $column => $value )											//  iterate noted column conditions
		{
			if( is_array( $value ) )
			{
				foreach( $value as $nr => $part )
					$value[$nr]	= $this->realizeConditionQueryPart( $column, $part );
				$part	= '('.implode( ' OR ', $value ).')';
			}
			else
				$part	= $this->realizeConditionQueryPart( $column, $value );
			$conditions[]	= $part;

		}

		/*  --  THIS IS NEW, UNDER DEVELOPMENT, UNSECURE AND UNSTABLE  --  */
		if( $allowFunctions )																		//  function are allowed
			foreach( $functionConditions as $function => $value ){									//  iterate noted functions
				$conditions[]	= $this->realizeConditionQueryPart( $function, $value, FALSE );		//  extend conditions
			}

		return implode( ' AND ', $conditions );														//  return AND combined conditions
	}

	/**
	 *	Returns reference the database connection.
	 *	@access		public
	 *	@return		Object
	 */
	public function & getDBConnection()
	{
		return $this->dbc;
	}

	/**
	 *	Returns set fetch mode.
	 *	@access		public
	 *	@return		int			$fetchMode		Currently set fetch mode
	 */
	protected function getFetchMode()
	{
		return $this->fetchMode;
	}

	/**
	 *	Returns current primary focus or index focuses.
	 *	@access		public
	 *	@return		array
	 */
	public function getFocus()
	{
		return $this->focusedIndices;
	}

	/**
	 *	Returns all Indices of this Table.
	 *	@access		public
	 *	@return		array
	 */
	public function getIndices()
	{
		return $this->indices;
	}

	/**
	 *	Returns all Indices of this Table.
	 *	@access		public
	 *	@return		array
	 */
	public function getLastQuery()
	{
		return $this->dbc->lastQuery;
	}

	/**
	 *	Builds and returns ORDER BY Statement Component.
	 *	@access		protected
	 *	@param		array		$limits			Array of Offset and Limit
	 *	@return		string
	 */
	protected function getLimitCondition( $limits = array() )
	{
		if( !is_array( $limits ) )
			return;
		$limit		= !isset( $limits[1] ) ? 0 : abs( $limits[1] );
		$offset		= !isset( $limits[0] ) ? 0 : abs( $limits[0] );
		if( $limit )
			return ' LIMIT '.$limit.' OFFSET '.$offset;
	}

	/**
	 *	Builds and returns ORDER BY Statement Component.
	 *	@access		protected
	 *	@param		array		$orders			Associative Array with Orders
	 *	@return		string
	 */
	protected function getOrderCondition( $orders = array() )
	{
		$order	= '';
		if( is_array( $orders ) && count( $orders ) )
		{
			$list	= array();
			foreach( $orders as $column => $direction )
				$list[] = $column.' '.strtoupper( $direction );
			$order	= ' ORDER BY '.implode( ', ', $list );
		}
		return $order;
	}

	/**
	 *	Returns the name of the primary key column.
	 *	@access		public
	 *	@return		string
	 */
	public function getPrimaryKey()
	{
		return $this->primaryKey;
	}

	/**
	 *	Returns the name of the table.
	 *	@access		public
	 *	@return		string
	 */
	public function getTableName()
	{
		return $this->tableName;
	}

	/**
	 *	Indicates wheter the focus on a index (including primary key) is set.
	 *	@access		public
	 *	@return		string
	 */
	public function isFocused( $index = NULL )
	{
		if( !count( $this->focusedIndices ) )
			return FALSE;
		if( $index && !array_key_exists( $index, $this->focusedIndices ) )
			return FALSE;
		return TRUE;
	}

	protected function realizeConditionQueryPart( $column, $value, $maskColumn = TRUE )
	{
		$patternOperators	= '/^(<=|>=|<|>|!=)(.+)/';
		$patternBetween		= '/^(><|!><)([0-9]+)&([0-9]+)$/';
		if( preg_match( '/^%/', $value ) || preg_match( '/%$/', $value ) )
		{
			$operation	= ' LIKE ';
			$value		= $this->secureValue( $value );
		}
		else if( preg_match( $patternBetween, trim( $value ), $result ) )
		{
			$matches	= array();
			preg_match_all( $patternBetween, $value, $matches );
			$operation	= $matches[1][0] == '!><' ? ' NOT BETWEEN ' : ' BETWEEN ';
			$value		= $this->secureValue( $matches[2][0] ).' AND '.$this->secureValue( $matches[3][0] );
		}
		else if( preg_match( $patternOperators, $value, $result ) )
		{
			$matches	= array();
			preg_match_all( $patternOperators, $value, $matches );
			$operation	= ' '.$matches[1][0].' ';
			$value		= $this->secureValue( $matches[2][0] );
		}
		else
		{
			if( strtolower( $value ) == 'is null' || strtolower( $value ) == 'is not null')
				$operation	= ' ';
			else if( $value === NULL )
			{
				$operation	= ' IS ';
				$value		= 'NULL';
			}
			else
			{
				$operation	= ' = ';
				$value		= $this->secureValue( $value );
			}
		}
		$column	= $maskColumn ? '`'.$column.'`' : $column;
		return $column.$operation.$value;
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
	 *	Setting all columns of the table.
	 *	@access		public
	 *	@param		array		$columns	List of table columns
	 *	@return		void
	 *	@throws		Exception
	 */
	public function setColumns( $columns )
	{
		if( !( is_array( $columns ) && count( $columns ) ) )
			throw new \InvalidArgumentException( 'Column array must not be empty' );
		$this->columns = $columns;
	}

	/**
	 *	Setting a reference to a database connection.
	 *	@access		public
	 *	@param		PDO		$dbc		Database connection resource object
	 *	@return		void
	 */
	public function setDbConnection( $dbc )
	{
		if( !is_object( $dbc ) )
			throw new \InvalidArgumentException( 'Database connection resource must be an object' );
		if( !is_a( $dbc, 'PDO' ) )
			throw new \InvalidArgumentException( 'Database connection resource must be a direct or inherited PDO object' );
		$this->dbc = $dbc;
	}

	/**
	 *	Sets fetch mode.
	 *	Mode is a mandatory integer representing a PDO fetch mode.
	 *	@access		public
	 *	@param		int			$mode			PDO fetch mode
	 *	@see		http://www.php.net/manual/en/pdo.constants.php
	 *	@return		void
	 */
	public function setFetchMode( $mode )
	{
		$this->fetchMode	= $mode;
	}

	/**
	 *	Setting all indices of this table.
	 *	@access		public
	 *	@param		array		$indices		List of table indices
	 *	@return		bool
	 */
	public function setIndices( $indices )
	{
		foreach( $indices as $index )
		{
			if( !in_array( $index, $this->columns ) )
				throw new \InvalidArgumentException( 'Column "'.$index.'" is not existing in table "'.$this->tableName.'" and cannot be an index' );
			if( $index === $this->primaryKey )
				throw new \InvalidArgumentException( 'Column "'.$index.'" is already primary key and cannot be an index' );
		}
		$this->indices	= $indices;
		array_unique( $this->indices );
	}

	/**
	 *	Setting the name of the primary key.
	 *	@access		public
	 *	@param		string		$column		Pimary key column of this table
	 *	@return		void
	 */
	public function setPrimaryKey( $column )
	{
		if( !strlen( trim( $column ) ) )
			throw new \InvalidArgumentException( 'Primary key column cannot be empty' );
		if( !in_array( $column, $this->columns ) )
			throw new \InvalidArgumentException( 'Column "'.$column.'" is not existing and can not be primary key' );
		$this->primaryKey = $column;
	}

	/**
	 *	Setting the name of the table.
	 *	@access		public
	 *	@param		string		$tableName		Name of this table
	 *	@return		void
	 */
	public function setTableName( $tableName )
	{
		if( !strlen( trim( $tableName ) ) )
			throw new \InvalidArgumentException( 'Table name cannot be empty' );
		$this->tableName = $tableName;
	}

	/**
	 *	Setting the name of the table.
	 *	@access		public
	 *	@param		string		$tableName		Name of this table
	 *	@return		void
	 */
	public function setUndoStorage( $storage )
	{
		$this->undoStorage = $storage;
	}

	/**
	 *	Checks if a focus is set for following operation and throws an exception if not.
	 *	@access		protected
	 *	@throws		RuntimeException
	 *	@return		void
	 */
	protected function validateFocus()
	{
		if( !$this->isFocused() )
			throw new \RuntimeException( 'No Primary Key or Index focused for Table "'.$this->tableName.'"' );
	}

	/**
	 *	Checks columns names for querying methods (find,get), sets wildcard if empty or throws an exception if inacceptable.
	 *	@access		protected
	 *	@param		mixed		$columns			String or array of column names to validate
	 *	@return		void
	 */
	protected function validateColumns( &$columns )
	{
		if( is_string( $columns ) && $columns )
			$columns	= array( $columns );
		else if( is_array( $columns ) && !count( $columns ) )
			$columns	= $this->columns;
		else if( $columns === NULL || $columns == FALSE )
			$columns	= $this->columns;

		if( !is_array( $columns ) )
			throw new \InvalidArgumentException( 'Column keys must be an array of column names, a column name string or "*"' );
		foreach( $columns as $column )
			if( $column != '*' && !in_array( $column, $this->columns ) )
				throw new \InvalidArgumentException( 'Column key "'.$column.'" is not a valid column of table "'.$this->tableName.'"' );
	}
}
?>
