<?php /** @noinspection DuplicatedCode */
/** @noinspection PhpRedundantDocCommentInspection */
/** @noinspection PhpMultipleClassDeclarationsInspection */
/** @noinspection PhpUnused */

/**
 *	A better abstract database table.
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
 *	@package		CeusMedia_Database_PDO
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2007-2024 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
namespace CeusMedia\Database\PDO;

use CeusMedia\Cache\Adapter\Noop as NoopCache;
use CeusMedia\Common\Alg\Obj\Factory as ObjectFactory;
use CeusMedia\Common\Exception\Deprecation;
use CeusMedia\Database\PDO\Table\Reader as TableReader;
use CeusMedia\Database\PDO\Table\Writer as TableWriter;
use Psr\SimpleCache\CacheInterface as SimpleCacheInterface;

use DomainException;
use InvalidArgumentException;
use PDO;
use Psr\SimpleCache\InvalidArgumentException as SimpleCacheInvalidArgumentException;
use RangeException;
use ReflectionException;
use ReflectionObject;
use RuntimeException;

/**
 *	Abstract database table.
 *	@category		Library
 *	@package		CeusMedia_Database_PDO
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2007-2024 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 *	@template		DatabaseEntityTemplate
 */
abstract class BetterTable
{
	/**	@var	Connection|PDO|NULL								$dbc			PDO database connection object */
	protected Connection|PDO|NULL $dbc;

	/**	@var	string										$name			Name of Database Table without Prefix */
	protected string $name									= '';

	/**	@var	array										$columns		List of Database Table Columns */
	protected array $columns								= [];

	/**	@var	array										$indices		List of foreign Keys of Database Table */
	protected array $indices								= [];

	/**	@var	string										$primaryKey		Primary Key of Database Table */
	protected string $primaryKey							= '';

	/**	@var	TableReader									$reader			Database Table Reader Object for reading from Database Table */
	protected TableReader $reader;

	/**	@var	TableWriter									$writer			Database Table Writer Object for writing to Database Table */
	protected TableWriter $writer;

	/**	@var	string										$prefix			Database Table Prefix */
	protected string $prefix								= '';

	/**	@var	SimpleCacheInterface						$cache			Model data cache */
	protected SimpleCacheInterface $cache;

	/**	@var	SimpleCacheInterface|null					$cacheInstance	Cache adapter instance to use as cache by default */
	public static ?SimpleCacheInterface $cacheInstance		= NULL;

	/**	@var	string										$cacheClass		Name of default cache adapter class */
	public static string $cacheClass						= NoopCache::class;

	/**	@var	mixed										$cacheResource	Resource to connect to by cache adapter */
	public static mixed $cacheResource						= NULL;

	/**	@var	string										$cacheKey		Prefix of cache key */
	protected string $cacheKey								= '';

	/**	@var	integer										$fetchMode		PDO fetch mode, default: PDO::FETCH_OBJ */
	protected int $fetchMode								= PDO::FETCH_OBJ;

	/**	@var	string|NULL									$fetchEntityClass	Entity class name for PDO fetch mode FETCH_CLASS */
	protected ?string $fetchEntityClass						= NULL;

	/**	@var	object|NULL									$fetchEntityObject	Entity object for PDO fetch mode FETCH_INTO */
	protected ?object $fetchEntityObject					= NULL;

	/**
	 *	Constructor.
	 *	@access		public
	 *	@param		Connection|PDO			$dbc		PDO database connection object
	 *	@param		?string				$prefix		Table name prefix
	 *	@param		int|string|NULL		$id			ID to focus on
	 *	@return		void
	 *	@throws		ReflectionException
	 */
	public function __construct( Connection|PDO $dbc, ?string $prefix = NULL, int|string $id = NULL )
	{
		$this->checkTableSetup();
		$this->setDatabase( $dbc, $prefix, $id );
		$this->setupCache();
	}

	/**
	 *	Returns Data of single Line by ID.
	 *	@access		public
	 *	@param		array|object	$data			Map of data (array | anonymous object | dictionary | traversable | iterator | entity object) to store
	 *	@param		boolean			$stripTags		Flag: strip HTML Tags from values
	 *	@return		integer
	 *	@throws		SimpleCacheInvalidArgumentException
	 */
	public function add( array|object $data, bool $stripTags = TRUE ): int
	{
		$id	= $this->writer->insert( $data, $stripTags );
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
		return $this->reader->count( $conditions );
	}

	/**
	 *	Returns number of entries within an index.
	 *	@access		public
	 *	@param		string					$key			Index Key
	 *	@param		float|array|int|string	$value			Value(s) of Index
	 *	@return		integer					Number of entries within this index
	 */
	public function countByIndex( string $key, float|array|int|string $value ): int
	{
		return $this->reader->count( [$key => $value] );
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
		return $this->reader->countFast( $conditions );
	}

	/**
	 *	Modifies data of single row by ID.
	 *	@access		public
	 *	@param		integer|string	$id				ID to focus on
	 *	@param		array|object	$data			Data to edit
	 *	@param		boolean			$stripTags		Flag: strip HTML Tags from values
	 *	@return		integer			Number of changed rows
	 *	@throws		SimpleCacheInvalidArgumentException
	 */
	public function edit( int|string $id, array|object $data, bool $stripTags = TRUE ): int
	{
		$this->writer->focusPrimary( $id );
		$this->reader->focusPrimary( $id );
		$result	= 0;
		if( $this->reader->has() )
			$result	= $this->writer->update( $data, $stripTags );
		$this->writer->defocus();
		$this->reader->defocus();
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
		$this->checkIndices( $indices, TRUE, TRUE, TRUE );
		return $this->writer->updateByConditions( $data, $indices, $stripTags );
	}

	/**
	 *	Returns Data of single Line by ID.
	 *	@access		public
	 *	@param		integer|string	$id				ID to focus on
	 *	@return		DatabaseEntityTemplate|object|array|NULL
	 *	@throws		SimpleCacheInvalidArgumentException
	 *	@noinspection	PhpDocSignatureInspection
	 */
	public function get( int|string $id ): object|array|NULL
	{
		$cacheData	= $this->cache->get($this->cacheKey . $id );
		if( is_string( $cacheData ) )
			/** @var object|array $data */
			$data = unserialize( $cacheData );
		else{
			$this->reader->focusPrimary( $id );
			/** @var object|array|NULL $data */
			$data	= $this->reader->get();
			$this->reader->defocus();
			$this->cache->set( $this->cacheKey.$id, serialize( $data ) );
		}
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
	 *	@return		array<DatabaseEntityTemplate|object|array>
	 *	@todo		remove $fields parameter and deprecation exception
	 */
	public function getAll( array $conditions = [], array $orders = [], array $limits = [], array $fields = [], array $groupings = [], array $having = [] ): array
	{
		if( [] !== $fields )
			throw Deprecation::create( 'Using fields in getAll is discouraged' )
				->setDescription( 'Code update is needed due to change of PDO table class top support entity classes' )
				->setSuggestion( 'Use getColumnOfAllByConditions or getColumnsOfAllByConditions instead' );
		return $this->reader->find( [], $conditions, $orders, $limits, $groupings, $having );
	}

	/**
	 *	Returns Data of all Lines selected by Index.
	 *	@access		public
	 *	@param		string						$key		Key of Index
	 *	@param		float|array|bool|int|string	$value		Value(s) of Index
	 *	@param		array						$orders		Map of Orders to include in SQL Query
	 *	@param		array						$limits		List of Limits to include in SQL Query
	 *	@param		array						$fields		List of fields or one field to return from result
	 *	@return		array<DatabaseEntityTemplate|object|array>
	 *	@todo		remove $fields parameter and deprecation exception
	 *	@throws		RangeException							if index key is empty but mandatory
	 *	@throws		DomainException							if index key is not an index column
	 */
	public function getAllByIndex( string $key, float|array|bool|int|string $value, array $orders = [], array $limits = [], array $fields = [] ): array
	{
		if( [] !== $fields )
			throw Deprecation::create( 'Using fields in getAll is discouraged' )
				->setDescription( 'Code update is needed due to change of PDO table class top support entity classes' )
				->setSuggestion( 'Use getColumnOfAllByConditions or getColumnsOfAllByConditions instead' );
		$this->checkIndices( [$key => $value], TRUE, TRUE, TRUE );
		return $this->getAll( [$key => $value], $orders, $limits );
	}

	/**
	 *	Returns Data of all Lines selected by Indices.
	 *	@access		public
	 *	@param		array			$indices		Map of Index Keys and Values
	 *	@param		array			$orders			Map of Orders to include in SQL Query
	 *	@param		array			$limits			List of Limits to include in SQL Query
	 *	@param		array			$fields			List of fields or one field to return from result
	 *	@return		array<int,DatabaseEntityTemplate|object|array>
	 *	@todo		remove $fields parameter and deprecation exception
	 *	@throws		RangeException					If index map is empty but mandatory
	 *	@throws		DomainException					If an index key is not an index column
	 */
	public function getAllByIndices( array $indices = [], array $orders = [], array $limits = [], array $fields = [] ): array
	{
		if( [] !== $fields )
			throw Deprecation::create( 'Using fields in getAll is discouraged' )
				->setDescription( 'Code update is needed due to change of PDO table class top support entity classes' )
				->setSuggestion( 'Use getColumnOfAllByConditions or getColumnsOfAllByConditions instead' );

		$this->checkIndices( $indices, TRUE, TRUE, TRUE );
		return $this->getAll( $indices, $orders, $limits );
	}

	/**
	 *	Returns data of first entry selected by index.
	 *	Returns NULL if nothing found.
	 *	@access		public
	 *	@param		string					$key			Key of Index
	 *	@param		float|array|int|string	$value			Value(s) of Index
	 *	@param		array					$orders			Map of Orders to include in SQL Query
	 *	@return		DatabaseEntityTemplate|object|array|NULL	Structure depending on fetch type, NULL if no entries
	 *	@throws		DomainException							If given index key is not a defined column
	 *	@noinspection	PhpDocSignatureInspection
	 */
	public function getByIndex( string $key, float|array|int|string $value, array $orders = [] ): object|array|NULL
	{
		$this->reader->focusIndex( $key, $value );
		/** @var object|array|NULL $data */
		$data	= $this->reader->get( TRUE, $orders );
		$this->reader->defocus();
		return $data;
	}

	/**
	 *	Returns data of single line selected by indices.
	 *	@access		public
	 *	@param		array				$indices		Map of Index Keys and Values
	 *	@param		array				$orders			Map of Orders to include in SQL Query
	 *	@return		DatabaseEntityTemplate|object|array|NULL	Structure depending on fetch type, string if field selected, NULL if field selected and no entries
	 *	@throws		InvalidArgumentException			If given fields list is neither a list nor a string
	 *	@throws		DomainException						If a given index key is not a defined column
	 *	@noinspection	PhpDocSignatureInspection
	 */
	public function getByIndices( array $indices, array $orders = [] ): object|array|NULL
	{
		$this->checkIndices( $indices, TRUE, TRUE, TRUE );
		foreach( $indices as $key => $value )
			$this->reader->focusIndex( $key, $value );
		/** @var object|array $result */
		$result	= $this->reader->get( TRUE, $orders );
		$this->reader->defocus();
		return $result;
	}

	/**
	 *	Returns specific column of all rows found by conditions.
	 *	@access		public
	 *	@param		string			$column			Column to include in SQL Query
	 *	@param		array			$conditions		Map of Conditions to include in SQL Query
	 *	@param		array			$orders			Map of Orders to include in SQL Query
	 *	@param		array			$limits			Map of Limits to include in SQL Query
	 *	@param		array			$groupings		List of columns to group by
	 *	@param		array			$having			List of conditions to apply after grouping
	 *	@param		boolean			$strict			Flag: throw exception if result is empty and fields are selected (default: FALSE)
	 *	@return		array<int,float|int|bool|string|NULL>
	 *	@throws		RangeException					If given field/column name is empty
	 *	@throws		DomainException					If field/column is not a table column
	 */
	public function getColumnOfAllByConditions( string $column, array $conditions = [], array $orders = [], array $limits = [], array $groupings = [], array $having = [], bool $strict = FALSE ): array
	{
		$this->checkField( $column );
		$data	= $this->reader->find( $column, $conditions, $orders, $limits, $groupings, $having );
		foreach( $data as $nr => $set )
			$data[$nr]	= $this->getFieldFromResult( $set, $column, $strict );
		return $data;
	}

	/**
	 *	Returns list of table columns.
	 *	@access		public
	 *	@return		array
	 */
	public function getColumns(): array
	{
		return $this->reader->getColumns();
	}

	/**
	 *	Returns specific columns of all rows found by conditions.
	 *	@access		public
	 *	@param		array			$columns		List of Columns to include in SQL Query
	 *	@param		array			$conditions		Map of Conditions to include in SQL Query
	 *	@param		array			$orders			Map of Orders to include in SQL Query
	 *	@param		array			$limits			Map of Limits to include in SQL Query
	 *	@param		array			$groupings		List of columns to group by
	 *	@param		array			$having			List of conditions to apply after grouping
	 *	@param		boolean			$strict			Flag: throw exception if result is empty and fields are selected (default: FALSE)
	 *	@return		array<object|array>
	 *	@throws		DomainException					If a requested field is not a table column
	 *	@throws		RangeException					If a requested field is not within result fields
	 *	@throws		RangeException					In strict mode if given result list is empty
	 */
	public function getColumnsOfAllByConditions( array $columns = [], array $conditions = [], array $orders = [], array $limits = [], array $groupings = [], array $having = [], bool $strict = FALSE ): array
	{
		$data	= $this->reader->find( $columns, $conditions, $orders, $limits, $groupings, $having );
		foreach( $data as $nr => $set )
			$data[$nr]	= $this->getFieldsFromResult( $set, $columns, $strict );
		return $data;
	}

	/**
	 *	Returns list of distinct column values.
	 *	@access		public
	 *	@param		string			$column			Column to get distinct values for
	 *	@param		array			$conditions		Map of Conditions to include in SQL Query
	 *	@param		array			$orders			Map of Orders to include in SQL Query
	 *	@param		array			$limits			List of Limits to include in SQL Query
	 *	@return		array			List of distinct column values
	 */
	public function getDistinct( string $column, array $conditions, array $orders = [], array $limits = [] ): array
	{
		return $this->reader->getDistinctColumnValues( $column, $conditions, $orders, $limits );
	}

	/**
	 *	Returns set fetch mode.
	 *	@return		int
	 */
	public function getFetchMode(): int
	{
		return $this->reader->getFetchMode();
	}

	/**
	 *	Returns a single column (by field) value of a single line (by ID).
	 *	@access		public
	 *	@param		int|string		$id				ID to focus on
	 *	@param		string			$field			Single field to return
	 *	@return		bool|int|float|string|NULL		Value of field of first found row for primary key ID, NULL if no entries found
	 *	@throws		SimpleCacheInvalidArgumentException
	 *	@throws		RangeException					If field is empty but mandatory
	 *	@throws		DomainException					If field is not a table column
	 */
	public function getField( int|string $id, string $field ): bool|int|float|string|NULL
	{
		/** @var string $field */
		$field		= $this->checkField( $field );
		$data		= $this->get( $id );
		return $this->getFieldFromResult( $data, $field, FALSE );
	}

	/**
	 *	Returns single field value of first entry selected by index.
	 *	@access		public
	 *	@param		string					$field			Field to return from result
	 *	@param		string					$key			Key of index
	 *	@param		float|array|int|string	$value			Value(s) of index
	 *	@param		array					$orders			Map of orders to include in SQL query
	 *	@param		boolean					$strict			Flag: throw exception if result is empty (default: FALSE)
	 *	@return		bool|int|float|string|NULL				Value of field of first found row for index value, NULL if no entries found
	 *	@throws		RangeException			if field is empty
	 *	@throws		DomainException			if field is not a table column
	 */
	public function getFirstFieldByIndex( string $field, string $key, float|array|int|string $value, array $orders = [], bool $strict = FALSE ): float|bool|int|string|NULL
	{
		$this->checkField( $field, TRUE );
		$this->reader->focusIndex( $key, $value );
		/** @var object|array $data */
		$data	= $this->reader->get( TRUE, $orders, [], [$field] );
		$this->reader->defocus();
		return $this->getFieldFromResult( $data, $field, $strict );
	}

	/**
	 *	Returns data of first entry selected by index.
	 *	@access		public
	 *	@param		array					$fields			List of fields to return from result
	 *	@param		string					$key			Key of Index
	 *	@param		float|array|int|string	$value			Value(s) of Index
	 *	@param		array					$orders			Map of Orders to include in SQL Query
	 *	@param		boolean					$strict			Flag: throw exception if result is empty (default: FALSE)
	 *	@return		object|array|NULL		Structure depending on fetch type, NULL if no entries
	 *	@throws		RangeException							If list of fields too small (needs at least 2)
	 *	@throws		RangeException							If a requested field is empty
	 *	@throws		DomainException							If given index key is not a defined indexed column
	 *	@throws		DomainException							If a requested field is not a table column
	 *	@throws		RangeException							If a requested field is not within result fields
	 *	@throws		RangeException							In strict mode if given result list is empty
	 */
	public function getFirstFieldsByIndex( array $fields, string $key, float|array|int|string $value, array $orders = [], bool $strict = FALSE ): object|array|NULL
	{
		if( count( $fields ) < 2 )
			throw new RangeException( 'No or not enough columns specified' );
		foreach( $fields as $field )
			$this->checkField( $field, TRUE );
		$this->reader->focusIndex( $key, $value );
		/** @var object|array $result */
		$result	= $this->reader->get( TRUE, $orders, [], $fields );
		$this->reader->defocus();
		return $this->getFieldsFromResult( $result, $fields, $strict );
	}

	/**
	 *	Returns data of single line selected by indices.
	 *	@access		public
	 *	@param		string				$field			Field to return from result
	 *	@param		array				$indices		Map of Index Keys and Values
	 *	@param		array				$orders			Map of Orders to include in SQL Query
	 *	@param		boolean				$strict			Flag: throw exception if result is empty (default: FALSE)
	 *	@return		bool|int|float|string|NULL			Value of field of first found row for index value, NULL if no entries found
	 *	@throws		RangeException						If a requested field is empty
	 *	@throws		DomainException						If given index key is not a defined indexed column
	 *	@throws		DomainException						If requested field is not a table column
	 *	@throws		RangeException						If requested field is not within result fields
	 *	@throws		RangeException						In strict mode if given result list is empty
	 */
	public function getFirstFieldByIndices( string $field, array $indices, array $orders = [], bool $strict = FALSE ): bool|int|float|string|NULL
	{
		$this->checkField( $field );
		$this->checkIndices( $indices, TRUE, TRUE, TRUE );
		foreach( $indices as $key => $value )
			$this->reader->focusIndex( $key, $value );
		/** @var object|array|NULL $result */
		$result	= $this->reader->get( TRUE, $orders );
		$this->reader->defocus();
		return $this->getFieldFromResult( $result, $field, $strict );
	}

	/**
	 *	Returns data of single line selected by indices.
	 *	@access		public
	 *	@param		array				$fields			List of fields to return from result
	 *	@param		array				$indices		Map of Index Keys and Values
	 *	@param		array				$orders			Map of Orders to include in SQL Query
	 *	@param		boolean				$strict			Flag: throw exception if result is empty (default: FALSE)
	 *	@return		object|array|NULL	Structure depending on fetch type, NULL if field selected and no entries
	 *	@throws		RangeException						If less than 2 fields requested
	 *	@throws		DomainException						If a requested field is not a table column
	 *	@throws		RangeException						If index map is empty but mandatory
	 *	@throws		DomainException						If an index key is not an index column
	 */
	public function getFirstFieldsByIndices( array $fields, array $indices, array $orders = [], bool $strict = FALSE ): object|array|NULL
	{
		if( count( $fields ) < 2 )
			throw new RangeException( 'No or not enough columns specified' );
		foreach( $fields as $nr => $field )
			$fields[$nr]	= $this->checkField( $field, TRUE );
		$this->checkIndices( $indices, TRUE, TRUE, TRUE );
		foreach( $indices as $key => $value )
			$this->reader->focusIndex( $key, $value );
		/** @var object|array $result */
		$result	= $this->reader->get( TRUE, $orders );
		$this->reader->defocus();
		return $this->getFieldsFromResult( $result, $fields, $strict );
	}

	/**
	 *	Returns list of table index columns.
	 *	@access		public
	 *	@return		array
	 */
	public function getIndices(): array
	{
		return $this->reader->getIndices();
	}

	/**
	 *	Returns last statement.
	 *	@access		public
	 *	@return		?string
	 */
	public function getLastQuery(): ?string
	{
		return $this->reader->getLastQuery();
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
		return $this->reader->getPrimaryKey();
	}

	/**
	 *	Indicates whether a table row is existing by ID.
	 *	@param		int|string		$id				ID to focus on
	 *	@return		boolean
	 *	@throws		SimpleCacheInvalidArgumentException
	 */
	public function has( int|string $id ): bool
	{
		if( $this->cache->has( $this->cacheKey.$id ) )
			return TRUE;
		$this->reader->focusPrimary( $id );
		$result	= $this->reader->has();
		$this->reader->defocus();
		return $result;
	}

	/**
	 *	Indicates whether a table row is existing by index.
	 *	@access		public
	 *	@param		string					$key			Key of Index
	 *	@param		float|array|int|string	$value			Value(s) of Index
	 *	@return		boolean
	 */
	public function hasByIndex( string $key, float|array|int|string $value ): bool
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
	 *	@param		int|string		$id				ID to focus on
	 *	@return		boolean
	 *	@throws		SimpleCacheInvalidArgumentException
	 */
	public function remove( int|string $id ): bool
	{
		$this->reader->focusPrimary( $id );
		$this->writer->focusPrimary( $id );
		$result	= FALSE;
		/** @var array $found */
		$found	= $this->reader->get( FALSE );
		if( 1 === count( $found ) ){
			$this->writer->delete();
			$result	= TRUE;
		}
		$this->reader->defocus();
		$this->writer->defocus();
		$this->cache->delete( $this->cacheKey.$id );
		return $result;
	}

	/**
	 *	Removes entries selected by index.
	 *	@access		public
	 *	@param		string					$key			Key of Index
	 *	@param		float|array|int|string	$value			Value(s) of Index
	 *	@return		integer
	 *	@throws		SimpleCacheInvalidArgumentException
	 *	@throws		DomainException			if given index key is not a defined indexed column
	 */
	public function removeByIndex( string $key, float|array|int|string $value ): int
	{
		$this->reader->focusIndex( $key, $value );
		$this->writer->focusIndex( $key, $value );
		$number	= $this->removeBySetFocus();
		$this->reader->defocus();
		$this->writer->defocus();
		return $number;
	}

	/**
	 *	Removes entries selected by index.
	 *	@access		public
	 *	@param		array			$indices		Map of Index Keys and Values
	 *	@return		integer			Number of removed entries
	 *	@throws		SimpleCacheInvalidArgumentException
	 *	@throws		DomainException			if a given index key is not a defined indexed column
	 */
	public function removeByIndices( array $indices ): int
	{
		$this->checkIndices( $indices, TRUE, TRUE, TRUE );
		foreach( $indices as $key => $value ){
			$this->reader->focusIndex( $key, $value );
			$this->writer->focusIndex( $key, $value );
		}
		$number	= $this->removeBySetFocus();
		$this->reader->defocus();
		$this->writer->defocus();
		return $number;
	}

	/**
	 *	Save entity object.
	 *	If entity class is defined in model, the given entity object class must match the configured class name.
	 *	@param		object		$entity
	 *	@param		bool		$stripTags
	 *	@return		bool
	 *	@throws		SimpleCacheInvalidArgumentException
	 *	@throws		InvalidArgumentException		If entity class is defined in model and given entity object class is not matching
	 *	@throws		ReflectionException				If given entity object is missing primary key column
	 *	@throws		ReflectionException
	 */
	public function save( object $entity, bool $stripTags = TRUE ): bool
	{
		$entityClass	= get_class( $entity );
		if( NULL !== $this->fetchEntityClass && $entityClass !== $this->fetchEntityClass )
			throw new InvalidArgumentException( vsprintf( 'Entity class (%s) mismatching table defined entity class (%s)', [
				$entityClass,
				$this->fetchEntityClass,
			] ) );
		if( NULL !== $this->fetchEntityObject && $entityClass !== get_class( $this->fetchEntityObject ) )
			throw new InvalidArgumentException( vsprintf( 'Entity class (%s) mismatching table defined entity class (%s)', [
				$entityClass,
				get_class( $this->fetchEntityObject ),
			] ) );
		$reflection	= new ReflectionObject( $entity );
		$property	= $reflection->getProperty( $this->primaryKey );
		/** @var int|string $id */
		$id			= $property->getValue( $entity );
		return 0 !== $this->edit( $id, $entity, $stripTags );
	}

	/**
	 *	@param		SimpleCacheInterface		$cache
	 *	@return		self<object>
	 */
	public function setCache( SimpleCacheInterface $cache ): self
	{
		$this->cache	= $cache;
		return $this;
	}

	/**
	 *	Sets fetch mode.
	 *	Mode is a mandatory integer representing a PDO fetch mode.
	 *	@access		public
	 *	@param		integer		$mode			PDO fetch mode
	 *	@see		https://php.net/manual/en/pdo.constants.php
	 *	@return		self<object>
	 */
	public function setFetchMode( int $mode ): self
	{
		$this->fetchMode	= $mode;
		$this->reader->setFetchMode( $this->fetchMode );
		return $this;
	}

	/**
	 *	@access		public
	 * 	@param		string|NULL		$className
	 *	@return		self<object>
	 */
	public function setFetchEntityClass( ?string $className ): self
	{
		$this->fetchEntityClass	= $className;
		$this->reader->setFetchEntityClass( $this->fetchEntityClass );
		return $this;
	}

	/**
	 *	@access		public
	 *	@param		object|NULL		$object
	 *	@return		self<object>
	 */
	public function setFetchEntityObject( ?object $object ): self
	{
		$this->fetchEntityObject	= $object;
		$this->reader->setFetchEntityObject( $this->fetchEntityObject );
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
	 *	@return		self<object>
	 *	@see		https://dev.mysql.com/doc/refman/4.1/en/truncate.html
	 */
	public function truncate(): self
	{
		$this->writer->truncate();
		return $this;
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
	 *	@throws		RangeException					in strict mode if field is empty but mandatory
	 *	@throws		DomainException					in strict mode if field is not a table column
	 */
	protected function checkField( string $field, bool $mandatory = FALSE, bool $strict = TRUE ): bool|string|null
	{
		$field	= trim( $field );
		if( 0 === strlen( $field ) ){
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
	 *	@throws		RangeException					in strict mode if index map is empty but mandatory
	 *	@throws		DomainException					in strict mode if an index key is not an index column
	 */
	protected function checkIndices( array $indices, bool $mandatory = FALSE, bool $strict = TRUE, bool $withPrimaryKey = FALSE ): bool|array
	{
		if( 0 === count( $indices ) ){
			if( $mandatory ){
				if( !$strict )
					return FALSE;
				throw new RangeException( 'Index map must have at least one pair' );
			}
		}

		$list		= [];
		$indexList	= $this->reader->getIndices( $withPrimaryKey );
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
	 *	@param		object|array|null			$result			Query result as array or object
	 *	@param		string						$field			Field to return value of
	 *	@param		boolean						$strict			Flag: throw exception if result is empty
	 *	@return		string|int|float|bool|NULL	Structure depending on result and field list length
	 *	@throws		RangeException				If given result list is empty
	 *	@throws		DomainException				If requested field is not a table column
	 *	@throws		RangeException				If requested field is not within result fields
	 */
	protected function getFieldFromResult( object|array|null $result, string $field, bool $strict = TRUE ): float|bool|int|string|NULL
	{
		if( is_null( $result ) || is_array( $result ) && 0 === count( $result ) ){
			if( $strict )
				throw new RangeException( 'Result is empty' );
			return NULL;
		}
		if( !in_array( $field, $this->columns, TRUE ) )
			throw new DomainException( 'Field "'.$field.'" is not an existing column' );

		if( 1 === preg_match( '/^(.+) AS (.+)$/i', $field, $matches ) ){
			if( in_array( $matches[2], $this->columns, TRUE ) )
				throw new DomainException( 'Field "'.$field.'" is not possible since '.$matches[2].' is a column' );
			$field	= $matches[2];
		}

		if( is_object( $result ) ){
			if( !property_exists( $result, $field ) )
				throw new RangeException( 'Field "'.$field.'" is not an column of result set' );
			/** @var array<string,string|int|float|bool|NULL> $values */
			$values	= get_object_vars( $result );
			return $values[$field];
		}

		if( !isset( $result[$field] ) )
			throw new RangeException( 'Field "'.$field.'" is not an column of result set' );
		return $result[$field];
	}

	/**
	 *	Returns any fields from a query result.
	 *	@access		protected
	 *	@param		object|array|null			$result			Query result as array or object
	 *	@param		string[]					$fields			List of fields
	 *	@param		boolean						$strict			Flag: throw exception if result is empty
	 *	@return		array|object|NULL			Structure depending on result and field list length
	 *	@throws		InvalidArgumentException	If given fields list is neither a list nor a string
	 *	@throws		RangeException				If given result list is empty
	 *	@throws		DomainException				If requested field is not a table column
	 *	@throws		RangeException				If requested field is not within result fields
	 */
	protected function getFieldsFromResult( object|array|null $result, array $fields = [], bool $strict = TRUE ): object|array|NULL
	{
		if( is_null( $result ) || is_array( $result ) && 0 === count( $result ) ){
			if( $strict )
				throw new RangeException( 'Result is empty' );
			return [];
		}

		foreach( $fields as $nr => $field )
			if( $field === '*' )
				array_splice( $fields, $nr, 1, $this->columns );

		foreach( $fields as $nr => $field ){
			if( 1 === preg_match( '/^(.+) AS (.+)$/i', $field, $matches ) ){
				if( in_array( $matches[2], $this->columns, TRUE ) )
					throw new DomainException( 'Field "'.$field.'" is not possible since '.$matches[2].' is a column' );
				$fields[$nr]	= $matches[2];
			}
			else if( !in_array( $field, $this->columns, TRUE ) )
				throw new DomainException( 'Field "'.$field.'" is not an existing column' );
		}

		if( is_object( $result ) ) {
			$map = (object)[];
			foreach( $fields as $field ){
				if( !property_exists( $result, $field ) )
					throw new RangeException( 'Field "'.$field.'" is not an column of result set' );
				$values	= get_object_vars( $result );
				$map->$field	= $values[$field];
			}
			return $map;
		}

		$list	= [];
		foreach( $fields as $field ){
			if( '*' !== $field  && !isset( $result[$field] ) )
				throw new RangeException( 'Field "'.$field.'" is not an column of result set' );
			$list[$field]	= $result[$field];
		}
		return $list;
	}

	/**
	 *	@access		protected
	 *	@return		self<object>
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
	 *	@param		Connection|PDO			$dbc		PDO database connection object
	 *	@param		string|NULL			$prefix		Table name prefix
	 *	@param		int|string|NULL		$id			ID to focus on
	 *	@return		self<object>
	 */
	protected function setDatabase( Connection|PDO $dbc, ?string $prefix = NULL, int|string $id = NULL ): self
	{
		$this->dbc		= $dbc;
		$this->prefix	= (string) $prefix;
		$this->reader	= new TableReader(
			$dbc,
			$this->prefix . $this->name,
			$this->columns,
			$this->primaryKey,
			$id
		);
		$this->writer	= new TableWriter(
			$dbc,
			$this->prefix . $this->name,
			$this->columns,
			$this->primaryKey,
			$id
		);
		if( 0 !== $this->fetchMode )
			$this->reader->setFetchMode($this->fetchMode);
		$this->reader->setIndices( $this->indices );
		$this->writer->setIndices( $this->indices );
		if( NULL !== $this->fetchEntityClass )
			$this->reader->setFetchEntityClass( $this->fetchEntityClass );
		if( NULL !== $this->fetchEntityObject )
			$this->reader->setFetchEntityObject( $this->fetchEntityObject );
		return $this;
	}

	//  --  PRIVATE  --  //

	private function checkTableSetup(): void
	{
		if( '' === trim( $this->name ) )
			throw new RuntimeException( 'No table name set' );
		if( [] === $this->columns )
			throw new RuntimeException( 'No table columns set' );
	}

	/**
	 *	@return		int
	 *	@throws		SimpleCacheInvalidArgumentException
	 */
	private function removeBySetFocus(): int
	{
		/** @var array $rows */
		$rows	= $this->reader->get( FALSE );
		if( 0 === count( $rows ) )
			return 0;
		$number = $this->writer->delete();
		foreach( $rows as $row ){
			$id		= match( $this->fetchMode ){
				PDO::FETCH_CLASS, PDO::FETCH_OBJ	=> get_object_vars( $row )[$this->primaryKey],
				default								=> $row[$this->primaryKey],
			};
			$this->cache->delete( $this->cacheKey.$id );
		}
		return $number;
	}
}
