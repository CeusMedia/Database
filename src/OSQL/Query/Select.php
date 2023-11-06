<?php /** @noinspection PhpUnused */

/**
 *	Builder for SELECT statements.
 *
 *	Copyright (c) 2010-2020 Christian Würker (ceusmedia.de)
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
 *	@package		CeusMedia_Database_OSQL_Query
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2020 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
namespace CeusMedia\Database\OSQL\Query;

use CeusMedia\Common\Alg\Time\Clock;
use CeusMedia\Database\OSQL\Table;
use InvalidArgumentException;
use RuntimeException;

/**
 *	Builder for SELECT statements.
 *	@category		Library
 *	@package		CeusMedia_Database_OSQL_Query
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2020 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
class Select extends AbstractQuery implements QueryInterface
{
	public int $foundRows		= 0;

	protected bool $countRows	= FALSE;
	protected array $conditions	= [];
	protected array $orders		= [];
	protected array $fields		= ['*'];
	protected array $tables		= [];
	protected ?string $groupBy	= NULL;

	/**
	 *	Enable/disable counting of all rows ignoring limits.
	 *	The result can be read later by in query member "foundRows".
	 *	@access		public
	 *	@param		bool		$count		Flag: enable or disable counting
	 */
	public function countRows( bool $count = TRUE ): self
	{
		$this->countRows	= $count;
		return $this;
	}

	/**
	 *	Adds fields to select and returns query object for chainability.
	 *	@access		public
	 *	@param		array|string	$fields		List of fields to select or one field name or asterisk
	 *	@return		self
	 */
	public function get( $fields ): self
	{
		if( is_string( $fields ) )
			$fields	= array( $fields );
		if( !is_array( $fields ) )
			throw new InvalidArgumentException( 'Must be array or string' );
		return $this;
	}

	/**
	 *	...
	 *	@access		protected
	 *	@return		void
	 */
	protected function checkSetup()
	{
		if( count( $this->tables ) === 0 )
			throw new RuntimeException( 'No from clause set' );
	}

	/**
	 *	Sets table to select in and returns query object for chainability.
	 *	@access		public
	 *	@param		Table	$table		Table to select in
	 *	@return		self
	 */
	public function from( Table $table ): self
	{
		$this->tables[]	= $table;
		return $this;
	}

	public function groupBy( string $name ): self
	{
		$this->groupBy	= $name;
		return $this;
	}

	public function order( string $field, ?string $direction = 'ASC' ): self
	{
		$direction	= strtoupper( $direction ?? 'ASC' );
		if( !in_array( $direction, ['ASC', 'DESC'], TRUE ) )
			throw new InvalidArgumentException( 'Direction must be ASC or DESC' );
		$this->orders[]	= (object) [
			'field'		=> $field,
			'direction'	=> $direction,
		];
		return $this;
	}

	/**
	 *	Returns rendered FROM string.
	 *	@access		protected
	 *	@return		string
	 */
	protected function renderFrom(): string
	{
		if( count( $this->tables ) === 0 )
			throw new RuntimeException( 'No table set' );
		$list	= [];
		foreach( $this->tables as $table )
			$list[]	= $table->render();
		return ' FROM '.implode( ', ', $list );
	}

	/**
	 *	Returns rendered GROUP string.
	 *	@access		protected
	 *	@return		string
	 */
	protected function renderGrouping(): string
	{
		if( $this->groupBy === NULL )
			return '';
		return ' GROUP BY '.$this->groupBy;
	}

	protected function renderOrders(): string
	{
		if( count( $this->orders ) === 0 )
			return '';
		$list	= [];
		foreach( $this->orders as $order ){
			$list[]	= $order->field.' '.$order->direction;
		}
		return ' ORDER BY '.implode( ', ', $list );
	}

	/**
	 *	Returns rendered SQL statement and a map of parameters for parameter binding.
	 *	@access		public
	 *	@return		object
	 */
	public function render(): object
	{
//		$clock		= new Clock();
		$this->checkSetup();
		$parameters	= [];
		$fields		= implode( ', ', $this->fields );
		$from		= $this->renderFrom();
		$joins		= $this->renderJoins();
		$conditions	= $this->renderConditions( $parameters );
		$limit		= $this->renderLimit( $parameters );
		$offset		= $this->renderOffset( $parameters );
		$group		= $this->renderGrouping();
		$orders		= $this->renderOrders();
		$options	= $this->renderOptions();
		$query		= 'SELECT '.$options.$fields.$from.$joins.$conditions.$group.$orders.$limit.$offset;
		$query		= preg_replace( '/ (LEFT|INNER|FROM|WHERE|ORDER|LIMIT|GROUP|HAVING)/', PHP_EOL.'\\1', $query );
		return (object) [
			'query'			=> $query,
			'parameters'	=> $parameters,
		];
	}

	protected function renderOptions(): string
	{
		$options	= [];
		if( $this->countRows )
			$options[]	= 'SQL_CALC_FOUND_ROWS';
		if( count( $options ) === 0 )
			return '';
		return join( $options ).' ';
	}
}

/*
Syntax
---------------------

MySQL:   https://dev.mysql.com/doc/refman/5.7/en/select.html
MariaDB: https://mariadb.com/kb/en/library/select/

SELECT
    [ALL | DISTINCT | DISTINCTROW ]
      [HIGH_PRIORITY]
      [STRAIGHT_JOIN]
      [SQL_SMALL_RESULT] [SQL_BIG_RESULT] [SQL_BUFFER_RESULT]
      [SQL_CACHE | SQL_NO_CACHE] [SQL_CALC_FOUND_ROWS]
    select_expr [, select_expr ...]
    [FROM table_references
      [PARTITION partition_list]
    [WHERE where_condition]
    [GROUP BY {col_name | expr | position}
      [ASC | DESC], ... [WITH ROLLUP]]
    [HAVING where_condition]
    [ORDER BY {col_name | expr | position}
      [ASC | DESC], ...]
    [LIMIT {[offset,] row_count | row_count OFFSET offset}]
    [PROCEDURE procedure_name(argument_list)]
    [INTO OUTFILE 'file_name'
        [CHARACTER SET charset_name]
        export_options
      | INTO DUMPFILE 'file_name'
      | INTO var_name [, var_name]]
    [FOR UPDATE | LOCK IN SHARE MODE]]

*/
