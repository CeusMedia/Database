<?php
/**
 *	Builder for UPDATE statements.
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

use CeusMedia\Database\OSQL\Query\AbstractQuery;
use CeusMedia\Database\OSQL\Query\QueryInterface;
use CeusMedia\Database\OSQL\Table;

/**
 *	Builder for UPDATE statements.
 *	@category		Library
 *	@package		CeusMedia_Database_OSQL_Query
 *	@extends		\CeusMedia\Database\OSQL\QueryAbstract
 *	@implements		\CeusMedia\Database\OSQL\QueryInterface
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2020 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
class Update extends AbstractQuery implements QueryInterface
{
	protected $conditions	= array();
	protected $fields		= array();
	protected $table		= NULL;

	public $affectedRows;

	/**
	 *	...
	 *	@access		protected
	 *	@return		void
	 */
	protected function checkSetup()
	{
		if( !$this->table )
			throw new \Exception( 'No table clause set' );
	}

	/**
	 *	Sets table to update in and returns query object for chainability.
	 *	@access		public
	 *	@param		Table		$table	Table to update in
	 *	@return		self
	 */
	public function in( Table $table ): self
	{
		$this->table	= $table;
		return $this;
	}

	/**
	 *	Returns rendered SQL statement and a map of parameters for parameter binding.
	 *	@access		public
	 *	@return		array
	 */
	public function render(): object
	{
		$clock	= new \Alg_Time_Clock();
		$this->checkSetup();
		$parameters	= array();
		$fields		= $this->renderFields( $parameters );
		$table		= $this->table->render();
		$conditions	= $this->renderConditions( $parameters );
		$limit		= $this->renderLimit( $parameters );
		$offset		= $this->renderOffset( $parameters );
		$query		= 'UPDATE '.$table.$fields.$conditions.$limit.$offset;
		$this->timeRender	= $clock->stop( 6, 0 );
		return (object) array(
			'query'			=> $query,
			'parameters'	=> $parameters,
		);
	}

	/**
	 *	Returns rendered SET string.
	 *	@access		protected
	 *	@param		array		$parameters		Reference to parameters map
	 *	@return		string
	 */
	protected function renderFields( & $parameters ): string
	{
		if( !$this->fields )
			return '';
		$list	= array();
		foreach( $this->fields as $name => $value )
			$list[]	= $name.' = :'.$name;
		$parameters[$name]	= array(
			'type'	=> \PDO::PARAM_STR,
			'value'	=> $value
		);
		return ' SET '.implode( ', ', $list );
	}

	/**
	 *	Sets pair to update and returns query object for chainability.
	 *	@access		public
	 *	@param		string		$name		Column key
	 *	@param		mixed		$value		Value to set
	 *	@return		self
	 */
	public function set( $name, $value ): self
	{
		$this->fields[$name]	 = $value;
		return $this;
	}
}
