<?php /** @noinspection PhpMultipleClassDeclarationsInspection */
/** @noinspection PhpUnused */

/**
 *	Enhanced PDO Connection.
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
 *	@package		CeusMedia_Database_PDO_Connection
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2007-2023 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
namespace CeusMedia\Database\PDO\Connection;

use PDOException;
use PDOStatement;

/**
 *	Enhanced PDO Connection.
 *	@category		Library
 *	@package		CeusMedia_Database_PDO_Connection
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2020-2023 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 *	@todo			Code Documentation
 *	@codeCoverageIgnore
 */
class Php80 extends Base
{
	/**
	 *	Wrapper for PDO::query to support lazy connection mode.
	 *	Tries to connect database if not connected yet (lazy mode).
	 *	@access		public
	 *	@param		string		$query			SQL statement to query
	 *	@param		integer		$fetchMode		... (default: 2)
	 *	@return		PDOStatement|FALSE			PDO statement containing fetchable results
	 *	@noinspection	PhpHierarchyChecksInspection
	 */
	public function query( string $query, int $fetchMode = 2 ): PDOStatement|false
	{
		$this->logStatement( $query );
		$this->lastQuery	= $query;
		$this->numberStatements++;
		try{
			return parent::query( $query, $fetchMode );
		}
		catch( PDOException $e ){
			//  logs Error and throws SQL Exception
			$this->logError( $e, $query );
		}
		return FALSE;
	}
}