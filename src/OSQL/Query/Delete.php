<?php
/**
 *	Builder for DELETE statements.
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
 *	Builder for DELETE statements.
 *	@category		Library
 *	@package		CeusMedia_Database_OSQL_Query
 *	@extends		\CeusMedia\Database\OSQL\QueryAbstract
 *	@implements		\CeusMedia\Database\OSQL\QueryInterface
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2020 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
class Delete extends AbstractQuery implements QueryInterface
{
	protected $conditions	= array();
	protected $table		= NULL;

	public $affectedRows;

	public function from( Table $table ): self
	{
		$this->table	= $table;
		return $this;
	}

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
	 *	Returns rendered SQL statement and a map of parameters for parameter binding.
	 *	@access		public
	 *	@return		object
	 */
	public function render(): object
	{
		$clock		= new \Alg_Time_Clock();
		$this->checkSetup();
		$parameters	= array();
		$table		= $this->table->render();
		$conditions	= $this->renderConditions( $parameters );
		$limit		= $this->renderLimit( $parameters );
		$offset		= $this->renderOffset( $parameters );
		$query		= 'DELETE FROM '.$table.$conditions.$limit.$offset;
		$this->timeRender	= $clock->stop( 6, 0 );
		return (object) array(
			'query'			=> $query,
			'parameters'	=> $parameters,
		);
	}
}
