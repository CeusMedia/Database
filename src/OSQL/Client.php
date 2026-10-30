<?php
/** @noinspection PhpMultipleClassDeclarationsInspection */

/**
 *	Client to build and execute OSQL queries on an existing PDO database connection.
 *
 *	Copyright (c) 2010-2026 Christian Würker (ceusmedia.de)
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
 *	@package		CeusMedia_Database_OSQL
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2026 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */

namespace CeusMedia\Database\OSQL;

use CeusMedia\Common\Exception\SQL as SqlException;
use CeusMedia\Database\OSQL\Query\Delete;
use CeusMedia\Database\OSQL\Query\Insert;
use CeusMedia\Database\OSQL\Query\QueryInterface;
use CeusMedia\Database\OSQL\Query\Select;
use CeusMedia\Database\OSQL\Query\Update;
use CeusMedia\Database\PDO\Connection;
use PDO;
use PDOStatement;
use Throwable;

/**
 *	Client to build and execute OSQL queries on an existing PDO database connection.
 *	@category		Library
 *	@package		CeusMedia_Database_OSQL
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2026 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
class Client
{
	public static int $defaultFetchMode	= PDO::FETCH_OBJ;

	protected PDO $dbc;

	protected int $fetchMode;

	/**
	 *	Constructor.
	 *	@param		PDO		$dbc		Database connection, preferably a PDO\Connection (for logging and nested transactions)
	 */
	public function __construct( PDO $dbc )
	{
		$this->dbc			= $dbc;
		$this->fetchMode	= static::$defaultFetchMode;
	}

	/**
	 *	Returns new delete query.
	 *	@param		Table|string|NULL	$table		Table to delete from
	 *	@return		Delete
	 */
	public function delete( Table|string|NULL $table = NULL ): Delete
	{
		$query	= new Delete( $this );
		return NULL !== $table ? $query->from( $table ) : $query;
	}

	public function getConnection(): PDO
	{
		return $this->dbc;
	}

	public function getFetchMode(): int
	{
		return $this->fetchMode;
	}

	/**
	 *	Returns new insert query.
	 *	@param		Table|string|NULL	$table		Table to insert into
	 *	@return		Insert
	 */
	public function insert( Table|string|NULL $table = NULL ): Insert
	{
		$query	= new Insert( $this );
		return NULL !== $table ? $query->into( $table ) : $query;
	}

	/**
	 *	Prepares and executes given query and returns statement.
	 *	Notes statement and parameters on query, also if execution fails.
	 *	Used by the queries - call execute() or the fetch methods of a query instead.
	 *	@param		QueryInterface		$query
	 *	@return		PDOStatement
	 *	@throws		SqlException		if execution fails and connection does not throw exceptions itself
	 */
	public function run( QueryInterface $query ): PDOStatement
	{
		$parts	= $query->render();
		if( property_exists( $query, 'statement' ) && property_exists( $query, 'parameters' ) ){
			$query->statement	= $parts->query;
			$query->parameters	= $parts->parameters;
		}
		$statement	= $this->dbc->prepare( $parts->query );
		foreach( array_values( $parts->parameters ) as $index => $value ){
			$type	= match( TRUE ){
				is_int( $value )	=> PDO::PARAM_INT,
				NULL === $value		=> PDO::PARAM_NULL,
				default				=> PDO::PARAM_STR,
			};
			$statement->bindValue( $index + 1, $value, $type );
		}
		if( !$statement->execute() ){
			$info	= $statement->errorInfo();
			throw new SqlException( (string) ( $info[2] ?? 'Execution failed' ), $info[1] ?? 0, $info[0] ?? NULL );
		}
		return $statement;
	}

	/**
	 *	Returns new select query.
	 *	@param		Table|string|NULL	$table		Table to select from, may contain an alias, eg. "orders AS o"
	 *	@return		Select
	 */
	public function select( Table|string|NULL $table = NULL ): Select
	{
		$query	= new Select( $this );
		return NULL !== $table ? $query->from( $table ) : $query;
	}

	/**
	 *	Sets default fetch mode for select queries of this client.
	 *	@param		int		$mode		PDO fetch mode, eg. PDO::FETCH_ASSOC
	 *	@return		self
	 */
	public function setFetchMode( int $mode ): self
	{
		$this->fetchMode	= $mode;
		return $this;
	}

	/**
	 *	Runs given callback within a transaction and returns its result.
	 *	Commits, if the callback returns, and rolls back and rethrows, if it throws.
	 *	Uses nestable transactions of PDO\Connection, if given.
	 *	@template	T
	 *	@param		callable(self): T	$callback		Callback to run, gets this client as argument
	 *	@return		T
	 */
	public function transaction( callable $callback ): mixed
	{
		if( $this->dbc instanceof Connection )
			return $this->dbc->transaction( fn() => $callback( $this ) );
		$this->dbc->beginTransaction();
		try{
			$result	= $callback( $this );
		}
		catch( Throwable $e ){
			$this->dbc->rollBack();
			throw $e;
		}
		$this->dbc->commit();
		return $result;
	}

	/**
	 *	Returns new update query.
	 *	@param		Table|string|NULL	$table		Table to update
	 *	@return		Update
	 */
	public function update( Table|string|NULL $table = NULL ): Update
	{
		$query	= new Update( $this );
		return NULL !== $table ? $query->table( $table ) : $query;
	}
}
