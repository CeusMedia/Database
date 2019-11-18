<?php
/**
 *	Builder for INSERT statements.
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
 *	Builder for INSERT statements.
 *	@category		Library
 *	@package		CeusMedia_Database_OSQL_Query
 *	@extends		\CeusMedia\Database\OSQL\QueryAbstract
 *	@implements		\CeusMedia\Database\OSQL\QueryInterface
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2019 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
class Insert extends AbstractQuery implements QueryInterface
{
	protected $fields;
	protected $table		= NULL;

	public $lastInsertId;

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

	public function into( Table $table ): self
	{
		$this->table	= $table;
		return $this;
	}

	/**
	 *	Returns rendered VALUE string.
	 *	@access		protected
	 *	@param		array		$parameters		Reference to parameters map
	 *	@return		string
	 */
	protected function renderFields( & $parameters ): string
	{
		if( !$this->fields )
			return '';
		$listKeys	= array();
		$listVals	= array();
		foreach( $this->fields as $name => $value ){
			$key	= 'value_'.str_replace( '.', '_', $name );
			$listKeys[]	= $name;
			$listVals[]	= ':'.$key;
			$parameters[$key]	= array(
				'type'	=> \PDO::PARAM_STR,
				'value'	=> $value
			);
		}
		return '( '.implode( ', ', $listKeys ).' ) VALUE ( '.implode( ', ', $listVals ).' )';
	}

	/**
	 *	Returns rendered SQL statement and a map of parameters for parameter binding.
	 *	@access		public
	 *	@return		array
	 */
	public function render(): array
	{
		$this->checkSetup();
		$parameters	= array();
		$table		= $this->table->render();
		$fields		= $this->renderFields( $parameters );
		$conditions	= $this->renderConditions( $parameters );
		$limit		= $this->renderLimit( $parameters );
		$offset		= $this->renderOffset( $parameters );
		$query		= 'INSERT INTO '.$table.$fields.$conditions.$limit.$offset;
		return (object) array(
			'query'			=> $query,
			'parameters'	=> $parameters,
		);
	}

	/**
	 *	Add pair to insert and returns query object for chainability.
	 *	@access		public
	 *	@return		self
	 */
	public function set( string $name, $value ): self
	{
		$this->fields[$name]	 = $value;
		return $this;
	}
}
?>
