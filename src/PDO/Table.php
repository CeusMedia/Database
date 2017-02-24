<?php
/**
 *	Abstract database table.
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
 *	@package		CeusMedia_Database_PDO
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2007-2015 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
namespace CeusMedia\Database\PDO;
/**
 *	Abstract database table.
 *	@category		Library
 *	@package		CeusMedia_Database_PDO
 *	@uses			\CeusMedia\Database\PDO\Table\Writer
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2007-2015 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
abstract class Table{

	/**	@var		DB_PDO_Connection				$dbc			PDO database connection object */
	protected $dbc;
	/**	@var		string							$name			Name of Database Table without Prefix */
	protected $name									= "";
	/**	@var		array							$columns		List of Database Table Columns */
	protected $columns								= array();
	/**	@var		array							$name			List of foreign Keys of Database Table */
 	protected $indices								= array();
	/**	@var		string							$primaryKey		Primary Key of Database Table */
	protected $primaryKey							= "";
	/**	@var		Database_PDO_Table_Writer		$table			Database Table Writer Object for reading from and writing to Database Table */
	protected $table;
	/**	@var		string							$prefix			Database Table Prefix */
 	protected $prefix;
	/**	@var		ADT_List_Dictionary				$cache			Model data cache */
	protected $cache;
	/**	@var		integer							$fetchMode		PDO fetch mode */
	protected $fetchMode;

	public static $cacheClass						= 'ADT_List_Dictionary';

	/**
	 *	Constructor.
	 *	@access		public
	 *	@param		\CeusMedia\Database\PDO\Connection	$dbc		PDO database connection object
	 *	@param		string				$prefix		Table name prefix
	 *	@param		integer				$id			ID to focus on
	 *	@return		void
	 */
	public function __construct( \CeusMedia\Database\PDO\Connection $dbc, $prefix = NULL, $id = NULL ){
		$this->setDatabase( $dbc );
	}

	/**
	 *	Returns Data of single Line by ID.
	 *	@access		public
	 *	@param		array			$data			Data to add to Table
	 *	@param		boolean			$stripTags		Flag: strip HTML Tags from values
	 *	@return		integer
	 */
	public function add( $data, $stripTags = TRUE ){
		$id	= $this->table->insert( $data, $stripTags );
		$this->cache->set( $this->cacheKey.$id, $this->get( $id ) );
		return $id;
	}

	/**
	 *	Returns number of entries at all or for given conditions.
	 *	@access		public
	 *	@param		array			$conditions		Map of conditions
	 *	@return		integer			Number of entries
	 */
	public function count( $conditions = array() ){
		return $this->table->count( $conditions );
	}

	/**
	 *	Returns number of entries within an index.
	 *	@access		public
	 *	@param		string			$key			Index Key
	 *	@param		string			$value			Value of Index
	 *	@return		integer			Number of entries within this index
	 */
	public function countByIndex( $key, $value ){
		$conditions	= array( $key => $value );
		return $this->count( $conditions );
	}

	/**
	 *	Returns number of entries selected by map of indices.
	 *	@access		public
	 *	@param		array			$indices		Map of index conditions
	 *	@return		integer			Number of entries within this index
	 */
	public function countByIndices( $indices ){
		return $this->count( $indices );
	}

	/**
	 *	Modifies data of single row by ID.
	 *	@access		public
	 *	@param		integer			$id				ID to focus on
	 *	@param		array			$data			Data to edit
	 *	@param		boolean			$stripTags		Flag: strip HTML Tags from values
	 *	@return		integer			Number of changed rows
	 */
	public function edit( $id, $data, $stripTags = TRUE ){
		$this->table->focusPrimary( $id );
		$result	= 0;
		if( count( $this->table->get( FALSE ) ) )
			$result	= $this->table->update( $data, $stripTags );
		$this->table->defocus();
		$this->cache->remove( $this->cacheKey.$id );
		return $result;
	}

	public function editByIndices( $indices, $data ){
		if( !is_array( $indices ) )
			throw new \InvalidArgumentException( 'Index map must be an array' );
		if( !$indices )
			throw new \InvalidArgumentException( 'Index map cannot be empty' );
		return $this->table->updateByConditions( $data, $indices );
	}

	/**
	 *	Returns Data of single Line by ID.
	 *	@access		public
	 *	@param		integer			$id				ID to focus on
	 *	@param		string			$field			Single Field to return
	 *	@return		mixed
	 *	@todo		add arguments 'fields' using method 'getFieldsFromResult'
	 *	@todo		note throwable exceptions
	 */
	public function get( $id, $field = '' ){
		$data = $this->cache->get( $this->cacheKey.$id );
		if( !$data ){
			$this->table->focusPrimary( $id );
			$data	= $this->table->get( TRUE );
			$this->table->defocus();
			$this->cache->set( $this->cacheKey.$id, $data );
		}
		if( $field ){
			if( empty( $data ) )
				return $data;
			if( !in_array( $field, $this->columns ) )
				throw new \InvalidArgumentException( 'Field "'.$field.'" is not an existing column' );
			switch( $this->fetchMode ){
				case \PDO::FETCH_CLASS:
				case \PDO::FETCH_OBJ:
					return $data->$field;
				default:
					return $data[$field];
			}
		}
		return $data;
	}

	/**
	 *	Returns Data of all Lines.
	 *	@access		public
	 *	@param		array			$conditions		Map of Conditions to include in SQL Query
	 *	@param		array			$orders			Map of Orders to include in SQL Query
	 *	@param		array			$limits			Map of Limits to include in SQL Query
	 *	@param		array			$groupings		List of columns to group by
	 *	@param		array			$havings		List of conditions to apply after grouping
	 *	@return		array
	 */
	public function getAll( $conditions = array(), $orders = array(), $limits = array(), $columns = array(), $groupings = array(), $havings = array() ){
		return $this->table->find( $columns, $conditions, $orders, $limits, $groupings, $havings );
	}

	/**
	 *	Returns Data of all Lines selected by Index.
	 *	@access		public
	 *	@param		string			$key			Key of Index
	 *	@param		string			$value			Value of Index
	 *	@param		array			$orders			Map of Orders to include in SQL Query
	 *	@param		array			$limits			List of Limits to include in SQL Query
	 *	@return		array
	 *	@todo		add arguments 'fields' using method 'getFieldsFromResult'
	 *	@todo		OR add ...
	 */
	public function getAllByIndex( $key, $value, $orders = array(), $limits = array() ){
		$this->table->focusIndex( $key, $value );
		$data	= $this->table->get( FALSE, $orders, $limits );
		$this->table->defocus();
		return $data;
	}

	/**
	 *	Returns Data of all Lines selected by Indices.
	 *	@access		public
	 *	@param		array			$indices		Map of Index Keys and Values
	 *	@param		array			$conditions		Map of Conditions to include in SQL Query
	 *	@param		array			$orders			Map of Orders to include in SQL Query
	 *	@param		array			$limits			List of Limits to include in SQL Query
	 *	@return		array
	 *	@todo		add arguments 'fields' using method 'getFieldsFromResult'
	 *	@todo		note throwable exceptions
	 */
	public function getAllByIndices( $indices = array(), $orders = array(), $limits = array() ){
		if( !is_array( $indices ) )
			throw new \InvalidArgumentException( 'Index map must be an array' );
		if( !$indices )
			throw new \InvalidArgumentException( 'Index map must have atleast one pair' );
		foreach( $indices as $key => $value )
			$this->table->focusIndex( $key, $value );
		$data	= $this->table->get( FALSE, $orders, $limits );
		$this->table->defocus();
		return $data;
	}

	/**
	 *	Returns data of first entry selected by index.
	 *	@access		public
	 *	@param		string			$key			Key of Index
	 *	@param		string			$value			Value of Index
	 *	@param		string			$field			Single Field to return
	 *	@param		array			$orders			Map of Orders to include in SQL Query
	 *	@param		boolean			$strict			Flag: throw exception if result is empty (default: FALSE)
	 *	@return		mixed
	 *	@todo		change argument order: move fields to end
	 */
	public function getByIndex( $key, $value, $fields = array(), $orders = array(), $strict = FALSE ){
		$this->table->focusIndex( $key, $value );
		$data	= $this->table->get( TRUE, $orders );
		$this->table->defocus();
		return $this->getFieldsFromResult( $fields, $data, $strict );
	}

	/**
	 *	Returns data of single line selected by indices.
	 *	@access		public
	 *	@param		array			$indices		Map of Index Keys and Values
	 *	@param		array			$orders			Map of Orders to include in SQL Query
	 *	@param		string			$fields			List of fields or one field to return from result
	 *	@param		boolean			$strict			Flag: throw exception if result is empty (default: FALSE)
	 *	@return		mixed
	 *	@throws		InvalidArgumentException		if given indices is not an array
	 *	@throws		InvalidArgumentException		if given indices list is empty
	 *	@todo  		change default value of argument 'strict' to TRUE
	 */
	public function getByIndices( $indices, $orders = array(), $fields = array(), $strict = FALSE ){
		if( !is_array( $indices ) )
			throw new \InvalidArgumentException( 'Index map must be an array' );
		if( !$indices )
			throw new \InvalidArgumentException( 'Index map must have atleast one pair' );
		foreach( $indices as $key => $value )
			$this->table->focusIndex( $key, $value );
		$data	= $this->table->get( TRUE, $orders );
		$this->table->defocus();
		return $this->getFieldsFromResult( $result, $fields, $strict );
	}

	/**
	 *	Returns list of table columns.
	 *	@access		public
	 *	@return		array
	 */
	public function getColumns(){
		return $this->table->getColumns();
	}

	/**
	 *	Returns any fields or one field from a query result.
	 *	@access		protected
	 *	@param		mixed			$result			Query result as array or object
	 *	@param		array|string	$fields			List of fields or one field
	 *	@param		boolean			$strict			Flag: throw exception if result is empty
	 */
	protected function getFieldsFromResult( $result, $fields = array(), $strict = TRUE ){
		if( is_string( $fields ) )
			$fields	= strlen( trim( $fields ) ) ? array( trim( $fields ) ) : array();
		if( !is_array( $fields ) )
			throw new \InvalidArgumentException( 'Fields must be of array or string' );
		if( !$result ){
			if( $strict )
				throw new \Exception( 'Result is empty' );
			if( count( $fields ) === 1 )
				return NULL;
			return array();
		}
		if( count( $fields ) ){
			foreach( $fields as $field )
				if( !in_array( $field, $this->columns ) )
					throw new \InvalidArgumentException( 'Field "'.$field.'" is not an existing column' );
		}
		$fields	= $this->columns;

		if( count( $fields ) === 1 ){
			switch( $this->fetchMode ){
				case \PDO::FETCH_CLASS:
				case \PDO::FETCH_OBJ:
					return $data->$field;
				default:
					return $data[$field];
			}
		}
		switch( $this->fetchMode ){
			case \PDO::FETCH_CLASS:
			case \PDO::FETCH_OBJ:
				$map	= (object) array();
				foreach( $fields as $field )
					$map->$field	= $data->$field;
				return $map;
			default:
				$list	= array();
				foreach( $fields as $field )
					$list[$field]	= $data[$field];
				return $list;
		}
	}

	public function getLastQuery(){
		return $this->table->getLastQuery();
	}

	/**
	 *	Returns table name with or without index.
	 *	@access		public
	 *	@param		boolean			$prefixed		Flag: return table name with prefix
	 *	@return		string			Table name with or without prefix
	 */
	public function getName( $prefixed = TRUE ){
		if( $prefixed )
			return $this->prefix.$this->name;
		return $this->name;
	}

	/**
	 *	Indicates whether a table row is existing by ID.
	 *	@param		integer			$id				ID to focus on
	 *	@return		boolean
	 */
	public function has( $id ){
		if( $this->cache->has( $this->cacheKey.$id ) )
			return TRUE;
		return (bool) $this->get( $id );
	}

	/**
	 *	Indicates whether a table row is existing by index.
	 *	@access		public
	 *	@param		string			$key			Key of Index
	 *	@param		string			$value			Value of Index
	 *	@return		boolean
	 */
	public function hasByIndex( $key, $value ){
		return (bool) $this->getByIndex( $key, $value );
	}

	/**
	 *	Indicates whether a Table Row is existing by a Map of Indices.
	 *	@access		public
	 *	@param		array			$indices		Map of Index Keys and Values
	 *	@return		boolean
	 */
	public function hasByIndices( $indices ){
		return (bool) $this->getByIndices( $indices );
	}

	/**
	 *	Returns Data of single Line by ID.
	 *	@access		public
	 *	@param		integer			$id				ID to focus on
	 *	@return		boolean
	 */
	public function remove( $id ){
		$this->table->focusPrimary( $id );
		$result	= FALSE;
		if( count( $this->table->get( FALSE ) ) ){
			$this->table->delete();
			$result	= TRUE;
		}
		$this->table->defocus();
		$this->cache->remove( $this->cacheKey.$id );
		return $result;
	}

	/**
	 *	Removes entries selected by index.
	 *	@access		public
	 *	@param		string			$key			Key of Index
	 *	@param		string			$value			Value of Index
	 *	@return		boolean
	 */
	public function removeByIndex( $key, $value ){
		$this->table->focusIndex( $key, $value );
		$result	= FALSE;
		$rows	= $this->table->get( FALSE );
		if( count( $rows ) ){
			$this->table->delete();
			foreach( $rows as $row ){
				switch( $this->fetchMode ){
					case \PDO::FETCH_CLASS:
					case \PDO::FETCH_OBJ:
						$id	= $row->{$this->primaryKey};
						break;
					default:
						$id	= $row[$this->primaryKey];
				}
				$this->cache->remove( $this->cacheKey.$id );
			}
			$result	= TRUE;
		}
		$this->table->defocus();
		return $result;
	}

	/**
	 *	Removes entries selected by index.
	 *	@access		public
	 *	@param		array			$indices		Map of Index Keys and Values
	 *	@return		integer			Number of removed entries
	 */
	public function removeByIndices( $indices ){
		if( !is_array( $indices ) )
			throw new \InvalidArgumentException( 'Index map must be an array' );
		if( !$indices )
			throw new \InvalidArgumentException( 'Index map cannot be empty' );
		foreach( $indices as $key => $value )
			$this->table->focusIndex( $key, $value );

		$rows	= $this->table->get( FALSE );
		if( count( $rows ) ){
			$number	= $this->table->delete();
			foreach( $rows as $row ){
				switch( $this->fetchMode ){
					case \PDO::FETCH_CLASS:
					case \PDO::FETCH_OBJ:
						$id	= $row->{$this->primaryKey};
						break;
					default:
						$id	= $row[$this->primaryKey];
				}
				$this->cache->remove( $this->cacheKey.$id );
			}
		}
		$this->table->defocus();
		return $number;
	}

	public function setCache( \CeusMedia\Cache\AdapterInterface $cache ){
		$this->cache	= $cache;
	}

	/**
	 *	Sets Environment of Controller by copying Framework Member Variables.
	 *	@access		public
	 *	@param		\CeusMedia\Database\PDO\Connection	$dbc		PDO database connection object
	 *	@param		string				$prefix		Table name prefix
	 *	@param		integer				$id			ID to focus on
	 *	@return		void
	 */
	public function setDatabase( \CeusMedia\Database\PDO\Connection $dbc, $prefix = NULL, $id = NULL ){
		$this->dbc		= $dbc;
		$this->prefix	= (string) $prefix;
		$this->table	= new \CeusMedia\Database\PDO\Table\Writer(
			$dbc,
			$this->prefix.$this->name,
			$this->columns,
			$this->primaryKey,
			$id
		);
		if( $this->fetchMode )
			$this->table->setFetchMode( $this->fetchMode );
		$this->table->setIndices( $this->indices );
		$this->cache	= \Alg_Object_Factory::createObject( self::$cacheClass );
		$this->cacheKey	= 'db.'.$this->prefix.$this->name.'.';
	}

	public function setUndoStorage( $storage ){
		$this->table->setUndoStorage( $storage );
	}

	/**
	 *	Removes all data and resets incremental counter.
	 *	Note: This method does not return the number of removed rows.
	 *	@access		public
	 *	@return		void
	 *	@see		http://dev.mysql.com/doc/refman/4.1/en/truncate.html
	 */
	public function truncate(){
		$this->table->truncate();
	}
}
?>
