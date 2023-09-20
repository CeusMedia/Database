<?php /** @noinspection PhpMultipleClassDeclarationsInspection */

/**
*	Pool for database connections.
 *
 *	Copyright (c) 2007-2023 Christian Würker (ceusmedia.de)
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
 *	@package		CeusMedia_Database_PDO
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2007-2023 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
namespace CeusMedia\Database\PDO;

use RuntimeException;
use DomainException;

/**
 *	Pool for database connections.
 *	@category		Library
 *	@package		CeusMedia_Database_PDO
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2007-2023 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
class Pool
{
	/** @var		string|NULL		$default		Name of default connection */
	protected ?string $default;

	/** @var		array		$connections	Map of connections by name */
	protected array $connections		= [];

	/**
	 *	Add a connection by a name.
	 *	The first connection added will be the default connection, automatically.
	 *	@access		public
	 *	@param		string			$name			Name of connection in pool
	 *	@param		Connection		$connection		Connection to add
	 *	@param		boolean			$default		Flag: make this the default connection, default: no
	 *	@return		self
	 *	@throws		DomainException					if a connection with this name is already set
	 */
	public function add( string $name, Connection $connection, bool $default = FALSE ): self
	{
		if( isset( $this->connections[$name] ) )
			throw new DomainException( 'Connection with this name already set' );
		$this->connections[$name]	= $connection;
		if( $default || is_null( $this->default ) )
			$this->setDefault( $name );
		return $this;
	}

	/**
	 *	Returns connection by name or default connection.
	 *	@access		public
	 *	@param		?string			$name			Name of connection to get, default connection if NULL
	 *	@return		Connection
	 *	@throws		RuntimeException				if no connection name given and no default connection name is set
	 *	@throws		DomainException					if no connection is available by this name
	 */
	public function get( ?string $name = NULL ): Connection
	{
		if( is_null( $name ) ){
			if( is_null( $this->default ) )
				throw new RuntimeException( 'No default connection set' );
			$name	= $this->default;
		}
		if( !isset( $this->connections[$name] ) )
			throw new DomainException( 'No connection set by this name' );
		return $this->get( $name );
	}

	/**
	 *	Returns name of default connection, if set.
	 *	@access		public
	 *	@return		string
	 *	@throws		RuntimeException				if no default connection name is set
	 */
	public function getDefault(): string
	{
		if( is_null( $this->default ) )
			throw new RuntimeException( 'No default connection set' );
		return $this->default;
	}

	/**
	 *	Removes a connection by its name.
	 *	@access		public
	 *	@param		string			$name			Name of connection to remove
	 *	@return		self
	 *	@throws		DomainException					if no connection is available by this name
	 */
	public function remove( string $name ): self
	{
		if( !isset( $this->connections[$name] ) )
			throw new DomainException( 'No connection set by this name' );
		unset( $this->connections[$name] );
		return $this;

	}

	/**
	 *	Sets name of default connection.
	 *	@access		public
	 *	@param		string			$name			Name of connection to set as default
	 *	@return		self
	 *	@throws		DomainException					if no connection is available by this name
	 */
	public function setDefault( string $name ): self
	{
		if( !isset( $this->connections[$name] ) )
			throw new DomainException( 'No connection set by this name' );
		$this->default	= $name;
		return $this;
	}
}
