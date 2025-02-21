<?php
declare(strict_types=1);

/**
 *	...
 *
 *	Copyright (c) 2014-2025 Christian Würker (ceusmedia.de)
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
 *	@category		Tool
 *	@package		CeusMedia_Database_Utility_MySQL
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2014-2025 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Hymn
 */
namespace CeusMedia\Database\Utility\MySQL;

use CeusMedia\Database\PDO\DataSourceName as PdoDataSourceName;
use RuntimeException;

/**
 *	...
 *
 *	@category		Tool
 *	@package		CeusMedia_Database_Utility_MySQL
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2014-2025 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Hymn
 *	@todo			code documentation
 */
class OptionsFile
{
	protected PdoDataSourceName $dsn;
	protected string $defaultFileName		= '.mysqlOptions.cfg';
	protected string $actualFileName;

	public function __construct( PdoDataSourceName $connection )
	{
		$this->dsn	= $connection;
	}

	public function create( ?string $fileName = NULL, bool $strict = TRUE ): self
	{
		$fileName	= is_null( $fileName ) ? $this->defaultFileName : $fileName;
		if( file_exists( $fileName ) && $strict ){
			$message	= 'MySQL options file "'.$fileName.'" is already existing';
			throw new RuntimeException( $message );
		}
		$lines		= ['[client]'];
		$optionList	= [
			'host'		=> $this->dsn->getHost(),
			'port'		=> $this->dsn->getPort(),
//			'database'	=> $this->dsn->getDatabase(),
			'user'		=> $this->dsn->getUsername(),
			'password'	=> $this->dsn->getPassword(),
		];
		foreach( $optionList as $key => $value )
			$lines[]	= $key.'='.$value;
		file_put_contents( $fileName, join( PHP_EOL, $lines ) );
		$this->actualFileName	 = $fileName;
		return $this;
	}

	public function getDefaultFileName(): string
	{
		return $this->defaultFileName;
	}

	public function has( ?string $fileName = NULL ): bool
	{
		$fileName	= is_null( $fileName ) ? $this->defaultFileName : $fileName;
		return file_exists( $fileName );
	}

	public function remove( ?string $fileName = NULL ): self
	{
		$fileName	= is_null( $fileName ) ? $this->defaultFileName : $fileName;
		if( $this->has( $fileName ) )
			@unlink( $fileName );
		return $this;
	}

	public function setDefaultFileName( string$fileName ): self
	{
		$this->defaultFileName	= $fileName;
		return $this;
	}
}
