<?php
/** @noinspection PhpMultipleClassDeclarationsInspection */

/**
 *	OSQL query to read data.
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

use CeusMedia\Database\OSQL\Condition;
use CeusMedia\Database\OSQL\Condition\Group;
use CeusMedia\Database\OSQL\Identifier;
use CeusMedia\Database\OSQL\Table;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 *	OSQL query to read data.
 *	@category		Library
 *	@package		CeusMedia_Database_OSQL_Query
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2026 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
class Select extends AbstractQuery
{
	use ConditionsTrait;

	/** @var string[] $fields Quoted fields, all columns if empty */
	protected array $fields			= [];

	protected bool $distinct		= FALSE;

	protected ?Table $table			= NULL;

	/** @var string[] $joins Rendered joins */
	protected array $joins			= [];

	/** @var string[] $groupings Quoted columns */
	protected array $groupings		= [];

	protected ?Group $having		= NULL;

	/** @var string[] $orders Rendered orders */
	protected array $orders			= [];

	protected ?int $limit			= NULL;

	protected int $offset			= 0;

	protected ?int $fetchMode		= NULL;

	/** @var class-string|NULL $fetchClass */
	protected ?string $fetchClass	= NULL;

	protected bool $counting		= FALSE;

	public function __clone()
	{
		$this->cloneConditions();
		if( NULL !== $this->having )
			$this->having	= clone $this->having;
	}

	/**
	 *	Executes query and returns all found rows.
	 *	@return		array<int,array<string,mixed>|object>
	 */
	public function all(): array
	{
		$statement	= $this->client->run( $this );
		if( NULL !== $this->fetchClass )
			/** @var array<int,object> */
			return $statement->fetchAll( PDO::FETCH_CLASS, $this->fetchClass );
		/** @var array<int,array<string,mixed>|object> */
		return $statement->fetchAll( $this->fetchMode ?? $this->client->getFetchMode() );
	}

	/**
	 *	Executes query and returns first column of all found rows.
	 *	@return		array<int,mixed>
	 */
	public function column(): array
	{
		return $this->client->run( $this )->fetchAll( PDO::FETCH_COLUMN, 0 );
	}

	/**
	 *	Returns number of found rows, ignoring order, limit and offset.
	 *	For pagination: get total by count(), then rows by page( $page, $perPage )->all().
	 *	@return		int
	 */
	public function count(): int
	{
		$query				= clone $this;
		$query->counting	= TRUE;
		$value				= $this->client->run( $query )->fetchColumn( 0 );
		$this->statement	= $query->statement;
		$this->parameters	= $query->parameters;
		return is_numeric( $value ) ? (int) $value : 0;
	}

	/**
	 *	Sets whether duplicate rows are removed (SELECT DISTINCT).
	 *	@param		bool		$distinct
	 *	@return		static
	 */
	public function distinct( bool $distinct = TRUE ): static
	{
		$this->distinct	= $distinct;
		return $this;
	}

	/**
	 *	Executes query and returns all found rows, same as all().
	 *	@return		array<int,array<string,mixed>|object>
	 */
	public function execute(): array
	{
		return $this->all();
	}

	/**
	 *	Indicates whether at least one row is found.
	 *	@return		bool
	 */
	public function exists(): bool
	{
		return NULL !== $this->first();
	}

	/**
	 *	Fetches rows as objects of given class, instead of fetch mode.
	 *	@param		class-string	$className
	 *	@return		static
	 */
	public function fetchAs( string $className ): static
	{
		if( !class_exists( $className ) )
			throw new InvalidArgumentException( 'Class "'.$className.'" is not existing' );
		$this->fetchClass	= $className;
		return $this;
	}

	/**
	 *	Sets fetch mode for this query, instead of fetch mode of client.
	 *	@param		int		$mode		PDO fetch mode, eg. PDO::FETCH_ASSOC
	 *	@return		static
	 */
	public function fetchMode( int $mode ): static
	{
		$this->fetchMode	= $mode;
		$this->fetchClass	= NULL;
		return $this;
	}

	/**
	 *	Sets fields to select, all columns by default.
	 *	A field can be "*", "o.*", a column ("total", "o.total"), an aggregate function ("COUNT(*)",
	 *	"SUM(o.total)") and have an alias ("o.total AS sum").
	 *	@param		string		...$fields
	 *	@return		static
	 */
	public function fields( string ...$fields ): static
	{
		$this->fields	= array_map( [Identifier::class, 'quoteField'], array_values( $fields ) );
		return $this;
	}

	/**
	 *	Executes query, limited to one row, and returns it - or NULL if nothing found.
	 *	@return		array<string,mixed>|object|NULL
	 */
	public function first(): array|object|NULL
	{
		$query	= ( clone $this )->limit( 1 );
		$rows	= $query->all();
		$this->statement	= $query->statement;
		$this->parameters	= $query->parameters;
		return $rows[0] ?? NULL;
	}

	/**
	 *	Sets table to select from.
	 *	@param		Table|string	$table		Table object or name, may contain an alias, eg. "orders AS o"
	 *	@return		static
	 */
	public function from( Table|string $table ): static
	{
		$this->table	= Table::resolve( $table );
		return $this;
	}

	/**
	 *	Sets columns to group by.
	 *	@param		string		...$fields		Columns (or aliases of selected fields)
	 *	@return		static
	 */
	public function groupBy( string ...$fields ): static
	{
		$this->groupings	= array_map( static fn( string $field ) => Identifier::quoteColumn( $field ), array_values( $fields ) );
		return $this;
	}

	/**
	 *	Adds condition on grouped rows (HAVING), combined by AND with having conditions before.
	 *	Field can be an aggregate function, eg. having( 'COUNT(*)', 5, Condition::OP_GT ), or an alias of a selected field.
	 *	@param		Condition|Group|string	$field		Condition, group of conditions or field to create condition for
	 *	@param		mixed					$value		Value, if field is given
	 *	@param		string					$operator	Operator, if field is given, default: "="
	 *	@return		static
	 */
	public function having( Condition|Group|string $field, mixed $value = NULL, string $operator = Condition::OP_EQ ): static
	{
		$condition		= self::resolveCondition( func_get_args() );
		$this->having	= self::combineConditions( $this->having, Group::OPERATION_AND, $condition );
		return $this;
	}

	/**
	 *	Adds inner join.
	 *	Without right key, the left key is used as column of both tables (USING).
	 *	@param		Table|string	$table		Table object or name, may contain an alias, eg. "customers AS c"
	 *	@param		string			$keyLeft	Column of joined tables, eg. "o.customerId", or common column, if no right key given
	 *	@param		string|NULL		$keyRight	Column of joining table, eg. "c.customerId"
	 *	@return		static
	 */
	public function join( Table|string $table, string $keyLeft, ?string $keyRight = NULL ): static
	{
		return $this->addJoin( 'JOIN', $table, $keyLeft, $keyRight );
	}

	/**
	 *	Adds left (outer) join.
	 *	@param		Table|string	$table		Table object or name, may contain an alias
	 *	@param		string			$keyLeft	Column of joined tables, or common column, if no right key given
	 *	@param		string|NULL		$keyRight	Column of joining table
	 *	@return		static
	 */
	public function leftJoin( Table|string $table, string $keyLeft, ?string $keyRight = NULL ): static
	{
		return $this->addJoin( 'LEFT JOIN', $table, $keyLeft, $keyRight );
	}

	/**
	 *	Sets maximum number of rows, NULL for no limit.
	 *	@param		int|NULL	$limit
	 *	@return		static
	 */
	public function limit( ?int $limit ): static
	{
		if( NULL !== $limit && $limit < 1 )
			throw new InvalidArgumentException( 'Limit must be at least 1' );
		$this->limit	= $limit;
		return $this;
	}

	/**
	 *	Sets number of rows to skip.
	 *	@param		int		$offset
	 *	@return		static
	 */
	public function offset( int $offset ): static
	{
		if( $offset < 0 )
			throw new InvalidArgumentException( 'Offset must not be negative' );
		$this->offset	= $offset;
		return $this;
	}

	/**
	 *	Adds order.
	 *	@param		string		$field			Column, alias of selected field or aggregate function
	 *	@param		string		$direction		ASC or DESC, default: ASC
	 *	@return		static
	 */
	public function order( string $field, string $direction = 'ASC' ): static
	{
		$direction	= strtoupper( trim( $direction ) );
		if( !in_array( $direction, ['ASC', 'DESC'], TRUE ) )
			throw new InvalidArgumentException( 'Direction must be ASC or DESC' );
		$this->orders[]	= Identifier::quoteExpression( $field ).' '.$direction;
		return $this;
	}

	/**
	 *	Sets limit and offset for given page.
	 *	@param		int		$page			Page number, starting at 1
	 *	@param		int		$perPage		Rows per page
	 *	@return		static
	 */
	public function page( int $page, int $perPage ): static
	{
		if( $page < 1 )
			throw new InvalidArgumentException( 'Page must be at least 1' );
		return $this->limit( $perPage )->offset( ( $page - 1 ) * $perPage );
	}

	/**
	 *	Returns statement with placeholders and list of parameters.
	 *	@return		object{query: string, parameters: array<int,string|int|float|NULL>}
	 */
	public function render(): object
	{
		if( NULL === $this->table )
			throw new RuntimeException( 'No table set' );
		$parameters	= [];
		$distinct	= $this->distinct ? 'DISTINCT ' : '';
		$fields		= [] !== $this->fields ? implode( ', ', $this->fields ) : '*';
		$from		= ' FROM '.$this->table->render().implode( $this->joins );
		$where		= self::renderConditionGroup( $this->conditions, ' WHERE ', $parameters );
		$group		= [] !== $this->groupings ? ' GROUP BY '.implode( ', ', $this->groupings ) : '';
		$having		= self::renderConditionGroup( $this->having, ' HAVING ', $parameters );

		if( $this->counting ){
			if( [] === $this->groupings && NULL === $this->having && !$this->distinct )
				return self::result( 'SELECT COUNT(*)'.$from.$where, $parameters );
			$query	= 'SELECT '.$distinct.$fields.$from.$where.$group.$having;
			return self::result( 'SELECT COUNT(*) FROM ('.$query.') AS `counted`', $parameters );
		}
		$orders		= [] !== $this->orders ? ' ORDER BY '.implode( ', ', $this->orders ) : '';
		$query		= 'SELECT '.$distinct.$fields.$from.$where.$group.$having.$orders.$this->renderLimit();
		return self::result( $query, $parameters );
	}

	/**
	 *	Adds right (outer) join.
	 *	@param		Table|string	$table		Table object or name, may contain an alias
	 *	@param		string			$keyLeft	Column of joined tables, or common column, if no right key given
	 *	@param		string|NULL		$keyRight	Column of joining table
	 *	@return		static
	 */
	public function rightJoin( Table|string $table, string $keyLeft, ?string $keyRight = NULL ): static
	{
		return $this->addJoin( 'RIGHT JOIN', $table, $keyLeft, $keyRight );
	}

	/**
	 *	Executes query, limited to one row, and returns its first column - or NULL if nothing found.
	 *	@return		mixed
	 */
	public function value(): mixed
	{
		$query	= ( clone $this )->limit( 1 );
		$value	= $this->client->run( $query )->fetchColumn( 0 );
		$this->statement	= $query->statement;
		$this->parameters	= $query->parameters;
		return FALSE === $value ? NULL : $value;
	}

	//  --  PROTECTED  --  //

	protected function addJoin( string $type, Table|string $table, string $keyLeft, ?string $keyRight ): static
	{
		$specification	= NULL === $keyRight
			? ' USING ('.Identifier::quoteName( $keyLeft ).')'
			: ' ON '.Identifier::quoteColumn( $keyLeft ).' = '.Identifier::quoteColumn( $keyRight );
		$this->joins[]	= ' '.$type.' '.Table::resolve( $table )->render().$specification;
		return $this;
	}

	protected function renderLimit(): string
	{
		if( NULL === $this->limit )
			//  MySQL needs a limit for an offset, so use the largest possible
			return 0 !== $this->offset ? ' LIMIT 18446744073709551615 OFFSET '.$this->offset : '';
		return ' LIMIT '.$this->limit.( 0 !== $this->offset ? ' OFFSET '.$this->offset : '' );
	}
}
