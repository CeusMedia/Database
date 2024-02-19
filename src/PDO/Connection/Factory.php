<?php /** @noinspection PhpMultipleClassDeclarationsInspection */
/** @noinspection PhpUnused */

/**
 *	Factory for enhanced PDO connections.
 *
 *	Copyright (c) 2007-2024 Christian Würker (ceusmedia.de)
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
 *	@package		CeusMedia_Database_PDO_Connection
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2007-2024 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
namespace CeusMedia\Database\PDO\Connection;

use CeusMedia\Database\PDO\Connection\Base;
use CeusMedia\Database\PDO\Connection\Php80;
use CeusMedia\Database\PDO\Connection\Php81;
use RangeException;
use RuntimeException;

/**
 *	Factory for enhanced PDO connections.
 *	@category		Library
 *	@package		CeusMedia_Database_PDO_Connection
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2007-2024 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 *	@todo			Code Documentation
 */
class Factory
{
	const STRATEGY_NONE			= 0;
	const STRATEGY_BY_VERSION	= 1;
	const STRATEGY_PHP80		= 2;
	const STRATEGY_PHP81		= 3;
	const STRATEGIES		= [
		self::STRATEGY_NONE,
		self::STRATEGY_BY_VERSION,
		self::STRATEGY_PHP80,
		self::STRATEGY_PHP81,
	];

	protected int $strategy	= self::STRATEGY_BY_VERSION;

	protected string $dsn;
	protected array $driverOptions	= [];
	protected ?string $username		= NULL;
	protected ?string $password		= NULL;

	/**
	 *	Constructor, establishes Database Connection using a DSN. Set Error Handling to use Exceptions.
	 *	@access		public
	 *	@param		string		$dsn			Data Source Name
	 *	@param		?string		$username		Name of Database User
	 *	@param		?string		$password		Password of Database User
	 *	@param		array		$driverOptions	Array of Driver Options
	 *	@return		Base
	 *	@see		http://php.net/manual/en/pdo.drivers.php
	 */
	public static function createByPhpVersion( string $dsn, ?string $username = NULL, ?string $password = NULL, array $driverOptions = [] ): Base
	{
		$factory	= new self( $dsn, $username, $password, $driverOptions );
		$factory->setStrategy( self::STRATEGY_BY_VERSION );
		return $factory->create();
	}

	/**
	 *	Constructor, establishes Database Connection using a DSN.
	 *	@access		public
	 *	@param		string		$dsn			Data Source Name
	 *	@param		?string		$username		Name of Database User
	 *	@param		?string		$password		Password of Database User
	 *	@param		array		$driverOptions	Array of Driver Options
	 *	@see		http://php.net/manual/en/pdo.drivers.php
	 */
	public function __construct( string $dsn, ?string $username = NULL, ?string $password = NULL, array $driverOptions = [] )
	{
		$this->dsn				= $dsn;
		$this->username			= $username;
		$this->password			= $password;
		$this->driverOptions	= $driverOptions;
	}

	/**
	 *	@param		array		$driverOptions
	 *	@return		Base
	 *	@thows		RuntimeException		if set strategy is not available
	 */
	public function create( array $driverOptions = [] ): Base
	{
		$driverOptions	= array_merge( $this->driverOptions, $driverOptions );
		$strategy		= $this->strategy;
		if( self::STRATEGY_BY_VERSION === $this->strategy )
			$strategy	= $this->detectStrategyByPhpVersion();

		return match( $strategy ){
			self::STRATEGY_PHP81	=> new Php81( $this->dsn, $this->username, $this->password, $driverOptions ),
			self::STRATEGY_PHP80	=> new Php80( $this->dsn, $this->username, $this->password, $driverOptions ),
			self::STRATEGY_NONE		=> throw new RuntimeException( 'No strategy set' ),
			default					=> throw new RuntimeException( 'No support for PHP '.PHP_VERSION ),
		};
	}

	/**
	 *	@param		int		$strategy
	 *	@return		self
	 *	@throws		RangeException
	 */
	public function setStrategy( int $strategy ): self
	{
		if( !in_array( $strategy, self::STRATEGIES, TRUE ) )
			throw new RangeException( 'Invalid strategy' );
		$this->strategy		= $strategy;
		return $this;
	}

	//  --  PROTECTED  --  //

	/**
	 *	Detects strategy depending on support for PHP version.
	 *	@return		int
	 *	@codeCoverageIgnore
	 */
	protected function detectStrategyByPhpVersion(): int
	{
		if( version_compare( PHP_VERSION, '8.1.0', '>=' ) )
			return self::STRATEGY_PHP81;
		if( version_compare( PHP_VERSION, '8.0.0', '>=' ) )
			return self::STRATEGY_PHP80;
		throw new RuntimeException( 'No support for PHP '.PHP_VERSION );
	}
}
