<?php /** @noinspection PhpMultipleClassDeclarationsInspection */
/** @noinspection PhpUnused */

/**
 *	Enhanced PDO Connection.
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

use CeusMedia\Common\Exception\SQL as SqlException;

use PDO;
use PDOException;
use PDOStatement;

/**
 *	Enhanced PDO Connection.
 *	@category		Library
 *	@package		CeusMedia_Database_PDO
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2007-2020 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 *	@todo			Code Documentation
 */
class ConnectionBase extends PDO
{
	/**	@var	string|NULL					$driver					PDO driver */
	protected ?string $driver				= NULL;

	/**	@var	integer						$numberExecutes			Number of execute calls */
	public int $numberExecutes				= 0;

	/**	@var	integer						$numberStatements		Number of executed statements */
	public int $numberStatements			= 0;

	/** @var	?string						$logFileErrors			Path of error log file, eg. logs/db/pdo/error.log */
	public ?string $logFileErrors			= NULL;

	/** @var	?string						$logFileStatements		Path of statement log file, eg. logs/db/pdo/query.log */
	public ?string $logFileStatements		= NULL;

	/**	@var	integer						$openTransactions		Number of opened nested transactions */
	protected int $openTransactions			= 0;

	/** @var	?string						$lastQuery				Latest executed query */
	public ?string $lastQuery				= NULL;

	/** @var	boolean						$innerTransactionFail	Flag: inner (nested) Transaction has failed */
	protected bool $innerTransactionFail	= FALSE;

	/** @var	string						$errorTemplate			Template for error log */
	public static string $errorTemplate		= "{time}: PDO:{pdoCode} SQL:{sqlCode} {sqlError} ({statement})\n";

	/** @var	array						$defaultOptions			Map of default options */
	public static array $defaultOptions		= [
		PDO::ATTR_ERRMODE   => PDO::ERRMODE_EXCEPTION,
	];

	/**
	 *	Constructor, establishes Database Connection using a DSN. Set Error Handling to use Exceptions.
	 *	@access		public
	 *	@param		string		$dsn			Data Source Name
	 *	@param		?string		$username		Name of Database User
	 *	@param		?string		$password		Password of Database User
	 *	@param		array		$driverOptions	Array of Driver Options
	 *	@return		void
	 *	@see		http://php.net/manual/en/pdo.drivers.php
	 */
	public function __construct( string $dsn, ?string $username = NULL, ?string $password = NULL, array $driverOptions = [] )
	{
		//  extend given options by default options
		$options	= $driverOptions + self::$defaultOptions;
		parent::__construct( $dsn, $username, $password, $options );
		//  note name of used driver
		/** @var string|NULL $defaultDriver */
		$defaultDriver	= $this->getAttribute( PDO::ATTR_DRIVER_NAME );
		if( $defaultDriver !== NULL )
			$this->driver	= $defaultDriver;
	}

/*	for PHP 5.3.6+
	public function __destruct(){
		if( $this->openTransactions )
			throw new Exception_Runtime( 'Transaction not closed' );
	}*/

	/**
	 *	Starts a Transaction.
	 *	@access		public
	 *	@return		bool
	 */
	public function beginTransaction(): bool
	{
		//  increase Transaction Counter
		$this->openTransactions++;
		//  no Transaction is open
		if( $this->openTransactions == 1 )
			//  begin Transaction
			parent::beginTransaction();
		return TRUE;
	}

	/**
	 *	Commits a Transaction.
	 *	@access		public
	 *	@return		bool
	 */
	public function commit(): bool
	{
		//  there has been an inner RollBack or no Transaction was opened
		if( $this->openTransactions === 0 )
			//  ignore Commit
			return FALSE;
		//  commit of an outer Transaction
		if( $this->openTransactions == 1 ){
			//  remember about failed inner Transaction
			if( $this->innerTransactionFail ){
				//  rollback outer Transaction instead of committing
				$this->rollBack();
//				throw new RuntimeException( 'Commit failed due to a nested transaction failed' );
				//  indicated that the Transaction has failed
				return FALSE;
			}
			//  no failed inner Transaction
			else
				//  commit Transaction
				parent::commit();
		}
		//  decrease Transaction Counter
		$this->openTransactions--;
		return TRUE;
	}

	/**
	 *	Returns PDO driver used for connection, detected only if the DSN was given as object.
	 *	@access		public
	 *	@return		string|NULL		Database Driver (dblib|firebird|informix|mysql|mssql|oci|odbc|pgsql|sqlite|sybase)
	 */
	public function getDriver(): ?string
	{
		return $this->driver;
	}

	/**
	 *	Static constructor, establishes Database Connection using a DSN. Set Error Handling to use Exceptions.
	 *	@access		public
	 *	@param		string		$dsn			Data Source Name
	 *	@param		?string		$username		Name of Database User
	 *	@param		?string		$password		Password of Database User
	 *	@param		array		$driverOptions	Array of Driver Options
	 *	@return		self
	 *	@see		http://php.net/manual/en/pdo.drivers.php
	 */
	public static function getInstance( string $dsn, ?string $username = NULL, ?string $password = NULL, array $driverOptions = [] ): self
	{
		return new self( $dsn, $username, $password, $driverOptions );
	}

	public function getOpenTransactions(): int
	{
		return $this->openTransactions;
	}

	/**
	 *	Returns list of tables in database.
	 *	With given prefix the returned list will be filtered.
	 *	@access		public
	 *	@param		?string		$prefix		Table prefix to filter by (optional).
	 *	@return		array
	 */
	public function getTables( ?string $prefix = NULL ): array
	{
		$query	= "SHOW TABLES" . ( !is_null( $prefix ) ? " LIKE '".$prefix."%'" : "" );
		$result	= parent::query( $query );
		if( $result === FALSE )
			return [];
		$tables	= $result->fetchAll( PDO::FETCH_COLUMN );
		if( $tables === FALSE )
			return [];
		return $tables;
	}

	/**
	 *	Notes Information from PDO Exception in Error Log File and throw SQL Exception.
	 *	@access		protected
	 *	@param		PDOException	$exception		PDO Exception thrown by invalid SQL Statement
	 *	@param		string			$statement		SQL Statement which originated PDO Exception
	 *	@return		void
	 */
	protected function logError( PDOException $exception, string $statement )
	{
		if( $this->logFileErrors === NULL )
			return;
//			throw $exception;

		$info		= $exception->errorInfo;
		$sqlError	= $info[2] ?? '';
		$sqlCode	= $info[1];
		$pdoCode	= $info[0];
		$message	= $exception->getMessage();
		/** @var string $statement */
		$statement	= preg_replace( "@\r?\n@", " ", $statement );
		/** @var string $statement */
		$statement	= preg_replace( "@  +@", " ", $statement );

		$note	= self::$errorTemplate;
		$note	= str_replace( "{time}", (string) time(), $note );
		$note	= str_replace( "{sqlError}", $sqlError, $note );
		$note	= str_replace( "{sqlCode}", $sqlCode, $note );
		$note	= str_replace( "{pdoCode}", $pdoCode, $note );
		$note	= str_replace( "{message}", $message, $note );
		$note	= str_replace( "{statement}", $statement, $note );

		error_log( $note, 3, $this->logFileErrors );
		throw new SqlException( $message, $sqlCode, $pdoCode );
	}

	/**
	 *	Notes a SQL Statement in Statement Log File.
	 *	@access		protected
	 *	@param		string		$statement		SQL Statement
	 *	@return		void
	 */
	protected function logStatement( string $statement )
	{
		if( $this->logFileStatements === NULL )
			return;
		$statement	= preg_replace( "@(\r)?\n@", " ", $statement );
		$message	= time()." ".getenv( 'REMOTE_ADDR' )." ".$statement."\n";
		error_log( $message, 3, $this->logFileStatements);
	}

	/**
	 *	Prepare statement.
	 *	@access		public
	 *	@param		string		$statement			SQL Statement to prepare
	 *	@param		array		$options		Map of additional driver options
	 *	@return		PDOStatement
	 */
	public function prepare( $statement, $options = NULL ): PDOStatement
	{
		$this->numberStatements++;
		$this->logStatement( $statement );
		return parent::prepare( $statement, $options ?? [] );
	}

	/**
	 *	Rolls back a Transaction.
	 *	@access		public
	 *	@return		boolean
	 */
	public function rollBack(): bool
	{
		//  there has been an inner RollBack or no Transaction was opened
		if( $this->openTransactions === 0 )
			//  ignore Commit
			return FALSE;
		//  only 1 Transaction open
		if( $this->openTransactions == 1 ){
			//  roll back Transaction
			parent::rollBack();
			//  forget about failed inner Transactions
			$this->innerTransactionFail	= FALSE;
		}
		else
			//  note about failed inner Transactions
			$this->innerTransactionFail	= TRUE;
		//  decrease Transaction Counter
		$this->openTransactions--;
		return TRUE;
	}

	/**
	 *	Sets File Name of Error Log.
	 *	@access		public
	 *	@param		string		$fileName		File Name of Statement Error File
	 *	@return		self
	 */
	public function setErrorLogFile( string $fileName ): self
	{
		$this->logFileErrors	= $fileName;
		if( strlen( trim( $fileName ) ) > 0 && !file_exists( dirname( $fileName ) ) )
			mkdir( dirname( $fileName ), 0700, TRUE );
		return $this;
	}

	/**
	 *	Sets File Name of Statement Log.
	 *	@access		public
	 *	@param		string		$fileName		File Name of Statement Log File
	 *	@return		self
	 */
	public function setStatementLogFile( string $fileName ): self
	{
		$this->logFileStatements	= $fileName;
		if( strlen( trim( $fileName ) ) > 0 && !file_exists( dirname( $fileName ) ) )
			mkdir( dirname( $fileName ), 0700, TRUE );
		return $this;
	}
}


if( version_compare( PHP_VERSION, '8.1.0', '>=' ) ){
	class ConnectionPHP81 extends ConnectionBase
	{
		/**
		 *	Wrapper for PDO::exec to support lazy connection mode.
		 *	Tries to connect database if not connected yet (lazy mode).
		 *	@access		public
		 *	@param		string		$statement		SQL statement to execute
		 *	@return		integer		Number of affected rows
		 */
		public function exec( string $statement ): int
		{
			$affectedRows   = 0;
			$this->logStatement( $statement );
			try{
				$this->numberExecutes++;
				$this->numberStatements++;
				$result	= parent::exec( $statement );
				if( $result !== FALSE )
					$affectedRows = $result;
			}
			catch( PDOException $e ){
				//  logs Error and throws SQL Exception
				$this->logError( $e, $statement );
			}
			return $affectedRows;
		}

		/**
		 *	Wrapper for PDO::query to support lazy connection mode.
		 *	Tries to connect database if not connected yet (lazy mode).
		 *	@access		public
		 *	@param		string		$statement		SQL statement to query
		 *	@param		integer		$fetchMode		... (default: 2)
		 *	@return		PDOStatement				PDO statement containing fetchable results
		 */
		public function query( string $statement, ?int $fetchMode = null, mixed ...$fetchModeArgs ): PDOStatement|false
		{
			$this->logStatement( $statement );
			$this->lastQuery	= $statement;
			$this->numberStatements++;
			try{
				return parent::query( $statement, $fetchMode );
			}
			catch( PDOException $e ){
				//  logs Error and throws SQL Exception
				$this->logError( $e, $statement );
			}
			return FALSE;
		}
	}
	class Connection extends ConnectionPHP81 {}
}
else if( version_compare( PHP_VERSION, '8.0.0', '>=' ) ){
	class ConnectionPHP80 extends ConnectionBase
	{
		/**
		 *	Wrapper for PDO::exec to support lazy connection mode.
		 *	Tries to connect database if not connected yet (lazy mode).
		 *	@access		public
		 *	@param		string		$statement		SQL statement to execute
		 *	@return		integer		Number of affected rows
		 */
		public function exec( string $statement ): int
		{
			$affectedRows   = 0;
			$this->logStatement( $statement );
			try{
				$this->numberExecutes++;
				$this->numberStatements++;
				$result	= parent::exec( $statement );
				if( $result !== FALSE )
					$affectedRows = $result;
			}
			catch( PDOException $e ){
				//  logs Error and throws SQL Exception
				$this->logError( $e, $statement );
			}
			return $affectedRows;
		}

		/**
		 *	Wrapper for PDO::query to support lazy connection mode.
		 *	Tries to connect database if not connected yet (lazy mode).
		 *	@access		public
		 *	@param		string		$statement		SQL statement to query
		 *	@param		integer		$fetchMode		... (default: 2)
		 *	@return		PDOStatement|FALSE			PDO statement containing fetchable results
		 */
		public function query( string $statement, int $fetchMode = 2 )
		{
			$this->logStatement( $statement );
			$this->lastQuery	= $statement;
			$this->numberStatements++;
			try{
				return parent::query( $statement, $fetchMode );
			}
			catch( PDOException $e ){
				//  logs Error and throws SQL Exception
				$this->logError( $e, $statement );
			}
			return FALSE;
		}
	}
	class Connection extends ConnectionPHP80 {}
}
