<?php
/**
 *	Client wrapper to use OSQL with an existing PDO database connection.
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
 *	@package		CeusMedia_Database_OSQL
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2019 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
namespace CeusMedia\Database\OSQL;

/**
 *	Client wrapper to use OSQL with an existing PDO database connection.
 *	@category		Library
 *	@package		CeusMedia_Database_OSQL
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2019 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
class Client
{
	protected $fetchMode;
	public static $defaultFetchMode	= \PDO::FETCH_OBJ;

	/**
	 *	Constructor.
	 *	@access		public
	 *	@param		PDO		$dbc		Database connection using PDO
	 *	@return		void
	 */
	public function __construct( \PDO $dbc )
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
	 *	@param		QueryInterface
	 *	@param		array
	 */
	public function execute( QueryInterface $query )
	{
		$clock	= new \Alg_Time_Clock();
		$parts	= $query->render();
		$query->timeRender	= $clock->stop( 6, 0 );

		$clock->start();
		$stmt	= $this->dbc->prepare( $parts[0] );
		foreach( $parts[1] as $name => $parameter )
			$stmt->bindParam( $name, $parameter['value'], $parameter['type'] );
		$query->timePrepare	= $clock->stop( 6, 0 );

		$clock->start();
		$result	= $stmt->execute();
		if( !$result ){
			$info	= $stmt->errorInfo();
			throw new \Exception( $info[2], $info[1] );
		}
		$query->timeExecute	= $clock->stop( 6, 0 );

		if( $query instanceof Query\Select )
			return $stmt->fetchAll( $this->fetchMode );
		return $result;
	}

	public function getLastInsertId()
	{
		return $this->dbc->lastInsertId();
	}

	public function setFetchMode( $mode )
	{
		$this->fetchMode	= $mode;
	}
}
?>
