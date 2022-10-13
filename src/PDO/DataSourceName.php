<?php /** @noinspection PhpUnused */

/**
 *	Builder for Data Source Name Strings.
 *
 *	Copyright (c) 2007-2020 Christian Würker (ceusmedia.de)
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
 *	@copyright		2007-2020 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
namespace CeusMedia\Database\PDO;

use Exception;
use PDO;
use RuntimeException;

/**
 *	Builder for Data Source Name Strings.
 *	@category		Library
 *	@package		CeusMedia_Database_PDO
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2007-2020 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
class DataSourceName
{
	/**	@var		string			$driver			Database Driver */
	protected string $driver;

	/**	@var		string|NULL		$database		Database Name */
	protected ?string $database;

	/**	@var		string|NULL		$username		Database Username */
	protected ?string $username;

	/**	@var		string|NULL		$password		Database Password */
	protected ?string $password;

	/**	@var		string|NULL		$host			Host Name or URI*/
	protected ?string $host;

	/**	@var		int|NULL		$port			Host Port */
	protected ?int $port;

	/**	@var		array		$drivers		List of possible PDO drivers */
	protected array $drivers	  	= [
		'cubrid',
		'dblib',
		'firebird',
		'informix',
		'mssql',
		'mysql',
		'oci',
		'odbc',
		'pgsql',
		'sqlite',
		'sybase',
	];

	/**
	 *	Constructor.
	 *	@access		public
	 *	@param		string		$driver			Database Driver (cubrid,dblib|firebird|informix|mysql|mssql|oci|odbc|pgsql|sqlite|sybase)
	 *	@param		?string		$database		Database Name
	 *	@return		void
	 */
	public function __construct( string $driver, string $database = NULL )
	{
		$this->driver	= $this->checkDriverSupport( $driver );
		if( $database !== NULL && strlen( trim( $database ) ) > 0 )
			$this->setDatabase( $database );
	}

	/**
	 *	Converts DSN Object into a String.
	 *	@access		public
	 *	@return		string
	 *	@throws		Exception
	 */
	public function __toString(): string
	{
		return $this->render();
	}

	/**
	 *	Checks whether current driver is installed with PHP and supported.
	 *	@access		protected
	 *	@param		string		$driver			Driver Name to check (will become lowercase)
	 *	@return		string		Sanitized driver key
	 *	@throws		RuntimeException			if PDO Driver is not supported
	 *	@throws		RuntimeException			if PDO Driver is not loaded
	 */
	protected function checkDriverSupport( string $driver ): string
	{
		$driver	= strtolower( $driver );
		if( !in_array( $driver, $this->drivers, TRUE ) )
			throw new RuntimeException( 'PDO driver "'.$driver.'" is not supported' );
		if( !in_array( $driver, PDO::getAvailableDrivers(), TRUE ) )
			throw new RuntimeException( 'PDO driver "'.$driver.'" is not loaded' );
		return $driver;
	}

	/**
	 *	Returns set PDO driver.
	 *	@access		public
	 *	@return		string		Database Driver (cubrid,dblib|firebird|informix|mysql|mssql|oci|odbc|pgsql|sqlite|sybase)
	 */
	public function getDriver(): string
	{
		return $this->driver;
	}

	/**
	 *	Static constructor.
	 *	@access		public
	 *	@param		string		$driver			Database Driver (cubrid,dblib|firebird|informix|mysql|mssql|oci|odbc|pgsql|sqlite|sybase)
	 *	@param		?string		$database		Database Name
	 *	@return		self
	 */
	public static function getInstance( string $driver, ?string $database = NULL ): self
	{
		return new self( $driver, $database );
	}

	/**
	 *  ...
	 *  @return	 	string
	 *  @throws		 Exception
	 */
	public function render(): string
	{
		$prefix	= $this->driver.':';
		switch( $this->driver ){
			case 'firebird':
				return $prefix.$this->renderDsnForFirebird();
			case 'informix':
				return $prefix.$this->renderDsnForInformix();
			case 'oci':
				return $prefix.$this->renderDsnForOci();
			case 'odbc':
				return $prefix.$this->renderDsnForOdbc();
			case 'pgsql':
				return $prefix.$this->renderDsnForPgsql();
			case 'sqlite':
				return $prefix.$this->renderDsnForSqlite();
			//  cubrid, dblib, mssql, mysql, sybase
			default:
				return $prefix.$this->renderDsnForDefault();
		}
	}

	/**
	 *	Returns Data Source Name String.
	 *	@access		public
	 *	@static
	 *	@param		string		$driver			Database Driver (cubrid|dblib|firebird|informix|mysql|mssql|oci|odbc|pgsql|sqlite|sybase)
	 *	@param		string		$database		Database Name
	 *	@param		?string		$host			Host Name or URI
	 *	@param		?integer	$port			Host Port
	 *	@param		?string		$username		Username
	 *	@param		?string		$password		Password
	 *	@return		string
	 *  @throws		Exception
	 */
	public static function renderStatic( string $driver, string $database, ?string $host = NULL, ?int $port = NULL, ?string $username = NULL, ?string $password = NULL ): string
	{
		$dsn	= new self( $driver, $database );
		if( $host !== NULL )
			$dsn->setHost( $host );
		if( $port !== NULL )
			$dsn->setPort( $port );
		if( $username !== NULL )
			$dsn->setUsername( $username );
		if( $password !== NULL )
			$dsn->setPassword( $password );
		return $dsn->render();
	}

	/**
	 *	Sets Database, a String or File URI.
	 *	@access		public
	 *	@param		string		$database		Database Name
	 *	@return		self
	 */
	public function setDatabase( string $database ): self
	{
		$this->database	= $database;
		return $this;
	}

	/**
	 *	Sets Host Name or URI if Database Server is using HTTP.
	 *	@access		public
	 *	@param		string		$host 			Host Name or URI
	 *	@return		self
	 */
	public function setHost( string $host ): self
	{
		$this->host	= $host;
		return $this;
	}

	/**
	 *	Sets Password.
	 *	@access		public
	 *	@param		string		$password		Password
	 *	@return		self
	 */
	public function setPassword( string $password ): self
	{
		$this->password	= $password;
		return $this;
	}

	/**
	 *	Sets Port if Database Server is using HTTP.
	 *	@access		public
	 *	@param		integer		$port			Host Port
	 *	@return		self
	 */
	public function setPort( int $port ): self
	{
		$this->port	= $port;
		return $this;
	}

	/**
	 *	Sets Username.
	 *	@access		public
	 *	@param		string		$username		Username
	 *	@return		self
	 */
	public function setUsername( string $username ): self
	{
		$this->username	= $username;
		return $this;
	}

	//  --  PROTECTED  --  //

	/**
	 *	...
	 *	@access		protected
	 *	@return		string
	 */
	protected function renderDsnForDefault(): string
	{
		$port	= !is_null( $this->port ) && $this->port > 0 ? $this->port : NULL;
		$map	= array(
			'host'		=> $this->host,
			'port'		=> $port,
			'dbname'	=> $this->database,
		);
		return $this->renderDsnParts( $map );
	}

	/**
	 *	...
	 *	@access		protected
	 *	@return		string
	 */
	protected function renderDsnForFirebird(): string
	{
		$host	= !is_null( $this->host ) ? $this->host : NULL;
		$port	= !is_null( $this->port ) && $this->port > 0 ? $this->port : NULL;
		$map	= array(
			'DataSource'	=> $host,
			'Port'			=> $port,
			'Database'		=> $this->database,
			'User'			=> $this->username,
			'Password'		=> $this->password
		);
		return $this->renderDsnParts( $map );
	}

	/**
	 *	...
	 *	@access		protected
	 *	@return		string
	 */
	protected function renderDsnForInformix(): string
	{
		return $this->renderDsnParts( [
			'host'		=> !is_null( $this->host ) ? $this->host : NULL,
			'service'	=> !is_null( $this->port ) && $this->port > 0 ? $this->port : NULL,
			'database'	=> $this->database,
		] );
	}

	/**
	 *	@access		protected
	 *	@return		string
	 *	@todo		implement 'charset'
	 */
	protected function renderDsnForOci(): string
	{
		$dbname	= $this->database;
		$port	= !is_null( $this->port ) && $this->port > 0 ? ':'.$this->port : '';
		if( !is_null( $this->host ) )
			$dbname	= '//'.$this->host.$port.'/'.$this->database;
		return 'dbname='.$dbname;
	}

	/**
	 *	@access		protected
	 *	@return		string
	 *	@todo		implement
	 *  @throws		Exception
	 */
	protected function renderDsnForOdbc(): string
	{
		throw new Exception( 'Not yet implemented' );
	}

	/**
	 *	...
	 *	@access		protected
	 *	@return		string
	 */
	protected function renderDsnForPgsql(): string
	{
		return $this->renderDsnParts( array(
			'host'		=> !is_null( $this->host ) ? $this->host : NULL,
			'port'		=> !is_null( $this->port ) && $this->port > 0 ? $this->port : NULL,
			'dbname'	=> $this->database,
			'user'		=> $this->username,
			'password'	=> $this->password
		), ' ' );
	}

	/**
	 *	...
	 *	@access		protected
	 *	@return		string
	 */
	protected function renderDsnForSqlite(): string
	{
		if( $this->database !== NULL )
			return $this->database;
		throw new RuntimeException( 'No sqlite file set (using $database parameter or ::setDatabase)' );
	}

	/**
	 *	Flattens Map of DSN Parts using a Delimiter.
	 *	@access		protected
	 *	@param		array		$map			DSN Parts Map
	 *	@param		string		$delimiter		Delimiter between DSN Parts
	 *	@return		string
	 */
	protected function renderDsnParts( array $map, string $delimiter = '; ' ): string
	{
		$list	= [];
		foreach( $map as $key => $value )
			if( !is_null( $value ) )
				$list[]	= $key.'='.$value;
		return implode( $delimiter, $list );
	}
}
