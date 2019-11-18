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
	protected $conditions	= array();
	protected $fields		= '*';
	protected $tables		= array();
	protected $groupBy		= NULL;

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

/*	public function having( $name, $value )
	{
		$this->having	= array( $name, $value );
	}
*/
	/**
	 *	Join with another table and returns query object for chainability.
	 *	@access		public
	 *	@param		Table		$table		Another to join in
	 *	@param		string		$keyLeft	Column key of current table for equi join
	 *	@param		string		$rightLeft	Column key of new table for equi join
	 *	@throws		\Exception	...
	 *	@return		QueryInterface
	 */
	public function join( Table $table, string $keyLeft, string $keyRight ): QueryInterface
	{
		if( !$this->tables )
			throw new \Exception( 'No table to join set' );
		$lastTable	= array_pop( $this->tables );
		$lastTable->join( $table, $keyLeft, $keyRight );
		array_push( $this->tables, $lastTable );
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
		$this->timeRender	= $clock->stop( 6, 0 );
		return array( $query, $parameters );
		return (object) array(
			'query'			=> $query,
			'parameters'	=> $parameters,
		);
	}
}
