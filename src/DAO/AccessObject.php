<?php
/**
 *	...
 *
 *	Copyright (c) 2010-2024 Christian Würker (ceusmedia.de)
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
 *	@package		CeusMedia_Database_DAO
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2024 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
namespace CeusMedia\Database\DAO;

/**
 *	...
 *
 *	@category		Library
 *	@package		CeusMedia_Database_DAO
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2024 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
class AccessObject
{
	protected Table $table;
	protected $primaryKey	= NULL;

	public function __construct( Table $table )
	{
		$this->table	= $table;
	}

	public function __get( $key )
	{
		throw new \Exception( get_class( $this ).': Field "'.$key.'" is not available' );
	}

	public function __set( $key, $value )
	{
		if( !in_array( $key, $this->table->getFieldNames() ) )
			throw new \Exception( get_class( $this ).': Field "'.$key.'" is not available' );
		$this->$key	= $value;
	}

	public function getFieldNames()
	{
		return $this->table->getFieldNames();
	}

	/**
	 *	@todo		Should this be public?
	 */
	public function getTable(): Table
	{
		return $this->table;
	}

	/**
	 *	Set Data Access Table.
	 *	@access		public
	 *	@param		Table	$table		Database Access Table
	 *	@return		self
	 */
	public function setTable( Table $table ): self
	{
		$this->table	= $table;
		return $this;
	}

	public function updateField( $key, $value )
	{
		return $this->table->updateById( $this->table->getPrimaryKey(), [$key => $value] );
	}

	public function updateFields( $fields )
	{
		return $this->table->updateById( $this->table->getPrimaryKey(), $fields );
	}
}
