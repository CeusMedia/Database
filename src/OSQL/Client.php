<?php /** @noinspection PhpMultipleClassDeclarationsInspection */

/**
 *	Client wrapper to use OSQL with an existing PDO database connection.
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

use CeusMedia\Common\Alg\Time\Clock;
use CeusMedia\Common\Exception\SQL as SqlException;
use CeusMedia\Database\OSQL\Query\QueryInterface;
use InvalidArgumentException;
use PDO;

/**
 *	Client wrapper to use OSQL with an existing PDO database connection.
 *	@category		Library
 *	@package		CeusMedia_Database_OSQL
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2023 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
class Client
{
	public static int $defaultFetchMode	= PDO::FETCH_OBJ;

	protected PDO $dbc;
	protected int $fetchMode;

	/**
	 *	Constructor.
	 *	@access		public
	 *	@param		PDO		$dbc		Database connection using PDO
	 *	@return		void
	 */
	public function __construct( PDO $dbc )
	{
		$this->dbc	= $dbc;
		$this->setFetchMode( self::$defaultFetchMode );
	}

/*
	public function select()
	{
		return new \CeusMedia\Database\OSQL\Query\Select();
	}

	public function update()
	{
		return new \CeusMedia\Database\OSQL\Query\Update();
	}

	public function delete()
	{
		return new \CeusMedia\Database\OSQL\Query\Delete();
	}*/
/*
	public function getStringFromQuery( $query )
	{
		$query	= $query->render();
		return $query[0];
	}
*/

	/**
	 *	Executes query and returns result.
	 *	@access		public
	 *	@param		QueryInterface		$query
	 *	@return		object|array|int|float|string|bool
	 */
	public function execute( QueryInterface $query )
	{
		$clock		= new Clock();
		$queryParts	= $query->render();
		$query->timing['render']	= $clock->stop( 0, 6 );
		$query->statement			= $queryParts->query;
		$query->parameters			= $queryParts->parameters;

		$clock->start();
		$stmt	= $this->dbc->prepare( $queryParts->query );
		foreach( $queryParts->parameters as $name => $parameter ){
			$type	= $this->getPdoTypeFromValue( $parameter['value'] );
			$stmt->bindParam( $name, $parameter['value'], $type );
		}
		$query->timing['prepare']	= $clock->stop( 0, 6 );

		$clock->start();
		$result	= $stmt->execute();
		if( !$result ){
			$info	= $stmt->errorInfo();
			throw new SqlException( $info[2] ?? NULL, $info[1], $info[0] );
		}
		$query->timing['execute']	= $clock->stop( 0, 6 );
		$query->timing['total']		= array_sum( $query->timing );

		if( strpos( $queryParts->query, 'SQL_CALC_FOUND_ROWS' ) ){
			$query->foundRows	= current( $this->dbc->query( 'SELECT FOUND_ROWS()' )->fetch() );
		}

		if( $query instanceof Query\Select ){
			$query->result	= $stmt->fetchAll( $this->fetchMode );
			return $query->result;
		}
		else if( $query instanceof Query\Insert ){
			$query->rowCount		= $stmt->rowCount();
			$query->lastInsertId	= $this->dbc->lastInsertId() ?: NULL;
			return $query->lastInsertId;
		}
		else if( $query instanceof Query\Update || $query instanceof Query\Delete ){
			$query->rowCount	= $stmt->rowCount();
			return $query->rowCount;
		}
		return $result;
	}

	/**
	 *	@param		mixed		$value
	 *	@return		int
	 */
	protected function getPdoTypeFromValue( $value ): int
	{
		$typeMap	= [
			'boolean'	=> PDO::PARAM_BOOL,
			'integer'	=> PDO::PARAM_INT,
			'double'	=> PDO::PARAM_STR,
			'string'	=> PDO::PARAM_STR,
			'NULL'		=> PDO::PARAM_NULL,
		];
		$type		= gettype( $value );
		if( !array_key_exists( $type, $typeMap ) )
			throw new InvalidArgumentException( 'Value of type "'.$type.'" is not supported' );
	 	return $typeMap[$type];
	}

	/**
	 *	@return		string|FALSE
	 */
	public function getLastInsertId()
	{
		return $this->dbc->lastInsertId();
	}

	public function setFetchMode( int $mode ): self
	{
		$this->fetchMode	= $mode;
		return $this;
	}
}
