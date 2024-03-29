<?php /** @noinspection PhpMultipleClassDeclarationsInspection */
/** @noinspection PhpUnused */

/**
 *	Abstract database table.
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
 *	@package		CeusMedia_Database_PDO
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2007-2020 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
namespace CeusMedia\Database\PDO;

use CeusMedia\Cache\Adapter\Noop as NoopCache;
use CeusMedia\Common\Alg\Obj\Factory as ObjectFactory;
use CeusMedia\Database\PDO\Table\Writer as TableWriter;
use Psr\SimpleCache\CacheInterface as SimpleCacheInterface;

use DomainException;
use InvalidArgumentException;
use PDO;
use Psr\SimpleCache\InvalidArgumentException as SimpleCacheInvalidArgumentException;
use RangeException;
use ReflectionException;
use RuntimeException;

/**
 *	Abstract database table.
 *	@category		Library
 *	@package		CeusMedia_Database_PDO
 *	@uses			TableWriter
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2007-2020 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
abstract class Table
{
	/**	@var	Connection|NULL					$dbc			PDO database connection object */
	protected ?Connection $dbc;

	/**	@var	string							$name			Name of Database Table without Prefix */
	protected string $name						= '';

	/**	@var	array							$columns		List of Database Table Columns */
	protected array $columns					= [];

	/**	@var	array							$indices		List of foreign Keys of Database Table */
	protected array $indices					= [];

	/**	@var	string							$primaryKey		Primary Key of Database Table */
	protected string $primaryKey				= '';

	/**	@var	TableWriter						$table			Database Table Writer Object for reading from and writing to Database Table */
	protected TableWriter $table;

	/**	@var	string							$prefix			Database Table Prefix */
	protected string $prefix					= '';

	/**	@var	SimpleCacheInterface			$cache			Model data cache */
	protected SimpleCacheInterface $cache;

	/**	@var	SimpleCacheInterface|null		$cacheInstance	Cache adapter instance to use as cache by default */
	public static ?SimpleCacheInterface $cacheInstance		= NULL;

	/** @var	string							$cacheClass		Name of default cache adapter class */
	public static string $cacheClass			= NoopCache::class;

	/** @var	mixed							$cacheResource	Resource to connect to by cache adapter */
	public static $cacheResource				= NULL;

	/** @var	string							$cacheKey		Prefix of cache key */
	protected string $cacheKey					= '';

	/**	@var	integer							$fetchMode		PDO fetch mode, default: PDO::FETCH_OBJ */
	protected int $fetchMode					= PDO::FETCH_OBJ;

	/**
	 *	Constructor.
	 *	@access		public
	 *	@param		Connection		$dbc		PDO database connection object
	 *	@param		?string			$prefix		Table name prefix
	 *	@param		?integer		$id			ID to focus on
	 *	@return		void
	 *	@throws		ReflectionException
	 */
	public function __construct( Connection $dbc, ?string $prefix = NULL, ?int $id = NULL )
	{
		$this->checkTableSetup();
		$this->setDatabase( $dbc, $prefix, $id );
		$this->setupCache();
	}

	/**
	 *	Returns Data of single Line by ID.
	 *	@access		public
	 *	@param		array			$data			Data to add to Table
	 *	@param		boolean			$stripTags		Flag: strip HTML Tags from values
	 *	@return		integer
	 *	@throws		SimpleCacheInvalidArgumentException
	 */
	public function add( array $data, bool $stripTags = TRUE ): int
	{
		$id	= $this->table->insert( $data, $stripTags );
		$this->cache->set( $this->cacheKey.$id, serialize( $this->get( $id ) ) );
		return $id;
	}

	/**
	 *	Returns number of entries at all or for given conditions.
	 *	@access		public
	 *	@param		array			$conditions		Map of conditions
	 *	@return		integer			Number of entries
	 */
	public function count( array $conditions = [] ): int
	{
		return $this->table->count( $conditions );
	}

	/**
	 *	Returns number of entries within an index.
	 *	@access		public
	 *	@param		string			$key			Index Key
	 *	@param		string|array	$value			Value(s) of Index
	 *	@return		integer			Number of entries within this index
	 */
	public function countByIndex( string $key, $value ): int
	{
		return $this->table->count( [$key => $value] );
	}

	/**
	 *	Returns number of entries selected by map of indices.
	 *	@access		public
	 *	@param		array			$indices		Map of index conditions
	 *	@return		integer			Number of entries within this index
	 */
	public function countByIndices( array $indices ): int
	{
		return $this->count( $indices );
	}

	/**
	 *	Returns number of entries of a large table by map of conditions.
	 *	Attention: The returned number may be inaccurate, but this is much faster.
	 *	@access		public
	 *	@param		array			$conditions		Map of conditions
	 *	@return		integer			Number of entries
	 */
	public function countFast( array $conditions ): int
	{
		return $this->table->countFast( $conditions );
	}

	/**
	 *	Modifies data of single row by ID.
	 *	@access		public
	 *	@param		integer			$id				ID to focus on
	 *	@param		array			$data			Data to edit
	 *	@param		boolean			$stripTags		Flag: strip HTML Tags from values
	 *	@return		integer			Number of changed rows
	 *	@throws		SimpleCacheInvalidArgumentException
	 */
	public function edit( int $id, array $data, bool $stripTags = TRUE ): int
	{
		$this->table->focusPrimary( $id );
		$result	= 0;
		if( $this->table->get() !== NULL )
			$result	= $this->table->update( $data, $stripTags );
		$this->table->defocus();
		$this->cache->delete( $this->cacheKey.$id );
		return $result;
	}

	/**
	 *	Modifies data of several rows by indices.
	 *	@access		public
	 *	@param		array			$indices		Map of Index Keys and Values
	 *	@param		array			$data			Data to edit
	 *	@param		boolean			$stripTags		Flag: strip HTML Tags from values
	 *	@return		integer			Number of changed rows
	 */
	public function editByIndices( array $indices, array $data, bool $stripTags = TRUE ): int
	{
		$this->checkIndices( $indices, TRUE );
		return $this->table->updateByConditions( $data, $indices, $stripTags );
	}

	/**
	 *	Returns Data of single Line by ID.
	 *	@access		public
	 *	@param		integer			$id				ID to focus on
	 *	@param		string			$field			Single Field to return
	 *	@return		object|array|string|int|float|bool|NULL
	 *	@throws		SimpleCacheInvalidArgumentException
	 */
	public function get( int $id, string $field = '' )
	{
		/** @var string $field */
		$field		= $this->checkField( $field );
		$cacheData	= $this->cache->get($this->cacheKey . $id );
		if( is_string( $cacheData ) )
			/** @var object|array $data */
			$data = unserialize( $cacheData );
		else{
			$this->table->focusPrimary( $id );
			/** @var object|array $data */
			$data	= $this->table->get();
			$this->table->defocus();
			$this->cache->set( $this->cacheKey.$id, serialize( $data ) );
		}
		if( strlen( trim( $field ) ) !== 0 )
			return $this->getFieldFromResult( $data, $field );
		return $data;
	}

	/**
	 *	Returns Data of all Lines.
	 *	@access		public
	 *	@param		array			$conditions		Map of Conditions to include in SQL Query
	 *	@param		array			$orders			Map of Orders to include in SQL Query
	 *	@param		array			$limits			Map of Limits to include in SQL Query
	 *	@param		array			$fields			Map of Columns to include in SQL Query
	 *	@param		array			$groupings		List of columns to group by
	 *	@param		array			$having			List of conditions to apply after grouping
	 *	@param		boolean			$strict			Flag: throw exception if result is empty and fields are selected (default: FALSE)
	 *	@return		array
	 */
	public function getAll( array $conditions = [], array $orders = [], array $limits = [], array $fields = [], array $groupings = [], array $having = [], bool $strict = FALSE ): array
	{
		$data	= $this->table->find( $fields, $conditions, $orders, $limits, $groupings, $having );
		if( count( $fields ) !== 0 ){
			foreach( $data as $nr => $set ){
				if( count( $fields ) === 1 )
					$data[$nr]	= $this->getFieldFromResult( $set, $fields[0], $strict );
				else
					$data[$nr]	= $this->getFieldsFromResult( $set, $fields, $strict );
			}
		}
		return $data;
	}

	/**
	 *	Returns Data of all Lines selected by Index.
	 *	@access		public
	 *	@param		string			$key			Key of Index
	 *	@param		string|array	$value			Value(s) of Index
	 *	@param		array			$orders			Map of Orders to include in SQL Query
	 *	@param		array			$limits			List of Limits to include in SQL Query
	 *	@param		array			$fields			List of fields or one field to return from result
	 *	@param		boolean			$strict			Flag: throw exception if result is empty and fields are selected (default: FALSE)
	 *	@return		array
	 */
	public function getAllByIndex( string $key, $value, array $orders = [], array $limits = [], array $fields = [], bool $strict = FALSE ): array
	{
		if( !in_array( $key, $this->table->getIndices(), TRUE ) )
			throw new DomainException( 'Requested column "'.$key.'" is not an index' );
		$conditions	= [$key => $value];
		return $this->getAll( $conditions, $orders, $limits, $fields, [], [], $strict );
	}

	/**
	 *	Returns Data of all Lines selected by Indices.
	 *	@access		public
	 *	@param		array			$indices		Map of Index Keys and Values
	 *	@param		array			$orders			Map of Orders to include in SQL Query
	 *	@param		array			$limits			List of Limits to include in SQL Query
	 *	@param		array			$fields			List of fields or one field to return from result
	 *	@param		boolean			$strict			Flag: throw exception if result is empty and fields are selected (default: FALSE)
	 *	@return		array
	 */
	public function getAllByIndices( array $indices = [], array $orders = [], array $limits = [], array $fields = [], bool $strict = FALSE ): array
	{
		$this->checkIndices( $indices, TRUE );
		foreach( $indices as $key => $value )
			$this->table->focusIndex( $key, $value );
		/** @var array $data */
		$data	= $this->table->get( FALSE, $orders, $limits );
		$this->table->defocus();
		if( count( $fields ) > 0 )
			foreach( $data as $nr => $set ){
				if( count( $fields ) === 1 )
					$data[$nr]	= $this->getFieldFromResult( $set, current( $fields ), $strict );
				else
					$data[$nr]	= $this->getFieldsFromResult( $set, $fields, $strict );
			}
		return $data;
	}

	/**
	 *	Returns data of first entry selected by index.
	 *	@access		public
	 *	@param		string				$key			Key of Index
	 *	@param		string|array		$value			Value(s) of Index
	 *	@param		array				$orders			Map of Orders to include in SQL Query
	 *	@param		array|string		$fields			List of fields or one field to return from result
	 *	@param		boolean				$strict			Flag: throw exception if result is empty (default: FALSE)
	 *	@return		object|array|string|int|float|bool|NULL	Structure depending on fetch type, string if field selected, NULL if field selected and no entries
	 *	@todo		change argument order: move fields to end
	 *	@throws		InvalidArgumentException		If given fields list is neither a list nor a string
	 */
	public function getByIndex( string $key, $value, array $orders = [], $fields = [], bool $strict = FALSE )
	{
		if( is_string( $fields ) )
			$fields	= strlen( trim( $fields ) ) > 0 ? array( trim( $fields ) ) : [];
		if( !is_array( $fields ) )
			throw new InvalidArgumentException( 'Fields must be of array or string' );
		foreach( $fields as $field )
			$this->checkField( $field );
		$this->table->focusIndex( $key, $value );
		/** @var object|array $data */
		$data	= $this->table->get( TRUE, $orders );
		$this->table->defocus();
		if( count( $fields ) === 1 )
			return $this->getFieldFromResult( $data, current( $fields ), $strict );
		return $this->getFieldsFromResult( $data, $fields, $strict );
	}

	/**
	 *	Returns data of single line selected by indices.
	 *	@access		public
	 *	@param		array				$indices		Map of Index Keys and Values
	 *	@param		array				$orders			Map of Orders to include in SQL Query
	 *	@param		array|string		$fields			List of fields or one field to return from result
	 *	@param		boolean				$strict			Flag: throw exception if result is empty (default: FALSE)
	 *	@return		object|array|string|int|float|bool|NULL	Structure depending on fetch type, string if field selected, NULL if field selected and no entries
	 *	@throws		InvalidArgumentException		If given fields list is neither a list nor a string
	 *	@todo  		change default value of argument 'strict' to TRUE
	 */
	public function getByIndices( array $indices, array $orders = [], $fields = [], bool $strict = FALSE )
	{
		if( is_string( $fields ) )
			$fields	= strlen( trim( $fields ) ) > 0 ? array( trim( $fields ) ) : [];
		if( !is_array( $fields ) )
			throw new InvalidArgumentException( 'Fields must be of array or string' );
		foreach( $fields as $nr => $field )
			$fields[$nr]	= $this->checkField( $field );
		$this->checkIndices( $indices, TRUE );
		foreach( $indices as $key => $value )
			$this->table->focusIndex( $key, $value );
		/** @var object|array $result */
		$result	= $this->table->get( TRUE, $orders );
		$this->table->defocus();
		if( count( $fields ) === 1 )
			return $this->getFieldFromResult( $result, current( $fields ), $strict );
		return $this->getFieldsFromResult( $result, $fields, $strict );
	}

	/**
	 *	Returns list of table columns.
	 *	@access		public
	 *	@return		array
	 */
	public function getColumns(): array
	{
		return $this->table->getColumns();
	}

	/**
	 *	Returns list of table index columns.
	 *	@access		public
	 *	@return		array
	 */
	public function getIndices(): array
	{
		return $this->table->getIndices();
	}

	/**
	 *	Returns last statement.
	 *	@access		public
	 *	@return		?string
	 */
	public function getLastQuery(): ?string
	{
		return $this->table->getLastQuery();
	}

	/**
	 *	Returns table name with or without index.
	 *	@access		public
	 *	@param		boolean			$prefixed		Flag: return table name with prefix
	 *	@return		string			Table name with or without prefix
	 */
	public function getName( bool $prefixed = TRUE ): string
	{
		if( $prefixed )
			return $this->prefix.$this->name;
		return $this->name;
	}

	/**
	 *	Returns primary key columns name of table.
	 *	@access		public
	 *	@return		string			Primary key column name
	 */
	public function getPrimaryKey(): string
	{
		return $this->table->getPrimaryKey();
	}

	/**
	 *	Indicates whether a table row is existing by ID.
	 *	@param		integer			$id				ID to focus on
	 *	@return		boolean
	 *	@throws		SimpleCacheInvalidArgumentException
	 */
	public function has( int $id ): bool
	{
		if( $this->cache->has( $this->cacheKey.$id ) )
			return TRUE;
		return (bool) $this->get( $id );
	}

	/**
	 *	Indicates whether a table row is existing by index.
	 *	@access		public
	 *	@param		string			$key			Key of Index
	 *	@param		string|array	$value			Value(s) of Index
	 *	@return		boolean
	 */
	public function hasByIndex( string $key, $value ): bool
	{
		return (bool) $this->getByIndex( $key, $value );
	}

	/**
	 *	Indicates whether a Table Row is existing by a Map of Indices.
	 *	@access		public
	 *	@param		array			$indices		Map of Index Keys and Values
	 *	@return		boolean
	 */
	public function hasByIndices( array $indices ): bool
	{
		return (bool) $this->getByIndices( $indices );
	}

	/**
	 *	Returns Data of single Line by ID.
	 *	@access		public
	 *	@param		integer			$id				ID to focus on
	 *	@return		boolean
	 *	@throws		SimpleCacheInvalidArgumentException
	 */
	public function remove( int $id ): bool
	{
		$this->table->focusPrimary( $id );
		$result	= FALSE;
		/** @var array $found */
		$found	= $this->table->get( FALSE );
		if( count( $found ) === 1 ){
			$this->table->delete();
			$result	= TRUE;
		}
		$this->table->defocus();
		$this->cache->delete( $this->cacheKey.$id );
		return $result;
	}

	/**
	 *	Removes entries selected by index.
	 *	@access		public
	 *	@param		string			$key			Key of Index
	 *	@param		string|array	$value			Value(s) of Index
	 *	@return		integer
	 *	@throws		SimpleCacheInvalidArgumentException
	 */
	public function removeByIndex( string $key, $value ): int
	{
		$this->table->focusIndex( $key, $value );
		$number	= $this->removeBySetFocus();
		$this->table->defocus();
		return $number;
	}

	/**
	 *	Removes entries selected by index.
	 *	@access		public
	 *	@param		array			$indices		Map of Index Keys and Values
	 *	@return		integer			Number of removed entries
	 *	@throws		SimpleCacheInvalidArgumentException
	 */
	public function removeByIndices( array $indices ): int
	{
		$this->checkIndices( $indices, TRUE );
		foreach( $indices as $key => $value )
			$this->table->focusIndex( $key, $value );
		$number	= $this->removeBySetFocus();
		$this->table->defocus();
		return $number;
	}

	public function setCache( SimpleCacheInterface $cache ): self
	{
		$this->cache	= $cache;
		return $this;
	}

/*	public function setUndoStorage( $storage ): self
	{
		$this->table->setUndoStorage( $storage );
		return $this;
	}*/

	/**
	 *	Removes all data and resets incremental counter.
	 *	Note: This method does not return the number of removed rows.
	 *	@access		public
	 *	@return		void
	 *	@see		http://dev.mysql.com/doc/refman/4.1/en/truncate.html
	 */
	public function truncate()
	{
		$this->table->truncate();
	}

	//  --  PROTECTED  --  //

	/**
	 *	Indicates whether a requested field is a table column.
	 *	Returns trimmed field key if found, otherwise FALSE if not a string or not a table column.
	 *	Returns FALSE if empty and mandatory, otherwise NULL.
	 *	In strict mode exceptions will be thrown if field is not a string, empty but mandatory or not a table column.
	 *	@access		protected
	 *	@param		string			$field			Table Column to check for existence
	 *	@param		boolean			$mandatory		Force a value, otherwise return NULL or throw exception in strict mode
	 *	@param		boolean			$strict			Strict mode (default): throw exception instead of returning FALSE or NULL
	 *	@return		string|NULL|FALSE				Trimmed Field name if found, NULL otherwise or exception in strict mode
	 *	@throws		InvalidArgumentException		in strict mode if field is not a string and strict mode is on
	 *	@throws		RangeException					in strict mode if field is empty but mandatory
	 *	@throws		DomainException					in strict mode if field is not a table column
	 */
	protected function checkField( string $field, bool $mandatory = FALSE, bool $strict = TRUE )
	{
		$field	= trim( $field );
		if( strlen( $field ) === 0 ){
			if( $mandatory ){
				if( !$strict )
					return FALSE;
				throw new RangeException( 'Field must have a value' );
			}
			return NULL;
		}
		if( !in_array( $field, $this->columns, TRUE ) ){
			if( !$strict )
				return FALSE;
			$message	= 'Field "%s" is not an existing column of table %s';
			throw new DomainException( sprintf( $message, $field, $this->getName() ) );
		}
		return $field;
	}

	/**
	 *	Indicates whether a given map of indices is valid.
	 *	Returns map if valid or FALSE if not an array or empty but mandatory.
	 *	In strict mode exceptions will be thrown if map is not an array or empty but mandatory.
	 *	FYI: The next logical check - if index keys are valid columns and noted indices - is done by used table reader class.
	 *	@access		protected
	 *	@param		array			$indices		Map of Index Keys and Values
	 *	@param		boolean			$mandatory		Force at least one pair, otherwise return FALSE or throw exception in strict mode
	 *	@param		boolean			$strict			Strict mode (default): throw exception instead of returning FALSE
	 *	@param		boolean			$withPrimaryKey	Flag: include table primary key within index list
	 *	@return		array|boolean	Map if valid, FALSE otherwise or exceptions in strict mode
	 *	@throws		InvalidArgumentException		in strict mode if field is not a string
	 *	@throws		RangeException					in strict mode if field is empty but mandatory
	 *	@throws		DomainException					in strict mode if field is not an index
	 */
	protected function checkIndices( array $indices, bool $mandatory = FALSE, bool $strict = TRUE, bool $withPrimaryKey = FALSE )
	{
		if( count( $indices ) === 0 ){
			if( $mandatory ){
				if( !$strict )
					return FALSE;
				throw new RangeException( 'Index map must have at least one pair' );
			}
		}

		$list		= [];
		$indexList	= $this->table->getIndices( $withPrimaryKey );
		foreach( $indices as $index => $value ){
			if( !in_array( $index, $indexList, TRUE ) ){
				if( $strict )
					throw new DomainException( 'Column "'.$index.'" is not an index' );
				return FALSE;
			}
			$list[$index]	= $value;
		}
		return $list;
	}

	/**
	 *	Returns any fields or one field from a query result.
	 *	@access		protected
	 *	@param		object|array				$result			Query result as array or object
	 *	@param		string						$field			Field to return value of
	 *	@param		boolean						$strict			Flag: throw exception if result is empty
	 *	@return		string|int|float|bool|NULL	Structure depending on result and field list length
	 *	@throws		RangeException				If given result list is empty
	 *	@throws		DomainException				If requested field is not a table column
	 *	@throws		RangeException				If requested field is not within result fields
	 */
	protected function getFieldFromResult( $result, string $field, bool $strict = TRUE )
	{
		if( is_null( $result ) || is_array( $result ) && count( $result ) === 0 ){
			if( $strict )
				throw new RangeException( 'Result is empty' );
			return NULL;
		}
		if( !in_array( $field, $this->columns, TRUE ) )
			throw new DomainException( 'Field "'.$field.'" is not an existing column' );

		if( preg_match( '/^(.+) AS (.+)$/i', $field, $matches ) === 1 ){
			if( in_array( $matches[2], $this->columns, TRUE ) )
				throw new DomainException( 'Field "'.$field.'" is not possible since '.$matches[2].' is a column' );
			$field	= $matches[2];
		}

		if( in_array( $this->fetchMode, [PDO::FETCH_CLASS, PDO::FETCH_OBJ], TRUE ) ){
			/** @var object $result */
			if( !property_exists( $result, $field ) )
				throw new RangeException( 'Field "'.$field.'" is not an column of result set' );
			/** @var array<string,string|int|float|bool|NULL> $values */
			$values	= get_object_vars( $result );
			return $values[$field];
		}

		/** @var array $result */
		if( !isset( $result[$field] ) )
			throw new RangeException( 'Field "'.$field.'" is not an column of result set' );
		return $result[$field];
	}

	/**
	 *	Returns any fields from a query result.
	 *	@access		protected
	 *	@param		object|array				$result			Query result as array or object
	 *	@param		string[]					$fields			List of fields
	 *	@param		boolean						$strict			Flag: throw exception if result is empty
	 *	@return		string|int|float|bool|array|object|NULL	Structure depending on result and field list length
	 *	@throws		InvalidArgumentException		If given fields list is neither a list nor a string
	 *	@throws		RangeException					If given result list is empty
	 *	@throws		DomainException					If requested field is not a table column
	 *	@throws		RangeException					If requested field is not within result fields
	 */
	protected function getFieldsFromResult( $result, array $fields = [], bool $strict = TRUE )
	{
		if( count( $fields ) === 0 )
			return $result;
		if( count( $fields ) === 1 )
			return $this->getFieldFromResult( $result, current( $fields ) );

		if( is_null( $result ) || is_array( $result ) && count( $result ) === 0 ){
			if( $strict )
				throw new RangeException( 'Result is empty' );
			return [];
		}

		foreach( $fields as $nr => $field )
			if( $field === '*' )
				array_splice( $fields, $nr, 1, $this->columns );

		foreach( $fields as $nr => $field ){
			if( preg_match( '/^(.+) AS (.+)$/i', $field, $matches ) === 1 ){
				if( in_array( $matches[2], $this->columns, TRUE ) )
					throw new DomainException( 'Field "'.$field.'" is not possible since '.$matches[2].' is a column' );
				$fields[$nr]	= $matches[2];
			}
			else if( !in_array( $field, $this->columns, TRUE ) )
				throw new DomainException( 'Field "'.$field.'" is not an existing column' );
		}

		if( in_array( $this->fetchMode, [PDO::FETCH_CLASS, PDO::FETCH_OBJ], TRUE ) ) {
			/** @var object $result */
			$map = (object)[];
			foreach ($fields as $field) {
				if (!property_exists($result, $field))
					throw new RangeException('Field "' . $field . '" is not an column of result set');
				$values = get_object_vars($result);
				$map->$field = $values[$field];
			}
			return $map;
		}

		/** @var array $result */
		$list	= [];
		foreach( $fields as $field ){
			if( $field !== '*' && !isset( $result[$field] ) )
				throw new RangeException( 'Field "'.$field.'" is not an column of result set' );
			$list[$field]	= $result[$field];
		}
		return $list;
	}

	/**
	 *	@access		protected
	 *	@return		self
	 *	@throws		ReflectionException
	 */
	protected function setupCache(): self
	{
		if( self::$cacheInstance instanceof SimpleCacheInterface)
			$this->cache	= self::$cacheInstance;
		else{
			$cacheFactory	= new ObjectFactory( [self::$cacheResource] );
			/** @var SimpleCacheInterface $cacheInstance */
			/** @noinspection PhpUnhandledExceptionInspection */
			$cacheInstance	= $cacheFactory->create( self::$cacheClass );
			$this->cache	= $cacheInstance;
		}
		$this->cacheKey	= 'db.'.$this->prefix.$this->name.'.';
		return $this;
	}

	/**
	 *	@access		protected
	 *	@param		Connection		$dbc		PDO database connection object
	 *	@param		string|NULL		$prefix		Table name prefix
	 *	@param		integer|NULL	$id			ID to focus on
	 *	@return		self
	 */
	protected function setDatabase( Connection $dbc, ?string $prefix = NULL, ?int $id = NULL ): self
	{
		$this->dbc = $dbc;
		$this->prefix = (string) $prefix;
		$this->table = new TableWriter(
			$dbc,
			$this->prefix . $this->name,
			$this->columns,
			$this->primaryKey,
			$id
		);
		if ($this->fetchMode > 0)
			$this->table->setFetchMode($this->fetchMode);
		$this->table->setIndices( $this->indices );
		return $this;
	}

	//  --  PRIVATE  --  //

	private function checkTableSetup(): void
	{
		if( strlen( trim( $this->name ) ) === 0 )
			throw new RuntimeException( 'No table name set' );
		if( count( $this->columns ) === 0 )
			throw new RuntimeException( 'No table columns set' );
	}

	/**
	 *	@return		int
	 *	@throws		SimpleCacheInvalidArgumentException
	 */
	private function removeBySetFocus(): int
	{
		/** @var array $rows */
		$rows	= $this->table->get( FALSE );
		if( count( $rows ) === 0 )
			return 0;
		$number = $this->table->delete();
		foreach( $rows as $row ){
			switch( $this->fetchMode ){
				case PDO::FETCH_CLASS:
				case PDO::FETCH_OBJ:
//						$id	= $row->{$this->primaryKey};
					$id	= get_object_vars( $row )[$this->primaryKey];
					break;
				default:
					$id	= $row[$this->primaryKey];
			}
			$this->cache->delete( $this->cacheKey.$id );
		}
		return $number;
	}
}
