<?php
/**
 *	...
 *
 *	Copyright (c) 2010-2023 Christian Würker (ceusmedia.de)
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
 *	@package		CeusMedia_Database_OSQL
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2023 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
namespace CeusMedia\Database\OSQL;

use RuntimeException;

/**
 *	...
 *	@category		Library
 *	@package		CeusMedia_Database_OSQL
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2023 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
class Table
{
	protected ?string $name		= NULL;
	protected ?string $alias	= NULL;
	protected array $joins		= [];

	/**
	 *	Constructor.
	 *	@access		public
	 *	@param		string|NULL		$name		Table name
	 *	@param		string|NULL		$alias		Alias name
	 *	@return		void
	 */
	public function __construct( ?string $name = NULL, ?string $alias = NULL )
	{
		if( $name !== NULL )
			$this->setName( $name );
		if( $alias !== NULL )
			$this->setAlias( $alias );
	}

	/**
	 *	Return alias name.
	 *	@access		public
	 *	@return		string|NULL
	 */
	public function getAlias(): ?string
	{
		return $this->alias;
	}

	/**
	 *	Return table name.
	 *	@access		public
	 *	@return		string|NULL
	 */
	public function getName(): ?string
	{
		return $this->name;
	}

	public function render(): string
	{
		if( $this->name === NULL )
			throw new RuntimeException( 'No table name set' );
		$joins	= '';
		if( count( $this->joins ) !== 0 ){
			$joins	= [];
			foreach( $this->joins as $join ){
				$tableName	= $join['table']->render();
				$equiJoin	= $join['left'].' = '.$join['right'];
				$joins[]	= ' LEFT OUTER JOIN '.$tableName.' ON ( '.$equiJoin.' )';
			}
			$joins	= join( $joins );
		}
		if( $this->alias !== NULL && $this->alias !== $this->name )
			return $this->name.' AS '.$this->alias.$joins;
		return $this->name.$joins;
	}

	/**
	 *	Set alias name.
	 *	@access		public
	 *	@param		string		$alias		Alias name
	 *	@return		self
	 */
	public function setAlias( string $alias ): self
	{
		$this->alias	= $alias;
		return $this;
	}

	/**
	 *	Set alias name.
	 *	@access		public
	 *	@param		string		$name		Table name
	 *	@return		self
	 */
	public function setName( string $name ): self
	{
		$this->name	= $name;
		return $this;
	}

	/**
	 *	Join with another table.
	 *	@access		public
	 *	@param		Table			$table		Another to join in
	 *	@param		string			$keyLeft	Column key of current table for equi join
	 *	@param		string			$keyRight	Column key of new table for equi join
	 *	@return		self
	 */
	public function join( Table $table, string $keyLeft, string $keyRight ): self
	{
		$this->joins[]	= [
			'table'	=> $table,
			'left'	=> $keyLeft,
			'right'	=> $keyRight
		];
		return $this;
	}
}
