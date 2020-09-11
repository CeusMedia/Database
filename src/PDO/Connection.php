<?php
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

use Exception_SQL;
use PDO;
use PDOException;
use PDOStatement;

/**
 *	Enhanced PDO Connection.
 *	@category		Library
 *	@package		CeusMedia_Database_PDO
 *	@uses			Exception_SQL
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2007-2020 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 *	@todo			Code Documentation
 */
class Connection extends PDO
{
	protected $driver				= NULL;

	public $numberExecutes			= 0;

	public $numberStatements		= 0;

	//  eg. logs/db/pdo/error.log
	public $logFileErrors			= NULL;

	//  eg. logs/db/pdo/query.log
	public $logFileStatements		= NULL;

	protected $openTransactions		= 0;

	public $lastQuery				= NULL;

	//  Flag: inner (nested) Transaction has failed
	protected $innerTransactionFail	= FALSE;

	public static $errorTemplate	= "{time}: PDO:{pdoCode} SQL:{sqlCode} {sqlError} ({statement})\n";

	public static $defaultOptions	= array(
		PDO::ATTR_ERRMODE   => PDO::ERRMODE_EXCEPTION,
	);

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
	public function __construct( string $dsn, ?string $username = NULL, ?string $password = NULL, array $driverOptions = array() )
	{
		//  extend given options by default options
		$options	= $driverOptions + self::$defaultOptions;
		parent::__construct( $dsn, $username, $password, $options );
		//  note name of used driver
		$this->driver	= $this->getAttribute( PDO::ATTR_DRIVER_NAME );
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
		if( !$this->openTransactions )
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
	 *	Executes a Statement and returns Number of affected Rows.
	 *	@access		public
	 *	@param		mixed		$statement			SQL Statement to execute
	 *	@return		integer
	 */
	public function exec( $statement )
	{
        $affectedRows   = 0;
		$this->logStatement( $statement );
		try{
			$this->numberExecutes++;
			$this->numberStatements++;
			$affectedRows = parent::exec( $statement );
		}
		catch( PDOException $e ){
			//  logs Error and throws SQL Exception
			$this->logError( $e, $statement );
		}
		return $affectedRows;
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
	public static function getInstance( string $dsn, ?string $username = NULL, ?string $password = NULL, array $driverOptions = array() ): self
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
		return parent::query( $query )->fetchAll( PDO::FETCH_COLUMN );
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
		if( !$this->logFileErrors )
			return;
//			throw $exception;
		$info		= $exception->errorInfo;
		$sqlError	= isset( $info[2] ) ? $info[2] : NULL;
		$sqlCode	= $info[1];
		$pdoCode	= $info[0];
		$message	= $exception->getMessage();
		$statement	= preg_replace( "@\r?\n@", " ", $statement );
		$statement	= preg_replace( "@  +@", " ", $statement );

		$note	= self::$errorTemplate;
		$note	= str_replace( "{time}", (string) time(), $note );
		$note	= str_replace( "{sqlError}", $sqlError, $note );
		$note	= str_replace( "{sqlCode}", $sqlCode, $note );
		$note	= str_replace( "{pdoCode}", $pdoCode, $note );
		$note	= str_replace( "{message}", $message, $note );
		$note	= str_replace( "{statement}", $statement, $note );

		error_log( $note, 3, $this->logFileErrors );
		throw new Exception_SQL( $sqlError, $sqlCode, $pdoCode );
	}

	/**
	 *	Notes a SQL Statement in Statement Log File.
	 *	@access		protected
	 *	@param		string		$statement		SQL Statement
	 *	@return		void
	 */
	protected function logStatement( string $statement )
	{
		if( !$this->logFileStatements )
			return;
		$statement	= preg_replace( "@(\r)?\n@", " ", $statement );
		$message	= time()." ".getenv( 'REMOTE_ADDR' )." ".$statement."\n";
		error_log( $message, 3, $this->logFileStatements);
	}

	public function prepare( $statement, $driverOptions = array() ): PDOStatement
	{
		$this->numberStatements++;
		$this->logStatement( $statement );
		return parent::prepare( $statement, $driverOptions );
	}

	public function query( string $statement, int $fetchMode = PDO::FETCH_ASSOC )
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

	/**
	 *	Rolls back a Transaction.
	 *	@access		public
	 *	@return		boolean
	 */
	public function rollBack(): bool
	{
		//  there has been an inner RollBack or no Transaction was opened
		if( !$this->openTransactions )
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
