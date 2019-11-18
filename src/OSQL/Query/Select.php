<?php
/**
 *	Builder for SELECT statements.
 *
 *	Copyright (c) 2010-2019 Christian Würker (ceusmedia.de)
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
 *	@copyright		2010-2019 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
namespace CeusMedia\Database\OSQL\Query;

use CeusMedia\Database\OSQL\Query\AbstractQuery;
use CeusMedia\Database\OSQL\Query\QueryInterface;
use CeusMedia\Database\OSQL\Table;

/**
 *	Builder for SELECT statements.
 *	@category		Library
 *	@package		CeusMedia_Database_OSQL_Query
 *	@extends		\CeusMedia\Database\OSQL\QueryAbstract
 *	@implements		\CeusMedia\Database\OSQL\QueryInterface
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2019 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
class Select extends AbstractQuery implements QueryInterface
{
	protected $countRows	= FALSE;
	protected $conditions	= array();
	protected $orders		= array();
	protected $fields		= '*';
	protected $tables		= array();
	protected $groupBy		= NULL;

	public $foundRows		= 0;
	public $finalQuery;

	/**
	 *	Enable/disable counting of all rows ignoring limits.
	 *	The result can be read later by in query member "foundRows".
	 *	@access		public
	 *	@param		bool		Flag: enable or disable counting
	 */
	public function countRows( ?bool $count = TRUE ): self
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
			throw new \InvalidArgumentException( 'Must be array or string' );
		foreach( $fields as $field ){
			if( trim( $field ) === '*' ){
				$this->fields	= '*';
				break;
			}
			else if( $this->fields	== '*' )
				$this->fields	= array( trim( $field ) );
			else
				$this->fields[]	= trim( $field );
		}
		return $this;
	}

	/**
	 *	...
	 *	@access		protected
	 *	@return		void
	 */
	protected function checkSetup()
	{
		if( !$this->tables )
			throw new \Exception( 'No from clause set' );
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
		$direction	= strtoupper( $direction );
		if( !in_array( $direction, ['ASC', 'DESC'] ) )
			throw new \InvalidArgumentException( 'Direction must be ASC or DESC' );
		$this->orders[]	= (object) array(
			'field'		=> $field,
			'direction'	=> $direction,
		);
		return $this;
	}

	/**
	 *	Returns rendered FROM string.
	 *	@access		protected
	 *	@return		string
	 */
	protected function renderFrom(): string
	{
		if( !$this->tables )
			throw new \RuntimeException( 'No table set' );
		$list	= array();
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
		if( !$this->groupBy )
			return '';
		return ' GROUP BY '.$this->groupBy;
	}

	protected function renderOrders(): string
	{
		if( !$this->orders )
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
	 *	@return		array
	 */
	public function render(): object
	{
		$clock		= new \Alg_Time_Clock();
		$this->checkSetup();
		$parameters	= array();
		$fields		= is_array( $this->fields ) ? implode( ',', $this->fields ) : $this->fields;
		$from		= $this->renderFrom();
		$conditions	= $this->renderConditions( $parameters );
		$limit		= $this->renderLimit( $parameters );
		$offset		= $this->renderOffset( $parameters );
		$group		= $this->renderGrouping();
		$query		= 'SELECT '.$fields.$from.$conditions.$limit.$offset.$group;
		$query		= preg_replace( '/ (LEFT|INNER|FROM|WHERE)/', PHP_EOL.'\\1', $query );
		$orders		= $this->renderOrders();
		$options	= $this->renderOptions();
		$this->timeRender	= $clock->stop( 6, 0 );
		return array( $query, $parameters );
		return (object) array(
			'query'			=> $query,
			'parameters'	=> $parameters,
		);
	}

	protected function renderOptions()
	{
		$options	= [];
		if( $this->countRows )
			$options[]	= 'SQL_CALC_FOUND_ROWS';
		return $options ? join( $options ).' ' : '';
	}

}
